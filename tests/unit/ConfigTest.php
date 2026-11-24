<?php
declare(strict_types=1);

namespace Frenet\Tests;

use Frenet\Config;
use Opencart\System\Engine\Config as CoreConfig;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase {
	public function testReadsTheShippingFrenetSettings(): void {
		$config = $this->createConfig([
			'shipping_frenet_status'     => '1',
			'shipping_frenet_token'      => 'abc123',
			'shipping_frenet_postcode'   => '01310100',
			'shipping_frenet_msg_prazo'  => ' %s - em até %d dia(s) ',
			'shipping_frenet_sort_order' => '3'
		]);

		self::assertTrue($config->isEnabled());
		self::assertSame('abc123', $config->getToken());
		self::assertSame('01310100', $config->getOriginPostcode());
		self::assertSame('%s - em até %d dia(s)', $config->getDeliveryTimeMessage());
		self::assertSame(3, $config->getSortOrder());
	}

	public function testReturnsEmptyValuesWhenNothingIsSaved(): void {
		$config = $this->createConfig([]);

		self::assertFalse($config->isEnabled());
		self::assertSame('', $config->getToken());
		self::assertSame('', $config->getOriginPostcode());
		self::assertSame('', $config->getDeliveryTimeMessage());
		self::assertSame(0, $config->getSortOrder());
		self::assertFalse($config->isDebug());
	}

	public function testRemovesThePostcodeMaskAndTokenSpaces(): void {
		$config = $this->createConfig([
			'shipping_frenet_token'    => "  abc123 \n",
			'shipping_frenet_postcode' => '01310-100'
		]);

		self::assertSame('abc123', $config->getToken());
		self::assertSame('01310100', $config->getOriginPostcode());
	}

	public function testReadsTheTimeoutTheAttemptsAndTheDebugSwitch(): void {
		$config = $this->createConfig(['shipping_frenet_timeout' => '8', 'shipping_frenet_attempts' => '2', 'shipping_frenet_debug' => '1']);

		self::assertSame(8, $config->getTimeout());
		self::assertSame(2, $config->getAttempts());
		self::assertTrue($config->isDebug());
	}

	public function testUsesTheDefaultTimeoutAndAttemptsWhenNothingIsSaved(): void {
		$config = $this->createConfig([]);

		self::assertSame(Config::TIMEOUT_DEFAULT, $config->getTimeout());
		self::assertSame(Config::ATTEMPTS_DEFAULT, $config->getAttempts());
	}

	#[TestWith(['0', Config::TIMEOUT_MIN, Config::ATTEMPTS_MIN])]
	#[TestWith(['1', Config::TIMEOUT_MIN, Config::ATTEMPTS_MIN])]
	#[TestWith(['99', Config::TIMEOUT_MAX, Config::ATTEMPTS_MAX])]
	#[TestWith(['-5', Config::TIMEOUT_MIN, Config::ATTEMPTS_MIN])]
	public function testKeepsTheTimeoutAndAttemptsWithinTheSupportedRange(string $saved, int $timeout, int $attempts): void {
		$config = $this->createConfig(['shipping_frenet_timeout' => $saved, 'shipping_frenet_attempts' => $saved]);

		self::assertSame($timeout, $config->getTimeout());
		self::assertSame($attempts, $config->getAttempts());
	}

	/**
	 * Config filled with the given settings.
	 *
	 * @param array<string, string> $settings
	 *
	 * @return Config
	 */
	private function createConfig(array $settings): Config {
		$core = new CoreConfig();

		foreach ($settings as $key => $value) {
			$core->set($key, $value);
		}

		return new Config($core);
	}
}
