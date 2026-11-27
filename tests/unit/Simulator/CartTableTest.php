<?php
declare(strict_types=1);

namespace Frenet\Tests\Simulator;

use Frenet\Simulator\CartTable;
use Opencart\System\Library\DB;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use stdClass;

final class CartTableTest extends TestCase {
	public function testAnswersTheCartTableWithThePageProduct(): void {
		$db = new DB([]);

		$result = (new CartTable($db, 'oc_', 40, 3, ['226' => '15', '227' => ['17', '18']], 2))->query("SELECT * FROM `oc_cart` WHERE `api_id` = '0' AND `customer_id` = '0' AND `session_id` = ''");

		self::assertInstanceOf(stdClass::class, $result);
		self::assertSame(1, $result->num_rows);
		self::assertSame([$result->row], $result->rows);

		$row = $result->row;

		self::assertIsArray($row);
		self::assertSame(40, $row['product_id']);
		self::assertSame(3, $row['quantity']);
		self::assertSame(2, $row['subscription_plan_id']);
		self::assertSame('{"226":"15","227":["17","18"]}', $row['option']);
		self::assertSame(0, $row['override']);
		self::assertSame([], $db->queries);
	}

	public function testSendsAnEmptyOptionListAsAJsonList(): void {
		$result = (new CartTable(new DB([]), 'oc_', 40, 1, [], 0))->query('SELECT * FROM `oc_cart`');

		self::assertInstanceOf(stdClass::class, $result);
		self::assertIsArray($result->row);
		self::assertSame('[]', $result->row['option']);
	}

	#[TestWith(["DELETE FROM `oc_cart` WHERE (`api_id` > '0' OR `customer_id` = '0') AND `date_added` < DATE_SUB(NOW(), INTERVAL 1 HOUR)"], 'expired carts')]
	#[TestWith(["UPDATE `oc_cart` SET `session_id` = 'abc' WHERE `customer_id` = '7'"], 'cart of a customer')]
	#[TestWith(["INSERT INTO `oc_cart` SET `product_id` = '40'"], 'new line')]
	#[TestWith(["delete from `oc_cart` where `cart_id` = '0'"], 'lower case')]
	public function testNeverChangesTheCartTableOfTheStore(string $sql): void {
		$db = new DB([]);

		self::assertTrue((new CartTable($db, 'oc_', 40, 1, [], 0))->query($sql));
		self::assertSame([], $db->queries);
	}

	public function testAnswersAnyOtherReadOfTheCartTableWithoutRows(): void {
		$db = new DB([]);

		$result = (new CartTable($db, 'oc_', 40, 1, [], 0))->query("SELECT COUNT(*) AS `total` FROM `oc_cart` WHERE `product_id` = '40'");

		self::assertInstanceOf(stdClass::class, $result);
		self::assertSame(0, $result->num_rows);
		self::assertSame([], $result->row);
		self::assertSame([], $db->queries);
	}

	#[TestWith(["SELECT * FROM `oc_product` WHERE `product_id` = '40'"], 'product')]
	#[TestWith(["SELECT * FROM `oc_cart_note` WHERE `cart_id` = '1'"], 'table whose name starts with cart')]
	#[TestWith(["SELECT * FROM `shop_cart`"], 'cart table of another prefix')]
	public function testRunsAnyOtherQueryOnTheStoreDatabase(string $sql): void {
		$db = new DB([[['product_id' => '40']]]);

		$result = (new CartTable($db, 'oc_', 40, 1, [], 0))->query($sql);

		self::assertInstanceOf(stdClass::class, $result);
		self::assertSame([['product_id' => '40']], $result->rows);
		self::assertSame([$sql], $db->queries);
	}

	public function testEscapesWithTheStoreDatabase(): void {
		self::assertSame("O\\'Reilly", (new CartTable(new DB([]), 'oc_', 40, 1, [], 0))->escape("O'Reilly"));
	}
}
