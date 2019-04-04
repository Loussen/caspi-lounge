<?php
class ControllerExtensionTltBlogTltBlogSeo extends Controller {
	public function index() {
        $this->config->set('tltblog_seo_passed', 1);

        if (!$this->config->get('tltblog_seo')) {
            return new Action('error/not_found');
		} else {
            $this->url->addRewrite($this);
        }

		$this->load->model('extension/tltblog/url_alias');

		// Decode URL
		if (isset($this->request->get['_route_'])) {
			$parts = explode('/', $this->request->get['_route_']);

			unset($this->request->get['route']);
			
			// remove any empty arrays from trailing
			if (utf8_strlen(end($parts)) == 0) {
				array_pop($parts);
			}

			foreach ($parts as $part) {
				$query = $this->model_extension_tltblog_url_alias->getUrlAliasByKeyword($this->db->escape($part));

				if ($query) {
					$url = explode('=', $query['query']);

					if ($url[0] == 'tltblog_id') {
						$this->request->get['tltblog_id'] = $url[1];
					}

					if ($url[0] == 'tlttag_id') {
						$this->request->get['tlttag_id'] = $url[1];
					}

					if ($url[0] == 'tltpath') {
						$this->request->get['tltpath'] = $url[1];
					}

					if ($query['query'] && $url[0] != 'tltblog_id' && $url[0] != 'tlttag_id' && $url[0] != 'tltpath') {
						$this->request->get['route'] = $query['query'];
					}
                } else {
					$this->request->get['route'] = 'error/not_found';

					break;
				}
			}

			if (!isset($this->request->get['route'])) {
				if (isset($this->request->get['tltblog_id'])) {
					$this->request->get['route'] = 'extension/tltblog/tltblog';
				} elseif (isset($this->request->get['tlttag_id'])) {
					$this->request->get['route'] = 'extension/tltblog/tlttag';
				} elseif (isset($this->request->get['tltpath'])) {
					$this->request->get['route'] = 'extension/tltblog/tlttag';
				}
            }

			if (isset($this->request->get['route'])) {
				return new Action($this->request->get['route']);
			}
		}
	}

	public function rewrite($link) {
		$this->load->model('extension/tltblog/url_alias');
		
		$url_info = parse_url(str_replace('&amp;', '&', $link));

		$url = '';

		$data = array();

		if (isset($url_info['query'])) {
			parse_str($url_info['query'], $data);

            foreach ($data as $key => $value) {
				if (isset($data['route'])) {
					if (($data['route'] == 'extension/tltblog/tltblog' && $key == 'tltblog_id') || ($data['route'] == 'extension/tltblog/tlttag' && $key == 'tlttag_id')){
						$url_alias = $this->model_extension_tltblog_url_alias->getUrlAliasByQuery($this->db->escape($key . '=' . $value));
	
						if ($url_alias) {
							$url .= '/' . $url_alias['keyword'];
	
							unset($data[$key]);
						}
					} elseif ($key == 'tltpath') {
						$url_alias = $this->model_extension_tltblog_url_alias->getUrlAliasByQuery($this->db->escape($key . '=' . $value));
	
						if ($url_alias) {
							$url .= '/' . $url_alias['keyword'];
	
							unset($data[$key]);
						}
					}
				}
			}
		}

		if ($url) {
			unset($data['route']);

			$query = '';

			if ($data) {
				foreach ($data as $key => $value) {
					$query .= '&' . rawurlencode((string)$key) . '=' . rawurlencode((string)$value);
				}

				if ($query) {
					$query = '?' . str_replace('&', '&amp;', trim($query, '&'));
				}
			}

			return $url_info['scheme'] . '://' . $url_info['host'] . (isset($url_info['port']) ? ':' . $url_info['port'] : '') . str_replace('/index.php', '', $url_info['path']) . $url . $query;
		} else {
			return $link;
		}
	}
}
