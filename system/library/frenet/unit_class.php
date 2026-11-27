<?php
namespace Frenet;

use Opencart\System\Library\DB;

/**
 * Finds the store weight and length classes in kg and cm, the units the Frenet API expects.
 */
class UnitClass {
	/**
	 * @var string
	 */
	public const KILOGRAM = 'kg';

	/**
	 * @var string
	 */
	public const CENTIMETRE = 'cm';

	public function __construct(private DB $db, private string $prefix) {}

	/**
	 * Weight class whose unit is kg in any language, or 0 when the store has none.
	 *
	 * @return int
	 */
	public function findWeightClassId(): int {
		return $this->find('weight', self::KILOGRAM);
	}

	/**
	 * Length class whose unit is cm in any language, or 0 when the store has none.
	 *
	 * @return int
	 */
	public function findLengthClassId(): int {
		return $this->find('length', self::CENTIMETRE);
	}

	/**
	 * Lowest class id of the weight or length classes with the unit, ignoring case and spaces.
	 *
	 * @param string $type
	 * @param string $unit
	 *
	 * @return int
	 */
	private function find(string $type, string $unit): int {
		$result = $this->db->query("SELECT `" . $type . "_class_id` AS `class_id` FROM `" . $this->prefix . $type . "_class_description` WHERE LOWER(TRIM(`unit`)) = '" . $this->db->escape($unit) . "' ORDER BY `" . $type . "_class_id` LIMIT 1");

		if (!is_object($result) || !isset($result->row) || !is_array($result->row)) {
			return 0;
		}

		$class_id = $result->row['class_id'] ?? 0;

		return is_numeric($class_id) ? (int)$class_id : 0;
	}
}
