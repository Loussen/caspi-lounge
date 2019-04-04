<?php
class ControllerExtensionPaymentEposAz extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/payment/eposaz');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('payment_eposaz', $this->request->post);

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

		$data['entry_publickey'] = $this->language->get('entry_publickey');
		$data['entry_privatekey'] = $this->language->get('entry_privatekey');
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

		if (isset($this->error['publickey'])) {
			$data['error_publickey'] = $this->error['publickey'];
		} else {
			$data['error_publickey'] = '';
		}

		if (isset($this->error['privatekey'])) {
			$data['error_privatekey'] = $this->error['privatekey'];
		} else {
			$data['error_privatekey'] = '';
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
			'href' => $this->url->link('extension/payment/eposaz', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/payment/eposaz', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('extension/payment', 'user_token=' . $this->session->data['user_token'], true);

		if (isset($this->request->post['payment_eposaz_publickey'])) {
			$data['payment_eposaz_publickey'] = $this->request->post['payment_eposaz_publickey'];
		} else {
			$data['payment_eposaz_publickey'] = $this->config->get('payment_eposaz_publickey');
		}

		if (isset($this->request->post['payment_eposaz_privatekey'])) {
			$data['payment_eposaz_privatekey'] = $this->request->post['payment_eposaz_privatekey'];
		} else {
			$data['payment_eposaz_privatekey'] = $this->config->get('payment_eposaz_privatekey');
		}

		if (isset($this->request->post['payment_eposaz_type'])) {
			$data['payment_eposaz_type'] = $this->request->post['payment_eposaz_type'];
		} else {
			$data['payment_eposaz_type'] = $this->config->get('payment_eposaz_type');
		}

        if (isset($this->request->post['payment_eposaz_taksit'])) {
            $data['payment_eposaz_taksit'] = $this->request->post['payment_eposaz_taksit'];
        } else {
            $data['payment_eposaz_taksit'] = $this->config->get('payment_eposaz_taksit');
        }

		if (isset($this->request->post['payment_eposaz_currency'])) {
			$data['payment_eposaz_currency'] = $this->request->post['payment_eposaz_currency'];
		} else {
			$data['payment_eposaz_currency'] = $this->config->get('payment_eposaz_currency');
		}

        if (isset($this->request->post['payment_eposaz_total'])) {
            $data['payment_eposaz_total'] = $this->request->post['payment_eposaz_total'];
        } else {
            $data['payment_eposaz_total'] = $this->config->get('payment_eposaz_total');
        }

        if (isset($this->request->post['payment_eposaz_order_status_id'])) {
            $data['payment_eposaz_order_status_id'] = $this->request->post['payment_eposaz_order_status_id'];
        } else {
            $data['payment_eposaz_order_status_id'] = $this->config->get('payment_eposaz_order_status_id');
        }

        if (isset($this->request->post['payment_eposaz_success_order_status_id'])) {
            $data['payment_eposaz_success_order_status_id'] = $this->request->post['payment_eposaz_success_order_status_id'];
        } else {
            $data['payment_eposaz_success_order_status_id'] = $this->config->get('payment_eposaz_success_order_status_id');
        }

        if (isset($this->request->post['payment_eposaz_failure_order_status_id'])) {
            $data['payment_eposaz_failure_order_status_id'] = $this->request->post['payment_eposaz_failure_order_status_id'];
        } else {
            $data['payment_eposaz_failure_order_status_id'] = $this->config->get('payment_eposaz_failure_order_status_id');
        }

		$this->load->model('localisation/order_status');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		if (isset($this->request->post['payment_eposaz_geo_zone_id'])) {
			$data['payment_eposaz_geo_zone_id'] = $this->request->post['payment_eposaz_geo_zone_id'];
		} else {
			$data['payment_eposaz_geo_zone_id'] = $this->config->get('payment_eposaz_geo_zone_id');
		}

		$this->load->model('localisation/geo_zone');

		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		if (isset($this->request->post['eposaz_status'])) {
			$data['payment_eposaz_status'] = $this->request->post['payment_eposaz_status'];
		} else {
			$data['payment_eposaz_status'] = $this->config->get('payment_eposaz_status');
		}

		if (isset($this->request->post['payment_eposaz_sort_order'])) {
			$data['payment_eposaz_sort_order'] = $this->request->post['payment_eposaz_sort_order'];
		} else {
			$data['payment_eposaz_sort_order'] = $this->config->get('payment_eposaz_sort_order');
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/payment/eposaz', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/payment/eposaz')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (!$this->request->post['payment_eposaz_publickey']) {
			$this->error['publickey'] = $this->language->get('error_publickey');
		}

		if (!$this->request->post['payment_eposaz_privatekey']) {
			$this->error['privatekey'] = $this->language->get('error_privatekey');
		}

		return !$this->error;
	}
}