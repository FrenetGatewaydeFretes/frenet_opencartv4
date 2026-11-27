<?php
namespace Opencart\Catalog\Model\Extension\Opencartfrenetv4\Shipping;

use Frenet\Api;
use Frenet\Config as FrenetConfig;
use Frenet\GeoZone;
use Frenet\Http\Client;
use Frenet\Log as FrenetLog;
use Frenet\Platform;
use Frenet\Postcode;
use Frenet\Quote\Builder;
use Frenet\Quote\Cache as QuoteCache;
use Frenet\Quote\Catalog;
use Frenet\Quote\Service;
use Frenet\Quote\UnavailableException;
use Frenet\UnitClass;
use Opencart\System\Engine\Model;
use Opencart\System\Library\Log as CoreLog;
use Throwable;

/**
 * Frenet shipping method: quotes the cart, or the product page alone, through the Frenet API and offers each service.
 */
class Frenet extends Model {
	/**
	 * @var string
	 */
	private const FILE_CACHE = 'file';

	/**
	 * @var int
	 */
	private const SIMULATION_ATTEMPTS = 1;

	/**
	 * Shipping method with one option per service the Frenet API offers, or a message in the Frenet block when the quote fails or no service serves the address.
	 *
	 * @param array<string, mixed> $address
	 *
	 * @return array<string, mixed>
	 */
	public function getQuote(array $address): array {
		$this->autoloader->register('Frenet', DIR_EXTENSION . 'opencartfrenetv4/system/library/frenet/');

		return $this->quote($address, $this->cart->getProducts(), false);
	}

	/**
	 * Quotes the page product alone as the cart would, with one attempt and without filling a file cache.
	 *
	 * @param array<string, mixed> $address
	 * @param array<string, mixed> $product
	 *
	 * @return array<string, mixed>
	 */
	public function getProductQuote(array $address, array $product): array {
		return $this->quote($address, [$product], true);
	}

	/**
	 * Shipping address of a Brazilian postcode with the country and state of the store, as the cart shipping estimate builds it.
	 *
	 * @param string $postcode
	 * @param string $state
	 *
	 * @return array<string, mixed>
	 */
	public function getPostcodeAddress(string $postcode, string $state): array {
		$this->load->model('localisation/country');
		$this->load->model('localisation/zone');

		$country = $this->findRow($this->model_localisation_country->getCountries(), 'iso_code_2', Postcode::COUNTRY);
		$zone = $country ? $this->findRow($this->model_localisation_zone->getZonesByCountryId((int)$country['country_id']), 'code', $state) : [];

		return [
			'firstname'      => '',
			'lastname'       => '',
			'company'        => '',
			'address_1'      => '',
			'address_2'      => '',
			'postcode'       => (string)Postcode::format($postcode),
			'city'           => '',
			'zone_id'        => (int)($zone['zone_id'] ?? 0),
			'zone'           => (string)($zone['name'] ?? ''),
			'zone_code'      => (string)($zone['code'] ?? $state),
			'country_id'     => (int)($country['country_id'] ?? 0),
			'country'        => (string)($country['name'] ?? ''),
			'iso_code_2'     => Postcode::COUNTRY,
			'iso_code_3'     => (string)($country['iso_code_3'] ?? 'BRA'),
			'address_format' => (string)($country['address_format'] ?? '')
		];
	}

	/**
	 * Postcode of an address of the customer, or an empty string when the customer has no such address.
	 *
	 * @param int $customer_id
	 * @param int $address_id
	 *
	 * @return string
	 */
	public function getAddressPostcode(int $customer_id, int $address_id): string {
		$query = $this->db->query("SELECT `postcode` FROM `" . DB_PREFIX . "address` WHERE `address_id` = '" . $address_id . "' AND `customer_id` = '" . $customer_id . "'");

		return (string)($query->row['postcode'] ?? '');
	}

	/**
	 * Frenet shipping method for the products, at checkout or in the simulator, where API answer problems go to the log only in debug.
	 *
	 * @param array<string, mixed>                    $address
	 * @param array<int|string, array<string, mixed>> $products
	 * @param bool                                    $simulation
	 *
	 * @return array<string, mixed>
	 */
	private function quote(array $address, array $products, bool $simulation): array {
		$config = new FrenetConfig($this->config);
		$log = new FrenetLog(new CoreLog(FrenetLog::FILENAME), $config->isDebug());

		if (!$this->isInGeoZone($config->getGeoZoneId(), $address, $log)) {
			return [];
		}

		if ($config->getToken() === '') {
			$log->error('Quote skipped: the token is not configured', ['postcode' => $address['postcode'] ?? '']);

			return [];
		}

		$this->load->language('extension/opencartfrenetv4/shipping/frenet');

		$address['iso_code_2'] = $this->getCountryCode($address);

		if (!Postcode::isQuotable((string)($address['postcode'] ?? ''), $address['iso_code_2'])) {
			$log->debug('Quote skipped: the address has no valid postcode', ['postcode' => $address['postcode'] ?? '', 'country' => $address['iso_code_2']]);

			return $this->getMethod($config, [], $this->language->get('error_postcode'));
		}

		$request = null;

		try {
			$units = new UnitClass($this->db, DB_PREFIX);
			$builder = new Builder($config, $this->weight, $this->length, $config->getWeightClassId() ?: $units->findWeightClassId(), $config->getLengthClassId() ?: $units->findLengthClassId(), VERSION);
			$request = $builder->build($address, $products, $this->getCatalog($builder->filterShipped($products), $log), (string)($this->session->data['coupon'] ?? ''));
			$cache = new QuoteCache($this->cache, !$simulation || $this->config->get('cache_engine') !== self::FILE_CACHE);
			$result = (new Api(new Client(), $cache, $log, $config->getToken(), VERSION, $config->getTimeout(), $simulation ? self::SIMULATION_ATTEMPTS : $config->getAttempts()))->quote($request);
		} catch (Throwable $exception) {
			$context = $exception instanceof UnavailableException ? [] : ['exception' => $exception::class, 'at' => $exception->getFile() . ':' . $exception->getLine()];
			$context += $request !== null ? ['request' => $request->toArray()] : ['postcode' => $address['postcode'] ?? ''];

			$log->error('Quote failed: ' . $exception->getMessage(), $context);

			return $this->getMethod($config, [], $this->language->get('error_unavailable'));
		}

		if ($result->getErrors() !== []) {
			$this->logAnswer($log, $simulation, 'Services with an error were left out of the quote', ['services' => $result->getErrors(), 'request' => $request->toArray()]);
		}

		if ($result->getServices() === []) {
			$this->logAnswer($log, $simulation, 'Quote returned no service' . ($result->getMessage() !== '' ? ': ' . $result->getMessage() : ''), ['request' => $request->toArray()]);

			return $this->getMethod($config, [], $this->language->get('error_no_services'));
		}

		$template = $config->isDeliveryTimeShown() ? ($config->getDeliveryTimeMessage() ?: $this->language->get('text_delivery_time')) : '';

		return $this->getMethod($config, $this->getOptions($result->getServices(), $template, $config->getAdditionalDays(), $config->getTaxClassId()), false);
	}

	/**
	 * Logs a problem of the API answer as an error at checkout and only in debug for the simulator, which buyers call far more.
	 *
	 * @param FrenetLog            $log
	 * @param bool                 $simulation
	 * @param string               $message
	 * @param array<string, mixed> $context
	 *
	 * @return void
	 */
	private function logAnswer(FrenetLog $log, bool $simulation, string $message, array $context): void {
		if ($simulation) {
			$log->debug($message, $context);
		} else {
			$log->error($message, $context);
		}
	}

	/**
	 * Row whose field has the value, or an empty array when none has.
	 *
	 * @param array<int, array<string, mixed>> $rows
	 * @param string                           $field
	 * @param string                           $value
	 *
	 * @return array<string, mixed>
	 */
	private function findRow(array $rows, string $field, string $value): array {
		foreach ($rows as $row) {
			if ((string)($row[$field] ?? '') === $value) {
				return $row;
			}
		}

		return [];
	}

	/**
	 * Frenet shipping method, named in both the title key read up to 4.0.1.1 and the name key read from 4.0.2.0.
	 *
	 * @param FrenetConfig                        $config
	 * @param array<string, array<string, mixed>> $options
	 * @param false|string                        $error
	 *
	 * @return array<string, mixed>
	 */
	private function getMethod(FrenetConfig $config, array $options, false|string $error): array {
		$title = $this->language->get('text_title');

		return [
			'code'       => 'frenet',
			'title'      => $title,
			'name'       => $title,
			'quote'      => $options,
			'sort_order' => $config->getSortOrder(),
			'error'      => $error
		];
	}

	/**
	 * One checkout option per service, named in both the title key read up to 4.0.1.1 and the name key read from 4.0.2.0.
	 *
	 * @param array<int, Service> $services
	 * @param string              $template
	 * @param int                 $additional_days
	 * @param int                 $tax_class_id
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function getOptions(array $services, string $template, int $additional_days, int $tax_class_id): array {
		$options = [];

		foreach ($services as $service) {
			$name = $service->getTitle($template, $additional_days);

			$options[$service->getCode()] = [
				'code'         => 'frenet.' . $service->getCode(),
				'title'        => $name,
				'name'         => $name,
				'cost'         => $service->getPrice(),
				'tax_class_id' => $tax_class_id,
				'text'         => $this->currency->format($this->tax->calculate($service->getPrice(), $tax_class_id, (bool)$this->config->get('config_tax')), $this->session->data['currency'])
			];
		}

		return $options;
	}

	/**
	 * Whether Frenet serves the address: any address without a geo zone, otherwise only the ones inside it.
	 *
	 * @param int                  $geo_zone_id
	 * @param array<string, mixed> $address
	 * @param FrenetLog            $log
	 *
	 * @return bool
	 */
	private function isInGeoZone(int $geo_zone_id, array $address, FrenetLog $log): bool {
		if ($geo_zone_id === 0) {
			return true;
		}

		$geo_zone = new GeoZone($this->db, DB_PREFIX);

		if ($geo_zone->contains($geo_zone_id, $address)) {
			return true;
		}

		if ($geo_zone->exists($geo_zone_id)) {
			$log->debug('Quote skipped: the address is outside the Frenet geo zone', ['country_id' => $address['country_id'] ?? 0, 'zone_id' => $address['zone_id'] ?? 0]);
		} else {
			$log->error('Quote skipped: the geo zone ' . $geo_zone_id . ' chosen for Frenet no longer exists, choose another one in the settings');
		}

		return false;
	}

	/**
	 * SKU and categories of the shipped products, or none when they cannot be read, so the quote goes with the model as SKU.
	 *
	 * @param array<int|string, array<string, mixed>> $products
	 * @param FrenetLog                               $log
	 *
	 * @return array<int, array{sku: string, category: string}>
	 */
	private function getCatalog(array $products, FrenetLog $log): array {
		try {
			return (new Catalog($this->db, DB_PREFIX, (int)$this->config->get('config_language_id'), (int)$this->config->get('config_store_id'), (new Platform(VERSION))->hasSkuInProductCode()))->getProducts(array_column($products, 'product_id'));
		} catch (Throwable $exception) {
			$log->error('SKU and categories could not be read, quoting with the model only: ' . $exception->getMessage());

			return [];
		}
	}

	/**
	 * ISO code of the address country, read by its id when the address does not bring it, or empty when it has none.
	 *
	 * @param array<string, mixed> $address
	 *
	 * @return string
	 */
	private function getCountryCode(array $address): string {
		$code = trim((string)($address['iso_code_2'] ?? ''));

		if ($code !== '' || empty($address['country_id'])) {
			return $code;
		}

		$this->load->model('localisation/country');

		$country = $this->model_localisation_country->getCountry((int)$address['country_id']);

		return trim((string)($country['iso_code_2'] ?? ''));
	}
}
