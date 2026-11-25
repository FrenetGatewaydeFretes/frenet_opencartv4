<?php
namespace Frenet\Quote;

/**
 * Reads the services of a Frenet API quote, whether the answer lists several services or brings a single one.
 */
class Parser {
	/**
	 * Services that can be offered: entries without an error, a code and a price are left out.
	 *
	 * @param string $body
	 *
	 * @throws Exception
	 *
	 * @return array<int, Service>
	 */
	public function parse(string $body): array {
		$data = json_decode($body, true);

		if (!is_array($data)) {
			throw new Exception('The Frenet API answered with invalid JSON');
		}

		$entries = $data['ShippingSevicesArray'] ?? [];

		if (!is_array($entries)) {
			return [];
		}

		if (isset($entries['ServiceCode'])) {
			$entries = [$entries];
		}

		$services = [];

		foreach ($entries as $entry) {
			if (!is_array($entry) || filter_var($entry['Error'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
				continue;
			}

			$code = $this->getText($entry['ServiceCode'] ?? null);
			$price = $this->getText($entry['ShippingPrice'] ?? null);

			if ($code === '' || $price === '') {
				continue;
			}

			$services[] = new Service($code, $this->getText($entry['ServiceDescription'] ?? null), (float)str_replace(',', '.', $price), (int)$this->getText($entry['DeliveryTime'] ?? null));
		}

		return $services;
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
