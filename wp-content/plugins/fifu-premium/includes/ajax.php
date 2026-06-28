<?php

function fifu_log_all_ajax_requests() {
    if (defined('DOING_AJAX') && DOING_AJAX) {
        $action = $_POST['action'] ?? 'undefined';
        error_log('AJAX action: ' . $action);
        error_log(print_r($_POST, true));
    }
}

// add_action('admin_init', 'fifu_log_all_ajax_requests');

