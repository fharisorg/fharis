<?php

add_action('admin_init', 'fifu_add_fields_to_all_taxonomies');

function fifu_add_fields_to_all_taxonomies() {
    if (fifu_is_off('fifu_taxonomy'))
        return;

    $taxonomies = get_taxonomies();
    foreach ($taxonomies as $taxonomy) {
        if ($taxonomy !== 'product_cat') {
            add_action("{$taxonomy}_add_form_fields", 'fifu_add_fields_to_taxonomy');
            add_action("{$taxonomy}_edit_form_fields", 'fifu_edit_fields_taxonomy');
            add_action("created_{$taxonomy}", 'fifu_save_taxonomy_meta', 10, 2);
            add_action("edited_{$taxonomy}", 'fifu_save_taxonomy_meta', 10, 2);
        }
    }
}

function fifu_add_fields_to_taxonomy($taxonomy) {
    fifu_ctgr_add_box();
}

function fifu_edit_fields_taxonomy($term) {
    fifu_ctgr_edit_box($term);
}

function fifu_save_taxonomy_meta($term_id) {
    fifu_ctgr_save_properties($term_id);
}

add_filter('get_the_archive_description', function ($description) {
    if (is_tax('product_cat'))
        return $description;

    $image_url = fifu_ctgr_get_url(null);

    if (empty($image_url))
        return $description;

    $image_html = '<img src="' . esc_url($image_url) . '" alt="" style="max-width:100%; height:auto;">';

    return $image_html . $description;
});

