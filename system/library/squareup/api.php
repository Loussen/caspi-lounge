<?php

namespace Squareup;

class Api extends Library {
    const API_URL = 'https://connect.squareup.com';
    const API_VERSION = 'v2';
    const API_VERSION_HEADER = '2018-09-18';
    const HREF_SQUARE_ITEM = 'https://squareup.com/dashboard/items/library/%s';
    const ENDPOINT_APPLE_PAY = 'apple-pay/domains';
    const ENDPOINT_ADD_CARD = 'customers/%s/cards';
    const ENDPOINT_AUTH = 'oauth2/authorize';
    const ENDPOINT_BATCH_CHANGE_INVENTORY = 'inventory/batch-change';
    const ENDPOINT_BATCH_DELETE = 'catalog/batch-delete';
    const ENDPOINT_BATCH_RETRIEVE = 'catalog/batch-retrieve';
    const ENDPOINT_BATCH_RETRIEVE_INVENTORY_COUNTS = 'inventory/batch-retrieve-counts';
    const ENDPOINT_BATCH_RETRIEVE_INVENTORY_CHANGES = 'inventory/batch-retrieve-changes';
    const ENDPOINT_BATCH_UPSERT = 'catalog/batch-upsert';
    const ENDPOINT_CAPTURE_TRANSACTION = 'locations/%s/transactions/%s/capture';
    const ENDPOINT_CUSTOMERS = 'customers';
    const ENDPOINT_DELETE_CARD = 'customers/%s/cards/%s';
    const ENDPOINT_GET_TRANSACTION = 'locations/%s/transactions/%s';
    const ENDPOINT_LIST_CATALOG = 'catalog/list';
    const ENDPOINT_LOCATIONS = 'locations';
    const ENDPOINT_ORDERS = 'locations/%s/orders';
    const ENDPOINT_REFRESH_TOKEN = 'oauth2/clients/%s/access-token/renew';
    const ENDPOINT_REFUND_TRANSACTION = 'locations/%s/transactions/%s/refund';
    const ENDPOINT_TOKEN = 'oauth2/token';
    const ENDPOINT_TRANSACTIONS = 'locations/%s/transactions';
    const ENDPOINT_VOID_TRANSACTION = 'locations/%s/transactions/%s/void';
    const ENDPOINT_WEBHOOKS = 'v1/%s/webhooks';
    const ENDPOINT_MERCHANT_INFO = 'v1/me';
    const ENDPOINT_UPLOAD_IMAGE = 'v1/%s/items/%s/image';
    const SCOPE = 'MERCHANT_PROFILE_READ PAYMENTS_READ PAYMENTS_WRITE SETTLEMENTS_READ CUSTOMERS_READ CUSTOMERS_WRITE ITEMS_READ ITEMS_WRITE ORDERS_WRITE INVENTORY_READ INVENTORY_WRITE';

    public function itemLink($item_id) {
        return sprintf(self::HREF_SQUARE_ITEM, $item_id);
    }

    public function registerApplePayDomain($domain) {
        $request_data = array(
            'method' => 'POST',
            'endpoint' => self::ENDPOINT_APPLE_PAY,
            'auth_type' => 'Bearer',
            'parameters' => array(
                'domain_name' => $domain
            )
        );

        return $this->api($request_data);
    }

    public function verifyToken($access_token, &$first_location_id) {
        $locations = null;

        try {
            $request_data = array(
                'method' => 'GET',
                'endpoint' => self::ENDPOINT_LOCATIONS,
                'auth_type' => 'Bearer',
                'token' => $access_token
            );

            $api_result = $this->api($request_data);

            $locations = array_filter($api_result['locations'], array($this, 'filterLocation'));

            if (!empty($locations)) {
                $first_location = current($locations);
                $first_location_id = $first_location['id'];
            } else {
                $first_location_id = null;
            }
        } catch (\Squareup\Exception\Api $e) {
            if ($e->isAccessTokenRevoked() || $e->isAccessTokenExpired()) {
                return null;
            }

            // In case some other error occurred
            throw $e;
        }

        return $locations;
    }

    public function updateWebhookPermissions($location_id, $permissions) {
        $request_data = array(
            'method' => 'PUT',
            'endpoint' => sprintf(self::ENDPOINT_WEBHOOKS, $location_id),
            'no_version' => true, // The version is included in the endpoint
            'parameters' => $permissions,
            'auth_type' => 'Bearer'
        );

        $return = $this->api($request_data);

        // API v1 error handling
        if (!empty($return['type']) && !empty($return['message']) && $return['type'] == 'bad_request') {
            throw new \Squareup\Exception\Api($this->registry, $return['message']);
        }
    }

    public function getMerchantName() {
        $request_data = array(
            'method' => 'GET',
            'endpoint' => self::ENDPOINT_MERCHANT_INFO,
            'no_version' => true, // The version is included in the endpoint
            'auth_type' => 'Bearer'
        );

        $merchant = $this->api($request_data);

        if (isset($merchant['name'])) {
            return $merchant['name'];
        }

        return '';
    }

    protected function getCurlFile($filepath, $mime_type) {
        if (version_compare(PHP_VERSION, '5.5', '>=')) {
            return new \CURLFile($filepath, $mime_type);
        } else {
            return '@' . realpath($filepath) . ($mime_type ? ";type=$mime_type" : '');
        }
    }

    public function uploadImage($filepath, $item_id) {
        $parameters = array(
            'image_data' => $this->getCurlFile($filepath, 'image/jpeg')
        );

        $request_data = array(
            'method' => 'POST',
            'endpoint' => sprintf(self::ENDPOINT_UPLOAD_IMAGE, $this->config->get('payment_squareup_location_id'), $item_id),
            'no_version' => true, // The version is included in the endpoint
            'content_type' => 'multipart/form-data',
            'parameters' => $parameters,
            'auth_type' => 'Bearer'
        );

        $return = $this->api($request_data);

        // API v1 error handling
        if (empty($return['url']) || empty($return['id'])) {
            throw new \Squareup\Exception\Api($this->registry, "Cannot upload image for " . $item_id);
        }

        return $return;
    }

    public function authLink($client_id) {
        $state = $this->authState();

        $redirect_uri = str_replace('&amp;', '&', $this->url->link('extension/payment/squareup/oauth_callback', 'user_token=' . $this->session->data['user_token'], true));

        $this->session->data['payment_squareup_oauth_redirect'] = $redirect_uri;

        $params = array(
            'client_id' => $client_id,
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'locale' => 'en-US',
            'session' => 'false',
            'state' => $state,
            'redirect_uri' => $redirect_uri
        );

        return self::API_URL . '/' . self::ENDPOINT_AUTH . '?' . http_build_query($params);
    }

    public function countSquareItems() {
        $count = 0;

        do {
            $cursor = isset($result['cursor']) ? $result['cursor'] : '';
            $types = array('ITEM');

            $result = $this->listCatalog($cursor, $types);

            if (isset($result['objects']) && is_array($result['objects'])) {
                $count += count($result['objects']);
            }
        } while (isset($result['cursor']));

        return $count;
    }

    public function getInventoryAfter($cursor, $timestamp, $location_ids) {
        $parameters = array();

        if ($cursor) {
            $parameters['cursor'] = $cursor;
        }

        $parameters['location_ids'] = $location_ids;
        $parameters['updated_after'] = date('Y-m-d', $timestamp) . 'T' . date('H:i:sP', $timestamp); // RFC 3339

        $request_data = array(
            'method' => 'POST',
            'endpoint' => self::ENDPOINT_BATCH_RETRIEVE_INVENTORY_CHANGES,
            'parameters' => $parameters,
            'auth_type' => 'Bearer'
        );

        return $this->api($request_data);
    }

    public function getInventories($cursor, $location_ids, $catalog_object_ids) {
        $parameters = array();

        if ($cursor) {
            $parameters['cursor'] = $cursor;
        }

        $parameters['location_ids'] = $location_ids;
        $parameters['catalog_object_ids'] = $catalog_object_ids;

        $request_data = array(
            'method' => 'POST',
            'endpoint' => self::ENDPOINT_BATCH_RETRIEVE_INVENTORY_COUNTS,
            'parameters' => $parameters,
            'auth_type' => 'Bearer'
        );

        return $this->api($request_data);
    }

    public function pushInventoryAdjustments($adjustments) {
        $parameters = array();

        $itempotency_key = md5(microtime(true));

        $parameters['idempotency_key'] = $itempotency_key;
        $parameters['changes'] = array();

        foreach ($adjustments as $adjustment) {
            $parameters['changes'][] = array(
                'type' => 'ADJUSTMENT',
                'adjustment' => array(
                    'from_state' => (string)$adjustment['from_state'],
                    'to_state' => (string)$adjustment['to_state'],
                    'location_id' => $this->config->get('payment_squareup_location_id'),
                    'catalog_object_id' => (string)$adjustment['catalog_object_id'],
                    'quantity' => (string)$adjustment['quantity'],
                    'occurred_at' => date('Y-m-d') . 'T' . date('H:i:sP') // RFC 3339
                )
            );
        }

        $request_data = array(
            'method' => 'POST',
            'endpoint' => self::ENDPOINT_BATCH_CHANGE_INVENTORY,
            'parameters' => $parameters,
            'auth_type' => 'Bearer'
        );

        return $this->api($request_data);
    }

    public function listItems($object_ids) {
        $data = array(
            'object_ids' => $object_ids,
            'include_related_objects' => true
        );

        $request_data = array(
            'method' => 'POST',
            'endpoint' => self::ENDPOINT_BATCH_RETRIEVE,
            'auth_type' => 'Bearer',
            'parameters' => $data
        );

        return $this->api($request_data);
    }

    public function listCatalog($cursor, $types = array()) {
        $get_param = array();

        if ($cursor) {
            $get_param[] = 'cursor=' . $cursor;
        }

        if ($types) {
            $get_param[] = 'types=' . implode(',', $types);
        }

        $request_data = array(
            'method' => 'GET',
            'endpoint' => self::ENDPOINT_LIST_CATALOG . ($get_param ? '?' . implode('&', $get_param) : ''),
            'auth_type' => 'Bearer'
        );

        return $this->api($request_data);
    }

    public function batchUpsertCatalog($data) {
        $request_data = array(
            'method' => 'POST',
            'endpoint' => self::ENDPOINT_BATCH_UPSERT,
            'auth_type' => 'Bearer',
            'parameters' => $data
        );

        return $this->api($request_data);
    }

    public function batchDeleteCatalog($data) {
        $request_data = array(
            'method' => 'POST',
            'endpoint' => self::ENDPOINT_BATCH_DELETE,
            'auth_type' => 'Bearer',
            'parameters' => $data
        );

        return $this->api($request_data);
    }

    public function fetchLocations($access_token, &$first_location_id) {
        $request_data = array(
            'method' => 'GET',
            'endpoint' => self::ENDPOINT_LOCATIONS,
            'auth_type' => 'Bearer',
            'token' => $access_token
        );

        $api_result = $this->api($request_data);

        $locations = array_filter($api_result['locations'], array($this, 'filterLocation'));

        if (!empty($locations)) {
            $first_location = current($locations);
            $first_location_id = $first_location['id'];
        } else {
            $first_location_id = null;
        }

        return $locations;
    }

    public function exchangeCodeForAccessToken($code) {
        $request_data = array(
            'method' => 'POST',
            'endpoint' => self::ENDPOINT_TOKEN,
            'no_version' => true,
            'parameters' => array(
                'client_id' => $this->session->data['payment_squareup_connect']['payment_squareup_client_id'],
                'client_secret' => $this->session->data['payment_squareup_connect']['payment_squareup_client_secret'],
                'redirect_uri' => $this->session->data['payment_squareup_oauth_redirect'],
                'code' => $code
            )
        );

        return $this->api($request_data);
    }

    public function refreshToken() {
        $request_data = array(
            'method' => 'POST',
            'endpoint' => sprintf(self::ENDPOINT_REFRESH_TOKEN, $this->config->get('payment_squareup_client_id')),
            'no_version' => true,
            'auth_type' => 'Client',
            'token' => $this->config->get('payment_squareup_client_secret'),
            'parameters' => array(
                'access_token' => $this->config->get('payment_squareup_access_token')
            )
        );

        return $this->api($request_data);
    }

    public function addCard($square_customer_id, $card_data) {
        $request_data = array(
            'method' => 'POST',
            'endpoint' => sprintf(self::ENDPOINT_ADD_CARD, $square_customer_id),
            'auth_type' => 'Bearer',
            'parameters' => $card_data
        );

        $result = $this->api($request_data);

        return array(
            'id' => $result['card']['id'],
            'card_brand' => $result['card']['card_brand'],
            'last_4' => $result['card']['last_4']
        );
    }

    public function deleteCard($square_customer_id, $card) {
        $request_data = array(
            'method' => 'DELETE',
            'endpoint' => sprintf(self::ENDPOINT_DELETE_CARD, $square_customer_id, $card),
            'auth_type' => 'Bearer'
        );

        return $this->api($request_data);
    }

    public function addLoggedInCustomer() {
        $request_data = array(
            'method' => 'POST',
            'endpoint' => self::ENDPOINT_CUSTOMERS,
            'auth_type' => 'Bearer',
            'parameters' => array(
                'given_name' => $this->customer->getFirstName(),
                'family_name' => $this->customer->getLastName(),
                'email_address' => $this->customer->getEmail(),
                'reference_id' => $this->customer->getId()
            )
        );

        $result = $this->api($request_data);

        return array(
            'customer_id' => $this->customer->getId(),
            'square_customer_id' => $result['customer']['id']
        );
    }

    public function addCustomer($given_name, $family_name, $email_address) {
        $request_data = array(
            'method' => 'POST',
            'endpoint' => self::ENDPOINT_CUSTOMERS,
            'auth_type' => 'Bearer',
            'parameters' => array(
                'given_name' => $given_name,
                'family_name' => $family_name,
                'email_address' => $email_address
            )
        );

        $result = $this->api($request_data);

        return $result['customer'];
    }

    public function createRecurringOrder($order_id, $price, $is_trial, $quantity, $currency) {
        $location_id = $this->config->get('payment_squareup_location_id');

        $parameters = array();

        $line_items[] = array(
            'quantity' => (string)$quantity,
            'name' => 'Recurring Payment',
            'base_price_money' => array(
                'amount' => $this->lowestDenomination($price, $currency),
                'currency' => $currency
            ),
            'variation_name' => 'Order #' . $order_id,
        );

        $parameters['line_items'] = $line_items;
        $parameters['idempotency_key'] = md5(microtime(true));

        $request_data = array(
            'method' => 'POST',
            'endpoint' => sprintf(self::ENDPOINT_ORDERS, $location_id),
            'auth_type' => 'Bearer',
            'parameters' => $parameters
        );

        $result = $this->api($request_data);

        return $result['order']['id'];
    }

    public function createOrder($order_id, $rounding_adjustment = 0, $resync_on_price_difference = false) {
        unset($this->session->data['squareup_ad_hoc_items']);

        $location_id = $this->config->get('payment_squareup_location_id');

        $this->load->model('checkout/order');
        $this->load->model('catalog/product');

        $parameters = array();

        $line_items = array();
        $discounts = array();

        $total_price = 0;

        $order_products = $this->model_checkout_order->getOrderProducts($order_id);

        if (!empty($order_products)) {
            $this->session->data['squareup_ad_hoc_items'] = array();
        }

        foreach ($order_products as $product) {
            $product_info = $this->model_catalog_product->getProduct($product['product_id']);

            $product_base_price = $product_info['price'];

            if (false === $variation = $this->getProductVariation($product['product_id'], $product['order_product_id'])) {
                $product_base_price = $product['price'];

                $line_item = array(
                    'quantity' => (string)(int)$product['quantity'],
                    'name' => $product['name'],
                    'base_price_money' => $this->convertToSquarePrice($product_base_price),
                    'variation_name' => $product['model']
                );

                $object_price = (float)$product_base_price;

                $this->session->data['squareup_ad_hoc_items'][] = $product['order_product_id'];
            } else {
                $total_modifier_price = 0;

                $line_item = array(
                    'quantity' => (string)(int)$product['quantity'],
                    'catalog_object_id' => $variation['id']
                );

                $modifiers = array();

                foreach ($this->model_checkout_order->getOrderOptions($order_id, $product['order_product_id']) as $option) {
                    if (empty($option['product_option_value_id'])) {
                        continue;
                    }

                    if ($this->config->get('payment_squareup_ad_hoc_sync')) {
                        if (!$this->isRequiredProductOption($option['product_option_value_id'])) {
                            $price = $this->getProductOptionValuePrice($option['product_option_value_id']);

                            $total_modifier_price += $price;

                            // Provide ad-hoc modifiers
                            $modifiers[] = array(
                                'name' => $option['name'] . ": " . $option['value'],
                                'base_price_money' => $this->convertToSquarePrice($price)
                            );
                        }
                    } else {
                        // Use standard catalog object ids
                        if (false !== $modifier = $this->getVariationModifier($option['product_option_value_id'])) {
                            if (isset($modifier['data']['price_money'])) {
                                $total_modifier_price += $this->convertToLocalPrice($modifier['data']['price_money']);
                            }

                            $modifiers[] = array(
                                'catalog_object_id' => $modifier['id']
                            );
                        }
                    }
                }

                if (!empty($modifiers)) {
                    $line_item['modifiers'] = $modifiers;
                }

                $object_price = (float)$variation['price'] + $total_modifier_price;
            }

            $item_discounts = array();
            $item_discounts_value = 0;

            foreach ($this->model_checkout_order->getOrderOptions($order_id, $product['order_product_id']) as $option) {
                if (empty($option['product_option_value_id'])) {
                    continue;
                }

                if (false !== $negative_value = $this->getNegativeProductOptionValue($option['product_option_value_id'])) {
                    $option_negative_value = (int)$product['quantity'] * $negative_value;

                    $item_discounts[] = array(
                        'name' => $option['name'] . ':' . $option['value'],
                        'amount_money' => $this->convertToSquarePrice(abs($option_negative_value))
                    );

                    $item_discounts_value += $option_negative_value; // This is a subtraction because $negative_value is always negative
                }
            }

            if (false !== $negative_price_change = $this->getNegativePriceChange($order_products, $product['product_id'], $product_base_price)) {
                $negative_price_change_value = (int)$product['quantity'] * $negative_price_change['price'];

                $item_discounts[] = array(
                    'name' => $negative_price_change['name'],
                    'amount_money' => $this->convertToSquarePrice(abs($negative_price_change_value))
                );

                $item_discounts_value += $negative_price_change_value; // This is a subtraction because $negative_price_change['price'] is always negative
            }

            $line_item_price = (int)$product['quantity'] * $object_price + $item_discounts_value; // $item_discounts_value is always negative

            $total_price += $line_item_price;

            $taxes = $this->getProductTaxes($product_info['tax_class_id'], $line_item_price);

            if (!empty($taxes['square_taxes'])) {
                $line_item['taxes'] = $taxes['square_taxes'];

                $total_price += $taxes['total'];
            }

            if (!empty($item_discounts)) {
                $line_item['discounts'] = $item_discounts;
            }

            $line_items[] = $line_item;

            if (!empty($taxes['line_items'])) {
                $line_items = array_merge($line_items, $taxes['line_items']);

                $total_price += $taxes['line_items_total'];
            }
        }

        $order_totals = $this->model_checkout_order->getOrderTotals($order_id);

        foreach ($order_totals as $total) {
            if ($total['value'] < 0) {
                $discounts[] = array(
                    'name' => $total['title'],
                    'amount_money' => $this->convertToSquarePrice(abs($total['value']))
                );

                $total_price -= $total['value'];
            } else if (!in_array($total['code'], array('total', 'sub_total', 'tax'))) {
                $line_items[] = array(
                    'quantity' => '1',
                    'name' => $total['title'],
                    'base_price_money' => $this->convertToSquarePrice((float)$total['value']),
                    'variation_name' => 'N/A',
                );

                $total_price += (float)$total['value'];
            }
        }

        $order_info = $this->model_checkout_order->getOrder($order_id);

        // We are subtracting the non-rounded calculated Square total from the rounded OpenCart total
        $price_diff = $this->lowestDenomination($order_info['total'], $order_info['currency_code']) - $this->lowestDenomination($total_price, $order_info['currency_code']);

        if ($price_diff != 0 && $resync_on_price_difference) {
            throw new \Squareup\Exception\Api($this->registry, "SQUAREUP: Re-syncing the items due to price difference!");
        }

        if ($price_diff < 0) {
            // The price differs most probably because of reduced price by product options. We must account for this as a new discount
            $discounts[] = array(
                'name' => 'OpenCart Price Difference',
                'amount_money' => $this->convertToSquarePrice(abs($price_diff), true)
            );
        } else if ($price_diff > 0) {
            // The price differs most probably because of something we missed. Even though we should not go in this case, we must account for this as a new line item, just in case
            $line_items[] = array(
                'quantity' => '1',
                'name' => 'OpenCart Price Difference',
                'base_price_money' => $this->convertToSquarePrice($price_diff, true),
                'variation_name' => 'N/A',
            );
        }

        // Rounding adjustment. Used when the total order price is different than the OC price
        if ($rounding_adjustment > 0) {
            $discounts[] = array(
                'name' => 'Rounding Correction',
                'amount_money' => $this->convertToSquarePrice($rounding_adjustment, true)
            );
        } else if ($rounding_adjustment < 0) {
            $line_items[] = array(
                'quantity' => '1',
                'name' => 'Rounding Correction',
                'base_price_money' => $this->convertToSquarePrice(abs($rounding_adjustment), true),
                'variation_name' => 'N/A',
            );
        }

        $parameters['line_items'] = $line_items;
        $parameters['idempotency_key'] = md5(microtime(true));

        if (!empty($discounts)) {
            $parameters['discounts'] = $discounts;
        }

        $request_data = array(
            'method' => 'POST',
            'endpoint' => sprintf(self::ENDPOINT_ORDERS, $location_id),
            'auth_type' => 'Bearer',
            'parameters' => $parameters
        );

        $result = $this->api($request_data);

        return $result['order'];
    }

    public function addTransaction($data) {
        $location_id = $this->config->get('payment_squareup_location_id');

        $request_data = array(
            'method' => 'POST',
            'endpoint' => sprintf(self::ENDPOINT_TRANSACTIONS, $location_id),
            'auth_type' => 'Bearer',
            'parameters' => $data
        );

        $result = $this->api($request_data);

        return $result['transaction'];
    }

    public function getTransaction($location_id, $transaction_id) {
        $request_data = array(
            'method' => 'GET',
            'endpoint' => sprintf(self::ENDPOINT_GET_TRANSACTION, $location_id, $transaction_id),
            'auth_type' => 'Bearer'
        );

        $result = $this->api($request_data);

        return $result['transaction'];
    }

    public function captureTransaction($location_id, $transaction_id) {
        $request_data = array(
            'method' => 'POST',
            'endpoint' => sprintf(self::ENDPOINT_CAPTURE_TRANSACTION, $location_id, $transaction_id),
            'auth_type' => 'Bearer'
        );

        $this->api($request_data);
    }

    public function voidTransaction($location_id, $transaction_id) {
        $request_data = array(
            'method' => 'POST',
            'endpoint' => sprintf(self::ENDPOINT_VOID_TRANSACTION, $location_id, $transaction_id),
            'auth_type' => 'Bearer'
        );

        $this->api($request_data);
    }

    public function refundTransaction($location_id, $transaction_id, $reason, $amount, $currency, $tender_id) {
        $request_data = array(
            'method' => 'POST',
            'endpoint' => sprintf(self::ENDPOINT_REFUND_TRANSACTION, $location_id, $transaction_id),
            'auth_type' => 'Bearer',
            'parameters' => array(
                'idempotency_key' => uniqid(),
                'tender_id' => $tender_id,
                'reason' => $reason,
                'amount_money' => array(
                    'amount' => $this->lowestDenomination($amount, $currency),
                    'currency' => $currency
                )
            )
        );

        $refund_result = $this->api($request_data);

        $transaction = $this->getTransaction($location_id, $transaction_id);

        $refunds = !empty($transaction['refunds']) ? $transaction['refunds'] : array();
        $found = false;

        foreach ($refunds as $refund) {
            if ($refund['id'] == $refund_result['refund']['id']) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            $refunds[] = $refund_result['refund'];
        }

        $transaction['refunds'] = $refunds;

        return $transaction;
    }

    public function roundPrice($value, $currency) {
        $power = $this->currency->getDecimalPlace($currency);

        return (float)round((float)$value, $power);
    }

    public function lowestDenomination($value, $currency) {
        $power = $this->currency->getDecimalPlace($currency);

        $value = (float)$value;

        return (int)bcmul($value, pow(10, $power));
    }

    public function standardDenomination($value, $currency) {
        $power = $this->currency->getDecimalPlace($currency);

        $value = (int)$value;

        return (float)($value / pow(10, $power));
    }

    public function convertToLocalPrice($square_money) {
        $amount = $square_money['amount'];
        $currency = $square_money['currency'];

        $standard_amount = $this->standardDenomination($amount, $currency);

        return $standard_amount;
    }

    public function convertToSquarePrice($price, $in_lowest = false) {
        $to = $this->config->get('config_currency');

        return array(
            'amount' => !$in_lowest ? $this->lowestDenomination($price, $to) : $price,
            'currency' => $to
        );
    }

    public function getProductVariation($product_id, $order_product_id) {
        if ($this->config->get('payment_squareup_sync_source') != 'opencart') {
            return false;
        }

        $var = array();

        $sql_order_options = "SELECT pov.option_id, pov.option_value_id FROM `" . DB_PREFIX . "product_option_value` pov LEFT JOIN `" . DB_PREFIX . "product_option` po ON (po.product_option_id = pov.product_option_id) LEFT JOIN `" . DB_PREFIX . "order_option` oo ON (oo.product_option_id = pov.product_option_id AND oo.product_option_value_id = pov.product_option_value_id) WHERE oo.order_product_id=" . (int)$order_product_id . " AND po.required=1";

        foreach ($this->db->query($sql_order_options)->rows as $option) {
            $var[] = $option['option_id'] . ':' . $option['option_value_id'];
        }

        sort($var);

        $sql_variation = "SELECT sciv.square_id, scomb.price FROM `" . DB_PREFIX . "squareup_combination_item_variation` sciv LEFT JOIN `" . DB_PREFIX . "squareup_combination` scomb ON (scomb.squareup_combination_id = sciv.squareup_combination_id) WHERE scomb.var='" . $this->db->escape(implode(';', $var)) . "' AND scomb.product_id=" . (int)$product_id;

        $result = $this->db->query($sql_variation);

        if ($result->num_rows > 0) {
            return array(
                'id' => $result->row['square_id'],
                'price' => (float)$result->row['price']
            );
        }

        return false;
    }

    public function getLocationCurrency($default) {
        $locations = $this->config->get('payment_squareup_locations');
        $location_id = $this->config->get('payment_squareup_location_id');

        if (!empty($locations) && !empty($location_id)) {
            foreach ($locations as $location) {
                if ($location['id'] == $location_id && $this->isCurrencySupported($location['currency'])) {
                    return $location['currency'];
                }
            }
        }

        return $default;
    }

    protected function getNegativePriceChange($order_products, $product_id, $base_price) {
        if ($this->config->get('payment_squareup_sync_source') != 'opencart') {
            return false;
        }
        
        /*
            This method returns false by default. In case there exists a product special with a price smaller than $base_price, return the special price. In case there exists a product discount with a price smaller than $base_price, return the discount price.

            Note that if the special price >= $base_price, the discount is given a chance. This may lead to a discrepancy with the price calculated in system/library/cart.php. Ultimately, it is caused by a special price >= $base_price, which is not to be expected in a typical scenario.

            Important: We expect this method to always either return negative prices, or to return false. The negative prices will and must affect the total submitted to Square.
        */

        // Product Specials
        $product_special_query = $this->db->query("SELECT price FROM " . DB_PREFIX . "product_special WHERE product_id = '" . (int)$product_id . "' AND customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) ORDER BY priority ASC, price ASC LIMIT 1");

        if ($product_special_query->num_rows) {
            $price = $product_special_query->row['price'];

            if ($base_price > $price) {
                // As mentioned above, if we do not go into this IF statement, the discounts will be given a chance.
                return array(
                    'price' => $price - $base_price,
                    'name' => 'Product Special'
                );
            }
        }

        // Product Discounts
        $discount_quantity = 0;

        foreach ($order_products as $order_product) {
            if ($order_product['product_id'] == $product_id) {
                $discount_quantity += $order_product['quantity'];
            }
        }

        $product_discount_query = $this->db->query("SELECT price FROM " . DB_PREFIX . "product_discount WHERE product_id = '" . (int)$product_id . "' AND customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND quantity <= '" . (int)$discount_quantity . "' AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) ORDER BY quantity DESC, priority ASC, price ASC LIMIT 1");

        if ($product_discount_query->num_rows) {
            $price = $product_discount_query->row['price'];

            if ($base_price > $price) {
                return array(
                    'price' => $price - $base_price,
                    'name' => 'Product Discount'
                );
            }
        }

        return false;
    }

    protected function getNegativeProductOptionValue($product_option_value_id) {
        if ($this->config->get('payment_squareup_sync_source') != 'opencart') {
            return false;
        }

        // We are interested only in the non-required option modifiers because the required price difference is already included in the Square variation price upon sync.

        $sql = "SELECT pov.price, pov.price_prefix FROM `" . DB_PREFIX . "product_option_value` pov LEFT JOIN `" . DB_PREFIX . "product_option` po ON (po.product_option_id = pov.product_option_id) WHERE po.required=0 AND pov.product_option_value_id=" . (int)$product_option_value_id;

        $result = $this->db->query($sql);

        if ($result->num_rows > 0) {
            $price = (float)$result->row['price'];
            $price_prefix = $result->row['price_prefix'];

            return $price_prefix == '-' ? -$price : false;
        }

        return false;
    }

    protected function api($request_data) {
        $url = self::API_URL;

        if (empty($request_data['no_version'])) {
            $url .= '/' . self::API_VERSION;
        }

        $url .= '/' . $request_data['endpoint'];

        $curl_options = array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true
        );

        if (!empty($request_data['content_type'])) {
            $content_type = $request_data['content_type'];
        } else {
            $content_type = 'application/json';
        }

        // handle method and parameters
        if (isset($request_data['parameters']) && is_array($request_data['parameters']) && count($request_data['parameters'])) {
            $params = $this->encodeParameters($request_data['parameters'], $content_type);
        } else {
            $params = null;
        }

        switch ($request_data['method']) {
            case 'GET' :
                $curl_options[CURLOPT_POST] = false;

                if (is_string($params)) {
                    $curl_options[CURLOPT_URL] .= ((strpos($url, '?') === false) ? '?' : '&') . $params;
                }

                break;
            case 'POST' :
                $curl_options[CURLOPT_POST] = true;

                if ($params !== null) {
                    $curl_options[CURLOPT_POSTFIELDS] = $params;
                }

                break;
            default : 
                $curl_options[CURLOPT_CUSTOMREQUEST] = $request_data['method'];

                if ($params !== null) {
                    $curl_options[CURLOPT_POSTFIELDS] = $params;
                }

                break;
        }

        // handle headers
        $added_headers = array();

        if (!empty($request_data['auth_type'])) {
            if (empty($request_data['token'])) {
                $token = $this->config->get('payment_squareup_access_token');
            } else {
                // custom token trumps regular one
                $token = $request_data['token'];
            }
            
            $added_headers[] = 'Authorization: ' . $request_data['auth_type'] . ' ' . $token;
        }

        // use Content-Type: multipart/form-data when we provide an array
        if (!is_array($params)) {
            $added_headers[] = 'Content-Type: ' . $content_type;
        } else {
            $added_headers[] = 'Content-Type: multipart/form-data';
        }

        // Add endpoint version header as per: https://medium.com/square-corner-blog/api-versioning-for-connect-v2-2a4fb7298efd
        $added_headers[] = sprintf('Square-Version: %s', self::API_VERSION_HEADER);

        if (isset($request_data['headers']) && is_array($request_data['headers'])) {
            $curl_options[CURLOPT_HTTPHEADER] = array_merge($added_headers, $request_data['headers']);
        } else {
            $curl_options[CURLOPT_HTTPHEADER] = $added_headers;
        }

        if ($this->config->get('payment_squareup_debug')) {
            $error_fh = fopen('php://memory', 'w+b');
            $curl_options[CURLOPT_VERBOSE] = true;
            $curl_options[CURLOPT_STDERR] = $error_fh;
        }

        $this->debug("SQUAREUP DEBUG START...");
        $this->debug("SQUAREUP ENDPOINT: " . $curl_options[CURLOPT_URL]);
        $this->debug("SQUAREUP HEADERS: " . print_r($curl_options[CURLOPT_HTTPHEADER], true));
        $this->debug("SQUAREUP PARAMS: " . (is_array($params) ? json_encode($params) : $params));

        // Fire off the request
        $ch = curl_init();
        curl_setopt_array($ch, $curl_options);

        $sleeps = array(0, 1, 2, 6);

        while (null !== $sleep = array_shift($sleeps)) {
            sleep($sleep);

            $result = curl_exec($ch);
            $info = curl_getinfo($ch);

            if (preg_match('~^(0|429|5\d+)$~', $info['http_code'])) {
                $this->debug("SQUAREUP ERROR ENCOUNTERED: " . print_r($info, true));
            } else {
                break;
            }
        }

        if ($this->config->get('payment_squareup_debug')) {
            rewind($error_fh);
            $this->debug("SQUAREUP VERBOSE LOG: " . PHP_EOL . stream_get_contents($error_fh) . "=========");
            fclose($error_fh);
        }

        if ($result) {
            $this->debug("SQUAREUP RESULT: " . $result);

            curl_close($ch);

            $return = json_decode($result, true);

            if (!empty($return['errors'])) {
                throw new \Squareup\Exception\Api($this->registry, $return['errors']);
            } else {
                return $return;
            }
        } else {
            $info = curl_getinfo($ch);

            curl_close($ch);

            if (!empty($info['http_code'])) {
                throw new \Squareup\Exception\Api($this->registry, "CURL error. Info: " . print_r($info, true), true);
            } else {
                throw new \Squareup\Exception\Network("Temporary network error. Please try again later.");
            }
        }
    }

    public function isCurrencySupported($currency) {
        $sql = "SELECT currency_id FROM `" . DB_PREFIX . "currency` WHERE `code`='" . $this->db->escape($currency) . "' AND status='1'";

        return $this->db->query($sql)->num_rows > 0;
    }

    protected function isRequiredProductOption($product_option_value_id) {
        $sql = "SELECT po.required FROM `" . DB_PREFIX . "product_option_value` pov LEFT JOIN `" . DB_PREFIX . "product_option` po ON (po.product_option_id = pov.product_option_id) WHERE pov.product_option_value_id=" . (int)$product_option_value_id . " AND po.required='1'";

        return $this->db->query($sql)->num_rows > 0;
    }

    protected function getProductOptionValuePrice($product_option_value_id) {
        $sql = "SELECT price FROM `" . DB_PREFIX . "product_option_value` WHERE product_option_value_id=" . (int)$product_option_value_id . " AND price_prefix='+'";

        $result = $this->db->query($sql);

        if ($result->num_rows > 0) {
            return (float)$result->row['price'];
        }

        return 0;
    }

    protected function getProductTaxes($tax_class_id, $value) {
        $rates = $this->tax->getRates($value, $tax_class_id);

        $tax_rate_ids = array_keys($rates);

        $applied = array();

        if (!empty($tax_rate_ids)) {
            $objects = array();
            $total = 0;
            $line_items_total = 0;
            $line_items = array();

            if (!$this->config->get('payment_squareup_ad_hoc_sync')) {
                $sql = "SELECT sc.data, sc.square_id, strt.tax_rate_id FROM `" . DB_PREFIX . "squareup_tax_rate_tax` strt LEFT JOIN `" . DB_PREFIX . "tax_rule` tru ON (tru.tax_rate_id = strt.tax_rate_id) LEFT JOIN `" . DB_PREFIX . "squareup_catalog` sc ON (sc.square_id = strt.square_id) WHERE tru.tax_class_id='" . (int)$tax_class_id . "' AND sc.square_id IS NOT NULL";

                $result = $this->db->query($sql);

                if ($result->num_rows > 0) {
                    foreach ($result->rows as $tax) {
                        if (!in_array((int)$tax['tax_rate_id'], $tax_rate_ids)) {
                            continue;
                        }

                        $data = json_decode($tax['data'], true);

                        if (
                            $data['calculation_phase'] == 'TAX_SUBTOTAL_PHASE' &&
                            $data['inclusion_type'] == 'ADDITIVE' &&
                            $data['enabled']
                        ) {
                            $total += $value * (float)$data['percentage'] / 100;
                        }
                        
                        $objects[] = array(
                            'catalog_object_id' => $tax['square_id']
                        );

                        $applied[] = (int)$tax['tax_rate_id'];
                    }
                }
            }

            // Account for fixed-price taxes / other missed taxes, if possible
            foreach ($rates as $tax_rate_id => $tax_rate) {
                if (in_array((int)$tax_rate_id, $applied)) {
                    continue;
                }

                $percentage = (float)round((float)$tax_rate['amount'] * 100 / $value, 2);

                if ($percentage >= 0 && $percentage <= 100) {
                    // The Square API does not accept percentages below 0 and exceeding 100
                    $objects[] = array(
                        'name' => $tax_rate['name'],
                        'type' => 'ADDITIVE',
                        'percentage' => (string)$percentage
                    );

                    $total += (float)$tax_rate['amount'];
                } else {
                    $line_items[] = array(
                        'quantity' => '1',
                        'name' => $tax_rate['name'],
                        'base_price_money' => $this->convertToSquarePrice((float)$tax_rate['amount']),
                        'variation_name' => 'N/A',
                    );

                    $line_items_total += (float)$tax_rate['amount'];
                }

                $applied[] = (int)$tax_rate_id;
            }

            if (!empty($objects)) {
                return array(
                    'square_taxes' => $objects,
                    'line_items' => $line_items,
                    'total' => $total,
                    'line_items_total' => $line_items_total
                );
            }
        }

        return false;
    }

    protected function getVariationModifier($product_option_value_id) {
        $sql = "SELECT sc.data, sc.square_id FROM `" . DB_PREFIX . "squareup_product_option_value_modifier` spovm LEFT JOIN `" . DB_PREFIX . "squareup_catalog` sc ON (sc.square_id = spovm.square_id) WHERE spovm.product_option_value_id=" . (int)$product_option_value_id . " AND sc.square_id IS NOT NULL";

        $result = $this->db->query($sql);

        if ($result->num_rows > 0) {
            return array(
                'id' => $result->row['square_id'],
                'data' => json_decode($result->row['data'], true)
            );
        }

        return false;
    }

    protected function debug($text) {
        if ($this->config->get('payment_squareup_debug')) {
            $this->log->write($text);
        }
    }

    protected function filterLocation($location) {
        if (empty($location['capabilities'])) {
            return false;
        }

        if ($location['status'] != 'ACTIVE') {
            return false;
        }

        return in_array('CREDIT_CARD_PROCESSING', $location['capabilities']);
    }

    protected function encodeParameters(&$params, $content_type) {
        switch ($content_type) {
            case 'application/json' :
                return json_encode($params);
            case 'application/x-www-form-urlencoded' :
                return http_build_query($params);
            default :
            case 'multipart/form-data' :
                // curl will handle the params as multipart form data if we just leave it as an array
                return $params;
        }
    }

    protected function authState() {
        if (!isset($this->session->data['payment_squareup_oauth_state'])) {
            $this->session->data['payment_squareup_oauth_state'] = bin2hex(openssl_random_pseudo_bytes(32));
        }

        return $this->session->data['payment_squareup_oauth_state'];
    }
}