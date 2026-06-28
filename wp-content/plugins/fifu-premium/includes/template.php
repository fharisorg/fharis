<?php

global $post;

if (!isset($post) || !isset($post->ID)) {
    return;
}

if (function_exists('fifu_is_rey_active') && fifu_is_rey_active() && function_exists('fifu_gallery_get_html')) {
    echo fifu_gallery_get_html(
            $post->ID, null,
            'fifu-woo-gallery woocommerce-product-gallery--with-images woocommerce-product-gallery--columns-4 images',
            ''
    );
} elseif (function_exists('fifu_is_blocksy_active') && fifu_is_blocksy_active()) {
    add_action('woocommerce_after_template_part', function ($template_name, $template_path, $located, $args) {
        if ($template_name !== 'single-product/product-image.php') {
            return;
        }
        ob_get_clean();

        global $post;
        if (!isset($post) || !isset($post->ID)) {
            return;
        }
        if (function_exists('fifu_gallery_get_html')) {
            echo fifu_gallery_get_html(
                    $post->ID, null,
                    'fifu-woo-gallery woocommerce-product-gallery woocommerce-product-gallery--with-images woocommerce-product-gallery--columns-4 images',
                    ''
            );
        }
    }, 5, 99);
} elseif (function_exists('fifu_gallery_get_html')) {
    echo fifu_gallery_get_html(
            $post->ID, null,
            'fifu-woo-gallery woocommerce-product-gallery woocommerce-product-gallery--with-images woocommerce-product-gallery--columns-4 images',
            ''
    );
}
