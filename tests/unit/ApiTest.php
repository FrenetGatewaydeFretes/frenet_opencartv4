<?php
declare(strict_types=1);

namespace Frenet\Tests;

use Frenet\Api;
use Frenet\Http\Client;
use Frenet\Http\Response;
use Frenet\Module;
use Frenet\Quote\Exception;
use Frenet\Quote\Item;
use Frenet\Quote\Request;
use PHPUnit\Framework\TestCase;

final class ApiTest extends TestCase {
	public function testPostsTheRequestAsJsonOverHttpsWithTheTokenAndTheVersions(): void {
		$client = new class extends Client {
			/**
			 * @var array<string, mixed>
			 */
			public array $sent = [];

			/**
			 * Keeps what would be sent and answers an empty quote.
			 *
			 * @param string                $url
			 * @param string                $body
			 * @param array<string, string> $headers
			 * @param int                   $timeout
			 *
			 * @return Response
			 */
			public function post(string $url, string $body, array $headers, int $timeout): Response {
				$this->sent = ['url' => $url, 'body' => $body, 'headers' => $headers, 'timeout' => $timeout];

				return new Response(200, '{"ShippingSevicesArray":[]}');
			}
		};

		(new Api($client, 'abc123', '4.1.0.4', 7))->quote($this->createRequest());

		self::assertSame('https://api.frenet.com.br/shipping/quote', $client->sent['url']);
		self::assertSame(json_encode($this->createRequest()->toArray()), $client->sent['body']);
		self::assertSame([
			'Accept'       => 'application/json',
			'Content-Type' => 'application/json',
			'User-Agent'   => 'Frenet-OpenCart4/' . Module::VERSION . ' (OpenCart 4.1.0.4)',
			'token'        => 'abc123'
		], $client->sent['headers']);
		self::assertSame(7, $client->sent['timeout']);
	}

	public function testReturnsTheServicesOfTheMock(): void {
		$services = $this->createApi('abc123')->quote($this->createRequest());

		self::assertCount(2, $services);
		self::assertSame('SEDEX', $services[1]->getDescription());
	}

	public function testReturnsTheOnlyServiceOfTheMock(): void {
		$services = $this->createApi('single-service')->quote($this->createRequest());

		self::assertCount(1, $services);
		self::assertSame('03220', $services[0]->getCode());
	}

	public function testReportsTheStatusOfARefusedToken(): void {
		try {
			$this->createApi('')->quote($this->createRequest());
			self::fail('Expected the API to refuse the request');
		} catch (Exception $exception) {
			self::assertSame(401, $exception->getCode());
			self::assertSame('The Frenet API answered with HTTP 401', $exception->getMessage());
		}
	}

	public function testReportsAnErrorAnswer(): void {
		$this->expectException(Exception::class);
		$this->expectExceptionCode(503);

		$this->createApi('status-503')->quote($this->createRequest());
	}

	/**
	 * Api pointed at the Frenet mock with the given token.
	 *
	 * @param string $token
	 *
	 * @return Api
	 */
	private function createApi(string $token): Api {
		return new Api(new Client(), $token, '4.1.0.4', 5, getenv('FRENET_MOCK_URL') . '/shipping/quote');
	}

	/**
	 * Request with one item.
	 *
	 * @return Request
	 */
	private function createRequest(): Request {
		return new Request('01310100', '20040020', 'BR', 100.0, '', [new Item(1.0, 16.0, 2.0, 11.0, 1)], '4.1.0.4');
	}
}
