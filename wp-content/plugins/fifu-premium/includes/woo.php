<?php

function fifu_woo_zoom() {
    return fifu_is_on('fifu_wc_zoom') ? 'inline' : 'none';
}

function fifu_woo_lbox() {
    return fifu_is_on('fifu_wc_lbox');
}

function fifu_woo_theme() {
    return file_exists(get_template_directory() . '/woocommerce');
}

# https://docs.woocommerce.com/document/image-sizes-theme-developers/

function fifu_woo_get_image_size() {
    if (class_exists('WooCommerce')) {
        if (is_shop())
            return wc_get_image_size('woocommerce_get_image_size_woocommerce_thumbnail');
        if (is_product())
            return wc_get_image_size('woocommerce_get_image_size_woocommerce_single');
    }
}

function fifu_woo_template_override($template, $slug) {
    global $post;

    $product_page = array('single-product/product-image.php');

    if (fifu_is_on('fifu_gallery') && $post && class_exists('WooCommerce') && is_product() && !fifu_is_elementor_editor()) {
        if (in_array($slug, $product_page)) {
            // if (fifu_is_yith_woocommerce_badges_management_active())
            echo apply_filters('woocommerce_single_product_image_thumbnail_html', '', $post->ID);
            return FIFU_INCLUDES_DIR . '/template.php';
        }
    }
    return $template;
}

add_filter('wc_get_template', 'fifu_woo_template_override', 99, 2);

function fifu_in_gallery($att_id) {
    $att_post = get_post($att_id);
    $post_parent = get_post($att_post->post_parent ?? null);
    if (!isset($post_parent->ID))
        return false;
    $gallery_ids = get_post_meta($post_parent->ID, '_product_image_gallery', true);
    if ($gallery_ids)
        $gallery_ids = array_filter(explode(',', $gallery_ids));
    if (is_array($gallery_ids))
        return in_array($att_id, $gallery_ids);
    return false;
}

add_action('woocommerce_product_duplicate', 'fifu_woocommerce_product_duplicate', 10, 1);

function fifu_woocommerce_product_duplicate($array) {
    if (!$array || !$array->get_meta_data())
        return;

    $post_id = $array->get_id();
    foreach ($array->get_meta_data() as $meta_data) {
        $data = $meta_data->get_data();
        if (in_array($data['key'] ?? '', array('fifu_image_url', 'fifu_video_url', 'fifu_slider_image_url_0'))) {
            delete_post_meta($post_id, '_thumbnail_id');
        } else if (
                (strpos($data['key'] ?? '', 'fifu_image_url_') !== false) ||
                (strpos($data['key'] ?? '', 'fifu_video_url_') !== false) ||
                (strpos($data['key'] ?? '', 'fifu_slider_image_url_') !== false)) {
            delete_post_meta($post_id, '_product_image_gallery');
        }
    }
}

function fifu_gallery_get_html($post_id, $original_class, $gallery_class, $gallery_css) {
    global $FIFU_SESSION;

    /* theme: Furnicom */
    if (isset($_GET['variation']))
        $post_id = $_GET['variation'];

    $ratio = get_post_meta($post_id, 'fifu_slider_ratio', true);
    $ratio = $ratio ? 'fifu-ratio="' . $ratio . '"' : '';

    $class = "fifu " . $original_class;

    $attribute_map = array();
    $url_map = array();
    $srcset_map = array();

    // variable products
    $attributes = fifu_db_get_variation_attributes($post_id);
    if ($attributes) {
        foreach ($attributes as $attribute) {
            if (!isset($attribute_map[$attribute->meta_key])) {
                $attribute_map[$attribute->meta_key] = array();
            }
            if (!isset($attribute_map[$attribute->meta_key][$attribute->meta_value])) {
                $attribute_map[$attribute->meta_key][$attribute->meta_value] = array();
            }
            array_push($attribute_map[$attribute->meta_key][$attribute->meta_value], $attribute->post_id);

            if (!isset($url_map[$attribute->post_id])) {
                $aux = fifu_db_get_featured_and_gallery_urls($attribute->post_id);
                if ($aux) {
                    $urls = $aux[0]->urls ?? '';
                    $tmp = $urls ? explode('|', $urls) : array();
                    for ($i = 0; $i < sizeof($tmp); $i++) {
                        $tmp[$i] = fifu_get_cdn_url($tmp[$i], get_post_thumbnail_id($attribute->post_id), null, null);
                        $url = $tmp[$i];
                    }
                    $url_map[$attribute->post_id] = $tmp;
                }
            }
        }
    }

    $gallery_css = $gallery_css ? 'style="' . $gallery_css . '"' : '';

    $html = sprintf('<div class="fifu-slider %s" id="fifu-slider-%s" %s %s>', $gallery_class, $post_id, $ratio, $gallery_css);
    if (fifu_is_on('fifu_slider_counter'))
        $html = $html . '<div style="font-size:12px; padding:2px 5px 2px 5px; background:rgba(0, 0, 0, 0.3); z-index:50; position:absolute; color:white" id="counter-slider"></div>';
    $html = $html . '<ul id="image-gallery" class="gallery list-unstyled cS-hidden fifu-product-gallery">';

    $att_id = get_post_meta($post_id, '_thumbnail_id', true);
    $url = fifu_get_full_image_url($att_id);
    $url = fifu_get_cdn_url($url, $att_id, null, null);
    $urls = array($url);
    $image_urls = array();
    $video_urls = array();

    $alt = get_post_meta($post_id, 'fifu_image_alt', true);
    if (!$alt)
        $alt = strip_tags(get_the_title($post_id));

    $att_ids = get_post_meta($post_id, '_product_image_gallery', true);
    if ($att_ids) {
        $att_ids = array_filter(explode(',', $att_ids));
        foreach ($att_ids as $att_id) {
            $url = fifu_get_full_image_url($att_id);
            $original = $url;
            $url = fifu_get_cdn_url($url, $att_id, null, null);
            if (fifu_is_video_thumb($original)) {
                array_push($video_urls, $url);
                $FIFU_SESSION['fifu-video'][$original] = fifu_video_src_by_img($original);
            } else
                array_push($image_urls, $url);
        }
    }

    if (fifu_is_on("fifu_videos_before")) {
        $urls = array_merge($urls, $video_urls);
        $urls = array_merge($urls, $image_urls);
    } else {
        $urls = array_merge($urls, $image_urls);
        $urls = array_merge($urls, $video_urls);
    }

    // urls of parent product
    $url_map[$post_id] = $urls;

    // js
    wp_enqueue_script('fifu-variable-js', plugins_url('/html/js/variable.js', __FILE__), array('jquery'), fifu_version_number_enq());
    wp_localize_script('fifu-variable-js', 'fifuVariableVars', [
        'attribute_map' => $attribute_map,
        'url_map' => $url_map,
        'srcset_map' => $srcset_map,
        'post_id' => $post_id,
        'fifu_video' => fifu_is_on('fifu_video'),
        'fifu_variations_merge' => fifu_is_on('fifu_variations_merge'),
    ]);

    $urls = get_filtered_urls_by_selected_variations($post_id, $url_map, $attribute_map);

    $i = -1;
    $i_video = null;
    $i_slider = 0;
    $video_url = null;
    foreach ($urls as $url) {
        $i++;
        $error_url = get_option('fifu_error_url');
        $original = $FIFU_SESSION['cdn-new-old'][$url] ?? $url;

        // get video URL
        if (fifu_is_video_thumb($url) || fifu_is_video_thumb($original)) {
            if (is_null($i_video)) {
                $video_url = get_post_meta($post_id, 'fifu_video_url', true);
                if (!$video_url) {
                    $video_url = get_post_meta($post_id, 'fifu_video_url_0', true);
                    $i_video = 1;
                } else
                    $i_video = 0;
            } else {
                do {
                    $video_url = get_post_meta($post_id, 'fifu_video_url_' . $i_video++, true);
                } while ($video_url && !fifu_is_video($video_url));
            }

            if (!$video_url && fifu_is_on('fifu_slider')) {
                do {
                    $slider_image = get_post_meta($post_id, 'fifu_slider_image_url_' . $i_slider++, true);
                    if ($slider_image && strpos($slider_image, $original) !== false && strpos($slider_image, '#http') !== false) {
                        $aux = explode('#http', $slider_image);
                        $video_url = $aux[0] ?? null;
                    }
                } while (!$video_url && $slider_image);
            }
        }

        if ($url) {
            if (fifu_is_from_speedup($url)) {
                $signed_url = fifu_speedup_get_signed_url($url, 128, 128, null, null, false);
                $set = fifu_speedup_get_set($url);

                if (fifu_is_video($url)) {
                    $html = $html . sprintf(
                                    '<li data-thumb="%s" data-src="%s" data-srcset="%s" data-poster="%s"><img class="%s" onerror="%s" alt="%s"/></li>',
                                    esc_url($signed_url),
                                    esc_url($video_url),
                                    esc_attr($set),
                                    esc_url($url),
                                    esc_attr($original_class),
                                    "jQuery(this).hide();",
                                    esc_attr($alt)
                            );
                    continue;
                }

                $sizes = fifu_speedup_get_sizes($url);
                $html = $html . sprintf(
                                '<li data-thumb="%s" data-src="%s" data-srcset="%s"><img src="%s" class="%s" onerror="%s" alt="%s"/></li>',
                                esc_url($signed_url),
                                esc_url(FIFU_PLACEHOLDER),
                                esc_attr($set),
                                esc_url(fifu_speedup_get_signed_url($url, $sizes[0] ?? 0, $sizes[1] ?? 0, null, null, false)),
                                esc_attr("fifu {$original_class}"),
                                "jQuery(this).hide();",
                                esc_attr($alt)
                        );
                continue;
            }

            if ($i == 0) {
                $custom_video_url = get_post_meta($post_id, 'fifu_custom_video_url', true);
                $audio_url = get_post_meta($post_id, 'fifu_audio_url', true);
                $iframe_url = "";

                if ($custom_video_url)
                    $video_url = $custom_video_url;

                if ($audio_url)
                    $video_url = $audio_url;
            } else {
                $custom_video_url = null;
                $audio_url = null;
                $iframe_url = get_post_meta($post_id, 'fifu_image_ifm_' . ($i - 1), true);
            }

            if (fifu_is_video_thumb($original) || $custom_video_url || $audio_url) {
                $type = 'data-src';
                $data_video = '';

                // for video files
                if (fifu_is_local_video($video_url) || fifu_is_amazon_video($video_url) || fifu_is_wpcom_video($video_url) || $custom_video_url || $audio_url) {
                    $type = 'data-video';
                    $poster = '';
                    if (fifu_is_local_video($video_url)) {
                        $extension = pathinfo($video_url, PATHINFO_EXTENSION);
                        $file_type = "video/{$extension}";
                    } else {
                        if ($audio_url) {
                            $file_type = 'audio/mpeg';
                            $poster = ", \"poster\":\"{$url}\", \"style\": \"object-fit:cover\"";
                        } else {
                            $file_type = fifu_is_amazon_video($video_url) || fifu_is_wpcom_video($video_url) || $custom_video_url ? 'video/mp4' : 'video';
                        }
                    }
                    $data_video = '{"source": [{"src":"' . $video_url . '", "type":"' . $file_type . '"}], "attributes": {"preload": false, "controls": true' . $poster . '}}';
                }

                // for unsupported videos
                if (fifu_is_googledrive_video($video_url))
                    $video_url = fifu_googledrive_src($video_url);
                elseif (fifu_is_mega_video($video_url))
                    $video_url = fifu_mega_src($video_url);

                $aux = $url;
                if (isset($FIFU_SESSION['cdn-new-old'][$url])) {
                    if (fifu_is_odysee_thumb($url)) {
                        $parts = explode('/plain/', $FIFU_SESSION['cdn-new-old'][$url]);
                        $aux = $parts[1] ?? $aux;
                    } else
                        $aux = $FIFU_SESSION['cdn-new-old'][$url];
                }
                $FIFU_SESSION['fifu-video'][$aux] = fifu_video_src($video_url);

                $image_url = fifu_db_get_image_url_by_video_url($video_url);
                $att_id = fifu_db_get_att_id($post_id, $image_url, false);
                $metadata = wp_get_attachment_metadata($att_id);
                if ($metadata) {
                    $width = $metadata['width'] ?? 0;
                    $height = $metadata['height'] ?? 0;
                    $data_lg_size = "data-lg-size=\"{$width}-{$height}\"";
                } else
                    $data_lg_size = '';

                $html = $html . sprintf(
                                '<li %s data-thumb="%s" %s=\'%s\' data-poster="%s"><img src="%s" class="img-responsive%s" onerror="%s" alt="%s"/></li>',
                                $data_lg_size,
                                esc_url($url),
                                $type,
                                esc_attr($type == 'data-video' ? $data_video : $video_url),
                                esc_url($audio_url ? '' : $url),
                                esc_url($url),
                                $class ? ' ' . esc_attr($class) : '',
                                $error_url ? sprintf("this.src='%s'", esc_url($error_url)) : "",
                                esc_attr($alt)
                        );
            } else {
                $url_thumb = null;
                if (get_option('fifu_square_mobile') || get_option('fifu_square_desktop')) {
                    $url_thumb = fifu_get_cdn_url($url, $att_id, 150, 1);
                    $url = fifu_get_cdn_url($url, $att_id, 1920, 0);
                }

                $html = $html . sprintf(
                                '<li data-thumb="%s" data-src="%s" %s><img src="%s" class="%s" onerror="%s" alt="%s"/></li>',
                                esc_url($url_thumb ? $url_thumb : $url),
                                esc_url($iframe_url ? $iframe_url : $url),
                                $iframe_url ? 'data-iframe="true"' : '',
                                esc_url($url),
                                esc_attr($class),
                                $error_url ? sprintf("this.src='%s'", esc_url($error_url)) : "",
                                esc_attr($alt)
                        );
            }
        }
    }
    // add status
    $html = str_replace('<img ', '<img fifu-replaced="1" ', $html);
    return $html . '</ul></div>';
}

function fifu_get_cdn_url($url, $att_id, $width, $crop) {
    if (fifu_is_off('fifu_photon'))
        return $url;

    global $FIFU_SESSION;

    $args = fifu_is_on('fifu_otfcdn') ? fifu_get_photon_args($width, $crop) : null;

    $new_url = fifu_jetpack_photon_url($url, $args, $att_id);

    $FIFU_SESSION['cdn-new-old'][$new_url] = $url;

    return $new_url;
}

function fifu_woocommerce_email_order_items_table($output, $order) {
    if (fifu_is_off('fifu_order_email'))
        return $output;

    // set a flag so we don't recursively call this filter
    static $run = 0;

    // if we've already run this filter, bail out
    if ($run)
        return $output;

    $args = array(
        'show_image' => true,
        'image_size' => array(100, 100),
    );

    $run++;

    return wc_get_email_order_items($order, $args);
}

add_filter('woocommerce_email_order_items_table', 'fifu_woocommerce_email_order_items_table', 10, 2);

function fifu_on_products_page() {
    return strpos($_SERVER['REQUEST_URI'] ?? '', 'wp-admin/edit.php') !== false && strpos($_SERVER['REQUEST_URI'] ?? '', 'post_type=product') !== false;
}

function fifu_on_categories_page() {
    return strpos($_SERVER['REQUEST_URI'] ?? '', 'wp-admin/edit-tags.php?taxonomy=product_cat&post_type=product') !== false;
}

function fifu_get_pretty_variation_attributes_map($parent_product_id) {
    // Initialize an empty array to store the map
    $variation_map = [];

    // Get the parent product object
    $parent_product = wc_get_product($parent_product_id);

    // Check if it's a variable product
    if ($parent_product && $parent_product->is_type('variable')) {
        // Get the child variation IDs
        $variations = $parent_product->get_children();

        // Get the pretty names of the attributes
        $pretty_names = fifu_get_pretty_attribute_names($parent_product_id);

        $attributes = fifu_get_all_variation_attributes($variations);

        $pretty_names = filterPrettyNames($pretty_names, $attributes);

        foreach ($attributes as $variation_id => $attribute_values) {
            $mapped = [];
            foreach ($attribute_values as $key => $value) {
                // Strip 'attribute_' prefix for pretty name lookup
                $stripped_key = preg_replace('/^attribute_/', '', $key);
                if (isset($pretty_names[$stripped_key])) {
                    $mapped[$pretty_names[$stripped_key]] = $value;
                } else {
                    // Use stripped key as fallback instead of raw key
                    $mapped[$stripped_key] = $value;
                }
            }
            $variation_map[$variation_id] = $mapped;
        }
    }

    return $variation_map;
}

function filterPrettyNames($pretty_names, $attributes) {
    if (empty($attributes)) {
        return [];
    }

    // Get the first element of the attributes array
    $firstAttribute = reset($attributes);

    // Convert the keys of the first attribute to lowercase for case-insensitive comparison
    $firstAttributeLowerKeys = array_change_key_case($firstAttribute, CASE_LOWER);

    // Filter pretty names based on keys existing in the first attribute (case-insensitive)
    $filteredPrettyNames = array_filter($pretty_names, function ($key) use ($firstAttributeLowerKeys) {
        return array_key_exists('attribute_' . strtolower($key), $firstAttributeLowerKeys);
    }, ARRAY_FILTER_USE_KEY);

    return $filteredPrettyNames;
}

function fifu_get_all_variation_attributes($variation_ids) {
    global $wpdb;

    // Check if there are any variations
    if (empty($variation_ids)) {
        return [];
    }

    // Prepare SQL query
    $placeholders = implode(',', array_fill(0, count($variation_ids), '%d'));
    $sql = "SELECT post_id, meta_key, meta_value 
            FROM {$wpdb->postmeta} 
            WHERE post_id IN ($placeholders) 
              AND meta_key LIKE 'attribute_%'";

    // Execute the query
    $results = $wpdb->get_results($wpdb->prepare($sql, $variation_ids));

    // Organize attributes by variation ID
    $attributes = [];
    foreach ($results as $result) {
        $attributes[$result->post_id][$result->meta_key] = $result->meta_value;
    }

    return $attributes;
}

function fifu_get_pretty_attribute_names($product_id) {
    // Get the product attributes
    $attributes = get_post_meta($product_id, '_product_attributes', true);

    // Initialize an empty array to store the pretty names
    $pretty_names = [];

    if (is_array($attributes)) {
        // Iterate over the attributes
        foreach ($attributes as $attribute) {
            if (!($attribute['is_variation'] ?? false))
                continue;

            // Get the attribute name
            $name = $attribute['name'] ?? '';

            // Get the pretty name
            $pretty_name = wc_attribute_label($name);

            // Add to the array
            $pretty_names[$name] = $pretty_name;
        }
    }

    return $pretty_names;
}

function fifu_is_variable_product($post_id) {
    if (class_exists("WooCommerce")) {
        $product = wc_get_product($post_id);
        if ($product)
            return $product->get_type() == "variable";
    }
    return false;
}

function fifu_array_to_sorted_html_table($data, $post_id) {
    global $FIFU_SESSION;

    // Initialize an empty string to store the HTML table
    $html = '';

    // Determine the column names dynamically
    $firstItem = reset($data);
    $columns = $firstItem ? array_keys($firstItem) : array();
    if ($columns) {
        array_unshift($columns, 'ID');  // Add 'ID' as the first column
        array_push($columns, '<center><span class="dashicons dashicons-camera" style="font-size:20px; text-align:right"></span></center>');  // Add 'Image' as the last column
        // Sort the array based on the values in the inner arrays
        uasort($data, function ($a, $b) {
            foreach ($a as $key => $value) {
                if (isset($a[$key]) && isset($b[$key])) {
                    if ($a[$key] != $b[$key]) {
                        return $a[$key] <=> $b[$key];
                    }
                }
            }
            return 0;
        });
    }

    // Generate header row
    $html .= '<table id="fifu-variable-table" style="text-align:left; width:100%" post-parent="' . $post_id . '"><tbody>';
    $html .= '<tr class="color">';
    foreach ($columns as $col) {
        if (strpos($col, 'ID') !== false) {
            $html .= "<th style=\"width:64px\">$col</th>";
        } elseif (strpos($col, 'dashicons-camera') !== false) {
            $html .= "<th style=\"width:40px\">$col</th>";
        } else {
            $html .= "<th style=\"min-width:100px\">$col</th>";
        }
    }
    $html .= '</tr>';

    // Generate data rows
    foreach ($data as $id => $attributes) {
        $html .= '<tr class="color">';
        $html .= "<td>$id</td>";  // First column is the ID
        foreach ($columns as $col) {
            if ($col !== 'ID') {  // Skip the 'ID' column as it's already added
                if (strpos($col, 'dashicons-camera') !== false) {
                    // Add your image here. For example, using a placeholder image.
                    list($border, $height, $width, $video_url, $video_src, $is_ctgr, $is_variable, $image_url, $url, $vars) = fifu_column_featured($id, false);
                    $html .= "
                        <td>
                            <div
                                class=\"fifu-quick\"
                                post-id=\"{$id}\"
                                video-url=\"{$video_url}\"
                                video-src=\"{$video_src}\"
                                is-ctgr=\"{$is_ctgr}\"
                                image-url=\"{$image_url}\"
                                is-variable=\"{$is_variable}\"
                                style=\"height: {$height}px; width: {$height}px; background:url('{$url}') no-repeat center center; background-size:cover; {$border}; cursor:pointer;\">
                            </div>
                        </td>
                    ";
                    $FIFU_SESSION['fifu-quick-edit'][$id] = $vars;
                } else {
                    $html .= '<td>' . ($attributes[$col] ?? '') . '</td>';
                }
            }
        }
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';

    return $html;
}

function get_default_selected_variations($product_id) {
    $product = wc_get_product($product_id);
    if (!$product || !$product->is_type('variable'))
        return false;

    $defaults = $product->get_default_attributes();
    if (empty($defaults))
        return false;

    $selected_values = array();
    foreach ($defaults as $attribute_key => $attribute_value) {
        if ($attribute_value === '' || $attribute_value === null)
            continue;
        $attribute_key = strpos($attribute_key, 'attribute_') === 0 ? $attribute_key : 'attribute_' . $attribute_key;
        $selected_values[$attribute_key] = $attribute_value;
    }

    return empty($selected_values) ? false : $selected_values;
}

function get_filtered_urls_by_selected_variations($post_id, $url_map, $attribute_map) {
    $selected_variations = get_default_selected_variations($post_id);

    if (empty($selected_variations))
        return $url_map[$post_id] ?? array();

    $matching_variation_ids = array();
    foreach ($attribute_map as $attr_key => $values) {
        if (isset($selected_variations[$attr_key])) {
            $selected_value = $selected_variations[$attr_key];
            if (isset($values[$selected_value])) {
                if (empty($matching_variation_ids)) {
                    $matching_variation_ids = $values[$selected_value];
                } else {
                    $matching_variation_ids = array_intersect($matching_variation_ids, $values[$selected_value]);
                }
            }
        }
    }

    if (empty($matching_variation_ids))
        return $url_map[$post_id] ?? array();

    $filtered_urls = array();
    foreach ($matching_variation_ids as $variation_id) {
        if (isset($url_map[$variation_id])) {
            $filtered_urls = array_merge($filtered_urls, $url_map[$variation_id]);
        }
    }

    return array_unique($filtered_urls);
}

// function my_custom_thumbnail_size( $size ) {
//     // Change the width, height, and crop values as needed
//     $size['width'] = 110;  // Define your desired width
//     $size['height'] = 228; // Define your desired height
//     $size['crop'] = 0;     // Crop the image to fit the dimensions
//     return $size;
// }
// add_filter( 'woocommerce_get_image_size_thumbnail', 'my_custom_thumbnail_size' );
// function my_custom_single_image_size( $size ) {
//     $size['width'] = 600;  // Define your desired width
//     $size['height'] = 600; // Define your desired height
//     $size['crop'] = 1;     // Crop the image to fit the dimensions
//     return $size;
// }
// add_filter( 'woocommerce_get_image_size_single', 'my_custom_single_image_size' );
// function my_custom_gallery_thumbnail_size( $size ) {
//     $size['width'] = 150;  // Define your desired width
//     $size['height'] = 150; // Define your desired height
//     $size['crop'] = 1;     // Crop the image to fit the dimensions
//     return $size;
// }
// add_filter( 'woocommerce_get_image_size_gallery_thumbnail', 'my_custom_gallery_thumbnail_size' );
// function custom_mime_types($mime_types){
//     $mime_types['csv'] = 'text/csv'; // Adding .csv extension
//     return $mime_types;
// }
// add_filter('upload_mimes', 'custom_mime_types');

/* import */

add_filter('woocommerce_product_importer_formatting_callbacks', 'fifu_woocommerce_product_importer_formatting_callbacks', 10, 2);

function fifu_woocommerce_product_importer_formatting_callbacks($callbacks, $importer) {

    function fifu_no_op_callback($string) {
        return $string;
    }

    if (method_exists($importer, 'get_raw_keys')) {
        $raw_keys = $importer->get_raw_keys();

        foreach ($raw_keys as $keyIndex => $key) {
            if (strpos($key, 'fifu') === 0 && strpos($key, 'url') !== false) {
                if (isset($callbacks[$keyIndex]) && $callbacks[$keyIndex] === 'wp_kses_post') {
                    $callbacks[$keyIndex] = 'fifu_no_op_callback';
                }
            }
        }
    }

    return $callbacks;
}

function fifu_video_list_priority($object) {
    $meta_data = $object->get_meta_data();
    if (is_array($meta_data)) {
        foreach ($meta_data as $meta) {
            if (($meta->key ?? '') === 'fifu_list_url') {
                return false;
            }
            if (($meta->key ?? '') === 'fifu_list_video_url') {
                return true;
            }
        }
    }
    return false;
}

/**
 * Get variation URLs and all image URLs (featured + gallery) for a variable product
 *
 * @param int $product_id The parent variable product ID
 * @return array Array of variations with 'url' and 'images' (array of URLs)
 */
function fifu_get_variation_data($product_id) {
    $data = [];

    // Load the parent product
    $product = wc_get_product($product_id);

    // Only continue if it's a variable product
    if (!$product || !$product->is_type('variable')) {
        return $data;
    }

    // Get all variation IDs for this product
    $variation_ids = $product->get_children();

    foreach ($variation_ids as $variation_id) {
        $variation = wc_get_product($variation_id);

        if ($variation) {
            // Get variation page URL
            $variation_url = get_permalink($variation_id);

            // Collect all image IDs (featured + gallery)
            $image_ids = [];

            // Add featured image ID if available
            $featured_id = $variation->get_image_id();
            if ($featured_id) {
                $image_ids[] = $featured_id;
            }

            // Add gallery image IDs
            $gallery_ids = $variation->get_gallery_image_ids();
            if (!empty($gallery_ids)) {
                $image_ids = array_merge($image_ids, $gallery_ids);
            }

            // Convert IDs into URLs
            $image_urls = [];
            foreach ($image_ids as $id) {
                $url = wp_get_attachment_url($id);
                if ($url) {
                    $image_urls[] = $url;
                }
            }

            // Store result if we have at least one image
            if (!empty($image_urls)) {
                $data[$variation_id] = [
                    'url' => $variation_url,
                    'images' => $image_urls,
                ];
            }
        }
    }

    return $data;
}

