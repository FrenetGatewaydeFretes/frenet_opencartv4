<?php
namespace Frenet;

use Frenet\Http\Client;
use Frenet\Http\Exception as HttpException;
use Frenet\Http\Response;
use Frenet\Quote\Exception;
use Frenet\Quote\Parser;
use Frenet\Quote\Request;
use Frenet\Quote\Result;
use Frenet\Quote\UnavailableException;

/**
 * Quotes through the Frenet REST API over HTTPS, retrying failures within the time limit and logging the reason of each one.
 */
class Api {
	/**
	 * @var string
	 */
	public const QUOTE_URL = 'https://api.frenet.com.br/shipping/quote';

	/**
	 * @var int
	 */
	private const RETRY_DELAY = 300000;

	public function __construct(private Client $client, private Log $log, private string $token, private string $opencart_version, private int $timeout, private int $attempts, private string $quote_url = self::QUOTE_URL) {}

	/**
	 * Services the Frenet API offers for the request, retrying a timeout, a connection failure, a 5xx or an unreadable answer; a 4xx other than 408 and 429 is never retried.
	 *
	 * @param Request $request
	 *
	 * @throws UnavailableException
	 *
	 * @return Result
	 */
	public function quote(Request $request): Result {
		$payload = $request->toArray();
		$body = json_encode($payload);

		if ($body === false) {
			throw new UnavailableException('Could not encode the quote request: ' . json_last_error_msg());
		}

		$headers = $this->getHeaders();
		$attempts = max(1, $this->attempts);
		$error = '';

		$this->log->debug('Quote request', ['url' => $this->quote_url, 'headers' => $headers, 'body' => $payload]);

		for ($attempt = 1; $attempt <= $attempts; $attempt++) {
			if ($attempt > 1) {
				usleep(self::RETRY_DELAY);
			}

			try {
				$response = $this->client->post($this->quote_url, $body, $headers, $this->timeout);
			} catch (HttpException $exception) {
				$error = $exception->getMessage();
				$this->logAttempt($attempt, $attempts, $error);

				continue;
			}

			$this->log->debug('Quote response', ['attempt' => $attempt, 'status' => $response->getStatusCode(), 'body' => $response->getBody()]);

			if ($response->isRefused()) {
				throw new UnavailableException('The Frenet API refused the quote with ' . $this->describe($response) . ' (not retried)', $response->getStatusCode());
			}

			if (!$response->isSuccessful()) {
				$error = $this->describe($response);
				$this->logAttempt($attempt, $attempts, $error);

				continue;
			}

			try {
				return (new Parser())->parse($response->getBody());
			} catch (Exception $exception) {
				$error = $exception->getMessage();
				$this->logAttempt($attempt, $attempts, $error);
			}
		}

		throw new UnavailableException('The Frenet API gave no usable answer after ' . $attempts . ' attempt(s). Last error: ' . $error);
	}

	/**
	 * Headers of every quote: JSON, the token and a User-Agent with the module and the OpenCart versions.
	 *
	 * @return array<string, string>
	 */
	private function getHeaders(): array {
		return [
			'Accept'       => 'application/json',
			'Content-Type' => 'application/json',
			'User-Agent'   => 'Frenet-' . Request::PLATFORM_NAME . '/' . Module::VERSION . ' (OpenCart ' . $this->opencart_version . ')',
			'token'        => $this->token
		];
	}

	/**
	 * Records why one attempt failed, with its number among the attempts allowed.
	 *
	 * @param int    $attempt
	 * @param int    $attempts
	 * @param string $error
	 *
	 * @return void
	 */
	private function logAttempt(int $attempt, int $attempts, string $error): void {
		$this->log->error('Quote attempt ' . $attempt . '/' . $attempts . ' failed: ' . $error);
	}

	/**
	 * HTTP status of an answer, with the Message of the Frenet API when it sends one.
	 *
	 * @param Response $response
	 *
	 * @return string
	 */
	private function describe(Response $response): string {
		$data = json_decode($response->getBody(), true);
		$message = is_array($data) && is_scalar($data['Message'] ?? null) ? trim((string)$data['Message']) : '';

		return 'HTTP ' . $response->getStatusCode() . ($message !== '' ? ': ' . $message : '');
	}
}
