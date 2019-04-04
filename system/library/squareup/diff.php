<?php

namespace Squareup;

class Diff extends Library {
    const CHUNK_MAP_SIZE = 1000;
    const CHUNK_HANDLER_SIZE = 200;
    const COMPLEX_INVENTORY_EMAIL_THRESHOLD = 25;
    const DELAYED_INSERT_UPDATE_COUNT = 1000;
    const REGEX_GEO_ZONE = '~\s*\[(.*?)\]\s*$~';
    const STATUS_DONE = 0;
    const STATUS_ERROR = 1;
    const STATUS_NOT_STARTED = -1;
    const DIFF_LOG_LAST_N_DAYS = 7;

    private $combinationIssues;
    private $complexInitialInventoriesCount = 0;
    private $complexInitialInventoriesLinks = array();
    private $delayedInsertObject = array();
    private $delayedInsertObjectDownloaded = array();
    private $delayedUpdateVersion = array();
    private $diffId;
    private $diffType;
    private $disassociateBatchCount = 0;
    private $disassociateBatches = array();
    private $disassociateCurrentBatch;
    private $initialInventories = array();
    private $isForcedDiff = false;
    private $maxAttempts = 3;

    public function syncInventories($is_webhook = false) {
        $this->initDiffId();
        $this->diffType = 'inventory';
        $this->isForcedDiff = $is_webhook; // Suppress the timeout check and the stdout

        $start = time();

        if ($this->config->get('payment_squareup_inventory_sync') != 'none') {
            $this->squareup_diff_square_inventory->work($is_webhook);
        }

        $end = time();

        $period = (int)($end - $start);

        $hours = str_pad((int)($period / 3600), 2, "0", STR_PAD_LEFT);
        $minutes = str_pad((int)($period / 60 % 60), 2, "0", STR_PAD_LEFT);
        $seconds = str_pad((int)($period % 60), 2, "0", STR_PAD_LEFT);

        $success_message = sprintf("Catalog sync finished successfully in: %s:%s:%s", $hours, $minutes, $seconds);

        $this->output($success_message);
    }

    public function syncOrderItems($source, $order_id) {
        $sql = "SELECT * FROM `" . DB_PREFIX . "order_product` WHERE order_id=" . (int)$order_id;

        $result = $this->db->query($sql);

        if ($result->num_rows > 0) {
            $product_ids = array();

            foreach ($result->rows as $row) {
                if (!in_array((int)$row['product_id'], $product_ids)) {
                    $product_ids[] = (int)$row['product_id'];
                }
            }

            if (!empty($product_ids)) {
                $sql_square_ids = "SELECT spi.square_id FROM `" . DB_PREFIX . "squareup_product_item` spi WHERE spi.product_id IN (" . implode(",", $product_ids) . ")";

                $result_square_ids = $this->db->query($sql_square_ids);

                if ($result_square_ids->num_rows > 0) {
                    $square_ids = array();

                    foreach ($result_square_ids->rows as $row) {
                        if (!in_array($row['square_id'], $square_ids)) {
                            $square_ids[] = $row['square_id'];
                        }
                    }

                    if (!empty($square_ids)) {
                        $this->syncItems($source, $square_ids, $product_ids);
                    }
                }
            }
        }
    }

    public function syncItems($source, $square_ids, $product_ids) {
        // Typically, the diffs are preserved as history of what happened
        // Feel free to use truncate for debugging purposes. Before you do, ensure you have a backup of squareup_diff
        $this->cleanDiffTable();

        $this->initDiffId();
        $this->diffType = 'order';

        $this->combinationIssues = array();
        $this->disassociateBatchCount = 0;
        $this->disassociateBatches = array();
        $this->initDisassociateCurrentBatch();
        $this->isForcedDiff = true; // Suppress the timeout check and the stdout

        $this->downloadSquareItems($square_ids);

        if ($source == 'square') {
            // Todo - should be used when we implement a Catalog sync in the direction Square > OpenCart
        } else if ($source == 'opencart') {
            $this->squareup_diff_opencart_option->work($product_ids);
            $this->squareup_diff_opencart_tax->work($product_ids);
            $this->squareup_diff_opencart_category->work($product_ids);
            $this->squareup_diff_opencart_combination->work($product_ids);
            $this->squareup_diff_opencart_product->work($product_ids);
        }

        // $this->prepareDisassociateBatches(true, array());
    }

    public function executeSync($source) {
        try {
            // Typically, the diffs are preserved as history of what happened
            // Feel free to use truncate for debugging purposes. Before you do, ensure you have a backup of squareup_diff
            $this->cleanDiffTable();

            $start = time();

            $this->initDiffId();

            $this->combinationIssues = array();
            $this->complexInitialInventoriesCount = 0;
            $this->complexInitialInventoriesLinks = array();
            $this->diffType = 'cron';
            $this->disassociateBatchCount = 0;
            $this->disassociateBatches = array();
            $this->initDisassociateCurrentBatch();
            $this->isForcedDiff = false;

            // Get the latest version of the relevant Square objects
            $this->measureStats(function() {
                $this->downloadSquareCatalog();
            });

            // Amend OpenCart tax names to include the correct Geo Zone in brackets
            // Deprecated - should be used when we implement a Catalog sync in the direction Square > OpenCart
            // $this->amendTaxNames();

            // Generate a diff for the options
            if ($source == 'square') {
                // Deprecated - should be used when we implement a Catalog sync in the direction Square > OpenCart
                // $this->output("Initializing sync from Square to OpenCart...");
                // $this->squareup_diff_square_option->work();
                // $this->squareup_diff_square_category->work();
                // $this->squareup_diff_square_tax->work();
                // $this->squareup_diff_square_product->work();
            } else if ($source == 'opencart') {
                $this->output("Initializing sync from OpenCart to Square...");

                $this->measureStats(function() {
                    $this->squareup_diff_opencart_option->work();
                });

                $this->measureStats(function() {
                    $this->squareup_diff_opencart_tax->work();
                });

                $this->measureStats(function() {
                    $this->squareup_diff_opencart_category->work();
                });

                $this->measureStats(function() {
                    $this->squareup_diff_opencart_combination->work();
                });

                $this->measureStats(function() {
                    $this->squareup_diff_opencart_product->work();
                });
            }

            if ($this->config->get('payment_squareup_inventory_sync') != 'none') {
                $this->measureStats(function() {
                    $this->squareup_diff_square_inventory->work();
                });
            }

            // $this->measureStats(function() {
            //     $this->prepareDisassociateBatches(true);
            // });

            $end = time();

            $period = (int)($end - $start);

            $hours = str_pad((int)($period / 3600), 2, "0", STR_PAD_LEFT);
            $minutes = str_pad((int)($period / 60 % 60), 2, "0", STR_PAD_LEFT);
            $seconds = str_pad((int)($period % 60), 2, "0", STR_PAD_LEFT);

            $success_message = sprintf("Catalog sync finished successfully in: %s:%s:%s", $hours, $minutes, $seconds);

            $this->output($success_message);

            $this->setInitialSyncFlag();

            return array(
                'success' => $success_message,
                'warning' => array(
                    'combination_issues' => $this->combinationIssues,
                    'complex_initial_inventories_count' => $this->complexInitialInventoriesCount,
                    'complex_initial_inventories_links' => $this->complexInitialInventoriesLinks
                )
            );
        } catch (\Squareup\Exception\Api $e) {
            $this->output($e->getMessage());

            throw $e;
        } catch (\Exception $e) {
            $this->output($e->getMessage());

            throw $e;
        }
    }

    public function chunkedMap($sql, $callback, $page_callback = null, $increment_page = true) {
        $limit = self::CHUNK_MAP_SIZE;
        $page = 0;

        do {
            $result = $this->db->query($sql .  " LIMIT " . ($page * $limit) . "," . $limit);

            foreach ($result->rows as &$row) {
                if (false === $callback($row)) {
                    break;
                }
            }

            if (is_callable($page_callback)) {
                if (false === $page_callback()) {
                    break;
                }
            }

            if ($increment_page) {
                $page++;
            }
        } while ($result->num_rows > 0);
    }

    public function chunkHandler(&$ids, $callback, $forced = false) {
        if (empty($ids) || (!$forced && count($ids) < self::CHUNK_HANDLER_SIZE)) {
            return;
        }

        $callback($ids);

        $ids = array();
    }

    public function getDiffId() {
        return $this->diffId;
    }

    public function logCombinationIssue($message) {
        $this->combinationIssues[] = $message;
    }

    public function output($message) {
        if ($this->isForcedDiff) {
            return;
        }

        if (defined('STDOUT')) {
            fwrite(STDOUT, date('Y-m-d H:i:s - ') . $message . PHP_EOL);
        } else {
            echo date('Y-m-d H:i:s - ') . $message . '<br /><hr />';
        }
    }

    public function getLanguageIds() {
        $result = array();

        $sql = "SELECT language_id FROM `" . DB_PREFIX . "language`";

        foreach ($this->db->query($sql)->rows as $row) {
            $result[] = (int)$row['language_id'];
        }

        return $result;
    }

    public function getStoreIds() {
        $result = array(0);

        $this->load->model('setting/store');

        foreach ($this->model_setting_store->getStores() as $store) {
            $result[] = (int)$store['store_id'];
        }

        return $result;
    }

    public function getLocationOverrideOrDefault($variation_data, $key) {
        if (isset($variation_data['location_overrides']) && is_array($variation_data['location_overrides'])) {
            foreach ($variation_data['location_overrides'] as $location_override) {
                if ($location_override['location_id'] == $this->config->get('payment_squareup_location_id')) {
                    if (isset($location_override[$key])) {
                        return $location_override[$key];
                    }
                }
            }
        }

        if (isset($variation_data[$key])) {
            return $variation_data[$key];
        }

        return null;
    }

    public function appendCurrentLocation($locations) {
        if (!in_array($this->config->get('payment_squareup_location_id'), $locations)) {
            $locations[] = $this->config->get('payment_squareup_location_id');
        }

        return $locations;
    }

    public function getCustomerGroupIds() {
        $result = array();

        $sql = "SELECT customer_group_id FROM `" . DB_PREFIX . "customer_group`";

        foreach ($this->db->query($sql)->rows as $row) {
            $result[] = (int)$row['customer_group_id'];
        }

        return $result;
    }

    public function downloadSquareItems($item_ids) {
        $result = $this->squareup_api->listItems($item_ids);

        if (isset($result['objects']) && is_array($result['objects'])) {
            foreach ($result['objects'] as $object) {
                $this->pushDelayedInsertObject($object);

                if (!empty($object['item_data']['variations'])) {
                    foreach ($object['item_data']['variations'] as $variation) {
                        $this->pushDelayedInsertObject($variation);
                    }
                }

                if (!empty($object['modifier_list_data']['modifiers'])) {
                    foreach ($object['modifier_list_data']['modifiers'] as $modifier) {
                        $this->pushDelayedInsertObject($modifier);
                    }
                }
            }
        }

        if (isset($result['related_objects']) && is_array($result['related_objects'])) {
            $modifier_list_ids = array();

            foreach ($result['related_objects'] as $object) {
                $this->pushDelayedInsertObject($object);

                if ($object['type'] == 'MODIFIER_LIST') {
                    $modifier_list_ids[] = $object['id'];
                }
            }

            if (!empty($modifier_list_ids)) {
                $this->downloadSquareItems($modifier_list_ids);
            }
        }

        $this->runDelayedInsertObjectQuery();
    }

    public function downloadSquareCatalog() {
        $this->output("Downloading Square catalog...");

        $this->prepareTemporaryDownloadedTable();

        do {
            $cursor = isset($result['cursor']) ? $result['cursor'] : '';

            $types = array('MODIFIER_LIST', 'ITEM', 'CATEGORY', 'MODIFIER', 'ITEM_VARIATION', 'TAX');

            $result = $this->squareup_api->listCatalog($cursor, $types);

            if (isset($result['objects']) && is_array($result)) {
                $this->delayedInsertObject = array();
                $this->delayedInsertObjectDownloaded = array();

                foreach ($result['objects'] as &$object) {
                    $this->pushDelayedInsertObject($object);
                    $this->pushDownloadedObject($object['id']);
                }

                $this->runDelayedInsertObjectQuery();
                $this->runDownloadedObjectQuery();
            }
        } while (isset($result['cursor']));

        $this->deleteNonDownloadedObjects();
    }

    public function addDiff(&$data) {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "squareup_diff` SET `diff_id`='" . $this->db->escape($this->diffId) . "', `diff_type`='" . $this->db->escape($this->diffType) . "', `type`='" . $this->db->escape($data['type']) . "', `last_id_map`='" . $this->db->escape(json_encode($data['last_id_map'])) . "', `data`='" . $this->db->escape(json_encode($data['data'])) . "', `created_on`='" . date('Y-m-d H:i:s') . "'");

        return $this->db->getLastId();
    }

    public function updateDiff(&$data) {
        $this->db->query("UPDATE `" . DB_PREFIX . "squareup_diff` SET attempt=" . (int)$data['attempt'] . ", status=" . (int)$data['status'] . ", executed_on='" . $this->db->escape($data['executed_on']) . "', message='" . $this->db->escape($data['message']) . "' WHERE squareup_diff_id=" . (int)$data['squareup_diff_id']);
    }

    public function cleanDiffTable() {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "squareup_diff` WHERE DATEDIFF(NOW(), `created_on`) > " . self::DIFF_LOG_LAST_N_DAYS);
    }

    public function executeDiff($squareup_diff_id = null) {
        $this->timeoutable(function() use (&$squareup_diff_id) {
            $this->executeTimeoutableDiff($squareup_diff_id);
        });
    }

    public function escapeValue(&$value) {
        if ($value === "NOW()") {
            return $value;
        }

        return "'" . $this->db->escape($value) . "'";
    }

    public function determineDeleteOrDisassociate(&$row, &$delete_ids, &$disassociate_ids, &$last_id_map) {
        $present_at_location_ids = json_decode($row['present_at_location_ids'], true);
        $absent_at_location_ids = json_decode($row['absent_at_location_ids'], true);
        $data = json_decode($row['data'], true);
        $has_been_unset = false;

        foreach ($present_at_location_ids as $index => $location_id) {
            if ($location_id == $this->config->get('payment_squareup_location_id')) {
                unset($present_at_location_ids[$index]);
                $has_been_unset = true;
            }
        }

        $present_at_location_ids = array_values($present_at_location_ids);

        if (!empty($row['present_at_all_locations']) && !$has_been_unset && $row['type'] != 'CATEGORY' && !in_array($this->config->get('payment_squareup_location_id'), $absent_at_location_ids)) {
            $absent_at_location_ids = $this->appendCurrentLocation($absent_at_location_ids);
            $has_been_unset = true;
        }

        if ((count($present_at_location_ids) >= 1 || !empty($row['present_at_all_locations'])) && $has_been_unset) {
            $object = array(
                'id' => $row['square_id'],
                'version' => (int)$row['version'],
                'type' => $row['type'],
                'present_at_all_locations' => !empty($row['present_at_all_locations']),
                'present_at_location_ids' => $present_at_location_ids,
                'absent_at_location_ids' => $absent_at_location_ids
            );

            if ($row['type'] == 'ITEM') {
                foreach ($data['variations'] as &$variation) {
                    $variation['present_at_location_ids'] = $present_at_location_ids;
                    $variation['absent_at_location_ids'] = $absent_at_location_ids;
                    $disassociate_ids[] = $variation['id'];
                }
            }

            $object[strtolower($row['type']) . '_data'] = $data;

            $this->disassociateCurrentBatch['objects'][] = $object;
            $this->disassociateCurrentBatch['last_id_map'] = $last_id_map;
            $disassociate_ids[] = $row['square_id'];
        } else if (count($present_at_location_ids) == 0 && empty($row['present_at_all_locations'])) {
            $delete_ids[] = $row['square_id'];
        }

        // $this->prepareDisassociateBatches(false);
    }

    protected function initDiffId() {
        $this->diffId = md5(microtime(true));

        $this->load->model('extension/payment/squareup');

        $this->model_extension_payment_squareup->editSquareSetting(array(
            'payment_squareup_last_sync_diff_id' => $this->diffId
        ));
    }

    protected function initDisassociateCurrentBatch() {
        $this->disassociateCurrentBatch = array(
            'objects' => array(),
            'last_id_map' => array()
        );
    }

    protected function timeoutable($callback) {
        if (!$this->isForcedDiff) {
            $this->checkTimeOut();
        }

        $callback();
    }

    public function measureStats($callback) {
        $start = microtime(true);
        $callback();
        $end = microtime(true);
        $this->output(sprintf("Sub-task finished in: %s seconds | Peak memory usage: %s | Current memory usage: %s", $end - $start, memory_get_peak_usage(true), memory_get_usage(true)));
    }

    protected function deleteNonDownloadedObjects() {
        $subquery = "square_id NOT IN (SELECT square_id FROM `" . DB_PREFIX . "squareup_catalog_downloaded`)";

        $sql_existing = "SELECT square_id FROM `" . DB_PREFIX . "squareup_catalog` WHERE " . $subquery . " LIMIT 0,1";

        if ($this->db->query($sql_existing)->num_rows == 0) {
            return;
        }

        $this->output("Removing non-downloaded objects...");

        $diff_info = array(
            'type' => 'delete_opencart',
            'last_id_map' => array(),
            'data' => array(
                array(
                    'table' => 'squareup_catalog',
                    'subquery_where' => $subquery
                )
            )
        );

        $squareup_diff_id = $this->addDiff($diff_info);

        $this->executeDiff($squareup_diff_id);
    }

    protected function prepareTemporaryDownloadedTable() {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "squareup_catalog_downloaded`");
        $this->db->query("CREATE TEMPORARY TABLE `" . DB_PREFIX . "squareup_catalog_downloaded` (
         `square_id` varchar(24),
         PRIMARY KEY `square_id` (`square_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    }

    protected function pushDownloadedObject($square_id) {
        if (count($this->delayedInsertObjectDownloaded) >= self::DELAYED_INSERT_UPDATE_COUNT) {
            $this->runDownloadedObjectQuery();
        }

        $this->delayedInsertObjectDownloaded[] = $square_id;
    }

    protected function runDownloadedObjectQuery() {
        if (empty($this->delayedInsertObjectDownloaded)) {
            return;
        }

        $sql_temp = "INSERT INTO `" . DB_PREFIX . "squareup_catalog_downloaded` (`square_id`) VALUES ";
        $values_temp = array();

        foreach ($this->delayedInsertObjectDownloaded as &$square_id) {
            $values_temp[] = "('" . $this->db->escape($square_id) . "')";
        }

        $sql_temp .= implode(",", $values_temp);
        $this->db->query($sql_temp);

        $this->delayedInsertObjectDownloaded = array();
    }

    protected function pushDelayedInsertObject(&$object) {
        if (count($this->delayedInsertObject) >= self::DELAYED_INSERT_UPDATE_COUNT) {
            $this->runDelayedInsertObjectQuery();
        }

        $sql = "SELECT square_id FROM `" . DB_PREFIX . "squareup_catalog` WHERE square_id='" . $this->db->escape($object['id']) . "' AND version='" . $this->db->escape($object['version']) . "'";

        $exists = $this->db->query($sql);

        if ($exists->num_rows > 0) {
            return false;
        }

        $present_at_location_ids = isset($object['present_at_location_ids']) ? $object['present_at_location_ids'] : array();
        $absent_at_location_ids = isset($object['absent_at_location_ids']) ? $object['absent_at_location_ids'] : array();
        $present_at_all_locations = !empty($object['present_at_all_locations']);

        if (in_array($this->config->get('payment_squareup_location_id'), $absent_at_location_ids)) {
            return false;
        }

        if (!empty($present_at_location_ids) && !$present_at_all_locations && !in_array($this->config->get('payment_squareup_location_id'), $present_at_location_ids)) {
            return false;
        }

        $this->delayedInsertObject[] = array(
            $object['type'],
            $object['id'],
            $object['version'],
            json_encode($object[strtolower($object['type']) . '_data']),
            (int)$present_at_all_locations,
            json_encode($present_at_location_ids),
            json_encode($absent_at_location_ids)
        );

        return true;
    }

    protected function runDelayedInsertObjectQuery() {
        if (empty($this->delayedInsertObject)) {
            return;
        }

        $diff_info = array(
            'type' => 'create_opencart',
            'last_id_map' => array(),
            'data' => array(
                array(
                    'table' => 'squareup_catalog',
                    'multiple' => array(
                        'keys' => array(
                            'type',
                            'square_id',
                            '#version',
                            '#data',
                            '#present_at_all_locations',
                            '#present_at_location_ids',
                            '#absent_at_location_ids'
                        ),
                        'values' => $this->delayedInsertObject
                    )
                )
            )
        );

        $squareup_diff_id = $this->addDiff($diff_info);

        $this->executeDiff($squareup_diff_id);

        $this->delayedInsertObject = array();
    }

    protected function executeTimeoutableDiff($squareup_diff_id = null) {
        $limit = 1000;
        $page = 0;

        do {
            if (is_null($squareup_diff_id)) {
                $sql = "SELECT * FROM `" . DB_PREFIX . "squareup_diff` WHERE diff_id='" . $this->db->escape($this->diffId) . "' AND status=" . self::STATUS_NOT_STARTED . " ORDER BY squareup_diff_id ASC LIMIT " . ($page * $limit) . "," . $limit;
            } else {
                $sql = "SELECT * FROM `" . DB_PREFIX . "squareup_diff` WHERE squareup_diff_id='" . (int)$squareup_diff_id . "' AND status=" . self::STATUS_NOT_STARTED . " ORDER BY squareup_diff_id ASC LIMIT " . ($page * $limit) . "," . $limit;
            }

            $result = $this->db->query($sql);
            $num_rows = $result->num_rows;

            if ($num_rows > 0) {
                foreach ($result->rows as $row) {
                    $row['data'] = json_decode($row['data'], true);
                    $row['last_id_map'] = json_decode($row['last_id_map'], true);

                    while ($row['status'] != self::STATUS_DONE && $row['attempt'] < $this->maxAttempts) {
                        $row['attempt'] = (int)$row['attempt'] + 1;
                        $this->executeDiffHandler($row);
                        $row['executed_on'] = date('Y-m-d H:i:s');
                    };

                    $this->updateDiff($row);

                    if ($row['status'] == self::STATUS_ERROR) {
                        $this->output(sprintf("Error: %s", $row['message']));
                        throw new \Squareup\Exception\Api($this->registry, $row['message']); // Terminate the whole script and return the error.
                    }
                }

                $page++;
            }
        } while ($num_rows > 0);
    }

    public function prepareDisassociateBatches($force = false) {
        if (count($this->disassociateCurrentBatch) > 0) {
            $this->disassociateBatches[] = $this->disassociateCurrentBatch;

            $this->initDisassociateCurrentBatch();
        }

        if (count($this->disassociateBatches) == 10 || ($force && count($this->disassociateBatches) > 0)) {
            $this->output("Disassociating Square objects from the Square location...");

            foreach ($this->disassociateBatches as $batch) {
                $diff_info = array(
                    'type' => 'disassociate_square',
                    'last_id_map' => $batch['last_id_map'],
                    'data' => array(
                        'idempotency_key' => $this->getDiffId() . '.' . md5(microtime(true)),
                        'batches' => array(
                            array(
                                'objects' => $batch['objects']
                            )
                        )
                    )
                );

                if (!empty($batch['objects'])) {
                    $this->addDiff($diff_info);
                }
            }

            $this->executeDiff();

            $this->disassociateBatches = array();
        }
    }

    protected function amendTaxNames() {
        $this->output("Amending OpenCart Tax Rate names to include Geo Zones...");

        $sql = "SELECT gz.name as geo_zone, tr.name, tr.tax_rate_id FROM `" . DB_PREFIX . "tax_rate` tr LEFT JOIN `" . DB_PREFIX . "geo_zone` gz ON (gz.geo_zone_id = tr.geo_zone_id) WHERE tr.type='P'";

        $update_opencart_data = array();

        foreach ($this->db->query($sql)->rows as $row) {
            if (preg_match(self::REGEX_GEO_ZONE, $row['name'])) {
                continue;
            }

            $new_name = $this->makeTaxName($row['name'], $row['geo_zone']);

            if (strtolower(str_replace(' ', '', $row['name'])) != strtolower(str_replace(' ', '', $new_name))) {
                $update_opencart_data[] = array(
                    'table' => 'tax_rate',
                    'set' => array(
                        'name' => $new_name
                    ),
                    'where' => array(
                        'tax_rate_id' => $row['tax_rate_id']
                    )
                );
            }
        }

        if (!empty($update_opencart_data)) {
            $diff_info = array(
                'type' => 'update_opencart',
                'last_id_map' => array(),
                'data' => $update_opencart_data
            );

            $this->addDiff($diff_info);

            $this->executeDiff();
        }
    }

    protected function makeTaxName($name, $geo_zone) {
        if (is_null($geo_zone)) {
            return $name;
        } else {
            return preg_replace(self::REGEX_GEO_ZONE, '', $name) . ' [' . $geo_zone . ']';
        }
    }

    protected function executeDiffHandler(&$diff) {
        switch ($diff['type']) {
            case 'create_opencart' : $this->createOpenCart($diff); break;
            case 'update_opencart' : $this->updateOpenCart($diff); break;
            case 'delete_opencart' : $this->deleteOpenCart($diff); break;
            case 'upsert_square' : $this->upsertSquare($diff); break;
            case 'delete_square' : $this->deleteSquare($diff); break;
            case 'disassociate_square' : $this->disassociateSquare($diff); break;
        }
    }

    protected function createOpenCart(&$diff) {
        try {
            $last_id_map = array();

            foreach ($diff['data'] as $insert) {
                $table = "`" . DB_PREFIX . $insert['table'] . "`";

                $keys = array();
                $values = array();
                $on_duplicate = array();

                if (isset($insert['set'])) {
                    foreach ($insert['set'] as $key => $value) {
                        if (stripos($value, '@') === 0 && array_key_exists($value, $last_id_map)) {
                            $value = $last_id_map[$value];
                        }

                        if (stripos($key, '#') === 0) {
                            $key = substr($key, 1);
                            $use_on_duplicate = true;
                        } else {
                            $use_on_duplicate = false;
                        }

                        $set_string = "`" . $key . "`=" . $this->escapeValue($value);

                        $values[] = $set_string;

                        if ($use_on_duplicate) {
                            $on_duplicate[] = $set_string;
                        }
                    }

                    $sql = "INSERT INTO " . $table . " SET " . implode(', ', $values);
                } else if (isset($insert['multiple'])) {
                    foreach ($insert['multiple']['keys'] as $key) {
                        if (stripos($key, '#') === 0) {
                            $key = substr($key, 1);
                            $on_duplicate[] = "`" . $key . "`=VALUES(`" . $key . "`)";
                        }

                        $keys[] = "`" . $key . "`";
                    }

                    foreach ($insert['multiple']['values'] as $collection) {
                        $parts = array();
                        foreach ($collection as $value) {
                            $parts[] = $this->escapeValue($value);
                        }
                        $values[] = "(" . implode(",", $parts) . ")";
                    }

                    $sql = "INSERT INTO " . $table . " (" . implode(",", $keys) . ") VALUES " . implode(', ', $values);
                }

                if (!empty($on_duplicate)) {
                    $sql .= " ON DUPLICATE KEY UPDATE " . implode(', ', $on_duplicate);
                }

                $this->db->query($sql);

                if (array_key_exists($insert['table'], $diff['last_id_map'])) {
                    $key = $diff['last_id_map'][$insert['table']];

                    $last_id_map[$key] = $this->db->getLastId();
                }
            }
            
            $diff['status'] = self::STATUS_DONE;
        } catch (\Exception $e) {
            $diff['status'] = self::STATUS_ERROR;
            $diff['attempt'] = $this->maxAttempts;
            $diff['message'] = $e->getMessage();
        }
    }

    protected function updateOpenCart(&$diff) {
        try {
            foreach ($diff['data'] as $update) {
                $table = "`" . DB_PREFIX . $update['table'] . "`";

                $values = array();
                $where = array();

                foreach ($update['set'] as $key => $value) {
                    $values[] = "`" . $key . "`=" . $this->escapeValue($value);
                }

                foreach ($update['where'] as $where_key => $condition) {
                    if (is_array($condition)) {
                        $where[] = "`" . $where_key . "` IN (" . implode(',', array_map(array($this, 'escapeValue'), $condition)) . ")";
                    } else {
                        $where[] = "`" . $where_key . "`=" . $this->escapeValue($condition);
                    }
                }

                $sql = "UPDATE " . $table . " SET " . implode(', ', $values);

                if (!empty($where)) {
                    $sql .= " WHERE " . implode(' AND ', $where);
                }

                $this->db->query($sql);
            }
            
            $diff['status'] = self::STATUS_DONE;
        } catch (\Exception $e) {
            $diff['status'] = self::STATUS_ERROR;
            $diff['attempt'] = $this->maxAttempts;
            $diff['message'] = $e->getMessage();
        }
    }

    protected function deleteOpenCart(&$diff) {
        try {
            foreach ($diff['data'] as $delete) {
                $table = "`" . DB_PREFIX . $delete['table'] . "`";

                $where = array();

                if (isset($delete['where'])) {
                    foreach ($delete['where'] as $where_key => $condition) {
                        if (is_array($condition)) {
                            $where[] = "`" . $where_key . "` IN (" . implode(',', array_map(array($this, 'escapeValue'), $condition)) . ")";
                        } else {
                            $where[] = "`" . $where_key . "`=" . $this->escapeValue($condition);
                        }
                    }
                }

                if (isset($delete['subquery_where'])) {
                    $where[] = $delete['subquery_where'];
                }

                $sql = "DELETE FROM " . $table;

                if (!empty($where)) {
                    $sql .= " WHERE " . implode(' AND ', $where);
                }

                $this->db->query($sql);
            }
            
            $diff['status'] = self::STATUS_DONE;
        } catch (\Exception $e) {
            $diff['status'] = self::STATUS_ERROR;
            $diff['attempt'] = $this->maxAttempts;
            $diff['message'] = $e->getMessage();
        }
    }

    protected function checkTimeOut() {
        $this->load->model('extension/payment/squareup');

        if ($this->model_extension_payment_squareup->cronHasTimedOut()) {
            $message = "The Catalog Sync task has timed out.";

            $this->output($message);

            throw new \Squareup\Exception\Timeout($message);
        }
    }

    protected function setInitialSyncFlag() {
        $this->load->model('extension/payment/squareup');
        
        $this->model_extension_payment_squareup->editSquareSetting(array(
            'payment_squareup_initial_sync' => '1'
        ));
    }

    protected function pushDelayedUpdateVersion($type, $last_id_map, $version, $square_id) {
        if (count($this->delayedUpdateVersion) >= self::DELAYED_INSERT_UPDATE_COUNT) {
            $this->runDelayedUpdateVersionQuery();
        }

        if (array_key_exists($type, $last_id_map)) {
            $table = $last_id_map[$type];

            $id_column = $last_id_map[$type] . '_id';

            $sql_id = "SELECT `" . $id_column . "` FROM `" . DB_PREFIX . $table . "` WHERE square_id='" . $this->db->escape($square_id) . "'";

            $result = $this->db->query($sql_id);

            if ($result->num_rows > 0) {
                $this->pushDelayedUpdateVersionEntry($table, $id_column, (int)$result->row[$id_column], $version, $square_id);
            }
        }
    }

    protected function pushDelayedUpdateVersionEntry($reference_table, $id_column, $id, $version, $square_id) {
        $keys = array();
        $values = array();

        $keys[] = $id_column;
        $keys[] = '#version';
        $keys[] = '#square_id';

        $values[] = $id;
        $values[] = $version;
        $values[] = $square_id;

        $this->delayedUpdateVersion[] = array(
            'table' => $reference_table,
            'keys' => $keys,
            'values' => $values
        );
    }

    protected function runDelayedUpdateVersionQuery() {
        if (empty($this->delayedUpdateVersion)) {
            return;
        }

        $data = array();

        foreach ($this->delayedUpdateVersion as $delayed_update) {
            if (!isset($data[$delayed_update['table']])) {
                $data[$delayed_update['table']] = array(
                    'table' => $delayed_update['table'],
                    'multiple' => array(
                        'keys' => $delayed_update['keys'],
                        'values' => array()
                    )
                );
            }

            $data[$delayed_update['table']]['multiple']['values'][] = $delayed_update['values'];
        }

        $diff_info = array(
            'type' => 'create_opencart',
            'last_id_map' => array(),
            'data' => array_values($data)
        );

        $squareup_diff_id = $this->addDiff($diff_info);

        $this->executeDiff($squareup_diff_id);

        $this->delayedUpdateVersion = array();
    }

    protected function upsertSquare(&$diff) {
        try {
            $result = $this->squareup_api->batchUpsertCatalog($diff['data']);
            $version_map = array();

            $this->delayedInsertObject = array();
            $this->delayedUpdateVersion = array();

            if (isset($result['objects']) && is_array($result['objects'])) {
                foreach ($result['objects'] as $object) {
                    $this->pushDelayedInsertObject($object);
                    $version_map[$object['id']] = $object['version'];

                    if ($object['type'] == 'MODIFIER_LIST') {
                        foreach ($object['modifier_list_data']['modifiers'] as $modifier) {
                            $this->pushDelayedInsertObject($modifier);
                            $version_map[$modifier['id']] = $modifier['version'];
                        }
                    }

                    if ($object['type'] == 'ITEM') {
                        foreach ($object['item_data']['variations'] as $variation) {
                            $this->pushDelayedInsertObject($variation);

                            $this->pushDelayedUpdateVersion($variation['type'], $diff['last_id_map'], $variation['version'], $variation['id']);
                            $version_map[$variation['id']] = $variation['version'];
                        }
                    }

                    $this->pushDelayedUpdateVersion($object['type'], $diff['last_id_map'], $object['version'], $object['id']);
                }
            }

            $this->runDelayedInsertObjectQuery();
            $this->runDelayedUpdateVersionQuery();

            if (isset($result['id_mappings']) && is_array($result['id_mappings'])) {
                $afterVersionUpdateCallbacks = array();
                $this->initialInventories = array();

                foreach ($result['id_mappings'] as $mapping) {
                    $parts = explode(":", substr($mapping['client_object_id'], 1));

                    $table = $parts[0];
                    $id_column = $parts[1];
                    $id = (int)$parts[2];

                    $square_id = $mapping['object_id'];
                    $version = $version_map[$square_id];

                    $this->pushDelayedUpdateVersionEntry($table, $id_column, $id, $version, $square_id);

                    if ($table == 'squareup_combination_item_variation' && $id_column == 'squareup_combination_id') {
                        $afterVersionUpdateCallbacks[] = function() use ($id) {
                            //$this->output("----- CALLBACK ONE: " . $id);
                            $this->prepareInitialInventories($id);
                        };
                    } else if ($table == 'squareup_product_item' && $id_column == 'product_id') {
                        $afterVersionUpdateCallbacks[] = function() use ($id) {
                            //$this->output("----- CALLBACK TWO: " . $id);
                            $this->uploadImages($id);
                        };
                    }
                }

                $this->runDelayedUpdateVersionQuery();

                foreach ($afterVersionUpdateCallbacks as $callback) {
                    $callback();
                }

                $this->timeoutable(function() {
                    $this->pushInitialInventories();
                });
            }

            $diff['status'] = self::STATUS_DONE;
        } catch (\Squareup\Exception\Api $e) {
            $diff['status'] = self::STATUS_ERROR;
            $diff['message'] = $diff['message'] . "ATTEMPT #" . $diff['attempt'] . PHP_EOL . "==========" . PHP_EOL . $e->getMessage() . PHP_EOL . PHP_EOL;

            sleep(1);
        } catch (\Exception $e) {
            $diff['status'] = self::STATUS_ERROR;
            $diff['attempt'] = $this->maxAttempts;
            $diff['message'] = $e->getMessage();
        }
    }

    protected function pushInitialInventories() {
        if (!empty($this->initialInventories)) {
            $this->output("Pushing initial inventories from OpenCart to Square...");

            $this->squareup_api->pushInventoryAdjustments($this->initialInventories);
        }
    }

    protected function uploadImages($product_id) {
        $sql = "SELECT p.image, spi.square_id, spii.url, sc.data FROM `" . DB_PREFIX . "squareup_product_item` spi LEFT JOIN `" . DB_PREFIX . "product` p ON (p.product_id = spi.product_id) LEFT JOIN `" . DB_PREFIX . "squareup_product_item_image` spii ON (spii.image = p.image) LEFT JOIN `" . DB_PREFIX . "squareup_catalog` sc ON (sc.square_id = spi.square_id) WHERE spi.product_id=" . (int)$product_id;

        $result = $this->db->query($sql);

        if ($result->num_rows) {
            $data = json_decode($result->row['data'], true);

            if (empty($data['image_url']) || $data['image_url'] != $result->row['url']) {
                $image_path = DIR_IMAGE . $result->row['image'];

                $resizer = new \Squareup\Image($this->registry, $image_path, $result->row['square_id']);

                if (null !== $resized_file = $resizer->resize()) {
                    $this->output("Uploading image: " . $image_path);

                    $image = $this->squareup_api->uploadImage($resized_file, $result->row['square_id']);

                    $diff_info = array(
                        'type' => 'create_opencart',
                        'last_id_map' => array(),
                        'data' => array(
                            array(
                                'table' => 'squareup_product_item_image',
                                'set' => array(
                                    'image' => $result->row['image'],
                                    '#url' => $image['url']
                                )
                            )
                        )
                    );

                    $squareup_diff_id = $this->addDiff($diff_info);

                    $this->executeDiff($squareup_diff_id);
                }

                unset($resizer);
            }
        }
    }

    protected function prepareInitialInventories($squareup_combination_id) {
        $sql = "SELECT scomb.var, scomb.quantity, sciv.square_id as variation_square_id, spi.square_id as item_square_id FROM `" . DB_PREFIX . "squareup_combination` scomb LEFT JOIN `" . DB_PREFIX . "squareup_combination_item_variation` sciv ON (scomb.squareup_combination_id = sciv.squareup_combination_id) LEFT JOIN `" . DB_PREFIX . "squareup_product_item` spi ON (spi.product_id = scomb.product_id) WHERE scomb.squareup_combination_id=" . (int)$squareup_combination_id . " AND scomb.subtract=1";

        $result = $this->db->query($sql);

        $initial_quantity = 0;

        if ($result->num_rows) {
            $var = explode(';', $result->row['var']);

            $initial_quantity = (int)$result->row['quantity'];

            if (count($var) > 1) {
                if ($this->complexInitialInventoriesCount < self::COMPLEX_INVENTORY_EMAIL_THRESHOLD) {
                    $this->complexInitialInventoriesLinks[] = array(
                        'item_square_id' => $result->row['item_square_id'],
                        'variation_square_id' => $result->row['variation_square_id']
                    );
                }

                $this->complexInitialInventoriesCount++;
            }

            $this->initialInventories[] = array(
                'catalog_object_id' => $result->row['variation_square_id'],
                'quantity' => $initial_quantity,
                'from_state' => 'NONE',
                'to_state' => 'IN_STOCK'
            );

            if (count($this->initialInventories) == 100) {
                $this->timeoutable(function() {
                    $this->pushInitialInventories();
                });
                $this->initialInventories = array();
            }
        }
    }

    protected function disassociateSquare(&$diff) {
        try {
            $result = $this->squareup_api->batchUpsertCatalog($diff['data']);

            $this->delayedInsertObject = array();
            $this->delayedUpdateVersion = array();

            if (isset($result['objects']) && is_array($result['objects'])) {
                foreach ($result['objects'] as $object) {
                    $this->pushDelayedInsertObject($object);
                    if ($object['type'] == 'MODIFIER_LIST') {
                        foreach ($object['modifier_list_data']['modifiers'] as $modifier) {
                            $this->pushDelayedInsertObject($modifier);
                        }
                    }

                    if ($object['type'] == 'ITEM') {
                        foreach ($object['item_data']['variations'] as $variation) {
                            $this->pushDelayedInsertObject($variation);

                            $this->pushDelayedUpdateVersion($variation['type'], $diff['last_id_map'], $variation['version'], $variation['id']);
                        }
                    }

                    $this->pushDelayedUpdateVersion($object['type'], $diff['last_id_map'], $object['version'], $object['id']);
                }
            }

            $this->runDelayedInsertObjectQuery();
            $this->runDelayedUpdateVersionQuery();

            $diff['status'] = self::STATUS_DONE;
        } catch (\Squareup\Exception\Api $e) {
            $diff['status'] = self::STATUS_ERROR;
            $diff['message'] = $diff['message'] . "ATTEMPT #" . $diff['attempt'] . PHP_EOL . "==========" . PHP_EOL . $e->getMessage() . PHP_EOL . PHP_EOL;

            sleep(1);
        }
    }

    protected function deleteSquare(&$diff) {
        try {
            $this->squareup_api->batchDeleteCatalog($diff['data']);

            $diff['status'] = self::STATUS_DONE;
        } catch (\Squareup\Exception\Api $e) {
            $diff['status'] = self::STATUS_ERROR;
            $diff['message'] = $diff['message'] . "ATTEMPT #" . $diff['attempt'] . PHP_EOL . "==========" . PHP_EOL . $e->getMessage() . PHP_EOL . PHP_EOL;

            sleep(1);
        }
    }
}