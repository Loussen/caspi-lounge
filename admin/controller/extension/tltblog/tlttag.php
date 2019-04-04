<?php
class ControllerExtensionTltBlogTltTag extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/tltblog/tlttag');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/tltblog/tlttag');

		$this->getList();
	}

	public function add() {
		$this->load->language('extension/tltblog/tlttag');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/tltblog/tlttag');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_extension_tltblog_tlttag->addTltTag($this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('extension/tltblog/tlttag', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
	}

	public function edit() {
		$this->load->language('extension/tltblog/tlttag');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/tltblog/tlttag');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_extension_tltblog_tlttag->editTltTag($this->request->get['tlttag_id'], $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['filter_title'])) {
				$url .= '&filter_title=' . urlencode(html_entity_decode($this->request->get['filter_title'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_status'])) {
				$url .= '&filter_status=' . $this->request->get['filter_status'];
			}

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('extension/tltblog/tlttag', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
	}

	public function delete() {
		$this->load->language('extension/tltblog/tlttag');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/tltblog/tlttag');

		if (isset($this->request->post['selected']) && $this->validateDelete()) {
			foreach ($this->request->post['selected'] as $tlttag_id) {
				$this->model_extension_tltblog_tlttag->deleteTltTag($tlttag_id);
			}

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['filter_title'])) {
				$url .= '&filter_title=' . urlencode(html_entity_decode($this->request->get['filter_title'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_status'])) {
				$url .= '&filter_status=' . $this->request->get['filter_status'];
			}

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('extension/tltblog/tlttag', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getList();
	}

	public function copy() {
		$this->load->language('extension/tltblog/tlttag');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/tltblog/tlttag');

		if (isset($this->request->post['selected']) && $this->validateCopy()) {
			foreach ($this->request->post['selected'] as $tlttag_id) {
				$this->model_extension_tltblog_tlttag->copyTltTag($tlttag_id);
			}

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['filter_title'])) {
				$url .= '&filter_title=' . urlencode(html_entity_decode($this->request->get['filter_title'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_status'])) {
				$url .= '&filter_status=' . $this->request->get['filter_status'];
			}

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('extension/tltblog/tlttag', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getList();
	}

	protected function getList() {
		if (isset($this->request->get['filter_title'])) {
			$filter_title = $this->request->get['filter_title'];
		} else {
			$filter_title = null;
		}

		if (isset($this->request->get['filter_status'])) {
			$filter_status = $this->request->get['filter_status'];
		} else {
			$filter_status = null;
		}

		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
		} else {
			$sort = 'td.title';
		}

		if (isset($this->request->get['order'])) {
			$order = $this->request->get['order'];
		} else {
			$order = 'ASC';
		}

		if (isset($this->request->get['page'])) {
			$page = $this->request->get['page'];
		} else {
			$page = 1;
		}

		$url = '';

		if (isset($this->request->get['filter_title'])) {
			$url .= '&filter_title=' . urlencode(html_entity_decode($this->request->get['filter_title'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_status'])) {
			$url .= '&filter_status=' . $this->request->get['filter_status'];
		}

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/tltblog/tlttag', 'user_token=' . $this->session->data['user_token'] . $url, true)
		);

		$data['add'] = $this->url->link('extension/tltblog/tlttag/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['copy'] = $this->url->link('extension/tltblog/tlttag/copy', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['delete'] = $this->url->link('extension/tltblog/tlttag/delete', 'user_token=' . $this->session->data['user_token'] . $url, true);

		$data['tlttags'] = array();

		$filter_data = array(
			'filter_title'	  => $filter_title,
			'filter_status'   => $filter_status,
			'sort'            => $sort,
			'order'           => $order,
			'start'           => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit'           => $this->config->get('config_limit_admin')
		);

        $this->load->model('setting/setting');

        if ($this->config->get('tltblog_status') && $this->config->get('tltblog_tlttag_status')) {
            $data['error_config'] = '';

            $tlttag_total = $this->model_extension_tltblog_tlttag->getTotalTltTags($filter_data);
            $results = $this->model_extension_tltblog_tlttag->getTltTags($filter_data);
        } else {
            $data['error_config'] = sprintf($this->language->get('error_config'), $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=tltblog', true));
            $tlttag_total = 0;
            $results = array();
        }

		foreach ($results as $result) {
			$data['tlttags'][] = array(
				'tlttag_id' => $result['tlttag_id'],
				'title'      => $result['title'],
				'status'     => ($result['status']) ? $this->language->get('text_enabled') : $this->language->get('text_disabled'),
				'sort_order' => $result['sort_order'],
				'edit'       => $this->url->link('extension/tltblog/tlttag/edit', 'user_token=' . $this->session->data['user_token'] . '&tlttag_id=' . $result['tlttag_id'] . $url, true)
			);
		}

		$data['heading_title'] = $this->language->get('heading_title');

		$data['text_list'] = $this->language->get('text_list');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['text_no_results'] = $this->language->get('text_no_results');
		$data['text_confirm'] = $this->language->get('text_confirm');

		$data['column_title'] = $this->language->get('column_title');
		$data['column_status'] = $this->language->get('column_status');
		$data['column_sort_order'] = $this->language->get('column_sort_order');
		$data['column_action'] = $this->language->get('column_action');

		$data['entry_title'] = $this->language->get('entry_title');
		$data['entry_status'] = $this->language->get('entry_status');

		$data['button_copy'] = $this->language->get('button_copy');
		$data['button_add'] = $this->language->get('button_add');
		$data['button_edit'] = $this->language->get('button_edit');
		$data['button_delete'] = $this->language->get('button_delete');
		$data['button_filter'] = $this->language->get('button_filter');

        $data['text_copyright'] = '&copy; ' . date('Y') . ', <a href="https://taiwanleaftea.com" target="_blank" class="alert-link" title="Authentic tea from Taiwan">Taiwanleaftea.com</a>';
        $data['text_donation'] = 'If you find this software useful and to support further development please buy <a href="https://taiwanleaftea.com" class="alert-link" target="_blank" title="Authentic tea from Taiwan">here</a> a cup of tea.';
        $data['donate'] = true;

        $data['user_token'] = $this->session->data['user_token'];

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		if (isset($this->request->post['selected'])) {
			$data['selected'] = (array)$this->request->post['selected'];
		} else {
			$data['selected'] = array();
		}

		$url = '';

		if (isset($this->request->get['filter_title'])) {
			$url .= '&filter_title=' . urlencode(html_entity_decode($this->request->get['filter_title'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_status'])) {
			$url .= '&filter_status=' . $this->request->get['filter_status'];
		}

		if ($order == 'ASC') {
			$url .= '&order=DESC';
		} else {
			$url .= '&order=ASC';
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['sort_title'] = $this->url->link('extension/tltblog/tlttag', 'user_token=' . $this->session->data['user_token'] . '&sort=td.title' . $url, true);
		$data['sort_status'] = $this->url->link('extension/tltblog/tlttag', 'user_token=' . $this->session->data['user_token'] . '&sort=t.status' . $url, true);
		$data['sort_sort_order'] = $this->url->link('extension/tltblog/tlttag', 'user_token=' . $this->session->data['user_token'] . '&sort=t.sort_order' . $url, true);

		$url = '';

		if (isset($this->request->get['filter_title'])) {
			$url .= '&filter_title=' . urlencode(html_entity_decode($this->request->get['filter_title'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_status'])) {
			$url .= '&filter_status=' . $this->request->get['filter_status'];
		}

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		$pagination = new Pagination();
		$pagination->total = $tlttag_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('extension/tltblog/tlttag', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();

		$data['results'] = sprintf($this->language->get('text_pagination'), ($tlttag_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($tlttag_total - $this->config->get('config_limit_admin'))) ? $tlttag_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $tlttag_total, ceil($tlttag_total / $this->config->get('config_limit_admin')));

		$data['filter_title'] = $filter_title;
		$data['filter_status'] = $filter_status;

		$data['sort'] = $sort;
		$data['order'] = $order;

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/tltblog/tlttag_list', $data));
	}

	protected function getForm()
    {
        $this->document->addScript('view/javascript/limax.js');

		$data['heading_title'] = $this->language->get('heading_title');

		$data['text_form'] = !isset($this->request->get['tlttag_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['text_none'] = $this->language->get('text_none');
		$data['text_yes'] = $this->language->get('text_yes');
		$data['text_no'] = $this->language->get('text_no');
		$data['text_default'] = $this->language->get('text_default');

		$data['entry_title'] = $this->language->get('entry_title');
		$data['entry_meta_title'] = $this->language->get('entry_meta_title');
		$data['entry_meta_description'] = $this->language->get('entry_meta_description');
		$data['entry_meta_keyword'] = $this->language->get('entry_meta_keyword');
		$data['entry_keyword'] = $this->language->get('entry_keyword');
		$data['entry_store'] = $this->language->get('entry_store');
		$data['entry_required'] = $this->language->get('entry_required');
		$data['entry_show_in_sitemap'] = $this->language->get('entry_show_in_sitemap');
		$data['entry_sort_order'] = $this->language->get('entry_sort_order');
		$data['entry_status'] = $this->language->get('entry_status');

		$data['help_keyword'] = $this->language->get('help_keyword');
		$data['help_show_in_sitemap'] = $this->language->get('help_show_in_sitemap');

		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');
		$data['button_remove'] = $this->language->get('button_remove');

		$data['tab_general'] = $this->language->get('tab_general');
		$data['tab_data'] = $this->language->get('tab_data');

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['title'])) {
			$data['error_title'] = $this->error['title'];
		} else {
			$data['error_title'] = array();
		}

		if (isset($this->error['meta_title'])) {
			$data['error_meta_title'] = $this->error['meta_title'];
		} else {
			$data['error_meta_title'] = array();
		}

		if (isset($this->error['keyword'])) {
			$data['error_keyword'] = $this->error['keyword'];
		} else {
			$data['error_keyword'] = array();
		}

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/tltblog/tlttag', 'user_token=' . $this->session->data['user_token'] . $url, true)
		);

		if (!isset($this->request->get['tlttag_id'])) {
			$data['action'] = $this->url->link('extension/tltblog/tlttag/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		} else {
			$data['action'] = $this->url->link('extension/tltblog/tlttag/edit', 'user_token=' . $this->session->data['user_token'] . '&tlttag_id=' . $this->request->get['tlttag_id'] . $url, true);
		}

		$data['cancel'] = $this->url->link('extension/tltblog/tlttag', 'user_token=' . $this->session->data['user_token'] . $url, true);

		if (isset($this->request->get['tlttag_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$tlttag_info = $this->model_extension_tltblog_tlttag->getTltTag($this->request->get['tlttag_id']);
		}

		$data['user_token'] = $this->session->data['user_token'];

		$this->load->model('localisation/language');

		$data['languages'] = $this->model_localisation_language->getLanguages();

		if (isset($this->request->post['tlttag_description'])) {
			$data['tlttag_description'] = $this->request->post['tlttag_description'];
		} elseif (isset($this->request->get['tlttag_id'])) {
			$data['tlttag_description'] = $this->model_extension_tltblog_tlttag->getTltTagDescription($this->request->get['tlttag_id']);
		} else {
			$data['tlttag_description'] = array();
		}

		$this->load->model('setting/store');

		$data['stores'] = $this->model_setting_store->getStores();

		if (isset($this->request->post['tlttag_store'])) {
			$data['tlttag_store'] = $this->request->post['tlttag_store'];
		} elseif (isset($this->request->get['tlttag_id'])) {
			$data['tlttag_store'] = $this->model_extension_tltblog_tlttag->getTltTagStores($this->request->get['tlttag_id']);
		} else {
			$data['tlttag_store'] = array(0);
		}

		$this->load->model('extension/tltblog/url_alias');

		if (isset($this->request->post['keyword'])) {
			$data['keyword'] = $this->request->post['keyword'];
		} elseif (isset($this->request->get['tlttag_id'])) {
			$data['keyword'] = $this->model_extension_tltblog_url_alias->getUrlAliasByQuery('tlttag_id=' . $this->request->get['tlttag_id']);
		} else {
			$data['keyword'] = array();
		}

		if (isset($this->request->post['sort_order'])) {
			$data['sort_order'] = $this->request->post['sort_order'];
		} elseif (!empty($tlttag_info)) {
			$data['sort_order'] = $tlttag_info['sort_order'];
		} else {
			$data['sort_order'] = '0';
		}

		if (isset($this->request->post['status'])) {
			$data['status'] = $this->request->post['status'];
		} elseif (!empty($tlttag_info)) {
			$data['status'] = $tlttag_info['status'];
		} else {
			$data['status'] = false;
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/tltblog/tlttag_form', $data));
	}

	protected function validateForm() {
		if (!$this->user->hasPermission('modify', 'extension/tltblog/tlttag')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$this->load->model('localisation/language');
		$languages = $this->model_localisation_language->getLanguages();

		foreach ($this->request->post['tlttag_description'] as $language_id => $value) {
			if ((utf8_strlen($value['title']) < 3) || (utf8_strlen($value['title']) > 255)) {
				$this->error['title'][$language_id] = $this->language->get('error_title');
			}

			if ((utf8_strlen($value['meta_title']) < 3) || (utf8_strlen($value['meta_title']) > 255)) {
				$this->error['meta_title'][$language_id] = $this->language->get('error_meta_title');
			}
		}

        $this->load->model('extension/tltblog/url_alias');

        foreach ($this->request->post['keyword'] as $language_id => $keyword) {
            if (utf8_strlen($keyword) > 0) {
                if (count(array_keys($this->request->post['keyword'], $keyword)) > 1) {
                    $this->error['keyword'][$language_id] = $this->language->get('error_keyword');
                }

                if (isset($this->request->get['tlttag_id'])) {
                    if (!$this->model_extension_tltblog_url_alias->checkUrlAliasIsFree($this->request->post['keyword'][$language_id], 'tlttag_id=' . $this->request->get['tlttag_id'])) {
                        $this->error['keyword'][$language_id] = $this->language->get('error_keyword');
                    }
                } else {
                    if (!$this->model_extension_tltblog_url_alias->checkUrlAliasIsFree($this->request->post['keyword'][$language_id])) {
                        $this->error['keyword'][$language_id] = $this->language->get('error_keyword');
                    }
                }
            }
        }

		if ($this->error && !isset($this->error['warning'])) {
			$this->error['warning'] = $this->language->get('error_warning');
		}

		return !$this->error;
	}

	protected function validateDelete() {
		if (!$this->user->hasPermission('modify', 'extension/tltblog/tlttag')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	protected function validateCopy() {
		if (!$this->user->hasPermission('modify', 'extension/tltblog/tlttag')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	public function autocomplete() {
		$json = array();

		if (isset($this->request->get['filter_title'])) {
			$this->load->model('extension/tltblog/tlttag');

			if (isset($this->request->get['filter_title'])) {
				$filter_title = $this->request->get['filter_title'];
			} else {
				$filter_title = '';
			}

			if (isset($this->request->get['limit'])) {
				$limit = $this->request->get['limit'];
			} else {
				$limit = 5;
			}

			$filter_data = array(
				'filter_title'  => $filter_title,
				'start'        => 0,
				'limit'        => $limit
			);

			$results = $this->model_extension_tltblog_tlttag->getTltTags($filter_data);

			foreach ($results as $result) {
				$json[] = array(
					'tlttag_id' => $result['tlttag_id'],
					'title'       => strip_tags(html_entity_decode($result['title'], ENT_QUOTES, 'UTF-8')),
				);
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

    public function install()
    {
        $this->load->model('setting/setting');

        if ($this->config->has('tltblog_tlttag_status')) {
            $this->model_setting_setting->editSettingValue('tltblog', 'tltblog_tlttag_status', '1');
        } else {
            $this->load->model('extension/tltblog/settings');

            $tltblog_settings = $this->model_extension_tltblog_settings->getSettings();
            $tltblog_settings['tltblog_tlttag_status'] = '1';
            $this->model_setting_setting->editSetting('tltblog', $tltblog_settings);
        }
    }

    public function uninstall()
    {
        $this->load->model('setting/setting');

        if ($this->config->has('tltblog_tlttag_status')) {
            $this->model_setting_setting->editSettingValue('tltblog', 'tltblog_tlttag_status', '0');
        }
    }
}