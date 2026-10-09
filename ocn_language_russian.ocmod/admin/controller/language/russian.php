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
		$data['translate'] = $this->url->link('extension/ocn_language_russian/language/russian.translate', 'user_token=' . $this->session->data['user_token']);
		$data['check'] = $this->url->link('extension/ocn_language_russian/language/russian.check', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=language');

		$data['language_russian_status'] = $this->config->get('language_russian_status');
		$data['extension_version'] = $this->extensionVersion();
		$data['extension_links'] = $this->extensionLinks();

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

		$translation = $this->translationView();

		$data['translation_groups'] = $translation['groups'];
		$data['translation_all'] = $translation['all'];
		$data['translation_error'] = $translation['error'];

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

	public function check(): void
	{
		$this->load->language('extension/ocn_language_russian/language/russian');

		$json = [];

		if (!$this->user->hasPermission('access', 'extension/ocn_language_russian/language/russian') && !$this->user->hasPermission('modify', 'extension/ocn_language_russian/language/russian')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$repo = $this->githubRepo();

			if ($repo === null) {
				$json['error'] = $this->language->get('error_update_link');
			}
		}

		if (!$json) {
			$remote = $this->latestGithubRelease($repo);

			if ($remote === null) {
				$json['error'] = $this->language->get('error_update');
			} else {
				$local = $this->extensionVersion();
				$json['version'] = $remote['version'];
				$json['url'] = $remote['url'];
				$json['kind'] = $remote['kind'];

				if ($this->compareVersions($remote['version'], $local) > 0) {
					$json['newer'] = true;
					$json['message'] = sprintf($this->language->get($remote['kind'] === 'tag' ? 'text_update_tag' : 'text_update_available'), $remote['version']);
				} else {
					$json['newer'] = false;
					$json['message'] = $this->language->get('text_update_current');
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function translate(): void
	{
		$this->load->language('extension/ocn_language_russian/language/russian');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/ocn_language_russian/language/russian')) {
			$json['error'] = $this->language->get('error_permission');
		}

		$language_id = $this->russianLanguageId();

		if (!$json && !$language_id) {
			$json['error'] = $this->language->get('error_translation_language');
		}

		$selected = $this->request->post['selected'] ?? [];
		$names = $this->request->post['name'] ?? [];
		$extras = $this->request->post['extra'] ?? [];

		if (!$json && (!is_array($selected) || !$selected)) {
			$json['error'] = $this->language->get('error_translation');
		}

		$updated = [];
		$caches = [];

		if (!$json) {
			foreach ($selected as $key) {
				$key = (string)$key;
				$parts = explode(':', $key, 2);

				if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
					continue;
				}

				$name = trim((string)($names[$key] ?? ''));
				$extra = trim((string)($extras[$key] ?? ''));
				$row = $this->writeTranslation($parts[0], $parts[1], $name, $extra, $language_id);

				if (!$row) {
					continue;
				}

				$updated[] = $row;
				$caches[$parts[0]] = true;
			}

			if (!$updated) {
				$json['error'] = $this->language->get('error_translation');
			} else {
				foreach (array_keys($caches) as $type) {
					$cache = $this->translationCacheKey($type);

					if ($cache !== '') {
						$this->cache->delete($cache);
					}
				}

				$json['success'] = $this->language->get('text_translation_success');
				$json['rows'] = $updated;
			}
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
		return $this->localisationMap()['region'];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function localisationMap(): array
	{
		static $map = null;

		if (is_array($map)) {
			return $map;
		}

		$file = DIR_EXTENSION . 'ocn_language_russian/system/localisation_ru.php';
		$loaded = is_file($file) ? require $file : [];

		if (!is_array($loaded)) {
			$loaded = [];
		}

		foreach (['country', 'region', 'length', 'weight', 'stock_status', 'order_status', 'return_status', 'return_action', 'return_reason', 'subscription_status', 'customer_group', 'currency'] as $key) {
			if (!isset($loaded[$key]) || !is_array($loaded[$key])) {
				$loaded[$key] = [];
			}
		}

		$map = $loaded;

		return $map;
	}

	private function russianLanguageId(): int
	{
		$this->load->model('localisation/language');

		$language_info = $this->model_localisation_language->getLanguageByCode('ru-ru');

		return $language_info ? (int)$language_info['language_id'] : 0;
	}

	/**
	 * @return array<string, array{zone_id: int, name: string}>
	 */
	private function russianZoneRecords(int $country_id, int $language_id): array
	{
		$query = $this->db->query("SELECT `z`.`zone_id`, `z`.`code`, `zd`.`name` FROM `" . DB_PREFIX . "zone` `z` LEFT JOIN `" . DB_PREFIX . "zone_description` `zd` ON (`z`.`zone_id` = `zd`.`zone_id` AND `zd`.`language_id` = '" . (int)$language_id . "') WHERE `z`.`country_id` = '" . (int)$country_id . "'");

		$zones = [];

		foreach ($query->rows as $row) {
			$zones[(string)$row['code']] = [
				'zone_id' => (int)$row['zone_id'],
				'name' => (string)($row['name'] ?? '')
			];
		}

		return $zones;
	}

	/**
	 * @return array{groups: array<int, array<string, mixed>>, all: bool, error: string}
	 */
	private function translationView(): array
	{
		$language_id = $this->russianLanguageId();

		if (!$language_id) {
			return [
				'groups' => [],
				'all' => false,
				'error' => $this->language->get('error_translation_language')
			];
		}

		$map = $this->localisationMap();
		$groups = [];

		$groups[] = $this->translationGroup('country', $this->language->get('text_group_country'), '', [
			$this->translationSection('country', '', $this->countryTranslationRows($map, $language_id), false)
		]);
		$groups[] = $this->translationGroup('region', $this->language->get('text_group_region'), '', [
			$this->translationSection('region', '', $this->regionTranslationRows($map, $language_id), false)
		]);
		$groups[] = $this->translationGroup('length', $this->language->get('text_group_length'), '', [
			$this->translationSection('length', '', $this->measureTranslationRows('length', $map['length'], $language_id), true)
		]);
		$groups[] = $this->translationGroup('weight', $this->language->get('text_group_weight'), '', [
			$this->translationSection('weight', '', $this->measureTranslationRows('weight', $map['weight'], $language_id), true)
		]);
		$groups[] = $this->translationGroup('stock', $this->language->get('text_group_stock'), '', [
			$this->translationSection('stock', '', $this->statusTranslationRows('stock_status', 'stock_status_id', 'stock', $map['stock_status'], $language_id), false)
		]);
		$groups[] = $this->translationGroup('order', $this->language->get('text_group_order'), '', [
			$this->translationSection('order', '', $this->statusTranslationRows('order_status', 'order_status_id', 'order', $map['order_status'], $language_id), false)
		]);
		$groups[] = $this->translationGroup('return', $this->language->get('text_group_return'), '', [
			$this->translationSection('return_status', $this->language->get('text_group_return_status'), $this->statusTranslationRows('return_status', 'return_status_id', 'return_status', $map['return_status'], $language_id), false),
			$this->translationSection('return_action', $this->language->get('text_group_return_action'), $this->statusTranslationRows('return_action', 'return_action_id', 'return_action', $map['return_action'], $language_id), false),
			$this->translationSection('return_reason', $this->language->get('text_group_return_reason'), $this->statusTranslationRows('return_reason', 'return_reason_id', 'return_reason', $map['return_reason'], $language_id), false)
		]);
		$groups[] = $this->translationGroup('subscription', $this->language->get('text_group_subscription'), '', [
			$this->translationSection('subscription', '', $this->statusTranslationRows('subscription_status', 'subscription_status_id', 'subscription', $map['subscription_status'], $language_id), false)
		]);
		$groups[] = $this->translationGroup('customer_group', $this->language->get('text_group_customer'), '', [
			$this->translationSection('customer_group', '', $this->customerGroupTranslationRows($map['customer_group'], $language_id), true)
		]);
		$groups[] = $this->translationGroup('currency', $this->language->get('text_group_currency'), $this->language->get('text_translation_currency'), [
			$this->translationSection('currency', '', $this->currencyTranslationRows($map['currency']), false)
		]);

		$groups = array_values(array_filter($groups));
		$all = $groups !== [];

		foreach ($groups as $group) {
			foreach ($group['sections'] as $section) {
				if (!$section['all']) {
					$all = false;
					break 2;
				}
			}
		}

		return [
			'groups' => $groups,
			'all' => $all,
			'error' => ''
		];
	}

	/**
	 * @param array<int, array<string, mixed>> $sections
	 *
	 * @return array<string, mixed>
	 */
	private function translationGroup(string $code, string $title, string $note, array $sections): array
	{
		$sections = array_values(array_filter($sections));

		if (!$sections) {
			return [];
		}

		return [
			'code' => $code,
			'title' => $title,
			'note' => $note,
			'sections' => $sections
		];
	}

	/**
	 * @param array<int, array<string, mixed>> $rows
	 *
	 * @return array<string, mixed>
	 */
	private function translationSection(string $code, string $title, array $rows, bool $dual): array
	{
		if (!$rows) {
			return [];
		}

		foreach ($rows as $index => $row) {
			$rows[$index]['apply'] = false;
		}

		return [
			'code' => $code,
			'title' => $title,
			'dual' => $dual,
			'rows' => $rows,
			'all' => false
		];
	}

	/**
	 * @param array<string, mixed> $map
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function countryTranslationRows(array $map, int $language_id): array
	{
		$name = trim((string)($map['country']['RU'] ?? ''));
		$country_id = $this->findCountryId('RU');

		if ($name === '' || !$country_id) {
			return [];
		}

		$query = $this->db->query("SELECT `name` FROM `" . DB_PREFIX . "country_description` WHERE `country_id` = '" . (int)$country_id . "' AND `language_id` = '" . (int)$language_id . "' LIMIT 1");

		if (!$query->num_rows) {
			return [];
		}

		$current = (string)$query->row['name'];

		return [[
			'key' => 'country:RU',
			'label' => 'RU',
			'current' => $current,
			'current_extra' => '',
			'name' => $name,
			'extra' => '',
			'apply' => $current !== $name
		]];
	}

	/**
	 * @param array<string, mixed> $map
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function regionTranslationRows(array $map, int $language_id): array
	{
		$country_id = $this->findCountryId('RU');

		if (!$country_id) {
			return [];
		}

		$zones = $this->russianZoneRecords($country_id, $language_id);
		$rows = [];

		foreach ($map['region'] as $code => $name) {
			$code = (string)$code;
			$name = trim((string)$name);

			if ($name === '' || !isset($zones[$code])) {
				continue;
			}

			$current = $zones[$code]['name'];

			$rows[] = [
				'key' => 'region:' . $code,
				'label' => $code,
				'current' => $current,
				'current_extra' => '',
				'name' => $name,
				'extra' => '',
				'apply' => $current !== $name
			];
		}

		usort($rows, static function (array $a, array $b): int {
			return strcmp($a['name'], $b['name']);
		});

		return $rows;
	}

	/**
	 * @param array<string, mixed> $definitions
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function measureTranslationRows(string $type, array $definitions, int $language_id): array
	{
		$table = $type === 'weight' ? 'weight_class' : 'length_class';
		$id_column = $table . '_id';
		$query = $this->db->query("SELECT `c`.`" . $id_column . "` AS `id`, `c`.`value`, `d`.`title`, `d`.`unit` FROM `" . DB_PREFIX . $table . "` `c` LEFT JOIN `" . DB_PREFIX . $table . "_description` `d` ON (`c`.`" . $id_column . "` = `d`.`" . $id_column . "` AND `d`.`language_id` = '" . (int)$language_id . "')");
		$rows = [];

		foreach ($definitions as $code => $definition) {
			if (!is_array($definition)) {
				continue;
			}

			$code = (string)$code;
			$title = trim((string)($definition['title'] ?? ''));
			$unit = trim((string)($definition['unit'] ?? ''));
			$match = $this->matchMeasure($query->rows, $code, $unit, (string)($definition['value'] ?? ''));

			if (!$match || ($title === '' && $unit === '')) {
				continue;
			}

			$current_title = (string)$match['title'];
			$current_unit = (string)$match['unit'];

			$rows[] = [
				'key' => $type . ':' . $code,
				'label' => $code,
				'current' => $current_title,
				'current_extra' => $current_unit,
				'name' => $title,
				'extra' => $unit,
				'apply' => $current_title !== $title || $current_unit !== $unit
			];
		}

		return $rows;
	}

	/**
	 * @param array<int, array<string, mixed>> $rows
	 *
	 * @return array<string, mixed>|null
	 */
	private function matchMeasure(array $rows, string $unit, string $new_unit, string $value): ?array
	{
		foreach ([$unit, $new_unit] as $candidate) {
			if ($candidate === '') {
				continue;
			}

			foreach ($rows as $row) {
				if ((string)$row['unit'] === $candidate && $row['title'] !== null) {
					return $row;
				}
			}
		}

		if ($value === '') {
			return null;
		}

		foreach ($rows as $row) {
			$current_unit = (string)$row['unit'];

			if ($row['title'] === null) {
				continue;
			}

			if (abs((float)$row['value'] - (float)$value) < 0.0000001 && ($current_unit === $unit || $current_unit === $new_unit)) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * @param array<int|string, mixed> $names
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function statusTranslationRows(string $table, string $id_column, string $type, array $names, int $language_id): array
	{
		$ids = [];

		foreach (array_keys($names) as $id) {
			$id = (int)$id;

			if ($id > 0) {
				$ids[] = $id;
			}
		}

		if (!$ids) {
			return [];
		}

		$query = $this->db->query("SELECT `" . $id_column . "` AS `id`, `name` FROM `" . DB_PREFIX . $table . "` WHERE `language_id` = '" . (int)$language_id . "' AND `" . $id_column . "` IN (" . implode(',', $ids) . ")");
		$current = [];

		foreach ($query->rows as $row) {
			$current[(int)$row['id']] = (string)$row['name'];
		}

		$rows = [];

		foreach ($names as $id => $name) {
			$id = (int)$id;
			$name = trim((string)$name);

			if ($id < 1 || $name === '' || !isset($current[$id])) {
				continue;
			}

			$rows[] = [
				'key' => $type . ':' . $id,
				'label' => (string)$id,
				'current' => $current[$id],
				'current_extra' => '',
				'name' => $name,
				'extra' => '',
				'apply' => $current[$id] !== $name
			];
		}

		return $rows;
	}

	/**
	 * @param array<int|string, mixed> $definitions
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function customerGroupTranslationRows(array $definitions, int $language_id): array
	{
		$ids = [];

		foreach (array_keys($definitions) as $id) {
			$id = (int)$id;

			if ($id > 0) {
				$ids[] = $id;
			}
		}

		if (!$ids) {
			return [];
		}

		$query = $this->db->query("SELECT `customer_group_id` AS `id`, `name`, `description` FROM `" . DB_PREFIX . "customer_group_description` WHERE `language_id` = '" . (int)$language_id . "' AND `customer_group_id` IN (" . implode(',', $ids) . ")");
		$current = [];

		foreach ($query->rows as $row) {
			$current[(int)$row['id']] = $row;
		}

		$rows = [];

		foreach ($definitions as $id => $definition) {
			$id = (int)$id;

			if ($id < 1 || !is_array($definition) || !isset($current[$id])) {
				continue;
			}

			$name = trim((string)($definition['name'] ?? ''));
			$description = trim((string)($definition['description'] ?? ''));
			$current_name = (string)$current[$id]['name'];
			$current_description = (string)$current[$id]['description'];

			if ($name === '' && $description === '') {
				continue;
			}

			$rows[] = [
				'key' => 'customer_group:' . $id,
				'label' => (string)$id,
				'current' => $current_name,
				'current_extra' => $current_description,
				'name' => $name,
				'extra' => $description,
				'apply' => $current_name !== $name || $current_description !== $description
			];
		}

		return $rows;
	}

	/**
	 * @param array<string, mixed> $definitions
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function currencyTranslationRows(array $definitions): array
	{
		$title = trim((string)($definitions['RUB'] ?? ''));

		if ($title === '') {
			return [];
		}

		$query = $this->db->query("SELECT `title` FROM `" . DB_PREFIX . "currency` WHERE `code` = 'RUB' LIMIT 1");

		if (!$query->num_rows) {
			return [];
		}

		return [[
			'key' => 'currency:RUB',
			'label' => 'RUB',
			'current' => (string)$query->row['title'],
			'current_extra' => '',
			'name' => $title,
			'extra' => '',
			'apply' => false
		]];
	}

	/**
	 * @return array{key: string, current: string, current_extra: string}|null
	 */
	private function writeTranslation(string $type, string $code, string $name, string $extra, int $language_id): ?array
	{
		$map = $this->localisationMap();

		if ($type === 'country' && $code === 'RU') {
			$name = $this->clip($name, 128);
			$country_id = $this->findCountryId('RU');

			if ($name === '' || !$country_id || !$this->updateNamedRow('country_description', 'country_id', $country_id, $language_id, $name)) {
				return null;
			}

			return ['key' => 'country:RU', 'current' => $name, 'current_extra' => ''];
		}

		if ($type === 'region' && isset($map['region'][$code])) {
			$name = $this->clip($name, 128);
			$country_id = $this->findCountryId('RU');
			$zones = $country_id ? $this->russianZoneRecords($country_id, $language_id) : [];

			if ($name === '' || !isset($zones[$code]) || !$this->updateNamedRow('zone_description', 'zone_id', (int)$zones[$code]['zone_id'], $language_id, $name)) {
				return null;
			}

			return ['key' => 'region:' . $code, 'current' => $name, 'current_extra' => ''];
		}

		if (($type === 'length' || $type === 'weight') && isset($map[$type][$code]) && is_array($map[$type][$code])) {
			return $this->writeMeasure($type, $code, $name, $extra, $language_id);
		}

		$status = [
			'stock' => ['stock_status', 'stock_status_id', 'stock_status'],
			'order' => ['order_status', 'order_status_id', 'order_status'],
			'return_status' => ['return_status', 'return_status_id', 'return_status'],
			'return_action' => ['return_action', 'return_action_id', 'return_action'],
			'return_reason' => ['return_reason', 'return_reason_id', 'return_reason'],
			'subscription' => ['subscription_status', 'subscription_status_id', 'subscription_status']
		];

		if (isset($status[$type])) {
			$id = (int)$code;
			$name = $this->clip($name, 32);
			$source = $status[$type][2];

			if ($id < 1 || $name === '' || !isset($map[$source][$id]) || !$this->updateNamedRow($status[$type][0], $status[$type][1], $id, $language_id, $name)) {
				return null;
			}

			return ['key' => $type . ':' . $id, 'current' => $name, 'current_extra' => ''];
		}

		if ($type === 'customer_group') {
			return $this->writeCustomerGroup((int)$code, $name, $extra, $language_id);
		}

		if ($type === 'currency' && $code === 'RUB') {
			$name = $this->clip($name, 32);

			if ($name === '' || !isset($map['currency']['RUB'])) {
				return null;
			}

			$query = $this->db->query("SELECT `currency_id` FROM `" . DB_PREFIX . "currency` WHERE `code` = 'RUB' LIMIT 1");

			if (!$query->num_rows) {
				return null;
			}

			$this->db->query("UPDATE `" . DB_PREFIX . "currency` SET `title` = '" . $this->db->escape($name) . "' WHERE `code` = 'RUB'");

			return ['key' => 'currency:RUB', 'current' => $name, 'current_extra' => ''];
		}

		return null;
	}

	/**
	 * @return array{key: string, current: string, current_extra: string}|null
	 */
	private function writeMeasure(string $type, string $code, string $name, string $extra, int $language_id): ?array
	{
		$map = $this->localisationMap();
		$definition = $map[$type][$code];
		$table = $type === 'weight' ? 'weight_class' : 'length_class';
		$id_column = $table . '_id';
		$name = $this->clip($name, 32);
		$extra = $this->clip($extra, 4);

		if ($name === '' && $extra === '') {
			return null;
		}

		$query = $this->db->query("SELECT `c`.`" . $id_column . "` AS `id`, `c`.`value`, `d`.`title`, `d`.`unit` FROM `" . DB_PREFIX . $table . "` `c` LEFT JOIN `" . DB_PREFIX . $table . "_description` `d` ON (`c`.`" . $id_column . "` = `d`.`" . $id_column . "` AND `d`.`language_id` = '" . (int)$language_id . "')");
		$match = $this->matchMeasure($query->rows, $code, (string)($definition['unit'] ?? ''), (string)($definition['value'] ?? ''));

		if (!$match) {
			return null;
		}

		$fields = [];

		if ($name !== '') {
			$fields[] = "`title` = '" . $this->db->escape($name) . "'";
		}

		if ($extra !== '') {
			$fields[] = "`unit` = '" . $this->db->escape($extra) . "'";
		}

		$this->db->query("UPDATE `" . DB_PREFIX . $table . "_description` SET " . implode(', ', $fields) . " WHERE `" . $id_column . "` = '" . (int)$match['id'] . "' AND `language_id` = '" . (int)$language_id . "'");

		$current_title = $name !== '' ? $name : (string)$match['title'];
		$current_unit = $extra !== '' ? $extra : (string)$match['unit'];

		return [
			'key' => $type . ':' . $code,
			'current' => $current_title,
			'current_extra' => $current_unit
		];
	}

	/**
	 * @return array{key: string, current: string, current_extra: string}|null
	 */
	private function writeCustomerGroup(int $id, string $name, string $extra, int $language_id): ?array
	{
		$map = $this->localisationMap();
		$name = $this->clip($name, 32);
		$extra = $this->clip($extra, 65535);

		if ($id < 1 || ($name === '' && $extra === '') || !isset($map['customer_group'][$id])) {
			return null;
		}

		$query = $this->db->query("SELECT `name`, `description` FROM `" . DB_PREFIX . "customer_group_description` WHERE `customer_group_id` = '" . (int)$id . "' AND `language_id` = '" . (int)$language_id . "' LIMIT 1");

		if (!$query->num_rows) {
			return null;
		}

		$fields = [];

		if ($name !== '') {
			$fields[] = "`name` = '" . $this->db->escape($name) . "'";
		}

		if ($extra !== '') {
			$fields[] = "`description` = '" . $this->db->escape($extra) . "'";
		}

		$this->db->query("UPDATE `" . DB_PREFIX . "customer_group_description` SET " . implode(', ', $fields) . " WHERE `customer_group_id` = '" . (int)$id . "' AND `language_id` = '" . (int)$language_id . "'");

		return [
			'key' => 'customer_group:' . $id,
			'current' => $name !== '' ? $name : (string)$query->row['name'],
			'current_extra' => $extra !== '' ? $extra : (string)$query->row['description']
		];
	}

	private function updateNamedRow(string $table, string $id_column, int $id, int $language_id, string $name): bool
	{
		$allowed = [
			'country_description' => 'country_id',
			'zone_description' => 'zone_id',
			'stock_status' => 'stock_status_id',
			'order_status' => 'order_status_id',
			'return_status' => 'return_status_id',
			'return_action' => 'return_action_id',
			'return_reason' => 'return_reason_id',
			'subscription_status' => 'subscription_status_id'
		];

		if (!isset($allowed[$table]) || $allowed[$table] !== $id_column || $id < 1 || $name === '') {
			return false;
		}

		$query = $this->db->query("SELECT `" . $id_column . "` FROM `" . DB_PREFIX . $table . "` WHERE `" . $id_column . "` = '" . (int)$id . "' AND `language_id` = '" . (int)$language_id . "' LIMIT 1");

		if (!$query->num_rows) {
			return false;
		}

		$this->db->query("UPDATE `" . DB_PREFIX . $table . "` SET `name` = '" . $this->db->escape($name) . "' WHERE `" . $id_column . "` = '" . (int)$id . "' AND `language_id` = '" . (int)$language_id . "'");

		return true;
	}

	private function clip(string $value, int $limit): string
	{
		$value = trim($value);

		if ($value === '') {
			return '';
		}

		return mb_substr($value, 0, $limit);
	}

	private function translationCacheKey(string $type): string
	{
		$caches = [
			'country' => 'country',
			'region' => 'zone',
			'length' => 'length_class',
			'weight' => 'weight_class',
			'stock' => 'stock_status',
			'order' => 'order_status',
			'return_status' => 'return_status',
			'return_action' => 'return_action',
			'return_reason' => 'return_reason',
			'subscription' => 'subscription_status',
			'customer_group' => 'customer_group',
			'currency' => 'currency'
		];

		return $caches[$type] ?? '';
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

	/**
	 * @return array{version: string, url: string, kind: string}|null
	 */
	private function latestGithubRelease(string $repo): ?array
	{
		$release = $this->githubRelease($repo);
		$tag = $this->githubBestTag($repo);

		if ($release === null && $tag === null) {
			return null;
		}

		if ($release !== null && ($tag === null || $this->compareVersions($release['version'], $tag['version']) >= 0)) {
			return $release;
		}

		return $tag;
	}

	/**
	 * @return array{version: string, url: string, kind: string}|null
	 */
	private function githubRelease(string $repo): ?array
	{
		$response = $this->githubGet('https://api.github.com/repos/' . $repo . '/releases/latest');

		if ($response['status'] === 404) {
			return null;
		}

		if ($response['status'] !== 200 || !is_array($response['body'])) {
			return null;
		}

		$version = $this->plainVersion((string)($response['body']['tag_name'] ?? ''));

		if ($version === '') {
			return null;
		}

		$url = trim((string)($response['body']['html_url'] ?? ''));

		if ($url === '') {
			$url = 'https://github.com/' . $repo . '/releases/tag/' . rawurlencode((string)($response['body']['tag_name'] ?? $version));
		}

		return [
			'version' => $version,
			'url' => $url,
			'kind' => 'release'
		];
	}

	/**
	 * @return array{version: string, url: string, kind: string}|null
	 */
	private function githubBestTag(string $repo): ?array
	{
		$url = 'https://api.github.com/repos/' . $repo . '/tags?per_page=30';
		$best = '';
		$best_name = '';
		$ok = false;

		for ($page = 0; $page < 5 && $url !== ''; $page++) {
			$response = $this->githubGet($url);

			if ($response['status'] !== 200 || !is_array($response['body'])) {
				return $ok ? $this->tagResult($repo, $best, $best_name) : null;
			}

			$ok = true;
			$count = 0;

			foreach ($response['body'] as $tag) {
				if (!is_array($tag)) {
					continue;
				}

				$count++;
				$name = trim((string)($tag['name'] ?? ''));
				$version = $this->plainVersion($name);

				if ($version === '') {
					continue;
				}

				if ($best === '' || $this->compareVersions($version, $best) > 0) {
					$best = $version;
					$best_name = $name;
				}
			}

			$next = (string)($response['next'] ?? '');

			if ($next === '' || $count < 30) {
				break;
			}

			$url = $next;
		}

		return $this->tagResult($repo, $best, $best_name);
	}

	/**
	 * @return array{version: string, url: string, kind: string}|null
	 */
	private function tagResult(string $repo, string $version, string $name): ?array
	{
		if ($version === '') {
			return null;
		}

		$tag = $name !== '' ? $name : $version;

		return [
			'version' => $version,
			'url' => 'https://github.com/' . $repo . '/releases/tag/' . rawurlencode($tag),
			'kind' => 'tag'
		];
	}

	/**
	 * @return array{status: int, body: array<mixed>|null, next: string}
	 */
	private function githubGet(string $url): array
	{
		$curl = curl_init();

		curl_setopt_array($curl, [
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => true,
			CURLOPT_CONNECTTIMEOUT => 5,
			CURLOPT_TIMEOUT => 5,
			CURLOPT_USERAGENT => 'ocn-language-russian',
			CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json'],
			CURLOPT_FOLLOWLOCATION => true
		]);

		$body = curl_exec($curl);
		$status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
		$header_size = (int)curl_getinfo($curl, CURLINFO_HEADER_SIZE);
		$error = curl_errno($curl);
		curl_close($curl);

		if ($body === false || $error) {
			return ['status' => 0, 'body' => null, 'next' => ''];
		}

		$headers = substr((string)$body, 0, $header_size);
		$payload = substr((string)$body, $header_size);
		$decoded = json_decode($payload, true);

		return [
			'status' => $status,
			'body' => is_array($decoded) ? $decoded : null,
			'next' => $this->githubNextLink($headers)
		];
	}

	private function githubNextLink(string $headers): string
	{
		if (!preg_match_all('/^Link:\s*(.+)$/mi', $headers, $lines) || !$lines[1]) {
			return '';
		}

		$links = $lines[1];
		$line = (string)end($links);

		if (!preg_match('/<([^>]+)>;\s*rel="next"/', $line, $match)) {
			return '';
		}

		return $match[1];
	}

	private function plainVersion(string $version): string
	{
		$version = trim($version);

		if (str_starts_with(strtolower($version), 'v')) {
			$version = substr($version, 1);
		}

		return trim($version);
	}

	private function compareVersions(string $left, string $right): int
	{
		$a = $this->versionParts($left);
		$b = $this->versionParts($right);
		$length = max(count($a['numbers']), count($b['numbers']));

		for ($i = 0; $i < $length; $i++) {
			$av = $a['numbers'][$i] ?? 0;
			$bv = $b['numbers'][$i] ?? 0;

			if ($av !== $bv) {
				return $av <=> $bv;
			}
		}

		return $a['re'] <=> $b['re'];
	}

	/**
	 * @return array{numbers: array<int, int>, re: int}
	 */
	private function versionParts(string $version): array
	{
		$version = $this->plainVersion($version);
		$re = 0;

		if (preg_match('/\.RE(\d+)$/i', $version, $match)) {
			$re = (int)$match[1];
			$version = substr($version, 0, -strlen($match[0]));
		}

		$numbers = [];

		foreach (explode('.', $version) as $part) {
			$numbers[] = (int)$part;
		}

		return ['numbers' => $numbers, 're' => $re];
	}

	private function extensionVersion(): string
	{
		return trim((string)($this->extensionInstall()['version'] ?? ''));
	}

	/**
	 * @return array{repository: string, forum: string, site: string}
	 */
	private function githubRepo(): ?string
	{
		$link = trim((string)($this->extensionInstall()['link'] ?? ''));
		$parts = parse_url($link);

		if (!is_array($parts)) {
			return null;
		}

		$host = strtolower((string)($parts['host'] ?? ''));

		if ($host !== 'github.com' && $host !== 'www.github.com') {
			return null;
		}

		$path = trim((string)($parts['path'] ?? ''), '/');
		$path = preg_replace('/\.git$/', '', $path) ?? '';

		if (!preg_match('#^([^/]+)/([^/]+)$#', $path, $match)) {
			return null;
		}

		if ($match[1] === '' || $match[2] === '') {
			return null;
		}

		return $match[1] . '/' . $match[2];
	}

	/**
	 * @return array{repository: string, forum: string, site: string}
	 */
	private function extensionLinks(): array
	{
		return [
			'repository' => trim((string)($this->extensionInstall()['link'] ?? '')),
			'forum' => 'https://forum.opencart.name/resources/Русский-язык-для-opencart-4.131/',
			'site' => 'https://www.opencart.com/index.php?route=marketplace/extension/info&extension_id=39070'
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function extensionInstall(): array
	{
		$file = DIR_EXTENSION . 'ocn_language_russian/install.json';

		if (!is_file($file)) {
			return [];
		}

		$install = json_decode((string)file_get_contents($file), true);

		return is_array($install) ? $install : [];
	}
}
