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

		if (!$json) {
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('language_russian', $this->request->post);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function install(): void
	{
		if ($this->user->hasPermission('modify', 'extension/language')) {
			$language_info = $this->model_localisation_language->getLanguageByCode('ru-ru');

			if (!$language_info) {
				// Add language
				$language_data = [
					'name' => 'Russian',
					'code' => 'ru-ru',
					'locale' => 'ru-ru',
					'extension' => 'ocn_language_russian',
					'status' => 1,
					'sort_order' => 1
				];

				$this->load->model('localisation/language');
				$language_id = $this->model_localisation_language->addLanguage($language_data);

				// Fix seo url
				// $this->load->model('extension/ocn_language_russian/language/russian');
				// $this->model_extension_ocn_language_russian_language_russian->fixSeoUrl($language_id);
			} else {
				// Edit language
				$this->load->model('localisation/language');

				$this->model_localisation_language->editLanguage($language_info['language_id'], $language_info + ['extension' => 'ocn_language_russian']);
			}
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
}
