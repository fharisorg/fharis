<?php

/**
 * FIFU Auto-Share — common (provider-agnostic) helpers
 * - Capability/nonce checks
 * - Worker HTTP client (adds site/partial_key query params)
 * - Minimal per-user storage for connection snapshot
 */
// ---- Capability / Nonce -----------------------------------------------------
function fifu_as_can_manage(WP_REST_Request $req) {
    $nonce = $req->get_header('x-wp-nonce');
    return current_user_can('manage_options') && wp_verify_nonce($nonce, 'wp_rest');
}

// ---- JSON helpers ------------------------------------------------------------
function fifu_as_json($data, int $status = 200) {
    return new WP_REST_Response($data, $status, [
        'Content-Type' => 'application/json; charset=utf-8'
    ]);
}

function fifu_as_read_json(WP_REST_Request $req) {
    $raw = $req->get_body();
    if (!$raw)
        return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

// ---- Context ----------------------------------------------------------------
function fifu_as_site_id() {
    if (is_multisite())
        return get_network()->id . ':' . get_current_blog_id();
    return (string) parse_url(home_url(), PHP_URL_HOST);
}

function fifu_as_user_id() {
    $u = get_current_user_id();
    return $u ? (int) $u : 0;
}

/** Version reported to the Worker (plugin build/version). */
function fifu_as_version() {
    return function_exists('fifu_version_number') ? fifu_version_number() : 'dev';
}

// ---- Worker base (fixed) -----------------------------------------------------
/** Cloudflare Worker handling OAuth and posting. */
function fifu_as_worker_base() {
    return 'https://auto-share.fifu.workers.dev';
}

// ---- HTTP client to Worker ---------------------------------------------------
/**
 * Call the Worker with minimal headers and the **required auth query params**:
 *   ?site=fifu_get_home_url()&partial_key=fifu_partial_key()
 *
 * Returns decoded array or WP_Error on transport/status error.
 */
function fifu_as_worker_request(string $method, string $path, array $payload = []) {
    // Base path
    $base = rtrim(fifu_as_worker_base(), '/') . '/' . ltrim($path, '/');

    // REQUIRED INTERNAL AUTH PARAMS
    $q = [
        'site' => function_exists('fifu_get_home_url') ? fifu_get_home_url() : home_url(),
        'partial_key' => function_exists('fifu_partial_key') ? fifu_partial_key() : '',
    ];

    // Append query params safely (keeps any existing query in $path)
    $url = add_query_arg($q, $base);

    $args = [
        'method' => strtoupper($method),
        'timeout' => 20,
        'headers' => [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-FIFU-Site' => fifu_as_site_id(),
            'X-FIFU-User' => (string) fifu_as_user_id(),
            'X-FIFU-Version' => fifu_as_version(),
        ],
        'body' => $payload ? wp_json_encode($payload) : null,
    ];

    $res = wp_remote_request($url, $args);
    if (is_wp_error($res)) {
        return new WP_Error('fifu_worker_http', $res->get_error_message(), ['status' => 502]);
    }
    $code = (int) wp_remote_retrieve_response_code($res);
    $body = (string) wp_remote_retrieve_body($res);
    $json = json_decode($body, true);
    if ($code >= 400) {
        return new WP_Error('fifu_worker_bad_status', "Worker HTTP $code", ['status' => 502, 'body' => $body]);
    }
    return is_array($json) ? $json : [];
}

// ---- Minimal storage (per-user) ---------------------------------------------
function fifu_as_meta_key(string $provider, string $suffix) {
    return 'fifu_as_' . sanitize_key($provider) . '_' . sanitize_key($suffix);
}

/** Store connection snapshot (account/pages/token) for a provider. */
function fifu_as_store_connection(string $provider, array $data) {
    $uid = fifu_as_user_id();
    update_user_meta($uid, fifu_as_meta_key($provider, 'account'), sanitize_text_field($data['accountName'] ?? ''));
    update_user_meta($uid, fifu_as_meta_key($provider, 'pages'), wp_json_encode($data['pages'] ?? []));
    update_user_meta($uid, fifu_as_meta_key($provider, 'token'), wp_json_encode($data['token'] ?? []));
    update_user_meta($uid, fifu_as_meta_key($provider, 'updated'), time());
}

/** Safe snapshot for UI. */
function fifu_as_get_status_snapshot(string $provider) {
    $uid = fifu_as_user_id();
    $acc = get_user_meta($uid, fifu_as_meta_key($provider, 'account'), true);
    $pages = json_decode((string) get_user_meta($uid, fifu_as_meta_key($provider, 'pages'), true), true) ?: [];
    $tok = json_decode((string) get_user_meta($uid, fifu_as_meta_key($provider, 'token'), true), true) ?: [];
    $upd = (int) get_user_meta($uid, fifu_as_meta_key($provider, 'updated'), true);

    $connected = !empty($tok['access_token']); // minimal check
    return [
        'connected' => $connected,
        'accountName' => $acc ?: null,
        'pages' => array_map(function ($p) {
            return [
                'id' => isset($p['id']) ? (string) $p['id'] : null,
                'name' => isset($p['name']) ? (string) $p['name'] : null,
                'connected' => !empty($p['connected']),
            ];
        }, $pages),
        'lastChecked' => $upd ?: time(),
    ];
}

