<?php
namespace Frenet\Simulator;

use Opencart\System\Library\DB;
use stdClass;

/**
 * Database of the store with a cart table that holds only the page product, so the core cart reads it and never writes the real one.
 */
final class CartTable {
	/**
	 * @var array<string, int|string>
	 */
	private array $row;

	/**
	 * @param DB                       $db
	 * @param string                   $prefix
	 * @param int                      $product_id
	 * @param int                      $quantity
	 * @param array<int|string, mixed> $options
	 * @param int                      $subscription_plan_id
	 */
	public function __construct(private DB $db, private string $prefix, int $product_id, int $quantity, array $options, int $subscription_plan_id) {
		$this->row = [
			'cart_id'              => 0,
			'api_id'               => 0,
			'store_id'             => 0,
			'customer_id'          => 0,
			'session_id'           => '',
			'product_id'           => $product_id,
			'subscription_plan_id' => $subscription_plan_id,
			'option'               => (string)json_encode($options),
			'quantity'             => $quantity,
			'override'             => 0,
			'price'                => 0,
			'date_added'           => date('Y-m-d H:i:s')
		];
	}

	/**
	 * Answers the cart table with the page product, ignores every change to it and runs any other query on the store database.
	 *
	 * @param string $sql
	 *
	 * @return bool|object
	 */
	public function query(string $sql): bool|object {
		if (!str_contains($sql, '`' . $this->prefix . 'cart`')) {
			return $this->db->query($sql);
		}

		$statement = strtoupper(ltrim($sql));

		if (str_starts_with($statement, 'SELECT * FROM `' . strtoupper($this->prefix) . 'CART`')) {
			return $this->getResult([$this->row]);
		}

		return str_starts_with($statement, 'SELECT') ? $this->getResult([]) : true;
	}

	/**
	 * Escapes a value with the store database.
	 *
	 * @param string $value
	 *
	 * @return string
	 */
	public function escape(string $value): string {
		return $this->db->escape($value);
	}

	/**
	 * Result in the shape the core database returns for a SELECT.
	 *
	 * @param array<int, array<string, int|string>> $rows
	 *
	 * @return stdClass
	 */
	private function getResult(array $rows): stdClass {
		$result = new stdClass();
		$result->num_rows = count($rows);
		$result->row = $rows[0] ?? [];
		$result->rows = $rows;

		return $result;
	}
}
