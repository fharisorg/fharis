<?php

define('FIFU_JETPACK_SIZES', serialize(array(75, 100, 150, 240, 320, 500, 640, 800, 1024, 1280, 1600)));

function is_from_jetpack($url) {
    return $url && strpos($url, "wp.fifu.app") !== false;
}

function fifu_resize_jetpack_image_size($size, $url) {
    if (strpos($url, 'wp.fifu.app/') !== false) {
        // Parse the URL to extract its components
        $parts = parse_url($url);
        $path_parts = explode('/', trim($parts['path'] ?? '', '/'));
        $path_count = count($path_parts);

        // Extract query parameters (if any)
        $query = $parts['query'] ?? '';
        parse_str($query, $query_params);

        // Add or update the size parameter in the query
        $query_params['w'] = $size;
        $query_params['h'] = 0;
        $query_params['c'] = 0;

        if ($path_count >= 4) {
            // The second-to-last element is the signature
            $signature_index = $path_count - 2;

            // Remove the signature from the path
            unset($path_parts[$signature_index]);

            // Rebuild the path without the signature
            $new_path = '/' . implode('/', $path_parts);

            // Rebuild the query string
            $new_query = http_build_query($query_params);

            // Create the unsigned URL to calculate a new signature
            $unsigned_url = '//' . ($parts['host'] ?? '') . $new_path . ($new_query ? '?' . $new_query : '');

            // Generate a new signature
            $new_signature = fifu_get_signature($unsigned_url, 'fifu');

            // Insert the new signature into the second-to-last position
            array_splice($path_parts, $signature_index, 0, $new_signature);

            // Rebuild the path with the new signature
            $final_path = '/' . implode('/', $path_parts);

            // Return the complete URL with the new signature
            return ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . $final_path . ($new_query ? '?' . $new_query : '');
        }
    }

    return $url;
}

function fifu_jetpack_get_set($url, $is_slider) {
    $quality = $is_slider ? 1.1 : 1;
    $set = '';
    $count = 0;
    foreach (unserialize(FIFU_JETPACK_SIZES) as $i)
        $set .= (($count++ != 0) ? ', ' : '') . fifu_resize_jetpack_image_size($i * $quality, $url) . ' ' . $i . 'w';
    return $set;
}

function fifu_jetpack_blocked($url) {
    if (!$url)
        return true;

    if (fifu_is_on('fifu_otfcdn'))
        return false;

    if (fifu_is_photon_url($url))
        return true;

    $blocklist = array('localhost', 'amazon-adsystem.com', 'sapo.io', 'image.influenster.com', 'api.screenshotmachine.com', 'img.brownsfashion.com', 'fbcdn.net', 'nitrocdn.com', 'brightspotcdn.com', 'realtysouth.com', 'tiktokcdn.com', 'fdcdn.akamaized.net', 'blockchainstock.azureedge.net', 'aa.com.tr', 'cdn.discordapp.com', 'download.schneider-electric.com', 'cdn.fbsbx.com', 'canva.com', 'cdn.fifu.app', 'cloud.fifu.app', 'images.placeholders.dev');
    foreach ($blocklist as $domain) {
        if (strpos($url, $domain) !== false)
            return true;
    }
    return false;
}

function fifu_is_photon_url($url) {
    $list = array('wp.fifu.app');
    foreach ($list as $domain) {
        if (strpos($url, $domain) !== false)
            return true;
    }
    return false;
}

function fifu_jetpack_crop($url, $w, $h, $p, $q) {
    $w = (float) $w;
    $h = (float) $h;
    $p = (float) $p;
    $q = (float) $q;

    if ($p != $q) {
        if (($p / $q) >= ($w / $h)) {
            $a = $w;
            $b = $w * $q / $p;
            $x = 0;
            $y = ($h - $b) / 2;
        } else {
            $b = $h;
            $a = $h * $p / $q;
            $x = ($w - $a) / 2;
            $y = 0;
        }
    } elseif ($p == $q) {
        if ($w >= $h) {
            $b = $h;
            $a = $h;
            $x = ($w - $a) / 2;
            $y = 0;
        } else {
            $a = $w;
            $b = $w;
            $x = 0;
            $y = ($h - $b) / 2;
        }
    }
    return sprintf('%s&crop=%spx,%spx,%spx,%spx', $url, $x, $y, $a, $b);
}

function fifu_jetpack_photon_url($url, $args, $att_id) {
    if (fifu_jetpack_blocked($url))
        return $url;

    if (fifu_is_on('fifu_otfcdn')) {
        if (fifu_ends_with($url, '.svg'))
            return str_replace('.webp', '.svg', fifu_otf_get_image_url($att_id, $url, ''));

        $otf_url = fifu_otf_get_image_url($att_id, $url, $args ? add_query_arg($args, '?') : '');
        return $otf_url;
    } else {
        if (fifu_ends_with($url, '.svg'))
            return $url;

        return fifu_pubcdn_get_image_url($att_id, $url, $args);
    }
}

function fifu_original_image_url($url) {
    if (!is_from_jetpack($url))
        return $url;
    return fifu_decode_pubcdn_url($url);
}

// Copy the CDN parameters to the error url
function fifu_jetpack_replace_src($photonUrl, $errorUrl, $att_id) {
    if (fifu_is_on('fifu_otfcdn')) {
        if (strpos($photonUrl, '//img.') === false && strpos($photonUrl, '//i0.fifu.app') === false) {
            return $errorUrl;
        }
        $queryParameters = explode('?', $photonUrl)[1] ?? '';
        $qp = $queryParameters ? '?' . $queryParameters : '';
        return fifu_otf_get_image_url(null, $errorUrl, $qp);
    } else {
        // Check if 'wp.fifu.app/' is in the photonUrl
        if (strpos($photonUrl, 'wp.fifu.app/') === false) {
            return $errorUrl;
        }
        $queryParameters = explode('?', $photonUrl)[1] ?? '';
        $queryParameters = explode('&p=', $queryParameters)[0] ?? $queryParameters;
        $qp = $queryParameters ? '?' . $queryParameters : '';
        return fifu_pubcdn_get_image_url($att_id, $errorUrl, $qp);
    }
}

/* https://developer.wordpress.com/docs/site-performance/site-accelerator-cdn/ */

function fifu_pubcdn_get_image_url($att_id, $image_url, $qp) {
    if (fifu_is_cdn_url($image_url))
        return $image_url;

    $image_url = fifu_original_image_url($image_url);

    if ($att_id) {
        $alt = get_post_meta($att_id, '_wp_attachment_image_alt', true);
        $slug = $alt ? $alt : fifu_get_parent_slug($att_id);
        $post = get_post($att_id);
        $post_id = $post && isset($post->post_parent) ? $post->post_parent : null;
    } else {
        $slug = 'not-found';
        $post_id = null;
    }

    if ($post_id) {
        $qp = $qp ? $qp . '&' : '?';
        $qp .= 'p=' . $post_id;
    }

    $decoded_string = urldecode($slug);
    if (function_exists('transliterator_transliterate')) {
        $post_slug = sanitize_title(transliterator_transliterate('Any-Latin; Latin-ASCII', $decoded_string));
    } else {
        // Fallback: Remove non-ASCII characters and sanitize
        $fallback_slug = preg_replace('/[^\x20-\x7E]/u', '', $decoded_string);
        $post_slug = sanitize_title($fallback_slug);
    }

    $post_slug = $post_slug ? $post_slug : 'image';

    $encoded_url = fifu_base64($image_url);
    $new_url = "//wp.fifu.app/" . get_option('fifu_main_domain') . "/" . $encoded_url . "/" . $post_slug . ".webp" . $qp;
    $signature = fifu_get_signature($new_url, 'fifu');
    return 'https:' . str_replace($encoded_url, $encoded_url . '/' . $signature, $new_url);
}

function fifu_decode_pubcdn_url($url) {
    $parts = explode('/', $url);
    if (isset($parts[4])) {
        $base64 = $parts[4];
        $base64 .= str_repeat('=', (4 - strlen($base64) % 4) % 4); // pad if needed
        $decoded = base64_decode(strtr($base64, '-_', '+/'));
        return $decoded ? $decoded : $url;
    }
    return $url;
}

add_filter('jetpack_photon_skip_image', 'fifu_jetpack_photon_skip_image', 10, 3);

function fifu_jetpack_photon_skip_image($skip, $image_url, $args) {
    if (fifu_is_remote_image_url($image_url))
        return true;

    return $skip;
}

