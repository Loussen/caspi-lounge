<?php

namespace Squareup\Diff;

use Squareup\Library;

class OpenCartCategory extends Library {
    public function work($product_ids = array()) {
        // Determine if any categories are different from those in Square and mark this in the relation tables by setting version = 0
        $this->updateVersionsInOpenCart($product_ids);

        // Delete redundant references between category's and Square CATEGORYs. This step is to detect and delete any links to CATEGORYs deleted from the Square dashboard
        $this->deleteCategoryCategory();

        // Detect any version differences and prepare upsertion batches
        $this->insertUpdate();

        // Delete redundant references between category's and Square CATEGORYs
        $this->deleteCategoryCategory();

        // Delete redundant Square CATEGORYs
        $this->deleteCategory();
    }

    public function insertUpdate() {
        $this->squareup_diff->output("OC CATEGORY: Upload categories...");

        $batch = array();

        $sql = "SELECT c.category_id, scc.square_id, sc.version, sc.present_at_location_ids, sc.absent_at_location_ids, sc.present_at_all_locations FROM `" . DB_PREFIX . "category` c LEFT JOIN `" . DB_PREFIX . "squareup_category_category` scc ON (scc.category_id = c.category_id) LEFT JOIN `" . DB_PREFIX . "squareup_catalog` sc ON (sc.square_id = scc.square_id) WHERE (scc.square_id IS NULL OR scc.version IS NULL OR scc.version != sc.version)";

        $this->squareup_diff->chunkedMap($sql, function(&$category) use (&$batch) {
            $upsert_id = is_null($category['square_id']) ? '#squareup_category_category:category_id:' . $category['category_id'] : $category['square_id'];
            $present_at_location_ids = is_null($category['present_at_location_ids']) ? array() : json_decode($category['present_at_location_ids'], true);

            $category_object = array(
                'type' => 'CATEGORY',
                'id' => $upsert_id,
                'absent_at_location_ids' => array(), // Required by the Square API
                'present_at_location_ids' => array(), // Required by the Square API
                'present_at_all_locations' => true, // Required by the Square API
                'category_data' => array(
                    'name' => $this->getCategoryName($category['category_id'])
                )
            );

            if (!is_null($category['version'])) {
                $category_object['version'] = (int)$category['version'];
            }

            $batch[] = $category_object;
        }, function() use (&$batch) {
            if (empty($batch)) {
                return;
            }

            $diff_info = array(
                'type' => 'upsert_square',
                'last_id_map' => array(
                    'CATEGORY' => 'squareup_category_category'
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

    public function updateVersionsInOpenCartHandler(&$update_ids) {
        $diff_info = array(
            'type' => 'update_opencart',
            'last_id_map' => array(),
            'data' => array(
                array(
                    'table' => 'squareup_category_category',
                    'set' => array(
                        'version' => 0
                    ),
                    'where' => array(
                        'squareup_category_category_id' => $update_ids
                    )
                )
            )
        );

        $this->squareup_diff->addDiff($diff_info);

        $this->squareup_diff->executeDiff();
    }

    public function updateVersionsInOpenCart($product_ids = array()) {
        $this->squareup_diff->output("OC CATEGORY: Find categories edited from within Square...");

        $update_ids = array();

        if (empty($product_ids)) {
            $sql = "SELECT * FROM `" . DB_PREFIX . "squareup_catalog` WHERE `type`='CATEGORY'";
        } else {
            $sql = "SELECT DISTINCT sc.* FROM `" . DB_PREFIX . "product_to_category` p2c LEFT JOIN `" . DB_PREFIX . "squareup_category_category` scc ON (scc.category_id = p2c.category_id) LEFT JOIN `" . DB_PREFIX . "squareup_catalog` sc ON (sc.square_id = scc.square_id) WHERE p2c.product_id IN (" . implode(",", $product_ids) . ") AND sc.`type`='CATEGORY'";
        }

        $this->squareup_diff->chunkedMap($sql, function(&$category) use (&$update_ids) {
            if (false !== $squareup_category_category_id = $this->hasToBeUpdated($category)) {
                $update_ids[] = $squareup_category_category_id;

                $this->squareup_diff->chunkHandler($update_ids, array($this, 'updateVersionsInOpenCartHandler'));
            }
        });

        $this->squareup_diff->chunkHandler($update_ids, array($this, 'updateVersionsInOpenCartHandler'), true);
    }

    public function hasToBeUpdated(&$category) {
        $data = json_decode($category['data'], true);

        $sql = "SELECT scc.squareup_category_category_id, scc.category_id FROM `" . DB_PREFIX . "squareup_category_category` scc LEFT JOIN `" . DB_PREFIX . "category` c ON (c.category_id = scc.category_id) WHERE c.category_id IS NOT NULL AND scc.square_id='" . $this->db->escape($category['square_id']) . "'";

        $result = $this->db->query($sql);

        if ($result->num_rows) {
            if ($this->getCategoryName($result->row['category_id']) != $data['name']) {
                return (int)$result->row['squareup_category_category_id'];
            }
        }

        return false;
    }

    public function getCategoryName($category_id) {
        $sql = "SELECT GROUP_CONCAT(cd1.name ORDER BY cp.level SEPARATOR ' > ') AS name FROM " . DB_PREFIX . "category_path cp LEFT JOIN " . DB_PREFIX . "category c1 ON (cp.category_id = c1.category_id) LEFT JOIN " . DB_PREFIX . "category c2 ON (cp.path_id = c2.category_id) LEFT JOIN " . DB_PREFIX . "category_description cd1 ON (cp.path_id = cd1.category_id) LEFT JOIN " . DB_PREFIX . "category_description cd2 ON (cp.category_id = cd2.category_id) WHERE cd1.language_id = '" . (int)$this->config->get('config_language_id') . "' AND cd2.language_id = '" . (int)$this->config->get('config_language_id') . "' AND cp.category_id=" . (int)$category_id;

        return html_entity_decode($this->db->query($sql)->row['name'], ENT_QUOTES, "UTF-8");
    }

    public function deleteCategoryCategoryHandler(&$delete_ids) {
        $diff_info = array(
            'type' => 'delete_opencart',
            'last_id_map' => array(),
            'data' => array(
                array(
                    'table' => 'squareup_category_category',
                    'where' => array(
                        'squareup_category_category_id' => $delete_ids
                    )
                )
            )
        );

        $this->squareup_diff->addDiff($diff_info);

        $this->squareup_diff->executeDiff();
    }

    public function deleteCategoryCategory() {
        $this->squareup_diff->output("OC CATEGORY: Delete references between categories...");

        $delete_ids = array();
        // Remove the ghost entries from squareup_category_category
        $sql = "SELECT scc.squareup_category_category_id FROM `" . DB_PREFIX . "squareup_category_category` scc LEFT JOIN `" . DB_PREFIX . "squareup_catalog` sc ON (sc.square_id = scc.square_id AND sc.type='CATEGORY') LEFT JOIN `" . DB_PREFIX . "category` c ON (c.category_id=scc.category_id) WHERE sc.square_id IS NULL OR scc.category_id IS NULL";

        $this->squareup_diff->chunkedMap($sql, function(&$row) use (&$delete_ids) {
            $delete_ids[] = $row['squareup_category_category_id'];

            $this->squareup_diff->chunkHandler($delete_ids, array($this, 'deleteCategoryCategoryHandler'));
        }, function() use (&$delete_ids) {
            $this->squareup_diff->chunkHandler($delete_ids, array($this, 'deleteCategoryCategoryHandler'), true);
        }, false);
    }

    public function areNonLocationItemsAssigned($category_square_id) {
        $limit = 1000;
        $page = 0;

        do {
            $sql = "SELECT present_at_all_locations, present_at_location_ids, data FROM `" . DB_PREFIX . "squareup_catalog` WHERE `type`='ITEM' LIMIT " . ($page * $limit) . "," . $limit;

            $result = $this->db->query($sql);
            $num_rows = $result->num_rows;

            if ($num_rows > 0) {
                foreach ($result->rows as $item) {
                    $data = json_decode($item['data'], true);

                    if (!isset($data['category_id']) || $data['category_id'] != $category_square_id) {
                        continue;
                    }

                    if (!empty($item['present_at_all_locations'])) {
                        return true;
                    } else {
                        $present_at_location_ids = json_decode($item['present_at_location_ids'], true);

                        foreach ($present_at_location_ids as $location_id) {
                            if ($location_id != $this->config->get('payment_squareup_location_id')) {
                                return true;
                            }
                        }
                    }
                }

                $page++;
            }
        } while ($num_rows > 0);

        return false;
    }

    public function deleteCategoryHandler(&$delete_ids) {
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
                    'table' => 'squareup_category_category',
                    'where' => array(
                        'square_id' => $delete_ids
                    )
                )
            )
        );

        $this->squareup_diff->addDiff($diff_info);

        $this->squareup_diff->executeDiff();
    }

    public function deleteCategory() {
        $this->squareup_diff->output("OC CATEGORY: Delete categories...");

        $delete_ids = array();
        $category_was_deleted = false;

        $sql = "SELECT sc.* FROM `" . DB_PREFIX . "squareup_catalog` sc LEFT JOIN `" . DB_PREFIX . "squareup_category_category` css ON (css.square_id = sc.square_id) LEFT JOIN `" . DB_PREFIX . "category` c ON (c.category_id = css.category_id) WHERE (css.square_id IS NULL OR c.category_id IS NULL) AND sc.type='CATEGORY'";

        $this->squareup_diff->chunkedMap($sql, function(&$row) use (&$delete_ids, &$category_was_deleted) {
            if (!$this->areNonLocationItemsAssigned($row['square_id'])) {
                $delete_ids[] = $row['square_id'];
                $category_was_deleted = true;

                $this->squareup_diff->chunkHandler($delete_ids, array($this, 'deleteCategoryHandler'));
            }
        }, function() use (&$delete_ids, &$category_was_deleted) {
            $this->squareup_diff->chunkHandler($delete_ids, array($this, 'deleteCategoryHandler'), true);

            return $category_was_deleted;
        }, false);
    }
}