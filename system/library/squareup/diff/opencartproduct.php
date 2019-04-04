<?php

namespace Squareup\Diff;

use Squareup\Library;

class OpenCartProduct extends Library {
    const UPPER_VARIATION_LIMIT = 80000;

    public function work($product_ids = array()) {
        // Determine if any products are different from those in Square and mark this in the relation tables by setting version = 0
        $this->updateVersionsInOpenCart($product_ids);

        // Delete redundant references between combinations and Square item variations. This step is to detect and delete any links to item variations deleted from the Square dashboard
        $this->deleteCombinationItemVariation();

        // Delete redundant references between product's and Square items. This step is to detect and delete any links to items deleted from the Square dashboard
        $this->deleteProductItem();

        // Detect any version differences and prepare upsertion batches
        $this->insertUpdate();

        // Delete redundant references between combinations and Square item variations
        $this->deleteCombinationItemVariation();

        // Delete redundant references between product's and Square items
        $this->deleteProductItem();

        // Delete redundant Square items
        $this->deleteItem();

        // Delete redundant Square item variations
        $this->deleteItemVariation();

        $this->squareup_diff_opencart_option->deleteProductOptionModifierList();

        $this->squareup_diff_opencart_option->deleteProductOptionValueModifier();
    }

    public function updateVersionsInOpenCartHandler(&$update_ids) {
        $diff_info = array(
            'type' => 'update_opencart',
            'last_id_map' => array(),
            'data' => array(
                array(
                    'table' => 'squareup_product_item',
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
        $this->squareup_diff->output("OC PRODUCT: Find items/item variations edited from within Square...");

        $update_ids = array();

        if (empty($product_ids)) {
            $sql = "SELECT * FROM `" . DB_PREFIX . "squareup_catalog` WHERE `type`='ITEM'";
        } else {
            $sql = "SELECT DISTINCT sc.* FROM `" . DB_PREFIX . "squareup_product_item` spi LEFT JOIN `" . DB_PREFIX . "squareup_catalog` sc ON (spi.square_id = sc.square_id) WHERE spi.product_id IN (" . implode(",", $product_ids) . ") AND sc.type='ITEM'";
        }

        $this->squareup_diff->chunkedMap($sql, function(&$item) use (&$update_ids) {
            if (false !== $squareup_item_id = $this->hasToBeUpdated($item)) {
                $update_ids[] = $squareup_item_id;

                $this->squareup_diff->chunkHandler($update_ids, array($this, 'updateVersionsInOpenCartHandler'));
            }
        });

        $this->squareup_diff->chunkHandler($update_ids, array($this, 'updateVersionsInOpenCartHandler'), true);
    }

    public function hasToBeUpdated(&$item) {
        $data = json_decode($item['data'], true);

        if (isset($data['variations'])) {
            $sql = "SELECT spi.product_id, spi.square_id, sciv.square_id as variation_id, scomb.price as variation_price, scomb.name as variation_name, scomb.subtract as variation_subtract FROM `" . DB_PREFIX . "squareup_product_item` spi LEFT JOIN `" . DB_PREFIX . "squareup_combination` scomb ON (scomb.product_id = spi.product_id) LEFT JOIN `" . DB_PREFIX . "squareup_combination_item_variation` sciv ON (sciv.squareup_combination_id = scomb.squareup_combination_id) LEFT JOIN `" . DB_PREFIX . "product` p ON (p.product_id = spi.product_id) WHERE p.product_id IS NOT NULL AND spi.square_id='" . $this->db->escape($item['square_id']) . "'";

            $result = $this->db->query($sql);

            if ($result->num_rows) {
                foreach ($result->rows as $row) {
                    if (is_null($row['variation_id'])) {
                        // This is a new variation in OpenCart, therefore, we need to upsert this item. We return its Square ID meaning it's internal version will be set to 0 in the method OpenCartProduct::updateVersionsInOpenCart
                        return $row['square_id'];
                    }

                    foreach ($data['variations'] as $variation) {
                        if ($variation['id'] == $row['variation_id'] && $this->hasProductDifference($data, $variation['item_variation_data'], $row)) {
                            // We only need to return the ITEM square_id, therefore it is okay if the iteration does not go through the rest of the varations. The whole ITEM along with all variations will be upserted at a later point because the item version would be set to 0.
                            return $row['square_id'];
                        }
                    }
                }
            }

            // Iterate through the variations and see if only a variation version has been changed after a download
            foreach ($data['variations'] as $variation) {
                $sql = "SELECT sciv.squareup_combination_item_variation_id FROM `" . DB_PREFIX . "squareup_combination_item_variation` sciv LEFT JOIN `" . DB_PREFIX . "squareup_catalog` sc ON (sc.square_id = sciv.square_id) WHERE sciv.square_id='" . $this->db->escape($variation['id']) . "' AND sc.version != sciv.version";

                if ($this->db->query($sql)->num_rows > 0) {
                    return $item['square_id'];
                }
            }
        } else {
            return $item['square_id'];
        }

        return false;
    }

    public function insertUpdate() {
        $this->squareup_diff->output("OC PRODUCT: Upload items...");

        $batch = array();
        $batchItemCount = 0;
        $total = 0;

        $sql = "SELECT p.product_id, spi.square_id, sc.version, sc.data, sc.present_at_location_ids, sc.absent_at_location_ids, sc.present_at_all_locations FROM `" . DB_PREFIX . "product` p LEFT JOIN `" . DB_PREFIX . "squareup_product_item` spi ON (spi.product_id = p.product_id) LEFT JOIN `" . DB_PREFIX . "squareup_catalog` sc ON (sc.square_id = spi.square_id) WHERE p.status=1 AND (spi.square_id IS NULL OR spi.version IS NULL OR spi.version != sc.version)";

        $this->squareup_diff->chunkedMap($sql, function(&$item) use (&$total, &$batchItemCount, &$batch) {
            $upsert_id = !is_null($item['square_id']) ? $item['square_id'] : '#squareup_product_item:product_id:' . $item['product_id'];
            
            $product_info = $this->getProductInfo($item['product_id']);

            $variations = array();
            $tax_ids = false;
            $modifier_list_info = false;

            if (!is_null($item['data'])) {
                $item_data = json_decode($item['data'], true);

                if (isset($item_data['variations'])) {
                    $variations = $item_data['variations'];
                }

                if (isset($item_data['tax_ids'])) {
                    $tax_ids = $item_data['tax_ids'];
                }

                if (isset($item_data['modifier_list_info'])) {
                    $modifier_list_info = $item_data['modifier_list_info'];
                }
            }

            $present_at_location_ids = is_null($item['present_at_location_ids']) ? array() : json_decode($item['present_at_location_ids'], true);

            $item_variations = $this->makeItemVariations($product_info, $upsert_id, $variations);

            $item_object = array(
                'type' => 'ITEM',
                'id' => $upsert_id,
                'absent_at_location_ids' => is_null($item['absent_at_location_ids']) ? array() : json_decode($item['absent_at_location_ids'], true),
                'present_at_all_locations' => !empty($item['present_at_all_locations']),
                'present_at_location_ids' => $this->squareup_diff->appendCurrentLocation($present_at_location_ids),
                'item_data' => array(
                    'name' => $product_info['name'],
                    'description' => $product_info['description'],
                    'available_online' => true,
                    'available_for_pickup' => true,
                    'available_electronically' => true,
                    'variations' => $item_variations,
                    'product_type' => 'REGULAR',
                    'skip_modifier_screen' => false
                )
            );

            if (false !== $product_info['category_id']) {
                $item_object['item_data']['category_id'] = $product_info['category_id'];
            }

            if (false !== $product_info['modifier_list_info'] && !$this->config->get('payment_squareup_ad_hoc_sync')) {
                // Use new modifier lists
                $item_object['item_data']['modifier_list_info'] = $product_info['modifier_list_info'];
            } else {
                // Use no modifier lists
                $item_object['item_data']['modifier_list_info'] = array();
            }
            //@todo - find out why the item versions are not being updated after we upsert without a modifier_list_info

            if (false !== $product_info['image_url']) {
                $item_object['item_data']['image_url'] = $product_info['image_url'];
            }

            if (false !== $tax_ids) {
                $item_object['tax_ids'] = $tax_ids;
            }

            if (!is_null($item['version'])) {
                $item_object['version'] = (int)$item['version'];
            }

            $total += count($item_variations);

            $thisCount = count($item_variations) + 1;

            if ($batchItemCount + $thisCount > 1000) {
                $this->uploadBatch($batch);
                $batchItemCount = 0;
            }

            if ($total >= self::UPPER_VARIATION_LIMIT) {
                // Break the whole upsert here. This is the upper limit the Square API can support
                return false;
            }

            $batch[] = $item_object;
            $batchItemCount += $thisCount;
        }, function() use (&$batch) {
            $this->uploadBatch($batch);
        });
    }

    public function uploadBatch(&$batch) {
        if (empty($batch)) {
            return;
        }

        $diff_info = array(
            'type' => 'upsert_square',
            'last_id_map' => array(
                'ITEM' => 'squareup_product_item',
                'ITEM_VARIATION' => 'squareup_combination_item_variation'
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
    }

    public function findSetVariationValue($key, $variations, $variation_id, $default_value) {
        foreach ($variations as $variation) {
            if (isset($variation['item_variation_data'][$key]) && $variation['id'] == $variation_id) {
                return $variation['item_variation_data'][$key];
            }
        }

        return $default_value;
    }

    public function makeItemVariations($product_info, $item_id, $variations) {
        $variations_sql = "SELECT scomb.squareup_combination_id, scomb.sku, scomb.upc, scomb.name, scomb.subtract, scomb.price, sc.square_id, sc.version, sc.present_at_location_ids, sc.absent_at_location_ids, sc.present_at_all_locations FROM `" . DB_PREFIX . "squareup_combination` scomb LEFT JOIN `" . DB_PREFIX . "squareup_combination_item_variation` sciv ON (scomb.squareup_combination_id = sciv.squareup_combination_id) LEFT JOIN `" . DB_PREFIX . "squareup_catalog` sc ON (sc.square_id = sciv.square_id) WHERE scomb.product_id=" . (int)$product_info['product_id'];

        $variation_objects = array();

        foreach ($this->db->query($variations_sql)->rows as $variation) {
            $variation_id = is_null($variation['square_id']) ? '#squareup_combination_item_variation:squareup_combination_id:' . $variation['squareup_combination_id'] : $variation['square_id'];

            $present_at_location_ids = is_null($variation['present_at_location_ids']) ? array() : json_decode($variation['present_at_location_ids'], true);

            $variation_object = array(
                'id' => $variation_id,
                'type' => 'ITEM_VARIATION',
                'absent_at_location_ids' => is_null($variation['absent_at_location_ids']) ? array() : json_decode($variation['absent_at_location_ids'], true),
                'present_at_all_locations' => !empty($variation['present_at_all_locations']),
                'present_at_location_ids' => $this->squareup_diff->appendCurrentLocation($present_at_location_ids),
                'item_variation_data' => array(
                    'item_id' => $item_id,
                    'name' => $variation['name'],
                    'sku' => $this->findSetVariationValue('sku', $variations, $variation_id, $variation['sku']),
                    'upc' => $this->findSetVariationValue('upc', $variations, $variation_id, $variation['upc']),
                    'pricing_type' => 'FIXED_PRICING',
                    'price_money' => $this->squareup_api->convertToSquarePrice($variation['price']),
                    'track_inventory' => (bool)$variation['subtract']
                )
            );

            if (!is_null($variation['version'])) {
                $variation_object['version'] = (int)$variation['version'];
            }

            $variation_objects[] = $variation_object;
        }

        return $variation_objects;
    }

    public function cleanHTML($html) {
        return trim(strip_tags(preg_replace('/\<br(\s*)?\/?\>/i', "\n", $html)));
    }

    public function getProductCategory($product_id) {
        $sql = "SELECT scc.square_id FROM `" . DB_PREFIX . "product_to_category` p2c LEFT JOIN `" . DB_PREFIX . "squareup_category_category` scc ON (scc.category_id = p2c.category_id) LEFT JOIN `" . DB_PREFIX . "category_description` cd ON cd.category_id = scc.category_id WHERE cd.language_id=" . (int)$this->config->get('config_language_id') . " AND p2c.product_id=" . (int)$product_id . " ORDER BY cd.name ASC LIMIT 0,1";

        $result = $this->db->query($sql);

        if ($result->num_rows > 0) {
            return $result->row['square_id'];
        }

        return false;
    }

    public function hasProductDifference(&$item_data, &$variation_data, &$row) {
        $product_id = (int)$row['product_id'];

        if (false !== $product_info = $this->getProductInfo($product_id)) {
            $square_name = isset($item_data['name']) ? $item_data['name'] : '';
            $square_description = isset($item_data['description']) ? $item_data['description'] : '';
            $square_track_inventory = (bool)$variation_data['track_inventory'];
            $square_category_id = isset($item_data['category_id']) ? $item_data['category_id'] : false;
            $square_image_url = isset($item_data['image_url']) ? $item_data['image_url'] : false;
            $square_modifier_list_info = isset($item_data['modifier_list_info']) ? $item_data['modifier_list_info'] : false;

            if (isset($variation_data['price_money'])) {
                $square_price = (float)$this->squareup_api->convertToLocalPrice($variation_data['price_money']);
            } else {
                $square_price = (float)0;
            }

            if ($row['variation_name'] == 'Regular') {
                // Perform a normal comparison without real variations
                $product_price = (float)$product_info['price'];
                $product_subtract = $product_info['subtract'];
                $variation_name_diff = false;
                $variation_sku = !empty($variation_data['sku']) ? $variation_data['sku'] : null;
                $variation_upc = !empty($variation_data['upc']) ? $variation_data['upc'] : null;

                $sku_diff = !empty($product_info['sku']) && $product_info['sku'] != $variation_sku;
                $upc_diff = !empty($product_info['upc']) && $product_info['upc'] != $variation_upc;
            } else {
                // Perform a comparison with the data contained in $row
                $product_price = (float)$row['variation_price'];
                $product_subtract = $row['variation_subtract'];
                $variation_name_diff = $row['variation_name'] != $variation_data['name'];
                $sku_diff = false;
                $upc_diff = false;
            }

            // if ($this->hasModifierListInfoDifference($square_modifier_list_info, $product_info['modifier_list_info'])) {
            //     var_dump($square_modifier_list_info, $product_info['modifier_list_info']);
            // }

            return 
                $product_subtract != $square_track_inventory ||
                $product_price != $square_price ||
                $product_info['category_id'] != $square_category_id ||
                $sku_diff ||
                $upc_diff ||
                $variation_name_diff ||
                $product_info['name'] != $square_name ||
                $product_info['image_url'] != $square_image_url ||
                $this->hasModifierListInfoDifference($square_modifier_list_info, $product_info['modifier_list_info']) ||
                $product_info['description'] != $square_description;
        }

        return true;
    }

    public function hasTaxIdsDifference($a, $b) {
        sort($a);
        sort($b);
        return json_encode($a) != json_encode($b);
    }

    public function getProductInfo($product_id) {
        $product_info = $this->db->query("SELECT pd.name, pd.description, p.model, p.sku, p.upc, p.price, p.tax_class_id, p.subtract, spii.url FROM `" . DB_PREFIX . "product` p LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (pd.product_id = p.product_id) LEFT JOIN `" . DB_PREFIX . "squareup_product_item_image` spii ON (spii.image = p.image) WHERE pd.language_id=" . (int)$this->config->get('config_language_id') . " AND p.product_id=" . (int)$product_id);

        if ($product_info->num_rows == 0) {
            return false;
        }

        $image_url = false;

        if (!empty($product_info->row['url'])) {
            $image_url = $product_info->row['url'];
        }

        $product_name = html_entity_decode($product_info->row['name'], ENT_QUOTES, "UTF-8");

        $product_name = str_replace(array('[', ']'), array('(', ')'), $product_name);

        if ($product_info->row['model']) {
            $product_name .= ' [' . $product_info->row['model'] . ']';
        }

        return array(
            'product_id' => (int)$product_id,
            'subtract' => (bool)$product_info->row['subtract'],
            'name' => $product_name,
            'description' => $this->cleanProductDescription($product_info->row['description']),
            'model' => $product_info->row['model'],
            'sku' => $product_info->row['sku'],
            'upc' => $product_info->row['upc'],
            'price' => (float)$product_info->row['price'],
            'category_id' => $this->getProductCategory($product_id),
            'image_url' => $image_url,
            'modifier_list_info' => $this->getProductModifierListInfo($product_id)
        );
    }

    public function getTaxIds($tax_class_id) {
        $result = array();
        if ($tax_class_id != 0) {
            $sql = "SELECT DISTINCT strt.square_id FROM `" . DB_PREFIX . "tax_rule` tru LEFT JOIN `" . DB_PREFIX . "squareup_tax_rate_tax` strt ON (tru.tax_rate_id = strt.tax_rate_id) WHERE tru.tax_class_id='" . $tax_class_id . "' AND strt.square_id IS NOT NULL";

            foreach ($this->db->query($sql)->rows as $row) {
                $result[] = $row['square_id'];
            }
        }

        return $result;
    }

    public function cleanProductDescription($text) {
        $text = html_entity_decode($text, ENT_QUOTES, "UTF-8");
        $text = $this->cleanHTML($text);

        // Square has an uppser limit of 4096 characters for the description
        return (string)substr($text, 0, 4096);
    }

    public function getProductModifierListInfo($product_id) {
        if ($this->config->get('payment_squareup_ad_hoc_sync')) {
            return false;
        }

        $sql = "SELECT spoml.square_id, o.type, po.required, (SELECT COUNT(*) FROM `" . DB_PREFIX . "product_option_value` pov_count WHERE pov_count.product_option_id = po.product_option_id) as option_value_count FROM `" . DB_PREFIX . "squareup_product_option_modifier_list` spoml LEFT JOIN `" . DB_PREFIX . "product_option` po ON (po.product_option_id = spoml.product_option_id) LEFT JOIN `" . DB_PREFIX . "option` o ON (o.option_id = po.option_id) WHERE po.product_id=" . (int)$product_id . " AND spoml.square_id IS NOT NULL AND (po.required=0 OR (po.required=1 AND o.type='checkbox'))";

        $result = array();

        foreach ($this->db->query($sql)->rows as $modifier_list) {
            if ($modifier_list['required'] == '1') {
                $min = 1;
            } else {
                $min = -1;
            }

            if ($modifier_list['type'] == 'checkbox') {
                $max = (int)$modifier_list['option_value_count'];
            } else {
                $max = 1;
            }

            $result[] = array(
                'modifier_list_id' => $modifier_list['square_id'],
                'min_selected_modifiers' => $min,
                'max_selected_modifiers' => $max,
                'enabled' => true
            );
        }

        return !empty($result) ? $result : false;
    }

    public function hasModifierListInfoDifference($square_modifier_list_info, $opencart_modifier_list_info) {
        if (
            false === $square_modifier_list_info && false !== $opencart_modifier_list_info ||
            false !== $square_modifier_list_info && false === $opencart_modifier_list_info
        ) {
            return true;
        }

        if (false === $square_modifier_list_info && false === $opencart_modifier_list_info) {
            return false;
        }

        if (count($square_modifier_list_info) != count($opencart_modifier_list_info)) {
            return true;
        }

        usort($square_modifier_list_info, array($this, 'sortModifierListInfo'));
        usort($opencart_modifier_list_info, array($this, 'sortModifierListInfo'));

        return json_encode($square_modifier_list_info) != json_encode($opencart_modifier_list_info);
    }

    public function sortModifierListInfo($m1, $m2) {
        return strcmp($m1['modifier_list_id'], $m2['modifier_list_id']);
    }

    public function deleteCombinationItemVariationHandler(&$delete_ids) {
        $diff_info = array(
            'type' => 'delete_opencart',
            'last_id_map' => array(),
            'data' => array(
                array(
                    'table' => 'squareup_combination_item_variation',
                    'where' => array(
                        'squareup_combination_item_variation_id' => $delete_ids
                    )
                )
            )
        );

        $this->squareup_diff->addDiff($diff_info);

        $this->squareup_diff->executeDiff();
    }

    public function deleteCombinationItemVariation() {
        $this->squareup_diff->output("OC PRODUCT: Delete references between combinations and item variations...");

        $delete_ids = array();

        // Remove the ghost entries from squareup_combination_item_variation
        $sql = "SELECT sciv.squareup_combination_item_variation_id FROM `" . DB_PREFIX . "squareup_combination_item_variation` sciv LEFT JOIN `" . DB_PREFIX . "squareup_catalog` sc ON (sc.square_id = sciv.square_id) LEFT JOIN `" . DB_PREFIX . "squareup_combination` scomb ON (scomb.squareup_combination_id=sciv.squareup_combination_id) WHERE sc.square_id IS NULL OR scomb.squareup_combination_id IS NULL";

        $this->squareup_diff->chunkedMap($sql, function(&$row) use (&$delete_ids) {
            $delete_ids[] = $row['squareup_combination_item_variation_id'];

            $this->squareup_diff->chunkHandler($delete_ids, array($this, 'deleteCombinationItemVariationHandler'));
        }, function() use (&$delete_ids) {
            $this->squareup_diff->chunkHandler($delete_ids, array($this, 'deleteCombinationItemVariationHandler'), true);
        }, false);
    }

    public function deleteProductItemHandler(&$delete_ids) {
        $diff_info = array(
            'type' => 'delete_opencart',
            'last_id_map' => array(),
            'data' => array(
                array(
                    'table' => 'squareup_product_item',
                    'where' => array(
                        'squareup_product_item_id' => $delete_ids
                    )
                )
            )
        );

        $this->squareup_diff->addDiff($diff_info);

        $this->squareup_diff->executeDiff();
    }

    public function deleteProductItem() {
        $this->squareup_diff->output("OC PRODUCT: Delete references between products and items...");

        $delete_ids = array();

        // Remove the ghost entries from squareup_product_item
        $sql = "SELECT spi.squareup_product_item_id FROM `" . DB_PREFIX . "squareup_product_item` spi LEFT JOIN `" . DB_PREFIX . "squareup_catalog` sc ON (sc.square_id = spi.square_id) LEFT JOIN `" . DB_PREFIX . "product` p ON (p.product_id=spi.product_id) WHERE sc.square_id IS NULL OR p.product_id IS NULL";

        $this->squareup_diff->chunkedMap($sql, function(&$row) use (&$delete_ids) {
            $delete_ids[] = $row['squareup_product_item_id'];

            $this->squareup_diff->chunkHandler($delete_ids, array($this, 'deleteProductItemHandler'));
        }, function() use (&$delete_ids) {
            $this->squareup_diff->chunkHandler($delete_ids, array($this, 'deleteProductItemHandler'), true);
        }, false);
    }

    public function disassociateItemVariationHandler(&$disassociate_ids) {
        $diff_info = array(
            'type' => 'delete_opencart',
            'last_id_map' => array(),
            'data' => array(
                array(
                    'table' => 'squareup_catalog',
                    'where' => array(
                        'square_id' => $disassociate_ids
                    )
                ),
                array(
                    'table' => 'squareup_combination_item_variation',
                    'where' => array(
                        'square_id' => $disassociate_ids
                    )
                )
            )
        );

        $this->squareup_diff->addDiff($diff_info);

        $this->squareup_diff->executeDiff();
    }

    public function deleteItemVariationHandler(&$delete_ids) {
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
                ),
                array(
                    'table' => 'squareup_combination_item_variation',
                    'where' => array(
                        'square_id' => $delete_ids
                    )
                )
            )
        );

        $this->squareup_diff->addDiff($diff_info);

        $this->squareup_diff->executeDiff();
    }

    public function deleteItemVariation() {
        $this->squareup_diff->output("OC PRODUCT: Delete item variations...");

        $delete_ids = array();
        $disassociate_ids = array();

        $sql = "SELECT sc.* FROM `" . DB_PREFIX . "squareup_catalog` sc LEFT JOIN `" . DB_PREFIX . "squareup_combination_item_variation` sciv ON (sciv.square_id = sc.square_id) LEFT JOIN `" . DB_PREFIX . "squareup_combination` scomb ON (scomb.squareup_combination_id = sciv.squareup_combination_id) WHERE (sciv.square_id IS NULL OR scomb.squareup_combination_id IS NULL) AND sc.type='ITEM_VARIATION' ORDER BY sc.square_id ASC";

        $this->squareup_diff->chunkedMap($sql, function(&$row) use (&$delete_ids, &$disassociate_ids) {
            $last_id_map = array(
                // 'ITEM' => 'squareup_product_item',
                'ITEM_VARIATION' => 'squareup_combination_item_variation'
            );

            $this->squareup_diff->determineDeleteOrDisassociate($row, $delete_ids, $disassociate_ids, $last_id_map);

            $this->squareup_diff->chunkHandler($delete_ids, array($this, 'deleteItemVariationHandler'));
            $this->squareup_diff->chunkHandler($disassociate_ids, array($this, 'disassociateItemVariationHandler'));
        }, function() use (&$delete_ids, &$disassociate_ids) {
            $this->squareup_diff->chunkHandler($delete_ids, array($this, 'deleteItemVariationHandler'), true);
            $this->squareup_diff->chunkHandler($disassociate_ids, array($this, 'disassociateItemVariationHandler'), true);

            $this->squareup_diff->prepareDisassociateBatches(true);
        }, false);
    }

    public function disassociateItemHandler(&$disassociate_ids) {
        $diff_info = array(
            'type' => 'delete_opencart',
            'last_id_map' => array(),
            'data' => array(
                array(
                    'table' => 'squareup_catalog',
                    'where' => array(
                        'square_id' => $disassociate_ids
                    )
                ),
                array(
                    'table' => 'squareup_product_item',
                    'where' => array(
                        'square_id' => $disassociate_ids
                    )
                )
            )
        );

        $this->squareup_diff->addDiff($diff_info);

        $this->squareup_diff->executeDiff();
    }

    public function deleteItemHandler(&$delete_ids) {
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
                ),
                array(
                    'table' => 'squareup_product_item',
                    'where' => array(
                        'square_id' => $delete_ids
                    )
                )
            )
        );

        $this->squareup_diff->addDiff($diff_info);

        $this->squareup_diff->executeDiff();
    }

    public function deleteItem() {
        $this->squareup_diff->output("OC PRODUCT: Delete items...");

        $delete_ids = array();
        $disassociate_ids = array();
        
        $sql = "SELECT sc.* FROM `" . DB_PREFIX . "squareup_catalog` sc LEFT JOIN `" . DB_PREFIX . "squareup_product_item` spi ON (spi.square_id = sc.square_id) WHERE spi.square_id IS NULL AND sc.type='ITEM' ORDER BY sc.square_id ASC";
        
        $this->squareup_diff->chunkedMap($sql, function(&$row) use (&$delete_ids, &$disassociate_ids) {
            $last_id_map = array(
                'ITEM' => 'squareup_product_item',
                'ITEM_VARIATION' => 'squareup_combination_item_variation'
            );

            $this->squareup_diff->determineDeleteOrDisassociate($row, $delete_ids, $disassociate_ids, $last_id_map);

            $this->squareup_diff->chunkHandler($delete_ids, array($this, 'deleteItemHandler'));
            $this->squareup_diff->chunkHandler($disassociate_ids, array($this, 'disassociateItemHandler'));
            $this->squareup_diff->chunkHandler($disassociate_ids, array($this, 'disassociateItemVariationHandler'));
        }, function() use (&$delete_ids, &$disassociate_ids) {
            $this->squareup_diff->chunkHandler($delete_ids, array($this, 'deleteItemHandler'), true);
            $this->squareup_diff->chunkHandler($disassociate_ids, array($this, 'disassociateItemHandler'), true);
            $this->squareup_diff->chunkHandler($disassociate_ids, array($this, 'disassociateItemVariationHandler'), true);

            $this->squareup_diff->prepareDisassociateBatches(true);
        }, false);
    }
}