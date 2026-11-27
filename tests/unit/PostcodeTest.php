<?php
declare(strict_types=1);

namespace Frenet\Tests;

use Frenet\Postcode;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class PostcodeTest extends TestCase {
	#[TestWith(['01310100'], 'digits only')]
	#[TestWith([' 01310-100 '], 'hyphen and spaces around')]
	#[TestWith(['01.310-100'], 'dot and hyphen')]
	#[TestWith(['01310 100'], 'space')]
	public function testRemovesTheMaskOfThePostcode(string $postcode): void {
		self::assertSame('01310100', Postcode::getDigits($postcode));
	}

	#[TestWith(['01310100'], 'digits only')]
	#[TestWith([' 01310-100 '], 'hyphen and spaces around')]
	#[TestWith(['01.310-100'], 'dot and hyphen')]
	#[TestWith(['01310 100'], 'space')]
	public function testFormatsThePostcode(string $postcode): void {
		self::assertSame('01310-100', Postcode::format($postcode));
	}

	#[TestWith(['0131010'], 'seven digits')]
	#[TestWith(['013101000'], 'nine digits')]
	#[TestWith(['01310-10A'], 'letter')]
	#[TestWith(['CEP 01310100'], 'label')]
	#[TestWith([''], 'empty')]
	public function testRefusesAPostcodeWithoutEightDigits(string $postcode): void {
		self::assertSame('', Postcode::getDigits($postcode));
		self::assertNull(Postcode::format($postcode));
	}

	#[TestWith(['01310-100', 'BR'], 'Brazilian postcode')]
	#[TestWith(['01310100', ''], 'country missing, taken as Brazil')]
	#[TestWith(['1100-148', 'PT'], 'Portuguese postcode')]
	public function testQuotesAnAddressWithAValidPostcode(string $postcode, string $country): void {
		self::assertTrue(Postcode::isQuotable($postcode, $country));
	}

	#[TestWith(['', 'BR'], 'empty in Brazil')]
	#[TestWith(['0131010', 'BR'], 'seven digits in Brazil')]
	#[TestWith(['2004', 'BR'], 'four digits in Brazil')]
	#[TestWith(['abc', ''], 'letters with the country missing')]
	#[TestWith([' ', 'PT'], 'blank in another country')]
	public function testDoesNotQuoteAnAddressWithoutAValidPostcode(string $postcode, string $country): void {
		self::assertFalse(Postcode::isQuotable($postcode, $country));
	}
}
