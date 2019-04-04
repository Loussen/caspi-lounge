<?php
class ModelDesignSeoUrl extends Model {


    public function getHomeUrl($store_id=0, $language_id=1) {
        $query = $this->db->query("
                                                  SELECT * FROM `" . DB_PREFIX . "seo_url`
                                                  WHERE store_id = '" . (int)$store_id . "'
                                                  AND language_id = '" . (int)$language_id . "'
                                                  AND query = 'common/home'
                                                  LIMIT 0, 1
                    ");
        return $query->row;
    }


}