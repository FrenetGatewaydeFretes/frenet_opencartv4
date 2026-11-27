<?php
namespace Frenet;

use Opencart\System\Library\DB;

/**
 * Checks addresses against the geo zone chosen for Frenet, the way the core shipping extensions do.
 */
class GeoZone {
	public function __construct(private DB $db, private string $prefix) {}

	/**
	 * Whether the geo zone covers the country and zone of the address, the whole country included.
	 *
	 * @param int                  $geo_zone_id
	 * @param array<string, mixed> $address
	 *
	 * @return bool
	 */
	public function contains(int $geo_zone_id, array $address): bool {
		return $this->hasRows("SELECT 1 FROM `" . $this->prefix . "zone_to_geo_zone` WHERE `geo_zone_id` = '" . $geo_zone_id . "' AND `country_id` = '" . $this->getId($address['country_id'] ?? 0) . "' AND (`zone_id` = '" . $this->getId($address['zone_id'] ?? 0) . "' OR `zone_id` = '0') LIMIT 1");
	}

	/**
	 * Whether the geo zone still exists in the store.
	 *
	 * @param int $geo_zone_id
	 *
	 * @return bool
	 */
	public function exists(int $geo_zone_id): bool {
		return $this->hasRows("SELECT 1 FROM `" . $this->prefix . "geo_zone` WHERE `geo_zone_id` = '" . $geo_zone_id . "' LIMIT 1");
	}

	/**
	 * Whether the query returns at least one row.
	 *
	 * @param string $sql
	 *
	 * @return bool
	 */
	private function hasRows(string $sql): bool {
		$result = $this->db->query($sql);

		return is_object($result) && isset($result->num_rows) && is_numeric($result->num_rows) && $result->num_rows > 0;
	}

	/**
	 * Id of an address field, 0 when it is missing or not a number.
	 *
	 * @param mixed $value
	 *
	 * @return int
	 */
	private function getId(mixed $value): int {
		return is_numeric($value) ? (int)$value : 0;
	}
}
