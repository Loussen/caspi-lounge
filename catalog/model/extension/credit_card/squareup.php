<?php

class ModelExtensionCreditCardSquareup extends Model {
    public function addCustomer($data) {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "squareup_customer` SET customer_id='" . (int)$data['customer_id'] . "', sandbox='0', square_customer_id='" . $this->db->escape($data['square_customer_id']) . "'");
    }

    public function getCustomer($customer_id) {
        return $this->db->query("SELECT * FROM `" . DB_PREFIX . "squareup_customer` WHERE customer_id = '" . (int)$customer_id . "' AND sandbox='0'")->row;
    }

    public function updateDefaultCustomerToken($customer_id, $squareup_token_id) {
        $this->db->query("UPDATE `" . DB_PREFIX . "squareup_customer` SET squareup_token_id='" . (int)$squareup_token_id . "' WHERE customer_id = '" . (int)$customer_id . "' AND sandbox='0'");
    }

    public function getFirstTokenId($customer_id) {
        foreach ($this->getCards($customer_id) as $card) {
            return (int)$card['squareup_token_id'];
        }

        return 0;
    }

    public function getTokenIdByCustomerAndToken($customer_id, $token) {
        foreach ($this->getCards($customer_id) as $card) {
            if ($card['token'] == $token) {
                return (int)$card['squareup_token_id'];
            }
        }

        return 0;
    }

    public function addCard($customer_id, $data) {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "squareup_token` SET customer_id='" . (int)$customer_id . "', sandbox='0', token='" . $this->db->escape($data['id']) . "', brand='" . $this->db->escape($data['card_brand']) . "', ends_in='" . (int)$data['last_4'] . "', date_added=NOW()");
    }

    public function getCard($squareup_token_id) {
        return $this->db->query("SELECT * FROM `" . DB_PREFIX . "squareup_token` WHERE squareup_token_id='" . (int)$squareup_token_id . "'")->row;
    }
    
    public function getCards($customer_id) {
        return $this->db->query("SELECT * FROM `" . DB_PREFIX . "squareup_token` WHERE customer_id='" . (int)$customer_id . "' AND sandbox='0'")->rows;
    }

    public function cardExists($customer_id, $data) {
        return $this->db->query("SELECT * FROM `" . DB_PREFIX . "squareup_token` WHERE customer_id='" . (int)$customer_id . "' AND brand='" . $this->db->escape($data['card_brand']) . "' AND ends_in='" . (int)$data['last_4'] . "'")->num_rows > 0;
    }

    public function verifyCardCustomer($squareup_token_id, $customer_id) {
        return $this->db->query("SELECT * FROM `" . DB_PREFIX . "squareup_token` WHERE squareup_token_id='" . (int)$squareup_token_id . "' AND customer_id='" . (int)$customer_id . "'")->num_rows > 0;
    }

    public function deleteCard($squareup_token_id) {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "squareup_token` WHERE squareup_token_id='" . (int)$squareup_token_id . "'");
    }
}