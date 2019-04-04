<?php
class ControllerExtensionModuleTltBlog extends Controller {
	public function index($setting) {
		if (isset($setting['module_description'][$this->config->get('config_language_id')]['title'])) {
			$data['heading_title'] = html_entity_decode($setting['module_description'][$this->config->get('config_language_id')]['title'], ENT_QUOTES, 'UTF-8');
			$data['show_title'] = $setting['show_title'];
			
			if (isset($setting['show_blog_title'])) {
				$data['show_blog_title'] = $setting['show_blog_title'];
			} else {
				$data['show_blog_title'] = '1';
			}
			
			$data['show_blogs'] = $setting['show_blogs'];
			$data['num_columns'] = $setting['num_columns'];
			$data['show_image'] = $setting['show_image'];
			
			if (isset($setting['template'])) {
				if (strcmp($setting['template'], 'tltblog') != 0) {
					$theme = $this->config->get('config_theme');
					$pathtotpl = DIR_TEMPLATE . $this->config->get('theme_' . $theme . '_directory') . '/template/';

					if (file_exists($pathtotpl . 'extension/module/' . $setting['template'] . '.twig')) {
						$template = 'extension/module/' . $setting['template'];
					} else {
						$template = 'extension/module/tltblog';
					}
				} else {
					$template = 'extension/module/tltblog';
				}
			} else {
				$template = 'extension/module/tltblog';
			}
			
			$this->load->model('extension/tltblog/tltblog');
			$this->load->model('setting/setting');
			$this->load->model('tool/image');
			
			if ($this->config->get('tltblog_seo')) {
				require_once(DIR_APPLICATION . 'controller/extension/tltblog/tltblog_seo.php');
				$tltblog_seo = new ControllerExtensionTltBlogTltBlogSeo($this->registry);
				$this->url->addRewrite($tltblog_seo);
			}
			
			$data['tltblogs'] = array();

			if (isset($setting['tags_to_show'])) {
				$where_tags = $setting['tags_to_show'];
			} else {
				$where_tags = array();
			}

			$blogs_count = $this->model_extension_tltblog_tltblog->countTltBlogs($where_tags);

			$cache_id = md5(http_build_query($setting));
			
			$results = '';

			if ($setting['limit'] > $blogs_count) {
				$results = $this->cache->get('tltblog.' . $cache_id . '.' . '1' . '.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id'));
			}
			
			if (!$results) {
                $results = $this->model_extension_tltblog_tltblog->getTltBlogs($setting['limit'], $where_tags, 1, 0, false);
                $this->cache->set('tltblog.' . $cache_id . '.' . '1' . '.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id'), $results);
			}

			if ($this->config->has('tltblog_path')) {
			    $path_array = $this->config->get('tltblog_path');
            }
			
			if ($results) {
				if (isset($this->request->get['tltpath'])) {
					$path = $this->request->get['tltpath'];
				} elseif (isset($path_array[$this->config->get('config_language_id')])) {
					$path = $path_array[$this->config->get('config_language_id')];
				} else {
					$path = 'blogs';
				}
								
				foreach ($results as $result) {
					if ($result['image']) {
						$image = $this->model_tool_image->resize($result['image'], $setting['width'], $setting['height']);
					} else {
						$image = $this->model_tool_image->resize('placeholder.png', $setting['width'], $setting['height']);
					}
					
					if ($data['show_blogs'] && $result['show_description']) {
						$data['tltblogs'][] = array(
							'tltblog_id'  		=> $result['tltblog_id'],
							'thumb'       		=> $image,
							'title'       		=> $result['title'],
							'show_title'       	=> ($data['show_blog_title'] == '1') ? '1' : ($result['show_title'] * $data['show_blog_title']),
							'intro'       		=> html_entity_decode($result['intro'], ENT_QUOTES, 'UTF-8'),
							'show_description' 	=> $result['show_description'],
							'href'        		=> $this->url->link('extension/tltblog/tltblog', 'tltpath=' . $path . '&tltblog_id=' . $result['tltblog_id'])
						);
					} else {
						$data['tltblogs'][] = array(
							'tltblog_id'  		=> $result['tltblog_id'],
							'thumb'       		=> $image,
							'title'       		=> $result['title'],
							'show_title'       	=> ($data['show_blog_title'] == '1') ? '1' : ($result['show_title'] * $data['show_blog_title']),
							'intro'       		=> html_entity_decode($result['intro'], ENT_QUOTES, 'UTF-8'),
							'show_description' 	=> '0',
							'href'        		=> ''
						);
					}
				}

				return $this->load->view($template, $data);
			}
		}
	}
}