<?php

function fifu_display_expired_notice() {
    $fifu = fifu_get_strings_notice();
    if (get_option('fifu_key') && get_option('fifu_expired') && !get_option('fifu_expired_notice_dismissed'))
        echo '<div class="notice notice-error is-dismissible" id="fifu-license-expired-notice"><p><span class="dashicons dashicons-camera"></span> <b>FIFU</b>: ' . $fifu['notice']['expired']() . ' <a href="' . esc_url(get_admin_url(null, 'admin.php?page=fifu-license-key')) . '" style="text-decoration:none"><span class="dashicons dashicons-update"></span></a></p></div>';

    if (!get_option('fifu_key'))
        echo '<div class="notice notice-error is-dismissible"><p><span class="dashicons dashicons-camera"></span> <b>FIFU</b>: ' . $fifu['notice']['key']() . ' <a href="' . esc_url(get_admin_url(null, 'admin.php?page=fifu-license-key')) . '" style="text-decoration:none"><span class="dashicons dashicons-admin-network"></span></a></p></div>';
}

add_action('admin_notices', 'fifu_display_expired_notice');

function fifu_enqueue_admin_scripts($hook) {
    // Load the dismissal script anywhere the expired notice can appear.
    // Avoid enqueuing unnecessarily when the notice isn't relevant.
    $has_key = (bool) get_option('fifu_key');
    $is_expired = (bool) get_option('fifu_expired');
    $dismissed = (bool) get_option('fifu_expired_notice_dismissed');

    if ($has_key && $is_expired && !$dismissed) {
        wp_enqueue_script('fifu-notices', plugins_url('/html/js/notices.js', __FILE__), array('jquery'), fifu_version_number_enq());
        wp_localize_script('fifu-notices', 'fifuAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fifu_dismiss_expired_nonce')
        ));
    }
}

add_action('admin_enqueue_scripts', 'fifu_enqueue_admin_scripts');

function fifu_dismiss_expired_notice() {
    check_ajax_referer('fifu_dismiss_expired_nonce', 'nonce');
    update_option('fifu_expired_notice_dismissed', true);
    wp_send_json_success();
}

add_action('wp_ajax_fifu_dismiss_expired_notice', 'fifu_dismiss_expired_notice');

