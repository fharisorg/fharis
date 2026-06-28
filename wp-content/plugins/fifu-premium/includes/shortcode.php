<?php

function fifu_shortcode_id($atts) {
    if (isset($atts['post_id']))
        return $atts['post_id'];

    global $post;
    return $post->ID ?? null;
}

function fifu_shortcode_term_id($atts) {
    return $atts['term_id'] ?? fifu_ctgr_get_term_id();
}

function fifu_get_attr_src($post_id) {
    $src = fifu_main_image_url($post_id, true);
    if (!$src)
        return "";

    $src = fifu_get_cdn_url($src, get_post_thumbnail_id($post_id), null, null);

    fifu_add_parameters_single_post($post_id);

    // Escape URL for safe HTML attribute output
    $src = esc_url($src);
    return " src=\"{$src}\"";
}

function fifu_get_attr_alt($post_id) {
    $image_alt = get_post_meta($post_id, 'fifu_image_alt', true);
    if (!$image_alt)
        return "";

    // Sanitize and escape alt text
    $image_alt = esc_attr(wp_strip_all_tags($image_alt));
    return " alt=\"{$image_alt}\"";
}

function fifu_get_attr_width($atts) {
    if (!isset($atts['width']))
        return "";

    $width = esc_attr($atts['width']);

    return " width=\"{$width}\"";
}

function fifu_get_attr_height($atts) {
    if (!isset($atts['height']))
        return "";

    $height = esc_attr($atts['height']);

    return " height=\"{$height}\"";
}

function fifu_get_attr_style($atts) {
    if (!isset($atts['style']))
        return "";

    // Escape style attribute content
    $style = esc_attr($atts['style']);

    return " style=\"{$style}\"";
}

// [fifu post_id="123"]
function fifu_shortcode_main_url($atts) {
    $post_id = fifu_shortcode_id($atts);

    return '<img ' . fifu_get_attr_src($post_id) . fifu_get_attr_alt($post_id) . fifu_get_attr_width($atts) . fifu_get_attr_height($atts) . fifu_get_attr_style($atts) . '>';
}

add_shortcode('fifu', 'fifu_shortcode_main_url');

// [fifu_slider post_id="123"]
function fifu_shortcode_slider($atts) {
    $width = $atts['width'] ?? null;
    $height = $atts['height'] ?? null;
    return fifu_slider_get_html(fifu_shortcode_id($atts), null, null, null, $width, $height);
}

add_shortcode('fifu_slider', 'fifu_shortcode_slider');

// [fifu_gallery post_id="123"]
function fifu_shortcode_gallery($atts) {
    fifu_add_lightslider(true);
    return fifu_gallery_get_html(
            fifu_shortcode_id($atts), null,
            'fifu-woo-gallery',
            ''
    );
}

add_shortcode('fifu_gallery', 'fifu_shortcode_gallery');

// [fifu_form_image post_id="123"]
function fifu_shortcode_form_image($atts) {
    $strings = fifu_get_strings_shortcode();
    $placeholder = $strings['placeholder']['image']();
    $label = $strings['label']['image']();

    $post_id = fifu_shortcode_id($atts);

    if (!$post_id)
        return;

    return ("
        <script>
            jQuery(document).ready(function ($) {
                jQuery('#fifu-form-input-image-url').on('change', function () {
                    url = jQuery(this).val();
                    fifuSetImageUrl(url);
                });
            });

            function fifuSetImageUrl(url) {
                jQuery.ajax({
                    method: 'POST',
                    url: fifuImageVars.fifu_rest_url + 'fifu-premium/v2/form-set-image-url/',
                    data: {
                        'image_url': url,
                        'post_id': {$post_id}
                    },
                    async: true,
                    beforeSend: function (xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', fifuImageVars.fifu_nonce);
                    },
                    success: function (data) {
                        data = JSON.parse(data);
                    },
                    error: function (jqXHR, textStatus, errorThrown) {
                        console.log(jqXHR);
                        console.log(textStatus);
                        console.log(errorThrown);
                    }
                });
            }
        </script>

        <div id='fifu-form-image'>
            <form action='javascript:void(0)'>
                <!--label for='fifu-form-input-image-url'>{$label}</label-->
                <input type='text' id='fifu-form-input-image-url' name='fifu-form-input-image-url' placeholder='{$placeholder}'>
            </form>
        </div>
    ");
}

add_shortcode('fifu_form_image', 'fifu_shortcode_form_image');

add_action('rest_api_init', function () {
    if (fifu_is_on('fifu_shortform')) {
        register_rest_route('fifu-premium/v2', '/form-set-image-url/', array(
            'methods' => 'POST',
            'callback' => 'fifu_api_form_save_image_url',
            'permission_callback' => 'fifu_is_user_logged_in',
        ));
    }
});

function fifu_is_user_logged_in() {
    return is_user_logged_in();
}

function fifu_api_form_save_image_url(WP_REST_Request $request) {
    $post_id = $request['post_id'] ?? null;
    $image_url = $request['image_url'] ?? null;
    fifu_dev_set_image($post_id, $image_url);
    return json_encode(array());
}

// https://developer.wordpress.org/reference/functions/current_user_can/
// https://developer.wordpress.org/reference/functions/map_meta_cap/
// [fifu_taxonomy]
function fifu_taxonomy_shortcode($atts) {
    // Ensure the post is in the loop to avoid errors
    if (!in_the_loop() || fifu_is_off('fifu_taxonomy')) {
        return '';
    }

    // Get current post ID
    $post_id = get_the_ID();

    // Check if a slug is provided
    if (empty($atts['slug'] ?? '')) {
        return 'Taxonomy slug not specified.';
    }

    // Get terms associated with the current post and specified taxonomy
    $terms = get_the_terms($post_id, $atts['slug']);

    // Check if there are any terms associated
    if (!$terms || is_wp_error($terms)) {
        return 'No terms found for this taxonomy.';
    }

    // Initialize output
    $output = '';

    // Loop through terms and append image tags
    foreach ($terms as $term) {
        $term_id = $term->term_id;
        $url = fifu_ctgr_get_url($term_id);
        if (fifu_is_on('fifu_photon') && isset($atts['width'])) {
            $width = $atts['width'];
            $height = $atts['height'] ?? null;
            $url = fifu_resize_with_photon($url, $width, $height, null, fifu_get_term_thumbnail_id($term_id), null);
        }

        $image_attributes = '<img ' .
                'src="' . $url . '" ' .
                'alt="' . fifu_ctgr_get_alt($term_id) . '" ' .
                fifu_get_attr_width($atts) .
                fifu_get_attr_height($atts) .
                fifu_get_attr_style($atts) .
                '>';
        $output .= $image_attributes;
    }

    return $output;
}

add_shortcode('fifu_taxonomy', 'fifu_taxonomy_shortcode');

