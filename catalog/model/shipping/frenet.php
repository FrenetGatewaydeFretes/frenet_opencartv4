<?php
namespace Opencart\Catalog\Model\Extension\Opencartfrenetv4\Shipping;

use Frenet\Api;
use Frenet\Config as FrenetConfig;
use Frenet\Http\Client;
use Frenet\Log as FrenetLog;
use Frenet\Platform;
use Frenet\Postcode;
use Frenet\Quote\Builder;
use Frenet\Quote\Catalog;
use Frenet\Quote\Service;
use Frenet\Quote\UnavailableException;
use Frenet\UnitClass;
use Opencart\System\Engine\Model;
use Opencart\System\Library\Log as CoreLog;
use Throwable;

/**
 * Frenet shipping method: quotes the cart through the Frenet API and offers each service at checkout.
 */
class Frenet extends Model {
	/**
	 * Shipping method with one option per service the Frenet API offers, or a message in the Frenet block when the quote fails or no service serves the address.
	 *
	 * @param array<string, mixed> $address
	 *
	 * @return array<string, mixed>
	 */
	public function getQuote(array $address): array {
		$this->autoloader->register('Frenet', DIR_EXTENSION . 'opencartfrenetv4/system/library/frenet/');

		$config = new FrenetConfig($this->config);
		$log = new FrenetLog(new CoreLog(FrenetLog::FILENAME), $config->isDebug());

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
			$products = $this->cart->getProducts();
			$request = $builder->build($address, $products, $this->getCatalog($builder->filterShipped($products), $log), (string)($this->session->data['coupon'] ?? ''));
			$result = (new Api(new Client(), $log, $config->getToken(), VERSION, $config->getTimeout(), $config->getAttempts()))->quote($request);
		} catch (Throwable $exception) {
			$context = $exception instanceof UnavailableException ? [] : ['exception' => $exception::class, 'at' => $exception->getFile() . ':' . $exception->getLine()];
			$context += $request !== null ? ['request' => $request->toArray()] : ['postcode' => $address['postcode'] ?? ''];

			$log->error('Quote failed: ' . $exception->getMessage(), $context);

			return $this->getMethod($config, [], $this->language->get('error_unavailable'));
		}

		if ($result->getErrors() !== []) {
			$log->error('Services with an error were left out of the quote', ['services' => $result->getErrors(), 'request' => $request->toArray()]);
		}

		if ($result->getServices() === []) {
			$log->error('Quote returned no service' . ($result->getMessage() !== '' ? ': ' . $result->getMessage() : ''), ['request' => $request->toArray()]);

			return $this->getMethod($config, [], $this->language->get('error_no_services'));
		}

		$template = $config->isDeliveryTimeShown() ? ($config->getDeliveryTimeMessage() ?: $this->language->get('text_delivery_time')) : '';

		return $this->getMethod($config, $this->getOptions($result->getServices(), $template, $config->getAdditionalDays(), $config->getTaxClassId()), false);
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
