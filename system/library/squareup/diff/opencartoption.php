<?php

namespace Squareup\Diff;

use Squareup\Library;

class OpenCartOption extends Library {
    public function work($product_ids = array()) {
        if (!$this->config->get('payment_squareup_ad_hoc_sync')) {
            // Determine if any product options are different from those in Square and mark this in the relation tables by setting version = 0
            $this->updateVersionsInOpenCart($product_ids);

            // Delete redundant references between product_option's and Square MODIFIER_LISTs
            $this->deleteProductOptionModifierList();

            // Delete redundant references between product_option_value's and Square modifiers. This step is to detect and delete any links to modifiers deleted from the Square dashboard
            $this->deleteProductOptionValueModifier();

            // Detect any version differences (depends on the step above) and prepare upsertion batches
            $this->insertUpdate();

            // Delete redundant references between product_option's and Square MODIFIER_LISTs
            $this->deleteProductOptionModifierList();
        } else {
            $this->db->query("TRUNCATE `" . DB_PREFIX . "squareup_product_option_modifier_list`");
            $this->db->query("TRUNCATE `" . DB_PREFIX . "squareup_product_option_value_modifier`");
        }

        // Delete redundant Square MODIFIER_LISTs
        $this->deleteModifierList();

        // Delete redundant references between product_option_value's and Square modifiers
        $this->deleteProductOptionValueModifier();

        // Delete redundant Square modifiers
        $this->deleteModifier();
    }

    public function updateVersionsInOpenCartHandler(&$update_ids) {
        $diff_info = array(
            'type' => 'update_opencart',
            'last_id_map' => array(),
            'data' => array(
                array(
                    'table' => 'squareup_product_option_modifier_list',
                    'set' => array(
                        'version' => 0
                    ),
                    'where' => array(
                        'square_id' => $update_ids
                    )
                )
            )
        );

        $this->squareup_diff->addDiff($diff_info);

        $this->squareup_diff->executeDiff();
    }

    public function updateVersionsInOpenCart($product_ids = array()) {
        $this->squareup_diff->output("OC OPTION: Find modifiers/modifier lists edited from within Square...");

        $update_ids = array();

        if (empty($product_ids)) {
            $sql = "SELECT sc.* FROM `" . DB_PREFIX . "squareup_catalog` sc WHERE sc.`type`='MODIFIER_LIST'";
        } else {
            $sql = "SELECT DISTINCT sc.* FROM `" . DB_PREFIX . "product_option` po LEFT JOIN `" . DB_PREFIX . "squareup_product_option_modifier_list` spoml ON (spoml.product_option_id = po.product_option_id) LEFT JOIN `" . DB_PREFIX . "squareup_catalog` sc ON (sc.square_id = spoml.square_id) WHERE po.product_id IN (" . implode(",", $product_ids) . ") AND sc.`type`='MODIFIER_LIST'";
        }

        $this->squareup_diff->chunkedMap($sql, function(&$modifier_list) use (&$update_ids) {
            if (false !== $square_id = $this->hasToBeUpdated($modifier_list)) {
                $update_ids[] = $square_id;

                $this->squareup_diff->chunkHandler($update_ids, array($this, 'updateVersionsInOpenCartHandler'));
            }
        });

        $this->squareup_diff->chunkHandler($update_ids, array($this, 'updateVersionsInOpenCartHandler'), true);
    }

    public function hasToBeUpdated(&$modifier_list) {
        $data = json_decode($modifier_list['data'], true);

        $sql = "SELECT spoml.square_id, spoml.product_option_id, od.name, o.type FROM `" . DB_PREFIX . "squareup_product_option_modifier_list` spoml LEFT JOIN `" . DB_PREFIX . "product_option` po ON (spoml.product_option_id = po.product_option_id) LEFT JOIN `" . DB_PREFIX . "option_description` od ON (od.option_id = po.option_id) LEFT JOIN `" . DB_PREFIX . "option` o ON (o.option_id = po.option_id) WHERE od.language_id=" . (int)$this->config->get('config_language_id') . " AND spoml.square_id='" . $this->db->escape($modifier_list['square_id']) . "' AND (po.required=0 OR (po.required=1 AND o.type='checkbox'))";

        $result = $this->db->query($sql);

        if ($result->num_rows) {
            $modifiers = array();

            if (isset($data['modifiers'])) {
                $modifiers = $data['modifiers'];
            }

            foreach ($result->rows as $row) {
                if (
                    $row['name'] != $data['name'] || 
                    $this->areOptionTypesDifferent($row['type'], $data['selection_type']) || 
                        $this->hashOrderedModifiers($modifiers) != 
                        $this->hashOrderedProductOptionValues($row['product_option_id'])
                ) {
                    return $result->row['square_id'];
                }
            }
        }

        return false;
    }

    public function areOptionTypesDifferent($opencart_type, $square_type) {
        if ($square_type == 'MULTIPLE') {
            return $opencart_type != 'checkbox';
        } else {
            return !in_array($opencart_type, array('radio', 'select'));
        }
    }

    public function hashOrderedModifiers($modifiers) {
        $stuff = array();

        foreach ($modifiers as $modifier) {
            $price = (float)(isset($modifier['modifier_data']['price_money']) ?
                $this->squareup_api->convertToLocalPrice($modifier['modifier_data']['price_money']) : 0);

            $stuff[] = $modifier['modifier_data']['name'] . '|+' . $price;
        }

        sort($stuff);

        return md5(json_encode($stuff));
    }

    public function hashOrderedProductOptionValues($product_option_id) {
        $stuff = array();

        $sql = "SELECT ovd.name, pov.price_prefix, pov.price FROM `" . DB_PREFIX . "product_option_value` pov LEFT JOIN `" . DB_PREFIX . "option_value_description` ovd ON (ovd.option_value_id = pov.option_value_id) WHERE pov.product_option_id=" . (int)$product_option_id . " AND ovd.language_id=" . (int)$this->config->get('config_language_id');

        foreach ($this->db->query($sql)->rows as $option_value) {
            $price_candidate = 0;

            if ($option_value['price_prefix'] == '+') {
                $price_candidate = $option_value['price'];
            }

            $price_prefix = $price_candidate > 0 ? $option_value['price_prefix'] : '+';

            $price = (float)$price_candidate;

            $stuff[] = $option_value['name'] . '|' . $price_prefix . $price;
        }

        sort($stuff);

        return md5(json_encode($stuff));
    }

    public function insertUpdate() {
        $this->squareup_diff->output("OC OPTION: Upload modifiers and modifier lists...");
        
        $batch = array();

        $sql = "SELECT po.product_option_id, o.option_id, o.type, od.name, spoml.square_id, sc.version, sc.present_at_location_ids, sc.absent_at_location_ids, sc.present_at_all_locations FROM `" . DB_PREFIX . "product_option` po LEFT JOIN `" . DB_PREFIX . "option` o ON (o.option_id = po.option_id) LEFT JOIN `" . DB_PREFIX . "option_description` od ON (od.option_id = o.option_id) LEFT JOIN `" . DB_PREFIX . "squareup_product_option_modifier_list` spoml ON (spoml.product_option_id = po.product_option_id) LEFT JOIN `" . DB_PREFIX . "squareup_catalog` sc ON (sc.square_id = spoml.square_id) WHERE od.language_id=" . (int)$this->config->get('config_language_id') . " AND o.type IN ('select', 'radio', 'checkbox') AND (spoml.square_id IS NULL OR spoml.version != sc.version) AND (po.required=0 OR (po.required=1 AND o.type='checkbox'))";

        $this->squareup_diff->chunkedMap($sql, function(&$product_option) use (&$batch) {
            $is_new = is_null($product_option['square_id']);

            $upsert_id = $is_new ? '#squareup_product_option_modifier_list:product_option_id:' . $product_option['product_option_id'] : $product_option['square_id'];

            $present_at_location_ids = is_null($product_option['present_at_location_ids']) ? array() : json_decode($product_option['present_at_location_ids'], true);

            $selection_type = $product_option['type'] == 'checkbox' ? 'MULTIPLE' : 'SINGLE';

            $modifiers = array();

            $this->modifiers($modifiers, $product_option['product_option_id'], $is_new);

            $modifier_list = array(
                'type' => 'MODIFIER_LIST',
                'id' => $upsert_id,
                'absent_at_location_ids' => is_null($product_option['absent_at_location_ids']) ? array() : json_decode($product_option['absent_at_location_ids'], true),
                'present_at_all_locations' => !empty($product_option['present_at_all_locations']),
                'present_at_location_ids' => $this->squareup_diff->appendCurrentLocation($present_at_location_ids),
                'modifier_list_data' => array(
                    'name' => $product_option['name'],
                    'selection_type' => $selection_type,
                    'modifiers' => $modifiers
                )
            );

            if (!is_null($product_option['version'])) {
                $modifier_list['version'] = (int)$product_option['version'];
            }

            $batch[] = $modifier_list;
        }, function() use (&$batch) {
            if (empty($batch)) {
                return;
            }

            $diff_info = array(
                'type' => 'upsert_square',
                'last_id_map' => array(
                    'MODIFIER_LIST' => 'squareup_product_option_modifier_list',
                    'MODIFIER' => 'squareup_product_option_value_modifier'
                ),
                'data' => array(
                    'idempotency_key' => $this->squareup_diff->getDiffId() . '.' . md5(microtime(true)),
                    'batches' => array(
                        array(
                            'objects' => $batch
                        )
                    )
                )
            );

            $this->squareup_diff->addDiff($diff_info);

            $this->squareup_diff->executeDiff();

            $batch = array();
        });        
    }

    public function modifiers(&$result, $product_option_id, $is_new) {
        $sql = "SELECT pov.product_option_value_id, ov.sort_order, ovd.name, spovm.square_id, sc.version, pov.price_prefix, pov.price FROM `" . DB_PREFIX . "product_option_value` pov LEFT JOIN `" . DB_PREFIX . "option_value` ov ON (ov.option_value_id = pov.option_value_id) LEFT JOIN `" . DB_PREFIX . "option_value_description` ovd ON (ovd.option_value_id = pov.option_value_id) LEFT JOIN `" . DB_PREFIX . "squareup_product_option_value_modifier` spovm ON (spovm.product_option_value_id = pov.product_option_value_id) LEFT JOIN `" . DB_PREFIX . "squareup_catalog` sc ON (sc.square_id = spovm.square_id) WHERE ovd.language_id=" . (int)$this->config->get('config_language_id') . " AND pov.product_option_id=" . (int)$product_option_id;

        foreach ($this->db->query($sql)->rows as $product_option_value) {
            $upsert_id = is_null($product_option_value['square_id']) || $is_new ? '#squareup_product_option_value_modifier:product_option_value_id:' . $product_option_value['product_option_value_id'] : $product_option_value['square_id'];

            $price = 0;

            if ($product_option_value['price_prefix'] == '+') {
                $price = (float)$product_option_value['price'];
            }

            $modifier = array(
                'type' => 'MODIFIER',
                'id' => $upsert_id,
                'modifier_data' => array(
                    'name' => $product_option_value['name'],
                    'on_by_default' => false,
                    'ordinal' => (int)$product_option_value['sort_order']
                )
            );

            if (!is_null($product_option_value['version'])) {
                $modifier['version'] = (int)$product_option_value['version'];
            }

            if ($price > 0) {
                $modifier['modifier_data']['price_money'] = $this->squareup_api->convertToSquarePrice($price);
            }

            $result[] = $modifier;
        }
    }

    public function deleteProductOptionModifierListHandler(&$delete_ids) {
        $diff_info = array(
            'type' => 'delete_opencart',
            'last_id_map' => array(),
            'data' => array(
                array(
                    'table' => 'squareup_product_option_modifier_list',
                    'where' => array(
                        'squareup_product_option_modifier_list_id' => $delete_ids
                    )
                )
            )
        );

        $this->squareup_diff->addDiff($diff_info);

        $this->squareup_diff->executeDiff();
    }

    public function deleteProductOptionModifierList() {
        $this->squareup_diff->output("OC OPTION: Delete references between product options and modifier lists...");

        $delete_ids = array();
        
        $sql = "SELECT spoml.squareup_product_option_modifier_list_id FROM `" . DB_PREFIX . "squareup_product_option_modifier_list` spoml LEFT JOIN `" . DB_PREFIX . "squareup_catalog` sc ON (sc.square_id = spoml.square_id AND sc.type='MODIFIER_LIST') LEFT JOIN `" . DB_PREFIX . "product_option` po ON (po.product_option_id=spoml.product_option_id) LEFT JOIN `" . DB_PREFIX . "option` o ON (o.option_id = po.option_id) WHERE sc.square_id IS NULL OR po.product_option_id IS NULL OR (po.required=1 AND o.type != 'checkbox')";

        $this->squareup_diff->chunkedMap($sql, function(&$row) use (&$delete_ids) {
            $delete_ids[] = $row['squareup_product_option_modifier_list_id'];

            $this->squareup_diff->chunkHandler($delete_ids, array($this, 'deleteProductOptionModifierListHandler'));
        }, function() use (&$delete_ids) {
            $this->squareup_diff->chunkHandler($delete_ids, array($this, 'deleteProductOptionModifierListHandler'), true);
        }, false);
    }

    public function deleteProductOptionValueModifierHandler(&$delete_ids) {
        $diff_info = array(
            'type' => 'delete_opencart',
            'last_id_map' => array(),
            'data' => array(
                array(
                    'table' => 'squareup_product_option_value_modifier',
                    'where' => array(
                        'squareup_product_option_value_modifier_id' => $delete_ids
                    )
                )
            )
        );

        $this->squareup_diff->addDiff($diff_info);

        $this->squareup_diff->executeDiff();
    }

    public function deleteProductOptionValueModifier() {
        $this->squareup_diff->output("OC OPTION: Delete references between product option values and modifiers...");

        $delete_ids = array();
        
        $sql = "SELECT spovm.squareup_product_option_value_modifier_id FROM `" . DB_PREFIX . "squareup_product_option_value_modifier` spovm LEFT JOIN `" . DB_PREFIX . "squareup_catalog` sc ON (sc.square_id = spovm.square_id AND sc.type='MODIFIER') LEFT JOIN `" . DB_PREFIX . "product_option_value` pov ON (pov.product_option_value_id=spovm.product_option_value_id) LEFT JOIN `" . DB_PREFIX . "product_option` po ON (po.product_option_id = pov.product_option_id) LEFT JOIN `" . DB_PREFIX . "option` o ON (o.option_id = po.option_id) WHERE sc.square_id IS NULL OR spovm.product_option_value_id IS NULL OR (po.required=1 AND o.type != 'checkbox')";

        $this->squareup_diff->chunkedMap($sql, function(&$row) use (&$delete_ids) {
            $delete_ids[] = $row['squareup_product_option_value_modifier_id'];

            $this->squareup_diff->chunkHandler($delete_ids, array($this, 'deleteProductOptionValueModifierHandler'));
        }, function() use (&$delete_ids) {
            $this->squareup_diff->chunkHandler($delete_ids, array($this, 'deleteProductOptionValueModifierHandler'), true);
        }, false);
    }

    public function disassociateModifierListHandler(&$disassociate_ids) {
        $diff_info = array(
            'type' => 'delete_opencart',
            'last_id_map' => array(),
            'data' => array(
                array(
                    'table' => 'squareup_catalog',
                    'where' => array(
                        'square_id' => $disassociate_ids
                    )
                )
            )
        );

        $this->squareup_diff->addDiff($diff_info);

        $this->squareup_diff->executeDiff();
    }

    public function deleteModifierListHandler(&$delete_ids) {
        $diff_info = array(
            'type' => 'delete_square',
            'last_id_map' => array(),
            'data' => array(
                'object_ids' => $delete_ids
            )
        );

        $this->squareup_diff->addDiff($diff_info);

        $diff_info = array(
            'type' => 'delete_opencart',
            'last_id_map' => array(),
            'data' => array(
                array(
                    'table' => 'squareup_catalog',
                    'where' => array(
                        'square_id' => $delete_ids
                    )
                )
            )
        );

        $this->squareup_diff->addDiff($diff_info);

        $this->squareup_diff->executeDiff();
    }

    public function deleteModifierList() {
        $this->squareup_diff->output("OC OPTION: Delete modifier lists...");

        $delete_ids = array();
        $disassociate_ids = array();
        
        $sql = "SELECT sc.* FROM `" . DB_PREFIX . "squareup_catalog` sc LEFT JOIN `" . DB_PREFIX . "squareup_product_option_modifier_list` spoml ON (spoml.square_id = sc.square_id) WHERE spoml.square_id IS NULL AND sc.type='MODIFIER_LIST'";
        
        $this->squareup_diff->chunkedMap($sql, function(&$row) use (&$delete_ids, &$disassociate_ids) {
            $last_id_map = array(
                'MODIFIER_LIST' => 'squareup_product_option_modifier_list'
            );

            $this->squareup_diff->determineDeleteOrDisassociate($row, $delete_ids, $disassociate_ids, $last_id_map);

            $this->squareup_diff->chunkHandler($delete_ids, array($this, 'deleteModifierListHandler'));
            $this->squareup_diff->chunkHandler($disassociate_ids, array($this, 'disassociateModifierListHandler'));
        }, function() use (&$delete_ids, &$disassociate_ids) {
            $this->squareup_diff->chunkHandler($delete_ids, array($this, 'deleteModifierListHandler'), true);
            $this->squareup_diff->chunkHandler($disassociate_ids, array($this, 'disassociateModifierListHandler'), true);

            $this->squareup_diff->prepareDisassociateBatches(true);
        }, false);
    }

    public function deleteModifierHandler(&$delete_ids) {
        $diff_info = array(
            'type' => 'delete_square',
            'last_id_map' => array(),
            'data' => array(
                'object_ids' => $delete_ids
            )
        );

        $this->squareup_diff->addDiff($diff_info);

        $diff_info = array(
            'type' => 'delete_opencart',
            'last_id_map' => array(),
            'data' => array(
                array(
                    'table' => 'squareup_catalog',
                    'where' => array(
                        'square_id' => $delete_ids
                    )
                )
            )
        );

        $this->squareup_diff->addDiff($diff_info);

        $this->squareup_diff->executeDiff();
    }

    public function deleteModifier() {
        $this->squareup_diff->output("OC OPTION: Delete modifiers...");

        $delete_ids = array();

        $sql = "SELECT sc.* FROM `" . DB_PREFIX . "squareup_catalog` sc LEFT JOIN `" . DB_PREFIX . "squareup_product_option_value_modifier` spovm ON (spovm.square_id = sc.square_id) LEFT JOIN `" . DB_PREFIX . "product_option_value` pov ON (pov.product_option_value_id = spovm.product_option_value_id) WHERE (spovm.square_id IS NULL OR pov.product_option_value_id IS NULL) AND sc.type='MODIFIER'";
        
        $this->squareup_diff->chunkedMap($sql, function(&$row) use (&$delete_ids) {
            // Modifiers are always deleted because they are part of a modifier_list
            $delete_ids[] = $row['square_id']; 

            $this->squareup_diff->chunkHandler($delete_ids, array($this, 'deleteModifierHandler'));
        }, function() use (&$delete_ids) {
            $this->squareup_diff->chunkHandler($delete_ids, array($this, 'deleteModifierHandler'), true);
        }, false);
    }
}