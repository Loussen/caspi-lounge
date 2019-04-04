<?php
class ModelExtensionTltBlogTltTag extends Model {
	public function addTltTag($data) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "tlttag SET sort_order = '" . (int)$data['sort_order'] . "', status = '" . (int)$data['status'] . "'");

		$tlttag_id = $this->db->getLastId();

		foreach ($data['tlttag_description'] as $language_id => $value) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "tlttag_description SET tlttag_id = '" . (int)$tlttag_id . "', language_id = '" . (int)$language_id . "', title = '" . $this->db->escape($value['title']) . "', meta_title = '" . $this->db->escape($value['meta_title']) . "', meta_description = '" . $this->db->escape($value['meta_description']) . "', meta_keyword = '" . $this->db->escape($value['meta_keyword']) . "'");
		}

		if (isset($data['tlttag_store'])) {
			foreach ($data['tlttag_store'] as $store_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "tlttag_to_store SET tlttag_id = '" . (int)$tlttag_id . "', store_id = '" . (int)$store_id . "'");
			}
		}

        $this->load->model('extension/tltblog/url_alias');

        foreach ($data['keyword'] as $language_id => $keyword) {
            if (utf8_strlen($keyword) > 0) {
                $this->model_extension_tltblog_url_alias->saveUrlAlias($keyword, 'tlttag_id=' . (int)$tlttag_id, $language_id);
            }
		}

		$this->cache->delete('tltblog');

		return $tlttag_id;
	}

	public function editTltTag($tlttag_id, $data) {
		$this->db->query("UPDATE " . DB_PREFIX . "tlttag SET sort_order = '" . (int)$data['sort_order'] . "', status = '" . (int)$data['status'] . "' WHERE tlttag_id = '" . (int)$tlttag_id . "'");

		$this->db->query("DELETE FROM " . DB_PREFIX . "tlttag_description WHERE tlttag_id = '" . (int)$tlttag_id . "'");

		foreach ($data['tlttag_description'] as $language_id => $value) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "tlttag_description SET tlttag_id = '" . (int)$tlttag_id . "', language_id = '" . (int)$language_id . "', title = '" . $this->db->escape($value['title']) . "', meta_title = '" . $this->db->escape($value['meta_title']) . "', meta_description = '" . $this->db->escape($value['meta_description']) . "', meta_keyword = '" . $this->db->escape($value['meta_keyword']) . "'");
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "tlttag_to_store WHERE tlttag_id = '" . (int)$tlttag_id . "'");

		if (isset($data['tlttag_store'])) {
			foreach ($data['tlttag_store'] as $store_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "tlttag_to_store SET tlttag_id = '" . (int)$tlttag_id . "', store_id = '" . (int)$store_id . "'");
			}
		}

		$this->load->model('extension/tltblog/url_alias');

        $this->model_extension_tltblog_url_alias->deleteUrlAlias('tlttag_id=' . (int)$tlttag_id);

        foreach ($data['keyword'] as $language_id => $keyword) {
            if (utf8_strlen($keyword) > 0) {
                $this->model_extension_tltblog_url_alias->saveUrlAlias($keyword, 'tlttag_id=' . (int)$tlttag_id, $language_id);
            }
        }

		$this->cache->delete('tltblog');
	}

	public function copyTltTag($tlttag_id) {
		$query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "tlttag b LEFT JOIN " . DB_PREFIX . "tlttag_description bd ON (b.tlttag_id = bd.tlttag_id) WHERE b.tlttag_id = '" . (int)$tlttag_id . "' AND bd.language_id = '" . (int)$this->config->get('config_language_id') . "'");

		if ($query->num_rows) {
			$data = $query->row;

			$data['keyword'] = array();
			$data['status'] = '0';

			$tlttag_descriptions = $this->getTltTagDescription($tlttag_id);
			
			foreach ($tlttag_descriptions as $result) {
				$tlttag_description_data[$result['language_id']] = array(
					'language_id'	   => $result['language_id'],
					'title'            => (strlen('Copy of ' . $result['title']) < 255 ? 'Copy of ' . $result['title'] : $result['title']),
					'meta_title'       => $result['meta_title'],
					'meta_description' => $result['meta_description'],
					'meta_keyword'     => $result['meta_keyword'],
				);
			}

			$data['tlttag_description'] = $tlttag_description_data;
			$data['tlttag_store'] = $this->getTltTagStores($tlttag_id);

			$this->addTltTag($data);
		}
	}

	public function deleteTltTag($tlttag_id)
    {
		$this->db->query("DELETE FROM " . DB_PREFIX . "tlttag WHERE tlttag_id = '" . (int)$tlttag_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "tlttag_description WHERE tlttag_id = '" . (int)$tlttag_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "tltblog_to_tag WHERE tlttag_id = '" . (int)$tlttag_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "tlttag_to_store WHERE tlttag_id = '" . (int)$tlttag_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "tltblog_url_alias WHERE query = 'tlttag_id=" . (int)$tlttag_id . "'");

		$this->cache->delete('tltblog');
	}

	public function getTltTag($tlttag_id)
    {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "tlttag t LEFT JOIN " . DB_PREFIX . "tlttag_description td ON (t.tlttag_id = td.tlttag_id) WHERE t.tlttag_id = '" . (int)$tlttag_id . "' AND td.language_id = '" . (int)$this->config->get('config_language_id') . "'");

		return $query->row;
	}

	public function getTltTags($data = array()) {
		if ($data) {
			$sql = "SELECT * FROM " . DB_PREFIX . "tlttag t LEFT JOIN " . DB_PREFIX . "tlttag_description td ON (t.tlttag_id = td.tlttag_id) WHERE td.language_id = '" . (int)$this->config->get('config_language_id') . "'";

			if (!empty($data['filter_title'])) {
				$sql .= " AND td.title LIKE '%" . $this->db->escape($data['filter_title']) . "%'";
			}

			if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
				$sql .= " AND t.status = '" . (int)$data['filter_status'] . "'";
			}

			$sort_data = array(
				'td.title',
				't.status',
				't.sort_order'
			);

			if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
				$sql .= " ORDER BY " . $data['sort'];
			} else {
				$sql .= " ORDER BY td.title";
			}

			if (isset($data['order']) && ($data['order'] == 'DESC')) {
				$sql .= " DESC";
			} else {
				$sql .= " ASC";
			}

			if (isset($data['start']) || isset($data['limit'])) {
				if ($data['start'] < 0) {
					$data['start'] = 0;
				}

				if ($data['limit'] < 1) {
					$data['limit'] = 20;
				}

				$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
			}

			$query = $this->db->query($sql);

			return $query->rows;
		} else {
			$tlttag_data = $this->cache->get('tltblog.alltlttags.' . (int)$this->config->get('config_language_id'));

			if (!$tlttag_data) {
				$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "tlttag t LEFT JOIN " . DB_PREFIX . "tlttag_description td ON (t.tlttag_id = td.tlttag_id) WHERE td.language_id = '" . (int)$this->config->get('config_language_id') . "' AND t.status = '1' ORDER BY td.title");

				$tlttag_data = $query->rows;

				$this->cache->set('tltblog.alltlttags.' . (int)$this->config->get('config_language_id'), $tlttag_data);
			}

			return $tlttag_data;
		}
	}

	public function getTltTagDescription($tlttag_id) {
		$tlttag_description_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "tlttag_description WHERE tlttag_id = '" . (int)$tlttag_id . "'");

		foreach ($query->rows as $result) {
			$tlttag_description_data[$result['language_id']] = array(
				'tlttag_id'		   => $result['tlttag_id'],
				'language_id'      => $result['language_id'],
				'title'            => $result['title'],
				'meta_title'       => $result['meta_title'],
				'meta_description' => $result['meta_description'],
				'meta_keyword'     => $result['meta_keyword'],
			);
		}

		return $tlttag_description_data;
	}

	public function getTltTagsForBlog($tltblog_id) {
		$query = $this->db->query("SELECT t.tlttag_id AS tlttag_id, td.title AS title FROM  " . DB_PREFIX . "tltblog_to_tag b2t LEFT JOIN  " . DB_PREFIX . "tlttag t ON (b2t.tlttag_id = t.tlttag_id) LEFT JOIN  " . DB_PREFIX . "tlttag_description td ON (t.tlttag_id = td.tlttag_id) LEFT JOIN  " . DB_PREFIX . "tlttag_to_store t2s ON (t.tlttag_id = t2s.tlttag_id) WHERE b2t.tltblog_id = '" . (int)$tltblog_id . "' AND t.status = '1' AND td.language_id = '" . (int)$this->config->get('config_language_id') . "' AND t2s.store_id = '" . (int)$this->config->get('config_store_id') . "' ORDER BY t.sort_order, LCASE(td.title) ASC");

		return $query->rows;
	}

	public function getTltTagStores($tlttag_id) {
		$tlttag_store_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "tlttag_to_store WHERE tlttag_id = '" . (int)$tlttag_id . "'");

		foreach ($query->rows as $result) {
			$tlttag_store_data[] = $result['store_id'];
		}

		return $tlttag_store_data;
	}

	public function getTotalTltTags() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "tlttag");

		return $query->row['total'];
	}
}