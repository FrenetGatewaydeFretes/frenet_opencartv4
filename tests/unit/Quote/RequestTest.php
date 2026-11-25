<?php
declare(strict_types=1);

namespace Frenet\Tests\Quote;

use Frenet\Quote\Item;
use Frenet\Quote\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase {
	public function testBuildsTheBodyOfTheFrenetApi(): void {
		$request = new Request('01310100', '20040020', 'BR', 150.5, 'DESCONTO10', [new Item(0.5, 16.0, 2.0, 11.0, 3)], '4.1.0.4');

		self::assertSame([
			'SellerCEP'            => '01310100',
			'RecipientCEP'         => '20040020',
			'RecipientDocument'    => '',
			'RecipientCountry'     => 'BR',
			'ShipmentInvoiceValue' => 150.5,
			'Coupom'               => 'DESCONTO10',
			'PlatformName'         => 'OpenCart4',
			'PlatformVersion'      => '4.1.0.4',
			'ShippingItemArray'    => [[
				'Weight'    => 0.5,
				'Length'    => 16.0,
				'Height'    => 2.0,
				'Width'     => 11.0,
				'Diameter'  => 0,
				'SKU'       => '',
				'Category'  => '',
				'isFragile' => false,
				'Quantity'  => 3
			]]
		], $request->toArray());
	}
}
