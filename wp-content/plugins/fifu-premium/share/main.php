<?php

/**
 * FIFU Auto-Share — REST routes (provider-agnostic dispatcher)
 * Namespace: fifu-premium/v2
 *
 * Endpoints used by the minimal client (test.js):
 *  - POST /fifu-premium/v2/social/{provider}/auth/start
 *  - POST /fifu-premium/v2/social/{provider}/auth/finalize
 *  - GET  /fifu-premium/v2/social/{provider}/status
 *
 * Extend switch-cases to add Instagram/Threads later.
 */
// NOTE: No require/include; loader ensures helpers/providers are loaded.

add_action('rest_api_init', function () {
    $ns = 'fifu-premium/v2';

    /**
     * POST /social/{provider}/auth/start
     * Called by: client JS when the user initiates connection.
     * Why: fetch OAuth URL from Worker to open the provider popup.
     */
    register_rest_route($ns, '/social/(?P<provider>[a-z0-9_-]+)/auth/start', [
        'methods' => 'POST',
        'permission_callback' => 'fifu_as_can_manage',
        'callback' => function (WP_REST_Request $req) {
            $provider = sanitize_key($req['provider']);
            switch ($provider) {
                case 'facebook':
                    return fifu_as_facebook_auth_start($req);
                // case 'instagram': return fifu_as_instagram_auth_start($req);
                // case 'threads':   return fifu_as_threads_auth_start($req);
                default:
                    return new WP_Error('fifu_as_unknown_provider', 'Provider not supported', ['status' => 400]);
            }
        },
    ]);

    /**
     * POST /social/{provider}/auth/finalize
     * Called by: client JS after popup posts tempToken via postMessage.
     * Why: exchange tempToken with Worker; persist tokens/account/pages.
     */
    register_rest_route($ns, '/social/(?P<provider>[a-z0-9_-]+)/auth/finalize', [
        'methods' => 'POST',
        'permission_callback' => 'fifu_as_can_manage',
        'callback' => function (WP_REST_Request $req) {
            $provider = sanitize_key($req['provider']);
            switch ($provider) {
                case 'facebook':
                    return fifu_as_facebook_auth_finalize($req);
                // case 'instagram': return fifu_as_instagram_auth_finalize($req);
                // case 'threads':   return fifu_as_threads_auth_finalize($req);
                default:
                    return new WP_Error('fifu_as_unknown_provider', 'Provider not supported', ['status' => 400]);
            }
        },
    ]);

    /**
     * GET /social/{provider}/status
     * Called by: client JS on load and after finalize.
     * Why: display current connection/account/page snapshot.
     */
    register_rest_route($ns, '/social/(?P<provider>[a-z0-9_-]+)/status', [
        'methods' => 'GET',
        'permission_callback' => 'fifu_as_can_manage',
        'callback' => function (WP_REST_Request $req) {
            $provider = sanitize_key($req['provider']);
            switch ($provider) {
                case 'facebook':
                    return fifu_as_facebook_status($req);
                // case 'instagram': return fifu_as_instagram_status($req);
                // case 'threads':   return fifu_as_threads_status($req);
                default:
                    return new WP_Error('fifu_as_unknown_provider', 'Provider not supported', ['status' => 400]);
            }
        },
    ]);
});

