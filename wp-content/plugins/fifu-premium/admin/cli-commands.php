<?php

class fifu_cli extends WP_CLI_Command {

    // admin

    function reset() {
        fifu_reset_settings();
        //WP_CLI::line($args[0]);
    }

    function debug($args) {
        switch ($args[0] ?? '') {
            case 'on':
                update_option('fifu_debug', 'toggleon', 'no'); // toggle
                break;
            case 'off':
                update_option('fifu_debug', 'toggleoff', 'no'); // toggle
                break;
        }
    }

    // automatic

    function content($args, $assoc_args) {
        if (!empty($assoc_args['skip'])) {
            update_option('fifu_skip', $args[0] ?? '', 'no');
            return;
        }
        if (!empty($assoc_args['cpt'])) {
            update_option('fifu_html_cpt', $args[0] ?? '', 'no');
            return;
        }
        if (!empty($assoc_args['overwrite'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_ovw_first', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_ovw_first', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['media'])) {
            update_option('fifu_html_media', $args[0] ?? '', 'no');
            return;
        }
        if (!empty($assoc_args['all-run'])) {
            fifu_create_generic_hook('content');
            return;
        }
        switch ($args[0] ?? '') {
            case 'on':
                update_option('fifu_get_first', 'toggleon', 'no'); // toggle
                break;
            case 'off':
                update_option('fifu_get_first', 'toggleoff', 'no'); // toggle
                break;
        }
    }

    function search($args, $assoc_args) {
        if (!empty($assoc_args['min-width'])) {
            update_option('fifu_auto_set_width', $args[0] ?? '', 'no');
            return;
        }
        if (!empty($assoc_args['min-height'])) {
            update_option('fifu_auto_set_height', $args[0] ?? '', 'no');
            return;
        }
        if (!empty($assoc_args['blocklist'])) {
            update_option('fifu_auto_set_blocklist', str_replace(',', '
', $args[0] ?? ''), 'no'); // don't edit
            return;
        }
        if (!empty($assoc_args['cpt'])) {
            update_option('fifu_auto_set_cpt', $args[0] ?? '', 'no');
            return;
        }
        if (!empty($assoc_args['source'])) {
            update_option('fifu_auto_set_source', $args[0] ?? '', 'no');
            return;
        }
        if (!empty($assoc_args['layout'])) {
            update_option('fifu_auto_set_layout', $args[0] ?? '', 'no');
            return;
        }
        switch ($args[0] ?? '') {
            case 'on':
                update_option('fifu_auto_set', 'toggleon', 'no'); // toggle
                break;
            case 'off':
                update_option('fifu_auto_set', 'toggleoff', 'no'); // toggle
                break;
        }
    }

    function isbn($args, $assoc_args) {
        if (!empty($assoc_args['field'])) {
            update_option('fifu_isbn_custom_field', $args[0] ?? '', 'no');
            return;
        }
        switch ($args[0] ?? '') {
            case 'on':
                update_option('fifu_isbn', 'toggleon', 'no'); // toggle
                break;
            case 'off':
                update_option('fifu_isbn', 'toggleoff', 'no'); // toggle
                break;
        }
    }

    function asin($args, $assoc_args) {
        if (!empty($assoc_args['field'])) {
            update_option('fifu_asin_custom_field', $args[0] ?? '', 'no');
            return;
        }
        if (!empty($assoc_args['partner'])) {
            update_option('fifu_asin_credentials_partner', $args[0] ?? '', 'no');
            return;
        }
        if (!empty($assoc_args['access'])) {
            update_option('fifu_asin_credentials_access', $args[0] ?? '', 'no');
            return;
        }
        if (!empty($assoc_args['secret'])) {
            update_option('fifu_asin_credentials_secret', $args[0] ?? '', 'no');
            return;
        }
        if (!empty($assoc_args['locale'])) {
            update_option('fifu_asin_credentials_locale', $args[0] ?? '', 'no');
            return;
        }
        switch ($args[0] ?? '') {
            case 'on':
                update_option('fifu_asin', 'toggleon', 'no'); // toggle
                break;
            case 'off':
                update_option('fifu_asin', 'toggleoff', 'no'); // toggle
                break;
        }
    }

    function customfield($args, $assoc_args) {
        if (!empty($assoc_args['field'])) {
            update_option('fifu_customfield_custom_field', $args[0] ?? '', 'no');
            return;
        }
        switch ($args[0] ?? '') {
            case 'on':
                update_option('fifu_customfield', 'toggleon', 'no'); // toggle
                break;
            case 'off':
                update_option('fifu_customfield', 'toggleoff', 'no'); // toggle
                break;
        }
    }

    function finder($args, $assoc_args) {
        if (!empty($assoc_args['field'])) {
            update_option('fifu_finder_custom_field', $args[0] ?? '', 'no');
            return;
        }
        if (!empty($assoc_args['video'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_video_finder', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_video_finder', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['amazon'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_amazon_finder', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_amazon_finder', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        switch ($args[0] ?? '') {
            case 'on':
                update_option('fifu_finder', 'toggleon', 'no'); // toggle
                break;
            case 'off':
                update_option('fifu_finder', 'toggleoff', 'no'); // toggle
                break;
        }
    }

    function tags($args, $assoc_args = []) {
        if (!empty($assoc_args['orientation'])) {
            update_option('fifu_tags_orientation', $args[0] ?? '', 'no');
            return;
        }
        switch ($args[0] ?? '') {
            case 'on':
                update_option('fifu_tags', 'toggleon', 'no'); // toggle
                break;
            case 'off':
                update_option('fifu_tags', 'toggleoff', 'no'); // toggle
                break;
        }
    }

    function screenshot($args, $assoc_args) {
        if (!empty($assoc_args['size'])) {
            update_option('fifu_screenshot_size', $args[0] ?? '', 'no');
            return;
        }
        if (!empty($assoc_args['field'])) {
            update_option('fifu_screenshot_custom_field', $args[0] ?? '', 'no');
            return;
        }
        switch ($args[0] ?? '') {
            case 'on':
                update_option('fifu_screenshot', 'toggleon', 'no'); // toggle
                break;
            case 'off':
                update_option('fifu_screenshot', 'toggleoff', 'no'); // toggle
                break;
        }
        return;
    }

    // featured image

    function image($args, $assoc_args) {
        if (!empty($assoc_args['pcontent-add'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_pcontent_add', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_pcontent_add', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['pcontent-remove'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_pcontent_remove', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_pcontent_remove', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['pcontent-types'])) {
            update_option('fifu_pcontent_types', $args[0] ?? '', 'no');
            return;
        }
        if (!empty($assoc_args['hide'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_hide', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_hide', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['hide-types'])) {
            update_option('fifu_hide_type', $args[0] ?? '', 'no');
            return;
        }
        if (!empty($assoc_args['hide-formats'])) {
            update_option('fifu_hide_format', $args[0] ?? '', 'no');
            return;
        }
        if (!empty($assoc_args['default'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_enable_default_url', 'toggleon', 'no'); // toggle
                    $default_url = get_option('fifu_default_url');
                    if (!$default_url)
                        fifu_db_delete_default_url();
                    elseif (fifu_is_on('fifu_fake')) {
                        if (!wp_get_attachment_url(get_option('fifu_default_attach_id'))) {
                            $att_id = fifu_db_create_attachment($default_url);
                            update_option('fifu_default_attach_id', $att_id);
                            fifu_db_set_default_url();
                        } else
                            fifu_db_update_default_url($default_url);
                    }
                    break;
                case 'off':
                    update_option('fifu_enable_default_url', 'toggleoff', 'no'); // toggle
                    fifu_db_delete_default_url();
                    break;
            }
            return;
        }
        if (!empty($assoc_args['default-url'])) {
            update_option('fifu_default_url', $args[0] ?? '', 'no');
            if (fifu_is_off('fifu_enable_default_url'))
                fifu_db_delete_default_url();
            elseif (!($args[0] ?? ''))
                fifu_db_delete_default_url();
            return;
        }
        if (!empty($assoc_args['default-types'])) {
            update_option('fifu_default_cpt', $args[0] ?? '', 'no');
            return;
        }
        if (!empty($assoc_args['replace'])) {
            update_option('fifu_error_url', $args[0] ?? '', 'no');
            return;
        }
        if (!empty($assoc_args['block'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_block', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_block', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['popup'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_popup', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_popup', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['redirection'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_redirection', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_redirection', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['taxonomy'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_taxonomy', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_taxonomy', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
    }

    function upload($args, $assoc_args) {
        if (!empty($assoc_args['domain'])) {
            update_option('fifu_upload_domain', $args[0] ?? '', 'no');
            return;
        }
        if (!empty($assoc_args['show-button'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_upload_show', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_upload_show', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['job'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_upload_job', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_upload_job', 'toggleoff', 'no'); // toggle
                    break;
            }
        }
        if (!empty($assoc_args['proxy'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_upload_proxy', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_upload_proxy', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['private-proxy'])) {
            update_option('fifu_upload_private_proxy', $args[0] ?? '', 'no');
            return;
        }
    }

    // shortcodes

    function shortform($args) {
        switch ($args[0] ?? '') {
            case 'on':
                update_option('fifu_shortform', 'toggleon', 'no'); // toggle
                break;
            case 'off':
                update_option('fifu_shortform', 'toggleoff', 'no'); // toggle
                break;
        }
    }

    // featured slider

    function slider($args, $assoc_args) {
        if (!empty($assoc_args['pause'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_slider_stop', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_slider_stop', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['buttons'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_slider_ctrl', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_slider_ctrl', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['auto'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_slider_auto', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_slider_auto', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['gallery'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_slider_gallery', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_slider_gallery', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['thumb-gallery'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_slider_thumb', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_slider_thumb', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['counter'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_slider_counter', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_slider_counter', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['crop'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_slider_crop', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_slider_crop', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['single'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_slider_single', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_slider_single', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['vertical'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_slider_vertical', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_slider_vertical', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['time-image'])) {
            update_option('fifu_slider_pause', $args[0] ?? '', 'no');
            return;
        }
        if (!empty($assoc_args['time-transition'])) {
            update_option('fifu_slider_speed', $args[0] ?? '', 'no');
            return;
        }
        if (!empty($assoc_args['left'])) {
            update_option('fifu_slider_left', $args[0] ?? '', 'no');
            return;
        }
        if (!empty($assoc_args['right'])) {
            update_option('fifu_slider_right', $args[0] ?? '', 'no');
            return;
        }
        switch ($args[0] ?? '') {
            case 'on':
                update_option('fifu_slider', 'toggleon', 'no'); // toggle
                break;
            case 'off':
                update_option('fifu_slider', 'toggleoff', 'no'); // toggle
                break;
        }
    }

    // featured video

    function video($args, $assoc_args) {
        if (!empty($assoc_args['thumb-home'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_video_thumb', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_video_thumb', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['thumb-page'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_video_thumb_page', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_video_thumb_page', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['thumb-post'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_video_thumb_post', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_video_thumb_post', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['thumb-cpt'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_video_thumb_cpt', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_video_thumb_cpt', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['play'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_video_play_button', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_video_play_button', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['play-color'])) {
            update_option('fifu_video_color', $args[0] ?? '', 'no');
            return;
        }
        if (!empty($assoc_args['play-mode'])) {
            update_option('fifu_play_type', $args[0] ?? '', 'no');
            return;
        }
        if (!empty($assoc_args['play-zindex'])) {
            update_option('fifu_video_zindex', $args[0] ?? '', 'no');
            return;
        }
        if (!empty($assoc_args['play-size'])) {
            update_option('fifu_video_size', $args[0] ?? '', 'no');
            return;
        }
        if (!empty($assoc_args['play-hide'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_video_play_hide_grid', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_video_play_hide_grid', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['play-hide-wc'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_video_play_hide_grid_wc', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_video_play_hide_grid_wc', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['min-width'])) {
            update_option('fifu_video_min_width', $args[0] ?? '', 'no');
            return;
        }
        if (!empty($assoc_args['controls'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_video_controls', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_video_controls', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['mouse'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_mouse_video', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_mouse_video', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['autoplay'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_autoplay', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_autoplay', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['autoplay-front'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_autoplay_front', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_autoplay_front', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['autoplay-else'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_autoplay_elsewhere', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_autoplay_elsewhere', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['loop'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_loop', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_loop', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['mute-desktop'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_video_mute', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_video_mute', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['mute-mobile'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_video_mute_mobile', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_video_mute_mobile', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['background'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_video_background', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_video_background', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['background-single'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_video_background_single', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_video_background_single', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['privacy'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_video_privacy', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_video_privacy', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['later'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_video_later', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_video_later', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['later-left'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_video_later_left', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_video_later_left', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        switch ($args[0] ?? '') {
            case 'on':
                update_option('fifu_video', 'toggleon', 'no'); // toggle
                break;
            case 'off':
                update_option('fifu_video', 'toggleoff', 'no'); // toggle
                break;
        }
    }

    // license key

    function key($args, $assoc_args) {
        if (!empty($assoc_args['number'])) {
            update_option('fifu_key', $args[0] ?? '', 'no');
            return;
        }
    }

    // metadata

    function metadata($args) {
        switch ($args[0] ?? '') {
            case 'on':
                update_option('fifu_fake_stop', false, 'no');
                fifu_enable_fake();
                update_option('fifu_fake', 'toggleon', 'no'); // toggle
                break;
            case 'off':
                update_option('fifu_fake_stop', true, 'no');
                update_option('fifu_fake', 'toggleoff', 'no'); // toggle
                break;
        }
    }

    function clean() {
        fifu_db_enable_clean();
        update_option('fifu_data_clean', 'toggleoff', 'no');
    }

    function schedule($args, $assoc_args) {
        switch ($args[0] ?? '') {
            case 'on':
                update_option('fifu_cron_metadata', 'toggleon', 'no'); // toggle
                break;
            case 'off':
                update_option('fifu_cron_metadata', 'toggleoff', 'no'); // toggle
                break;
        }
    }

    // performance

    function cdn($args, $assoc_args) {
        if (!empty($assoc_args['content'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_cdn_content', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_cdn_content', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['fifu'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_otfcdn', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_otfcdn', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['domain'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_own_domain', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_own_domain', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        switch ($args[0] ?? '') {
            case 'on':
                update_option('fifu_photon', 'toggleon', 'no'); // toggle
                break;
            case 'off':
                update_option('fifu_photon', 'toggleoff', 'no'); // toggle
                break;
        }
    }

    function square($args, $assoc_args) {
        if (!empty($assoc_args['desktop'])) {
            update_option('fifu_square_desktop', $args[0] ?? '', 'no');
            return;
        }
        if (!empty($assoc_args['mobile'])) {
            update_option('fifu_square_mobile', $args[0] ?? '', 'no');
            return;
        }
    }

    // audio

    function audio($args) {
        switch ($args[0] ?? '') {
            case 'on':
                update_option('fifu_audio', 'toggleon', 'no'); // toggle
                break;
            case 'off':
                update_option('fifu_audio', 'toggleoff', 'no'); // toggle
                break;
        }
    }

    function bbpress($args, $assoc_args) {
        if (!empty($assoc_args['fields'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_bbpress_fields', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_bbpress_fields', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
    }

    // sizes

    function sizes($args, $assoc_args) {
        if (!empty($assoc_args['save'])) {
            $size = explode('=', $args[0] ?? '');
            $name = $size[0] ?? '';
            $size = explode('x', $size[1] ?? '');
            $w = (int) ($size[0] ?? 0);
            $h = (int) ($size[1] ?? 0);
            $c = ($size[2] ?? '0') === '1'; // Convert to boolean
            $value = [
                'w' => $w,
                'h' => $h,
                'c' => $c
            ];
            update_option('fifu_defined_size_' . $name, $value, 'no');
            return;
        }
    }

    // woocommerce

    function woo($args, $assoc_args) {
        if (!empty($assoc_args['lightbox'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_wc_lbox', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_wc_lbox', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['zoom'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_wc_zoom', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_wc_zoom', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['category-auto'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_auto_category', 'toggleon', 'no'); // toggle
                    if (!get_option('fifu_auto_category_created')) {
                        fifu_db_insert_auto_category_image();
                        update_option('fifu_auto_category_created', true, 'no');
                    }
                    break;
                case 'off':
                    update_option('fifu_auto_category', 'toggleoff', 'no'); // toggle
                    update_option('fifu_auto_category_created', false, 'no');
                    break;
            }
            return;
        }
        if (!empty($assoc_args['order-email'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_order_email', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_order_email', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['gallery'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_gallery', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_gallery', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['adaptive'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_adaptive_height', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_adaptive_height', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['videos-before'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_videos_before', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_videos_before', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['variations-merge'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_variations_merge', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_variations_merge', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
        if (!empty($assoc_args['buy-text'])) {
            update_option('fifu_buy_text', $args[0] ?? '', 'no');
            return;
        }
        if (!empty($assoc_args['buy-disclaimer'])) {
            update_option('fifu_buy_disclaimer', $args[0] ?? '', 'no');
            return;
        }
        if (!empty($assoc_args['buy-cf'])) {
            update_option('fifu_buy_cf', $args[0] ?? '', 'no');
            return;
        }
        if (!empty($assoc_args['buy'])) {
            switch ($args[0] ?? '') {
                case 'on':
                    update_option('fifu_buy', 'toggleon', 'no'); // toggle
                    break;
                case 'off':
                    update_option('fifu_buy', 'toggleoff', 'no'); // toggle
                    break;
            }
            return;
        }
    }
}

WP_CLI::add_command('fifu', 'fifu_cli');

add_action('wp_insert_post', function ($post_id, $post, $update) {
    fifu_update_fake_attach_id($post->ID);
}, 10, 3);

