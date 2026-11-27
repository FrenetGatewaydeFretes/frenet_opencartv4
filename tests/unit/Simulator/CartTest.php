<?php
declare(strict_types=1);

namespace Frenet\Tests\Simulator;

use Frenet\Simulator\Cart;
use Opencart\System\Engine\Config;
use Opencart\System\Engine\Registry;
use Opencart\System\Library\DB;
use PHPUnit\Framework\TestCase;

final class CartTest extends TestCase {
	public function testReadsThePageProductThroughTheCoreCartOfAVisitor(): void {
		$db = new DB([[['product_id' => '40', 'price' => '100.0000', 'weight' => '1.5']]]);

		$product = $this->createCart($db)->getProduct(40, 2, ['226' => '15'], 0);

		self::assertNotNull($product);
		self::assertSame('40', $product['product_id']);
		self::assertSame(2, $product['quantity']);
		self::assertSame(['226' => '15'], $product['option']);
		self::assertFalse($product['logged']);
		self::assertSame(["SELECT * FROM `oc_product` WHERE `product_id` = '40'"], $db->queries);
	}

	public function testReturnsNothingWhenTheStoreDoesNotSellTheProduct(): void {
		$db = new DB([[]]);

		self::assertNull($this->createCart($db)->getProduct(41, 1, [], 0));
		self::assertSame(["SELECT * FROM `oc_product` WHERE `product_id` = '41'"], $db->queries);
	}

	private function createCart(DB $db): Cart {
		$registry = new Registry();
		$registry->set('config', new Config());

		return new Cart($registry, $db, 'oc_');
	}
}
