<?php
declare(strict_types=1);

namespace Frenet\Tests;

use Frenet\Platform;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class PlatformTest extends TestCase {
	#[TestWith(['4.0.0.0'])]
	#[TestWith(['4.0.2.3'])]
	#[TestWith(['4.1.0.4'])]
	public function testBuildsRoutesWithTheSeparatorEveryReleaseAccepts(string $version): void {
		self::assertSame('extension/opencartfrenetv4/shipping/frenet|save', (new Platform($version))->getRoute('extension/opencartfrenetv4/shipping/frenet', 'save'));
	}

	#[TestWith(['4.0.0.0', '|'])]
	#[TestWith(['4.0.1.1', '|'])]
	#[TestWith(['4.0.2.0', '.'])]
	#[TestWith(['4.1.0.4', '.'])]
	public function testBuildsEventActionsWithTheSeparatorOfTheRelease(string $version, string $separator): void {
		self::assertSame('extension/opencartfrenetv4/shipping/frenet' . $separator . 'addSimulator', (new Platform($version))->getEventAction('extension/opencartfrenetv4/shipping/frenet', 'addSimulator'));
	}

	public function testPassesTheEventAsSeparateArgumentsOnTheFirstRelease(): void {
		self::assertSame(
			['frenet_simulator', 'Frenet simulator', 'catalog/view/product/product/after', 'extension/x|y', true, 0],
			(new Platform('4.0.0.0'))->getEventArguments('frenet_simulator', 'Frenet simulator', 'catalog/view/product/product/after', 'extension/x|y')
		);
	}

	#[TestWith(['4.0.1.0'])]
	#[TestWith(['4.1.0.4'])]
	public function testPassesTheEventAsOneArrayFromTheSecondRelease(string $version): void {
		self::assertSame(
			[[
				'code'        => 'frenet_simulator',
				'description' => 'Frenet simulator',
				'trigger'     => 'catalog/view/product/product/after',
				'action'      => 'extension/x.y',
				'status'      => false,
				'sort_order'  => 2
			]],
			(new Platform($version))->getEventArguments('frenet_simulator', 'Frenet simulator', 'catalog/view/product/product/after', 'extension/x.y', false, 2)
		);
	}

	#[TestWith(['4.0.0.0', true, false])]
	#[TestWith(['4.0.2.3', true, false])]
	#[TestWith(['4.1.0.0', false, false])]
	#[TestWith(['4.1.0.3', false, false])]
	#[TestWith(['4.1.0.4', false, true])]
	public function testKnowsWherePromotionsAndSkuLive(string $version, bool $special_table, bool $sku_in_code): void {
		$platform = new Platform($version);

		self::assertSame($special_table, $platform->hasProductSpecialTable());
		self::assertSame($sku_in_code, $platform->hasSkuInProductCode());
	}

	public function testTreatsAnUnknownVersionAsTheFirstRelease(): void {
		$platform = new Platform('');

		self::assertSame('extension/x|y', $platform->getEventAction('extension/x', 'y'));
		self::assertCount(6, $platform->getEventArguments('code', 'description', 'trigger', 'action'));
		self::assertTrue($platform->hasProductSpecialTable());
		self::assertFalse($platform->hasSkuInProductCode());
	}

	public function testKeepsTheVersion(): void {
		self::assertSame('4.0.2.3', (new Platform('4.0.2.3'))->getVersion());
	}
}
