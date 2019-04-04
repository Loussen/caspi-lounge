<?php

class ModelExtensionPaymentSquareup extends Model {
    const RECURRING_ACTIVE = 1;
    const RECURRING_INACTIVE = 2;
    const RECURRING_CANCELLED = 3;
    const RECURRING_SUSPENDED = 4;
    const RECURRING_EXPIRED = 5;
    const RECURRING_PENDING = 6;
    
    const TRANSACTION_DATE_ADDED = 0;
    const TRANSACTION_PAYMENT = 1;
    const TRANSACTION_OUTSTANDING_PAYMENT = 2;
    const TRANSACTION_SKIPPED = 3;
    const TRANSACTION_FAILED = 4;
    const TRANSACTION_CANCELLED = 5;
    const TRANSACTION_SUSPENDED = 6;
    const TRANSACTION_SUSPENDED_FAILED = 7;
    const TRANSACTION_OUTSTANDING_FAILED = 8;
    const TRANSACTION_EXPIRED = 9;

    const CRON_ENDED_FLAG_INVALID = -1;
    const CRON_ENDED_FLAG_COMPLETE = 1;
    const CRON_ENDED_FLAG_ERROR = 2;
    const CRON_ENDED_FLAG_TIMEOUT = 3;
    const CRON_STARTED_FLAG_ON_DEMAND = 1;
    const CRON_STARTED_FLAG_STANDARD = 2;
    const CRON_STARTED_FLAG_INVENTORY = 3;

    const HREF_OPEN_TICKET = 'https://isenselabs.com/tickets/open';
    const HREF_SQUARE_DASHBOARD = 'https://squareup.com/dashboard/items/library';
    const HREF_BULK_TUTORIAL = 'https://www.youtube.com/watch?v=ZHOOlEN8mHs';

    public function getMethod($address, $total) {
        $geo_zone_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get('payment_squareup_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");

        $squareup_display_name = $this->config->get('payment_squareup_display_name');

        $this->load->language('extension/payment/squareup');

        if (!empty($squareup_display_name[$this->config->get('config_language_id')])) {
            $title = $squareup_display_name[$this->config->get('config_language_id')];
        } else {
            $title = $this->language->get('text_default_squareup_name');
        }

        $status = true;

        $minimum_total = (float)$this->config->get('payment_squareup_total');

        $squareup_geo_zone_id = $this->config->get('payment_squareup_geo_zone_id');

        if ($minimum_total > 0 && $minimum_total > $total) {
            $status = false;
        } else if (empty($squareup_geo_zone_id)) {
            $status = true;
        } else if ($geo_zone_query->num_rows == 0) {
            $status = false;
        }

        $method_data = array();

        if ($status) {
            $method_data = array(
                'code'      => 'squareup',
                'title'     => $title,
                'terms'     => '',
                'sort_order' => (int)$this->config->get('payment_squareup_sort_order')
            );
        }

        return $method_data;
    }

    public function addTransaction($transaction, $merchant_id, $address, $order_id, $user_agent, $ip) {
        $amount = $this->squareup_api->standardDenomination($transaction['tenders'][0]['amount_money']['amount'], $transaction['tenders'][0]['amount_money']['currency']);

        $this->db->query("INSERT INTO `" . DB_PREFIX . "squareup_transaction` SET transaction_id='" . $this->db->escape($transaction['id']) . "', square_customer_id='" . (!empty($transaction['customer_id']) ? $this->db->escape($transaction['customer_id']) : '') . "', merchant_id='" . $this->db->escape($merchant_id) . "', location_id='" . $this->db->escape($transaction['location_id']) . "', order_id='" . (int)$order_id . "', transaction_type='" . $this->db->escape($transaction['tenders'][0]['card_details']['status']) . "', transaction_amount='" . (float)$amount . "', transaction_currency='" . $this->db->escape($transaction['tenders'][0]['amount_money']['currency']) . "', billing_address_city='" . $this->db->escape($address['locality']) . "', billing_address_country='" . $this->db->escape($address['country']) . "', billing_address_postcode='" . $this->db->escape($address['postal_code']) . "', billing_address_province='" . $this->db->escape($address['sublocality']) . "', billing_address_street_1='" . $this->db->escape($address['address_line_1']) . "', billing_address_street_2='" . $this->db->escape($address['address_line_2']) . "', device_browser='" . $this->db->escape($user_agent) . "', device_ip='" . $this->db->escape($ip) . "', created_at='" . $this->db->escape($transaction['created_at']) . "', is_refunded='" . (int)(!empty($transaction['refunds'])) . "', refunded_at='" . $this->db->escape(!empty($transaction['refunds']) ? $transaction['refunds'][0]['created_at'] : '') . "', tenders='" . $this->db->escape(json_encode($transaction['tenders'])) . "', refunds='" . $this->db->escape(json_encode(!empty($transaction['refunds']) ? $transaction['refunds'] : array())) . "'");
    }

    public function tokenExpiredEmail() {
        if (!$this->mailResendPeriodExpired('token_expired')) {
            return;
        }

        $mail = new Mail();

        $mail->protocol = $this->config->get('config_mail_protocol');
        $mail->parameter = $this->config->get('config_mail_parameter');

        $mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
        $mail->smtp_username = $this->config->get('config_mail_smtp_username');
        $mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, "UTF-8");
        $mail->smtp_port = $this->config->get('config_mail_smtp_port');
        $mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

        $subject = $this->language->get('text_token_expired_subject');
        $message = $this->language->get('text_token_expired_message');

        $mail->setTo($this->config->get('config_email'));
        $mail->setFrom($this->config->get('config_email'));
        $mail->setSender($this->config->get('config_name'));
        $mail->setSubject(html_entity_decode($subject, ENT_QUOTES, "UTF-8"));
        $mail->setText(strip_tags($message));
        $mail->setHtml($message);

        $mail->send();
    }

    public function tokenRevokedEmail() {
        if (!$this->mailResendPeriodExpired('token_revoked')) {
            return;
        }

        $mail = new Mail();

        $mail->protocol = $this->config->get('config_mail_protocol');
        $mail->parameter = $this->config->get('config_mail_parameter');

        $mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
        $mail->smtp_username = $this->config->get('config_mail_smtp_username');
        $mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, "UTF-8");
        $mail->smtp_port = $this->config->get('config_mail_smtp_port');
        $mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

        $subject = $this->language->get('text_token_revoked_subject');
        $message = $this->language->get('text_token_revoked_message');

        $mail->setTo($this->config->get('config_email'));
        $mail->setFrom($this->config->get('config_email'));
        $mail->setSender($this->config->get('config_name'));
        $mail->setSubject(html_entity_decode($subject, ENT_QUOTES, "UTF-8"));
        $mail->setText(strip_tags($message));
        $mail->setHtml($message);
        
        $mail->send();
    }

    public function cronEmail($result) {
        $mail = new Mail();
        
        $mail->protocol = $this->config->get('config_mail_protocol');
        $mail->parameter = $this->config->get('config_mail_parameter');

        $mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
        $mail->smtp_username = $this->config->get('config_mail_smtp_username');
        $mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, "UTF-8");
        $mail->smtp_port = $this->config->get('config_mail_smtp_port');
        $mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

        $br = '<br />';

        $subject = $this->language->get('text_cron_subject');

        $message = $this->language->get('text_cron_message') . $br . $br;

        $message .= '<strong>' . $this->language->get('text_cron_summary_token_heading') . '</strong>' . $br;

        if ($result['token_update_error']) {
            $message .= $result['token_update_error'] . $br . $br;
        } else {
            $message .= $this->language->get('text_cron_summary_token_updated') . $br . $br;
        }

        if (!empty($result['transaction_error'])) {
            $message .= '<strong>' . $this->language->get('text_cron_summary_error_heading') . '</strong>' . $br;

            $message .= implode($br, $result['transaction_error']) . $br . $br;
        }

        if (!empty($result['transaction_fail'])) {
            $message .= '<strong>' . $this->language->get('text_cron_summary_fail_heading') . '</strong>' . $br;

            foreach ($result['transaction_fail'] as $order_recurring_id => $amount) {
                $message .= sprintf($this->language->get('text_cron_fail_charge'), $order_recurring_id, $amount) . $br;
            }
        }

        if (!empty($result['transaction_success'])) {
            $message .= '<strong>' . $this->language->get('text_cron_summary_success_heading') . '</strong>' . $br;

            foreach ($result['transaction_success'] as $order_recurring_id => $amount) {
                $message .= sprintf($this->language->get('text_cron_success_charge'), $order_recurring_id, $amount) . $br;
            }
        }

        $message .= '<strong>' . $this->language->get('text_cron_summary_success_sync_heading') . '</strong>' . $br;

        if (!empty($result['sync_success'])) {
            $message .= $result['sync_success'];
        }

        // if (!empty($result['sync_warning'])) {
        //     $message .= '<strong>' . $this->language->get('text_cron_summary_warning_sync_heading') . '</strong>' . $br;

        //     $message .= $result['sync_warning'];
        // }

        if (!empty($result['sync_error'])) {
            $message .= $this->language->get('text_cron_summary_fail_sync') . $br . $result['sync_error'];
        }

        $mail->setTo($this->config->get('payment_squareup_cron_email'));
        $mail->setFrom($this->config->get('config_email'));
        $mail->setSender($this->config->get('config_name'));
        $mail->setSubject(html_entity_decode($subject, ENT_QUOTES, "UTF-8"));
        $mail->setText(strip_tags($message));
        $mail->setHtml($message);
        $mail->send();
    }

    public function expirationEmail($expirations) {
        if (empty($expirations['expiring_authorized_transactions']) && empty($expirations['expired_authorized_transactions'])) {
            return;
        }

        $mail = new Mail();
        
        $mail->protocol = $this->config->get('config_mail_protocol');
        $mail->parameter = $this->config->get('config_mail_parameter');

        $mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
        $mail->smtp_username = $this->config->get('config_mail_smtp_username');
        $mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, "UTF-8");
        $mail->smtp_port = $this->config->get('config_mail_smtp_port');
        $mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

        $br = '<br />';

        $subject = $this->language->get('text_cron_expiration_subject');

        $message = '';

        if (!empty($expirations['expiring_authorized_transactions'])) {
            $message .= '<strong>' . $this->language->get('text_cron_expiration_message_expiring') . '</strong>' . $br . $br;

            $message .= '<table>';
            foreach ($expirations['expiring_authorized_transactions'] as $transaction) {
                $message .= '<tr>';
                $message .= '<td>' . $transaction['transaction_id'] . '</td>';
                $message .= '<td>| ' . sprintf($this->language->get('text_order_id'), $transaction['order_id']) . '</td>';
                $message .= '<td>| ' . $transaction['customer_name'] . '</td>';
                $message .= '<td>| <a href="' . $transaction['transaction_url'] . '" target="_blank">' . $this->language->get('text_view') . '</a></td>';
                $message .= '</tr>';
            }
            $message .= '</table>';

            $message .= $br . $br;
        }

        if (!empty($expirations['expired_authorized_transactions'])) {
            $message .= '<strong>' . $this->language->get('text_cron_expiration_message_expired') . '</strong>' . $br . $br;

            $message .= '<table>';
            foreach ($expirations['expired_authorized_transactions'] as $transaction) {
                $message .= '<tr>';
                $message .= '<td>' . $transaction['transaction_id'] . '</td>';
                $message .= '<td>| ' . sprintf($this->language->get('text_order_id'), $transaction['order_id']) . '</td>';
                $message .= '<td>| ' . $transaction['customer_name'] . '</td>';
                $message .= '<td>| <a href="' . $transaction['transaction_url'] . '" target="_blank">' . $this->language->get('text_view') . '</a></td>';
                $message .= '</tr>';
            }
            $message .= '</table>';

            $message .= $br . $br;
        }

        $mail->setTo($this->config->get('config_email'));
        $mail->setFrom($this->config->get('config_email'));
        $mail->setSender($this->config->get('config_name'));
        $mail->setSubject(html_entity_decode($subject, ENT_QUOTES, "UTF-8"));
        $mail->setText(strip_tags($message));
        $mail->setHtml($message);
        $mail->send();
    }

    public function squareOrderErrorEmail($errors, $order_id) {
        if (empty($errors)) {
            return;
        }

        $mail = new Mail();
        
        $mail->protocol = $this->config->get('config_mail_protocol');
        $mail->parameter = $this->config->get('config_mail_parameter');

        $mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
        $mail->smtp_username = $this->config->get('config_mail_smtp_username');
        $mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, "UTF-8");
        $mail->smtp_port = $this->config->get('config_mail_smtp_port');
        $mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

        $br = '<br />';

        $subject = $this->language->get('text_order_error_mail_subject');

        $message = sprintf($this->language->get('text_order_error_mail_intro'), $order_id);

        $message .= $br . $br;

        $message .= '<ul>';

        foreach ($errors as $error) {
            $message .= '<li>' . $error . '</li>';
        }

        $message .= '</ul>';

        $message .= $br . $br;

        $ticket_href = self::HREF_OPEN_TICKET;

        $message .= sprintf($this->language->get('text_order_error_mail_outro'), $ticket_href, $ticket_href);

        $mail->setTo($this->config->get('config_email'));
        $mail->setFrom($this->config->get('config_email'));
        $mail->setSender($this->config->get('config_name'));
        $mail->setSubject(html_entity_decode($subject, ENT_QUOTES, "UTF-8"));
        $mail->setText(strip_tags($message));
        $mail->setHtml($message);
        $mail->send();
    }

    public function syncIssuesEmail($warnings) {
        if (empty($warnings['combination_issues']) && empty($warnings['complex_initial_inventories_links'])) {
            return;
        }

        $mail = new Mail();
        
        $mail->protocol = $this->config->get('config_mail_protocol');
        $mail->parameter = $this->config->get('config_mail_parameter');

        $mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
        $mail->smtp_username = $this->config->get('config_mail_smtp_username');
        $mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, "UTF-8");
        $mail->smtp_port = $this->config->get('config_mail_smtp_port');
        $mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

        $br = '<br />';

        $subject = $this->language->get('text_cron_warnings_subject');

        $message = "";

        $count_issues = count($warnings['combination_issues']);

        if ($count_issues > 0) {
            $message .= sprintf($this->language->get('text_cron_warnings_intro'), $count_issues);

            $message .= $br . $br;

            $message .= '<ul>';

            foreach ($warnings['combination_issues'] as $warning) {
                $message .= '<li>' . $warning . '</li>';
            }

            $message .= '</ul>';

            $message .= $br . $br;
        }

        if (!empty($warnings['complex_initial_inventories_links'])) {
            $more = $warnings['complex_initial_inventories_count'] - count($warnings['complex_initial_inventories_links']);

            $message .= $this->language->get('text_cron_inventory_links_intro');

            $message .= $br . $br;

            $message .= sprintf($this->language->get('text_cron_inventory_dashboard'), self::HREF_SQUARE_DASHBOARD, self::HREF_BULK_TUTORIAL);

            $message .= '<ul>';

            foreach ($warnings['complex_initial_inventories_links'] as $item) {
                if (false !== $item_link = $this->makeItemLink($item)) {
                    $message .= '<li><a href="' . $item_link['href'] . '" target="_blank">' . $item_link['text'] . '</a></li>';
                }
            }

            if ($more > 0) {
                $message .= '<li>' . sprintf($this->language->get('text_cron_inventory_links_more'), number_format($more, 0, '.', ',')) . '</li>';
            }

            $message .= '</ul>';

            $message .= $br . $br;
        }

        $mail->setTo($this->config->get('config_email'));
        $mail->setFrom($this->config->get('config_email'));
        $mail->setSender($this->config->get('config_name'));
        $mail->setSubject(html_entity_decode($subject, ENT_QUOTES, "UTF-8"));
        $mail->setText(strip_tags($message));
        $mail->setHtml($message);
        $mail->send();
    }

    protected function makeItemLink($item) {
        $sql_item = "SELECT sc.data FROM `" . DB_PREFIX . "squareup_catalog` sc WHERE sc.type='ITEM' AND sc.square_id='" . $item['item_square_id'] . "'";

        $result_item = $this->db->query($sql_item);

        if ($result_item->num_rows > 0) {
            $item_data = json_decode($result_item->row['data'], true);

            $sql_variation = "SELECT sc.data FROM `" . DB_PREFIX . "squareup_catalog` sc WHERE sc.type='ITEM_VARIATION' AND sc.square_id='" . $item['variation_square_id'] . "'";

            $result_variation = $this->db->query($sql_variation);

            if ($result_variation->num_rows > 0) {
                $this->load->library('squareup');

                $variation_data = json_decode($result_variation->row['data'], true);

                return array(
                    'href' => $this->squareup_api->itemLink($item['item_square_id']),
                    'text' => $item_data['name'] . ' - ' . $variation_data['name']
                );
            }
        }

        return false;
    }

    public function newTaxRatesEmail($tax_rates) {
        if (empty($tax_rates)) {
            return;
        }

        $mail = new Mail();
        
        $mail->protocol = $this->config->get('config_mail_protocol');
        $mail->parameter = $this->config->get('config_mail_parameter');

        $mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
        $mail->smtp_username = $this->config->get('config_mail_smtp_username');
        $mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, "UTF-8");
        $mail->smtp_port = $this->config->get('config_mail_smtp_port');
        $mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

        $br = '<br />';

        $subject = $this->language->get('text_cron_tax_rates_subject');

        $count = count($tax_rates);
        $url = $this->url->link('extension/payment/squareup/info', 'squareup_settings=1&cron_token=' . $this->config->get('payment_squareup_cron_token'), true);

        $message = sprintf($this->language->get('text_cron_tax_rates_intro'), $count, $url);

        $message .= $br . $br;

        $message .= '<ul>';

        foreach ($tax_rates as $tax_rate) {
            $message .= '<li>' . htmlentities($tax_rate['name'], ENT_QUOTES, "UTF-8") . '</li>';
        }

        $message .= '</ul>';

        $mail->setTo($this->config->get('config_email'));
        $mail->setFrom($this->config->get('config_email'));
        $mail->setSender($this->config->get('config_name'));
        $mail->setSubject(html_entity_decode($subject, ENT_QUOTES, "UTF-8"));
        $mail->setText(strip_tags($message));
        $mail->setHtml($message);
        $mail->send();
    }

    public function oneCronStandardPeriodHasPassed() {
        if (!$this->config->get('payment_squareup_initial_sync')) {
            return true;
        }

        $time = (int)$this->config->get('payment_squareup_cron_started_at');

        $this->load->config('squareup/cron');

        if ($time > 0 && (time() - $time > 60 * (int)$this->config->get('payment_squareup_cron_standard_period'))) {
            return true;
        }

        return false;
    }

    public function oneCronInventoryPeriodHasPassed() {
        if (!$this->config->get('payment_squareup_initial_sync')) {
            return true;
        }

        $time = (int)$this->config->get('payment_squareup_cron_started_at');

        $this->load->config('squareup/cron');

        if ($time > 0 && (time() - $time > $this->config->get('squareup_cron_inventory_period'))) {
            return true;
        }

        return false;
    }

    public function setBeginSyncFlags() {
        $this->editSquareSetting(array(
            'payment_squareup_cron_is_running' => '1'
        ));
    }

    public function setEndSyncFlags() {
        $this->editSquareSetting(array(
            'payment_squareup_cron_is_running' => '0'
        ));
    }

    public function setBeginCronFlags($flag) {
        $this->editSquareSetting(array(
            'payment_squareup_cron_is_running' => '1',
            'payment_squareup_cron_started_at' => time(),
            'payment_squareup_cron_started_type' => $flag
        ));
    }

    public function setEndCronFlags($flag) {
        if ($flag != self::CRON_ENDED_FLAG_INVALID) {
            $this->editSquareSetting(array(
                'payment_squareup_cron_is_running' => '0',
                'payment_squareup_cron_is_on_demand' => '0',
                'payment_squareup_cron_ended_at' => time(),
                'payment_squareup_cron_ended_type' => $flag
            ));
        }
    }

    public function cronHasTimedOut() {
        $time = (int)$this->getSquareSetting('payment_squareup_cron_started_at');

        switch ($this->getSquareSetting('payment_squareup_cron_started_type')) {
            case self::CRON_STARTED_FLAG_STANDARD :
            case self::CRON_STARTED_FLAG_ON_DEMAND : {
                // Standard period minus 5 minutes
                $timeout = (int)$this->getSquareSetting('payment_squareup_cron_standard_period') * 60 - (int)$this->config->get('squareup_cron_timeout_buffer');
                $default_timeout = (int)$this->config->get('squareup_cron_default_standard_timeout') * 60;
            } break;
            case self::CRON_STARTED_FLAG_INVENTORY : {
                $timeout = (int)$this->config->get('squareup_cron_default_inventory_timeout') * 60;
                $default_timeout = (int)$this->config->get('squareup_cron_default_inventory_timeout') * 60;
            } break;
        }

        $this->load->config('squareup/cron');

        if ($timeout == 0) {
            $timeout = $default_timeout;
        }

        if ($time > 0 && (time() - $time > $timeout)) {
            return true;
        }

        return false;
    }

    public function recurringPayments() {
        return (bool)$this->config->get('payment_squareup_recurring_status');
    }

    public function createRecurring($recurring, $order_id, $description, $reference) {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "order_recurring` SET `order_id` = '" . (int)$order_id . "', `date_added` = NOW(), `status` = '" . self::RECURRING_ACTIVE . "', `product_id` = '" . (int)$recurring['product_id'] . "', `product_name` = '" . $this->db->escape($recurring['name']) . "', `product_quantity` = '" . $this->db->escape($recurring['quantity']) . "', `recurring_id` = '" . (int)$recurring['recurring']['recurring_id'] . "', `recurring_name` = '" . $this->db->escape($recurring['recurring']['name']) . "', `recurring_description` = '" . $this->db->escape($description) . "', `recurring_frequency` = '" . $this->db->escape($recurring['recurring']['frequency']) . "', `recurring_cycle` = '" . (int)$recurring['recurring']['cycle'] . "', `recurring_duration` = '" . (int)$recurring['recurring']['duration'] . "', `recurring_price` = '" . (float)$recurring['recurring']['price'] . "', `trial` = '" . (int)$recurring['recurring']['trial'] . "', `trial_frequency` = '" . $this->db->escape($recurring['recurring']['trial_frequency']) . "', `trial_cycle` = '" . (int)$recurring['recurring']['trial_cycle'] . "', `trial_duration` = '" . (int)$recurring['recurring']['trial_duration'] . "', `trial_price` = '" . (float)$recurring['recurring']['trial_price'] . "', `reference` = '" . $this->db->escape($reference) . "'");

        return $this->db->getLastId();
    }

    public function validateCRON() {
        if (!$this->config->get('payment_squareup_status')) {
            return false;
        }

        if (isset($this->request->get['cron_token']) && $this->request->get['cron_token'] == $this->config->get('payment_squareup_cron_token')) {
            return true;
        }

        if (defined('SQUAREUP_ROUTE')) {
            return true;
        }

        return false;
    }

    public function updateToken() {
        try {
            $response = $this->squareup_api->refreshToken();

            if (!isset($response['access_token']) || !isset($response['token_type']) || !isset($response['expires_at']) || !isset($response['merchant_id']) || $response['merchant_id'] != $this->config->get('payment_squareup_merchant_id')) {
                return $this->language->get('error_squareup_cron_token');
            } else {
                $this->editSquareSetting(array(
                    'payment_squareup_access_token' => $response['access_token'],
                    'payment_squareup_access_token_expires' => $response['expires_at']
                ));
            }
        } catch (\Squareup\Exception\Api $e) {
            return $e->getMessage();
        }

        return '';
    }

    public function nextRecurringPayments() {
        $payments = array();

        if (!$this->config->get('payment_squareup_recurring_status')) {
            return $payments;
        }

        $this->load->library('squareup');

        $recurring_sql = "SELECT * FROM `" . DB_PREFIX . "order_recurring` `or` INNER JOIN `" . DB_PREFIX . "squareup_transaction` st ON (st.transaction_id = `or`.reference) WHERE `or`.status='" . self::RECURRING_ACTIVE . "'";

        $this->load->model('checkout/order');

        foreach ($this->db->query($recurring_sql)->rows as $recurring) {
            if (!$this->paymentIsDue($recurring['order_recurring_id'])) {
                continue;
            }

            // Skip transactions made from other merchant accounts
            if ($recurring['merchant_id'] != $this->config->get('payment_squareup_merchant_id')) {
                continue;
            }

            $order_info = $this->model_checkout_order->getOrder($recurring['order_id']);

            $billing_address = array(
                'first_name' => $order_info['payment_firstname'],
                'last_name' => $order_info['payment_lastname'],
                'address_line_1' => $recurring['billing_address_street_1'],
                'address_line_2' => $recurring['billing_address_street_2'],
                'locality' => $recurring['billing_address_city'],
                'sublocality' => $recurring['billing_address_province'],
                'postal_code' => $recurring['billing_address_postcode'],
                'country' => $recurring['billing_address_country'],
                'organization' => $recurring['billing_address_company']
            );

            $transaction_tenders = @json_decode($recurring['tenders'], true);

            $location_currency = $this->squareup_api->getLocationCurrency(null);

            if (is_null($location_currency) || !$this->squareup_api->isCurrencySupported($recurring['transaction_currency'])) {
                throw new \Exception($this->language->get('error_currency_invalid'));
            }

            $price = (float)($recurring['trial'] ? $recurring['trial_price'] : $recurring['recurring_price']);

            $price = $this->currency->convert($price, $recurring['transaction_currency'], $location_currency);

            $price = $this->squareup_api->roundPrice($price, $location_currency);

            $square_order_id = null;

            if ($price > 0) {
                // If the Square order throws an error, ignore it and submit the transaction with no items
                
                try {
                    $square_order_id = $this->squareup_api->createRecurringOrder($order_info['order_id'], $price, (bool)$recurring['trial'], $recurring['product_quantity'], $location_currency);
                } catch (\Squareup\Exception\Api $e) {
                    if ($e->isCurlError() || $e->isAccessTokenRevoked() || $e->isAccessTokenExpired()) {
                        throw $e;
                    }
                }
            
                $transaction = array(
                    'note' => sprintf($this->language->get('text_order_id'), $order_info['order_id']),
                    'idempotency_key' => uniqid(),
                    'amount_money' => array(
                        'amount' => $this->squareup_api->lowestDenomination($price * $recurring['product_quantity'], $location_currency),
                        'currency' => $location_currency
                    ),
                    'billing_address' => $billing_address,
                    'buyer_email_address' => $order_info['email'],
                    'delay_capture' => false,
                    'customer_id' => $transaction_tenders[0]['customer_id'],
                    'customer_card_id' => $transaction_tenders[0]['card_details']['card']['id'],
                    'integration_id' => Squareup::SQUARE_INTEGRATION_ID
                );

                if (!is_null($square_order_id)) {
                    $transaction['order_id'] = $square_order_id;
                }

                $payments[] = array(
                    'is_free' => $price == 0,
                    'order_id' => $recurring['order_id'],
                    'order_recurring_id' => $recurring['order_recurring_id'],
                    'billing_address' => $billing_address,
                    'transaction' => $transaction
                );
            } else {
                throw new \Exception($this->language->get('error_price_invalid_negative'));
            }
        }

        return $payments;
    }

    public function addRecurringTransaction($order_recurring_id, $reference, $amount, $status) {
        if ($status) {
            $type = self::TRANSACTION_PAYMENT;
        } else {
            $type = self::TRANSACTION_FAILED;
        }

        $this->db->query("INSERT INTO `" . DB_PREFIX . "order_recurring_transaction` SET order_recurring_id='" . (int)$order_recurring_id . "', reference='" . $this->db->escape($reference) . "', type='" . (int)$type . "', amount='" . (float)$amount . "', date_added=NOW()");
    }

    public function updateRecurringExpired($order_recurring_id) {
        $recurring_info = $this->getRecurring($order_recurring_id);

        if ($recurring_info['trial']) {
            // If we are in trial, we need to check if the trial will end at some point
            $expirable = (bool)$recurring_info['trial_duration'];
        } else {
            // If we are not in trial, we need to check if the recurring will end at some point
            $expirable = (bool)$recurring_info['recurring_duration'];
        }

        // If recurring payment can expire (trial_duration > 0 AND recurring_duration > 0)
        if ($expirable) {
            $number_of_successful_payments = $this->getTotalSuccessfulPayments($order_recurring_id);

            $total_duration = (int)$recurring_info['trial_duration'] + (int)$recurring_info['recurring_duration'];
            
            // If successful payments exceed total_duration
            if ($number_of_successful_payments >= $total_duration) {
                $this->db->query("UPDATE `" . DB_PREFIX . "order_recurring` SET status='" . self::RECURRING_EXPIRED . "' WHERE order_recurring_id='" . (int)$order_recurring_id . "'");

                return true;
            }
        }

        return false;
    }

    public function updateRecurringTrial($order_recurring_id) {
        $recurring_info = $this->getRecurring($order_recurring_id);

        // If recurring payment is in trial and can expire (trial_duration > 0)
        if ($recurring_info['trial'] && $recurring_info['trial_duration']) {
            $number_of_successful_payments = $this->getTotalSuccessfulPayments($order_recurring_id);

            // If successful payments exceed trial_duration
            if ($number_of_successful_payments >= $recurring_info['trial_duration']) {
                $this->db->query("UPDATE `" . DB_PREFIX . "order_recurring` SET trial='0' WHERE order_recurring_id='" . (int)$order_recurring_id . "'");

                return true;
            }
        }

        return false;
    }

    public function suspendRecurringProfile($order_recurring_id) {
        $this->db->query("UPDATE `" . DB_PREFIX . "order_recurring` SET status='" . self::RECURRING_SUSPENDED . "' WHERE order_recurring_id='" . (int)$order_recurring_id . "'");

        return true;
    }

    public function updateTransaction($squareup_transaction_id, $type, $refunds = array()) {
        $this->db->query("UPDATE `" . DB_PREFIX . "squareup_transaction` SET transaction_type='" . $this->db->escape($type) . "', is_refunded='" . (int)!empty($refunds) . "', refunds='" . $this->db->escape(json_encode($refunds)) . "' WHERE squareup_transaction_id='" . (int)$squareup_transaction_id . "'");
    }

    public function getExpiringAuthorizedTransactions() {
        $two_days_ago = date('Y-m-d', time() - (2 * 24 * 3600));

        return $this->db->query("SELECT * FROM `" . DB_PREFIX . "squareup_transaction` WHERE transaction_type='AUTHORIZED' AND created_at < '" . $two_days_ago . "' AND merchant_id='" . $this->db->escape($this->config->get('payment_squareup_merchant_id')) . "'")->rows;
    }

    public function setDetailedExceptionHandler() {
        set_error_handler(array($this, 'detailedExceptionHandler'));
    }

    public function setExceptionHandler() {
        set_error_handler(array($this, 'exceptionHandler'));
    }

    public function setErrorLogHandler() {
        set_error_handler(array($this, 'logHandler'));
    }

    public function logHandler($code, $message, $file, $line) {
        if (error_reporting() === 0) {
            return false;
        }

        switch ($code) {
            case E_NOTICE:
            case E_USER_NOTICE:
                $error = 'Notice';
                break;
            case E_WARNING:
            case E_USER_WARNING:
                $error = 'Warning';
                break;
            case E_ERROR:
            case E_USER_ERROR:
                $error = 'Fatal Error';
                break;
            default:
                $error = 'Unknown';
                break;
        }

        $message = 'PHP ' . $error . ':  ' . $message . ' in ' . $file . ' on line ' . $line;

        if ($this->config->get('error_log')) {
            $this->log->write($message);
        }
    }

    public function detailedExceptionHandler($code, $message, $file, $line) {
        $this->logHandler($code, $message, $file, $line);

        throw new \Exception($message);
    }

    public function exceptionHandler($code, $message, $file, $line) {
        $this->load->language('extension/payment/squareup');

        $this->logHandler($code, $message, $file, $line);

        throw new \Exception(sprintf($this->language->get('error_generic'), $this->config->get('config_telephone'), $this->config->get('config_email')));
    }

    public function getApplePayLineItems($order_id) {
        $this->load->model('checkout/order');
        $this->load->model('localisation/country');
        $this->load->model('localisation/zone');

        $this->load->library('squareup');

        $order_info = $this->model_checkout_order->getOrder($order_id);

        $lineItems = array();
        $lineTotal = array();

        foreach ($this->model_checkout_order->getOrderProducts($order_id) as $product) {
            $options = array();

            foreach ($this->model_checkout_order->getOrderOptions($order_id, $product['order_product_id']) as $option) {
                $options[] = trim($option['value']);
            }

            if (count($options)) {
                $option_text = ' (' . implode(', ', $options) . ')';
            } else {
                $option_text = '';
            }

            $lineItems[] = array(
                'label' => $product['name'] . $option_text,
                'amount' => $this->convertPriceNumeric((float)$product['total'], $order_info['currency_code']),
                'pending' => false
            );
        }

        foreach ($this->model_checkout_order->getOrderTotals($order_id) as $total) {
            if ($total['code'] != 'sub_total' && $total['code'] != 'total') {
                $lineItems[] = array(
                    'label' => $total['title'],
                    'amount' => $this->convertPriceNumeric((float)$total['value'], $order_info['currency_code']),
                    'pending' => false
                );
            } else if ($total['code'] == 'total') {
                $lineTotal = array(
                    'label' => $total['title'],
                    'amount' => $this->convertPriceNumeric((float)$total['value'], $order_info['currency_code']),
                    'pending' => false
                );
            }
        }

        $result = null;

        $shipping_country_info = $this->model_localisation_country->getCountry($order_info['shipping_country_id']);
        $shipping_zone_info = $this->model_localisation_zone->getZone($order_info['shipping_zone_id']);
        $payment_country_info = $this->model_localisation_country->getCountry($order_info['payment_country_id']);

        if (!empty($payment_country_info['iso_code_2'])) {
            $result = array(
                'requestShippingAddress' => false,
                'requestBillingInfo' => false,
                'currencyCode' => $order_info['currency_code'],
                'countryCode' => $payment_country_info['iso_code_2'],
                'lineItems' => $lineItems,
                'total' => $lineTotal
            );

            if (!empty($shipping_country_info['iso_code_3']) && !empty($shipping_zone_info['code'])) {
                $result['shippingContact'] = array(
                    'givenName' => $order_info['shipping_firstname'],
                    'familyName' => $order_info['shipping_lastname'],
                    'email' => $order_info['email'],
                    'country' => $shipping_country_info['iso_code_3'],
                    'region' => $shipping_zone_info['code'],
                    'city' => $order_info['shipping_city'],
                    'addressLines' => array(
                        $order_info['shipping_address_1'],
                        $order_info['shipping_address_2']
                    ),
                    'postalCode' => $order_info['shipping_postcode']
                );
            } else {
                $result['requestShippingAddress'] = true;
            }
        }

        return $result;
    }

    public function getNewTaxRates() {
        $sql = "SELECT * FROM `" . DB_PREFIX . "tax_rate` WHERE geo_zone_id='0' AND type='P'";

        return $this->db->query($sql)->rows;
    }

    private function convertPriceNumeric($price, $currency) {
        return $this->currency->format($this->currency->convert($price, $this->config->get('config_currency'), $currency), $currency, '', false);
    }

    private function getLastSuccessfulRecurringPaymentDate($order_recurring_id) {
        return $this->db->query("SELECT date_added FROM `" . DB_PREFIX . "order_recurring_transaction` WHERE order_recurring_id='" . (int)$order_recurring_id . "' AND type='" . self::TRANSACTION_PAYMENT . "' ORDER BY date_added DESC LIMIT 0,1")->row['date_added'];
    }

    private function getRecurring($order_recurring_id) {
        $recurring_sql = "SELECT * FROM `" . DB_PREFIX . "order_recurring` WHERE order_recurring_id='" . (int)$order_recurring_id . "'";

        return $this->db->query($recurring_sql)->row;
    }

    private function getTotalSuccessfulPayments($order_recurring_id) {
        return $this->db->query("SELECT COUNT(*) as total FROM `" . DB_PREFIX . "order_recurring_transaction` WHERE order_recurring_id='" . (int)$order_recurring_id . "' AND type='" . self::TRANSACTION_PAYMENT . "'")->row['total'];
    }

    private function paymentIsDue($order_recurring_id) {
        // We know the recurring profile is active.
        $recurring_info = $this->getRecurring($order_recurring_id);

        if ($recurring_info['trial']) {
            $frequency = $recurring_info['trial_frequency'];
            $cycle = (int)$recurring_info['trial_cycle'];
        } else {
            $frequency = $recurring_info['recurring_frequency'];
            $cycle = (int)$recurring_info['recurring_cycle'];
        }
        // Find date of last payment
        if (!$this->getTotalSuccessfulPayments($order_recurring_id)) {
            $previous_time = strtotime($recurring_info['date_added']);
        } else {
            $previous_time = strtotime($this->getLastSuccessfulRecurringPaymentDate($order_recurring_id));
        }

        switch ($frequency) {
            case 'day' : $time_interval = 24 * 3600; break;
            case 'week' : $time_interval = 7 * 24 * 3600; break;
            case 'semi_month' : $time_interval = 15 * 24 * 3600; break;
            case 'month' : $time_interval = 30 * 24 * 3600; break;
            case 'year' : $time_interval = 365 * 24 * 3600; break;
        }

        $due_date = date('Y-m-d', $previous_time + ($time_interval * $cycle));

        $this_date = date('Y-m-d');

        return $this_date >= $due_date;
    }

    public function getSquareSetting($key) {
        $result = $this->db->query("SELECT `value`, `serialized` FROM `" . DB_PREFIX . "setting` WHERE `code`='payment_squareup' AND `key`='" . $this->db->escape($key) . "'");

        if ($result->num_rows) {
            if ($result->row['serialized'] == '1') {
                return json_decode($result->row['value'], true);
            } else {
                return $result->row['value'];
            }
        }

        return null;
    }

    public function editSquareSetting($settings) {
        foreach ($settings as $key => $value) {
            $this->db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `code`='payment_squareup' AND `key`='" . $this->db->escape($key) . "'");

            $this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `code`='payment_squareup', `key`='" . $this->db->escape($key) . "', `value`='" . $this->db->escape($value) . "', serialized=0, store_id=0");
        }
    }

    private function mailResendPeriodExpired($key) {
        $result = (int)$this->cache->get('squareup.' . $key);

        if (!$result) {
            // No result, therefore this is the first e-mail and the re-send period should be regarded as expired.
            $this->cache->set('squareup.' . $key, time());
        } else {
            // There is an entry in the cache. We will calculate the time difference (delta)
            $delta = time() - $result;

            if ($delta >= 15 * 60) {
                // More than 15 minutes have passed, therefore the re-send period has expired.
                $this->cache->set('squareup.' . $key, time());
            } else {
                // Less than 15 minutes have passed before the last e-mail, therefore the re-send period has not expired.
                return false;
            }
        }

        // In all other cases, the re-send period has expired.
        return true;
    }
}
