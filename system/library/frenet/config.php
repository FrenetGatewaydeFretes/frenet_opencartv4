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
	 * Token generated in the Frenet panel that authenticates every API call.
	 *
	 * @return string
	 */
	public function getToken(): string {
		return trim((string)$this->get('token'));
	}

	/**
	 * Origin postcode with the mask removed, as the Frenet API expects.
	 *
	 * @return string
	 */
	public function getOriginPostcode(): string {
		return (string)preg_replace('/\D/', '', (string)$this->get('postcode'));
	}

	/**
	 * Template for the rate title, where %s is the service name and %d the delivery days, empty for the default one.
	 *
	 * @return string
	 */
	public function getDeliveryTimeMessage(): string {
		return trim((string)$this->get('msg_prazo'));
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
	 * Integer setting clamped to the supported range, or the default when the store owner left it empty.
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

		if ($value === null || $value === '') {
			return $default;
		}

		return max($min, min($max, (int)$value));
	}

	/**
	 * Raw value of one shipping_frenet_* setting.
	 *
	 * @param string $key
	 *
	 * @return mixed
	 */
	private function get(string $key): mixed {
		return $this->config->get(self::PREFIX . $key);
	}
}
