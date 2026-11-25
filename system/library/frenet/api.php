<?php
namespace Frenet;

use Frenet\Http\Client;
use Frenet\Http\Exception as HttpException;
use Frenet\Quote\Exception;
use Frenet\Quote\Parser;
use Frenet\Quote\Request;
use Frenet\Quote\Service;

/**
 * Quotes through the Frenet REST API over HTTPS, authenticated by the token of the store.
 */
class Api {
	/**
	 * @var string
	 */
	public const QUOTE_URL = 'https://api.frenet.com.br/shipping/quote';

	public function __construct(private Client $client, private string $token, private string $opencart_version, private int $timeout, private string $quote_url = self::QUOTE_URL) {}

	/**
	 * Services the Frenet API offers for the request.
	 *
	 * @param Request $request
	 *
	 * @throws Exception
	 * @throws HttpException
	 *
	 * @return array<int, Service>
	 */
	public function quote(Request $request): array {
		$body = json_encode($request->toArray());

		if ($body === false) {
			throw new Exception('Could not encode the quote request: ' . json_last_error_msg());
		}

		$response = $this->client->post($this->quote_url, $body, $this->getHeaders(), $this->timeout);

		if (!$response->isSuccessful()) {
			throw new Exception('The Frenet API answered with HTTP ' . $response->getStatusCode(), $response->getStatusCode());
		}

		return (new Parser())->parse($response->getBody());
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
}
