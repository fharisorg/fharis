<?php

define('FIFU_AUTHOR', get_option('fifu_author') ?: 77777);

add_filter('get_attached_file', 'fifu_replace_attached_file', 10, 2);

function fifu_replace_attached_file($att_url, $att_id) {
    return fifu_process_url($att_url, $att_id);
}

function fifu_process_url($att_url, $att_id) {
    if (strpos($att_url, "https://thumbnails.odycdn.com") === 0 ||
            strpos($att_url, "https://res.cloudinary.com/glide/") === 0 ||
            strpos($att_url, "https://i0.fifu.app") === 0 ||
            strpos($att_url, str_replace("//", "//img.", get_home_url())) === 0 ||
            strpos($att_url, "//wp.fifu.app") === 0)
        return $att_url;

    if (!$att_id)
        return $att_url;

    $att_post = get_post($att_id);

    if (!$att_post)
        return $att_url;

    // internal
    if ($att_post->post_author != FIFU_AUTHOR) {
        fifulocal_add_url_parameters($att_post->guid, $att_id);
        return $att_url;
    }

    $url = get_post_meta($att_id, '_wp_attached_file', true); // to avoid wp_get_attachment_url() infinite loop

    fifu_fix_legacy($url, $att_id);

    return fifu_process_external_url($url, $att_id, null);
}

function fifu_process_external_url($url, $att_id, $size) {
    return fifu_add_url_parameters($url, $att_id, $size);
}

function fifu_fix_legacy($url, $att_id) {
    if (strpos($url, ';') === false)
        return;
    $att_url = get_post_meta($att_id, '_wp_attached_file');
    $att_url = is_array($att_url) ? ($att_url[0] ?? '') : $att_url;
    if (fifu_starts_with($att_url, ';http') || fifu_starts_with($att_url, ';/'))
        update_post_meta($att_id, '_wp_attached_file', $url);
}

add_filter('admin_post_thumbnail_html', 'fifu_admin_post_thumbnail_html', 10, 3);

function fifu_admin_post_thumbnail_html($content, $post_id, $thumbnail_id) {
    return $content;
}

add_filter('wp_get_attachment_url', 'fifu_replace_attachment_url', 10, 2);

function fifu_replace_attachment_url($att_url, $att_id) {
    if ($att_url)
        return fifu_process_url($att_url, $att_id);
    return $att_url;
}

add_filter('posts_where', 'fifu_query_attachments');

function fifu_query_attachments($where) {
    global $wpdb;
    if (fifu_is_web_story() || (($_POST['action'] ?? '') == 'query-attachments' || ($_POST['action'] ?? '') == 'get-attachment'))
        $where .= $wpdb->prepare(" AND {$wpdb->posts}.post_author <> %d ", FIFU_AUTHOR);
    return $where;
}

add_filter('posts_where', function ($where, \WP_Query $q) {
    global $wpdb;
    if (fifu_is_web_story() || (is_admin() && $q->is_main_query() && strpos($where, 'attachment') !== false))
        $where .= $wpdb->prepare(" AND {$wpdb->posts}.post_author <> %d ", FIFU_AUTHOR);
    return $where;
}, 10, 2);

add_filter('wp_get_attachment_image_src', 'fifu_replace_attachment_image_src', 10, 3);

function fifu_replace_attachment_image_src($image, $att_id, $size) {
    if (!$image || !$att_id || fifu_is_houzez_active() || fifu_is_wpresidence_active())
        return $image;

    $att_post = get_post($att_id);

    if (!$att_post)
        return $image;

    // internal
    if ($att_post->post_author != FIFU_AUTHOR)
        return $image;

    global $FIFU_SESSION;
    $prev_url = null;
    if (isset($FIFU_SESSION['cdn-new-old']) && isset($image[0]) && isset($FIFU_SESSION['cdn-new-old'][$image[0]]))
        $prev_url = $FIFU_SESSION['cdn-new-old'][$image[0]];

    $FIFU_SESSION['att_img_src'] = $FIFU_SESSION['att_img_src'] ?? array();

    $image[0] = fifu_process_url($image[0] ?? '', $att_id);

    $original_url = fifu_main_image_url(get_queried_object_id(), true);
    if (fifu_should_hide() && ($original_url == $image[0] || ($prev_url && $prev_url == $original_url))) {
        if (!in_array($original_url, $FIFU_SESSION['att_img_src'])) {
            $aux = is_array($size) ? implode(',', $size) : $size;
            $FIFU_SESSION['att_img_src'][] = $original_url . $aux;
            return null;
        }
    }

    $FIFU_SESSION['att_img_src'][] = $original_url;

    if (fifu_is_from_speedup($image[0] ?? ''))
        $image = fifu_speedup_get_url($image, $size, $att_id);

    // photon
    if (fifu_is_on('fifu_photon') && !fifu_jetpack_blocked($image[0] ?? '') && !fifu_is_in_editor())
        $image = fifu_get_photon_url($image, $size, $att_id);

    if (fifu_is_on('fifu_screenshot')) {
        if (fifu_is_screenshot($image[0] ?? '')) {
            $screenshot_size = get_option('fifu_screenshot_size');
            $image[0] = fifu_replace_screenshot_size($image[0], $screenshot_size);
        }
    }

    if (($image[1] ?? 0) <= 1 && ($image[2] ?? 0) <= 1) {
        $result = fifu_add_size($image, $size);
        $image = $result['image'] ?? $image;
    }

    return $image;
}

function fifu_add_size($image, $size) {
    // Get size details using fifu_get_image_size_details
    $size_details = fifu_get_image_size_details($size);

    // If no valid size details are found, return the original image with null crop
    if (!($size_details['width'] ?? false) && !($size_details['height'] ?? false)) {
        return array(
            'image' => $image,
            'crop' => null
        );
    }

    // Assign only width and height to the image array
    $image[1] = $size_details['width'] ?? 0;
    $image[2] = $size_details['height'] ?? 0;

    // Return the modified image and crop separately
    return array(
        'image' => $image,
        'crop' => $size_details['crop'] ?? false
    );
}

function fifu_get_photon_url($image, $size, $att_id) {
    if (fifu_is_on('fifu_otfcdn'))
        return $image;

    $result = fifu_add_size($image, $size);
    $image = $result['image'] ?? $image;
    $w = $image[1] ?? 0;
    $h = $image[2] ?? 0;
    $c = ($result['crop'] ?? false) ? 1 : 0;

    $image[0] = fifu_jetpack_photon_url($image[0] ?? '', "?w={$w}&h={$h}&c={$c}", $att_id);
    $image[0] = fifu_process_external_url($image[0], $att_id, $size);

    return $image;
}

add_filter('wp_calculate_image_sizes', 'fifu_replace_calculate_image_sizes', 10, 3);

function fifu_replace_calculate_image_sizes($sizes, $array, $src) {
    return $sizes;
}

add_filter('wp_calculate_image_srcset', 'fifu_replace_calculate_image_srcset', 10, 5);

function fifu_replace_calculate_image_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id) {
    return $sources;
}

//add_filter('wp_img_tag_add_srcset_and_sizes_attr', 'fifu_wp_img_tag_add_srcset_and_sizes_attr', 10, 4);
//function fifu_wp_img_tag_add_srcset_and_sizes_attr($value, $image, $context, $attachment_id) {
//    return $value;
//}

add_filter('wp_calculate_image_srcset_meta', 'fifu_wp_calculate_image_srcset_meta', 10, 4);

function fifu_wp_calculate_image_srcset_meta($image_meta, $size_array, $image_src, $attachment_id) {
    return $image_meta;
}

add_filter('max_srcset_image_width', 'fifu_max_srcset_image_width', 10, 2);

function fifu_max_srcset_image_width($max_width, $size_array) {
    return $max_width;
}

add_action('template_redirect', 'fifu_action', 10);

function fifu_action() {
    ob_start("fifu_callback");
}

function fifu_callback($buffer) {
    global $FIFU_SESSION;

    if (empty($buffer))
        return $buffer;

    /* plugins: Oxygen, Bricks */
    if (isset($_REQUEST['ct_builder']) || isset($_REQUEST['bricks']) || isset($_REQUEST['fb-edit']))
        return $buffer;

    /* img */

    $buffer = fifu_filter_og_images($buffer);

    $srcType = "src";
    $imgList = array();
    preg_match_all('/<img[^>]*>/', $buffer, $imgList);

    foreach (($imgList[0] ?? []) as $imgItem) {
        preg_match('/(' . $srcType . ')([^\'\"]*[\'\"]){2}/', $imgItem, $src);
        if (!$src)
            continue;
        $del = substr($src[0] ?? '', - 1);
        $url = fifu_normalize(explode($del, $src[0] ?? '')[1] ?? '');
        $post_id = null;

        // get parameters
        $data = null;
        $prev_url = null;

        if (isset($FIFU_SESSION[$url])) {
            $data = $FIFU_SESSION[$url];
        } else {
            if (isset($FIFU_SESSION['cdn-new-old'][$url])) {
                $prev_url = $FIFU_SESSION['cdn-new-old'][$url];
                if (isset($FIFU_SESSION[$prev_url])) {
                    $data = $FIFU_SESSION[$prev_url];
                }
            }
        }

        if (!$data)
            continue;

        if (strpos($imgItem, 'fifu-replaced') !== false)
            continue;

        if ($data['local'] ?? false)
            continue;

        $post_id = $data['post_id'] ?? null;
        $att_id = $data['att_id'] ?? null;
        $type = $data['type'] ?? null;
        $featured = $data['featured'] ?? null;
        $gallery = $data['gallery'] ?? null;
        $is_category = $data['category'] ?? false;
        $theme_width = $data['theme-width'] ?? null;
        $theme_height = $data['theme-height'] ?? null;

        if ($featured && is_single()) {
            $buffer = str_replace('</head>', '<link rel="preload" as="image" href="' . esc_url($url) . '">' . "</head>\n", $buffer);
        }

        // video
        if (fifu_is_video_thumb($url) || fifu_is_video_thumb($prev_url) || get_post_meta($post_id, 'fifu_custom_video_url', true) || get_post_meta($post_id, 'fifu_audio_url', true) || fifu_is_video_thumb(fifu_decode_pubcdn_url($url))) {
            if (fifu_is_on('fifu_video') || fifu_is_on('fifu_audio')) {
                // add video class
                if (strpos($imgItem, 'class=') !== false)
                    $newImgItem = str_replace(' class=' . $del, ' class=' . $del . 'fifu-video ', $imgItem);
                else
                    $newImgItem = str_replace('<img ', '<img class=' . $del . 'fifu-video' . $del . ' ', $imgItem);

                // add featured
                if ($featured)
                    $newImgItem = str_replace('<img ', '<img fifu-featured="1" ', $newImgItem);

                // add status
                $newImgItem = str_replace('<img ', '<img fifu-replaced="1" ', $newImgItem);

                // speed up
                if (fifu_is_from_speedup($url)) {
                    $newImgItem = str_replace('<img ', '<img srcset="' . fifu_speedup_get_set($url) . '" ', $newImgItem);
                    $newImgItem = str_replace('<img ', '<img sizes="(max-width:' . $theme_width . 'px) 100vw, ' . $theme_width . 'px" ', $newImgItem);
                }

                // submit
                $buffer = str_replace($imgItem, $newImgItem, $buffer);
            }
        } else {
            // slider
            if ($type == 'slider') {
                if ((fifu_is_houzez_active() || fifu_is_wpresidence_active()) && fifu_is_on('fifu_photon')) {
                    $srcset = fifu_jetpack_get_set(fifu_jetpack_photon_url($url, null, $att_id), true);
                    $newImgItem = str_replace('<img ', '<img srcset="' . $srcset . '" ', $imgItem);
                    $buffer = str_replace($imgItem, fifu_replace($newImgItem, $post_id, null, null, null), $buffer);
                    continue;
                } else {
                    if (strpos($imgItem, 'fifu-grid-img') !== false)
                        continue;

                    if (is_singular('product') && $featured)
                        continue;

                    $slider_url = get_post_meta($post_id, 'fifu_slider_image_url_0', true);
                    if (is_from_jetpack($url)) {
                        $aux = explode('//', $slider_url)[1] ?? '';
                        if (strpos($url, $aux) === false)
                            continue;
                    } elseif ($url != $slider_url)
                        continue;
                }
            }


            if ($featured) {
                // add featured
                $newImgItem = str_replace('<img ', '<img fifu-featured="' . $featured . '" ', $imgItem);

                // add category 
                if ($is_category)
                    $newImgItem = str_replace('<img ', '<img fifu-category="1" ', $newImgItem);

                // add post_id
                if (get_post_type($post_id) == 'product')
                    $newImgItem = str_replace('<img ', '<img product-id="' . $post_id . '" ', $newImgItem);
                else
                    $newImgItem = str_replace('<img ', '<img post-id="' . $post_id . '" ', $newImgItem);

                // add theme sizes
                if ($theme_width && $theme_height) {
                    $newImgItem = str_replace('<img ', '<img theme-width="' . $theme_width . '" ', $newImgItem);
                    $newImgItem = str_replace('<img ', '<img theme-height="' . $theme_height . '" ', $newImgItem);
                }

                // speed up (doesn't work with ajax calls)
                if (fifu_is_from_speedup($url)) {
                    $newImgItem = str_replace('<img ', '<img srcset="' . fifu_speedup_get_set($url) . '" ', $newImgItem);
                    $newImgItem = str_replace('<img ', '<img sizes="(max-width:' . $theme_width . 'px) 100vw, ' . $theme_width . 'px" ', $newImgItem);
                }

                $buffer = str_replace($imgItem, fifu_replace($newImgItem, $post_id, null, null, null), $buffer);
            }
        }
    }

    /* background-image */

    $imgList = array();
    preg_match_all('/<[^>]*background-image[^>]*>/', $buffer, $imgList);
    foreach (($imgList[0] ?? []) as $imgItem) {
        if (strpos($imgItem, 'style=') === false || strpos($imgItem, 'url(') === false)
            continue;

        $mainDelimiter = substr(explode('style=', str_replace('\\', '', $imgItem))[1] ?? '', 0, 1);
        $subDelimiter = substr(explode('url(', str_replace('\\', '', $imgItem))[1] ?? '', 0, 1);
        if (in_array($subDelimiter, array('"', "'", ' ')))
            $url = preg_split('/[\'\" ]{1}\)/', preg_split('/url\([\'\" ]{1}/', $imgItem, -1)[1] ?? '', -1)[0] ?? '';
        else {
            $url = preg_split('/\)/', preg_split('/url\(/', $imgItem, -1)[1] ?? '', -1)[0] ?? '';
            $subDelimiter = '';
        }

        $newImgItem = $imgItem;

        $url = fifu_normalize($url);
        if (isset($FIFU_SESSION[$url])) {
            $data = $FIFU_SESSION[$url];

            if (strpos($imgItem, 'fifu-replaced') !== false)
                continue;

            if ($data['local'] ?? false)
                continue;

            $att_id = $data['att_id'] ?? null;

            $post_id = $data['post_id'] ?? null;
            $newImgItem = str_replace('>', ' ' . 'post-id="' . $post_id . '">', $newImgItem);
        }

        if ($newImgItem != $imgItem)
            $buffer = str_replace($imgItem, $newImgItem, $buffer);
    }

    return $buffer;
}

add_filter('woocommerce_single_product_image_thumbnail_html', 'fifu_woocommerce_single_product_image_thumbnail_html', 10, 2);

function fifu_woocommerce_single_product_image_thumbnail_html($html, $post_id = null) {
    return $html;
}

add_filter('wp_get_attachment_metadata', 'fifu_filter_wp_get_attachment_metadata', 10, 2);

function fifu_filter_wp_get_attachment_metadata($data, $att_id) {
    return $data;
}

add_filter('wp_get_attachment_image', 'fifu_wp_get_attachment_image', 10, 5);

function fifu_wp_get_attachment_image($html, $attachment_id, $size, $icon, $attr) {
    return $html;
}

function fifu_add_url_parameters($url, $att_id, $size) {
    global $FIFU_SESSION;

    // avoid duplicated call
    if (isset($FIFU_SESSION[$url]))
        return $url;

    $post = get_post($att_id);
    $post_id = $post ? $post->post_parent : null;

    if (!$post_id)
        return $url;

    // "categories" page
    if (function_exists('get_current_screen') && isset(get_current_screen()->parent_file) && get_current_screen()->parent_file == 'edit.php?post_type=product' && get_current_screen()->id == 'edit-product_cat')
        return fifu_optimized_column_image($url, $att_id);

    if (fifu_is_on('fifu_video')) {
        // custom
        $video_url = get_post_meta($post_id, 'fifu_custom_video_url', true);
        if ($video_url) {
            $FIFU_SESSION['fifu-custom-video'][$url] = $video_url;
            $FIFU_SESSION['fifu-permalink'][$url] = get_permalink($post_id);
        }

        // others
        if (fifu_is_video_thumb($url)) {
            if (!isset($FIFU_SESSION) || (isset($FIFU_SESSION) && !isset($FIFU_SESSION['fifu-video'][$url]))) {
                if (fifu_is_youtube_thumb($url) || fifu_is_vimeo_thumb($url) || fifu_is_local_thumb($url) || fifu_is_wpcom_thumb($url) || fifu_is_jwplayer_thumb($url) || fifu_is_sprout_thumb($url) || fifu_is_odysee_thumb($url) || fifu_is_rumble_thumb($url) || fifu_is_dailymotion_thumb($url) || fifu_is_twitter_thumb($url) || fifu_is_tiktok_thumb($url) || fifu_is_googledrive_thumb($url) || fifu_is_mega_thumb($url) || fifu_is_bunny_thumb($url) || fifu_is_bitchute_thumb($url) || fifu_is_brighteon_thumb($url) || fifu_is_soundcloud_thumb($url) || fifu_is_spotify_thumb($url) || fifu_is_amazon_thumb($url)) {
                    $original_image_url = fifu_original_image_url($url);
                    $original_image_url = fifu_is_suvideo_thumb($original_image_url) ? fifu_suvideo_2nd_thumb_url_only($original_image_url) : $original_image_url;
                    $FIFU_SESSION['fifu-video'][$url] = fifu_video_src_by_img($original_image_url);
                    $FIFU_SESSION['fifu-permalink'][$url] = get_permalink($post_id);
                }
            }
        }
    }

    if (fifu_is_on('fifu_audio')) {
        $audio_url = get_post_meta($post_id, 'fifu_audio_url', true);
        if ($audio_url) {
            if (!isset($FIFU_SESSION) || (isset($FIFU_SESSION) && !isset($FIFU_SESSION['fifu-audio'][$url]))) {
                $FIFU_SESSION['fifu-audio'][$url] = $audio_url;
            }
        }
    }

    if (fifu_is_on('fifu_popup')) {
        if (!isset($FIFU_SESSION) || (isset($FIFU_SESSION) && !isset($FIFU_SESSION['fifu-popup'][$post_id]))) {
            $html = get_post_meta($post_id, 'fifu_popup_html', true);
            if ($html) {
                $FIFU_SESSION['fifu-popup'][$post_id] = $html;
                wp_enqueue_script('popup-js', plugins_url('/html/js/popup.js', __FILE__), array('jquery'), fifu_version_number_enq());
                $json = wp_json_encode(['html' => $FIFU_SESSION['fifu-popup']]);
                wp_add_inline_script('popup-js', "var fifuPopupVars = {$json};", 'before');
            }
        }
    }

    if (fifu_is_on('fifu_redirection')) {
        $redirection_url = get_post_meta($post_id, 'fifu_redirection_url', true);
        if ($redirection_url) {
            $FIFU_SESSION['fifu-redirection'][$url] = $redirection_url;
            if (fifu_is_on('fifu_otfcdn')) {
                $FIFU_SESSION['fifu-redirection'][fifu_base64($url)] = $redirection_url;
            }
        }
    }

    $post_thumbnail_id = get_post_thumbnail_id($post_id);

    $is_category = false;
    if (!$post_thumbnail_id) {
        $post_thumbnail_id = get_term_meta($post_id, 'thumbnail_id', true);
        if ($post_thumbnail_id)
            $is_category = true;
    }

    $featured = $post_thumbnail_id == $att_id ? 1 : 0;
    $gallery = !$featured && fifu_in_gallery($att_id);

    if (!$featured && !$gallery)
        return $url;

    $parameters = array();
    $parameters['att_id'] = $att_id;
    $parameters['post_id'] = $post_id;
    $parameters['featured'] = $featured;
    $parameters['gallery'] = $gallery;
    $parameters['category'] = $is_category;
    $parameters['local'] = false;

    $type = null;

    // theme size
    if ($size) {
        $size_details = fifu_get_image_size_details($size);
        if (($size_details['width'] ?? false) && ($size_details['height'] ?? false)) {
            $parameters['theme-width'] = $size_details['width'];
            $parameters['theme-height'] = $size_details['height'];
            $parameters['theme-crop'] = $size_details['crop'] ?? false;
        }
    }

    $sliderUrl = get_post_meta($post_id, 'fifu_slider_image_url_0', true);
    $parameters['type'] = (fifu_is_on('fifu_slider') && fifu_show_slider($sliderUrl)) ? 'slider' : $type;

    $FIFU_SESSION[$url] = $parameters;

    if (fifu_is_from_speedup($url)) {
        $FIFU_SESSION['fifu-cloud'][$url] = fifu_speedup_get_set($url);
        wp_enqueue_script('fifu-cloud', plugins_url('/html/js/cloud.js', __FILE__), array('jquery'), fifu_version_number_enq());
        $json = wp_json_encode(['srcsets' => $FIFU_SESSION['fifu-cloud']]);
        wp_add_inline_script('fifu-cloud', "var fifuCloudVars = {$json};", 'before');
    }

    if (class_exists('WooCommerce') && !is_product() && (is_shop() || is_product_category())) {
        if (fifu_is_on('fifu_buy')) {
            if (!isset($FIFU_SESSION['fifu-lightbox'][$post_id])) {
                $data = fifu_api_product_data($post_id);
                $FIFU_SESSION['fifu-lightbox'][$post_id] = $data;
                wp_enqueue_script('fifu-lightbox-js', plugins_url('/html/js/lightbox.js', __FILE__), array('jquery'), fifu_version_number_enq());
                $json = wp_json_encode($data);
                wp_add_inline_script('fifu-lightbox-js', "var fifuLightboxVar{$post_id} = {$json};", 'before');
            }
        }
    }

    return $url;
}

function fifu_get_photon_args($h, $c) {
    $args = array();

    if (fifu_is_on('fifu_otfcdn')) {
        $args['w'] = $h;
        $args['h'] = $h;
        $args['c'] = $c;
        return $args;
    }

    $args['resize'] = $h . ',' . $h;
    return $args;
}

add_filter('facetwp_filtered_post_ids', function ($post_ids, $class) {
    foreach ($post_ids as $post_id) {
        fifu_add_parameters_single_post($post_id);
    }
    return $post_ids;
}, 10, 2);

function fifu_add_parameters_single_post($post_id) {
    $att_id = get_post_thumbnail_id($post_id);
    $url = get_post_meta($att_id, '_wp_attached_file', true);
    if ($url)
        fifu_add_url_parameters($url, $att_id, null);
}

function fifu_inject_json_into_footer() {
    global $FIFU_SESSION;

    if (isset($FIFU_SESSION['fifu-video'])) {
        $arr = json_encode($FIFU_SESSION['fifu-video']);
        echo "<script>var fifuVideoThumbVarsFooter = {$arr};</script>";
    }

    if (isset($FIFU_SESSION['fifu-audio'])) {
        $arr = json_encode($FIFU_SESSION['fifu-audio']);
        echo "<script>var fifuAudioVarsFooter = {$arr};</script>";
    }

    if (isset($FIFU_SESSION['fifu-redirection'])) {
        $arr = json_encode($FIFU_SESSION['fifu-redirection']);
        echo "<script>var fifuRedirectionVarsFooter = {$arr};</script>";
    }

    if (fifu_is_on('fifu_video') || fifu_is_on('fifu_audio')) {
        wp_enqueue_script('fifu-video-thumb-js', plugins_url('/html/js/thumb-video.js', __FILE__), array('jquery'), fifu_version_number_enq());
        wp_localize_script('fifu-video-thumb-js', 'fifuVideoThumbVars', [
            'thumbs' => isset($FIFU_SESSION['fifu-video']) ? $FIFU_SESSION['fifu-video'] : array(),
            'audios' => isset($FIFU_SESSION['fifu-audio']) ? $FIFU_SESSION['fifu-audio'] : array(),
            'cdn' => isset($FIFU_SESSION['cdn-new-old']) ? $FIFU_SESSION['cdn-new-old'] : array(),
            'customvideos' => isset($FIFU_SESSION['fifu-custom-video']) ? $FIFU_SESSION['fifu-custom-video'] : array(),
            'permalinks' => isset($FIFU_SESSION['fifu-permalink']) ? $FIFU_SESSION['fifu-permalink'] : array(),
            'fifu_photon' => fifu_is_on('fifu_photon'),
        ]);
    }
}

add_action('wp_footer', 'fifu_inject_json_into_footer');

// dont load remote image data in the media library when called from block editor

function custom_get_attachment_intercept() {
    $att_id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;

    if ($att_id > 0) {
        if (fifu_is_remote_image($att_id)) {
            $response = array(
                'success' => false,
                'data' => array(),
            );
            wp_send_json($response); // This terminates execution
        }
    }
}

add_action('wp_ajax_get-attachment', 'custom_get_attachment_intercept', 0);

function fifu_filter_og_images($buffer) {
    // Regex to match FIFU blocks
    $pattern_blocks = '/<!--\s*FIFU:meta:begin:[a-z]+\s*-->.*?<!--\s*FIFU:meta:end:[a-z]+\s*-->/is';

    // Extract all FIFU blocks
    $blocks = [];
    if (preg_match_all($pattern_blocks, $buffer, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as $match) {
            $blocks[] = $match[0];
        }
    }

    // Check if there is at least one og:image inside any FIFU block
    $has_fifu_ogimage = false;
    foreach ($blocks as $block) {
        if (preg_match('/<meta\s+[^>]*property=["\']og:image[^"\']*["\']/i', $block)) {
            $has_fifu_ogimage = true;
            break;
        }
    }

    // If no og:image was found inside FIFU blocks, return original buffer
    if (!$has_fifu_ogimage) {
        return $buffer;
    }

    // Otherwise, protect blocks and remove unwanted tags outside them
    $buffer_preserve = $buffer;
    foreach ($blocks as $i => $block) {
        $buffer_preserve = str_replace($block, "___FIFU_BLOCK_" . ($i + 1) . "___", $buffer_preserve);
    }

    // Remove ALL <meta property="og:image..."> tags outside FIFU blocks
    $buffer_preserve = preg_replace('/<meta\s+[^>]*property=["\']og:image[^"\']*["\'][^>]*>\s*/i', '', $buffer_preserve);

    // Remove ALL <meta name="twitter:image..."> tags outside FIFU blocks
    $buffer_preserve = preg_replace('/<meta\s+[^>]*name=["\']twitter:image[^"\']*["\'][^>]*>\s*/i', '', $buffer_preserve);

    // Restore preserved FIFU blocks in their original positions
    foreach ($blocks as $i => $block) {
        $buffer_preserve = str_replace("___FIFU_BLOCK_" . ($i + 1) . "___", $block, $buffer_preserve);
    }

    return $buffer_preserve;
}

