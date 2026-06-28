<?php

function fifu_create_customfield_hook() {
    if (fifu_active_job('fifu_customfield_semaphore', 5))
        return;

    if (get_option('fifu_customfield_custom_field')) {
        $result = fifu_db_get_customfields_without_featured_image();
        $prev_id = null;
        foreach ($result as $res) {
            fifu_set_transient('fifu_customfield_semaphore', new DateTime(), 0);
            $id = $res->id ?? null;
            if ($id == $prev_id)
                continue;
            $prev_id = $id;

            // Construct the URL with prefix and suffix if necessary
            $url = $res->meta_value ?? '';
            $cf_option = get_option('fifu_customfield_custom_field');
            $cf_list = explode(',', $cf_option);
            foreach ($cf_list as $cf_item) {
                if (strpos($cf_item, '{') !== false || strpos($cf_item, '}') !== false) {
                    // Extract the custom field name between the curly braces
                    preg_match('/{(.+?)}/', $cf_item, $matches);
                    $custom_field_name = $matches[1] ?? null;
                    if ($custom_field_name === ($res->meta_key ?? null)) {
                        $url = str_replace('{' . $custom_field_name . '}', $res->meta_value ?? '', $cf_item);
                        break;
                    }
                }
            }

            if ($url && strpos($url, 'http') === 0) {
                if ($res->is_ctgr ?? false) {
                    if (fifu_is_video($url))
                        fifu_dev_set_category_video($id, $url);
                    else
                        fifu_save_ctgr_image_data($id, $url, null, null);
                } else {
                    if (fifu_is_video($url))
                        fifu_dev_set_video($id, $url);
                    else
                        fifu_save_image_data($id, $url, null, null);
                }
            }
        }
    }
    fifu_delete_transient('fifu_customfield_semaphore');
}

function fifu_create_cloud_upload_auto_hook() {
    if (fifu_active_job('fifu_cloud_upload_auto_semaphore', 5))
        return;

    $urls = fifu_db_get_all_urls(0, null, null);

    // Limit the number of URLs to 100
    $urls = array_slice($urls, 0, 100);

    foreach ($urls as $url) {
        if (strpos($url->meta_key ?? '', 'video') !== false) {
            $url->video_url = $url->url ?? '';
            $url->url = fifu_video_img_large($url->url ?? '', $url->post_id ?? null, $url->category ?? null);
        }
    }
    fifu_create_thumbnails_list($urls, true);

    fifu_delete_transient('fifu_cloud_upload_auto_semaphore');
}

function fifu_create_cloud_delete_auto_hook() {
    if (fifu_active_job('fifu_cloud_delete_auto_semaphore', 5))
        return;

    $hex_ids = fifu_db_get_all_hex_ids();

    fifu_delete_thumbnails($hex_ids);

    fifu_delete_transient('fifu_cloud_delete_auto_semaphore');
}

function fifu_create_generic_hook($id) {
    if (fifu_active_job("fifu_{$id}_semaphore", 5))
        return;

    switch ($id) {
        case 'auto_set':
            $post_types_array = array_map('trim', explode(',', get_option("fifu_{$id}_cpt")));
            $post_types = join("','", $post_types_array);
            $result = fifu_db_get_post_types_without_featured_image($post_types);
            $service = 'title';
            $is_term = false;
            $extra_field = true;
            break;
        case 'metadatapost':
            $result = fifu_db_get_all_posts_without_meta();
            $service = $id;
            $is_term = false;
            $extra_field = true;
            break;
        case 'metadataterm':
            if (fifu_is_on('fifu_auto_category'))
                fifu_db_insert_auto_category_image();
            $result = fifu_db_get_categories_without_meta();
            $service = $id;
            $is_term = true;
            $extra_field = true;
            break;
        case 'importpost':
            $result = fifu_db_get_all_posts_to_import();
            $service = $id;
            $is_term = false;
            $extra_field = true;
            break;
        case 'importterm':
            $result = fifu_db_get_all_terms_to_import();
            $service = $id;
            $is_term = true;
            $extra_field = true;
            break;
        case 'isbn':
            $result = fifu_db_get_isbns_without_featured_image();
            $service = $id;
            $is_term = false;
            $extra_field = true;
            break;
        case 'asin':
            if (!get_option('fifu_asin_credentials_partner') ||
                    !get_option('fifu_asin_credentials_access') ||
                    !get_option('fifu_asin_credentials_secret') ||
                    !get_option('fifu_asin_credentials_locale'))
                $result = array();
            else
                $result = fifu_db_get_asins_without_featured_image();
            $service = $id;
            $is_term = false;
            $extra_field = true;
            break;
        case 'finder':
            $result = fifu_db_get_finders_without_featured_image();
            $service = $id;
            $is_term = false;
            $extra_field = true;
            break;
        case 'tags':
            $result = fifu_db_get_tags_without_featured_image();
            $service = $id;
            $is_term = false;
            $extra_field = true;
            break;
        case 'uploadpost':
            $result = fifu_db_get_posts_types_with_url_to_upload();
            $service = $id;
            $is_term = false;
            $extra_field = true;
            break;
        case 'uploadterm':
            $result = fifu_db_get_terms_with_url_to_upload();
            $service = $id;
            $is_term = true;
            $extra_field = true;
            break;
        case 'metain':
            $result = fifu_db_get_meta_in();
            if (count($result) == 0) {
                fifu_db_prepare_meta_in();
                $result = fifu_db_get_meta_in();
            }
            $service = $id;
            $extra_field = false;
            break;
        case 'metaout':
            $result = fifu_db_get_meta_out();
            if (count($result) == 0) {
                fifu_db_prepare_meta_out();
                $result = fifu_db_get_meta_out();
            }
            $service = $id;
            $extra_field = false;
            break;
        case 'content':
            $result = fifu_db_get_content();
            if (count($result) == 0) {
                fifu_db_prepare_content();
                $result = fifu_db_get_content();
            }
            $service = $id;
            $extra_field = false;
            break;
        default:
            return null;
    }

    $meta_key = "fifu_{$id}_sent";

    $post_ids = array();
    foreach ($result as $res)
        $post_ids[] = $res->post_id ?? null;

    if (!empty($post_ids)) {
        // Prepare data for the POST request
        $data = array(
            'route' => get_rest_url(),
            'post_ids' => $post_ids,
        );

        // Perform the POST request
        $response = wp_remote_post("https://plugin.featuredimagefromurl.com/api/update/{$service}/", array(
            'method' => 'POST',
            'body' => json_encode($data),
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => get_option('fifu_ws_key_ddg'),
            ),
            'timeout' => 60,
        ));

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            fifu_plugin_log(['fifu-create-generic-hook' => ['SERVICE' => $service, 'STATUS' => $error_message]]);
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code == 200) {
                fifu_plugin_log(['fifu-create-generic-hook' => ['SERVICE' => $service, 'STATUS' => $response_code, 'IDS' => count($post_ids)]]);
                if (!$extra_field) {
                    fifu_delete_transient("fifu_{$id}_semaphore");
                    return;
                }

                global $wpdb;
                $meta_value = 0;

                $values_placeholder = [];
                $prepare_values = [];
                foreach ($post_ids as $post_id) {
                    // Placeholder for each post_id and meta_value pair
                    $values_placeholder[] = '(%d, %s, %s)';

                    // Actual values to be inserted, corresponding to the placeholders
                    $prepare_values[] = $post_id;
                    $prepare_values[] = $meta_key;
                    $prepare_values[] = $meta_value;
                }

                // Construct the SQL statement
                if ($is_term)
                    $sql = "INSERT INTO {$wpdb->termmeta} (term_id, meta_key, meta_value) VALUES " . implode(', ', $values_placeholder);
                else
                    $sql = "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES " . implode(', ', $values_placeholder);

                // Prepare SQL statement with values
                $prepared_sql = $wpdb->prepare($sql, $prepare_values);

                // Execute the query
                $wpdb->query($prepared_sql);
            } else
                fifu_plugin_log(['fifu-create-generic-hook' => ['SERVICE' => $service, 'STATUS' => $response_code]]);
        }
    }


    if ($service == 'importpost' || $service == 'importterm') {
        if (empty($post_ids)) {
            $attempts = get_option("fifu_counter_{$service}");
            $attempts = $attempts ? $attempts + 1 : 1;
            if ($attempts >= 10) {
                delete_option("fifu_counter_{$service}");
                $request = new WP_REST_Request();
                $request->set_param('toggle', "fifu_toggle_{$service}");
                fifu_api_cron_delete($request);
            } else {
                update_option("fifu_counter_{$service}", $attempts, 'no');
            }

            if (fifu_is_wp_all_import_active())
                fifu_db_delete_garbage_wai();
        } else {
            update_option("fifu_counter_{$service}", 0, 'no');
        }
    }

    fifu_delete_transient("fifu_{$id}_semaphore");
}

function fifu_save_image_data($post_id, $url, $width, $height) {
    fifu_dev_set_image($post_id, $url);
    if ($width && $height) {
        $att_id = get_post_thumbnail_id($post_id);
        fifu_save_dimensions($att_id, $width, $height);
    }
}

function fifu_save_ctgr_image_data($term_id, $url, $width, $height) {
    fifu_dev_set_category_image($term_id, $url);
    if ($width && $height) {
        $att_id = get_term_meta($term_id, 'thumbnail_id', true);
        fifu_save_dimensions($att_id, $width, $height);
    }
}

function fifu_active_job($semaphore, $minutes) {
    $date = fifu_get_transient($semaphore);
    if (!$date)
        return false;

    if (gettype($date) != 'object') {
        fifu_set_transient($semaphore, new DateTime(), 0);
        return true;
    }

    return date_diff(new DateTime(), $date)->format('%i') < $minutes;
}