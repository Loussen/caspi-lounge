<?php
class ControllerExtensionPaymentBankOfBaku extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/payment/bank_of_baku');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('payment_bank_of_baku', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

//			$this->response->redirect($this->url->link('extension/payment', 'user_token=' . $this->session->data['user_token'], true));
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
		}

		$data['heading_title'] = $this->language->get('heading_title');

		$data['text_edit'] = $this->language->get('text_edit');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['text_all_zones'] = $this->language->get('text_all_zones');
		
		$data['text_optionally'] = $this->language->get('text_optionally');
		$data['text_visa'] = $this->language->get('text_visa');
		$data['text_master'] = $this->language->get('text_master');
		$data['text_bolcard'] = $this->language->get('text_bolcard');

		$data['entry_username'] = $this->language->get('entry_username');
		$data['entry_password'] = $this->language->get('entry_password');
		$data['entry_type'] = $this->language->get('entry_type');

		$data['entry_currency'] = $this->language->get('entry_currency');
		
		$data['entry_total'] = $this->language->get('entry_total');
		$data['entry_order_status'] = $this->language->get('entry_order_status');
		$data['entry_geo_zone'] = $this->language->get('entry_geo_zone');
		$data['entry_status'] = $this->language->get('entry_status');
		$data['entry_sort_order'] = $this->language->get('entry_sort_order');

		$data['help_total'] = $this->language->get('help_total');

		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['username'])) {
			$data['error_username'] = $this->error['username'];
		} else {
			$data['error_username'] = '';
		}

		if (isset($this->error['password'])) {
			$data['error_password'] = $this->error['password'];
		} else {
			$data['error_password'] = '';
		}

		if (isset($this->error['type'])) {
			$data['error_type'] = $this->error['type'];
		} else {
			$data['error_type'] = '';
		}

		if (isset($this->error['currency'])) {
			$data['error_currency'] = $this->error['currency'];
		} else {
			$data['error_currency'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_payment'),
			'href' => $this->url->link('extension/payment', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/payment/bank_of_baku', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/payment/bank_of_baku', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('extension/payment', 'user_token=' . $this->session->data['user_token'], true);

		if (isset($this->request->post['payment_bank_of_baku_username'])) {
			$data['payment_bank_of_baku_username'] = $this->request->post['payment_bank_of_baku_username'];
		} else {
			$data['payment_bank_of_baku_username'] = $this->config->get('payment_bank_of_baku_username');
		}

		if (isset($this->request->post['payment_bank_of_baku_password'])) {
			$data['payment_bank_of_baku_password'] = $this->request->post['payment_bank_of_baku_password'];
		} else {
			$data['payment_bank_of_baku_password'] = $this->config->get('payment_bank_of_baku_password');
		}

		if (isset($this->request->post['payment_bank_of_baku_type'])) {
			$data['payment_bank_of_baku_type'] = $this->request->post['payment_bank_of_baku_type'];
		} else {
			$data['payment_bank_of_baku_type'] = $this->config->get('payment_bank_of_baku_type');
		}

        if (isset($this->request->post['payment_bank_of_baku_taksit'])) {
            $data['payment_bank_of_baku_taksit'] = $this->request->post['payment_bank_of_baku_taksit'];
        } else {
            $data['payment_bank_of_baku_taksit'] = $this->config->get('payment_bank_of_baku_taksit');
        }

		if (isset($this->request->post['payment_bank_of_baku_currency'])) {
			$data['payment_bank_of_baku_currency'] = $this->request->post['payment_bank_of_baku_currency'];
		} else {
			$data['payment_bank_of_baku_currency'] = $this->config->get('payment_bank_of_baku_currency');
		}

        if (isset($this->request->post['payment_bank_of_baku_total'])) {
            $data['payment_bank_of_baku_total'] = $this->request->post['payment_bank_of_baku_total'];
        } else {
            $data['payment_bank_of_baku_total'] = $this->config->get('payment_bank_of_baku_total');
        }

        if (isset($this->request->post['payment_bank_of_baku_order_status_id'])) {
            $data['payment_bank_of_baku_order_status_id'] = $this->request->post['payment_bank_of_baku_order_status_id'];
        } else {
            $data['payment_bank_of_baku_order_status_id'] = $this->config->get('payment_bank_of_baku_order_status_id');
        }

        if (isset($this->request->post['payment_bank_of_baku_success_order_status_id'])) {
            $data['payment_bank_of_baku_success_order_status_id'] = $this->request->post['payment_bank_of_baku_success_order_status_id'];
        } else {
            $data['payment_bank_of_baku_success_order_status_id'] = $this->config->get('payment_bank_of_baku_success_order_status_id');
        }

        if (isset($this->request->post['payment_bank_of_baku_failure_order_status_id'])) {
            $data['payment_bank_of_baku_failure_order_status_id'] = $this->request->post['payment_bank_of_baku_failure_order_status_id'];
        } else {
            $data['payment_bank_of_baku_failure_order_status_id'] = $this->config->get('payment_bank_of_baku_failure_order_status_id');
        }

		$this->load->model('localisation/order_status');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		if (isset($this->request->post['payment_bank_of_baku_geo_zone_id'])) {
			$data['payment_bank_of_baku_geo_zone_id'] = $this->request->post['payment_bank_of_baku_geo_zone_id'];
		} else {
			$data['payment_bank_of_baku_geo_zone_id'] = $this->config->get('payment_bank_of_baku_geo_zone_id');
		}

		$this->load->model('localisation/geo_zone');

		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		if (isset($this->request->post['bank_of_baku_status'])) {
			$data['payment_bank_of_baku_status'] = $this->request->post['payment_bank_of_baku_status'];
		} else {
			$data['payment_bank_of_baku_status'] = $this->config->get('payment_bank_of_baku_status');
		}

		if (isset($this->request->post['payment_bank_of_baku_sort_order'])) {
			$data['payment_bank_of_baku_sort_order'] = $this->request->post['payment_bank_of_baku_sort_order'];
		} else {
			$data['payment_bank_of_baku_sort_order'] = $this->config->get('payment_bank_of_baku_sort_order');
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/payment/bank_of_baku', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/payment/bank_of_baku')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (!$this->request->post['payment_bank_of_baku_username']) {
			$this->error['username'] = $this->language->get('error_username');
		}

		if (!$this->request->post['payment_bank_of_baku_password']) {
			$this->error['password'] = $this->language->get('error_password');
		}

		return !$this->error;
	}
}