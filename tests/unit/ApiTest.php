<?php
declare(strict_types=1);

namespace Frenet\Tests;

use Frenet\Api;
use Frenet\Http\Client;
use Frenet\Http\Exception as HttpException;
use Frenet\Http\Response;
use Frenet\Log;
use Frenet\Module;
use Frenet\Quote\Cache;
use Frenet\Quote\Item;
use Frenet\Quote\Request;
use Frenet\Quote\UnavailableException;
use Opencart\System\Library\Cache as CoreCache;
use Opencart\System\Library\Log as CoreLog;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TypeError;
use ValueError;

final class ApiTest extends TestCase {
	private const QUOTE = '{"ShippingSevicesArray":[{"ServiceCode":"03220","ServiceDescription":"SEDEX","ShippingPrice":"42.50","DeliveryTime":"3"}]}';

	private CoreLog $writer;

	private CoreCache $store;

	protected function setUp(): void {
		$this->writer = new CoreLog(Log::FILENAME);
		$this->store = new CoreCache();
	}

	public function testPostsTheRequestAsJsonOverHttpsWithTheTokenAndTheVersions(): void {
		$client = new FakeClient([new Response(200, self::QUOTE)]);

		$this->createApi($client, 'abc123', 7, 3)->quote($this->createRequest());

		self::assertCount(1, $client->sent);
		self::assertSame('https://api.frenet.com.br/shipping/quote', $client->sent[0]['url']);
		self::assertSame(json_encode($this->createRequest()->toArray()), $client->sent[0]['body']);
		self::assertSame([
			'Accept'       => 'application/json',
			'Content-Type' => 'application/json',
			'User-Agent'   => 'Frenet-OpenCart4/' . Module::VERSION . ' (OpenCart 4.1.0.4)',
			'token'        => 'abc123'
		], $client->sent[0]['headers']);
		self::assertSame(7, $client->sent[0]['timeout']);
	}

	public function testRetriesATimeoutAndAServerError(): void {
		$client = new FakeClient([new HttpException('Operation timed out', CURLE_OPERATION_TIMEDOUT), new Response(503, '{"Message":"Busy"}'), new Response(200, self::QUOTE)]);

		$result = $this->createApi($client, 'abc123', 5, 3)->quote($this->createRequest());

		self::assertCount(3, $client->sent);
		self::assertCount(1, $result->getServices());
		self::assertSame(['ERROR Quote attempt 1/3 failed: Operation timed out', 'ERROR Quote attempt 2/3 failed: HTTP 503: Busy'], $this->writer->lines);
	}

	public function testRetriesAnAnswerThatIsNotJson(): void {
		$client = new FakeClient([new Response(200, '<html>Bad gateway</html>'), new Response(200, self::QUOTE)]);

		$result = $this->createApi($client, 'abc123', 5, 3)->quote($this->createRequest());

		self::assertCount(2, $client->sent);
		self::assertCount(1, $result->getServices());
		self::assertSame(['ERROR Quote attempt 1/3 failed: The Frenet API answered with invalid JSON'], $this->writer->lines);
	}

	public function testReturnsTheServicesOnTheAttemptThatWorks(): void {
		$client = new FakeClient([new Response(503, ''), new Response(200, self::QUOTE)]);

		$result = $this->createApi($client, 'abc123', 5, 3)->quote($this->createRequest());

		self::assertCount(2, $client->sent);
		self::assertSame('03220', $result->getServices()[0]->getCode());
		self::assertSame(['ERROR Quote attempt 1/3 failed: HTTP 503'], $this->writer->lines);
	}

	public function testGivesUpAfterTheLastAttemptWithTheLastError(): void {
		$client = new FakeClient([new HttpException('Connection refused', CURLE_COULDNT_CONNECT), new Response(502, '{"Message":"Bad gateway"}')]);

		try {
			$this->createApi($client, 'abc123', 5, 2)->quote($this->createRequest());
			self::fail('Expected the quote to be unavailable');
		} catch (UnavailableException $exception) {
			self::assertSame('The Frenet API gave no usable answer after 2 attempt(s). Last error: HTTP 502: Bad gateway', $exception->getMessage());
		}

		self::assertCount(2, $client->sent);
		self::assertSame(['ERROR Quote attempt 1/2 failed: Connection refused', 'ERROR Quote attempt 2/2 failed: HTTP 502: Bad gateway'], $this->writer->lines);
	}

	public function testNeverRetriesARefusedQuote(): void {
		$client = new FakeClient([new Response(401, '{"Message":"Authorization has been denied for this request."}'), new Response(200, self::QUOTE)]);

		try {
			$this->createApi($client, 'invalid', 5, 3)->quote($this->createRequest());
			self::fail('Expected the quote to be refused');
		} catch (UnavailableException $exception) {
			self::assertSame(401, $exception->getCode());
			self::assertSame('The Frenet API refused the quote with HTTP 401: Authorization has been denied for this request. (not retried)', $exception->getMessage());
		}

		self::assertCount(1, $client->sent);
	}

	public function testSendsAtLeastOneAttempt(): void {
		$client = new FakeClient([new Response(503, '')]);

		$this->expectException(UnavailableException::class);

		try {
			$this->createApi($client, 'abc123', 5, 0)->quote($this->createRequest());
		} finally {
			self::assertCount(1, $client->sent);
		}
	}

	public function testLogsTheRequestAndTheResponseWithoutTheTokenWhileDebugIsEnabled(): void {
		$client = new FakeClient([new Response(200, self::QUOTE)]);

		(new Api($client, new Cache($this->store), new Log($this->writer, true), 'abc123', '4.1.0.4', 5, 3))->quote($this->createRequest());

		self::assertCount(2, $this->writer->lines);
		self::assertStringStartsWith('DEBUG Quote request {"url":"https://api.frenet.com.br/shipping/quote"', $this->writer->lines[0]);
		self::assertStringContainsString('"token":"********"', $this->writer->lines[0]);
		self::assertStringStartsWith('DEBUG Quote response {"attempt":1,"status":200', $this->writer->lines[1]);
		self::assertStringNotContainsString('abc123', implode("\n", $this->writer->lines));
	}

	public function testQuotesTheServicesOfTheMock(): void {
		$result = $this->createApi(new Client(), 'abc123', 5, 1, getenv('FRENET_MOCK_URL') . '/shipping/quote')->quote($this->createRequest());

		self::assertCount(2, $result->getServices());
		self::assertSame('SEDEX', $result->getServices()[1]->getDescription());
	}

	public function testRetriesTheFlakyMockUntilItAnswers(): void {
		@unlink(sys_get_temp_dir() . '/frenet-mock-flaky');

		$result = $this->createApi(new Client(), 'flaky', 5, 2, getenv('FRENET_MOCK_URL') . '/shipping/quote')->quote($this->createRequest());

		self::assertCount(2, $result->getServices());
		self::assertSame(['ERROR Quote attempt 1/2 failed: HTTP 503: Mock failing every other quote'], $this->writer->lines);
	}

	public function testReportsTheMessageOfAQuoteWithoutServices(): void {
		$result = $this->createApi(new Client(), 'empty', 5, 1, getenv('FRENET_MOCK_URL') . '/shipping/quote')->quote($this->createRequest());

		self::assertSame([], $result->getServices());
		self::assertSame('Nenhuma transportadora disponível para o CEP informado', $result->getMessage());
	}

	public function testServesARepeatedQuoteFromTheCacheWithoutCallingTheApi(): void {
		$client = new FakeClient([new Response(200, self::QUOTE)]);
		$api = $this->createApi($client, 'abc123', 5, 3);

		$api->quote($this->createRequest());
		$result = $api->quote($this->createRequest());

		self::assertCount(1, $client->sent);
		self::assertSame('SEDEX', $result->getServices()[0]->getDescription());
		self::assertSame(42.5, $result->getServices()[0]->getPrice());
	}

	#[DataProvider('provideQuotesAgainWhenTheCartOrThePostcodeChangesCases')]
	public function testQuotesAgainWhenTheCartOrThePostcodeChanges(Request $changed): void {
		$client = new FakeClient([new Response(200, self::QUOTE), new Response(200, self::QUOTE)]);
		$api = $this->createApi($client, 'abc123', 5, 3);

		$api->quote($this->createRequest());
		$api->quote($changed);

		self::assertCount(2, $client->sent);
		self::assertSame(json_encode($changed->toArray()), $client->sent[1]['body']);
	}

	/**
	 * Requests that differ from createRequest() in one field each.
	 *
	 * @return iterable<string, array{Request}>
	 */
	public static function provideQuotesAgainWhenTheCartOrThePostcodeChangesCases(): iterable {
		yield 'another postcode' => [new Request('01310100', '01310100', 'BR', 100.0, '', [new Item(1.0, 16.0, 2.0, 11.0, 1, 'product 11', '')], '4.1.0.4')];
		yield 'another quantity' => [new Request('01310100', '20040020', 'BR', 200.0, '', [new Item(1.0, 16.0, 2.0, 11.0, 2, 'product 11', '')], '4.1.0.4')];
		yield 'another product' => [new Request('01310100', '20040020', 'BR', 100.0, '', [new Item(1.0, 16.0, 2.0, 11.0, 1, 'product 12', '')], '4.1.0.4')];
		yield 'another coupon' => [new Request('01310100', '20040020', 'BR', 100.0, 'FRETE10', [new Item(1.0, 16.0, 2.0, 11.0, 1, 'product 11', '')], '4.1.0.4')];
	}

	public function testQuotesAgainWithAnotherToken(): void {
		$client = new FakeClient([new Response(200, self::QUOTE), new Response(200, self::QUOTE)]);

		$this->createApi($client, 'abc123', 5, 3)->quote($this->createRequest());
		$this->createApi($client, 'xyz789', 5, 3)->quote($this->createRequest());

		self::assertCount(2, $client->sent);
	}

	#[DataProvider('provideQuotesAgainAfterAFailureCases')]
	public function testQuotesAgainAfterAFailure(HttpException|Response $failure): void {
		$client = new FakeClient([$failure, new Response(200, self::QUOTE)]);
		$api = $this->createApi($client, 'abc123', 5, 1);

		try {
			$api->quote($this->createRequest());
			self::fail('Expected the quote to be unavailable');
		} catch (UnavailableException $exception) {
			self::assertCount(1, $client->sent);
		}

		$result = $api->quote($this->createRequest());

		self::assertCount(2, $client->sent);
		self::assertCount(1, $result->getServices());
	}

	/**
	 * Failures of the API that must never be cached.
	 *
	 * @return iterable<string, array{HttpException|Response}>
	 */
	public static function provideQuotesAgainAfterAFailureCases(): iterable {
		yield 'timeout' => [new HttpException('Operation timed out after 5000 milliseconds', CURLE_OPERATION_TIMEDOUT)];
		yield 'server error' => [new Response(503, '{"Message":"Service Unavailable"}')];
		yield 'client error' => [new Response(401, '{"Message":"Acesso negado!"}')];
		yield 'invalid JSON' => [new Response(200, '<html>Bad gateway</html>')];
	}

	public function testQuotesAgainAfterAnAnswerWithoutServices(): void {
		$client = new FakeClient([new Response(200, '{"ShippingSevicesArray":[],"Message":"Nenhuma transportadora disponível"}'), new Response(200, self::QUOTE)]);
		$api = $this->createApi($client, 'abc123', 5, 3);

		$empty = $api->quote($this->createRequest());
		$result = $api->quote($this->createRequest());

		self::assertSame([], $empty->getServices());
		self::assertCount(2, $client->sent);
		self::assertCount(1, $result->getServices());
	}

	public function testCachesAnAnswerWithSomeServicesInError(): void {
		$body = '{"ShippingSevicesArray":[{"ServiceCode":"03220","ServiceDescription":"SEDEX","ShippingPrice":"42.50","DeliveryTime":"3"},{"ServiceCode":"04227","ServiceDescription":"Mini Envios","Error":true,"Msg":"Medidas excedidas"}]}';
		$client = new FakeClient([new Response(200, $body)]);
		$api = $this->createApi($client, 'abc123', 5, 3);

		$api->quote($this->createRequest());
		$result = $api->quote($this->createRequest());

		self::assertCount(1, $client->sent);
		self::assertCount(1, $result->getServices());
		self::assertSame('Medidas excedidas', $result->getErrors()[0]['message']);
	}

	public function testQuotesAgainAfterTheCacheExpires(): void {
		$client = new FakeClient([new Response(200, self::QUOTE), new Response(200, self::QUOTE)]);
		$api = $this->createApi($client, 'abc123', 5, 3);

		$api->quote($this->createRequest());

		foreach (array_keys($this->store->entries) as $key) {
			$this->store->entries[$key] = ['expires' => time(), 'body' => self::QUOTE];
		}

		$api->quote($this->createRequest());

		self::assertCount(2, $client->sent);
	}

	public function testQuotesTheApiWhenTheCacheCannotBeRead(): void {
		$this->store = new class extends CoreCache {
			public function get(string $key): mixed {
				throw new ValueError('fread(): Argument #2 ($length) must be greater than 0');
			}
		};
		$client = new FakeClient([new Response(200, self::QUOTE)]);

		$result = $this->createApi($client, 'abc123', 5, 3)->quote($this->createRequest());

		self::assertCount(1, $client->sent);
		self::assertCount(1, $result->getServices());
		self::assertSame(['ERROR Quote cache could not be read, asking the Frenet API: fread(): Argument #2 ($length) must be greater than 0'], $this->writer->lines);
	}

	public function testQuotesTheApiWhenTheCachedAnswerCannotBeParsed(): void {
		$client = new FakeClient([new Response(200, self::QUOTE)]);
		$api = $this->createApi($client, 'abc123', 5, 3);
		$this->store->set((new Cache($this->store))->getKey('abc123', (string)json_encode($this->createRequest()->toArray())), ['expires' => time() + 60, 'body' => '<html>Bad gateway</html>']);

		$result = $api->quote($this->createRequest());

		self::assertCount(1, $client->sent);
		self::assertCount(1, $result->getServices());
		self::assertSame(['ERROR Quote cache could not be read, asking the Frenet API: The Frenet API answered with invalid JSON'], $this->writer->lines);
	}

	public function testReturnsTheQuoteWhenTheCacheCannotBeWritten(): void {
		$this->store = new class extends CoreCache {
			public function set(string $key, mixed $value, int $expire = 0): void {
				throw new TypeError('flock(): Argument #1 ($stream) must be of type resource, bool given');
			}
		};
		$client = new FakeClient([new Response(200, self::QUOTE)]);

		$result = $this->createApi($client, 'abc123', 5, 3)->quote($this->createRequest());

		self::assertCount(1, $result->getServices());
		self::assertSame(['ERROR Quote cache could not be written: flock(): Argument #1 ($stream) must be of type resource, bool given'], $this->writer->lines);
	}

	public function testLogsACachedQuoteWithoutTheTokenWhileDebugIsEnabled(): void {
		$client = new FakeClient([new Response(200, self::QUOTE)]);
		$api = new Api($client, new Cache($this->store), new Log($this->writer, true), 'abc123', '4.1.0.4', 5, 3);

		$api->quote($this->createRequest());
		$api->quote($this->createRequest());

		self::assertCount(3, $this->writer->lines);
		self::assertStringStartsWith('DEBUG Quote served from the cache {"key":"frenet.quote.', $this->writer->lines[2]);
		self::assertStringContainsString('"RecipientCEP":"20040020"', $this->writer->lines[2]);
		self::assertStringNotContainsString('abc123', implode("\n", $this->writer->lines));
	}

	/**
	 * Api with the given client and token, logging errors only.
	 *
	 * @param Client $client
	 * @param string $token
	 * @param int    $timeout
	 * @param int    $attempts
	 * @param string $url
	 *
	 * @return Api
	 */
	private function createApi(Client $client, string $token, int $timeout, int $attempts, string $url = Api::QUOTE_URL): Api {
		return new Api($client, new Cache($this->store), new Log($this->writer, false), $token, '4.1.0.4', $timeout, $attempts, $url);
	}

	/**
	 * Request with one item.
	 *
	 * @return Request
	 */
	private function createRequest(): Request {
		return new Request('01310100', '20040020', 'BR', 100.0, '', [new Item(1.0, 16.0, 2.0, 11.0, 1, 'product 11', '')], '4.1.0.4');
	}
}
