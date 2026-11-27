<?php
namespace Frenet\Quote;

use Opencart\System\Library\Cache as CoreCache;

/**
 * Keeps successful Frenet answers in the OpenCart cache for a few minutes, keyed by the token and the request body.
 */
class Cache {
	/**
	 * @var int
	 */
	public const TTL = 600;

	/**
	 * @var string
	 */
	private const PREFIX = 'frenet.quote.';

	public function __construct(private CoreCache $cache) {}

	/**
	 * Key of the answer, so another account or any change in the cart, address or settings sent asks the API again.
	 *
	 * @param string $token
	 * @param string $body
	 *
	 * @return string
	 */
	public function getKey(string $token, string $body): string {
		return self::PREFIX . hash('sha256', $token . "\n" . $body);
	}

	/**
	 * Cached answer while it is younger than TTL, otherwise null.
	 *
	 * @param string $key
	 *
	 * @return string|null
	 */
	public function get(string $key): ?string {
		$entry = $this->quietly(fn (): mixed => $this->cache->get($key));

		if (!is_array($entry) || !is_string($entry['body'] ?? null) || !is_int($entry['expires'] ?? null) || $entry['expires'] <= time()) {
			return null;
		}

		return $entry['body'];
	}

	/**
	 * Keeps the answer for TTL seconds, also inside the entry, because the file cache of 4.0.2.x returns expired files.
	 *
	 * @param string $key
	 * @param string $body
	 *
	 * @return void
	 */
	public function set(string $key, string $body): void {
		$this->quietly(function() use ($key, $body): void {
			$this->cache->set($key, ['expires' => time() + self::TTL, 'body' => $body], self::TTL);
		});
	}

	/**
	 * Runs a call to the core cache without its warnings, which the file cache prints before the JSON of the checkout.
	 *
	 * @param callable $call
	 *
	 * @return mixed
	 */
	private function quietly(callable $call): mixed {
		set_error_handler(static fn (): bool => true, E_WARNING | E_NOTICE);

		try {
			return $call();
		} finally {
			restore_error_handler();
		}
	}
}
