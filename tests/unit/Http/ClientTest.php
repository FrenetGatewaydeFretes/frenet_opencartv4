<?php
declare(strict_types=1);

namespace Frenet\Tests\Http;

use Frenet\Http\Client;
use Frenet\Http\Exception;
use Frenet\Http\Response;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase {
	public function testSendsTheBodyAndHeaders(): void {
		$response = (new Client())->post($this->url('/mock/echo'), '{"a":1}', ['Content-Type' => 'application/json', 'token' => 'abc'], 5);

		$echo = $this->decode($response);

		self::assertSame(200, $response->getStatusCode());
		self::assertSame('POST', $echo['method']);
		self::assertSame('{"a":1}', $echo['body']);
		self::assertIsArray($echo['headers']);
		self::assertSame('application/json', $echo['headers']['content-type']);
		self::assertSame('abc', $echo['headers']['token']);
	}

	public function testReturnsErrorStatusesInsteadOfThrowing(): void {
		$client = new Client();

		self::assertSame(401, $client->post($this->url('/mock/status/401'), '', [], 5)->getStatusCode());
		self::assertSame(503, $client->post($this->url('/mock/status/503'), '', [], 5)->getStatusCode());
	}

	public function testThrowsATimeoutWhenTheApiIsSlowerThanTheLimit(): void {
		try {
			(new Client())->post($this->url('/mock/delay/3'), '', [], 1);
			self::fail('Expected a timeout');
		} catch (Exception $exception) {
			self::assertSame(CURLE_OPERATION_TIMEDOUT, $exception->getCode());
		}
	}

	public function testThrowsAConnectionErrorWhenNothingListens(): void {
		try {
			(new Client())->post('http://127.0.0.1:1/shipping/quote', '', [], 1);
			self::fail('Expected a connection error');
		} catch (Exception $exception) {
			self::assertSame(CURLE_COULDNT_CONNECT, $exception->getCode());
			self::assertNotSame('', $exception->getMessage());
		}
	}

	public function testRefusesProtocolsOtherThanHttpAndHttps(): void {
		try {
			(new Client())->post('file:///etc/hostname', '', [], 1);
			self::fail('Expected the file protocol to be refused');
		} catch (Exception $exception) {
			self::assertSame(CURLE_UNSUPPORTED_PROTOCOL, $exception->getCode());
		}
	}

	public function testReturnsTheRedirectInsteadOfFollowingIt(): void {
		$response = (new Client())->post($this->url('/mock/redirect'), '', [], 5);

		self::assertSame(302, $response->getStatusCode());
		self::assertSame('', $response->getBody());
	}

	public function testAnswersNothingWhenThereIsNothingToSend(): void {
		self::assertSame([], (new Client())->postAll($this->url('/mock/echo'), [], [], 5));
	}

	public function testKeepsEachAnswerOfTheParallelRequestsUnderItsKey(): void {
		$answers = (new Client())->postAll($this->url('/mock/delay/1'), ['full' => '{"n":0}', 1 => '{"n":1}', 2 => '{"n":2}'], [], 5);

		self::assertSame(['full', 1, 2], array_keys($answers));

		foreach ($answers as $answer) {
			self::assertInstanceOf(Response::class, $answer);
			self::assertSame('{"delayed":1}', $answer->getBody());
		}
	}

	public function testKeepsTheBodyOfEachParallelRequest(): void {
		$answers = (new Client())->postAll($this->url('/mock/echo'), ['a' => 'first', 'b' => 'second'], ['token' => 'abc'], 5);

		$first = $this->decode($answers['a']);
		$second = $this->decode($answers['b']);

		self::assertSame('first', $first['body']);
		self::assertSame('second', $second['body']);
		self::assertIsArray($second['headers']);
		self::assertSame('abc', $second['headers']['token']);
	}

	public function testReturnsEachParallelTimeoutAsAnException(): void {
		$answers = (new Client())->postAll($this->url('/mock/delay/3'), ['x' => '', 'y' => ''], [], 1);

		self::assertInstanceOf(Exception::class, $answers['x']);
		self::assertInstanceOf(Exception::class, $answers['y']);
		self::assertSame(CURLE_OPERATION_TIMEDOUT, $answers['x']->getCode());
		self::assertSame(CURLE_OPERATION_TIMEDOUT, $answers['y']->getCode());
	}

	public function testMockQuoteRejectsRequestsWithoutToken(): void {
		$response = (new Client())->post($this->url('/shipping/quote'), '{}', ['Content-Type' => 'application/json'], 5);

		self::assertSame(401, $response->getStatusCode());
	}

	public function testMockQuoteAnswersTooLateForTheTimeLimitWithTheSlowToken(): void {
		try {
			(new Client())->post($this->url('/shipping/quote'), '{}', ['Content-Type' => 'application/json', 'token' => 'slow'], 2);
			self::fail('Expected a timeout');
		} catch (Exception $exception) {
			self::assertSame(CURLE_OPERATION_TIMEDOUT, $exception->getCode());
		}
	}

	public function testMockQuoteAnswersWithServicesWhenTheTokenIsSent(): void {
		$response = (new Client())->post($this->url('/shipping/quote'), '{}', ['Content-Type' => 'application/json', 'token' => 'abc'], 5);

		$quote = $this->decode($response);

		self::assertTrue($response->isSuccessful());
		self::assertIsArray($quote['ShippingSevicesArray']);
		self::assertSame(['03298', '03220'], array_column($quote['ShippingSevicesArray'], 'ServiceCode'));
	}

	/**
	 * Decoded JSON body of an answer that must be an HTTP response.
	 *
	 * @param Exception|Response $answer
	 *
	 * @return array<mixed>
	 */
	private function decode(Exception|Response $answer): array {
		self::assertInstanceOf(Response::class, $answer);

		$body = json_decode($answer->getBody(), true);

		self::assertIsArray($body);

		return $body;
	}

	/**
	 * URL of a path on the Frenet mock.
	 *
	 * @param string $path
	 *
	 * @return string
	 */
	private function url(string $path): string {
		return getenv('FRENET_MOCK_URL') . $path;
	}
}
