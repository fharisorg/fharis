<?php

if (!defined('ABSPATH'))
    exit;

/* BuddyBoss active? */

function fifu_is_buddyboss_platform_active(): bool {
    if (defined('BUDDYBOSS_PLATFORM_VERSION'))
        return true;
    if (!function_exists('is_plugin_active'))
        @include_once ABSPATH . 'wp-admin/includes/plugin.php';
    foreach (['buddyboss-platform/bp-loader.php', 'buddyboss-platform/buddyboss-platform.php'] as $f) {
        if (function_exists('is_plugin_active') && is_plugin_active($f))
            return true;
        if (is_multisite()) {
            $nw = (array) get_site_option('active_sitewide_plugins', []);
            if (isset($nw[$f]))
                return true;
        }
    }
    return false;
}

/* Only run when bbPress fields setting is ON */
if (!fifu_is_on('fifu_bbpress_fields'))
    return;

/* Allow <img> in activity content */
add_filter('bp_activity_allowed_tags', function ($tags) {
    $tags['img'] = [
        'src' => true, 'alt' => true, 'title' => true, 'width' => true, 'height' => true,
        'class' => true, 'loading' => true, 'decoding' => true, 'referrerpolicy' => true, 'style' => true,
        // allow lazy loaders
        'data-src' => true, 'data-lazy-src' => true, 'data-original' => true, 'srcset' => true,
    ];
    return $tags;
}, 10, 1);

/* Convert image URLs to <img>, then dedupe */

function fifu_bb_convert_image_urls_to_img($html) {
    if (!is_string($html) || $html === '')
        return $html;

    // Remove any edit-mode wrappers and remove buttons that might have been persisted
    $html = preg_replace_callback(
            '~<div\b[^>]*class="[^"]*\bfifu-img-wrap\b[^"]*"[^>]*>(.*?)</div>~is',
            function ($m) {
                // Extract only the image, discard the remove button
                if (preg_match('~<img\b[^>]*>~i', $m[1], $img)) {
                    return $img[0];
                }
                return $m[1];
            },
            $html
    );

    // Also remove any standalone remove buttons that might be left
    $html = preg_replace('~<button\b[^>]*class="[^"]*\bfifu-img-remove\b[^"]*"[^>]*>.*?</button>~is', '', $html);
    $html = preg_replace('~<span\b[^>]*class="[^"]*\bdashicons-no-alt\b[^"]*"[^>]*>.*?</span>~is', '', $html);

    // If the content already has any <img>, we avoid adding *new* ones on pre-save
    // but we still dedupe on render.
    $has_img = (strpos($html, '<img') !== false);

    if (!$has_img) {
        // 1) Links to images => <img>
        $html = preg_replace_callback(
                '~<a[^>]+href=(["\'])(https?://[^"\']+\.(?:png|jpe?g|gif|webp|avif))(?:\?[^"\']*)?\1[^>]*>.*?</a>~i',
                function ($m) {
                    $u = esc_url_raw($m[2]);
                    return sprintf('<img src="%s" alt="" loading="lazy" />', esc_url($u));
                },
                $html
        );

        // 2) Bare image URLs not inside tags => <img>
        $html = preg_replace_callback(
                '~(?<![\'">])(https?://[^\s<>"\']+\.(?:png|jpe?g|gif|webp|avif))(?:\?[^\s<>"\']*)?~i',
                function ($m) {
                    $u = esc_url_raw($m[1]);
                    return sprintf('<img src="%s" alt="" loading="lazy" />', esc_url($u));
                },
                $html
        );
    }

    // 3) Dedupe images and strip matching URLs/links.
    return fifu_bb_dedupe_images_and_links($html);
}

/* Robust dedupe: considers src, data-src, data-lazy-src, data-original, and srcset first URL */

function fifu_bb_dedupe_images_and_links($html) {
    $seen = [];

    // a) collapse duplicate <img> (same normalized URL)
    $html = preg_replace_callback(
            '~<img\b[^>]*>~i',
            function ($m) use (&$seen) {
                $tag = $m[0];

                // extract candidate URLs
                $urls = [];
                if (preg_match_all('~\b(?:src|data-src|data-lazy-src|data-original)=["\']([^"\']+)["\']~i', $tag, $mm)) {
                    foreach ($mm[1] as $u)
                        $urls[] = html_entity_decode($u, ENT_QUOTES);
                }
                if (preg_match('~\bsrcset=["\']([^"\']+)["\']~i', $tag, $ms)) {
                    // take first URL from srcset
                    $first = trim(explode(' ', trim($ms[1]))[0]);
                    if ($first)
                        $urls[] = html_entity_decode($first, ENT_QUOTES);
                }

                $key = '';
                foreach ($urls as $u) {
                    if ($u) {
                        $key = $u;
                        break;
                    }
                }
                if ($key === '')
                    return $tag;

                // normalize key (strip trailing spaces)
                $key = trim($key);

                if (isset($seen[$key])) {
                    // exact duplicate -> remove this tag
                    return '';
                }
                $seen[$key] = true;
                return $tag;
            },
            $html
    );

    if (!empty($seen)) {
        foreach (array_keys($seen) as $src) {
            $q = preg_quote($src, '~');
            // remove any <a href="src">…</a>
            $html = preg_replace('~<a[^>]+href=(["\'])' . $q . '(?:\?[^"\']*)?\1[^>]*>.*?</a>~i', '', $html);
            // remove bare src if still visible
            $html = preg_replace('~(?<![\'">])' . $q . '(?:\?[^\s<>"\']*)?~i', '', $html);
        }
    }

    // tidy
    return preg_replace("~\s{2,}~", ' ', trim($html));
}

/* PRE-SAVE: convert & dedupe (skips conversion if an <img> already exists) */
add_action('bp_activity_before_save', function ($activity) {
    if (!empty($activity->content)) {
        $activity->content = fifu_bb_convert_image_urls_to_img($activity->content);
    }
});

/* FINAL RENDER: run last to win over make_clickable/nofollow/lazyload wrappers */
add_filter('bp_get_activity_content', 'fifu_bb_convert_image_urls_to_img', PHP_INT_MAX);
add_filter('bp_get_activity_content_body', 'fifu_bb_convert_image_urls_to_img', PHP_INT_MAX);
add_filter('bp_get_activity_comment_content', 'fifu_bb_convert_image_urls_to_img', PHP_INT_MAX);

/* (Optional) enqueue your JS if you still want paste-time hiding */
add_action('wp_enqueue_scripts', function () {
    if (is_admin())
        return;
    if (!fifu_is_buddyboss_platform_active())
        return;
    if (function_exists('bp_is_active') && !bp_is_active('activity'))
        return;

    wp_enqueue_style(
            'fifu-buddyboss-css',
            plugins_url('/html/css/buddyboss.css', __FILE__),
            [],
            fifu_version_number_enq()
    );

    wp_enqueue_script(
            'fifu-buddyboss',
            plugins_url('/html/js/buddyboss.js', __FILE__),
            ['jquery'],
            fifu_version_number_enq(),
            true
    );
}, 20);

