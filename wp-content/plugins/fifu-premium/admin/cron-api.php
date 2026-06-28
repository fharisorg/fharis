<?php

add_action('rest_api_init', function () {
    register_rest_route('fifu-premium/v2', '/cron-add/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_cron_add',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('fifu-premium/v2', '/cron-delete/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_cron_delete',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('fifu-premium/v2', '/cron-run/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_cron_run',
        'permission_callback' => function ($request) {
            $token = $request->get_header('X-FIFU-Authorization');
            return $token === get_option('fifu_ws_key_ddg');
        },
        'args' => array(
            'feature' => array(
                'required' => true,
                'validate_callback' => function ($param, $request, $key) {
                    return in_array($param, ['fifu_toggle_screenshot', 'fifu_toggle_tags', 'fifu_toggle_isbn', 'fifu_toggle_asin', 'fifu_toggle_customfield', 'fifu_toggle_finder', 'fifu_toggle_auto_set', 'fifu_toggle_upload_job', 'fifu_toggle_cloud_upload_auto', 'fifu_toggle_cloud_delete_auto', 'fifu_toggle_cron_metadata', 'fifu_toggle_importpost', 'fifu_toggle_importterm']);
                }
            ),
        ),
    ));
});

function fifu_api_cron_add(WP_REST_Request $request) {
    $toggle = $request['toggle'] ?? null;

    $data = array(
        'site' => get_home_url(),
        'route' => get_rest_url(),
        'plugin' => FIFU_CLIENT,
        'version' => fifu_version_number(),
        'key' => fifu_partial_key(),
        'feature' => $toggle,
    );

    $query_string = http_build_query($data);

    $url = "https://plugin.featuredimagefromurl.com/api/cron_add/?" . $query_string;

    $is_async = ($toggle == 'fifu_toggle_importpost' || $toggle == 'fifu_toggle_importterm');

    if ($is_async) {
        $args = array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => get_option('fifu_ws_key_ddg'),
            ),
            'blocking' => true,
            'timeout' => 0.001,
        );
        wp_remote_get($url, $args);
        return new WP_REST_Response('', 200);
    }

    $args = array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => get_option('fifu_ws_key_ddg'),
        ),
        'timeout' => 60,
    );

    $response = wp_remote_get($url, $args);

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        fifu_plugin_log(['cron-add' => ['ERROR' => $error_message, 'toggle' => $toggle]]);
        return new WP_REST_Response('', 500);
    } else {
        $status_code = wp_remote_retrieve_response_code($response);
        fifu_plugin_log(['cron-add' => ['INFO' => $status_code, 'toggle' => $toggle]]);
    }

    return new WP_REST_Response('', $status_code);
}

function fifu_api_cron_delete(WP_REST_Request $request) {
    $toggle = $request['toggle'] ?? null;

    $data = array(
        'site' => get_home_url(),
        'feature' => $toggle,
    );

    $query_string = http_build_query($data);

    $url = "https://plugin.featuredimagefromurl.com/api/cron_delete/?" . $query_string;

    $args = array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => get_option('fifu_ws_key_ddg'),
        ),
        'timeout' => 60,
    );

    $response = wp_remote_get($url, $args);

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        fifu_plugin_log(['cron-delete' => ['ERROR' => $error_message, 'toggle' => $toggle]]);
        return new WP_REST_Response('', 500);
    } else {
        $status_code = wp_remote_retrieve_response_code($response);
        fifu_plugin_log(['cron-delete' => ['INFO' => $status_code, 'toggle' => $toggle]]);
    }

    return new WP_REST_Response('', $status_code);
}

function fifu_api_cron_run(WP_REST_Request $request) {
    $toggle = $request->get_param('feature');
    $option = str_replace('toggle_', '', $toggle);
    $id = str_replace('fifu_toggle_', '', $toggle);

    if (fifu_is_off($option))
        return new WP_REST_Response('', 403);

    $functionName = str_replace('toggle', 'cron', $toggle);
    $functionName($id);

    return new WP_REST_Response('', 200);
}

////////////////////////// RUN //////////////////////////

function fifu_cron_screenshot() {
    $result = fifu_db_get_posts_types_without_screenshot();
    foreach ($result as $res) {
        // $size = get_option('fifu_screenshot_size');
        // fifu_dev_set_image($res->post_id, "https://screenshot.fifu.app/{$size}/{$res->url}");
        fifu_dev_set_image($res->post_id, "https://screenshot.fifu.app/{$res->url}");
    }
}

function fifu_cron_tags($id) {
    fifu_create_generic_hook($id);
}

function fifu_cron_isbn($id) {
    fifu_create_generic_hook($id);
}

function fifu_cron_asin($id) {
    fifu_create_generic_hook($id);
}

function fifu_cron_customfield($id) {
    fifu_create_customfield_hook();
}

function fifu_cron_finder($id) {
    fifu_create_generic_hook($id);
}

function fifu_cron_auto_set($id) {
    fifu_create_generic_hook($id);
}

function fifu_cron_upload_job($id) {
    fifu_create_generic_hook('uploadpost');
    fifu_create_generic_hook('uploadterm');
}

function fifu_cron_cloud_upload_auto($id) {
    fifu_create_cloud_upload_auto_hook();
}

function fifu_cron_cloud_delete_auto($id) {
    fifu_create_cloud_delete_auto_hook();
}

function fifu_cron_cron_metadata($id) {
    if (fifu_get_transient('fifu_import_running'))
        return;
    fifu_create_generic_hook('metadatapost');
    fifu_create_generic_hook('metadataterm');
}

function fifu_cron_importpost($id) {
    fifu_create_generic_hook($id);
}

function fifu_cron_importterm($id) {
    fifu_create_generic_hook($id);
}

////////////////////////// NOW //////////////////////////

function fifu_wp_after_insert_post($post_id, $post, $update) {
    $att_id = get_post_thumbnail_id($post_id);
    if ($att_id)
        return;

    if (fifu_is_on('fifu_isbn')) {
        $cf = get_option('fifu_isbn_custom_field');
        $isbn = get_post_meta($post_id, $cf, true);
        $isbn = $isbn ? $isbn : get_post_meta($post_id, 'fifu_isbn', true);
        if ($isbn) {
            fifu_create_generic_hook('isbn');
            return;
        }
    }

    if (fifu_is_on('fifu_asin')) {
        $cf = get_option('fifu_asin_custom_field');
        $asin = get_post_meta($post_id, $cf, true);
        $asin = $asin ? $asin : get_post_meta($post_id, 'fifu_asin', true);
        if ($asin) {
            fifu_create_generic_hook('asin');
            return;
        }
    }

    if (fifu_is_on('fifu_finder')) {
        $cf = get_option('fifu_finder_custom_field');
        $url = get_post_meta($post_id, $cf, true);
        $url = $url ? $url : get_post_meta($post_id, 'fifu_finder_url', true);
        if ($url) {
            fifu_create_generic_hook('finder');
            return;
        }
    }

    if (fifu_is_on('fifu_auto_set')) {
        fifu_create_generic_hook('auto_set');
        return;
    }

    if (fifu_is_on('fifu_tags')) {
        fifu_create_generic_hook('tags');
        return;
    }

    if (fifu_is_on('fifu_customfield')) {
        $urls = fifu_get_customfield_urls($post_id);
        if (!empty($urls)) {
            fifu_create_customfield_hook();
            return;
        }
    }

    if (fifu_is_on('fifu_screenshot')) {
        $cf = get_option('fifu_screenshot_custom_field');
        $url = get_post_meta($post_id, $cf, true);
        if ($url) {
            fifu_cron_screenshot();
            return;
        }
    }
}

add_action('wp_after_insert_post', 'fifu_wp_after_insert_post', 10, 3);

////////////////////////// UTIL //////////////////////////

function fifu_get_customfield_urls($post_id) {
    $urls = [];
    $cf = get_option('fifu_customfield_custom_field');
    $cf_list = explode(',', $cf);

    foreach ($cf_list as $cf_item) {
        $cf_item = trim($cf_item);

        // Ensure the custom field is a valid custom field name and not a pattern
        if (strpos($cf_item, '{') !== false || strpos($cf_item, '}') !== false) {
            // Extract the custom field name between the curly braces
            preg_match('/{(.+?)}/', $cf_item, $matches);
            $custom_field_name = $matches[1] ?? null;

            // Get the full URL pattern and replace the placeholder with the custom field value
            $url_pattern = $cf_item;
            $custom_field_value = get_post_meta($post_id, $custom_field_name, true);
            if (!$custom_field_value)
                continue;
            $url = str_replace('{' . $custom_field_name . '}', $custom_field_value, $url_pattern);
        } else {
            $custom_field_value = get_post_meta($post_id, $cf_item, true);
            if (!$custom_field_value)
                continue;

            // Use the custom field value directly
            $url = $custom_field_value;
        }

        if ($url) {
            $urls[] = $url;
        }
    }

    return $urls;
}

