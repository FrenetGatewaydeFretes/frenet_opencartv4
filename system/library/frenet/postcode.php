<?php
namespace Frenet;

/**
 * Reads Brazilian postcodes typed by people and tells whether the Frenet API can quote an address.
 */
final class Postcode {
	/**
	 * @var string
	 */
	public const COUNTRY = 'BR';

	/**
	 * Postcode with the mask removed, or an empty string when it does not have 8 digits.
	 *
	 * @param string $postcode
	 *
	 * @return string
	 */
	public static function getDigits(string $postcode): string {
		$postcode = trim($postcode);

		if (preg_match('/^[0-9.\s-]+$/', $postcode) !== 1) {
			return '';
		}

		$digits = (string)preg_replace('/\D/', '', $postcode);

		return strlen($digits) === 8 ? $digits : '';
	}

	/**
	 * Whether the Frenet API can quote the address: a postcode with 8 digits in Brazil, any postcode in other countries.
	 *
	 * @param string $postcode
	 * @param string $country
	 *
	 * @return bool
	 */
	public static function isQuotable(string $postcode, string $country): bool {
		if ($country !== '' && $country !== self::COUNTRY) {
			return trim($postcode) !== '';
		}

		return self::getDigits($postcode) !== '';
	}
}
