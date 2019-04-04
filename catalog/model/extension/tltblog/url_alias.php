<?php
class ModelExtensionTltBlogUrlAlias extends Model {
	public function getUrlAliasByKeyword($keyword) {
		$result = $this->db->query("SELECT * FROM " . DB_PREFIX . "tltblog_url_alias WHERE keyword = '" . $this->db->escape($keyword) . "'");

		return $result->row;
	}

	public function getUrlAliasByQuery($query, $language = '') {
	    if (!$language) {
	        $language = $this->config->get('config_language_id');
        }
		$result = $this->db->query("SELECT * FROM " . DB_PREFIX . "tltblog_url_alias WHERE query = '" . $this->db->escape($query) . "' AND language_id = '" . $language . "'");

		return $result->row;
	}
}