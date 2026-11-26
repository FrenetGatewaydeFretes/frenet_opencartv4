<?php
namespace Opencart\Admin\Controller\Extension\Opencartfrenetv4\Shipping;

use Frenet\Config as FrenetConfig;
use Frenet\Module;

class Frenet extends \Opencart\System\Engine\Controller {
	/**
	 * @var array<int, string>
	 */
	private const LEGACY_SETTINGS = [
		'shipping_frenet_contrato_codigo',
		'shipping_frenet_contrato_senha',
		'shipping_frenet_contrato_token'
	];

	private $error = array();

	public function index() {
		$this->autoloader->register('Frenet', DIR_EXTENSION . 'opencartfrenetv4/system/library/frenet/');

		$data = $this->load->language('extension/opencartfrenetv4/shipping/frenet');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if ($this->request->server['REQUEST_METHOD'] != 'POST') {
			$this->migrateLegacySettings();
		}

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('shipping_frenet', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true));
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['postcode'])) {
			$data['error_postcode'] = $this->error['postcode'];
		} else {
			$data['error_postcode'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/opencartfrenetv4/shipping/frenet', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/opencartfrenetv4/shipping/frenet', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true);

		if (isset($this->request->post['shipping_frenet_status'])) {
			$data['shipping_frenet_status'] = $this->request->post['shipping_frenet_status'];
		} else {
			$data['shipping_frenet_status'] = $this->config->get('shipping_frenet_status');
		}

		if (isset($this->request->post['shipping_frenet_postcode'])) {
			$data['shipping_frenet_postcode'] = $this->request->post['shipping_frenet_postcode'];
		} else {
			$data['shipping_frenet_postcode'] = $this->config->get('shipping_frenet_postcode');
		}

		if (isset($this->request->post['shipping_frenet_msg_prazo'])) {
			$data['shipping_frenet_msg_prazo'] = $this->request->post['shipping_frenet_msg_prazo'];
		} else {
			$data['shipping_frenet_msg_prazo'] = $this->config->get('shipping_frenet_msg_prazo');
		}

		if (isset($this->request->post['shipping_frenet_token'])) {
			$data['shipping_frenet_token'] = $this->request->post['shipping_frenet_token'];
		} else {
			$data['shipping_frenet_token'] = $this->model_setting_setting->getValue('shipping_frenet_token');
		}

		if (isset($this->request->post['shipping_frenet_sort_order'])) {
			$data['shipping_frenet_sort_order'] = $this->request->post['shipping_frenet_sort_order'];
		} else {
			$data['shipping_frenet_sort_order'] = $this->config->get('shipping_frenet_sort_order');
		}

		$config = new FrenetConfig($this->config);
		$settings = [
			'shipping_frenet_timeout'  => $config->getTimeout(),
			'shipping_frenet_attempts' => $config->getAttempts(),
			'shipping_frenet_debug'    => (int)$config->isDebug()
		];

		foreach ($settings as $key => $value) {
			$data[$key] = $this->request->post[$key] ?? $value;
		}

		$data['timeout_min'] = FrenetConfig::TIMEOUT_MIN;
		$data['timeout_max'] = FrenetConfig::TIMEOUT_MAX;
		$data['attempts_min'] = FrenetConfig::ATTEMPTS_MIN;
		$data['attempts_max'] = FrenetConfig::ATTEMPTS_MAX;

		$data['text_version'] = sprintf($this->language->get('text_version'), Module::VERSION);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/opencartfrenetv4/shipping/frenet', $data));
	}

	/**
	 * Moves the token saved by version 1.0.0 to shipping_frenet_token and removes the settings of 1.0.0 that are no longer used.
	 *
	 * @return void
	 */
	private function migrateLegacySettings(): void {
		$settings = $this->model_setting_setting->getSetting('shipping_frenet');
		$legacy = array_intersect_key($settings, array_flip(self::LEGACY_SETTINGS));

		if (!$legacy) {
			return;
		}

		$token = trim((string)($settings['shipping_frenet_token'] ?? ''));

		if ($token === '') {
			$settings['shipping_frenet_token'] = trim((string)($settings['shipping_frenet_contrato_token'] ?? ''));
		}

		$this->model_setting_setting->editSetting('shipping_frenet', array_diff_key($settings, $legacy));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/opencartfrenetv4/shipping/frenet')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}
