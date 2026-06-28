<?php

function fifu_find_featured_image($url, $find_video) {
    $queryParams = http_build_query([
        'site' => fifu_get_home_url(),
        'partial_key' => fifu_partial_key(),
        'url' => $url,
        'find_video' => $find_video
    ]);
    $workerUrl = "https://find-media.fifu.app?" . $queryParams;

    $response = wp_remote_get($workerUrl);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) != 200)
        return null;

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (empty($data['url'] ?? null))
        return null;

    if (count($data['url']) == 1)
        return $data['url'][0];

    return fifu_find_largest_image($data['url']);
}

function fifu_find_largest_image($urls) {
    $largest = null;
    $largest_area = 0;

    foreach ($urls as $img_url) {
        $img_url = html_entity_decode($img_url);
        $size = @getimagesize($img_url);
        if ($size) {
            $width = $size[0] ?? 0;
            $height = $size[1] ?? 0;
            $area = $width * $height;
            if ($area > $largest_area) {
                $largest = $img_url;
                $largest_area = $area;
            }
        }
    }

    return $largest;
}

function fifu_find_amazon_media($url, $post_id) {
    $queryParams = http_build_query([
        'site' => fifu_get_home_url(),
        'partial_key' => fifu_partial_key(),
        'url' => $url,
    ]);
    $workerUrl = "https://find-media-amazon.fifu.workers.dev?" . $queryParams;

    $response = wp_remote_get($workerUrl);
    sleep(6);

    $counter = get_post_meta($post_id, 'fifu_finder_counter', true);
    $counter = !$counter ? 1 : $counter + 1;
    update_post_meta($post_id, 'fifu_finder_counter', $counter);
    error_log('[' . $post_id . '] (' . $counter . '): ' . $url);

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) != 200)
        return null;

    $body = wp_remote_retrieve_body($response);
    $arr_urls = json_decode($body, true);

    if (!$arr_urls)
        return null;

    error_log('[' . $post_id . '] Images found: ' . count($arr_urls['image'] ?? []));
    error_log('[' . $post_id . '] Videos found: ' . count($arr_urls['video'] ?? []));
    delete_post_meta($post_id, 'fifu_finder_counter');

    return $arr_urls;
}

