<?php
declare(strict_types=1);

namespace Frenet\Tests\Quote;

use Frenet\Quote\Cache;
use Opencart\System\Library\Cache as CoreCache;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class CacheTest extends TestCase {
	private const BODY = '{"ShippingSevicesArray":[]}';

	private CoreCache $store;

	private Cache $cache;

	protected function setUp(): void {
		$this->store = new CoreCache();
		$this->cache = new Cache($this->store);
	}

	public function testBuildsAKeyTheOpenCartFileCacheKeepsWhole(): void {
		$key = $this->cache->getKey('abc123', self::BODY);

		self::assertMatchesRegularExpression('/^frenet\.quote\.[0-9a-f]{64}$/', $key);
		self::assertSame($key, $this->cache->getKey('abc123', self::BODY));
		self::assertStringNotContainsString('abc123', $key);
	}

	public function testBuildsAnotherKeyForAnotherTokenOrBody(): void {
		$key = $this->cache->getKey('abc123', self::BODY);

		self::assertNotSame($key, $this->cache->getKey('xyz789', self::BODY));
		self::assertNotSame($key, $this->cache->getKey('abc123', '{"ShippingSevicesArray":[{}]}'));
	}

	public function testKeepsTheBodyForTenMinutesInTheEntryAndInTheCoreCache(): void {
		$this->cache->set('frenet.quote.key', self::BODY);

		self::assertSame(self::BODY, $this->cache->get('frenet.quote.key'));
		self::assertIsArray($this->store->entries['frenet.quote.key']);
		self::assertEqualsWithDelta(time() + 600, $this->store->entries['frenet.quote.key']['expires'], 1);
		self::assertSame(600, $this->store->expires['frenet.quote.key']);
	}

	public function testIgnoresAnExpiredEntryTheFileCacheStillReturns(): void {
		$this->store->set('frenet.quote.key', ['expires' => time(), 'body' => self::BODY]);

		self::assertNull($this->cache->get('frenet.quote.key'));
	}

	#[TestWith([[]], 'missing')]
	#[TestWith([null], 'null')]
	#[TestWith(['{"ShippingSevicesArray":[]}'], 'plain string')]
	#[TestWith([['expires' => 0]], 'without body')]
	#[TestWith([['body' => '{"ShippingSevicesArray":[]}']], 'without expiry')]
	#[TestWith([['expires' => '9999999999', 'body' => '{"ShippingSevicesArray":[]}']], 'expiry as text')]
	public function testIgnoresAnEntryItDidNotWrite(mixed $entry): void {
		$this->store->set('frenet.quote.key', $entry);

		self::assertNull($this->cache->get('frenet.quote.key'));
	}

	public function testKeepsTheWarningsOfTheCoreCacheOutOfThePage(): void {
		$store = new class extends CoreCache {
			public function get(string $key): mixed {
				return fopen(sys_get_temp_dir() . '/frenet-missing/cache.' . $key, 'r');
			}

			public function set(string $key, mixed $value, int $expire = 0): void {
				fopen(sys_get_temp_dir() . '/frenet-missing/cache.' . $key, 'r');
			}
		};

		$warnings = [];

		set_error_handler(static function(int $code, string $message) use (&$warnings): bool {
			$warnings[] = $message;

			return true;
		});

		try {
			$cache = new Cache($store);

			self::assertNull($cache->get('frenet.quote.key'));

			$cache->set('frenet.quote.key', self::BODY);
		} finally {
			restore_error_handler();
		}

		self::assertSame([], $warnings);
	}
}
