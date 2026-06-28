<?php

class FifuDdg {

    function get_image_url($keywords, $post_id, $many) {
        $width = get_option('fifu_auto_set_width');
        $height = get_option('fifu_auto_set_height');
        $blocklist = get_option('fifu_auto_set_blocklist');
        $blocklist_arr = $blocklist ? explode(PHP_EOL, $blocklist) : null;
        $sources = str_replace(' ', '', get_option('fifu_auto_set_source'));
        $layout = get_option('fifu_auto_set_layout');

        $site_url = get_site_url();
        $url_components = parse_url($site_url);
        $domain = $url_components['host'] ?? '';

        $new_md5 = md5($this->concatenate_variables($keywords, $width, $height, $blocklist_arr, $sources, $layout));
        $json_arr = $post_id ? get_post_meta($post_id, 'fifu_search_proxy', true) : null;

        if ($json_arr) {
            $arr = json_decode($json_arr, true);
            $old_md5 = $arr[0] ?? null;
            $attempts_proxy = ($old_md5 == $new_md5) ? ($arr[1] ?? 1) : 1;
        } else {
            $attempts_proxy = 1;
        }

        if ($attempts_proxy > 3)
            return null;

        fifu_plugin_log(['fifu-ddg' => ['INFO' => $keywords]]);

        fifu_plugin_log(['fifu-ddg' => ['INFO' => 'Attempts proxy: ' . $attempts_proxy]]);

        $res = $this->get_results($sources, $keywords, $layout, $domain, $blocklist_arr, $width, $height);

        $data = $res ? json_decode($res) : null;

        if (!$data) {
            if ($post_id) {
                $this->update_search_meta($json_arr, $new_md5, $post_id);
            }

            fifu_plugin_log(['fifu-ddg' => ['WARNING' => 'not found']]);
            return null;
        } else {
            if ($post_id)
                delete_post_meta($post_id, 'fifu_search_proxy');

            if (!$many) {
                foreach ($data as $img) {
                    if (fifu_is_valid_image_url($img->url ?? '')) {
                        fifu_plugin_log(['fifu-ddg' => ['INFO' => $img->url]]);
                        return $img;
                    }
                }
            }

            return $data;
        }
    }

    function concatenate_variables($keywords, $width, $height, $blocklist_arr, $sources, $layout) {
        $concatenated_values = $keywords . $width . $height . $sources . $layout;

        if (is_array($blocklist_arr)) {
            foreach ($blocklist_arr as $item) {
                $concatenated_values .= $item;
            }
        }

        return $concatenated_values;
    }

    function update_search_meta($json_arr, $new_md5, $post_id) {
        if ($json_arr) {
            $arr = json_decode($json_arr, true);
            if (is_array($arr) && count($arr) >= 2) {
                $old_md5 = $arr[0] ?? null;
                $attempts = ($old_md5 == $new_md5) ? (($arr[1] ?? 0) + 1) : 1;
            } else {
                $attempts = 1;
            }
        } else {
            $attempts = 1;
        }

        $new_arr = array($new_md5, $attempts);
        $json_arr = json_encode($new_arr);
        update_post_meta($post_id, 'fifu_search_proxy', $json_arr);
    }

    function get_results($sources, $keywords, $layout, $domain, $blocklist_arr, $width, $height) {
        $queryParams = http_build_query([
            'site' => fifu_get_home_url(),
            'partial_key' => fifu_partial_key(),
            'sources' => $sources,
            'keywords' => $keywords,
            'layout' => $layout,
            'domain' => $domain,
            'blocklist' => $blocklist_arr ? implode(',', $blocklist_arr) : '',
            'width' => $width,
            'height' => $height,
        ]);

        $workerEndpoint = 'https://search-engine-image.fifu.app';

        $url = "{$workerEndpoint}?{$queryParams}";

        $response = wp_safe_remote_get($url, array('timeout' => 30));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) != 200) {
            fifu_plugin_log(['fifu-ddg' => ['ERROR' => $response]]);
            return null;
        }

        $body = wp_remote_retrieve_body($response);

        return $body;
    }
}

function fifu_ddg_search($post_title, $post_id, $many) {
    $ddg = new FifuDdg();
    return $ddg->get_image_url($post_title, $post_id, $many);
}

