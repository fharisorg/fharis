<?php

function fifu_wpml_is_wcml_active() {
    return defined('WCML_VERSION') || function_exists('wcml_is_multi_currency_on');
}

function fifu_wpml_copy_prefixed_post_meta($source_id, $target_id) {
    if (!$source_id || !$target_id || $source_id === $target_id) {
        return;
    }

    $source_meta = get_post_meta($source_id);
    if (empty($source_meta)) {
        return;
    }

    $post_type = get_post_type($source_id);

    $extract_first_scalar = static function ($values) {
        $values = is_array($values) ? $values : [$values];

        $first_value = $values[0] ?? null;

        return is_array($first_value) ? reset($first_value) : $first_value;
    };

    $list_value = null;
    $list_meta_values = $source_meta['fifu_list_url'] ?? null;
    $existing_list_value = $list_meta_values !== null ? $extract_first_scalar($list_meta_values) : null;
    $existing_list_value = is_string($existing_list_value) ? trim($existing_list_value) : '';

    if ($existing_list_value !== '') {
        $list_value = $existing_list_value;
    } elseif ('product' === $post_type) {
        $image_meta_keys = array_filter(
                array_keys($source_meta),
                static function ($meta_key) {
                    return preg_match('/^fifu_image_url(?:_\d+)?$/', $meta_key);
                }
        );

        if (!empty($image_meta_keys)) {
            usort($image_meta_keys, 'strnatcmp');

            $image_urls = [];

            foreach ($image_meta_keys as $image_meta_key) {
                $image_values = $source_meta[$image_meta_key];
                $image_values = is_array($image_values) ? $image_values : [$image_values];

                foreach ($image_values as $image_value) {
                    if (is_array($image_value)) {
                        $image_value = reset($image_value);
                    }

                    if (is_string($image_value)) {
                        $image_value = trim($image_value);
                    }

                    if (!is_string($image_value) || $image_value === '') {
                        continue;
                    }

                    $image_urls[] = $image_value;
                }
            }

            $image_urls = array_values(array_unique($image_urls));

            if ($image_urls) {
                $list_value = implode('|', $image_urls);
            }
        }
    }

    if ($list_value !== null && $list_value !== '') {
        delete_post_meta($target_id, '_product_image_gallery');
        delete_post_meta($target_id, '_thumbnail_id');
        clean_post_cache($target_id);
        fifu_dev_set_image_list($target_id, $list_value);
    }

    foreach ($source_meta as $meta_key => $values) {
        if ('fifu_image_url' === $meta_key && 'product' === $post_type) {
            continue;
        }

        if ($meta_key !== 'fifu_image_url') {
            continue;
        }

        $values = is_array($values) ? $values : [$values];

        $first_value = $values[0] ?? null;
        $first_value = is_array($first_value) ? reset($first_value) : $first_value;

        fifu_dev_set_image($target_id, $first_value);
    }
}

function fifu_wpml_sync_variation_fifu_meta($original_product_id, $translated_product_id, $lang = null) {
    if (!$original_product_id || !$translated_product_id) {
        return;
    }

    $variation_ids = get_posts([
        'post_type' => 'product_variation',
        'post_parent' => $original_product_id,
        'fields' => 'ids',
        'posts_per_page' => -1,
        'post_status' => ['publish', 'private'],
    ]);

    if (empty($variation_ids)) {
        return;
    }

    if (!$lang) {
        $details = apply_filters('wpml_post_language_details', null, $translated_product_id);
        $lang = is_array($details) ? ($details['language_code'] ?? null) : null;
    }

    foreach ($variation_ids as $orig_var_id) {
        $translated_var_id = $lang ? apply_filters('wpml_object_id', $orig_var_id, 'product_variation', false, $lang) : null;

        if (!$translated_var_id || $translated_var_id === $orig_var_id) {
            continue;
        }

        fifu_wpml_copy_prefixed_post_meta($orig_var_id, $translated_var_id);
    }
}

add_action('wcml_after_duplicate_product_post_meta', function ($original_id, $translated_id, $data = false) {
    fifu_wpml_copy_prefixed_post_meta($original_id, $translated_id);

    fifu_wpml_sync_variation_fifu_meta($original_id, $translated_id);
}, 10, 3);

add_action('wcml_after_sync_product_data', function ($original_id, $translated_id, $language) {
    fifu_wpml_copy_prefixed_post_meta($original_id, $translated_id);

    fifu_wpml_sync_variation_fifu_meta($original_id, $translated_id, $language);
}, 10, 3);

add_action('wcml_synchronize_product_variation_translations', function ($original_variation, $variation_translations, $translations_languages) {
    if (empty($variation_translations)) {
        return;
    }

    $source_id = is_object($original_variation) ? ($original_variation->ID ?? 0) : (int) $original_variation;

    if (!$source_id) {
        return;
    }

    foreach ($variation_translations as $translation_id) {
        if (!$translation_id || $translation_id === $source_id) {
            continue;
        }

        fifu_wpml_copy_prefixed_post_meta($source_id, $translation_id);
    }
}, 10, 3);

add_action('icl_make_duplicate', function ($source_id, $lang, $post_array, $duplicate_id) {
    $post_type = get_post_type($source_id);

    if (!$post_type) {
        return;
    }

    if (in_array($post_type, ['product', 'product_variation'], true) && fifu_wpml_is_wcml_active()) {
        return;
    }

    fifu_wpml_copy_prefixed_post_meta($source_id, $duplicate_id);
}, 10, 4);

