<?php
namespace Frenet\Quote;

/**
 * Cart line sent to the Frenet API: unit weight in kg, measures in cm, quantity, SKU and categories.
 */
class Item {
	public function __construct(private float $weight, private float $length, private float $height, private float $width, private int $quantity, private string $sku, private string $category) {}

	/**
	 * Item in the ShippingItemArray format of the Frenet API.
	 *
	 * @return array<string, bool|float|int|string>
	 */
	public function toArray(): array {
		return [
			'Weight'    => $this->weight,
			'Length'    => $this->length,
			'Height'    => $this->height,
			'Width'     => $this->width,
			'Diameter'  => 0,
			'SKU'       => $this->sku,
			'Category'  => $this->category,
			'isFragile' => false,
			'Quantity'  => $this->quantity
		];
	}
}
