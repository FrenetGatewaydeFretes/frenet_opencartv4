<?php
namespace Opencart\Catalog\Model\Extension\Opencartfrenetv4\Shipping;

use Frenet\Api;
use Frenet\Config as FrenetConfig;
use Frenet\Http\Client;
use Frenet\Log as FrenetLog;
use Frenet\Quote\Item;
use Frenet\Quote\Request;
use Frenet\Quote\Service;
use Frenet\Quote\UnavailableException;
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

		$request = null;

		try {
			$request = $this->createRequest($config, $address);
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

		return $this->getMethod($config, $this->getOptions($result->getServices(), $config->getDeliveryTimeMessage()), false);
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
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function getOptions(array $services, string $template): array {
		$options = [];

		foreach ($services as $service) {
			$name = $service->getTitle($template);

			$options[$service->getCode()] = [
				'code'         => 'frenet.' . $service->getCode(),
				'title'        => $name,
				'name'         => $name,
				'cost'         => $service->getPrice(),
				'tax_class_id' => 0,
				'text'         => $this->currency->format($this->tax->calculate($service->getPrice(), 0, (bool)$this->config->get('config_tax')), $this->session->data['currency'])
			];
		}

		return $options;
	}

	/**
	 * Quote request for the products in the cart and the shipping address.
	 *
	 * @param FrenetConfig         $config
	 * @param array<string, mixed> $address
	 *
	 * @return Request
	 */
	private function createRequest(FrenetConfig $config, array $address): Request {
		$this->load->model('localisation/country');

		$country = $this->model_localisation_country->getCountry((int)($address['country_id'] ?? 0));
		$items = [];

		foreach ($this->cart->getProducts() as $product) {
			$quantity = max(1, (int)$product['quantity']);

			$items[] = new Item(
				$this->getWeightInKg((int)$product['weight_class_id'], (float)$product['weight']) / $quantity,
				$this->getLengthInCm((int)$product['length_class_id'], (float)$product['length']),
				$this->getLengthInCm((int)$product['length_class_id'], (float)$product['height']),
				$this->getLengthInCm((int)$product['length_class_id'], (float)$product['width']),
				$quantity
			);
		}

		return new Request(
			$config->getOriginPostcode(),
			(string)preg_replace('/\D/', '', (string)($address['postcode'] ?? '')),
			(string)($country['iso_code_2'] ?? 'BR'),
			(float)$this->cart->getSubTotal(),
			(string)($this->session->data['coupon'] ?? ''),
			$items,
			VERSION
		);
	}

	/**
	 * Weight in kilograms, converting only grams as version 1.0.0 did.
	 *
	 * @param int   $weight_class_id
	 * @param float $weight
	 *
	 * @return float
	 */
	private function getWeightInKg(int $weight_class_id, float $weight): float {
		$query = $this->db->query("SELECT `wcd`.`unit` FROM `" . DB_PREFIX . "weight_class_description` `wcd` WHERE `wcd`.`weight_class_id` = '" . $weight_class_id . "' AND `wcd`.`language_id` = '" . (int)$this->config->get('config_language_id') . "'");

		return ($query->row['unit'] ?? '') === 'g' ? $weight / 1000 : $weight;
	}

	/**
	 * Measure in centimetres, converting only millimetres as version 1.0.0 did.
	 *
	 * @param int   $length_class_id
	 * @param float $length
	 *
	 * @return float
	 */
	private function getLengthInCm(int $length_class_id, float $length): float {
		$query = $this->db->query("SELECT `lcd`.`unit` FROM `" . DB_PREFIX . "length_class_description` `lcd` WHERE `lcd`.`length_class_id` = '" . $length_class_id . "' AND `lcd`.`language_id` = '" . (int)$this->config->get('config_language_id') . "'");

		return ($query->row['unit'] ?? '') === 'mm' ? $length / 10 : $length;
	}
}
