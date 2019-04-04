<?php

namespace Squareup\Diff;

use Squareup\Library;

class OpenCartTax extends Library {
    public function work($product_ids = array()) {
        if (!$this->config->get('payment_squareup_ad_hoc_sync')) {
            // Determine if any tax rates are different from those in Square and mark this in the relation table by setting version = 0
            $this->updateVersionsInOpenCart($product_ids);

            // Delete redundant references between tax_rates and Square TAXes
            $this->deleteTaxRateTax();

            // Detect any version differences (depends on the step above) and prepare upsertion batches
            $this->insertUpdate();
        } else {
            $this->db->query("TRUNCATE `" . DB_PREFIX . "squareup_tax_rate_tax`");
        }

        // Delete redundant Square TAXes
        $this->deleteTax();
    }

    public function updateVersionsInOpenCartHandler(&$update_ids) {
        $diff_info = array(
            'type' => 'update_opencart',
            'last_id_map' => array(),
            'data' => array(
                array(
                    'table' => 'squareup_tax_rate_tax',
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
        $this->squareup_diff->output("OC TAX: Find taxes edited from within Square...");

        $update_ids = array();

        if (empty($product_ids)) {
            $sql = "SELECT * FROM `" . DB_PREFIX . "squareup_catalog` WHERE `type`='TAX'";
        } else {
            $sql = "SELECT DISTINCT sc.* FROM `" . DB_PREFIX . "tax_rule` tr LEFT JOIN `" . DB_PREFIX . "squareup_tax_rate_tax` strt ON (strt.tax_rate_id = tr.tax_rate_id) LEFT JOIN `" . DB_PREFIX . "product` p ON (p.tax_class_id = tr.tax_class_id) LEFT JOIN `" . DB_PREFIX . "squareup_catalog` sc ON (sc.square_id = strt.square_id) WHERE p.product_id IN (" . implode(",", $product_ids) . ") AND sc.`type`='TAX'";
        }

        $this->squareup_diff->chunkedMap($sql, function(&$tax) use (&$update_ids) {
            if (false !== $square_id = $this->hasToBeUpdated($tax)) {
                $update_ids[] = $square_id;

                $this->squareup_diff->chunkHandler($update_ids, array($this, 'updateVersionsInOpenCartHandler'));
            }
        });

        $this->squareup_diff->chunkHandler($update_ids, array($this, 'updateVersionsInOpenCartHandler'), true);
    }

    public function hasToBeUpdated(&$tax) {
        $data = json_decode($tax['data'], true);

        $sql = "SELECT strt.square_id, tr.name, tr.rate FROM `" . DB_PREFIX . "squareup_tax_rate_tax` strt LEFT JOIN `" . DB_PREFIX . "tax_rate` tr ON (strt.tax_rate_id = tr.tax_rate_id) WHERE strt.square_id='" . $this->db->escape($tax['square_id']) . "' AND tr.type='P'";

        $result = $this->db->query($sql);

        if ($result->num_rows) {
            foreach ($result->rows as $row) {
                if (
                    $data['calculation_phase'] != 'TAX_SUBTOTAL_PHASE' ||
                    $data['inclusion_type'] != 'ADDITIVE' ||
                    !$data['enabled'] ||
                    $row['name'] != $data['name'] ||
                    (float)$row['rate'] != (float)$data['percentage']
                ) {
                    return $result->row['square_id'];
                }
            }
        }

        return false;
    }

    public function deleteTaxRateTaxHandler(&$delete_ids) {
        $diff_info = array(
            'type' => 'delete_opencart',
            'last_id_map' => array(),
            'data' => array(
                array(
                    'table' => 'squareup_tax_rate_tax',
                    'where' => array(
                        'squareup_tax_rate_tax_id' => $delete_ids
                    )
                )
            )
        );

        $this->squareup_diff->addDiff($diff_info);

        $this->squareup_diff->executeDiff();
    }

    public function deleteTaxRateTax() {
        $this->squareup_diff->output("OC TAX: Delete references between tax rates and taxes...");

        $delete_ids = array();
        // Remove the ghost entries from squareup_tax_rate_tax
        $sql = "SELECT strt.squareup_tax_rate_tax_id FROM `" . DB_PREFIX . "squareup_tax_rate_tax` strt LEFT JOIN `" . DB_PREFIX . "squareup_catalog` sc ON (sc.square_id = strt.square_id AND sc.type='TAX') LEFT JOIN `" . DB_PREFIX . "tax_rate` tr ON (tr.tax_rate_id=strt.tax_rate_id) WHERE sc.square_id IS NULL OR tr.tax_rate_id IS NULL OR tr.type != 'P'";

        $this->squareup_diff->chunkedMap($sql, function(&$row) use (&$delete_ids) {
            $delete_ids[] = $row['squareup_tax_rate_tax_id'];

            $this->squareup_diff->chunkHandler($delete_ids, array($this, 'deleteTaxRateTaxHandler'));
        }, function() use (&$delete_ids) {
            $this->squareup_diff->chunkHandler($delete_ids, array($this, 'deleteTaxRateTaxHandler'), true);
        }, false);
    }

    public function insertUpdate() {
        $this->squareup_diff->output("OC TAX: Upload taxes...");

        $sql = "SELECT tr.tax_rate_id, tr.rate, tr.name, strt.square_id, sc.version, sc.present_at_location_ids, sc.absent_at_location_ids, sc.present_at_all_locations FROM `" . DB_PREFIX . "tax_rate` tr LEFT JOIN `" . DB_PREFIX . "squareup_tax_rate_tax` strt ON (strt.tax_rate_id = tr.tax_rate_id) LEFT JOIN `" . DB_PREFIX . "squareup_catalog` sc ON (sc.square_id = strt.square_id) WHERE tr.type = 'P' AND (strt.square_id IS NULL OR strt.version IS NULL OR strt.version != sc.version)";

        $batch = array();

        $this->squareup_diff->chunkedMap($sql, function(&$tax_rate) use (&$batch) {
            $is_new = is_null($tax_rate['square_id']);

            $upsert_id = $is_new ? '#squareup_tax_rate_tax:tax_rate_id:' . $tax_rate['tax_rate_id'] : $tax_rate['square_id'];

            $present_at_location_ids = is_null($tax_rate['present_at_location_ids']) ? array() : json_decode($tax_rate['present_at_location_ids'], true);

            $tax = array(
                'type' => 'TAX',
                'id' => $upsert_id,
                'absent_at_location_ids' => is_null($tax_rate['absent_at_location_ids']) ? array() : json_decode($tax_rate['absent_at_location_ids'], true),
                'present_at_all_locations' => !empty($tax_rate['present_at_all_locations']),
                'present_at_location_ids' => $present_at_location_ids,
                'tax_data' => array(
                    'name' => $tax_rate['name'],
                    'calculation_phase' => 'TAX_SUBTOTAL_PHASE',
                    'inclusion_type' => 'ADDITIVE',
                    'percentage' => (string)$tax_rate['rate'],
                    'applies_to_custom_amounts' => false,
                    'enabled' => true
                )
            );

            if (!is_null($tax_rate['version'])) {
                $tax['version'] = (int)$tax_rate['version'];
            }

            $batch[] = $tax;
        }, function() use (&$batch) {
            if (empty($batch)) {
                return;
            }

            $diff_info = array(
                'type' => 'upsert_square',
                'last_id_map' => array(
                    'TAX' => 'squareup_tax_rate_tax'
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

    public function disassociateTaxHandler(&$disassociate_ids) {
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

    public function deleteTaxHandler(&$delete_ids) {
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

    public function deleteTax() {
        $this->squareup_diff->output("OC TAX: Delete taxes...");

        $delete_ids = array();
        $sql = "SELECT sc.* FROM `" . DB_PREFIX . "squareup_catalog` sc LEFT JOIN `" . DB_PREFIX . "squareup_tax_rate_tax` strt ON (strt.square_id = sc.square_id) WHERE strt.square_id IS NULL AND sc.type='TAX'";
        
        $this->squareup_diff->chunkedMap($sql, function(&$row) use (&$delete_ids, &$disassociate_ids) {
            $last_id_map = array(
                'TAX' => 'squareup_tax_rate_tax'
            );

            $this->squareup_diff->determineDeleteOrDisassociate($row, $delete_ids, $disassociate_ids, $last_id_map);

            $this->squareup_diff->chunkHandler($delete_ids, array($this, 'deleteTaxHandler'));
            $this->squareup_diff->chunkHandler($disassociate_ids, array($this, 'disassociateTaxHandler'));
        }, function() use (&$delete_ids, &$disassociate_ids) {
            $this->squareup_diff->chunkHandler($delete_ids, array($this, 'deleteTaxHandler'), true);
            $this->squareup_diff->chunkHandler($disassociate_ids, array($this, 'disassociateTaxHandler'), true);

            $this->squareup_diff->prepareDisassociateBatches(true);
        }, false);
    }
}