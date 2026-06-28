<?php
add_action('add_meta_boxes', 'fifu_insert_meta_box');

function fifu_insert_meta_box() {
    if (fifu_is_web_story() || fifu_is_search_filter_pro())
        return;

    if (get_option('fifu_lock'))
        return;

    $fifu = fifu_get_strings_meta_box_php();
    $post_types = fifu_get_post_types();

    foreach ($post_types as $post_type) {
        if ($post_type == 'product') {
            add_meta_box('urlMetaBox', $fifu['title']['product']['image'](), 'fifu_show_elements', $post_type, 'side', 'default');

            add_meta_box('wooGalleryMetaBox', $fifu['title']['product']['images'](), 'fifu_wc_show_elements', $post_type, 'side', 'default');

            if (fifu_is_on('fifu_video')) {
                add_meta_box('wooVideoUrlMetaBox', $fifu['title']['product']['video'](), 'fifu_video_show_elements', $post_type, 'side', 'default');
                add_meta_box('wooCommerceVideoGalleryMetaBox', $fifu['title']['product']['videos'](), 'fifu_video_wc_show_elements', $post_type, 'side', 'default');
            }

            if (fifu_is_on('fifu_slider'))
                add_meta_box('wooSliderImageUrlMetaBox', $fifu['title']['product']['slider'](), 'fifu_slider_show_elements', $post_type, 'side', 'default');

            if (fifu_is_on('fifu_isbn'))
                add_meta_box('isbnMetaBox', $fifu['title']['post']['isbn'](), 'fifu_isbn_show_elements', $post_type, 'side', 'default');

            if (fifu_is_on('fifu_asin'))
                add_meta_box('asinMetaBox', $fifu['title']['post']['asin'](), 'fifu_asin_show_elements', $post_type, 'side', 'default');

            if (fifu_is_on('fifu_finder'))
                add_meta_box('finderMetaBox', $fifu['title']['post']['finder'](), 'fifu_finder_show_elements', $post_type, 'side', 'default');

            if (fifu_is_on('fifu_audio'))
                add_meta_box('audioMetaBox', $fifu['title']['post']['audio'](), 'fifu_audio_show_elements', $post_type, 'side', 'default');
        } else {
            if ($post_type) {
                add_meta_box('imageUrlMetaBox', $fifu['title']['post']['image'](), 'fifu_show_elements', $post_type, 'side', 'default');

                if (fifu_is_on('fifu_video'))
                    add_meta_box('videoUrlMetaBox', $fifu['title']['post']['video'](), 'fifu_video_show_elements', $post_type, 'side', 'default');

                if (fifu_is_on('fifu_slider'))
                    add_meta_box('sliderImageUrlMetaBox', $fifu['title']['post']['slider'](), 'fifu_slider_show_elements', $post_type, 'side', 'default');

                if (fifu_is_on('fifu_isbn'))
                    add_meta_box('isbnMetaBox', $fifu['title']['post']['isbn'](), 'fifu_isbn_show_elements', $post_type, 'side', 'default');

                if (fifu_is_on('fifu_asin'))
                    add_meta_box('asinMetaBox', $fifu['title']['post']['asin'](), 'fifu_asin_show_elements', $post_type, 'side', 'default');

                if (fifu_is_on('fifu_finder'))
                    add_meta_box('finderMetaBox', $fifu['title']['post']['finder'](), 'fifu_finder_show_elements', $post_type, 'side', 'default');

                if (fifu_is_on('fifu_audio'))
                    add_meta_box('audioMetaBox', $fifu['title']['post']['audio'](), 'fifu_audio_show_elements', $post_type, 'side', 'default');

                if (fifu_is_on('fifu_redirection'))
                    add_meta_box('redirectionMetaBox', $fifu['title']['post']['redirection'](), 'fifu_redirection_show_elements', $post_type, 'side', 'default');

                if (fifu_is_on('fifu_popup'))
                    add_meta_box('popupMetaBox', $fifu['title']['post']['popup'](), 'fifu_popup_show_elements', $post_type, 'side', 'default');
            }
        }
    }
    fifu_register_meta_box_script();
}

add_action('add_meta_boxes', 'remove_metaboxes', 50);

function remove_metaboxes() {
    global $post;

    if (!$post)
        return;

    $url = get_post_meta($post->ID, 'fifu_image_url', true);
    $video_url = get_post_meta($post->ID, 'fifu_video_url', true);
    $slider_url = get_post_meta($post->ID, 'fifu_slider_image_url_0', true);
    $gallery_urls = fifu_get_gallery_urls($post->ID);
    if ($url || $video_url || $slider_url) {
        if (!fifu_is_rank_math_seo_active())
            remove_meta_box('postimagediv', 'product', 'side');
    }
    if ($gallery_urls || $slider_url) {
        remove_meta_box('woocommerce-product-images', 'product', 'side');
    }
}

function fifu_register_meta_box_script() {
    // for edition
    if (isset($_REQUEST['post'])) {
        $blocked_list = array('wppb-rf-cpt', 'wppb-epf-cpt');
        $post_id = $_REQUEST['post'];
        $post_type = get_post_type($post_id);
        if (in_array($post_type, $blocked_list))
            return;
    }
    // for new posts
    if (isset($_REQUEST['post_type'])) {
        $blocked_list = array('wppb-rf-cpt', 'wppb-epf-cpt');
        $post_type = $_REQUEST['post_type'];
        if (in_array($post_type, $blocked_list))
            return;
    }

    $fifu = fifu_get_strings_meta_box_php();
    $fifu_help = fifu_get_strings_help();

    wp_enqueue_script('fifu-cookie', 'https://cdnjs.cloudflare.com/ajax/libs/js-cookie/latest/js.cookie.min.js');

    wp_enqueue_script('jquery-block-ui', 'https://cdnjs.cloudflare.com/ajax/libs/jquery.blockUI/2.70/jquery.blockUI.min.js');
    wp_enqueue_style('fancy-box-css', 'https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.css');
    wp_enqueue_script('fancy-box-js', 'https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.min.js');

    // Safely get current screen
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;

    if (($screen && ($screen->taxonomy ?? null) != 'product_cat') && (fifu_is_on('fifu_slider') || (class_exists('WooCommerce') && (($screen->post_type ?? '') === 'product')))) {
        wp_register_style('jquery-sortablejs-css', plugins_url('/html/css/sortable.css', __FILE__), array(), fifu_version_number_enq());
        wp_enqueue_style('jquery-sortablejs-css');
    }

    wp_enqueue_script('fifu-rest-route-js', plugins_url('/html/js/rest-route.js', __FILE__), array('jquery'), fifu_version_number_enq());
    wp_enqueue_script('fifu-meta-box-js', plugins_url('/html/js/meta-box.js', __FILE__), fifu_is_gutenberg_screen() ? array('jquery', 'wp-edit-post') : array('jquery'), fifu_version_number_enq());
    wp_enqueue_script('fifu-video-util-js', plugins_url('/html/js/video-util.js', __FILE__), array('jquery'), fifu_version_number_enq());
    wp_enqueue_script('fifu-video-meta-box-js', plugins_url('/html/js/video-meta-box.js', __FILE__), array('jquery'), fifu_version_number_enq());
    wp_enqueue_script('fifu-convert-url-js', plugins_url('/html/js/convert-url.js', __FILE__), array('jquery'), fifu_version_number_enq());

    wp_register_style('fifu-unsplash-css', plugins_url('/html/css/unsplash.css', __FILE__), array(), fifu_version_number_enq());
    wp_enqueue_style('fifu-unsplash-css');
    wp_enqueue_script('fifu-unsplash-js', plugins_url('/html/js/unsplash.js', __FILE__), array('jquery'), fifu_version_number_enq());

    wp_localize_script('fifu-video-util-js', 'fifuVideoUtilVars', [
        'uploadDir' => fifu_get_upload_dir(),
    ]);

    // register custom variables for the AJAX script
    wp_localize_script('fifu-rest-route-js', 'fifuScriptVars', [
        'restUrl' => esc_url_raw(rest_url()),
        'homeUrl' => esc_url_raw(home_url()),
        'nonce' => wp_create_nonce('wp_rest'),
        'partialKey' => fifu_partial_key(),
    ]);

    if (fifu_is_sirv_active())
        wp_enqueue_script('fifu-sirv-js', 'https://scripts.sirv.com/sirv.js');

    wp_localize_script('fifu-meta-box-js', 'fifuMetaBoxVars', [
        'get_the_ID' => get_the_ID(),
        'is_sirv_active' => fifu_is_sirv_active(),
        'wait' => $fifu['common']['wait'](),
        'is_taxonomy' => $screen->taxonomy ?? null,
        'is_product' => get_post_type() == 'product',
        'is_classic_editor' => is_plugin_active('classic-editor/classic-editor.php'),
        'enable_upload' => fifu_is_on('fifu_upload_show'),
        'orientation' => get_option('fifu_tags_orientation'),
        'txt_title_examples' => $fifu_help['title']['examples'](),
        'txt_title_keywords' => $fifu_help['title']['keywords'](),
        'txt_title_more' => $fifu_help['title']['more'](),
        'txt_title_url' => $fifu_help['title']['url'](),
        'txt_title_empty' => $fifu_help['title']['empty'](),
        'txt_desc_more' => $fifu_help['desc']['more'](),
        'txt_desc_url' => $fifu_help['desc']['url'](),
        'txt_desc_keywords' => $fifu_help['desc']['keywords'](),
        'txt_desc_empty' => $fifu_help['desc']['empty'](),
        'txt_unlock' => $fifu_help['unsplash']['unlock'](),
        'txt_more' => $fifu_help['unsplash']['more'](),
        'txt_loading' => $fifu_help['unsplash']['loading'](),
        'alt_text_label' => $fifu['common']['alt']()
    ]);

    wp_localize_script('fifu-video-meta-box-js', 'fifuVideoMetaBoxVars', [
        'restUrl' => esc_url_raw(rest_url()),
        'nonce' => wp_create_nonce('wp_rest'),
    ]);

    wp_localize_script('fifu-convert-url-js', 'fifuConvertUrlVars', [
        'restUrl' => esc_url_raw(rest_url()),
        'homeUrl' => esc_url_raw(home_url()),
        'nonce' => wp_create_nonce('wp_rest'),
        'partialKey' => fifu_partial_key(),
    ]);

    // Hide native Woo variation image picker when FIFU fields are used
    if (class_exists('WooCommerce') && (($screen->post_type ?? '') === 'product')) {
        wp_register_style('woo-meta-box-variation-css', plugins_url('/html/css/woo-meta-box-variation.css', __FILE__), array(), fifu_version_number_enq());
        wp_enqueue_style('woo-meta-box-variation-css');

        wp_enqueue_script(
                'fifu-woo-meta-box-variation-js',
                plugins_url('/html/js/woo-meta-box-variation.js', __FILE__),
                array('jquery'),
                fifu_version_number_enq()
        );
    }
}

add_action('add_meta_boxes', 'fifu_add_css');

function fifu_add_css() {
    wp_register_style('fifu-editor', plugins_url('/html/css/editor.css', __FILE__), array(), fifu_version_number_enq());
    wp_enqueue_style('fifu-editor');
}

function fifu_show_elements($post) {
    $margin = 'margin-top:5px;margin-left:3px;';
    $width = 'width:100%;';
    $height = 'height:150px;';
    $align = 'text-align:left;';

    $url = esc_url(get_post_meta($post->ID, 'fifu_image_url', true) ?? '');
    $alt = esc_attr(get_post_meta($post->ID, 'fifu_image_alt', true));

    if ($url) {
        $show_button = 'display:none;';
        $show_alt = $show_image = $show_link = '';
    } else {
        $show_alt = $show_image = $show_link = 'display:none;';
        $show_button = '';
    }

    $show_upload = fifu_is_on('fifu_upload_show') ? '' : 'display:none';
    $show_screenshot = fifu_is_on('fifu_screenshot') ? '' : 'display:none;';

    $fifu = fifu_get_strings_meta_box();
    include 'html/meta-box.html';
}

function fifu_isbn_show_elements($post) {
    $fifu = fifu_get_strings_meta_box();
    $input_style = 'width:100%;font-size:13px;';
    $isbn = esc_attr(get_post_meta($post->ID, 'fifu_isbn', true));
    $show_isbn = $isbn ? '' : 'display:none;';
    include 'html/meta-box-isbn.html';
}

function fifu_asin_show_elements($post) {
    $fifu = fifu_get_strings_meta_box();
    $input_style = 'width:100%;font-size:13px;';
    $asin = esc_attr(get_post_meta($post->ID, 'fifu_asin', true));
    $show_asin = $asin ? '' : 'display:none;';
    include 'html/meta-box-asin.html';
}

function fifu_finder_show_elements($post) {
    $fifu = fifu_get_strings_meta_box();
    $input_style = 'width:100%;font-size:13px;';
    $finder = esc_url(get_post_meta($post->ID, 'fifu_finder_url', true) ?? '');
    $show_finder = $finder ? '' : 'display:none;';
    include 'html/meta-box-finder.html';
}

function fifu_audio_show_elements($post) {
    $fifu = fifu_get_strings_meta_box();
    $input_style = 'width:100%;font-size:13px;';
    $audio = esc_url(get_post_meta($post->ID, 'fifu_audio_url', true) ?? '');
    $show_audio = $audio ? '' : 'display:none;';
    include 'html/meta-box-audio.html';
}

function fifu_redirection_show_elements($post) {
    $fifu = fifu_get_strings_meta_box();
    $input_style = 'width:100%;font-size:13px;';
    $redirection = esc_url(get_post_meta($post->ID, 'fifu_redirection_url', true) ?? '');
    $show_redirection = $redirection ? '' : 'display:none;';
    include 'html/meta-box-redirection.html';
}

function fifu_popup_show_elements($post) {
    $fifu = fifu_get_strings_meta_box();
    $input_style = 'width:100%;font-size:13px;';
    $popup = get_post_meta($post->ID, 'fifu_popup_html', true);
    $show_popup = $popup ? '' : 'display:none;';
    include 'html/meta-box-popup.html';
}

function fifu_video_show_elements($post) {
    $margin = 'margin-top:10px;';
    $width = 'width:100%;';
    $height = 'height:150px;';
    $align = 'text-align:left;';

    $url = esc_url(get_post_meta($post->ID, 'fifu_video_url', true) ?? '');
    $url = $url ? $url : esc_url(get_post_meta($post->ID, 'fifu_custom_video_url', true) ?? '');

    if ($url) {
        $show_button = 'display:none;';
        $show_video = $show_link = '';
        if (fifu_is_local_video($url)) {
            $show_video_local = '';
            $show_video = $show_video_custom = 'display:none;';
        } else if (fifu_is_custom_video($url) || strpos($url, 'm3u8') !== false) {
            $show_video_custom = '';
            $show_video = $show_video_local = 'display:none;';
        } else {
            $show_video = '';
            $show_video_local = $show_video_custom = 'display:none;';
        }
    } else {
        $show_video = $show_video_local = $show_video_custom = $show_link = 'display:none;';
        $show_button = '';
    }

    $fifu = fifu_get_strings_meta_box();
    include 'html/meta-box-video.html';
}

function fifu_wc_show_elements($post) {
    $fifu = fifu_get_strings_meta_box();

    $urls = array();
    $alts = array();
    $ifms = array();

    $i = 0;
    while (true) {
        $url = get_post_meta($post->ID, 'fifu_image_url_' . $i, true);
        $alt = get_post_meta($post->ID, 'fifu_image_alt_' . $i, true);
        $ifm = get_post_meta($post->ID, 'fifu_image_ifm_' . $i, true);
        if (!$url)
            break;

        $urls[$i] = esc_url($url);
        $alts[$i] = esc_attr($alt);
        $ifms[$i] = $ifm;
        $i++;
    }

    wp_enqueue_script('woo-meta-box-js', plugins_url('/html/js/woo-meta-box.js', __FILE__), array('jquery'), fifu_version_number_enq());
    wp_localize_script('woo-meta-box-js', 'fifuBoxImageVars', [
        'urls' => $urls,
        'alts' => $alts,
        'ifms' => $ifms,
        'text_url' => $fifu['image']['url'](),
        'text_alt' => $fifu['image']['alt'](),
        'text_ifm' => $fifu['image']['ifm'](),
        'text_ok' => $fifu['image']['ok'](),
    ]);

    include 'html/woo-meta-box.html';
}

function fifu_video_wc_show_elements($post) {
    $fifu = fifu_get_strings_meta_box();

    $video_urls = array();
    $image_urls = array();

    $i = 0;
    while (true) {
        $video_url = get_post_meta($post->ID, 'fifu_video_url_' . $i, true);
        $image_url = fifu_video_img_large($video_url, $post->ID, false);
        if (!$video_url)
            break;

        $video_urls[$i] = esc_url($video_url);
        $image_urls[$i] = esc_url($image_url);
        $i++;
    }

    wp_enqueue_script('woo-video-meta-box-js', plugins_url('/html/js/woo-video-meta-box.js', __FILE__), array('jquery'), fifu_version_number_enq());
    wp_localize_script('woo-video-meta-box-js', 'fifuVideoVars', [
        'restUrl' => esc_url_raw(rest_url()),
        'homeUrl' => esc_url_raw(home_url()),
        'nonce' => wp_create_nonce('wp_rest'),
        'videoUrls' => $video_urls,
        'imageUrls' => $image_urls,
        'text_url' => $fifu['video']['url'](),
        'text_ok' => $fifu['video']['ok'](),
    ]);

    include 'html/woo-meta-box-video.html';
}

function fifu_slider_show_elements($post) {
    $ratio = esc_attr(get_post_meta($post->ID, 'fifu_slider_ratio', true));

    $fifu = fifu_get_strings_meta_box();

    $urls = array();
    $alts = array();

    $i = 0;
    while (true) {
        $url = get_post_meta($post->ID, 'fifu_slider_image_url_' . $i, true);
        $alt = get_post_meta($post->ID, 'fifu_slider_image_alt_' . $i, true);
        if (!$url)
            break;

        $urls[$i] = esc_url($url);
        $alts[$i] = esc_attr($alt);
        $i++;
    }

    wp_enqueue_script('slider-meta-box-js', plugins_url('/html/js/slider-meta-box.js', __FILE__), array('jquery'), fifu_version_number_enq());
    wp_localize_script('slider-meta-box-js', 'fifuSliderVars', [
        'restUrl' => esc_url_raw(rest_url()),
        'homeUrl' => esc_url_raw(home_url()),
        'nonce' => wp_create_nonce('wp_rest'),
        'urls' => $urls,
        'alts' => $alts,
        'is_product' => get_post_type() == 'product',
        'text_image_video_url' => $fifu['placeholder']['image-video'](),
        'text_alt' => $fifu['image']['alt'](),
        'text_ok' => $fifu['image']['ok'](),
    ]);

    include 'html/meta-box-slider.html';
}

// for wp all import: avoid duplicated images
function fifu_has_properties() {
    if (fifu_is_ol_scrapes_active())
        return true;

    foreach ($_POST as $key => $value) {
        if (strpos($key, 'fifu') !== false)
            return true;
    }
    return false;
}

add_action('woocommerce_before_product_object_save', 'fifu_woocommerce_before_product_object_save');

function fifu_woocommerce_before_product_object_save($product) {
    // fix for images deleted from WooCommerce product gallery
    $product_data = $product->get_data();
    $ids = $product_data['gallery_image_ids'] ?? [];
    if ($ids)
        update_post_meta($product->get_id(), 'fifu_tmp_product_image_gallery', implode(',', $ids));
}

add_action('save_post', 'fifu_save_properties');

function fifu_save_properties($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        return;

    $post_type = get_post_type($post_id);
    if (!$_POST || $post_type == 'nav_menu_item' || $post_type == 'revision')
        return;

    $action = $_POST['action'] ?? '';
    if ($action == 'woocommerce_do_ajax_product_import')
        return;

    if (isset($_POST['dokan_edit_product_nonce']))
        return;

    $post_content = get_post_field('post_content', $post_id);
    if (has_block('fifu/image', $post_content))
        return;

    /* image url from wcfm */
    if ($action == 'wcfm_ajax_controller') {
        if (fifu_is_wcfm_active() && isset($_POST['wcfm_products_manage_form'])) {
            $image_url = esc_url_raw(rtrim(fifu_get_wcfm_url($_POST['wcfm_products_manage_form'])));
            fifu_dev_set_image($post_id, $image_url);
            return;
        }
    }

    if (!fifu_has_properties())
        return;

    /* plugin: yoast duplicate post */
    $post_status = $_POST['post_status'] ?? '';
    if ($post_status == 'dp-rewrite-republish')
        fifu_dev_set_image($post_id, null);

    if (fifu_has_local_featured_image($post_id)) {
        $auto_set = false;
    } else {
        $fifu_input_url = $_POST['fifu_input_url'] ?? '';
        if (empty($fifu_input_url)) {
            $auto_set = true;
        } else {
            if (has_post_thumbnail($post_id)) {
                if (fifu_main_image_url($post_id, false) != $fifu_input_url) {
                    $auto_set = true;
                } else {
                    $auto_set = fifu_is_on('fifu_ovw_first');
                }
            } else {
                $auto_set = false;
            }
        }
    }

    /* image url */
    $url = null;
    if (isset($_POST['fifu_input_url'])) {
        $url = esc_url_raw(rtrim($_POST['fifu_input_url']));
        if ($auto_set) {
            $first_image = fifu_first_url_in_content($post_id, null, false);
            $first_video = fifu_first_url_in_content($post_id, null, true);

            if ($first_image && $first_video && get_option('fifu_html_media') != 'image')
                return;

            if ($first_image && fifu_is_on('fifu_get_first') && (!$url || fifu_is_on('fifu_ovw_first')) && !fifu_has_local_featured_image($post_id) && fifu_is_valid_cpt($post_id))
                $url = $first_image;
        }

        fifu_update_or_delete($post_id, 'fifu_image_url', $url);
    }

    /* image url from toolset forms */
    if (fifu_is_toolset_active() && isset($_POST['wpcf-fifu_image_url'])) {
        $url = esc_url_raw(rtrim($_POST['wpcf-fifu_image_url']));
        if ($url)
            fifu_update_or_delete($post_id, 'fifu_image_url', $url);
    }

    /* image url from aliplugin */
    if (fifu_is_aliplugin_active() && isset($_POST['imageUrl'])) {
        $url = esc_url_raw(rtrim($_POST['imageUrl']));
        if ($url)
            fifu_update_or_delete($post_id, 'fifu_image_url', $url);
    }

    /* image url from aawp */
    if (fifu_is_aawp_active() && !fifu_has_local_featured_image($post_id)) {
        $url = fifu_get_url_from_aawp($post_id);
        if ($url)
            fifu_update_or_delete($post_id, 'fifu_image_url', $url);
    }

    /* image url from external form: user-submitted-posts */
    if (!$url && isset($_POST['fifu_image_url']) && !is_array($_POST['fifu_image_url'])) {
        $url = esc_url_raw(rtrim($_POST['fifu_image_url']));
        if ($url)
            fifu_update_or_delete($post_id, 'fifu_image_url', $url);
    }

    /* alt */
    if (isset($_POST['fifu_input_alt'])) {
        $alt = esc_html(wp_strip_all_tags($_POST['fifu_input_alt']));
        fifu_update_or_delete_value($post_id, 'fifu_image_alt', $alt);
    }

    /* isbn */
    if (isset($_POST['fifu_input_isbn'])) {
        $isbn = wp_strip_all_tags(trim($_POST['fifu_input_isbn']));
        $isbn = str_replace('-', '', $isbn);
        fifu_update_or_delete_value($post_id, 'fifu_isbn', $isbn);
    }

    /* asin */
    if (isset($_POST['fifu_input_asin'])) {
        $asin = wp_strip_all_tags(trim($_POST['fifu_input_asin']));
        $asin = str_replace('-', '', $asin);
        fifu_update_or_delete_value($post_id, 'fifu_asin', $asin);
    }

    /* finder */
    if (isset($_POST['fifu_input_finder'])) {
        $page_url = esc_url_raw(rtrim($_POST['fifu_input_finder']));
        fifu_update_or_delete($post_id, 'fifu_finder_url', $page_url);
    }

    /* audio */
    if (isset($_POST['fifu_input_audio'])) {
        $audio_url = esc_url_raw(rtrim($_POST['fifu_input_audio']));
        fifu_update_or_delete($post_id, 'fifu_audio_url', $audio_url);
    }

    /* redirection */
    if (isset($_POST['fifu_input_redirection'])) {
        $page_url = esc_url_raw(rtrim($_POST['fifu_input_redirection']));
        fifu_update_or_delete($post_id, 'fifu_redirection_url', $page_url);
    }

    /* popup */
    if (isset($_POST['fifu_input_popup'])) {
        $popup_html = esc_html(rtrim($_POST['fifu_input_popup']));
        fifu_update_or_delete($post_id, 'fifu_popup_html', $popup_html);
    }

    /* gallery */
    if ($post_type == 'product') {
        // delete all custom fields
        if (isset($_POST['inputHiddenImageLength'])) {
            $length = $_POST['inputHiddenImageLength'];
            for ($i = 0; $i < $length; $i++) {
                delete_post_meta($post_id, 'fifu_image_url_' . $i);
                delete_post_meta($post_id, 'fifu_image_alt_' . $i);
                delete_post_meta($post_id, 'fifu_image_ifm_' . $i);
            }
        }
        // add custom fields
        if (isset($_POST['inputHiddenImageListIds'])) {
            $list = $_POST['inputHiddenImageListIds'];
            if (strlen($list) !== 0) {
                $indexes = explode('|', $list);
                $i = 0;
                foreach ($indexes as $index) {
                    $input_url = 'fifu_input_url_' . $index;
                    $input_alt = 'fifu_input_alt_' . $index;
                    $input_ifm = 'fifu_input_ifm_' . $index;
                    if (isset($_POST[$input_url]) && isset($_POST[$input_alt]) && isset($_POST[$input_ifm])) {
                        $url = esc_url_raw(rtrim($_POST[$input_url]));
                        $alt = wp_strip_all_tags($_POST[$input_alt]);
                        $ifm = esc_url_raw(rtrim($_POST[$input_ifm]));
                        fifu_update_or_delete($post_id, 'fifu_image_url_' . $i, $url);
                        fifu_update_or_delete_value($post_id, 'fifu_image_alt_' . $i, $alt);
                        fifu_update_or_delete_value($post_id, 'fifu_image_ifm_' . $i, $ifm);
                        $i++;
                    }
                }
            }
        }
    }

    fifu_save($post_id, !$auto_set);

    /* dimensions featured */
    $width = fifu_get_width_meta($_POST);
    $height = fifu_get_height_meta($_POST);
    $att_id = get_post_thumbnail_id($post_id);
    fifu_save_dimensions($att_id, $width, $height);

    /* dimensions slider */
    $width = fifu_get_dimension_meta_slider($_POST, 'width');
    $height = fifu_get_dimension_meta_slider($_POST, 'height');
    for ($i = 0; $i < sizeof($width); $i++) {
        $slider_input_url = $_POST['fifu_slider_input_url_' . $i] ?? '';
        $att_id = fifu_db_get_att_id($post_id, esc_url_raw(rtrim($slider_input_url)), false);
        fifu_save_dimensions($att_id, $width[$i] ?? null, $height[$i] ?? null);
    }

    /* dimensions image gallery */
    $width = fifu_get_dimension_meta_image_gallery($_POST, 'width');
    $height = fifu_get_dimension_meta_image_gallery($_POST, 'height');
    for ($i = 0; $i < sizeof($width); $i++) {
        $input_url = $_POST['fifu_input_url_' . $i] ?? '';
        $att_id = fifu_db_get_att_id($post_id, esc_url_raw(rtrim($input_url)), false);
        fifu_save_dimensions($att_id, $width[$i] ?? null, $height[$i] ?? null);
    }

    /* dimensions video gallery */
    $width = fifu_get_dimension_meta_video_gallery($_POST, 'width');
    $height = fifu_get_dimension_meta_video_gallery($_POST, 'height');
    for ($i = 0; $i < sizeof($width); $i++) {
        $video_input_image_src = $_POST['fifu_video_input_image_src_' . $i] ?? '';
        $image_url = esc_url_raw(rtrim($video_input_image_src));
        $att_id = fifu_db_get_att_id($post_id, $image_url, false);
        if (!$att_id)
            $att_id = fifu_db_get_att_id($post_id, str_replace('mqdefault', 'maxresdefault', $image_url), false);
        fifu_save_dimensions($att_id, $width[$i] ?? null, $height[$i] ?? null);
    }

    /* featured video (youtube dimensions) */
    if (isset($_POST['fifu_video_input_url']) && isset($_POST['fifu_video_input_image_src'])) {
        $video_url = esc_url_raw(rtrim($_POST['fifu_video_input_url']));
        $image_url = esc_url_raw(rtrim($_POST['fifu_video_input_image_src']));
        if (fifu_is_youtube_video($video_url)) {
            $att_id = get_post_thumbnail_id($post_id);
            fifu_update_youtube_dimensions($att_id, $image_url);

            $db_image_url = fifu_db_get_image_url_by_video_url($video_url);
            if ($db_image_url && $db_image_url != $image_url)
                fifu_db_update_image_url_by_video_url($video_url, $image_url);
        }
    }

    /* video gallery (youtube dimensions) */
    $width = fifu_get_dimension_meta_video_gallery($_POST, 'width');
    for ($i = 0; $i < sizeof($width); $i++) {
        $video_url = esc_url_raw(rtrim($_POST['fifu_video_input_url_' . $i] ?? ''));
        $image_url = esc_url_raw(rtrim($_POST['fifu_video_input_image_src_' . $i] ?? ''));
        if (fifu_is_youtube_video($video_url)) {
            $att_id = fifu_db_get_att_id($post_id, $image_url, false);
            if (!$att_id)
                $att_id = fifu_db_get_att_id($post_id, str_replace('mqdefault', 'maxresdefault', $image_url), false);
            fifu_update_youtube_dimensions($att_id, $image_url);
        }
    }
}

function fifu_save_dimensions($att_id, $width, $height) {
    if (!$att_id || !$width || !$height)
        return;

    $metadata = null;
    $metadata['width'] = $width;
    $metadata['height'] = $height;

    // https://developer.wordpress.org/reference/functions/wp_get_attachment_metadata/
    // $url = wp_get_attachment_url($att_id);
    // if (fifu_is_from_speedup($url)) {
    //     // original dimensions
    //     $aux = explode('-', $url);
    //     $original_width = $aux[1];
    //     $original_height = explode('/', $aux[2])[0];
    //     $sizes = array();
    //     $subsizes = wp_get_registered_image_subsizes();
    //     foreach ($subsizes as $key => $value) {
    //         $width = $value['width'];
    //         $height = $value['height'];
    //         foreach (unserialize(FIFU_SPEEDUP_SIZES) as $i) {
    //             if ($width <= $i) {
    //                 if ($i < $original_width) {
    //                     $new_url = str_replace('original', $i, $url);
    //                     // adjust sizes
    //                     if ($height)
    //                         $height = $i * $height / $width;
    //                     else
    //                         $height = $i * $original_height / $original_width;
    //                     $width = $i;
    //                 }
    //                 break;
    //             }
    //         }
    //         $value['file'] = $new_url;
    //         $value['width'] = $width;
    //         $value['height'] = (int) $height;
    //         $value['mime-type'] = 'image/webp';
    //         unset($value['crop']);
    //         $sizes[$key] = $value;
    //     }
    //     $metadata['sizes'] = $sizes;
    //     $metadata['file'] = $url;
    // }

    wp_update_attachment_metadata($att_id, $metadata);
}

function fifu_save($post_id, $ignore) {
    fifu_video_save_properties($post_id, $ignore);
    fifu_slider_save_properties($post_id);

    fifu_update_fake_attach_id($post_id);

    if (fifu_is_on('fifu_auto_category'))
        fifu_db_insert_auto_category_image();

    if (fifu_is_houzez_active() && get_post_meta($post_id, 'fifu_slider_image_url_0', true)) {
        wp_cache_flush();
        delete_post_meta($post_id, 'fifu_houzez_urls');
        delete_post_meta($post_id, 'fave_property_images');
        foreach (explode(',', get_post_meta($post_id, '_product_image_gallery', true)) as $att_id)
            add_post_meta($post_id, 'fave_property_images', $att_id, false);
    } elseif (fifu_is_wpresidence_active() && get_post_meta($post_id, 'fifu_slider_image_url_0', true)) {
        wp_cache_flush();
        $gallery = get_post_meta($post_id, '_product_image_gallery', true);
        $thumbnail_id = get_post_meta($post_id, '_thumbnail_id', true);
        $ids = $thumbnail_id ? array_merge([$thumbnail_id], explode(',', $gallery)) : explode(',', $gallery);
        update_post_meta($post_id, 'wpestate_property_gallery', array_combine(range(1, count($ids)), $ids));
    }
}

function fifu_video_save_properties($post_id, $ignore) {
    /* video url */
    if (isset($_POST['fifu_video_input_url'])) {
        $url = esc_url_raw(rtrim($_POST['fifu_video_input_url']));
        $first_image = fifu_first_url_in_content($post_id, null, false);
        $first_video = fifu_first_url_in_content($post_id, null, true);

        if (fifu_is_on('fifu_get_first') && $first_image && $first_video && get_option('fifu_html_media') == 'image')
            return;

        if ($first_video && fifu_is_on('fifu_video') && fifu_is_on('fifu_get_first') && (!$url || fifu_is_on('fifu_ovw_first')) && !$ignore && !fifu_has_local_featured_image($post_id) && fifu_is_valid_cpt($post_id))
            $url = $first_video;

        // custom
        $fifu_input_url = $_POST['fifu_input_url'] ?? '';
        if ($fifu_input_url && (fifu_is_custom_video($url) || fifu_is_local_video($url))) {
            fifu_update_or_delete($post_id, 'fifu_custom_video_url', $url);
            fifu_update_or_delete($post_id, 'fifu_video_url', '');
        } else {
            fifu_update_or_delete($post_id, 'fifu_video_url', $url);
            if ($url) {
                fifu_update_or_delete($post_id, 'fifu_image_url', '');
                fifu_update_or_delete($post_id, 'fifu_custom_video_url', '');

                /* captured video thumbnail */
                if (isset($_POST['fifu_video_captured_frame']) && isset($_POST['fifu_video_time_frame'])) {
                    $frame = $_POST['fifu_video_captured_frame'];
                    $time_frame = $_POST['fifu_video_time_frame'];
                    if ($frame)
                        fifu_upload_captured_iframe($frame, $url, $time_frame);
                }
            } else {
                fifu_update_or_delete($post_id, 'fifu_custom_video_url', $url);
            }
        }
    }

    /* gallery */
    if (get_post_type($post_id) == 'product') {
        if (empty($_POST))
            return;

        // delete all custom fields
        if (isset($_POST['inputHiddenVideoLength'])) {
            $length = $_POST['inputHiddenVideoLength'];
            for ($i = 0; $i < $length; $i++)
                delete_post_meta($post_id, 'fifu_video_url_' . $i);
        }

        // add custom fields
        if (isset($_POST['inputHiddenVideoListIds'])) {
            $list = $_POST['inputHiddenVideoListIds'];
            if (strlen($list) !== 0) {
                $indexes = explode('|', $list);
                $i = 0;
                foreach ($indexes as $index) {
                    $input_url = 'fifu_video_input_url_' . $index;
                    if (isset($_POST[$input_url])) {
                        $url = esc_url_raw(rtrim($_POST[$input_url]));
                        fifu_update_or_delete($post_id, 'fifu_video_url_' . $i, $url);
                        $i++;
                    }
                }
            }
        }
    }
}

function fifu_slider_save_properties($post_id) {
    if (empty($_POST))
        return;

    /* ratio */
    $ratio = $_POST['fifu_slider_input_ratio'] ?? '';
    fifu_update_or_delete_value($post_id, 'fifu_slider_ratio', $ratio);

    // delete all custom fields
    if (isset($_POST['inputHiddenSliderLength'])) {
        $length = $_POST['inputHiddenSliderLength'];
        for ($i = 0; $i < $length; $i++) {
            delete_post_meta($post_id, 'fifu_slider_image_url_' . $i);
            delete_post_meta($post_id, 'fifu_slider_image_alt_' . $i);
        }
    }

    // add custom fields
    if (isset($_POST['inputHiddenSliderListIds'])) {
        $list = $_POST['inputHiddenSliderListIds'];
        if (strlen($list) !== 0) {
            $indexes = explode('|', $list);
            $i = 0;
            foreach ($indexes as $index) {
                $input_url = 'fifu_slider_input_url_' . $index;
                $input_alt = 'fifu_slider_input_alt_' . $index;
                if (isset($_POST[$input_url])) {
                    $url = esc_url_raw(rtrim($_POST[$input_url]));
                    $alt = wp_strip_all_tags($_POST[$input_alt] ?? '');
                    fifu_update_or_delete($post_id, 'fifu_slider_image_url_' . $i, $url);
                    fifu_update_or_delete_value($post_id, 'fifu_slider_image_alt_' . $i, $alt);
                    $i++;
                }
            }
        }
    }
}

function fifu_update_or_delete_var($post_id, $field, $url) {
    if ($url)
        update_post_meta($post_id, $field, fifu_convert($url));
    else
        delete_post_meta($post_id, $field);
    fifu_update_fake_attach_id($post_id);
}

function fifu_update_or_delete($post_id, $field, $url) {
    if ($url) {
        update_post_meta($post_id, $field, $field != 'fifu_video_url' ? fifu_convert($url) : $url);
    } else
        delete_post_meta($post_id, $field, $url);
}

function fifu_update_or_delete_value($post_id, $field, $value) {
    if ($value)
        update_post_meta($post_id, $field, $value);
    else
        delete_post_meta($post_id, $field, $value);
}

function fifu_update_or_delete_ctgr($post_id, $field, $url) {
    if ($url) {
        update_term_meta($post_id, $field, $field != 'fifu_video_url' ? fifu_convert($url) : $url);
    } else
        delete_term_meta($post_id, $field, $url);
}

add_action('pmxi_before_xml_import', 'fifu_before_xml_import', 10, 1);

function fifu_before_xml_import($import_id) {
    if (fifu_is_on('fifu_auto_category')) {
        update_option('fifu_auto_category', 'toggleoff');
        update_option('fifu_auto_category_waiting', true);
    }
}

add_action('pmxi_after_xml_import', 'fifu_after_xml_import', 10, 1);

function fifu_after_xml_import($import_id) {
    if (get_option('fifu_auto_category_waiting')) {
        update_option('fifu_auto_category', 'toggleon');
        fifu_db_insert_auto_category_image();
        update_option('fifu_auto_category_created', true, 'no');
        delete_option('fifu_auto_category_waiting');
    }
}

function fifu_wai_save($post_id, $is_ctgr, $video_priority) {
    $urls = rtrim(get_post_meta($post_id, 'fifu_list_url', true), '|');
    $alts = rtrim(get_post_meta($post_id, 'fifu_list_alt', true), '|');
    if ($urls) {
        $urls = explode("|", $urls);
        if ($alts)
            $alts = explode("|", $alts);
        $i = 0;
        $i_alt = 0;
        // check if there is featured video
        $has_main = $video_priority && rtrim(get_post_meta($post_id, 'fifu_list_video_url', true), '|');
        foreach ($urls as $url) {
            $url = trim($url);
            $alt = $alts && sizeof($alts) > $i_alt ? $alts[$i_alt++] : '';

            if (!$has_main) {
                if ($url) {
                    fifu_update_or_delete($post_id, 'fifu_image_url', $url);
                    fifu_update_or_delete($post_id, 'fifu_image_alt', $alt);
                    $has_main = true;
                    delete_post_meta($post_id, 'fifu_video_url');
                    delete_post_meta($post_id, 'fifu_custom_video_url');
                }
            } else {
                if ($url) {
                    fifu_update_or_delete($post_id, 'fifu_image_url_' . $i, $url);
                    fifu_update_or_delete($post_id, 'fifu_image_alt_' . $i, $alt);
                    $i++;
                }
            }
        }
        // update: remove extra fields
        while (true) {
            $url = get_post_meta($post_id, 'fifu_image_url_' . $i, true);
            if ($url) {
                delete_post_meta($post_id, 'fifu_image_url_' . $i);
                delete_post_meta($post_id, 'fifu_image_alt_' . $i);
            } else
                break;
            $i++;
        }
    } else {
        // fifu_list_url exists, but it's empty: delete all urls
        if (!empty(get_metadata('post', $post_id, 'fifu_list_url'))) {
            delete_post_meta($post_id, 'fifu_image_url');
            $i = 0;
            while (true) {
                $url = get_post_meta($post_id, 'fifu_image_url_' . $i, true);
                if ($url) {
                    delete_post_meta($post_id, 'fifu_image_url_' . $i);
                    delete_post_meta($post_id, 'fifu_image_alt_' . $i);
                } else
                    break;
                $i++;
            }
            delete_post_meta($post_id, 'fifu_list_url');
            delete_post_meta($post_id, 'fifu_list_alt');
        }

        $isbn = get_post_meta($post_id, 'fifu_isbn', true);
        fifu_update_or_delete($post_id, 'fifu_isbn', $isbn);

        $asin = get_post_meta($post_id, 'fifu_asin', true);
        fifu_update_or_delete($post_id, 'fifu_asin', $asin);

        $finder_url = get_post_meta($post_id, 'fifu_finder_url', true);
        if ($finder_url)
            update_post_meta($post_id, 'fifu_finder_url', $finder_url);
        else
            delete_post_meta($post_id, 'fifu_finder_url', $finder_url);

        if ($is_ctgr) {
            $url = get_term_meta($post_id, 'fifu_image_url', true);
            $alt = get_term_meta($post_id, 'fifu_image_alt', true);
            if (!$url) {
                delete_term_meta($post_id, 'fifu_image_url', $url);
                delete_term_meta($post_id, 'fifu_image_alt', $alt);
            } else {
                fifu_update_or_delete_ctgr($post_id, 'fifu_image_url', $url);
                fifu_update_or_delete_ctgr($post_id, 'fifu_image_alt', $alt);
            }
        } else {
            $url = get_post_meta($post_id, 'fifu_image_url', true);
            $alt = get_post_meta($post_id, 'fifu_image_alt', true);
            if (!$url) {
                delete_post_meta($post_id, 'fifu_image_url', $url);
                delete_post_meta($post_id, 'fifu_image_alt', $alt);
            } else {
                fifu_update_or_delete($post_id, 'fifu_image_url', $url);
                fifu_update_or_delete($post_id, 'fifu_image_alt', $alt);
            }
        }
    }
}

function fifu_wai_video_save($post_id, $is_ctgr, $video_priority) {
    $urls = get_post_meta($post_id, 'fifu_list_video_url', true);
    if ($urls) {
        $urls = explode("|", $urls);
        $i = 0;
        // check if there is featured image
        $has_main = !$video_priority && rtrim(get_post_meta($post_id, 'fifu_list_url', true), '|');
        foreach ($urls as $url) {
            $url = trim($url);
            if (!$has_main) {
                if (fifu_is_custom_video($url)) {
                    $parts = explode("\\", $url);
                    $video_url = $parts[0] ?? '';
                    $image_url = $parts[1] ?? get_option('fifu_default_url');
                    if ($image_url) {
                        fifu_update_or_delete($post_id, 'fifu_custom_video_url', $video_url);
                        $has_main = true;
                        fifu_update_or_delete($post_id, 'fifu_image_url', $image_url);
                    }
                } else {
                    fifu_update_or_delete($post_id, 'fifu_video_url', $url);
                    $has_main = true;
                    delete_post_meta($post_id, 'fifu_image_url');
                }
            } else {
                fifu_update_or_delete($post_id, 'fifu_video_url_' . $i, $url);
                $i++;
            }
        }
        // update: remove extra fields
        while (true) {
            $url = get_post_meta($post_id, 'fifu_video_url_' . $i, true);
            if ($url)
                delete_post_meta($post_id, 'fifu_video_url_' . $i);
            else
                break;
            $i++;
        }
    } else {
        // fifu_list_video_url exists, but it's empty: delete all urls
        if (!empty(get_metadata('post', $post_id, 'fifu_list_video_url'))) {
            delete_post_meta($post_id, 'fifu_video_url');
            delete_post_meta($post_id, 'fifu_custom_video_url');
            $i = 0;
            while (true) {
                $url = get_post_meta($post_id, 'fifu_video_url_' . $i, true);
                if ($url)
                    delete_post_meta($post_id, 'fifu_video_url_' . $i);
                else
                    break;
                $i++;
            }
            delete_post_meta($post_id, 'fifu_list_video_url');
        }

        if ($is_ctgr) {
            $url = get_term_meta($post_id, 'fifu_video_url', true);
            if ($url) {
                if (fifu_is_custom_video($url)) {
                    $parts = explode("\\", $url);
                    $video_url = $parts[0] ?? '';
                    $image_url = $parts[1] ?? get_option('fifu_default_url');
                    if ($image_url) {
                        fifu_update_or_delete_ctgr($post_id, 'fifu_custom_video_url', $video_url);
                        fifu_update_or_delete_ctgr($post_id, 'fifu_image_url', $image_url);
                        delete_term_meta($post_id, 'fifu_video_url');
                    }
                } else
                    fifu_update_or_delete_ctgr($post_id, 'fifu_video_url', $url);
            }
        } else {
            $url = get_post_meta($post_id, 'fifu_video_url', true);
            if ($url) {
                if (fifu_is_custom_video($url)) {
                    $parts = explode("\\", $url);
                    $video_url = $parts[0] ?? '';
                    $image_url = $parts[1] ?? get_option('fifu_default_url');
                    if ($image_url) {
                        fifu_update_or_delete($post_id, 'fifu_custom_video_url', $video_url);
                        fifu_update_or_delete($post_id, 'fifu_image_url', $image_url);
                        delete_post_meta($post_id, 'fifu_video_url');
                    }
                } else
                    fifu_update_or_delete($post_id, 'fifu_video_url', $url);
            }
        }
    }
}

function fifu_slider_wai_save($post_id) {
    $list = get_post_meta($post_id, 'fifu_slider_list_url', true);
    $alts = get_post_meta($post_id, 'fifu_slider_list_alt', true);
    if ($list) {
        $list = explode("|", $list);
        $alts = $alts ? explode("|", $alts) : null;
        $i = 0;
        foreach ($list as $url) {
            $url = trim($url);
            fifu_update_or_delete($post_id, 'fifu_slider_image_url_' . $i, $url);
            fifu_update_or_delete($post_id, 'fifu_slider_image_alt_' . $i, ($alts[$i] ?? ''));
            $i++;
        }
    }
}

function fifu_wai_iframe_save($post_id) {
    $list = get_post_meta($post_id, 'fifu_list_iframe_url', true);
    if ($list) {
        $list = explode("|", $list);
        $i = 0;
        foreach ($list as $url) {
            $url = trim($url);
            fifu_update_or_delete($post_id, 'fifu_image_ifm_' . $i, $url);
            $i++;
        }
    }
}

function fifu_split_lists($post_id) {
    fifu_wai_save($post_id, null, null);
    fifu_wai_video_save($post_id, null, null);
    fifu_slider_wai_save($post_id);
}

add_action('pmxi_saved_post', 'fifu_wai_xml_variations', 10, 3);

function fifu_wai_xml_variations($post_id, $xml_node, $is_update) {
    if (!class_exists('WooCommerce'))
        return;

    $product = wc_get_product($post_id);

    if (!$product)
        return;

    if (!$product->is_type('variable'))
        return;

    $record = json_decode(json_encode((array) $xml_node), 1);

    $variants = fifu_find_variants_xml($record);
    if (!$variants)
        return;

    // fix for single variant file
    if (!array_key_exists('0', $variants))
        $variants = array($variants);

    foreach ($variants as $variant) {
        $sku = $variant['sku'] ?? null;
        if (!$sku)
            continue;

        $product_id = wc_get_product_id_by_sku($sku);
        if (!$product_id)
            continue;

        $fifu_list_url = $variant['fifu_list_url'] ?? null;
        if (!$fifu_list_url)
            continue;

        fifu_dev_set_image_list($product_id, $fifu_list_url);
    }
}

function fifu_find_variants_xml($array) {
    if (!function_exists('searchArrayRecursively')) {

        function searchArrayRecursively($array) {
            foreach ($array as $item) {
                if (is_array($item)) {
                    if (isset($item['sku']) && isset($item['fifu_list_url'])) {
                        return $array;
                    }
                    $result = searchArrayRecursively($item);
                    if ($result !== null) {
                        return $result;
                    }
                }
            }
            return null;
        }

    }

    foreach ($array as $item) {
        if (is_array($item)) {
            $found = searchArrayRecursively($item);
            if ($found !== null) {
                return $found;
            }
        }
    }

    return null;
}

add_action('before_delete_post', 'fifu_db_before_delete_post');

add_action('wp_trash_post', 'fifu_db_delete_category_image');

/* product variation, save metadata */

add_action('woocommerce_rest_insert_product_variation_object', 'fifu_save_product_variation', 10, 3);

function fifu_save_product_variation($object, $request, $insert) {
    fifu_save_product($object, $request, $insert);
    fifu_db_update_wc_additional_variation_images($object->get_id());
}

add_action('woocommerce_rest_insert_product_object', 'fifu_save_product', 10, 3);

function fifu_save_product($object, $request, $insert) {
    $post_id = $object->get_id();
    $alts = null;
    $urls = null;
    $is_slider = false;
    $is_video = false;
    $video_urls = null;

    foreach ($object->get_meta_data() as $data) {
        if ($data->key == 'fifu_image_url') {
            fifu_update_or_delete($post_id, 'fifu_image_url', $data->value);
            fifu_update_fake_attach_id($post_id);
        } else if ($data->key == 'fifu_video_url') {
            fifu_update_or_delete($post_id, 'fifu_video_url', $data->value);
            fifu_update_fake_attach_id($post_id);
        } else if ($data->key == 'fifu_list_alt')
            $alts = $data->value;
        else if ($data->key == 'fifu_list_url')
            $urls = $data->value;
        else if ($data->key == 'fifu_slider_list_url') {
            $urls = $data->value;
            $is_slider = true;
        } else if ($data->key == 'fifu_list_video_url') {
            $video_urls = $data->value;
            $is_video = true;
        }
    }
    $urls = $urls ? array_filter(explode("|", $urls)) : null;
    $alts = $alts ? array_filter(explode("|", $alts)) : null;
    $video_urls = $video_urls ? array_filter(explode("|", $video_urls)) : null;

    if ($insert)
        fifu_db_insert($post_id, $urls, $alts, $is_slider, $video_urls);
    else
        fifu_db_update($post_id, $urls, $alts, $is_slider, $video_urls);
}

add_action('woocommerce_rest_insert_product_cat', 'fifu_save_product_category', 10, 2);

function fifu_save_product_category($object, $request) {
    $params = $request->get_params();
    if (empty($params) || !isset($params['meta_data']))
        return;

    $term_id = $object->term_id;

    foreach ($params['meta_data'] as $meta) {
        if ($meta['key'] == 'fifu_image_url' || $meta['key'] == 'fifu_image_alt')
            update_term_meta($term_id, $meta['key'], $meta['value']);
    }
    fifu_db_ctgr_update_fake_attach_id($term_id);
}

/* regular woocommerce import */

add_action('woocommerce_product_import_inserted_product_object', 'fifu_woocommerce_import');

function fifu_woocommerce_import($object) {
    $video_priority = fifu_video_list_priority($object);

    $post_id = $object->get_id();
    fifu_wai_save($post_id, null, $video_priority);
    fifu_wai_video_save($post_id, null, $video_priority);
    fifu_slider_wai_save($post_id);
    fifu_wai_iframe_save($post_id);

    fifu_db_insert_import($post_id, false);

    if (!fifu_get_transient('fifu_import_running')) {
        $request = new WP_REST_Request();
        $request->set_param('toggle', 'fifu_toggle_importpost');
        fifu_api_cron_add($request);
    }
    fifu_set_transient('fifu_import_running', true, 15);
}

/* plugin: wcfm */

function fifu_is_wcfm_active() {
    return is_plugin_active('wc-frontend-manager/wc_frontend_manager.php');
}

function fifu_get_wcfm_url($content) {
    $url_parts = explode('fifu_image_url=', $content);
    $url = $url_parts[1] ?? null;
    return $url ? urldecode(explode('&', $url)[0]) : null;
}

/* plugin: toolset forms */

function fifu_is_toolset_active() {
    return is_plugin_active('cred-frontend-editor/plugin.php');
}

/* plugin: aliplugin */

function fifu_is_aliplugin_active() {
    return is_plugin_active('aliplugin/aliplugin.php');
}

/* plugin: slotslaunch */

function fifu_is_slotslaunch_active() {
    return is_plugin_active('slotslaunch-wp/slotslaunch.php');
}

/* plugin: sirv */

function fifu_is_sirv_active() {
    return is_plugin_active('sirv/sirv.php');
}

/* dimensions */

function fifu_get_width_meta($req) {
    if (isset($req['fifu_input_url']) && isset($req['fifu_input_image_width']) && $req['fifu_input_url'])
        return wp_strip_all_tags($req['fifu_input_image_width']);

    if (isset($req['fifu_video_input_url']) && isset($req['fifu_video_input_image_width']) && $req['fifu_video_input_url'])
        return wp_strip_all_tags($req['fifu_video_input_image_width']);

    return null;
}

function fifu_get_height_meta($req) {
    if (isset($req['fifu_input_url']) && isset($req['fifu_input_image_height']) && $req['fifu_input_url'])
        return wp_strip_all_tags($req['fifu_input_image_height']);

    if (isset($req['fifu_video_input_url']) && isset($req['fifu_video_input_image_height']) && $req['fifu_video_input_url'])
        return wp_strip_all_tags($req['fifu_video_input_image_height']);

    return null;
}

function fifu_get_dimension_meta_slider($req, $dimension) {
    $arr = array();

    if (!isset($req['inputHiddenSliderListIds']))
        return $arr;

    $list = $req['inputHiddenSliderListIds'];
    if (!$list && $list != "0")
        return $arr;

    $indexes = explode('|', $list);
    foreach ($indexes as $index) {
        $input_arr = "fifu_slider_input_{$dimension}_{$index}";
        if (isset($req[$input_arr]))
            array_push($arr, wp_strip_all_tags($req[$input_arr]));
    }
    return $arr;
}

function fifu_get_dimension_meta_image_gallery($req, $dimension) {
    $arr = array();

    if (!isset($req['inputHiddenImageListIds']))
        return $arr;

    $list = $req['inputHiddenImageListIds'];
    if (!$list && $list != "0")
        return $arr;

    $indexes = explode('|', $list);
    foreach ($indexes as $index) {
        $input_arr = "fifu_input_{$dimension}_{$index}";
        if (isset($req[$input_arr]))
            array_push($arr, wp_strip_all_tags($req[$input_arr]));
    }
    return $arr;
}

function fifu_get_dimension_meta_video_gallery($req, $dimension) {
    $arr = array();

    if (!isset($req['inputHiddenVideoListIds']))
        return $arr;

    $list = $req['inputHiddenVideoListIds'];
    if (!$list && $list != "0")
        return $arr;

    $indexes = explode('|', $list);
    foreach ($indexes as $index) {
        $input_arr = "fifu_video_input_{$dimension}_{$index}";
        if (isset($req[$input_arr]))
            array_push($arr, wp_strip_all_tags($req[$input_arr]));
    }
    return $arr;
}

/* plugin: wordpress importer */

add_action('import_end', 'fifu_import_end', 10, 0);

function fifu_import_end() {
    if (isset($_POST['action']) && $_POST['action'] == "woocommerce_csv_import_request" && !isset($_POST['mapping']))
        return;

    fifu_db_delete_thumbnail_id_without_attachment();
    fifu_enable_fake();
}

/* plugin: yoast duplicate post */

function fifu_duplicate_post_meta_keys_filter($meta_keys) {
    $remove_thumbnail = false;
    $thumbnail_id = null;

    for ($i = 0; $i < count($meta_keys); $i++) {
        if (fifu_starts_with($meta_keys[$i], 'fifu'))
            $remove_thumbnail = true;
        elseif ($meta_keys[$i] == '_thumbnail_id')
            $thumbnail_id = $i;
    }

    if ($remove_thumbnail)
        unset($meta_keys[$thumbnail_id]);

    return $meta_keys;
}

add_filter('duplicate_post_meta_keys_filter', 'fifu_duplicate_post_meta_keys_filter');

/* plugin: aawp */

function fifu_get_url_from_aawp($post_id) {
    $post_content = get_post_field('post_content', $post_id);
    if (strpos($post_content, '[amazon bestseller="') !== false) {
        $matches = array();
        preg_match('/\[amazon bestseller=[^\]]+\]/', $post_content, $matches);
        $shortcode = $matches[0] ?? '';
        if ($shortcode) {
            $text = explode('"', $shortcode)[1] ?? '';
            $asins = fifu_get_aawp_asins('bestseller', $text);
            if ($asins) {
                $asins = explode(',', $asins);
                $asin = $asins[array_rand($asins)];
                $image_ids = fifu_get_aawp_image_ids($asin);
                if ($image_ids) {
                    $image_id = explode(',', $image_ids)[0];
                    return "https://m.media-amazon.com/images/I/{$image_id}.jpg";
                }
            }
        }
    }
    return null;
}

/* plugin: bear - bulk editor and products manager professional for woocommerce */

add_filter('woobe_before_update_product_field', 'fifu_woobe_bulk_finished', 10, 3);

function fifu_woobe_bulk_finished($value, $product_id, $field_key) {
    if ($field_key == 'fifu_image_url')
        fifu_dev_set_image($product_id, $value);
    elseif ($field_key == 'fifu_video_url')
        fifu_dev_set_video($product_id, $value);
    elseif ($field_key == 'fifu_list_url')
        fifu_dev_set_image_list($product_id, $value);

    return $value;
}

/* plugin: dokan */

add_action('dokan_new_product_after_product_tags', 'fifu_dokan_new_product_after_product_tags', 10);

function fifu_dokan_new_product_after_product_tags() {
    $fifu = fifu_get_strings_dokan();
    ?>

    <div class="dokan-form-group">
        <label for="fifu_input_url" class="form-label"><span class="dashicons dashicons-camera" style="font-size:20px"></span> <?php $fifu['title']['product']['image'](); ?></label>
        <input type="text" class="dokan-form-control" name="fifu_input_url" placeholder="<?php $fifu['placeholder']['product']['image'](); ?>">
    </div>

    <?php
    // gallery
    fifu_dokan_import_scripts();

    fifu_wc_show_elements(get_post());
}

add_action('dokan_product_edit_after_product_tags', 'fifu_dokan_product_edit_after_product_tags', 99, 2);

function fifu_dokan_product_edit_after_product_tags($post, $post_id) {
    $fifu = fifu_get_strings_dokan();
    $url = esc_url(get_post_meta($post_id, 'fifu_image_url', true) ?? '');
    ?>

    <div class="dokan-form-group">
        <label for="fifu_input_url" class="form-label"><span class="dashicons dashicons-camera" style="font-size:20px"></span> <?php $fifu['title']['product']['image'](); ?></label>
        <input type="text" class="dokan-form-control" name="fifu_input_url" value="<?php echo $url; ?>" placeholder="<?php $fifu['placeholder']['product']['image'](); ?>">
    </div>

    <?php
    // gallery
    fifu_dokan_import_scripts();

    fifu_wc_show_elements($post);
}

add_action('dokan_new_product_added', 'fifu_dokan_save_meta', 10, 2);
add_action('dokan_product_updated', 'fifu_dokan_save_meta', 10, 2);

function fifu_dokan_save_meta($post_id, $data) {
    if (!dokan_is_user_seller(get_current_user_id()))
        return;

    /* featured image */

    $url = esc_url_raw(rtrim($data['fifu_input_url'] ?? ''));
    fifu_update_or_delete($post_id, 'fifu_image_url', $url);

    /* gallery */

    // delete all custom fields
    if (isset($data['inputHiddenImageLength'])) {
        $length = $data['inputHiddenImageLength'];
        for ($i = 0; $i < $length; $i++) {
            delete_post_meta($post_id, 'fifu_image_url_' . $i);
            delete_post_meta($post_id, 'fifu_image_alt_' . $i);
            delete_post_meta($post_id, 'fifu_image_ifm_' . $i);
        }
    }
    // add custom fields
    if (isset($data['inputHiddenImageListIds'])) {
        $list = $data['inputHiddenImageListIds'];
        if (strlen($list) !== 0) {
            $indexes = explode('|', $list);
            $i = 0;
            foreach ($indexes as $index) {
                $input_url = 'fifu_input_url_' . $index;
                $input_alt = 'fifu_input_alt_' . $index;
                $input_ifm = 'fifu_input_ifm_' . $index;
                if (isset($data[$input_url]) && isset($data[$input_alt])) {
                    $url = esc_url_raw(rtrim($data[$input_url]));
                    $alt = wp_strip_all_tags($data[$input_alt]);
                    $ifm = esc_url_raw(rtrim($data[$input_ifm] ?? ''));
                    fifu_update_or_delete($post_id, 'fifu_image_url_' . $i, $url);
                    fifu_update_or_delete_value($post_id, 'fifu_image_alt_' . $i, $alt);
                    fifu_update_or_delete_value($post_id, 'fifu_image_ifm_' . $i, $ifm);
                    $i++;
                }
            }
        }
    }

    fifu_update_fake_attach_id($post_id);
}

function fifu_dokan_import_scripts() {
    wp_enqueue_script('jquery-block-ui', 'https://cdnjs.cloudflare.com/ajax/libs/jquery.blockUI/2.70/jquery.blockUI.min.js');
    wp_enqueue_style('fancy-box-css', 'https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.css');
    wp_enqueue_script('fancy-box-js', 'https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.min.js');

    wp_register_style('jquery-sortablejs-css', plugins_url('/html/css/sortable.css', __FILE__), array(), fifu_version_number_enq());
    wp_enqueue_style('jquery-sortablejs-css');

    wp_enqueue_script('fifu-convert-url-js', plugins_url('/html/js/convert-url.js', __FILE__), array('jquery'), fifu_version_number_enq());

    wp_localize_script('fifu-convert-url-js', 'fifuConvertUrlVars', [
        'restUrl' => esc_url_raw(rest_url()),
        'homeUrl' => esc_url_raw(home_url()),
        'nonce' => wp_create_nonce('wp_rest'),
        'partialKey' => fifu_partial_key(),
    ]);
}

/* plugin: multivendorx */

add_action('mvx_product_manager_right_panel_after', 'fifu_mvx_product_manager_right_panel_after', 10);

function fifu_mvx_product_manager_right_panel_after($post_id) {
    $fifu = fifu_get_strings_dokan();
    $url = esc_url(get_post_meta($post_id, 'fifu_image_url', true) ?? '');
    ?>

    <br>

    <div>
        <label for="fifu_input_url" class="form-label"><span class="dashicons dashicons-camera" style="font-size:20px"></span> <?php $fifu['title']['product']['image'](); ?></label>
        <br>
        <input class="form-control" type="url" name="fifu_input_url" value="<?php echo $url; ?>" placeholder="<?php $fifu['placeholder']['product']['image'](); ?>">
    </div>

    <?php
}

add_action('mvx_process_product_object', 'fifu_mvx_process_product_object', 10);

function fifu_mvx_process_product_object($data) {
    $url = $data->get_meta('fifu_image_url');
    $url = $url ? $url : null;
    fifu_dev_set_image($data->id, $url);
}

/* plugin: datafeedr */

add_filter('dfrps_do_import_product_thumbnail/do_import', function (bool $do_import, WP_Post $post, array $product) {
    if (!isset($product['image']))
        return $do_import;

    $urls = array();
    $fields = ['image', 'alternateimage', 'alternateimagefour', 'alternateimagethree', 'alternateimagetwo',];
    foreach ($fields as $field) {
        if (isset($product[$field]))
            array_push($urls, $product[$field]);
    }
    $urls = array_unique($urls);

    $do_import = false;
    fifu_dev_set_image_list($post->ID, implode('|', $urls));

    return $do_import;
}, 10, 3);

/* plugin: polylang-pro */

add_filter('pll_copy_post_metas', 'fifu_pll_copy_post_metas', 10, 5);

function fifu_pll_copy_post_metas($metas, $sync, $from, $to, $lang) {
    if ($sync) {
        if (in_array('_thumbnail_id', $metas)) {
            $att_id = get_post_thumbnail_id($to);
            if (get_post_field('post_author', $att_id) == FIFU_AUTHOR) {
                unset($metas['_product_image_gallery']);
                unset($metas['_thumbnail_id']);
            }
        }
        return $metas;
    }

    if (in_array('fifu_list_url', $metas)) {
        unset($metas['_product_image_gallery']);
        unset($metas['_thumbnail_id']);
        $metas = fifu_remove_from_arr_by_str($metas, '_thumbnail_id');
        fifu_dev_set_image_list($to, null);
        fifu_dev_set_image_list($to, get_post_meta($from, 'fifu_list_url', true));
    }

    // return array_merge($metas, array('my_post_meta'));
    return $metas;
}

function fifu_remove_from_arr_by_str($array, $string) {
    $new_array = [];
    foreach ($array as $element) {
        if (strpos($element, $string) === false) {
            $new_array[] = $element;
        }
    }
    return $new_array;
}

// hide all FIFU metas from the Custom Fields box

add_filter('is_protected_meta', function ($protected, $meta_key, $meta_type) {
    if ($meta_type === 'post' && 0 === strpos($meta_key, 'fifu_')) {
        return true;
    }
    return $protected;
}, 10, 3);

