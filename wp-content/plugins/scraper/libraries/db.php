<?php

if (!class_exists('SCRAPER_DB')) {

	class SCRAPER_DB {

		public function __construct() {
			$this->prepare_db_tables();
		}

		/**
		 * Create DB tables if not exitst.
		 */
		public function prepare_db_tables() {
			global $wpdb;
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			$collate = $wpdb->has_cap('collation') ? $wpdb->get_charset_collate() : '';

			$items_table = $wpdb->prefix . "scraper_items";
			if ($wpdb->get_var("SHOW TABLES LIKE '{$items_table}'") != $items_table) {
				$items_sql = "CREATE TABLE IF NOT EXISTS `$items_table` (
					`id` int(11) NOT NULL AUTO_INCREMENT,
					`hash` varchar(32) NOT NULL,
					`result` longtext,
					`timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					PRIMARY KEY (`id`),
					KEY `id` (`id`),
					KEY `hash` (`hash`),
					KEY `timestamp` (`timestamp`)
				) {$charset_collate};";
				dbDelta($items_sql);
			}
		}

		public function add_items($hash = '', $result = array()) {
			global $wpdb;
			$item_id = 0;
			if (!empty($hash)) {
				$wpdb->insert("{$wpdb->prefix}scraper_items", array('hash' => $hash, 'result' => maybe_serialize($result)));
				$item_id = $wpdb->insert_id;
			}
			return $item_id;
		}

		public function update_items($hash = '', $result = array()) {
			global $wpdb;
			if (!empty($hash)) {
				$new_data = array(
					'hash' => $hash,
					'result' => maybe_serialize($result)
				);
				$last_id = $wpdb->get_var("SELECT `id` FROM `{$wpdb->prefix}scraper_items` WHERE `hash`='{$hash}' ORDER BY `id` DESC");
				if (!empty($last_id)) {
					$updated = $wpdb->update("{$wpdb->prefix}scraper_items", $new_data, array('hash' => $hash, 'id' => $last_id));
					if (false === $updated) {
						return false;
					} else {
						return $last_id;
					}
				} else {
					$wpdb->insert("{$wpdb->prefix}scraper_items", $new_data);
					return $wpdb->insert_id;
				}
			}
			return false;
		}

		public function get_last_log($hash = '') {
			global $wpdb;
			$result = array();
			if (!empty($hash)) {
				$item = $wpdb->get_row("SELECT * FROM `{$wpdb->prefix}scraper_items` WHERE `hash`='{$hash}' ORDER BY `id` DESC");
				if (!empty($item)) {
					$result = maybe_unserialize(str_replace('\n', ' ', $item->result));
				}
			}
			return $result;
		}

	}

}

global $SCRAPER_DB;
$SCRAPER_DB = new SCRAPER_DB();
