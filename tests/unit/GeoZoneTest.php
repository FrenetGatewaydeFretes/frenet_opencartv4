<?php
declare(strict_types=1);

namespace Frenet\Tests;

use Frenet\GeoZone;
use Opencart\System\Library\DB;
use PHPUnit\Framework\TestCase;

final class GeoZoneTest extends TestCase {
	public function testFindsTheZoneOrTheWholeCountryOfTheAddressInTheGeoZone(): void {
		$db = new DB([[['found' => '1']]]);

		self::assertTrue((new GeoZone($db, 'oc_'))->contains(4, ['country_id' => '30', 'zone_id' => '458']));
		self::assertSame("SELECT 1 FROM `oc_zone_to_geo_zone` WHERE `geo_zone_id` = '4' AND `country_id` = '30' AND (`zone_id` = '458' OR `zone_id` = '0') LIMIT 1", $db->queries[0]);
	}

	public function testRefusesAnAddressOutsideTheGeoZone(): void {
		$db = new DB([[]]);

		self::assertFalse((new GeoZone($db, 'oc_'))->contains(4, ['country_id' => 'abc']));
		self::assertStringContainsString("`country_id` = '0' AND (`zone_id` = '0' OR `zone_id` = '0')", $db->queries[0]);
	}

	public function testTellsWhetherTheGeoZoneStillExists(): void {
		$db = new DB([[['found' => '1']], []]);
		$geo_zone = new GeoZone($db, 'oc_');

		self::assertTrue($geo_zone->exists(4));
		self::assertFalse($geo_zone->exists(9));
		self::assertSame("SELECT 1 FROM `oc_geo_zone` WHERE `geo_zone_id` = '9' LIMIT 1", $db->queries[1]);
	}
}
