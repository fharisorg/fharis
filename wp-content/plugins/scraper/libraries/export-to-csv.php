<?php

if (!class_exists('SCRAPER_ExportToCSV')) {

	class SCRAPER_ExportToCSV extends SCRAPER_Plugin {

		private $options;

		/**
		 * Construct the plugin object
		 */
		public function __construct() {
			$this->options = get_option('SCRAPER');

			//add_action('init', array($this, 'doexport'));
			add_action('wp_ajax_scraper_export_service', array($this, 'doexport'));
		}

		function doexport() {
			if (isset($_POST['hash']) && !empty($_POST['hash'])) {
				$task_hash = $_POST['hash'];
				$filename = "export-{$task_hash}.csv";
				$data = $this->scrape_task($task_hash);
				$this->export_to_csv($filename, $data);
				exit;
			}
			if (isset($_GET['export'])) {
				$data = $this->scrape_task($_GET['export']);
				echo "<pre>";
				print_r($data);
				exit;
			}
		}

		public function scrape_task($hash = '') {
			$enable_errors = $this->with_default('enable_errors', false, $this->options);
			if (isset($enable_errors) && $enable_errors == 'true') {
				error_reporting(E_ERROR);
				ini_set('display_errors', 1);
			}
			$disable_memory_limit = $this->with_default('disable_memory_limit', false, $this->options);
			if (isset($disable_memory_limit) && $disable_memory_limit == 'true') {
				ini_set("memory_limit", -1);
			}
			set_time_limit(1800);
			$output = array();
			$task = $this->get_task($hash);
			if (!empty($task)) {
				$data = json_decode($task['data'], true);
				if ((int) $data['connection']['total_run'] > 0 && (int) $data['connection']['total_run'] <= (int) $task['count_run']) {
					return false;
				}
				$source_connection = false;
				$increase_task_limit = false;
				$collected_urls = array();
				$next_page_path_defined = false;
				$page_increased = false;
				if ($data['singlePost'] == 'true') {
					if (isset($data['other']) && @$data['other'] && @$data['other']['bulkURL'] && strlen($data['other']['bulkURL']) > 5) {
						$url_list = array(@$data['contentURL']);
						$bulk_urls = explode("\n", $data['other']['bulkURL']);
						foreach ($bulk_urls as $key => $bulk_url) {
							$url_list[] = $bulk_url;
						}
						if (isset($task['last_index']) && @$task['last_index']) {
							$last_index = (int) $task['last_index'];
						} else {
							$last_index = 0;
						}
						$feed_items = array();
						$count_index = $task['task_limit'] ? (int) $task['task_limit'] : 100;
						for ($i = $last_index; $i < ($last_index + $count_index); $i++) {
							if (isset($url_list) && $this->is_url($url_list[$i])) {
								$feed_items[] = @$url_list[$i];
							}
						}
						foreach ($feed_items as $key => $post_url) {
							$output[] = $this->get_task_content($post_url, $data, $task);
						}
					} else {
						$output[] = $this->get_task_content($data['contentURL'], $data, $task);
					}
				} else {
					if ($task['current_page_url']) {
						$feedURL = $task['current_page_url'];
						$baseURL = false;
					} else {
						$feedURL = $data['feedURL'];
						$baseURL = false;
					}
					if ($feedURL) {
						$feed_html = $this->get_url($feedURL, $data, $baseURL);
						$feed_items = $this->parse_xpath($feed_html, $data['feed']['path'], 'deep_link');
						if ($feed_html) {
							$source_connection = true;
						}
						$last_index = 0;
						$last_index = (int) $task['last_index'];
						$collected_urls = $feed_items;
						foreach ($feed_items as $key => $post_url) {
							if ($last_index <= $key) {
								if (isset($task['task_limit']) && (int) @$task['task_limit'] > 0 && @$task['task_limit'] + $last_index <= $key) {
									//Skip Process
								} else {
									$post_url = $this->clean_url($post_url, $baseURL);
									$output[] = $this->get_task_content($post_url, $data, $task);
								}
							}
						}
					}
				}

				$nextPageFound = 0;
				if (@$data['nextPage']['path']) {
					$nextPageFound = 1;
					$next_page_path_defined = true;
					$feed_html = $this->get_url($feedURL, $data);
					if ($feed_html) {
						$source_connection = true;
					}
					//find next link
					$field_content = $this->parse_xpath($feed_html, $data['nextPage']['path'], 'deep_link');
					$nextPageURL = @$field_content[0];
					if ($nextPageURL) {
						$nextPageFound = 2;
					}
					$feedURL = $this->clean_url($feedURL, $baseURL);
					$nextPageURL = $this->clean_url($nextPageURL, $feedURL);
					if (count($collected_urls) <= (int) $task['last_index'] && $nextPageURL) {
						$this->increase_page($task, $nextPageURL);
						$next_output = $this->scrape_task($task);
						if (!empty($next_output) && is_array($next_output)) {
							$output = $output + $next_output;
						}
					}
				}
			}
			return $output;
		}

		public function get_task_content($post_url, $data, $task) {
			if ($task['post_update'] < 1) {
				$this->increase_index($task);
			}
			if (@$task['run_delay'] && @$task['run_delay'] != '0') {
				if ($this->delay_seconds[$task['run_delay']]) {
					sleep($this->delay_seconds[$task['run_delay']]);
				}
			}
			$post_html = $this->get_url($post_url, $data, $data['feedURL']);
			$post = array(
				'post_status' => $task['post_status'],
				'post_type' => $task['post_type'],
				'post_category' => ''
			);
			$gallery = array();
			$variables = array();
			$query_success = 0;
			$categories = array();
			$custom_fields = array();
			$tag_taxonomies = array();
			$field_by_type = array();
			$required_count = 0;
			$required_success = 0;
			$uniqueness = -1;
			foreach ($data['fields'] as $key => $field) {
				if ($field['type'] == 'variable' || $field['type'] == 'shortcode') {
					if ($task['parse_method'] == 'xpath') {
						$field_content = $this->parse_xpath($post_html, $field['path'], $field['prop']);
					} else {
						$field_content = $this->parse_regex($post_html, $field['path'], $field);
					}
					if ($field_content) {
						$query_success++;
					}
					if ($field['type'] == 'variable') {
						$variables[$field['name']] = $this->transform(@$field_content[0], $field, $data['fields'], array(), array(), array(), $data, array(), $post_url);
					}
					if ($field['type'] == 'shortcode') {
						$variables[$field['name']] = $this->transform(@$field_content[0], $field, $data['fields'], array(), array(), array(), $data, array(), $post_url);
					}
				}
			}
			foreach ($data['fields'] as $key => $field) {
				if ($field['type'] == 'post_title' || $field['type'] == '_sku') {
					if ($task['parse_method'] == 'xpath') {
						$field_content = $this->parse_xpath($post_html, $field['path'], $field['prop']);
					} else {
						$field_content = $this->parse_regex($post_html, $field['path'], $field);
					}
					if ($field_content) {
						$query_success++;
					}
					if ($field['type'] == 'post_title') {
						$field_content = $this->parse_xpath($post_html, $field['path'], $field['prop']);
						$post['post_title'] = $this->transform(@$field_content[0], $field, $data['fields'], $variables, array(), array(), $data, array(), $post_url);
					} else if ($field['type'] == '_sku') {
						$field_content = $this->parse_xpath($post_html, $field['path'], $field['prop']);
						$post[$field['type']] = $this->transform(@$field_content[0], $field, $data['fields'], $variables, array(), array(), $data, array(), $post_url);
					}
				}
			}
			if ($task['uniqueness_method'] == 'post_title' && $task['post_update'] == -1) {
				$uniqueness = $this->is_post_unique($post['post_title'], 'post_title', $task['post_type'], $data);
			}
			if ($task['uniqueness_method'] == 'URL' && $task['post_update'] == -1) {
				$uniqueness = $this->is_post_unique($post_url, 'URL', $task['post_type'], $data);
			}
			if ($task['uniqueness_method'] == 'product_sku' && $task['post_update'] == -1) {
				$uniqueness = $this->is_post_unique($post['_sku'], 'product_sku', $task['post_type'], $data);
			}
			if ($uniqueness > -1 && @$task['track_changes'] == '0') {
				$post['time'] = time();
				$post['date'] = date('Y-m-d H:i:s');
				return $post;
			}
			$excluded = $this->check_excluded($task, $post, @$task['exclude_field'], $variables);
			if ($excluded) {
				return false;
			}
			if (strpos($task['task_condition'], 'AND')) {
				$string_explode = explode('AND', $task['task_condition']);
				$result_arr = array();
				if ($string_explode) {
					foreach ($string_explode as $string_e_value) {
						$string_e_value = str_replace('(', '', $string_e_value);
						$string_e_value = str_replace(')', '', $string_e_value);
						$result_arr[] = $this->check_conditions($task, $post, $variables, $string_e_value);
					}
				}
				if (count(array_filter($result_arr)) == count($result_arr)) {
					//do nothing
				} else {
					return false;
				}
			} else if (strpos($task['task_condition'], 'OR')) {
				$string_explode = explode('OR', $task['task_condition']);
				$result_arr = array();
				if ($string_explode) {
					foreach ($string_explode as $string_e_value) {
						$string_e_value = str_replace('(', '', $string_e_value);
						$string_e_value = str_replace(')', '', $string_e_value);
						$result_arr[] = $this->check_conditions($task, $post, $variables, $string_e_value);
					}
				}
				if (!array_filter($result_arr)) {
					return false;
				}
			} else {
				$check_conditions = $this->check_conditions($task, $post, $variables);
				if (!$check_conditions) {
					return false;
				}
			}

			foreach ($data['fields'] as $key => $field) {
				if (isset($field['isRequired']) && @$field['isRequired'] == 'true') {
					$required_count++;
				}
				if ($task['parse_method'] == 'xpath') {
					$field_content = $this->parse_xpath($post_html, $field['path'], $field['prop']);
				} else {
					$field_content = $this->parse_regex($post_html, $field['path'], $field);
				}
				if ($field_content) {
					$query_success++;
					if (isset($field['isRequired']) && @$field['isRequired'] == 'true') {
						$required_success++;
					}
				}
				if ($field['type'] == 'featured_image') {
					$attachment_url = $this->transform(@$field_content[0], $field, $data['fields'], array(), array(), array(), $data, array(), $post_url, $task, $post);
					$post['featured_image'] = $attachment_url;
				} else if ($field['type'] == 'image') {
					$attachment_url = $this->transform(@$field_content[0], $field, $data['fields'], array(), array(), array(), $data, array(), $post_url, $task, $post);
					$post['image'] = $attachment_url;
				} else if ($field['type'] == 'downloaded_file') {
					$attachment_url = $this->transform(@$field_content[0], $field, $data['fields'], array(), array(), array(), $data, array(), $post_url, $task, $post);
					$post['downloaded_file'] = $attachment_url;
				} elseif ($field['type'] == 'gallery') {
					$gallery = array();
					foreach ($field_content as $key => $field_url) {
						$field_url = $this->transform(@$field_url, $field, $data['fields'], array(), array(), array(), $data, array(), $post_url, $task, $post);
						$field_url = is_array($field_url) ? $field_url : array($field_url);
						foreach ($field_url as $key => $field_url_array_part) {
							$gallery[] = $field_url_array_part;
						}
					}
					$post['gallery'] = implode(', ', $gallery);
				} else if ($field['type'] == 'variable') {
					//Pass
				} else if ($field['type'] == 'post_title') {
					$post[$field['type']] = $this->transform(@$field_content[0], $field, $data['fields'], $variables, array(), array(), $data, array(), $post_url, $task, $post);
				} else if ($field['type'] == 'post_date') {
					$date = $this->transform(@$field_content[0], $field, $data['fields'], $variables, array(), array(), $data, array(), $post_url, $task, $post);
					$post[$field['type']] = $this->date_transform($date);
					$post['post_date_gmt'] = $this->date_transform($date);
					$post['post_modified'] = $this->date_transform($date);
					$post['post_modified_gmt'] = $this->date_transform($date);
				} else if ($field['type'] == 'post_content') {
					$multiple_content_element = '';
					foreach (@$field_content as $key => $value) {
						$multiple_content_element .= $this->transform($value, $field, $data['fields'], $variables, array(), array(), $data, array(), $post_url, $task, $task, $post);
					}
					if (count($field_content) == 0) {
						$multiple_content_element = $this->transform('', $field, $data['fields'], $variables, array(), array(), $data, array(), $post_url, $task, $task, $post);
					}
					$post[$field['type']] = $multiple_content_element;
				} else if ($field['type'] == 'post_excerpt') {
					$multiple_content_element = '';
					foreach (@$field_content as $key => $value) {
						$multiple_content_element .= $this->transform($value, $field, $data['fields'], $variables, array(), array(), $data, array(), $post_url, $task, $task, $post);
					}
					$post[$field['type']] = $multiple_content_element;
				} else if ($field['type'] == 'tags_input') {
					$tags_input = array();
					foreach ($field_content as $field_key => $field_value) {
						$tags_input[] = $this->transform(@$field_value, $field, $data['fields'], $variables, array(), array(), $data, array(), $post_url);
					}
					if (!$field['name']) {
						$field['name'] = 'post_tag';
					}
					if (!@$tag_taxonomies[$field['name']]) {
						$tag_taxonomies[$field['name']] = array();
					}
					if ($field['splitContent'] == 'true') {
						$tag_taxonomies[$field['name']] = @$tags_input[0];
					} else {
						$tag_taxonomies[$field['name']] = @$tags_input;
					}
				} else if ($field['type'] == 'post_author') {
					$post['post_author'] = $this->transform(@$field_content[0], $field, $data['fields'], $variables, array(), array(), $data, array(), $post_url, $task, $post);
				} else if ($field['type'] == 'post_slug') {
					$post['post_slug'] = $this->transform(@$field_content[0], $field, $data['fields'], $variables, array(), array(), $data, array(), $post_url, $task, $post);
				} else if ($field['type'] == 'post_category') {
					foreach ($field_content as $field_key => $field_value) {
						$categories[] = $this->transform(@$field_value, $field, $data['fields'], $variables, array(), array(), $data, array(), $post_url);
					}
					if ($field['splitContent'] == 'true') {
						$post[$field['type']] = @$categories[0];
					} else {
						$post[$field['type']] = @$categories;
					}
				} else {
					if (@$field['isMultiple'] == 'true') {
						if (@$field_content) {
							foreach ($field_content as $key => $content) {
								if (!@$custom_fields[$field['type']]) {
									$custom_fields[$field['type']] = array();
								}
								@$custom_fields[$field['type']][@$field['name']][] = $this->transform(@$content, $field, $data['fields'], $variables, array(), array(), $data, array(), $post_url);
							}
						} else {
							$possible_split = $this->transform(@$field_content[0], $field, $data['fields'], $variables, array(), array(), $data, array(), $post_url);
							foreach (@$possible_split as $key => $content) {
								@$custom_fields[$field['type']][@$field['name']][] = $content;
							}
						}
					} else {
						@$custom_fields[$field['type']] = $this->transform(@$field_content[0], $field, $data['fields'], $variables, array(), array(), $data, array(), $post_url);
					}
					$field_by_type[$field['type']] = $field;
				}
			}

			if ($query_success > 0 && $required_count == $required_success) {
				if (!empty($tag_taxonomies)) {
					foreach ($tag_taxonomies as $taxonomy => $terms) {
						$post[$taxonomy] = (!empty($terms) ? implode(', ', $terms) : '');
					}
				}
				$post_categories = array($task['category_id']);
				if (@$task['category_ids']) {
					$category_ids = json_decode($task['category_ids']);
					$post_categories = array();
					foreach ($category_ids as $key => $category_id) {
						$term = get_term_by('id', $category_id, 'category');
						$post_categories[] = $term->name;
					}
				}
				if ($categories) {
					foreach ($categories as $key => $category_name) {
						$post_categories[] = $category_name;
					}
				}
				if ($task['post_type'] == 'product') {
					$post['product_cat'] = ($post_categories ? implode(', ', $post_categories) : '');
				} else {
					$post['post_category'] = ($post_categories ? implode(', ', $post_categories) : '');
				}
				if ($custom_fields) {
					foreach ($custom_fields as $key => $value) {
						$post["_{$key}"] = $value;
						if (@$field_by_type[$key]['isJSON'] == 'true') {
							$post["_{$key}"] = $value[$field_by_type[$key]['name']];
						} else if (is_array($value)) {
							$items = $value;
							foreach ($items as $cf_name => $cf_values) {
								$custom_field_key = "_{$key}_{$cf_name}";
								$post["_{$key}_{$cf_name}"] = implode('|', $cf_values);
							}
						}
					}
				}
				$post['time'] = time();
				$post['date'] = date('Y-m-d H:i:s');
				$post['_scraper_post_source_url'] = $post_url;
				$post['_scraper_task_hash'] = @$task['hash'];
				return $post;
			} else {
				return false;
			}
		}

		public function export_to_csv($filename = 'export.csv', $data = array(), $headers = array()) {
			ob_clean();
			ob_start();
			header("Content-Type: application/force-download");
			header("Content-Type: application/octet-stream");
			header("Content-Type: application/download");
			header('Content-Type: text/x-csv');
			header('Content-Disposition: attachment;filename=' . $filename);
			header("Content-Transfer-Encoding: binary");
			$fp = fopen('php://output', 'w');
			if (!empty($data)) {
				/**
				 * Print Header
				 */
				if (empty($headers)) {
					foreach ($data as $item) {
						$headers = array_keys($item);
					}
				}
				fputcsv($fp, $headers);
				/**
				 * Print Row Data
				 */
				foreach ($data as $item) {
					$rowData = array_values($item);
					fputcsv($fp, $rowData);
				}
			}
			exit;
		}

	}

}
$SCRAPER_ExportToCSV = new SCRAPER_ExportToCSV();
