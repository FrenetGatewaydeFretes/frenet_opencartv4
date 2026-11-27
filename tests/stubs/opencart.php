<?php
namespace Opencart\System\Engine {
	if (!class_exists(Config::class, false)) {
		/**
		 * Same public API as the OpenCart 4 core Config, without loading files from DIR_CONFIG.
		 */
		class Config {
			/**
			 * @var array<string, mixed>
			 */
			private array $data = [];

			/**
			 * Value of a setting, or an empty string when it is not set, as the core returns.
			 *
			 * @param string $key
			 *
			 * @return mixed
			 */
			public function get(string $key): mixed {
				return $this->data[$key] ?? '';
			}

			/**
			 * Sets a setting value.
			 *
			 * @param string $key
			 * @param mixed  $value
			 *
			 * @return void
			 */
			public function set(string $key, mixed $value): void {
				$this->data[$key] = $value;
			}

			/**
			 * Whether a setting has a value.
			 *
			 * @param string $key
			 *
			 * @return bool
			 */
			public function has(string $key): bool {
				return isset($this->data[$key]);
			}
		}
	}
}

namespace Opencart\System\Engine {
	if (!class_exists(Registry::class, false)) {
		/**
		 * Same public API as the OpenCart 4 core Registry.
		 */
		class Registry {
			/**
			 * @var array<string, object>
			 */
			private array $data = [];

			/**
			 * Object kept under the key, or null.
			 *
			 * @param string $key
			 *
			 * @return object|null
			 */
			public function get(string $key): ?object {
				return $this->data[$key] ?? null;
			}

			/**
			 * Keeps an object under the key.
			 *
			 * @param string $key
			 * @param object $value
			 *
			 * @return void
			 */
			public function set(string $key, object $value): void {
				$this->data[$key] = $value;
			}
		}
	}
}

namespace Opencart\System\Library {
	if (!class_exists(Log::class, false)) {
		/**
		 * Same public API as the OpenCart 4 core Log, keeping lines in memory instead of a file under DIR_LOGS.
		 */
		class Log {
			/**
			 * @var array<int, string>
			 */
			public array $lines = [];

			public function __construct(public string $filename) {}

			/**
			 * Keeps the message as the core writes it.
			 *
			 * @param mixed $message
			 *
			 * @return void
			 */
			public function write(mixed $message): void {
				$this->lines[] = print_r($message, true);
			}
		}
	}
}

namespace Opencart\System\Library {
	if (!class_exists(Cache::class, false)) {
		/**
		 * Same public API as the OpenCart 4 core Cache, keeping the entries and their expiry in memory.
		 */
		class Cache {
			/**
			 * @var array<string, mixed>
			 */
			public array $entries = [];

			/**
			 * @var array<string, int>
			 */
			public array $expires = [];

			/**
			 * Entry of the key, or an empty array as the file cache answers for a missing key.
			 *
			 * @param string $key
			 *
			 * @return mixed
			 */
			public function get(string $key): mixed {
				return $this->entries[$key] ?? [];
			}

			/**
			 * Keeps the entry and the seconds it was given to live.
			 *
			 * @param string $key
			 * @param mixed  $value
			 * @param int    $expire
			 *
			 * @return void
			 */
			public function set(string $key, mixed $value, int $expire = 0): void {
				$this->entries[$key] = $value;
				$this->expires[$key] = $expire;
			}
		}
	}
}

namespace Opencart\System\Library {
	if (!class_exists(DB::class, false)) {
		/**
		 * Same public API as the OpenCart 4 core DB, answering each query from a queue and keeping the SQL it received.
		 */
		class DB {
			/**
			 * @var array<int, string>
			 */
			public array $queries = [];

			/**
			 * @param array<int, array<int, array<string, mixed>>> $results
			 */
			public function __construct(private array $results) {}

			/**
			 * Keeps the SQL and returns the next rows of the queue in the shape of the core result.
			 *
			 * @param string $sql
			 *
			 * @return bool|\stdClass
			 */
			public function query(string $sql): bool|\stdClass {
				$this->queries[] = $sql;

				$rows = array_shift($this->results) ?? [];
				$result = new \stdClass();
				$result->rows = $rows;
				$result->row = $rows[0] ?? [];
				$result->num_rows = count($rows);

				return $result;
			}

			/**
			 * Escapes a value as the core does for MySQL.
			 *
			 * @param string $value
			 *
			 * @return string
			 */
			public function escape(string $value): string {
				return addslashes($value);
			}
		}
	}
}

namespace Opencart\System\Library\Cart {
	if (!class_exists(Weight::class, false)) {
		/**
		 * Same public API as the OpenCart 4.1 core Weight, with the weight classes given instead of read from the database.
		 */
		class Weight {
			/**
			 * @param array<int, array{unit: string, value: float}> $weights
			 */
			public function __construct(private array $weights) {}

			/**
			 * Converts a value between two weight classes by their values, as the core does.
			 *
			 * @param float $value
			 * @param int   $from
			 * @param int   $to
			 *
			 * @return float
			 */
			public function convert(float $value, int $from, int $to): float {
				if ($from === $to) {
					return $value;
				}

				return $value * (($this->weights[$to]['value'] ?? 1) / ($this->weights[$from]['value'] ?? 1));
			}

			/**
			 * Unit of a weight class, or an empty string when it does not exist.
			 *
			 * @param int $weight_class_id
			 *
			 * @return string
			 */
			public function getUnit(int $weight_class_id): string {
				return $this->weights[$weight_class_id]['unit'] ?? '';
			}
		}
	}

	if (!class_exists(Length::class, false)) {
		/**
		 * Same public API as the OpenCart 4.1 core Length, with the length classes given instead of read from the database.
		 */
		class Length {
			/**
			 * @param array<int, array{unit: string, value: float}> $lengths
			 */
			public function __construct(private array $lengths) {}

			/**
			 * Converts a value between two length classes by their values, as the core does.
			 *
			 * @param float $value
			 * @param int   $from
			 * @param int   $to
			 *
			 * @return float
			 */
			public function convert(float $value, int $from, int $to): float {
				if ($from === $to) {
					return $value;
				}

				return $value * (($this->lengths[$to]['value'] ?? 1) / ($this->lengths[$from]['value'] ?? 1));
			}

			/**
			 * Unit of a length class, or an empty string when it does not exist.
			 *
			 * @param int $length_class_id
			 *
			 * @return string
			 */
			public function getUnit(int $length_class_id): string {
				return $this->lengths[$length_class_id]['unit'] ?? '';
			}
		}
	}
}

namespace Opencart\System\Library\Cart {
	if (!class_exists(Cart::class, false)) {
		/**
		 * Core cart reduced to what the simulator relies on: it clears old carts, then reads the cart table and each product.
		 */
		class Cart {
			private object $db;

			private object $customer;

			public function __construct(\Opencart\System\Engine\Registry $registry) {
				$this->db = $registry->get('db') ?? new \stdClass();
				$this->customer = $registry->get('customer') ?? new \stdClass();

				$this->db->query("DELETE FROM `oc_cart` WHERE `customer_id` = '0' AND `date_added` < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
			}

			/**
			 * Products of the cart of the customer, each with the columns of its cart row and the product row.
			 *
			 * @return array<int, array<string, mixed>>
			 */
			public function getProducts(): array {
				$products = [];

				foreach ($this->db->query("SELECT * FROM `oc_cart` WHERE `customer_id` = '" . (int)$this->customer->getId() . "'")->rows as $cart) {
					$product = $this->db->query("SELECT * FROM `oc_product` WHERE `product_id` = '" . (int)$cart['product_id'] . "'")->row;

					if (!$product) {
						$this->db->query("DELETE FROM `oc_cart` WHERE `cart_id` = '" . (int)$cart['cart_id'] . "'");

						continue;
					}

					$products[] = ['option' => (array)json_decode($cart['option'], true), 'logged' => $this->customer->isLogged()] + $product + $cart;
				}

				return $products;
			}
		}
	}
}
