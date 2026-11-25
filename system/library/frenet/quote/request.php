<?php
namespace Frenet\Quote;

/**
 * Body of a quote request to the Frenet API.
 */
class Request {
	/**
	 * @var string
	 */
	public const PLATFORM_NAME = 'OpenCart4';

	/**
	 * @param string           $seller_postcode
	 * @param string           $recipient_postcode
	 * @param string           $recipient_country
	 * @param float            $invoice_value
	 * @param string           $coupon
	 * @param array<int, Item> $items
	 * @param string           $platform_version
	 */
	public function __construct(private string $seller_postcode, private string $recipient_postcode, private string $recipient_country, private float $invoice_value, private string $coupon, private array $items, private string $platform_version) {}

	/**
	 * Request in the JSON format of the Frenet API.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return [
			'SellerCEP'            => $this->seller_postcode,
			'RecipientCEP'         => $this->recipient_postcode,
			'RecipientDocument'    => '',
			'RecipientCountry'     => $this->recipient_country,
			'ShipmentInvoiceValue' => $this->invoice_value,
			'Coupom'               => $this->coupon,
			'PlatformName'         => self::PLATFORM_NAME,
			'PlatformVersion'      => $this->platform_version,
			'ShippingItemArray'    => array_map(static fn (Item $item): array => $item->toArray(), $this->items)
		];
	}
}
