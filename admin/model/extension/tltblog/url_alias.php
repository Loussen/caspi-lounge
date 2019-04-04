<?php
class ModelExtensionTltBlogUrlAlias extends Model {
	public function getUrlAlias($keyword) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "tltblog_url_alias WHERE keyword = '" . $this->db->escape($keyword) . "'");

		return $query->row;
	}

    public function getUrlAliasByQuery($url_query) {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "tltblog_url_alias WHERE query = '" . $this->db->escape($url_query) . "'");

        $keywords = array();

        if ($query->num_rows) {
            foreach ($query->rows as $result) {
                $keywords[$result['language_id']] = $result['keyword'];
            }
        }

        return $keywords;
    }

	public function checkUrlAliasIsFree($keyword, $query = '') {
		$tltblog_alias = $this->db->query("SELECT * FROM " . DB_PREFIX . "tltblog_url_alias WHERE keyword = '" . $this->db->escape($keyword) . "'");

		$oc_alias = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE keyword = '" . $this->db->escape($keyword) . "'");

		if ($tltblog_alias->num_rows == 0 && $oc_alias->num_rows == 0) {
			return true;
		} elseif ($oc_alias->num_rows != 0) {
			return false;
		} elseif ($tltblog_alias->row['query'] == $query) {
			return true;
		} else {
			return false;
		}
	}

	public function saveUrlAlias($keyword, $query, $language_id) {
		$sqlquery = $this->db->query("SELECT * FROM " . DB_PREFIX . "tltblog_url_alias WHERE (query = '" . $query . " ' AND language_id = '". $language_id ."') OR keyword = '" . $keyword . "'");
		
		if ($sqlquery->num_rows == 0) {
			$result = $this->db->query("INSERT INTO " . DB_PREFIX . "tltblog_url_alias SET query = '" . $query . "', keyword = '" . $this->db->escape($keyword) . "', language_id = '" . $language_id . "'");

			return $result;
		} else {
			return false;
		}
	}

	public function deleteUrlAlias($query) {
		$result = $this->db->query("DELETE FROM " . DB_PREFIX . "tltblog_url_alias WHERE query LIKE '%" . $query . "%'");

		return $result;
	}
}