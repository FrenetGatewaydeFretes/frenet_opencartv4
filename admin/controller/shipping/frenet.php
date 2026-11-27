<?php
namespace Opencart\Admin\Controller\Extension\Opencartfrenetv4\Shipping;

use Frenet\Config as FrenetConfig;
use Frenet\Module;
use Frenet\Platform;
use Frenet\Postcode;
use Frenet\UnitClass;
use Opencart\System\Engine\Controller;

/**
 * Frenet shipping settings: the form, its AJAX save with validation and the move of the settings of version 1.0.0.
 */
class Frenet extends Controller {
	/**
	 * @var string
	 */
	private const ROUTE = 'extension/opencartfrenetv4/shipping/frenet';

	/**
	 * @var string
	 */
	private const PANEL_URL = 'https://painel.frenet.com.br/';

	/**
	 * @var array<int, string>
	 */
	private const LEGACY_SETTINGS = [
		'shipping_frenet_contrato_codigo',
		'shipping_frenet_contrato_senha',
		'shipping_frenet_contrato_token'
	];

	/**
	 * @var array<int, string>
	 */
	private const FIELDS = [
		'shipping_frenet_status',
		'shipping_frenet_postcode',
		'shipping_frenet_delivery_time',
		'shipping_frenet_msg_prazo',
		'shipping_frenet_additional_days',
		'shipping_frenet_weight_class_id',
		'shipping_frenet_length_class_id',
		'shipping_frenet_minimum_height',
		'shipping_frenet_minimum_width',
		'shipping_frenet_minimum_length',
		'shipping_frenet_default_weight',
		'shipping_frenet_timeout',
		'shipping_frenet_attempts',
		'shipping_frenet_debug',
		'shipping_frenet_tax_class_id',
		'shipping_frenet_geo_zone_id',
		'shipping_frenet_sort_order'
	];

	/**
	 * Settings form, with the token never shown and the settings of version 1.0.0 moved on the first visit.
	 *
	 * @return void
	 */
	public function index(): void {
		$this->autoloader->register('Frenet', DIR_EXTENSION . 'opencartfrenetv4/system/library/frenet/');

		$this->load->language(self::ROUTE);

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		$data['migrated'] = $this->user->hasPermission('modify', self::ROUTE) && $this->migrateLegacySettings();

		$user_token = 'user_token=' . $this->session->data['user_token'];

		$data['breadcrumbs'] = [
			['text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', $user_token)],
			['text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', $user_token . '&type=shipping')],
			['text' => $this->language->get('heading_title'), 'href' => $this->url->link(self::ROUTE, $user_token)]
		];

		$platform = new Platform(VERSION);

		$data['save'] = $this->url->link($platform->getRoute(self::ROUTE, 'save'), $user_token);
		$data['alert_url'] = $this->url->link($platform->getRoute(self::ROUTE, 'alert'), $user_token);
		$data['back'] = $this->url->link('marketplace/extension', $user_token . '&type=shipping');

		$config = new FrenetConfig($this->config);
		$units = new UnitClass($this->db, DB_PREFIX);

		$data['alert'] = $this->load->view(self::ROUTE . '_alert', $this->getAlerts($config->getGeoZoneId()));
		$data['token_saved'] = $this->model_setting_setting->getValue('shipping_frenet_token') !== '';
		$data['shipping_frenet_status'] = $config->isEnabled();
		$data['shipping_frenet_postcode'] = Postcode::format($config->getOriginPostcode()) ?? $config->getOriginPostcode();
		$data['shipping_frenet_delivery_time'] = $config->isDeliveryTimeShown();
		$data['shipping_frenet_msg_prazo'] = $config->getDeliveryTimeMessage();
		$data['shipping_frenet_additional_days'] = $config->getAdditionalDays();
		$data['shipping_frenet_weight_class_id'] = $config->getWeightClassId() ?: $units->findWeightClassId();
		$data['shipping_frenet_length_class_id'] = $config->getLengthClassId() ?: $units->findLengthClassId();
		$data['shipping_frenet_minimum_height'] = $this->formatNumber($config->getMinimumHeight());
		$data['shipping_frenet_minimum_width'] = $this->formatNumber($config->getMinimumWidth());
		$data['shipping_frenet_minimum_length'] = $this->formatNumber($config->getMinimumLength());
		$data['shipping_frenet_default_weight'] = $this->formatNumber($config->getDefaultWeight());
		$data['shipping_frenet_timeout'] = $config->getTimeout();
		$data['shipping_frenet_attempts'] = $config->getAttempts();
		$data['shipping_frenet_debug'] = $config->isDebug();
		$data['shipping_frenet_tax_class_id'] = $config->getTaxClassId();
		$data['shipping_frenet_geo_zone_id'] = $config->getGeoZoneId();
		$data['shipping_frenet_sort_order'] = $config->getSortOrder();

		$this->load->model('localisation/weight_class');
		$this->load->model('localisation/length_class');
		$this->load->model('localisation/tax_class');
		$this->load->model('localisation/geo_zone');

		$data['weight_classes'] = $this->model_localisation_weight_class->getWeightClasses();
		$data['length_classes'] = $this->model_localisation_length_class->getLengthClasses();
		$data['tax_classes'] = $this->model_localisation_tax_class->getTaxClasses();
		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		$data['timeout_min'] = FrenetConfig::TIMEOUT_MIN;
		$data['timeout_max'] = FrenetConfig::TIMEOUT_MAX;
		$data['attempts_min'] = FrenetConfig::ATTEMPTS_MIN;
		$data['attempts_max'] = FrenetConfig::ATTEMPTS_MAX;
		$data['text_version'] = sprintf($this->language->get('text_version'), Module::VERSION);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view(self::ROUTE, $data));
	}

	/**
	 * Saves the settings and answers JSON: the error of each invalid field, or the success with the values as saved.
	 *
	 * @return void
	 */
	public function save(): void {
		$this->autoloader->register('Frenet', DIR_EXTENSION . 'opencartfrenetv4/system/library/frenet/');

		$this->load->language(self::ROUTE);
		$this->load->model('setting/setting');

		$json = [];
		$post = $this->request->post;

		if (!$this->user->hasPermission('modify', self::ROUTE)) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		$postcode = Postcode::format((string)($post['shipping_frenet_postcode'] ?? ''));
		$typed_token = trim((string)($post['shipping_frenet_token'] ?? ''));
		$token = $typed_token !== '' ? $typed_token : $this->model_setting_setting->getValue('shipping_frenet_token');

		if (!$json) {
			if ($postcode === null) {
				$json['error']['postcode'] = $this->language->get('error_postcode');
			}

			if (!empty($post['shipping_frenet_status']) && $token === '') {
				$json['error']['token'] = $this->language->get('error_token');
			}

			if ($json) {
				$json['error']['warning'] = $this->language->get('error_warning');
			}
		}

		if (!$json) {
			$values = ['shipping_frenet_token' => $token];

			foreach (self::FIELDS as $field) {
				$values[$field] = trim((string)($post[$field] ?? ''));
			}

			$values['shipping_frenet_postcode'] = (string)$postcode;

			[$values, $adjusted] = $this->adjust($values);

			$this->model_setting_setting->editSetting('shipping_frenet', $values);

			$json['success'] = implode(' ', array_merge([$this->language->get('text_success')], $adjusted));
			$json['shipping_frenet_token'] = '';

			foreach (array_merge(['shipping_frenet_postcode', 'shipping_frenet_additional_days'], array_keys($this->getRanges()), array_keys($this->getMinimums())) as $field) {
				$json[$field] = $values[$field];
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput((string)json_encode($json));
	}

	/**
	 * Warnings above the form, reloaded by the form after each save so they follow what was saved.
	 *
	 * @return void
	 */
	public function alert(): void {
		$this->autoloader->register('Frenet', DIR_EXTENSION . 'opencartfrenetv4/system/library/frenet/');

		$this->load->language(self::ROUTE);
		$this->load->model('setting/setting');

		$this->response->setOutput($this->load->view(self::ROUTE . '_alert', $this->getAlerts((int)$this->model_setting_setting->getValue('shipping_frenet_geo_zone_id'))));
	}

	/**
	 * Data of the warnings: the token is missing, or the geo zone chosen for Frenet was deleted.
	 *
	 * @param int $geo_zone_id
	 *
	 * @return array<string, mixed>
	 */
	private function getAlerts(int $geo_zone_id): array {
		$this->load->model('localisation/geo_zone');

		return [
			'token_required'   => $this->model_setting_setting->getValue('shipping_frenet_token') === '',
			'geo_zone_missing' => $geo_zone_id > 0 && !$this->model_localisation_geo_zone->getGeoZone($geo_zone_id),
			'panel_url'        => self::PANEL_URL
		];
	}

	/**
	 * Settings with the numbers within what the quote accepts, and the message of each number that was changed.
	 *
	 * @param array<string, string> $values
	 *
	 * @return array{array<string, string>, array<int, string>}
	 */
	private function adjust(array $values): array {
		$adjusted = [];

		foreach ($this->getRanges() as $field => [$label, $min, $max, $default]) {
			$typed = $values[$field];
			$value = is_numeric($typed) ? max($min, min($max, (int)$typed)) : $default;

			if ($typed !== '' && (string)$value !== $typed) {
				$adjusted[] = sprintf($this->language->get('text_adjusted'), $this->language->get($label), $value, $min, $max);
			}

			$values[$field] = (string)$value;
		}

		$days = $values['shipping_frenet_additional_days'];
		$additional_days = ctype_digit($days) ? (int)$days : 0;

		if ($days !== '' && (string)$additional_days !== $days) {
			$adjusted[] = sprintf($this->language->get('text_adjusted_min'), $this->language->get('entry_additional_days'), $additional_days);
		}

		$values['shipping_frenet_additional_days'] = (string)$additional_days;

		foreach ($this->getMinimums() as $field => [$label, $default]) {
			$value = FrenetConfig::parsePositive($values[$field]);

			if ($value === null) {
				$adjusted[] = sprintf($this->language->get('text_default_used'), $this->language->get($label), $this->formatNumber($default));
				$value = $default;
			}

			$values[$field] = $this->formatNumber($value);
		}

		return [$values, $adjusted];
	}

	/**
	 * Integer settings kept within a range: label, minimum, maximum and default of each.
	 *
	 * @return array<string, array{string, int, int, int}>
	 */
	private function getRanges(): array {
		return [
			'shipping_frenet_timeout'  => ['entry_timeout', FrenetConfig::TIMEOUT_MIN, FrenetConfig::TIMEOUT_MAX, FrenetConfig::TIMEOUT_DEFAULT],
			'shipping_frenet_attempts' => ['entry_attempts', FrenetConfig::ATTEMPTS_MIN, FrenetConfig::ATTEMPTS_MAX, FrenetConfig::ATTEMPTS_DEFAULT]
		];
	}

	/**
	 * Decimal settings that must be greater than zero: label and default of each.
	 *
	 * @return array<string, array{string, float}>
	 */
	private function getMinimums(): array {
		return [
			'shipping_frenet_minimum_height' => ['entry_minimum_height', FrenetConfig::DEFAULT_HEIGHT],
			'shipping_frenet_minimum_width'  => ['entry_minimum_width', FrenetConfig::DEFAULT_WIDTH],
			'shipping_frenet_minimum_length' => ['entry_minimum_length', FrenetConfig::DEFAULT_LENGTH],
			'shipping_frenet_default_weight' => ['entry_default_weight', FrenetConfig::DEFAULT_WEIGHT]
		];
	}

	/**
	 * Moves the token saved by version 1.0.0 to shipping_frenet_token and removes the settings of 1.0.0, telling whether it did.
	 *
	 * @return bool
	 */
	private function migrateLegacySettings(): bool {
		$settings = $this->model_setting_setting->getSetting('shipping_frenet');
		$legacy = array_intersect_key($settings, array_flip(self::LEGACY_SETTINGS));

		if (!$legacy) {
			return false;
		}

		if (trim((string)($settings['shipping_frenet_token'] ?? '')) === '') {
			$settings['shipping_frenet_token'] = trim((string)($settings['shipping_frenet_contrato_token'] ?? ''));
		}

		$this->model_setting_setting->editSetting('shipping_frenet', array_diff_key($settings, $legacy));

		return true;
	}

	/**
	 * Decimal number for an input, without trailing zeros.
	 *
	 * @param float $value
	 *
	 * @return string
	 */
	private function formatNumber(float $value): string {
		return rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
	}
}
