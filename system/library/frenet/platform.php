<?php
namespace Frenet;

/**
 * What changes between OpenCart 4 releases, so the rest of the extension never compares versions.
 */
class Platform {
	/**
	 * @var string
	 */
	public const EXTENSION = 'opencartfrenetv4';

	/**
	 * @var string
	 */
	private const DOT_ROUTES = '4.0.2.0';

	/**
	 * @var string
	 */
	private const EVENT_ARRAY = '4.0.1.0';

	/**
	 * @var string
	 */
	private const SPECIALS_IN_DISCOUNT = '4.1.0.0';

	/**
	 * @var string
	 */
	private const SKU_IN_PRODUCT_CODE = '4.1.0.3';

	/**
	 * @var string
	 */
	private const VARIANT_OVERRIDE_LIST = '4.0.1.0';

	/**
	 * @var string
	 */
	private const VARIANT_OVERRIDE_KEYS = '4.0.2.2';

	/**
	 * @var string
	 */
	private const VARIANT_IN_CART = '4.1.0.4';

	public function __construct(private string $version) {}

	/**
	 * OpenCart version the store runs.
	 *
	 * @return string
	 */
	public function getVersion(): string {
		return $this->version;
	}

	/**
	 * Route of an extension method for links and AJAX calls, with the separator every OpenCart 4 release accepts.
	 *
	 * @param string $path
	 * @param string $method
	 *
	 * @return string
	 */
	public function getRoute(string $path, string $method): string {
		return $path . '|' . $method;
	}

	/**
	 * Action of an event, which the store keeps as saved and so needs the separator of its own release.
	 *
	 * @param string $path
	 * @param string $method
	 *
	 * @return string
	 */
	public function getEventAction(string $path, string $method): string {
		return $path . ($this->isAtLeast(self::DOT_ROUTES) ? '.' : '|') . $method;
	}

	/**
	 * Arguments of the core addEvent() of this release: one array from 4.0.1.0, separate values before it.
	 *
	 * @param string $code
	 * @param string $description
	 * @param string $trigger
	 * @param string $action
	 * @param bool   $status
	 * @param int    $sort_order
	 *
	 * @return array<int, mixed>
	 */
	public function getEventArguments(string $code, string $description, string $trigger, string $action, bool $status = true, int $sort_order = 0): array {
		if (!$this->isAtLeast(self::EVENT_ARRAY)) {
			return [$code, $description, $trigger, $action, $status, $sort_order];
		}

		return [[
			'code'        => $code,
			'description' => $description,
			'trigger'     => $trigger,
			'action'      => $action,
			'status'      => $status,
			'sort_order'  => $sort_order
		]];
	}

	/**
	 * Whether promotional prices live in product_special; from 4.1.0.0 they are product_discount rows marked special.
	 *
	 * @return bool
	 */
	public function hasProductSpecialTable(): bool {
		return !$this->isAtLeast(self::SPECIALS_IN_DISCOUNT);
	}

	/**
	 * Whether the SKU lives in product_code, where the admin writes it from 4.1.0.3; before, it is a column of product.
	 *
	 * @return bool
	 */
	public function hasSkuInProductCode(): bool {
		return $this->isAtLeast(self::SKU_IN_PRODUCT_CODE);
	}

	/**
	 * Values of the variant the cart button adds to the options: all up to 4.0.0.0, then only the ones the variant overrides.
	 *
	 * @param array<int|string, mixed> $variant
	 * @param array<int|string, mixed> $override
	 *
	 * @return array<int|string, mixed>
	 */
	public function getVariantOptions(array $variant, array $override): array {
		if (!$this->isAtLeast(self::VARIANT_OVERRIDE_LIST)) {
			return $variant;
		}

		$keys = [];

		foreach ($this->isAtLeast(self::VARIANT_OVERRIDE_KEYS) ? array_keys($override) : $override as $key) {
			if (is_int($key) || is_string($key)) {
				$keys[] = (string)$key;
			}
		}

		return array_filter($variant, static fn (int|string $key): bool => in_array((string)$key, $keys, true), ARRAY_FILTER_USE_KEY);
	}

	/**
	 * Whether the cart button adds a variant as itself, from 4.1.0.4, or as its master product, before.
	 *
	 * @return bool
	 */
	public function addsVariantToCart(): bool {
		return $this->isAtLeast(self::VARIANT_IN_CART);
	}

	/**
	 * Whether the store runs the given OpenCart version or a newer one.
	 *
	 * @param string $version
	 *
	 * @return bool
	 */
	private function isAtLeast(string $version): bool {
		return version_compare($this->version, $version, '>=');
	}
}
