<?php
namespace Frenet\Quote;

/**
 * Shipping service offered in a quote, with the price and the delivery time the Frenet API returned.
 */
class Service {
	public function __construct(private string $code, private string $description, private float $price, private int $delivery_time) {}

	/**
	 * Code of the service in the Frenet API, unique within a quote.
	 *
	 * @return string
	 */
	public function getCode(): string {
		return $this->code;
	}

	/**
	 * Name of the service as the Frenet API describes it.
	 *
	 * @return string
	 */
	public function getDescription(): string {
		return $this->description;
	}

	/**
	 * Shipping price in the currency of the quote.
	 *
	 * @return float
	 */
	public function getPrice(): float {
		return $this->price;
	}

	/**
	 * Delivery time in days, 0 when the Frenet API did not return one.
	 *
	 * @return int
	 */
	public function getDeliveryTime(): int {
		return $this->delivery_time;
	}

	/**
	 * Name shown at checkout: the template with %s for the service and %d for the days, or the service alone without a template or a delivery time.
	 *
	 * @param string $template
	 *
	 * @return string
	 */
	public function getTitle(string $template): string {
		$description = htmlspecialchars($this->description, ENT_COMPAT, 'UTF-8');

		if ($template === '' || $this->delivery_time < 1) {
			return $description;
		}

		return strtr($template, ['%s' => $description, '%d' => (string)$this->delivery_time]);
	}
}
