<?php

namespace Squareup\Diff;

use Squareup\Library;

class OpenCartCombination extends Library {
    private $combinations = array();

    public function work($product_ids = array()) {
        $this->makeCombinations($product_ids);
    }

    protected function makeCombinations($product_ids = array()) {
        // Making permutations of required options
        $this->squareup_diff->output("Preparing combinations of OpenCart required product options...");

        $limit = 1000;
        $page = 0;

        do {
            $product_id_condition = "";

            if (!empty($product_ids)) {
                $product_id_condition = " WHERE product_id IN (" . implode(",", $product_ids) . ") ";
            }

            $sql = "SELECT product_id, sku, upc, model, price, quantity, subtract FROM `" . DB_PREFIX . "product` " . $product_id_condition . " LIMIT " . ($page * $limit) . "," . $limit;

            $result = $this->db->query($sql);
            $num_rows = $result->num_rows;

            if ($num_rows > 0) {
                array_map(array($this, 'makeProductCombinations'), $result->rows);

                $page++;
            }
        } while ($num_rows > 0);

        $this->submitCombinations();

        $this->db->query("DELETE FROM `" . DB_PREFIX . "squareup_combination` WHERE product_id NOT IN (SELECT product_id FROM `" . DB_PREFIX . "product`)");
    }

    protected function findNextFieldIndex($field, $product_id, $name, $var) {
        $find_sql = "SELECT " . $field . ", var FROM `" . DB_PREFIX . "squareup_combination` WHERE product_id=" . (int)$product_id;

        $max = 0;
        $regex = '~[^0-9]*([0-9]+)$~';

        $result = $this->db->query($find_sql);

        foreach ($result->rows as $row) {
            $matches = array();

            preg_match($regex, $row[$field], $matches);

            if (isset($matches[1]) && is_numeric($matches[1])) {
                if ($row['var'] == $var) {
                    return (int)$matches[1];
                }

                $max = max((int)$matches[1], $max);
            }
        }

        foreach ($this->combinations as $combination) {
            if ($combination['product_id'] != $product_id) {
                continue;
            }

            $matches = array();

            preg_match($regex, $combination[$field], $matches);

            if (isset($matches[1]) && is_numeric($matches[1])) {
                if ($combination['var'] == $var) {
                    return (int)$matches[1];
                }

                $max = max((int)$matches[1], $max);
            }
        }

        return $max + 1;
    }

    protected function makeProductCombinations($row) {
        $product_id = (int)$row['product_id'];
        $quantity = (int)$row['quantity'];
        $sku = $row['sku'];
        $upc = $row['upc'] ? $row['upc'] : 'N/A';
        $price = (float)$row['price'];
        $subtract = (bool)$row['subtract'];
        $combination_names = array();

        $required_options_sql = "SELECT DISTINCT od.name as `option`, ovd.name as `value`, pov.price_prefix, pov.price, pov.quantity, pov.subtract, pov.option_id, pov.option_value_id FROM `" . DB_PREFIX . "product_option_value` pov LEFT JOIN `" . DB_PREFIX . "product_option` po ON (po.product_option_id = pov.product_option_id) LEFT JOIN `" . DB_PREFIX . "option_description` od ON (od.option_id = po.option_id AND od.language_id = '" . (int)$this->config->get('config_language_id') . "') LEFT JOIN `" . DB_PREFIX . "option_value_description` ovd ON (ovd.option_value_id = pov.option_value_id AND ovd.language_id='" . (int)$this->config->get('config_language_id') . "') LEFT JOIN `" . DB_PREFIX . "option` o ON (o.option_id = po.option_id) WHERE pov.product_id=" . $product_id . " AND po.required=1 AND o.type IN ('select', 'radio') AND od.name != '' AND ovd.name != '' ORDER BY od.name ASC, ovd.name ASC";

        $required_options = $this->db->query($required_options_sql);

        if ($required_options->num_rows > 0) {
            // Required options have been found. Create combinations
            $possible_options = $this->extractPossibleOptions($required_options->rows);

            $combinations = $this->combineOptions($possible_options);

            foreach ($combinations as $combination) {
                $combination_name = $this->makeCombinationName($combination);
                $combination_info = $this->makeCombinationInfo($price, $subtract, $combination, $required_options->rows);
                $combination_sku = $sku ? ($sku . '-' . $this->findNextFieldIndex('sku', $product_id, $combination_name, $combination_info['var'])) : '';
                $combination_upc = $upc . '-' . $this->findNextFieldIndex('upc', $product_id, $combination_name, $combination_info['var']);

                $combination_names[] = $this->createUpdateCombination($product_id, $combination_name, $combination_sku, $combination_upc, $combination_info['price'], $combination_info['subtract'], $combination_info['var'], $combination_info['quantity']);
            }
        } else {
            // No required options found. Therefore, this must result in only 1 variation with the base SKU
            $combination_names[] = $this->createUpdateCombination($product_id, 'Regular', $sku, $upc, $price, $subtract, '', $quantity);
        }

        $this->db->query("DELETE FROM `" . DB_PREFIX . "squareup_combination` WHERE product_id=" . $product_id . " AND name NOT IN (" . implode(',', $combination_names) . ")");
    }

    protected function createUpdateCombination($product_id, $name, $sku, $upc, $price, $subtract, $var, $quantity) {
        if (count($this->combinations) >= 1000) {
            $this->submitCombinations();
        }

        $this->combinations[] = array(
            'product_id' => (int)$product_id,
            'name' => $this->db->escape($name),
            'var' => $this->db->escape($var),
            'price' => (float)$price,
            'subtract' => (int)$subtract,
            'sku' => $this->db->escape($sku),
            'upc' => $this->db->escape($upc),
            'quantity' => (int)$quantity
        );

        return "'" . $this->db->escape($name) . "'";
    }

    protected function submitCombinations() {
        if (empty($this->combinations)) {
            return;
        }

        $sql = "INSERT INTO `" . DB_PREFIX . "squareup_combination` (`product_id`, `name`, `var`, `price`, `subtract`, `sku`, `upc`, `quantity`) VALUES ";

        $values = array();

        foreach ($this->combinations as $combination) {
            $parts = array();

            $parts[] = $combination['product_id'];
            $parts[] = "'" . $combination['name'] . "'";
            $parts[] = "'" . $combination['var'] . "'";
            $parts[] = $combination['price'];
            $parts[] = $combination['subtract'];
            $parts[] = "'" . $combination['sku'] . "'";
            $parts[] = "'" . $combination['upc'] . "'";
            $parts[] = $combination['quantity'];

            $values[] = "(" . implode(",", $parts) . ")";
        }

        $sql .= " " . implode(",", $values);

        $sql .= " ON DUPLICATE KEY UPDATE `price`=VALUES(`price`), `price`=VALUES(`price`), `subtract`=VALUES(`subtract`), `sku`=VALUES(`sku`), `upc`=VALUES(`upc`), `quantity`=VALUES(`quantity`)";

        $this->db->query($sql);

        $this->combinations = array();
    }

    protected function makeCombinationInfo($product_price, $product_subtract, $combination, $required_options) {
        $result = array(
            'price' => $product_price,
            'subtract' => $product_subtract,
            'quantity' => 0,
            'var' => ''
        );

        $var = array();
        $quantity = 0;

        foreach ($required_options as $required_option) {
            foreach ($combination as $option => $value) {
                if ($required_option['option'] == $option && $required_option['value'] == $value) {
                    $var[] = $required_option['option_id'] . ':' . $required_option['option_value_id'];

                    if ($required_option['price_prefix'] == '+') {
                        $result['price'] += (float)$required_option['price'];
                    } else if ($required_option['price_prefix'] == '-') {
                        $result['price'] -= (float)$required_option['price'];
                    }

                    $result['subtract'] = $result['subtract'] && (bool)$required_option['subtract'];
                    $quantity = (int)$required_option['quantity'];
                }
            }
        }

        sort($var);

        if (count($var) <= 1) {
            $result['quantity'] = $quantity;
        }

        $result['var'] = implode(';', $var);
        $result['price'] = max($result['price'], 0);

        return $result;
    }

    protected function makeCombinationName($combination) {
        $result = array();

        foreach ($combination as $key => $value) {
            $result[] = $key . ':' . $value;
        }

        return implode('; ', $result);
    }

    protected function extractPossibleOptions($rows) {
        $result = array();

        foreach ($rows as $row) {
            $result[$row['option']][] = $row['value'];
        }

        return $result;
    }

    protected function combineOptions($arrays) {
        // Based on: https://gist.github.com/cecilemuller/4688876
        $result = array(array());

        foreach ($arrays as $property => $property_values) {
            $tmp = array();
            foreach ($result as $result_item) {
                foreach ($property_values as $property_value) {
                    $tmp[] = array_merge($result_item, array($property => $property_value));
                }
            }
            $result = $tmp;
        }

        return $result;
    }
}