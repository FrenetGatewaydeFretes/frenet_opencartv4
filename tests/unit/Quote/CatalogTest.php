<?php
declare(strict_types=1);

namespace Frenet\Tests\Quote;

use Frenet\Quote\Catalog;
use Opencart\System\Library\DB;
use PHPUnit\Framework\TestCase;

final class CatalogTest extends TestCase {
	public function testSendsTheSkuColumnOrTheModelAndTheNamesOfTheEnabledCategoriesOfTheStore(): void {
		$db = new DB([
			[
				['product_id' => '40', 'model' => 'product 11', 'sku' => ''],
				['product_id' => '41', 'model' => 'Camiseta azul', 'sku' => 'CAM-AZ-M']
			],
			[
				['product_id' => '40', 'name' => 'Desktops'],
				['product_id' => '40', 'name' => 'Phones &amp; PDAs'],
				['product_id' => '41', 'name' => 'Moda | Verão']
			]
		]);

		$products = (new Catalog($db, 'oc_', 2, 3, false))->getProducts(['40', 41, 41]);

		self::assertSame([
			40 => ['sku' => 'product 11', 'category' => 'Desktops|Phones & PDAs'],
			41 => ['sku' => 'CAM-AZ-M', 'category' => 'Moda / Verão']
		], $products);
		self::assertCount(2, $db->queries);
		self::assertStringContainsString('SELECT `product_id`, `model`, `sku` FROM `oc_product` WHERE `product_id` IN (40,41)', $db->queries[0]);
		self::assertStringContainsString("`cd`.`language_id` = '2'", $db->queries[1]);
		self::assertStringContainsString("`c`.`status` = '1'", $db->queries[1]);
		self::assertStringContainsString("`c2s`.`store_id` = '3'", $db->queries[1]);
	}

	public function testReadsTheSkuFromTheProductCodesFromOpenCart4103(): void {
		$db = new DB([
			[
				['product_id' => '40', 'model' => 'product 11'],
				['product_id' => '41', 'model' => 'Camiseta azul']
			],
			[
				['product_id' => '41', 'value' => ''],
				['product_id' => '41', 'value' => 'CAM-AZ-M'],
				['product_id' => '41', 'value' => 'CAM-AZ-G']
			],
			[]
		]);

		$products = (new Catalog($db, 'oc_', 1, 0, true))->getProducts([40, 41]);

		self::assertSame(['sku' => 'product 11', 'category' => ''], $products[40]);
		self::assertSame(['sku' => 'CAM-AZ-M', 'category' => ''], $products[41]);
		self::assertStringContainsString('SELECT `product_id`, `model` FROM `oc_product` WHERE', $db->queries[0]);
		self::assertStringContainsString("FROM `oc_product_code` WHERE UPPER(`code`) = 'SKU' AND `product_id` IN (40,41) ORDER BY `product_code_id`", $db->queries[1]);
	}

	public function testSendsTheModelWhenTheProductHasNoSkuCode(): void {
		$db = new DB([
			[['product_id' => '41', 'model' => 'Camiseta azul']],
			[],
			[]
		]);

		self::assertSame('Camiseta azul', (new Catalog($db, 'oc_', 1, 0, true))->getProducts([41])[41]['sku']);
	}

	public function testDoesNotQueryWithoutProducts(): void {
		$db = new DB([]);

		self::assertSame([], (new Catalog($db, 'oc_', 1, 0, true))->getProducts([]));
		self::assertSame([], $db->queries);
	}
}
