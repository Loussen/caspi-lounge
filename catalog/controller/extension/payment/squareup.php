<?php

class ControllerExtensionPaymentSquareup extends Controller {
    public function index() {
        $this->load->language('extension/payment/squareup');

        $this->load->model('extension/payment/squareup');

        $this->load->library('squareup');

        $data['action'] = $this->url->link('extension/payment/squareup/checkout', '', true);
        $data['squareup_js_api'] = Squareup::PAYMENT_FORM_URL;

        if (!empty($this->session->data['payment_address']['postcode'])) {
            $data['payment_zip'] = $this->session->data['payment_address']['postcode'];
        } else {
            $data['payment_zip'] = '';
        }

        $data['app_id'] = $this->config->get('payment_squareup_client_id');

        $data['location_id'] = $this->config->get('payment_squareup_location_id');

        $data['cards'] = array();
        $data['has_selected_card'] = false;
        $data['icon_status'] = (bool)$this->config->get('payment_squareup_icon_status');
        $data['accepted_cards_status'] = (bool)$this->config->get('payment_squareup_accepted_cards_status');

        $apple_pay_line_items = $this->model_extension_payment_squareup->getApplePayLineItems($this->session->data['order_id']);
        $has_applepay_line_items = !is_null($apple_pay_line_items);

        $data['has_applepay_line_items'] = $has_applepay_line_items;
        $data['apple_pay_line_items'] = $has_applepay_line_items ? json_encode($apple_pay_line_items) : '{}';

        if ($this->customer->isLogged()) {
            $data['is_logged'] = true;

            $this->load->model('extension/credit_card/squareup');

            $cards = $this->model_extension_credit_card_squareup->getCards($this->customer->getId());

            $square_customer = $this->model_extension_credit_card_squareup->getCustomer($this->customer->getId());

            foreach ($cards as $card) {
                $selected = $card['squareup_token_id'] == $square_customer['squareup_token_id'];

                if ($selected) {
                    $data['has_selected_card'] = true;
                }

                $data['cards'][] = array(
                    'id' => $card['squareup_token_id'],
                    'selected' => $selected,
                    'text' => sprintf($this->language->get('text_card_ends_in'), $card['brand'], $card['ends_in'])
                );
            }
        } else {
            $data['is_logged'] = false;
        }

        $data['error_currency'] = '';
        $data['warning_currency'] = '';

        $location_currency = $this->squareup_api->getLocationCurrency(null);

        if (is_null($location_currency)) {
            $data['error_currency'] = $this->language->get('error_currency_invalid');
        } else {
            if ($this->session->data['currency'] != $location_currency) {
                $rate = round($this->currency->getValue($location_currency) / $this->currency->getValue($this->session->data['currency']), 8);

                $this->load->model('checkout/order');

                $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

                $amount = $this->currency->format($this->currency->convert($order_info['total'], $this->config->get('config_currency'), $location_currency), $location_currency, 1, true);

                $data['warning_currency'] = sprintf($this->language->get('warning_currency_converted'), $location_currency, $rate, $amount);
            }
        }

        // Workaround:
        // There is an "unset($this->session->data['shipping_address']);" at the beginning of ControllerCheckoutConfirm::index()
        // This unset is messing up the taxes which is why we need to re-create $this->session->data['shipping_address'] like so:
        if (!$this->cart->hasShipping() && empty($this->session->data['shipping_address']) && $this->customer->isLogged() && $this->config->get('config_tax_customer') == 'shipping') {

            $this->load->model('account/address');

            $this->session->data['shipping_address'] = $this->model_account_address->getAddress($this->customer->getAddressId());
        }

        return $this->load->view('extension/payment/squareup', $data);
    }

    public function checkout() {
        $this->load->language('extension/payment/squareup');

        $this->load->model('extension/payment/squareup');
        $this->load->model('extension/credit_card/squareup');
        $this->load->model('checkout/order');
        $this->load->model('localisation/country');

        $this->model_extension_payment_squareup->setExceptionHandler();

        $this->load->library('squareup');

        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        $shipping_country_info = $this->model_localisation_country->getCountry($order_info['shipping_country_id']);

        $billing_country_info = $this->model_localisation_country->getCountry($order_info['payment_country_id']);

        if (!empty($billing_country_info)) {
            $billing_address = array(
                'first_name' => $order_info['payment_firstname'],
                'last_name' => $order_info['payment_lastname'],
                'address_line_1' => $order_info['payment_address_1'],
                'address_line_2' => $order_info['payment_address_2'],
                'locality' => $order_info['payment_city'],
                'sublocality' => $order_info['payment_zone'],
                'postal_code' => $order_info['payment_postcode'],
                'country' => $billing_country_info['iso_code_2'],
                'organization' => $order_info['payment_company']
            );
        } else {
            $billing_address = array();
        }

        if (!empty($shipping_country_info)) {
            $shipping_address = array(
                'first_name' => $order_info['shipping_firstname'],
                'last_name' => $order_info['shipping_lastname'],
                'address_line_1' => $order_info['shipping_address_1'],
                'address_line_2' => $order_info['shipping_address_2'],
                'locality' => $order_info['shipping_city'],
                'sublocality' => $order_info['shipping_zone'],
                'postal_code' => $order_info['shipping_postcode'],
                'country' => $shipping_country_info['iso_code_2'],
                'organization' => $order_info['shipping_company']
            );
        } else {
            $shipping_address = array();
        }

        $json = array();

        try {
            // Ensure we have registered the customer with Square
            $square_customer = $this->model_extension_credit_card_squareup->getCustomer($this->customer->getId());

            if (!$square_customer && $this->customer->isLogged()) {
                $square_customer = $this->squareup_api->addLoggedInCustomer();

                $this->model_extension_credit_card_squareup->addCustomer($square_customer);
            }

            $use_saved = false;
            $square_card_id = null;

            // Save the card only if we have paid without a digital wallet...
            if (empty($this->request->post['squareup_digital_wallet_type']) || $this->request->post['squareup_digital_wallet_type'] == 'NONE') {
                // check if user is logged in and wanted to save this card
                if ($this->customer->isLogged() && !empty($this->request->post['squareup_select_card'])) {
                    $card_verified = $this->model_extension_credit_card_squareup->verifyCardCustomer($this->request->post['squareup_select_card'], $this->customer->getId());

                    if (!$card_verified) {
                        throw new \Squareup\Exception\Api($this->registry, $this->language->get('error_card_invalid'));
                    }

                    $card = $this->model_extension_credit_card_squareup->getCard($this->request->post['squareup_select_card']);

                    $use_saved = true;
                    $square_card_id = $card['token'];
                } else if ($this->customer->isLogged() && isset($this->request->post['squareup_save_card'])) {
                    // Save the card
                    $card_data = array(
                        'card_nonce' => $this->request->post['squareup_nonce'],
                        'billing_address' => $billing_address,
                        'cardholder_name' => $order_info['payment_firstname'] . ' ' . $order_info['payment_lastname']
                    );

                    $square_card = $this->squareup_api->addCard($square_customer['square_customer_id'], $card_data);

                    if (!$this->model_extension_credit_card_squareup->cardExists($this->customer->getId(), $square_card)) {
                        $this->model_extension_credit_card_squareup->addCard($this->customer->getId(), $square_card);
                    }

                    $use_saved = true;
                    $square_card_id = $square_card['id'];
                }
            }

            $location_currency = $this->squareup_api->getLocationCurrency(null);

            if (is_null($location_currency)) {
                throw new \Exception($this->language->get('error_currency_invalid'));
            }

            $order_total = $this->squareup_api->roundPrice($this->currency->convert($order_info['total'], $this->config->get('config_currency'), $location_currency), $location_currency);

            $order_total_amount = $this->squareup_api->lowestDenomination($order_total, $location_currency);

            // If the Square order throws an error or if the order price is different than the OC price, ignore it and submit the transaction with no items
            $square_order_id = null;
            $square_order_errors = array();

            try {
                $source = $this->config->get('payment_squareup_sync_source');
                $re_sync_items = false;

                try {
                    $square_order = $this->squareup_api->createOrder($order_info['order_id'], 0, true);

                    $price_diff = $square_order['total_money']['amount'] - $order_total_amount;

                    $re_sync_items = $price_diff != 0;
                } catch (\Squareup\Exception\Api $e) {
                    if (empty($source) || $source == 'none' || $e->isCurlError() || $e->isAccessTokenRevoked() || $e->isAccessTokenExpired()) {
                        // Throw the exception in case no sync will reoccur, or if the access token has expired/been revoked, or if there is a CURL error
                        throw $e;
                    }

                    $re_sync_items = true;
                }

                if ($re_sync_items) {
                    if ($source != 'none') {
                        if (!$this->config->get('payment_squareup_cron_is_running')) {
                            $this->model_extension_payment_squareup->setBeginSyncFlags();

                            try {
                                $this->squareup_diff->syncOrderItems($source, $order_info['order_id']);
                            } catch (\Squareup\Exception\Api $e) {
                                if ($e->isCurlError() || $e->isAccessTokenRevoked() || $e->isAccessTokenExpired()) {
                                    throw $e;
                                }
                            }

                            $this->model_extension_payment_squareup->setEndSyncFlags();
                        }

                        $square_order = $this->squareup_api->createOrder($order_info['order_id']);

                        $price_diff = $square_order['total_money']['amount'] - $order_total_amount;
                    }
                }

                if ($price_diff != 0) {
                    $square_order = $this->squareup_api->createOrder($order_info['order_id'], $price_diff);
                }

                if ($square_order['total_money']['amount'] == $order_total_amount) {
                    $square_order_id = $square_order['id'];
                }
            } catch (\Squareup\Exception\Api $e) {
                if ($e->isCurlError() || $e->isAccessTokenRevoked() || $e->isAccessTokenExpired()) {
                    throw $e;
                }

                $square_order_errors = $e->getMessages();
            } catch (\Exception $e) {
                throw $e;
            }

            // Prepare Transaction
            $transaction_data = array(
                'note' => sprintf($this->language->get('text_order_id'), $order_info['order_id']),
                'reference_id' => $order_info['order_id'],
                'idempotency_key' => uniqid(),
                'amount_money' => array(
                    'amount' => $order_total_amount,
                    'currency' => $location_currency
                ),
                'billing_address' => $billing_address,
                'buyer_email_address' => $order_info['email'],
                'delay_capture' => !$this->cart->hasRecurringProducts() && $this->config->get('payment_squareup_delay_capture'),
                'integration_id' => Squareup::SQUARE_INTEGRATION_ID
            );

            if (!is_null($square_order_id)) {
                $transaction_data['order_id'] = $square_order_id;
            }

            if (!empty($shipping_address)) {
                $transaction_data['shipping_address'] = $shipping_address;
            }

            if ($use_saved) {
                $transaction_data['customer_card_id'] = $square_card_id;
                $transaction_data['customer_id'] = $square_customer['square_customer_id'];

                $square_token_id = $this->model_extension_credit_card_squareup->getTokenIdByCustomerAndToken($this->customer->getId(), $square_card_id);
                $this->model_extension_credit_card_squareup->updateDefaultCustomerToken($this->customer->getId(), $square_token_id);
            } else {
                $transaction_data['card_nonce'] = $this->request->post['squareup_nonce'];

                if (!$this->customer->isLogged() && $this->config->get('payment_squareup_guest') && !empty($this->session->data['guest']['firstname']) && !empty($this->session->data['guest']['lastname']) && !empty($this->session->data['guest']['email'])) {
                    $guest_customer = $this->squareup_api->addCustomer($this->session->data['guest']['firstname'], $this->session->data['guest']['lastname'], $this->session->data['guest']['email']);

                    $transaction_data['customer_id'] = $guest_customer['id'];
                }
            }

            $transaction = $this->squareup_api->addTransaction($transaction_data);

            if (isset($this->request->server['HTTP_USER_AGENT'])) {
                $user_agent = $this->request->server['HTTP_USER_AGENT'];
            } else {
                $user_agent = '';
            }

            if (isset($this->request->server['REMOTE_ADDR'])) {
                $ip = $this->request->server['REMOTE_ADDR'];
            } else {
                $ip = '';
            }

            $this->model_extension_payment_squareup->addTransaction($transaction, $this->config->get('payment_squareup_merchant_id'), $billing_address, $this->session->data['order_id'], $user_agent, $ip);

            if (!empty($transaction['tenders'][0]['card_details']['status'])) {
                $transaction_status = strtolower($transaction['tenders'][0]['card_details']['status']);
            } else {
                $transaction_status = '';
            }

            $this->session->data['squareup_is_capture'] = $transaction_status == 'captured';

            $this->model_extension_payment_squareup->squareOrderErrorEmail($square_order_errors, $order_info['order_id']);

            $order_status_id = $this->config->get('payment_squareup_status_' . $transaction_status);

            if ($order_status_id) {
                if ($this->cart->hasRecurringProducts() && $transaction_status == 'captured') {
                    foreach ($this->cart->getRecurringProducts() as $item) {
                        if ($item['recurring']['trial']) {
                            $trial_price = $this->tax->calculate($item['recurring']['trial_price'] * $item['quantity'], $item['tax_class_id']);
                            $trial_amt = $this->currency->format($trial_price, $this->session->data['currency']);
                            $trial_text =  sprintf($this->language->get('text_trial'), $trial_amt, $item['recurring']['trial_cycle'], $item['recurring']['trial_frequency'], $item['recurring']['trial_duration']);

                            $item['recurring']['trial_price'] = $trial_price;
                        } else {
                            $trial_text = '';
                        }

                        $recurring_price = $this->tax->calculate($item['recurring']['price'] * $item['quantity'], $item['tax_class_id']);
                        $recurring_amt = $this->currency->format($recurring_price, $this->session->data['currency']);
                        $recurring_description = $trial_text . sprintf($this->language->get('text_recurring'), $recurring_amt, $item['recurring']['cycle'], $item['recurring']['frequency']);

                        $item['recurring']['price'] = $recurring_price;

                        if ($item['recurring']['duration'] > 0) {
                            $recurring_description .= sprintf($this->language->get('text_length'), $item['recurring']['duration']);
                        }

                        if (!$item['recurring']['trial']) {
                            // We need to override this value for the proper calculation in updateRecurringExpired
                            $item['recurring']['trial_duration'] = 0;
                        }


                        $this->model_extension_payment_squareup->createRecurring($item, $this->session->data['order_id'], $recurring_description, $transaction['id']);
                    }
                }

                $order_status_comment = $this->language->get('squareup_status_comment_' . $transaction_status);

                // The payment went through. Amend the error handler to one which only logs errors
                $this->model_extension_payment_squareup->setErrorLogHandler();

                $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $order_status_id, $order_status_comment, true);
            }

            $json['redirect'] = $this->url->link('checkout/success', '', true);
        } catch (\Squareup\Exception\Api $e) {
            if ($e->isCurlError()) {
                $json['error'] = $this->language->get('text_token_issue_customer_error');
            } else if ($e->isAccessTokenRevoked()) {
                // Send reminder e-mail to store admin to refresh the token
                $this->model_extension_payment_squareup->tokenRevokedEmail();

                $json['error'] = $this->language->get('text_token_issue_customer_error');
            } else if ($e->isAccessTokenExpired()) {
                // Send reminder e-mail to store admin to refresh the token
                $this->model_extension_payment_squareup->tokenExpiredEmail();

                $json['error'] = $this->language->get('text_token_issue_customer_error');
            } else {
                $json['error'] = $e->getMessage();
            }
        } catch (\Exception $e) {
            $json['error'] = $e->getMessage();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function info() {
        if (!$this->validate()) {
            $this->response->redirect($this->url->link($this->config->get('action_default'), '', true));
        } else {
            $append_token = !empty($this->session->data['user_token']) ? '&user_token=' . $this->session->data['user_token'] : '';

            if (isset($this->request->get['squareup_settings'])) {
                $this->response->redirect(sprintf($this->config->get('payment_squareup_admin_url_settings'), $append_token));
            } else {
                $this->response->redirect(sprintf($this->config->get('payment_squareup_admin_url_transaction'), (int)$this->request->get['squareup_transaction_id'], $append_token));
            }
        }
    }

    public function cron() {
        $this->load->language('extension/payment/squareup');

        $this->load->model('extension/payment/squareup');

        $this->model_extension_payment_squareup->setDetailedExceptionHandler();

        $this->load->library('squareup');

        $flag = ModelExtensionPaymentSquareup::CRON_ENDED_FLAG_INVALID;

        register_shutdown_function(function() use (&$flag) {
            $this->model_extension_payment_squareup->setEndCronFlags($flag);
        });

        if ($this->model_extension_payment_squareup->validateCRON()) {
            try {
                if ($this->config->get('payment_squareup_cron_is_running') && $this->model_extension_payment_squareup->cronHasTimedOut()) {
                    throw new \Squareup\Exception\Timeout("The CRON task has timed out.");
                } else if (!$this->config->get('payment_squareup_cron_is_running')) {
                    if ($this->config->get('payment_squareup_cron_is_on_demand')) {
                        $this->model_extension_payment_squareup->setBeginCronFlags(ModelExtensionPaymentSquareup::CRON_STARTED_FLAG_ON_DEMAND);

                        if ($this->config->get('payment_squareup_debug')) {
                            $this->log->write("SQUAREUP SYNC: ON-DEMAND");
                        }

                        // On-demand sync - after pressing the button in the admin panel
                        $this->on_demand_sync();

                        $flag = ModelExtensionPaymentSquareup::CRON_ENDED_FLAG_COMPLETE;
                    } else if ($this->model_extension_payment_squareup->oneCronStandardPeriodHasPassed()) {
                        $this->model_extension_payment_squareup->setBeginCronFlags(ModelExtensionPaymentSquareup::CRON_STARTED_FLAG_STANDARD);

                        if ($this->config->get('payment_squareup_debug')) {
                            $this->log->write("SQUAREUP SYNC: STANDARD");
                        }

                        // Standard periodic sync, ran every cron stantard period (set from the admin panel)
                        $this->standard_sync();

                        $flag = ModelExtensionPaymentSquareup::CRON_ENDED_FLAG_COMPLETE;
                    } else if ($this->model_extension_payment_squareup->oneCronInventoryPeriodHasPassed()) {
                        $this->model_extension_payment_squareup->setBeginCronFlags(ModelExtensionPaymentSquareup::CRON_STARTED_FLAG_INVENTORY);

                        if ($this->config->get('payment_squareup_debug')) {
                            $this->log->write("SQUAREUP SYNC: INVENTORY");
                        }

                        // Inventory periodic sync, ran every cron inventory period
                        $this->inventory_sync();

                        $flag = ModelExtensionPaymentSquareup::CRON_ENDED_FLAG_COMPLETE;
                    }
                }
            } catch (\Squareup\Exception\Api $e) {
                if ($this->config->get('payment_squareup_debug')) {
                    $this->log->write("SQUAREUP CRON API ERROR: " . implode(PHP_EOL, $e->getMessages()));
                }

                $flag = ModelExtensionPaymentSquareup::CRON_ENDED_FLAG_ERROR;
            } catch (\Squareup\Exception\Timeout $e) {
                if ($this->config->get('payment_squareup_debug')) {
                    $this->log->write("SQUAREUP CRON TIMEOUT: " . $e->getMessage());
                }

                $flag = ModelExtensionPaymentSquareup::CRON_ENDED_FLAG_TIMEOUT;
            } catch (\Exception $e) {
                if ($this->config->get('payment_squareup_debug')) {
                    $this->log->write("SQUAREUP CRON ERROR: " . $e->getMessage());
                }

                $flag = ModelExtensionPaymentSquareup::CRON_ENDED_FLAG_ERROR;
            }
        }

        $this->model_extension_payment_squareup->setEndCronFlags($flag);
    }

    protected function catalog_sync() {
        $result = array(
            'sync_success' => '',
            'sync_warning' => '',
            'sync_error' => ''
        );
        $square_location_currency = $this->squareup_api->getLocationCurrency(null);
        // Catalog sync
        try {
            $source = $this->config->get('payment_squareup_sync_source');

            if ($this->config->get('config_currency') !== $square_location_currency) {
                throw new \Exception(sprintf($this->language->get('error_currency_mismatch'), $square_location_currency));
            }
            // Deprecated - should be used when we implement a Catalog sync in the direction Square > OpenCart
            // if (!$this->config->get('payment_squareup_initial_sync')) {
            //     if ($this->config->get('payment_squareup_initial_sync_type') == '4' && $source == 'square') {
            //         $source = 'opencart';
            //     } else if ($this->config->get('payment_squareup_initial_sync_type') == '2' && $source == 'opencart') {
            //         $source = 'square';
            //     }
            // }

            if ($source != 'none') {
                $sync_result = $this->squareup_diff->executeSync($source);

                $result['sync_success'] = $sync_result['success'];

                $result['sync_warning'] = $sync_result['warning'];
            } else {
                $result['sync_success'] = $this->language->get('text_sync_disabled');
            }
        } catch (\Squareup\Exception\Api $e) {
            $result['sync_error'] = implode('<br />', $e->getMessages());
        } catch (\Exception $e) {
            $result['sync_error'] = $e->getMessage();
        }

        // Deprecated - should be used when we implement a Catalog sync in the direction Square > OpenCart

        // $new_tax_rates = $this->model_extension_payment_squareup->getNewTaxRates();

        // $this->model_extension_payment_squareup->newTaxRatesEmail($new_tax_rates);

        $this->model_extension_payment_squareup->syncIssuesEmail($result['sync_warning']);

        return $result;
    }

    protected function inventory_sync() {
        $this->squareup_diff->syncInventories();
    }

    protected function on_demand_sync() {
        $result = array(
            'token_update_error' => '',
            'sync_success' => '',
            'sync_warning' => '',
            'sync_error' => ''
        );

        $result['token_update_error'] = $this->model_extension_payment_squareup->updateToken();

        $result = array_merge($result, $this->catalog_sync());

        if ($this->config->get('payment_squareup_cron_email_status')) {
            $this->model_extension_payment_squareup->cronEmail($result);
        }
    }

    protected function standard_sync() {
        $result = array(
            'transaction_success' => array(),
            'transaction_error' => array(),
            'transaction_fail' => array(),
            'token_update_error' => '',
            'sync_success' => '',
            'sync_warning' => '',
            'sync_error' => ''
        );

        $expirations = array(
            'expired_authorized_transactions' => array(),
            'expiring_authorized_transactions' => array()
        );

        // Update token
        $result['token_update_error'] = $this->model_extension_payment_squareup->updateToken();

        // Catalog Sync
        $result = array_merge($result, $this->catalog_sync());

        // Recurring
        $this->load->model('checkout/order');

        foreach ($this->model_extension_payment_squareup->nextRecurringPayments() as $payment) {
            if ($this->model_extension_payment_squareup->cronHasTimedOut()) {
                throw new \Squareup\Exception\Timeout("The recurring payments task has timed out.");
            }

            try {
                if (!$payment['is_free']) {
                    $transaction = $this->squareup_api->addTransaction($payment['transaction']);

                    $transaction_status = !empty($transaction['tenders'][0]['card_details']['status']) ?
                        strtolower($transaction['tenders'][0]['card_details']['status']) : '';

                    $target_currency = $transaction['tenders'][0]['amount_money']['currency'];

                    $amount = $this->squareup_api->standardDenomination($transaction['tenders'][0]['amount_money']['amount'], $target_currency);

                    $this->model_extension_payment_squareup->addTransaction($transaction, $this->config->get('payment_squareup_merchant_id'), $payment['billing_address'], $payment['order_id'], "CRON JOB", "127.0.0.1");

                    $reference = $transaction['id'];
                } else {
                    $amount = 0;
                    $target_currency = $this->config->get('config_currency');
                    $reference = '';
                    $transaction_status = 'captured';
                }

                $success = $transaction_status == 'captured';

                $this->model_extension_payment_squareup->addRecurringTransaction($payment['order_recurring_id'], $reference, $amount, $success);

                $trial_expired = false;
                $recurring_expired = false;
                $profile_suspended = false;

                if ($success) {
                    $trial_expired = $this->model_extension_payment_squareup->updateRecurringTrial($payment['order_recurring_id']);

                    $recurring_expired = $this->model_extension_payment_squareup->updateRecurringExpired($payment['order_recurring_id']);

                    $result['transaction_success'][$payment['order_recurring_id']] = $this->currency->format($amount, $target_currency);
                } else {
                    // Transaction was not successful. Suspend the recurring profile.
                    $profile_suspended = $this->model_extension_payment_squareup->suspendRecurringProfile($payment['order_recurring_id']);

                    $result['transaction_fail'][$payment['order_recurring_id']] = $this->currency->format($amount, $target_currency);
                }


                $order_status_id = $this->config->get('payment_squareup_status_' . $transaction_status);

                if ($order_status_id) {
                    if (!$payment['is_free']) {
                        $order_status_comment = $this->language->get('squareup_status_comment_' . $transaction_status);
                    } else {
                        $order_status_comment = '';
                    }

                    if ($profile_suspended) {
                        $order_status_comment .= $this->language->get('text_squareup_profile_suspended');
                    }

                    if ($trial_expired) {
                        $order_status_comment .= $this->language->get('text_squareup_trial_expired');
                    }

                    if ($recurring_expired) {
                        $order_status_comment .= $this->language->get('text_squareup_recurring_expired');
                    }

                    if ($success) {
                        $notify = (bool)$this->config->get('payment_squareup_notify_recurring_success');
                    } else {
                        $notify = (bool)$this->config->get('payment_squareup_notify_recurring_fail');
                    }

                    $this->model_checkout_order->addOrderHistory($payment['order_id'], $order_status_id, trim($order_status_comment), $notify);
                }
            } catch (\Squareup\Exception\Api $e) {
                $result['transaction_error'][] = '[ID: ' . $payment['order_recurring_id'] . '] - ' . implode('<br />', $e->getMessages());
            } catch (\Exception $e) {
                $result['transaction_error'][] = '[ID: ' . $payment['order_recurring_id'] . '] - ' . $e->getMessage();
            }
        };

        // Transactions
        $this->load->model('checkout/order');

        foreach ($this->model_extension_payment_squareup->getExpiringAuthorizedTransactions() as $expiring_authorized_transaction) {
            if ($this->model_extension_payment_squareup->cronHasTimedOut()) {
                throw new \Squareup\Exception\Timeout("The expiring transactions task has timed out.");
            }

            $new_transaction = $this->squareup_api->getTransaction($expiring_authorized_transaction['location_id'], $expiring_authorized_transaction['transaction_id']);

            $status = $new_transaction['tenders'][0]['card_details']['status'];
            $refunds = !empty($new_transaction['refunds']) ? $new_transaction['refunds'] : array();

            $this->model_extension_payment_squareup->updateTransaction($expiring_authorized_transaction['squareup_transaction_id'], $status, $refunds);

            $order_info = $this->model_checkout_order->getOrder($expiring_authorized_transaction['order_id']);

            $transaction_data = array(
                'transaction_id' => $expiring_authorized_transaction['transaction_id'],
                'order_id' => $expiring_authorized_transaction['order_id'],
                'customer_name' => trim($order_info['firstname']) . ' ' . trim($order_info['lastname']),
                'transaction_url' => $this->url->link('extension/payment/squareup/info', 'squareup_transaction_id=' . $expiring_authorized_transaction['squareup_transaction_id'] . '&cron_token=' . $this->config->get('payment_squareup_cron_token'), true)
            );

            if ($status != 'AUTHORIZED') {
                $expirations['expired_authorized_transactions'][] = $transaction_data;

                $order_status_id = $this->config->get('payment_squareup_status_' . strtolower($status));

                $order_status_comment = $this->language->get('squareup_status_comment_' . strtolower($status));

                $this->model_checkout_order->addOrderHistory($expiring_authorized_transaction['order_id'], $order_status_id, $order_status_comment, true);
            } else {
                $expirations['expiring_authorized_transactions'][] = $transaction_data;
            }
        }

        $this->model_extension_payment_squareup->expirationEmail($expirations);

        if ($this->config->get('payment_squareup_cron_email_status')) {
            $this->model_extension_payment_squareup->cronEmail($result);
        }
    }

    public function beforeAddOrderHistory(&$route, &$args) {
        $this->registry->set('squareup_order_history', new \Squareup\OrderHistory($this->registry));

        $this->squareup_order_history->persistOrderStock($args[0]);
    }

    public function afterAddOrderHistory(&$route, &$args, &$output) {
        if (!$this->registry->has('squareup_order_history')) {
            return;
        }

        $order_id = $args[0];
        $adjustments = array();
        $is_capture = !empty($this->session->data['squareup_is_capture']) || !empty($this->request->post['squareup_is_capture']);
        $ad_hoc_items = !empty($this->session->data['squareup_ad_hoc_items']) ? $this->session->data['squareup_ad_hoc_items'] : array();
        $stock_difference = $this->squareup_order_history->getOrderStockDifference($order_id);

        unset($this->session->data['squareup_is_capture']);
        unset($this->session->data['squareup_ad_hoc_items']);

        // Store ad-hoc items. They will be used to restrict the itemized re-stock.
        foreach ($ad_hoc_items as $order_product_id) {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "squareup_ad_hoc` SET order_product_id=" . (int)$order_product_id);
        }

        // Process Square-specific quantity adjustments
        if ($this->squareup_order_history->isPaymentMethodSquare($order_id)) {
            // Step 1 - first, revert the products to their original state. This is to ensure the Square quantity changes will be properly applied later below. In case $stock_difference === false, no need to do anything.
            if ($stock_difference !== false) {
                if ($this->config->get('payment_squareup_debug')) {
                    $this->log->write("SQUAREUP ORDER HISTORY - REVERT STOCK DIFFERENCE: " . json_encode($stock_difference));
                }

                // Revert stocks
                foreach ($stock_difference as $order_product) {
                    $product_id = (int)$order_product['product_id'];
                    // We expect all signs to be minus, but we also support plus in case of third-party mods. The end-goal is to have the same quantities as before the order history has been added.
                    $sign = (int)$order_product['quantity'] > 0 ? '+' : '-';
                    $quantity = abs($order_product['quantity']);

                    $this->db->query("UPDATE " . DB_PREFIX . "product SET quantity = (quantity " . $sign . " " . (int)$quantity . ") WHERE product_id = '" . (int)$product_id . "' AND subtract = '1'");

                    $order_options = $order_product['order_options'];

                    foreach ($order_options as $order_option) {
                        $product_option_value_id = (int)$order_option['product_option_value_id'];

                        $this->db->query("UPDATE " . DB_PREFIX . "product_option_value SET quantity = (quantity " . $sign . " " . (int)$quantity . ") WHERE product_option_value_id = '" . (int)$product_option_value_id . "' AND subtract = '1'");
                    }
                }
            }

            // Step 2
            // Process square action. In case of capture, we only need to deduct the OpenCart quantities without pushing an inventory adjustment to Square. This is because such is automatically made in case the transaction has been captured.
            if ($is_capture) {
                // Stock subtraction
                $order_products = $this->model_checkout_order->getOrderProducts($order_id);

                if ($this->config->get('payment_squareup_debug')) {
                    $this->log->write("SQUAREUP ORDER HISTORY - CAPTURE EVENT: " . json_encode($order_products));
                }

                foreach ($order_products as $order_product) {
                    $this->db->query("UPDATE " . DB_PREFIX . "product SET quantity = (quantity - " . (int)$order_product['quantity'] . ") WHERE product_id = '" . (int)$order_product['product_id'] . "' AND subtract = '1'");

                    $order_options = $this->model_checkout_order->getOrderOptions($order_id, $order_product['order_product_id']);

                    foreach ($order_options as $order_option) {
                        $this->db->query("UPDATE " . DB_PREFIX . "product_option_value SET quantity = (quantity - " . (int)$order_product['quantity'] . ") WHERE product_option_value_id = '" . (int)$order_option['product_option_value_id'] . "' AND subtract = '1'");
                    }
                }
            } else {
                // Refund
                if (!empty($this->request->post['square_refund']) && is_array($this->request->post['square_refund'])) {
                    // This is a Square return with a refund

                    if ($this->config->get('payment_squareup_debug')) {
                        $this->log->write("SQUAREUP ORDER HISTORY - REFUND EVENT: " . json_encode($this->request->post['square_refund']));
                    }

                    foreach ($this->request->post['square_refund'] as $refund) {
                        if (!empty($refund['catalog_object_id'])) {
                            /* Important: This step has been prohibited in the Inventory API version 2018-09-18. The code remains here in case of future updates. */
                            // $adjustments[] = array(
                            //     'catalog_object_id' => $refund['catalog_object_id'],
                            //     'quantity' => $refund['quantity'],
                            //     'from_state' => 'SOLD',
                            //     'to_state' => 'UNLINKED_RETURN'
                            // );
                        }

                        $this->db->query("INSERT INTO `" . DB_PREFIX . "squareup_refund` SET order_product_id=" . (int)$refund['order_product_id'] . ", quantity=" . (int)$refund['quantity']);
                    }
                }

                // Restock
                if (!empty($this->request->post['square_restock']) && is_array($this->request->post['square_restock'])) {
                    // This is a Square return with a restock

                    if ($this->config->get('payment_squareup_debug')) {
                        $this->log->write("SQUAREUP ORDER HISTORY - RESTOCK EVENT: " . json_encode($this->request->post['square_restock']));
                    }

                    foreach ($this->request->post['square_restock'] as $restock) {
                        if (!empty($restock['catalog_object_id'])) {
                            $adjustments[] = array(
                                'catalog_object_id' => $restock['catalog_object_id'],
                                'quantity' => $restock['quantity'],
                                'from_state' => 'UNLINKED_RETURN',
                                'to_state' => 'IN_STOCK'
                            );
                        }

                        $this->db->query("INSERT INTO `" . DB_PREFIX . "squareup_restock` SET order_product_id=" . (int)$restock['order_product_id'] . ", quantity=" . (int)$restock['quantity']);

                        $this->db->query("UPDATE " . DB_PREFIX . "product SET quantity = (quantity + " . (int)$restock['quantity'] . ") WHERE product_id = '" . (int)$restock['product_id'] . "' AND subtract = '1'");

                        $order_options = $this->model_checkout_order->getOrderOptions($order_id, $restock['order_product_id']);

                        foreach ($order_options as $order_option) {
                            $this->db->query("UPDATE " . DB_PREFIX . "product_option_value SET quantity = (quantity + " . (int)$restock['quantity'] . ") WHERE product_option_value_id = '" . (int)$order_option['product_option_value_id'] . "' AND subtract = '1'");
                        }
                    }
                }
            }
        } else {
            // If the payment method is not Square, push the order differences

            if ($stock_difference !== false) {
                // Revert stocks
                foreach ($stock_difference as $order_product) {
                    $quantity = (int)$order_product['quantity'];
                    $product_id = (int)$order_product['product_id'];
                    $catalog_object_id = $order_product['catalog_object_id'];

                    if (!empty($catalog_object_id)) {
                        if ($quantity < 0) {
                            // This is a return
                            /* Important: This step has been prohibited in the Inventory API version 2018-09-18. The code remains here in case of future updates. */
                            // $adjustments[] = array(
                            //     'catalog_object_id' => $catalog_object_id,
                            //     'quantity' => $quantity,
                            //     'from_state' => 'SOLD',
                            //     'to_state' => 'UNLINKED_RETURN'
                            // );

                            $adjustments[] = array(
                                'catalog_object_id' => $catalog_object_id,
                                'quantity' => $quantity,
                                'from_state' => 'UNLINKED_RETURN',
                                'to_state' => 'IN_STOCK'
                            );
                        } else {
                            // This is a purchase
                            $adjustments[] = array(
                                'catalog_object_id' => $catalog_object_id,
                                'quantity' => abs($quantity),
                                'from_state' => 'IN_STOCK',
                                'to_state' => 'SOLD'
                            );
                        }
                    }
                }
            }
        }

        if (!empty($adjustments)) {
            $this->load->library('squareup');

            try {
                $this->squareup_api->pushInventoryAdjustments($adjustments);
            } catch (\Squareup\Exception\Api $e) {
                $this->load->model('extension/payment/squareup');

                if ($e->isCurlError()) {
                    // Do nothing
                } else if ($e->isAccessTokenRevoked()) {
                    // Send reminder e-mail to store admin to refresh the token
                    $this->model_extension_payment_squareup->tokenRevokedEmail();
                } else if ($e->isAccessTokenExpired()) {
                    // Send reminder e-mail to store admin to refresh the token
                    $this->model_extension_payment_squareup->tokenExpiredEmail();
                } else {
                    // Do nothing
                }
            } catch (\Exception $e) {
                // Do nothing
            }
        }
    }

    // A webhook is NOT triggered in case a product "track inventory" has been disabled from the Square Dashboard.

    public function webhook() {
        if (!$this->request->server['HTTPS']) {
            return;
        }

        if (empty($this->request->server['HTTP_X_SQUARE_SIGNATURE'])) {
            return;
        }

        $payload = file_get_contents('php://input');

        $stringToSign = $this->config->get('payment_squareup_webhook_url_static') . $payload;

        $stringSignature = base64_encode(hash_hmac('sha1', $stringToSign, $this->config->get('payment_squareup_webhook_signature'), true));

        if (!hash_equals($stringSignature, $this->request->server['HTTP_X_SQUARE_SIGNATURE'])) {
            return;
        }

        if ($this->config->get('payment_squareup_cron_is_running')) {
            return;
        }

        if ($this->config->get('payment_squareup_debug')) {
            $this->log->write('SQUAREUP WEBHOOK: ' . $payload);
        }

        $data = json_decode($payload, true);

        if ($data['event_type'] == 'INVENTORY_UPDATED') {
            if ($this->config->get('payment_squareup_inventory_sync') != 'none') {
                $this->load->library('squareup');

                $this->squareup_diff->syncInventories(true);
            }
        }
    }

    protected function validate() {
        if (empty($this->request->get['cron_token']) || $this->request->get['cron_token'] != $this->config->get('payment_squareup_cron_token')) {
            return false;
        }

        if (empty($this->request->get['squareup_transaction_id']) && empty($this->request->get['squareup_settings'])) {
            return false;
        }

        if (!$this->config->get('payment_squareup_admin_url_transaction')) {
            return false;
        }

        if (!$this->config->get('payment_squareup_admin_url_settings')) {
            return false;
        }

        return true;
    }
}
