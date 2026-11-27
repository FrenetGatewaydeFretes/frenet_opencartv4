<?php
declare(strict_types=1);

namespace Frenet\Tests\Quote;

use Frenet\Config;
use Frenet\Quote\Builder;
use Frenet\Quote\Exception;
use Opencart\System\Engine\Config as CoreConfig;
use Opencart\System\Library\Cart\Length;
use Opencart\System\Library\Cart\Weight;
use PHPUnit\Framework\TestCase;

final class BuilderTest extends TestCase {
	private const KG = 1;
	private const G = 2;
	private const LB = 3;
	private const OZ = 4;
	private const CM = 1;
	private const MM = 2;
	private const INCH = 3;

	public function testBuildsThePayloadTheFrenetApiExpects(): void {
		$request = $this->createBuilder()->build(
			['postcode' => '20040-020', 'iso_code_2' => 'BR'],
			[$this->product(['product_id' => 41, 'quantity' => 2, 'weight' => 3, 'length' => 16, 'height' => 2, 'width' => 11, 'total' => 199.9])],
			[41 => ['sku' => 'CAM-AZ-M', 'category' => 'Moda|Camisetas']],
			'FRETE10'
		)->toArray();

		self::assertSame('01310100', $request['SellerCEP']);
		self::assertSame('20040020', $request['RecipientCEP']);
		self::assertSame('BR', $request['RecipientCountry']);
		self::assertSame(199.9, $request['ShipmentInvoiceValue']);
		self::assertSame('FRETE10', $request['Coupom']);
		self::assertSame('OpenCart4', $request['PlatformName']);
		self::assertSame('4.1.0.4', $request['PlatformVersion']);
		self::assertSame([[
			'Weight'    => 1.5,
			'Length'    => 16.0,
			'Height'    => 2.0,
			'Width'     => 11.0,
			'Diameter'  => 0,
			'SKU'       => 'CAM-AZ-M',
			'Category'  => 'Moda|Camisetas',
			'isFragile' => false,
			'Quantity'  => 2
		]], $request['ShippingItemArray']);
	}

	public function testConvertsEveryUnitToKilogramsAndCentimetres(): void {
		$items = $this->items([
			$this->product(['weight' => 500, 'weight_class_id' => self::G, 'length' => 160, 'height' => 20, 'width' => 110, 'length_class_id' => self::MM]),
			$this->product(['weight' => 2, 'weight_class_id' => self::LB, 'length' => 8, 'height' => 1, 'width' => 5, 'length_class_id' => self::INCH]),
			$this->product(['weight' => 16, 'weight_class_id' => self::OZ, 'length' => 30, 'height' => 4, 'width' => 20, 'length_class_id' => self::CM]),
			$this->product(['weight' => 2, 'weight_class_id' => self::KG])
		]);

		self::assertSame([0.5, 16.0, 2.0, 11.0], [$items[0]['Weight'], $items[0]['Length'], $items[0]['Height'], $items[0]['Width']]);
		self::assertSame([0.907194, 20.32, 2.54, 12.7], [$items[1]['Weight'], $items[1]['Length'], $items[1]['Height'], $items[1]['Width']]);
		self::assertSame([0.453592, 30.0, 4.0, 20.0], [$items[2]['Weight'], $items[2]['Length'], $items[2]['Height'], $items[2]['Width']]);
		self::assertSame(2.0, $items[3]['Weight']);
	}

	public function testKeepsLightWeightsAndTheWeightOfOneUnit(): void {
		$items = $this->items([
			$this->product(['weight' => 0.4, 'weight_class_id' => self::G]),
			$this->product(['weight' => 1500, 'quantity' => 1000, 'weight_class_id' => self::G])
		]);

		self::assertSame(0.0004, $items[0]['Weight']);
		self::assertSame(0.0015, $items[1]['Weight']);
		self::assertSame(1000, $items[1]['Quantity']);
	}

	public function testRaisesMeasuresBelowTheMinimumToTheMinimum(): void {
		$items = $this->items([
			$this->product(['length' => 10, 'height' => 0.5, 'width' => 5]),
			$this->product(['length' => 1, 'height' => 1, 'width' => 1, 'length_class_id' => self::MM])
		]);

		self::assertSame([16.0, 2.0, 11.0], [$items[0]['Length'], $items[0]['Height'], $items[0]['Width']]);
		self::assertSame([16.0, 2.0, 11.0], [$items[1]['Length'], $items[1]['Height'], $items[1]['Width']]);
	}

	public function testUsesTheMappedClassesEvenWhenGramsAreTheStoreDefault(): void {
		$weight = new Weight([self::KG => ['unit' => 'kg', 'value' => 0.001], self::G => ['unit' => 'g', 'value' => 1.0]]);

		$items = $this->items([$this->product(['weight' => 1500, 'weight_class_id' => self::G])], $this->createBuilder([], $weight));

		self::assertSame(1.5, $items[0]['Weight']);
	}

	public function testSendsTheConfiguredMinimumsForAProductWithoutWeightOrMeasures(): void {
		$items = $this->items([
			$this->product(['weight' => 0, 'length' => 0, 'height' => '', 'width' => '0.00000000']),
			$this->product(['weight' => 1, 'length' => 0, 'height' => 5, 'width' => 0])
		], $this->createBuilder([
			'shipping_frenet_minimum_height' => '3',
			'shipping_frenet_minimum_width'  => '12',
			'shipping_frenet_minimum_length' => '18',
			'shipping_frenet_default_weight' => '0.4'
		]));

		self::assertSame([0.4, 18.0, 3.0, 12.0], [$items[0]['Weight'], $items[0]['Length'], $items[0]['Height'], $items[0]['Width']]);
		self::assertSame([1.0, 18.0, 5.0, 12.0], [$items[1]['Weight'], $items[1]['Length'], $items[1]['Height'], $items[1]['Width']]);
	}

	public function testUsesTheCorreiosMinimumsWhenNothingIsConfigured(): void {
		$items = $this->items([$this->product(['weight' => 0, 'length' => 0, 'height' => 0, 'width' => 0])]);

		self::assertSame([1.0, 16.0, 2.0, 11.0], [$items[0]['Weight'], $items[0]['Length'], $items[0]['Height'], $items[0]['Width']]);
	}

	public function testDeclaresTheSellingPriceOfTheShippedItemsWithoutTheCoupon(): void {
		$request = $this->createBuilder()->build(['postcode' => '20040020'], [
			$this->product(['product_id' => 41, 'quantity' => 2, 'price' => 90, 'total' => 180, 'weight' => 0.6]),
			$this->product(['product_id' => 50, 'quantity' => 1, 'price' => 50, 'total' => 50, 'shipping' => '0', 'weight' => 0])
		], [41 => ['sku' => 'CAM-AZ-M', 'category' => 'Camisetas'], 50 => ['sku' => 'EBOOK', 'category' => 'Livros']], 'DESCONTO10')->toArray();

		self::assertIsArray($request['ShippingItemArray']);
		self::assertCount(1, $request['ShippingItemArray']);
		self::assertIsArray($request['ShippingItemArray'][0]);
		self::assertSame('CAM-AZ-M', $request['ShippingItemArray'][0]['SKU']);
		self::assertSame(0.3, $request['ShippingItemArray'][0]['Weight']);
		self::assertSame(180.0, $request['ShipmentInvoiceValue']);
		self::assertSame('DESCONTO10', $request['Coupom']);
	}

	public function testSendsTheDecodedModelWhenTheCatalogHasNoDetailsForTheProduct(): void {
		$items = $this->items([$this->product(['product_id' => 99, 'model' => 'A&amp;B-01'])]);

		self::assertSame('A&B-01', $items[0]['SKU']);
		self::assertSame('', $items[0]['Category']);
	}

	public function testKeepsOnlyTheProductsThatRequireShipping(): void {
		$products = [
			'a' => $this->product(['product_id' => 41]),
			'b' => $this->product(['product_id' => 50, 'shipping' => '0']),
			'c' => $this->product(['product_id' => 51, 'quantity' => 0])
		];

		self::assertSame(['a'], array_keys($this->createBuilder()->filterShipped($products)));
	}

	public function testRefusesACartWithoutProductsToShip(): void {
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('No product in the cart requires shipping');

		$this->createBuilder()->build(['postcode' => '20040020'], [$this->product(['shipping' => '0'])], [], '');
	}

	public function testFallsBackToBrazilWhenTheAddressHasNoCountry(): void {
		$request = $this->createBuilder()->build(['postcode' => '20040020', 'iso_code_2' => ''], [$this->product([])], [], '')->toArray();

		self::assertSame('BR', $request['RecipientCountry']);
	}

	public function testRefusesAWeightClassThatDoesNotExist(): void {
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('The weight class mapped to kilograms does not exist: 99');

		$this->createBuilder([], null, 99)->build(['postcode' => '20040020'], [$this->product([])], [], '');
	}

	public function testRefusesALengthClassThatDoesNotExist(): void {
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('The length class mapped to centimetres does not exist: 9');

		$this->createBuilder([], null, self::KG, 9)->build(['postcode' => '20040020'], [$this->product([])], [], '');
	}

	public function testAsksToChooseTheKilogramClassWhenTheStoreHasNone(): void {
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('The store has no weight class with the unit kg: choose the kilogram class in the Frenet settings');

		$this->createBuilder([], null, 0)->build(['postcode' => '20040020'], [$this->product([])], [], '');
	}

	public function testAsksToChooseTheCentimetreClassWhenTheStoreHasNone(): void {
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('The store has no length class with the unit cm: choose the centimetre class in the Frenet settings');

		$this->createBuilder([], null, self::KG, 0)->build(['postcode' => '20040020'], [$this->product([])], [], '');
	}

	/**
	 * Items of a request for the products.
	 *
	 * @param array<int, array<string, mixed>> $products
	 * @param Builder|null                     $builder
	 *
	 * @return array<int, array<mixed>>
	 */
	private function items(array $products, ?Builder $builder = null): array {
		$items = ($builder ?? $this->createBuilder())->build(['postcode' => '20040020', 'iso_code_2' => 'BR'], $products, [], '')->toArray()['ShippingItemArray'];

		self::assertIsArray($items);

		return array_values(array_filter($items, 'is_array'));
	}

	/**
	 * Builder with the classes of a default OpenCart 4 store and the given settings.
	 *
	 * @param array<string, mixed> $settings
	 * @param Weight|null          $weight
	 * @param int                  $weight_class_id
	 * @param int                  $length_class_id
	 *
	 * @return Builder
	 */
	private function createBuilder(array $settings = [], ?Weight $weight = null, int $weight_class_id = self::KG, int $length_class_id = self::CM): Builder {
		$core = new CoreConfig();

		foreach ($settings + ['shipping_frenet_postcode' => '01310-100'] as $key => $value) {
			$core->set($key, $value);
		}

		return new Builder(
			new Config($core),
			$weight ?? new Weight([self::KG => ['unit' => 'kg', 'value' => 1.0], self::G => ['unit' => 'g', 'value' => 1000.0], self::LB => ['unit' => 'lb', 'value' => 2.2046], self::OZ => ['unit' => 'oz', 'value' => 35.274]]),
			new Length([self::CM => ['unit' => 'cm', 'value' => 1.0], self::MM => ['unit' => 'mm', 'value' => 10.0], self::INCH => ['unit' => 'in', 'value' => 0.3937]]),
			$weight_class_id,
			$length_class_id,
			'4.1.0.4'
		);
	}

	/**
	 * Cart::getProducts() line, where weight and total are the totals of the line.
	 *
	 * @param array<string, mixed> $values
	 *
	 * @return array<string, mixed>
	 */
	private function product(array $values): array {
		return $values + [
			'product_id'      => 40,
			'model'           => 'product 11',
			'shipping'        => '1',
			'quantity'        => 1,
			'price'           => 101,
			'total'           => 101,
			'weight'          => 1,
			'weight_class_id' => self::KG,
			'length'          => 16,
			'height'          => 2,
			'width'           => 11,
			'length_class_id' => self::CM
		];
	}
}
