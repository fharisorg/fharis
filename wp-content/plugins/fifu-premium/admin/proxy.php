<?php

function fifu_proxy_get_list() {
    $private = get_option('fifu_upload_private_proxy');
    if ($private) {
        $list = array();
        preg_match_all("/([^ ,]+[:][^ ,]+[@])*([0-9]{1,3}[.]){3}[0-9]{1,3}[:][0-9]{1,5}/", $private, $matches);
        foreach ($matches[0] as $match) {
            $match = explode('@', $match);

            if (count($match) > 1) {
                // Format: user:pass@ip:port
                list($username, $password) = fifu_parse_proxy_credentials($match[0] ?? '');
                list($proxy, $port) = fifu_parse_proxy_address($match[1] ?? '');
            } else {
                // Format: ip:port
                $username = null;
                $password = null;
                list($proxy, $port) = fifu_parse_proxy_address($match[0] ?? '');
            }

            array_push($list, array($proxy, $port, $username, $password));
        }
        return $list;
    }

    $list = fifu_get_transient('fifu_proxy_list');
    if ($list)
        return $list;

    $list = fifu_proxy_scrape();
    if ($list)
        fifu_set_transient('fifu_proxy_list', $list, 300);

    return $list;
}

function fifu_parse_proxy_credentials($credentials) {
    $parts = explode(':', $credentials);
    return [
        $parts[0] ?? null, // username
        $parts[1] ?? null   // password
    ];
}

function fifu_parse_proxy_address($address) {
    $parts = explode(':', $address);
    return [
        $parts[0] ?? null, // proxy IP
        $parts[1] ?? null   // port
    ];
}

function fifu_proxy_scrape() {
    $queryParams = http_build_query([
        'site' => fifu_get_home_url(),
        'partial_key' => fifu_partial_key(),
    ]);
    $workerUrl = "https://free-proxy-list.fifu.workers.dev?" . $queryParams;

    try {
        $response = wp_remote_get($workerUrl);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) != 200)
            return null;

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!empty($data['proxies'] ?? null))
            return $data['proxies'];
    } catch (Exception $e) {
        error_log('fifu-proxy:', $e);
    }

    return null;
}

function fifu_proxy_download_url($url, $ip, $port, $user, $password, $get_html) {
    $crl = curl_init();
    curl_setopt($crl, CURLOPT_PROXY, "{$ip}:{$port}");
    curl_setopt($crl, CURLOPT_URL, $url);
    curl_setopt($crl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($crl, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($crl, CURLOPT_HTTPPROXYTUNNEL, false);
    curl_setopt($crl, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($crl, CURLOPT_VERBOSE, true);

    if ($get_html)
        curl_setopt($crl, CURLOPT_ENCODING, "");

    if ($user)
        curl_setopt($crl, CURLOPT_PROXYUSERPWD, "{$user}:{$password}");

    try {
        $ret = curl_exec($crl);
    } catch (Exception $e) {
        return null;
    }

    $content_type = curl_getinfo($crl, CURLINFO_CONTENT_TYPE);
    $verbose = curl_getinfo($crl);

    curl_close($crl);

    if ($get_html) {
        return $ret;
    } elseif ($content_type) {
        if (strpos($content_type, 'image') !== false)
            return $ret;
        return 'invalid-type';
    }

    return null;
}

function fifu_proxy_get_cache() {
    $cache = get_option('fifu_cache_proxy');
    return $cache ? unserialize($cache) : null;
}

function fifu_proxy_download($url, $get_html) {
    $host = fifu_get_host($url);
    $cache_proxy = fifu_proxy_get_cache();
    $content = null;
    if ($cache_proxy) {
        foreach ($cache_proxy as $i => $proxy) {
            if (isset($proxy[$host])) {
                $params = array();
                for ($j = 0; $j <= 3; $j++)
                    array_push($params, ($proxy[$host][$j] ?? null));

                fifu_plugin_log(['fifu_proxy_download' => ['Cached proxy' => ($params[0] ?? 'N/A')]]);

                // two attempts with the same before unset
                $content = fifu_proxy_download_url($url, $params[0] ?? null, $params[1] ?? null, $params[2] ?? null, $params[3] ?? null, $get_html);
                if (!$content) {
                    $content = fifu_proxy_download_url($url, $params[0] ?? null, $params[1] ?? null, $params[2] ?? null, $params[3] ?? null, $get_html);
                    if (!$content) {
                        unset($cache_proxy[$i]);
                    }
                } else
                    break;
            }
            $i++;
        }
    } else {
        $cache_proxy = array();
    }
    if (!$content) {
        $proxies = fifu_proxy_get_list();
        $i = 0;
        foreach ($proxies as $proxy) {
            $params = array();
            for ($j = 0; $j <= 3; $j++)
                array_push($params, ($proxy[$j] ?? null));

            fifu_plugin_log(['fifu_proxy_download' => ['Trying proxy' => "{$i}: " . ($params[0] ?? 'N/A') . ":" . ($params[1] ?? 'N/A')]]);
            $i++;

            if (!get_option('fifu_cache_proxy'))
                return null;

            $content = fifu_proxy_download_url($url, $params[0] ?? null, $params[1] ?? null, $params[2] ?? null, $params[3] ?? null, $get_html);
            if ($content == 'invalid-type') {
                continue;
            }
            if ($content) {
                $arr[$host] = $proxy;
                array_push($cache_proxy, $arr);
                update_option('fifu_cache_proxy', serialize($cache_proxy), 'no');
                fifu_plugin_log(['fifu_proxy_download' => ['Notice' => 'Good one!']]);
                break;
            }
        }
    }
    if ($get_html) {
        // return html
        return $content;
    } elseif ($content) {
        // download image
        $tmp = get_temp_dir() . date("Ymd-His") . '.jpg';
        file_put_contents($tmp, $content);
        return $tmp;
    }
    return null;
}

