<?php
define('TLT_BLOG_DB_VERSION', '5.1.3');
define('TLT_BLOG_EDITION', 'FREE');
define('TLT_BLOG_VERSION', '5.1.6');
define('TLT_BLOG_DROP_DB', false);

class ControllerExtensionTltBlogTltBlogSettings extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/tltblog/tltblog_settings');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->load->model('localisation/language');
		$this->load->model('extension/tltblog/url_alias');

        $data['languages'] = $languages = $this->model_localisation_language->getLanguages();

        if ($this->config->get('tltblog_db_version') !== TLT_BLOG_VERSION) $this->upgrade();

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			if ($this->request->post['tltblog_facebook_page']) {
				$this->request->post['tltblog_facebook_page'] = preg_replace(array('/\s/', '/\//'), '', $this->request->post['tltblog_facebook_page']);
			}

			$request_post = $this->request->post;

            if ($this->request->post['tltblog_status']) {
                $request_post['tltblog_tltblog_settings_status'] = '1';
            } else {
                $request_post['tltblog_tltblog_settings_status'] = '0';
            }

            $request_post['tltblog_tltblog_status'] = $this->config->has('tltblog_tltblog_status') ? $this->config->get('tltblog_tltblog_status') : '0';
            $request_post['tltblog_tlttag_status'] = $this->config->has('tltblog_tlttag_status') ? $this->config->get('tltblog_tlttag_status') : '0';

            $request_post['tltblog_db_version'] = TLT_BLOG_DB_VERSION;
            $request_post['tltblog_edition'] = TLT_BLOG_EDITION;
            $request_post['tltblog_version'] = TLT_BLOG_VERSION;

			$this->model_setting_setting->editSetting('tltblog', $request_post);

			$this->model_extension_tltblog_url_alias->deleteUrlAlias('tltpath=');

			foreach ($this->request->post['tltblog_path'] as $language_id => $value) {
                $this->model_extension_tltblog_url_alias->saveUrlAlias($this->db->escape($value), 'tltpath=' . $this->db->escape($value), $language_id);
            }

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=tltblog', true));
		}

        $language_keys = array(
            'heading_title',
            'text_edit',
            'text_enabled',
            'text_disabled',
            'text_yes',
            'text_no',
            'text_summary',
            'text_large_image',
            'text_pro_only',
            'entry_path',
            'entry_path_title',
            'entry_show_path',
            'entry_num_columns',
            'entry_blogs_page',
            'entry_show_date',
            'entry_date_format',
            'entry_show_image',
            'entry_width',
            'entry_height',
            'entry_seo',
            'entry_schemaorg',
            'entry_schemaorg_image',
            'entry_status',
            'entry_meta_title',
            'entry_meta_description',
            'entry_meta_keyword',
            'entry_menu',
            'entry_twitter',
            'entry_twitter_card',
            'entry_twitter_name',
            'entry_facebook',
            'entry_facebook_name',
            'entry_facebook_appid',
            'entry_resize_image',
            'help_facebook_page',
            'help_path',
            'help_path_title',
            'help_show_path',
            'help_blogs_page',
            'help_show_image',
            'help_num_columns',
            'help_date_format',
            'help_seo',
            'help_schemaorg_image',
            'help_status',
            'help_meta',
            'help_menu',
            'help_twitter_status',
            'help_facebook_status',
            'help_resize_image',
            'entry_facebook_page',
            'placeholder_blogs_page',
            'placeholder_username',
            'button_save',
            'button_cancel',
            'tab_general',
            'tab_design',
            'tab_structured_data',
            'error_seo_disabled'
        );

        foreach ($language_keys as $key) {
            $data[$key] = $this->language->get($key);
        }

		if (!property_exists('Document', 'tlt_metatags')) {
			$data['error_library'] = $this->language->get('error_library');
		} else {
			$data['error_library'] = '';
		}
		
		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['tltblog_path'])) {
			$data['error_path'] = $this->error['tltblog_path'];
		} else {
			$data['error_path'] = array();
		}

		if (isset($this->error['tltblog_path_title'])) {
			$data['error_path_title'] = $this->error['tltblog_path_title'];
		} else {
			$data['error_path_title'] = array();
		}

		if (isset($this->error['tltblog_width'])) {
			$data['error_width'] = $this->error['tltblog_width'];
		} else {
			$data['error_width'] = '';
		}

		if (isset($this->error['tltblog_height'])) {
			$data['error_height'] = $this->error['tltblog_height'];
		} else {
			$data['error_height'] = '';
		}

		if (isset($this->error['meta_title'])) {
			$data['error_meta_title'] = $this->error['meta_title'];
		} else {
			$data['error_meta_title'] = array();
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_module'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=tltblog', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/tltblog/tltblog_settings', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/tltblog/tltblog_settings', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=tltblog', true);

		if (isset($this->request->post['tltblog_status'])) {
			$data['tltblog_status'] = $this->request->post['tltblog_status'];
		} else {
			$data['tltblog_status'] = $this->config->get('tltblog_status');
		}

        if (isset($this->request->post['tltblog_path'])) {
			$data['tltblog_path'] = $this->request->post['tltblog_path'];
		} elseif ($this->config->has('tltblog_path')) {
			$data['tltblog_path'] = $this->config->get('tltblog_path');
		} else {
			$data['tltblog_path'] = array();
		}

		if (isset($this->request->post['tltblog_path_title'])) {
			$data['tltblog_path_title'] = $this->request->post['tltblog_path_title'];
		} elseif ($this->config->has('tltblog_path_title')) {
			$data['tltblog_path_title'] = $this->config->get('tltblog_path_title');
		} else {
			$data['tltblog_path_title'] = array();
		}

		if (isset($this->request->post['tltblog_meta'])) {
			$data['tltblog_meta'] = $this->request->post['tltblog_meta'];
		} elseif ($this->config->has('tltblog_meta')) {
			$data['tltblog_meta'] = $this->config->get('tltblog_meta');
		} else {
			$data['tltblog_meta'] = array();
		}

		if (isset($this->request->post['tltblog_show_path'])) {
			$data['tltblog_show_path'] = $this->request->post['tltblog_show_path'];
		} elseif ($this->config->has('tltblog_show_path')) {
			$data['tltblog_show_path'] = $this->config->get('tltblog_show_path');
		} else {
			$data['tltblog_show_path'] = '1';
		}

		if (isset($this->request->post['tltblog_num_columns'])) {
			$data['tltblog_num_columns'] = $this->request->post['tltblog_num_columns'];
		} elseif ($this->config->has('tltblog_num_columns')) {
			$data['tltblog_num_columns'] = $this->config->get('tltblog_num_columns');
		} else {
			$data['tltblog_num_columns'] = '1';
		}

		if (isset($this->request->post['tltblog_blogs_page'])) {
			$data['tltblog_blogs_page'] = $this->request->post['tltblog_blogs_page'];
		} elseif ($this->config->has('tltblog_blogs_page')) {
			$data['tltblog_blogs_page'] = (int)$this->config->get('tltblog_blogs_page');
		} else {
			$data['tltblog_blogs_page'] = '';
		}

		if (isset($this->request->post['tltblog_show_image'])) {
			$data['tltblog_show_image'] = $this->request->post['tltblog_show_image'];
		} elseif ($this->config->has('tltblog_show_image')) {
			$data['tltblog_show_image'] = $this->config->get('tltblog_show_image');
		} else {
			$data['tltblog_show_image'] = '1';
		}

		if (isset($this->request->post['tltblog_width'])) {
			$data['tltblog_width'] = $this->request->post['tltblog_width'];
		} elseif ($this->config->has('tltblog_width')) {
			$data['tltblog_width'] = (int)$this->config->get('tltblog_width');
		} else {
			$data['tltblog_width'] = 200;
		}

		if (isset($this->request->post['tltblog_height'])) {
			$data['tltblog_height'] = $this->request->post['tltblog_height'];
		} elseif ($this->config->has('tltblog_height')) {
			$data['tltblog_height'] = (int)$this->config->get('tltblog_height');
		} else {
			$data['tltblog_height'] = 200;
		}

		if (isset($this->request->post['tltblog_seo'])) {
			$data['tltblog_seo'] = $this->request->post['tltblog_seo'];
		} elseif ($this->config->has('tltblog_seo')) {
			$data['tltblog_seo'] = $this->config->get('tltblog_seo');
		} else {
			$data['tltblog_seo'] = '0';
		} 

		// If you have non-standard SEO module, which doesn't use config_seo_url setting simple replace this if ... else contruction with 
		// $data['tltblog_global_seo_off'] = false;
		// <--begin-->
		if ($this->config->get('config_seo_url')) {
			$data['global_seo_off'] = false;
		} else {
			$data['global_seo_off'] = true;
		} 
		// <---end--->

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/tltblog/tltblog_settings', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/tltblog/tltblog_settings')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

        $languages = $this->model_localisation_language->getLanguages();

        foreach ($languages as $language) {
            if ((utf8_strlen($this->request->post['tltblog_path'][$language['language_id']]) < 3) || (utf8_strlen($this->request->post['tltblog_path'][$language['language_id']]) > 64)) {
                $this->error['tltblog_path'][$language['language_id']] = $this->language->get('error_path');
            }
        }

		$this->load->model('extension/tltblog/url_alias');

        foreach ($this->request->post['tltblog_path'] as $language_id => $value) {
            if (!$this->model_extension_tltblog_url_alias->checkUrlAliasIsFree($value, 'tltpath=' . $value)) {
                $this->error['tltblog_path'][$language_id] = $this->language->get('error_path_exist');
            }
        }

		if ($this->request->post['tltblog_show_path']) {
			foreach ($this->request->post['tltblog_path_title'] as $language_id => $value) {
				if ((utf8_strlen($value['path_title']) < 3) || (utf8_strlen($value['path_title']) > 64)) {
					$this->error['tltblog_path_title'][$language_id] = $this->language->get('error_path_title');
				}
			}
		}

		if ($this->request->post['tltblog_show_image']) {
			if (!$this->request->post['tltblog_width'] || ((int)$this->request->post['tltblog_width'] < 1)) {
				$this->error['tltblog_width'] = $this->language->get('error_width');
			}
	
			if (!$this->request->post['tltblog_height'] || ((int)$this->request->post['tltblog_height'] < 1)) {
				$this->error['tltblog_height'] = $this->language->get('error_height');
			}
		}

		return !$this->error;
	}

	public function install()
    {
        $this->load->model('setting/setting');
        $this->load->model('extension/tltblog/settings');

        $db_version = $this->config->get('tltblog_db_version');
        $check_tables = $this->model_extension_tltblog_settings->checkTables();

        if (!$check_tables) {
            $this->model_extension_tltblog_settings->install();
            $tltblog_settings = array();
        } elseif ($db_version !== TLT_BLOG_DB_VERSION) {
            $this->model_extension_tltblog_settings->upgrade();
            $tltblog_settings = $this->model_extension_tltblog_settings->getSettings();
        }

        $tltblog_settings['tltblog_db_version'] = TLT_BLOG_DB_VERSION;
        $tltblog_settings['tltblog_edition'] = TLT_BLOG_EDITION;
        $tltblog_settings['tltblog_version'] = TLT_BLOG_VERSION;
        $tltblog_settings['tltblog_settings_status'] = '0';
        $tltblog_settings['tltblog_tltblog_status'] = isset($tltblog_settings['tltblog_tltblog_status']) ? $tltblog_settings['tltblog_tltblog_status'] : '0';
        $tltblog_settings['tltblog_tlttag_status'] = isset($tltblog_settings['tltblog_tlttag_status']) ? $tltblog_settings['tltblog_tlttag_status'] : '0';

        $this->model_setting_setting->editSetting('tltblog', $tltblog_settings);
    }

    public function uninstall() {
        $this->load->model('setting/setting');
		$this->model_setting_setting->deleteSetting('tltblog');

		if (TLT_BLOG_DROP_DB) {
            $this->load->model('extension/tltblog/settings');
            $this->model_extension_tltblog_settings->uninstall();
        } else {
		    $this->model_setting_setting->editSetting('tltblog', [
                'tltblog_db_version'    => TLT_BLOG_DB_VERSION,
                'tltblog_edition'       => TLT_BLOG_EDITION,
                'tltblog_version'       => TLT_BLOG_VERSION
            ]);
        }

        $this->cache->delete('tltblog');
    }

    protected function upgrade()
    {
        $this->load->model('setting/setting');
        $this->load->model('extension/tltblog/settings');

        $tltblog_settings = $this->model_extension_tltblog_settings->getSettings();

        if ($this->config->get('tltblog_version') === '5.1.4') {
            if ($this->config->get('tltblog_status')) {
                $tltblog_settings['tltblog_tltblog_settings_status'] = '1';
                $tltblog_settings['tltblog_tltblog_status'] = '1';
                $tltblog_settings['tltblog_tlttag_status'] = '1';
            } else {
                $tltblog_settings['tltblog_tltblog_settings_status'] = '0';
                $tltblog_settings['tltblog_tltblog_status'] = '0';
                $tltblog_settings['tltblog_tlttag_status'] = '0';
            }
        } else {
            if ($this->config->get('tltblog_status')) {
                $tltblog_settings['tltblog_tltblog_settings_status'] = '1';
                $tltblog_settings['tltblog_tltblog_status'] = '1';
                $tltblog_settings['tltblog_tlttag_status'] = '1';
            } else {
                $tltblog_settings['tltblog_tltblog_settings_status'] = '0';
                $tltblog_settings['tltblog_tltblog_status'] = '0';
                $tltblog_settings['tltblog_tlttag_status'] = '0';
            }
        }

        $tltblog_settings['tltblog_db_version'] = TLT_BLOG_DB_VERSION;
        $tltblog_settings['tltblog_edition'] = TLT_BLOG_EDITION;
        $tltblog_settings['tltblog_version'] = TLT_BLOG_VERSION;

        $this->model_setting_setting->editSetting('tltblog', $tltblog_settings);
    }
}
