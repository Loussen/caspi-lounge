<?php
class ControllerStartupSeoUrl extends Controller {
    public function index() {
        // Add rewrite to url class
        if ($this->config->get('config_seo_url')) {
            $this->url->addRewrite($this);
        }

        // Decode URL
        if (isset($this->request->get['_route_'])) {
            $parts = explode('/', $this->request->get['_route_']);

            // remove any empty arrays from trailing
            if (utf8_strlen(end($parts)) == 0) {
                array_pop($parts);
            }

            foreach ($parts as $part) {
                $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE keyword = '" . $this->db->escape($part) . "' AND store_id = '" . (int)$this->config->get('config_store_id') . "'");

                if ($query->num_rows) {
                    $url = explode('=', $query->row['query']);

                    if ($url[0] == 'product_id') {
                        $this->request->get['product_id'] = $url[1];
                        $this->request->get['route'] = 'product/product';
                    }

                    if ($url[0] == 'category_id') {
                        if (!isset($this->request->get['path'])) {
                            $this->request->get['path'] = $url[1];
                        } else {
                            $this->request->get['path'] .= '_' . $url[1];
                        }
                        $this->request->get['route'] = 'product/category';
                    }

                    if ($url[0] == 'manufacturer_id') {
                        $this->request->get['manufacturer_id'] = $url[1];
                        $this->request->get['route'] = 'product/manufacturer/info';
                    }

                    if ($url[0] == 'information_id') {
                        $this->request->get['information_id'] = $url[1];
                        $this->request->get['route'] = 'information/information';
                    }

                    if ($url[0] == 'blog_id') {
                        $this->request->get['blog_id'] = $url[1];
                        $this->request->get['route'] = 'information/blog/item';
                    }

                    if ($query->row['query'] && $url[0] != 'information_id' && $url[0] != 'blog_id' && $url[0] != 'manufacturer_id' && $url[0] != 'category_id' && $url[0] != 'product_id') {
                        $this->request->get['route'] = $query->row['query'];
                    }

                } else {
                    $this->request->get['route'] = 'error/not_found';

                    // break;
                }


                // echo "<pre>";
                // print_r( $part ." | ". $this->request->get['route'] );
                // echo "</pre>";

            } // foreach parts

            if (!isset($this->request->get['route'])) {
                if (isset($this->request->get['product_id'])) {
                    $this->request->get['route'] = 'product/product';
                } elseif (isset($this->request->get['path'])) {
                    $this->request->get['route'] = 'product/category';
                } elseif (isset($this->request->get['manufacturer_id'])) {
                    $this->request->get['route'] = 'product/manufacturer/info';
                } elseif (isset($this->request->get['information_id'])) {
                    $this->request->get['route'] = 'information/information';
                }
            }
        }
    }

    public function rewrite($link) {
        $url_info = parse_url(str_replace('&amp;', '&', $link));

        $url = '';

        $data = array();






        parse_str($url_info['query'], $data);



        foreach ($data as $key => $value) {

            $blog_true = false;

            if (isset($data['route'])) {
                if (($data['route'] == 'product/product' && $key == 'product_id') || (($data['route'] == 'product/manufacturer/info' || $data['route'] == 'product/product') && $key == 'manufacturer_id') || ($data['route'] == 'information/information' && $key == 'information_id') || ($data['route'] == 'information/blog/item' && $key == 'blog_id')) {

                    if ( $data['route'] == 'product/product' )
                    {

                        $language_result = $this->model_localisation_language->getLanguagePerCode( $this->config->get('config_language') );
//                        echo "<pre>";
//                        print_r( $language_result );
//                        echo "</pre>";
                    }


                    if ( $data['route'] == 'information/blog/item' && $key == 'blog_id' )
                    {
                        $blog_true = true;
                    }

                    $query = $this->db->query("
                                                SELECT * FROM " . DB_PREFIX . "seo_url
                                                WHERE `query` = '" . $this->db->escape($key . '=' . (int)$value) . "'
                                                AND store_id = '" . (int)$this->config->get('config_store_id') . "'
                                                AND language_id = '" . (int)(isset($language_result) ? $language_result['language_id'] : $this->config->get('config_language_id')) . "'
                    ");




                    if ($query->num_rows && $query->row['keyword']) {
                        $url .= '/' . $query->row['keyword'];

                        unset($data[$key]);
                    }

                } elseif ($key == 'route') {
                    $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE `query` = '" . $this->db->escape($value) . "' AND store_id = '" . (int)$this->config->get('config_store_id') . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "'");
                    if ($query->num_rows && $query->row['keyword']) {
                        $url .= '/' . $query->row['keyword'];
                        unset($data[$key]);
                    } else if ($data['route'] == "common/home") {
                        $url .= '/';
                    }

                } elseif ($key == 'path') {
                    $categories = explode('_', $value);

//                    echo "<pre>";
//                    print_r( $categories );
//                    echo "</pre>";

                    $category_say = 0;
                    foreach ($categories as $category) { $category_say++;

                        if ($category_say>1)
                        {
                            $language_result = $this->model_localisation_language->getLanguagePerCode( $this->config->get('config_language') );
//                            echo "<pre>";
//                            print_r( $language_result );
//                            echo "</pre>";
                        }

                        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE `query` = 'category_id=" . (int)$category . "' AND store_id = '" . (int)$this->config->get('config_store_id') . "' AND language_id = '" . (int)(isset($language_result) ? $language_result['language_id'] : $this->config->get('config_language_id')) . "'");

                        if ($query->num_rows && $query->row['keyword']) {
                            $url .= '/' . $query->row['keyword'];
                        } else {
                            $url = '';

                            break;
                        }
                    }

                    unset($data[$key]);
                }
            }
        }


        if ( $blog_true==true )
        {
            $url_arr = explode('/', $url);

            // remove any empty arrays from trailing
            if (utf8_strlen(end($url_arr)) == 0) {
                array_pop($url_arr);
            }

            $url_count = count($url_arr);

            if ($url_count>2)
            {
                $url = "/". $url_arr[1] ."/blogs/". $url_arr[2];
            }
            else
            {
                $url = "/blogs". $url;
            }

        } // if blog_true==true

        // echo "<pre>";
        // print_r( $url );
        // echo "</pre>";



        if ($url) {
            unset($data['route']);

            $query = '';

            if ($data) {
                foreach ($data as $key => $value) {
                    $query .= '&' . rawurlencode((string)$key) . '=' . rawurlencode((is_array($value) ? http_build_query($value) : (string)$value));
                }

                if ($query) {
                    $query = '?' . str_replace('&', '&amp;', trim($query, '&'));
                }
            }

            return $url_info['scheme'] . '://' . $url_info['host'] . (isset($url_info['port']) ? ':' . $url_info['port'] : '') . str_replace('/index.php', '', $url_info['path']) . $url . $query;
        } else {
            return $link;
        }
    }
}
