<?php
namespace Frenet;

use Opencart\System\Engine\Config as CoreConfig;

/**
 * Typed read access to the shipping_frenet_* settings so callers never handle raw OpenCart keys.
 */
class Config {
	/**
	 * @var string
	 */
	private const PREFIX = 'shipping_frenet_';

	/**
	 * @var string
	 */
	public const LEGACY_TOKEN = 'contrato_token';

	/**
	 * @var int
	 */
	public const TIMEOUT_MIN = 2;

	/**
	 * @var int
	 */
	public const TIMEOUT_MAX = 10;

	/**
	 * @var int
	 */
	public const TIMEOUT_DEFAULT = 5;

	/**
	 * @var int
	 */
	public const ATTEMPTS_MIN = 1;

	/**
	 * @var int
	 */
	public const ATTEMPTS_MAX = 3;

	/**
	 * @var int
	 */
	public const ATTEMPTS_DEFAULT = 3;

	/**
	 * @var float
	 */
	public const DEFAULT_HEIGHT = 2.0;

	/**
	 * @var float
	 */
	public const DEFAULT_WIDTH = 11.0;

	/**
	 * @var float
	 */
	public const DEFAULT_LENGTH = 16.0;

	/**
	 * @var float
	 */
	public const DEFAULT_WEIGHT = 1.0;

	/**
	 * @var int
	 */
	public const SIMULATOR_LIMIT_DEFAULT = 60;

	public function __construct(private CoreConfig $config) {}

	/**
	 * Whether the store owner enabled Frenet as a shipping method.
	 *
	 * @return bool
	 */
	public function isEnabled(): bool {
		return (bool)$this->get('status');
	}

	/**
	 * Token generated in the Frenet panel that authenticates every API call, or the one version 1.0.0 saved until the settings are opened.
	 *
	 * @return string
	 */
	public function getToken(): string {
		$token = trim($this->get('token'));

		return $token !== '' ? $token : trim($this->get(self::LEGACY_TOKEN));
	}

	/**
	 * Origin postcode with the mask removed, as the Frenet API expects.
	 *
	 * @return string
	 */
	public function getOriginPostcode(): string {
		return (string)preg_replace('/\D/', '', $this->get('postcode'));
	}

	/**
	 * Template for the rate title, where %s is the service name and %d the delivery days, empty for the default one.
	 *
	 * @return string
	 */
	public function getDeliveryTimeMessage(): string {
		return trim($this->get('msg_prazo'));
	}

	/**
	 * Whether the rate title shows the delivery days, on until the store owner turns it off.
	 *
	 * @return bool
	 */
	public function isDeliveryTimeShown(): bool {
		$value = $this->get('delivery_time');

		return $value === '' || (bool)$value;
	}

	/**
	 * Days the store owner adds to the delivery time of every service, never negative.
	 *
	 * @return int
	 */
	public function getAdditionalDays(): int {
		return max(0, (int)$this->get('additional_days'));
	}

	/**
	 * Tax class charged on the shipping cost, 0 for none.
	 *
	 * @return int
	 */
	public function getTaxClassId(): int {
		return max(0, (int)$this->get('tax_class_id'));
	}

	/**
	 * Geo zone where Frenet is offered, 0 for every address.
	 *
	 * @return int
	 */
	public function getGeoZoneId(): int {
		return max(0, (int)$this->get('geo_zone_id'));
	}

	/**
	 * Weight class the store owner mapped to kilograms, the unit the Frenet API expects, or 0 when none was chosen.
	 *
	 * @return int
	 */
	public function getWeightClassId(): int {
		return (int)$this->get('weight_class_id');
	}

	/**
	 * Length class the store owner mapped to centimetres, the unit the Frenet API expects, or 0 when none was chosen.
	 *
	 * @return int
	 */
	public function getLengthClassId(): int {
		return (int)$this->get('length_class_id');
	}

	/**
	 * Smallest height in centimetres sent for a product, also used when the product has none.
	 *
	 * @return float
	 */
	public function getMinimumHeight(): float {
		return $this->getPositive('minimum_height', self::DEFAULT_HEIGHT);
	}

	/**
	 * Smallest width in centimetres sent for a product, also used when the product has none.
	 *
	 * @return float
	 */
	public function getMinimumWidth(): float {
		return $this->getPositive('minimum_width', self::DEFAULT_WIDTH);
	}

	/**
	 * Smallest length in centimetres sent for a product, also used when the product has none.
	 *
	 * @return float
	 */
	public function getMinimumLength(): float {
		return $this->getPositive('minimum_length', self::DEFAULT_LENGTH);
	}

	/**
	 * Weight in kilograms sent for a product registered without one.
	 *
	 * @return float
	 */
	public function getDefaultWeight(): float {
		return $this->getPositive('default_weight', self::DEFAULT_WEIGHT);
	}

	/**
	 * Seconds each quote attempt may take, kept within the supported range.
	 *
	 * @return int
	 */
	public function getTimeout(): int {
		return $this->getInRange('timeout', self::TIMEOUT_DEFAULT, self::TIMEOUT_MIN, self::TIMEOUT_MAX);
	}

	/**
	 * How many times a failed quote is sent, kept within the supported range.
	 *
	 * @return int
	 */
	public function getAttempts(): int {
		return $this->getInRange('attempts', self::ATTEMPTS_DEFAULT, self::ATTEMPTS_MIN, self::ATTEMPTS_MAX);
	}

	/**
	 * Whether the product page shows the shipping simulator, on until the store owner turns it off.
	 *
	 * @return bool
	 */
	public function isSimulatorEnabled(): bool {
		$value = $this->get('simulator');

		return $value === '' || (bool)$value;
	}

	/**
	 * Simulator quotes each IP may ask per minute, 0 for no limit.
	 *
	 * @return int
	 */
	public function getSimulatorLimit(): int {
		$value = $this->get('simulator_limit');

		return is_numeric($value) ? max(0, (int)$value) : self::SIMULATOR_LIMIT_DEFAULT;
	}

	/**
	 * Whether every request and response goes to frenet.log, besides the errors.
	 *
	 * @return bool
	 */
	public function isDebug(): bool {
		return (bool)$this->get('debug');
	}

	/**
	 * Position of Frenet among the shipping methods at checkout.
	 *
	 * @return int
	 */
	public function getSortOrder(): int {
		return (int)$this->get('sort_order');
	}

	/**
	 * Number typed in the admin, with comma or dot decimals, or null when it is not greater than zero.
	 *
	 * @param mixed $value
	 *
	 * @return float|null
	 */
	public static function parsePositive(mixed $value): ?float {
		$number = is_scalar($value) ? str_replace(',', '.', trim((string)$value)) : '';

		return is_numeric($number) && (float)$number > 0 ? (float)$number : null;
	}

	/**
	 * Decimal setting greater than zero, or the default when the saved value is missing, zero or not a number.
	 *
	 * @param string $key
	 * @param float  $default
	 *
	 * @return float
	 */
	private function getPositive(string $key, float $default): float {
		return self::parsePositive($this->get($key)) ?? $default;
	}

	/**
	 * Integer setting clamped to the supported range, or the default when the saved value is not a number.
	 *
	 * @param string $key
	 * @param int    $default
	 * @param int    $min
	 * @param int    $max
	 *
	 * @return int
	 */
	private function getInRange(string $key, int $default, int $min, int $max): int {
		$value = $this->get($key);

		if (!is_numeric($value)) {
			return $default;
		}

		return max($min, min($max, (int)$value));
	}

	/**
	 * Value of one shipping_frenet_* setting as text, empty when it is missing or not a single value.
	 *
	 * @param string $key
	 *
	 * @return string
	 */
	private function get(string $key): string {
		$value = $this->config->get(self::PREFIX . $key);

		return is_scalar($value) ? (string)$value : '';
	}
}
