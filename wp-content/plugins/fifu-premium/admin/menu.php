<?php

define('FIFU_SETTINGS', serialize(array('fifu_block', 'fifu_popup', 'fifu_redirection', 'fifu_auto_set', 'fifu_auto_set_width', 'fifu_auto_set_height', 'fifu_auto_set_blocklist', 'fifu_auto_set_cpt', 'fifu_auto_set_source', 'fifu_auto_set_layout', 'fifu_tags_orientation', 'fifu_html_media', 'fifu_upload_domain', 'fifu_skip', 'fifu_html_cpt', 'fifu_isbn', 'fifu_isbn_custom_field', 'fifu_asin', 'fifu_asin_custom_field', 'fifu_asin_credentials_partner', 'fifu_asin_credentials_access', 'fifu_asin_credentials_secret', 'fifu_asin_credentials_locale', 'fifu_square_mobile', 'fifu_square_desktop', 'fifu_screenshot_custom_field', 'fifu_screenshot_size', 'fifu_customfield', 'fifu_customfield_custom_field', 'fifu_finder_custom_field', 'fifu_finder', 'fifu_video_finder', 'fifu_amazon_finder', 'fifu_tags', 'fifu_screenshot', 'fifu_debug', 'fifu_audio', 'fifu_photon', 'fifu_otfcdn', 'fifu_own_domain', 'fifu_cdn_content', 'fifu_reset', 'fifu_fake', 'fifu_order_email', 'fifu_gallery', 'fifu_adaptive_height', 'fifu_videos_before', 'fifu_variations_merge', 'fifu_buy', 'fifu_key', 'fifu_email', 'fifu_error_url', 'fifu_default_url', 'fifu_default_cpt', 'fifu_pcontent_types', 'fifu_hide_format', 'fifu_hide_type', 'fifu_enable_default_url', 'fifu_cron_metadata', 'fifu_video_min_width', 'fifu_video_color', 'fifu_video_zindex', 'fifu_video_size', 'fifu_slider', 'fifu_slider_auto', 'fifu_slider_gallery', 'fifu_slider_thumb', 'fifu_slider_counter', 'fifu_slider_crop', 'fifu_slider_single', 'fifu_slider_vertical', 'fifu_slider_ctrl', 'fifu_slider_stop', 'fifu_slider_speed', 'fifu_slider_pause', 'fifu_wc_lbox', 'fifu_wc_zoom', 'fifu_hide', 'fifu_pcontent_add', 'fifu_pcontent_remove', 'fifu_get_first', 'fifu_ovw_first', 'fifu_update_all', 'fifu_run_delete_all', 'fifu_mouse_video', 'fifu_loop', 'fifu_autoplay', 'fifu_autoplay_front', 'fifu_autoplay_elsewhere', 'fifu_video_mute', 'fifu_video_mute_mobile', 'fifu_video_background', 'fifu_video_background_single', 'fifu_video_privacy', 'fifu_video_later', 'fifu_video_later_left', 'fifu_video', 'fifu_video_thumb', 'fifu_video_thumb_page', 'fifu_video_thumb_post', 'fifu_video_thumb_cpt', 'fifu_video_play_button', 'fifu_video_play_hide_grid', 'fifu_video_play_hide_grid_wc', 'fifu_video_controls', 'fifu_auto_category', 'fifu_taxonomy', 'fifu_data_clean', 'fifu_shortform', 'fifu_play_type', 'fifu_upload_show', 'fifu_upload_proxy', 'fifu_upload_job', 'fifu_upload_private_proxy', 'fifu_slider_left', 'fifu_slider_right', 'fifu_buy_text', 'fifu_buy_disclaimer', 'fifu_buy_cf', 'fifu_bbpress_fields', 'fifu_cloud_upload_auto', 'fifu_cloud_delete_auto', 'fifu_cloud_hotlink')));
define('FIFU_ACTION_SETTINGS', '/wp-admin/admin.php?page=fifu-premium');
define('FIFU_ACTION_CLOUD', '/wp-admin/admin.php?page=fifu-cloud');

define('FIFU_SLUG', 'fifu-premium');

add_action('admin_menu', 'fifu_insert_menu');
if (is_multisite()) {
    add_action('network_admin_menu', 'fifu_insert_network_menu');
}

function fifu_with_main_site($callback) {
    $switched = false;

    if (is_multisite()) {
        $main_site_id = function_exists('get_main_site_id') ? get_main_site_id() : 0;
        if ($main_site_id) {
            $current_blog_id = get_current_blog_id();
            if ($current_blog_id !== $main_site_id) {
                switch_to_blog($main_site_id);
                $switched = true;
            }
        }
    }

    $callback();

    if ($switched) {
        restore_current_blog();
    }
}

function fifu_insert_menu() {
    fifu_insert_menu_common('manage_options', false);
}

function fifu_insert_network_menu() {
    if (!is_multisite()) {
        return;
    }

    fifu_with_main_site(function () {
        fifu_insert_menu_common('manage_network_options', true);
    });
}

function fifu_insert_menu_common($capability, $is_network) {
    $fifu = fifu_get_strings_settings();

    if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'fifu') !== false) {
        wp_enqueue_script('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/js/all.min.js');
        wp_enqueue_style('jquery-ui-style1', 'https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.css');
        wp_enqueue_style('jquery-ui-style2', 'https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.structure.min.css');
        wp_enqueue_style('jquery-ui-style3', 'https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.theme.min.css');

        wp_enqueue_script('jquery-ui', 'https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js');
        wp_enqueue_script('jquery-block-ui', 'https://cdnjs.cloudflare.com/ajax/libs/jquery.blockUI/2.70/jquery.blockUI.min.js');

        wp_enqueue_style('datatable-css', '//cdn.datatables.net/1.11.3/css/jquery.dataTables.min.css');
        wp_enqueue_style('datatable-select-css', '//cdn.datatables.net/select/1.3.3/css/select.dataTables.min.css');
        wp_enqueue_style('datatable-buttons-css', '//cdn.datatables.net/buttons/2.0.1/css/buttons.dataTables.min.css');
        wp_enqueue_script('datatable-js', '//cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js');
        wp_enqueue_script('datatable-select', '//cdn.datatables.net/select/1.3.3/js/dataTables.select.min.js');
        wp_enqueue_script('datatable-buttons', '//cdn.datatables.net/buttons/2.0.1/js/dataTables.buttons.min.js');

        wp_enqueue_script('fifu-rest-route-js', plugins_url('/html/js/rest-route.js', __FILE__), array('jquery'), fifu_version_number_enq());

        // register custom variables for the AJAX script
        wp_localize_script('fifu-rest-route-js', 'fifuScriptVars', [
            'restUrl' => esc_url_raw(rest_url()),
            'homeUrl' => esc_url_raw(home_url()),
            'nonce' => wp_create_nonce('wp_rest'),
            'networkAdmin' => is_network_admin(),
        ]);

        wp_enqueue_script('fifu-async', plugins_url('/html/js/async.js', __FILE__), array('jquery'), fifu_version_number_enq());
        wp_localize_script('fifu-async', 'fifuAsyncVars', array(
            'restUrl' => esc_url_raw(rest_url()),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_rest'),
            'server_ok' => $fifu['server']['success'](),
            'server_nok' => $fifu['server']['fail'](),
            'key_success' => $fifu['key']['success']['main'](),
            'key_expired' => $fifu['key']['expired']['main'](),
            'key_invalid' => $fifu['key']['invalid']['main'](),
            'key_success_details' => $fifu['key']['success']['details'](),
            'key_expired_details' => $fifu['key']['expired']['details'](),
            'key_invalid_details' => $fifu['key']['invalid']['details'](),
            'expiredText' => $fifu['options']['expired'](),
            'networkAdmin' => is_network_admin(),
        ));
    }

    $menu_callback = $is_network ? 'fifu_get_network_menu_html' : 'fifu_get_menu_html';
    $license_callback = $is_network ? 'fifu_network_license_key' : 'fifu_license_key';
    $is_network_admin = $is_network;
    $cloud_callback = $is_network ? 'fifu_network_cloud' : 'fifu_cloud';
    $troubleshooting_callback = $is_network ? 'fifu_network_troubleshooting' : 'fifu_troubleshooting';
    $status_callback = $is_network ? 'fifu_network_support_data' : 'fifu_support_data';
    $test_callback = $is_network ? 'fifu_network_test_page' : 'fifu_test_page';

    add_menu_page('Featured Image from URL', 'FIFU', $capability, FIFU_SLUG, $menu_callback, 'dashicons-camera', 57);
    add_submenu_page(FIFU_SLUG, 'FIFU Settings', $fifu['options']['settings'](), $capability, FIFU_SLUG, $menu_callback);
    add_submenu_page(FIFU_SLUG, 'FIFU License key', $fifu['options']['key'](), $capability, 'fifu-license-key', $license_callback);
    // add_submenu_page(FIFU_SLUG, 'FIFU Test', 'Test', $capability, 'fifu-test', $test_callback);

    if (!$is_network_admin) {
        add_submenu_page(FIFU_SLUG, 'FIFU Cloud', $fifu['options']['cloud'](), $capability, 'fifu-cloud', $cloud_callback);
        add_submenu_page(FIFU_SLUG, 'FIFU Troubleshooting', $fifu['options']['troubleshooting'](), $capability, 'fifu-troubleshooting', $troubleshooting_callback);
        add_submenu_page(FIFU_SLUG, 'FIFU Status', $fifu['options']['status'](), $capability, 'fifu-support-data', $status_callback);
    }

    add_action('admin_init', 'fifu_get_menu_settings');
}

function fifu_get_network_menu_html() {
    fifu_with_main_site('fifu_get_menu_html');
}

function fifu_network_license_key() {
    fifu_with_main_site('fifu_license_key');
}

function fifu_network_cloud() {
    fifu_with_main_site('fifu_cloud');
}

function fifu_network_troubleshooting() {
    fifu_with_main_site('fifu_troubleshooting');
}

function fifu_network_support_data() {
    fifu_with_main_site('fifu_support_data');
}

function fifu_network_test_page() {
    fifu_with_main_site('fifu_test_page');
}

function fifu_test_page() {
    flush();

    $fifu = fifu_get_strings_settings();

    wp_enqueue_style('fifu-base-ui-css', plugins_url('/html/css/base-ui.css', __FILE__), array(), fifu_version_number_enq());
    wp_enqueue_style('fifu-menu-css', plugins_url('/html/css/menu.css', __FILE__), array(), fifu_version_number_enq());
    wp_enqueue_script('fifu-menu-js', plugins_url('/html/js/menu.js', __FILE__), array('jquery', 'jquery-ui'), fifu_version_number_enq());

    wp_enqueue_style('fifu-test', plugins_url('/html/css/test.css', __FILE__), array(), fifu_version_number_enq());
    wp_enqueue_script('fifu-test', plugins_url('/html/js/test.js', __FILE__), array('jquery', 'jquery-ui'), fifu_version_number_enq());

    wp_localize_script('fifu-menu-js', 'fifuScriptVars', [
        'restUrl' => esc_url_raw(rest_url()),
        'homeUrl' => esc_url_raw(home_url()),
        'nonce' => wp_create_nonce('wp_rest'),
        'wait' => $fifu['php']['message']['wait'](),
        'wait1' => $fifu['php']['message']['wait1'](),
        'lock' => get_option('fifu_lock'),
        'emptyKey' => empty(get_option('fifu_key')),
        'expired' => get_option('fifu_expired'),
        'keyText' => $fifu['options']['key'](),
        'pluginUrl' => plugins_url() . '/' . FIFU_SLUG,
        'networkAdmin' => is_network_admin(),
    ]);

    include 'html/test.html';
}

function fifu_cloud() {
    flush();

    $fifu = fifu_get_strings_settings();
    $fifucloud = fifu_get_strings_cloud();

    // css and js
    wp_enqueue_script('fifu-cookie', 'https://cdnjs.cloudflare.com/ajax/libs/js-cookie/latest/js.cookie.min.js');
    wp_enqueue_style('fifu-menu-su-css', plugins_url('/html/css/menu-su.css', __FILE__), array(), fifu_version_number_enq());
    wp_enqueue_script('fifu-menu-su-js', plugins_url('/html/js/menu-su.js', __FILE__), array('jquery', 'jquery-ui'), fifu_version_number_enq());

    wp_enqueue_style('fifu-base-ui-css', plugins_url('/html/css/base-ui.css', __FILE__), array(), fifu_version_number_enq());
    wp_enqueue_style('fifu-menu-css', plugins_url('/html/css/menu.css', __FILE__), array(), fifu_version_number_enq());
    wp_enqueue_script('fifu-cloud-js', plugins_url('/html/js/cloud.js', __FILE__), array('jquery'), fifu_version_number_enq());

    wp_localize_script('fifu-cloud-js', 'fifuScriptCloudVars', [
        'signUpComplete' => fifu_su_sign_up_complete(),
        'woocommerce' => class_exists('WooCommerce'),
        'availableImages' => fifu_db_count_available_images(),
        'down' => $fifucloud['ws']['down'](),
        'connected' => $fifucloud['ws']['connection']['ok'](),
        'notConnected' => $fifucloud['ws']['connection']['fail'](),
        'noImages' => $fifucloud['table']['no']['images'](),
        'noPosts' => $fifucloud['table']['no']['posts'](),
        'noData' => $fifucloud['table']['no']['data'](),
        'selectAll' => $fifucloud['table']['select']['all'](),
        'selectNone' => $fifucloud['table']['select']['none'](),
        'load' => $fifucloud['table']['load'](),
        'limit' => $fifucloud['table']['limit'](),
        'delete' => $fifucloud['table']['delete'](),
        'upload' => $fifucloud['table']['upload'](),
        'link' => $fifucloud['table']['link'](),
        'dialogDelete' => $fifucloud['table']['dialog']['delete'](),
        'dialogCancel' => $fifucloud['table']['dialog']['cancel'](),
        'dialogYes' => $fifucloud['table']['dialog']['yes'](),
        'dialogNo' => $fifucloud['table']['dialog']['no'](),
        'category' => $fifucloud['table']['category'](),
        'slider' => $fifucloud['table']['slider'](),
        'gallery' => $fifucloud['table']['gallery'](),
        'featured' => $fifucloud['table']['featured'](),
        'filterResults' => $fifucloud['table']['filter'](),
        'showResults' => $fifucloud['table']['show'](),
    ]);

    $enable_cloud_upload_auto = get_option('fifu_cloud_upload_auto');
    $enable_cloud_delete_auto = get_option('fifu_cloud_delete_auto');
    $enable_cloud_hotlink = get_option('fifu_cloud_hotlink');

    include 'html/cloud.html';

    if (fifu_is_valid_nonce('nonce_fifu_form_cloud_upload_auto', FIFU_ACTION_CLOUD))
        fifu_update_option('fifu_input_cloud_upload_auto', 'fifu_cloud_upload_auto');

    if (fifu_is_valid_nonce('nonce_fifu_form_cloud_delete_auto', FIFU_ACTION_CLOUD))
        fifu_update_option('fifu_input_cloud_delete_auto', 'fifu_cloud_delete_auto');

    if (fifu_is_valid_nonce('nonce_fifu_form_cloud_hotlink', FIFU_ACTION_CLOUD))
        fifu_update_option('fifu_input_cloud_hotlink', 'fifu_cloud_hotlink');
}

function fifu_license_key() {
    flush();

    $fifu = fifu_get_strings_settings();

    // css and js
    wp_enqueue_style('fifu-base-ui-css', plugins_url('/html/css/base-ui.css', __FILE__), array(), fifu_version_number_enq());
    wp_enqueue_style('fifu-menu-css', plugins_url('/html/css/menu.css', __FILE__), array(), fifu_version_number_enq());
    wp_enqueue_script('fifu-menu-js', plugins_url('/html/js/menu.js', __FILE__), array('jquery', 'jquery-ui'), fifu_version_number_enq());

    // register custom variables for the AJAX script
    wp_localize_script('fifu-menu-js', 'fifuScriptVars', [
        'restUrl' => esc_url_raw(rest_url()),
        'homeUrl' => esc_url_raw(home_url()),
        'nonce' => wp_create_nonce('wp_rest'),
        'wait' => $fifu['php']['message']['wait'](),
        'lock' => get_option('fifu_lock'),
        'emptyKey' => empty(get_option('fifu_key')),
        'expired' => get_option('fifu_expired'),
        'keyText' => $fifu['options']['key'](),
        'networkAdmin' => is_network_admin(),
    ]);

    $license_key = get_option('fifu_key');
    $email = get_option('fifu_email');

    include 'html/license-key.html';

    if (fifu_is_valid_nonce('nonce_fifu_form_key')) {
        // fifu_update_option('fifu_input_key', 'fifu_key');
    }
}

function fifu_troubleshooting() {
    flush();

    $fifu = fifu_get_strings_settings();

    // css and js
    wp_enqueue_style('fifu-base-ui-css', plugins_url('/html/css/base-ui.css', __FILE__), array(), fifu_version_number_enq());
    wp_enqueue_style('fifu-menu-css', plugins_url('/html/css/menu.css', __FILE__), array(), fifu_version_number_enq());
    wp_enqueue_script('fifu-troubleshooting-js', plugins_url('/html/js/troubleshooting.js', __FILE__), array('jquery', 'jquery-ui'), fifu_version_number_enq());

    include 'html/troubleshooting.html';
}

function fifu_support_data() {
    $fifu = fifu_get_strings_settings();

    // css
    wp_enqueue_style('fifu-base-ui-css', plugins_url('/html/css/base-ui.css', __FILE__), array(), fifu_version_number_enq());
    wp_enqueue_style('fifu-menu-css', plugins_url('/html/css/menu.css', __FILE__), array(), fifu_version_number_enq());

    // page-specific JS to hide admin notices, matching other FIFU pages
    wp_enqueue_script('fifu-support-data-js', plugins_url('/html/js/support-data.js', __FILE__), array('jquery', 'jquery-ui'), fifu_version_number_enq());

    wp_enqueue_script('fifu-async', plugins_url('/html/js/async.js', __FILE__), array('jquery'), fifu_version_number_enq());
    wp_localize_script('fifu-async', 'fifuAsyncVars', array(
        'restUrl' => esc_url_raw(rest_url()),
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wp_rest'),
        'server_ok' => $fifu['server']['success'](),
        'server_nok' => $fifu['server']['fail'](),
    ));

    $status_server = get_option('fifu_status_server') ? 'success' : 'fail';
    $enable_block = get_option('fifu_block');
    $enable_popup = get_option('fifu_popup');
    $enable_redirection = get_option('fifu_redirection');
    $enable_auto_set = get_option('fifu_auto_set');
    $max_auto_set_width = get_option('fifu_auto_set_width');
    $max_auto_set_height = get_option('fifu_auto_set_height');
    $auto_set_blocklist = esc_textarea(get_option('fifu_auto_set_blocklist'));
    $auto_set_cpt = esc_attr(get_option('fifu_auto_set_cpt'));
    $auto_set_source = esc_attr(get_option('fifu_auto_set_source'));
    $auto_set_layout = esc_attr(get_option('fifu_auto_set_layout'));
    $tags_orientation = esc_attr(get_option('fifu_tags_orientation'));
    $html_media = esc_attr(get_option('fifu_html_media'));
    $upload_domain = esc_attr(get_option('fifu_upload_domain'));
    $skip = esc_attr(get_option('fifu_skip'));
    $html_cpt = esc_attr(get_option('fifu_html_cpt'));
    $enable_isbn = get_option('fifu_isbn');
    $isbn_custom_field = esc_attr(get_option('fifu_isbn_custom_field'));
    $enable_asin = get_option('fifu_asin');
    $asin_custom_field = esc_attr(get_option('fifu_asin_custom_field'));
    $asin_credentials_partner = esc_attr(get_option('fifu_asin_credentials_partner'));
    $asin_credentials_access = esc_attr(get_option('fifu_asin_credentials_access'));
    $asin_credentials_secret = esc_attr(get_option('fifu_asin_credentials_secret'));
    $asin_credentials_locale = esc_attr(get_option('fifu_asin_credentials_locale'));
    $square_mobile = esc_attr(get_option('fifu_square_mobile'));
    $square_desktop = esc_attr(get_option('fifu_square_desktop'));
    $screenshot_custom_field = esc_attr(get_option('fifu_screenshot_custom_field'));
    $screenshot_size = esc_attr(get_option('fifu_screenshot_size'));
    $enable_customfield = get_option('fifu_customfield');
    $customfield_custom_field = esc_attr(get_option('fifu_customfield_custom_field'));
    $finder_custom_field = esc_attr(get_option('fifu_finder_custom_field'));
    $enable_finder = get_option('fifu_finder');
    $enable_video_finder = get_option('fifu_video_finder');
    $enable_amazon_finder = get_option('fifu_amazon_finder');
    $enable_tags = get_option('fifu_tags');
    $enable_screenshot = get_option('fifu_screenshot');
    $enable_debug = get_option('fifu_debug');
    $enable_audio = get_option('fifu_audio');
    $enable_photon = get_option('fifu_photon');
    $enable_otfcdn = get_option('fifu_otfcdn');
    $enable_own_domain = get_option('fifu_own_domain');
    $enable_cdn_content = get_option('fifu_cdn_content');
    $enable_reset = get_option('fifu_reset');
    $enable_fake = get_option('fifu_fake');
    $enable_order_email = get_option('fifu_order_email');
    $enable_gallery = get_option('fifu_gallery');
    $enable_adaptive_height = get_option('fifu_adaptive_height');
    $enable_videos_before = get_option('fifu_videos_before');
    $enable_variations_merge = get_option('fifu_variations_merge');
    $enable_buy = get_option('fifu_buy');
    $license_key = get_option('fifu_key');
    $email = get_option('fifu_email');
    $error_url = esc_url(get_option('fifu_error_url'));
    $default_url = esc_url(get_option('fifu_default_url'));
    $default_cpt = esc_attr(get_option('fifu_default_cpt'));
    $pcontent_types = esc_attr(get_option('fifu_pcontent_types'));
    $hide_format = esc_attr(get_option('fifu_hide_format'));
    $hide_type = esc_attr(get_option('fifu_hide_type'));
    $enable_default_url = get_option('fifu_enable_default_url');
    $enable_cron_metadata = get_option('fifu_cron_metadata');
    $min_video_width = get_option('fifu_video_min_width');
    $video_color = esc_attr(get_option('fifu_video_color'));
    $video_zindex = get_option('fifu_video_zindex');
    $video_size = get_option('fifu_video_size');
    $enable_slider = get_option('fifu_slider');
    $enable_slider_auto = get_option('fifu_slider_auto');
    $enable_slider_gallery = get_option('fifu_slider_gallery');
    $enable_slider_thumb = get_option('fifu_slider_thumb');
    $enable_slider_counter = get_option('fifu_slider_counter');
    $enable_slider_crop = get_option('fifu_slider_crop');
    $enable_slider_single = get_option('fifu_slider_single');
    $enable_slider_vertical = get_option('fifu_slider_vertical');
    $enable_slider_ctrl = get_option('fifu_slider_ctrl');
    $enable_slider_stop = get_option('fifu_slider_stop');
    $slider_speed = get_option('fifu_slider_speed');
    $slider_pause = get_option('fifu_slider_pause');
    $enable_wc_lbox = get_option('fifu_wc_lbox');
    $enable_wc_zoom = get_option('fifu_wc_zoom');
    $enable_hide = get_option('fifu_hide');
    $enable_pcontent_add = get_option('fifu_pcontent_add');
    $enable_pcontent_remove = get_option('fifu_pcontent_remove');
    $enable_get_first = get_option('fifu_get_first');
    $enable_ovw_first = get_option('fifu_ovw_first');
    $enable_update_all = 'toggleoff';
    $enable_run_delete_all = get_option('fifu_run_delete_all');
    $enable_run_delete_all_time = get_option('fifu_run_delete_all_time');
    $enable_autoplay = get_option('fifu_autoplay');
    $enable_autoplay_front = get_option('fifu_autoplay_front');
    $enable_autoplay_elsewhere = get_option('fifu_autoplay_elsewhere');
    $enable_video_mute = get_option('fifu_video_mute');
    $enable_video_mute_mobile = get_option('fifu_video_mute_mobile');
    $enable_video_background = get_option('fifu_video_background');
    $enable_video_background_single = get_option('fifu_video_background_single');
    $enable_video_privacy = get_option('fifu_video_privacy');
    $enable_video_later = get_option('fifu_video_later');
    $enable_video_later_left = get_option('fifu_video_later_left');
    $enable_loop = get_option('fifu_loop');
    $enable_mouse_video = get_option('fifu_mouse_video');
    $enable_video = get_option('fifu_video');
    $enable_video_thumb = get_option('fifu_video_thumb');
    $enable_video_thumb_page = get_option('fifu_video_thumb_page');
    $enable_video_thumb_post = get_option('fifu_video_thumb_post');
    $enable_video_thumb_cpt = get_option('fifu_video_thumb_cpt');
    $enable_video_play_button = get_option('fifu_video_play_button');
    $enable_video_play_hide_grid = get_option('fifu_video_play_hide_grid');
    $enable_video_play_hide_grid_wc = get_option('fifu_video_play_hide_grid_wc');
    $enable_video_controls = get_option('fifu_video_controls');
    $enable_auto_category = get_option('fifu_auto_category');
    $enable_taxonomy = get_option('fifu_taxonomy');
    $enable_data_clean = 'toggleoff';
    $enable_shortform = get_option('fifu_shortform');
    $play_type_option = get_option('fifu_play_type');
    $enable_upload_show = get_option('fifu_upload_show');
    $enable_upload_proxy = get_option('fifu_upload_proxy');
    $enable_upload_job = get_option('fifu_upload_job');
    $upload_private_proxy = esc_attr(get_option('fifu_upload_private_proxy'));
    $slider_left = esc_url(get_option('fifu_slider_left'));
    $slider_right = esc_url(get_option('fifu_slider_right'));
    $buy_text = get_option('fifu_buy_text');
    $buy_disclaimer = get_option('fifu_buy_disclaimer');
    $buy_cf = get_option('fifu_buy_cf');
    $enable_bbpress_fields = get_option('fifu_bbpress_fields');
    $enable_cloud_upload_auto = get_option('fifu_cloud_upload_auto');
    $enable_cloud_delete_auto = get_option('fifu_cloud_delete_auto');
    $enable_cloud_hotlink = get_option('fifu_cloud_hotlink');

    include 'html/support-data.html';
}

function fifu_get_menu_html() {
    flush();

    $fifu = fifu_get_strings_settings();
    $fifucloud = fifu_get_strings_cloud();

    // css and js
    wp_enqueue_style('fifu-base-ui-css', plugins_url('/html/css/base-ui.css', __FILE__), array(), fifu_version_number_enq());
    wp_enqueue_style('fifu-menu-css', plugins_url('/html/css/menu.css', __FILE__), array(), fifu_version_number_enq());
    wp_enqueue_script('fifu-menu-js', plugins_url('/html/js/menu.js', __FILE__), array('jquery', 'jquery-ui'), fifu_version_number_enq());

    // register custom variables for the AJAX script
    wp_localize_script('fifu-menu-js', 'fifuScriptVars', [
        'restUrl' => esc_url_raw(rest_url()),
        'homeUrl' => esc_url_raw(home_url()),
        'nonce' => wp_create_nonce('wp_rest'),
        'wait' => $fifu['php']['message']['wait'](),
        'wait1' => $fifu['php']['message']['wait1'](),
        'lock' => get_option('fifu_lock'),
        'emptyKey' => empty(get_option('fifu_key')),
        'expired' => get_option('fifu_expired'),
        'keyText' => $fifu['options']['key'](),
        'saving' => $fifu['word']['saving'](),
        'saved' => $fifu['word']['saved'](),
        'error' => $fifu['word']['error'](),
        'reset' => $fifu['word']['reset'](),
        'save' => $fifu['word']['save'](),
        'pluginUrl' => plugins_url() . '/' . FIFU_SLUG,
        'networkAdmin' => is_network_admin(),
    ]);

    $enable_block = get_option('fifu_block');
    $enable_popup = get_option('fifu_popup');
    $enable_redirection = get_option('fifu_redirection');
    $enable_auto_set = get_option('fifu_auto_set');
    $max_auto_set_width = get_option('fifu_auto_set_width');
    $max_auto_set_height = get_option('fifu_auto_set_height');
    $auto_set_blocklist = esc_textarea(get_option('fifu_auto_set_blocklist'));
    $auto_set_cpt = esc_attr(get_option('fifu_auto_set_cpt'));
    $auto_set_source = esc_attr(get_option('fifu_auto_set_source'));
    $auto_set_layout = esc_attr(get_option('fifu_auto_set_layout'));
    $tags_orientation = esc_attr(get_option('fifu_tags_orientation'));
    $html_media = esc_attr(get_option('fifu_html_media'));
    $upload_domain = esc_attr(get_option('fifu_upload_domain'));
    $skip = esc_attr(get_option('fifu_skip'));
    $html_cpt = esc_attr(get_option('fifu_html_cpt'));
    $enable_isbn = get_option('fifu_isbn');
    $isbn_custom_field = esc_attr(get_option('fifu_isbn_custom_field'));
    $enable_asin = get_option('fifu_asin');
    $asin_custom_field = esc_attr(get_option('fifu_asin_custom_field'));
    $asin_credentials_partner = esc_attr(get_option('fifu_asin_credentials_partner'));
    $asin_credentials_access = esc_attr(get_option('fifu_asin_credentials_access'));
    $asin_credentials_secret = esc_attr(get_option('fifu_asin_credentials_secret'));
    $asin_credentials_locale = esc_attr(get_option('fifu_asin_credentials_locale'));
    $square_mobile = esc_attr(get_option('fifu_square_mobile'));
    $square_desktop = esc_attr(get_option('fifu_square_desktop'));
    $screenshot_custom_field = esc_attr(get_option('fifu_screenshot_custom_field'));
    $screenshot_size = esc_attr(get_option('fifu_screenshot_size'));
    $enable_customfield = get_option('fifu_customfield');
    $customfield_custom_field = esc_attr(get_option('fifu_customfield_custom_field'));
    $finder_custom_field = esc_attr(get_option('fifu_finder_custom_field'));
    $enable_finder = get_option('fifu_finder');
    $enable_video_finder = get_option('fifu_video_finder');
    $enable_amazon_finder = get_option('fifu_amazon_finder');
    $enable_tags = get_option('fifu_tags');
    $enable_screenshot = get_option('fifu_screenshot');
    $enable_debug = get_option('fifu_debug');
    $enable_audio = get_option('fifu_audio');
    $enable_photon = get_option('fifu_photon');
    $enable_otfcdn = get_option('fifu_otfcdn');
    $enable_own_domain = get_option('fifu_own_domain');
    $enable_cdn_content = get_option('fifu_cdn_content');
    $enable_reset = get_option('fifu_reset');
    $enable_fake = get_option('fifu_fake');
    $enable_order_email = get_option('fifu_order_email');
    $enable_gallery = get_option('fifu_gallery');
    $enable_adaptive_height = get_option('fifu_adaptive_height');
    $enable_videos_before = get_option('fifu_videos_before');
    $enable_variations_merge = get_option('fifu_variations_merge');
    $enable_buy = get_option('fifu_buy');
    $error_url = esc_url(get_option('fifu_error_url'));
    $default_url = esc_url(get_option('fifu_default_url'));
    $default_cpt = esc_attr(get_option('fifu_default_cpt'));
    $pcontent_types = esc_attr(get_option('fifu_pcontent_types'));
    $hide_format = esc_attr(get_option('fifu_hide_format'));
    $hide_type = esc_attr(get_option('fifu_hide_type'));
    $enable_default_url = get_option('fifu_enable_default_url');
    $enable_cron_metadata = get_option('fifu_cron_metadata');
    $min_video_width = get_option('fifu_video_min_width');
    $video_color = esc_attr(get_option('fifu_video_color'));
    $video_zindex = get_option('fifu_video_zindex');
    $video_size = get_option('fifu_video_size');
    $enable_slider = get_option('fifu_slider');
    $enable_slider_auto = get_option('fifu_slider_auto');
    $enable_slider_gallery = get_option('fifu_slider_gallery');
    $enable_slider_thumb = get_option('fifu_slider_thumb');
    $enable_slider_counter = get_option('fifu_slider_counter');
    $enable_slider_crop = get_option('fifu_slider_crop');
    $enable_slider_single = get_option('fifu_slider_single');
    $enable_slider_vertical = get_option('fifu_slider_vertical');
    $enable_slider_ctrl = get_option('fifu_slider_ctrl');
    $enable_slider_stop = get_option('fifu_slider_stop');
    $slider_speed = get_option('fifu_slider_speed');
    $slider_pause = get_option('fifu_slider_pause');
    $enable_wc_lbox = get_option('fifu_wc_lbox');
    $enable_wc_zoom = get_option('fifu_wc_zoom');
    $enable_hide = get_option('fifu_hide');
    $enable_pcontent_add = get_option('fifu_pcontent_add');
    $enable_pcontent_remove = get_option('fifu_pcontent_remove');
    $enable_get_first = get_option('fifu_get_first');
    $enable_ovw_first = get_option('fifu_ovw_first');
    $enable_update_all = 'toggleoff';
    $enable_run_delete_all = get_option('fifu_run_delete_all');
    $enable_run_delete_all_time = get_option('fifu_run_delete_all_time');
    $enable_autoplay = get_option('fifu_autoplay');
    $enable_autoplay_front = get_option('fifu_autoplay_front');
    $enable_autoplay_elsewhere = get_option('fifu_autoplay_elsewhere');
    $enable_video_mute = get_option('fifu_video_mute');
    $enable_video_mute_mobile = get_option('fifu_video_mute_mobile');
    $enable_video_background = get_option('fifu_video_background');
    $enable_video_background_single = get_option('fifu_video_background_single');
    $enable_video_privacy = get_option('fifu_video_privacy');
    $enable_video_later = get_option('fifu_video_later');
    $enable_video_later_left = get_option('fifu_video_later_left');
    $enable_loop = get_option('fifu_loop');
    $enable_mouse_video = get_option('fifu_mouse_video');
    $enable_video = get_option('fifu_video');
    $enable_video_thumb = get_option('fifu_video_thumb');
    $enable_video_thumb_page = get_option('fifu_video_thumb_page');
    $enable_video_thumb_post = get_option('fifu_video_thumb_post');
    $enable_video_thumb_cpt = get_option('fifu_video_thumb_cpt');
    $enable_video_play_button = get_option('fifu_video_play_button');
    $enable_video_play_hide_grid = get_option('fifu_video_play_hide_grid');
    $enable_video_play_hide_grid_wc = get_option('fifu_video_play_hide_grid_wc');
    $enable_video_controls = get_option('fifu_video_controls');
    $enable_auto_category = get_option('fifu_auto_category');
    $enable_taxonomy = get_option('fifu_taxonomy');
    $enable_data_clean = 'toggleoff';
    $enable_shortform = get_option('fifu_shortform');
    $play_type_option = get_option('fifu_play_type');
    $enable_upload_show = get_option('fifu_upload_show');
    $enable_upload_proxy = get_option('fifu_upload_proxy');
    $enable_upload_job = get_option('fifu_upload_job');
    $upload_private_proxy = esc_attr(get_option('fifu_upload_private_proxy'));
    $slider_left = esc_url(get_option('fifu_slider_left'));
    $slider_right = esc_url(get_option('fifu_slider_right'));
    $buy_text = get_option('fifu_buy_text');
    $buy_disclaimer = get_option('fifu_buy_disclaimer');
    $buy_cf = get_option('fifu_buy_cf');
    $enable_bbpress_fields = get_option('fifu_bbpress_fields');

    include 'html/menu.html';

    $arr = fifu_update_menu_options();

    // category
    if (fifu_is_on('fifu_auto_category')) {
        if (!get_option('fifu_auto_category_created')) {
            fifu_db_insert_auto_category_image();
            update_option('fifu_auto_category_created', true, 'no');
        }
    } else
        update_option('fifu_auto_category_created', false, 'no');

    // default
    if (!$arr['fifu_default_cpt']) { # submit via post type form
        $default_url = $arr['fifu_default_url']; # submit via default url form
        if (!empty($default_url) && fifu_is_on('fifu_enable_default_url') && fifu_is_on('fifu_fake')) {
            if (!wp_get_attachment_url(get_option('fifu_default_attach_id'))) {
                $att_id = fifu_db_create_attachment($default_url);
                update_option('fifu_default_attach_id', $att_id);
                fifu_db_set_default_url();
            } else
                fifu_db_update_default_url($default_url);
        }
    }

    // reset
    if (fifu_is_on('fifu_reset')) {
        fifu_reset_settings();
        update_option('fifu_reset', 'toggleoff', 'no');
    }
}

function fifu_get_menu_settings() {
    foreach (unserialize(FIFU_SETTINGS) as $i)
        fifu_get_setting($i);
}

function fifu_reset_settings() {
    foreach (unserialize(FIFU_SETTINGS) as $i) {
        if ($i != 'fifu_key' &&
                $i != 'fifu_email' &&
                $i != 'fifu_default_url' &&
                $i != 'fifu_enable_default_url')
            delete_option($i);
    }
}

function fifu_get_setting($type) {
    register_setting('settings-group', $type);

    $arrPlayType = array('fifu_play_type');
    $arr0 = array('fifu_auto_set_width', 'fifu_auto_set_height');
    $arrEmpty = array('fifu_default_url', 'fifu_upload_private_proxy', 'fifu_slider_left', 'fifu_slider_right', 'fifu_buy_text', 'fifu_buy_disclaimer', 'fifu_buy_cf', 'fifu_isbn_custom_field', 'fifu_asin_custom_field', 'fifu_asin_credentials_partner', 'fifu_asin_credentials_access', 'fifu_asin_credentials_secret', 'fifu_asin_credentials_locale', 'fifu_square_mobile', 'fifu_square_desktop', 'fifu_screenshot_custom_field', 'fifu_customfield_custom_field', 'fifu_finder_custom_field', 'fifu_auto_set_source', 'fifu_auto_set_layout', 'fifu_tags_orientation', 'fifu_upload_domain', 'fifu_skip', 'fifu_html_cpt', 'fifu_hide_format', 'fifu_hide_type', 'fifu_pcontent_types');
    $arrEmptyNo = array('fifu_error_url', 'fifu_key', 'fifu_email', 'fifu_auto_set_blocklist');
    $arrDefaultType = array('fifu_default_cpt');
    $arr50 = array('fifu_video_size');
    $arr100 = array('fifu_video_min_width');
    $arr1000 = array('fifu_slider_speed', 'fifu_video_zindex');
    $arr2000 = array('fifu_slider_pause');
    $arr1280x960 = array('fifu_screenshot_size');
    $arrRed = array('fifu_video_color');
    $arrPost = array('fifu_auto_set_cpt');
    $arrImage = array('fifu_html_media');
    $arrOn = array('fifu_wc_zoom', 'fifu_wc_lbox');
    $arrOnNo = array('fifu_fake', 'fifu_video_play_button', 'fifu_video_thumb', 'fifu_video_thumb_post', 'fifu_video_thumb_page', 'fifu_video_thumb_cpt', 'fifu_video_controls', 'fifu_slider_crop', 'fifu_adaptive_height', 'fifu_gallery', 'fifu_slider_thumb');
    $arrOffNo = array('fifu_auto_category_created', 'fifu_data_clean', 'fifu_update_all', 'fifu_run_delete_all', 'fifu_reset', 'fifu_enable_cron_metadata');

    if (get_option($type) === false) {
        if (in_array($type, $arrPlayType))
            update_option($type, 'inline', 'no');
        else if (in_array($type, $arr0))
            update_option($type, 0);
        else if (in_array($type, $arrEmpty))
            update_option($type, '');
        else if (in_array($type, $arrEmptyNo))
            update_option($type, '', 'no');
        else if (in_array($type, $arrDefaultType))
            update_option($type, "post,page,product", 'no');
        else if (in_array($type, $arr50))
            update_option($type, 50);
        else if (in_array($type, $arr100))
            update_option($type, 100, 'no');
        else if (in_array($type, $arr1000))
            update_option($type, 1000);
        else if (in_array($type, $arr2000))
            update_option($type, 2000);
        else if (in_array($type, $arr1280x960))
            update_option($type, '1280x960');
        else if (in_array($type, $arrRed))
            update_option($type, 'red', 'no');
        else if (in_array($type, $arrPost))
            update_option($type, 'post', 'no');
        else if (in_array($type, $arrImage))
            update_option($type, 'image', 'no');
        else if (in_array($type, $arrOn))
            update_option($type, 'toggleon');
        else if (in_array($type, $arrOnNo))
            update_option($type, 'toggleon', 'no');
        else if (in_array($type, $arrOffNo))
            update_option($type, 'toggleoff', 'no');
        else
            update_option($type, 'toggleoff');
    }
}

function fifu_update_menu_options() {
    if (fifu_is_valid_nonce('nonce_fifu_form_block'))
        fifu_update_option('fifu_input_block', 'fifu_block');

    if (fifu_is_valid_nonce('nonce_fifu_form_popup'))
        fifu_update_option('fifu_input_popup', 'fifu_popup');

    if (fifu_is_valid_nonce('nonce_fifu_form_redirection'))
        fifu_update_option('fifu_input_redirection', 'fifu_redirection');

    if (fifu_is_valid_nonce('nonce_fifu_form_auto_set'))
        fifu_update_option('fifu_input_auto_set', 'fifu_auto_set');

    if (fifu_is_valid_nonce('nonce_fifu_form_auto_set_dimensions')) {
        fifu_update_option('fifu_input_auto_set_width', 'fifu_auto_set_width');
        fifu_update_option('fifu_input_auto_set_height', 'fifu_auto_set_height');
    }

    if (fifu_is_valid_nonce('nonce_fifu_form_auto_set_blocklist'))
        fifu_update_option('fifu_input_auto_set_blocklist', 'fifu_auto_set_blocklist');

    if (fifu_is_valid_nonce('nonce_fifu_form_auto_set_cpt'))
        fifu_update_option('fifu_input_auto_set_cpt', 'fifu_auto_set_cpt');

    if (fifu_is_valid_nonce('nonce_fifu_form_auto_set_source'))
        fifu_update_option('fifu_input_auto_set_source', 'fifu_auto_set_source');

    if (fifu_is_valid_nonce('nonce_fifu_form_auto_set_layout'))
        fifu_update_option('fifu_input_auto_set_layout', 'fifu_auto_set_layout');

    if (fifu_is_valid_nonce('nonce_fifu_form_tags_orientation'))
        fifu_update_option('fifu_input_tags_orientation', 'fifu_tags_orientation');

    if (fifu_is_valid_nonce('nonce_fifu_form_html_media'))
        fifu_update_option('fifu_input_html_media', 'fifu_html_media');

    if (fifu_is_valid_nonce('nonce_fifu_form_upload_domain'))
        fifu_update_option('fifu_input_upload_domain', 'fifu_upload_domain');

    if (fifu_is_valid_nonce('nonce_fifu_form_skip'))
        fifu_update_option('fifu_input_skip', 'fifu_skip');

    if (fifu_is_valid_nonce('nonce_fifu_form_html_cpt'))
        fifu_update_option('fifu_input_html_cpt', 'fifu_html_cpt');

    if (fifu_is_valid_nonce('nonce_fifu_form_isbn'))
        fifu_update_option('fifu_input_isbn', 'fifu_isbn');

    if (fifu_is_valid_nonce('nonce_fifu_form_isbn_custom_field'))
        fifu_update_option('fifu_input_isbn_custom_field', 'fifu_isbn_custom_field');

    if (fifu_is_valid_nonce('nonce_fifu_form_asin'))
        fifu_update_option('fifu_input_asin', 'fifu_asin');

    if (fifu_is_valid_nonce('nonce_fifu_form_asin_custom_field'))
        fifu_update_option('fifu_input_asin_custom_field', 'fifu_asin_custom_field');

    if (fifu_is_valid_nonce('nonce_fifu_form_asin_credentials')) {
        fifu_update_option('fifu_input_asin_credentials_partner', 'fifu_asin_credentials_partner');
        fifu_update_option('fifu_input_asin_credentials_access', 'fifu_asin_credentials_access');
        fifu_update_option('fifu_input_asin_credentials_secret', 'fifu_asin_credentials_secret');
        fifu_update_option('fifu_input_asin_credentials_locale', 'fifu_asin_credentials_locale');
    }

    if (fifu_is_valid_nonce('nonce_fifu_form_square')) {
        fifu_update_option('fifu_input_square_mobile', 'fifu_square_mobile');
        fifu_update_option('fifu_input_square_desktop', 'fifu_square_desktop');
    }

    if (fifu_is_valid_nonce('nonce_fifu_form_screenshot_custom_field'))
        fifu_update_option('fifu_input_screenshot_custom_field', 'fifu_screenshot_custom_field');

    if (fifu_is_valid_nonce('nonce_fifu_form_screenshot_size'))
        fifu_update_option('fifu_input_screenshot_size', 'fifu_screenshot_size');

    if (fifu_is_valid_nonce('nonce_fifu_form_customfield'))
        fifu_update_option('fifu_input_customfield', 'fifu_customfield');

    if (fifu_is_valid_nonce('nonce_fifu_form_customfield_custom_field'))
        fifu_update_option('fifu_input_customfield_custom_field', 'fifu_customfield_custom_field');

    if (fifu_is_valid_nonce('nonce_fifu_form_finder_custom_field'))
        fifu_update_option('fifu_input_finder_custom_field', 'fifu_finder_custom_field');

    if (fifu_is_valid_nonce('nonce_fifu_form_finder'))
        fifu_update_option('fifu_input_finder', 'fifu_finder');

    if (fifu_is_valid_nonce('nonce_fifu_form_video_finder'))
        fifu_update_option('fifu_input_video_finder', 'fifu_video_finder');

    if (fifu_is_valid_nonce('nonce_fifu_form_amazon_finder'))
        fifu_update_option('fifu_input_amazon_finder', 'fifu_amazon_finder');

    if (fifu_is_valid_nonce('nonce_fifu_form_tags'))
        fifu_update_option('fifu_input_tags', 'fifu_tags');

    if (fifu_is_valid_nonce('nonce_fifu_form_screenshot'))
        fifu_update_option('fifu_input_screenshot', 'fifu_screenshot');

    if (fifu_is_valid_nonce('nonce_fifu_form_debug'))
        fifu_update_option('fifu_input_debug', 'fifu_debug');

    if (fifu_is_valid_nonce('nonce_fifu_form_audio'))
        fifu_update_option('fifu_input_audio', 'fifu_audio');

    if (fifu_is_valid_nonce('nonce_fifu_form_photon'))
        fifu_update_option('fifu_input_photon', 'fifu_photon');

    if (fifu_is_valid_nonce('nonce_fifu_form_otfcdn'))
        fifu_update_option('fifu_input_otfcdn', 'fifu_otfcdn');

    if (fifu_is_valid_nonce('nonce_fifu_form_own_domain'))
        fifu_update_option('fifu_input_own_domain', 'fifu_own_domain');

    if (fifu_is_valid_nonce('nonce_fifu_form_cdn_content'))
        fifu_update_option('fifu_input_cdn_content', 'fifu_cdn_content');

    if (fifu_is_valid_nonce('nonce_fifu_form_reset'))
        fifu_update_option('fifu_input_reset', 'fifu_reset');

    if (fifu_is_valid_nonce('nonce_fifu_form_fake'))
        fifu_update_option('fifu_input_fake', 'fifu_fake');

    if (fifu_is_valid_nonce('nonce_fifu_form_order_email'))
        fifu_update_option('fifu_input_order_email', 'fifu_order_email');

    if (fifu_is_valid_nonce('nonce_fifu_form_gallery'))
        fifu_update_option('fifu_input_gallery', 'fifu_gallery');

    if (fifu_is_valid_nonce('nonce_fifu_form_adaptive_height'))
        fifu_update_option('fifu_input_adaptive_height', 'fifu_adaptive_height');

    if (fifu_is_valid_nonce('nonce_fifu_form_videos_before'))
        fifu_update_option('fifu_input_videos_before', 'fifu_videos_before');

    if (fifu_is_valid_nonce('nonce_fifu_form_variations_merge'))
        fifu_update_option('fifu_input_variations_merge', 'fifu_variations_merge');

    if (fifu_is_valid_nonce('nonce_fifu_form_buy'))
        fifu_update_option('fifu_input_buy', 'fifu_buy');

    if (fifu_is_valid_nonce('nonce_fifu_form_buy_text')) {
        fifu_update_option('fifu_input_buy_text', 'fifu_buy_text');
        fifu_update_option('fifu_input_buy_disclaimer', 'fifu_buy_disclaimer');
        fifu_update_option('fifu_input_buy_cf', 'fifu_buy_cf');
    }

    if (fifu_is_valid_nonce('nonce_fifu_form_error_url'))
        fifu_update_option('fifu_input_error_url', 'fifu_error_url');

    if (fifu_is_valid_nonce('nonce_fifu_form_default_url'))
        fifu_update_option('fifu_input_default_url', 'fifu_default_url');

    if (fifu_is_valid_nonce('nonce_fifu_form_default_cpt'))
        fifu_update_option('fifu_input_default_cpt', 'fifu_default_cpt');

    if (fifu_is_valid_nonce('nonce_fifu_form_pcontent_types'))
        fifu_update_option('fifu_input_pcontent_types', 'fifu_pcontent_types');

    if (fifu_is_valid_nonce('nonce_fifu_form_hide_format'))
        fifu_update_option('fifu_input_hide_format', 'fifu_hide_format');

    if (fifu_is_valid_nonce('nonce_fifu_form_hide_type'))
        fifu_update_option('fifu_input_hide_type', 'fifu_hide_type');

    if (fifu_is_valid_nonce('nonce_fifu_form_enable_default_url'))
        fifu_update_option('fifu_input_enable_default_url', 'fifu_enable_default_url');

    if (fifu_is_valid_nonce('nonce_fifu_form_cron_metadata'))
        fifu_update_option('fifu_input_cron_metadata', 'fifu_cron_metadata');

    if (fifu_is_valid_nonce('nonce_fifu_form_spinner')) {
        fifu_update_option('fifu_input_slider_pause', 'fifu_slider_pause');
        fifu_update_option('fifu_input_slider_speed', 'fifu_slider_speed');
        fifu_update_option('fifu_input_slider_left', 'fifu_slider_left');
        fifu_update_option('fifu_input_slider_right', 'fifu_slider_right');
    }

    if (fifu_is_valid_nonce('nonce_fifu_form_video_min_width'))
        fifu_update_option('fifu_input_video_min_width', 'fifu_video_min_width');

    if (fifu_is_valid_nonce('nonce_fifu_form_video_color'))
        fifu_update_option('fifu_input_video_color', 'fifu_video_color');

    if (fifu_is_valid_nonce('nonce_fifu_form_video_zindex'))
        fifu_update_option('fifu_input_video_zindex', 'fifu_video_zindex');

    if (fifu_is_valid_nonce('nonce_fifu_form_video_size'))
        fifu_update_option('fifu_input_video_size', 'fifu_video_size');

    if (fifu_is_valid_nonce('nonce_fifu_form_slider'))
        fifu_update_option('fifu_input_slider', 'fifu_slider');

    if (fifu_is_valid_nonce('nonce_fifu_form_slider_auto'))
        fifu_update_option('fifu_input_slider_auto', 'fifu_slider_auto');

    if (fifu_is_valid_nonce('nonce_fifu_form_slider_gallery'))
        fifu_update_option('fifu_input_slider_gallery', 'fifu_slider_gallery');

    if (fifu_is_valid_nonce('nonce_fifu_form_slider_thumb'))
        fifu_update_option('fifu_input_slider_thumb', 'fifu_slider_thumb');

    if (fifu_is_valid_nonce('nonce_fifu_form_slider_counter'))
        fifu_update_option('fifu_input_slider_counter', 'fifu_slider_counter');

    if (fifu_is_valid_nonce('nonce_fifu_form_slider_crop'))
        fifu_update_option('fifu_input_slider_crop', 'fifu_slider_crop');

    if (fifu_is_valid_nonce('nonce_fifu_form_slider_single'))
        fifu_update_option('fifu_input_slider_single', 'fifu_slider_single');

    if (fifu_is_valid_nonce('nonce_fifu_form_slider_vertical'))
        fifu_update_option('fifu_input_slider_vertical', 'fifu_slider_vertical');

    if (fifu_is_valid_nonce('nonce_fifu_form_slider_ctrl'))
        fifu_update_option('fifu_input_slider_ctrl', 'fifu_slider_ctrl');

    if (fifu_is_valid_nonce('nonce_fifu_form_slider_stop'))
        fifu_update_option('fifu_input_slider_stop', 'fifu_slider_stop');

    if (fifu_is_valid_nonce('nonce_fifu_form_wc_lbox'))
        fifu_update_option('fifu_input_wc_lbox', 'fifu_wc_lbox');

    if (fifu_is_valid_nonce('nonce_fifu_form_wc_zoom'))
        fifu_update_option('fifu_input_wc_zoom', 'fifu_wc_zoom');

    if (fifu_is_valid_nonce('nonce_fifu_form_hide'))
        fifu_update_option('fifu_input_hide', 'fifu_hide');

    if (fifu_is_valid_nonce('nonce_fifu_form_pcontent_add'))
        fifu_update_option('fifu_input_pcontent_add', 'fifu_pcontent_add');

    if (fifu_is_valid_nonce('nonce_fifu_form_pcontent_remove'))
        fifu_update_option('fifu_input_pcontent_remove', 'fifu_pcontent_remove');

    if (fifu_is_valid_nonce('nonce_fifu_form_get_first'))
        fifu_update_option('fifu_input_get_first', 'fifu_get_first');

    if (fifu_is_valid_nonce('nonce_fifu_form_ovw_first'))
        fifu_update_option('fifu_input_ovw_first', 'fifu_ovw_first');

    if (fifu_is_valid_nonce('nonce_fifu_form_update_all'))
        fifu_update_option('fifu_input_update_all', 'fifu_update_all');

    if (fifu_is_valid_nonce('nonce_fifu_form_run_delete_all'))
        fifu_update_option('fifu_input_run_delete_all', 'fifu_run_delete_all');

    if (fifu_is_valid_nonce('nonce_fifu_form_autoplay'))
        fifu_update_option('fifu_input_autoplay', 'fifu_autoplay');

    if (fifu_is_valid_nonce('nonce_fifu_form_autoplay_front'))
        fifu_update_option('fifu_input_autoplay_front', 'fifu_autoplay_front');

    if (fifu_is_valid_nonce('nonce_fifu_form_autoplay_elsewhere'))
        fifu_update_option('fifu_input_autoplay_elsewhere', 'fifu_autoplay_elsewhere');

    if (fifu_is_valid_nonce('nonce_fifu_form_video_mute'))
        fifu_update_option('fifu_input_video_mute', 'fifu_video_mute');

    if (fifu_is_valid_nonce('nonce_fifu_form_video_mute_mobile'))
        fifu_update_option('fifu_input_video_mute_mobile', 'fifu_video_mute_mobile');

    if (fifu_is_valid_nonce('nonce_fifu_form_video_background'))
        fifu_update_option('fifu_input_video_background', 'fifu_video_background');

    if (fifu_is_valid_nonce('nonce_fifu_form_video_background_single'))
        fifu_update_option('fifu_input_video_background_single', 'fifu_video_background_single');

    if (fifu_is_valid_nonce('nonce_fifu_form_video_privacy'))
        fifu_update_option('fifu_input_video_privacy', 'fifu_video_privacy');

    if (fifu_is_valid_nonce('nonce_fifu_form_video_later'))
        fifu_update_option('fifu_input_video_later', 'fifu_video_later');

    if (fifu_is_valid_nonce('nonce_fifu_form_video_later_left'))
        fifu_update_option('fifu_input_video_later_left', 'fifu_video_later_left');

    if (fifu_is_valid_nonce('nonce_fifu_form_loop'))
        fifu_update_option('fifu_input_loop', 'fifu_loop');

    if (fifu_is_valid_nonce('nonce_fifu_form_mouse_video'))
        fifu_update_option('fifu_input_mouse_video', 'fifu_mouse_video');

    if (fifu_is_valid_nonce('nonce_fifu_form_video'))
        fifu_update_option('fifu_input_video', 'fifu_video');

    if (fifu_is_valid_nonce('nonce_fifu_form_video_thumb'))
        fifu_update_option('fifu_input_video_thumb', 'fifu_video_thumb');

    if (fifu_is_valid_nonce('nonce_fifu_form_video_thumb_page'))
        fifu_update_option('fifu_input_video_thumb_page', 'fifu_video_thumb_page');

    if (fifu_is_valid_nonce('nonce_fifu_form_video_thumb_post'))
        fifu_update_option('fifu_input_video_thumb_post', 'fifu_video_thumb_post');

    if (fifu_is_valid_nonce('nonce_fifu_form_video_thumb_cpt'))
        fifu_update_option('fifu_input_video_thumb_cpt', 'fifu_video_thumb_cpt');

    if (fifu_is_valid_nonce('nonce_fifu_form_video_play_button'))
        fifu_update_option('fifu_input_video_play_button', 'fifu_video_play_button');

    if (fifu_is_valid_nonce('nonce_fifu_form_video_play_hide_grid'))
        fifu_update_option('fifu_input_video_play_hide_grid', 'fifu_video_play_hide_grid');

    if (fifu_is_valid_nonce('nonce_fifu_form_video_play_hide_grid_wc'))
        fifu_update_option('fifu_input_video_play_hide_grid_wc', 'fifu_video_play_hide_grid_wc');

    if (fifu_is_valid_nonce('nonce_fifu_form_video_controls'))
        fifu_update_option('fifu_input_video_controls', 'fifu_video_controls');

    if (fifu_is_valid_nonce('nonce_fifu_form_auto_category'))
        fifu_update_option('fifu_input_auto_category', 'fifu_auto_category');

    if (fifu_is_valid_nonce('nonce_fifu_form_taxonomy'))
        fifu_update_option('fifu_input_taxonomy', 'fifu_taxonomy');

    if (fifu_is_valid_nonce('nonce_fifu_form_data_clean'))
        fifu_update_option('fifu_input_data_clean', 'fifu_data_clean');

    if (fifu_is_valid_nonce('nonce_fifu_form_shortform'))
        fifu_update_option('fifu_input_shortform', 'fifu_shortform');

    if (fifu_is_valid_nonce('nonce_fifu_form_play_type'))
        fifu_update_option('fifu_input_play_type', 'fifu_play_type');

    if (fifu_is_valid_nonce('nonce_fifu_form_upload_show'))
        fifu_update_option('fifu_input_upload_show', 'fifu_upload_show');

    if (fifu_is_valid_nonce('nonce_fifu_form_upload_proxy'))
        fifu_update_option('fifu_input_upload_proxy', 'fifu_upload_proxy');

    if (fifu_is_valid_nonce('nonce_fifu_form_upload_job'))
        fifu_update_option('fifu_input_upload_job', 'fifu_upload_job');

    if (fifu_is_valid_nonce('nonce_fifu_form_upload_private_proxy'))
        fifu_update_option('fifu_input_upload_private_proxy', 'fifu_upload_private_proxy');

    if (fifu_is_valid_nonce('nonce_fifu_form_bbpress_fields'))
        fifu_update_option('fifu_input_bbpress_fields', 'fifu_bbpress_fields');

    // delete all run log
    if (fifu_is_on('fifu_run_delete_all'))
        update_option('fifu_run_delete_all_time', current_time('mysql'), 'no');

    // urgent updates
    $arr = array();
    if (isset($_POST['fifu_input_default_url'])) {
        $arr['fifu_default_url'] = wp_strip_all_tags($_POST['fifu_input_default_url']);
    } else {
        $default_url = get_option('fifu_default_url');
        $arr['fifu_default_url'] = $default_url ? $default_url : '';
    }

    if (isset($_POST['fifu_input_default_cpt'])) {
        $arr['fifu_default_cpt'] = wp_strip_all_tags($_POST['fifu_input_default_cpt']);
    } else
        $arr['fifu_default_cpt'] = null;

    if (isset($_POST['fifu_input_pcontent_types'])) {
        $arr['fifu_pcontent_types'] = wp_strip_all_tags($_POST['fifu_input_pcontent_types']);
    } else
        $arr['fifu_pcontent_types'] = null;

    if (isset($_POST['fifu_input_hide_format'])) {
        $arr['fifu_hide_format'] = wp_strip_all_tags($_POST['fifu_input_hide_format']);
    } else
        $arr['fifu_hide_format'] = null;

    if (isset($_POST['fifu_input_hide_type'])) {
        $arr['fifu_hide_type'] = wp_strip_all_tags($_POST['fifu_input_hide_type']);
    } else
        $arr['fifu_hide_type'] = null;

    return $arr;
}

function fifu_update_option($input, $field) {
    if (!isset($_POST[$input]))
        return;

    $value = $_POST[$input] ?? '';

    $arr_boolean = array('fifu_adaptive_height', 'fifu_videos_before', 'fifu_variations_merge', 'fifu_taxonomy', 'fifu_auto_category', 'fifu_auto_set', 'fifu_autoplay', 'fifu_autoplay_front', 'fifu_autoplay_elsewhere', 'fifu_bbpress_fields', 'fifu_block', 'fifu_buy', 'fifu_cdn_content', 'fifu_cron_metadata', 'fifu_data_clean', 'fifu_enable_default_url', 'fifu_fake', 'fifu_finder', 'fifu_gallery', 'fifu_get_first', 'fifu_hide', 'fifu_pcontent_add', 'fifu_pcontent_remove', 'fifu_isbn', 'fifu_asin', 'fifu_customfield', 'fifu_debug', 'fifu_audio', 'fifu_loop', 'fifu_mouse_video', 'fifu_ovw_first', 'fifu_photon', 'fifu_otfcdn', 'fifu_own_domain', 'fifu_popup', 'fifu_redirection', 'fifu_reset', 'fifu_run_delete_all', 'fifu_shortform', 'fifu_slider', 'fifu_slider_auto', 'fifu_slider_counter', 'fifu_slider_crop', 'fifu_slider_single', 'fifu_slider_ctrl', 'fifu_slider_gallery', 'fifu_slider_stop', 'fifu_slider_thumb', 'fifu_slider_vertical', 'fifu_tags', 'fifu_screenshot', 'fifu_update_all', 'fifu_upload_job', 'fifu_upload_proxy', 'fifu_upload_show', 'fifu_order_email', 'fifu_video', 'fifu_video_background', 'fifu_video_background_single', 'fifu_video_privacy', 'fifu_video_later', 'fifu_video_later_left', 'fifu_video_controls', 'fifu_video_finder', 'fifu_amazon_finder', 'fifu_video_mute', 'fifu_video_mute_mobile', 'fifu_video_play_button', 'fifu_video_play_hide_grid', 'fifu_video_play_hide_grid_wc', 'fifu_video_thumb', 'fifu_video_thumb_cpt', 'fifu_video_thumb_page', 'fifu_video_thumb_post', 'fifu_wc_lbox', 'fifu_wc_zoom', 'fifu_cloud_upload_auto', 'fifu_cloud_delete_auto', 'fifu_cloud_hotlink');
    if (in_array($field, $arr_boolean)) {
        if (in_array($value, array('on', 'off')))
            update_option($field, 'toggle' . $value);
        return;
    }

    $arr_int = array('fifu_auto_set_height', 'fifu_auto_set_width', 'fifu_slider_pause', 'fifu_slider_speed', 'fifu_video_min_width', 'fifu_video_zindex', 'fifu_video_size');
    if (in_array($field, $arr_int)) {
        if (filter_var($value, FILTER_VALIDATE_INT))
            update_option($field, $value);
        return;
    }

    $arr_hex = array('fifu_key');
    if (in_array($field, $arr_hex)) {
        if (filter_var(trim($value), FILTER_VALIDATE_REGEXP, array("options" => array("regexp" => "/^[a-z0-9-]+$/"))))
            update_option($field, trim($value));
        return;
    }

    $arr_hex = array('fifu_screenshot_size');
    if (in_array($field, $arr_hex)) {
        if (filter_var(trim($value), FILTER_VALIDATE_REGEXP, array("options" => array("regexp" => "/^\d{1,4}x\d{1,4}$/"))))
            update_option($field, trim($value));
        return;
    }

    $arr_email = array('fifu_email');
    if (in_array($field, $arr_email)) {
        if (filter_var($value, FILTER_VALIDATE_EMAIL))
            update_option($field, $value);
        return;
    }

    $arr_play_type = array('fifu_play_type');
    if (in_array($field, $arr_play_type)) {
        if (in_array($value, array('inline', 'lightbox')))
            update_option($field, $value);
        return;
    }

    $arr_square_type = array('fifu_square_mobile', 'fifu_square_desktop');
    if (in_array($field, $arr_square_type)) {
        if (in_array($value, array('', 'crop', 'extend')))
            update_option($field, $value);
        return;
    }

    $arr_url = array('fifu_default_url', 'fifu_error_url', 'fifu_slider_left', 'fifu_slider_right');
    if (in_array($field, $arr_url)) {
        if (empty($value) || filter_var($value, FILTER_VALIDATE_URL))
            update_option($field, esc_url_raw($value));
        return;
    }

    $arr_textarea = array('fifu_auto_set_blocklist');
    if (in_array($field, $arr_textarea)) {
        update_option($field, sanitize_textarea_field($value));
        return;
    }

    $arr_text = array('fifu_auto_set_cpt', 'fifu_auto_set_source', 'fifu_auto_set_layout', 'fifu_tags_orientation', 'fifu_html_media', 'fifu_upload_domain', 'fifu_default_cpt', 'fifu_pcontent_types', 'fifu_hide_format', 'fifu_hide_type', 'fifu_finder_custom_field', 'fifu_isbn_custom_field', 'fifu_asin_custom_field', 'fifu_asin_credentials_partner', 'fifu_asin_credentials_access', 'fifu_asin_credentials_secret', 'fifu_asin_credentials_locale', 'fifu_screenshot_custom_field', 'fifu_customfield_custom_field', 'fifu_skip', 'fifu_html_cpt', 'fifu_upload_private_proxy', 'fifu_video_color', 'fifu_buy_text', 'fifu_buy_disclaimer', 'fifu_buy_cf');
    if (in_array($field, $arr_text))
        update_option($field, sanitize_text_field($value));
}

function fifu_enable_fake() {
    fifu_db_clear_meta_out();
    fifu_create_generic_hook('metain');
}

function fifu_disable_fake() {
    fifu_create_generic_hook('metaout');
}

function fifu_version() {
    $plugin_data = get_plugin_data(FIFU_PLUGIN_DIR . 'fifu-premium.php');
    $name = $plugin_data['Name'] ?? '';
    $version = $plugin_data['Version'] ?? '';
    return $plugin_data && $name && $version ? $name . ':' . $version : '';
}

function fifu_version_number() {
    $plugin_data = get_plugin_data(FIFU_PLUGIN_DIR . 'fifu-premium.php');
    return $plugin_data['Version'] ?? '';
}

function fifu_version_number_enq() {
    if (fifu_is_on('fifu_debug'))
        return mt_rand();
    return fifu_version_number();
}

function fifu_su_sign_up_complete() {
    return isset(get_option('fifu_su_privkey')[0]) ? true : false;
}

function fifu_su_get_email() {
    $su_email_option = get_option('fifu_su_email');
    return base64_decode($su_email_option[0] ?? '');
}

function fifu_get_last($meta_key) {
    $list = '';
    foreach (fifu_db_get_last($meta_key) as $key => $row) {
        $aux = $row->meta_value . ' &#10; → ' . get_permalink($row->id);
        $list .= '&#10; - ' . $aux;
    }
    return $list;
}

function fifu_get_plugins_list() {
    $list = '';
    foreach (get_plugins() as $key => $domain) {
        $name = $domain['Name'] . ' (' . $domain['TextDomain'] . ')';
        $list .= '&#10; - ' . $name;
    }
    return $list;
}

function fifu_get_active_plugins_list() {
    $list = '';
    $active_plugins = get_option('active_plugins', []);
    $all_plugins = get_plugins();

    foreach ($active_plugins as $basename) {
        if (isset($all_plugins[$basename])) {
            $data = $all_plugins[$basename];
            $name = $data['Name'] ?? $basename;
            $text_domain = $data['TextDomain'] ?? '';
            $author = isset($data['Author']) ? wp_strip_all_tags($data['Author']) : '';

            $display = $name;
            if ($text_domain !== '') {
                $display .= ' (' . $text_domain . ')';
            }
            if ($author !== '') {
                $display .= ': ' . $author;
            }
        } else {
            // Fallback to directory name if metadata is missing
            $parts = explode('/', $basename);
            $display = $parts[0] ?? $basename;
        }

        $list .= '&#10; - ' . $display;
    }
    return $list;
}

function fifu_get_registered_sizes() {
    $raw_sizes = fifu_db_select_option_prefix('fifu_detected_size_');
    $formatted_list = '';

    if ($raw_sizes && is_array($raw_sizes)) {
        foreach ($raw_sizes as $size) {
            // Extract the name by removing the prefix
            $name = str_replace('fifu_detected_size_', '', $size->option_name);

            // Unserialize the value to get width, height and crop
            $data = maybe_unserialize($size->option_value);

            if (is_array($data) && isset($data['w']) && isset($data['h']) && isset($data['c'])) {
                $crop_value = $data['c'] ? '1' : '0';
                $formatted_list .= '&#10; - ' . $name . ': ' . $data['w'] . 'x' . $data['h'] . 'x' . $crop_value;
            }
        }
    }

    return $formatted_list ?: '&#10; - No registered sizes found';
}

function fifu_check_update_url($key, $network = false) {
    update_option('fifu_key', trim($key));
    if (is_multisite() && $network) {
        fifu_propagate_key(true);
    }
    return (bool) fifu_server_check_key($network);
}

function fifu_is_valid_nonce($nonce, $action = FIFU_ACTION_SETTINGS) {
    return isset($_POST[$nonce]) && wp_verify_nonce($_POST[$nonce], $action);
}

function fifu_check_status_server() {
    check_ajax_referer('wp_rest', 'security');

    $option_value = get_option('fifu_status_server');
    if ($option_value !== false) {
        wp_send_json_success(array('option_value' => $option_value));
    } else {
        wp_send_json_error();
    }
}

add_action('wp_ajax_fifu_check_status_server', 'fifu_check_status_server');

