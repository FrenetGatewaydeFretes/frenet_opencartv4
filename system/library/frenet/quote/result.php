<?php
namespace Frenet\Quote;

/**
 * What the Frenet API answered for a quote: the services that can be offered, the ones left out with an error and the general message.
 */
class Result {
	/**
	 * @param array<int, Service>               $services
	 * @param array<int, array<string, string>> $errors
	 * @param string                            $message
	 */
	public function __construct(private array $services, private array $errors, private string $message) {}

	/**
	 * Services that can be offered at checkout.
	 *
	 * @return array<int, Service>
	 */
	public function getServices(): array {
		return $this->services;
	}

	/**
	 * Services the Frenet API returned with an error, with the code, description, carrier and the Msg of each.
	 *
	 * @return array<int, array<string, string>>
	 */
	public function getErrors(): array {
		return $this->errors;
	}

	/**
	 * General Message of the answer, such as why no carrier serves the postcode.
	 *
	 * @return string
	 */
	public function getMessage(): string {
		return $this->message;
	}
}
