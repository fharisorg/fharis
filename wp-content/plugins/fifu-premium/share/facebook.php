<?php

/**
 * FIFU Auto-Share — Facebook-specific thin handlers
 * Worker does OAuth & data fetching. PHP proxies and stores.
 */
// NOTE: No require/include here; loader wires common helpers.

/**
 * Route: POST /fifu-premium/v2/social/facebook/auth/start
 * Called by: client JS (user clicks "Continue with Facebook").
 * Why: get OAuth authorization URL + state from the Worker to open a popup.
 * Flow: JS → PHP(route) → Worker(/v2/oauth/facebook/start?site&partial_key) → JS opens authUrl.
 */
function fifu_as_facebook_auth_start(WP_REST_Request $req) {
    $body = fifu_as_read_json($req);

    $payload = [
        'provider' => 'facebook',
        'site_id' => fifu_as_site_id(),
        'user_id' => fifu_as_user_id(),
        'intent' => $body['intent'] ?? 'login',
        // Report plugin version to Worker
        'version' => fifu_as_version(),
    ];

    // Worker v2 endpoint; common helper appends ?site=…&partial_key=…
    $wk = fifu_as_worker_request('POST', '/v2/oauth/facebook/start', $payload);
    if (is_wp_error($wk))
        return $wk;

    return fifu_as_json([
        'authUrl' => isset($wk['authUrl']) ? esc_url_raw($wk['authUrl']) : '',
        'state' => isset($wk['state']) ? sanitize_text_field($wk['state']) : '',
        'popupOrigin' => isset($wk['popupOrigin']) ? esc_url_raw($wk['popupOrigin']) : '',
    ]);
}

/**
 * Route: POST /fifu-premium/v2/social/facebook/auth/finalize
 * Called by: client JS after popup posts a message with tempToken.
 * Why: exchange tempToken+state with Worker for long-lived tokens + pages; store snapshot.
 * Flow: JS(tempToken) → PHP(route) → Worker(/v2/oauth/facebook/finalize?site&partial_key) → PHP stores → JS updates UI.
 */
function fifu_as_facebook_auth_finalize(WP_REST_Request $req) {
    $body = fifu_as_read_json($req);
    $token = isset($body['tempToken']) ? sanitize_text_field($body['tempToken']) : '';
    $state = isset($body['state']) ? sanitize_text_field($body['state']) : '';

    if (!$token) {
        return new WP_Error('fifu_as_bad_request', 'Missing tempToken', ['status' => 400]);
    }

    $payload = [
        'provider' => 'facebook',
        'site_id' => fifu_as_site_id(),
        'user_id' => fifu_as_user_id(),
        'tempToken' => $token,
        'state' => $state,
        'version' => fifu_as_version(),
    ];

    $wk = fifu_as_worker_request('POST', '/v2/oauth/facebook/finalize', $payload);
    if (is_wp_error($wk))
        return $wk;

    $store = [
        'accountName' => isset($wk['accountName']) ? (string) $wk['accountName'] : '',
        'pages' => (isset($wk['pages']) && is_array($wk['pages'])) ? $wk['pages'] : [],
        'token' => (isset($wk['token']) && is_array($wk['token'])) ? $wk['token'] : [],
    ];
    fifu_as_store_connection('facebook', $store);

    return fifu_as_json([
        'connected' => !empty($store['token']['access_token']),
        'accountName' => $store['accountName'],
        'pages' => $store['pages'],
    ]);
}

/**
 * Route: GET /fifu-premium/v2/social/facebook/status
 * Called by: client JS on load and after finalize.
 * Why: provide a safe connection snapshot to display in UI.
 * Flow: JS → PHP(route) → user meta snapshot → JS labels/status.
 */
function fifu_as_facebook_status(WP_REST_Request $req) {
    return fifu_as_json(fifu_as_get_status_snapshot('facebook'));
}

