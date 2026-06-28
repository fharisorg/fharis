<?php

function fifulocal_add_url_parameters($url, $att_id) {
    global $FIFU_SESSION;

    $url = fifulocal_get_main_url($url);

    // avoid duplicated call
    if (isset($FIFU_SESSION[$url]))
        return;

    $post_id = get_the_ID();

    if (!$post_id)
        return;

    if (fifu_is_on('fifu_redirection')) {
        $redirection_url = get_post_meta($post_id, 'fifu_redirection_url', true);
        if ($redirection_url) {
            $FIFU_SESSION['fifu-redirection'][$url] = $redirection_url;
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
        return;

    $parameters = array();
    $parameters['att_id'] = $att_id;
    $parameters['post_id'] = $post_id;
    $parameters['featured'] = $featured;
    $parameters['gallery'] = $gallery;
    $parameters['category'] = $is_category;
    $parameters['local'] = true;

    $FIFU_SESSION[$url] = $parameters;

    if (class_exists('WooCommerce') && !is_product() && (is_shop() || is_product_category())) {
        if (fifu_is_on('fifu_buy')) {
            if (!isset($FIFU_SESSION['fifulocal-lightbox'][$post_id])) {
                $data = fifu_api_product_data($post_id);
                $FIFU_SESSION['fifulocal-lightbox'][$post_id] = $data;
                wp_enqueue_script('fifu-lightbox-js', plugins_url('/html/js/lightbox.js', __FILE__), array('jquery'), fifu_version_number_enq());
                wp_add_inline_script(
                    'fifu-lightbox-js',
                    'var fifuLightboxVar' . $post_id . ' = ' . wp_json_encode($data) . ';',
                    'before'
                );
            }
        }
    }
}

add_action('template_redirect', 'fifulocal_action', 10);

function fifulocal_action() {
    ob_start("fifulocal_callback");
}

function fifulocal_callback($buffer) {
    global $FIFU_SESSION;

    if (empty($buffer))
        return;

    /* plugins: Oxygen, Bricks */
    if (isset($_REQUEST['ct_builder']) || isset($_REQUEST['bricks']) || isset($_REQUEST['fb-edit']))
        return $buffer;

    /* img */

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

        $url = fifulocal_get_main_url($url);

        // get parameters
        if (isset($FIFU_SESSION[$url]))
            $data = $FIFU_SESSION[$url];
        else
            continue;

        if (strpos($imgItem, 'fifulocal-replaced') !== false)
            continue;

        if (!($data['local'] ?? false))
            continue;

        $post_id = $data['post_id'] ?? null;
        $att_id = $data['att_id'] ?? null;
        $featured = $data['featured'] ?? null;
        $gallery = $data['gallery'] ?? null;
        $is_category = $data['category'] ?? false;

        if ($featured) {
            // add featured
            $newImgItem = str_replace('<img ', '<img fifulocal-featured="' . $featured . '" ', $imgItem);

            // add category 
            if ($is_category)
                $newImgItem = str_replace('<img ', '<img fifu-category="1" ', $newImgItem);

            // add post_id
            if (get_post_type($post_id) == 'product')
                $newImgItem = str_replace('<img ', '<img product-id="' . $post_id . '" ', $newImgItem);
            else
                $newImgItem = str_replace('<img ', '<img post-id="' . $post_id . '" ', $newImgItem);

            $buffer = str_replace($imgItem, fifu_replace($newImgItem, $post_id, null, null, null), $buffer);
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

            if (strpos($imgItem, 'fifulocal-replaced') !== false)
                continue;

            if (!($data['local'] ?? false))
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

function fifulocal_get_main_url($url) {
    if (!$url)
        return;

    $aux = explode('.', $url);
    if (!$aux || sizeof($aux) <= 1)
        return;

    $extension = $aux[sizeof($aux) - 1] ?? '';
    if (!$extension)
        return;

    return preg_replace("/-[0-9]+x[0-9]+.[a-z]{1,4}$/", '.' . $extension, $url);
}

