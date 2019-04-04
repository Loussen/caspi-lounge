<?php

namespace Squareup;

class OrderHistory extends Library {
    private $stock = array();

    public function persistOrderStock($order_id) {
        $this->stock[$order_id] = $this->getOrderStocks($order_id);
    }

    public function getOrderStockDifference($order_id) {
        $result = array();

        if (isset($this->stock[$order_id])) {
            $this->load->model('checkout/order');

            $stocks = $this->getOrderStocks($order_id);

            foreach ($stocks as $order_product_id => $new_quantity) {
                if ($new_quantity != $this->stock[$order_id][$order_product_id]) {
                    $result[$order_product_id] = array(
                        'catalog_object_id' => $this->getSquareItemObjectIdByOrderProductId($order_product_id),
                        'product_id' => $this->getProductIdByOrderProductId($order_product_id),
                        'quantity' => $this->stock[$order_id][$order_product_id] - $new_quantity,
                        'order_options' => array()
                    );

                    $order_options = $this->model_checkout_order->getOrderOptions($order_id, $order_product_id);

                    foreach ($order_options as $order_option) {
                        $result[$order_product_id]['order_options'][$order_option['order_option_id']] = array(
                            'product_option_value_id' => $order_option['product_option_value_id']
                        );
                    }
                }
            }
        }

        return !empty($result) ? $result : false;
    }

    public function getOtherPaymentMethodPurchasedCounts($order_id) {
        if (isset($this->stock[$order_id]) && !$this->isPaymentMethodSquare($order_id)) {
            $this->load->library('squareup');

            $result = array();
            $stocks = $this->getOrderStocks($order_id);

            foreach ($stocks as $order_product_id => $new_quantity) {
                $sold = $this->stock[$order_id][$order_product_id] - $new_quantity;

                if ($sold > 0 && (false !== $square_id = $this->getSquareItemObjectIdByOrderProductId($order_product_id))) {
                    $result[$square_id] = $sold;
                }
            }

            if (count($result) > 0) {
                return $result;
            }
        }

        return false;
    }

    public function isPaymentMethodSquare($order_id) {
        $sql = "SELECT payment_code FROM `" . DB_PREFIX . "order` WHERE order_id='" . (int)$order_id . "'";

        return $this->db->query($sql)->row['payment_code'] == 'squareup';
    }

    public function getSquareItemObjectIdByOrderProductId($order_product_id) {
        $this->load->library('squareup');
        
        $product_id = $this->getProductIdByOrderProductId($order_product_id);

        if (false !== $item_variation = $this->squareup_api->getProductVariation($product_id, $order_product_id)) {
            return $item_variation['id'];
        }

        return false;
    }

    public function getProductIdByOrderProductId($order_product_id) {
        $sql = "SELECT op.product_id FROM `" . DB_PREFIX . "order_product` op WHERE op.order_product_id='" . (int)$order_product_id . "'";

        $result = $this->db->query($sql);

        return (int)$result->row['product_id'];
    }

    protected function getOrderStocks($order_id) {
        $result = array();

        $sql = "SELECT op.order_product_id, p.quantity FROM `" . DB_PREFIX . "order_product` op LEFT JOIN `" . DB_PREFIX . "product` p ON (p.product_id = op.product_id) WHERE p.subtract='1' AND op.order_id='" . (int)$order_id . "'";

        foreach ($this->db->query($sql)->rows as $row) {
            $result[(int)$row['order_product_id']] = (int)$row['quantity'];
        }

        return $result;
    }
}