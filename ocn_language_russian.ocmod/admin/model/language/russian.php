<?php
namespace Opencart\Admin\Model\Extension\OcnLanguageRussian\Language;
class Russian extends \Opencart\System\Engine\Controller
{
	public function fixSeoUrl(int $language_id): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "seo_url`	SET `value` = 'ru-ru', `keyword` = 'ru-ru' WHERE `language_id` = '" . $language_id . "' AND `key` = 'language'");
	}
}
