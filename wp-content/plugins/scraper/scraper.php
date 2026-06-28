<?php

/*
  Plugin Name: Scraper
  Plugin URI: https://scraper.site
  Description: Scraper is a Wordpress plugin that copies content and posts automatically from any website. With tons of useful and unique features, Scraper takes content creating process to another level.
  Author: wpBots
  Author URI: https://scraper.site
  Version: 2.0.6
 */

define('SCRAPER_PLUGIN_VERSION', '2.0.6');
define('SCRAPER_PLUGIN_NAME', 'Scraper');
define('SCRAPER_PLUGIN_URL', 'https://scraper.site');
define('SCRAPER_PLUGIN_MAIN_FILE_PATH', __FILE__);
define('SCRAPER_CONFIG_MENU_TEXT', 'Scraper Settings');

define('SCRAPER_MAXIMUM_POST_COUNT', 200);

require_once('plugin.php');

//3rd party libraries
require_once('libraries/bitly.php');

SCRAPER_Plugin_init();
register_activation_hook(SCRAPER_PLUGIN_MAIN_FILE_PATH, array('SCRAPER_Plugin', 'activate'));
register_deactivation_hook(SCRAPER_PLUGIN_MAIN_FILE_PATH, array('SCRAPER_Plugin', 'deactivate'));
register_uninstall_hook(SCRAPER_PLUGIN_MAIN_FILE_PATH, array('SCRAPER_Plugin', 'uninstall'));

/**
 * Cron URls for tasks.
 */
require_once('libraries/cron-urls.php');
/**
 * Export output to CSV file
 */
require_once('libraries/export-to-csv.php');