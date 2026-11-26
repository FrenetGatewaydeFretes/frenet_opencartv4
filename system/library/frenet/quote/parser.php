<?php
namespace Frenet\Quote;

/**
 * Reads a Frenet API quote, whether the answer lists several services or brings a single one, keeping apart the services with an error.
 */
class Parser {
	/**
	 * Services that can be offered, the services left out with an error and the general message of the answer.
	 *
	 * @param string $body
	 *
	 * @throws Exception
	 *
	 * @return Result
	 */
	public function parse(string $body): Result {
		$data = json_decode($body, true);

		if (!is_array($data)) {
			throw new Exception('The Frenet API answered with invalid JSON');
		}

		$message = $this->getText($data['Message'] ?? null);
		$entries = $data['ShippingSevicesArray'] ?? [];

		if (!is_array($entries)) {
			return new Result([], [], $message);
		}

		if (isset($entries['ServiceCode'])) {
			$entries = [$entries];
		}

		$services = [];
		$errors = [];

		foreach ($entries as $entry) {
			if (!is_array($entry)) {
				continue;
			}

			if (filter_var($entry['Error'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
				$errors[] = [
					'code'        => $this->getText($entry['ServiceCode'] ?? null),
					'description' => $this->getText($entry['ServiceDescription'] ?? null),
					'carrier'     => $this->getText($entry['Carrier'] ?? null),
					'message'     => $this->getText($entry['Msg'] ?? null)
				];

				continue;
			}

			$code = $this->getText($entry['ServiceCode'] ?? null);
			$price = $this->getText($entry['ShippingPrice'] ?? null);

			if ($code === '' || $price === '') {
				continue;
			}

			$services[] = new Service($code, $this->getText($entry['ServiceDescription'] ?? null), (float)str_replace(',', '.', $price), (int)$this->getText($entry['DeliveryTime'] ?? null));
		}

		return new Result($services, $errors, $message);
	}

	/**
	 * Value of a field as trimmed text, empty when it is missing or not a single value.
	 *
	 * @param mixed $value
	 *
	 * @return string
	 */
	private function getText(mixed $value): string {
		return is_scalar($value) ? trim((string)$value) : '';
	}
}
