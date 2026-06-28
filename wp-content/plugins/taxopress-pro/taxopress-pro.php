<?php
/**
 * Plugin Name: TaxoPress Pro
 * Plugin URI: https://wordpress.org/plugins/simple-tags/
 * Description: TaxoPress allows you to create and manage Tags, Categories, and all your WordPress taxonomy terms.
 * Version: 3.37.2
 * Author: TaxoPress
 * Author URI: https://taxopress.com
 * Text Domain: taxopress-pro
 * Domain Path: /languages
 * Min WP Version: 4.9.7
 * Requires PHP: 7.2
 * License: GPLv3
 *
 * Copyright (c) 2022 Taxopress
 *
 * @package 	taxopress-pro
 * @author		TaxoPress
 * @copyright   Copyright (c) 2022 Taxopress
 * @license		GNU General Public License version 2
 * @link		https://TaxoPress.com/
 */

######################################
/*
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

Contributors to the TaxoPress code include:
    - Kevin Drouvin (kevin.drouvin@gmail.com - http://inside-dev.net)
    - Martin Modler (modler@webformatik.com - http://www.webformatik.com)
    - Vladimir Kolesnikov (vladimir@extrememember.com - http://blog.sjinks.pro)

Sections of the TaxoPress code are based on Custom Post Type UI by WebDevStudios.

Credits Icons :
    - famfamfam - http://www.famfamfam.com/lab/icons/silk/

*/

// don't load directly
if (!defined('ABSPATH')) {
    die('-1');
}

if (!defined('STAGS_VERSION')) {
define('STAGS_VERSION', '3.37.2');
}


if (defined('TAXOPRESS_FILE')) {
	return;
}

define ( 'TAXOPRESS_FILE', __FILE__ );
define('STAGS_MIN_PHP_VERSION', '7.2');
define('STAGS_OPTIONS_NAME', 'simpletags'); // Option name for save settings
define('STAGS_OPTIONS_NAME_AUTO', 'simpletags-auto'); // Option name for save settings auto terms

define('STAGS_URL', plugins_url('', __FILE__));
define('STAGS_DIR', rtrim(plugin_dir_path(__FILE__), '/'));
define('TAXOPRESS_ABSPATH', __DIR__);
define('TAXOPRESS_PLUGIN_FILE', TAXOPRESS_ABSPATH . '/taxopress-pro.php');

define('TAXOPRESS_VERSION',	  	STAGS_VERSION);
define('TAXOPRESS_PRO_VERSION', STAGS_VERSION);
define('TAXOPRESS_PRO_EDD_ITEM_ID', 608);
define('TAXOPRESS_EDD_STORE_URL', 'https://taxopress.com');
define('TAXOPRESS_PLUGIN_AUTHOR', 'PublishPress');

// Check PHP min version
if (version_compare(PHP_VERSION, STAGS_MIN_PHP_VERSION, '<')) {
    require STAGS_DIR . '/inc/class.compatibility.php';

    // possibly display a notice, trigger error
    add_action('admin_init', array('SimpleTags_Compatibility', 'admin_init'));

    // stop execution of this file
    return;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

require STAGS_DIR . '/inc/loads.php';

// Activation, uninstall
register_activation_hook(__FILE__, array('SimpleTags_Plugin', 'activation'));
register_deactivation_hook(__FILE__, array('SimpleTags_Plugin', 'deactivation'));

add_action('init', 'init_simple_tags', 0);


// *** Pro includes ***
require_once (dirname(__FILE__) . '/includes-pro/load.php');
