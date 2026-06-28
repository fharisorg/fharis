<?php

/*
 * Plugin Name: Featured Image from URL (FIFU) Premium
 * Plugin URI: https://fifu.app/
 * Description: Use remote media as the featured image and beyond.
 * Version: 7.1.5
 * Author: fifu.app
 * Author URI: https://fifu.app/
 * WC requires at least: 4.0
 * WC tested up to: 10.2.2
 * Text Domain: fifu-premium
 */

define('FIFU_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FIFU_INCLUDES_DIR', FIFU_PLUGIN_DIR . 'includes');
define('FIFU_ADMIN_DIR', FIFU_PLUGIN_DIR . 'admin');
define('FIFU_SHARE_DIR', FIFU_PLUGIN_DIR . 'share');
define('FIFU_ELEMENTOR_DIR', FIFU_PLUGIN_DIR . 'elementor');
define('FIFU_GRAVITY_DIR', FIFU_PLUGIN_DIR . 'gravity-forms');
define('FIFU_UPDATE_CHECKER_DIR', FIFU_PLUGIN_DIR . 'plugin-update-checker');
define('FIFU_LANGUAGES_DIR', WP_CONTENT_DIR . '/uploads/fifu/languages/');
define('FIFU_CLOUD_DEBUG', false);

if (!defined('FIFU_DELETE_ALL_URLS')) {
    define('FIFU_DELETE_ALL_URLS', false);
}

$FIFU_SESSION = array();

// Required includes with error handling
$required_includes = [
    FIFU_INCLUDES_DIR . '/util.php',
    FIFU_INCLUDES_DIR . '/structured-data.php',
    FIFU_INCLUDES_DIR . '/ajax.php',
    FIFU_INCLUDES_DIR . '/attachment.php',
    FIFU_INCLUDES_DIR . '/bbpress.php',
    FIFU_INCLUDES_DIR . '/buddyboss.php',
    FIFU_INCLUDES_DIR . '/convert-url.php',
    FIFU_INCLUDES_DIR . '/external-post.php',
    FIFU_INCLUDES_DIR . '/local.php',
    FIFU_INCLUDES_DIR . '/jetpack.php',
    FIFU_INCLUDES_DIR . '/otf.php',
    FIFU_INCLUDES_DIR . '/rest.php',
    FIFU_INCLUDES_DIR . '/shortcode.php',
    FIFU_INCLUDES_DIR . '/speedup.php',
    FIFU_INCLUDES_DIR . '/thumbnail.php',
    FIFU_INCLUDES_DIR . '/thumbnail-category.php',
    FIFU_INCLUDES_DIR . '/video.php',
    FIFU_INCLUDES_DIR . '/woo.php'
];

foreach ($required_includes as $file) {
    if (file_exists($file)) {
        require_once $file;
    }
}

$required_admin = [
    FIFU_ADMIN_DIR . '/api.php',
    FIFU_SHARE_DIR . '/main.php',
    FIFU_SHARE_DIR . '/facebook.php',
    FIFU_SHARE_DIR . '/instagram.php',
    FIFU_SHARE_DIR . '/common-meta.php',
    FIFU_ADMIN_DIR . '/asin.php',
    FIFU_ADMIN_DIR . '/block.php',
    FIFU_ADMIN_DIR . '/books.php',
    FIFU_ADMIN_DIR . '/category.php',
    FIFU_ADMIN_DIR . '/column.php',
    FIFU_ADMIN_DIR . '/cron.php',
    FIFU_ADMIN_DIR . '/cron-api.php',
    FIFU_ADMIN_DIR . '/db.php',
    FIFU_ADMIN_DIR . '/ddg.php',
    FIFU_ADMIN_DIR . '/debug.php',
    FIFU_ADMIN_DIR . '/dimensions.php',
    FIFU_ADMIN_DIR . '/distributor.php',
    FIFU_ADMIN_DIR . '/finder.php',
    FIFU_ADMIN_DIR . '/languages.php',
    FIFU_ADMIN_DIR . '/lightbox.php',
    FIFU_ADMIN_DIR . '/log.php',
    FIFU_ADMIN_DIR . '/media-library.php',
    FIFU_ADMIN_DIR . '/menu.php',
    FIFU_ADMIN_DIR . '/meta-box.php',
    FIFU_ADMIN_DIR . '/meta-box-variation.php',
    FIFU_ADMIN_DIR . '/notices.php',
    FIFU_ADMIN_DIR . '/proxy.php',
    FIFU_ADMIN_DIR . '/rsa.php',
    FIFU_ADMIN_DIR . '/server.php',
    FIFU_ADMIN_DIR . '/strings.php',
    FIFU_ADMIN_DIR . '/sheet-editor.php',
    FIFU_ADMIN_DIR . '/taxonomy.php',
    FIFU_ADMIN_DIR . '/transient.php',
    FIFU_ADMIN_DIR . '/widgets.php',
    FIFU_ADMIN_DIR . '/wai-addon.php',
    FIFU_ADMIN_DIR . '/wpml.php'
];

foreach ($required_admin as $file) {
    if (file_exists($file)) {
        require_once $file;
    }
}

if (file_exists(FIFU_ELEMENTOR_DIR . '/elementor-fifu-extension.php')) {
    require_once (FIFU_ELEMENTOR_DIR . '/elementor-fifu-extension.php');
}

if (function_exists('fifu_is_gravity_forms_active') && fifu_is_gravity_forms_active()) {
    $gravity_forms_file = WP_PLUGIN_DIR . '/gravityforms/gravityforms.php';
    if (file_exists($gravity_forms_file)) {
        require_once $gravity_forms_file;
    }
    if (class_exists('GFForms') && file_exists(FIFU_GRAVITY_DIR . '/fifufieldaddon.php')) {
        require_once (FIFU_GRAVITY_DIR . '/fifufieldaddon.php');
    }
}

if (defined('WP_CLI') && WP_CLI && file_exists(FIFU_ADMIN_DIR . '/cli-commands.php'))
    require_once (FIFU_ADMIN_DIR . '/cli-commands.php');

register_activation_hook(__FILE__, 'fifu_activate');

function fifu_activate($network_wide) {
    // https://multilingualpress.org/docs/how-to-install-wordpress-multisite/
    if (is_multisite() && $network_wide) {
        global $wpdb;
        foreach ($wpdb->get_col("SELECT blog_id FROM $wpdb->blogs") as $blog_id) {
            switch_to_blog($blog_id);
            fifu_activate_actions();
            fifu_set_author();
            restore_current_blog();
        }
        // Execute network-wide operations on main site
        switch_to_blog(get_main_site_id());
        fifu_propagate_key(true);
        restore_current_blog();
    } else {
        fifu_activate_actions();
        fifu_set_author();
        // Set redirect transient only for non-multisite
        set_transient('fifu_redirect_to_settings', true, 30);
    }
}

// Redirect to plugin settings page after activation (non-multisite only)
add_action('admin_init', function () {
    if (!is_multisite() && get_transient('fifu_redirect_to_settings')) {
        delete_transient('fifu_redirect_to_settings');
        if (is_admin() && !isset($_GET['activate-multi'])) {
            wp_safe_redirect(admin_url('admin.php?page=' . FIFU_SLUG));
            exit;
        }
    }
});

function fifu_activate_actions() {
    fifu_db_create_table_video_oembed();
    fifu_db_create_table_import();

    fifu_db_create_table_invalid_media_su();
    fifu_db_create_table_md5();
    fifu_db_maybe_create_table_meta_in();
    fifu_db_maybe_create_table_meta_out();
    fifu_db_maybe_create_table_content();
}

register_deactivation_hook(__FILE__, 'fifu_deactivation');

function fifu_deactivation() {
    
}

add_action('upgrader_process_complete', 'fifu_upgrade', 10, 2);

function fifu_upgrade($upgrader_object, $options) {
    $current_plugin_path_name = plugin_basename(__FILE__);
    if (($options['action'] ?? '') == 'update' && ($options['type'] ?? '') == 'plugin') {
        if (isset($options['plugins'])) {
            foreach ((array) $options['plugins'] as $each_plugin) {
                if ($each_plugin == $current_plugin_path_name) {
                    if (is_multisite()) {
                        global $wpdb;
                        foreach ($wpdb->get_col("SELECT blog_id FROM $wpdb->blogs") as $blog_id) {
                            switch_to_blog($blog_id);
                            fifu_upgrade_actions();
                            restore_current_blog();
                        }
                    } else {
                        fifu_upgrade_actions();
                    }
                }
            }
        }
    }
}

function fifu_upgrade_actions() {
    fifu_db_create_table_video_oembed();
    fifu_db_create_table_import();

    fifu_db_create_table_invalid_media_su();
    fifu_db_create_table_md5();
    fifu_db_maybe_create_table_meta_in();
    fifu_db_maybe_create_table_meta_out();
    fifu_db_maybe_create_table_content();

    fifu_db_delete_deprecated_data();
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'fifu_action_links');
add_filter('network_admin_plugin_action_links_' . plugin_basename(__FILE__), 'fifu_action_links');

function fifu_action_links($links) {
    $strings = fifu_get_strings_plugins();
    $links[] = '<a href="' . esc_url(get_admin_url(null, 'admin.php?page=fifu-premium')) . '">' . $strings['settings']() . '</a>';
    return $links;
}

function fifu_filter_update_checks($queryArgs) {
    $queryArgs['license_key'] = get_option('fifu_key');
    $parsed_url = parse_url(get_site_url());
    $queryArgs['domain'] = $parsed_url['host'] ?? '';
    return $queryArgs;
}

if (is_admin()) {
    if (file_exists(__DIR__ . '/plugin-update-checker/plugin-update-checker.php')) {
        require 'plugin-update-checker/plugin-update-checker.php';

        if (class_exists('YahnisElsts\PluginUpdateChecker\v5\PucFactory')) {
            $fifuUpdateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
                    'https://update.fifu.app/details',
                    __FILE__,
                    'fifu-premium'
            );

            // add the license key to query arguments
            $fifuUpdateChecker->addQueryArgFilter('fifu_filter_update_checks');
        }
    }
}

function fifu_expired_row_meta($plugin_meta, $plugin_file, $plugin_data, $status) {
    if (strpos($plugin_file, 'fifu-premium.php') !== false) {
        $new_links = array(
            'email' => '<a style="color:#2271b1">support@fifu.app</a>',
        );
        $plugin_meta = array_merge($plugin_meta, $new_links);
    }
    return $plugin_meta;
}

add_filter('plugin_row_meta', 'fifu_expired_row_meta', 10, 4);

function fifu_uninstall() {
    global $pagenow;
    if ($pagenow !== 'plugins.php')
        return;

    $strings = fifu_get_strings_uninstall();

    wp_enqueue_script('jquery-block-ui', 'https://cdnjs.cloudflare.com/ajax/libs/jquery.blockUI/2.70/jquery.blockUI.min.js');
    wp_enqueue_style('fancy-box-css', 'https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.css');
    wp_enqueue_script('fancy-box-js', 'https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.min.js');
    wp_enqueue_style('fifu-uninstall-css', plugins_url('includes/html/css/uninstall.css', __FILE__), array(), fifu_version_number_enq());
    wp_enqueue_script('fifu-uninstall-js', plugins_url('includes/html/js/uninstall.js', __FILE__), array('jquery'), fifu_version_number_enq());
    wp_localize_script('fifu-uninstall-js', 'fifuUninstallVars', [
        'restUrl' => esc_url_raw(rest_url()),
        'nonce' => wp_create_nonce('wp_rest'),
        'buttonTextClean' => $strings['button']['text']['clean'](),
        'buttonTextDeactivate' => $strings['button']['text']['deactivate'](),
        'buttonDescriptionClean' => $strings['button']['description']['clean'](),
        'buttonDescriptionDeactivate' => $strings['button']['description']['deactivate'](),
    ]);
}

add_action('admin_footer', 'fifu_uninstall');

// https://developer.woocommerce.com/docs/hpos-extension-recipe-book/
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

function fifu_custom_action_after_site_initialization($new_site) {
    if (!is_multisite()) {
        return;
    }

    $main_site_id = function_exists('get_main_site_id') ? get_main_site_id() : 0;
    if (!$main_site_id) {
        return;
    }

    $switched = false;
    if (get_current_blog_id() !== $main_site_id) {
        switch_to_blog($main_site_id);
        $switched = true;
    }

    fifu_propagate_key(true);

    if ($switched) {
        restore_current_blog();
    }
}

add_action('wp_initialize_site', 'fifu_custom_action_after_site_initialization');

