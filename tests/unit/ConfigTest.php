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

	public function testFallsBackToTheTokenOfVersionOneUntilTheSettingsAreSaved(): void {
		self::assertSame('legacy', $this->createConfig(['shipping_frenet_contrato_token' => ' legacy '])->getToken());
		self::assertSame('new', $this->createConfig(['shipping_frenet_token' => 'new', 'shipping_frenet_contrato_token' => 'legacy'])->getToken());
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

	public function testUsesTheDefaultTimeoutAndAttemptsWhenTheSavedValueIsNotANumber(): void {
		$config = $this->createConfig(['shipping_frenet_timeout' => 'abc', 'shipping_frenet_attempts' => ['2']]);

		self::assertSame(Config::TIMEOUT_DEFAULT, $config->getTimeout());
		self::assertSame(Config::ATTEMPTS_DEFAULT, $config->getAttempts());
	}

	public function testReadsTheUnitClassesTheStoreOwnerMapped(): void {
		$config = $this->createConfig(['shipping_frenet_weight_class_id' => '5', 'shipping_frenet_length_class_id' => '3']);

		self::assertSame(5, $config->getWeightClassId());
		self::assertSame(3, $config->getLengthClassId());
		self::assertSame(0, $this->createConfig([])->getWeightClassId());
		self::assertSame(0, $this->createConfig([])->getLengthClassId());
	}

	public function testReadsTheMinimumMeasuresAndTheDefaultWeight(): void {
		$config = $this->createConfig([
			'shipping_frenet_minimum_height' => '3',
			'shipping_frenet_minimum_width'  => '12,5',
			'shipping_frenet_minimum_length' => '18',
			'shipping_frenet_default_weight' => '0.3'
		]);

		self::assertSame(3.0, $config->getMinimumHeight());
		self::assertSame(12.5, $config->getMinimumWidth());
		self::assertSame(18.0, $config->getMinimumLength());
		self::assertSame(0.3, $config->getDefaultWeight());
	}

	public function testFallsBackToTheCorreiosMinimumsWhenTheValueIsEmptyOrNotPositive(): void {
		$config = $this->createConfig(['shipping_frenet_minimum_height' => '0', 'shipping_frenet_minimum_width' => '-4', 'shipping_frenet_minimum_length' => 'abc']);

		self::assertSame(Config::DEFAULT_HEIGHT, $config->getMinimumHeight());
		self::assertSame(Config::DEFAULT_WIDTH, $config->getMinimumWidth());
		self::assertSame(Config::DEFAULT_LENGTH, $config->getMinimumLength());
		self::assertSame(Config::DEFAULT_WEIGHT, $config->getDefaultWeight());
	}

	public function testParsesPositiveNumbersTypedInTheAdmin(): void {
		self::assertSame(12.5, Config::parsePositive(' 12,5 '));
		self::assertSame(0.0004, Config::parsePositive('0.0004'));
		self::assertSame(2.0, Config::parsePositive(2));
		self::assertNull(Config::parsePositive('0'));
		self::assertNull(Config::parsePositive('-3'));
		self::assertNull(Config::parsePositive('abc'));
		self::assertNull(Config::parsePositive(null));
		self::assertNull(Config::parsePositive(['1']));
	}

	public function testReadsAListAsEmptyAndKeepsBooleansAndNumbers(): void {
		$config = $this->createConfig(['shipping_frenet_token' => ['abc'], 'shipping_frenet_status' => true, 'shipping_frenet_sort_order' => 4]);

		self::assertSame('', $config->getToken());
		self::assertTrue($config->isEnabled());
		self::assertSame(4, $config->getSortOrder());
	}

	/**
	 * Config filled with the given settings.
	 *
	 * @param array<string, mixed> $settings
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
