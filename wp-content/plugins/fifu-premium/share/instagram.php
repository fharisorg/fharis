<?php

function fifu_share_prepare_instagram_media($post_id, $site_origin, $override_url = null) {
    $candidates = [];
    if ($override_url) {
        $candidates[] = $override_url;
    }

    $featured = get_the_post_thumbnail_url($post_id, 'full');
    if ($featured) {
        $candidates[] = $featured;
    }

    if (function_exists('fifu_main_image_url')) {
        $main = fifu_main_image_url($post_id, true);
        if ($main) {
            $candidates[] = $main;
        }
    }

    $candidates = array_filter($candidates, static function ($candidate) {
        return is_string($candidate) && trim($candidate) !== '';
    });
    $candidates = array_unique(array_map('trim', $candidates));

    foreach ($candidates as $candidate) {
        $normalized = fifu_share_normalize_https_url($candidate);
        if (!$normalized) {
            continue;
        }
        if (!fifu_share_is_same_host($normalized, $site_origin)) {
            continue;
        }
        return array(
            'type' => 'image',
            'image_url' => $normalized,
        );
    }

    return null;
}

function fifu_share_select_instagram_account(array $accounts, $requested_ig_user_id, $requested_page_id, $stored_selected) {
    $normalize = static function ($value) {
        if (!is_string($value)) {
            return '';
        }
        $value = trim($value);
        return $value === '' ? '' : sanitize_text_field($value);
    };
    $requested_ig_user_id = $normalize($requested_ig_user_id);
    $requested_page_id = $normalize($requested_page_id);
    $stored_selected = $normalize($stored_selected);

    $finder = static function ($field, $value, array $accounts) use ($normalize) {
        if ($value === '') {
            return null;
        }
        foreach ($accounts as $account) {
            if (!isset($account[$field])) {
                continue;
            }
            if ($value === $normalize($account[$field])) {
                return $account;
            }
        }
        return null;
    };

    $match = $finder('ig_user_id', $requested_ig_user_id, $accounts);
    if ($match) {
        return $match;
    }

    $match = $finder('page_id', $requested_page_id, $accounts);
    if ($match) {
        return $match;
    }

    $match = $finder('ig_user_id', $stored_selected, $accounts);
    if ($match) {
        return $match;
    }

    if (count($accounts) === 1) {
        return $accounts[0];
    }

    return null;
}

function fifu_share_instagram_api(WP_REST_Request $request) {
    $post_id = $request->get_param('postId');
    if (!$post_id) {
        $post_id = $request->get_param('post_id');
    }
    $post_id = $post_id !== null ? intval($post_id) : 0;

    if ($post_id <= 0) {
        return new WP_REST_Response(array('error' => 'missing_post_id'), 400);
    }

    $post = get_post($post_id);
    if (!$post) {
        return new WP_REST_Response(array('error' => 'post_not_found'), 404);
    }

    $tenant_id = (string) get_current_blog_id();
    $oauth_option_key = 'fifu_oauth_' . $tenant_id;
    $oauth_payload = get_option($oauth_option_key);
    if (!is_array($oauth_payload) || empty($oauth_payload['user_access_token'])) {
        return new WP_REST_Response(array('error' => 'instagram_not_connected'), 409);
    }

    $status_snapshot = fifu_get_social_status_snapshot();
    $instagram_status = isset($status_snapshot['instagram']) && is_array($status_snapshot['instagram']) ? $status_snapshot['instagram'] : array();
    $instagram_accounts = isset($instagram_status['accounts']) && is_array($instagram_status['accounts']) ? $instagram_status['accounts'] : array();
    if (empty($instagram_accounts)) {
        return new WP_REST_Response(array('error' => 'instagram_account_missing'), 409);
    }

    $instagram_enabled_requested = isset($instagram_status['enabled_requested']) ? (bool) $instagram_status['enabled_requested'] : false;
    if (!$instagram_enabled_requested) {
        return new WP_REST_Response(array('error' => 'instagram_not_enabled'), 409);
    }

    $requested_ig_user_id = $request->get_param('igUserId');
    if (!$requested_ig_user_id) {
        $requested_ig_user_id = $request->get_param('ig_user_id');
    }
    $requested_page_id = $request->get_param('pageId');
    if (!$requested_page_id) {
        $requested_page_id = $request->get_param('page_id');
    }

    $stored_selected = isset($instagram_status['selected_ig_user_id']) ? $instagram_status['selected_ig_user_id'] : null;
    $selected_account = fifu_share_select_instagram_account($instagram_accounts, $requested_ig_user_id, $requested_page_id, $stored_selected);
    if (!$selected_account || empty($selected_account['ig_user_id'])) {
        return new WP_REST_Response(array(
            'error' => 'instagram_account_selection_required',
            'message' => 'Select which Instagram account should receive the post.',
                ), 409);
    }

    $site_origin = rtrim(home_url('/'), '/');
    $site_origin = set_url_scheme($site_origin, 'https');
    if (parse_url($site_origin, PHP_URL_SCHEME) !== 'https') {
        return new WP_REST_Response(array('error' => 'site_not_https', 'message' => 'Instagram publishing requires the site origin to use HTTPS.'), 409);
    }

    $site_param = fifu_get_home_url();
    $site_param = set_url_scheme($site_param, 'https');
    $partial_key = fifu_partial_key();

    $override_media_url = null;
    foreach (array('imageUrl', 'image_url', 'mediaUrl', 'media_url') as $media_key) {
        $candidate = $request->get_param($media_key);
        if ($candidate) {
            $override_media_url = $candidate;
            break;
        }
    }

    $media_payload = fifu_share_prepare_instagram_media($post_id, $site_origin, $override_media_url);
    if (!$media_payload) {
        return new WP_REST_Response(array(
            'error' => 'instagram_media_not_found',
            'message' => 'Unable to locate an HTTPS image hosted on this site for the post. Provide imageUrl or set a local featured image.',
                ), 422);
    }

    $post_payload = fifu_build_post_share_payload($post_id);
    if (!$post_payload) {
        return new WP_REST_Response(array('error' => 'unable_to_prepare_post'), 500);
    }

    $caption_param = $request->get_param('caption');
    if (!$caption_param) {
        $caption_param = $request->get_param('message');
    }
    if (!$caption_param) {
        $caption_param = $request->get_param('text');
    }

    if (is_string($caption_param)) {
        $caption = trim(wp_strip_all_tags($caption_param));
    } else {
        $caption = '';
    }

    if ($caption === '') {
        $caption = (string) $post_payload['excerpt'];
    }
    $caption = preg_replace('/\s+/', ' ', $caption);
    if (function_exists('mb_substr')) {
        $caption = mb_substr($caption, 0, 2200);
    } else {
        $caption = substr($caption, 0, 2200);
    }

    $permalink_https = isset($post_payload['permalink']) ? set_url_scheme($post_payload['permalink'], 'https') : '';
    $share_link = '';
    if ($permalink_https && fifu_share_is_same_host($permalink_https, $site_origin)) {
        $share_link = $permalink_https;
    }

    $payload = array(
        'tenant_id' => $tenant_id,
        'site' => $site_param,
        'site_origin' => $site_origin,
        'partial_key' => $partial_key,
        'ig_user_id' => (string) $selected_account['ig_user_id'],
        'page_id' => isset($selected_account['page_id']) ? (string) $selected_account['page_id'] : '',
        'post' => $post_payload,
        'media' => $media_payload,
        'oauth' => $oauth_payload,
        'instagram_enabled' => $instagram_enabled_requested,
        'status_snapshot' => $status_snapshot,
        'requested_by' => array(
            'user_id' => get_current_user_id(),
            'timestamp' => gmdate('c', current_time('timestamp', true)),
        ),
    );

    if ($caption !== '') {
        $payload['caption'] = $caption;
        $payload['message'] = $caption;
    }

    if ($share_link !== '') {
        $payload['link'] = $share_link;
    }

    $worker_url = 'https://auto-share.fifu.workers.dev/share/instagram';
    $response = wp_remote_post($worker_url, array(
        'timeout' => 20,
        'headers' => array(
            'Content-Type' => 'application/json',
        ),
        'body' => wp_json_encode($payload),
    ));

    if (is_wp_error($response)) {
        fifu_plugin_log(['share-instagram' => [
                'error' => 'worker_unreachable',
                'message' => $response->get_error_message(),
        ]]);
        return new WP_REST_Response(array(
            'error' => 'worker_unreachable',
            'details' => $response->get_error_message(),
                ), 502);
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if ($status_code < 200 || $status_code >= 300) {
        fifu_plugin_log(['share-instagram' => [
                'error' => 'worker_error',
                'status_code' => $status_code,
                'body' => substr($body, 0, 2000),
        ]]);
        return new WP_REST_Response(array(
            'error' => 'worker_error',
            'status_code' => $status_code,
            'body' => $body,
                ), 502);
    }

    update_option('fifu_instagram_selected_ig_user', sanitize_text_field((string) $selected_account['ig_user_id']), false);

    return new WP_REST_Response(array(
        'ok' => true,
        'worker_status' => $status_code,
        'worker_response' => is_array($data) ? $data : array('raw' => $body),
            ), 200);
}

// Register REST endpoint for Instagram share
add_action('rest_api_init', function () {
    register_rest_route('fifu-premium/v2', '/share/instagram/', array(
        'methods' => 'POST',
        'callback' => 'fifu_share_instagram_api',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
});

