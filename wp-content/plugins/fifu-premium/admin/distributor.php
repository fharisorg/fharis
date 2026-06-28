<?php

define('FIFU_HOUSES_DEBUG', false);

define('FIFU_HOUSES_DEBUG_LIST', 'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?q=80&w=2075&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D|https://images.unsplash.com/photo-1523217582562-09d0def993a6?q=80&w=2080&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D|https://images.unsplash.com/photo-1570129477492-45c003edd2be?q=80&w=2070&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D|https://images.unsplash.com/photo-1568605114967-8130f3a36994?q=80&w=2070&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D|https://plus.unsplash.com/premium_photo-1661964475795-f0cb85767a88?q=80&w=1974&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D|https://images.unsplash.com/photo-1554995207-c18c203602cb?q=80&w=2070&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D');

function fifu_dt_before_set_meta($meta, $post_id) {
    if (isset($meta['fifu_houzez_urls'])) {
        $url_list = implode('|', $meta['fifu_houzez_urls']);
        $url_list = FIFU_HOUSES_DEBUG ? FIFU_HOUSES_DEBUG_LIST : $url_list;
        if ($url_list == get_post_meta($post_id, 'fifu_slider_list_url', true) && get_post_meta($post_id, 'fifu_slider_image_url_0', true) && !FIFU_HOUSES_DEBUG)
            return;

        delete_post_meta($post_id, 'fifu_houzez_urls');
        delete_post_meta($post_id, 'fave_property_images');

        $alt_list = array();
        fifu_dev_set_slider($post_id, $url_list, $alt_list);
        wp_cache_flush();

        $gallery_ids = get_post_meta($post_id, '_product_image_gallery', true);
        if (!empty($gallery_ids)) {
            foreach (explode(',', $gallery_ids) as $att_id) {
                if (!empty($att_id)) {
                    add_post_meta($post_id, 'fave_property_images', $att_id, false);
                }
            }
        }
    }
}

function fifu_dt_prepared_meta($prepared_meta, $post_id) {
    if (isset($prepared_meta['_thumbnail_id']) || isset($prepared_meta['fave_property_images'])) {
        $prepared_meta['fifu_houzez_urls'] = [];
    }

    // Check if _thumbnail_id exists and get its URL
    if (isset($prepared_meta['_thumbnail_id'])) {
        $thumbnail_id = $prepared_meta['_thumbnail_id'][0] ?? null;
        if ($thumbnail_id) {
            $thumbnail_url = wp_get_attachment_url($thumbnail_id);
            if ($thumbnail_url) {
                $prepared_meta['fifu_houzez_urls'][] = $thumbnail_url;
            }
        }
    }

    // Check if fave_property_images exists and get URLs for each image
    if (isset($prepared_meta['fave_property_images'])) {
        foreach ($prepared_meta['fave_property_images'] as $image_id) {
            $image_url = wp_get_attachment_url($image_id);
            if ($image_url) {
                // Add each image URL to the array
                $prepared_meta['fifu_houzez_urls'][] = $image_url;
            }
        }
    }

    return $prepared_meta;
}

function fifu_dt_push_post_media($new_post_id, $post_media, $post_id, $args) {
    return false;
}

if (function_exists('fifu_is_houzez_active') && fifu_is_houzez_active()) {
    add_action('dt_push_post_media', 'fifu_dt_push_post_media', 10, 4);
    add_action('dt_before_set_meta', 'fifu_dt_before_set_meta', 10, 2);
    add_action('dt_prepared_meta', 'fifu_dt_prepared_meta', 10, 2);
}
