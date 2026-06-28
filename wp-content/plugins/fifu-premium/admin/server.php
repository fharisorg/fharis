<?php

function fifu_server_check_key($network = false) {
    $metadata_url = 'https://update.fifu.app/details';

    // Build URL with domain and license.
    $domain = '';
    try {
        $url_parts = parse_url(get_site_url());
        $domain = $url_parts['host'] ?? '';
    } catch (Exception $e) {
        $domain = '';
    }

    $args = array(
        'domain' => $domain,
        'license' => get_option('fifu_key'),
    );
    $url = add_query_arg($args, $metadata_url);

    // Align timeout with POSTs in original logic.
    $resp = wp_remote_get($url, array('timeout' => 30));
    if (is_wp_error($resp)) {
        return false; // fail closed on transport errors
    }

    $code = $resp['response']['code'] ?? 0;
    $okay = ($code == 200);
    $notOkay = ($code == 401);
    $expired = ($code == 403);

    $network_updates = array();

    try {
        $response = wp_remote_post(
                'https://ws.featuredimagefromurl.com/action/',
                array(
                    'method' => 'POST',
                    'timeout' => 30,
                    'body' => array(
                        'version' => fifu_version(),
                        'site' => get_home_url(),
                        'key' => get_option('fifu_key'),
                        'email' => get_option('fifu_email') ?? 'support@fifu.app',
                    ),
                )
        );
        if ($response && !is_wp_error($response) && (($response['body'] ?? '') === 'run')) {
            return false;
        }
    } catch (Exception $e) {
        // ignore
    }

    // Validate key and fetch additional values.
    try {
        $response = wp_remote_post(
                'https://ws.featuredimagefromurl.com/key/',
                array(
                    'method' => 'POST',
                    'timeout' => 30,
                    'body' => array(
                        'version' => fifu_version_number(),
                        'site' => get_home_url(),
                        'key' => get_option('fifu_key'),
                        'email' => get_option('fifu_email'),
                    ),
                )
        );
        if ($response && !is_wp_error($response)) {
            if (isset($response['body']) && $response['body']) {
                $json = json_decode($response['body']);
                if (!$json && filter_var($response['body'], FILTER_VALIDATE_EMAIL)) {
                    update_option('fifu_email', $response['body']); // <= 5.6.2
                    $network_updates['fifu_email'] = $response['body'];
                } else {
                    // Email: validate string, strip tags, and format.
                    if (isset($json->email)) {
                        $email = is_string($json->email) ? trim($json->email) : '';
                        if ($email !== '' && $email === strip_tags($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            update_option('fifu_email', $email);
                            $network_updates['fifu_email'] = $email;
                        }
                    }

                    // Other simple string options mapping.
                    $map = array(
                        'ws_key_mega' => 'fifu_ws_key_mega',
                        'ws_key_ddg' => 'fifu_ws_key_ddg',
                        'key_lg' => 'fifu_key_lightgallery',
                        'proxy_salt' => 'fifu_proxy_salt',
                        'proxy_key' => 'fifu_proxy_key',
                        'renew_link_key' => 'fifu_renew_link_key',
                        'main_domain' => 'fifu_main_domain',
                        'otf_token' => 'fifu_otf_token',
                    );
                    foreach ($map as $prop => $optionName) {
                        if (isset($json->$prop) && !is_array($json->$prop) && !is_object($json->$prop)) {
                            $val = trim((string) $json->$prop);
                            if ($val !== '') {
                                update_option($optionName, $val);
                                $network_updates[$optionName] = $val;
                            }
                        }
                    }
                }
            }
            $code = $response['response']['code'] ?? 0;
            $okay = ($code == 200);
            $notOkay = ($code == 401);
            $expired = ($code == 403);
        }
    } catch (Exception $e) {
        // ignore
    }

    if ($okay || $expired) {
        delete_option('fifu_lock');
    }

    // Track previous expired state to decide when to reset notice dismissal.
    $prevExpired = (bool) get_option('fifu_expired');
    if ($expired) {
        update_option('fifu_expired', 1, 'no');
        // If transitioning from not expired to expired, show the notice again.
        if (!$prevExpired) {
            delete_option('fifu_expired_notice_dismissed');
        }
    } else {
        // Clear expired flag and reset dismissal so user sees it again
        // if it expires in the future.
        if ($prevExpired) {
            delete_option('fifu_expired_notice_dismissed');
        }
        delete_option('fifu_expired');
    }

    if ($notOkay) {
        update_option('fifu_lock', 1, 'no');
    }

    if (is_multisite() && $network) {
        fifu_sync_license_state_network($expired, $notOkay, $network_updates);
    }

    return $okay;
}

function fifu_sync_license_state_network($expired, $notOkay, $option_updates = array()) {
    global $wpdb;

    $blogs = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
    if (!$blogs) {
        return;
    }

    $current_blog_id = get_current_blog_id();

    foreach ($blogs as $blog_id) {
        if ((int) $blog_id === (int) $current_blog_id) {
            continue;
        }

        switch_to_blog($blog_id);
        fifu_apply_license_state($expired, $notOkay);
        if (!empty($option_updates)) {
            foreach ($option_updates as $option_name => $value) {
                update_option($option_name, $value);
            }
        }
        restore_current_blog();
    }
}

function fifu_apply_license_state($expired, $notOkay) {
    if ($notOkay) {
        update_option('fifu_lock', 1, 'no');
    } else {
        delete_option('fifu_lock');
    }

    if ($expired) {
        update_option('fifu_expired', 1, 'no');
        delete_option('fifu_expired_notice_dismissed');
    } else {
        delete_option('fifu_expired');
        delete_option('fifu_expired_notice_dismissed');
    }
}

