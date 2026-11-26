<?php
declare(strict_types=1);

namespace Frenet\Tests;

use Frenet\Http\Client;
use Frenet\Http\Exception as HttpException;
use Frenet\Http\Response;

/**
 * Client that answers from a queue instead of the network and keeps every request it received.
 */
final class FakeClient extends Client {
	/**
	 * @var array<int, array{url: string, body: string, headers: array<string, string>, timeout: int}>
	 */
	public array $sent = [];

	/**
	 * @param array<int, HttpException|Response> $answers
	 */
	public function __construct(private array $answers) {}

	/**
	 * Keeps the request and returns the next answer of the queue, throwing it when it is a transport failure.
	 *
	 * @param string                $url
	 * @param string                $body
	 * @param array<string, string> $headers
	 * @param int                   $timeout
	 *
	 * @throws HttpException
	 *
	 * @return Response
	 */
	public function post(string $url, string $body, array $headers, int $timeout): Response {
		$this->sent[] = ['url' => $url, 'body' => $body, 'headers' => $headers, 'timeout' => $timeout];

		$answer = array_shift($this->answers) ?? new Response(500, '');

		if ($answer instanceof HttpException) {
			throw $answer;
		}

		return $answer;
	}
}
