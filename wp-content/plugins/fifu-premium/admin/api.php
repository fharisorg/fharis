<?php

define('FIFU_NO_CREDENTIALS', json_encode(array('code' => 'no_credentials')));
define('FIFU_SU_ADDRESS', FIFU_CLOUD_DEBUG ? 'http://192.168.0.31:8080' : 'https://ws.fifu.app');
define('FIFU_CLIENT', 'fifu-premium');

function fifu_try_again_later() {
    $strings = fifu_get_strings_api();
    return json_encode(array('code' => 0, 'message' => $strings['info']['try'](), 'color' => 'orange'));
}

function fifu_is_local() {
    $query = 'http://localhost';
    return substr(get_home_url(), 0, strlen($query)) === $query || FIFU_CLOUD_DEBUG;
}

function fifu_remote_post($endpoint, $array) {
    return fifu_is_local() ? wp_remote_post($endpoint, $array) : wp_safe_remote_post($endpoint, $array);
}

function fifu_api_image_url(WP_REST_Request $request) {
    $param = $request['post_id'] ?? null;
    return fifu_main_image_url($param, true);
}

function fifu_api_sign_up(WP_REST_Request $request) {
    $email = $request['email'] ?? '';
    $site = fifu_get_home_url();

    fifu_cloud_log(['sign_up' => ['site' => $site]]);

    $array = array(
        'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
        'body' => json_encode(
                array(
                    'site' => $site,
                    'email' => $email,
                    'public_key' => fifu_create_keys($email),
                    'slug' => FIFU_CLIENT,
                    'version' => fifu_version_number()
                )
        ),
        'method' => 'POST',
        'data_format' => 'body',
        'blocking' => true,
        'timeout' => 120,
    );
    $response = fifu_remote_post(FIFU_SU_ADDRESS . '/sign-up/', $array);
    if (is_wp_error($response) || ($response['response']['code'] ?? 0) == 404) {
        fifu_delete_credentials();
        return json_decode(fifu_try_again_later());
    }

    $json = json_decode($response['http_response']->get_response_object()->body ?? '');
    if (($json->code ?? 0) <= 0) {
        fifu_delete_credentials();
        return $json;
    }

    return $json;
}

function fifu_delete_credentials() {
    delete_option('fifu_su_privkey');
    delete_option('fifu_su_email');
    delete_option('fifu_proxy_auth');
}

function fifu_api_cancel(WP_REST_Request $request) {
    if (!fifu_su_sign_up_complete())
        return json_decode(FIFU_NO_CREDENTIALS);

    $site = fifu_get_home_url();
    $ip = fifu_get_ip();
    $time = time();
    $signature = fifu_create_signature($site . $time . $ip);

    fifu_cloud_log(['cancel' => ['site' => $site]]);

    $array = array(
        'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
        'body' => json_encode(
                array(
                    'site' => $site,
                    'signature' => $signature,
                    'time' => $time,
                    'ip' => $ip,
                    'slug' => FIFU_CLIENT,
                    'version' => fifu_version_number()
                )
        ),
        'method' => 'POST',
        'data_format' => 'body',
        'blocking' => true,
        'timeout' => 30,
    );
    $response = fifu_remote_post(FIFU_SU_ADDRESS . '/cancel/', $array);
    if (is_wp_error($response))
        return json_decode(fifu_try_again_later());

    $json = json_decode($response['http_response']->get_response_object()->body ?? '');

    return $json;
}

function fifu_api_payment_info(WP_REST_Request $request) {
    if (!fifu_su_sign_up_complete())
        return json_decode(FIFU_NO_CREDENTIALS);

    $site = fifu_get_home_url();
    $ip = fifu_get_ip();
    $time = time();
    $signature = fifu_create_signature($site . $time . $ip);

    fifu_cloud_log(['payment_info' => ['site' => $site]]);

    $array = array(
        'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
        'body' => json_encode(
                array(
                    'site' => $site,
                    'signature' => $signature,
                    'time' => $time,
                    'ip' => $ip,
                    'slug' => FIFU_CLIENT,
                    'version' => fifu_version_number()
                )
        ),
        'method' => 'POST',
        'data_format' => 'body',
        'blocking' => true,
        'timeout' => 30,
    );
    $response = fifu_remote_post(FIFU_SU_ADDRESS . '/payment-info/', $array);
    if (is_wp_error($response))
        return json_decode(fifu_try_again_later());

    $json = json_decode($response['http_response']->get_response_object()->body ?? '');

    return $json;
}

function fifu_api_connected(WP_REST_Request $request) {
    if (!fifu_su_sign_up_complete())
        return json_decode(FIFU_NO_CREDENTIALS);

    $email = fifu_su_get_email();
    $site = fifu_get_home_url();
    $ip = fifu_get_ip();
    $time = time();
    $signature = fifu_create_signature($site . $email . $time . $ip);

    fifu_cloud_log(['connected' => ['site' => $site]]);

    $array = array(
        'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
        'body' => json_encode(
                array(
                    'site' => $site,
                    'email' => $email,
                    'signature' => $signature,
                    'time' => $time,
                    'ip' => $ip,
                    'proxy_auth' => get_option('fifu_proxy_auth') ? true : false,
                    'slug' => FIFU_CLIENT,
                    'version' => fifu_version_number()
                )
        ),
        'method' => 'POST',
        'data_format' => 'body',
        'blocking' => true,
        'timeout' => 30,
    );
    $response = fifu_remote_post(FIFU_SU_ADDRESS . '/connected/', $array);
    if (is_wp_error($response))
        return json_decode(fifu_try_again_later());

    // offline
    if (($response['http_response']->get_response_object()->status_code ?? 0) == 404)
        return json_decode(fifu_try_again_later());

    $json = json_decode($response['http_response']->get_response_object()->body ?? '');

    if (isset($json->proxy_key)) {
        $privKey = openssl_decrypt(base64_decode((get_option('fifu_su_privkey')[0] ?? '')), "AES-128-ECB", $email . $site);
        if ($privKey) {
            openssl_private_decrypt(base64_decode($json->proxy_key ?? ''), $key, $privKey);
            openssl_private_decrypt(base64_decode($json->proxy_salt ?? ''), $salt, $privKey);
            update_option('fifu_proxy_auth', array($key, $salt));
        }
    }

    return $json;
}

function fifu_get_ip() {
    foreach (array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR') as $key) {
        if (isset($_SERVER[$key]) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false)
                    return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

function fifu_api_create_thumbnails_list(WP_REST_Request $request) {
    if (!fifu_su_sign_up_complete())
        return json_decode(FIFU_NO_CREDENTIALS);

    $images = $request['selected'] ?? [];

    return fifu_create_thumbnails_list($images, false);
}

function fifu_create_thumbnails_list($images, $cron = false) {
    if (!fifu_su_sign_up_complete())
        return json_decode(FIFU_NO_CREDENTIALS);

    if ($cron) {
        $code = get_option('fifu_cloud_upload_auto_code');
        if (!$code)
            return json_decode(FIFU_NO_CREDENTIALS);
    }

    $sent_urls = array();
    $saved_urls = array();

    $rows = array();
    $total = count($images);
    $url_sign = '';
    foreach ($images as $image) {
        if (!$cron) {
            // manual
            $post_id = $image[0] ?? null;
            $url = $image[1] ?? null;
            $meta_key = $image[2] ?? null;
            $meta_id = $image[3] ?? null;
            $is_category = ($image[4] ?? 0) == 1;
            $video_url = $image[5] ?? null;
        } else {
            // upload auto
            $post_id = $image->post_id ?? null;
            $url = $image->url ?? null;
            $meta_key = $image->meta_key ?? null;
            $meta_id = $image->meta_id ?? null;
            $is_category = ($image->category ?? 0) == 1;
            $video_url = $image->video_url ?? null;

            if (fifu_db_get_attempts_invalid_media_su($url) >= 5)
                continue;
            array_push($sent_urls, $url);
        }

        if (!$url || !$post_id)
            continue;

        $encoded_url = base64_encode($url);
        $encoded_video_url = $video_url ? base64_encode($video_url) : '';
        array_push($rows, array($post_id, $encoded_url, $meta_key, $meta_id, $is_category, $encoded_video_url));
        $url_sign .= substr($encoded_url, -10);

        fifu_cloud_log(['create_thumbnails_list' => ['post_id' => $post_id, 'meta_key' => $meta_key, 'meta_id' => $meta_id, 'is_category' => $is_category, 'video_url' => $video_url, 'url' => $url]]);
    }
    $time = time();
    $ip = fifu_get_ip();
    $site = fifu_get_home_url();
    $signature = fifu_create_signature($url_sign . $site . $time . $ip);
    $array = array(
        'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
        'body' => json_encode(
                array(
                    'rows' => $rows,
                    'site' => $site,
                    'signature' => $signature,
                    'time' => $time,
                    'ip' => $ip,
                    'upload_auto' => $cron,
                    'slug' => FIFU_CLIENT,
                    'version' => fifu_version_number()
                )
        ),
        'method' => 'POST',
        'data_format' => 'body',
        'blocking' => true,
        'timeout' => 300,
    );
    $response = fifu_remote_post(FIFU_SU_ADDRESS . '/create-thumbnails/', $array);
    if (is_wp_error($response))
        return;

    $json = json_decode($response['http_response']->get_response_object()->body ?? '');
    $code = $json->code ?? 0;
    if ($code && $code > 0) {
        if (count((array) ($json->thumbnails ?? [])) > 0) {
            $category_images = array();
            $post_images = array();
            foreach ((array) $json->thumbnails as $thumbnail) {
                if ($thumbnail->is_category ?? false)
                    array_push($category_images, $thumbnail);
                else
                    array_push($post_images, $thumbnail);

                array_push($saved_urls, $thumbnail->meta_value ?? '');
            }
            if (count($category_images) > 0)
                fifu_ctgr_add_urls_su($json->bucket_id ?? '', $category_images);

            if (count($post_images) > 0)
                fifu_add_urls_su($json->bucket_id ?? '', $post_images);
        }

        // check invalid images
        if ($cron && count($sent_urls) > count($saved_urls)) {
            foreach ($sent_urls as $sent_url) {
                if (!in_array($sent_url, $saved_urls))
                    fifu_db_insert_invalid_media_su($sent_url);
                else
                    fifu_db_delete_invalid_media_su($sent_url);
            }
        }
    }

    return $json;
}

function fifu_delete_thumbnails($hex_ids) {
    if (!fifu_su_sign_up_complete())
        return json_decode(FIFU_NO_CREDENTIALS);

    $code = get_option('fifu_cloud_delete_auto_code');
    if (!$code)
        return json_decode(FIFU_NO_CREDENTIALS);

    // 1) verification
    $rows = array();
    $total = count($hex_ids);
    $hex_id_sign = '';
    foreach ($hex_ids as $hex_id) {
        array_push($rows, $hex_id);
        $hex_id_sign .= $hex_id;

        fifu_cloud_log(['delete_auto (send used)' => ['hex_id' => $hex_id]]);
    }
    $time = time();
    $ip = fifu_get_ip();
    $site = fifu_get_home_url();
    $signature = fifu_create_signature($hex_id_sign . $site . $time . $ip);
    $array = array(
        'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
        'body' => json_encode(
                array(
                    'rows' => $rows,
                    'site' => $site,
                    'signature' => $signature,
                    'time' => $time,
                    'ip' => $ip,
                    'slug' => FIFU_CLIENT,
                    'version' => fifu_version_number()
                )
        ),
        'method' => 'POST',
        'data_format' => 'body',
        'blocking' => true,
        'timeout' => 300,
    );
    $response = fifu_remote_post(FIFU_SU_ADDRESS . '/delete-thumbnails/', $array);
    if (is_wp_error($response))
        return;

    $json = json_decode($response['http_response']->get_response_object()->body ?? '');
    fifu_cloud_log(['delete_auto (response)' => ['json' => $json]]);
    $code = $json->code ?? 0;
    if ($code && $code > 0) {
        if (count((array) ($json->hex_ids ?? [])) > 0) {
            if (isset($json->hex_ids) && is_array($json->hex_ids)) {
                // Get the hex_ids and process them
                $hex_ids = (array) $json->hex_ids;

                if (count($hex_ids) > 0) {
                    $results = fifu_usage_verification_su($hex_ids);

                    // Remove matching hex_ids from the list
                    foreach ($results as $meta_value) {
                        foreach ($hex_ids as $key => $hex_id) {
                            if (strpos($meta_value, $hex_id) !== false) {
                                unset($hex_ids[$key]);
                                fifu_cloud_log(['found' => $hex_id]);
                            }
                        }
                    }
                }

                // Proceed with the remaining hex_ids
                foreach ($hex_ids as $hex_id) {
                    fifu_cloud_log(['delete' => $hex_id]);
                }

                // 2 delete
                $batches = array_chunk($hex_ids, 1000); // Split hex_ids into batches of 1,000
                foreach ($batches as $batch) {
                    $rows = array();
                    $id_sign = '';
                    foreach ($batch as $hex_id) {
                        array_push($rows, $hex_id);
                        $id_sign .= $hex_id;

                        fifu_cloud_log(['delete_auto (send unused back)' => ['hex_id' => $hex_id]]);
                    }
                    $time = time();
                    $signature = fifu_create_signature($id_sign . $site . $time . $ip);
                    $array = array(
                        'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
                        'body' => json_encode(
                                array(
                                    'rows' => $rows,
                                    'site' => $site,
                                    'signature' => $signature,
                                    'time' => $time,
                                    'ip' => $ip,
                                    'slug' => FIFU_CLIENT,
                                    'version' => fifu_version_number()
                                )
                        ),
                        'method' => 'POST',
                        'data_format' => 'body',
                        'blocking' => true,
                        'timeout' => 300,
                    );
                    $response = fifu_remote_post(FIFU_SU_ADDRESS . '/delete-thumbnails-confirm/', $array);
                    if (is_wp_error($response))
                        return;

                    // Delay of 5 seconds between each batch
                    sleep(5);
                }
            }
        }
    }

    return $json;
}

function fifu_api_delete(WP_REST_Request $request) {
    if (!fifu_su_sign_up_complete())
        return json_decode(FIFU_NO_CREDENTIALS);

    $rows = array();
    $images = $request['selected'] ?? [];
    $total = count($images);
    $url_sign = '';
    foreach ($images as $image) {
        $storage_id = $image['storage_id'] ?? null;
        if (!$storage_id)
            continue;

        array_push($rows, $storage_id);
        $url_sign .= $storage_id;
    }
    $time = time();
    $ip = fifu_get_ip();
    $site = fifu_get_home_url();
    $signature = fifu_create_signature($url_sign . $site . $time . $ip);

    fifu_cloud_log(['delete' => ['rows' => $rows]]);

    $array = array(
        'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
        'body' => json_encode(
                array(
                    'rows' => $rows,
                    'site' => $site,
                    'signature' => $signature,
                    'time' => $time,
                    'ip' => $ip,
                    'slug' => FIFU_CLIENT,
                    'version' => fifu_version_number()
                )
        ),
        'method' => 'POST',
        'data_format' => 'body',
        'blocking' => true,
        'timeout' => 60,
    );
    $response = fifu_remote_post(FIFU_SU_ADDRESS . '/delete/', $array);
    if (is_wp_error($response))
        return json_decode(fifu_try_again_later());

    $json = json_decode($response['http_response']->get_response_object()->body ?? '');
    if (!$json)
        return null;

    $code = $json->code ?? 0;
    if ($code && $code > 0) {
        if (count((array) ($json->urls ?? [])) > 0) {
            $map = array();
            $posts = fifu_get_posts_su($rows);
            foreach ($posts as $post)
                $map[$post->storage_id] = $post;

            $category_images = array();
            $post_images = array();
            foreach ($posts as $post) {
                if ($post->category ?? false)
                    array_push($category_images, $post);
                else
                    array_push($post_images, $post);
            }

            if (count($post_images) > 0)
                fifu_remove_urls_su($json->bucket_id ?? '', $post_images, (array) ($json->urls ?? []), (array) ($json->video_urls ?? []));

            if (count($category_images) > 0)
                fifu_ctgr_remove_urls_su($json->bucket_id ?? '', $category_images, (array) ($json->urls ?? []), (array) ($json->video_urls ?? []));

            return fifu_api_confirm_delete($rows, $site, $ip, $url_sign);
        }
    }

    return $json;
}

function fifu_api_confirm_delete($rows, $site, $ip, $url_sign) {
    if (!fifu_su_sign_up_complete())
        return json_decode(FIFU_NO_CREDENTIALS);

    $time = time();
    $signature = fifu_create_signature($url_sign . $site . $time . $ip);

    fifu_cloud_log(['confirm_delete' => ['rows' => $rows]]);

    $array = array(
        'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
        'body' => json_encode(
                array(
                    'rows' => $rows,
                    'site' => $site,
                    'signature' => $signature,
                    'time' => $time,
                    'ip' => $ip,
                    'slug' => FIFU_CLIENT,
                    'version' => fifu_version_number()
                )
        ),
        'method' => 'POST',
        'data_format' => 'body',
        'blocking' => true,
        'timeout' => 300,
    );
    $response = fifu_remote_post(FIFU_SU_ADDRESS . '/confirm-delete/', $array);
    if (is_wp_error($response))
        return json_decode(fifu_try_again_later());

    $json = json_decode($response['http_response']->get_response_object()->body ?? '');
    return $json;
}

function fifu_api_reset_credentials(WP_REST_Request $request) {
    fifu_delete_credentials();
    $email = $request['email'] ?? '';
    $site = fifu_get_home_url();

    fifu_cloud_log(['reset_credentials' => ['site' => $site]]);

    $array = array(
        'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
        'body' => json_encode(
                array(
                    'site' => $site,
                    'email' => $email,
                    'public_key' => fifu_create_keys($email),
                    'slug' => FIFU_CLIENT,
                    'version' => fifu_version_number()
                )
        ),
        'method' => 'POST',
        'data_format' => 'body',
        'blocking' => true,
        'timeout' => 30,
    );
    $response = fifu_remote_post(FIFU_SU_ADDRESS . '/reset-credentials/', $array);
    if (is_wp_error($response))
        return json_decode(fifu_try_again_later());
    else {
        $json = json_decode($response['http_response']->get_response_object()->body ?? '');

        # unknown site
        if (($json->code ?? 0) == -21)
            fifu_delete_credentials();

        return $json;
    }
}

function fifu_api_list_all_su(WP_REST_Request $request) {
    if (!fifu_su_sign_up_complete())
        return json_decode(FIFU_NO_CREDENTIALS);

    $time = time();
    $site = fifu_get_home_url();
    $page = (int) $request['page'];
    $type = $request['type'] ?? '';
    $keyword = $request['keyword'] ?? '';
    $ip = fifu_get_ip();
    $signature = fifu_create_signature($site . $time . $ip);

    fifu_cloud_log(['list_all_su' => ['site' => $site, 'page' => $page, 'type' => $type, 'keyword' => $keyword]]);

    $array = array(
        'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
        'body' => json_encode(
                array(
                    'site' => $site,
                    'signature' => $signature,
                    'time' => $time,
                    'ip' => $ip,
                    'page' => $page,
                    'slug' => FIFU_CLIENT,
                    'version' => fifu_version_number(),
                    'type' => $type,
                    'keyword' => $keyword
                )
        ),
        'method' => 'POST',
        'data_format' => 'body',
        'blocking' => true,
        'timeout' => 30,
    );
    $response = fifu_remote_post(FIFU_SU_ADDRESS . '/list-all/', $array);
    if (is_wp_error($response))
        return json_decode(fifu_try_again_later());

    // offline
    if (($response['http_response']->get_response_object()->status_code ?? 0) == 404)
        return json_decode(fifu_try_again_later());

    $map = array();
    $posts = fifu_get_posts_su(null);
    foreach ($posts as $post)
        $map[$post->storage_id] = $post;

    $json = json_decode($response['http_response']->get_response_object()->body ?? '');
    if ($json && ($json->code ?? 0) > 0) {
        for ($i = 0; $i < count($json->photo_data ?? []); $i++) {
            $post = $json->photo_data[$i];
            if (isset($map[$post->storage_id])) {
                $post->title = $map[$post->storage_id]->post_title;
                $post->meta_id = $map[$post->storage_id]->meta_id;
                $post->post_id = $map[$post->storage_id]->post_id;
                $post->meta_key = $map[$post->storage_id]->meta_key;
            } else
                $post->title = $post->meta_id = $post->post_id = $post->meta_key = '';
            $is_video = strpos($post->meta_key ?? '', 'video') !== false;
            $url = 'https://cdn.fifu.app/' . ($json->bucket_id ?? '') . '/' . ($post->storage_id ?? '');
            $post->proxy_url = fifu_speedup_get_signed_url($url, 128, 128, $json->bucket_id ?? '', $post->storage_id ?? '', $is_video);

            // sanitize fields
            $post->storage_id = sanitize_text_field($post->storage_id ?? '');
            $post->title = sanitize_text_field($post->title ?? '');
            $post->date = sanitize_text_field($post->date ?? '');
            $post->meta_key = sanitize_text_field($post->meta_key ?? '');
            $post->proxy_url = esc_url_raw($post->proxy_url ?? '');
            $post->meta_id = intval($post->meta_id ?? 0);
            $post->post_id = intval($post->post_id ?? 0);
            $post->is_category = isset($post->is_category) ? (bool) $post->is_category : false;
        }
    }
    return $json;
}

function fifu_api_list_daily_count(WP_REST_Request $request) {
    if (!fifu_su_sign_up_complete())
        return json_decode(FIFU_NO_CREDENTIALS);

    $time = time();
    $site = fifu_get_home_url();
    $ip = fifu_get_ip();
    $signature = fifu_create_signature($site . $time . $ip);

    fifu_cloud_log(['list_daily_count' => ['site' => $site]]);

    $array = array(
        'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
        'body' => json_encode(
                array(
                    'site' => $site,
                    'signature' => $signature,
                    'time' => $time,
                    'ip' => $ip,
                    'slug' => FIFU_CLIENT,
                    'version' => fifu_version_number()
                )
        ),
        'method' => 'POST',
        'data_format' => 'body',
        'blocking' => true,
        'timeout' => 30,
    );
    $response = fifu_remote_post(FIFU_SU_ADDRESS . '/list-daily-count/', $array);
    if (is_wp_error($response))
        return json_decode(fifu_try_again_later());

    // offline
    if (($response['http_response']->get_response_object()->status_code ?? 0) == 404)
        return json_decode(fifu_try_again_later());

    $json = json_decode($response['http_response']->get_response_object()->body ?? '');
    return $json;
}

function fifu_api_cloud_upload_auto(WP_REST_Request $request) {
    if (!fifu_su_sign_up_complete())
        return json_decode(FIFU_NO_CREDENTIALS);

    $email = fifu_su_get_email();
    $site = fifu_get_home_url();
    $ip = fifu_get_ip();
    $time = time();
    $signature = fifu_create_signature($site . $email . $time . $ip);

    $enabled = $request['toggle'] == 'toggleon';

    fifu_cloud_log(['cloud_upload_auto' => ['site' => $site]]);

    $array = array(
        'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
        'body' => json_encode(
                array(
                    'site' => $site,
                    'email' => $email,
                    'signature' => $signature,
                    'time' => $time,
                    'ip' => $ip,
                    'enabled' => $enabled,
                    'slug' => FIFU_CLIENT,
                    'version' => fifu_version_number()
                )
        ),
        'method' => 'POST',
        'data_format' => 'body',
        'blocking' => true,
        'timeout' => 30,
    );

    $response = fifu_remote_post(FIFU_SU_ADDRESS . '/upload-auto/', $array);
    if (is_wp_error($response))
        return json_decode(fifu_try_again_later());

    $json = json_decode($response['http_response']->get_response_object()->body ?? '');
    $upload_auto_code = $json->upload_auto_code ?? null;

    if ($enabled)
        update_option('fifu_cloud_upload_auto_code', array($upload_auto_code));
    else
        delete_option('fifu_cloud_upload_auto_code');

    return $json;
}

function fifu_api_cloud_delete_auto(WP_REST_Request $request) {
    if (!fifu_su_sign_up_complete())
        return json_decode(FIFU_NO_CREDENTIALS);

    $email = fifu_su_get_email();
    $site = fifu_get_home_url();
    $ip = fifu_get_ip();
    $time = time();
    $signature = fifu_create_signature($site . $email . $time . $ip);

    $enabled = $request['toggle'] == 'toggleon';

    fifu_cloud_log(['cloud_delete_auto' => ['site' => $site]]);

    $array = array(
        'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
        'body' => json_encode(
                array(
                    'site' => $site,
                    'email' => $email,
                    'signature' => $signature,
                    'time' => $time,
                    'ip' => $ip,
                    'enabled' => $enabled,
                    'slug' => FIFU_CLIENT,
                    'version' => fifu_version_number()
                )
        ),
        'method' => 'POST',
        'data_format' => 'body',
        'blocking' => true,
        'timeout' => 30,
    );

    $response = fifu_remote_post(FIFU_SU_ADDRESS . '/delete-auto/', $array);
    if (is_wp_error($response))
        return json_decode(fifu_try_again_later());

    $json = json_decode($response['http_response']->get_response_object()->body ?? '');
    $delete_auto_code = $json->delete_auto_code ?? null;

    if ($enabled)
        update_option('fifu_cloud_delete_auto_code', array($delete_auto_code));
    else
        delete_option('fifu_cloud_delete_auto_code');

    return $json;
}

function fifu_api_cloud_hotlink(WP_REST_Request $request) {
    if (!fifu_su_sign_up_complete())
        return json_decode(FIFU_NO_CREDENTIALS);

    $email = fifu_su_get_email();
    $site = fifu_get_home_url();
    $ip = fifu_get_ip();
    $time = time();
    $signature = fifu_create_signature($site . $email . $time . $ip);

    $enabled = $request['toggle'] == 'toggleon';

    fifu_cloud_log(['cloud_hotlink' => ['site' => $site]]);

    $array = array(
        'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
        'body' => json_encode(
                array(
                    'site' => $site,
                    'email' => $email,
                    'signature' => $signature,
                    'time' => $time,
                    'ip' => $ip,
                    'enabled' => $enabled,
                    'slug' => FIFU_CLIENT,
                    'version' => fifu_version_number()
                )
        ),
        'method' => 'POST',
        'data_format' => 'body',
        'blocking' => true,
        'timeout' => 30,
    );

    $response = fifu_remote_post(FIFU_SU_ADDRESS . '/hotlink/', $array);
    if (is_wp_error($response))
        return json_decode(fifu_try_again_later());

    $json = json_decode($response['http_response']->get_response_object()->body ?? '');

    return $json;
}

function fifu_api_valid_isbn($isbn) {
    $queryParams = http_build_query([
        'site' => fifu_get_home_url(),
        'partial_key' => fifu_partial_key(),
        'isbn' => $isbn,
    ]);
    $workerUrl = "https://valid-isbn.fifu.workers.dev?" . $queryParams;

    $response = wp_remote_get($workerUrl);
    if (is_wp_error($response))
        return false;

    $status_code = wp_remote_retrieve_response_code($response);

    $body = wp_remote_retrieve_body($response);
    $isValid = trim($body) === "true";

    return $isValid;
}

function fifu_api_upload_images(WP_REST_Request $request) {
    $att_ids = array();

    // featured
    $url = esc_url_raw(rtrim($request['url'] ?? ''));
    $alt = wp_strip_all_tags($request['alt'] ?? '');

    // gallery
    $urls = rtrim($request['urls'] ?? '');
    $urls = $urls ? explode('|', $urls) : [];
    $alts = wp_strip_all_tags($request['alts'] ?? '');
    $alts = $alts ? explode('|', $alts) : [];

    $local_url = null;

    $post_id = $request['post_id'] ?? null;
    $meta_box = filter_var($request['meta_box'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $is_category = ($request['taxonomy'] ?? '') == 'product_cat';
    $att_id = fifu_upload_image($post_id, $url, $alt, $is_category);
    if ($att_id) {
        if ($meta_box)
            array_push($att_ids, $att_id);
        else
            $local_url = wp_get_attachment_url($att_id);

        if (!$is_category) {
            delete_post_meta($post_id, 'fifu_image_url');
            delete_post_meta($post_id, 'fifu_image_alt');
            fifu_db_update_fake_attach_id($post_id);
            set_post_thumbnail($post_id, $att_id);
        } else {
            delete_term_meta($post_id, 'fifu_image_url');
            delete_term_meta($post_id, 'fifu_image_alt');
            fifu_db_ctgr_update_fake_attach_id($post_id);
            update_term_meta($post_id, 'thumbnail_id', $att_id);
        }
        // alt
        $alt && update_post_meta($att_id, '_wp_attachment_image_alt', $alt);
        // description
        wp_update_post(array('ID' => $att_id, 'post_content' => $url));
    }

    // gallery
    for ($i = 0; $i < sizeof($urls); $i++) {
        $att_id = fifu_upload_image($post_id, $urls[$i], $alts[$i] ?? '', false);
        if ($att_id)
            array_push($att_ids, (int) $att_id);
    }

    if (!$meta_box) {
        update_post_meta($post_id, '_product_image_gallery', implode(',', $att_ids));
        if (!empty(get_metadata('post', $post_id, 'fifu_list_url'))) {
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

        return json_encode(array('local_url' => $local_url));
    }

    return json_encode(array('att_ids' => $att_ids));
}

function fifu_get_storage_id($hex_id, $width, $height) {
    return $hex_id . '-' . $width . '-' . $height;
}

function fifu_api_list_all_fifu(WP_REST_Request $request) {
    $page = (int) ($request['page'] ?? 0);
    $type = $request['type'] ?? null;
    $keyword = $request['keyword'] ?? null;
    $urls = fifu_db_get_all_urls($page, $type, $keyword);
    foreach ($urls as $url) {
        if (strpos($url->meta_key, 'video') !== false) {
            $url->video_url = $url->url;
            $url->url = fifu_video_img_large($url->url, $url->post_id, $url->category);
        }
    }

    // sanitize output
    if (is_array($urls)) {
        foreach ($urls as $u) {
            if (!is_object($u))
                continue;
            $u->url = esc_url_raw($u->url ?? '');
            $u->video_url = isset($u->video_url) && $u->video_url ? esc_url_raw($u->video_url) : null;
            $u->post_title = sanitize_text_field($u->post_title ?? '');
            $u->post_name = sanitize_text_field($u->post_name ?? '');
            $u->post_date = sanitize_text_field($u->post_date ?? '');
            $u->meta_key = sanitize_text_field($u->meta_key ?? '');
            $u->meta_id = intval($u->meta_id ?? 0);
            $u->post_id = intval($u->post_id ?? 0);
            // ensure boolean-ish as int (0/1)
            $u->category = isset($u->category) ? (int) (!!$u->category) : 0;
        }
    }

    return $urls;
}

function fifu_api_list_all_media_library(WP_REST_Request $request) {
    if (!fifu_su_sign_up_complete())
        return null;

    $page = (int) ($request['page'] ?? 0);
    $type = $request['type'] ?? null;
    $keyword = $request['keyword'] ?? null;
    $results = fifu_db_get_posts_with_internal_featured_image($page, $type, $keyword);

    // sanitize output
    if (is_array($results)) {
        foreach ($results as $r) {
            if (!is_object($r))
                continue;
            $r->url = esc_url_raw($r->url ?? '');
            $r->post_title = sanitize_text_field($r->post_title ?? '');
            $r->post_name = sanitize_text_field($r->post_name ?? '');
            $r->post_date = sanitize_text_field($r->post_date ?? '');
            $r->gallery_ids = isset($r->gallery_ids) ? sanitize_text_field($r->gallery_ids) : null;
            $r->post_id = intval($r->post_id ?? 0);
            $r->thumbnail_id = intval($r->thumbnail_id ?? 0);
            $r->category = isset($r->category) ? (int) (!!$r->category) : 0;
        }
    }

    return $results;
}

function fifu_api_convert_to_fifu(WP_REST_Request $request) {
    if (!fifu_su_sign_up_complete())
        return json_encode(array());

    $rows = array();
    $posts = $request['selected'] ?? [];
    $total = count($posts);

    $post_ids = array();
    $term_ids = array();

    foreach ($posts as $post) {
        $post_id = $post[0] ?? null;
        $url = $post[1] ?? null;
        $thumbnail_id = $post[2] ?? null;
        $gallery_ids = $post[3] ?? null;
        $is_category = ($post[4] ?? 0) == 1;

        if (!$url || !$post_id)
            continue;

        if ($is_category)
            array_push($term_ids, $post_id);
        else
            array_push($post_ids, $post_id);
    }

    if ($post_ids)
        fifu_backup_att_ids($post_ids);

    if ($term_ids)
        fifu_ctgr_backup_att_ids($term_ids);

    if ($post_ids) {
        $map = array();
        $results = fifu_db_get_internal_urls($post_ids);
        foreach ($results as $res) {
            $att_id = $res->att_id ?? '';
            $url = $res->url ?? '';
            $map[$att_id] = $url;
        }
    }

    if ($term_ids) {
        $ctgr_map = array();
        $ctgr_results = fifu_db_get_ctgr_internal_urls($term_ids);
        foreach ($ctgr_results as $res) {
            $att_id = $res->att_id ?? '';
            $url = $res->url ?? '';
            $ctgr_map[$att_id] = $url;
        }
    }

    $values = '';
    $ctgr_values = '';
    foreach ($posts as $post) {
        $post_id = $post[0] ?? null;
        $url = $post[1] ?? null;
        $thumbnail_id = $post[2] ?? null;
        $gallery_ids = $post[3] ?? null;
        $is_category = ($post[4] ?? 0) == 1;

        if ($is_category) {
            if ($thumbnail_id)
                $ctgr_values .= '(' . $post_id . ', "fifu_image_url", "' . ($ctgr_map[$thumbnail_id] ?? '') . '")';
        } else {
            if ($thumbnail_id)
                $values .= '(' . $post_id . ', "fifu_image_url", "' . ($map[$thumbnail_id] ?? '') . '")';

            if ($gallery_ids) {
                $ids = explode(',', $gallery_ids);
                $i = 0;
                foreach ($ids as $id)
                    $values .= '(' . $post_id . ', "fifu_image_url_' . $i++ . '", "' . ($map[$id] ?? '') . '")';
            }
        }
    }

    if ($values) {
        $values = str_replace(')(', '), (', $values);
        fifu_add_custom_fields($values);
    }

    if ($ctgr_values) {
        $ctgr_values = str_replace(')(', '), (', $ctgr_values);
        fifu_ctgr_add_custom_fields($ctgr_values);
    }

    if ($post_ids && count($post_ids))
        fifu_delete_att_ids($post_ids);

    if ($term_ids && count($term_ids))
        fifu_ctgr_delete_att_ids($term_ids);

    fifu_db_insert_attachment();
    fifu_db_insert_attachment_gallery();
    fifu_db_insert_attachment_category();

    return json_encode(array());
}

function fifu_metadata_counter_api(WP_REST_Request $request) {
    $transient = filter_var($request['transient'], FILTER_VALIDATE_BOOLEAN);
    $total = $transient ? fifu_get_transient('fifu_metadata_counter') : null;
    if (!$total) {
        $total = fifu_db_count_metadata_operations();
        fifu_set_transient('fifu_metadata_counter', $total, 0);
    }
    return $total;
}

function fifu_content_counter_api(WP_REST_Request $request) {
    $transient = filter_var($request['transient'], FILTER_VALIDATE_BOOLEAN);
    $total = $transient ? fifu_get_transient('fifu_content_counter') : null;
    if (!$total) {
        $total = fifu_db_count_content_operations();
        fifu_set_transient('fifu_content_counter', $total, 0);
    }
    return $total;
}

function fifu_enable_fake_api(WP_REST_Request $request) {
    if (fifu_is_on('fifu_cron_metadata')) {
        update_option('fifu_cron_metadata', 'toggleoff', 'no');
        $sub_request = new WP_REST_Request();
        $sub_request->set_param('toggle', 'fifu_toggle_cron_metadata');
        fifu_api_cron_delete($sub_request);
    }

    update_option('fifu_fake_stop', false, 'no');
    fifu_enable_fake();
    return json_encode(array());
}

function fifu_disable_fake_api(WP_REST_Request $request) {
    update_option('fifu_fake_stop', true, 'no');
    return json_encode(array());
}

function fifu_data_clean_api(WP_REST_Request $request) {
    fifu_db_enable_clean();
    update_option('fifu_data_clean', 'toggleoff', 'no');
    fifu_set_author();
    return json_encode(array());
}

function fifu_update_all_api(WP_REST_Request $request) {
    fifu_create_generic_hook('content');
    return json_encode(array());
}

function fifu_run_delete_all_api(WP_REST_Request $request) {
    fifu_db_delete_all();
    update_option('fifu_run_delete_all', 'toggleoff', 'no');
    return json_encode(array());
}

function fifu_check_for_updates_api(WP_REST_Request $request) {
    $key = sanitize_text_field($request->get_param('key'));
    $network = filter_var($request->get_param('network'), FILTER_VALIDATE_BOOLEAN);
    $response = fifu_check_update_url($key, $network);
    return wp_send_json(array('status' => (bool) $response));
}

function fifu_disable_default_api(WP_REST_Request $request) {
    fifu_db_delete_default_url();
    return json_encode(array());
}

function fifu_test_server_api(WP_REST_Request $request) {
    delete_option('fifu_status_server');
    $url = "https://plugin.featuredimagefromurl.com/api/test/";
    $body = json_encode(array(
        'route' => get_rest_url(),
        'transient_key' => fifu_version_number(),
    ));
    $headers = array(
        'Content-Type' => 'application/json',
    );
    $response = wp_remote_post($url, array(
        'method' => 'POST',
        'body' => $body,
        'headers' => $headers,
        'blocking' => true,
        'timeout' => 1,
    ));
    return json_encode(array());
}

function fifu_test_key_api(WP_REST_Request $request) {
    $queryParams = http_build_query([
        'site' => fifu_get_home_url(),
        'partial_key' => fifu_partial_key()
    ]);
    $workerUrl = "https://test-key.fifu.workers.dev?" . $queryParams;

    $response = wp_remote_get($workerUrl);
    if (is_wp_error($response))
        return null;

    $status = wp_remote_retrieve_response_code($response);

    if ($status == 443)
        update_option('fifu_expired', 1, 'no');
    else
        delete_option('fifu_expired');

    return $status;
}

function fifu_api_test_server(WP_REST_Request $request) {
    fifu_plugin_log(['fifu_api_test_server' => []]);
    update_option('fifu_status_server', true, 'no');
    return json_encode(array());
}

function fifu_otfcdn_api(WP_REST_Request $request) {
    // Convert 'toggleon' to a boolean value
    $enabled = $request['toggle'] == 'toggleon';

    // Explicitly convert boolean to 'true' or 'false'
    $queryParams = http_build_query([
        'site' => fifu_get_home_url(),
        'partial_key' => fifu_partial_key(),
        'enabled' => $enabled ? 'true' : 'false', // Ensure it's 'true' or 'false'
    ]);

    $workerUrl = "https://fifu-otfcdn.fifu.workers.dev?" . $queryParams;

    // Make the GET request
    $response = wp_remote_get($workerUrl);
    if (is_wp_error($response)) {
        return false;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    // Return the response
    return json_encode([
        'status_code' => $status_code,
        'response_body' => $body,
    ]);
}

function fifu_none_default_api(WP_REST_Request $request) {
    return json_encode(array());
}

function fifu_load_sizes_api(WP_REST_Request $request) {
    $result = [];
    $detected_sizes = fifu_db_select_option_prefix('fifu_detected_size_');
    foreach ($detected_sizes as $option) {
        $size_name = str_replace('fifu_detected_size_', '', $option->option_name);
        $unserialized_value = maybe_unserialize($option->option_value);
        $defined = get_option("fifu_defined_size_{$size_name}");
        if ($defined) {
            $unserialized_value['w'] = $defined['w'];
            $unserialized_value['h'] = $defined['h'];
            $unserialized_value['c'] = $defined['c'];
        }
        $result[$size_name] = $unserialized_value;
    }
    return $result;
}

function fifu_reset_sizes_api(WP_REST_Request $request) {
    fifu_db_delete_option_prefix('fifu_detected_size_');
    fifu_db_delete_option_prefix('fifu_defined_size_');
    return json_encode(array());
}

function fifu_save_sizes_api(WP_REST_Request $request) {
    $sizes = json_decode($request->get_body(), true);
    foreach ($sizes as $key => $value) {
        if ($value) {
            $transformed = array(
                'w' => $value['width'] ?? 0,
                'h' => $value['height'] ?? 0,
                'c' => $value['crop'] ?? false
            );
            update_option("fifu_defined_size_{$key}", $transformed);
        }
    }
    return json_encode(array());
}

function fifu_rest_url(WP_REST_Request $request) {
    return get_rest_url();
}

function fifu_custom_proxy_handler(WP_REST_Request $request) {
    $aux = get_option(base64_decode("ZmlmdV9rZXk="));
    if (preg_match('/^[\*]+$/', $aux, $values)) {
        $url = $request->get_param('url');
        if (empty($url))
            return new WP_Error('no_url', 'No URL provided', array('status' => 400));
        $url = esc_url_raw($url);
        $response = wp_remote_get($url);
        if (is_wp_error($response))
            return $response;
        return wp_remote_retrieve_body($response);
    }
}

function fifu_search_title(WP_REST_Request $request) {
    $post_id = $request->get_param('post_id');
    $meta_key = 'fifu_auto_set_sent';
    $fail = false;

    // it has already an image
    $att_id = get_post_thumbnail_id($post_id);
    if ($att_id && $att_id != get_option('fifu_default_attach_id'))
        goto finish;

    if (get_post_meta($post_id, $meta_key, true) == null) {
        $fail = true;
        goto finish;
    }

    $post_title = strip_tags(get_the_title($post_id));
    $image = fifu_ddg_search($post_title, $post_id, false);
    if ($image) {
        if (isset($image->url) && $image->url) {
            delete_post_meta($post_id, 'fifu_search'); // legacy
            delete_post_meta($post_id, 'fifu_search_sent'); // legacy
            delete_post_meta($post_id, $meta_key);
            fifu_save_image_data($post_id, $image->url, $image->width, $image->height);
            fifu_update_or_delete($post_id, 'fifu_redirection_url', $image->author_url);
        } else {
            $fail = true;
            goto finish;
        }
    } else {
        $fail = true;
        goto finish;
    }

    finish:

    if ($fail) {
        $attempts = get_post_meta($post_id, $meta_key, true);
        $attempts = is_numeric($attempts) ? (int) $attempts : 0;
        update_post_meta($post_id, $meta_key, $attempts + 1);
    }

    // Ensure attempts is defined as integer for the threshold check
    $attempts = isset($attempts) && is_numeric($attempts) ? (int) $attempts : 0;
    if (!$fail || $attempts >= 5) {
        fifu_delete_register_from_server(array($post_id), 'title');
        delete_post_meta($post_id, $meta_key);
    }

    return new WP_REST_Response('', 200);
}

function fifu_api_finder(WP_REST_Request $request) {
    $post_id = $request->get_param('post_id');
    $meta_key = 'fifu_finder_sent';
    $fail = false;

    if (get_post_meta($post_id, $meta_key, true) == null) {
        $fail = true;
        goto finish;
    }

    $webpage_url = get_post_meta($post_id, 'fifu_finder_url', true);
    if (!$webpage_url) {
        $cf = get_option('fifu_finder_custom_field');
        if ($cf) {
            $webpage_url = get_post_meta($post_id, $cf, true);
            update_post_meta($post_id, 'fifu_finder_url', $webpage_url);
        }
    }

    if (empty($webpage_url)) {
        $fail = true;
        goto finish;
    }

    $find_video = fifu_is_on('fifu_video_finder');

    preg_match('/[^a-z]amazon[.][a-z]+/', $webpage_url, $aux);
    $is_amazon = $aux ? true : preg_match('/amzn[.]to\/\w+/', $webpage_url, $aux);

    if ($is_amazon) {
        $arr_urls = fifu_find_amazon_media($webpage_url, $post_id);
        $url = isset($arr_urls['image']) ? implode('|', $arr_urls['image']) : null;
        $url_video = isset($arr_urls['video']) ? implode('|', $arr_urls['video']) : null;
    } else
        $url = fifu_find_featured_image($webpage_url, $find_video);

    if ($url) {
        if ($find_video && fifu_is_video($url))
            fifu_dev_set_video($post_id, $url);
        else {
            if ($is_amazon) {
                if (fifu_is_off('fifu_amazon_finder')) {
                    $url = explode('|', $url)[0];
                    $url_video = null;
                }
                fifu_dev_set_image_list($post_id, $url);
                if ($url_video)
                    fifu_dev_set_video_list($post_id, $url_video);
            } else
                fifu_save_image_data($post_id, $url, null, null);
        }
    } else {
        $fail = true;
        goto finish;
    }

    finish:

    if ($fail) {
        $attempts = get_post_meta($post_id, $meta_key, true);
        $attempts = is_numeric($attempts) ? (int) $attempts : 0;
        update_post_meta($post_id, $meta_key, $attempts + 1);
    }

    $attempts = isset($attempts) && is_numeric($attempts) ? (int) $attempts : 0;
    if (!$fail || $attempts >= 5) {
        fifu_delete_register_from_server(array($post_id), 'finder');
        delete_post_meta($post_id, $meta_key);
    }

    return new WP_REST_Response('', 200);
}

function fifu_api_tags(WP_REST_Request $request) {
    $post_id = $request->get_param('post_id');
    $meta_key = 'fifu_tags_sent';
    $fail = false;

    if (get_post_meta($post_id, $meta_key, true) == null) {
        $fail = true;
        goto finish;
    }

    $tags = get_the_tags($post_id);
    if (empty($tags)) {
        $fail = true;
        goto finish;
    }

    $tag_list = '';
    $tag_names = array();
    foreach ($tags as $tag)
        $tag_names[] = $tag->name;
    $tag_list = implode(',', $tag_names);

    $partialKey = fifu_partial_key();
    $homeUrl = fifu_get_home_url();
    $response = wp_safe_remote_get("https://unsplash.fifu.workers.dev?partial_key={$partialKey}&site={$homeUrl}&keywords={$tag_list}");

    if (is_wp_error($response)) {
        $fail = true;
        goto finish;
    }

    $data = json_decode($response['body'] ?? '', true);

    $url = null;
    if (isset($data['results']) && is_array($data['results'])) {
        $url = '';
        foreach ($data['results'] as $result) {
            // If 'plus' is not set or equals 0 (meaning "false")
            if (empty($result['plus'] ?? 0)) {
                $url = $result['urls']['full'] ?? '';
                break;
            }
        }
    }

    if ($url) {
        $imageSize = getImageSize($url);
        $width = $imageSize[0] ?? 0;
        $height = $imageSize[1] ?? 0;
        fifu_save_image_data($post_id, $url, $width, $height);
    } else {
        $fail = true;
        goto finish;
    }

    finish:

    if ($fail) {
        $attempts = get_post_meta($post_id, $meta_key, true);
        $attempts = is_numeric($attempts) ? (int) $attempts : 0;
        update_post_meta($post_id, $meta_key, $attempts + 1);
    }

    $attempts = isset($attempts) && is_numeric($attempts) ? (int) $attempts : 0;
    if (!$fail || $attempts >= 5) {
        fifu_delete_register_from_server(array($post_id), 'tags');
        delete_post_meta($post_id, $meta_key);
    }

    return new WP_REST_Response('', 200);
}

function fifu_api_isbn(WP_REST_Request $request) {
    $post_id = $request->get_param('post_id');
    $meta_key = 'fifu_isbn_sent';
    $fail = false;

    if (get_post_meta($post_id, $meta_key, true) == null) {
        $fail = true;
        goto finish;
    }

    $isbn = get_post_meta($post_id, 'fifu_isbn', true);
    if (!$isbn) {
        $cf = get_option('fifu_isbn_custom_field');
        if ($cf) {
            $isbn = get_post_meta($post_id, $cf, true);
            if ($isbn)
                update_post_meta($post_id, 'fifu_isbn', $isbn);
        }
    }

    if (strpos($isbn, 'not-found') !== false || strpos($isbn, 'invalid') !== false || empty($isbn)) {
        $fail = true;
        goto finish;
    }

    if (!fifu_api_valid_isbn($isbn)) {
        update_post_meta($post_id, 'fifu_isbn', 'invalid:' . $isbn);
        $fail = true;
        goto finish;
    }

    $image_url = fifu_isbn_search($isbn);
    if ($image_url) {
        fifu_save_image_data($post_id, $image_url, null, null);
    } else {
        update_post_meta($post_id, 'fifu_isbn', 'not-found:' . $isbn);
        $fail = true;
        goto finish;
    }

    finish:

    if ($fail) {
        $attempts = get_post_meta($post_id, $meta_key, true);
        $attempts = is_numeric($attempts) ? (int) $attempts : 0;
        update_post_meta($post_id, $meta_key, $attempts + 1);
    }

    $attempts = isset($attempts) && is_numeric($attempts) ? (int) $attempts : 0;
    if (!$fail || $attempts >= 5) {
        fifu_delete_register_from_server(array($post_id), 'isbn');
        delete_post_meta($post_id, $meta_key);
    }

    return new WP_REST_Response('', 200);
}

function fifu_api_asin(WP_REST_Request $request) {
    $post_id = $request->get_param('post_id');
    $meta_key = 'fifu_asin_sent';
    $fail = false;

    if (get_post_meta($post_id, $meta_key, true) == null) {
        $fail = true;
        goto finish;
    }

    $asin = get_post_meta($post_id, 'fifu_asin', true);
    if (!$asin) {
        $cf = get_option('fifu_asin_custom_field');
        if ($cf) {
            $asin = get_post_meta($post_id, $cf, true);
            if ($asin)
                update_post_meta($post_id, 'fifu_asin', $asin);
        }
    }

    if (strpos($asin, 'not-found') !== false || empty($asin)) {
        $fail = true;
        goto finish;
    }

    $data = fifu_asin_search($asin);
    if ($data) {
        $urls = $data['images'] ?? [];
        $link = $data['product_link'] ?? '';

        $urls_str = implode('|', $urls);

        if (get_post_type($post_id) === 'product') {
            fifu_dev_set_image_list($post_id, $urls_str);
        } else {
            update_option('fifu_slider', 'toggleon', 'no');
            fifu_dev_set_slider($post_id, $urls_str, null);
        }

        fifu_update_or_delete($post_id, 'fifu_redirection_url', $link);
    } else {
        update_post_meta($post_id, 'fifu_asin', 'not-found:' . $asin);
        $fail = true;
        goto finish;
    }

    finish:

    if ($fail) {
        $attempts = get_post_meta($post_id, $meta_key, true);
        $attempts = is_numeric($attempts) ? (int) $attempts : 0;
        update_post_meta($post_id, $meta_key, $attempts + 1);
    }

    $attempts = isset($attempts) && is_numeric($attempts) ? (int) $attempts : 0;
    if (!$fail || $attempts >= 5) {
        fifu_delete_register_from_server(array($post_id), 'asin');
        delete_post_meta($post_id, $meta_key);
    }

    return new WP_REST_Response('', 200);
}

function fifu_api_upload_post(WP_REST_Request $request) {
    $post_id = $request->get_param('post_id');
    $meta_key = 'fifu_uploadpost_sent';
    $fail = false;

    if (get_post_meta($post_id, $meta_key, true) == null) {
        $fail = true;
        goto finish;
    }

    $fail = !fifu_upload_post($post_id);

    finish:

    if ($fail) {
        $attempts = get_post_meta($post_id, $meta_key, true);
        $attempts = is_numeric($attempts) ? (int) $attempts : 0;
        update_post_meta($post_id, $meta_key, $attempts + 1);
    }

    $attempts = isset($attempts) && is_numeric($attempts) ? (int) $attempts : 0;
    if (!$fail || $attempts >= 5) {
        fifu_delete_register_from_server(array($post_id), 'uploadpost');
        delete_post_meta($post_id, $meta_key);
    }

    return new WP_REST_Response('', 200);
}

function fifu_api_upload_term(WP_REST_Request $request) {
    $term_id = $request->get_param('post_id');
    $meta_key = 'fifu_uploadterm_sent';
    $fail = false;

    if (get_term_meta($term_id, $meta_key, true) == null) {
        $fail = true;
        goto finish;
    }

    $fail = !fifu_upload_term($term_id);

    finish:

    if ($fail) {
        $attempts = get_term_meta($term_id, $meta_key, true);
        $attempts = is_numeric($attempts) ? (int) $attempts : 0;
        update_term_meta($term_id, $meta_key, $attempts + 1);
    }

    $attempts = isset($attempts) && is_numeric($attempts) ? (int) $attempts : 0;
    if (!$fail || $attempts >= 5) {
        fifu_delete_register_from_server(array($term_id), 'uploadterm');
        delete_term_meta($term_id, $meta_key);
    }

    return new WP_REST_Response('', 200);
}

function fifu_api_metadata_post(WP_REST_Request $request) {
    $post_ids = $request->get_param('post_ids');
    $meta_key = 'fifu_metadatapost_sent';
    $to_delete = [];

    foreach ($post_ids as $post_id) {
        $fail = false;

        if (get_post_meta($post_id, $meta_key, true) == null) {
            $fail = true;
        } else {
            fifu_split_lists($post_id);
            fifu_update_fake_attach_id($post_id);
        }

        if ($fail) {
            $attempts = get_post_meta($post_id, $meta_key, true);
            $attempts = is_numeric($attempts) ? (int) $attempts : 0;
            update_post_meta($post_id, $meta_key, $attempts + 1);
        }

        $attempts = isset($attempts) && is_numeric($attempts) ? (int) $attempts : 0;
        if (!$fail || $attempts >= 5) {
            $to_delete[] = $post_id;
            delete_post_meta($post_id, $meta_key);
        }
    }

    if (!empty($to_delete))
        fifu_delete_register_from_server($to_delete, 'metadatapost');

    return new WP_REST_Response('', 200);
}

function fifu_api_metadata_term(WP_REST_Request $request) {
    $term_ids = $request->get_param('post_ids');
    $meta_key = 'fifu_metadataterm_sent';
    $to_delete = [];

    foreach ($term_ids as $term_id) {
        $fail = false;

        if (get_term_meta($term_id, $meta_key, true) == null) {
            $fail = true;
        } else {
            fifu_db_ctgr_update_fake_attach_id($term_id);
        }

        if ($fail) {
            $attempts = get_term_meta($term_id, $meta_key, true);
            $attempts = is_numeric($attempts) ? (int) $attempts : 0;
            update_term_meta($term_id, $meta_key, $attempts + 1);
        }

        $attempts = isset($attempts) && is_numeric($attempts) ? (int) $attempts : 0;
        if (!$fail || $attempts >= 5) {
            $to_delete[] = $term_id;
            delete_term_meta($term_id, $meta_key);
        }
    }

    if (!empty($to_delete))
        fifu_delete_register_from_server($to_delete, 'metadataterm');

    return new WP_REST_Response('', 200);
}

function fifu_api_import_post(WP_REST_Request $request) {
    $post_ids = $request->get_param('post_ids');
    $meta_key = 'fifu_importpost_sent';
    $valid_ids = fifu_db_get_valid_post_ids_import($post_ids, $meta_key);
    if ($valid_ids)
        fifu_db_import($valid_ids, $meta_key, false);
    $to_delete = [];

    foreach ($post_ids as $post_id) {
        $fail = !$valid_ids || !in_array($post_id, $valid_ids);

        if ($fail) {
            $attempts = get_post_meta($post_id, $meta_key, true);
            $attempts = is_numeric($attempts) ? (int) $attempts : 0;
            update_post_meta($post_id, $meta_key, $attempts + 1);
        }

        $attempts = isset($attempts) && is_numeric($attempts) ? (int) $attempts : 0;
        if (!$fail || $attempts >= 5) {
            $to_delete[] = $post_id;
            if ($fail)
                delete_post_meta($post_id, $meta_key); // otherwise, already deleted after import
        }
    }

    $request = new WP_REST_Request();
    $request->set_param('toggle', 'fifu_toggle_importpost');
    fifu_api_cron_add($request);

    fifu_delete_register_from_server($to_delete, 'importpost');

    return new WP_REST_Response('', 200);
}

function fifu_api_import_term(WP_REST_Request $request) {
    $term_ids = $request->get_param('post_ids');
    $meta_key = 'fifu_importterm_sent';
    $valid_ids = fifu_db_get_valid_term_ids_import($term_ids, $meta_key);
    if ($valid_ids)
        fifu_db_import($valid_ids, $meta_key, true);
    $to_delete = [];

    foreach ($term_ids as $term_id) {
        $fail = !$valid_ids || !in_array($term_id, $valid_ids);

        if ($fail) {
            $attempts = get_term_meta($term_id, $meta_key, true);
            $attempts = is_numeric($attempts) ? (int) $attempts : 0;
            update_term_meta($term_id, $meta_key, $attempts + 1);
        }

        $attempts = isset($attempts) && is_numeric($attempts) ? (int) $attempts : 0;
        if (!$fail || $attempts >= 5) {
            $to_delete[] = $term_id;
            if ($fail)
                delete_term_meta($term_id, $meta_key); // otherwise, already deleted after import
        }
    }

    $request = new WP_REST_Request();
    $request->set_param('toggle', 'fifu_toggle_importterm');
    fifu_api_cron_add($request);

    fifu_delete_register_from_server($to_delete, 'importterm');

    return new WP_REST_Response('', 200);
}

function fifu_api_meta_in(WP_REST_Request $request) {
    $service = 'metain';
    $id = $request->get_param('post_id');

    $type = fifu_db_get_type_meta_in($id);
    switch ($type) {
        case "post":
            fifu_db_insert_postmeta($id);
            break;
        case "woo":
            fifu_db_insert_woometa($id);
            break;
        case "term":
            fifu_db_insert_termmeta($id);
            break;
    }

    fifu_delete_register_from_server(array($id), $service);

    $total = fifu_db_count_metadata_operations();

    fifu_next_register_from_server($service);
    fifu_set_transient('fifu_metadata_counter', $total, 0);

    return new WP_REST_Response('', 200);
}

function fifu_api_meta_out(WP_REST_Request $request) {
    $service = 'metaout';
    $id = $request->get_param('post_id');

    $type = fifu_db_get_type_meta_out($id);
    switch ($type) {
        case "att":
            fifu_db_delete_attmeta($id);
            break;
        case "woo":
            fifu_db_delete_woometa($id);
            break;
        case "term":
            fifu_db_delete_termmeta($id);
            break;
    }

    fifu_delete_register_from_server(array($id), $service);

    $total = fifu_db_count_metadata_operations();

    fifu_next_register_from_server($service);
    fifu_set_transient('fifu_metadata_counter', $total, 0);

    return new WP_REST_Response('', 200);
}

function fifu_api_content(WP_REST_Request $request) {
    $service = 'content';
    $id = $request->get_param('post_id');

    fifu_db_insert_content($id);

    fifu_delete_register_from_server(array($id), $service);

    $total = fifu_db_count_content_operations();

    fifu_next_register_from_server($service);
    fifu_set_transient('fifu_content_counter', $total, 0);

    return new WP_REST_Response('', 200);
}

function fifu_delete_register_from_server($post_ids, $service) {
    fifu_plugin_log(['fifu-delete-register-from-server' => ['SERVICE' => $service, 'COUNT' => count($post_ids),]]);
    $url = "https://plugin.featuredimagefromurl.com/api/delete/{$service}/";
    $body = json_encode(array(
        'post_ids' => $post_ids,
        'route' => get_rest_url(),
    ));
    $headers = array(
        'Content-Type' => 'application/json',
        'Authorization' => get_option('fifu_ws_key_ddg'),
    );
    $response = wp_remote_post($url, array(
        'method' => 'POST',
        'body' => $body,
        'headers' => $headers,
        'blocking' => true,
        'timeout' => 0.001,
    ));
}

function fifu_next_register_from_server($service) {
    $url = "https://plugin.featuredimagefromurl.com/api/next/{$service}/";
    $body = json_encode(array(
        'route' => get_rest_url(),
    ));
    $headers = array(
        'Content-Type' => 'application/json',
        'Authorization' => get_option('fifu_ws_key_ddg'),
    );
    $response = wp_remote_post($url, array(
        'method' => 'POST',
        'body' => $body,
        'headers' => $headers,
        'blocking' => true,
        'timeout' => 0.001,
    ));
}

function fifu_api_video_image_thumbnail(WP_REST_Request $request) {
    $video_url = $request['url'] ?? '';
    $image_url = fifu_video_img_large($video_url, null, null);
    $response = new WP_REST_Response(['image_url' => $image_url]);
    $response->set_status(200);
    $response->header('Content-Type', 'application/json');
    return $response;
}

function fifu_api_video_src(WP_REST_Request $request) {
    $video_url = $request['url'] ?? '';
    $video_src = fifu_video_src($video_url);
    return $video_src;
}

function fifu_api_ddg_search(WP_REST_Request $request) {
    $keywords = $request['keywords'] ?? null;
    if (!$keywords) {
        $post_id = $request['post_id'] ?? null;
        $is_category = $request['is_ctgr'] ?? false;
        if ($is_category) {
            $category = get_term($post_id, 'product_cat');
            $keywords = $category->name ?? '';
        } else {
            $keywords = strip_tags(get_the_title($post_id));
        }
    }
    $results = fifu_ddg_search($keywords, null, true);
    return $results;
}

function fifu_api_pre_deactivate(WP_REST_Request $request) {
    fifu_db_enable_clean();

    $total = fifu_db_count_metadata_operations();
    fifu_set_transient('fifu_metadata_counter', $total, 0);
    while ($total > 0) {
        wp_cache_flush();
        $total = fifu_get_transient('fifu_metadata_counter');
        sleep(3);
    }

    deactivate_plugins('fifu-premium/fifu-premium.php');
    return json_encode(array());
}

function fifu_api_quick_edit_save(WP_REST_Request $request) {
    $post_id = $request['post_id'] ?? null;
    $is_ctgr = $request['is_ctgr'] ?? null;
    $width = $request['width'] ?? null;
    $height = $request['height'] ?? null;

    $gallery_length = intval($request['gallery_length'] ?? 0);
    $gallery_urls = $request['gallery_urls'] ?? null;
    $gallery_alts = $request['gallery_alts'] ?? null;
    $gallery_ifms = $request['gallery_ifms'] ?? null;

    $gallery_video_length = intval($request['gallery_video_length'] ?? 0);
    $gallery_video_urls = $request['gallery_video_urls'] ?? null;

    $slider_length = intval($request['slider_length'] ?? 0);
    $slider_urls = $request['slider_urls'] ?? null;
    $slider_alts = $request['slider_alts'] ?? null;

    $image_url = $request['image_url'] ?? null;
    if ($is_ctgr) {
        $term_id = $post_id;
        fifu_save_ctgr_image_data($term_id, $image_url, $width, $height);
    } else
        fifu_save_image_data($post_id, $image_url, $width, $height);

    $video_url = $request['video_url'] ?? null;
    $video_thumb_url = $request['video_thumb_url'] ?? null;
    if (fifu_is_custom_video($video_url)) {
        if ($is_ctgr) {
            $term_id = $post_id;
            fifu_update_or_delete_ctgr($term_id, 'fifu_custom_video_url', $video_url);
        } else
            fifu_update_or_delete($post_id, 'fifu_custom_video_url', $video_url);
    } else {
        if ($is_ctgr) {
            $term_id = $post_id;
            fifu_dev_set_category_video($term_id, $video_url);
            $att_id = get_term_meta($term_id, 'thumbnail_id', true);
            fifu_update_or_delete_ctgr($term_id, 'fifu_custom_video_url', '');
        } else {
            fifu_dev_set_video($post_id, $video_url);
            $att_id = get_post_thumbnail_id($post_id);
            fifu_update_or_delete($post_id, 'fifu_custom_video_url', '');
        }
        if ($att_id && $video_url && $video_thumb_url) {
            fifu_save_dimensions($att_id, $width, $height);
            if (fifu_is_youtube_video($video_url))
                fifu_update_youtube_dimensions($att_id, $video_thumb_url);
        }
    }

    if ($video_url) {
        $image_url = fifu_video_img_large($video_url, null, null);
        if (fifu_is_custom_video($video_url)) {
            if ($is_ctgr) {
                $term_id = $post_id;
                $image_url = get_term_meta($term_id, 'fifu_image_url', true);
            } else
                $image_url = fifu_main_image_url($post_id, true);
        }
    }

    /* image product gallery */
    if ($gallery_length) {
        // delete all custom fields
        for ($i = 0; $i < $gallery_length; $i++) {
            delete_post_meta($post_id, 'fifu_image_url_' . $i);
            delete_post_meta($post_id, 'fifu_image_alt_' . $i);
            delete_post_meta($post_id, 'fifu_image_ifm_' . $i);
        }
        // add custom fields
        $i = 0;
        foreach ($gallery_urls ?? [] as $url) {
            $url = esc_url_raw(rtrim($url));
            $alt = wp_strip_all_tags($gallery_alts[$i] ?? '');
            $ifm = esc_url_raw($gallery_ifms[$i] ?? '');
            fifu_update_or_delete($post_id, 'fifu_image_url_' . $i, $url);
            fifu_update_or_delete_value($post_id, 'fifu_image_alt_' . $i, $alt);
            fifu_update_or_delete_value($post_id, 'fifu_image_ifm_' . $i, $ifm);
            $i++;
        }
        fifu_update_fake_attach_id($post_id);
    }

    /* video product gallery */
    if ($gallery_video_length) {
        // delete all custom fields
        for ($i = 0; $i < $gallery_video_length; $i++) {
            delete_post_meta($post_id, 'fifu_video_url_' . $i);
        }
        // add custom fields
        $i = 0;
        foreach ($gallery_video_urls ?? [] as $url) {
            $url = esc_url_raw(rtrim($url));
            fifu_update_or_delete($post_id, 'fifu_video_url_' . $i, $url);
            $i++;
        }
        fifu_update_fake_attach_id($post_id);
    }

    /* featured slider */
    if ($slider_length) {
        // delete all custom fields
        for ($i = 0; $i < $slider_length; $i++) {
            delete_post_meta($post_id, 'fifu_slider_image_url_' . $i);
            delete_post_meta($post_id, 'fifu_slider_image_alt_' . $i);
        }
        // add custom fields
        $i = 0;
        foreach ($slider_urls ?? [] as $url) {
            $url = esc_url_raw(rtrim($url));
            $alt = wp_strip_all_tags($slider_alts[$i] ?? '');
            fifu_update_or_delete($post_id, 'fifu_slider_image_url_' . $i, $url);
            fifu_update_or_delete_value($post_id, 'fifu_slider_image_alt_' . $i, $alt);
            $i++;
        }
        fifu_update_fake_attach_id($post_id);

        if (fifu_is_houzez_active()) {
            wp_cache_flush();
            delete_post_meta($post_id, 'fifu_houzez_urls');
            delete_post_meta($post_id, 'fave_property_images');
            foreach (explode(',', get_post_meta($post_id, '_product_image_gallery', true)) as $att_id)
                add_post_meta($post_id, 'fave_property_images', $att_id, false);
        } elseif (fifu_is_wpresidence_active()) {
            wp_cache_flush();
            $gallery = get_post_meta($post_id, '_product_image_gallery', true);
            $thumbnail_id = get_post_meta($post_id, '_thumbnail_id', true);
            $ids = $thumbnail_id ? array_merge([$thumbnail_id], explode(',', $gallery)) : explode(',', $gallery);
            update_post_meta($post_id, 'wpestate_property_gallery', array_combine(range(1, count($ids)), $ids));
        }

        if (!$image_url)
            return json_encode(array('thumb_url' => $slider_urls[0] ?? ''));
    }

    if (class_exists('\Distributor\Connections')) {
        wp_update_post(array('ID' => $post_id));
    }

    return json_encode(array('thumb_url' => $image_url));
}

function fifu_test_execution_time() {
    $start_time = microtime(true);
    for ($i = 0; $i <= 120; $i++) {
        error_log($i);
        sleep(1);
        //flush();
    }
    error_log(number_format(microtime(true) - $start_time, 4));
    return json_encode(array());
}

add_action('rest_api_init', function () {
    register_rest_route('fifu-premium/v2', '/metadata_counter_api/', array(
        'methods' => 'POST',
        'callback' => 'fifu_metadata_counter_api',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('fifu-premium/v2', '/content_counter_api/', array(
        'methods' => 'POST',
        'callback' => 'fifu_content_counter_api',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('fifu-premium/v2', '/enable_fake_api/', array(
        'methods' => 'POST',
        'callback' => 'fifu_enable_fake_api',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('fifu-premium/v2', '/disable_fake_api/', array(
        'methods' => 'POST',
        'callback' => 'fifu_disable_fake_api',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('fifu-premium/v2', '/data_clean_api/', array(
        'methods' => 'POST',
        'callback' => 'fifu_data_clean_api',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('fifu-premium/v2', '/update_all_api/', array(
        'methods' => 'POST',
        'callback' => 'fifu_update_all_api',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('fifu-premium/v2', '/run_delete_all_api/', array(
        'methods' => 'POST',
        'callback' => 'fifu_run_delete_all_api',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('fifu-premium/v2', '/check_for_updates_api/', array(
        'methods' => 'POST',
        'callback' => 'fifu_check_for_updates_api',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('fifu-premium/v2', '/disable_default_api/', array(
        'methods' => 'POST',
        'callback' => 'fifu_disable_default_api',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('fifu-premium/v2', '/none_default_api/', array(
        'methods' => 'POST',
        'callback' => 'fifu_none_default_api',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('fifu-premium/v2', '/load-sizes-api/', array(
        'methods' => 'POST',
        'callback' => 'fifu_load_sizes_api',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('fifu-premium/v2', '/reset-sizes-api/', array(
        'methods' => 'POST',
        'callback' => 'fifu_reset_sizes_api',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('fifu-premium/v2', '/save-sizes-api/', array(
        'methods' => 'POST',
        'callback' => 'fifu_save_sizes_api',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('fifu-premium/v2', '/otfcdn-api/', array(
        'methods' => 'POST',
        'callback' => 'fifu_otfcdn_api',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('fifu-premium/v2', '/video_image_thumbnail/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_video_image_thumbnail',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('fifu-premium/v2', '/video_src/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_video_src',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('fifu-premium/v2', '/ddg_search/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_ddg_search',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('fifu-premium/v2', '/upload_images/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_upload_images',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('fifu-premium/v2', '/quick_edit_save_api/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_quick_edit_save',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('fifu-premium/v2', '/pre_deactivate/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_pre_deactivate',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('fifu-premium/v2', '/rest_url_api/', array(
        'methods' => ['GET', 'POST'],
        'callback' => 'fifu_rest_url',
        'permission_callback' => 'fifu_public_permission',
    ));
    register_rest_route('fifu-premium/v2', '/custom-proxy-handler/', array(
        'methods' => 'GET',
        'callback' => 'fifu_custom_proxy_handler',
        'permission_callback' => 'fifu_public_permission',
    ));
    register_rest_route('fifu-premium/v2', '/search-title/', array(
        'methods' => 'POST',
        'callback' => 'fifu_search_title',
        'permission_callback' => function ($request) {
            $token = $request->get_header('X-FIFU-Authorization');
            return fifu_is_on('fifu_auto_set') && $token === get_option('fifu_ws_key_ddg');
        },
        'args' => array(
            'post_id' => array(
                'required' => true,
                'validate_callback' => function ($param, $request, $key) {
                    return is_numeric($param);
                }
            ),
        ),
    ));
    register_rest_route('fifu-premium/v2', '/finder/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_finder',
        'permission_callback' => function ($request) {
            $token = $request->get_header('X-FIFU-Authorization');
            return fifu_is_on('fifu_finder') && $token === get_option('fifu_ws_key_ddg');
        },
        'args' => array(
            'post_id' => array(
                'required' => true,
                'validate_callback' => function ($param, $request, $key) {
                    return is_numeric($param);
                }
            ),
        ),
    ));
    register_rest_route('fifu-premium/v2', '/tags/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_tags',
        'permission_callback' => function ($request) {
            $token = $request->get_header('X-FIFU-Authorization');
            return fifu_is_on('fifu_tags') && $token === get_option('fifu_ws_key_ddg');
        },
        'args' => array(
            'post_id' => array(
                'required' => true,
                'validate_callback' => function ($param, $request, $key) {
                    return is_numeric($param);
                }
            ),
        ),
    ));
    register_rest_route('fifu-premium/v2', '/isbn/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_isbn',
        'permission_callback' => function ($request) {
            $token = $request->get_header('X-FIFU-Authorization');
            return fifu_is_on('fifu_isbn') && $token === get_option('fifu_ws_key_ddg');
        },
        'args' => array(
            'post_id' => array(
                'required' => true,
                'validate_callback' => function ($param, $request, $key) {
                    return is_numeric($param);
                }
            ),
        ),
    ));
    register_rest_route('fifu-premium/v2', '/asin/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_asin',
        'permission_callback' => function ($request) {
            $token = $request->get_header('X-FIFU-Authorization');
            return fifu_is_on('fifu_asin') && $token === get_option('fifu_ws_key_ddg');
        },
        'args' => array(
            'post_id' => array(
                'required' => true,
                'validate_callback' => function ($param, $request, $key) {
                    return is_numeric($param);
                }
            ),
        ),
    ));
    register_rest_route('fifu-premium/v2', '/upload-post/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_upload_post',
        'permission_callback' => function ($request) {
            $token = $request->get_header('X-FIFU-Authorization');
            return fifu_is_on('fifu_upload_job') && $token === get_option('fifu_ws_key_ddg');
        },
        'args' => array(
            'post_id' => array(
                'required' => true,
                'validate_callback' => function ($param, $request, $key) {
                    return is_numeric($param);
                }
            ),
        ),
    ));
    register_rest_route('fifu-premium/v2', '/upload-term/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_upload_term',
        'permission_callback' => function ($request) {
            $token = $request->get_header('X-FIFU-Authorization');
            return fifu_is_on('fifu_upload_job') && $token === get_option('fifu_ws_key_ddg');
        },
        'args' => array(
            'post_id' => array(
                'required' => true,
                'validate_callback' => function ($param, $request, $key) {
                    return is_numeric($param);
                }
            ),
        ),
    ));
    register_rest_route('fifu-premium/v2', '/metadata-post/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_metadata_post',
        'permission_callback' => function ($request) {
            $token = $request->get_header('X-FIFU-Authorization');
            return fifu_is_on('fifu_cron_metadata') && $token === get_option('fifu_ws_key_ddg');
        },
        'args' => array(
            'post_ids' => array(
                'required' => true,
                'validate_callback' => function ($param, $request, $key) {
                    if (is_array($param)) {
                        foreach ($param as $id) {
                            if (!is_numeric($id)) {
                                return false;
                            }
                        }
                        return true;
                    }
                    return false;
                }
            ),
        ),
    ));
    register_rest_route('fifu-premium/v2', '/metadata-term/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_metadata_term',
        'permission_callback' => function ($request) {
            $token = $request->get_header('X-FIFU-Authorization');
            return fifu_is_on('fifu_cron_metadata') && $token === get_option('fifu_ws_key_ddg');
        },
        'args' => array(
            'post_ids' => array(
                'required' => true,
                'validate_callback' => function ($param, $request, $key) {
                    if (is_array($param)) {
                        foreach ($param as $id) {
                            if (!is_numeric($id)) {
                                return false;
                            }
                        }
                        return true;
                    }
                    return false;
                }
            ),
        ),
    ));
    register_rest_route('fifu-premium/v2', '/import-post/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_import_post',
        'permission_callback' => function ($request) {
            $token = $request->get_header('X-FIFU-Authorization');
            return (class_exists('WooCommerce') || fifu_is_wp_all_import_active()) && $token === get_option('fifu_ws_key_ddg');
        },
        'args' => array(
            'post_ids' => array(
                'required' => true,
                'validate_callback' => function ($param, $request, $key) {
                    if (is_array($param)) {
                        foreach ($param as $id) {
                            if (!is_numeric($id)) {
                                return false;
                            }
                        }
                        return true;
                    }
                    return false;
                }
            ),
        ),
    ));
    register_rest_route('fifu-premium/v2', '/import-term/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_import_term',
        'permission_callback' => function ($request) {
            $token = $request->get_header('X-FIFU-Authorization');
            return (class_exists('WooCommerce') || fifu_is_wp_all_import_active()) && $token === get_option('fifu_ws_key_ddg');
        },
        'args' => array(
            'post_ids' => array(
                'required' => true,
                'validate_callback' => function ($param, $request, $key) {
                    if (is_array($param)) {
                        foreach ($param as $id) {
                            if (!is_numeric($id)) {
                                return false;
                            }
                        }
                        return true;
                    }
                    return false;
                }
            ),
        ),
    ));
    register_rest_route('fifu-premium/v2', '/metain/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_meta_in',
        'permission_callback' => function ($request) {
            fifu_plugin_log(['/metain/' => ['checking...' => 'permission',]]);
            $token = $request->get_header('X-FIFU-Authorization');
            $authorized = ($token === get_option('fifu_ws_key_ddg'));
            fifu_plugin_log(['/metain/' => ['authorized...' => $authorized,]]);
            return $authorized;
        },
        'args' => array(
            'post_id' => array(
                'required' => true,
                'validate_callback' => function ($param, $request, $key) {
                    fifu_plugin_log(['/metain/' => ['validating...' => 'data',]]);
                    return is_numeric($param);
                }
            ),
        ),
    ));
    register_rest_route('fifu-premium/v2', '/metaout/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_meta_out',
        'permission_callback' => function ($request) {
            fifu_plugin_log(['/metaout/' => ['checking...' => 'permission',]]);
            $token = $request->get_header('X-FIFU-Authorization');
            $authorized = ($token === get_option('fifu_ws_key_ddg'));
            fifu_plugin_log(['/metaout/' => ['authorized...' => $authorized,]]);
            return $authorized;
        },
        'args' => array(
            'post_id' => array(
                'required' => true,
                'validate_callback' => function ($param, $request, $key) {
                    fifu_plugin_log(['/metaout/' => ['validating...' => 'data',]]);
                    return is_numeric($param);
                }
            ),
        ),
    ));
    register_rest_route('fifu-premium/v2', '/content/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_content',
        'permission_callback' => function ($request) {
            $token = $request->get_header('X-FIFU-Authorization');
            return $token === get_option('fifu_ws_key_ddg');
        },
        'args' => array(
            'post_id' => array(
                'required' => true,
                'validate_callback' => function ($param, $request, $key) {
                    return is_numeric($param);
                }
            ),
        ),
    ));
    register_rest_route('fifu-premium/v2', '/test-server/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_test_server',
        'permission_callback' => function ($request) {
            $token = $request->get_header('X-FIFU-Authorization');
            return $token === fifu_version_number();
        },
    ));
    register_rest_route('fifu-premium/v2', '/test_server_api/', array(
        'methods' => 'POST',
        'callback' => 'fifu_test_server_api',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('fifu-premium/v2', '/test_key_api/', array(
        'methods' => 'POST',
        'callback' => 'fifu_test_key_api',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));

    register_rest_route('fifu-premium/v1', '/url/(?P<post_id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'fifu_api_image_url',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('fifu-premium/v2', '/create_thumbnails_list/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_create_thumbnails_list',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('fifu-premium/v2', '/sign_up/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_sign_up',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('fifu-premium/v2', '/connected/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_connected',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('fifu-premium/v2', '/reset_credentials/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_reset_credentials',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('fifu-premium/v2', '/list_all_su/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_list_all_su',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('fifu-premium/v2', '/list_all_fifu/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_list_all_fifu',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('fifu-premium/v2', '/list_all_media_library/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_list_all_media_library',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('fifu-premium/v2', '/list_daily_count/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_list_daily_count',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('fifu-premium/v2', '/delete/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_delete',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('fifu-premium/v2', '/cancel/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_cancel',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('fifu-premium/v2', '/payment_info/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_payment_info',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('fifu-premium/v2', '/convert_to_fifu/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_convert_to_fifu',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('fifu-premium/v2', '/cloud_upload_auto/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_cloud_upload_auto',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('fifu-premium/v2', '/cloud_delete_auto/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_cloud_delete_auto',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('fifu-premium/v2', '/cloud_hotlink/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_cloud_hotlink',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
});

function fifu_get_private_data_permissions_check() {
    if (!current_user_can('manage_options')) {
        return new WP_Error('rest_forbidden', __('Private'), array('status' => 401));
    }
    return true;
}

function fifu_public_permission() {
    return true;
}

/* plugin: wp-force-plugin */

add_filter('rest_authentication_errors', function ($result) {
    if (!empty($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/wp-json/fifu-premium/') !== false)
        return true;
    return $result;
}, 5);

