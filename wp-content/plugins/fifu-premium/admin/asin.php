<?php

class FifuPaapi {

    function get_image_urls($asin) {
        $res = $this->get_image_url_product($asin);
        if ($res) {
            return $res;
        }
        return null;
    }

    function get_image_url_product($asin) {
        $queryParams = [
            'site' => fifu_get_home_url(),
            'partial_key' => fifu_partial_key(),
            'asin' => $asin,
            'partner_tag' => get_option('fifu_asin_credentials_partner') ?: '',
            'access_key' => get_option('fifu_asin_credentials_access') ?: '',
            'secret_key' => get_option('fifu_asin_credentials_secret') ?: '',
            'locale' => get_option('fifu_asin_credentials_locale') ?: '',
        ];

        $workerUrl = "https://paapi5.fifu.workers.dev";

        try {
            $response = wp_remote_post($workerUrl, [
                'body' => json_encode($queryParams), // Encode the array to JSON
                'headers' => [
                    'Content-Type' => 'application/json', // Set the Content-Type header
                ],
                'timeout' => 10,
            ]);

            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) != 200) {
                return null;
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (!empty($data['images'] ?? [])) {
                return $data;
            }
        } catch (Exception $e) {
            error_log('fifu-asin: ' . $e->getMessage() . ' - ASIN: ' . $asin);
        }

        return null;
    }

}

function fifu_asin_search($asin) {
    $paapi = new FifuPaapi();
    return $paapi->get_image_urls($asin);
}

