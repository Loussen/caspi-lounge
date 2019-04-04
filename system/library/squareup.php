<?php

class Squareup extends \Squareup\Library {
    const PAYMENT_FORM_URL = 'https://js.squareup.com/v2/paymentform';
    const SQUARE_INTEGRATION_ID = 'sqi_65a5ac54459940e3600a8561829fd970';
    const VIEW_TRANSACTION_URL = 'https://squareup.com/dashboard/sales/transactions/%s/by-unit/%s';
    const MYSQL_GROUP_CONCAT_MAX_LEN = 1000000;

    public function __construct($registry) {
        $registry->get('db')->query("SET SESSION group_concat_max_len = " . self::MYSQL_GROUP_CONCAT_MAX_LEN);

        $registry->set('squareup_diff', new \Squareup\Diff($registry));
        // $registry->set('squareup_diff_square_option', new \Squareup\Diff\SquareOption($registry));
        // $registry->set('squareup_diff_square_category', new \Squareup\Diff\SquareCategory($registry));
        // $registry->set('squareup_diff_square_tax', new \Squareup\Diff\SquareTax($registry));
        // $registry->set('squareup_diff_square_combination', new \Squareup\Diff\SquareCombination($registry));
        // $registry->set('squareup_diff_square_product', new \Squareup\Diff\SquareProduct($registry));
        $registry->set('squareup_diff_square_inventory', new \Squareup\Diff\SquareInventory($registry));
        $registry->set('squareup_diff_opencart_option', new \Squareup\Diff\OpenCartOption($registry));
        $registry->set('squareup_diff_opencart_category', new \Squareup\Diff\OpenCartCategory($registry));
        $registry->set('squareup_diff_opencart_tax', new \Squareup\Diff\OpenCartTax($registry));
        $registry->set('squareup_diff_opencart_combination', new \Squareup\Diff\OpenCartCombination($registry));
        $registry->set('squareup_diff_opencart_product', new \Squareup\Diff\OpenCartProduct($registry));
        $registry->set('squareup_api', new \Squareup\Api($registry));

        parent::__construct($registry);
    }
}