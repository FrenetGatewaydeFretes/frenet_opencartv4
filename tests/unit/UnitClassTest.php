<?php
declare(strict_types=1);

namespace Frenet\Tests;

use Frenet\UnitClass;
use Opencart\System\Library\DB;
use PHPUnit\Framework\TestCase;

final class UnitClassTest extends TestCase {
	public function testFindsTheKilogramAndCentimetreClasses(): void {
		$db = new DB([[['class_id' => '7']], [['class_id' => '4']]]);
		$units = new UnitClass($db, 'oc_');

		self::assertSame(7, $units->findWeightClassId());
		self::assertSame(4, $units->findLengthClassId());
		self::assertSame("SELECT `weight_class_id` AS `class_id` FROM `oc_weight_class_description` WHERE LOWER(TRIM(`unit`)) = 'kg' ORDER BY `weight_class_id` LIMIT 1", $db->queries[0]);
		self::assertSame("SELECT `length_class_id` AS `class_id` FROM `oc_length_class_description` WHERE LOWER(TRIM(`unit`)) = 'cm' ORDER BY `length_class_id` LIMIT 1", $db->queries[1]);
	}

	public function testAnswersZeroWhenTheStoreHasNoSuchClass(): void {
		$units = new UnitClass(new DB([[], []]), 'oc_');

		self::assertSame(0, $units->findWeightClassId());
		self::assertSame(0, $units->findLengthClassId());
	}
}
