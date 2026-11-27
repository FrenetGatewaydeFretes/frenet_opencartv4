<?php
namespace Frenet;

use Frenet\Http\Client;
use Frenet\Http\Exception as HttpException;
use Frenet\Http\Response;
use Frenet\Quote\Cache;
use Frenet\Quote\Exception;
use Frenet\Quote\Parser;
use Frenet\Quote\Request;
use Frenet\Quote\Result;
use Frenet\Quote\UnavailableException;
use Throwable;

/**
 * Quotes through the Frenet REST API over HTTPS or the cache, retrying failures within the time limit and logging the reason of each one.
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

	public function __construct(private Client $client, private Cache $cache, private Log $log, private string $token, private string $opencart_version, private int $timeout, private int $attempts, private string $quote_url = self::QUOTE_URL) {}

	/**
	 * Services for the request from the cache or the API, retrying timeouts, 5xx and unreadable answers but never a refusal.
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

		$key = $this->cache->getKey($this->token, $body);
		$cached = $this->readCache($key);

		if ($cached !== null) {
			$this->log->debug('Quote served from the cache', ['key' => $key, 'body' => $payload]);

			return $cached;
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
				$result = (new Parser())->parse($response->getBody());
			} catch (Exception $exception) {
				$error = $exception->getMessage();
				$this->logAttempt($attempt, $attempts, $error);

				continue;
			}

			if ($result->getServices() !== []) {
				$this->writeCache($key, $response->getBody());
			}

			return $result;
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
	 * Cached answer, with any cache failure treated as a miss so the quote still reaches the API.
	 *
	 * @param string $key
	 *
	 * @return Result|null
	 */
	private function readCache(string $key): ?Result {
		try {
			$body = $this->cache->get($key);

			return $body !== null ? (new Parser())->parse($body) : null;
		} catch (Throwable $exception) {
			$this->log->error('Quote cache could not be read, asking the Frenet API: ' . $exception->getMessage());

			return null;
		}
	}

	/**
	 * Stores the answer, logging a cache failure instead of losing a quote the API already answered.
	 *
	 * @param string $key
	 * @param string $body
	 *
	 * @return void
	 */
	private function writeCache(string $key, string $body): void {
		try {
			$this->cache->set($key, $body);
		} catch (Throwable $exception) {
			$this->log->error('Quote cache could not be written: ' . $exception->getMessage());
		}
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
