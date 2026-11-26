<?php
namespace Frenet\Quote;

use Frenet\Config;
use Frenet\Postcode;
use Opencart\System\Library\Cart\Length;
use Opencart\System\Library\Cart\Weight;

/**
 * Turns the OpenCart cart and shipping address into a quote request with the shipped products only, in kg and cm.
 */
class Builder {
	public function __construct(private Config $config, private Weight $weight, private Length $length, private int $weight_class_id, private int $length_class_id, private string $platform_version) {}

	/**
	 * Request for the products of Cart::getProducts() that require shipping, whose weight and total are the line totals.
	 *
	 * @param array<string, mixed>                             $address
	 * @param array<int|string, array<string, mixed>>          $products
	 * @param array<int, array{sku: string, category: string}> $catalog
	 * @param string                                           $coupon
	 *
	 * @throws Exception
	 *
	 * @return Request
	 */
	public function build(array $address, array $products, array $catalog, string $coupon): Request {
		if ($this->weight->getUnit($this->weight_class_id) === '') {
			throw new Exception('The weight class mapped to kilograms does not exist: ' . $this->weight_class_id);
		}

		if ($this->length->getUnit($this->length_class_id) === '') {
			throw new Exception('The length class mapped to centimetres does not exist: ' . $this->length_class_id);
		}

		$default_weight = $this->config->getDefaultWeight();
		$minimum_length = $this->config->getMinimumLength();
		$minimum_height = $this->config->getMinimumHeight();
		$minimum_width = $this->config->getMinimumWidth();

		$items = [];
		$invoice_value = 0.0;

		foreach ($this->filterShipped($products) as $product) {
			$quantity = (int)$this->getNumber($product['quantity'] ?? 0);
			$details = $catalog[(int)$this->getNumber($product['product_id'] ?? 0)] ?? ['sku' => $this->decode($product['model'] ?? ''), 'category' => ''];
			$length_class_id = (int)$this->getNumber($product['length_class_id'] ?? 0);
			$weight = $this->toKilograms($this->getNumber($product['weight'] ?? 0) / $quantity, (int)$this->getNumber($product['weight_class_id'] ?? 0));

			$items[] = new Item(
				$weight > 0 ? $weight : $default_weight,
				$this->atLeast($this->toCentimetres($this->getNumber($product['length'] ?? 0), $length_class_id), $minimum_length),
				$this->atLeast($this->toCentimetres($this->getNumber($product['height'] ?? 0), $length_class_id), $minimum_height),
				$this->atLeast($this->toCentimetres($this->getNumber($product['width'] ?? 0), $length_class_id), $minimum_width),
				$quantity,
				$details['sku'],
				$details['category']
			);

			$invoice_value += $this->getNumber($product['total'] ?? 0);
		}

		if ($items === []) {
			throw new Exception('No product in the cart requires shipping');
		}

		$country = $this->decode($address['iso_code_2'] ?? '');

		return new Request(
			$this->config->getOriginPostcode(),
			(string)preg_replace('/\D/', '', $this->decode($address['postcode'] ?? '')),
			$country !== '' ? $country : Postcode::COUNTRY,
			round($invoice_value, 2),
			$coupon,
			$items,
			$this->platform_version
		);
	}

	/**
	 * Products of Cart::getProducts() that go into the quote: the ones that require shipping.
	 *
	 * @param array<int|string, array<string, mixed>> $products
	 *
	 * @return array<int|string, array<string, mixed>>
	 */
	public function filterShipped(array $products): array {
		return array_filter($products, fn (array $product): bool => $this->getNumber($product['quantity'] ?? 0) >= 1 && (bool)($product['shipping'] ?? false));
	}

	/**
	 * Measure raised to the minimum and rounded to tenths of a millimetre.
	 *
	 * @param float $value
	 * @param float $minimum
	 *
	 * @return float
	 */
	private function atLeast(float $value, float $minimum): float {
		return round(max($value, $minimum), 2);
	}

	/**
	 * Weight converted by the core from the class of the product to the kilogram class, without the conversion noise.
	 *
	 * @param float $value
	 * @param int   $from
	 *
	 * @return float
	 */
	private function toKilograms(float $value, int $from): float {
		return round($this->weight->convert($value, $from, $this->weight_class_id), 6);
	}

	/**
	 * Measure converted by the core from the class of the product to the centimetre class.
	 *
	 * @param float $value
	 * @param int   $from
	 *
	 * @return float
	 */
	private function toCentimetres(float $value, int $from): float {
		return $this->length->convert($value, $from, $this->length_class_id);
	}

	/**
	 * Number of a cart field, zero when it is empty or not a number.
	 *
	 * @param mixed $value
	 *
	 * @return float
	 */
	private function getNumber(mixed $value): float {
		return is_numeric($value) ? (float)$value : 0.0;
	}

	/**
	 * Plain text of a cart or address value, which OpenCart keeps HTML-escaped.
	 *
	 * @param mixed $value
	 *
	 * @return string
	 */
	private function decode(mixed $value): string {
		return is_scalar($value) ? trim(html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8')) : '';
	}
}
