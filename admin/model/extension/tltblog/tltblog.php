<?php
class ModelExtensionTltBlogTltBlog extends Model {
	public function addTltBlog($data) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "tltblog SET sort_order = '" . (int)$data['sort_order'] . "', image = '" . (isset($data['image']) ? $this->db->escape($data['image']) : '') . "', bottom = '" . (isset($data['bottom']) ? (int)$data['bottom'] : 0) . "', show_title = '" . (isset($data['show_title']) ? (int)$data['show_title'] : 0) . "', status = '" . (int)$data['status'] . "', show_description = '" . (isset($data['show_description']) ? (int)$data['show_description'] : 0) . "'");

		$tltblog_id = $this->db->getLastId();

		foreach ($data['tltblog_description'] as $language_id => $value) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "tltblog_description SET tltblog_id = '" . (int)$tltblog_id . "', language_id = '" . (int)$language_id . "', title = '" . $this->db->escape($value['title']) . "', intro = '" . $this->db->escape($value['intro']) . "', description = '" . $this->db->escape($value['description']) . "', meta_title = '" . $this->db->escape($value['meta_title']) . "', meta_description = '" . $this->db->escape($value['meta_description']) . "', meta_keyword = '" . $this->db->escape($value['meta_keyword']) . "'");
		}

		if (isset($data['tltblog_related'])) {
			foreach ($data['tltblog_related'] as $related_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "tltblog_related SET tltblog_id = '" . (int)$tltblog_id . "', related_id = '" . (int)$related_id . "'");
			}
		}

		if (isset($data['tltblog_tags'])) {
			foreach ($data['tltblog_tags'] as $tlttag_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "tltblog_to_tag SET tltblog_id = '" . (int)$tltblog_id . "', tlttag_id = '" . (int)$tlttag_id . "'");
			}
		}

		if (isset($data['tltblog_store'])) {
			foreach ($data['tltblog_store'] as $store_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "tltblog_to_store SET tltblog_id = '" . (int)$tltblog_id . "', store_id = '" . (int)$store_id . "'");
			}
		}

		if (isset($data['tltblog_layout'])) {
			foreach ($data['tltblog_layout'] as $store_id => $layout_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "tltblog_to_layout SET tltblog_id = '" . (int)$tltblog_id . "', store_id = '" . (int)$store_id . "', layout_id = '" . (int)$layout_id . "'");
			}
		}

        $this->load->model('extension/tltblog/url_alias');

        foreach ($data['keyword'] as $language_id => $keyword) {
            if (utf8_strlen($keyword) > 0) {
                $this->model_extension_tltblog_url_alias->saveUrlAlias($keyword, 'tltblog_id=' . (int)$tltblog_id, $language_id);
            }
        }

		$this->cache->delete('tltblog');

		return $tltblog_id;
	}

	public function editTltBlog($tltblog_id, $data) {
		$this->db->query("UPDATE " . DB_PREFIX . "tltblog SET sort_order = '" . (int)$data['sort_order'] . "', image = '" . (isset($data['image']) ? $this->db->escape($data['image']) : '') . "', bottom = '" . (isset($data['bottom']) ? (int)$data['bottom'] : 0) . "', show_title = '" . (isset($data['show_title']) ? (int)$data['show_title'] : 0) . "', status = '" . (int)$data['status'] . "', show_description = '" . (isset($data['show_description']) ? (int)$data['show_description'] : 0) . "' WHERE tltblog_id = '" . (int)$tltblog_id . "'");

		$this->db->query("DELETE FROM " . DB_PREFIX . "tltblog_description WHERE tltblog_id = '" . (int)$tltblog_id . "'");

		foreach ($data['tltblog_description'] as $language_id => $value) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "tltblog_description SET tltblog_id = '" . (int)$tltblog_id . "', language_id = '" . (int)$language_id . "', title = '" . $this->db->escape($value['title']) . "', intro = '" . $this->db->escape($value['intro']) . "', description = '" . $this->db->escape($value['description']) . "', meta_title = '" . $this->db->escape($value['meta_title']) . "', meta_description = '" . $this->db->escape($value['meta_description']) . "', meta_keyword = '" . $this->db->escape($value['meta_keyword']) . "'");
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "tltblog_related WHERE tltblog_id = '" . (int)$tltblog_id . "'");

		if (isset($data['tltblog_related'])) {
			foreach ($data['tltblog_related'] as $related_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "tltblog_related SET tltblog_id = '" . (int)$tltblog_id . "', related_id = '" . (int)$related_id . "'");
			}
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "tltblog_to_tag WHERE tltblog_id = '" . (int)$tltblog_id . "'");

		if (isset($data['tltblog_tags'])) {
			foreach ($data['tltblog_tags'] as $tlttag_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "tltblog_to_tag SET tltblog_id = '" . (int)$tltblog_id . "', tlttag_id = '" . (int)$tlttag_id . "'");
			}
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "tltblog_to_store WHERE tltblog_id = '" . (int)$tltblog_id . "'");

		if (isset($data['tltblog_store'])) {
			foreach ($data['tltblog_store'] as $store_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "tltblog_to_store SET tltblog_id = '" . (int)$tltblog_id . "', store_id = '" . (int)$store_id . "'");
			}
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "tltblog_to_layout WHERE tltblog_id = '" . (int)$tltblog_id . "'");

		if (isset($data['tltblog_layout'])) {
			foreach ($data['tltblog_layout'] as $store_id => $layout_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "tltblog_to_layout SET tltblog_id = '" . (int)$tltblog_id . "', store_id = '" . (int)$store_id . "', layout_id = '" . (int)$layout_id . "'");
			}
		}

        $this->load->model('extension/tltblog/url_alias');

        $this->model_extension_tltblog_url_alias->deleteUrlAlias('tltblog_id=' . (int)$tltblog_id);

        foreach ($data['keyword'] as $language_id => $keyword) {
            if (utf8_strlen($keyword) > 0) {
                $this->model_extension_tltblog_url_alias->saveUrlAlias($keyword, 'tltblog_id=' . (int)$tltblog_id, $language_id);
            }
        }

		$this->cache->delete('tltblog');
	}

	public function copyTltBlog($tltblog_id) {
		$query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "tltblog b LEFT JOIN " . DB_PREFIX . "tltblog_description bd ON (b.tltblog_id = bd.tltblog_id) WHERE b.tltblog_id = '" . (int)$tltblog_id . "' AND bd.language_id = '" . (int)$this->config->get('config_language_id') . "'");
		if ($query->num_rows) {
			$data = $query->row;

			$data['keyword'] = array();
			$data['status'] = '0';

			$tltblog_descriptions = $this->getTltBlogDescription($tltblog_id);
			
			foreach ($tltblog_descriptions as $result) {
				$tltblog_description_data[$result['language_id']] = array(
					'language_id'	   => $result['language_id'],
					'title'            => (strlen('Copy of ' . $result['title']) < 255 ? 'Copy of ' . $result['title'] : $result['title']),
					'author'  	       => $result['author'],
					'intro'  	       => $result['intro'],
					'description'      => $result['description'],
					'meta_title'       => $result['meta_title'],
					'meta_description' => $result['meta_description'],
					'meta_keyword'     => $result['meta_keyword'],
				);
			}

			$data['tltblog_description'] = $tltblog_description_data;
			$data['tltblog_related'] = $this->getTltBlogRelated($tltblog_id);
			$data['tltblog_layout'] = $this->getTltBlogLayouts($tltblog_id);
			$data['tltblog_store'] = $this->getTltBlogStores($tltblog_id);
			$data['tltblog_tags'] = $this->getTltBlogTags($tltblog_id);

			$this->addTltBlog($data);
		}
	}

	public function deleteTltBlog($tltblog_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "tltblog WHERE tltblog_id = '" . (int)$tltblog_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "tltblog_description WHERE tltblog_id = '" . (int)$tltblog_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "tltblog_related WHERE tltblog_id = '" . (int)$tltblog_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "tltblog_to_store WHERE tltblog_id = '" . (int)$tltblog_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "tltblog_to_tag WHERE tltblog_id = '" . (int)$tltblog_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "tltblog_to_layout WHERE tltblog_id = '" . (int)$tltblog_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "tltblog_url_alias WHERE query = 'tltblog_id=" . (int)$tltblog_id . "'");

		$this->cache->delete('tltblog');
	}

	public function getTltBlog($tltblog_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "tltblog WHERE tltblog_id = '" . (int)$tltblog_id . "'");

		return $query->row;
	}

	public function getTltBlogs($data = array()) {
		if ($data) {
			$sql = "SELECT * FROM " . DB_PREFIX . "tltblog b LEFT JOIN " . DB_PREFIX . "tltblog_description bd ON (b.tltblog_id = bd.tltblog_id) WHERE bd.language_id = '" . (int)$this->config->get('config_language_id') . "'";

			if (!empty($data['filter_title'])) {
				$sql .= " AND bd.title LIKE '%" . $this->db->escape($data['filter_title']) . "%'";
			}

			if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
				$sql .= " AND b.status = '" . (int)$data['filter_status'] . "'";
			}

			$sort_data = array(
				'bd.title',
				'b.status',
				'b.sort_order'
			);

			if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
				$sql .= " ORDER BY " . $data['sort'];
			} else {
				$sql .= " ORDER BY bd.title";
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
			$tltblog_data = $this->cache->get('tltblog.' . (int)$this->config->get('config_language_id'));

			if (!$tltblog_data) {
				$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "tltblog b LEFT JOIN " . DB_PREFIX . "tltblog_description bd ON (b.tltblog_id = bd.tltblog_id) WHERE bd.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY bd.title");

				$tltblog_data = $query->rows;

				$this->cache->set('tltblog.' . (int)$this->config->get('config_language_id'), $tltblog_data);
			}

			return $tltblog_data;
		}
	}

	public function getTltBlogDescription($tltblog_id) {
		$tltblog_description_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "tltblog_description WHERE tltblog_id = '" . (int)$tltblog_id . "'");

		foreach ($query->rows as $result) {
			$tltblog_description_data[$result['language_id']] = array(
				'language_id'      => $result['language_id'],
				'title'            => $result['title'],
				'intro'  	       => $result['intro'],
				'description'      => $result['description'],
				'meta_title'       => $result['meta_title'],
				'meta_description' => $result['meta_description'],
				'meta_keyword'     => $result['meta_keyword'],
			);
		}

		return $tltblog_description_data;
	}

	public function getTltBlogRelated($tltblog_id) {
		$tltblog_related_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "tltblog_related WHERE tltblog_id = '" . (int)$tltblog_id . "'");

		foreach ($query->rows as $result) {
			$tltblog_related_data[] = $result['related_id'];
		}

		return $tltblog_related_data;
	}

	public function getTltBlogTags($tltblog_id) {
		$tltblog_tags_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "tltblog_to_tag WHERE tltblog_id = '" . (int)$tltblog_id . "'");

		foreach ($query->rows as $result) {
			$tltblog_tags_data[] = $result['tlttag_id'];
		}

		return $tltblog_tags_data;
	}

	public function getTltBlogTagsDescription($tltblog_id) {
		$tltblog_tags_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "tlttag_description td LEFT JOIN " . DB_PREFIX . "tltblog_to_tag btt ON (td.tlttag_id = btt.tlttag_id) WHERE tltblog_id = '" . (int)$tltblog_id . "' AND td.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY td.title ASC");

		foreach ($query->rows as $result) {
			$tltblog_tags_data[] = array(
				'tlttag_id'		=> $result['tlttag_id'],
				'title'			=> $result['title']
			);
		}

		return $tltblog_tags_data;
	}

	public function getTltBlogStores($tltblog_id) {
		$tltblog_store_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "tltblog_to_store WHERE tltblog_id = '" . (int)$tltblog_id . "'");

		foreach ($query->rows as $result) {
			$tltblog_store_data[] = $result['store_id'];
		}

		return $tltblog_store_data;
	}

	public function getTltBlogLayouts($tltblog_id) {
		$tltblog_layout_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "tltblog_to_layout WHERE tltblog_id = '" . (int)$tltblog_id . "'");

		foreach ($query->rows as $result) {
			$tltblog_layout_data[$result['store_id']] = $result['layout_id'];
		}

		return $tltblog_layout_data;
	}

	public function getTotalTltBlogs() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "tltblog");

		return $query->row['total'];
	}

	public function getTotalTltBlogsByLayoutId($layout_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "tltblog_to_layout WHERE layout_id = '" . (int)$layout_id . "'");

		return $query->row['total'];
	}
}