<?php
declare(strict_types=1);

namespace Frenet\Tests\Quote;

use Frenet\Quote\Service;
use PHPUnit\Framework\TestCase;

final class ServiceTest extends TestCase {
	public function testFillsTheTemplateWithTheServiceAndTheDays(): void {
		self::assertSame('SEDEX - em até 3 dia(s)', (new Service('03220', 'SEDEX', 42.5, 3))->getTitle('%s - em até %d dia(s)'));
	}

	public function testShowsOnlyTheServiceWithoutATemplateOrADeliveryTime(): void {
		self::assertSame('SEDEX', (new Service('03220', 'SEDEX', 42.5, 3))->getTitle(''));
		self::assertSame('Jadlog', (new Service('JADLOG', 'Jadlog', 50.0, 0))->getTitle('%s - em até %d dia(s)'));
	}

	public function testEscapesTheServiceName(): void {
		self::assertSame('&lt;b&gt;SEDEX&lt;/b&gt; - 3', (new Service('03220', '<b>SEDEX</b>', 42.5, 3))->getTitle('%s - %d'));
	}
}
