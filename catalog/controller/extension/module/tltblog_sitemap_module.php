<?php
class ControllerExtensionModuleTltBlogSitemapModule extends Controller {
	public function index($setting) {
		$this->load->language('extension/module/tltblog_sitemap_module');
		$this->load->model('extension/tltblog/tltblog');
		$this->load->model('setting/setting');

		if ($this->config->get('tltblog_seo')) {
			require_once(DIR_APPLICATION . 'controller/extension/tltblog/tltblog_seo.php');
			$tltblog_seo = new ControllerExtensionTltBlogTltBlogSeo($this->registry);
			$this->url->addRewrite($tltblog_seo);
		}

		$data['heading_blogs'] = $this->language->get('heading_blogs');
		$data['heading_tags'] = $this->language->get('heading_tags');

		if ($this->config->has('tltblog_path')) {
			$path_array = $this->config->get('tltblog_path');
            $path = $path_array[$this->config->get('config_language_id')];
		} else {
			$path = 'blogs';
		}

		if ($this->config->has('tltblog_path_title')) {
			$path_title = $this->config->get('tltblog_path_title');
			if (isset($path_title[$this->config->get('config_language_id')]['path_title'])) {
                $data['path_title'] = $path_title[$this->config->get('config_language_id')]['path_title'];
            }
		} else {
			$data['path_title'] = $this->language->get('tlt_heading_title');
		}

		$data['show_path'] = $this->config->get('tltblog_show_path');

		$data['tltblogs'] = array();
		$data['tlttags'] = array();
		$data['type'] = $setting['type'];
		$tltblogs = array();
		$tlttags = array();

		if ($setting['type'] == 'blogs') {
			$tltblogs = $this->model_extension_tltblog_tltblog->getTltBlogsForSitemap();
		} elseif ($setting['type'] == 'tags') {
			$tlttags = $this->model_extension_tltblog_tltblog->getTltTagsForSitemap();
		} else {
			$tltblogs = $this->model_extension_tltblog_tltblog->getTltBlogsForSitemap();
			$tlttags = $this->model_extension_tltblog_tltblog->getTltTagsForSitemap();
		}

		foreach ($tltblogs as $tltblog) {
			$data['tltblogs'][] = array(
				'title'     => $tltblog['title'],
				'href'     => $this->url->link('extension/tltblog/tltblog', 'tltpath=' . $path . '&tltblog_id=' . $tltblog['tltblog_id'])
			);
		}

		foreach ($tlttags as $tlttag) {		
			$data['tlttags'][] = array(
				'title'     => $tlttag['title'],
				'href'     => $this->url->link('extension/tltblog/tlttag', 'tltpath=' . $path . '&tlttag_id=' . $tlttag['tlttag_id'])
			);
		}

		return $this->load->view('extension/module/tltblog_sitemap_module', $data);
	}
}