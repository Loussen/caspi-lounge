<?php
class ControllerExtensionModuleTltBlog extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/tltblog');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/module');
		$this->load->model('localisation/language');
		
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			if (!isset($this->request->get['module_id'])) {
				$this->model_setting_module->addModule('tltblog', $this->request->post);
			} else {
				$this->model_setting_module->editModule($this->request->get['module_id'], $this->request->post);
				$cache_id = md5(http_build_query($this->model_setting_module->getModule($this->request->get['module_id'])));
				$this->cache->delete('tltblog.' . $cache_id);
			}
			
			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		}

        $language_keys = array(
            'heading_title',
            'text_edit',
            'text_enabled',
            'text_disabled',
            'text_sortorder',
            'text_random',
            'text_dateasc',
            'text_datedesc',
            'text_yes',
            'text_no',
            'text_from_blog',
            'entry_name',
            'entry_title',
            'entry_show_title',
            'entry_show_blog_title',
            'entry_show_blogs',
            'entry_limit',
            'entry_sort',
            'entry_num_columns',
            'entry_show_image',
            'entry_width',
            'entry_height',
            'entry_tags_to_show',
            'entry_template',
            'entry_status',
            'help_sort',
            'help_show_blog_title',
            'help_show_blogs',
            'help_limit',
            'help_tags_to_show',
            'help_template',
            'button_save',
		    'button_cancel'
        );

        foreach ($language_keys as $key) {
            $data[$key] = $this->language->get($key);
        }

		$data['user_token'] = $this->session->data['user_token'];

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['name'])) {
			$data['error_name'] = $this->error['name'];
		} else {
			$data['error_name'] = '';
		}

		if (isset($this->error['title'])) {
			$data['error_title'] = $this->error['title'];
		} else {
			$data['error_title'] = array();
		}

		if (isset($this->error['limit'])) {
			$data['error_limit'] = $this->error['limit'];
		} else {
			$data['error_limit'] = '';
		}

		if (isset($this->error['width'])) {
			$data['error_width'] = $this->error['width'];
		} else {
			$data['error_width'] = '';
		}

		if (isset($this->error['height'])) {
			$data['error_height'] = $this->error['height'];
		} else {
			$data['error_height'] = '';
		}

		if (isset($this->error['template'])) {
			$data['error_template'] = $this->error['template'];
		} else {
			$data['error_template'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_module'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);

		if (!isset($this->request->get['module_id'])) {
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('extension/module/tltblog', 'user_token=' . $this->session->data['user_token'], true)
			);
		} else {
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('extension/module/tltblog', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->request->get['module_id'], true)
			);
		}

		if (!isset($this->request->get['module_id'])) {
			$data['action'] = $this->url->link('extension/module/tltblog', 'user_token=' . $this->session->data['user_token'], true);
		} else {
			$data['action'] = $this->url->link('extension/module/tltblog', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->request->get['module_id'], true);
		}

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

		if (isset($this->request->get['module_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$module_info = $this->model_setting_module->getModule($this->request->get['module_id']);
		}

		if (isset($this->request->post['name'])) {
			$data['name'] = $this->request->post['name'];
		} elseif (!empty($module_info)) {
			$data['name'] = $module_info['name'];
		} else {
			$data['name'] = '';
		}

		$data['languages'] = $this->model_localisation_language->getLanguages();

		if (isset($this->request->post['module_description'])) {
			$data['module_description'] = $this->request->post['module_description'];
		} elseif (!empty($module_info)) {
			$data['module_description'] = $module_info['module_description'];
		} else {
			$data['module_description'] = array();
		}

		if (isset($this->request->post['show_title'])) {
			$data['show_title'] = $this->request->post['show_title'];
		} elseif (!empty($module_info)) {
			$data['show_title'] = $module_info['show_title'];
		} else {
			$data['show_title'] = '1';
		}

		if (isset($this->request->post['show_blog_title'])) {
			$data['show_blog_title'] = $this->request->post['show_blog_title'];
		} elseif (!empty($module_info) && isset($module_info['show_blog_title'])) {
			$data['show_blog_title'] = $module_info['show_blog_title'];
		} else {
			$data['show_blog_title'] = '1';
		}

		if (isset($this->request->post['show_blogs'])) {
			$data['show_blogs'] = $this->request->post['show_blogs'];
		} elseif (!empty($module_info)) {
			$data['show_blogs'] = $module_info['show_blogs'];
		} else {
			$data['show_blogs'] = '1';
		}

		if (isset($this->request->post['num_columns'])) {
			$data['num_columns'] = $this->request->post['num_columns'];
		} elseif (!empty($module_info)) {
			$data['num_columns'] = $module_info['num_columns'];
		} else {
			$data['num_columns'] = 1;
		}

		if (isset($this->request->post['limit'])) {
			$data['limit'] = $this->request->post['limit'];
		} elseif (!empty($module_info)) {
			$data['limit'] = (int)$module_info['limit'];
		} else {
			$data['limit'] = 6;
		}

		if (isset($this->request->post['show_image'])) {
			$data['show_image'] = $this->request->post['show_image'];
		} elseif (!empty($module_info)) {
			$data['show_image'] = $module_info['show_image'];
		} else {
			$data['show_image'] = '1';
		}

		if (isset($this->request->post['width'])) {
			$data['width'] = $this->request->post['width'];
		} elseif (!empty($module_info)) {
			$data['width'] = (int)$module_info['width'];
		} else {
			$data['width'] = 200;
		}

		if (isset($this->request->post['height'])) {
			$data['height'] = $this->request->post['height'];
		} elseif (!empty($module_info)) {
			$data['height'] = (int)$module_info['height'];
		} else {
			$data['height'] = 200;
		}

		$this->load->model('extension/tltblog/tlttag');

		if (isset($this->request->post['tags_to_show'])) {
			$tags_to_show = $this->request->post['tags_to_show'];
		} elseif (!empty($module_info) && isset($module_info['tags_to_show'])) {
			$tags_to_show = $module_info['tags_to_show'];
		} else {
			$tags_to_show = array();
		}
		
		$data['tags_to_show'] = array();
		
		foreach ($tags_to_show as $tlttag_id) {
			$tag_info = $this->model_extension_tltblog_tlttag->getTltTag($tlttag_id);

			if ($tag_info) {
				$data['tags_to_show'][] = array(
					'tlttag_id' => $tag_info['tlttag_id'],
					'title' => $tag_info['title']
				);
			}
		}		

		if (isset($this->request->post['template'])) {
			$data['template'] = $this->request->post['template'];
		} elseif (!empty($module_info) && isset($module_info['template'])) {
			$data['template'] = $module_info['template'];
		} else {
			$data['template'] = 'tltblog';
		}

		if (isset($this->request->post['status'])) {
			$data['status'] = $this->request->post['status'];
		} elseif (!empty($module_info)) {
			$data['status'] = $module_info['status'];
		} else {
			$data['status'] = '';
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/tltblog', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/tltblog')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if ((utf8_strlen($this->request->post['name']) < 3) || (utf8_strlen($this->request->post['name']) > 64)) {
			$this->error['name'] = $this->language->get('error_name');
		}

		foreach ($this->request->post['module_description'] as $language_id => $value) {
			if ((utf8_strlen($value['title']) < 3) || (utf8_strlen($value['title']) > 64)) {
				$this->error['title'][$language_id] = $this->language->get('error_title');
			}
		}

		if (!$this->request->post['limit'] || ((int)$this->request->post['limit'] < 1)) {
			$this->error['limit'] = $this->language->get('error_limit');
		}

		if ($this->request->post['show_image']) {
			if (!$this->request->post['width'] || ((int)$this->request->post['width'] < 1)) {
				$this->error['width'] = $this->language->get('error_width');
			}
	
			if (!$this->request->post['height'] || ((int)$this->request->post['height'] < 1)) {
				$this->error['height'] = $this->language->get('error_height');
			}
		}

		if ((utf8_strlen($this->request->post['template']) < 3) || (utf8_strlen($this->request->post['template']) > 64)) {
			$this->error['template'] = $this->language->get('error_template');
		}

		return !$this->error;
	}

    public function install() {

    }

    public function uninstall() {
		$this->cache->delete('tltblog');
    }
}