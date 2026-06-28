<?php

/*
 * thumbnail_small 100 x 75
 * thumbnail_medium 200 x 150
 * thumbnail_large 640 x 476
 */

function fifu_vimeo_oembed($url, $size) {
    $queryParams = http_build_query([
        'site' => fifu_get_home_url(),
        'partial_key' => fifu_partial_key(),
        'id' => fifu_vimeo_id($url),
        'size' => $size,
        'url' => $url,
        'domain' => fifu_get_domain()
    ]);
    $workerUrl = "https://oembed-vimeo.fifu.app?" . $queryParams;

    $response = wp_remote_get($workerUrl);
    if (is_wp_error($response))
        return null;

    $status_code = wp_remote_retrieve_response_code($response);

    $image_url = $status_code == 200 ? wp_remote_retrieve_body($response) : null;
    $embed_url = fifu_vimeo_src($url);
    fifu_db_insert_video_oembed($url, $image_url, $embed_url);
}

function fifu_vimeo_img($url, $size) {
    $image_url = fifu_db_get_image_url_by_video_url($url);
    if ($image_url)
        return $image_url;

    fifu_vimeo_oembed($url, $size);
    return fifu_db_get_image_url_by_video_url($url);
}

function fifu_vimeo_id($url) {
    preg_match("/(https?:\/\/)?(www\.)?(player\.)?vimeo\.com\/([a-z]*\/)*([0-9]{6,11}[\/]*[a-z0-9]*)[?]?.*/", $url, $matches);
    return sizeof($matches) > 4 ? ($matches[5] ?? null) : null;
}

function fifu_vimeo_src($url) {
    return 'https://player.vimeo.com/video/' . str_replace('/', '?h=', fifu_vimeo_id($url));
}

function fifu_is_vimeo_video($url) {
    return strpos($url, 'vimeo') !== false;
}

function fifu_vimeo_social_url($id) {
    return 'https://player.vimeo.com/video/' . $id . '?autoplay=1';
}

function fifu_vimeo_social_img($url) {
    return fifu_vimeo_img($url, 'thumbnail_large');
}

function fifu_is_vimeo_thumb($src) {
    return $src && strpos($src, 'i.vimeocdn.com') !== false;
}

/*
 * default 120 x 90
 * mqdefault 320 x 180
 * hqdefault 480 x 360
 * sddefault 640 x 480
 * maxresdefault
 */

function fifu_youtube_oembed($url) {
    $video_id = fifu_youtube_id($url);

    $queryParams = http_build_query([
        'site' => fifu_get_home_url(),
        'partial_key' => fifu_partial_key(),
        'video_id' => $video_id,
    ]);
    $workerUrl = "https://oembed-youtube.fifu.workers.dev?" . $queryParams;

    $response = wp_remote_get($workerUrl);
    if (is_wp_error($response))
        return null;

    $image_url = wp_remote_retrieve_body($response);

    if (strpos($image_url, 'mqdefault') !== false) {
        $maxres_image_url = str_replace('mqdefault', 'maxresdefault', $image_url);
        global $wpdb;
        $wpdb->update(
                $wpdb->postmeta,
                ['meta_value' => $image_url],
                ['meta_key' => '_wp_attached_file', 'meta_value' => $maxres_image_url]
        );
        $wpdb->update(
                $wpdb->posts,
                ['post_content_filtered' => $image_url],
                ['post_author' => FIFU_AUTHOR, 'post_content_filtered' => $maxres_image_url]
        );
    }

    $playlist = null;
    if (strpos($url, 'list=') !== false) {
        $query_params = array();
        $parsed_url = parse_url($url, PHP_URL_QUERY);
        parse_str($parsed_url ?? '', $query_params);
        $playlist = '?list=' . ($query_params['list'] ?? '');
    }

    $embed_url = 'https://www.youtube.com/embed/' . $video_id . $playlist;
    fifu_db_insert_video_oembed($url, $image_url, $embed_url);
}

function fifu_youtube_img($url, $size) {
    if ($size) {
        $video_id = fifu_youtube_id($url);
        return "https://img.youtube.com/vi/{$video_id}/{$size}.jpg";
    }

    $image_url = fifu_db_get_image_url_by_video_url($url);
    if ($image_url)
        return $image_url;

    fifu_youtube_oembed($url);
    return fifu_db_get_image_url_by_video_url($url);
}

function fifu_youtube_id($url) {
    preg_match("/^(?:http(?:s)?:\/\/)?(?:www\.)?(?:m\.)?(?:youtu\.be\/|youtube\.com\/(?:(?:watch)?\?(?:.*&)?v(?:i)?=|(?:embed|v|vi|user|shorts)\/))([^\?&\"'>]+)/", $url, $matches);
    return sizeof($matches) > 0 ? ($matches[1] ?? null) : null;
}

function fifu_youtube_src($url) {
    return 'https://www.youtube.com/embed/' . fifu_video_id($url);
}

function fifu_is_youtube_video($url) {
    return strpos($url, 'youtu') !== false;
}

function fifu_youtube_social_url($id) {
    return 'https://www.youtube.com/v/' . $id . '?version=3&amp;autohide=1';
}

function fifu_youtube_social_img($url) {
    return 'https://i.ytimg.com/vi/' . fifu_youtube_id($url) . '/hqdefault.jpg';
}

function fifu_youtube_parameter($url) {
    if (strpos($url, '?') === false)
        return null;
    $qp = parse_url($url, PHP_URL_QUERY);
    $qp = preg_replace('/v=[^&]+[&]*/', '', $qp);
    return $qp ? '?' . $qp : '';
}

function fifu_is_youtube_thumb($src) {
    return $src && strpos($src, 'img.youtube.com') !== false;
}

/*
 * cloudinary
 */

function fifu_cloudinary_src($url) {
    return $url;
}

function fifu_is_cloudinary_video($url) {
    return strpos($url, 'cloudinary.com') !== false && strpos($url, '/video/') !== false;
}

function fifu_cloudinary_img($url) {
    return str_replace('mp4', 'jpg', $url);
}

function fifu_cloudinary_social_img($url) {
    return fifu_cloudinary_img($url);
}

function fifu_is_cloudinary_thumb($src) {
    return $src && strpos($src, 'res.cloudinary.com') !== false && strpos($src, '/video/') !== false;
}

/*
 * tumblr
 */

function fifu_tumblr_src($url) {
    return $url;
}

function fifu_is_tumblr_video($url) {
    return strpos($url, 'tumblr.com') !== false;
}

function fifu_tumblr_img($url) {
    $tmp = str_replace('https://vt.media.tumblr.com', 'https://78.media.tumblr.com', $url);
    return str_replace('.mp4', '_smart1.jpg', $tmp);
}

function fifu_tumblr_social_img($url) {
    return fifu_tumblr_img($url);
}

function fifu_is_tumblr_thumb($src) {
    return $src && strpos($src, 'tumblr.com') !== false;
}

/*
 * local
 */

function fifu_local_oembed($url) {
    $queryParams = http_build_query([
        'site' => fifu_get_home_url(),
        'partial_key' => fifu_partial_key(),
        'video_url' => $url
    ]);
    $workerUrl = "https://oembed-local.fifu.workers.dev?" . $queryParams;

    $response = wp_remote_get($workerUrl);
    if (is_wp_error($response))
        return null;

    $status_code = wp_remote_retrieve_response_code($response);

    $image_url = $status_code == 200 ? wp_remote_retrieve_body($response) : null;

    if (!$image_url) {
        $image_url = preg_replace('/\.(mp4|mov|webm|m3u8)$/', '-fifu-$1.webp', $url);
    }

    $embed_url = fifu_local_src($url);
    fifu_db_insert_video_oembed($url, $image_url, $embed_url);
}

function fifu_local_src($url) {
    return $url;
}

function fifu_is_local_video($url) {
    $upload_dir = fifu_get_upload_dir();
    return strpos($url, parse_url(get_site_url(), PHP_URL_HOST)) !== false && strpos($url, $upload_dir) !== false && (strpos($url, 'mp4') !== false || strpos($url, 'mov') !== false || strpos($url, 'webm') !== false || strpos($url, 'm3u8') !== false);
}

function fifu_local_img($url) {
    $image_url = fifu_db_get_image_url_by_video_url($url);
    if ($image_url)
        return $image_url;

    fifu_local_oembed($url);
    return fifu_db_get_image_url_by_video_url($url);
}

function fifu_local_social_img($url) {
    return fifu_local_img($url);
}

function fifu_is_local_thumb($src) {
    $upload_dir = fifu_get_upload_dir();
    return $src && strpos($src, parse_url(get_site_url(), PHP_URL_HOST)) !== false && strpos($src, $upload_dir) !== false && strpos($src, '-fifu-') !== false;
}

/*
 * publitio
 */

function fifu_publitio_src($url) {
    return $url;
}

function fifu_is_publitio_video($url) {
    return strpos($url, 'publit.io') !== false;
}

function fifu_publitio_img($url) {
    return str_replace('mp4', 'jpg', $url);
}

function fifu_publitio_social_img($url) {
    return fifu_publitio_img($url);
}

function fifu_is_publitio_thumb($src) {
    return $src && strpos($src, 'publit.io') !== false;
}

/* gag */

function fifu_gag_src($url) {
    return $url;
}

function fifu_is_gag_video($url) {
    return strpos($url, '9cache.com') !== false;
}

function fifu_gag_img($url) {
    return explode('_', $url)[0] ?? $url . '_460c_offset0.jpg';
}

function fifu_gag_social_img($url) {
    return fifu_gag_img($url);
}

function fifu_is_gag_thumb($src) {
    return $src && strpos($src, '9cache.com') !== false;
}

/* wordpress.com */

// example 1: https://videos.files.wordpress.com/knHSQ2fb/pexel-stock-video_dvd.mp4
// example 2: https://videos.files.wordpress.com/Ygmx4akX/red-line-1.mp4
// Not implemented:
// https://videopress.com/v/OcobLTqC

function fifu_wpcom_oembed($url) {
    $queryParams = http_build_query([
        'site' => fifu_get_home_url(),
        'partial_key' => fifu_partial_key(),
        'video_id' => fifu_wpcom_id($url),
        'domain' => fifu_get_domain()
    ]);
    $workerUrl = "https://oembed-wpcom.fifu.workers.dev?" . $queryParams;

    $response = wp_remote_get($workerUrl);
    if (is_wp_error($response))
        return null;

    $status_code = wp_remote_retrieve_response_code($response);

    $image_url = $status_code == 200 ? wp_remote_retrieve_body($response) : null;

    $embed_url = fifu_wpcom_src($url);
    fifu_db_insert_video_oembed($url, $image_url, $embed_url);
}

function fifu_wpcom_id($url) {
    $parts = explode('/', $url);
    return $parts[3] ?? '';
}

function fifu_wpcom_src($url) {
    return $url;
}

function fifu_is_wpcom_video($url) {
    return strpos($url, 'videos.files.wordpress.com') !== false && (strpos($url, '.mp4') !== false || strpos($url, '.mov') !== false);
}

function fifu_wpcom_img($url) {
    $image_url = fifu_db_get_image_url_by_video_url($url);
    if ($image_url)
        return $image_url;

    fifu_wpcom_oembed($url);
    return fifu_db_get_image_url_by_video_url($url);
}

function fifu_wpcom_social_img($url) {
    return fifu_wpcom_img($url);
}

function fifu_is_wpcom_thumb($src) {
    return $src && strpos($src, 'videos.files.wordpress.com') !== false && strpos($src, '.jpg') !== false;
}

/* tiktok */

// Example of video URL: https://www.tiktok.com/@scout2015/video/6718335390845095173

function fifu_tiktok_oembed($url) {
    $queryParams = http_build_query([
        'site' => fifu_get_home_url(),
        'partial_key' => fifu_partial_key(),
        'url' => $url,
        'domain' => fifu_get_domain()
    ]);
    $workerUrl = "https://oembed-tiktok.fifu.workers.dev?" . $queryParams;

    $response = wp_remote_get($workerUrl);
    if (is_wp_error($response))
        return null;

    $status_code = wp_remote_retrieve_response_code($response);

    $image_url = $status_code == 200 ? wp_remote_retrieve_body($response) : null;

    $embed_url = fifu_tiktok_src($url);
    fifu_db_insert_video_oembed($url, $image_url, $embed_url);
}

function fifu_tiktok_id($url) {
    $parts = explode('/', $url);
    if (isset($parts[5])) {
        return explode('?', $parts[5])[0] ?? '';
    }
    return '';
}

function fifu_tiktok_src($url) {
    return 'https://www.tiktok.com/embed/v2/' . fifu_tiktok_id($url);
}

function fifu_is_tiktok_video($url) {
    return strpos($url, 'tiktok.com') !== false;
}

function fifu_tiktok_img($url) {
    $image_url = fifu_db_get_image_url_by_video_url($url);
    if ($image_url)
        return $image_url;

    fifu_tiktok_oembed($url);
    return fifu_db_get_image_url_by_video_url($url);
}

function fifu_tiktok_social_img($url) {
    return fifu_tiktok_img($url);
}

function fifu_is_tiktok_thumb($src) {
    return $src && strpos($src, 'tiktokcdn.com') !== false;
}

/* googledrive */

function fifu_googledrive_oembed($url) {
    $queryParams = http_build_query([
        'site' => fifu_get_home_url(),
        'partial_key' => fifu_partial_key(),
        'video_id' => fifu_googledrive_id($url),
        'domain' => fifu_get_domain()
    ]);
    $workerUrl = "https://oembed-googledrive.fifu.workers.dev?" . $queryParams;

    $response = wp_remote_get($workerUrl);
    if (is_wp_error($response))
        return null;

    $status_code = wp_remote_retrieve_response_code($response);

    $image_url = $status_code == 200 ? wp_remote_retrieve_body($response) : null;

    if ($image_url) {
        $image_url = fifu_upload_video_thumbnail($url, $image_url);
        $embed_url = fifu_googledrive_src($url);
        fifu_db_insert_video_oembed($url, $image_url, $embed_url);
    }
}

function fifu_googledrive_id($url) {
    $parts = explode('/', $url);
    if (isset($parts[5])) {
        return explode('/', $parts[5])[0] ?? '';
    }
    return '';
}

function fifu_googledrive_src($url) {
    return 'https://drive.google.com/file/d/' . fifu_googledrive_id($url) . '/preview';
}

function fifu_is_googledrive_video($url) {
    return strpos($url, 'drive.google.com/file') !== false;
}

function fifu_googledrive_img($url) {
    $image_url = fifu_db_get_image_url_by_video_url($url);
    if ($image_url)
        return $image_url;

    fifu_googledrive_oembed($url);
    return fifu_db_get_image_url_by_video_url($url);
}

function fifu_googledrive_social_img($url) {
    return fifu_googledrive_img($url);
}

function fifu_is_googledrive_thumb($src) {
    return $src && strpos($src, '/fifu/videothumb/googledrive/') !== false;
}

/* mega */

function fifu_mega_oembed($url) {
    $video_id = fifu_mega_id($url);
    $api_key = get_option('fifu_ws_key_mega');
    if (!$api_key)
        return null;
    $data = array('source' => 'mega', 'id' => $video_id, 'key' => $api_key);
    $curl = curl_init("https://mega.fifu.workers.dev/");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($curl, CURLOPT_REFERER, fifu_get_domain());
    $resp = curl_exec($curl);

    $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    if ($http_status == 200) {
        $resp = json_decode($resp, true);
        if (isset($resp['create'])) {
            $image_url = null;
            if ($resp['create']) {
                curl_setopt($curl, CURLOPT_URL, $resp['url'] ?? '');
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
                $resp = curl_exec($curl);
                $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                if ($http_status == 200)
                    $image_url = $resp;
            } else {
                $image_url = $resp['url'] ?? null;
            }
            if ($image_url) {
                $image_url = fifu_upload_video_thumbnail($url, $image_url);
                $embed_url = fifu_mega_src($url);
                fifu_db_insert_video_oembed($url, $image_url, $embed_url);
            }
        }
    }
    curl_close($curl);
}

function fifu_mega_id($url) {
    $parts = explode('/', $url);
    if (isset($parts[4])) {
        return explode('!', $parts[4])[0] ?? '';
    }
    return '';
}

function fifu_mega_src($url) {
    return 'https://mega.nz/embed/' . fifu_mega_id($url);
}

function fifu_is_mega_video($url) {
    return strpos($url, 'mega.nz') !== false;
}

function fifu_mega_img($url) {
    $image_url = fifu_db_get_image_url_by_video_url($url);
    if ($image_url)
        return $image_url;

    fifu_mega_oembed($url);
    return fifu_db_get_image_url_by_video_url($url);
}

function fifu_mega_social_img($url) {
    return fifu_mega_img($url);
}

function fifu_is_mega_thumb($src) {
    return $src && strpos($src, '/fifu/videothumb/mega/') !== false;
}

/* bunny */

// Example of video URL: https://video.bunnycdn.com/play/100390/7f8512f6-1b44-4290-913e-86f2d74ac878
// Example of video URL: https://video.bunnycdn.com/play/51808/3f2bd49c-0496-4fc4-ad42-026f09d474ea

function fifu_bunny_oembed($url) {
    $queryParams = http_build_query([
        'site' => fifu_get_home_url(),
        'partial_key' => fifu_partial_key(),
        'video_id' => fifu_bunny_id($url),
        'domain' => fifu_get_domain()
    ]);
    $workerUrl = "https://oembed-bunny.fifu.workers.dev?" . $queryParams;

    $response = wp_remote_get($workerUrl);
    if (is_wp_error($response))
        return null;

    $status_code = wp_remote_retrieve_response_code($response);

    $image_url = $status_code == 200 ? wp_remote_retrieve_body($response) : null;

    $embed_url = fifu_bunny_src($url);
    fifu_db_insert_video_oembed($url, $image_url, $embed_url);
}

function fifu_bunny_id($url) {
    $parts = explode('/play/', $url);
    return $parts[1] ?? '';
}

function fifu_bunny_src($url) {
    return 'https://video.bunnycdn.com/embed/' . fifu_bunny_id($url);
}

function fifu_is_bunny_video($url) {
    return strpos($url, 'video.bunnycdn.com') !== false;
}

function fifu_bunny_img($url) {
    $image_url = fifu_db_get_image_url_by_video_url($url);
    if ($image_url)
        return $image_url;

    fifu_bunny_oembed($url);
    return fifu_db_get_image_url_by_video_url($url);
}

function fifu_bunny_social_img($url) {
    return fifu_bunny_img($url);
}

function fifu_is_bunny_thumb($src) {
    return $src && (strpos($src, 'b-cdn.net') !== false && strpos($src, 'thumbnail') !== false);
}

/* bitchute */

function fifu_bitchute_oembed($url) {
    $queryParams = http_build_query([
        'site' => fifu_get_home_url(),
        'partial_key' => fifu_partial_key(),
        'url' => $url,
        'domain' => fifu_get_domain()
    ]);
    $workerUrl = "https://oembed-bitchute.fifu.workers.dev?" . $queryParams;

    $response = wp_remote_get($workerUrl);
    if (is_wp_error($response))
        return null;

    $status_code = wp_remote_retrieve_response_code($response);

    $image_url = $status_code == 200 ? wp_remote_retrieve_body($response) : null;

    $embed_url = fifu_bitchute_src($url);
    fifu_db_insert_video_oembed($url, $image_url, $embed_url);
}

function fifu_bitchute_id($url) {
    $parts = explode('/video/', $url);
    return $parts[1] ?? '';
}

function fifu_bitchute_src($url) {
    return 'https://www.bitchute.com/embed/' . fifu_bitchute_id($url);
}

function fifu_is_bitchute_video($url) {
    return strpos($url, 'www.bitchute.com') !== false;
}

function fifu_bitchute_img($url) {
    $image_url = fifu_db_get_image_url_by_video_url($url);
    if ($image_url)
        return $image_url;

    fifu_bitchute_oembed($url);
    return fifu_db_get_image_url_by_video_url($url);
}

function fifu_bitchute_social_img($url) {
    return fifu_bitchute_img($url);
}

function fifu_is_bitchute_thumb($src) {
    return $src && strpos($src, 'bitchute.com/live') !== false;
}

/* brighteon */

function fifu_brighteon_oembed($url) {
    $queryParams = http_build_query([
        'site' => fifu_get_home_url(),
        'partial_key' => fifu_partial_key(),
        'url' => $url,
        'domain' => fifu_get_domain()
    ]);
    $workerUrl = "https://oembed-brighteon.fifu.workers.dev?" . $queryParams;

    $response = wp_remote_get($workerUrl);
    if (is_wp_error($response))
        return null;

    $status_code = wp_remote_retrieve_response_code($response);

    $image_url = $status_code == 200 ? wp_remote_retrieve_body($response) : null;

    $embed_url = fifu_brighteon_src($url);
    fifu_db_insert_video_oembed($url, $image_url, $embed_url);
}

function fifu_brighteon_id($url) {
    $parts = explode('/', $url);
    return $parts[3] ?? '';
}

function fifu_brighteon_src($url) {
    return 'https://www.brighteon.com/embed/' . fifu_brighteon_id($url);
}

function fifu_is_brighteon_video($url) {
    return strpos($url, 'www.brighteon.com') !== false;
}

function fifu_brighteon_img($url) {
    $image_url = fifu_db_get_image_url_by_video_url($url);
    if ($image_url)
        return $image_url;

    fifu_brighteon_oembed($url);
    return fifu_db_get_image_url_by_video_url($url);
}

function fifu_brighteon_social_img($url) {
    return fifu_brighteon_img($url);
}

function fifu_is_brighteon_thumb($src) {
    return $src && (strpos($src, 'photos.brighteon.com') !== false || strpos($src, 'video.brighteon.com') !== false);
}

/* spotify */

// Example: https://open.spotify.com/track/003vvx7Niy0yvhvHt4a68B

function fifu_spotify_oembed($url) {
    $queryParams = http_build_query([
        'site' => fifu_get_home_url(),
        'partial_key' => fifu_partial_key(),
        'url' => $url,
    ]);
    $workerUrl = "https://oembed-spotify.fifu.workers.dev?" . $queryParams;

    $response = wp_remote_get($workerUrl);
    if (is_wp_error($response))
        return null;

    $status_code = wp_remote_retrieve_response_code($response);

    $image_url = $status_code == 200 ? wp_remote_retrieve_body($response) : null;

    $embed_url = fifu_spotify_src($url);
    fifu_db_insert_video_oembed($url, $image_url, $embed_url);
}

function fifu_spotify_id($url) {
    $parts = explode('track/', explode('?', $url)[0] ?? '');
    return $parts[1] ?? '';
}

function fifu_spotify_src($url) {
    return 'https://open.spotify.com/embed/track/' . fifu_spotify_id($url) . '?utm_source=generator';
}

function fifu_is_spotify_video($url) {
    return strpos($url, 'spotify.com') !== false;
}

function fifu_spotify_img($url) {
    $image_url = fifu_db_get_image_url_by_video_url($url);
    if ($image_url)
        return $image_url;

    fifu_spotify_oembed($url);
    return fifu_db_get_image_url_by_video_url($url);
}

function fifu_spotify_social_img($url) {
    return fifu_spotify_img($url);
}

function fifu_is_spotify_thumb($src) {
    return $src && strpos($src, 'i.scdn.co') !== false;
}

/* amazon */

// Example of video URL: https://m.media-amazon.com/images/S/vse-vms-transcoding-artifact-us-east-1-prod/d32c2c8e-680a-4d96-9b41-811bd678c624/default.jobtemplate.mp4.480.mp4?thumb-id=615reCVL-NL.SX1600_.jpg

function fifu_amazon_oembed($url) {
    $queryParams = http_build_query([
        'site' => fifu_get_home_url(),
        'partial_key' => fifu_partial_key(),
        'url' => $url,
    ]);
    $workerUrl = "https://oembed-amazon.fifu.workers.dev?" . $queryParams;

    $response = wp_remote_get($workerUrl);
    if (is_wp_error($response))
        return null;

    $status_code = wp_remote_retrieve_response_code($response);

    $image_url = $status_code == 200 ? wp_remote_retrieve_body($response) : null;

    fifu_db_insert_video_oembed($url, $image_url, $url);
}

function fifu_amazon_src($url) {
    return $url;
}

function fifu_is_amazon_video($url) {
    return strpos($url, 'm.media-amazon.com') !== false && strpos($url, '.mp4') !== false;
}

function fifu_amazon_img($url) {
    $image_url = fifu_db_get_image_url_by_video_url($url);
    if ($image_url)
        return $image_url;

    fifu_amazon_oembed($url);
    return fifu_db_get_image_url_by_video_url($url);
}

function fifu_amazon_social_img($url) {
    return fifu_amazon_img($url);
}

function fifu_is_amazon_thumb($src) {
    return $src && strpos($src, 'm.media-amazon.com') !== false && strpos($src, 'SX1600_.') !== false;
}

/* jwplayer */

// Example of video URL: https://cdn.jwplayer.com/players/bQ5HB8IU-oB0asnIq.html

function fifu_jwplayer_oembed($url) {
    $video_id = fifu_video_id($url);

    $queryParams = http_build_query([
        'site' => fifu_get_home_url(),
        'partial_key' => fifu_partial_key(),
        'video_id' => $video_id,
    ]);
    $workerUrl = "https://oembed-jwplayer.fifu.workers.dev?" . $queryParams;

    $response = wp_remote_get($workerUrl);
    if (is_wp_error($response))
        return null;

    $status_code = wp_remote_retrieve_response_code($response);

    $image_url = $status_code == 200 ? wp_remote_retrieve_body($response) : null;

    $embed_url = "https://content.jwplatform.com/players/{$video_id}.html";
    fifu_db_insert_video_oembed($url, $image_url, $embed_url);
}

function fifu_jwplayer_id($url) {
    return explode('.', explode('/', $url)[4] ?? '')[0] ?? '';
}

function fifu_jwplayer_player_id($url) {
    return explode('-', fifu_jwplayer_id($url))[1] ?? '';
}

function fifu_jwplayer_src($url) {
    $video_id = fifu_video_id($url);
    $player_id = fifu_jwplayer_player_id($url);
    return "https://content.jwplatform.com/players/{$video_id}.html";
}

function fifu_is_jwplayer_video($url) {
    return strpos($url, 'jwplayer.com') !== false;
}

function fifu_jwplayer_img($url, $size) {
    $image_url = fifu_db_get_image_url_by_video_url($url);
    if ($image_url)
        return $image_url;

    fifu_jwplayer_oembed($url, $size);
    return fifu_db_get_image_url_by_video_url($url);
}

function fifu_jwplayer_social_img($url) {
    return fifu_jwplayer_img($url, 1280);
}

function fifu_is_jwplayer_thumb($src) {
    return $src && strpos($src, 'jwplatform.com') !== false;
}

function fifu_jwplayer_social_url($id) {
    return 'https://cdn.jwplayer.com/players/' . $id . '.html';
}

/* sprout (crazy patterns) */

// Example of video URL: https://sproutvideodemo.vids.io/videos/709adab51013eccaf8/longboarding

function fifu_sprout_oembed($url) {
    $queryParams = http_build_query([
        'site' => fifu_get_home_url(),
        'partial_key' => fifu_partial_key(),
        'url' => $url,
        'domain' => fifu_get_domain()
    ]);
    $workerUrl = "https://oembed-sprout.fifu.workers.dev?" . $queryParams;

    $response = wp_remote_get($workerUrl);
    if (is_wp_error($response))
        return null;

    $status_code = wp_remote_retrieve_response_code($response);

    $embed_url = $status_code == 200 ? wp_remote_retrieve_body($response) : null;

    $image_url = fifu_sprout_find_image_url($embed_url);
    fifu_db_insert_video_oembed($url, $image_url, $embed_url);
}

function fifu_is_sprout_video($url) {
    return strpos($url, 'vids.io') !== false;
}

function fifu_sprout_find_image_url($src) {
    $aux = explode('/', $src);
    $video_id = $aux[4] ?? '';
    $security_token = $aux[5] ?? '';
    return 'https://cdn-thumbnails.sproutvideo.com/' . $video_id . '/' . $security_token . '/1/';
}

function fifu_sprout_src($url) {
    $embed_url = fifu_db_get_embed_url_by_video_url($url);
    if ($embed_url)
        return $embed_url;

    fifu_sprout_oembed($url);
    return fifu_db_get_embed_url_by_video_url($url);
}

function fifu_sprout_img($url) {
    $src = fifu_sprout_src($url);
    return fifu_sprout_find_image_url($src);
}

function fifu_sprout_social_img($url) {
    return fifu_sprout_img($url);
}

function fifu_is_sprout_thumb($src) {
    return $src && strpos($src, 'cdn-thumbnails.sproutvideo.com') !== false;
}

/* rumble */

function fifu_rumble_oembed($url) {
    $queryParams = http_build_query([
        'site' => fifu_get_home_url(),
        'partial_key' => fifu_partial_key(),
        'url' => $url,
        'domain' => fifu_get_domain()
    ]);
    $workerUrl = "https://oembed-rumble.fifu.workers.dev?" . $queryParams;

    $response = wp_remote_get($workerUrl);
    if (is_wp_error($response))
        return null;

    if (wp_remote_retrieve_response_code($response) != 200)
        return null;

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!isset($data))
        return null;

    $image_url = $data['image_url'] ?? null;
    $embed_url = $data['embed_url'] ?? null;

    fifu_db_insert_video_oembed($url, $image_url, $embed_url);
}

function fifu_is_rumble_video($url) {
    return strpos($url, 'rumble.com') !== false;
}

function fifu_rumble_src($url) {
    $embed_url = fifu_db_get_embed_url_by_video_url($url);
    if ($embed_url)
        return $embed_url;

    fifu_rumble_oembed($url);
    return fifu_db_get_embed_url_by_video_url($url);
}

function fifu_rumble_img($url) {
    $image_url = fifu_db_get_image_url_by_video_url($url);
    if ($image_url)
        return $image_url;

    fifu_rumble_oembed($url);
    return fifu_db_get_image_url_by_video_url($url);
}

function fifu_rumble_social_img($url) {
    return fifu_rumble_img($url);
}

function fifu_is_rumble_thumb($src) {
    return $src && (strpos($src, 'rmbl.ws') !== false || strpos($src, 'rumble.cloud') !== false || strpos($src, '1a-1791.com') !== false);
}

/* soundcloud */

function fifu_soundcloud_oembed($url) {
    $queryParams = http_build_query([
        'site' => fifu_get_home_url(),
        'partial_key' => fifu_partial_key(),
        'url' => $url,
        'domain' => fifu_get_domain()
    ]);
    $workerUrl = "https://oembed-soundcloud.fifu.workers.dev?" . $queryParams;

    $response = wp_remote_get($workerUrl);
    if (is_wp_error($response))
        return null;

    if (wp_remote_retrieve_response_code($response) != 200)
        return null;

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!isset($data))
        return null;

    $image_url = $data['image_url'] ?? null;
    $embed_url = $data['embed_url'] ?? null;

    fifu_db_insert_video_oembed($url, $image_url, $embed_url);
}

function fifu_is_soundcloud_video($url) {
    return strpos($url, 'soundcloud.com') !== false;
}

function fifu_soundcloud_src($url) {
    $embed_url = fifu_db_get_embed_url_by_video_url($url);
    if ($embed_url)
        return $embed_url;

    fifu_soundcloud_oembed($url);
    return fifu_db_get_embed_url_by_video_url($url);
}

function fifu_soundcloud_img($url) {
    $image_url = fifu_db_get_image_url_by_video_url($url);
    if ($image_url)
        return $image_url;

    fifu_soundcloud_oembed($url);
    return fifu_db_get_image_url_by_video_url($url);
}

function fifu_soundcloud_social_img($url) {
    return fifu_soundcloud_img($url);
}

function fifu_is_soundcloud_thumb($src) {
    return $src && strpos($src, 'sndcdn.com') !== false;
}

/* dailymotion */

function fifu_dailymotion_oembed($url) {
    $queryParams = http_build_query([
        'site' => fifu_get_home_url(),
        'partial_key' => fifu_partial_key(),
        'url' => $url,
        'domain' => fifu_get_domain()
    ]);
    $workerUrl = "https://oembed-dailymotion.fifu.workers.dev?" . $queryParams;

    $response = wp_remote_get($workerUrl);
    if (is_wp_error($response))
        return null;

    $status_code = wp_remote_retrieve_response_code($response);

    $image_url = $status_code == 200 ? wp_remote_retrieve_body($response) : null;

    $embed_url = fifu_dailymotion_src($url);
    fifu_db_insert_video_oembed($url, $image_url, $embed_url);
}

function fifu_dailymotion_id($url) {
    $parts = explode('/', $url);
    if (isset($parts[4])) {
        return explode('?', $parts[4])[0] ?? '';
    }
    return '';
}

function fifu_is_dailymotion_video($url) {
    return strpos($url, 'dailymotion.com') !== false;
}

function fifu_dailymotion_src($url) {
    return str_replace('/video/', '/embed/video/', $url);
}

function fifu_dailymotion_img($url) {
    $image_url = fifu_db_get_image_url_by_video_url($url);
    if ($image_url)
        return $image_url;

    fifu_dailymotion_oembed($url);
    return fifu_db_get_image_url_by_video_url($url);
}

function fifu_dailymotion_social_img($url) {
    return fifu_dailymotion_img($url);
}

function fifu_is_dailymotion_thumb($src) {
    return $src && strpos($src, 'dmcdn.net') !== false;
}

/* twitter */

function fifu_twitter_oembed($url) {
    $queryParams = http_build_query([
        'site' => fifu_get_home_url(),
        'partial_key' => fifu_partial_key(),
        'id' => fifu_twitter_id($url),
        'domain' => fifu_get_domain()
    ]);
    $workerUrl = "https://oembed-twitter.fifu.workers.dev?" . $queryParams;

    $response = wp_remote_get($workerUrl);
    if (is_wp_error($response))
        return null;

    if (wp_remote_retrieve_response_code($response) != 200)
        return null;

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!isset($data))
        return null;

    $image_url = $data['image_url'] ?? null;
    $embed_url = $data['embed_url'] ?? null;

    fifu_db_insert_video_oembed($url, $image_url, $embed_url);
}

function fifu_twitter_id($url) {
    $parts = explode('/', $url);
    return $parts[5] ?? '';
}

function fifu_is_twitter_video($url) {
    return strpos($url, 'twitter.com') !== false;
}

function fifu_twitter_src($url) {
    $embed_url = fifu_db_get_embed_url_by_video_url($url);
    if ($embed_url)
        return $embed_url;

    fifu_twitter_oembed($url);
    return fifu_db_get_embed_url_by_video_url($url);
}

function fifu_twitter_img($url) {
    $image_url = fifu_db_get_image_url_by_video_url($url);
    if ($image_url)
        return $image_url;

    fifu_twitter_oembed($url);
    return fifu_db_get_image_url_by_video_url($url);
}

function fifu_twitter_social_img($url) {
    return fifu_twitter_img($url);
}

function fifu_is_twitter_thumb($src) {
    return $src && strpos($src, 'pbs.twimg.com') !== false;
}

/* cloudflarestream */

function fifu_cloudflarestream_src($url) {
    return preg_replace('/manifest\/video.*/', 'iframe', $url);
}

function fifu_is_cloudflarestream_video($url) {
    return strpos($url, 'cloudflarestream.com') !== false;
}

function fifu_cloudflarestream_img($url) {
    return preg_replace('/manifest\/video.*/', 'thumbnails/thumbnail.jpg', $url);
}

function fifu_cloudflarestream_social_img($url) {
    return fifu_cloudflarestream_img($url);
}

function fifu_is_cloudflarestream_thumb($src) {
    return $src && strpos($src, 'cloudflarestream.com') !== false && strpos($src, '/thumbnails/') !== false;
}

/* odysee */

function fifu_odysee_oembed($url) {
    $queryParams = http_build_query([
        'site' => fifu_get_home_url(),
        'partial_key' => fifu_partial_key(),
        'url' => $url,
        'domain' => fifu_get_domain()
    ]);
    $workerUrl = "https://oembed-odysee.fifu.workers.dev?" . $queryParams;

    $response = wp_remote_get($workerUrl);
    if (is_wp_error($response))
        return null;

    $status_code = wp_remote_retrieve_response_code($response);

    $image_url = $status_code == 200 ? wp_remote_retrieve_body($response) : null;

    $embed_url = fifu_odysee_src($url);
    fifu_db_insert_video_oembed($url, $image_url, $embed_url);
}

function fifu_odysee_id($url) {
    $arr = explode('/', $url);
    return ($arr[3] ?? '') . '/' . ($arr[4] ?? '');
}

function fifu_is_odysee_video($url) {
    return strpos($url, 'odysee.com') !== false;
}

function fifu_odysee_src($url) {
    return 'https://odysee.com/$/embed/' . fifu_video_id($url);
}

function fifu_odysee_img($url) {
    $image_url = fifu_db_get_image_url_by_video_url($url);
    if ($image_url)
        return $image_url;

    fifu_odysee_oembed($url);
    return fifu_db_get_image_url_by_video_url($url);
}

function fifu_odysee_social_img($url) {
    return fifu_odysee_img($url);
}

function fifu_is_odysee_thumb($src) {
    return $src && strpos($src, 'thumbnails.odycdn.com') !== false;
}

/* suvideo */

function fifu_suvideo_id($url) {
    $video_url = fifu_suvideo_video_url_only($url);
    return fifu_video_id($video_url);
}

function fifu_suvideo_src($url) {
    $video_url = fifu_suvideo_video_url_only($url);
    return fifu_video_src($video_url);
}

function fifu_is_suvideo_video($url) {
    return strpos($url, 'fifu-thumb=') !== false;
}

function fifu_suvideo_img($url) {
    $video_url = fifu_suvideo_video_url_only($url);
    $image_url = fifu_video_img_large($video_url, null, null);
    return fifu_suvideo_thumb_url_only($url) . '?video-thumb=' . fifu_remove_query_strings($image_url);
}

function fifu_suvideo_social_img($url) {
    return fifu_suvideo_img($url);
}

function fifu_is_suvideo_thumb($src) {
    return $src && strpos($src, 'cdn.fifu.app') !== false && strpos($src, 'video-thumb') !== false;
}

function fifu_suvideo_social_url($id) {
    return fifu_video_social_url($id);
}

function fifu_suvideo_video_url_only($url) {
    return preg_replace("/.fifu-thumb=.*/", "", $url);
}

function fifu_suvideo_thumb_url_only($url) {
    return preg_replace("/.*fifu-thumb=/", "", $url);
}

function fifu_suvideo_2nd_thumb_url_only($url) {
    return preg_replace("/.*video-thumb=/", "", $url);
}

/*
 * custom
 */

function fifu_custom_src($url) {
    return $url;
}

/*
 * size
 */

function fifu_is_video($url) {
    return fifu_is_automatic_video($url) || fifu_is_custom_video($url);
}

function fifu_is_automatic_video($url) {
    return fifu_is_youtube_video($url) || fifu_is_vimeo_video($url) || fifu_is_cloudinary_video($url) || fifu_is_tumblr_video($url) || fifu_is_local_video($url) || fifu_is_publitio_video($url) || fifu_is_gag_video($url) || fifu_is_wpcom_video($url) || fifu_is_tiktok_video($url) || fifu_is_googledrive_video($url) || fifu_is_mega_video($url) || fifu_is_bunny_video($url) || fifu_is_bitchute_video($url) || fifu_is_brighteon_video($url) || fifu_is_spotify_video($url) || fifu_is_amazon_video($url) || fifu_is_jwplayer_video($url) || fifu_is_sprout_video($url) || fifu_is_rumble_video($url) || fifu_is_soundcloud_video($url) || fifu_is_dailymotion_video($url) || fifu_is_twitter_video($url) || fifu_is_cloudflarestream_video($url) || fifu_is_odysee_video($url);
}

function fifu_is_custom_video($url) {
    $parts = explode("\\http", $url);
    $url = $parts[0] ?? $url;

    if (fifu_is_automatic_video($url))
        return false;
    return strpos($url, '.mp4') !== false || strpos($url, '.mov') !== false || strpos($url, '.webm') !== false || strpos($url, '.m3u8') !== false;
}

function fifu_video_id($url) {
    if (fifu_is_youtube_video($url))
        return fifu_youtube_id($url);
    if (fifu_is_vimeo_video($url))
        return fifu_vimeo_id($url);
    if (fifu_is_tiktok_video($url))
        return fifu_tiktok_id($url);
    if (fifu_is_googledrive_video($url))
        return fifu_googledrive_id($url);
    if (fifu_is_mega_video($url))
        return fifu_mega_id($url);
    if (fifu_is_bunny_video($url))
        return fifu_bunny_id($url);
    if (fifu_is_bitchute_video($url))
        return fifu_bitchute_id($url);
    if (fifu_is_brighteon_video($url))
        return fifu_brighteon_id($url);
    if (fifu_is_spotify_video($url))
        return fifu_spotify_id($url);
    if (fifu_is_jwplayer_video($url))
        return fifu_jwplayer_id($url);
    if (fifu_is_odysee_video($url))
        return fifu_odysee_id($url);
    return null;
}

function fifu_video_img_small($url) {
    if (fifu_is_suvideo_video($url))
        return fifu_suvideo_img($url);
    if (fifu_is_youtube_video($url))
        return fifu_youtube_img($url, 'default');
    if (fifu_is_vimeo_video($url))
        return fifu_vimeo_img($url, 'thumbnail_small');
    if (fifu_is_cloudinary_video($url))
        return fifu_cloudinary_img($url);
    if (fifu_is_tumblr_video($url))
        return fifu_tumblr_img($url);
    if (fifu_is_local_video($url))
        return fifu_local_img($url);
    if (fifu_is_publitio_video($url))
        return fifu_publitio_img($url);
    if (fifu_is_gag_video($url))
        return fifu_gag_img($url);
    if (fifu_is_wpcom_video($url))
        return fifu_wpcom_img($url);
    if (fifu_is_tiktok_video($url))
        return fifu_tiktok_img($url);
    if (fifu_is_googledrive_video($url))
        return fifu_googledrive_img($url);
    if (fifu_is_mega_video($url))
        return fifu_mega_img($url);
    if (fifu_is_bunny_video($url))
        return fifu_bunny_img($url);
    if (fifu_is_bitchute_video($url))
        return fifu_bitchute_img($url);
    if (fifu_is_brighteon_video($url))
        return fifu_brighteon_img($url);
    if (fifu_is_spotify_video($url))
        return fifu_spotify_img($url);
    if (fifu_is_amazon_video($url))
        return fifu_amazon_img($url);
    if (fifu_is_jwplayer_video($url))
        return fifu_jwplayer_img($url, 320);
    if (fifu_is_sprout_video($url))
        return fifu_sprout_img($url);
    if (fifu_is_rumble_video($url))
        return fifu_rumble_img($url);
    if (fifu_is_soundcloud_video($url))
        return fifu_soundcloud_img($url);
    if (fifu_is_dailymotion_video($url))
        return fifu_dailymotion_img($url);
    if (fifu_is_twitter_video($url))
        return fifu_twitter_img($url);
    if (fifu_is_cloudflarestream_video($url))
        return fifu_cloudflarestream_img($url);
    if (fifu_is_odysee_video($url))
        return fifu_odysee_img($url);
    return null;
}

function fifu_video_img_large($url, $post_id, $is_category) {
    if (fifu_is_suvideo_video($url))
        return fifu_suvideo_img($url);
    if (fifu_is_youtube_video($url))
        return fifu_youtube_img($url, null);
    if (fifu_is_vimeo_video($url))
        return fifu_vimeo_img($url, 'thumbnail_large');
    if (fifu_is_cloudinary_video($url))
        return fifu_cloudinary_img($url);
    if (fifu_is_tumblr_video($url))
        return fifu_tumblr_img($url);
    if (fifu_is_local_video($url))
        return fifu_local_img($url);
    if (fifu_is_publitio_video($url))
        return fifu_publitio_img($url);
    if (fifu_is_gag_video($url))
        return fifu_gag_img($url);
    if (fifu_is_wpcom_video($url))
        return fifu_wpcom_img($url);
    if (fifu_is_tiktok_video($url))
        return fifu_tiktok_img($url);
    if (fifu_is_googledrive_video($url))
        return fifu_googledrive_img($url);
    if (fifu_is_mega_video($url))
        return fifu_mega_img($url);
    if (fifu_is_bunny_video($url))
        return fifu_bunny_img($url);
    if (fifu_is_bitchute_video($url))
        return fifu_bitchute_img($url);
    if (fifu_is_brighteon_video($url))
        return fifu_brighteon_img($url);
    if (fifu_is_spotify_video($url))
        return fifu_spotify_img($url);
    if (fifu_is_amazon_video($url))
        return fifu_amazon_img($url);
    if (fifu_is_jwplayer_video($url))
        return fifu_jwplayer_img($url, 1280);
    if (fifu_is_sprout_video($url))
        return fifu_sprout_img($url);
    if (fifu_is_rumble_video($url))
        return fifu_rumble_img($url);
    if (fifu_is_soundcloud_video($url))
        return fifu_soundcloud_img($url);
    if (fifu_is_dailymotion_video($url))
        return fifu_dailymotion_img($url);
    if (fifu_is_twitter_video($url))
        return fifu_twitter_img($url);
    if (fifu_is_cloudflarestream_video($url))
        return fifu_cloudflarestream_img($url);
    if (fifu_is_odysee_video($url))
        return fifu_odysee_img($url);
    return null;
}

function fifu_video_src($url) {
    if (fifu_is_suvideo_video($url))
        return fifu_suvideo_src($url);
    if (fifu_is_youtube_video($url))
        return fifu_youtube_src($url);
    if (fifu_is_vimeo_video($url))
        return fifu_vimeo_src($url);
    if (fifu_is_cloudinary_video($url))
        return fifu_cloudinary_src($url);
    if (fifu_is_tumblr_video($url))
        return fifu_tumblr_src($url);
    if (fifu_is_local_video($url))
        return fifu_local_src($url);
    if (fifu_is_publitio_video($url))
        return fifu_publitio_src($url);
    if (fifu_is_gag_video($url))
        return fifu_gag_src($url);
    if (fifu_is_wpcom_video($url))
        return fifu_wpcom_src($url);
    if (fifu_is_tiktok_video($url))
        return fifu_tiktok_src($url);
    if (fifu_is_googledrive_video($url))
        return fifu_googledrive_src($url);
    if (fifu_is_mega_video($url))
        return fifu_mega_src($url);
    if (fifu_is_bunny_video($url))
        return fifu_bunny_src($url);
    if (fifu_is_bitchute_video($url))
        return fifu_bitchute_src($url);
    if (fifu_is_brighteon_video($url))
        return fifu_brighteon_src($url);
    if (fifu_is_spotify_video($url))
        return fifu_spotify_src($url);
    if (fifu_is_amazon_video($url))
        return fifu_amazon_src($url);
    if (fifu_is_jwplayer_video($url))
        return fifu_jwplayer_src($url);
    if (fifu_is_sprout_video($url))
        return fifu_sprout_src($url);
    if (fifu_is_rumble_video($url))
        return fifu_rumble_src($url);
    if (fifu_is_soundcloud_video($url))
        return fifu_soundcloud_src($url);
    if (fifu_is_dailymotion_video($url))
        return fifu_dailymotion_src($url);
    if (fifu_is_twitter_video($url))
        return fifu_twitter_src($url);
    if (fifu_is_cloudflarestream_video($url))
        return fifu_cloudflarestream_src($url);
    if (fifu_is_odysee_video($url))
        return fifu_odysee_src($url);
    if (fifu_is_custom_video($url))
        return fifu_custom_src($url);
    return null;
}

function fifu_video_social_url($id) {
    if (fifu_is_youtube_video($id))
        return fifu_youtube_social_url($id);
    if (fifu_is_vimeo_video($id))
        return fifu_vimeo_social_url($id);
    if (fifu_is_jwplayer_video($id))
        return fifu_jwplayer_social_url($id);
    return null;
}

function fifu_video_social_img($url) {
    if (fifu_is_suvideo_video($url))
        return fifu_suvideo_img($url);
    if (fifu_is_youtube_video($url))
        return fifu_youtube_social_img($url);
    if (fifu_is_vimeo_video($url))
        return fifu_vimeo_social_img($url);
    if (fifu_is_cloudinary_video($url))
        return fifu_cloudinary_img($url);
    if (fifu_is_tumblr_video($url))
        return fifu_tumblr_img($url);
    if (fifu_is_local_video($url))
        return fifu_local_img($url);
    if (fifu_is_publitio_video($url))
        return fifu_publitio_img($url);
    if (fifu_is_gag_video($url))
        return fifu_gag_img($url);
    if (fifu_is_wpcom_video($url))
        return fifu_wpcom_img($url);
    if (fifu_is_tiktok_video($url))
        return fifu_tiktok_img($url);
    if (fifu_is_googledrive_video($url))
        return fifu_googledrive_img($url);
    if (fifu_is_mega_video($url))
        return fifu_mega_img($url);
    if (fifu_is_bunny_video($url))
        return fifu_bunny_img($url);
    if (fifu_is_bitchute_video($url))
        return fifu_bitchute_img($url);
    if (fifu_is_brighteon_video($url))
        return fifu_brighteon_img($url);
    if (fifu_is_spotify_video($url))
        return fifu_spotify_img($url);
    if (fifu_is_amazon_video($url))
        return fifu_amazon_img($url);
    if (fifu_is_jwplayer_video($url))
        return fifu_jwplayer_social_img($url);
    if (fifu_is_sprout_video($url))
        return fifu_sprout_img($url);
    if (fifu_is_rumble_video($url))
        return fifu_rumble_img($url);
    if (fifu_is_soundcloud_video($url))
        return fifu_soundcloud_img($url);
    if (fifu_is_dailymotion_video($url))
        return fifu_dailymotion_img($url);
    if (fifu_is_twitter_video($url))
        return fifu_twitter_img($url);
    if (fifu_is_cloudflarestream_video($url))
        return fifu_cloudflarestream_img($url);
    if (fifu_is_odysee_video($url))
        return fifu_odysee_img($url);
    return null;
}

function fifu_is_video_thumb($url) {
    return
            fifu_is_suvideo_thumb($url) ||
            fifu_is_youtube_thumb($url) ||
            fifu_is_vimeo_thumb($url) ||
            fifu_is_cloudinary_thumb($url) ||
            fifu_is_tumblr_thumb($url) ||
            fifu_is_local_thumb($url) ||
            fifu_is_publitio_thumb($url) ||
            fifu_is_gag_thumb($url) ||
            fifu_is_wpcom_thumb($url) ||
            fifu_is_tiktok_thumb($url) ||
            fifu_is_googledrive_thumb($url) ||
            fifu_is_mega_thumb($url) ||
            fifu_is_bunny_thumb($url) ||
            fifu_is_bitchute_thumb($url) ||
            fifu_is_brighteon_thumb($url) ||
            fifu_is_spotify_thumb($url) ||
            fifu_is_amazon_thumb($url) ||
            fifu_is_jwplayer_thumb($url) ||
            fifu_is_sprout_thumb($url) ||
            fifu_is_rumble_thumb($url) ||
            fifu_is_soundcloud_thumb($url) ||
            fifu_is_dailymotion_thumb($url) ||
            fifu_is_twitter_thumb($url) ||
            fifu_is_cloudflarestream_thumb($url) ||
            fifu_is_odysee_thumb($url);
}

function fifu_calls_oembed($url) {
    return fifu_is_youtube_video($url) || fifu_is_vimeo_video($url) || fifu_is_local_video($url) || fifu_is_wpcom_video($url) || fifu_is_tiktok_video($url) || fifu_is_googledrive_video($url) || fifu_is_mega_video($url) || fifu_is_sprout_video($url) || fifu_is_rumble_video($url) || fifu_is_soundcloud_video($url) || fifu_is_dailymotion_video($url) || fifu_is_twitter_video($url) || fifu_is_odysee_video($url);
}

function fifu_video_src_by_img($url) {
    return fifu_db_get_embed_url_by_image_url($url);
}

/*
 * auto play
 */

function fifu_mouse_video_enabled() {
    return fifu_is_on('fifu_mouse_video');
}

/*
 * parameters
 */

function fifu_autoplay_enabled() {
    return fifu_is_on('fifu_autoplay');
}

function fifu_autoplay_front_enabled() {
    return fifu_is_on('fifu_autoplay_front');
}

function fifu_autoplay_elsewhere_enabled() {
    return fifu_is_on('fifu_autoplay_elsewhere');
}

function fifu_video_mute_enabled() {
    return fifu_is_on('fifu_video_mute');
}

function fifu_video_mute_mobile_enabled() {
    return fifu_is_on('fifu_video_mute_mobile');
}

function fifu_video_background_enabled() {
    return fifu_is_on('fifu_video_background');
}

function fifu_loop_enabled() {
    return fifu_is_on('fifu_loop');
}

/*
 * thumbnail
 */

function fifu_video_thumb_display_home() {
    return fifu_is_on('fifu_video_thumb') && (is_home() || (class_exists('WooCommerce') && is_shop()) || is_archive() || is_search());
}

function fifu_video_thumb_display_page() {
    return fifu_is_on('fifu_video_thumb_page') && is_page();
}

function fifu_video_thumb_display_post() {
    return fifu_is_on('fifu_video_thumb_post') && is_singular('post');
}

function fifu_video_thumb_display_cpt() {
    return fifu_is_on('fifu_video_thumb_cpt') && fifu_is_cpt();
}

function fifu_video_thumb_enabled() {
    return fifu_is_on('fifu_video_thumb') || fifu_is_on('fifu_video_thumb_page') || fifu_is_on('fifu_video_thumb_post');
}

/*
 * ajax
 */

function fifu_should_wait_ajax() {
    return (fifu_is_yith_woocommerce_wishlist_active() && fifu_is_yith_woocommerce_wishlist_ajax_enabled());
}

/* dimensions */

function fifu_update_youtube_dimensions($att_id, $url) {
    $metadata = wp_get_attachment_metadata($att_id);
    if ($metadata && ($metadata['width'] ?? 0) == 120 && ($metadata['height'] ?? 0) == 90) {
        // metadata
        update_post_meta($att_id, '_wp_attached_file', $url);

        // post_content_filtered
        global $wpdb;
        $wpdb->update($wpdb->posts, ['post_content_filtered' => $url], ['ID' => $att_id]);

        // dimension
        $metadata['width'] = 320;
        $metadata['height'] = 180;
        wp_update_attachment_metadata($att_id, $metadata);
    }
}

