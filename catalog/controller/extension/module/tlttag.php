<?php
class ControllerExtensionModuleTltTag extends Controller {
	public function index() {
		$this->load->model('setting/setting');
		
		if (!$this->config->get('tltblog_status')) {
			return;
		}
		
		$this->load->language('extension/module/tlttag');

		$data['heading_title'] = $this->language->get('heading_title');

		$this->load->model('extension/tltblog/tltblog');

		if ($this->config->get('tltblog_seo')) {
			require_once(DIR_APPLICATION . 'controller/extension/tltblog/tltblog_seo.php');
			$tltblog_seo = new ControllerExtensionTltBlogTltBlogSeo($this->registry);
			$this->url->addRewrite($tltblog_seo);
		}

		$data['show_path'] = $this->config->get('tltblog_show_path');

        if ($this->config->has('tltblog_path')) {
            $path_array = $this->config->get('tltblog_path');
        }

        if (isset($this->request->get['tltpath'])) {
			$path = $this->request->get['tltpath'];
        } elseif (isset($path_array[$this->config->get('config_language_id')])) {
            $path = $path_array[$this->config->get('config_language_id')];
		} else {
			$path = 'blogs';
		}
		
		if (isset($this->request->get['tlttag_id'])) {
			$data['current_tag'] = $this->request->get['tlttag_id'];
		} else {
			$data['current_tag'] = '';
		}

		$data['tags'] = array();

		$tags = $this->model_extension_tltblog_tltblog->getTltTags();

		if (!$data['current_tag'] && isset($this->request->get['tltblog_id'])) {
			$tagsforblog = $this->model_extension_tltblog_tltblog->getTltTagsForBlog($this->request->get['tltblog_id']);
			if ($tagsforblog) {
				$data['current_tag'] = $tagsforblog[0]['tlttag_id'];
			}
		}
		
		foreach ($tags as $tag) {
            $data['tags'][] = array(
                'tlttag_id'  => $tag['tlttag_id'],
                'title' => $tag['title'],
                'href' => $this->url->link('extension/tltblog/tlttag', 'tltpath=' . $path . '&tlttag_id=' . $tag['tlttag_id'])
            );
		}
		
		return $this->load->view('extension/module/tlttag', $data);
	}
}