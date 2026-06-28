<?php

define('FIFU_PLACEHOLDER', 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

add_filter('wp_head', 'fifu_add_js');

global $pagenow;
if (!isset($pagenow) || !in_array($pagenow, array('post.php', 'post-new.php', 'admin-ajax.php', 'wp-cron.php'))) {
    if (fifu_is_yoast_seo_active()) {
        add_action('wpseo_opengraph_image', 'fifu_add_social_tag_yoast');
        add_action('wpseo_twitter_image', 'fifu_add_social_tag_yoast');
        add_action('wpseo_add_opengraph_images', 'fifu_add_social_tag_yoast_list');
    } else
        add_filter('wp_head', 'fifu_add_social_tags');
    add_filter('wp_head', 'fifu_video_add_social_tags');
    // Always handle FIFU structured data (image-only when SEO plugin is active)
    add_action('wp_head', 'fifu_add_structured_data', 99);
}

add_action('wp_head', 'fifu_home_add_social_tags', 9999);

add_filter('wp_head', 'fifu_add_lightslider');
add_filter('wp_head', 'fifu_add_video');
add_filter('wp_head', 'fifu_apply_css');

function fifu_add_js() {
    if (fifu_is_amp_request())
        return;

    if (fifu_su_sign_up_complete()) {
        echo '<link rel="preconnect" href="https://cloud.fifu.app">';
        echo '<link rel="preconnect" href="https://cdn.fifu.app">';
    }

    if (fifu_is_on('fifu_photon')) {
        if (fifu_is_on('fifu_otfcdn')) {
            $prefix = fifu_is_on('fifu_own_domain') ? "//img." : "//i0.fifu.app/";
            $href = str_replace("//", $prefix, get_home_url());
            echo "<link rel='dns-prefetch' href='{$href}'>";
            echo "<link rel='preconnect' href='{$href}'>";
        }

        echo "<link rel='dns-prefetch' href='https://wp.fifu.app/'>";
        echo "<link rel='preconnect' href='https://wp.fifu.app/' crossorigin>";
    }

    if (fifu_is_on('fifu_slider') || (fifu_is_on('fifu_gallery') && class_exists('WooCommerce') && is_product())) {
        wp_register_style('fifu-slider-style', plugins_url('/html/css/slider.css', __FILE__), array(), fifu_version_number_enq());
        wp_enqueue_style('fifu-slider-style');
        if (get_option('fifu_slider_left') || get_option('fifu_slider_right')) {
            wp_register_style('fifu-slider-custom-arrows', plugins_url('/html/css/slider-custom-arrows.css', __FILE__), array(), fifu_version_number_enq());
            wp_enqueue_style('fifu-slider-custom-arrows');
        }
    }

    if (class_exists('WooCommerce')) {
        wp_register_style('fifu-woo', plugins_url('/html/css/woo.css', __FILE__), array(), fifu_version_number_enq());
        wp_enqueue_style('fifu-woo');
        wp_add_inline_style('fifu-woo', 'img.zoomImg {display:' . fifu_woo_zoom() . ' !important}');
    }

    if (fifu_is_on('fifu_mouse_video') || fifu_is_on("fifu_video_play_button") || fifu_is_on('fifu_autoplay')) {
        wp_enqueue_script('youtube', 'https://www.youtube.com/iframe_api');
        wp_enqueue_script('fifu-vimeo-player', 'https://player.vimeo.com/api/player.js');
    }

    if (fifu_is_on('fifu_video'))
        wp_enqueue_style('dashicons');

    if (fifu_is_on('fifu_buy') && class_exists('WooCommerce') && (is_shop() || is_product_category()) && !is_cart()) {
        wp_enqueue_style('fancy-box-css', 'https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.css');
        wp_enqueue_script('fancy-box-js', 'https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.min.js');
        if (!wp_script_is('lightgallery')) {
            wp_enqueue_style('lightgallery-style', 'https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.8.3/css/lightgallery.min.css');
            wp_enqueue_script('lightgallery', 'https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.8.3/lightgallery.min.js');
            wp_enqueue_style('lightgallery-thumb-style', 'https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.8.3/css/lg-thumbnail.min.css');
            wp_enqueue_script('lightgallery-thumb', 'https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.8.3/plugins/thumbnail/lg-thumbnail.min.js');
            wp_enqueue_style('lightgallery-zoom-style', 'https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.8.3/css/lg-zoom.min.css');
            wp_enqueue_script('lightgallery-zoom', 'https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.8.3/plugins/zoom/lg-zoom.min.js');
            wp_enqueue_style('lightgallery-video-style', 'https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.8.3/css/lg-video.min.css');
            wp_enqueue_script('lightgallery-video', 'https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.8.3/plugins/video/lg-video.min.js');
        }
        wp_register_style('fifu-lightbox-style', plugins_url('/html/css/lightbox.css', __FILE__), array(), fifu_version_number_enq());
        wp_enqueue_style('fifu-lightbox-style');
        wp_enqueue_script('fifu-lightbox-js', plugins_url('/html/js/lightbox.js', __FILE__), array('jquery'), fifu_version_number_enq());
        wp_localize_script('fifu-lightbox-js', 'fifuLgVars', [
            'fifu_key_lightgallery' => get_option('fifu_key_lightgallery'),
        ]);
        if (!wp_style_is('dashicons'))
            wp_enqueue_style('dashicons');
    }

    $main_image_url = fifu_main_image_url(get_queried_object_id(), true);
    $base64_main_image_url = $main_image_url && fifu_is_on('fifu_otfcdn') ? fifu_base64($main_image_url) : null;

    // wp_enqueue_script('fifu-lighthouse-js', plugins_url('/html/js/lighthouse.js', __FILE__), array(), fifu_version_number_enq());
    // js
    if (fifu_is_on("fifu_slider") || get_option('fifu_error_url') || fifu_is_flatsome_active() || fifu_is_on("fifu_block") || fifu_is_on('fifu_redirection') || ((fifu_is_on("fifu_gallery") || !fifu_woo_lbox()) && class_exists('WooCommerce') && is_product())) {
        wp_enqueue_script('fifu-image-js', plugins_url('/html/js/image.js', __FILE__), array('jquery'), fifu_version_number_enq());
        wp_localize_script('fifu-image-js', 'fifuImageVars', [
            'fifu_slider' => fifu_is_on("fifu_slider") || (fifu_is_on("fifu_gallery") && class_exists('WooCommerce') && is_product()),
            'fifu_slider_vertical' => fifu_is_on('fifu_slider_vertical'),
            'fifu_is_front_page' => is_front_page() || is_home(),
            'fifu_woo_lbox_enabled' => fifu_woo_lbox(),
            'fifu_error_url' => get_option('fifu_error_url'),
            'fifu_is_flatsome_active' => fifu_is_flatsome_active(),
            'fifu_block' => fifu_is_on("fifu_block"),
            'fifu_redirection' => fifu_is_on('fifu_redirection'),
            'fifu_forwarding_url' => get_post_meta(get_queried_object_id(), 'fifu_redirection_url', true),
            'fifu_main_image_url' => $main_image_url,
            'base64_main_image_url' => $base64_main_image_url,
            'fifu_local_image_url' => get_the_post_thumbnail_url(get_the_ID(), 'full'),
        ]);
    }

    if (class_exists('WooCommerce') && is_product() && fifu_is_off('fifu_gallery')) {
        wp_enqueue_script('fifu-photoswipe-fix', plugins_url('/html/js/photoswipe-fix.js', __FILE__), array('jquery'), fifu_version_number_enq());
        wp_localize_script('fifu-photoswipe-fix', 'fifuSwipeVars', [
            'theme' => get_option('template'),
        ]);
    }

    if (fifu_is_on("fifu_popup")) {
        wp_register_style('fifu-popup-css', plugins_url('/html/css/popup.css', __FILE__), array(), fifu_version_number_enq());
        wp_enqueue_style('fifu-popup-css');
        wp_enqueue_script('fifu-popup-js', plugins_url('/html/js/popup.js', __FILE__), array('jquery'), fifu_version_number_enq());
        if (!wp_script_is('fancy-box-js')) {
            wp_enqueue_style('fancy-box-css', 'https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.css');
            wp_enqueue_script('fancy-box-js', 'https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.min.js');
        }
    }
}

function fifu_add_social_tag_yoast($image_url) {
    if (get_post_meta(get_the_ID(), '_yoast_wpseo_opengraph-image', true) || get_post_meta(get_the_ID(), '_yoast_wpseo_twitter-image', true))
        return $image_url;
    $url = fifu_main_image_url(get_the_ID(), true);
    return $url ? $url : $image_url;
}

function fifu_add_social_tag_yoast_list($object) {
    if (get_post_meta(get_the_ID(), '_yoast_wpseo_opengraph-image', true) || get_post_meta(get_the_ID(), '_yoast_wpseo_twitter-image', true))
        return;
    $object->add_image(fifu_main_image_url(get_the_ID(), true));
}

// General ld+json output: when an SEO plugin is active we emit only ImageObject
// Otherwise we emit minimal BlogPosting/Product with images
function fifu_add_structured_data() {
    // Keep behavior consistent with other meta handling
    if (is_front_page() || is_home() || is_tax())
        return;

    $post_id = get_the_ID();
    if (!$post_id)
        return;

    global $wpdb;
    $arr = $wpdb->get_col($wpdb->prepare(
                    "SELECT meta_value FROM $wpdb->postmeta WHERE post_id = %d AND meta_key LIKE %s",
                    $post_id,
                    'fifu_%image_url%'
            ));

    if (empty($arr))
        return;

    $type = get_post_type($post_id);
    if (!empty($arr) && is_singular($type))
        fifu_render_structured_data($post_id, $arr);
}

function fifu_add_social_tags() {
    if (is_front_page() || is_home() || is_tax())
        return;

    $post_id = get_the_ID();
    $title = str_replace("'", "&#39;", strip_tags(get_the_title($post_id)));
    $description = str_replace("'", "&#39;", wp_strip_all_tags(get_post_field('post_excerpt', $post_id)));

    global $wpdb;
    $arr = $wpdb->get_col($wpdb->prepare("SELECT meta_value FROM $wpdb->postmeta WHERE post_id = %d AND meta_key LIKE %s", $post_id, 'fifu_%image_url%'));

    if (empty($arr))
        return;

    foreach ($arr as $url) {
        if ($url) {
            if (fifu_is_from_speedup($url))
                $url = fifu_speedup_get_signed_url($url, 1280, 672, null, null, false);
            elseif (fifu_is_on('fifu_photon')) {
                $url = fifu_jetpack_photon_url($url, null, get_post_thumbnail_id($post_id));
            }
            include 'html/og-image.html';
        }
    }

    foreach ($arr as $url) {
        if ($url) {
            if (fifu_is_from_speedup($url))
                $url = fifu_speedup_get_signed_url($url, 1280, 672, null, null, false);
            elseif (fifu_is_on('fifu_photon')) {
                $url = fifu_jetpack_photon_url($url, null, get_post_thumbnail_id($post_id));
            }
            include 'html/twitter-image.html';
        }
    }
}

function fifu_video_add_social_tags() {
    if (is_front_page() || is_home())
        return;

    $post_id = get_the_ID();
    $url = get_post_meta($post_id, 'fifu_video_url', true);
    $title = str_replace("'", "&#39;", strip_tags(get_the_title($post_id)));
    $description = str_replace("'", "&#39;", str_replace('"', '&#34;', wp_strip_all_tags(get_post_field('post_excerpt', $post_id))));
    $video_id = fifu_video_id($url);

    $video_src = fifu_video_src($url);
    $video_img = fifu_video_social_img($url);

    $video_url = $video_id == null ? $url : fifu_video_social_url($video_id);

    if ($url) {
        if (fifu_is_from_speedup($video_img))
            $video_img = fifu_speedup_get_signed_url($video_img, 1280, 672, null, null, true);
        include 'html/social-video.html';
    }
}

function fifu_home_add_social_tags() {
    if (is_front_page()) {
        $url = get_option('fifu_default_url');
        if (!empty($url)) {
            $buffer_contents = ob_get_contents();
            if ($buffer_contents !== false && strpos($buffer_contents, '<meta property="og:image"') === false) {
                $url = esc_url($url);
                include 'html/social-home.html';
            }
        }
    }
}

function fifu_add_lightslider($is_shortcode = false) {
    $is_product = class_exists('WooCommerce') && is_product();

    if (fifu_is_houzez_active() || fifu_is_wpresidence_active())
        return;

    if (fifu_is_on('fifu_slider') || ($is_product && fifu_is_on('fifu_gallery')) || $is_shortcode) {
        // slider
        wp_enqueue_script('fifu-lightslider', plugins_url('/html/js/lightslider.js', __FILE__), array('jquery'), fifu_version_number_enq());
        wp_localize_script('fifu-lightslider', 'fifuMainSliderVars', [
            'fifu_error_url' => get_option('fifu_error_url'),
            'fifu_slider_crop' => fifu_is_on('fifu_slider_crop'),
            'fifu_slider_vertical' => fifu_is_on('fifu_slider_vertical'),
            'fifu_is_product' => $is_product,
            'fifu_is_front_page' => is_front_page(),
            'fifu_adaptive_height' => fifu_is_on("fifu_adaptive_height"),
        ]);
        wp_enqueue_script('jquery-zoom', 'https://cdnjs.cloudflare.com/ajax/libs/jquery-zoom/1.7.21/jquery.zoom.min.js');

        // css
        wp_enqueue_style('lightslider-style', 'https://cdnjs.cloudflare.com/ajax/libs/lightslider/1.1.6/css/lightslider.min.css');

        // js
        wp_enqueue_script('fifu-slider-js', plugins_url('/html/js/lightsliderConfig.js', __FILE__), array('jquery'), fifu_version_number_enq());
        wp_localize_script('fifu-slider-js', 'fifuSliderVars', [
            'fifu_slider_speed' => get_option('fifu_slider_speed'),
            'fifu_slider_auto' => fifu_is_on('fifu_slider_auto'),
            'fifu_slider_pause' => get_option('fifu_slider_pause'),
            'fifu_slider_ctrl' => fifu_is_on('fifu_slider_ctrl'),
            'fifu_slider_stop' => fifu_is_on('fifu_slider_stop'),
            'fifu_slider_gallery' => fifu_is_on('fifu_slider_gallery') || ($is_product && fifu_woo_lbox()),
            'fifu_slider_thumb' => fifu_is_on('fifu_slider_thumb'),
            'fifu_slider_counter' => fifu_is_on('fifu_slider_counter'),
            'fifu_slider_crop' => fifu_is_on('fifu_slider_crop'),
            'fifu_slider_vertical' => fifu_is_on('fifu_slider_vertical'),
            'fifu_slider_left' => get_option('fifu_slider_left'),
            'fifu_slider_right' => get_option('fifu_slider_right'),
            'fifu_is_product' => $is_product,
            'fifu_adaptive_height' => fifu_is_on("fifu_adaptive_height"),
            'fifu_url' => fifu_main_image_url(get_the_ID(), true),
            'fifu_error_url' => get_option('fifu_error_url'),
            'fifu_video' => fifu_is_on('fifu_video'),
            'fifu_is_mobile' => wp_is_mobile(),
            'fifu_wc_zoom' => fifu_is_on('fifu_wc_zoom'),
            'fifu_key_lightgallery' => get_option('fifu_key_lightgallery'),
            'pager' => fifu_is_on('fifu_slider_thumb') || (fifu_is_off('fifu_slider_thumb') && $is_product),
        ]);

        // gallery
        fifu_add_lightgallery($is_product);
    }
}

function fifu_add_lightgallery($is_product = false) {
    if (!wp_script_is('lightgallery')) {
        wp_enqueue_style('lightgallery-thumb-style', 'https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.8.3/css/lg-thumbnail.min.css');
        wp_enqueue_script('lightgallery-thumb', 'https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.8.3/plugins/thumbnail/lg-thumbnail.min.js');
        if ($is_product) {
            wp_enqueue_style('lightgallery-zoom-style', 'https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.8.3/css/lg-zoom.min.css');
            wp_enqueue_script('lightgallery-zoom', 'https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.8.3/plugins/zoom/lg-zoom.min.js');
        }
        wp_enqueue_style('lightgallery-video-style', 'https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.8.3/css/lg-video.min.css');
        wp_enqueue_script('lightgallery-video', 'https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.8.3/plugins/video/lg-video.min.js');
        wp_enqueue_style('lightgallery-style', 'https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.8.3/css/lightgallery.min.css');
        wp_enqueue_script('lightgallery', 'https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.8.3/lightgallery.min.js'); // to debug, replace min with umd
    }
}

function fifu_add_video() {
    $strings = fifu_get_strings_video();

    if (fifu_is_on('fifu_video') || fifu_is_on('fifu_audio')) {
        // css
        wp_register_style('fifu-video-css', plugins_url('/html/css/video.css', __FILE__), array(), fifu_version_number_enq());
        wp_enqueue_style('fifu-video-css');

        // Dynamic CSS
        if (fifu_is_shop() && fifu_is_avada_active()) {
            $inline_style1 = '.fifu_play {width: 100%; height: inherit; position: absolute; display: contents;}';
        } else {
            $inline_style1 = '.fifu_play {position: relative; width: 100%; z-index:' . get_option('fifu_video_zindex') . '; /* no zoom */}';
        }
        $inline_style2 = '.fifu_play .fifubtn:hover {background-color: ' . get_option('fifu_video_color') . '; opacity: 0.9;}';
        $inline_style3 = '.fifu_play_bg:hover {background-color: ' . get_option('fifu_video_color') . '; opacity: 0.9;}';
        $play_button_size = get_option('fifu_video_size');
        $inline_style4 = '.fifu_play .fifubtn {font-size: ' . $play_button_size . 'px; padding: ' . fifu_get_play_button_padding($play_button_size) . '}';
        wp_add_inline_style('fifu-video-css', $inline_style1);
        wp_add_inline_style('fifu-video-css', $inline_style2);
        wp_add_inline_style('fifu-video-css', $inline_style3);
        wp_add_inline_style('fifu-video-css', $inline_style4);

        // Side button        
        $side_button_position = fifu_is_on("fifu_video_later_left") ? "left" : "right";
        wp_add_inline_style('fifu-video-css', ".fifu_play .icon {opacity: 0.7; color: white; background-color: transparent; position: absolute; top: 5px; {$side_button_position}: 5px; font-size: 32px;}");

        // fancy-box
        if (get_option('fifu_play_type') == 'lightbox') {
            wp_enqueue_style('fancy-box-css', 'https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.css');
            wp_enqueue_script('fancy-box-js', 'https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.min.js');
        }

        // js
        wp_enqueue_script('fifu-video-js', plugins_url('/html/js/video.js', __FILE__), array('jquery'), fifu_version_number_enq());
        wp_localize_script('fifu-video-js', 'fifuVideoVars', [
            'fifu_is_flatsome_active' => fifu_is_flatsome_active(),
            'fifu_is_photolio_active' => fifu_is_photolio_active(),
            'fifu_is_content_views_pro_active' => fifu_is_content_views_pro_active(),
            'fifu_is_home' => (is_home() || (class_exists("WooCommerce") && is_shop()) || is_archive() || is_search()),
            'fifu_is_shop' => class_exists("WooCommerce") && is_shop(),
            'fifu_is_product_category' => class_exists("WooCommerce") && is_product_category(),
            'fifu_is_page' => is_page(),
            'fifu_is_post' => is_singular('post'),
            'fifu_video_thumb_display_home' => fifu_video_thumb_display_home(),
            'fifu_video_thumb_display_page' => fifu_video_thumb_display_page(),
            'fifu_video_thumb_display_post' => fifu_video_thumb_display_post(),
            'fifu_video_thumb_display_cpt' => fifu_video_thumb_display_cpt(),
            'fifu_video_min_width' => get_option('fifu_video_min_width'),
            'fifu_is_home_or_shop' => fifu_is_home_or_shop(),
            'fifu_is_front_page' => is_front_page(),
            'fifu_video_controls' => fifu_is_on("fifu_video_controls"),
            'fifu_mouse_video_enabled' => fifu_mouse_video_enabled(),
            'fifu_loop_enabled' => fifu_loop_enabled(),
            'fifu_autoplay_enabled' => fifu_autoplay_enabled(),
            'fifu_autoplay_front_enabled' => fifu_autoplay_front_enabled(),
            'fifu_autoplay_elsewhere_enabled' => fifu_autoplay_elsewhere_enabled(),
            'fifu_video_mute_enabled' => fifu_video_mute_enabled(),
            'fifu_video_mute_mobile_enabled' => fifu_video_mute_mobile_enabled(),
            'fifu_video_background_enabled' => fifu_video_background_enabled(),
            'fifu_video_background_single_enabled' => fifu_is_on('fifu_video_background_single'),
            'fifu_video_gallery_icon_enabled' => fifu_is_on('fifu_video_gallery_icon'),
            'fifu_is_elementor_active' => fifu_is_elementor_active(),
            'fifu_woocommerce' => class_exists("WooCommerce"),
            'fifu_is_divi_active' => fifu_is_divi_active(),
            'fifu_essential_grid_active' => fifu_is_essential_grid_active(),
            'fifu_is_product' => class_exists('WooCommerce') && is_product(),
            'fifu_adaptive_height' => fifu_is_on("fifu_adaptive_height"),
            'fifu_play_button_enabled' => fifu_is_on("fifu_video_play_button"),
            'fifu_play_hide_grid' => fifu_is_on('fifu_video_play_hide_grid'),
            'fifu_play_hide_grid_wc' => fifu_is_on('fifu_video_play_hide_grid_wc'),
            'fifu_url' => fifu_main_image_url(get_queried_object_id(), true),
            'fifu_is_play_type_inline' => get_option('fifu_play_type') == 'inline',
            'fifu_is_play_type_lightbox' => get_option('fifu_play_type') == 'lightbox',
            'fifu_video_color' => get_option('fifu_video_color'),
            'fifu_video_zindex' => get_option('fifu_video_zindex'),
            'fifu_should_hide' => fifu_should_hide(),
            'fifu_should_wait_ajax' => fifu_should_wait_ajax(),
            'fifu_is_mobile' => wp_is_mobile(),
            'fifu_privacy_enabled' => fifu_is_on("fifu_video_privacy"),
            'fifu_later_enabled' => fifu_is_on("fifu_video_later"),
            'fifu_woo_lbox_enabled' => fifu_woo_lbox(),
            'text_later' => $strings['button']['later'](),
            'text_queue' => $strings['button']['queue'](),
            'restUrl' => esc_url_raw(rest_url()),
            'session' => md5(session_id()),
            'uploadDir' => fifu_get_upload_dir(),
            'otfcdn' => fifu_is_on('fifu_otfcdn'),
        ]);
    }

    if (fifu_is_on("fifu_video_later")) {
        wp_enqueue_script('fifu-cookie', 'https://cdnjs.cloudflare.com/ajax/libs/js-cookie/latest/js.cookie.min.js');
        wp_enqueue_script('fifu-watch-later-js', plugins_url('/html/js/watch-later.js', __FILE__), array('jquery'), fifu_version_number_enq());
        wp_localize_script('fifu-watch-later-js', 'fifuLgVars', [
            'fifu_key_lightgallery' => get_option('fifu_key_lightgallery'),
        ]);
        if (!wp_script_is('fancy-box-js')) {
            wp_enqueue_style('fancy-box-css', 'https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.css');
            wp_enqueue_script('fancy-box-js', 'https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.min.js');
        }
        fifu_add_lightgallery(false);
    }
}

function fifu_get_play_button_padding($fontSize) {
    if ($fontSize > 20 && $fontSize < 50)
        return "2px 0px 2px 5px";
    elseif ($fontSize <= 20)
        return "1px 0px 1px 3px";
    return "3px 0px 3px 7px";
}

function fifu_apply_css() {
    if (fifu_is_off('fifu_wc_lbox'))
        echo '<style>[class$="woocommerce-product-gallery__trigger"] {display:none !important;}</style>';
}

add_filter('wp_get_attachment_image_attributes', 'fifu_wp_get_attachment_image_attributes', 10, 3);

function fifu_wp_get_attachment_image_attributes($attr, $attachment, $size) {
    global $FIFU_SESSION;

    // ignore themes
    if (in_array(strtolower(get_option('template')), array('jnews')))
        return $attr;

    if (!isset($attr['src']))
        return $attr;

    $url = $attr['src'];
    if (strpos($url, 'cdn.fifu.app') === false)
        return $attr;

    // "all products" page
    $current_screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ($current_screen && ($current_screen->parent_file ?? '') == 'edit.php?post_type=product') {
        $attr['src'] = fifu_optimized_column_image($url, $attachment->ID ?? 0);
        return $attr;
    }

    $sizes = fifu_speedup_get_sizes($url);
    $width = $sizes[0] ?? 0;
    $height = $sizes[1] ?? 0;
    $is_video = $sizes[2] ?? false;
    $clean_url = $sizes[3] ?? null;
    $attr['src'] = fifu_speedup_get_signed_url($url, $width, $height, null, null, false);
    $attr['loading'] = 'lazy';
    $attr['srcset'] = fifu_speedup_get_set($url);

    return $attr;
}

add_filter('woocommerce_product_get_image', 'fifu_woo_replace', 10, 5);

function fifu_woo_replace($html, $product, $woosize, $attr, $placeholder) {
    if (empty($product) || !is_object($product))
        return $html;
    return fifu_replace($html, $product->get_id(), null, null, null);
}

add_filter('post_thumbnail_html', 'fifu_replace', 10, 5);

function fifu_replace($html, $post_id, $post_thumbnail_id, $size, $attr = null) {
    global $FIFU_SESSION;

    if (!$html)
        return $html;

    $width = fifu_get_attribute('width', $html);
    $height = fifu_get_attribute('height', $html);
    $original_class = fifu_get_attribute('class', $html);
    $delimiter = fifu_get_delimiter('src', $html);

    $videoUrl = get_post_meta($post_id, 'fifu_video_url', true);
    if (fifu_is_on('fifu_video') && $videoUrl) {
        $alt = esc_attr(strip_tags(get_the_title($post_id)));
        $html = preg_replace('/alt=[\'\"][^[\'\"]*[\'\"]/', 'alt=' . $delimiter . $alt . $delimiter . ' title=' . $delimiter . $alt . $delimiter, $html);
        return $html;
    }

    $src = fifu_get_attribute('src', $html);
    if (isset($FIFU_SESSION) && isset($FIFU_SESSION[$src])) {
        $data = $FIFU_SESSION[$src];
        if (strpos($html, 'fifu-replaced') !== false)
            return $html;
    }

    if (!fifu_is_houzez_active() && !fifu_is_wpresidence_active()) {
        $sliderUrl = get_post_meta($post_id, 'fifu_slider_image_url_0', true);
        if ($sliderUrl && fifu_is_on('fifu_slider')) {
            if ($width && (int) $width < 175)
                return $html;
            if (class_exists('WooCommerce') && is_product())
                return $html;
            if (fifu_show_slider($sliderUrl) && !fifu_on_cpt_page())
                return fifu_slider_get_html($post_id, $original_class, null, null, $width, $height);
            return $html;
        }
    } else
        $sliderUrl = null;

    $url = get_post_meta($post_id, 'fifu_image_url', true);

    $title = null;
    if (fifu_is_on('fifu_redirection') && fifu_is_on('fifu_auto_set')) {
        $strings = fifu_get_strings_image();
        $redirection_url = get_post_meta($post_id, 'fifu_redirection_url', true);
        if (!empty($redirection_url)) {
            $redirection_parts = explode('/', $redirection_url);
            $redirection_domain = $redirection_parts[2] ?? null;
            if ($redirection_domain)
                $title = $strings['photo']['credit']() . ': ' . $redirection_domain;
        }
    }

    $alt = get_post_meta($post_id, 'fifu_image_alt', true);
    if (!$alt) {
        $alt = esc_attr(strip_tags(get_the_title($post_id)));
        $title = esc_attr($title ? $title : $alt);
        $custom_alt = 'alt=' . $delimiter . $alt . $delimiter . ' title=' . $delimiter . $title . $delimiter;
        $html = preg_replace('/alt=[\'\"][^[\'\"]*[\'\"]/', $custom_alt, $html);
        $html = fifu_check_alt_attribute($html, $custom_alt);
    } else {
        $alt = esc_attr(strip_tags($alt));
        $title = esc_attr($title ? $title : $alt);
        if ($url && $alt) {
            $html = preg_replace('/alt=[\'\"][^[\'\"]*[\'\"]/', 'alt=' . $delimiter . $alt . $delimiter . ' title=' . $delimiter . $title . $delimiter, $html);
        }
    }

    // onerror
    $errorUrl = get_option('fifu_error_url');
    if ($errorUrl) {
        if (fifu_is_on('fifu_photon')) {
            $errorUrl = fifu_jetpack_replace_src(html_entity_decode($src), $errorUrl, get_post_thumbnail_id($post_id));
        }
        $html = str_replace('/>', sprintf(' onerror="this.src=\'%s\'; jQuery(this).removeAttr(\'srcset\');"/>', $errorUrl), $html);
    }

    if ($url)
        return $html;

    $url = !$sliderUrl ? $url : $sliderUrl;

    // hide internal featured images
    if (!$url && fifu_should_hide())
        return '';

    return !$url ? $html : fifu_get_html($url, $alt, $width, $height);
}

function fifu_check_alt_attribute($html, $custom_alt) {
    // Get the `<img>` tag in the string.
    $imgTag = preg_match('/<img (.+?)\/?>/', $html, $matches);

    if (!isset($matches[1]))
        return $html;

    // Check if the `<img>` tag has an alt attribute.
    $attributes = $matches[1];

    // If the alt attribute is empty, add it
    if (!preg_match('/alt=[\'\"][^[\'\"]*[\'\"]/', $attributes))
        $html = str_replace("<img ", "<img {$custom_alt} ", $html);

    return $html;
}

function fifu_show_slider($sliderUrl) {
    $is_featured = fifu_main_image_url(get_queried_object_id(), true) == $sliderUrl;
    if (!$is_featured && fifu_is_on('fifu_slider_single'))
        return false;

    return $sliderUrl && is_valid_slider_locale();
}

function fifu_is_url($var) {
    return strpos($var, 'http') === 0;
}

function fifu_get_html($url, $alt, $width, $height) {
    $css = '';
    if (fifu_is_video($url)) {
        $cls = 'fifu-video';
        if (class_exists('WooCommerce') && is_cart())
            $cls = 'fifu';
    } else {
        $cls = 'fifu';
    }

    if (fifu_should_hide()) {
        $css = 'display:none';
        $cls = 'fifu';
    }

    $safe_url = esc_url($url);
    $safe_alt = esc_attr($alt);
    $safe_css = esc_attr($css);
    $safe_width = esc_attr($width);
    $safe_height = esc_attr($height);

    return sprintf(
            '<img class="%s" src="%s" alt="%s" title="%s" style="%s" data-large_image="%s" data-large_image_width="%s" data-large_image_height="%s" onerror="%s" width="%s" height="%s">',
            $cls,
            $safe_url,
            $safe_alt,
            $safe_alt,
            $safe_css,
            $safe_url,
            "800",
            "600",
            "jQuery(this).hide();",
            $safe_width,
            $safe_height
    );
}

function fifu_slider_get_html($post_id, $original_class, $gallery_class, $gallery_css, $width, $height) {
    global $FIFU_SESSION;

    $att_id = get_post_thumbnail_id($post_id);
    $height = $height ?: 0;
    $width = $width ?: 0;

    $css = fifu_should_hide() ? 'display:none' : '';

    $ratio = get_post_meta($post_id, 'fifu_slider_ratio', true);
    if (!$ratio && $width && $height && $width < 9999 && $height < 9999)
        $ratio = $width . ':' . $height;
    $attr_ratio = $ratio ? 'fifu-ratio="' . $ratio . '"' : '';

    $class = "fifu " . $original_class;

    $gallery_css = $gallery_css ? 'style="' . $gallery_css . '"' : '';

    $html = sprintf('<div class="fifu-slider %s" id="fifu-slider-%s" %s %s>', $gallery_class, $post_id, $attr_ratio, $gallery_css);
    if (fifu_is_on('fifu_slider_counter'))
        $html = $html . '<div style="font-size:12px; padding:2px 5px 2px 5px; background:rgba(0, 0, 0, 0.3); z-index:50; position:absolute; color:white" id="counter-slider"></div>';
    $html = $html . '<ul id="image-gallery" class="gallery list-unstyled cS-hidden fifu-post-gallery">';

    $i = 0;
    while (true) {
        $url = get_post_meta($post_id, 'fifu_slider_image_url_' . $i, true);
        $image_url = $url;
        $video_url = '';
        if (strpos($url, '#http') !== false) {
            $image_url = substr($url, strpos($url, '#http') + 1);
            $video_url = substr($url, 0, strpos($url, '#http'));
            $video_src = fifu_video_src_by_img($image_url);
            $FIFU_SESSION['fifu-video'][$image_url] = $video_src ? $video_src : $video_url;
        }

        $alt = get_post_meta($post_id, 'fifu_slider_image_alt_' . $i, true);

        if (!$image_url)
            break;

        $error_url = get_option('fifu_error_url');

        if ($image_url) {
            if (fifu_is_from_speedup($image_url)) {
                $signed_url = fifu_speedup_get_signed_url($image_url, 128, 128, null, null, false);
                $set = fifu_speedup_get_set($image_url);
                $html = $html . sprintf(
                                '<li data-thumb="%s" data-src="%s" data-srcset="%s" data-alt="%s"><img src="%s" style="%s" class="%s" onerror="%s" alt="%s"/></li>',
                                esc_url($signed_url),
                                esc_url(FIFU_PLACEHOLDER),
                                esc_attr($set),
                                esc_attr($alt),
                                esc_url(fifu_speedup_get_signed_url($image_url, $width, $height, null, null, false)),
                                esc_attr($css),
                                esc_attr("fifu {$original_class}"),
                                "jQuery(this).hide();",
                                esc_attr($alt)
                        );
                $i++;
                continue;
            }

            if ($video_url) {
                $type = 'data-src';
                $data_video = '';

                // for video files
                if (fifu_is_local_video($video_url) || fifu_is_amazon_video($video_url) || fifu_is_wpcom_video($video_url)) {
                    $type = 'data-video';
                    if (fifu_is_local_video($video_url)) {
                        $extension = pathinfo($video_url, PATHINFO_EXTENSION);
                        $file_type = "video/{$extension}";
                    } else {
                        $file_type = fifu_is_amazon_video($video_url) || fifu_is_wpcom_video($video_url) ? 'video/mp4' : 'video';
                    }
                    $data_video = '{"source": [{"src":"' . $video_url . '", "type":"' . $file_type . '"}], "attributes": {"preload": false, "controls": true}}';
                }

                // for unsupported videos
                if (fifu_is_googledrive_video($video_url))
                    $video_url = fifu_googledrive_src($video_url);
                elseif (fifu_is_mega_video($video_url))
                    $video_url = fifu_mega_src($video_url);

                $video_image_url = fifu_db_get_image_url_by_video_url($video_url);
                $att_id = fifu_db_get_att_id($post_id, $video_image_url, false);
                $metadata = wp_get_attachment_metadata($att_id);
                if ($metadata && isset($metadata['width']) && isset($metadata['height'])) {
                    $video_image_width = $metadata['width'];
                    $video_image_height = $metadata['height'];
                    $data_lg_size = "data-lg-size=\"{$video_image_width}-{$video_image_height}\"";
                } else
                    $data_lg_size = '';

                $html = $html . sprintf(
                                '<li %s data-thumb="%s" %s=\'%s\' data-poster="%s"><img src="%s" class="img-responsive%s" onerror="%s"/></li>',
                                $data_lg_size,
                                esc_url($image_url),
                                $type,
                                esc_attr($type == 'data-video' ? $data_video : $video_url),
                                esc_url($image_url),
                                esc_url($image_url),
                                $class ? ' ' . esc_attr($class) : '',
                                $error_url ? sprintf("this.src='%s'", esc_url($error_url)) : ""
                        );
            } else {
                $resized = fifu_resize_with_photon($image_url, $width, $height, 1, $att_id, null);
                $html = $html . sprintf(
                                '<li data-thumb="%s" data-src="%s" data-alt="%s"><img src="%s" style="%s" class="%s" onerror="%s" alt="%s"/></li>',
                                esc_url($resized),
                                esc_url($image_url),
                                esc_attr($alt),
                                esc_url($resized),
                                esc_attr($css),
                                esc_attr("fifu " . $original_class),
                                $error_url ? sprintf("this.src='%s'", esc_url($error_url)) : "",
                                esc_attr($alt)
                        );
            }
        }
        $i++;
    }
    // add status
    $html = str_replace('<img ', '<img fifu-replaced="1" ', $html);
    return $html . '</ul></div>';
}

function is_valid_slider_locale() {
    return !(class_exists('WooCommerce') && is_cart());
}

function is_slider_empty($post_id) {
    for ($i = 0; $i < 5; $i++)
        if (get_post_meta($post_id, 'fifu_slider_image_url_' . $i, true))
            return false;
    return true;
}

add_filter('the_content', 'fifu_remove_content_image');

function fifu_remove_content_image($content) {
    if (fifu_is_off('fifu_pcontent_remove'))
        return $content;

    $post_types_string = get_option('fifu_pcontent_types');
    $post_types_array = explode(',', $post_types_string);
    if ($post_types_string && !is_singular($post_types_array))
        return $content;

    global $post;
    if (!isset($post) || !isset($post->ID))
        return $content;

    $post_id = $post->ID;
    $att_id = get_post_thumbnail_id($post_id);
    $att_url = wp_get_attachment_url($att_id);

    if (!empty($att_url)) {
        $pattern = '/<img[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i';
        preg_match_all($pattern, $content, $matches);

        if (!empty($matches[1] ?? [])) {
            foreach ($matches[1] as $match) {
                $content_img_url = html_entity_decode($match);
                // Simple: exact or content URL is a substring of the attachment URL
                $should_replace = ($content_img_url == $att_url) || ($content_img_url !== '' && strpos($att_url, $content_img_url) !== false);
                if ($should_replace) {
                    $content = preg_replace('/<img[^>]+src=[\'"]' . preg_quote($match, '/') . '[\'"][^>]*>/i', '', $content, 1);
                    return $content;
                }
            }
        }

        if (fifu_is_on('fifu_video')) {
            $pattern = '/<(iframe|video)[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i';
            preg_match_all($pattern, $content, $matches);

            if (!empty($matches[2] ?? [])) {
                foreach ($matches[2] as $match) {
                    $content_video_url = html_entity_decode($match);
                    $video_url = get_post_meta(get_the_ID(), 'fifu_video_url', true);
                    if ($video_url) {
                        $video_id = fifu_video_id($video_url);
                        if ($video_id && strpos($content_video_url, $video_id) !== false) {
                            $content = preg_replace('/<(iframe|video)[^>]+src=[\'"]' . preg_quote($match, '/') . '[\'"][^>]*>/i', '', $content, 1);
                            return $content;
                        }
                    }
                }
            }
        }
    }
    return $content;
}

add_filter('the_content', 'fifu_add_to_content');

function fifu_add_to_content($content) {
    if (fifu_is_off('fifu_pcontent_add'))
        return $content;

    $post_types_string = get_option('fifu_pcontent_types');
    $post_types_array = explode(',', $post_types_string);
    if ($post_types_string && !is_singular($post_types_array))
        return $content;

    if (has_post_thumbnail())
        return '<div style="text-align:center">' . get_the_post_thumbnail() . '</div>' . $content;

    return $content;
}

add_filter('the_content', 'fifu_optimize_content');

function fifu_optimize_content($content) {
    if (fifu_is_off('fifu_cdn_content') || empty($content))
        return $content;

    wp_register_style('fifu-lazyload-style', plugins_url('/html/css/lazyload.css', __FILE__), array(), fifu_version_number_enq());
    wp_enqueue_style('fifu-lazyload-style');
    wp_enqueue_script('fifu-lazyload-js', plugins_url('/html/js/lazyload.js', __FILE__), array('jquery'), fifu_version_number_enq());

    global $post;

    // Return if post object doesn't exist or has no ID
    if (!isset($post) || !isset($post->ID))
        return $content;

    $post_id = $post->ID ?? 0;

    $srcType = "src";
    $imgList = array();
    preg_match_all('/<img[^>]*>/', $content, $imgList);

    foreach (($imgList[0] ?? []) as $imgItem) {
        preg_match('/(' . $srcType . ')([^\'\"]*[\'\"]){2}/', $imgItem, $src);
        if (!$src)
            continue;

        $del = substr($src[0], - 1);
        $url_parts = explode($del, $src[0]);
        $url = isset($url_parts[1]) ? fifu_normalize($url_parts[1]) : '';

        if (!$url || fifu_jetpack_blocked($url) || strpos($url, 'data:image') === 0)
            continue;

        $new_url = fifu_jetpack_photon_url($url, null, get_post_thumbnail_id($post_id));

        $newImgItem = str_replace($url, $new_url, html_entity_decode($imgItem));
        if (fifu_is_on('fifu_otfcdn'))
            $srcset = fifu_otf_get_set($new_url, false);
        else
            $srcset = fifu_jetpack_get_set($new_url, false);

        // custom lazy load
        $newImgItem = str_replace('<img ', '<img fifu-lazy="1" fifu-data-sizes="auto" fifu-data-srcset="' . $srcset . '" ', $newImgItem);
        $newImgItem = str_replace(' src=', ' fifu-data-src=', $newImgItem);
        $newImgItem = str_replace('<img ', '<img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" ', $newImgItem);

        $content = str_replace($imgItem, $newImgItem, $content);

        // fifu_update_cdn_stats();
    }

    $content = fifu_remove_source_tags($content);

    return $content;
}

function fifu_remove_source_tags($content) {
    $pattern = '/<source\b[^>]*>(.*?)<\/source>|<source\b[^>]*\/?>/i';
    $cleaned_content = preg_replace($pattern, '', $content);
    return $cleaned_content;
}

function fifu_should_hide() {
    if (fifu_is_off('fifu_hide'))
        return false;

    if (class_exists('WooCommerce') && is_product())
        return false;

    global $post;
    if (isset($post->ID) && $post->ID != get_queried_object_id())
        return false;

    $post_types_string = get_option('fifu_hide_type');
    $post_types_array = explode(',', $post_types_string);
    if ($post_types_string && !is_singular($post_types_array))
        return false;

    $formats = get_option('fifu_hide_format');
    if (isset($post->ID) && $formats) {
        $post_format = get_post_format($post->ID);
        if (false === $post_format)
            $post_format = 'standard';
        if (!in_array($post_format, explode(',', $formats)))
            return false;
    }

    return !is_front_page() && is_singular(get_post_type(get_the_ID()));
}

function fifu_is_cpt() {
    return in_array(get_post_type(get_the_ID()), array_diff(fifu_get_post_types(), array('post', 'page')));
}

function fifu_main_image_url($post_id, $front = false) {
    $url = get_post_meta($post_id, 'fifu_slider_image_url_0', true);
    if (!empty($url) && strpos($url, '#http') !== false)
        $url = substr($url, strpos($url, '#http') + 1);

    if (!$url)
        $url = get_post_meta($post_id, 'fifu_image_url', true);

    if (!$url) {
        $video_url = get_post_meta($post_id, 'fifu_video_url', true);

        // avoid oembed call
        if ($front && fifu_calls_oembed($video_url)) {
            $att_id = get_post_thumbnail_id($post_id);
            $att_url = get_post_meta($att_id, '_wp_attached_file', true); // avoid recursion
            $url = $att_url ? $att_url : fifu_video_img_large($video_url, $post_id, false);
        } else
            $url = fifu_video_img_large($video_url, $post_id, false);
    }

    if (!$url && fifu_no_internal_image($post_id) && (get_option('fifu_default_url') && fifu_is_on('fifu_enable_default_url'))) {
        if (fifu_is_valid_default_cpt($post_id))
            $url = get_option('fifu_default_url');
    }

    if (!$url)
        return null;

    $url = htmlspecialchars_decode($url);

    return str_replace("'", "%27", $url);
}

function fifu_no_internal_image($post_id) {
    return get_post_meta($post_id, '_thumbnail_id', true) == -1 || get_post_meta($post_id, '_thumbnail_id', true) == null || get_post_meta($post_id, '_thumbnail_id', true) == get_option('fifu_default_attach_id');
}

// it takes too long
function fifu_valid_url($url) {
    if (empty($url))
        return false;

    $url = fifu_convert($url);

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => str_replace(" ", "%20", $url),
        CURLOPT_HEADER => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY => true)
    );
    $header = explode("\n", curl_exec($curl) ?? '');
    $type = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
    curl_close($curl);
    return strpos($header[0] ?? '', ' 200') !== false || strpos($header[0] ?? '', ' 302') !== false;
}

function fifu_is_main_page() {
    return is_home() || (class_exists('WooCommerce') && is_shop());
}

function fifu_is_in_editor() {
    if (!is_admin() || !function_exists('get_current_screen'))
        return false;

    $screen = get_current_screen();
    if (!$screen)
        return false;

    $parent_base = isset($screen->parent_base) ? $screen->parent_base : '';
    $is_block_editor = isset($screen->is_block_editor) ? $screen->is_block_editor : false;

    return $parent_base === 'edit' || $is_block_editor;
}

function fifu_get_default_url() {
    return wp_get_attachment_url(get_option('fifu_default_attach_id'));
}

// rss

add_action('pre_rss2_ns', function () {
    // Start capturing the output
    ob_start();
}, 1);

add_action('rss2_ns', function () {
    $rss_ns = ob_get_clean(); // Get the current namespace output
    if (strpos($rss_ns, 'xmlns:media="http://search.yahoo.com/mrss/"') === false) {
        // Use a regular expression to capture the <rss> tag and its version number
        $rss_ns = preg_replace(
                '/(<rss version="[^"]+")/',
                '$1' . PHP_EOL . "\t" . 'xmlns:media="http://search.yahoo.com/mrss/"',
                $rss_ns
        );
    }
    echo $rss_ns;
}, 9999);

add_action('rss2_item', 'fifu_add_rss');

function fifu_add_rss() {
    global $post;
    if (!isset($post) || !isset($post->ID))
        return;

    if (has_post_thumbnail($post->ID)) {
        $thumbnail = fifu_main_image_url($post->ID, true); // external (no CDN)
        if ($thumbnail) {
            if (fifu_is_from_speedup($thumbnail))
                $thumbnail = fifu_speedup_get_signed_url($thumbnail, 1280, 853, null, null, false);
            elseif (fifu_is_on('fifu_photon')) {
                $thumbnail = fifu_jetpack_photon_url($thumbnail, null, get_post_thumbnail_id($post->ID));
            }
        } else {
            $thumbnail = wp_get_attachment_url(get_post_thumbnail_id($post->ID)); // internal
        }
        if ($thumbnail) {
            // Make sure ampersands are properly escaped for XML
            $clean_url = esc_url($thumbnail);
            echo '<media:content url="' . $clean_url . '" medium="image"></media:content>
            ';
        }
    }
}

// for ajax pagination
function fifu_posts_results($posts, $query) {
    if (!is_admin() && $query->is_main_query() && is_paged() && !empty($posts)) {
        foreach ($posts as $post) {
            if (isset($post->ID)) {
                fifu_add_parameters_single_post($post->ID);
            }
        }
    }
    return $posts;
}

add_filter('posts_results', 'fifu_posts_results', 10, 2);

function fifu_wpseo_schema_graph($graph, $context) {
    if (is_singular()) {
        $post_id = get_the_ID();

        $image_urls = fifu_get_slider_image_urls($post_id);
        if (empty($image_urls))
            $image_urls = fifu_get_image_urls($post_id);

        if (empty($image_urls)) {
            $url = fifu_main_image_url($post_id, true);
            $image_urls = $url ? [$url] : [];
        }

        if (!empty($image_urls)) {
            foreach ($graph as &$item) {
                // Replace the image URLs for WebPage, Article, and Product types
                if (isset($item['@type']) && in_array($item['@type'], ['Article', 'WebPage', 'Product'])) {
                    if (isset($item['primaryImageOfPage'])) {
                        $item['primaryImageOfPage'] = $image_urls[0];
                    }

                    if (isset($item['image'])) {
                        $item['image'] = $image_urls;
                    }
                }

                // Replace the image URLs for ImageObject types
                if (isset($item['@type']) && $item['@type'] === 'ImageObject') {
                    if (isset($item['url'])) {
                        $item['url'] = $image_urls[0];
                    }
                    if (isset($item['contentUrl'])) {
                        $item['contentUrl'] = $image_urls[0];
                    }
                }
            }
        }
    }
    return $graph;
}

add_filter('wpseo_schema_graph', 'fifu_wpseo_schema_graph', 10, 2);

add_filter('rank_math/opengraph/facebook/image', function ($image_url) {
    // prevent Rank Math from removing query parameters
    if (fifu_is_on('fifu_photon') && fifu_is_remote_image_url($image_url)) {
        return str_replace('https://', 'http://', $image_url);
    }
    return $image_url;
});

add_filter('rank_math/opengraph/twitter/image', function ($image_url) {
    // prevent Rank Math from removing query parameters
    if (fifu_is_on('fifu_photon') && fifu_is_remote_image_url($image_url)) {
        return str_replace('https://', 'http://', $image_url);
    }
    return $image_url;
});

add_filter('rank_math/sitemap/enable_caching', (get_option('fifu_otfcdn') == 'toggleon') ? '__return_false' : '__return_true');

// https://rankmath.com/kb/filters-hooks-api-developer/
add_filter('rank_math/sitemap/xml_img_src', function ($src, $post) {
    if (fifu_is_on('fifu_otfcdn') && is_object($post) && isset($post->ID)) {
        $att_id = get_post_thumbnail_id($post->ID);
        if (fifu_is_remote_image($att_id)) {
            if (strpos($src, '//img.') === false && strpos($src, '//i0.fifu.app') === false) {
                return fifu_otf_get_image_url($att_id, $src, null);
            }
        }
    }
    return $src;
}, 10, 2);

