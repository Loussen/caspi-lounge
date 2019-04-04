<?php
class ControllerCommonLanguage extends Controller {
    public function index() {
        $this->load->language('common/language');

        $data['action'] = $this->url->link('common/language/language', '', $this->request->server['HTTPS']);

        $data['code'] = $this->session->data['language'];

        $this->load->model('localisation/language');

        $data['languages'] = array();

        $results = $this->model_localisation_language->getLanguages();

        foreach ($results as $result) {
            if ($result['status']) {
                $data['languages'][] = array(
                    'name' => $result['name'],
                    'code' => $result['code']
                );
            }
        }

        if (!isset($this->request->get['route'])) {
            $data['redirect'] = $this->url->link('common/home');
        } else {
            $url_data = $this->request->get;

            unset($url_data['_route_']);

            $route = $url_data['route'];

            unset($url_data['route']);

            $url = '';

            if ($url_data) {
                $url = '&' . urldecode(http_build_query($url_data, '', '&'));
            }

            $data['redirect'] = $this->url->link($route, $url, $this->request->server['HTTPS']);
        }

        return $this->load->view('common/language', $data);
    }

    public function language() {
        if (isset($this->request->post['code'])) {
            $this->session->data['language'] = $this->request->post['code'];
        }





        if (isset($this->request->post['redirect'])) {

            $languages = $this->model_localisation_language->getLanguages();


            $this->request->post['redirect'] = str_replace(($this->request->server['HTTPS'] ? HTTPS_SERVER : HTTP_SERVER), "/", $this->request->post['redirect']);

            foreach ($languages as $language)
            {

                $language_code = explode("-", $language['code']);

                $this->request->post['redirect'] = str_replace("/". $language_code[0], "", $this->request->post['redirect']);
            } // foreach languages


            foreach ($languages as $language)
            {
                if ($language['code']==$this->session->data['language'])
                {

                    $language_code = explode("-", $language['code']);

                    if ($this->config->get('config_language')==$this->session->data['language'])
                    {
                        $replace_lang = "/";
                    }
                    else
                    {
                        $replace_lang = "/". $language_code[0];
                    }

                    break;
                }
            }


            if ( isset($replace_lang) )
            {
                if (strpos($this->request->post['redirect'], "/index.php") !== false) {
                    $this->request->post['redirect'] = $this->request->post['redirect'];
                }
                else
                {
                    $this->request->post['redirect'] = $replace_lang . $this->request->post['redirect'];
                }
            }

            $this->request->post['redirect'] = str_replace("//", "/", $this->request->post['redirect']);

//             echo "<pre>";
//             print_r( $this->request->post['redirect'] );
//             echo "</pre>";
//
//             exit;


            $this->response->redirect($this->request->post['redirect']);
        } else {
            $this->response->redirect($this->url->link('common/home'));
        }
    }
}