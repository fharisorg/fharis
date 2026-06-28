<?php

function fifu_otf_get_image_url($att_id, $image_url, $qp) {
    $encoded_url = fifu_base64($image_url);

    if ($att_id) {
        $alt = get_post_meta($att_id, '_wp_attachment_image_alt', true);
        $slug = $alt ? $alt : fifu_get_parent_slug($att_id);
        $post = get_post($att_id);
        $post_id = $post ? ($post->post_parent ?? null) : null;
    } else {
        $slug = 'not-found';
        $post_id = null;
    }

    $decoded_string = urldecode($slug);
    if (function_exists('transliterator_transliterate')) {
        $post_slug = sanitize_title(transliterator_transliterate('Any-Latin; Latin-ASCII', $decoded_string));
    } else {
        // Fallback: Remove non-ASCII characters and sanitize
        $fallback_slug = preg_replace('/[^\x20-\x7E]/u', '', $decoded_string);
        $post_slug = sanitize_title($fallback_slug);
    }

    $prefix = fifu_is_on('fifu_own_domain') ? "//img." : "//i0.fifu.app/";
    $base_url = str_replace("//", $prefix, get_home_url()) . '/' . $post_slug . '.webp';

    // Parse existing query parameters if any
    $params = [];
    if (!empty($qp)) {
        $qp = ltrim($qp, '?');
        parse_str($qp, $params);
    }

    // Add additional parameters
    if ($post_id) {
        $params['p'] = $post_id;
    }
    $params['u'] = $encoded_url;

    if (wp_is_mobile()) {
        $square_mobile = get_option('fifu_square_mobile');
        if ($square_mobile) {
            $params['sq'] = 1;
            $params['c'] = $square_mobile == 'crop' ? 1 : 0;
        }
    } else {
        $square_desktop = get_option('fifu_square_desktop');
        if ($square_desktop) {
            $params['sq'] = 1;
            $params['c'] = $square_desktop == 'crop' ? 1 : 0;
        }
    }

    fifu_add_session_params($params, $image_url, $post_id);

    // Build the URL without signature for signature generation
    $url_for_signature = $base_url;
    if (!empty($params)) {
        $url_for_signature .= '?' . http_build_query($params);
    }

    // Generate signature
    $signature = fifu_get_signature($url_for_signature, get_option('fifu_otf_token'));

    // Add signature to URL path
    $signed_url = fifu_add_signature_to_url($base_url, $signature);

    // Append query parameters
    if (!empty($params)) {
        $signed_url .= '?' . http_build_query($params);
    }

    return $signed_url;
}

/**
 * Updated function to resize image with signature in path
 */
function fifu_resize_otf_image_size($size, $url) {
    $size = (int) $size;

    // First remove any existing signature from URL path
    $url = fifu_remove_signature_from_url($url);

    $parsed_url = parse_url($url);
    $clean_url = ($parsed_url['scheme'] ?? 'https') . '://' . ($parsed_url['host'] ?? '') . ($parsed_url['path'] ?? '');

    // Initialize params array
    $params = [];

    // Keep existing params except w, h, c
    if (isset($parsed_url['query'])) {
        parse_str($parsed_url['query'], $existing_params);
        foreach ($existing_params as $key => $value) {
            if (!in_array($key, ['w', 'h', 'c'])) {
                $params[$key] = $value;
            }
        }
    }

    // Add our width parameter
    $params['w'] = $size;
    $params['h'] = 0;
    $params['c'] = 0;

    // Build the URL with parameters for signature generation
    $url_for_signature = $clean_url;
    if (!empty($params)) {
        $url_for_signature .= '?' . http_build_query($params);
    }

    // Generate signature
    $new_signature = fifu_get_signature($url_for_signature, get_option('fifu_otf_token'));

    // Add signature to URL path
    $signed_url = fifu_add_signature_to_url($clean_url, $new_signature);

    // Append query parameters
    if (!empty($params)) {
        $signed_url .= '?' . http_build_query($params);
    }

    return $signed_url;
}

function fifu_get_signature($url, $token) {
    // Generate the HMAC-SHA256 of the URL using the token as the key
    $hash = hash_hmac('sha256', $url, $token, true);

    // Convert the hash to a hexadecimal representation and truncate it to 12 characters
    $signature = substr(bin2hex($hash), 0, 12);

    return $signature;
}

function fifu_otf_get_original_image_dimensions($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_HEADER => true,
        CURLOPT_NOBODY => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    ]);

    $headers = curl_exec($ch);
    curl_close($ch);

    $width = '';
    $height = '';

    foreach (explode("\n", $headers) as $header) {
        if (stripos($header, 'x-original-img-width:') !== false) {
            $width = (int) trim(str_ireplace('x-original-img-width:', '', $header));
        }
        if (stripos($header, 'x-original-img-height:') !== false) {
            $height = (int) trim(str_ireplace('x-original-img-height:', '', $header));
        }
    }

    return [$width, $height];
}

function fifu_otf_get_set($url, $is_slider) {
    $quality = $is_slider ? 1.1 : 1;
    $set = '';
    $count = 0;
    foreach (unserialize(FIFU_JETPACK_SIZES) as $i)
        $set .= (($count++ != 0) ? ', ' : '') . fifu_resize_otf_image_size($i * $quality, $url) . ' ' . $i . 'w';
    return $set;
}

/**
 * Removes signature from the URL path
 * 
 * @param string $url The URL with potential signature in path
 * @return string URL without signature
 */
function fifu_remove_signature_from_url($url) {
    $parsed_url = parse_url($url);
    $path = $parsed_url['path'] ?? '';

    // Split path by '/'
    $path_parts = explode('/', trim($path, '/'));

    // Check if there are at least two parts and the last part contains an extension
    if (count($path_parts) >= 2 && strpos($path_parts[count($path_parts) - 1] ?? '', '.') !== false) {
        // Get the filename (last part)
        $filename = $path_parts[count($path_parts) - 1] ?? '';

        // If there's a signature (penultimate element), remove it
        if (count($path_parts) > 2) {
            // Remove penultimate element
            array_splice($path_parts, count($path_parts) - 2, 1);

            // Rebuild path
            $new_path = '/' . implode('/', $path_parts);

            // Rebuild URL
            $result = ($parsed_url['scheme'] ?? 'https') . '://' . ($parsed_url['host'] ?? '') . $new_path;
            if (isset($parsed_url['query'])) {
                $result .= '?' . $parsed_url['query'];
            }
            return $result;
        }
    }

    // If no signature found or path format not as expected, return original URL
    return $url;
}

/**
 * Adds signature as penultimate element in URL path
 * 
 * @param string $url The URL without signature
 * @param string $signature The signature to add
 * @return string URL with signature
 */
function fifu_add_signature_to_url($url, $signature) {
    $parsed_url = parse_url($url);
    $path = $parsed_url['path'] ?? '';

    // Split path by '/'
    $path_parts = explode('/', trim($path, '/'));

    // Check if path has at least one part and the last part contains an extension
    if (count($path_parts) >= 1 && strpos($path_parts[count($path_parts) - 1] ?? '', '.') !== false) {
        // Get the filename (last part)
        $filename = array_pop($path_parts);

        // Add signature before filename
        $path_parts[] = $signature;
        $path_parts[] = $filename;

        // Rebuild path
        $new_path = '/' . implode('/', $path_parts);

        // Rebuild URL
        $result = ($parsed_url['scheme'] ?? 'https') . '://' . ($parsed_url['host'] ?? '') . $new_path;
        if (isset($parsed_url['query'])) {
            $result .= '?' . $parsed_url['query'];
        }
        return $result;
    }

    // If path format not as expected, return original URL
    return $url;
}

function fifu_add_session_params(&$params, $image_url, $post_id) {
    if (fifu_is_on('fifu_video')) {
        if (fifu_is_video_thumb($image_url)) {
            if (fifu_is_youtube_thumb($image_url) || fifu_is_vimeo_thumb($image_url) || fifu_is_local_thumb($image_url) || fifu_is_wpcom_thumb($image_url) || fifu_is_jwplayer_thumb($image_url) || fifu_is_sprout_thumb($image_url) || fifu_is_odysee_thumb($image_url) || fifu_is_rumble_thumb($image_url) || fifu_is_dailymotion_thumb($image_url) || fifu_is_twitter_thumb($image_url) || fifu_is_tiktok_thumb($image_url) || fifu_is_googledrive_thumb($image_url) || fifu_is_mega_thumb($image_url) || fifu_is_bunny_thumb($image_url) || fifu_is_bitchute_thumb($image_url) || fifu_is_brighteon_thumb($image_url) || fifu_is_soundcloud_thumb($image_url) || fifu_is_spotify_thumb($image_url) || fifu_is_amazon_thumb($image_url)) {
                $video_src = fifu_video_src_by_img($image_url);
                $params['v'] = fifu_base64($video_src);
                return;
            }
        }

        if ($post_id) {
            $custom_video_url = get_post_meta($post_id, 'fifu_custom_video_url', true);
            if ($custom_video_url) {
                $permalink = get_permalink($post_id);
                $params['v'] = fifu_base64($custom_video_url);
                $params['l'] = fifu_base64($permalink);
                return;
            }
        }
    }

    if (fifu_is_on('fifu_audio')) {
        $audio_url = get_post_meta($post_id, 'fifu_audio_url', true);
        if ($audio_url) {
            $params['a'] = fifu_base64($audio_url);
            return;
        }
    }
}

