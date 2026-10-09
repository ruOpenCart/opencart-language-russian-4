<?php
namespace Opencart\Admin\Controller\Extension\OcnLanguageRussian\Language;
class Russian extends \Opencart\System\Engine\Controller
{
	public function index(): void
	{
		$this->load->language('extension/ocn_language_russian/language/russian');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=language')
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/ocn_language_russian/language/russian', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/ocn_language_russian/language/russian.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=language');

		$data['language_russian_status'] = $this->config->get('language_russian_status');

		$state = $this->presetFormState();

		$data['preset_active'] = $state['active'];
		$data['preset_selected'] = $state['selected'];
		$data['preset_zone_id'] = $state['zone_id'];
		$data['zones'] = $state['zones'];
		$data['preset_zone_ids'] = $state['preset_zone_ids'];

		$data['presets'] = [];

		foreach ($this->presets() as $code => $preset) {
			$data['presets'][] = [
				'code' => $code,
				'name' => $this->language->get($preset['label'])
			];
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/ocn_language_russian/language/russian', $data));
	}

	public function save(): void
	{
		$this->load->language('extension/ocn_language_russian/language/russian');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/ocn_language_russian/language/russian')) {
			$json['error'] = $this->language->get('error_permission');
		}

		$apply = $this->requestedDefaults();
		$preset = (string)($this->request->post['language_russian_preset'] ?? '');
		$values = [];

		if ($preset !== '' && !isset($this->presets()[$preset])) {
			$json['error'] = $this->language->get('error_preset');
		}

		if (!$json && in_array(true, $apply, true)) {
			$resolved = $this->resolvePreset($preset, $apply);

			if ($resolved['error']) {
				$json['error'] = $resolved['error'];
			} else {
				$values = $resolved['values'];
			}
		}

		if (!$json) {
			$this->load->model('setting/setting');

			$setting = $this->request->post;
			$setting['language_russian_preset'] = isset($this->presets()[$preset]) ? $preset : '';
			$setting['language_russian_zone_id'] = $this->storedZoneId();

			foreach (array_keys($apply) as $key) {
				unset($setting['language_russian_default_' . $key]);
			}

			$this->model_setting_setting->editSetting('language_russian', $setting);

			foreach ($values as $key => $value) {
				$this->writeConfigValue($key, $value);
			}

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function install(): void
	{
		if ($this->user->hasPermission('modify', 'extension/language')) {
			$this->load->model('localisation/language');

			$language_info = $this->model_localisation_language->getLanguageByCode('ru-ru');

			if (!$language_info) {
				$language_data = [
					'name' => 'Русский',
					'code' => 'ru-ru',
					'locale' => 'ru-ru',
					'extension' => 'ocn_language_russian',
					'status' => 1,
					'sort_order' => 1
				];

				$language_id = $this->model_localisation_language->addLanguage($language_data);
			} else {
				$language_id = (int)$language_info['language_id'];

				$this->model_localisation_language->editLanguage($language_id, $language_info + ['extension' => 'ocn_language_russian']);
			}

			$this->fillRussianMeta($language_id);
		}
	}

	public function uninstall(): void
	{
		if ($this->user->hasPermission('modify', 'extension/language')) {
			$this->load->model('localisation/language');

			$language_info = $this->model_localisation_language->getLanguageByCode('ru-ru');

			if ($language_info) {
				$this->model_localisation_language->deleteLanguage($language_info['language_id']);
			}
		}
	}

	/**
	 * @return array<string, array<string, string>>
	 */
	private function presets(): array
	{
		return [
			'bryansk' => [
				'label' => 'text_preset_bryansk',
				'country_iso' => 'RU',
				'zone_code' => 'RU-BRY',
				'timezone' => 'Europe/Moscow',
				'language' => 'ru-ru',
				'currency' => 'RUB'
			],
			'moscow' => [
				'label' => 'text_preset_moscow',
				'country_iso' => 'RU',
				'zone_code' => 'RU-MOW',
				'timezone' => 'Europe/Moscow',
				'language' => 'ru-ru',
				'currency' => 'RUB'
			],
			'russia' => [
				'label' => 'text_preset_russia',
				'country_iso' => 'RU',
				'zone_code' => '',
				'timezone' => 'Europe/Moscow',
				'language' => 'ru-ru',
				'currency' => 'RUB'
			]
		];
	}

	/**
	 * @return array<string, bool>
	 */
	private function requestedDefaults(): array
	{
		return [
			'country' => !empty($this->request->post['language_russian_default_country']),
			'zone' => (string)($this->request->post['language_russian_preset'] ?? '') !== 'russia' && (int)($this->request->post['language_russian_zone_id'] ?? 0) > 0,
			'timezone' => !empty($this->request->post['language_russian_default_timezone']),
			'language' => !empty($this->request->post['language_russian_default_language']),
			'language_admin' => !empty($this->request->post['language_russian_default_language_admin']),
			'currency' => !empty($this->request->post['language_russian_default_currency'])
		];
	}

	/**
	 * @param array<string, bool> $apply
	 *
	 * @return array{error: string, values: array<string, string>}
	 */
	private function resolvePreset(string $code, array $apply): array
	{
		$presets = $this->presets();

		if ($code === '' || !isset($presets[$code])) {
			return [
				'error' => $this->language->get('error_preset'),
				'values' => []
			];
		}

		$preset = $presets[$code];
		$errors = [];
		$values = [];

		$country_id = 0;

		if (!empty($apply['country']) || !empty($apply['zone'])) {
			$country_id = $this->findCountryId($preset['country_iso']);
		}

		if (!empty($apply['country'])) {
			if (!$country_id) {
				$errors[] = $this->language->get('error_country');
			} else {
				$values['config_country_id'] = (string)$country_id;
			}
		}

		if (!empty($apply['zone'])) {
			if (!$country_id) {
				if (!in_array($this->language->get('error_country'), $errors, true)) {
					$errors[] = $this->language->get('error_country');
				}
			} else {
				$zone_id = $this->resolveZoneId($country_id);

				if (!$zone_id) {
					$errors[] = $this->language->get('error_zone_select');
				} else {
					$values['config_zone_id'] = (string)$zone_id;
				}
			}
		}

		if (!empty($apply['timezone'])) {
			$values['config_timezone'] = $preset['timezone'];
		}

		if (!empty($apply['language']) || !empty($apply['language_admin'])) {
			$this->load->model('localisation/language');

			$language_info = $this->model_localisation_language->getLanguageByCode($preset['language']);

			if (!$language_info) {
				$errors[] = $this->language->get('error_language');
			} else {
				if (!empty($apply['language'])) {
					$values['config_language_catalog'] = $language_info['code'];
				}

				if (!empty($apply['language_admin'])) {
					$values['config_language_admin'] = $language_info['code'];
				}
			}
		}

		if (!empty($apply['currency'])) {
			$this->load->model('localisation/currency');

			$currency_info = $this->model_localisation_currency->getCurrencyByCode($preset['currency']);

			if (!$currency_info) {
				$errors[] = $this->language->get('error_currency');
			} else {
				$values['config_currency'] = $currency_info['code'];
			}
		}

		if ($errors) {
			$values = [];
		}

		return [
			'error' => implode('<br>', $errors),
			'values' => $values
		];
	}

	private function findCountryId(string $iso_code_2): int
	{
		$query = $this->db->query("SELECT `country_id` FROM `" . DB_PREFIX . "country` WHERE `iso_code_2` = '" . $this->db->escape($iso_code_2) . "' LIMIT 1");

		return $query->num_rows ? (int)$query->row['country_id'] : 0;
	}

	private function resolveZoneId(int $country_id): int
	{
		$zone_id = (int)($this->request->post['language_russian_zone_id'] ?? 0);

		return $this->zoneBelongsToCountry($zone_id, $country_id) ? $zone_id : 0;
	}

	private function zoneBelongsToCountry(int $zone_id, int $country_id): bool
	{
		if ($zone_id < 1 || $country_id < 1) {
			return false;
		}

		$query = $this->db->query("SELECT `zone_id` FROM `" . DB_PREFIX . "zone` WHERE `zone_id` = '" . (int)$zone_id . "' AND `country_id` = '" . (int)$country_id . "' AND `status` = '1' LIMIT 1");

		return (bool)$query->num_rows;
	}

	private function storedZoneId(): string
	{
		if ((string)($this->request->post['language_russian_preset'] ?? '') === 'russia') {
			return '';
		}

		$zone_id = (int)($this->request->post['language_russian_zone_id'] ?? 0);

		if ($zone_id < 1) {
			return '';
		}

		$country_id = $this->findCountryId('RU');

		if ($this->zoneBelongsToCountry($zone_id, $country_id)) {
			return (string)$zone_id;
		}

		$previous = (int)$this->config->get('language_russian_zone_id');

		return $this->zoneBelongsToCountry($previous, $country_id) ? (string)$previous : '';
	}

	/**
	 * @return array<int, array{zone_id: int, code: string, name: string}>
	 */
	private function countryZones(int $country_id): array
	{
		$language_id = (int)$this->config->get('config_language_id');

		$query = $this->db->query("SELECT `z`.`zone_id`, `z`.`code`, COALESCE(NULLIF(`zd`.`name`, ''), NULLIF(`zd1`.`name`, ''), `z`.`code`) AS `name` FROM `" . DB_PREFIX . "zone` `z` LEFT JOIN `" . DB_PREFIX . "zone_description` `zd` ON (`z`.`zone_id` = `zd`.`zone_id` AND `zd`.`language_id` = '" . $language_id . "') LEFT JOIN `" . DB_PREFIX . "zone_description` `zd1` ON (`z`.`zone_id` = `zd1`.`zone_id` AND `zd1`.`language_id` = '1') WHERE `z`.`country_id` = '" . (int)$country_id . "' AND `z`.`status` = '1'");

		$labels = $this->russianZoneLabels();
		$zones = [];

		foreach ($query->rows as $row) {
			$code = (string)$row['code'];

			$zones[] = [
				'zone_id' => (int)$row['zone_id'],
				'code' => $code,
				'name' => $labels[$code] ?? (string)$row['name']
			];
		}

		usort($zones, static function (array $a, array $b): int {
			return strcmp($a['name'], $b['name']);
		});

		return $zones;
	}

	/**
	 * @return array<string, string>
	 */
	private function russianZoneLabels(): array
	{
		static $labels = null;

		if (is_array($labels)) {
			return $labels;
		}

		$labels = [];
		$file = DIR_EXTENSION . 'ocn_language_russian/admin/language/ru-ru/language/russian.php';

		if (is_file($file)) {
			$_ = [];

			require $file;

			foreach ($_ as $key => $value) {
				$prefix = 'text_zone_';

				if (strncmp((string)$key, $prefix, strlen($prefix)) === 0) {
					$labels[substr((string)$key, strlen($prefix))] = (string)$value;
				}
			}
		}

		return $labels;
	}

	/**
	 * @param array<string, string> $preset
	 *
	 * @return array<string, bool>
	 */
	private function localeMatches(array $preset, int $country_id): array
	{
		$catalog = trim((string)$this->config->get('config_language_catalog'));

		if ($catalog === '') {
			$catalog = trim((string)$this->config->get('config_language'));
		}

		return [
			'country' => $country_id > 0 && (string)$this->config->get('config_country_id') === (string)$country_id,
			'timezone' => (string)$this->config->get('config_timezone') === $preset['timezone'],
			'language' => $catalog === $preset['language'],
			'language_admin' => (string)$this->config->get('config_language_admin') === $preset['language'],
			'currency' => (string)$this->config->get('config_currency') === $preset['currency']
		];
	}

	/**
	 * @param array<int, array{zone_id: int, code: string, name: string}> $zones
	 */
	private function zoneByCode(array $zones, string $code): array
	{
		foreach ($zones as $zone) {
			if ($zone['code'] === $code) {
				return $zone;
			}
		}

		return [];
	}

	/**
	 * @return array{active: array<string, bool>, selected: string, zone_id: int, zones: array<int, array{zone_id: int, code: string, name: string}>, preset_zone_ids: array<string, int>}
	 */
	private function presetFormState(): array
	{
		$presets = $this->presets();
		$country_id = $this->findCountryId('RU');
		$zones = $country_id ? $this->countryZones($country_id) : [];
		$bryansk = $this->zoneByCode($zones, 'RU-BRY');
		$moscow = $this->zoneByCode($zones, 'RU-MOW');
		$config_zone_id = (int)$this->config->get('config_zone_id');
		$saved_zone_id = (int)$this->config->get('language_russian_zone_id');
		$saved_preset = (string)$this->config->get('language_russian_preset');

		if (!isset($presets[$saved_preset])) {
			$saved_preset = '';
		}

		$selected = $saved_preset;
		$shared = $this->localeMatches($presets['bryansk'], $country_id);
		$zone_ids = [];

		foreach ($zones as $zone) {
			$zone_ids[$zone['zone_id']] = $zone;
		}

		if ($selected === '' && !in_array(false, $shared, true)) {
			if ($bryansk && $config_zone_id === $bryansk['zone_id']) {
				$selected = 'bryansk';
			} elseif ($moscow && $config_zone_id === $moscow['zone_id']) {
				$selected = 'moscow';
			} elseif (isset($zone_ids[$config_zone_id])) {
				$selected = 'russia';
			}
		}

		if (!isset($zone_ids[$saved_zone_id])) {
			$saved_zone_id = 0;
		}

		$display_zone_id = ($selected === 'russia' || !isset($zone_ids[$saved_zone_id])) ? 0 : $saved_zone_id;
		$active = $shared;

		return [
			'active' => $active,
			'selected' => $selected,
			'zone_id' => $display_zone_id,
			'zones' => $zones,
			'preset_zone_ids' => [
				'bryansk' => $bryansk['zone_id'] ?? 0,
				'moscow' => $moscow['zone_id'] ?? 0
			]
		];
	}

	private function fillRussianMeta(int $language_id): void
	{
		if (!$language_id) {
			return;
		}

		$this->load->model('setting/setting');
		$this->load->model('localisation/language');

		$description = $this->readConfigDescription();
		$source_language_id = $this->storeLanguageId($language_id);

		if (!$source_language_id) {
			return;
		}

		$source = $this->metaForLanguage($description, $source_language_id);
		$current = [];

		if (isset($description[$language_id]) && is_array($description[$language_id])) {
			$current = $description[$language_id];
		}

		$changed = !isset($description[$language_id]) || !is_array($description[$language_id]);

		foreach (['meta_title', 'meta_description', 'meta_keyword'] as $key) {
			$target = trim((string)($current[$key] ?? ''));
			$value = trim((string)($source[$key] ?? ''));

			if ($target === '' && $value !== '') {
				$current[$key] = $value;
				$changed = true;
			}
		}

		if (!$changed) {
			return;
		}

		$description[$language_id] = [
			'meta_title' => (string)($current['meta_title'] ?? ''),
			'meta_description' => (string)($current['meta_description'] ?? ''),
			'meta_keyword' => (string)($current['meta_keyword'] ?? '')
		];

		$this->writeConfigValue('config_description', $description);
	}

	/**
	 * @return array<int|string, mixed>
	 */
	private function readConfigDescription(): array
	{
		$raw = $this->model_setting_setting->getValue('config_description', 0);

		if ($raw === '') {
			return [];
		}

		$decoded = json_decode($raw, true);

		return is_array($decoded) ? $decoded : [];
	}

	private function storeLanguageId(int $except_language_id): int
	{
		$code = trim((string)$this->config->get('config_language_catalog'));

		if ($code === '' || $code === 'ru-ru') {
			$code = trim((string)$this->config->get('config_language'));
		}

		if ($code === '' || $code === 'ru-ru') {
			return 0;
		}

		$language_info = $this->model_localisation_language->getLanguageByCode($code);

		if (!$language_info || (int)$language_info['language_id'] === $except_language_id) {
			return 0;
		}

		return (int)$language_info['language_id'];
	}

	/**
	 * @param array<int|string, mixed> $description
	 *
	 * @return array{meta_title: string, meta_description: string, meta_keyword: string}
	 */
	private function metaForLanguage(array $description, int $language_id): array
	{
		$row = [];

		if (isset($description[$language_id]) && is_array($description[$language_id])) {
			$row = $description[$language_id];
		}

		$meta = [
			'meta_title' => trim((string)($row['meta_title'] ?? '')),
			'meta_description' => trim((string)($row['meta_description'] ?? '')),
			'meta_keyword' => trim((string)($row['meta_keyword'] ?? ''))
		];

		if ($meta['meta_title'] === '') {
			$meta['meta_title'] = trim((string)$this->model_setting_setting->getValue('config_meta_title', 0));
		}

		if ($meta['meta_description'] === '') {
			$meta['meta_description'] = trim((string)$this->model_setting_setting->getValue('config_meta_description', 0));
		}

		if ($meta['meta_keyword'] === '') {
			$meta['meta_keyword'] = trim((string)$this->model_setting_setting->getValue('config_meta_keyword', 0));
		}

		return $meta;
	}

	/**
	 * @param array<mixed>|string $value
	 */
	private function writeConfigValue(string $key, $value): void
	{
		$this->load->model('setting/setting');

		$query = $this->db->query("SELECT `setting_id` FROM `" . DB_PREFIX . "setting` WHERE `store_id` = '0' AND `code` = 'config' AND `key` = '" . $this->db->escape($key) . "' LIMIT 1");

		if ($query->num_rows) {
			$this->model_setting_setting->editValue('config', $key, $value, 0);

			return;
		}

		$stored = is_array($value) ? (string)json_encode($value) : (string)$value;

		$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = '0', `code` = 'config', `key` = '" . $this->db->escape($key) . "', `value` = '" . $this->db->escape($stored) . "', `serialized` = '" . (int)is_array($value) . "'");
	}
}
