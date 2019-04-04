<?php
class ModelExtensionTltBlogSettings extends Model {
    private $tables = array(
        'tltblog',
        'tltblog_description',
        'tltblog_related',
        'tltblog_to_layout',
        'tltblog_to_store',
        'tltblog_to_tag',
        'tlttag',
        'tlttag_description',
        'tlttag_to_store',
        'tltblog_url_alias'
    );
    
	public function install()
    {
        $this->db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "tltblog");

		$this->db->query("CREATE TABLE " . DB_PREFIX . "tltblog (
            tltblog_id int(11) NOT NULL AUTO_INCREMENT,
            image varchar(255) DEFAULT NULL,
            bottom tinyint(1) NOT NULL DEFAULT '0',
            sort_order int(11) NOT NULL DEFAULT '0',
            status tinyint(1) NOT NULL DEFAULT '0',
            show_description tinyint(1) NOT NULL DEFAULT '0',
            show_title tinyint(1) NOT NULL DEFAULT '0',
            PRIMARY KEY (tltblog_id)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci"
        );

        $this->db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "tltblog_description");

        $this->db->query("CREATE TABLE " . DB_PREFIX . "tltblog_description (
            tltblog_id int(11) NOT NULL,
            language_id int(11) NOT NULL,
            title varchar(255) NOT NULL DEFAULT '',
            intro text NOT NULL,
            description text NOT NULL,
            meta_title varchar(255) NOT NULL,
            meta_description varchar(255) NOT NULL,
            meta_keyword varchar(255) NOT NULL,
            PRIMARY KEY (tltblog_id,language_id)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci"
        );

        $this->db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "tltblog_related");

        $this->db->query("CREATE TABLE " . DB_PREFIX . "tltblog_related (
            tltblog_id int(11) NOT NULL,
            related_id int(11) NOT NULL,
            PRIMARY KEY (tltblog_id,related_id)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci"
        );

        $this->db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "tltblog_to_layout");

        $this->db->query("CREATE TABLE " . DB_PREFIX . "tltblog_to_layout (
            tltblog_id int(11) NOT NULL,
            store_id int(11) NOT NULL,
            layout_id int(11) NOT NULL,
            PRIMARY KEY (tltblog_id,store_id)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci"
        );

        $this->db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "tltblog_to_store");

        $this->db->query("CREATE TABLE " . DB_PREFIX . "tltblog_to_store (
            tltblog_id int(11) NOT NULL,
            store_id int(11) NOT NULL DEFAULT '0',
            PRIMARY KEY (tltblog_id,store_id)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci"
        );

        $this->db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "tltblog_to_tag");

        $this->db->query("CREATE TABLE " . DB_PREFIX . "tltblog_to_tag (
            tltblog_id int(11) NOT NULL,
            tlttag_id int(11) NOT NULL,
            PRIMARY KEY (tltblog_id,tlttag_id)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci"
        );

        $this->db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "tlttag");

        $this->db->query("CREATE TABLE " . DB_PREFIX . "tlttag (
            tlttag_id int(11) NOT NULL AUTO_INCREMENT,
            sort_order int(11) NOT NULL DEFAULT '0',
            status tinyint(1) NOT NULL DEFAULT '0',
            PRIMARY KEY (tlttag_id)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci"
        );

        $this->db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "tlttag_description");

        $this->db->query("CREATE TABLE " . DB_PREFIX . "tlttag_description (
            tlttag_id int(11) NOT NULL,
            language_id int(11) NOT NULL,
            title varchar(255) NOT NULL DEFAULT '',
            meta_title varchar(255) NOT NULL,
            meta_description varchar(255) NOT NULL,
            meta_keyword varchar(255) NOT NULL,
            PRIMARY KEY (tlttag_id,language_id)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci"
        );

        $this->db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "tlttag_to_store");

        $this->db->query("CREATE TABLE " . DB_PREFIX . "tlttag_to_store (
            tlttag_id int(11) NOT NULL,
            store_id int(11) NOT NULL DEFAULT '0',
            PRIMARY KEY (tlttag_id,store_id)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci"
        );

        $this->db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "tltblog_url_alias");

        $this->db->query("CREATE TABLE " . DB_PREFIX . "tltblog_url_alias (
            url_alias_id int(11) NOT NULL AUTO_INCREMENT,
            language_id int(11) NOT NULL,
            query varchar(255) NOT NULL,
            keyword varchar(255) NOT NULL,
            PRIMARY KEY (url_alias_id, language_id),
            KEY query (query),
            KEY keyword (keyword)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci"
        );
    }

    public function upgrade()
    {
        foreach ($this->tables as $table) {
            $this->db->query("CREATE TABLE " . DB_PREFIX . $table . "_copy LIKE " . $table);
            $this->db->query("INSERT INTO " . DB_PREFIX . $table . "_copy SELECT * FROM " . $table);
        }
        
        $this->install();
        
        foreach ($this->tables as $table) {
            $this->db->query("INSERT INTO " . DB_PREFIX . $table . " SELECT * FROM " . DB_PREFIX . $table . "_copy");
            $this->db->query("DROP TABLE " . DB_PREFIX . $table . "_copy");
        }
    }

    public function uninstall()
    {
        foreach ($this->tables as $table) {
            $this->db->query("DROP TABLE IF EXISTS " . DB_PREFIX . $table);
        }
    }

    public function getSettings($code = 'tltblog', $store_id = 0)
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE code = '" . $code . "' AND store_id = '" . (int) $store_id . "'");

        $settings = array();

        if ($query->num_rows) {
            foreach ($query->rows as $row) {
                $settings[$row['key']] = $row['serialized'] ? json_decode($row['value'], true) : $row['value'];
            }
        }

        return $settings;
    }

    public function checkTables()
    {
        $query = $this->db->query("SHOW TABLES FROM ". DB_DATABASE . " LIKE 'tltblog'");

        if ($query->num_rows) {
            return true;
        } else {
            return false;
        }
    }
}