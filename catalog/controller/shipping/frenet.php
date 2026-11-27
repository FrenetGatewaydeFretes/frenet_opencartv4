<?php
namespace Opencart\Catalog\Controller\Extension\Opencartfrenetv4\Shipping;

use Frenet\Config as FrenetConfig;
use Frenet\Log as FrenetLog;
use Frenet\Platform;
use Frenet\Postcode;
use Frenet\Simulator\Cart as SimulatorCart;
use Frenet\Simulator\ClientIp;
use Frenet\Simulator\Origin;
use Frenet\Simulator\RateLimit;
use Opencart\System\Engine\Controller;
use Opencart\System\Library\Log as CoreLog;
use Throwable;

/**
 * Shipping simulator of the product page: the block added after the product form, its postcode and its quote routes.
 */
class Frenet extends Controller {
	/**
	 * @var string
	 */
	private const ROUTE = 'extension/opencartfrenetv4/shipping/frenet';

	/**
	 * @var string
	 */
	private const FORM_MARKER = 'id="form-product"';

	/**
	 * @var string
	 */
	private const FORM_END = '</form>';

	/**
	 * Adds the simulator after the form of the product page, as the catalog/view/product/product/after event.
	 *
	 * @param string               $route
	 * @param array<string, mixed> $data
	 * @param string               $output
	 *
	 * @return void
	 */
	public function addSimulator(string &$route, array &$data, string &$output): void {
		$this->autoloader->register('Frenet', DIR_EXTENSION . 'opencartfrenetv4/system/library/frenet/');

		$product_id = (int)($data['product_id'] ?? 0);

		if (!$product_id || !$this->isSimulatorAvailable(new FrenetConfig($this->config))) {
			return;
		}

		$marker = strpos($output, self::FORM_MARKER);
		$position = $marker !== false ? strpos($output, self::FORM_END, $marker) : false;

		if ($position === false) {
			return;
		}

		$this->load->model('catalog/product');

		$product = $this->model_catalog_product->getProduct($product_id);

		if (!$product || !$product['shipping']) {
			return;
		}

		$this->load->language(self::ROUTE);

		$platform = new Platform(VERSION);
		$language = 'language=' . $this->config->get('config_language');

		$simulator = $this->load->view(self::ROUTE . '_simulator', [
			'quote_url'    => $this->url->link($platform->getRoute(self::ROUTE, 'quote'), $language),
			'postcode_url' => $this->url->link($platform->getRoute(self::ROUTE, 'postcode'), $language),
			'autofill'     => $this->customer->isLogged()
		]);

		$output = substr_replace($output, $simulator, $position + strlen(self::FORM_END), 0);
	}

	/**
	 * Postcode of the buyer for the simulator field: the shipping address of the session, else the default address of the customer.
	 *
	 * @return void
	 */
	public function postcode(): void {
		$this->autoloader->register('Frenet', DIR_EXTENSION . 'opencartfrenetv4/system/library/frenet/');

		$postcode = null;

		if ($this->isSimulatorAvailable(new FrenetConfig($this->config))) {
			$postcode = $this->getBuyerPostcode();
		}

		$this->response->addHeader('Cache-Control: no-store, private');

		$this->respond(['postcode' => (string)$postcode]);
	}

	/**
	 * Quotes the product of the page for the postcode typed, with the rules of the cart and the limit of quotes per IP.
	 *
	 * @return void
	 */
	public function quote(): void {
		$this->autoloader->register('Frenet', DIR_EXTENSION . 'opencartfrenetv4/system/library/frenet/');

		$this->load->language(self::ROUTE);

		$config = new FrenetConfig($this->config);

		if (!$this->isSimulatorAvailable($config)) {
			$this->respond(['error' => ['warning' => $this->language->get('error_simulator_disabled')]]);

			return;
		}

		if (Origin::isForeign($this->request->server, [$this->config->get('config_url')])) {
			$this->response->addHeader($this->getProtocol() . ' 403 Forbidden');

			$this->respond(['error' => ['warning' => $this->language->get('error_unavailable')]]);

			return;
		}

		$log = new FrenetLog(new CoreLog(FrenetLog::FILENAME), $config->isDebug());
		$retry_after = (new RateLimit($this->db, DB_PREFIX, $log, $config->getSimulatorLimit()))->hit(ClientIp::fromServer($this->request->server));

		if ($retry_after > 0) {
			$this->response->addHeader($this->getProtocol() . ' 429 Too Many Requests');
			$this->response->addHeader('Retry-After: ' . $retry_after);

			$this->respond(['error' => ['warning' => $this->language->get('error_simulator_limit')]]);

			return;
		}

		try {
			$json = $this->simulate($log);
		} catch (Throwable $exception) {
			$log->error('Simulator quote failed: ' . $exception->getMessage(), ['exception' => $exception::class, 'at' => $exception->getFile() . ':' . $exception->getLine()]);

			$json = ['error' => ['warning' => $this->language->get('error_unavailable')]];
		}

		$this->respond($json);
	}

	/**
	 * Rates of the product for the postcode, or the error of each field the buyer must fix, keyed like the cart button errors.
	 *
	 * @param FrenetLog $log
	 *
	 * @return array<string, mixed>
	 */
	private function simulate(FrenetLog $log): array {
		$post = $this->request->post;
		$typed = $post['postcode'] ?? '';
		$postcode = Postcode::getDigits(is_string($typed) ? $typed : '');
		$state = Postcode::getState($postcode);

		if ($state === '') {
			return ['error' => ['frenet_postcode' => $this->language->get('error_simulator_postcode')]];
		}

		$product_id = (int)($post['product_id'] ?? 0);
		$quantity = isset($post['quantity']) && is_scalar($post['quantity']) ? (int)$post['quantity'] : 1;
		$options = isset($post['option']) && is_array($post['option']) ? array_filter($post['option']) : [];
		$subscription_plan_id = isset($post['subscription_plan_id']) && is_scalar($post['subscription_plan_id']) ? (int)$post['subscription_plan_id'] : 0;

		if ($quantity < 1) {
			return ['error' => ['quantity' => $this->language->get('error_simulator_quantity')]];
		}

		$this->load->model('catalog/product');

		$product_info = $this->model_catalog_product->getProduct($product_id);

		if (!$product_info || !$product_info['shipping']) {
			return ['error' => ['warning' => $this->language->get('error_simulator_product')]];
		}

		$platform = new Platform(VERSION);
		$override = $product_info['override']['variant'] ?? [];
		$options = array_replace($options, $platform->getVariantOptions((array)($product_info['variant'] ?? []), is_array($override) ? $override : []));
		$cart_product_id = $product_info['master_id'] && !$platform->addsVariantToCart() ? (int)$product_info['master_id'] : (int)$product_info['product_id'];

		$errors = $this->getChoiceErrors($product_info, $options, $subscription_plan_id, $cart_product_id);

		if ($errors) {
			return ['error' => $errors];
		}

		$minimum = max(1, (int)$product_info['minimum']);

		if ($quantity < $minimum) {
			return ['error' => ['quantity' => sprintf($this->language->get('error_simulator_minimum'), $minimum)]];
		}

		$product = (new SimulatorCart($this->registry, $this->db, DB_PREFIX))->getProduct($cart_product_id, $quantity, $options, $subscription_plan_id);

		if ($product === null) {
			$log->error('Simulator could not read the product through the cart of the store, which may have been changed by another extension', ['product_id' => $cart_product_id]);

			return ['error' => ['warning' => $this->language->get('error_simulator_product')]];
		}

		$this->load->model(self::ROUTE);

		$address = $this->model_extension_opencartfrenetv4_shipping_frenet->getPostcodeAddress($postcode, $state);

		$this->setTaxAddresses($address);

		$method = $this->model_extension_opencartfrenetv4_shipping_frenet->getProductQuote($address, $product);

		if (!$method) {
			return ['error' => ['warning' => $this->language->get('error_no_services')]];
		}

		if ($method['error']) {
			return ['error' => ['warning' => $method['error']]];
		}

		$rates = [];

		foreach ($method['quote'] as $rate) {
			$rates[] = [
				'title' => html_entity_decode((string)$rate['title'], ENT_QUOTES, 'UTF-8'),
				'text'  => html_entity_decode((string)$rate['text'], ENT_QUOTES, 'UTF-8')
			];
		}

		return ['quote' => $rates];
	}

	/**
	 * Errors of the required options left empty and of the subscription not chosen, as the cart button checks them.
	 *
	 * @param array<string, mixed>     $product_info
	 * @param array<int|string, mixed> $options
	 * @param int                      $subscription_plan_id
	 * @param int                      $cart_product_id
	 *
	 * @return array<string, string>
	 */
	private function getChoiceErrors(array $product_info, array $options, int $subscription_plan_id, int $cart_product_id): array {
		$errors = [];

		foreach ($this->model_catalog_product->getOptions((int)($product_info['master_id'] ?: $product_info['product_id'])) as $option) {
			if ($option['required'] && empty($options[$option['product_option_id']])) {
				$errors['option_' . $option['product_option_id']] = sprintf($this->language->get('error_simulator_option'), html_entity_decode((string)$option['name'], ENT_QUOTES, 'UTF-8'));
			}
		}

		$subscriptions = $this->model_catalog_product->getSubscriptions($cart_product_id);

		if ($subscriptions && !in_array($subscription_plan_id, array_map('intval', array_column($subscriptions, 'subscription_plan_id')), true)) {
			$errors['subscription'] = $this->language->get('error_simulator_subscription');
		}

		return $errors;
	}

	/**
	 * Sets the tax addresses as the startup of the core does, with the postcode typed as the shipping address.
	 *
	 * @param array<string, mixed> $address
	 *
	 * @return void
	 */
	private function setTaxAddresses(array $address): void {
		$this->tax->clear();
		$this->tax->setShippingAddress((int)$address['country_id'], (int)$address['zone_id']);

		if (isset($this->session->data['payment_address']['country_id'], $this->session->data['payment_address']['zone_id'])) {
			$this->tax->setPaymentAddress((int)$this->session->data['payment_address']['country_id'], (int)$this->session->data['payment_address']['zone_id']);
		} elseif ($this->config->get('config_tax_default') == 'payment') {
			$this->tax->setPaymentAddress((int)$this->config->get('config_country_id'), (int)$this->config->get('config_zone_id'));
		}

		$this->tax->setStoreAddress((int)$this->config->get('config_country_id'), (int)$this->config->get('config_zone_id'));
	}

	/**
	 * Postcode of the shipping address of the session, else of the default address of the logged customer, or null.
	 *
	 * @return string|null
	 */
	private function getBuyerPostcode(): ?string {
		$postcode = Postcode::format((string)($this->session->data['shipping_address']['postcode'] ?? ''));

		if ($postcode !== null || !$this->customer->isLogged()) {
			return $postcode;
		}

		$this->load->model(self::ROUTE);

		return Postcode::format($this->model_extension_opencartfrenetv4_shipping_frenet->getAddressPostcode((int)$this->customer->getId(), (int)$this->customer->getAddressId()));
	}

	/**
	 * Whether the store quotes with Frenet and shows the simulator.
	 *
	 * @param FrenetConfig $config
	 *
	 * @return bool
	 */
	private function isSimulatorAvailable(FrenetConfig $config): bool {
		return $config->isEnabled() && $config->isSimulatorEnabled() && $config->getToken() !== '';
	}

	/**
	 * Protocol of the request for the status line, HTTP/1.1 when the server does not tell it.
	 *
	 * @return string
	 */
	private function getProtocol(): string {
		$protocol = $this->request->server['SERVER_PROTOCOL'] ?? '';

		return is_string($protocol) && preg_match('~^HTTP/\d(\.\d)?$~', $protocol) === 1 ? $protocol : 'HTTP/1.1';
	}

	/**
	 * Answers JSON.
	 *
	 * @param array<string, mixed> $json
	 *
	 * @return void
	 */
	private function respond(array $json): void {
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput((string)json_encode($json));
	}
}
