<?php

add_action('vg_sheet_editor/editor/register_columns', 'fifu_sheet_editor_register_columns', 20, 1);
add_filter('vg_sheet_editor/columns/blacklisted_columns', 'fifu_sheet_editor_blacklist_column', 20, 2);

function fifu_sheet_editor_register_columns($editor) {
    $post_types = $editor->args['enabled_post_types'];
    foreach ($post_types as $post_type) {
        if (!post_type_exists($post_type)) {
            return;
        }

        if ($post_type === 'product' && class_exists('WooCommerce')) {
            $editor->args['columns']->register_item(
                    'fifu_list_url',
                    $post_type,
                    array(
                        'data_type' => 'meta_data',
                        'column_width' => 170,
                        'title' => 'fifu_list_url',
                        'type' => '',
                        'supports_formulas' => true,
                        'allow_to_hide' => true,
                        'allow_to_rename' => true,
                        'supports_sql_formulas' => true,
                        'save_value_callback' => 'fifu_sheet_editor_save_product_gallery_images',
                    )
            );
        } else {
            $editor->args['columns']->register_item(
                    'fifu_image_url',
                    $post_type,
                    array(
                        'data_type' => 'meta_data',
                        'column_width' => 170,
                        'title' => 'fifu_image_url',
                        'type' => '',
                        'supports_formulas' => true,
                        'allow_to_hide' => true,
                        'allow_to_rename' => true,
                        'supports_sql_formulas' => true,
                        'save_value_callback' => 'fifu_sheet_editor_save_image_url',
                    )
            );

            $editor->args['columns']->register_item(
                    'fifu_image_alt',
                    $post_type,
                    array(
                        'data_type' => 'meta_data',
                        'column_width' => 170,
                        'title' => 'fifu_image_alt',
                        'type' => '',
                        'supports_formulas' => true,
                        'allow_to_hide' => true,
                        'allow_to_rename' => true,
                        'supports_sql_formulas' => true,
                    )
            );
        }
    }
}

function fifu_sheet_editor_save_image_url($post_id, $cell_key, $url, $post_type, $cell_args, $spreadsheet_columns) {
    fifu_dev_set_image($post_id, $url);
}

function fifu_sheet_editor_save_product_gallery_images($post_id, $cell_key, $urls, $post_type, $cell_args, $spreadsheet_columns) {
    fifu_dev_set_image_list($post_id, fifu_sheet_editor_normalize($urls));
}

function fifu_sheet_editor_normalize($urls) {
    // Normalize line endings and remove spaces
    $cleaned = str_replace(["\r\n", "\r"], "\n", $urls);
    $cleaned = str_replace(' ', '', $cleaned);

    // Find positions of all "http" occurrences
    preg_match_all('/\bhttp/i', $cleaned, $matches, PREG_OFFSET_CAPTURE);
    $delimiter_counts = [',' => 0, '|' => 0, "\n" => 0];

    foreach ($matches[0] as $index => $match) {
        if ($index === 0)
            continue; // Skip first URL

        $pos = $match[1];
        if ($pos === 0)
            continue; // Prevent negative offset

        $prev_char = $cleaned[$pos - 1];
        if (isset($delimiter_counts[$prev_char])) {
            $delimiter_counts[$prev_char]++;
        }
    }

    // Determine delimiter (prioritize \n if counts are equal)
    arsort($delimiter_counts);
    $detected_delimiter = array_key_first($delimiter_counts);

    // Split using positive lookahead for "http"
    $split_regex = '/(?=' . preg_quote($detected_delimiter, '/') . '?http)/i';
    $url_list = preg_split($split_regex, $cleaned, -1, PREG_SPLIT_NO_EMPTY);

    // Clean residual delimiters and join
    $url_list = array_map(function ($url) use ($detected_delimiter) {
        return ltrim($url, $detected_delimiter);
    }, $url_list);

    return implode('|', $url_list);
}

function fifu_sheet_editor_blacklist_column($columns, $post_type) {
    if ($post_type === 'product' && class_exists('WooCommerce')) {
        $columns[] = 'fifu_image_url';
        $columns[] = 'fifu_image_alt';
    } else {
        $columns[] = 'fifu_audio_url';
        $columns[] = 'fifu_video_url';
        $columns[] = 'fifu_redirection_url';
        $columns[] = 'fifu_search_proxy';
        $columns[] = 'fifu_tags_sent';
        $columns[] = 'fifu_custom_video_url';
    }
    return $columns;
}

// Remove original action using the singleton instance
add_action('plugins_loaded', function () {
    if (!class_exists('WPSE_Featured_Image_From_Url'))
        return;

    $original_instance = WPSE_Featured_Image_From_Url_Obj();

    remove_action(
            'vg_sheet_editor/editor/register_columns',
            array($original_instance, 'register_columns'),
            10
    );
}, 20);

