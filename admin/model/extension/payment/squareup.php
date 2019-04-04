<?php

class ModelExtensionPaymentSquareup extends Model {
    const RECURRING_ACTIVE = 1;
    const RECURRING_INACTIVE = 2;
    const RECURRING_CANCELLED = 3;
    const RECURRING_SUSPENDED = 4;
    const RECURRING_EXPIRED = 5;
    const RECURRING_PENDING = 6;

    private $database_name = DB_DATABASE;

    private $indexes = array(
        array(
            'table' => 'product_option_value',
            'column' => 'product_id'
        ),
        array(
            'table' => 'product',
            'column' => 'status'
        ),
        array(
            'table' => 'product',
            'column' => 'image'
        )
    );

    // column checks
    private $changes = array(
        array(
            'table' => 'squareup_customer',
            'old_column' => 'oc_customer_id',
            'new_column' => 'customer_id',
            'column_definition' => 'int(11) NOT NULL'
        ),
        array(
            'table' => 'squareup_token',
            'old_column' => 'oc_customer_id',
            'new_column' => 'customer_id',
            'column_definition' => 'int(11) NOT NULL'
        ),
        array(
            'table' => 'squareup_transaction',
            'old_column' => 'billing_address_street',
            'new_column' => 'billing_address_street_1',
            'column_definition' => 'char(100) NOT NULL'
        ),
    );

    private $additions = array(
        array(
            'table' => 'squareup_transaction',
            'column_name' => 'billing_address_street_2',
            'column_definition' => 'char(100) NOT NULL AFTER `billing_address_street_1`',
        ),
    );
    // end column checks

    private $predefinedCountries = array('USA', 'JPN', 'CAN', 'GBR', 'AUT', 'BEL', 'BGR', 'HRV', 'CYP', 'CZE', 'DNK', 'EST', 'FIN', 'FRA', 'DEU', 'GRC', 'HUN', 'IRL', 'ITA', 'LVA', 'LTU', 'LUX', 'MLT', 'NLD', 'POL', 'PRT', 'ROM', 'SVK', 'SVN', 'ESP', 'SWE');

    private $nonEuCountries = array('USA', 'JPN', 'CAN');

    public function getItemId($product_id) {
        $sql = "SELECT square_id FROM `" . DB_PREFIX . "squareup_product_item` WHERE product_id=" . (int)$product_id . " LIMIT 0,1";

        $result = $this->db->query($sql);

        if ($result->num_rows) {
            return $result->row['square_id'];
        }

        return null;
    }

    public function getTransaction($squareup_transaction_id) {
        return $this->db->query("SELECT * FROM `" . DB_PREFIX . "squareup_transaction` WHERE squareup_transaction_id='" . (int)$squareup_transaction_id . "'")->row;
    }

    public function getTransactions($data) {
        $sql = "SELECT * FROM `" . DB_PREFIX . "squareup_transaction`";

        if (isset($data['order_id'])) {
            $sql .= " WHERE order_id='" . (int)$data['order_id'] . "'";
        }

        $sql .= " ORDER BY created_at DESC";

        if (isset($data['start']) && isset($data['limit'])) {
            $sql .= " LIMIT " . $data['start'] . ', ' . $data['limit'];
        }

        return $this->db->query($sql)->rows;
    }

    public function getTotalTransactions($data) {
        $sql = "SELECT COUNT(*) as total FROM `" . DB_PREFIX . "squareup_transaction`";

        if (isset($data['order_id'])) {
            $sql .= " WHERE order_id='" . (int)$data['order_id'] . "'";
        }

        return $this->db->query($sql)->row['total'];
    }

    public function updateTransaction($squareup_transaction_id, $type, $refunds = array()) {
        $this->db->query("UPDATE `" . DB_PREFIX . "squareup_transaction` SET transaction_type='" . $this->db->escape($type) . "', is_refunded='" . (int)!empty($refunds) . "', refunds='" . $this->db->escape(json_encode($refunds)) . "' WHERE squareup_transaction_id='" . (int)$squareup_transaction_id . "'");
    }

    public function getOrderStatusId($order_id, $transaction_status = null) {
        if ($transaction_status) {
            return $this->config->get('payment_squareup_status_' . strtolower($transaction_status));
        } else {
            $this->load->model('sale/order');

            $order_info = $this->model_sale_order->getOrder($order_id);

            return $order_info['order_status_id'];
        }
    }

    public function editOrderRecurringStatus($order_recurring_id, $status) {
        $this->db->query("UPDATE `" . DB_PREFIX . "order_recurring` SET `status` = '" . (int)$status . "' WHERE `order_recurring_id` = '" . (int)$order_recurring_id . "'");
    }

    public function getAdminURLTransaction() {
        return $this->url->link('extension/payment/squareup/transaction_info', '&squareup_transaction_id=%s%s', true);
    }

    public function getAdminURLSettings() {
        return $this->url->link('extension/payment/squareup', '%s', true);
    }

    public function getNewTaxRates() {
        $sql = "SELECT * FROM `" . DB_PREFIX . "tax_rate` WHERE geo_zone_id='0' AND type='P'";

        return array_map(array($this, 'applyTaxRateInfo'), $this->db->query($sql)->rows);
    }

    public function isAdHocItem($order_product_id) {
        $sql = "SELECT sah.squareup_ad_hoc_id FROM `" . DB_PREFIX . "squareup_ad_hoc` sah WHERE sah.order_product_id=" . (int)$order_product_id;

        return $this->db->query($sql)->num_rows > 0;
    }

    public function getAllowedRestockQuantity($order_product_id) {
        $sql = "SELECT op.quantity - SUM(sr.quantity) as allowed_quantity, op.quantity as initial_quantity FROM `" . DB_PREFIX . "order_product` op LEFT JOIN `" . DB_PREFIX . "squareup_restock` sr ON (sr.order_product_id = op.order_product_id) WHERE op.order_product_id=" . (int)$order_product_id . " GROUP BY (op.order_product_id)";

        $result = $this->db->query($sql);

        return !is_null($result->row['allowed_quantity']) ? (int)$result->row['allowed_quantity'] : (int)$result->row['initial_quantity'];
    }

    public function getAllowedRefundQuantity($order_product_id) {
        $sql = "SELECT op.quantity - SUM(sr.quantity) as allowed_quantity, op.quantity as initial_quantity FROM `" . DB_PREFIX . "order_product` op LEFT JOIN `" . DB_PREFIX . "squareup_refund` sr ON (sr.order_product_id = op.order_product_id) WHERE op.order_product_id=" . (int)$order_product_id . " GROUP BY (op.order_product_id)";

        $result = $this->db->query($sql);

        return !is_null($result->row['allowed_quantity']) ? (int)$result->row['allowed_quantity'] : (int)$result->row['initial_quantity'];
    }

    protected function isTransactionFullyRefunded($transaction) {
        $refunds = @json_decode($transaction['refunds'], true);
        $refunded_amount = 0;
        $has_pending = false;

        foreach ($refunds as $refund) {
            if ($refund['status'] == 'REJECTED' || $refund['status'] == 'FAILED') {
                continue;
            }

            if ($refund['status'] == 'PENDING') {
                $has_pending = true;
            }

            $refunded_amount += $refund['amount_money']['amount'];
        }

        return !$has_pending && $refunded_amount == $this->squareup_api->lowestDenomination($transaction['transaction_amount'], $transaction['transaction_currency']);
    }

    protected function refundsAreDifferent($a, $b) {
        $simplified_a = array();
        $simplified_b = array();

        foreach ($a as $refund) {
            $simplified_a[$refund['id']] = $refund['status'];
        }

        foreach ($b as $refund) {
            $simplified_b[$refund['id']] = $refund['status'];
        }

        ksort($simplified_a);
        ksort($simplified_b);

        return json_encode($simplified_a) != json_encode($simplified_b);
    }

    protected function inLocations($location_id) {
        foreach ($this->config->get('payment_squareup_locations') as $location) {
            if ($location['id'] == $location_id) {
                return true;
            }
        }

        return false;
    }

    public function getTransactionStatus($transaction) {
        $detected_new_refund = false;

        $result['amount_refunded'] = $this->language->get('text_na');
        $result['is_fully_refunded'] = false;
        $result['type'] = $transaction['transaction_type'];
        $result['order_history_data'] = null;
        $result['is_merchant_transaction'] = $transaction['merchant_id'] == $this->config->get('payment_squareup_merchant_id') && $this->inLocations($transaction['location_id']);

        $refunds = @json_decode($transaction['refunds'], true);

        $result['text'] = $this->language->get('entry_status_' . strtolower($result['type']));

        // $transaction['refunds'] is what we currently have - it is a json_encoded representation of the refunds

        $this->load->library('squareup');

        if ($result['is_merchant_transaction'] && !in_array($transaction['transaction_type'], array('VOIDED', 'FAILED')) && !$this->isTransactionFullyRefunded($transaction)) {
            // Fetch the transaction and check for changes
            $updated_transaction = $this->squareup_api->getTransaction($transaction['location_id'], $transaction['transaction_id']);

            $updated_status = $updated_transaction['tenders'][0]['card_details']['status'];
            $updated_refunds = !empty($updated_transaction['refunds']) ? $updated_transaction['refunds'] : array();

            $result['type'] = $updated_status;
            $result['text'] = $this->language->get('entry_status_' . strtolower($result['type']));

            if ($updated_status == 'VOIDED') {
                // If transaction has been voided from the Square Dashboard
                $result['order_history_data'] = array(
                    'notify' => 1,
                    'order_id' => $transaction['order_id'],
                    'order_status_id' => $this->getOrderStatusId($transaction['order_id'], 'voided'),
                    'comment' => $this->language->get('squareup_status_comment_voided'),
                );
            } else if ($this->refundsAreDifferent($refunds, $updated_refunds)) {
                // We have some new refunds... Include them in the order history and in the result
                $refunds = $updated_refunds;
                $detected_new_refund = true;
            }

            $this->updateTransaction($transaction['squareup_transaction_id'], $updated_status, $updated_refunds);
        }

        $result['refunds'] = $refunds;

        if (!empty($refunds)) {
            $refunded_amount = 0;
            $has_pending = false;

            foreach ($refunds as $refund) {
                if ($refund['status'] == 'REJECTED' || $refund['status'] == 'FAILED') {
                    continue;
                }

                if ($refund['status'] == 'PENDING') {
                    $has_pending = true;
                }

                $refunded_amount += $refund['amount_money']['amount'];
            }

            $result['amount_refunded'] = $this->currency->format(
                $this->squareup_api->standardDenomination($refunded_amount, $transaction['transaction_currency']),
                $transaction['transaction_currency']
            );

            if ($refunded_amount == $this->squareup_api->lowestDenomination($transaction['transaction_amount'], $transaction['transaction_currency'])) {
                $result['text'] = $this->language->get('text_fully_refunded');
                $result['is_fully_refunded'] = true;
            } else {
                $result['text'] = $this->language->get('text_partially_refunded');
            }

            if ($has_pending) {
                $result['text'] = sprintf($this->language->get('text_refund_pending'), $result['text']);
            }

            if ($detected_new_refund) {
                if ($result['is_fully_refunded']) {
                    $order_history_data = array(
                        'notify' => 1,
                        'order_id' => $transaction['order_id'],
                        'order_status_id' => $this->getOrderStatusId($transaction['order_id'], 'fully_refunded'),
                        'comment' => $this->language->get('text_fully_refunded_comment')
                    );
                } else {
                    $order_history_data = array(
                        'notify' => 1,
                        'order_id' => $transaction['order_id'],
                        'order_status_id' => $this->getOrderStatusId($transaction['order_id'], 'partially_refunded'),
                        'comment' => $this->language->get('text_partially_refunded_comment')
                    );
                }

                $result['order_history_data'] = $order_history_data;
            }
        }

        return $result;
    }

    public function createIndexes() {
        foreach ($this->indexes as $index) {
            $name = 'square_' . $index['column'];
            $table = DB_PREFIX . $index['table'];

            if (!$this->indexExists($table, $name)) {
                $this->db->query("ALTER TABLE `" . $table . "` ADD INDEX " . $name . " (" . $index['column'] . ")");
            }
        }
    }

    public function indexExists($table, $name) {
        foreach ($this->db->query("SHOW INDEX FROM " . $table)->rows as $index) {
            if ($index['Key_name'] == $name) {
                return true;
            }
        }

        return false;
    }

    public function dropIndexes() {
        foreach ($this->indexes as $index) {
            $name = 'square_' . $index['column'];
            $table = DB_PREFIX . $index['table'];

            if ($this->indexExists($table, $name)) {
                $this->db->query("ALTER TABLE `" . $table . "` DROP INDEX " . $name);
            }
        }
    }

    public function createTables() {
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "squareup_transaction` (
          `squareup_transaction_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
          `transaction_id` varchar(255) NOT NULL,
          `merchant_id` char(32) NOT NULL,
          `location_id` varchar(32) NOT NULL,
          `square_customer_id` varchar(32) NOT NULL,
          `order_id` int(11) NOT NULL,
          `transaction_type` char(20) NOT NULL,
          `transaction_amount` decimal(15,2) NOT NULL,
          `transaction_currency` char(3) NOT NULL,
          `billing_address_city` char(100) NOT NULL,
          `billing_address_company` char(100) NOT NULL,
          `billing_address_country` char(3) NOT NULL,
          `billing_address_postcode` char(10) NOT NULL,
          `billing_address_province` char(20) NOT NULL,
          `billing_address_street_1` char(100) NOT NULL,
          `billing_address_street_2` char(100) NOT NULL,
          `device_browser` varchar(255) NOT NULL,
          `device_ip` char(15) NOT NULL,
          `created_at` char(29) NOT NULL,
          `is_refunded` tinyint(1) NOT NULL,
          `refunded_at` varchar(29) NOT NULL,
          `tenders` text NOT NULL,
          `refunds` text NOT NULL,
          PRIMARY KEY (`squareup_transaction_id`),
          KEY `order_id` (`order_id`),
          KEY `transaction_id` (`transaction_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "squareup_token` (
         `squareup_token_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
         `customer_id` int(11) NOT NULL,
         `sandbox` tinyint(1) NOT NULL,
         `token` char(40) NOT NULL,
         `date_added` datetime NOT NULL,
         `brand` VARCHAR(32) NOT NULL,
         `ends_in` VARCHAR(4) NOT NULL,
         PRIMARY KEY (`squareup_token_id`),
         KEY `getCards` (`customer_id`, `sandbox`),
         KEY `verifyCardCustomer` (`squareup_token_id`, `customer_id`),
         KEY `cardExists` (`customer_id`, `brand`, `ends_in`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "squareup_customer` (
         `customer_id` int(11) NOT NULL,
         `sandbox` tinyint(1) NOT NULL,
         `squareup_token_id` int(11) unsigned NOT NULL,
         `square_customer_id` varchar(32) NOT NULL,
         PRIMARY KEY (`customer_id`, `sandbox`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "squareup_catalog` (
         `type` enum('ITEM', 'ITEM_VARIATION', 'MODIFIER', 'MODIFIER_LIST', 'CATEGORY', 'DISCOUNT', 'TAX') NOT NULL,
         `square_id` varchar(24),
         `version` varchar(13),
         `data` MEDIUMTEXT,
         `present_at_all_locations` tinyint(1),
         `present_at_location_ids` TEXT,
         `absent_at_location_ids` TEXT,
         PRIMARY KEY (`type`, `square_id`),
         UNIQUE KEY `square_id` (`square_id`),
         INDEX `square_id_version` (`square_id`, `version`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "squareup_product_option_modifier_list` (
         `squareup_product_option_modifier_list_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
         `product_option_id` int(11),
         `square_id` varchar(24),
         `version` varchar(13),
         PRIMARY KEY (`squareup_product_option_modifier_list_id`),
         UNIQUE KEY `square_id_product_option_id` (`square_id`, `product_option_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "squareup_product_option_value_modifier` (
         `squareup_product_option_value_modifier_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
         `product_option_value_id` int(11),
         `square_id` varchar(24),
         `version` varchar(13),
         PRIMARY KEY (`squareup_product_option_value_modifier_id`),
         UNIQUE KEY `square_id_product_option_value_id` (`square_id`, `product_option_value_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "squareup_option_modifier_list` (
         `squareup_option_modifier_list_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
         `option_id` int(11),
         `square_id` varchar(24),
         `version` varchar(13),
         PRIMARY KEY (`squareup_option_modifier_list_id`),
         UNIQUE KEY `square_id_option_id` (`square_id`, `option_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "squareup_option_value_modifier` (
         `squareup_option_value_modifier_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
         `option_value_id` int(11),
         `square_id` varchar(24),
         `version` varchar(13),
         PRIMARY KEY (`squareup_option_value_modifier_id`),
         UNIQUE KEY `square_id` (`square_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "squareup_category_category` (
         `squareup_category_category_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
         `category_id` int(11),
         `square_id` varchar(24),
         `version` varchar(13),
         PRIMARY KEY (`squareup_category_category_id`),
         UNIQUE KEY `square_id` (`square_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "squareup_product_item` (
         `squareup_product_item_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
         `product_id` int(11),
         `square_id` varchar(24),
         `version` varchar(13),
         PRIMARY KEY (`squareup_product_item_id`),
         INDEX `square_id` (`square_id`),
         UNIQUE KEY `product_id_square_id` (`product_id`, `square_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "squareup_product_item_image` (
         `image` varchar(255) NOT NULL,
         `url` TEXT NOT NULL,
         PRIMARY KEY (`image`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "squareup_combination_item_variation` (
         `squareup_combination_item_variation_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
         `squareup_combination_id` int(11),
         `square_id` varchar(24),
         `version` varchar(13),
         PRIMARY KEY (`squareup_combination_item_variation_id`),
         UNIQUE KEY `square_id` (`square_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "squareup_tax_rate_tax` (
         `squareup_tax_rate_tax_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
         `tax_rate_id` int(11),
         `square_id` varchar(24),
         `version` varchar(13),
         PRIMARY KEY (`squareup_tax_rate_tax_id`),
         UNIQUE KEY `square_id` (`square_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "squareup_combination` (
         `squareup_combination_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
         `product_id` int(11),
         `quantity` int(11),
         `name` varchar(255),
         `sku` varchar(255),
         `upc` varchar(255),
         `price` decimal(15,4),
         `subtract` tinyint(1),
         `var` varchar(255),
         PRIMARY KEY (`squareup_combination_id`),
         UNIQUE KEY `unique_combination` (`product_id`, `name`(145), `var`(30))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "squareup_diff` (
         `squareup_diff_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
         `diff_id` varchar(32) NOT NULL,
         `diff_type` enum('cron', 'inventory', 'order') NOT NULL,
         `type` enum('create_opencart', 'update_opencart', 'delete_opencart', 'upsert_square', 'delete_square', 'disassociate_square') NOT NULL,
         `data` MEDIUMTEXT,
         `last_id_map` TEXT,
         `attempt` int(11) NOT NULL DEFAULT '0',
         `status` int(11) NOT NULL DEFAULT '-1',
         `created_on` datetime,
         `executed_on` datetime,
         `message` TEXT NOT NULL,
         PRIMARY KEY (`squareup_diff_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "squareup_restock` (
         `squareup_restock_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
         `order_product_id` int(11) NOT NULL,
         `quantity` int(11),
         PRIMARY KEY (`squareup_restock_id`),
         KEY (`order_product_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "squareup_refund` (
         `squareup_refund_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
         `order_product_id` int(11) NOT NULL,
         `quantity` int(11),
         PRIMARY KEY (`squareup_refund_id`),
         KEY (`order_product_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "squareup_ad_hoc` (
         `squareup_ad_hoc_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
         `order_product_id` int(11) NOT NULL,
         PRIMARY KEY (`squareup_ad_hoc_id`),
         KEY (`order_product_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    }

    public function dropTables() {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "squareup_ad_hoc`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "squareup_catalog`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "squareup_category_category`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "squareup_combination_item_variation`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "squareup_combination`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "squareup_customer`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "squareup_diff`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "squareup_option_modifier_list`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "squareup_option_value_modifier`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "squareup_product_item_image`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "squareup_product_item`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "squareup_product_option_modifier_list`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "squareup_product_option_value_modifier`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "squareup_refund`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "squareup_restock`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "squareup_tax_rate_tax`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "squareup_token`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "squareup_transaction`");
    }

    public function truncateMerchantSpecificTables() {
        $this->db->query("TRUNCATE TABLE `" . DB_PREFIX . "squareup_catalog`");
        $this->db->query("TRUNCATE TABLE `" . DB_PREFIX . "squareup_category_category`");
        $this->db->query("TRUNCATE TABLE `" . DB_PREFIX . "squareup_combination_item_variation`");
        $this->db->query("TRUNCATE TABLE `" . DB_PREFIX . "squareup_diff`");
        $this->db->query("TRUNCATE TABLE `" . DB_PREFIX . "squareup_option_modifier_list`");
        $this->db->query("TRUNCATE TABLE `" . DB_PREFIX . "squareup_option_value_modifier`");
        $this->db->query("TRUNCATE TABLE `" . DB_PREFIX . "squareup_product_item`");
        $this->db->query("TRUNCATE TABLE `" . DB_PREFIX . "squareup_product_item_image`");
        $this->db->query("TRUNCATE TABLE `" . DB_PREFIX . "squareup_product_option_modifier_list`");
        $this->db->query("TRUNCATE TABLE `" . DB_PREFIX . "squareup_product_option_value_modifier`");
        $this->db->query("TRUNCATE TABLE `" . DB_PREFIX . "squareup_tax_rate_tax`");
        $this->db->query("TRUNCATE TABLE `" . DB_PREFIX . "squareup_token`");
        $this->db->query("TRUNCATE TABLE `" . DB_PREFIX . "squareup_customer`");
    }

    public function alterTables() {
        $squareup_transaction_columns = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "squareup_transaction`");
        $squareup_customer_columns = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "squareup_customer`");

        $found_transaction_id = false;
        $found_transaction_square_customer_id = false;
        $found_customer_squareup_token_id = false;

        foreach ($squareup_transaction_columns->rows as $column) {
            if ($column['Field'] == 'transaction_id' && strtolower($column['Type']) == 'char(40)') {
                $found_transaction_id = true;
            }

            if ($column['Field'] == 'square_customer_id') {
                $found_transaction_square_customer_id = true;
            }
        }

        foreach ($squareup_customer_columns->rows as $column) {
            if ($column['Field'] == 'squareup_token_id') {
                $found_customer_squareup_token_id = true;
            }
        }

        if (!$found_transaction_id) {
            $this->db->query("ALTER TABLE `" . DB_PREFIX . "squareup_transaction` MODIFY transaction_id varchar(255) NOT NULL");
        }

        if (!$found_transaction_square_customer_id) {
            $this->db->query("ALTER TABLE `" . DB_PREFIX . "squareup_transaction` ADD COLUMN square_customer_id varchar(32) NOT NULL");
        }

        if (!$found_customer_squareup_token_id) {
            $this->db->query("ALTER TABLE `" . DB_PREFIX . "squareup_customer` ADD COLUMN squareup_token_id int(11) unsigned NOT NULL");
        }
    }

    public function setTableIndexes() {

    }

    public function createEvents() {
        $events = array(
            'admin/controller/*/after' => 'extension/payment/squareup/setAdminURL',
            'admin/view/common/column_left/before' => 'extension/payment/squareup/setAdminLink',
            'admin/view/catalog/product_form/before' => 'extension/payment/squareup/setProductWarning',
            'catalog/model/checkout/order/addOrderHistory/before' => 'extension/payment/squareup/beforeAddOrderHistory',
            'catalog/model/checkout/order/addOrderHistory/after' => 'extension/payment/squareup/afterAddOrderHistory'
        );

        $this->load->model('setting/event');

        foreach ($events as $trigger => $action) {
            $this->model_setting_event->addEvent('payment_squareup', $trigger, $action, 1, 0);
        }
    }

    public function dropEvents() {
        $this->load->model('setting/event');

        $this->model_setting_event->deleteEventByCode('payment_squareup');
    }

    public function inferOrderStatusId($search) {
        $order_status = $this->db->query("SELECT order_status_id FROM `" . DB_PREFIX . "order_status` WHERE LOWER(name) LIKE '" . $this->db->escape(strtolower($search)) . "%' ORDER BY LENGTH(name) ASC LIMIT 1");

        return $order_status->num_rows > 0 ? (int)$order_status->row['order_status_id'] : 0;
    }

    public function missingPreliminaryGeoZones() {
        return !$this->existsGeoZoneByName('%');
    }

    public function getPredefinedCountries() {
        return $this->predefinedCountries;
    }

    public function setupGeoZones() {
        $this->load->model('localisation/country');
        $this->load->model('localisation/zone');
        $this->load->model('localisation/geo_zone');

        // USA Geo Zones
        $this->geoZonePerCountryZone('USA', 'USA');

        // Canada Geo Zones
        $this->geoZonePerCountryZone('CAN', 'Canada');

        // Japan Geo Zone
        $this->geoZonePerCountryList(array('JPN'), 'Japan');

        // EU and UK
        $this->geoZonePerCountryList(array_diff($this->predefinedCountries, $this->nonEuCountries), 'EU/UK');

        // Store country, if needed
        $country_info = $this->model_localisation_country->getCountry($this->config->get('config_country_id'));

        if (!in_array($country_info['iso_code_3'], $this->predefinedCountries)) {
            $this->geoZonePerCountryList(array($country_info['iso_code_3']), $country_info['name']);
        }
    }

    public function updateTaxRateGeoZone($tax_rate_id, $geo_zone_id) {
        $this->db->query("UPDATE `" . DB_PREFIX . "tax_rate` SET geo_zone_id='" . (int)$geo_zone_id . "' WHERE tax_rate_id='" . (int)$tax_rate_id . "'");
    }

    public function countOpenCartProducts() {
        return (int)$this->db->query("SELECT COUNT(*) as total FROM `" . DB_PREFIX . "product`")->row['total'];
    }

    public function getLocationCurrency($locations, $location_id) {
        if (!empty($locations) && !empty($location_id)) {
            foreach ($locations as $location) {
                if ($location['id'] == $location_id) {
                    return $location['currency'];
                }
            }
        }

        return null;
    }

    public function setupApplePayDomainVerificationFile() {
        // Find root dir
        $parts = parse_url(HTTP_CATALOG);
        $dir = dirname(DIR_APPLICATION);

        if (!empty($parts['path'])) {
            $path = trim($parts['path'], '/');
            $steps = count(array_filter(explode('/', $path)));
        } else {
            $steps = 0;
        }

        for ($i = 0; $i < $steps; $i++) {
            $dir = dirname($dir);
        }

        // $dir is the root dir. Now copy the verification file to .well-known
        $target_dir = $dir . '/.well-known';

        if (!@is_dir($target_dir)) {
            @mkdir($target_dir, 0755);
        }

        $copy =
            @is_dir($target_dir) &&
            @is_writable($target_dir) &&
            @copy(
                DIR_SYSTEM . 'library/squareup/apple_pay/apple-developer-merchantid-domain-association',
                $target_dir . '/apple-developer-merchantid-domain-association');

        if (!$copy) {
            throw new \Squareup\Exception\Api($this->registry, sprintf($this->language->get("error_cannot_copy_verification"), $target_dir));
        }

        if (!empty($parts['host'])) {
            return $parts['host'];
        }
    }

    //start column check
    public function updateDatabase() {
        foreach ($this->changes as $change) {
            if (!$this->columnExists($change['table'], $change['new_column']) && $this->columnExists($change['table'], $change['old_column'])) {
                $this->renameColumn($change['table'], $change['old_column'], $change['new_column'], $change['column_definition']);
            }
        }
        foreach ($this->additions as $addition) {
            if (!$this->columnExists($addition['table'], $addition['column_name'])) {
                $this->createColumn($addition['table'], $addition['column_name'], $addition['column_definition']);
            }
        }
        return false;
    }

    private function columnExists($table, $column) {
        $query = $this->db->query("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema LIKE '%". $this->database_name ."%' AND table_name LIKE '%". $table ."%' AND column_name LIKE '". $column ."'");
        if ($query->row > 0) {
            return true;
        }else{
            return false;
        }
    }

    private function renameColumn($table, $old_column, $new_column, $column_definition) {
        $query = $this->db->query("ALTER TABLE " . DB_PREFIX . $table . " CHANGE " . $old_column . " " . $new_column . " " . $column_definition);
        return $query;
    }

    private function createColumn($table, $column_name, $column_definition) {
        $query = $this->db->query("ALTER TABLE " . DB_PREFIX . $table . " ADD COLUMN " . $column_name . " " . $column_definition);
        return $query;
    }
    // end column check

    private function getCountryIdByISO3($iso_code_3) {
        $query = $this->db->query("SELECT country_id FROM " . DB_PREFIX . "country WHERE iso_code_3 = '" . $this->db->escape($iso_code_3) . "'");

        return $query->row['country_id'];
    }

    private function existsGeoZoneByName($name) {
        $sql = "SELECT geo_zone_id FROM `" . DB_PREFIX . "geo_zone` WHERE name LIKE '" . $this->db->escape($name) . "' AND description LIKE '(Square)%'";

        return $this->db->query($sql)->num_rows > 0;
    }

    private function geoZonePerCountryZone($iso_code_3, $country_name) {
        $country_id = $this->getCountryIdByISO3($iso_code_3);

        foreach ($this->model_localisation_zone->getZonesByCountryId($country_id) as $zone) {
            $name = $country_name . ',' . $zone['code'];

            if (!$this->existsGeoZoneByName($name)) {
                $data = array(
                    'name' => $name,
                    'description' => '(Square) Created on: ' . date($this->language->get('datetime_format')),
                    'zone_to_geo_zone' => array(
                        array(
                            'country_id' => $country_id,
                            'zone_id' => $zone['zone_id']
                        )
                    )
                );

                $this->model_localisation_geo_zone->addGeoZone($data);
            }
        }
    }

    private function geoZonePerCountryList($iso_code_3_list, $geo_zone_name) {
        $zone_to_geo_zones = array();

        foreach ($iso_code_3_list as $iso_code_3) {
            $zone_to_geo_zones[] = array(
                'country_id' => $this->getCountryIdByISO3($iso_code_3),
                'zone_id' => 0
            );
        }

        if (!$this->existsGeoZoneByName($geo_zone_name)) {
            $data = array(
                'name' => $geo_zone_name,
                'description' => '(Square) Created on: ' . date($this->language->get('datetime_format')),
                'zone_to_geo_zone' => $zone_to_geo_zones
            );

            $this->model_localisation_geo_zone->addGeoZone($data);
        }
    }

    private function applyTaxRateInfo($tax_rate) {
        $tax_rate['suggested_geo_zone_id'] = $this->squareup_diff_square_tax->inferGeoZoneIdFromName($tax_rate['name']);
        $tax_rate['percentage'] = number_format((float)$tax_rate['rate'], 2, '.', '') . '%';

        return $tax_rate;
    }
}
