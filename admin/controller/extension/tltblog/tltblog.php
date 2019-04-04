<?php
class ControllerExtensionTltBlogTltBlog extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/tltblog/tltblog');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/tltblog/tltblog');

		$this->getList();
	}

	public function add() {
		$this->load->language('extension/tltblog/tltblog');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/tltblog/tltblog');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_extension_tltblog_tltblog->addTltBlog($this->request->post);

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

			$this->response->redirect($this->url->link('extension/tltblog/tltblog', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
	}

	public function edit() {
		$this->load->language('extension/tltblog/tltblog');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/tltblog/tltblog');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_extension_tltblog_tltblog->editTltBlog($this->request->get['tltblog_id'], $this->request->post);

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

			$this->response->redirect($this->url->link('extension/tltblog/tltblog', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
	}

	public function delete() {
		$this->load->language('extension/tltblog/tltblog');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/tltblog/tltblog');

		if (isset($this->request->post['selected']) && $this->validateDelete()) {
			foreach ($this->request->post['selected'] as $tltblog_id) {
				$this->model_extension_tltblog_tltblog->deleteTltBlog($tltblog_id);
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

			$this->response->redirect($this->url->link('extension/tltblog/tltblog', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getList();
	}

	public function copy() {
		$this->load->language('extension/tltblog/tltblog');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/tltblog/tltblog');

		if (isset($this->request->post['selected']) && $this->validateCopy()) {
			foreach ($this->request->post['selected'] as $tltblog_id) {
				$this->model_extension_tltblog_tltblog->copyTltBlog($tltblog_id);
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

			$this->response->redirect($this->url->link('extension/tltblog/tltblog', 'user_token=' . $this->session->data['user_token'] . $url, true));
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
			$sort = 'bd.title';
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
			'href' => $this->url->link('extension/tltblog/tltblog', 'user_token=' . $this->session->data['user_token'] . $url, true)
		);

		$data['add'] = $this->url->link('extension/tltblog/tltblog/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['copy'] = $this->url->link('extension/tltblog/tltblog/copy', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['delete'] = $this->url->link('extension/tltblog/tltblog/delete', 'user_token=' . $this->session->data['user_token'] . $url, true);

		$data['tltblogs'] = array();

		$filter_data = array(
			'filter_title'	  => $filter_title,
			'filter_status'   => $filter_status,
			'sort'            => $sort,
			'order'           => $order,
			'start'           => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit'           => $this->config->get('config_limit_admin')
		);

		$this->load->model('tool/image');

        $this->load->model('setting/setting');

        if ($this->config->get('tltblog_status') && $this->config->get('tltblog_tltblog_status')) {
            $data['error_config'] = '';

            $tltblog_total = $this->model_extension_tltblog_tltblog->getTotalTltBlogs($filter_data);
            $results = $this->model_extension_tltblog_tltblog->getTltBlogs($filter_data);
        } else {
            $data['error_config'] = sprintf($this->language->get('error_config'), $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=tltblog', true));
            $tltblog_total = 0;
            $results = array();
        }

		foreach ($results as $result) {
			if (is_file(DIR_IMAGE . $result['image'])) {
				$image = $this->model_tool_image->resize($result['image'], 40, 40);
			} else {
				$image = $this->model_tool_image->resize('no_image.png', 40, 40);
			}
			
			$tags = $this->model_extension_tltblog_tltblog->getTltBlogTagsDescription($result['tltblog_id']);
			$tagsdata = array();
			foreach ($tags as $tagvalue) {
				$tagsdata[] = array(
					'tlttag_id'	=> $tagvalue['tlttag_id'],
					'title'		=> $tagvalue['title'],
					'href'		=> $this->url->link('extension/tltblog/tlttag/edit', 'user_token=' . $this->session->data['user_token'] . '&tlttag_id=' . $tagvalue['tlttag_id'], true)
				);	
			}
			
			$data['tltblogs'][] = array(
				'tltblog_id' => $result['tltblog_id'],
				'image'      => $image,
				'title'      => $result['title'],
				'tags'		 => $tagsdata, //$result['tags'],
				'status'     => ($result['status']) ? $this->language->get('text_enabled') : $this->language->get('text_disabled'),
				'sort_order' => $result['sort_order'],
				'edit'       => $this->url->link('extension/tltblog/tltblog/edit', 'user_token=' . $this->session->data['user_token'] . '&tltblog_id=' . $result['tltblog_id'] . $url, true)
			);			
		}

		$data['heading_title'] = $this->language->get('heading_title');

		$data['text_list'] = $this->language->get('text_list');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['text_no_results'] = $this->language->get('text_no_results');
		$data['text_confirm'] = $this->language->get('text_confirm');

		$data['column_image'] = $this->language->get('column_image');
		$data['column_title'] = $this->language->get('column_title');
		$data['column_tags'] = $this->language->get('column_tags');
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

		$data['sort_title'] = $this->url->link('extension/tltblog/tltblog', 'user_token=' . $this->session->data['user_token'] . '&sort=bd.title' . $url, true);
		$data['sort_status'] = $this->url->link('extension/tltblog/tltblog', 'user_token=' . $this->session->data['user_token'] . '&sort=b.status' . $url, true);
		$data['sort_sort_order'] = $this->url->link('extension/tltblog/tltblog', 'user_token=' . $this->session->data['user_token'] . '&sort=b.sort_order' . $url, true);

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
		$pagination->total = $tltblog_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('extension/tltblog/tltblog', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();

		$data['results'] = sprintf($this->language->get('text_pagination'), ($tltblog_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($tltblog_total - $this->config->get('config_limit_admin'))) ? $tltblog_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $tltblog_total, ceil($tltblog_total / $this->config->get('config_limit_admin')));

		$data['filter_title'] = $filter_title;
		$data['filter_status'] = $filter_status;

		$data['sort'] = $sort;
		$data['order'] = $order;

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/tltblog/tltblog_list', $data));
	}

	protected function getForm()
    {
        $this->document->addScript('view/javascript/limax.js');

		$data['heading_title'] = $this->language->get('heading_title');

		$data['text_form'] = !isset($this->request->get['tltblog_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['text_none'] = $this->language->get('text_none');
		$data['text_yes'] = $this->language->get('text_yes');
		$data['text_no'] = $this->language->get('text_no');
		$data['text_default'] = $this->language->get('text_default');
		$data['text_pro_only'] = $this->language->get('text_pro_only');

		$data['entry_title'] = $this->language->get('entry_title');
		$data['entry_author'] = $this->language->get('entry_author');
		$data['entry_intro'] = $this->language->get('entry_intro');
		$data['entry_description'] = $this->language->get('entry_description');
		$data['entry_meta_title'] = $this->language->get('entry_meta_title');
		$data['entry_meta_description'] = $this->language->get('entry_meta_description');
		$data['entry_meta_keyword'] = $this->language->get('entry_meta_keyword');
		$data['entry_keyword'] = $this->language->get('entry_keyword');
		$data['entry_image'] = $this->language->get('entry_image');
		$data['entry_image_social'] = $this->language->get('entry_image_social');
		$data['entry_store'] = $this->language->get('entry_store');
		$data['entry_related'] = $this->language->get('entry_related');
		$data['entry_required'] = $this->language->get('entry_required');
		$data['entry_bottom'] = $this->language->get('entry_bottom');
		$data['entry_show_title'] = $this->language->get('entry_show_title');
		$data['entry_show_description'] = $this->language->get('entry_show_description');
		$data['entry_show_in_sitemap'] = $this->language->get('entry_show_in_sitemap');
		$data['entry_date'] = $this->language->get('entry_date');
		$data['entry_date_start'] = $this->language->get('entry_date_start');
		$data['entry_date_end'] = $this->language->get('entry_date_end');
		$data['entry_sort_order'] = $this->language->get('entry_sort_order');
		$data['entry_status'] = $this->language->get('entry_status');
		$data['entry_layout'] = $this->language->get('entry_layout');
		$data['entry_tags'] = $this->language->get('entry_tags');

		$data['help_keyword'] = $this->language->get('help_keyword');
		$data['help_bottom'] = $this->language->get('help_bottom');
		$data['help_show_title'] = $this->language->get('help_show_title');
		$data['help_show_description'] = $this->language->get('help_show_description');
		$data['help_datetime'] = $this->language->get('help_datetime');
		$data['help_date'] = $this->language->get('help_date');
		$data['help_related'] = $this->language->get('help_related');
		$data['help_tags'] = $this->language->get('help_tags');
		$data['help_image_social'] = $this->language->get('help_image_social');
		
		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');
		$data['button_image_add'] = $this->language->get('button_image_add');
		$data['button_remove'] = $this->language->get('button_remove');

		$data['tab_general'] = $this->language->get('tab_general');
		$data['tab_data'] = $this->language->get('tab_data');
		$data['tab_links'] = $this->language->get('tab_links');
		$data['tab_design'] = $this->language->get('tab_design');

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

		if (isset($this->error['intro'])) {
			$data['error_intro'] = $this->error['intro'];
		} else {
			$data['error_intro'] = array();
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
			'href' => $this->url->link('extension/tltblog/tltblog', 'user_token=' . $this->session->data['user_token'] . $url, true)
		);

		if (!isset($this->request->get['tltblog_id'])) {
			$data['action'] = $this->url->link('extension/tltblog/tltblog/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		} else {
			$data['action'] = $this->url->link('extension/tltblog/tltblog/edit', 'user_token=' . $this->session->data['user_token'] . '&tltblog_id=' . $this->request->get['tltblog_id'] . $url, true);
		}

		$data['cancel'] = $this->url->link('extension/tltblog/tltblog', 'user_token=' . $this->session->data['user_token'] . $url, true);

		if (isset($this->request->get['tltblog_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$tltblog_info = $this->model_extension_tltblog_tltblog->getTltBlog($this->request->get['tltblog_id']);
		}

		$data['user_token'] = $this->session->data['user_token'];

		$this->load->model('localisation/language');

		$data['languages'] = $this->model_localisation_language->getLanguages();

		if (isset($this->request->post['tltblog_description'])) {
			$data['tltblog_description'] = $this->request->post['tltblog_description'];
		} elseif (isset($this->request->get['tltblog_id'])) {
			$data['tltblog_description'] = $this->model_extension_tltblog_tltblog->getTltBlogDescription($this->request->get['tltblog_id']);
		} else {
			$data['tltblog_description'] = array();
		}

		if (isset($this->request->post['image'])) {
			$data['image'] = $this->request->post['image'];
		} elseif (!empty($tltblog_info)) {
			$data['image'] = $tltblog_info['image'];
		} else {
			$data['image'] = '';
		}

		$this->load->model('tool/image');

		if (isset($this->request->post['image']) && is_file(DIR_IMAGE . $this->request->post['image'])) {
			$data['thumb'] = $this->model_tool_image->resize($this->request->post['image'], 100, 100);
		} elseif (!empty($tltblog_info) && is_file(DIR_IMAGE . $tltblog_info['image'])) {
			$data['thumb'] = $this->model_tool_image->resize($tltblog_info['image'], 100, 100);
		} else {
			$data['thumb'] = $this->model_tool_image->resize('no_image.png', 100, 100);
		}

		$data['placeholder'] = $this->model_tool_image->resize('no_image.png', 100, 100);

		$this->load->model('setting/store');

		$data['stores'] = $this->model_setting_store->getStores();

		if (isset($this->request->post['tltblog_store'])) {
			$data['tltblog_store'] = $this->request->post['tltblog_store'];
		} elseif (isset($this->request->get['tltblog_id'])) {
			$data['tltblog_store'] = $this->model_extension_tltblog_tltblog->getTltBlogStores($this->request->get['tltblog_id']);
		} else {
			$data['tltblog_store'] = array(0);
		}

        $this->load->model('extension/tltblog/url_alias');

        if (isset($this->request->post['keyword'])) {
			$data['keyword'] = $this->request->post['keyword'];
		} elseif (!empty($tltblog_info)) {
            $data['keyword'] = $this->model_extension_tltblog_url_alias->getUrlAliasByQuery('tltblog_id=' . $this->request->get['tltblog_id']);
		} else {
			$data['keyword'] = array();
		}

		if (isset($this->request->post['bottom'])) {
			$data['bottom'] = $this->request->post['bottom'];
		} elseif (!empty($tltblog_info)) {
			$data['bottom'] = $tltblog_info['bottom'];
		} else {
			$data['bottom'] = '0';
		}

		if (isset($this->request->post['show_title'])) {
			$data['show_title'] = $this->request->post['show_title'];
		} elseif (!empty($tltblog_info)) {
			$data['show_title'] = $tltblog_info['show_title'];
		} else {
			$data['show_title'] = '1';
		}

		if (isset($this->request->post['show_description'])) {
			$data['show_description'] = $this->request->post['show_description'];
		} elseif (!empty($tltblog_info)) {
			$data['show_description'] = $tltblog_info['show_description'];
		} else {
			$data['show_description'] = '1';
		}

		if (isset($this->request->post['sort_order'])) {
			$data['sort_order'] = $this->request->post['sort_order'];
		} elseif (!empty($tltblog_info)) {
			$data['sort_order'] = $tltblog_info['sort_order'];
		} else {
			$data['sort_order'] = '0';
		}

		if (isset($this->request->post['status'])) {
			$data['status'] = $this->request->post['status'];
		} elseif (!empty($tltblog_info)) {
			$data['status'] = $tltblog_info['status'];
		} else {
			$data['status'] = false;
		}

		// Tags
		$this->load->model('extension/tltblog/tlttag');

		if (isset($this->request->post['tltblog_tags'])) {
			$tags = $this->request->post['tltblog_tags'];
		} elseif (isset($this->request->get['tltblog_id'])) {
			$tags = $this->model_extension_tltblog_tltblog->getTltBlogTags($this->request->get['tltblog_id']);
		} else {
			$tags = array();
		}

		$data['tltblog_tags'] = array();
		foreach ($tags as $tlttag_id) {
			$tag_info = $this->model_extension_tltblog_tlttag->getTltTag($tlttag_id);

			if ($tag_info) {
				$data['tltblog_tags'][] = array(
					'tlttag_id' => $tag_info['tlttag_id'],
					'title' => $tag_info['title']
				);
			}
		}

		// Related products
		$this->load->model('catalog/product');

		if (isset($this->request->post['tltblog_related'])) {
			$products = $this->request->post['tltblog_related'];
		} elseif (isset($this->request->get['tltblog_id'])) {
			$products = $this->model_extension_tltblog_tltblog->getTltBlogRelated($this->request->get['tltblog_id']);
		} else {
			$products = array();
		}

		$data['tltblog_relateds'] = array();
		foreach ($products as $related_id) {
			$product_info = $this->model_catalog_product->getProduct($related_id);

			if ($product_info) {
				$data['tltblog_relateds'][] = array(
					'related_id' => $product_info['product_id'],
					'name' => $product_info['name']
				);
			}
		}

		if (isset($this->request->post['product_layout'])) {
			$data['product_layout'] = $this->request->post['product_layout'];
		} elseif (isset($this->request->get['product_id'])) {
			$data['product_layout'] = $this->model_catalog_product->getProductLayouts($this->request->get['product_id']);
		} else {
			$data['product_layout'] = array();
		}

		$this->load->model('design/layout');

		$data['layouts'] = $this->model_design_layout->getLayouts();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/tltblog/tltblog_form', $data));
	}

	protected function validateForm() {
		if (!$this->user->hasPermission('modify', 'extension/tltblog/tltblog')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}
		
		foreach ($this->request->post['tltblog_description'] as $language_id => $value) {
			if ((utf8_strlen($value['title']) < 3) || (utf8_strlen($value['title']) > 255)) {
				$this->error['title'][$language_id] = $this->language->get('error_title');
			}

			if ((utf8_strlen($value['meta_title']) < 3) || (utf8_strlen($value['meta_title']) > 255)) {
				$this->error['meta_title'][$language_id] = $this->language->get('error_meta_title');
			}

			if (utf8_strlen($value['intro']) < 30) {
				$this->error['intro'][$language_id] = $this->language->get('error_intro');
			}
		}

        $this->load->model('extension/tltblog/url_alias');

        foreach ($this->request->post['keyword'] as $language_id => $keyword) {
            if (utf8_strlen($keyword) > 0) {
                if (count(array_keys($this->request->post['keyword'], $keyword)) > 1) {
                    $this->error['keyword'][$language_id] = $this->language->get('error_keyword');
                }

                if (isset($this->request->get['tltblog_id'])) {
                    if (!$this->model_extension_tltblog_url_alias->checkUrlAliasIsFree($this->request->post['keyword'][$language_id], 'tltblog_id=' . $this->request->get['tltblog_id'])) {
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
		if (!$this->user->hasPermission('modify', 'extension/tltblog/tltblog')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	protected function validateCopy() {
		if (!$this->user->hasPermission('modify', 'extension/tltblog/tltblog')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	public function autocomplete() {
		$json = array();

		if (isset($this->request->get['filter_name'])) {
			$this->load->model('catalog/product');

			if (isset($this->request->get['filter_name'])) {
				$filter_name = $this->request->get['filter_name'];
			} else {
				$filter_name = '';
			}

			if (isset($this->request->get['limit'])) {
				$limit = $this->request->get['limit'];
			} else {
				$limit = 5;
			}

			$filter_data = array(
				'filter_name'  => $filter_name,
				'start'        => 0,
				'limit'        => $limit
			);

			$results = $this->model_catalog_product->getProducts($filter_data);

			foreach ($results as $result) {
				$json[] = array(
					'related_id' => $result['product_id'],
					'name'       => strip_tags(html_entity_decode($result['name'], ENT_QUOTES, 'UTF-8')),
				);
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function install()
    {
        $this->load->model('setting/setting');

        if ($this->config->has('tltblog_tltblog_status')) {
            $this->model_setting_setting->editSettingValue('tltblog', 'tltblog_tltblog_status', '1');
        } else {
            $this->load->model('extension/tltblog/settings');

            $tltblog_settings = $this->model_extension_tltblog_settings->getSettings();
            $tltblog_settings['tltblog_tltblog_status'] = '1';
            $this->model_setting_setting->editSetting('tltblog', $tltblog_settings);
        }
    }

    public function uninstall()
    {
        $this->load->model('setting/setting');

        if ($this->config->has('tltblog_tltblog_status')) {
            $this->model_setting_setting->editSettingValue('tltblog', 'tltblog_tltblog_status', '0');
        }
    }
}