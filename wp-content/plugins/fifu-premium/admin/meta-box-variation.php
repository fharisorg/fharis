<?php

add_action('woocommerce_product_after_variable_attributes', 'fifu_new_variation_settings_fields', 10, 3);

function fifu_new_variation_settings_fields($loop, $variation_data, $variation) {
    $fifu = fifu_get_strings_meta_box_php();

    $vid = isset($variation->ID) ? $variation->ID : $loop;

    $grid_id = 'gridDemoImage-' . $vid;
    $hidden_div_id = 'inputVarHiddenImages-' . $vid;
    $list_ids_id = 'inputVarHiddenImageListIds-' . $vid;
    $list_ids_name = 'inputVarHiddenImageListIds[' . $vid . ']';
    $length_id = 'inputVarHiddenImageLength-' . $vid;
    $length_name = 'inputVarHiddenImageLength[' . $vid . ']';

    // Load image data
    $image_list = array();
    $image_alt_list = array();
    $image_width_list = array();
    $image_height_list = array();
    $image_ifm_list = array();

    // Featured image
    $featured_url = get_post_meta($variation->ID, 'fifu_image_url', true);
    $featured_alt = get_post_meta($variation->ID, 'fifu_image_alt', true);
    $featured_width = get_post_meta($variation->ID, 'fifu_var_input_width_0-' . $vid, true);
    $featured_height = get_post_meta($variation->ID, 'fifu_var_input_height_0-' . $vid, true);
    $featured_ifm = get_post_meta($variation->ID, 'fifu_var_input_ifm_0-' . $vid, true);

    if ($featured_url) {
        $image_list[] = $featured_url;
        $image_alt_list[] = $featured_alt;
        $image_width_list[] = $featured_width;
        $image_height_list[] = $featured_height;
        $image_ifm_list[] = $featured_ifm;
    }

    // Gallery images
    $i = 0;
    while (true) {
        $url = get_post_meta($variation->ID, 'fifu_image_url_' . $i, true);
        if (!$url || !is_string($url) || trim($url) === '')
            break;
        $alt = get_post_meta($variation->ID, 'fifu_image_alt_' . $i, true);
        $width = get_post_meta($variation->ID, 'fifu_var_input_width_' . ($i + 1) . '-' . $vid, true);
        $height = get_post_meta($variation->ID, 'fifu_var_input_height_' . ($i + 1) . '-' . $vid, true);
        $ifm = get_post_meta($variation->ID, 'fifu_var_input_ifm_' . ($i + 1) . '-' . $vid, true);

        $image_list[] = $url;
        $image_alt_list[] = $alt;
        $image_width_list[] = $width;
        $image_height_list[] = $height;
        $image_ifm_list[] = $ifm;
        $i++;
    }

    // Output grid and hidden fields
    echo '<span class="dashicons dashicons-camera"></span>';
    echo '<label style="padding-bottom:5px;display:inline-block;">' . $fifu['variation']['new']['images']() . '</label>';
    echo '<span id="fifu_help_alt" class="dashicons dashicons-editor-help" style="font-size:20px;cursor:help;float:right" title="' . $fifu['variation']['new']['help']() . '"></span>';
    echo '<div id="' . esc_attr($grid_id) . '"></div>';
    echo '<div id="' . esc_attr($hidden_div_id) . '">';
    foreach ($image_list as $idx => $url) {
        $alt = $image_alt_list[$idx] ?? '';
        $width = $image_width_list[$idx] ?? '';
        $height = $image_height_list[$idx] ?? '';
        $ifm = $image_ifm_list[$idx] ?? '';
        echo '<input type="hidden" id="fifu_var_input_url_' . $idx . '-' . $vid . '" name="fifu_var_input_url_' . $idx . '-' . $vid . '" value="' . esc_attr($url) . '"/>';
        echo '<input type="hidden" id="fifu_var_input_alt_' . $idx . '-' . $vid . '" name="fifu_var_input_alt_' . $idx . '-' . $vid . '" value="' . esc_attr($alt) . '"/>';
        echo '<input type="hidden" id="fifu_var_input_width_' . $idx . '-' . $vid . '" name="fifu_var_input_width_' . $idx . '-' . $vid . '" value="' . esc_attr($width) . '"/>';
        echo '<input type="hidden" id="fifu_var_input_height_' . $idx . '-' . $vid . '" name="fifu_var_input_height_' . $idx . '-' . $vid . '" value="' . esc_attr($height) . '"/>';
        echo '<input type="hidden" id="fifu_var_input_ifm_' . $idx . '-' . $vid . '" name="fifu_var_input_ifm_' . $idx . '-' . $vid . '" value="' . esc_attr($ifm) . '"/>';
    }
    echo '</div>';

    // Image list and length for JS
    $list_ids_value = '';
    if (count($image_list) > 0) {
        $list_ids_value = implode('|', range(0, count($image_list) - 1));
    }
    echo '<input type="hidden" id="' . esc_attr($list_ids_id) . '" name="' . esc_attr($list_ids_name) . '" value="' . esc_attr($list_ids_value) . '"/>';
    echo '<input type="hidden" id="' . esc_attr($length_id) . '" name="' . esc_attr($length_name) . '" value="' . esc_attr(count($image_list)) . '"/>';

    // WooCommerce image IDs
    $thumbnail_id = get_post_meta($variation->ID, '_thumbnail_id', true);
    $gallery_ids = get_post_meta($variation->ID, '_product_image_gallery', true);
    echo '<input type="hidden" id="fifu_wc_thumbnail_id_' . $vid . '" name="fifu_wc_thumbnail_id_' . $vid . '" value="' . esc_attr($thumbnail_id) . '"/>';
    echo '<input type="hidden" id="fifu_wc_gallery_ids_' . $vid . '" name="fifu_wc_gallery_ids_' . $vid . '" value="' . esc_attr($gallery_ids) . '"/>';

    // Add WooCommerce hidden input for testing
    woocommerce_wp_hidden_input(array(
        'id' => 'fifu_test_input_' . $vid,
        'name' => 'fifu_test_input_' . $vid,
        'value' => '',
    ));

    if (fifu_is_on('fifu_upload_show')) {
        echo '<input type="checkbox" id="fifu_upload_cb" name="fifu_upload_cb" value="yes" style="margin-left:8px;">';
        echo '<label for="fifu_upload_cb" style="margin-left:4px;">' . $fifu['variation']['new']['upload']() . '</label>';
    }
}

add_action('woocommerce_save_product_variation', 'fifu_new_save_variation_settings_fields', 10, 2);

function fifu_new_save_variation_settings_fields($variation_id, $loop) {
    // Get the image indices list for this variation (from hidden input)
    $vid = $variation_id;
    $list_raw = $_POST['inputVarHiddenImageListIds'][$vid] ?? '';
    $list_raw = is_string($list_raw) ? $list_raw : '';
    $indices = $list_raw === '' ? array() : array_values(array_filter(explode('|', $list_raw), 'strlen'));

    if (!empty($indices)) {
        // Save featured image (first in the list)
        $first_idx = $indices[0];
        $featured_url = $_POST['fifu_var_input_url_' . $first_idx . '-' . $vid] ?? null;
        $featured_url = $featured_url ? esc_url_raw(rtrim($featured_url)) : null;
        fifu_update_or_delete($variation_id, 'fifu_image_url', $featured_url);

        $featured_alt = $_POST['fifu_var_input_alt_' . $first_idx . '-' . $vid] ?? null;
        $featured_alt = $featured_alt ? sanitize_text_field($featured_alt) : null;
        fifu_update_or_delete_value($variation_id, 'fifu_image_alt', $featured_alt);

        // Save gallery images (remaining indices)
        $g_i = 0;
        foreach ($indices as $pos => $idx) {
            if ($pos === 0)
                continue; // skip featured
            $gal_url = $_POST['fifu_var_input_url_' . $idx . '-' . $vid] ?? null;
            $gal_url = $gal_url ? esc_url_raw(rtrim($gal_url)) : null;
            fifu_update_or_delete($variation_id, 'fifu_image_url_' . $g_i, $gal_url);

            $gal_alt = $_POST['fifu_var_input_alt_' . $idx . '-' . $vid] ?? null;
            $gal_alt = $gal_alt ? sanitize_text_field($gal_alt) : null;
            fifu_update_or_delete_value($variation_id, 'fifu_image_alt_' . $g_i, $gal_alt);

            $g_i++;
        }

        // Remove old gallery meta beyond current count
        while (true) {
            $existing = get_post_meta($variation_id, 'fifu_image_url_' . $g_i, true);
            if ($existing) {
                delete_post_meta($variation_id, 'fifu_image_url_' . $g_i);
                delete_post_meta($variation_id, 'fifu_image_alt_' . $g_i);
                $g_i++;
                continue;
            }
            break;
        }

        // Save dimensions for featured image
        $width = $_POST['fifu_var_input_width_' . $first_idx . '-' . $vid] ?? null;
        $height = $_POST['fifu_var_input_height_' . $first_idx . '-' . $vid] ?? null;
        $att_id = get_post_thumbnail_id($variation_id);
        if (function_exists('fifu_save_dimensions')) {
            fifu_save_dimensions($att_id, $width, $height);
        }

        // Save dimensions for gallery images
        $g_i = 0;
        foreach ($indices as $pos => $idx) {
            if ($pos === 0)
                continue;
            $w = $_POST['fifu_var_input_width_' . $idx . '-' . $vid] ?? null;
            $h = $_POST['fifu_var_input_height_' . $idx . '-' . $vid] ?? null;
            $gal_url_post = $_POST['fifu_var_input_url_' . $idx . '-' . $vid] ?? '';
            $gal_url = $gal_url_post ? esc_url_raw(rtrim($gal_url_post)) : '';
            $att_id = function_exists('fifu_db_get_att_id') ? fifu_db_get_att_id($variation_id, $gal_url, false) : 0;
            if (function_exists('fifu_save_dimensions')) {
                fifu_save_dimensions($att_id, $w, $h);
            }
            $g_i++;
        }
    } else {
        // Fallback: legacy behavior
        $url = $_POST['fifu_image_url'][$loop] ?? null;
        $url = $url ? esc_url_raw(rtrim($url)) : null;
        fifu_update_or_delete($variation_id, 'fifu_image_url', $url);

        foreach ($_POST as $key => $value) {
            if (strpos($key, 'fifu_image_url_') === 0) {
                $i = substr($key, strlen('fifu_image_url_'));
                $gurl = is_array($value) ? ($value[$loop] ?? null) : ($_POST[$key][$loop] ?? null);
                $gurl = $gurl ? esc_url_raw(rtrim($gurl)) : null;
                fifu_update_or_delete($variation_id, 'fifu_image_url_' . $i, $gurl);
            }
        }

        $width = $_POST['fifu_var_input_width'][$loop] ?? null;
        $height = $_POST['fifu_var_input_height'][$loop] ?? null;
        $att_id = get_post_thumbnail_id($variation_id);
        if (function_exists('fifu_save_dimensions')) {
            fifu_save_dimensions($att_id, $width, $height);
        }

        foreach ($_POST as $key => $value) {
            if (strpos($key, 'fifu_var_input_width_') === 0) {
                $i = substr($key, strlen('fifu_var_input_width_'));
                $width = is_array($value) ? ($value[$loop] ?? null) : ($_POST[$key][$loop] ?? null);
                $height = $_POST['fifu_var_input_height_' . $i][$loop] ?? null;
                $att_id = function_exists('fifu_db_get_att_id') ? fifu_db_get_att_id($variation_id, esc_url_raw(rtrim($_POST['fifu_image_url_' . $i][$loop] ?? '')), false) : 0;
                if (function_exists('fifu_save_dimensions')) {
                    fifu_save_dimensions($att_id, $width, $height);
                }
            }
        }
    }

    if (function_exists('fifu_update_fake_attach_id')) {
        fifu_update_fake_attach_id($variation_id);
    }

    // Optionally upload remote images into media library
    $fifu_upload_cb = $_POST['fifu_upload_cb'] ?? '';
    if ($fifu_upload_cb === 'yes') {
        $post_id = $variation_id;
        $url = get_post_meta($post_id, 'fifu_image_url', true);
        $alt = get_post_meta($post_id, 'fifu_image_alt', true);
        if (!$url)
            return;
        try {
            // Featured image upload
            $att_id = function_exists('fifu_upload_image') ? fifu_upload_image($post_id, $url, $alt, false) : 0;
            if (!$att_id)
                throw new Exception('UPLOAD ERROR: ' . $url);
            if ($alt)
                update_post_meta($att_id, '_wp_attachment_image_alt', $alt);
            wp_update_post(array('ID' => $att_id, 'post_content' => $url));

            // Gallery upload
            $i = 0;
            $gallery = function_exists('fifu_db_get_image_gallery_urls') ? fifu_db_get_image_gallery_urls($post_id) : array();
            $att_ids = '';
            foreach ($gallery as $item) {
                $meta_key_parts = explode('_', $item->meta_key);
                $id = $meta_key_parts[3] ?? '';
                $gal_url = $item->meta_value;
                $gal_alt = get_post_meta($post_id, 'fifu_image_alt_' . $id, true);
                $gal_att_id = function_exists('fifu_upload_image') ? fifu_upload_image($post_id, $gal_url, $gal_alt, false) : 0;
                if (!$gal_att_id)
                    throw new Exception('UPLOAD ERROR: ' . $gal_url);
                if ($gal_alt)
                    update_post_meta($gal_att_id, '_wp_attachment_image_alt', $gal_alt);
                wp_update_post(array('ID' => $gal_att_id, 'post_content' => $gal_url));
                $att_ids .= ($i++ == 0) ? $gal_att_id : ',' . $gal_att_id;
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
            error_log('ERROR: fifu_upload_image(' . $post_id . ')');
        }

        set_post_thumbnail($post_id, $att_id);
        delete_post_meta($post_id, 'fifu_image_url');
        delete_post_meta($post_id, 'fifu_image_alt');
        if (function_exists('fifu_db_update_fake_attach_id')) {
            fifu_db_update_fake_attach_id($post_id);
        }

        if (!empty($gallery)) {
            foreach ($gallery as $item) {
                $meta_key_parts = explode('_', $item->meta_key);
                $id = $meta_key_parts[3] ?? '';
                delete_post_meta($post_id, $item->meta_key);
                delete_post_meta($post_id, 'fifu_image_alt_' . $id);
            }
            update_post_meta($post_id, '_product_image_gallery', $att_ids);
            update_post_meta($post_id, '_wc_additional_variation_images', $att_ids);
        }
    }
}

add_action('woocommerce_ajax_save_product_variations', 'fifu_new_after_save_variation_settings_fields', 10, 1);

function fifu_new_after_save_variation_settings_fields($product_id) {
    fifu_db_update_wc_additional_variation_images($product_id);
}

