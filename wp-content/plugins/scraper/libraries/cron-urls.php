<?php

if (!class_exists('SCRAPER_CronUrls')) {

	class SCRAPER_CronUrls extends SCRAPER_Plugin {

		private $options;

		/**
		 * Construct the plugin object
		 */
		public function __construct() {
			$this->options = get_option('SCRAPER');
			add_action('init', array($this, 'init'));
			add_filter('query_vars', array($this, 'query_vars'), 0, 1);
			add_action('template_redirect', array($this, 'template_redirect'));
		}

		public function init() {
			global $wp_rewrite;
			add_rewrite_rule('^scraper/([^/]*)/?', 'index.php?scraper_task_id=$matches[1]', 'top');
		}

		public function query_vars($query_vars) {
			$query_vars[] = 'scraper_task_id';
			return $query_vars;
		}

		function template_redirect() {
			$scraper_task_id = get_query_var('scraper_task_id');
			if ($scraper_task_id) {
				$output = array('success' => false, 'results' => 'Invalid Task ID.');
				$task = $this->get_task($scraper_task_id);
				if (!empty($task)) {
					$results = $this->process_task($task, true);
					$output = array('success' => true, 'results' => $results);
				}
				echo json_encode($output);
				exit;
			}
			return;
		}

	}

}
$SCRAPER_CronUrls = new SCRAPER_CronUrls();
