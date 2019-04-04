<?php
class ControllerExtensionFeedTltBlogSitemap extends Controller {
	public function index() {
		if ($this->config->get('feed_tltblog_sitemap_status')) {
			if ($this->config->has('tltblog_path')) {
                $path_array = $this->config->get('tltblog_path');
                if (isset($path_array[$this->config->get('config_language_id')])) {
                    $path = $path_array[$this->config->get('config_language_id')];
                }
			} else {
				$path = 'blogs';
			}
			
			if ($this->config->get('tltblog_seo')) {
				require_once(DIR_APPLICATION . 'controller/extension/tltblog/tltblog_seo.php');
				$tltblog_seo = new ControllerExtensionTltBlogTltBlogSeo($this->registry);
				$this->url->addRewrite($tltblog_seo);
			}

			$output  = '<?xml version="1.0" encoding="UTF-8"?>';
			$output .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

			$this->load->model('extension/tltblog/tltblog');

			$tltblogs = $this->model_extension_tltblog_tltblog->getTltBlogsForSitemap();

			foreach ($tltblogs as $tltblog) {
				$output .= '<url>';
				$output .= '  <loc>' . $this->url->link('extension/tltblog/tltblog', 'tltpath=' . $path . '&tltblog_id=' . $tltblog['tltblog_id']) . '</loc>';
				$output .= '  <changefreq>weekly</changefreq>';
                $output .= '  <priority>1.0</priority>';
				$output .= '</url>';
			}

			$tlttags = $this->model_extension_tltblog_tltblog->getTltTagsForSitemap();

			foreach ($tlttags as $tlttag) {
				$output .= '<url>';
				$output .= '<loc>' . $this->url->link('extension/tltblog/tlttag', 'tltpath=' . $path . '&tlttag_id=' . $tlttag['tlttag_id']) . '</loc>';
				$output .= '<changefreq>weekly</changefreq>';
				$output .= '<priority>1.0</priority>';
				$output .= '</url>';
			}

			$output .= '</urlset>';

			$this->response->addHeader('Content-Type: application/xml');
			$this->response->setOutput($output);
		}
	}
}
