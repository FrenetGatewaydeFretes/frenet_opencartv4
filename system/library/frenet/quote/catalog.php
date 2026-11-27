<?php
namespace Frenet\Quote;

use Opencart\System\Library\DB;

/**
 * Reads what the cart does not carry for each product: the code sent as SKU and the category names the shopper sees.
 */
class Catalog {
	/**
	 * @var string
	 */
	public const CATEGORY_SEPARATOR = '|';

	/**
	 * @var string
	 */
	private const SKU_CODE = 'SKU';

	public function __construct(private DB $db, private string $prefix, private int $language_id, private int $store_id, private bool $sku_in_product_code) {}

	/**
	 * SKU, or the model when the product has none, and the names of the enabled categories of this store joined by |.
	 *
	 * @param array<int|string, int|string> $product_ids
	 *
	 * @return array<int, array{sku: string, category: string}>
	 */
	public function getProducts(array $product_ids): array {
		$ids = implode(',', array_unique(array_map('intval', $product_ids)));

		if ($ids === '') {
			return [];
		}

		$products = [];

		foreach ($this->getRows("SELECT `product_id`, `model`" . ($this->sku_in_product_code ? "" : ", `sku`") . " FROM `" . $this->prefix . "product` WHERE `product_id` IN (" . $ids . ")") as $row) {
			$sku = $this->decode($row['sku'] ?? '');

			$products[$this->getId($row)] = ['sku' => $sku !== '' ? $sku : $this->decode($row['model'] ?? ''), 'categories' => []];
		}

		if ($this->sku_in_product_code) {
			$coded = [];

			foreach ($this->getRows("SELECT `product_id`, `value` FROM `" . $this->prefix . "product_code` WHERE UPPER(`code`) = '" . self::SKU_CODE . "' AND `product_id` IN (" . $ids . ") ORDER BY `product_code_id`") as $row) {
				$product_id = $this->getId($row);
				$value = $this->decode($row['value'] ?? '');

				if ($value !== '' && isset($products[$product_id]) && !isset($coded[$product_id])) {
					$products[$product_id]['sku'] = $value;
					$coded[$product_id] = true;
				}
			}
		}

		foreach ($this->getRows("SELECT `p2c`.`product_id`, `cd`.`name` FROM `" . $this->prefix . "product_to_category` `p2c` INNER JOIN `" . $this->prefix . "category` `c` ON (`c`.`category_id` = `p2c`.`category_id` AND `c`.`status` = '1') INNER JOIN `" . $this->prefix . "category_to_store` `c2s` ON (`c2s`.`category_id` = `p2c`.`category_id` AND `c2s`.`store_id` = '" . $this->store_id . "') INNER JOIN `" . $this->prefix . "category_description` `cd` ON (`cd`.`category_id` = `p2c`.`category_id` AND `cd`.`language_id` = '" . $this->language_id . "') WHERE `p2c`.`product_id` IN (" . $ids . ") ORDER BY `cd`.`name`") as $row) {
			$product_id = $this->getId($row);

			if (isset($products[$product_id])) {
				$products[$product_id]['categories'][] = str_replace(self::CATEGORY_SEPARATOR, '/', $this->decode($row['name'] ?? ''));
			}
		}

		$details = [];

		foreach ($products as $product_id => $product) {
			$details[$product_id] = ['sku' => $product['sku'], 'category' => implode(self::CATEGORY_SEPARATOR, $product['categories'])];
		}

		return $details;
	}

	/**
	 * Rows of a query, empty when the core returns no result set.
	 *
	 * @param string $sql
	 *
	 * @return array<int, array<mixed>>
	 */
	private function getRows(string $sql): array {
		$result = $this->db->query($sql);
		$rows = [];

		if (is_object($result) && isset($result->rows) && is_array($result->rows)) {
			foreach ($result->rows as $row) {
				if (is_array($row)) {
					$rows[] = $row;
				}
			}
		}

		return $rows;
	}

	/**
	 * Product id of a row.
	 *
	 * @param array<mixed> $row
	 *
	 * @return int
	 */
	private function getId(array $row): int {
		return is_numeric($row['product_id'] ?? null) ? (int)$row['product_id'] : 0;
	}

	/**
	 * Plain text of a catalog value, which OpenCart stores HTML-escaped while the Frenet panel compares the plain text.
	 *
	 * @param mixed $value
	 *
	 * @return string
	 */
	private function decode(mixed $value): string {
		return is_scalar($value) ? trim(html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8')) : '';
	}
}
