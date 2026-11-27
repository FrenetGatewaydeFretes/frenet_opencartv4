<?php
namespace Frenet\Simulator;

use Opencart\System\Engine\Registry;
use Opencart\System\Library\Cart\Cart as CoreCart;
use Opencart\System\Library\DB;

/**
 * Builds the page product as the core cart of the store would hold it alone, with the price, options and weight of the cart.
 */
final class Cart {
	/**
	 * @var array<int, string>
	 */
	private const SHARED = ['config', 'session', 'tax', 'weight'];

	public function __construct(private Registry $registry, private DB $db, private string $prefix) {}

	/**
	 * Line of Cart::getProducts() for the product alone in a cart of a visitor, or null when the store does not sell it.
	 *
	 * @param int                      $product_id
	 * @param int                      $quantity
	 * @param array<int|string, mixed> $options
	 * @param int                      $subscription_plan_id
	 *
	 * @return array<string, mixed>|null
	 */
	public function getProduct(int $product_id, int $quantity, array $options, int $subscription_plan_id): ?array {
		$registry = new Registry();

		foreach (self::SHARED as $key) {
			$value = $this->registry->get($key);

			if ($value !== null) {
				$registry->set($key, $value);
			}
		}

		$registry->set('db', new CartTable($this->db, $this->prefix, $product_id, $quantity, $options, $subscription_plan_id));
		$registry->set('customer', new class {
			public function isLogged(): bool {
				return false;
			}

			public function getId(): int {
				return 0;
			}
		});

		$products = (new CoreCart($registry))->getProducts();
		$product = reset($products);

		return $product !== false ? $product : null;
	}
}
