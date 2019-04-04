<?php

class ControllerExtensionPaymentSquareup extends Controller {
    const CRON_ENDED_FLAG_COMPLETE = 1;
    const CRON_ENDED_FLAG_ERROR = 2;
    const CRON_ENDED_FLAG_TIMEOUT = 3;

    private $error = array();
    private $version = '3.1.7';

    public function index() {
        $this->load->language('extension/payment/squareup');

        $this->load->model('extension/payment/squareup');
        $this->load->model('setting/setting');

        $this->load->library('squareup');

        // Ensures missing tables would be created
        $this->model_extension_payment_squareup->createTables();

        // Ensures that all necessary alterations would be performed after an update
        $this->model_extension_payment_squareup->alterTables();
        $this->model_extension_payment_squareup->createIndexes();

        if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->user->hasPermission('modify', 'extension/payment/squareup')) {
            // Ensures that the necessary events are hooked
            $this->model_extension_payment_squareup->dropEvents();
            $this->model_extension_payment_squareup->createEvents();
        }

        //Check for old columns
        $this->model_extension_payment_squareup->updateDatabase();

        $missing_geo_zones = $this->model_extension_payment_squareup->missingPreliminaryGeoZones();
        $skip_geo_zones = (bool)$this->config->get('payment_squareup_skip_geo_zones');
        $from_geo_zone_link = (bool)isset($this->request->get['show_geo_zone']);

        // Deprecated - should be used when we implement a Catalog sync in the direction Square > OpenCart
        $can_modify_geo_zones = false; //$this->user->hasPermission('modify', 'localisation/geo_zone');

        if ($can_modify_geo_zones && ($from_geo_zone_link || ($missing_geo_zones && !$skip_geo_zones))) {
            $this->showGeoZones();
        } else {
            $this->showForm();
        }
    }

    public function connect() {
        $this->load->language('extension/payment/squareup');

        $this->load->library('squareup');

        $json = array();

        if (!$this->user->hasPermission('modify', 'extension/payment/squareup')) {
            $json['error'] = $this->language->get('error_permission');
        }

        if (empty($this->request->post['payment_squareup_client_id']) || strlen($this->request->post['payment_squareup_client_id']) > 32) {
            $json['error'] = $this->language->get('error_client_id');
        }

        if (empty($this->request->post['payment_squareup_client_secret']) || strlen($this->request->post['payment_squareup_client_secret']) > 50) {
            $json['error'] = $this->language->get('error_client_secret');
        }

        if (empty($json['error'])) {
            $this->session->data['payment_squareup_connect']['payment_squareup_client_id'] = $this->request->post['payment_squareup_client_id'];
            $this->session->data['payment_squareup_connect']['payment_squareup_client_secret'] = $this->request->post['payment_squareup_client_secret'];
            $this->session->data['payment_squareup_connect']['payment_squareup_webhook_signature'] = $this->request->post['payment_squareup_webhook_signature'];

            $json['redirect'] = $this->squareup_api->authLink($this->request->post['payment_squareup_client_id']);
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function transaction_info() {
        $this->load->language('extension/payment/squareup');

        $this->load->model('extension/payment/squareup');

        $this->load->library('squareup');

        if (isset($this->request->get['squareup_transaction_id'])) {
            $squareup_transaction_id = $this->request->get['squareup_transaction_id'];
        } else {
            $squareup_transaction_id = 0;
        }

        $transaction_info = $this->model_extension_payment_squareup->getTransaction($squareup_transaction_id);

        if (empty($transaction_info)) {
            $this->response->redirect($this->url->link('extension/payment/squareup', 'user_token=' . $this->session->data['user_token'], true));
        }

        $transaction_status = $this->model_extension_payment_squareup->getTransactionStatus($transaction_info);

        $this->document->setTitle(sprintf($this->language->get('heading_title_transaction'), $transaction_info['transaction_id']));

        $data['alerts'] = $this->pullAlerts();

        $this->clearAlerts();

        $data['text_edit'] = sprintf($this->language->get('heading_title_transaction'), $transaction_info['transaction_id']);

        $amount = $this->currency->format($transaction_info['transaction_amount'], $transaction_info['transaction_currency']);

        $data['confirm_capture'] = sprintf($this->language->get('text_confirm_capture'), $amount);
        $data['confirm_void'] = sprintf($this->language->get('text_confirm_void'), $amount);
        $data['confirm_refund'] = $this->language->get('text_confirm_refund');
        $data['insert_amount'] = sprintf($this->language->get('text_insert_amount'), $amount, $transaction_info['transaction_currency']);
        $data['text_loading'] = $this->language->get('text_loading_short');

        $data['billing_address_company'] = $transaction_info['billing_address_company'];
        $data['billing_address_street'] = $transaction_info['billing_address_street_1'] . ' ' . $transaction_info['billing_address_street_2'];
        $data['billing_address_city'] = $transaction_info['billing_address_city'];
        $data['billing_address_postcode'] = $transaction_info['billing_address_postcode'];
        $data['billing_address_province'] = $transaction_info['billing_address_province'];
        $data['billing_address_country'] = $transaction_info['billing_address_country'];

        $data['transaction_id'] = $transaction_info['transaction_id'];
        $data['is_fully_refunded'] = $transaction_status['is_fully_refunded'];
        $data['merchant'] = $transaction_info['merchant_id'];
        $data['order_id'] = $transaction_info['order_id'];
        $data['status'] = $transaction_status['text'];
        $data['order_history_data'] = json_encode($transaction_status['order_history_data']);
        $data['amount'] = $amount;
        $data['currency'] = $transaction_info['transaction_currency'];
        $data['browser'] = $transaction_info['device_browser'];
        $data['ip'] = $transaction_info['device_ip'];
        $data['date_created'] = date($this->language->get('datetime_format'), strtotime($transaction_info['created_at']));

        $data['is_merchant_transaction'] = $transaction_status['is_merchant_transaction'];

        if (!$data['is_merchant_transaction']) {
            $data['alerts'][] = array(
                'type' => 'warning',
                'icon' => 'warning',
                'text' => sprintf($this->language->get('text_different_merchant'), $transaction_info['merchant_id'], $this->config->get('payment_squareup_merchant_id'))
            );
        }


        $data['cancel'] = $this->url->link('extension/payment/squareup', 'user_token=' . $this->session->data['user_token'] . '&tab=tab-transaction', true);

        $data['url_order'] = $this->url->link('sale/order/info', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $transaction_info['order_id'], true);
        $data['url_void'] = $this->url->link('extension/payment/squareup/void', 'user_token=' . $this->session->data['user_token'] . '&preserve_alert=true&squareup_transaction_id=' . $transaction_info['squareup_transaction_id'], true);
        $data['url_capture'] = $this->url->link('extension/payment/squareup/capture', 'user_token=' . $this->session->data['user_token'] . '&preserve_alert=true&squareup_transaction_id=' . $transaction_info['squareup_transaction_id'], true);
        $data['url_refund'] = $this->url->link('extension/payment/squareup/refund', 'user_token=' . $this->session->data['user_token'] . '&preserve_alert=true&squareup_transaction_id=' . $transaction_info['squareup_transaction_id'], true);
        $data['url_refund_modal'] = $this->url->link('extension/payment/squareup/refund_modal', 'user_token=' . $this->session->data['user_token'] . '&preserve_alert=true&squareup_transaction_id=' . $transaction_info['squareup_transaction_id'], true);
        $data['url_transaction'] = sprintf(
            Squareup::VIEW_TRANSACTION_URL,
            $transaction_info['transaction_id'],
            $transaction_info['location_id']
        );

        $data['is_authorized'] = in_array($transaction_info['transaction_type'], array('AUTHORIZED'));
        $data['is_captured'] = in_array($transaction_info['transaction_type'], array('CAPTURED'));

        $data['has_refunds'] = count($transaction_status['refunds']) > 0;

        if ($data['has_refunds']) {
            $data['refunds'] = array();

            $data['text_refunds'] = sprintf($this->language->get('text_refunds'), count($transaction_status['refunds']));

            foreach ($transaction_status['refunds'] as $refund) {
                $amount = $this->currency->format(
                    $this->squareup_api->standardDenomination(
                        $refund['amount_money']['amount'],
                        $refund['amount_money']['currency']
                    ),
                    $refund['amount_money']['currency']
                );

                if (isset($refund['processing_fee_money'])) {
                    $fee = $this->currency->format(
                        $this->squareup_api->standardDenomination(
                            $refund['processing_fee_money']['amount'],
                            $refund['processing_fee_money']['currency']
                        ),
                        $refund['processing_fee_money']['currency']
                    );
                } else {
                    $fee = $this->language->get('text_na');
                }

                $data['refunds'][] = array(
                    'date_created' => date($this->language->get('datetime_format'), strtotime($refund['created_at'])),
                    'reason' => $refund['reason'],
                    'status' => $refund['status'],
                    'amount' => $amount,
                    'fee' => $fee
                );
            }
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/payment/squareup', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => sprintf($this->language->get('heading_title_transaction'), $transaction_info['squareup_transaction_id']),
            'href' => $this->url->link('extension/payment/squareup/transaction_info', 'user_token=' . $this->session->data['user_token'] . '&squareup_transaction_id=' . $squareup_transaction_id, true)
        );

        $data['catalog'] = $this->request->server['HTTPS'] ? HTTPS_CATALOG : HTTP_CATALOG;

        // API login
        $this->load->model('user/api');

        $api_info = $this->model_user_api->getApi($this->config->get('config_api_id'));

        if ($api_info && $this->user->hasPermission('modify', 'sale/order')) {
            $session = new Session($this->config->get('session_engine'), $this->registry);

            $session->start();

            $this->model_user_api->deleteApiSessionBySessonId($session->getId());

            $this->model_user_api->addApiSession($api_info['api_id'], $session->getId(), $this->request->server['REMOTE_ADDR']);

            $session->data['api_id'] = $api_info['api_id'];

            $data['api_token'] = $session->getId();
        } else {
            $data['api_token'] = '';
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payment/squareup_transaction_info', $data));
    }

    public function transactions() {
        $this->load->language('extension/payment/squareup');

        $this->load->model('extension/payment/squareup');

        if (isset($this->request->get['page'])) {
            $page = (int)$this->request->get['page'];
        } else {
            $page = 1;
        }

        $order_histories = array();

        $result = array(
            'transactions' => array(),
            'pagination' => ''
        );

        $filter_data = array(
            'start' => ($page - 1) * (int)10,
            'limit' => 10
        );

        if (isset($this->request->get['order_id'])) {
            // We want to get all possible transactions, regardless of the selected page
            $filter_data = array(
                'order_id' => $this->request->get['order_id']
            );
        }

        $transactions_total = $this->model_extension_payment_squareup->getTotalTransactions($filter_data);
        $transactions = $this->model_extension_payment_squareup->getTransactions($filter_data);

        $this->load->model('sale/order');

        foreach ($transactions as $transaction) {
            $amount = $this->currency->format($transaction['transaction_amount'], $transaction['transaction_currency']);

            $order_info = $this->model_sale_order->getOrder($transaction['order_id']);

            $transaction_status = $this->model_extension_payment_squareup->getTransactionStatus($transaction);

            if ($transaction_status['order_history_data']) {
                $order_histories[] = $transaction_status['order_history_data'];
            }

            $result['transactions'][] = array(
                'squareup_transaction_id' => $transaction['squareup_transaction_id'],
                'transaction_id' => $transaction['transaction_id'],
                'url_order' => $this->url->link('sale/order/info', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $transaction['order_id'], true),
                'url_void' => $this->url->link('extension/payment/squareup/void', 'user_token=' . $this->session->data['user_token'] . '&squareup_transaction_id=' . $transaction['squareup_transaction_id'], true),
                'url_capture' => $this->url->link('extension/payment/squareup/capture', 'user_token=' . $this->session->data['user_token'] . '&squareup_transaction_id=' . $transaction['squareup_transaction_id'], true),
                'url_refund' => $this->url->link('extension/payment/squareup/refund', 'user_token=' . $this->session->data['user_token'] . '&squareup_transaction_id=' . $transaction['squareup_transaction_id'], true),
                'url_refund_modal' => $this->url->link('extension/payment/squareup/refund_modal', 'user_token=' . $this->session->data['user_token'] . '&squareup_transaction_id=' . $transaction['squareup_transaction_id'], true),
                'confirm_capture' => sprintf($this->language->get('text_confirm_capture'), $amount),
                'confirm_void' => sprintf($this->language->get('text_confirm_void'), $amount),
                'order_id' => $transaction['order_id'],
                'type' => $transaction_status['type'],
                'status' => $transaction_status['text'],
                'amount_refunded' => $transaction_status['amount_refunded'],
                'is_fully_refunded' => $transaction_status['is_fully_refunded'],
                'is_merchant_transaction' => $transaction_status['is_merchant_transaction'],
                'text_different_merchant' => sprintf($this->language->get('text_different_merchant'), $transaction['merchant_id'], $this->config->get('payment_squareup_merchant_id')),
                'order_history_data' => $transaction_status['order_history_data'],
                'amount' => $amount,
                'customer' => $order_info['firstname'] . ' ' . $order_info['lastname'],
                'ip' => $transaction['device_ip'],
                'date_created' => date($this->language->get('datetime_format'), strtotime($transaction['created_at'])),
                'url_info' => $this->url->link('extension/payment/squareup/transaction_info', 'user_token=' . $this->session->data['user_token'] . '&squareup_transaction_id=' . $transaction['squareup_transaction_id'], true)
            );
        }

        $pagination = new Pagination();
        $pagination->total = $transactions_total;
        $pagination->page = $page;
        $pagination->limit = 10;
        $pagination->url = '{page}';

        $result['pagination'] = $pagination->render();

        if (isset($this->request->get['order_id'])) {
            $result['order_histories'] = $order_histories;
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($result));
    }

    public function refresh_token() {
        $this->load->language('extension/payment/squareup');

        if (!$this->user->hasPermission('modify', 'extension/payment/squareup')) {
            $this->pushAlert(array(
                'type' => 'danger',
                'icon' => 'exclamation-circle',
                'text' => $this->language->get('error_permission')
            ));

            $this->response->redirect($this->url->link('extension/payment/squareup', 'user_token=' . $this->session->data['user_token'], true));
        }

        $this->load->model('setting/setting');

        $this->load->library('squareup');

        try {
            $response = $this->squareup_api->refreshToken();

            if (!isset($response['access_token']) || !isset($response['token_type']) || !isset($response['expires_at']) || !isset($response['merchant_id']) ||
                $response['merchant_id'] != $this->config->get('payment_squareup_merchant_id')) {
                $this->pushAlert(array(
                    'type' => 'danger',
                    'icon' => 'exclamation-circle',
                    'text' => $this->language->get('error_refresh_access_token')
                ));
            } else {
                $settings = $this->model_setting_setting->getSetting('payment_squareup');

                $settings['payment_squareup_access_token'] = $response['access_token'];
                $settings['payment_squareup_access_token_expires'] = $response['expires_at'];

                $this->model_setting_setting->editSetting('payment_squareup', $settings);

                $this->pushAlert(array(
                    'type' => 'success',
                    'icon' => 'exclamation-circle',
                    'text' => $this->language->get('text_refresh_access_token_success')
                ));
            }
        } catch (\Squareup\Exception\Api $e) {
            $this->pushAlert(array(
                'type' => 'danger',
                'icon' => 'exclamation-circle',
                'text' => sprintf($this->language->get('error_token'), $e->getMessage())
            ));
        }

        $this->response->redirect($this->url->link('extension/payment/squareup', 'user_token=' . $this->session->data['user_token'], true));
    }

    public function oauth_callback() {
        $this->load->language('extension/payment/squareup');

        if (!$this->user->hasPermission('modify', 'extension/payment/squareup')) {
            $this->pushAlert(array(
                'type' => 'danger',
                'icon' => 'exclamation-circle',
                'text' => $this->language->get('error_permission')
            ));

            $this->response->redirect($this->url->link('extension/payment/squareup', 'user_token=' . $this->session->data['user_token'], true));
        }

        $this->load->library('squareup');

        if (isset($this->request->get['error']) || isset($this->request->get['error_description'])) {
            // auth error
            if ($this->request->get['error'] == 'access_denied' && $this->request->get['error_description'] == 'user_denied') {
                // user rejected giving auth permissions to his store
                $this->pushAlert(array(
                    'type' => 'warning',
                    'icon' => 'exclamation-circle',
                    'text' => $this->language->get('error_user_rejected_connect_attempt')
                ));
            }

            $this->response->redirect($this->url->link('extension/payment/squareup', 'user_token=' . $this->session->data['user_token'], true));
        }

        // verify parameters for the redirect from Square (against random url crawling)
        if (!isset($this->request->get['state']) || !isset($this->request->get['code'])) {
            // missing or wrong info
            $this->pushAlert(array(
                'type' => 'danger',
                'icon' => 'exclamation-circle',
                'text' => $this->language->get('error_possible_xss')
            ));

            $this->response->redirect($this->url->link('extension/payment/squareup', 'user_token=' . $this->session->data['user_token'], true));
        }

        // verify the state (against cross site requests)
        if (!isset($this->session->data['payment_squareup_oauth_state']) || $this->session->data['payment_squareup_oauth_state'] != $this->request->get['state']) {
            // state mismatch
            $this->pushAlert(array(
                'type' => 'danger',
                'icon' => 'exclamation-circle',
                'text' => $this->language->get('error_possible_xss')
            ));

            $this->response->redirect($this->url->link('extension/payment/squareup', 'user_token=' . $this->session->data['user_token'], true));
        }

        try {
            $token = $this->squareup_api->exchangeCodeForAccessToken($this->request->get['code']);

            $this->session->data['payment_squareup_token'] = $token;

            if ($this->config->has('payment_squareup_merchant_id') && $this->config->get('payment_squareup_merchant_id') != $token['merchant_id']) {
                $this->response->redirect($this->url->link('extension/payment/squareup/confirm_merchant', 'user_token=' . $this->session->data['user_token'], true));
            } else {
                $this->acceptNewMerchant();
                $this->clearConnectSession();
                $this->response->redirect($this->url->link('extension/payment/squareup', 'user_token=' . $this->session->data['user_token'], true));
            }
        } catch (\Squareup\Exception\Api $e) {
            $this->pushAlert(array(
                'type' => 'danger',
                'icon' => 'exclamation-circle',
                'text' => sprintf($this->language->get('error_token'), $e->getMessage())
            ));

            $this->response->redirect($this->url->link('extension/payment/squareup', 'user_token=' . $this->session->data['user_token'], true));
        }
    }

    public function confirm_merchant() {
        if (empty($this->session->data['payment_squareup_token'])) {
            $this->response->redirect($this->url->link('extension/payment/squareup', 'user_token=' . $this->session->data['user_token'], true));
        }

        $has_catalog_sync = 'none' != $this->config->get('payment_squareup_sync_source');

        if (isset($this->request->get['action'])) {
            if ($this->request->get['action'] == 'confirm') {
                $this->load->model('extension/payment/squareup');

                $this->acceptNewMerchant($has_catalog_sync);
                $this->model_extension_payment_squareup->truncateMerchantSpecificTables();
            }

            $this->clearConnectSession();
            $this->response->redirect($this->url->link('extension/payment/squareup', 'user_token=' . $this->session->data['user_token'], true));
        }

        $this->load->language('extension/payment/squareup');

        $this->document->setTitle($this->language->get('heading_title_confirm_merchant'));

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/payment/squareup', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title_confirm_merchant'),
            'href' => $this->url->link('extension/payment/squareup/confirm_merchant', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['has_catalog_sync'] = $has_catalog_sync;

        $data['confirm'] = $this->url->link('extension/payment/squareup/confirm_merchant', 'user_token=' . $this->session->data['user_token'] . '&action=confirm', true);
        $data['reject'] = $this->url->link('extension/payment/squareup/confirm_merchant', 'user_token=' . $this->session->data['user_token'] . '&action=reject', true);

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payment/squareup_confirm_merchant', $data));
    }

    protected function acceptNewMerchant($force_on_demand_sync = false) {
        $this->load->language('extension/payment/squareup');

        $this->load->library('squareup');

        $this->load->model('setting/setting');

        $token = $this->session->data['payment_squareup_token'];

        $previous_setting = $this->model_setting_setting->getSetting('payment_squareup');

        $previous_setting['payment_squareup_locations'] = $this->squareup_api->fetchLocations($token['access_token'], $first_location_id);

        if (
            !isset($previous_setting['payment_squareup_location_id']) ||
            (isset($previous_setting['payment_squareup_location_id']) && !in_array(
                $previous_setting['payment_squareup_location_id'],
                array_map(
                    function($location) {
                        return $location['id'];
                    },
                    $previous_setting['payment_squareup_locations']
                )
            ))
        ) {
            $previous_setting['payment_squareup_location_id'] = $first_location_id;
        }

        unset($previous_setting['payment_squareup_sandbox_locations']);
        unset($previous_setting['payment_squareup_sandbox_location_id']);

        $previous_setting['payment_squareup_client_id'] = $this->session->data['payment_squareup_connect']['payment_squareup_client_id'];
        $previous_setting['payment_squareup_client_secret'] = $this->session->data['payment_squareup_connect']['payment_squareup_client_secret'];
        $previous_setting['payment_squareup_webhook_signature'] = $this->session->data['payment_squareup_connect']['payment_squareup_webhook_signature'];
        $previous_setting['payment_squareup_merchant_id'] = $token['merchant_id'];
        $previous_setting['payment_squareup_merchant_name'] = '';
        $previous_setting['payment_squareup_access_token'] = $token['access_token'];
        $previous_setting['payment_squareup_access_token_expires'] = $token['expires_at'];

        if ($force_on_demand_sync) {
            $previous_setting['payment_squareup_cron_is_on_demand'] = '1';
        }

        $this->model_setting_setting->editSetting('payment_squareup', $previous_setting);

        $this->pushAlert(array(
            'type' => 'success',
            'icon' => 'exclamation-circle',
            'text' => $this->language->get('text_refresh_access_token_success')
        ));
    }

    protected function clearConnectSession() {
        unset($this->session->data['payment_squareup_connect']);
        unset($this->session->data['payment_squareup_oauth_state']);
        unset($this->session->data['payment_squareup_oauth_redirect']);
        unset($this->session->data['payment_squareup_token']);
    }

    public function capture() {
        $this->transactionAction(function($transaction_info, &$json) {
            $this->squareup_api->captureTransaction($transaction_info['location_id'], $transaction_info['transaction_id']);

            $status = 'CAPTURED';

            $this->model_extension_payment_squareup->updateTransaction($transaction_info['squareup_transaction_id'], $status);

            $json['order_history_data'] = array(
                'notify' => 1,
                'squareup_is_capture' => true,
                'order_id' => $transaction_info['order_id'],
                'order_status_id' => $this->model_extension_payment_squareup->getOrderStatusId($transaction_info['order_id'], $status),
                'comment' => $this->language->get('squareup_status_comment_' . strtolower($status)),
            );

            $json['success'] = $this->language->get('text_success_capture');
        });
    }

    public function void() {
        $this->transactionAction(function($transaction_info, &$json) {
            $this->squareup_api->voidTransaction($transaction_info['location_id'], $transaction_info['transaction_id']);

            $status = 'VOIDED';

            $this->model_extension_payment_squareup->updateTransaction($transaction_info['squareup_transaction_id'], $status);

            $json['order_history_data'] = array(
                'notify' => 1,
                'order_id' => $transaction_info['order_id'],
                'order_status_id' => $this->model_extension_payment_squareup->getOrderStatusId($transaction_info['order_id'], $status),
                'comment' => $this->language->get('squareup_status_comment_' . strtolower($status)),
            );

            $json['success'] = $this->language->get('text_success_void');
        });
    }

    public function refund_modal() {
        $this->load->language('extension/payment/squareup');

        $this->load->model('extension/payment/squareup');

        $this->load->library('squareup');

        $json = array();

        if (!$this->user->hasPermission('modify', 'extension/payment/squareup')) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            $data = array();

            $transaction_info = $this->model_extension_payment_squareup->getTransaction($this->request->get['squareup_transaction_id']);

            $max_allowed_amount = $this->squareup_api->lowestDenomination($transaction_info['transaction_amount'], $transaction_info['transaction_currency']);

            if (!empty($transaction_info['refunds'])) {
                foreach (json_decode($transaction_info['refunds'], true) as $refund) {
                    $max_allowed_amount -= $refund['amount_money']['amount'];
                }
            }

            $max_allowed_amount_standard = $this->squareup_api->standardDenomination($max_allowed_amount, $transaction_info['transaction_currency']);

            $max_allowed = $this->currency->format($max_allowed_amount_standard, $transaction_info['transaction_currency']);

            $data['price_prefix'] = $this->currency->getSymbolLeft($transaction_info['transaction_currency']);
            $data['price_suffix'] = $this->currency->getSymbolRight($transaction_info['transaction_currency']);

            $data['max_allowed'] = $max_allowed_amount_standard;

            $data['text_itemized_refund_intro'] = sprintf($this->language->get('text_itemized_refund_intro'), $max_allowed, $transaction_info['transaction_currency']);

            $data['products'] = array();

            $this->load->model('sale/order');

            $products = $this->model_sale_order->getOrderProducts($transaction_info['order_id']);

            foreach ($products as $product) {
                $is_ad_hoc_item = $this->model_extension_payment_squareup->isAdHocItem($product['order_product_id']);
                $allowed_restock_quantity = $this->model_extension_payment_squareup->getAllowedRestockQuantity($product['order_product_id']);
                $allowed_refund_quantity = $this->model_extension_payment_squareup->getAllowedRefundQuantity($product['order_product_id']);

                $max_refund_quantity = min($allowed_refund_quantity, (int)$product['quantity']);
                $max_restock_quantity = min($max_refund_quantity, $allowed_restock_quantity, (int)$product['quantity']);

                $price = $product['price'] + $product['tax'];
                $total = $product['total'] + (int)$product['quantity'] * $product['tax'];

                $data['products'][] = array(
                    'product_id'                        => $product['product_id'],
                    'order_product_id'                  => $product['order_product_id'],
                    'name'                              => $product['name'],
                    'model'                             => $product['model'],
                    'options'                           => $this->model_sale_order->getOrderOptions($transaction_info['order_id'], $product['order_product_id']),
                    'quantity'                          => $product['quantity'],
                    'max_restock_quantity'              => $max_restock_quantity,
                    'max_refund_quantity'               => $max_refund_quantity,
                    'is_ad_hoc_item'                    => $is_ad_hoc_item,
                    'price_raw'                         => $price,
                    'price'                             => $this->currency->format($price, $transaction_info['transaction_currency']),
                    'price_total_raw'                   => $total,
                    'price_total'                       => $this->currency->format($total, $transaction_info['transaction_currency'])
                );
            }

            $data['text_insert_amount'] = sprintf($this->language->get('text_insert_amount'), $max_allowed, $transaction_info['transaction_currency']);

            $json['html'] = $this->load->view('extension/payment/squareup_refund_modal', $data);
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function refund() {
        $this->transactionAction(function($transaction_info, &$json) {
            if (!empty($this->request->post['reason'])) {
                $reason = $this->request->post['reason'];
            } else {
                $reason = $this->language->get('text_no_reason_provided');
            }

            if (!empty($this->request->post['amount'])) {
                $amount = preg_replace('~[^0-9\.\,]~', '', $this->request->post['amount']);

                if (strpos($amount, ',') !== FALSE && strpos($amount, '.') !== FALSE) {
                    $amount = (float)str_replace(',', '', $amount);
                } else if (strpos($amount, ',') !== FALSE && strpos($amount, '.') === FALSE) {
                    $amount = (float)str_replace(',', '.', $amount);
                } else {
                    $amount = (float)$amount;
                }
            } else {
                $amount = 0;
            }

            $currency = $transaction_info['transaction_currency'];
            $tenders = @json_decode($transaction_info['tenders'], true);

            $updated_transaction = $this->squareup_api->refundTransaction($transaction_info['location_id'], $transaction_info['transaction_id'], $reason, $amount, $currency, $tenders[0]['id']);

            $status = $updated_transaction['tenders'][0]['card_details']['status'];

            $refunds = array();

            if (!empty($updated_transaction['refunds'])) {
                $refunds = $updated_transaction['refunds'];
            }

            $this->model_extension_payment_squareup->updateTransaction($transaction_info['squareup_transaction_id'], $status, $refunds);

            $total_refunded_amount = 0;
            $has_pending = false;
            foreach ($refunds as $refund) {
                if ($refund['status'] == 'REJECTED' || $refund['status'] == 'FAILED') {
                    continue;
                }

                if ($refund['status'] == 'PENDING') {
                    $has_pending = true;
                }

                $total_refunded_amount = $refund['amount_money']['amount'];
            }

            $refund_status = null;
            if (!$has_pending) {
                if ($total_refunded_amount == $this->squareup_api->lowestDenomination($transaction_info['transaction_amount'], $transaction_info['transaction_currency'])) {
                    $refund_status = 'fully_refunded';
                } else {
                    $refund_status = 'partially_refunded';
                }
            }

            $last_refund = array_pop($refunds);

            if ($last_refund) {
                $refunded_amount = $this->currency->format(
                    $this->squareup_api->standardDenomination(
                        $last_refund['amount_money']['amount'],
                        $last_refund['amount_money']['currency']
                    ),
                    $last_refund['amount_money']['currency']
                );

                $comment = sprintf($this->language->get('text_refunded_amount'), $refunded_amount, $last_refund['status'], $last_refund['reason']);

                $order_history_data = array(
                    'notify' => 1,
                    'order_id' => $transaction_info['order_id'],
                    'order_status_id' => $this->model_extension_payment_squareup->getOrderStatusId($transaction_info['order_id'], $refund_status),
                    'comment' => $comment
                );

                if (isset($this->request->post['restock']) || isset($this->request->post['refund'])) {
                    $order_history = new \Squareup\OrderHistory($this->registry);

                    if (isset($this->request->post['restock'])) {
                        $restock = array();

                        foreach ($this->request->post['restock'] as $order_product_id => $quantity) {
                            $catalog_object_id = $order_history->getSquareItemObjectIdByOrderProductId($order_product_id);
                            $product_id = $order_history->getProductIdByOrderProductId($order_product_id);

                            $restock[] = array(
                                'catalog_object_id' => false !== $catalog_object_id ? $catalog_object_id : null,
                                'quantity' => $quantity,
                                'order_product_id' => $order_product_id,
                                'product_id' => $product_id
                            );
                        }

                        if (!empty($restock)) {
                            $order_history_data['square_restock'] = $restock;
                        }
                    }

                    if (isset($this->request->post['refund'])) {
                        $refund = array();

                        foreach ($this->request->post['refund'] as $order_product_id => $quantity) {
                            $catalog_object_id = $order_history->getSquareItemObjectIdByOrderProductId($order_product_id);
                            $product_id = $order_history->getProductIdByOrderProductId($order_product_id);

                            $refund[] = array(
                                'catalog_object_id' => false !== $catalog_object_id ? $catalog_object_id : null,
                                'quantity' => $quantity,
                                'order_product_id' => $order_product_id,
                                'product_id' => $product_id
                            );
                        }

                        if (!empty($refund)) {
                            $order_history_data['square_refund'] = $refund;
                        }
                    }
                }

                $json['order_history_data'] = $order_history_data;

                $json['success'] = $this->language->get('text_success_refund');
            } else {
                $json['error'] = $this->language->get('error_no_refund');
            }
        });
    }

    public function order() {
        $this->load->language('extension/payment/squareup');

        $data['url_list_transactions'] = html_entity_decode($this->url->link('extension/payment/squareup/transactions', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $this->request->get['order_id'] . '&page={PAGE}', true), ENT_QUOTES, "UTF-8");
        $data['user_token'] = $this->session->data['user_token'];
        $data['order_id'] = $this->request->get['order_id'];

        $data['catalog'] = $this->request->server['HTTPS'] ? HTTPS_CATALOG : HTTP_CATALOG;

        // API login
        $this->load->model('user/api');

        $api_info = $this->model_user_api->getApi($this->config->get('config_api_id'));

        if ($api_info && $this->user->hasPermission('modify', 'sale/order')) {
            $session = new Session($this->config->get('session_engine'), $this->registry);

            $session->start();

            $this->model_user_api->deleteApiSessionBySessonId($session->getId());

            $this->model_user_api->addApiSession($api_info['api_id'], $session->getId(), $this->request->server['REMOTE_ADDR']);

            $session->data['api_id'] = $api_info['api_id'];

            $data['api_token'] = $session->getId();
        } else {
            $data['api_token'] = '';
        }

        return $this->load->view('extension/payment/squareup_order', $data);
    }

    public function install() {
        $this->load->model('extension/payment/squareup');

        $this->model_extension_payment_squareup->createTables();
    }

    public function uninstall() {
        $this->load->model('extension/payment/squareup');

        $this->model_extension_payment_squareup->dropTables();
    }

    public function recurringButtons() {
        if (!$this->user->hasPermission('modify', 'sale/recurring')) {
            return;
        }

        $this->load->model('extension/payment/squareup');

        $this->load->language('extension/payment/squareup');

        if (isset($this->request->get['order_recurring_id'])) {
            $order_recurring_id = $this->request->get['order_recurring_id'];
        } else {
            $order_recurring_id = 0;
        }

        $recurring_info = $this->model_sale_recurring->getRecurring($order_recurring_id);

        $data['button_text'] = $this->language->get('button_cancel_recurring');

        if ($recurring_info['status'] == ModelExtensionPaymentSquareup::RECURRING_ACTIVE) {
            $data['order_recurring_id'] = $order_recurring_id;
        } else {
            $data['order_recurring_id'] = '';
        }

        $this->load->model('sale/order');

        $order_info = $this->model_sale_order->getOrder($recurring_info['order_id']);

        $data['order_id'] = $recurring_info['order_id'];
        $data['store_id'] = $order_info['store_id'];
        $data['order_status_id'] = $order_info['order_status_id'];
        $data['comment'] = $this->language->get('text_order_history_cancel');
        $data['notify'] = 1;

        $data['catalog'] = $this->request->server['HTTPS'] ? HTTPS_CATALOG : HTTP_CATALOG;

        // API login
        $this->load->model('user/api');

        $api_info = $this->model_user_api->getApi($this->config->get('config_api_id'));

        if ($api_info && $this->user->hasPermission('modify', 'sale/order')) {
            $session = new Session($this->config->get('session_engine'), $this->registry);

            $session->start();

            $this->model_user_api->deleteApiSessionBySessonId($session->getId());

            $this->model_user_api->addApiSession($api_info['api_id'], $session->getId(), $this->request->server['REMOTE_ADDR']);

            $session->data['api_id'] = $api_info['api_id'];

            $data['api_token'] = $session->getId();
        } else {
            $data['api_token'] = '';
        }

        $data['cancel'] = html_entity_decode($this->url->link('extension/payment/squareup/recurringCancel', 'order_recurring_id=' . $order_recurring_id . '&user_token=' . $this->session->data['user_token'], true), ENT_QUOTES, "UTF-8");

        return $this->load->view('extension/payment/squareup_recurring_buttons', $data);
    }

    public function recurringCancel() {
        $this->load->language('extension/payment/squareup');

        $json = array();

        if (!$this->user->hasPermission('modify', 'sale/recurring')) {
            $json['error'] = $this->language->get('error_permission_recurring');
        } else {
            $this->load->model('sale/recurring');

            if (isset($this->request->get['order_recurring_id'])) {
                $order_recurring_id = $this->request->get['order_recurring_id'];
            } else {
                $order_recurring_id = 0;
            }

            $recurring_info = $this->model_sale_recurring->getRecurring($order_recurring_id);

            if ($recurring_info) {
                $this->load->model('extension/payment/squareup');

                $this->model_extension_payment_squareup->editOrderRecurringStatus($order_recurring_id, ModelExtensionPaymentSquareup::RECURRING_CANCELLED);

                $json['success'] = $this->language->get('text_canceled_success');
            } else {
                $json['error'] = $this->language->get('error_not_found');
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function setAdminLink(&$route, &$data, &$template) {
        if (!$this->config->has('payment_squareup_status')) {
            return;
        }

        if (!$this->user->hasPermission('access', 'extension/payment/squareup')) {
            return;
        }

        foreach ($data['menus'] as &$menu) {
            if ($menu['id'] == 'menu-extension') {
                $menu['children'][] = array(
                    'name' => 'Square',
                    'children' => array(),
                    'href' => $this->url->link('extension/payment/squareup', 'user_token=' . $this->session->data['user_token'], true)
                );

                return;
            }
        }
    }

    public function setProductWarning(&$route, &$product_form_data, &$output) {
        if (!$this->config->has('payment_squareup_status')) {
            return;
        }

        if (!$this->config->has('payment_squareup_inventory_sync') || $this->config->get('payment_squareup_inventory_sync') == 'none') {
            return;
        }

        if (empty($this->request->get['product_id'])) {
            return;
        }

        $this->load->language('extension/payment/squareup');

        $this->load->library('squareup');

        $this->load->model('extension/payment/squareup');

        if (null === $item_id = $this->model_extension_payment_squareup->getItemId($this->request->get['product_id'])) {
            return;
        }

        $url_extension = html_entity_decode($this->url->link('extension/payment/squareup', 'user_token=' . $this->session->data['user_token'], true), ENT_QUOTES, 'UTF-8');
        $url_dashboard = $this->squareup_api->itemLink($item_id);

        $data['text'] = sprintf($this->language->get('text_product_warning'), $url_extension, $url_dashboard);

        $product_form_data['footer'] = $this->load->view('extension/payment/squareup_product_form_warning', $data) . $product_form_data['footer'];
    }

    /*
     * This is an event handler triggered once per admin panel request because admin directory name may get modified while a CRON job is registered. This method sets payment_squareup_admin_url_transaction and payment_squareup_admin_url_settings required by the Square catalog method ControllerExtensionPaymentSquareup::info()
     */

    public function setAdminURL() {
        // In case user is not yet defined, do nothing
        if (!$this->registry->has('user')) {
            return;
        }

        // We need this to run only once per request
        $this->event->unregister('controller/*/after', 'extension/payment/squareup/setAdminURL');

        // No need to run it for non-logged-in users
        if (!$this->user->isLogged()) {
            return;
        }

        $this->load->model('setting/setting');
        $this->load->model('extension/payment/squareup');

        $this->model_setting_setting->editSettingValue('payment_squareup', 'payment_squareup_admin_url_transaction', $this->model_extension_payment_squareup->getAdminURLTransaction());
        $this->model_setting_setting->editSettingValue('payment_squareup', 'payment_squareup_admin_url_settings', $this->model_extension_payment_squareup->getAdminURLSettings());
    }

    // A deprecated (public set to private) method which should be used when we implement a Catalog sync in the direction Square > OpenCart
    //public function geoZone() {
    private function geoZone() {
        $this->load->language('extension/payment/squareup');

        $this->load->model('extension/payment/squareup');
        $this->load->model('setting/setting');

        $action = isset($this->request->get['action']) ? $this->request->get['action'] : 'skip';

        switch ($action) {
            case 'confirm' : {
                // Confirm
                if ($this->user->hasPermission('modify', 'localisation/geo_zone')) {
                    $this->model_extension_payment_squareup->setupGeoZones();

                    $this->session->data['success'] = $this->language->get('text_success_geo_zone');
                } else {
                    $this->pushAlert($this->language->get('error_permission_geo_zone'));
                }
            } break;
            default : {
                // Skip
                if ($this->user->hasPermission('modify', 'extension/payment/squareup')) {
                    $previous_setting = $this->model_setting_setting->getSetting('payment_squareup');

                    $skip_geo_zones_setting = array(
                        'payment_squareup_skip_geo_zones' => 1
                    );

                    $this->model_setting_setting->editSetting('payment_squareup', array_merge($previous_setting, $skip_geo_zones_setting));
                } else {
                    $this->pushAlert($this->language->get('error_permission'));
                }
            } break;
        }

        $this->response->redirect($this->url->link('extension/payment/squareup', 'user_token=' . $this->session->data['user_token'], true));
    }

    public function taxRate() {
        $this->load->language('extension/payment/squareup');

        $json = array();

        if (!$this->user->hasPermission('modify', 'localisation/tax_rate')) {
            $json['error'] = $this->language->get('error_permission_tax_rate');
        } else {
            $this->load->model('localisation/tax_rate');
            $this->load->model('extension/payment/squareup');

            foreach ($this->request->post['tax_rate'] as $tax_rate_id => $geo_zone_id) {
                if (empty($geo_zone_id)) {
                    $json['error_tax_rate_id'][] = $tax_rate_id;
                } else {
                    $this->model_extension_payment_squareup->updateTaxRateGeoZone($tax_rate_id, $geo_zone_id);
                }
            }

            if (!empty($json['error_tax_rate_id'])) {
                $json['error'] = $this->language->get('error_tax_rate');
            } else {
                $json['success'] = true;
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    // A deprecated (public set to private) method which should be used when we implement a Catalog sync in the direction Square > OpenCart
    // public function syncModal() {
    private function syncModal() {
        $json = array();

        $this->load->language('extension/payment/squareup');

        $this->load->model('extension/payment/squareup');

        $this->load->library('squareup');

        $data = array();

        $oc_product_count = $this->model_extension_payment_squareup->countOpenCartProducts();
        $square_product_count = $this->squareup_api->countSquareItems();

        $location_name = '';

        foreach ($this->config->get('payment_squareup_locations') as $location) {
            if ($location['id'] == $this->config->get('payment_squareup_location_id')) {
                $location_name = $location['name'];
            }
        }

        $square_delta = $square_product_count - $oc_product_count;
        $oc_delta = $oc_product_count - $square_product_count;

        $data['text_sync_configure_intro'] = sprintf($this->language->get('text_sync_configure_intro'), $location_name, $square_product_count, $oc_product_count);

        if ($square_delta >= 0) {
            $data['text_sync_configure_option_1'] = sprintf($this->language->get('text_sync_configure_option_1_unassign'), $square_delta);
        } else {
            $data['text_sync_configure_option_1'] = sprintf($this->language->get('text_sync_configure_option_1_assign'), abs($square_delta));
        }

        $data['text_sync_configure_option_2'] = sprintf($this->language->get('text_sync_configure_option_2'), $square_product_count);

        if ($oc_delta >= 0) {
            $data['text_sync_configure_option_3'] = sprintf($this->language->get('text_sync_configure_option_3_unassign'), $oc_delta);
        } else {
            $data['text_sync_configure_option_3'] = sprintf($this->language->get('text_sync_configure_option_3_assign'), abs($oc_delta));
        }

        $data['text_sync_configure_option_4'] = sprintf($this->language->get('text_sync_configure_option_4'), $oc_product_count);

        $direction = $this->getPostValue('payment_squareup_sync_source');

        if ($direction == 'opencart') {
            $data['selected'] = '2';
        } else {
            $data['selected'] = '4';
        }

        $json['html'] = $this->load->view('extension/payment/squareup_sync_modal', $data);
        $json['already_synced'] = (bool)$this->config->get('payment_squareup_initial_sync');

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function download_sync_log() {
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Content-Description: File Transfer');
        header('Content-Type: plain/text');
        header('Content-Disposition: attachment; filename="squareup_sync_log_' . date('Y-m-d_H-i-s', time()) . '.txt"');
        header('Content-Transfer-Encoding: binary');

        $diff_id = $this->config->get('payment_squareup_last_sync_diff_id');

        if ($diff_id) {
            $num_rows = 0;
            $step = 1000;
            $page = 0;

            do {
                $sql = "SELECT * FROM `" . DB_PREFIX . "squareup_diff` WHERE diff_id='" . $this->db->escape($diff_id) . "' ORDER BY squareup_diff_id ASC LIMIT " . ($page * $step) . "," . $step;

                $result = $this->db->query($sql);

                $num_rows = $result->num_rows;

                if ($num_rows > 0) {
                    foreach ($result->rows as $row) {
                        var_export($row);
                        echo PHP_EOL;
                    }
                }

                $page++;
            } while ($num_rows > 0);
        }

        exit;
    }

    public function url_check_cron_status() {
        $json = array();

        $this->load->config('squareup/cron');

        $this->load->model('setting/setting');

        $this->load->language('extension/payment/squareup');

        $setting = $this->model_setting_setting->getSetting('payment_squareup');

        $config = new Config();

        foreach ($setting as $key => $value) {
            $config->set($key, $value);
        }

        $json['on_demand_status'] = (bool)$config->get('payment_squareup_cron_is_on_demand') || (bool)$config->get('payment_squareup_cron_is_running');

        $cron_status_text = $this->language->get('text_na');

        if ((bool)$config->get('payment_squareup_cron_is_running')) {
            $time = date('l, F jS, Y h:i:s A, e', $config->get('payment_squareup_cron_started_at'));

            $cron_status_text = sprintf($this->language->get('text_cron_status_text_running'), $time);
        } else {
            if ($config->has('payment_squareup_cron_ended_type')) {
                if ((bool)$config->get('payment_squareup_cron_is_on_demand')) {
                    $time = date('l, F jS, Y h:i:s A, e', time());

                    $cron_status_text = sprintf($this->language->get('text_cron_status_text_queued'), $time);
                } else {
                    $time = date('l, F jS, Y h:i:s A, e', $config->get('payment_squareup_cron_ended_at'));

                    switch ($config->get('payment_squareup_cron_ended_type')) {
                        case self::CRON_ENDED_FLAG_COMPLETE : $cron_status_text = sprintf($this->language->get('text_cron_status_text_completed'), $time);
                            break;
                        case self::CRON_ENDED_FLAG_TIMEOUT : $cron_status_text = sprintf($this->language->get('text_cron_status_text_timed_out'), $time);
                            break;
                        case self::CRON_ENDED_FLAG_ERROR : $cron_status_text = sprintf($this->language->get('text_cron_status_text_failed'), $time);
                            break;
                    }
                }
            }
        }

        $json['cron_status_text'] = $cron_status_text;

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function on_demand_cron() {
        $this->load->language('extension/payment/squareup');

        if (!$this->user->hasPermission('modify', 'extension/payment/squareup')) {
            $this->pushAlert(array(
                'type' => 'danger',
                'icon' => 'exclamation-circle',
                'text' => $this->language->get('error_permission')
            ));
        } else if ($this->config->get('payment_squareup_cron_is_running')) {
            $this->pushAlert(array(
                'type' => 'danger',
                'icon' => 'exclamation-circle',
                'text' => $this->language->get('error_task_running')
            ));
        } else {
            $this->load->model('setting/setting');

            $setting = $this->model_setting_setting->getSetting('payment_squareup');

            $setting['payment_squareup_cron_is_on_demand'] = '1';

            $this->model_setting_setting->editSetting('payment_squareup', $setting);
        }

        $this->response->redirect($this->url->link('extension/payment/squareup', 'user_token=' . $this->session->data['user_token'], true));
    }

    protected function getPostValue($key) {
        if (isset($this->request->post[$key])) {
            return $this->request->post[$key];
        }

        return null;
    }

    protected function showGeoZones() {
        $this->document->setTitle($this->language->get('heading_title') . ' - ' . $this->language->get('text_configure_geo_zone'));

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/payment/squareup', 'user_token=' . $this->session->data['user_token'], true)
        );

        if (isset($this->request->get['show_geo_zone'])) {
            $data['breadcrumbs'][] = array(
                'text' => $this->language->get('text_configure_geo_zone'),
                'href' => $this->url->link('extension/payment/squareup', 'user_token=' . $this->session->data['user_token'] . '&show_geo_zone=1', true)
            );

            $data['cancel'] = html_entity_decode($this->url->link('extension/payment/squareup', 'user_token=' . $this->session->data['user_token'], true), ENT_QUOTES, "UTF-8");
        } else {
            $data['cancel'] = html_entity_decode($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true), ENT_QUOTES, "UTF-8");
        }

        $data['confirm'] = html_entity_decode($this->url->link('extension/payment/squareup/geoZone', 'user_token=' . $this->session->data['user_token'] . '&action=confirm', true), ENT_QUOTES, "UTF-8");
        $data['skip'] = html_entity_decode($this->url->link('extension/payment/squareup/geoZone', 'user_token=' . $this->session->data['user_token'] . '&action=skip', true), ENT_QUOTES, "UTF-8");

        $this->load->model('localisation/country');

        $predefined_countries = $this->model_extension_payment_squareup->getPredefinedCountries();

        $country_info = $this->model_localisation_country->getCountry($this->config->get('config_country_id'));

        $data['store_country'] = !in_array($country_info['iso_code_3'], $predefined_countries);

        $data['text_zone_store_country'] = sprintf($this->language->get('text_zone_store_country'), $country_info['name']);

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payment/squareup_geo_zone', $data));
    }

    protected function inLocations($locations, $location_id) {
        foreach ($locations as $location) {
            if ($location['id'] == $location_id) {
                return true;
            }
        }

        return false;
    }

    protected function showForm() {
        $this->load->config('squareup/cron');

        if ($this->request->server['HTTPS']) {
            $server = HTTPS_SERVER;
        } else {
            $server = HTTP_SERVER;
        }

        $previous_setting = $this->model_setting_setting->getSetting('payment_squareup');

        try {
            unset($previous_setting['payment_squareup_sandbox_locations']);
            unset($previous_setting['payment_squareup_sandbox_location_id']);

            if ($this->config->get('payment_squareup_access_token')) {
                if (false === $locations = $this->squareup_api->verifyToken($this->config->get('payment_squareup_access_token'), $first_location_id)) {
                    unset($previous_setting['payment_squareup_status']);
                    unset($previous_setting['payment_squareup_merchant_id']);
                    unset($previous_setting['payment_squareup_initial_sync']);
                    unset($previous_setting['payment_squareup_merchant_name']);
                    unset($previous_setting['payment_squareup_access_token']);
                    unset($previous_setting['payment_squareup_access_token_expires']);
                    unset($previous_setting['payment_squareup_locations']);
                    unset($previous_setting['payment_squareup_location_id']);

                    $this->config->set('payment_squareup_merchant_id', null);
                    $this->config->set('payment_squareup_initial_sync', null);
                    $this->config->set('payment_squareup_status', 0);
                    $this->config->set('payment_squareup_apple_pay_registered', 0);
                } else {
                    $previous_setting['payment_squareup_locations'] = $locations;

                    if (empty($previous_setting['payment_squareup_location_id']) || (!empty($first_location_id) && !$this->inLocations($locations, $previous_setting['payment_squareup_location_id']))) {
                        $previous_setting['payment_squareup_location_id'] = $first_location_id;
                    }

                    if (!$this->config->get('payment_squareup_apple_pay_registered')) {
                        if (null !== $domain = $this->model_extension_payment_squareup->setupApplePayDomainVerificationFile()) {
                            $result = $this->squareup_api->registerApplePayDomain($domain);

                            if ($result == 'VERIFIED') {
                                $previous_setting['payment_squareup_apple_pay_registered'] = 1;
                            }
                        }
                    }

                    $previous_setting['payment_squareup_merchant_name'] = $this->squareup_api->getMerchantName();
                }
            }

            $this->model_setting_setting->editSetting('payment_squareup', $previous_setting);
        } catch (\Squareup\Exception\Api $e) {
            $this->pushAlert(array(
                'type' => 'danger',
                'icon' => 'exclamation-circle',
                'text' => sprintf($this->language->get('text_location_error'), $e->getMessage())
            ));
        }

        $previous_config = new Config();

        foreach ($previous_setting as $key => $value) {
            $previous_config->set($key, $value);
        }

        if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validate()) {
            if (isset($this->request->post['payment_squareup_initial_sync_type'])) {
                $type = $this->request->post['payment_squareup_initial_sync_type'];

                if (in_array($type, array('1', '2'))) {
                    $this->request->post['payment_squareup_sync_source'] = 'opencart';
                } else {
                    $this->request->post['payment_squareup_sync_source'] = 'square';
                }
            }

            $previous_currency = $this->model_extension_payment_squareup->getLocationCurrency($previous_config->get('payment_squareup_locations'), $previous_config->get('payment_squareup_location_id'));

            if ($previous_currency != $this->config->get('config_currency') && $this->request->post['payment_squareup_sync_source'] != 'none') {
                $this->pushAlert(array(
                    'type' => 'danger',
                    'icon' => 'exclamation-circle',
                    'text' => sprintf($this->language->get('text_currency_different'), $this->config->get('config_currency'), $previous_currency)
                ));

                $this->request->post['payment_squareup_sync_source'] = 'none';
            }

            $new_settings = array_merge($previous_setting, $this->request->post);

            if (isset($new_settings['payment_squareup_location_id'])) {
                try {
                    $this->squareup_api->updateWebhookPermissions($new_settings['payment_squareup_location_id'], array(
                        'INVENTORY_UPDATED'
                    ));
                } catch (\Squareup\Exception\Api $e) {
                    $this->pushAlert(array(
                        'type' => 'danger',
                        'icon' => 'exclamation-circle',
                        'text' => sprintf($this->language->get('text_webhook_error'), $e->getMessage())
                    ));
                }
            }

            $this->model_setting_setting->editSetting('payment_squareup', $new_settings);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('extension/payment/squareup', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
        } else {
            if (!$previous_config->get('payment_squareup_cron_acknowledge')) {
                $this->pushAlert(array(
                    'type' => 'warning',
                    'icon' => 'exclamation-circle',
                    'text' => $this->language->get('text_warning_cron')
                ));
            }
        }

        $this->document->setTitle($this->language->get('heading_title'));

        $data['error_status']                       = $this->getValidationError('status');
        $data['error_display_name']                 = $this->getValidationError('display_name');
        $data['error_client_id']                    = $this->getValidationError('client_id');
        $data['error_client_secret']                = $this->getValidationError('client_secret');
        $data['error_delay_capture']                = $this->getValidationError('delay_capture');
        $data['error_location']                     = $this->getValidationError('location');
        $data['error_cron_email']                   = $this->getValidationError('cron_email');
        $data['error_cron_acknowledge']             = $this->getValidationError('cron_acknowledge');
        $data['error_status_authorized']            = $this->getValidationError('status_authorized');
        $data['error_status_captured']              = $this->getValidationError('status_captured');
        $data['error_status_voided']                = $this->getValidationError('status_voided');
        $data['error_status_failed']                = $this->getValidationError('status_failed');
        $data['error_status_partially_refunded']    = $this->getValidationError('status_partially_refunded');
        $data['error_status_fully_refunded']        = $this->getValidationError('status_fully_refunded');
        $data['error_cron_standard_period']                 = $this->getValidationError('cron_standard_period');

        $data['order_status_settings_hidden'] =
            (bool)$this->getSettingValue('payment_squareup_status_authorized') &&
            (bool)$this->getSettingValue('payment_squareup_status_captured') &&
            (bool)$this->getSettingValue('payment_squareup_status_voided') &&
            (bool)$this->getSettingValue('payment_squareup_status_failed') &&
            (bool)$this->getSettingValue('payment_squareup_status_partially_refunded') &&
            (bool)$this->getSettingValue('payment_squareup_status_fully_refunded');

        $data['payment_squareup_status']                    = $this->getSettingValue('payment_squareup_status');
        $data['payment_squareup_status_authorized']         = $this->getSettingValue('payment_squareup_status_authorized', $this->model_extension_payment_squareup->inferOrderStatusId('processing'));
        $data['payment_squareup_status_captured']           = $this->getSettingValue('payment_squareup_status_captured', $this->model_extension_payment_squareup->inferOrderStatusId('processed'));
        $data['payment_squareup_status_voided']             = $this->getSettingValue('payment_squareup_status_voided', $this->model_extension_payment_squareup->inferOrderStatusId('void'));
        $data['payment_squareup_status_failed']             = $this->getSettingValue('payment_squareup_status_failed', $this->model_extension_payment_squareup->inferOrderStatusId('fail'));
        $data['payment_squareup_status_partially_refunded'] = $this->getSettingValue('payment_squareup_status_partially_refunded', $this->model_extension_payment_squareup->inferOrderStatusId('refund'));
        $data['payment_squareup_status_fully_refunded']     = $this->getSettingValue('payment_squareup_status_fully_refunded', $this->model_extension_payment_squareup->inferOrderStatusId('refund'));

        $data['payment_squareup_display_name']              = $this->getSettingValue('payment_squareup_display_name');
        $data['payment_squareup_client_id']                 = $this->getSettingValue('payment_squareup_client_id');
        $data['payment_squareup_client_secret']             = $this->getSettingValue('payment_squareup_client_secret');
        $data['payment_squareup_webhook_signature']         = $this->getSettingValue('payment_squareup_webhook_signature');
        $data['payment_squareup_debug']                     = $this->getSettingValue('payment_squareup_debug');
        $data['payment_squareup_guest']                     = $this->getSettingValue('payment_squareup_guest');
        $data['payment_squareup_sort_order']                = $this->getSettingValue('payment_squareup_sort_order');
        $data['payment_squareup_total']                     = $this->getSettingValue('payment_squareup_total', '1.00');
        $data['payment_squareup_geo_zone_id']               = $this->getSettingValue('payment_squareup_geo_zone_id');
        $data['payment_squareup_locations']                 = $this->getSettingValue('payment_squareup_locations', $previous_config->get('payment_squareup_locations'));
        $data['payment_squareup_location_id']               = $this->getSettingValue('payment_squareup_location_id');
        $data['payment_squareup_delay_capture']             = $this->getSettingValue('payment_squareup_delay_capture');
        $data['payment_squareup_recurring_status']          = $this->getSettingValue('payment_squareup_recurring_status');
        $data['payment_squareup_cron_email_status']         = $this->getSettingValue('payment_squareup_cron_email_status');
        $data['payment_squareup_cron_email']                = $this->getSettingValue('payment_squareup_cron_email', $this->config->get('config_email'));
        $data['payment_squareup_cron_token']                = $this->getSettingValue('payment_squareup_cron_token');
        $data['payment_squareup_cron_acknowledge']          = $this->getSettingValue('payment_squareup_cron_acknowledge', null, true);
        $data['payment_squareup_notify_recurring_success']  = $this->getSettingValue('payment_squareup_notify_recurring_success');
        $data['payment_squareup_notify_recurring_fail']     = $this->getSettingValue('payment_squareup_notify_recurring_fail');
        $data['payment_squareup_merchant_id']               = $this->getSettingValue('payment_squareup_merchant_id', $previous_config->get('payment_squareup_merchant_id'));
        $data['payment_squareup_merchant_name']             = $this->getSettingValue('payment_squareup_merchant_name', $previous_config->get('payment_squareup_merchant_name'));
        $data['payment_squareup_admin_url_transaction']     = $this->getSettingValue('payment_squareup_admin_url_transaction', $this->model_extension_payment_squareup->getAdminURLTransaction());
        $data['payment_squareup_admin_url_settings']        = $this->getSettingValue('payment_squareup_admin_url_settings', $this->model_extension_payment_squareup->getAdminURLSettings());
        $data['payment_squareup_sync_source']               = $this->getSettingValue('payment_squareup_sync_source');
        $data['payment_squareup_icon_status']               = $this->getSettingValue('payment_squareup_icon_status', '1');
        $data['payment_squareup_accepted_cards_status']     = $this->getSettingValue('payment_squareup_accepted_cards_status');
        $data['payment_squareup_inventory_sync']            = $this->getSettingValue('payment_squareup_inventory_sync');
        $data['payment_squareup_cron_standard_period']      = $this->getSettingValue('payment_squareup_cron_standard_period', (int)$this->config->get('squareup_cron_standard_period') / 60);
        $data['payment_squareup_ad_hoc_sync']              = $this->getSettingValue('payment_squareup_ad_hoc_sync', '1');

        $data['max_standard_period'] = (int)$this->config->get('squareup_cron_standard_period') / 60;

        // Deprecated because we do not have the sync direction from Square to OpenCart.
        $data['initial_sync_not_performed']                 = false; //!$this->config->get('payment_squareup_initial_sync');

        if ($previous_config->get('payment_squareup_access_token') && $previous_config->get('payment_squareup_access_token_expires')) {
            $expiration_time = date_create_from_format('Y-m-d\TH:i:s\Z', $previous_config->get('payment_squareup_access_token_expires'));
            $now = date_create();

            $delta = $expiration_time->getTimestamp() - $now->getTimestamp();
            $expiration_date_formatted = $expiration_time->format('l, F jS, Y h:i:s A, e');

            if ($delta < 0) {
                $this->pushAlert(array(
                    'type' => 'danger',
                    'icon' => 'exclamation-circle',
                    'text' => sprintf($this->language->get('text_token_expired'), $this->url->link('extension/payment/squareup/refresh_token', 'user_token=' . $this->session->data['user_token'], true))
                ));
            } else if ($delta < (5 * 24 * 60 * 60)) { // token is valid, just about to expire
                $this->pushAlert(array(
                    'type' => 'warning',
                    'icon' => 'exclamation-circle',
                    'text' => sprintf($this->language->get('text_token_expiry_warning'), $expiration_date_formatted, $this->url->link('extension/payment/squareup/refresh_token', 'user_token=' . $this->session->data['user_token'], true))
                ));
            }

            $data['access_token_expires_time'] = $expiration_date_formatted;
        } else if ($previous_config->get('payment_squareup_client_id')) {
            $this->pushAlert(array(
                'type' => 'danger',
                'icon' => 'exclamation-circle',
                'text' => $this->language->get('text_token_revoked')
            ));

            $data['access_token_expires_time'] = $this->language->get('text_na');
        }

        $data['payment_squareup_redirect_uri'] = str_replace('&amp;', '&', $this->url->link('extension/payment/squareup/oauth_callback', '', true));
        $data['payment_squareup_refresh_link'] = $this->url->link('extension/payment/squareup/refresh_token', 'user_token=' . $this->session->data['user_token'], true);

        if (!$this->config->get('payment_squareup_status')) {
            $this->pushAlert(array(
                'type' => 'warning',
                'icon' => 'exclamation-circle',
                'text' => $this->language->get('text_extension_disabled'),
                'non_dismissable' => true
            ));
        }

        if (isset($this->error['warning'])) {
            $this->pushAlert(array(
                'type' => 'danger',
                'icon' => 'exclamation-circle',
                'text' => $this->error['warning']
            ));
        }

        // Insert success message from the session
        if (isset($this->session->data['success'])) {
            $this->pushAlert(array(
                'type' => 'success',
                'icon' => 'exclamation-circle',
                'text' => $this->session->data['success']
            ));

            unset($this->session->data['success']);
        }

        if ($this->request->server['HTTPS']) {
            // Push the SSL reminder alert
            $this->pushAlert(array(
                'type' => 'info',
                'icon' => 'lock',
                'text' => $this->language->get('text_notification_ssl')
            ));
        } else {
            // Push the SSL reminder alert
            $this->pushAlert(array(
                'type' => 'danger',
                'icon' => 'exclamation-circle',
                'text' => $this->language->get('error_no_ssl')
            ));
        }

        if ($this->config->get('payment_squareup_access_token')) {
            $this->pushAlert(array(
                'type' => 'info',
                'icon' => 'exclamation-circle',
                'text' => $this->language->get('text_enable_payment')
            ));
        }

        if ($this->config->get('payment_squareup_delay_capture')) {
            $this->pushAlert(array(
                'type' => 'warning',
                'icon' => 'exclamation-circle',
                'text' => $this->language->get('text_auth_voided_6_days')
            ));
        }

        if (!$this->squareup_api->getLocationCurrency(false)) {
            $current_location_currency = $this->model_extension_payment_squareup->getLocationCurrency($previous_config->get('payment_squareup_locations'), $previous_config->get('payment_squareup_location_id'));
            $url_edit_currencies = $this->url->link('localisation/currency', 'user_token=' . $this->session->data['user_token'], true);

            $this->pushAlert(array(
                'type' => 'danger',
                'icon' => 'exclamation-circle',
                'text' => sprintf($this->language->get('error_currency_unavailable'), $current_location_currency, $url_edit_currencies)
            ));
        }

        $tabs = array(
            'tab-transaction',
            'tab-setting',
            'tab-recurring',
            'tab-cron'
        );

        if (isset($this->request->get['tab']) && in_array($this->request->get['tab'], $tabs)) {
            $data['tab'] = $this->request->get['tab'];
        } else if ($this->error) {
            $data['tab'] = 'tab-setting';
        } else {
            $data['tab'] = $tabs[1];
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/payment/squareup', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['heading_title'] = $this->language->get('heading_title') . ' ' . $this->version;
        $data['action'] = html_entity_decode($this->url->link('extension/payment/squareup', 'user_token=' . $this->session->data['user_token'], true), ENT_QUOTES, "UTF-8");
        $data['cancel'] = html_entity_decode($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true), ENT_QUOTES, "UTF-8");
        $data['connect'] = html_entity_decode($this->url->link('extension/payment/squareup/connect', 'user_token=' . $this->session->data['user_token'], true), ENT_QUOTES, "UTF-8");

        // Deprecated - should be used when we implement a Catalog sync in the direction Square > OpenCart
        $data['setup_geo_zones'] = html_entity_decode($this->url->link('extension/payment/squareup', 'user_token=' . $this->session->data['user_token'] . '&show_geo_zone=1', true), ENT_QUOTES, "UTF-8");

        $data['on_demand_cron'] = html_entity_decode($this->url->link('extension/payment/squareup/on_demand_cron', 'user_token=' . $this->session->data['user_token'], true), ENT_QUOTES, "UTF-8");
        $data['url_list_transactions'] = html_entity_decode($this->url->link('extension/payment/squareup/transactions', 'user_token=' . $this->session->data['user_token'] . '&page={PAGE}', true), ENT_QUOTES, "UTF-8");
        $data['url_tax_rate'] = html_entity_decode($this->url->link('extension/payment/squareup/taxRate', 'user_token=' . $this->session->data['user_token'], true), ENT_QUOTES, "UTF-8");
        $data['url_sync_modal_options'] = html_entity_decode($this->url->link('extension/payment/squareup/syncModal', 'user_token=' . $this->session->data['user_token'], true), ENT_QUOTES, "UTF-8");
        $data['url_check_cron_status'] = html_entity_decode($this->url->link('extension/payment/squareup/url_check_cron_status', 'user_token=' . $this->session->data['user_token'], true), ENT_QUOTES, "UTF-8");
        $data['url_download_sync_log'] = html_entity_decode($this->url->link('extension/payment/squareup/download_sync_log', 'user_token=' . $this->session->data['user_token'], true), ENT_QUOTES, "UTF-8");

        $data['help'] = 'http://docs.isenselabs.com/square';
        $data['url_video_help'] = 'https://www.youtube.com/watch?v=4sSSKwA3KrM';
        $data['url_integration_settings_help'] = 'http://docs.isenselabs.com/square/integration_settings';

        $this->load->model('localisation/language');
        $data['languages'] = array();
        foreach ($this->model_localisation_language->getLanguages() as $language) {
            $data['languages'][] = array(
                'language_id' => $language['language_id'],
                'name' => $language['name'] . ($language['code'] == $this->config->get('config_language') ? $this->language->get('text_default') : ''),
                'image' => 'language/' . $language['code'] . '/'. $language['code'] . '.png'
            );
        }

        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        $this->load->model('localisation/geo_zone');
        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

        // Deprecated - should be used when we implement a Catalog sync in the direction Square > OpenCart
        $data['can_modify_geo_zones'] = false; // $this->user->hasPermission('modify', 'localisation/geo_zone');

        $data['payment_squareup_cron_command'] = 'export CUSTOM_SERVER_NAME=' . parse_url($server, PHP_URL_HOST) . '; export CUSTOM_SERVER_PORT=443; export SQUARE_CRON=1; export SQUARE_ROUTE=extension/payment/squareup/cron; ' . PHP_BINDIR . '/php -d memory_limit=512M -d session.save_path=' . session_save_path() . ' ' . DIR_SYSTEM . 'library/squareup/cron.php > /dev/null 2> /dev/null';

        if (!$this->config->get('payment_squareup_cron_token')) {
            $data['payment_squareup_cron_token'] = md5(mt_rand());
        }

        $data['payment_squareup_cron_url'] = 'https://' . parse_url($server, PHP_URL_HOST) . dirname(parse_url($server, PHP_URL_PATH)) . '/index.php?route=extension/payment/squareup/cron&cron_token={CRON_TOKEN}';

        $data['payment_squareup_webhook_url'] = 'https://' . parse_url($server, PHP_URL_HOST) . dirname(parse_url($server, PHP_URL_PATH)) . '/index.php?route=extension/payment/squareup/webhook';

        $data['catalog'] = $this->request->server['HTTPS'] ? HTTPS_CATALOG : HTTP_CATALOG;

        // API login
        $this->load->model('user/api');

        $api_info = $this->model_user_api->getApi($this->config->get('config_api_id'));

        if ($api_info && $this->user->hasPermission('modify', 'sale/order')) {
            $session = new Session($this->config->get('session_engine'), $this->registry);

            $session->start();

            $this->model_user_api->deleteApiSessionBySessonId($session->getId());

            $this->model_user_api->addApiSession($api_info['api_id'], $session->getId(), $this->request->server['REMOTE_ADDR']);

            $session->data['api_id'] = $api_info['api_id'];

            $data['api_token'] = $session->getId();
        } else {
            $data['api_token'] = '';
        }

        // Tax rate popup
        $new_tax_rates = $this->model_extension_payment_squareup->getNewTaxRates();
        $data['new_tax_rates'] = $new_tax_rates;
        $data['has_new_tax_rates'] = count($new_tax_rates) > 0;

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $data['alerts'] = $this->pullAlerts();

        $this->clearAlerts();

        $this->response->setOutput($this->load->view('extension/payment/squareup', $data));
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/payment/squareup')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (empty($this->request->post['payment_squareup_status'])) {
            return true;
        }

        if ($this->config->get('payment_squareup_merchant_id') && !$this->config->get('payment_squareup_locations')) {
            $this->error['warning'] = $this->language->get('text_no_appropriate_locations_warning');
        }

        if ($this->config->get('payment_squareup_locations') && isset($this->request->post['payment_squareup_location_id']) && !in_array($this->request->post['payment_squareup_location_id'], array_map(function($location) {
            return $location['id'];
        }, $this->config->get('payment_squareup_locations')))) {
            $this->error['location'] = $this->language->get('error_no_location_selected');
        }

        if (!empty($this->request->post['payment_squareup_cron_email_status'])) {
            if (!filter_var($this->request->post['payment_squareup_cron_email'], FILTER_VALIDATE_EMAIL)) {
                $this->error['cron_email'] = $this->language->get('error_invalid_email');
            }
        }

        if (empty($this->request->post['payment_squareup_cron_acknowledge'])) {
            $this->error['cron_acknowledge'] = $this->language->get('error_cron_acknowledge');
        }

        if (empty($this->request->post['payment_squareup_status_authorized'])) {
            $this->error['status_authorized'] = $this->language->get('error_status_not_set');
        }

        if (empty($this->request->post['payment_squareup_status_captured'])) {
            $this->error['status_captured'] = $this->language->get('error_status_not_set');
        }

        if (empty($this->request->post['payment_squareup_status_voided'])) {
            $this->error['status_voided'] = $this->language->get('error_status_not_set');
        }

        if (empty($this->request->post['payment_squareup_status_failed'])) {
            $this->error['status_failed'] = $this->language->get('error_status_not_set');
        }

        if (empty($this->request->post['payment_squareup_status_partially_refunded'])) {
            $this->error['status_partially_refunded'] = $this->language->get('error_status_not_set');
        }

        if (empty($this->request->post['payment_squareup_status_fully_refunded'])) {
            $this->error['status_fully_refunded'] = $this->language->get('error_status_not_set');
        }

        $this->load->config('squareup/cron');

        $max_period = $this->config->get('squareup_cron_standard_period') / 60;

        if (empty($this->request->post['payment_squareup_cron_standard_period']) || (int)$this->request->post['payment_squareup_cron_standard_period'] < 180 || (int)$this->request->post['payment_squareup_cron_standard_period'] > $max_period) {
            $this->error['cron_standard_period'] = sprintf($this->language->get('error_cron_standard_period'), 180, $max_period);
        }

        if ($this->error && empty($this->error['warning'])) {
            $this->error['warning'] = $this->language->get('error_form');
        }

        return !$this->error;
    }

    protected function transactionAction($callback) {
        $this->load->language('extension/payment/squareup');

        $this->load->model('extension/payment/squareup');

        $this->load->library('squareup');

        $json = array();

        if (!$this->user->hasPermission('modify', 'extension/payment/squareup')) {
            $json['error'] = $this->language->get('error_permission');
        }

        if (isset($this->request->get['squareup_transaction_id'])) {
            $squareup_transaction_id = $this->request->get['squareup_transaction_id'];
        } else {
            $squareup_transaction_id = 0;
        }

        $transaction_info = $this->model_extension_payment_squareup->getTransaction($squareup_transaction_id);

        if (empty($transaction_info)) {
            $json['error'] = $this->language->get('error_transaction_missing');
        } else if (empty($json['error'])) {
            try {
                $callback($transaction_info, $json);
            } catch (\Squareup\Exception\Api $e) {
                $json['error'] = $e->getMessage();
            }
        }

        if (isset($this->request->get['preserve_alert'])) {
            if (!empty($json['error'])) {
                $this->pushAlert(array(
                    'type' => 'danger',
                    'icon' => 'exclamation-circle',
                    'text' => $json['error']
                ));
            }

            if (!empty($json['success'])) {
                $this->pushAlert(array(
                    'type' => 'success',
                    'icon' => 'exclamation-circle',
                    'text' => $json['success']
                ));
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    protected function pushAlert($alert) {
        $this->session->data['payment_squareup_alerts'][] = $alert;
    }

    protected function pullAlerts() {
        if (isset($this->session->data['payment_squareup_alerts'])) {
            return $this->session->data['payment_squareup_alerts'];
        } else {
            return array();
        }
    }

    protected function clearAlerts() {
        unset($this->session->data['payment_squareup_alerts']);
    }

    protected function getSettingValue($key, $default = null, $checkbox = false) {
        if ($checkbox) {
            if ($this->request->server['REQUEST_METHOD'] == 'POST' && !isset($this->request->post[$key])) {
                return $default;
            } else {
                return $this->config->get($key);
            }
        }

        if (isset($this->request->post[$key])) {
            return $this->request->post[$key];
        } else if ($this->config->has($key)) {
            return $this->config->get($key);
        } else {
            return $default;
        }
    }

    protected function getValidationError($key) {
        if (isset($this->error[$key])) {
            return $this->error[$key];
        } else {
            return '';
        }
    }
}
