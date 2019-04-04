<?php

namespace Squareup\Diff;

use Squareup\Library;

class SquareInventory extends Library {
    const WEBHOOK_AFTER_INTERVAL = 90;

    public function work($is_webhook = false) {
        if ($is_webhook) {
            $this->retrieveStocksAfter(time() - self::WEBHOOK_AFTER_INTERVAL);
        } else {
            // Retrieve all variation IN_STOCK quantities
            $this->retrieveAllStocks();
        }
    }

    protected function retrieveStocksAfter($after) {
        $counts = array();

        do {
            $cursor = isset($result['cursor']) ? $result['cursor'] : '';
            $locations = array();

            if ($this->config->get('payment_squareup_inventory_sync') == 'inventory_single') {
                $locations[] = $this->config->get('payment_squareup_location_id');
            } else {
                foreach ($this->config->get('payment_squareup_locations') as $location) {
                    $locations[] = $location['id'];
                }
            }

            $result = $this->squareup_api->getInventoryAfter($cursor, $after, $locations);

            $object_ids = array();

            if (isset($result['changes']) && is_array($result['changes'])) {
                foreach ($result['changes'] as $change) {
                    if ($change['type'] == 'PHYSICAL_COUNT') {
                        $key = 'physical_count';
                    } else if ($change['type'] == 'ADJUSTMENT') {
                        $key = 'adjustment';
                    } else {
                        $key = null;
                    }

                    if (!is_null($key)) {
                        if ($change[$key]['catalog_object_type'] == 'ITEM_VARIATION') {
                            $catalog_object_id = $change[$key]['catalog_object_id'];

                            if (!in_array($catalog_object_id, $object_ids)) {
                                $object_ids[] = $catalog_object_id;
                            }
                        }
                    }
                }
            }
        } while (isset($result['cursor']));

        $this->retrieveAllStocks($object_ids);
    }

    protected function retrieveAllStocks($variation_square_ids = array()) {
        $this->squareup_diff->output("Fetching IN_STOCK inventories from Square...");

        // Generate OC update diff
        $limit = 1000;
        $page = 0;

        do {
            $variation_condition = "";

            if (!empty($variation_square_ids)) {
                $variation_condition = " AND scomb.product_id IN (SELECT tmpscomb.product_id FROM `" . DB_PREFIX . "squareup_combination` tmpscomb LEFT JOIN `" . DB_PREFIX . "squareup_combination_item_variation` tmpsciv ON (tmpscomb.squareup_combination_id = tmpsciv.squareup_combination_id) WHERE tmpsciv.square_id IN (" . implode(",", array_map(array($this->squareup_diff, 'escapeValue'), $variation_square_ids)) . ")) ";
            }

            $sql = "SELECT scomb.product_id, GROUP_CONCAT(CONCAT_WS('|', sc.square_id, scomb.var) SEPARATOR '#') as vars FROM `" . DB_PREFIX . "squareup_catalog` sc LEFT JOIN `" . DB_PREFIX . "squareup_combination_item_variation` sciv ON (sciv.square_id = sc.square_id) LEFT JOIN `" . DB_PREFIX . "squareup_combination` scomb ON (scomb.squareup_combination_id = sciv.squareup_combination_id) LEFT JOIN `" . DB_PREFIX . "product` p ON (p.product_id = scomb.product_id) WHERE sc.type='ITEM_VARIATION' AND scomb.squareup_combination_id IS NOT NULL " . $variation_condition . " GROUP BY scomb.product_id LIMIT " . ($page * $limit) . "," . $limit;

            $result = $this->db->query($sql);
            $num_rows = $result->num_rows;

            if ($num_rows > 0) {
                $object_ids = array();
                $object_count = 0;

                foreach ($result->rows as $row) {
                    $product_id = (int)$row['product_id'];

                    $pairs = explode('#', $row['vars']);

                    if (count($pairs) + $object_count > 1000) {
                        if (!empty($object_ids)) {
                            $this->retrieveAllStocksHandler($object_ids);

                            $object_ids = array();
                            $object_count = 0;
                        }
                    }

                    $object_ids[$product_id] = array();

                    foreach ($pairs as $pair) {
                        $parts = explode('|', $pair);

                        $object_ids[$product_id][] = array(
                            'square_id' => $parts[0],
                            'var' => $parts[1]
                        );

                        $object_count++;
                    }
                }

                if (!empty($object_ids)) {
                    $this->retrieveAllStocksHandler($object_ids);
                }

                $page++;
            }
        } while ($num_rows > 0);

        // Resolve the diff
        $this->squareup_diff->executeDiff();
    }

    protected function getSquareIds(&$collections) {
        $result = array();

        foreach ($collections as $product_id => &$items) {
            foreach ($items as &$item) {
                $result[] = $item['square_id'];
            }
        }

        return $result;
    }

    protected function identifyVariationFromCollection(&$square_id, &$collections) {
        foreach ($collections as $product_id => &$items) {
            foreach ($items as &$item) {
                if ($item['square_id'] == $square_id) {
                    return array(
                        'product_id' => $product_id,
                        'var' => $item['var']
                    );
                }
            }
        }
    }

    protected function retrieveAllStocksHandler($collections) {
        $counts = array();

        do {
            $cursor = isset($result['cursor']) ? $result['cursor'] : '';
            $locations = array();

            if ($this->config->get('payment_squareup_inventory_sync') == 'inventory_single') {
                $locations[] = $this->config->get('payment_squareup_location_id');
            } else {
                foreach ($this->config->get('payment_squareup_locations') as $location) {
                    $locations[] = $location['id'];
                }
            }

            $result = $this->squareup_api->getInventories($cursor, $locations, $this->getSquareIds($collections));

            if (isset($result['counts']) && is_array($result['counts'])) {
                foreach ($result['counts'] as $inventory_count) {
                    if (in_array($inventory_count['state'], array('IN_STOCK'))) {
                        $item_info = $this->identifyVariationFromCollection($inventory_count['catalog_object_id'], $collections);

                        $key = $item_info['product_id'];
                        $var = $item_info['var'];

                        if (!isset($counts[$key])) {
                            $counts[$key] = array(
                                'main' => 0,
                                'var' => array()
                            );
                        }

                        if (!isset($counts[$key]['var'][$var])) {
                            $counts[$key]['var'][$var] = 0;
                        }

                        $counts[$key]['main'] += (int)$inventory_count['quantity'];
                        $counts[$key]['var'][$var] += (int)$inventory_count['quantity'];
                    }
                }
            }
        } while (isset($result['cursor']));

        $diff_info = array();

        foreach ($counts as $product_id => $square_quantity) {
            if (false !== $quantity_diff = $this->getQuantityDiff($product_id, $square_quantity['main'], $square_quantity['var'])) {
                $diff_info = array_merge($diff_info, $quantity_diff);
            }
        }

        if (!empty($diff_info)) {
            $diff = array(
                'type' => 'update_opencart',
                'last_id_map' => array(),
                'data' => $diff_info
            );

            $this->squareup_diff->addDiff($diff);

            $this->squareup_diff->executeDiff();
        }
    }

    protected function getQuantityDiff($product_id, $quantity, $var) {
        $result = array();

        $sql_quantity = "SELECT quantity FROM `" . DB_PREFIX . "product` WHERE product_id=" . (int)$product_id;

        $quantity_result = $this->db->query($sql_quantity);

        if ($quantity_result->num_rows > 0) {
            if ((int)$quantity_result->row['quantity'] != (int)$quantity) {
                $result[] = array(
                    'table' => 'product',
                    'set' => array(
                        'quantity' => (int)$quantity,
                        'subtract' => 1
                    ),
                    'where' => array(
                        'product_id' => (int)$product_id
                    )
                );
            }
        }

        $product_option_value = array();

        foreach ($var as $ids => $quantity) {
            if (empty($ids)) continue;

            $options = explode(';', $ids);

            foreach ($options as $option_id_option_value_id) {
                $parts = explode(':', $option_id_option_value_id);

                $option_id = $parts[0];
                $option_value_id = $parts[1];

                $sql_pov = "SELECT pov.* FROM `" . DB_PREFIX . "product_option_value` pov LEFT JOIN `" . DB_PREFIX . "product_option` po ON (po.product_option_id = pov.product_option_id) WHERE po.required=1 AND pov.product_id=" . (int)$product_id . " AND pov.option_id=" . (int)$option_id . " AND pov.option_value_id=" . (int)$option_value_id;

                $result_pov = $this->db->query($sql_pov);

                if ($result_pov->num_rows > 0) {
                    $product_option_value_id = (int)$result_pov->row['product_option_value_id'];
                    $subtract = (bool)$result_pov->row['subtract'];
                    $current_quantity = (int)$result_pov->row['quantity'];

                    if (!isset($product_option_value[$product_option_value_id])) {
                        $product_option_value[$product_option_value_id] = array(
                            'square_quantity' => 0,
                            'current_quantity' => $current_quantity,
                            'subtract' => $subtract
                        );
                    }

                    $product_option_value[$product_option_value_id]['square_quantity'] += (int)$quantity;
                }
            }
        }

        foreach ($product_option_value as $product_option_value_id => $quantities) {
            if ($quantities['square_quantity'] != $quantities['current_quantity'] || !$quantities['subtract']) {
                $result[] = array(
                    'table' => 'product_option_value',
                    'set' => array(
                        'quantity' => (int)$quantities['square_quantity'],
                        'subtract' => 1,
                    ),
                    'where' => array(
                        'product_option_value_id' => (int)$product_option_value_id
                    )
                );
            }
        }

        return !empty($result) ? $result : false;
    }
}