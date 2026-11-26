<?php
declare(strict_types=1);

namespace Frenet\Tests\Quote;

use Frenet\Quote\Catalog;
use Opencart\System\Library\DB;
use PHPUnit\Framework\TestCase;

final class CatalogTest extends TestCase {
	public function testSendsTheSkuColumnOrTheModelAndTheNamesOfTheEnabledCategoriesOfTheStore(): void {
		$db = new DB([
			[['TABLE_NAME' => 'oc_product']],
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

		$products = (new Catalog($db, 'oc_', 2, 3))->getProducts(['40', 41, 41]);

		self::assertSame([
			40 => ['sku' => 'product 11', 'category' => 'Desktops|Phones & PDAs'],
			41 => ['sku' => 'CAM-AZ-M', 'category' => 'Moda / Verão']
		], $products);
		self::assertCount(3, $db->queries);
		self::assertStringContainsString("`TABLE_NAME` = 'oc_product' AND `COLUMN_NAME` = 'sku'", $db->queries[0]);
		self::assertStringContainsString("`TABLE_NAME` = 'oc_product_code' AND `COLUMN_NAME` = 'value'", $db->queries[0]);
		self::assertStringContainsString('SELECT `product_id`, `model`, `sku` FROM `oc_product` WHERE `product_id` IN (40,41)', $db->queries[1]);
		self::assertStringContainsString("`cd`.`language_id` = '2'", $db->queries[2]);
		self::assertStringContainsString("`c`.`status` = '1'", $db->queries[2]);
		self::assertStringContainsString("`c2s`.`store_id` = '3'", $db->queries[2]);
	}

	public function testReadsTheSkuFromTheProductCodesOfOpenCart41(): void {
		$db = new DB([
			[['TABLE_NAME' => 'oc_product_code']],
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

		$products = (new Catalog($db, 'oc_', 1, 0))->getProducts([40, 41]);

		self::assertSame(['sku' => 'product 11', 'category' => ''], $products[40]);
		self::assertSame(['sku' => 'CAM-AZ-M', 'category' => ''], $products[41]);
		self::assertStringContainsString('SELECT `product_id`, `model` FROM `oc_product`', $db->queries[1]);
		self::assertStringContainsString("FROM `oc_product_code` WHERE `code` = 'SKU' AND `product_id` IN (40,41) ORDER BY `product_code_id`", $db->queries[2]);
	}

	public function testPrefersTheProductCodeToTheSkuColumnKeptByOpenCart4103(): void {
		$db = new DB([
			[['TABLE_NAME' => 'oc_product'], ['TABLE_NAME' => 'oc_product_code']],
			[['product_id' => '41', 'model' => 'Camiseta azul', 'sku' => 'ANTIGO']],
			[['product_id' => '41', 'value' => 'CAM-AZ-M']],
			[]
		]);

		self::assertSame('CAM-AZ-M', (new Catalog($db, 'oc_', 1, 0))->getProducts([41])[41]['sku']);
	}

	public function testKeepsTheSkuColumnWhenTheProductHasNoSkuCode(): void {
		$db = new DB([
			[['TABLE_NAME' => 'oc_product'], ['TABLE_NAME' => 'oc_product_code']],
			[['product_id' => '41', 'model' => 'Camiseta azul', 'sku' => 'ANTIGO']],
			[],
			[]
		]);

		self::assertSame('ANTIGO', (new Catalog($db, 'oc_', 1, 0))->getProducts([41])[41]['sku']);
	}

	public function testDoesNotQueryWithoutProducts(): void {
		$db = new DB([]);

		self::assertSame([], (new Catalog($db, 'oc_', 1, 0))->getProducts([]));
		self::assertSame([], $db->queries);
	}
}
