<?php

define('FIFU_PROXY_TRANSLATE', 'https://translate.google.com/translate?hl=en&sl=pt&u=');

function fifu_upload_image($post_id, $url, $alt, $is_category) {
    set_time_limit(120);

    require_once(ABSPATH . '/wp-load.php');
    require_once(ABSPATH . '/wp-admin/includes/image.php');
    require_once(ABSPATH . '/wp-admin/includes/file.php');
    require_once(ABSPATH . '/wp-admin/includes/media.php');

    if (strpos($url, ".fifu.app") !== false) {
        if (strpos($url, "screenshot.fifu.app") === false)
            return null;
    }

    $is_google_drive = false;
    if (fifu_from_google_drive($url)) {
        $url = fifu_google_drive_url($url);
        $is_google_drive = true;
    }

    $att_id_md5 = fifu_db_get_thumbnail_id_by_md5($url);
    if ($att_id_md5) {
        if (get_post($att_id_md5))
            return $att_id_md5;
        fifu_db_delete_md5_by_thumbnail_id($att_id_md5);
    }

    if (fifu_is_base64($url)) {
        $tmp = get_temp_dir() . date("Ymd-His") . '.jpg';
        file_put_contents($tmp, file_get_contents($url));
    } else {
        if ($is_google_drive)
            $tmp = download_url(FIFU_PROXY_TRANSLATE . $url);
        else
            $tmp = fifu_is_on('fifu_upload_proxy') ? fifu_proxy_download($url, false) : download_url($url);
    }

    if (!$tmp || is_wp_error($tmp) || !is_string($tmp))
        return null;

    if (!$alt)
        $alt = strip_tags(get_the_title($post_id));

    $is_webp = false;
    // Check if GD is installed and if the function imagewebp is available
    if (extension_loaded('gd') && function_exists('imagewebp')) {
        $imageType = @exif_imagetype($tmp);

        // Check if the image is JPEG or PNG
        if ($imageType == IMAGETYPE_JPEG || $imageType == IMAGETYPE_PNG) {
            $image = ($imageType == IMAGETYPE_JPEG) ? @imagecreatefromjpeg($tmp) : @imagecreatefrompng($tmp);

            if ($image !== false) {
                $width = imagesx($image);
                $height = imagesy($image);

                // Save if this is a PNG with transparency
                $has_transparency = $imageType == IMAGETYPE_PNG && imagecolortransparent($image) >= 0;

                // Resize the image if either width or height is greater than 2048 pixels
                if ($width > 2048 || $height > 2048) {
                    $ratio = min(2048 / $width, 2048 / $height);
                    $newWidth = intval($width * $ratio);
                    $newHeight = intval($height * $ratio);
                    $resizedImage = imagecreatetruecolor($newWidth, $newHeight);

                    // Preserve transparency for PNG
                    if ($imageType == IMAGETYPE_PNG) {
                        imagealphablending($resizedImage, false);
                        imagesavealpha($resizedImage, true);
                        $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
                        imagefilledrectangle($resizedImage, 0, 0, $newWidth, $newHeight, $transparent);
                    }

                    imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                    imagedestroy($image);
                    $image = $resizedImage;
                }

                // Check if the image is a palette image and convert to true color if needed
                if (imageistruecolor($image) === false) {
                    $trueColorImage = imagecreatetruecolor(imagesx($image), imagesy($image));

                    // Preserve transparency for palette images
                    if ($imageType == IMAGETYPE_PNG) {
                        imagealphablending($trueColorImage, false);
                        imagesavealpha($trueColorImage, true);
                        $transparent = imagecolorallocatealpha($trueColorImage, 255, 255, 255, 127);
                        imagefilledrectangle($trueColorImage, 0, 0, imagesx($image), imagesy($image), $transparent);
                    }

                    imagecopy($trueColorImage, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
                    imagedestroy($image);
                    $image = $trueColorImage;
                }

                // Make sure to preserve alpha channel for PNGs
                if ($imageType == IMAGETYPE_PNG) {
                    imagealphablending($image, false);
                    imagesavealpha($image, true);
                }

                // Convert to WebP
                $webpPath = pathinfo($tmp, PATHINFO_DIRNAME) . '/' . pathinfo($tmp, PATHINFO_FILENAME) . '.webp';
                // For PNGs with transparency, we need to use 100 quality to ensure alpha channel is preserved
                $quality = ($imageType == IMAGETYPE_PNG && $has_transparency) ? 100 : 80;
                imagewebp($image, $webpPath, $quality);

                // Free up memory
                imagedestroy($image);

                $tmp = $webpPath; // Update the $tmp variable with the WebP path
                $is_webp = true;
            } else {
                // invalid or currupted image
                return null;
            }
        } else {
            if (!$imageType)
                return null;
        }
    } else {
        // Handle case where WebP conversion is not possible
        // You can log a message or take other actions as needed
    }

    $desc = $alt;
    $file_array = array();
    $file_array['name'] = ($alt ? sanitize_title($alt) : date("Ymd-His")) . ($is_webp ? '.webp' : '.jpg');
    $file_array['tmp_name'] = $tmp;
    if (is_wp_error($tmp)) {
        @unlink($file_array['name'] ?? '');
        return null;
    }

    $att_id = media_handle_sideload($file_array, $post_id, $desc);
    if (is_wp_error($att_id)) {
        @unlink($file_array['tmp_name'] ?? '');
        return $att_id;
    }

    // Clean up the temporary file if it exists
    if (file_exists($tmp)) {
        @unlink($tmp);
    }

    fifu_db_insert_md5($url, $att_id);

    return $att_id;
}

function fifu_upload_post($post_id) {
    $url = get_post_meta($post_id, 'fifu_image_url', true);
    $alt = get_post_meta($post_id, 'fifu_image_alt', true);
    if (!$url)
        return false;

    if (fifu_upload_skip_url($url))
        return false;

    try {
        /* featured image */
        fifu_plugin_log(['fifu_upload_post' => [$post_id => $url]]);
        $att_id = fifu_upload_image($post_id, $url, $alt, false);
        if (!$att_id || is_wp_error($att_id)) {
            fifu_plugin_log(['fifu_upload_image' => ['ERROR' => $post_id]]);
            return false;
        }
        $alt && update_post_meta($att_id, '_wp_attachment_image_alt', $alt);
        wp_update_post(array('ID' => $att_id, 'post_content' => $url));

        /* gallery */
        $error = false;
        $i = 0;
        $gallery = fifu_db_get_image_gallery_urls($post_id);
        $att_ids = '';
        foreach ($gallery as $item) {
            $meta_key_parts = explode('_', $item->meta_key);
            $id = $meta_key_parts[3] ?? '';
            $gal_url = $item->meta_value;
            if (!$gal_url)
                continue;
            $gal_alt = get_post_meta($post_id, 'fifu_image_alt_' . $id, true);
            $gal_att_id = fifu_upload_image($post_id, $gal_url, $gal_alt, false);
            if (!$gal_att_id || is_wp_error($gal_att_id)) {
                fifu_plugin_log(['fifu_upload_image' => ['ERROR' => $post_id]]);
                $error = true;
                break;
            }
            $gal_alt && update_post_meta($gal_att_id, '_wp_attachment_image_alt', $gal_alt);
            wp_update_post(array('ID' => $gal_att_id, 'post_content' => $gal_url));
            $att_ids .= ($i++ == 0) ? $gal_att_id : ',' . $gal_att_id;
        }

        if ($error)
            return false;
    } catch (Exception $e) {
        fifu_plugin_log(['fifu_upload_post' => ['ERROR' => $e->getMessage()]]);
        fifu_plugin_log(['fifu_upload_image' => ['ERROR' => $post_id]]);
        return false;
    }

    /* featured image */
    set_post_thumbnail($post_id, $att_id);
    delete_post_meta($post_id, 'fifu_image_url');
    delete_post_meta($post_id, 'fifu_image_alt');
    fifu_db_update_fake_attach_id($post_id);

    /* gallery */
    foreach ($gallery as $item) {
        $meta_key_parts = explode('_', $item->meta_key);
        $id = $meta_key_parts[3] ?? '';
        delete_post_meta($post_id, $item->meta_key);
        delete_post_meta($post_id, 'fifu_image_alt_' . $id);
    }
    update_post_meta($post_id, '_product_image_gallery', $att_ids);

    /* additional */
    $post_type = get_post_type($post_id);
    if ($post_type == 'product_variation')
        update_post_meta($post_id, '_wc_additional_variation_images', $att_ids);

    return true;
}

function fifu_upload_term($term_id) {
    $url = get_term_meta($term_id, 'fifu_image_url', true);
    $alt = get_term_meta($term_id, 'fifu_image_alt', true);
    if (!$url)
        return false;

    if (fifu_upload_skip_url($url))
        return false;

    try {
        $att_id = fifu_upload_image(null, $url, $alt, true);
        if (!$att_id || is_wp_error($att_id)) {
            fifu_plugin_log(['fifu_upload_image' => ['ERROR' => $term_id]]);
            return false;
        }
        $alt && update_post_meta($att_id, '_wp_attachment_image_alt', $alt);
        wp_update_post(array('ID' => $att_id, 'post_content' => $url));
        delete_term_meta($term_id, 'fifu_image_url');
        delete_term_meta($term_id, 'fifu_image_alt');
        fifu_db_ctgr_update_fake_attach_id($term_id);
        update_term_meta($term_id, 'thumbnail_id', $att_id);
    } catch (Exception $e) {
        fifu_plugin_log(['fifu_upload_term' => ['ERROR' => $e->getMessage()]]);
        fifu_plugin_log(['fifu_upload_image' => ['ERROR' => $term_id]]);
        return false;
    }

    return true;
}

function fifu_crop_image($att_id, $new_height, $post_id, $desc) {
    $sizes = wp_get_attachment_image_src($att_id, 'full');
    $width = $sizes[1] ?? null;
    $height = $sizes[2] ?? null;
    $path = wp_crop_image($att_id, 0, 0, $width, $new_height, $width, $new_height);

    $file_array = array();
    $file_array['name'] = date("Ymd-His") . '.jpg';
    $file_array['tmp_name'] = $path;
    if (is_wp_error($path)) {
        @unlink($file_array['name'] ?? '');
        return null;
    }
    $new_att_id = media_handle_sideload($file_array, $post_id, $desc);
    if (is_wp_error($new_att_id)) {
        @unlink($file_array['tmp_name'] ?? '');
        return $new_att_id;
    }
    wp_delete_attachment($att_id);
    return $new_att_id;
}

function fifu_resize_image($post_id, $att_id, $desc, $width) {
    $path = wp_get_original_image_path($att_id);

    $file_array = array();
    $file_array['name'] = date("Ymd-His") . '.jpg';
    $file_array['tmp_name'] = $path;

    $image = wp_get_image_editor($path, array());
    if (!is_wp_error($image)) {
        $image->resize($width, null, true);
        $image->save($path);
    }

    $new_att_id = media_handle_sideload($file_array, $post_id, $desc);
    if (is_wp_error($new_att_id)) {
        @unlink($file_array['tmp_name'] ?? '');
        return $new_att_id;
    }

    wp_delete_attachment($att_id);
    return $new_att_id;
}

function fifu_upload_captured_iframe($frame, $video_url, $time_frame) {
    $path = parse_url($video_url, PHP_URL_PATH);
    $file_name = basename($path);
    $extension = pathinfo($file_name, PATHINFO_EXTENSION);
    $new_name = str_replace('.' . $extension, "-fifu-{$time_frame}-" . $extension . '.webp', $file_name);
    $image_url = str_replace($file_name, $new_name, $video_url);

    $aux = explode('/', $path);
    $year = $aux[count($aux) - 3] ?? null;
    $month = $aux[count($aux) - 2] ?? null;

    $upload_dir = wp_upload_dir();
    $image_data = file_get_contents($frame);

    $upload_dir_path = ($upload_dir['basedir'] ?? '') . "/{$year}/{$month}";
    if (wp_mkdir_p($upload_dir_path))
        $file = "{$upload_dir_path}/{$new_name}";
    else
        $file = ($upload_dir['basedir'] ?? '') . "/{$new_name}";

    $atts = fifu_upload_find_frames(sanitize_file_name($new_name), $time_frame);
    foreach ($atts as $att) {
        wp_delete_attachment($att->ID);
    }

    file_put_contents($file, $image_data);

    $wp_filetype = wp_check_filetype($new_name, null);
    $attachment = array(
        'post_mime_type' => $wp_filetype['type'] ?? '',
        'post_title' => sanitize_file_name($new_name),
        'post_content' => '',
        'post_status' => 'inherit'
    );
    $att_id = wp_insert_attachment($attachment, $file);
    $attach_data = wp_generate_attachment_metadata($att_id, $file);
    wp_update_attachment_metadata($att_id, $attach_data);

    $queryParams = http_build_query([
        'site' => fifu_get_home_url(),
        'partial_key' => fifu_partial_key(),
        'video_url' => $video_url,
        'image_url' => $image_url
    ]);
    $workerUrl = "https://oembed-local.fifu.workers.dev?" . $queryParams;
    $response = wp_remote_get($workerUrl);

    fifu_db_delete_video_oembed_by_video_url($video_url);
    fifu_db_insert_video_oembed($video_url, $image_url, $video_url);
}

function fifu_upload_find_frames($new_name, $time_frame) {
    global $wpdb;
    $title_pattern = str_replace("-fifu-{$time_frame}-", "-fifu-%", $new_name);
    $query = "
        SELECT * FROM {$wpdb->posts}
        WHERE post_title LIKE %s
        AND post_type = 'attachment'
    ";
    return $wpdb->get_results($wpdb->prepare($query, $title_pattern));
}

function fifu_upload_skip_url($url) {
    if (strpos($url, ".fifu.app") !== false)
        return true;

    $domains = get_option('fifu_upload_domain');
    if ($domains) {
        $skip = true;
        $domains = explode(',', $domains);
        foreach ($domains as $domain) {
            if (strpos($url, $domain) !== false) {
                $skip = false;
                break;
            }
        }
        return $skip;
    }
    return false;
}

function fifu_upload_video_thumbnail($video_url, $thumb_url) {
    require_once(ABSPATH . '/wp-load.php');
    require_once(ABSPATH . '/wp-admin/includes/image.php');
    require_once(ABSPATH . '/wp-admin/includes/file.php');
    require_once(ABSPATH . '/wp-admin/includes/media.php');

    $tmp = download_url($thumb_url);
    $name = null;

    if (!$tmp) {
        fifu_plugin_log(['fifu_upload_video_thumbnail' => ['Failed to download URL' => $thumb_url]]);
        return null;
    }

    if (is_wp_error($tmp)) {
        fifu_plugin_log(['fifu_upload_video_thumbnail' => ['ERROR' => $tmp->get_error_message()]]);
        return null;
    }

    if (fifu_is_googledrive_video($video_url)) {
        $name = fifu_googledrive_id($video_url) . '.jpg';
        $slug = 'googledrive';
    } elseif (fifu_is_mega_video($video_url)) {
        $name = fifu_mega_id($video_url) . '.jpg';
        $name = str_replace('#', '-', $name);
        $slug = 'mega';
    }

    if (!$name)
        return null;

    $upload_dir = wp_upload_dir();
    $custom_subdir = "/fifu/videothumb/{$slug}";

    $custom_dir = ($upload_dir['basedir'] ?? '') . $custom_subdir;
    if (!file_exists($custom_dir))
        wp_mkdir_p($custom_dir);

    $file_contents = file_get_contents($tmp);
    $path = "{$custom_dir}/{$name}";
    file_put_contents($path, $file_contents);

    @unlink($tmp);

    return ($upload_dir['baseurl'] ?? '') . $custom_subdir . '/' . $name;
}

