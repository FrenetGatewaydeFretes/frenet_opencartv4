<?php
declare(strict_types=1);

namespace Frenet\Tests\Quote;

use Frenet\Quote\Exception;
use Frenet\Quote\Parser;
use Frenet\Quote\Service;
use PHPUnit\Framework\TestCase;

final class ParserTest extends TestCase {
	public function testReadsEveryServiceOfTheList(): void {
		$services = (new Parser())->parse((string)file_get_contents(dirname(__DIR__, 2) . '/mock/fixtures/quote.json'));

		self::assertSame(['03298', '03220'], array_map(static fn (Service $service): string => $service->getCode(), $services));
		self::assertSame('PAC', $services[0]->getDescription());
		self::assertSame(25.9, $services[0]->getPrice());
		self::assertSame(7, $services[0]->getDeliveryTime());
	}

	public function testReadsAListWithASingleService(): void {
		$services = (new Parser())->parse((string)file_get_contents(dirname(__DIR__, 2) . '/mock/fixtures/quote-single.json'));

		self::assertCount(1, $services);
		self::assertSame('03220', $services[0]->getCode());
		self::assertSame(42.5, $services[0]->getPrice());
	}

	public function testReadsASingleServiceSentWithoutAList(): void {
		$services = (new Parser())->parse('{"ShippingSevicesArray":{"ServiceCode":"03220","ServiceDescription":"SEDEX","ShippingPrice":"42,50","DeliveryTime":"3"}}');

		self::assertCount(1, $services);
		self::assertSame('SEDEX', $services[0]->getDescription());
		self::assertSame(42.5, $services[0]->getPrice());
	}

	public function testLeavesOutServicesWithAnErrorOrWithoutCodeOrPrice(): void {
		$services = (new Parser())->parse((string)json_encode(['ShippingSevicesArray' => [
			['ServiceCode' => '03298', 'ServiceDescription' => 'PAC', 'ShippingPrice' => '25.90', 'DeliveryTime' => '7', 'Error' => true, 'Msg' => 'Peso excedido'],
			['ServiceCode' => '04014', 'ServiceDescription' => 'SEDEX', 'ShippingPrice' => '42.50', 'Error' => 'true'],
			['ServiceCode' => '', 'ServiceDescription' => 'Sem código', 'ShippingPrice' => '10.00'],
			['ServiceCode' => 'JADLOG', 'ServiceDescription' => 'Jadlog'],
			'not a service',
			['ServiceCode' => 'MINI', 'ServiceDescription' => 'Mini Envios', 'ShippingPrice' => '9.90', 'Error' => false]
		]]));

		self::assertSame(['MINI'], array_map(static fn (Service $service): string => $service->getCode(), $services));
		self::assertSame(0, $services[0]->getDeliveryTime());
	}

	public function testAnswersNoServiceWhenTheListIsMissingOrEmpty(): void {
		self::assertSame([], (new Parser())->parse('{"ShippingSevicesArray":[],"Message":"Nenhum serviço"}'));
		self::assertSame([], (new Parser())->parse('{"Message":"Nenhum serviço"}'));
		self::assertSame([], (new Parser())->parse('{"ShippingSevicesArray":"none"}'));
	}

	public function testRefusesABodyThatIsNotJson(): void {
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('The Frenet API answered with invalid JSON');

		(new Parser())->parse('<html>Bad gateway</html>');
	}
}
