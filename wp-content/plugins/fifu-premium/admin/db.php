<?php

class FifuDb {

    private $wpdb;
    private $posts;
    private $options;
    private $postmeta;
    private $terms;
    private $termmeta;
    private $term_taxonomy;
    private $term_relationships;
    private $fifu_md5;
    private $fifu_video_oembed;
    private $fifu_meta_in;
    private $fifu_meta_out;
    private $fifu_import;
    private $fifu_content;
    private $fifu_invalid_media_su;
    private $aawp_lists;
    private $query;
    private $aawp_products;
    private $author;
    private $types;

    function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->posts = $wpdb->prefix . 'posts';
        $this->options = $wpdb->prefix . 'options';
        $this->postmeta = $wpdb->prefix . 'postmeta';
        $this->terms = $wpdb->prefix . 'terms';
        $this->termmeta = $wpdb->prefix . 'termmeta';
        $this->term_taxonomy = $wpdb->prefix . 'term_taxonomy';
        $this->term_relationships = $wpdb->prefix . 'term_relationships';
        $this->fifu_md5 = $wpdb->prefix . 'fifu_md5';
        $this->fifu_video_oembed = $wpdb->prefix . 'fifu_video_oembed';
        $this->fifu_meta_in = $wpdb->prefix . 'fifu_meta_in';
        $this->fifu_meta_out = $wpdb->prefix . 'fifu_meta_out';
        $this->fifu_import = $wpdb->prefix . 'fifu_import';
        $this->fifu_content = $wpdb->prefix . 'fifu_content';
        $this->fifu_invalid_media_su = $wpdb->prefix . 'fifu_invalid_media_su';
        $this->aawp_lists = $wpdb->prefix . 'aawp_lists';
        $this->aawp_products = $wpdb->prefix . 'aawp_products';
        $this->author = fifu_get_author();
        $this->types = $this->get_types();
    }

    function get_types(): string {
        $raw = (array) fifu_get_post_types();

        // Sanitize and validate against registered post types
        $registered = get_post_types([], 'names'); // array of valid names
        $safe = [];
        foreach ($raw as $pt) {
            $pt = sanitize_key($pt);
            if ($pt !== '' && isset($registered[$pt])) {
                $safe[] = $pt;
            }
        }
        // Deduplicate while preserving order
        $safe = array_values(array_unique($safe));
        return implode("','", $safe);
    }

    function sanitize_ids_csv($ids, bool $allow_zero = false): string {
        // Normalize $ids to an array
        if (is_string($ids)) {
            $ids = explode(',', $ids);
        } elseif (is_int($ids)) {
            $ids = [$ids];
        } elseif (!is_array($ids)) {
            $ids = [];
        }

        $set = [];
        foreach ($ids as $id) {
            if (is_int($id)) {
                $n = $id;
            } elseif (is_string($id)) {
                $id = trim($id);
                if ($id === '' || !ctype_digit($id)) { // digits only
                    continue;
                }
                $n = (int) $id; // safe after ctype_digit
            } else {
                continue;
            }

            if ($n > 0 || ($allow_zero && $n === 0)) {
                $set[$n] = true; // dedupe
            }
        }

        if (!$set) {
            return '0'; // ensures valid "IN (0)" => no matches
        }

        return implode(',', array_keys($set));
    }

    // Sanitize a list of post types (array or CSV string) for safe IN (...) usage
    function sanitize_post_types_list($post_types) {
        // Normalize input to array
        if (is_string($post_types)) {
            $post_types = explode(',', str_replace(['"', "'"], '', $post_types));
        } elseif (!is_array($post_types)) {
            $post_types = [];
        }

        // Whitelist of registered post types
        $registered = array_flip(get_post_types([], 'names'));

        // Sanitize + dedupe
        $set = [];
        foreach ($post_types as $pt) {
            $pt = sanitize_key(trim((string) $pt)); // [a-z0-9_-], lowercased
            if ($pt === '' || !isset($registered[$pt])) {
                continue;
            }
            $set[$pt] = true;
        }

        if (!$set) {
            // If used in a class context that defines $this->types, keep compatibility
            if (isset($this) && isset($this->types) && is_string($this->types) && $this->types !== '') {
                return $this->types;
            }
            // Safe default: match nothing
            return "''";
        }

        $items = array_keys($set);
        // sanitize_key already guarantees safe charset; quoting is enough for IN (...)
        return "'" . implode("','", $items) . "'";
    }

    function build_in_from_option_csv(string $base_key, string $option_name): array {
        $field = (string) get_option($option_name);

        $keys = [$base_key];
        if ($field !== '') {
            foreach (explode(',', $field) as $k) {
                $k = trim($k);
                if ($k !== '')
                    $keys[] = $k;
            }
        }
        $keys = array_values(array_unique($keys));

        $in = implode(',', array_fill(0, count($keys), '%s')); // e.g. ['fifu_isbn','custom1'] -> IN ('fifu_isbn','custom1')
        return [$in, $keys];
    }

    /* deprecated data */

    function delete_deprecated_options() {
        // Collect option names that will be deleted so we can evict them from object cache
        $in_list = "'fifu_cpt0','fifu_cpt1','fifu_cpt2','fifu_cpt3','fifu_cpt4','fifu_cpt5','fifu_cpt6','fifu_cpt7','fifu_cpt8','fifu_cpt9','fifu_data_generation','fifu_debug_mode','fifu_fake2','fifu_priority','fifu_update_all_id','fifu_update_all_status','fifu_update_all_timestamp','fifu_update_number','fifu_wc_theme','fifu_max_url','fifu_variation_attach_id_0','fifu_variation_attach_id_1','fifu_variation_attach_id_2','fifu_variation_attach_id_3','fifu_variation_attach_id_4','fifu_variation_attach_id_5','fifu_variation_attach_id_6','fifu_variation_attach_id_7','fifu_variation_attach_id_8','fifu_variation_attach_id_9','fifu_default_width','fifu_video_margin_bottom','fifu_video_vertical_margin','fifu_video_width_rtio','fifu_video_height_arch','fifu_video_height_ctgr','fifu_video_height_home','fifu_video_height_page','fifu_video_height_post','fifu_video_height_prod','fifu_video_height_shop','fifu_video_width_arch','fifu_video_width_ctgr','fifu_video_width_home','fifu_video_width_page','fifu_video_width_post','fifu_video_width_prod','fifu_video_width_shop','fifu_variation_gallery','fifu_video_height','fifu_video_crop','fifu_shortcode_max_height','fifu_image_height_shop','fifu_image_width_shop','fifu_image_height_prod','fifu_image_width_prod','fifu_image_height_cart','fifu_image_width_cart','fifu_image_height_ctgr','fifu_image_width_ctgr','fifu_image_height_arch','fifu_image_width_arch','fifu_image_height_home','fifu_image_width_home','fifu_image_height_page','fifu_image_width_page','fifu_image_height_post','fifu_image_width_post','fifu_parameters','fifu_slider_fade','fifu_flickr_post','fifu_flickr_page','fifu_flickr_arch','fifu_flickr_cart','fifu_flickr_ctgr','fifu_flickr_home','fifu_flickr_prod','fifu_flickr_shop','fifu_original','fifu_save_dimensions','fifu_save_dimensions_redirect','fifu_save_dimensions_all','fifu_clean_dimensions_all','fifu_css','fifu_jquery','fifu_class','fifu_shortcode_min_width','fifu_media_library','fifu_auto_set_blocked','fifu_isbn_blocked','fifu_shortpixel','fifu_video_black','fifu_flickr','fifu_giphy','fifu_video_related','fifu_unsplash_size','fifu_spinner_slider','fifu_spinner_video','fifu_spinner_image','fifu_valid','fifu_column_height','fifu_spinner_db','fifu_spinner_cron_metadata','fifu_confirm_delete_all','fifu_confirm_delete_all_time','fifu_gallery_selector','fifu_video_gallery_icon','fifu_hover','fifu_hover_selector','fifu_shortcode','fifu_grid_category','fifu_rss_width','fifu_bbpress_avatar','fifu_bbpress_copy','fifu_screenshot_high','fifu_screenshot_height','fifu_screenshot_scale','fifu_sizes','fifu_cdn_crop','fifu_cdn_social','fifu_mouse_vimeo','fifu_mouse_youtube','fifu_api_key_youtube','fifu_api_key_googledrive','fifu_bearer_token_twitter','fifu_ck','fifu_chk','fifu_install','fifu_auto_alt','fifu_variation','fifu_social_image_only','fifu_pop_first','fifu_content','fifu_content_page','fifu_content_cpt','fifu_query_strings','fifu_decode','fifu_spinner_nth','fifu_check','fifu_video_priority','fifu_update_ignore','fifu_video_list_priority','fifu_dynamic_alt','fifu_rss','fifu_social_home_url','fifu_social','fifu_lazy','fifu_hide_page','fifu_hide_post','fifu_hide_cpt','fifu_fake_created','fifu_su_always_connected','fifu_cloak','fifu_same_size','fifu_crop_delay','fifu_crop_ignore_parent','fifu_crop_default','fifu_fit','fifu_crop_ratio','fifu_crop0','fifu_crop1','fifu_crop2','fifu_crop3','fifu_crop4','fifu_video_play_draw','fifu_proxies','fifu_auto_set_license','fifu_square'";

        // Fetch names that match the three delete conditions
        $names_in = $this->wpdb->get_col("SELECT option_name FROM {$this->options} WHERE option_name IN ({$in_list})");
        $names_cors = $this->wpdb->get_col("SELECT option_name FROM {$this->options} WHERE option_name LIKE 'fifu_cors_proxy_%'");
        $names_session = $this->wpdb->get_col("SELECT option_name FROM {$this->options} WHERE option_name LIKE 'fifu_session_%'");

        // Perform deletes (keeps existing behaviour)
        $this->wpdb->query("
            DELETE FROM {$this->options} 
            WHERE option_name IN ({$in_list})
        ");
        $this->wpdb->query("
            DELETE FROM {$this->options} 
            WHERE option_name LIKE 'fifu_cors_proxy_%'
        ");
        $this->wpdb->query("
            DELETE FROM {$this->options} 
            WHERE option_name LIKE 'fifu_session_%'
        ");

        // Evict each option from object cache
        $all = array_unique(array_merge((array) $names_in, (array) $names_cors, (array) $names_session));
        foreach ($all as $opt) {
            if ($opt !== null && $opt !== '') {
                wp_cache_delete($opt, 'options');
            }
        }
    }

    /* attachment metadata */

    // delete 1 _wp_attached_file or _wp_attachment_image_alt for each attachment
    function delete_attachment_meta($ids, $is_ctgr) {
        $ctgr_sql = $is_ctgr ? "AND p.post_name LIKE 'fifu-category%'" : "";
        $ids_csv = $this->sanitize_ids_csv($ids);
        $author = $this->author;
        $sql = "
            DELETE pm
            FROM {$this->postmeta} pm JOIN {$this->posts} p ON pm.post_id = p.id
            WHERE pm.meta_key IN ('_wp_attached_file', '_wp_attachment_image_alt', '_wp_attachment_metadata')
            AND p.post_parent IN ({$ids_csv})
            AND p.post_author = %d 
            {$ctgr_sql}
        ";
        $this->wpdb->query($this->wpdb->prepare($sql, $author));
    }

    // has attachment created by FIFU
    function is_fifu_attachment($att_id) {
        $sql = $this->wpdb->prepare(
                "SELECT 1 FROM {$this->posts} WHERE id = %d AND post_author = %d",
                (int) $att_id,
                $this->author
        );
        return $this->wpdb->get_row($sql) != null;
    }

    function has_fifu_attachment($att_ids) {
        $ids_csv = $this->sanitize_ids_csv($att_ids);
        $sql = $this->wpdb->prepare(
                "SELECT 1 FROM {$this->posts} WHERE id IN ({$ids_csv}) AND post_author = %d",
                $this->author
        );
        return $this->wpdb->get_row($sql) != null;
    }

    // get ids from categories with external media and no thumbnail_id
    function get_categories_without_meta() {
        return $this->wpdb->get_results("
            SELECT DISTINCT term_id as post_id
            FROM {$this->termmeta} a
            WHERE a.meta_key IN ('fifu_image_url', 'fifu_video_url')
            AND a.meta_value IS NOT NULL 
            AND a.meta_value <> ''
            AND NOT EXISTS (
                SELECT 1 
                FROM {$this->termmeta} b 
                WHERE a.term_id = b.term_id 
                AND (
                    (b.meta_key = 'thumbnail_id' AND b.meta_value <> 0)
                    OR b.meta_key IN ('fifu_metadataterm_sent')
                )
            )
        ");
    }

    // get ids from posts with external media and no _thumbnail_id
    function get_posts_without_meta() {
        return $this->wpdb->get_results("
            SELECT DISTINCT post_id
            FROM {$this->postmeta} a
            WHERE a.meta_key IN ('fifu_image_url', 'fifu_video_url', 'fifu_slider_image_url_0')
            AND a.meta_value IS NOT NULL 
            AND a.meta_value <> ''
            AND NOT EXISTS (
                SELECT 1 
                FROM (SELECT post_id FROM {$this->postmeta} WHERE meta_key = '_thumbnail_id') AS b
                WHERE a.post_id = b.post_id 
            )
        ");
    }

    // get ids from posts with external media and no _thumbnail_id or _product_image_gallery
    function get_all_posts_without_meta() {
        return $this->wpdb->get_results("
            SELECT DISTINCT post_id
            FROM {$this->postmeta} a
            WHERE 
            (
                (
                    (
                        a.meta_key LIKE 'fifu_image_url_%'
                        OR a.meta_key LIKE 'fifu_video_url_%'
                        OR a.meta_key LIKE 'fifu_slider_image_url_%'
                        OR (
                            a.meta_key IN ('fifu_list_url', 'fifu_list_video_url', 'fifu_slider_list_url')
                            AND a.meta_value LIKE '%|http%'
                        )
                    )
                    AND NOT EXISTS (
                        SELECT 1 
                        FROM {$this->postmeta} b 
                        WHERE a.post_id = b.post_id 
                        AND b.meta_key IN ('_product_image_gallery', 'fifu_metadatapost_sent')
                    )
                )
                OR
                (
                    a.meta_key IN ('fifu_image_url', 'fifu_video_url', 'fifu_slider_image_url_0', 'fifu_list_url', 'fifu_list_video_url')
                    AND NOT EXISTS (
                        SELECT 1 
                        FROM {$this->postmeta} b 
                        WHERE a.post_id = b.post_id 
                        AND (
                            (b.meta_key = '_thumbnail_id' AND b.meta_value <> 0)
                            OR b.meta_key IN ('fifu_metadatapost_sent')
                        )
                    )
                )
            )
            AND a.meta_value IS NOT NULL 
            AND a.meta_value <> ''
        ");
    }

    // get thumbnail_id from category
    function get_category_thumbnail_id($term_id) {
        $sql = $this->wpdb->prepare(
                "SELECT meta_value FROM {$this->termmeta} WHERE term_id = %d AND meta_key = 'thumbnail_id'",
                (int) $term_id
        );
        return $this->wpdb->get_row($sql);
    }

    function get_posts_types_with_url_to_upload() {
        $domains = get_option('fifu_upload_domain');
        $tokens = is_array($domains) ? $domains : explode(',', (string) $domains);

        $likes = [];
        $params = [];

        foreach ($tokens as $domain) {
            $domain = trim((string) $domain);
            if ($domain === '')
                continue;
            $likes[] = 'pm.meta_value LIKE %s';
            $params[] = '%' . $this->wpdb->esc_like($domain) . '%';
        }

        $domain_sql = $likes ? ' AND (' . implode(' OR ', $likes) . ')' : '';

        $sql = "
            SELECT DISTINCT pm.post_id, p.post_type
            FROM {$this->postmeta} pm
            INNER JOIN {$this->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = 'fifu_image_url'
            {$domain_sql}
            AND NOT EXISTS (
                SELECT 1
                FROM {$this->postmeta} pm2
                WHERE pm2.post_id = pm.post_id
                  AND pm2.meta_key = 'fifu_uploadpost_sent'
            )
            LIMIT 1000
        ";

        if ($params) {
            $sql = $this->wpdb->prepare($sql, $params);
        }

        return $this->wpdb->get_results($sql);
    }

    function get_terms_with_url_to_upload() {
        $domains = get_option('fifu_upload_domain');
        $tokens = is_array($domains) ? $domains : explode(',', (string) $domains);

        // Normalize & dedupe domains
        $uniq = [];
        foreach ($tokens as $d) {
            $d = trim((string) $d);
            if ($d !== '') {
                $uniq[$d] = true;
            }
        }

        // Build optional domain filter with a single prepare()
        $likes = [];
        $params = [];
        foreach (array_keys($uniq) as $domain) {
            $likes[] = 'tm.meta_value LIKE %s';
            $params[] = '%' . $this->wpdb->esc_like($domain) . '%';
        }

        $domain_sql = $likes ? ' AND (' . implode(' OR ', $likes) . ')' : '';

        $sql = "
            SELECT DISTINCT tm.term_id AS post_id
            FROM {$this->termmeta} tm
            WHERE tm.meta_key IN ('fifu_image_url','fifu_video_url')
            {$domain_sql}
            AND NOT EXISTS (
                SELECT 1
                FROM {$this->termmeta} tm2
                WHERE tm2.term_id = tm.term_id
                  AND tm2.meta_key = 'fifu_uploadterm_sent'
            )
            LIMIT 1000
        ";

        if ($params) {
            $sql = $this->wpdb->prepare($sql, $params);
        }

        return $this->wpdb->get_results($sql);
    }

    // get ids from posts with external gallery
    function get_posts_with_external_gallery_without_meta() {
        return $this->wpdb->get_results("
            SELECT DISTINCT post_id 
            FROM {$this->postmeta} a 
            WHERE (
                a.meta_key LIKE 'fifu_image_url_%'
                OR a.meta_key LIKE 'fifu_video_url_%'
                OR a.meta_key LIKE 'fifu_slider_image_url_%'
            )
            AND a.meta_value IS NOT NULL 
            AND a.meta_value <> ''
            AND NOT EXISTS (
                SELECT 1 
                FROM (SELECT post_id FROM {$this->postmeta} WHERE meta_key IN ('_product_image_gallery', '_wc_additional_variation_images')) AS b
                WHERE a.post_id = b.post_id 
            )
        ");
    }

    // get urls from external gallery
    function get_gallery_urls($post_id) {
        $sql = $this->wpdb->prepare(
                "SELECT meta_value, meta_key
            FROM {$this->postmeta} a
            WHERE a.post_id = %d
            AND (
                a.meta_key LIKE 'fifu_image_url_%'
                OR a.meta_key LIKE 'fifu_video_url_%'
                OR (
                    a.meta_key LIKE 'fifu_slider_image_url_%'
                    AND a.meta_key <> 'fifu_slider_image_url_0' 
                )
            )
            AND a.meta_value <> ''
            ORDER BY meta_key",
                (int) $post_id
        );
        return $this->wpdb->get_results($sql);
    }

    // get alts from external gallery
    function get_gallery_alts($post_id) {
        $sql = $this->wpdb->prepare(
                "SELECT meta_value, meta_key
                FROM {$this->postmeta} a
                WHERE a.post_id = %d
                AND (
                    a.meta_key LIKE 'fifu_image_alt_%'
                )
                AND a.meta_value <> ''
                ORDER BY meta_key",
                (int) $post_id
        );
        return $this->wpdb->get_results($sql);
    }

    // get urls from slider
    function get_slider_urls($post_id) {
        $sql = $this->wpdb->prepare(
                "SELECT meta_value, meta_key
            FROM {$this->postmeta} a
            WHERE a.post_id = %d
            AND a.meta_key LIKE 'fifu_slider_image_url_%'
            AND a.meta_value <> ''
            ORDER BY meta_key",
                (int) $post_id
        );
        return $this->wpdb->get_results($sql);
    }

    // Function to get post IDs with both 'fifu_slider_list_url' and '_product_image_gallery'
    function get_post_ids_for_houzez() {
        $sql = "
            SELECT a.post_id
            FROM {$this->postmeta} AS a
            WHERE a.meta_key = 'fifu_slider_list_url'
            AND EXISTS (
                SELECT 1
                FROM {$this->postmeta} AS b
                WHERE a.post_id = b.post_id
                AND b.meta_key = '_product_image_gallery'
            )
        ";
        $results = $this->wpdb->get_results($sql);
        $post_ids = wp_list_pluck($results, 'post_id');
        return $post_ids;
    }

    function delete_fave_property_images($post_ids) {
        $post_ids_str = implode(',', array_map('intval', $post_ids));
        $this->wpdb->query("
            DELETE FROM {$this->postmeta}
            WHERE meta_key = 'fave_property_images'
            AND post_id IN ($post_ids_str)
        ");
    }

    function add_fave_property_images($post_ids) {
        $post_ids_str = implode(',', array_map('intval', $post_ids));

        $results = $this->wpdb->get_results("
            SELECT post_id, meta_value
            FROM {$this->postmeta}
            WHERE meta_key = '_product_image_gallery'
            AND post_id IN ($post_ids_str)
        ");

        $insert_values = [];

        foreach ($results as $row) {
            $post_id = intval($row->post_id);
            $gallery_ids = explode(',', $row->meta_value);

            foreach ($gallery_ids as $att_id) {
                $att_id = intval($att_id);
                $insert_values[] = "($post_id, 'fave_property_images', $att_id)";
            }
        }

        if (!empty($insert_values)) {
            $insert_query = "
                INSERT INTO {$this->postmeta} (post_id, meta_key, meta_value)
                VALUES " . implode(',', $insert_values);
            $this->wpdb->query($insert_query);
        }
    }

    // delete 1 _product_image_gallery for each post
    function delete_product_image_gallery_by($ids, $is_ctgr) {
        $ctgr_sql = $is_ctgr ? "AND post_name LIKE 'fifu-category%'" : "";
        $ids_csv = $this->sanitize_ids_csv($ids);
        $author = $this->author;

        $sql = "
            DELETE pm
            FROM {$this->postmeta} pm
            JOIN {$this->posts} p ON pm.post_id = p.post_parent
            WHERE pm.post_id IN ({$ids_csv})
              AND pm.meta_key IN ('_product_image_gallery', '_wc_additional_variation_images')
              AND p.post_author = %d 
              {$ctgr_sql}
        ";
        $this->wpdb->query($this->wpdb->prepare($sql, $author));
    }

    function delete_product_image_gallery_by_attach_ids($ids, $attach_ids) {
        // Keep the guard: only delete gallery meta if the referenced attachments
        // have been removed from wp_posts (i.e., the deletion actually occurred).
        $ids_csv = $this->sanitize_ids_csv($ids);
        $att_csv = $this->sanitize_ids_csv($attach_ids);
        $sql = "
            DELETE FROM {$this->postmeta}
            WHERE post_id IN ({$ids_csv})
              AND meta_key IN ('_product_image_gallery', '_wc_additional_variation_images')
              AND NOT EXISTS (
                SELECT 1
                FROM {$this->posts}
                WHERE id IN ({$att_csv})
              )
        ";
        return $this->wpdb->query($sql);
    }

    // insert 1 _product_image_gallery for each post
    function insert_product_image_gallery($ids, $is_ctgr) {
        $ctgr_sql = $is_ctgr ? "AND p.post_name LIKE 'fifu-category%'" : "";
        $ids_csv = $this->sanitize_ids_csv($ids);
        $author = $this->author;
        $sql = "
            INSERT INTO {$this->postmeta} (post_id, meta_key, meta_value) (
                SELECT post_parent, '_product_image_gallery', GROUP_CONCAT(id) 
                FROM {$this->posts} p 
                WHERE p.post_parent IN ({$ids_csv})
                AND p.id NOT IN (
                    SELECT pm.meta_value 
                    FROM {$this->postmeta} pm 
                    WHERE pm.post_id = p.post_parent 
                    AND pm.meta_key = '_thumbnail_id'
                )
                AND p.post_author = %d 
                {$ctgr_sql} 
                GROUP BY post_parent
            )
        ";
        $this->wpdb->query($this->wpdb->prepare($sql, $author));
    }

    // insert 1 _wc_additional_variation_images for each post
    function insert_wc_additional_variation_images($ids, $is_ctgr) {
        $ctgr_sql = $is_ctgr ? "AND p.post_name LIKE 'fifu-category%'" : "";
        $ids_csv = $this->sanitize_ids_csv($ids);
        $author = $this->author;
        $sql = "
            INSERT INTO {$this->postmeta} (post_id, meta_key, meta_value) (
                SELECT post_parent, '_wc_additional_variation_images', GROUP_CONCAT(id) 
                FROM {$this->posts} p 
                WHERE p.post_parent IN ({$ids_csv})
                AND p.id NOT IN (
                    SELECT pm.meta_value 
                    FROM {$this->postmeta} pm 
                    WHERE pm.post_id = p.post_parent 
                    AND pm.meta_key = '_thumbnail_id'
                )
                AND p.post_author = %d 
                {$ctgr_sql} 
                AND (SELECT post_type FROM {$this->posts} WHERE id = p.post_parent) = 'product_variation'
                GROUP BY post_parent
            )
        ";
        $this->wpdb->query($this->wpdb->prepare($sql, $author));
    }

    // get att_id by post and url
    function get_att_id($post_parent, $url, $is_ctgr) {
        $ctgr_sql = $is_ctgr ? "AND p.post_name LIKE 'fifu-category%'" : "";
        $sql = $this->wpdb->prepare(
                "SELECT pm.post_id
             FROM {$this->postmeta} pm
             WHERE pm.meta_key = '_wp_attached_file'
               AND pm.meta_value = %s
               AND pm.post_id IN (
                   SELECT p.id
                   FROM {$this->posts} p 
                   WHERE p.post_parent = %d
                     AND post_author = %d {$ctgr_sql}
               )
             LIMIT 1",
                $url,
                (int) $post_parent,
                $this->author
        );
        $row = $this->wpdb->get_row($sql);
        return $row ? (int) $row->post_id : null;
    }

    // auto set category image
    function insert_category_images_auto() {
        // Max ID:
        // $this->wpdb->query("
        //     INSERT INTO {$this->termmeta} (term_id, meta_key, meta_value)
        //         SELECT tt.term_id, 'fifu_image_url', pm.meta_value
        //         FROM {$this->term_taxonomy} tt
        //         INNER JOIN (SELECT term_taxonomy_id, MAX(object_id) AS object_id FROM {$this->term_relationships} GROUP BY term_taxonomy_id) rs ON tt.term_taxonomy_id = rs.term_taxonomy_id
        //         INNER JOIN {$this->postmeta} pm ON pm.post_id = rs.object_id AND pm.meta_key = 'fifu_image_url' AND pm.meta_value <> ''
        //         INNER JOIN {$this->posts} p ON p.id = pm.post_id
        //         WHERE tt.taxonomy = 'product_cat' AND tt.count > 0
        //         AND NOT EXISTS (SELECT 1 FROM {$this->termmeta} tm2 WHERE tm2.meta_key = 'fifu_image_url' AND tm2.term_id = tt.term_id)
        // ");
        // Random ID:
        $seed = date('YmdH'); // or use time() for more frequent changes

        $this->wpdb->query(
                $this->wpdb->prepare("
                INSERT INTO {$this->termmeta} (term_id, meta_key, meta_value)
                SELECT tt.term_id, 'fifu_image_url', pm.meta_value
                FROM {$this->term_taxonomy} tt
                INNER JOIN (
                    SELECT tr_rnd.term_taxonomy_id, tr_rnd.object_id
                    FROM (
                        SELECT tr.term_taxonomy_id, tr.object_id,
                            CONV(SUBSTRING(MD5(CONCAT(tr.term_taxonomy_id, ':', tr.object_id, ':', %s)), 1, 16), 16, 10) AS rnd
                        FROM {$this->term_relationships} tr
                    ) tr_rnd
                    INNER JOIN (
                        SELECT term_taxonomy_id, MIN(rnd) AS min_rnd
                        FROM (
                            SELECT tr.term_taxonomy_id,
                                CONV(SUBSTRING(MD5(CONCAT(tr.term_taxonomy_id, ':', tr.object_id, ':', %s)), 1, 16), 16, 10) AS rnd
                            FROM {$this->term_relationships} tr
                        ) tr_rnd2
                        GROUP BY term_taxonomy_id
                    ) tr_min
                    ON tr_rnd.term_taxonomy_id = tr_min.term_taxonomy_id
                    AND tr_rnd.rnd = tr_min.min_rnd
                ) rs ON tt.term_taxonomy_id = rs.term_taxonomy_id
                INNER JOIN {$this->postmeta} pm
                    ON pm.post_id = rs.object_id
                    AND pm.meta_key = 'fifu_image_url'
                    AND pm.meta_value <> ''
                INNER JOIN {$this->posts} p
                    ON p.id = pm.post_id
                WHERE tt.taxonomy = 'product_cat' 
                  AND tt.count > 0
                  AND NOT EXISTS (
                    SELECT 1 
                    FROM {$this->termmeta} tm2
                    WHERE tm2.meta_key = 'fifu_image_url'
                    AND tm2.term_id = tt.term_id
                )
            ", $seed, $seed)
        );
    }

    function update_category_images_auto() {
        // Max ID:
        // $result = $this->wpdb->get_results("
        //     SELECT tt.term_id, pm.meta_value
        //         FROM {$this->term_taxonomy} tt
        //         INNER JOIN (SELECT term_taxonomy_id, MAX(object_id) AS object_id FROM {$this->term_relationships} GROUP BY term_taxonomy_id) rs ON tt.term_taxonomy_id = rs.term_taxonomy_id
        //         INNER JOIN {$this->postmeta} pm ON pm.post_id = rs.object_id AND pm.meta_key = 'fifu_image_url' AND pm.meta_value <> ''
        //         INNER JOIN {$this->posts} p ON p.id = pm.post_id
        //         WHERE tt.taxonomy = 'product_cat' AND tt.count > 0
        //         AND EXISTS (SELECT 1 FROM {$this->termmeta} tm2 WHERE tm2.meta_key = 'fifu_image_url' AND tm2.term_id = tt.term_id)
        // ");
        // Random ID:
        $seed = mt_rand(); // Generate a seed in PHP

        $result = $this->wpdb->get_results($this->wpdb->prepare("
            SELECT tt.term_id, pm.meta_value
            FROM {$this->term_taxonomy} tt
            INNER JOIN (
                SELECT tr_rnd.term_taxonomy_id, tr_rnd.object_id
                FROM (
                    SELECT
                        tr.term_taxonomy_id,
                        tr.object_id,
                        RAND(%d) AS rnd
                    FROM {$this->term_relationships} tr
                ) tr_rnd
                INNER JOIN (
                    SELECT
                        term_taxonomy_id,
                        MIN(rnd) AS min_rnd
                    FROM (
                        SELECT
                            tr.term_taxonomy_id,
                            RAND(%d) AS rnd
                        FROM {$this->term_relationships} tr
                    ) tr_rnd2
                    GROUP BY term_taxonomy_id
                ) tr_min
                ON tr_rnd.term_taxonomy_id = tr_min.term_taxonomy_id
                AND tr_rnd.rnd = tr_min.min_rnd
            ) rs ON tt.term_taxonomy_id = rs.term_taxonomy_id
            INNER JOIN {$this->postmeta} pm
                ON pm.post_id = rs.object_id
                AND pm.meta_key = 'fifu_image_url'
                AND pm.meta_value <> ''
            INNER JOIN {$this->posts} p
                ON p.id = pm.post_id
            WHERE tt.taxonomy = 'product_cat'
              AND tt.count > 0
              AND EXISTS (
                SELECT 1 FROM {$this->termmeta} tm2
                WHERE tm2.meta_key = 'fifu_image_url'
                  AND tm2.term_id = tt.term_id
            )
        ", $seed, $seed));

        foreach ($result as $res) {
            $this->wpdb->update($this->termmeta, array('meta_value' => $res->meta_value), array('term_id' => $res->term_id, 'meta_key' => 'fifu_image_url'), null, null);
            wp_cache_flush();
            $this->ctgr_update_fake_attach_id($res->term_id);
        }
    }

    // get category id given post_id
    function get_category_id($post_id) {
        $sql = $this->wpdb->prepare(
                "SELECT tm.term_id
             FROM {$this->termmeta} tm
             INNER JOIN {$this->term_taxonomy} tt ON tm.term_id = tt.term_id
             INNER JOIN {$this->term_relationships} rs ON tt.term_taxonomy_id = rs.term_taxonomy_id
             INNER JOIN {$this->postmeta} pm ON pm.post_id = rs.object_id
             WHERE pm.post_id = %d
               AND pm.meta_key = 'fifu_image_url'
               AND pm.meta_key = tm.meta_key
               AND pm.meta_value = tm.meta_value
               AND pm.meta_value <> ''
               AND tt.taxonomy = 'product_cat'",
                (int) $post_id
        );
        return $this->wpdb->get_results($sql);
    }

    function get_child_category() {
        return $this->wpdb->get_results("
            SELECT DISTINCT tt.term_id, tt.parent, tt.count
            FROM {$this->term_taxonomy} tt
            INNER JOIN {$this->termmeta} tm ON tm.term_id = tt.term_id
            WHERE parent <> 0
            AND taxonomy = 'product_cat'
            ORDER BY count DESC
        ");
    }

    function exists_child_with_attachment($term_id, $parent) {
        $sql = $this->wpdb->prepare(
                "SELECT 1 
            FROM {$this->termmeta}
            WHERE term_id = %d
            AND meta_key = 'thumbnail_id'
            AND meta_value <> 0
            AND NOT EXISTS (
                SELECT 1 
                FROM {$this->termmeta} tm2
                WHERE tm2.term_id = %d
                AND tm2.meta_key = 'thumbnail_id'
                AND tm2.meta_value <> 0
            )",
                (int) $term_id,
                (int) $parent
        );
        // Use get_var to return a scalar; true if any row matches
        return (bool) $this->wpdb->get_var($sql);
    }

    function get_count_wp_postmeta() {
        return $this->wpdb->get_results("
            SELECT COUNT(1) AS amount
            FROM {$this->postmeta}
        ");
    }

    function get_count_wp_posts() {
        return $this->wpdb->get_results("
            SELECT COUNT(1) AS amount
            FROM {$this->posts}
        ");
    }

    function get_count_wp_posts_fifu() {
        $sql = $this->wpdb->prepare(
                "SELECT COUNT(1) AS amount FROM {$this->posts} WHERE post_author = %d",
                $this->author
        );
        return $this->wpdb->get_results($sql);
    }

    function get_count_wp_postmeta_fifu() {
        $sql = $this->wpdb->prepare(
                "SELECT COUNT(1) AS amount
             FROM {$this->postmeta}
             WHERE meta_key = '_wp_attached_file'
               AND EXISTS (
                   SELECT 1 FROM {$this->posts}
                   WHERE id = post_id AND post_author = %d
               )",
                $this->author
        );
        return $this->wpdb->get_results($sql);
    }

    function tables_created() {
        return $this->wpdb->get_var("SHOW TABLES LIKE '{$this->fifu_meta_in}'");
    }

    function debug_slug($slug) {
        $sql = $this->wpdb->prepare(
                "SELECT ID, post_author, post_content, post_title, post_status, post_parent, post_content_filtered, guid, post_type 
             FROM {$this->posts} 
             WHERE post_name = %s
               AND post_status <> 'private'
               AND (post_password = '' OR post_password IS NULL)",
                $slug
        );
        return $this->wpdb->get_results($sql);
    }

    function debug_postmeta($post_id) {
        $sql = $this->wpdb->prepare("
            SELECT pm.meta_key, pm.meta_value
            FROM {$this->postmeta} pm
            INNER JOIN {$this->posts} p ON p.ID = pm.post_id
            WHERE pm.post_id = %d 
              AND p.post_status <> 'private'
              AND (p.post_password = '' OR p.post_password IS NULL)
              AND (
                  pm.meta_key LIKE 'fifu%'
                  OR pm.meta_key IN ('_thumbnail_id', '_wp_attached_file', '_wp_attachment_image_alt', '_product_image_gallery', '_wc_additional_variation_images')
              )"
                , $post_id);
        return $this->wpdb->get_results($sql);
    }

    function debug_posts($id) {
        $sql = $this->wpdb->prepare("
            SELECT post_author, post_content, post_title, post_status, post_parent, post_content_filtered, guid, post_type
            FROM {$this->posts} 
            WHERE id = %d
            AND post_status <> 'private'
            AND (post_password = '' OR post_password IS NULL)"
                , $id);
        return $this->wpdb->get_results($sql);
    }

    function debug_metain() {
        // No placeholders here; do not call prepare()
        return $this->wpdb->get_results("SELECT * FROM {$this->fifu_meta_in}");
    }

    function debug_metaout() {
        // No placeholders here; do not call prepare()
        return $this->wpdb->get_results("SELECT * FROM {$this->fifu_meta_out}");
    }

    // count images without dimensions
    function get_count_posts_without_dimensions() {
        $author = $this->author;
        $sql = $this->wpdb->prepare("
            SELECT COUNT(1) AS amount
            FROM {$this->posts} p
            WHERE NOT EXISTS (
                SELECT 1 
                FROM {$this->postmeta} b
                WHERE p.id = b.post_id AND meta_key = '_wp_attachment_metadata'
            )
            AND p.post_author = %d
        ", $author);
        return $this->wpdb->get_results($sql);
    }

    // count urls with metadata
    function get_count_urls_with_metadata() {
        $author = $this->author;
        $sql = $this->wpdb->prepare("
            SELECT COUNT(1) AS amount
            FROM {$this->posts} p
            WHERE p.post_author = %d
        ", $author);
        return $this->wpdb->get_results($sql);
    }

    // Count URLs across postmeta and termmeta (no UNION; no meta_value filters; no tm '%list%' filter)
    function get_count_urls() {
        $sql = "
            SELECT
                (
                    SELECT COUNT(*)
                    FROM {$this->postmeta} AS pm
                    WHERE pm.meta_key LIKE 'fifu!_%' ESCAPE '!'
                    AND pm.meta_key LIKE '%url%'
                    AND pm.meta_key NOT LIKE '%list%'
                ) +
                (
                    SELECT COUNT(*)
                    FROM {$this->termmeta} AS tm
                    WHERE tm.meta_key LIKE 'fifu!_%' ESCAPE '!'
                    AND tm.meta_key LIKE '%url%'
                ) AS amount
        ";
        return (int) $this->wpdb->get_var($sql);
    }

    function get_count_metadata_operations() {
        return $this->wpdb->get_var("
            SELECT 
                COALESCE(
                    (
                        SELECT SUM(
                            CASE 
                                WHEN post_ids IS NULL OR post_ids = '' THEN 0
                                ELSE CHAR_LENGTH(post_ids) - CHAR_LENGTH(REPLACE(post_ids, ',', '')) + 1
                            END
                        ) 
                        FROM {$this->fifu_meta_in}
                    ), 0
                ) +
                COALESCE(
                    (
                        SELECT SUM(
                            CASE 
                                WHEN post_ids IS NULL OR post_ids = '' THEN 0
                                ELSE CHAR_LENGTH(post_ids) - CHAR_LENGTH(REPLACE(post_ids, ',', '')) + 1
                            END
                        ) 
                        FROM {$this->fifu_meta_out}
                    ), 0
                ) AS total_amount
        ");
    }

    function get_count_content_operations() {
        return $this->wpdb->get_var("
            SELECT 
                COALESCE(
                    (
                        SELECT SUM(
                            CASE 
                                WHEN post_ids IS NULL OR post_ids = '' THEN 0
                                ELSE CHAR_LENGTH(post_ids) - CHAR_LENGTH(REPLACE(post_ids, ',', '')) + 1
                            END
                        ) 
                        FROM {$this->fifu_content}
                    ), 0
                ) AS total_amount
        ");
    }

    // get last (images/videos/sliders)
    function get_last($meta_key) {
        $sql = $this->wpdb->prepare(
                "SELECT p.id, pm.meta_value
            FROM {$this->posts} p
            INNER JOIN {$this->postmeta} pm ON p.id = pm.post_id
            WHERE pm.meta_key = %s
            ORDER BY p.post_date DESC
            LIMIT 3",
                $meta_key
        );
        return $this->wpdb->get_results($sql);
    }

    function get_last_image() {
        return $this->wpdb->get_results("
            SELECT pm.meta_value
            FROM {$this->postmeta} pm 
            WHERE pm.meta_key = 'fifu_image_url'
            ORDER BY pm.meta_id DESC
            LIMIT 1
        ");
    }

    // get child posts (excluding the featured image) for a given post
    function get_attachments_without_post($post_id) {
        $sql = $this->wpdb->prepare(
                "SELECT GROUP_CONCAT(p.ID) AS ids
            FROM {$this->posts} p
            WHERE p.post_parent = %d
            AND p.post_author = %d
            AND p.post_name NOT LIKE %s
            AND NOT EXISTS (
                SELECT 1
                FROM {$this->postmeta} pm2
                WHERE pm2.post_id = p.post_parent
                    AND pm2.meta_key = '_thumbnail_id'
                    AND pm2.meta_value = p.ID
            )",
                (int) $post_id,
                (int) $this->author,
                'fifu-category%' // no need for %% since it's a %s value
        );

        // One row expected; return CSV string or null
        $ids_csv = $this->wpdb->get_var($sql);
        return $ids_csv ?: null;
    }

    function get_ctgr_attachments_without_post($term_id) {
        $sql = $this->wpdb->prepare(
                "SELECT GROUP_CONCAT(p.ID) AS ids
            FROM {$this->posts} p
            WHERE p.post_parent = %d
            AND p.post_author = %d
            AND p.post_name LIKE %s
            AND NOT EXISTS (
                SELECT 1
                FROM {$this->termmeta} tm
                WHERE tm.term_id = p.post_parent
                    AND tm.meta_key = 'thumbnail_id'
                    AND tm.meta_value = p.ID
            )",
                (int) $term_id,
                (int) $this->author,
                'fifu-category%' // pass pattern as a value; no %% needed
        );

        $ids_csv = $this->wpdb->get_var($sql);
        return $ids_csv ?: null;
    }

    function get_posts_without_featured_image($post_types) {
        $safe = $this->sanitize_post_types_list($post_types);
        return $this->wpdb->get_results("
            SELECT id, post_title
            FROM {$this->posts} 
            WHERE post_type IN ($safe)
            AND post_status = 'publish'
            AND NOT EXISTS (
                SELECT 1
                FROM {$this->postmeta} 
                WHERE post_id = id
                AND meta_key IN ('_thumbnail_id', 'fifu_image_url', 'fifu_video_url', 'fifu_slider_image_url_0')
            )
            ORDER BY id DESC
        ");
    }

    function get_post_types_without_featured_image($post_types) {
        $default_attach_id = (int) get_option('fifu_default_attach_id');
        $check_default = $default_attach_id ? "OR (meta_key = '_thumbnail_id' AND meta_value <> {$default_attach_id})" : "OR (meta_key = '_thumbnail_id')";

        $safe = $this->sanitize_post_types_list($post_types);
        return $this->wpdb->get_results("
            (
                SELECT id as post_id, post_title
                FROM {$this->posts} 
                WHERE post_type IN ($safe)
                AND post_status IN ('publish', 'draft')
                AND NOT EXISTS (
                    SELECT 1
                    FROM {$this->postmeta} 
                    WHERE post_id = id
                    AND (
                        meta_key IN ('fifu_image_url', 'fifu_video_url', 'fifu_slider_image_url_0', 'fifu_auto_set_sent')
                        {$check_default}
                    )
                )
                ORDER BY id DESC
            )
        ");
    }

    function get_isbns_without_featured_image() {
        [$in, $keys] = $this->build_in_from_option_csv('fifu_isbn', 'fifu_isbn_custom_field');

        $sql = $this->wpdb->prepare(
                "SELECT post_id, meta_value AS isbn
            FROM {$this->postmeta} pm
            WHERE pm.meta_key IN ($in)
            AND NOT EXISTS (
                SELECT 1
                FROM {$this->postmeta} x
                WHERE x.post_id = pm.post_id
                    AND x.meta_key IN ('_thumbnail_id','fifu_image_url','fifu_video_url','fifu_slider_image_url_0','fifu_isbn_sent')
            )
            ORDER BY post_id DESC",
                $keys
        );

        return $this->wpdb->get_results($sql);
    }

    function get_asins_without_featured_image() {
        [$in, $keys] = $this->build_in_from_option_csv('fifu_asin', 'fifu_asin_custom_field');

        $sql = $this->wpdb->prepare(
                "SELECT post_id, meta_value AS asin
            FROM {$this->postmeta} pm
            WHERE pm.meta_key IN ($in)
            AND NOT EXISTS (
                SELECT 1
                FROM {$this->postmeta} x
                WHERE x.post_id = pm.post_id
                    AND x.meta_key IN ('_thumbnail_id','fifu_image_url','fifu_video_url','fifu_slider_image_url_0','fifu_asin_sent')
            )
            ORDER BY post_id DESC",
                $keys
        );

        return $this->wpdb->get_results($sql);
    }

    function get_customfields_without_featured_image() {
        // Parse CSV via helper, then normalize tokens: "{field}" -> "field"
        [, $raw] = $this->build_in_from_option_csv('', 'fifu_customfield_custom_field');

        $keys = [];
        foreach ($raw as $cf_item) {
            $cf_item = trim($cf_item);
            if ($cf_item === '')
                continue;                    // drop empties
            if (preg_match('/\{(.+?)\}/', $cf_item, $m)) {
                $cf_item = $m[1];                              // extract inner name
            }
            $keys[] = $cf_item;                                // keep original token otherwise
        }
        $keys = array_values(array_unique($keys));             // drop duplicates
        if (!$keys)
            return [];

        // Safe IN (...) and bind for both UNION branches
        $in = implode(',', array_fill(0, count($keys), '%s')); // e.g. ['foo','bar'] -> IN ('foo','bar')
        $args = array_merge($keys, $keys);

        $sql = $this->wpdb->prepare("
            SELECT post_id AS id, meta_key, meta_value, 0 AS is_ctgr
            FROM {$this->postmeta} pm
            WHERE pm.meta_key IN ($in)
            AND NOT EXISTS (
                SELECT 1
                FROM {$this->postmeta}
                WHERE post_id = pm.post_id
                    AND meta_key IN ('_thumbnail_id','fifu_image_url','fifu_video_url','fifu_slider_image_url_0')
            )

            UNION

            SELECT term_id AS id, meta_key, meta_value, 1 AS is_ctgr
            FROM {$this->termmeta} tm
            WHERE tm.meta_key IN ($in)
            AND NOT EXISTS (
                SELECT 1
                FROM {$this->termmeta}
                WHERE term_id = tm.term_id
                    AND meta_key IN ('_thumbnail_id','fifu_image_url','fifu_video_url')
            )

            ORDER BY id DESC
        ", $args);

        return $this->wpdb->get_results($sql);
    }

    function get_posts_types_without_screenshot() {
        // Build safe IN(...) from option CSV
        [, $keys] = $this->build_in_from_option_csv('', 'fifu_screenshot_custom_field');
        $keys = array_values(array_filter($keys, static fn($k) => $k !== '')); // drop empties
        if (!$keys)
            return [];

        $in = implode(',', array_fill(0, count($keys), '%s')); // e.g. %s,%s -> IN ('key1','key2')
        $sql = $this->wpdb->prepare(
                "SELECT DISTINCT post_id, meta_value AS url
            FROM {$this->postmeta} pm
            WHERE pm.meta_key IN ($in)
            AND LEFT(pm.meta_value, 4) = 'http'
            AND NOT EXISTS (
                SELECT 1
                FROM {$this->postmeta}
                WHERE post_id = pm.post_id
                    AND meta_key IN ('_thumbnail_id','fifu_image_url','fifu_video_url','fifu_slider_image_url_0')
            )
            GROUP BY post_id
            ORDER BY post_id DESC",
                $keys
        );

        return $this->wpdb->get_results($sql);
    }

    function get_finders_without_featured_image() {
        // Safe IN(...) from option CSV (always includes 'fifu_finder_url')
        [$in, $keys] = $this->build_in_from_option_csv('fifu_finder_url', 'fifu_finder_custom_field');

        $default_attach_id = get_option('fifu_default_attach_id');

        // Build the conditional bit for the default thumbnail check
        $check_default_sql = " OR (x.meta_key = '_thumbnail_id')";
        $args = $keys; // placeholders for IN (...)

        if ($default_attach_id) {
            $check_default_sql = " OR (x.meta_key = '_thumbnail_id' AND x.meta_value <> %d)";
            $args = array_merge($args, [(int) $default_attach_id]); // add %d value at the end
        }

        $sql = $this->wpdb->prepare(
                "SELECT post_id, meta_value AS webpage_url
            FROM {$this->postmeta} pm
            WHERE pm.meta_key IN ($in)
            AND pm.meta_value LIKE 'http%'
            AND NOT EXISTS (
                SELECT 1
                FROM {$this->postmeta} x
                WHERE x.post_id = pm.post_id
                    AND (
                        x.meta_key IN ('fifu_image_url','fifu_video_url','fifu_slider_image_url_0','fifu_finder_sent')
                        {$check_default_sql}
                        OR (x.meta_key = 'fifu_finder_counter' AND x.meta_value >= 3)
                    )
            )
            ORDER BY post_id DESC",
                $args
        );

        return $this->wpdb->get_results($sql);
    }

    function get_tags_without_featured_image() {
        return $this->wpdb->get_results("
            SELECT id AS post_id, GROUP_CONCAT(name) AS tags
            FROM {$this->posts}             
            INNER JOIN {$this->term_relationships} tr ON id = object_id
            INNER JOIN {$this->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy IN ('post_tag', 'product_tag')
            INNER JOIN {$this->terms} t ON t.term_id = tt.term_id 
            WHERE post_type IN ('$this->types')
            AND post_status = 'publish'
            AND NOT EXISTS (
                SELECT 1
                FROM {$this->postmeta} 
                WHERE post_id = id
                AND meta_key IN ('_thumbnail_id', 'fifu_image_url', 'fifu_video_url', 'fifu_slider_image_url_0', 'fifu_tags_sent')
            )
            GROUP BY id
            ORDER BY post_id DESC
        ");
    }

    function get_number_of_posts() {
        return $this->wpdb->get_row("
            SELECT count(1) AS n
            FROM {$this->posts} 
            WHERE post_type IN ('$this->types')
            AND post_status = 'publish'"
                )->n;
    }

    function get_featured_and_gallery_ids($post_id) {
        $sql = $this->wpdb->prepare(
                "SELECT GROUP_CONCAT(meta_value SEPARATOR ',') as 'ids'
            FROM {$this->postmeta}
            WHERE post_id = %d
              AND meta_key IN ('_thumbnail_id', '_product_image_gallery')",
                (int) $post_id
        );
        return $this->wpdb->get_results($sql);
    }

    function get_featured_and_gallery_urls($post_id) {
        $this->wpdb->query("SET SESSION group_concat_max_len = 1048576;"); // because GROUP_CONCAT is limited to 1024 characters
        $sql = $this->wpdb->prepare(
                "SELECT GROUP_CONCAT(meta_value SEPARATOR '|') as 'urls'
            FROM {$this->postmeta}
            WHERE post_id = %d
              AND meta_key LIKE 'fifu_image_url%'
            ORDER BY meta_key",
                (int) $post_id
        );
        return $this->wpdb->get_results($sql);
    }

    function get_image_gallery_urls($post_id) {
        $sql = $this->wpdb->prepare(
                "SELECT meta_key, meta_value
            FROM {$this->postmeta}
            WHERE post_id = %d
              AND meta_key LIKE 'fifu_image_url_%'
            ORDER BY meta_key",
                (int) $post_id
        );
        return $this->wpdb->get_results($sql);
    }

    function delete_featured_and_gallery_urls($post_id) {
        $sql = $this->wpdb->prepare(
                "DELETE FROM {$this->postmeta} WHERE post_id = %d AND meta_key LIKE 'fifu_image_url%'",
                (int) $post_id
        );
        $this->wpdb->query($sql);
    }

    function get_variantion_products($post_id) {
        $sql = $this->wpdb->prepare(
                "SELECT id, post_title
            FROM {$this->posts}
            WHERE post_parent = %d
              AND post_type = 'product_variation'
              AND post_status <> 'trash'
            ORDER BY menu_order",
                (int) $post_id
        );
        return $this->wpdb->get_results($sql);
    }

    function get_variation_attributes($post_id) {
        $sql = $this->wpdb->prepare(
                "SELECT *
            FROM {$this->postmeta} pm
            WHERE post_id IN (
                SELECT id
                FROM {$this->posts} p 
                WHERE p.post_parent = %d
                  AND p.post_type = 'product_variation'
            )
            AND pm.meta_key LIKE 'attribute_%'",
                (int) $post_id
        );
        return $this->wpdb->get_results($sql);
    }

    function get_variation_att_ids($post_id) {
        $sql = $this->wpdb->prepare(
                "SELECT post_id, GROUP_CONCAT(meta_value) AS att_ids
            FROM {$this->postmeta} pm
            WHERE post_id IN (
                SELECT id
                FROM {$this->posts} p 
                WHERE p.post_parent = %d
                  AND p.post_type = 'product_variation'
            )
            AND pm.meta_key IN ('_thumbnail_id', '_product_image_gallery')
            GROUP BY post_id",
                (int) $post_id
        );
        return $this->wpdb->get_results($sql);
    }

    function insert_default_thumbnail_id($value) {
        $this->wpdb->query("
            INSERT INTO {$this->postmeta} (post_id, meta_key, meta_value)
            VALUES {$value}
        ");
    }

    // clean metadata

    function delete_image_url_category($term_id) {
        $sql = $this->wpdb->prepare(
                "DELETE FROM {$this->termmeta} WHERE term_id = %d AND meta_key = 'fifu_image_url'",
                (int) $term_id
        );
        $this->wpdb->query($sql);
    }

    function delete_attachments($ids) {
        $ids_csv = $this->sanitize_ids_csv($ids);
        $sql = $this->wpdb->prepare(
                "DELETE FROM {$this->posts} WHERE id IN ({$ids_csv}) AND post_author = %d",
                $this->author
        );
        $this->wpdb->query($sql);
    }

    function delete_any_attachments($ids) {
        $ids_csv = $this->sanitize_ids_csv($ids);
        $this->wpdb->query("DELETE FROM {$this->posts} WHERE id IN ({$ids_csv})");
    }

    function delete_attachment_meta_url_and_alt($ids) {
        $ids_csv = $this->sanitize_ids_csv($ids);
        $sql = $this->wpdb->prepare(
                "DELETE FROM {$this->postmeta}
            WHERE meta_key IN ('_wp_attached_file', '_wp_attachment_image_alt', '_wp_attachment_metadata')
              AND post_id IN ({$ids_csv})
              AND EXISTS (SELECT 1 FROM {$this->posts} WHERE id = post_id AND post_author = %d)",
                $this->author
        );
        $this->wpdb->query($sql);
    }

    function delete_thumbnail_id_without_attachment() {
        if (fifu_is_multisite_global_media_active()) {
            $this->wpdb->query("
                DELETE FROM {$this->postmeta} 
                WHERE meta_key = '_thumbnail_id' 
                AND meta_value NOT LIKE '100000%' 
                AND NOT EXISTS (
                    SELECT 1 
                    FROM {$this->posts} p 
                    WHERE p.id = meta_value
                )
            ");
            return;
        }

        $this->wpdb->query("
            DELETE FROM {$this->postmeta} 
            WHERE meta_key = '_thumbnail_id' 
            AND NOT EXISTS (
                SELECT 1 
                FROM {$this->posts} p 
                WHERE p.id = meta_value
            )
        ");

        $this->wpdb->query("
            DELETE FROM {$this->termmeta} 
            WHERE meta_key = 'thumbnail_id' 
            AND NOT EXISTS (
                SELECT 1 
                FROM {$this->posts} p 
                WHERE p.id = meta_value
            )
        ");

        $this->wpdb->query("
            DELETE FROM {$this->postmeta} 
            WHERE meta_key IN ('_product_image_gallery', '_wc_additional_variation_images')
            AND NOT EXISTS (
                SELECT 1
                FROM {$this->posts} p
                WHERE FIND_IN_SET(p.ID, {$this->postmeta}.meta_value)
            )
        ");
    }

    function delete_empty_urls_category() {
        $this->wpdb->query("
            DELETE FROM {$this->termmeta} 
            WHERE meta_key = 'fifu_image_url'
            AND (
                meta_value = ''
                OR meta_value is NULL
            )
        ");
    }

    function delete_empty_urls() {
        $this->wpdb->query("
            DELETE FROM {$this->postmeta} 
            WHERE meta_key = 'fifu_image_url'
            AND (
                meta_value = ''
                OR meta_value is NULL
            )
        ");
    }

    /* wp_options */

    function insert_option($name, $value) {
        $sql = $this->wpdb->prepare(
                "INSERT INTO {$this->options} (option_name, option_value) VALUES (%s, %s) ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)",
                $name,
                $value
        );
        $this->wpdb->query($sql);
    }

    function select_option($name) {
        $sql = $this->wpdb->prepare(
                "SELECT option_value FROM {$this->options} WHERE option_name = %s",
                $name
        );
        return $this->wpdb->get_results($sql);
    }

    function delete_option($name) {
        $sql = $this->wpdb->prepare(
                "DELETE FROM {$this->options} WHERE option_name = %s",
                $name
        );
        $this->wpdb->query($sql);
        if (!empty($name)) {
            wp_cache_delete($name, 'options');
        }
    }

    function select_option_prefix($prefix) {
        if ($prefix === '')
            return []; // avoid SELECT all
        $like = $this->wpdb->esc_like($prefix) . '%'; // escape LIKE wildcards safely
        $sql = $this->wpdb->prepare(
                "SELECT option_name, option_value
            FROM {$this->options}
            WHERE option_name LIKE %s
            ORDER BY option_name",
                $like
        );
        return $this->wpdb->get_results($sql);
    }

    function delete_option_prefix($prefix) {
        if ($prefix === '') {
            return 0; // safety: avoid deleting everything
        }
        $like = $this->wpdb->esc_like($prefix) . '%'; // escape % and _
        $sql_select = $this->wpdb->prepare(
                "SELECT option_name FROM {$this->options} WHERE option_name LIKE %s",
                $like
        );
        $options_to_delete = $this->wpdb->get_col($sql_select);
        $sql_delete = $this->wpdb->prepare(
                "DELETE FROM {$this->options} WHERE option_name LIKE %s",
                $like
        );
        $deleted_count = (int) $this->wpdb->query($sql_delete);
        // Clear cache for deleted options
        foreach ($options_to_delete as $option_name) {
            wp_cache_delete($option_name, 'options');
        }
        return $deleted_count;
    }

    /* speed up */

    function get_all_urls($page, $type, $keyword) {
        $page = max(0, (int) $page); // Ensure page is non-negative
        $start = $page * 1000;

        // Posts filter
        $filter_posts = '';
        if ($keyword) {
            $like = '%' . $this->wpdb->esc_like($keyword) . '%';
            if ($type == 'title')
                $filter_posts = $this->wpdb->prepare('AND p.post_title LIKE %s', $like);
            elseif ($type == 'url')
                $filter_posts = $this->wpdb->prepare('AND pm.meta_value LIKE %s', $like);
        }

        $sql = "
            (
                SELECT pm.meta_id, pm.post_id, pm.meta_value AS url, pm.meta_key, p.post_name, p.post_title, p.post_date, false AS category, null AS video_url
                FROM {$this->postmeta} pm
                INNER JOIN {$this->posts} p ON pm.post_id = p.id {$filter_posts}
                WHERE (pm.meta_key LIKE 'fifu_%image_url%' OR pm.meta_key LIKE 'fifu_video_url%')
                AND pm.meta_value NOT LIKE '%https://cdn.fifu.app/%'
                AND pm.meta_value NOT LIKE 'http://localhost/%'
                AND p.post_status <> 'trash'
            )
        ";
        if (class_exists('WooCommerce')) {
            // Terms filter
            $filter_terms = '';
            if ($keyword) {
                $like = '%' . $this->wpdb->esc_like($keyword) . '%';
                if ($type == 'title')
                    $filter_terms = $this->wpdb->prepare('AND t.name LIKE %s', $like);
                elseif ($type == 'url')
                    $filter_terms = $this->wpdb->prepare('AND tm.meta_value LIKE %s', $like);
            }
            $sql .= " 
                UNION
                (
                    SELECT tm.meta_id, tm.term_id AS post_id, tm.meta_value AS url, tm.meta_key, null AS post_name, t.name AS post_title, null AS post_date, true AS category, null AS video_url
                    FROM {$this->termmeta} tm
                    INNER JOIN {$this->terms} t ON tm.term_id = t.term_id {$filter_terms}
                    WHERE tm.meta_key IN ('fifu_image_url', 'fifu_video_url')
                    AND tm.meta_value NOT LIKE '%https://cdn.fifu.app/%'
                    AND tm.meta_value NOT LIKE 'http://localhost/%'
                )
            ";
        }
        $sql .= " 
            ORDER BY post_id DESC
            LIMIT {$start},1000
        ";
        return $this->wpdb->get_results($sql);
    }

    function get_all_hex_ids() {
        $sql = "
            (
                SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(pm.meta_value, '/', -1), '-', 1) AS hex_id
                FROM {$this->postmeta} pm
                INNER JOIN {$this->posts} p ON pm.post_id = p.id
                WHERE (pm.meta_key LIKE 'fifu_%image_url%' OR pm.meta_key LIKE 'fifu_video_url%')
                AND pm.meta_value LIKE '%https://cdn.fifu.app/%'
            )
        ";
        if (class_exists('WooCommerce')) {
            $sql .= " 
                UNION
                (
                    SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(tm.meta_value, '/', -1), '-', 1) AS hex_id
                    FROM {$this->termmeta} tm
                    INNER JOIN {$this->terms} t ON tm.term_id = t.term_id
                    WHERE tm.meta_key IN ('fifu_image_url', 'fifu_video_url')
                    AND tm.meta_value LIKE '%https://cdn.fifu.app/%'
                )
            ";
        }
        $sql .= " 
            ORDER BY hex_id DESC
        ";
        return $this->wpdb->get_col($sql);
    }

    function get_posts_with_internal_featured_image($page, $type, $keyword) {
        $start = max(0, (int) $page) * 1000;

        $filter = "";
        if ($keyword) {
            if ($type == 'title') {
                $like = '%' . $this->wpdb->esc_like($keyword) . '%';
                $filter = $this->wpdb->prepare('AND p.post_title LIKE %s', $like);
            } elseif ($type == 'postid') {
                $filter = $this->wpdb->prepare('AND pm.post_id = %d', (int) $keyword);
            }
        }

        // Prepare author filter fragments once to avoid preparing the whole query later
        $author_clause_posts = $this->wpdb->prepare('AND att.post_author <> %d', $this->author);
        $author_clause_terms = $author_clause_posts;

        $sql = "
            (
                SELECT 
                    pm.post_id, 
                    att.guid AS url, 
                    p.post_name, 
                    p.post_title, 
                    p.post_date, 
                    att.id AS thumbnail_id,
                    (SELECT meta_value FROM {$this->postmeta} pm2 WHERE pm2.post_id = pm.post_id AND pm2.meta_key = '_product_image_gallery') AS gallery_ids,
                    false AS category
                FROM {$this->postmeta} pm
                INNER JOIN {$this->posts} p ON pm.post_id = p.id {$filter} AND p.post_title <> ''
                INNER JOIN {$this->posts} att ON (
                    pm.meta_key = '_thumbnail_id'
                    AND pm.meta_value = att.id
                    {$author_clause_posts}
                )
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM {$this->postmeta}
                    WHERE post_id = pm.post_id
                    AND (meta_key LIKE 'fifu_%image_url%' OR meta_key IN ('bkp_thumbnail_id', 'bkp_product_image_gallery'))
                )
                AND (
                    SELECT COUNT(1)
                    FROM {$this->postmeta}
                    WHERE post_id = pm.post_id
                    AND meta_key = '_product_image_gallery'
                ) <= 1
                AND p.post_status <> 'trash'
            )
        ";
        if (class_exists('WooCommerce')) {
            $filter = "";
            if ($keyword) {
                if ($type == 'title') {
                    $like = '%' . $this->wpdb->esc_like($keyword) . '%';
                    $filter = $this->wpdb->prepare('AND t.name LIKE %s', $like);
                } elseif ($type == 'postid') {
                    $filter = $this->wpdb->prepare('AND tm.term_id = %d', (int) $keyword);
                }
            }
            $sql .= " 
                UNION 
                (
                    SELECT
                        tm.term_id AS post_id, 
                        att.guid AS url, 
                        null AS post_name, 
                        t.name AS post_title, 
                        null AS post_date, 
                        att.id AS thumbnail_id,
                        null AS gallery_ids,
                        true AS category
                    FROM {$this->termmeta} tm
                    INNER JOIN {$this->terms} t ON tm.term_id = t.term_id {$filter}
                    INNER JOIN {$this->posts} att ON (
                        tm.meta_key = 'thumbnail_id'
                        AND tm.meta_value = att.id
                        {$author_clause_terms}
                    )
                    WHERE NOT EXISTS (
                        SELECT 1
                        FROM {$this->termmeta}
                        WHERE term_id = tm.term_id
                        AND (meta_key = 'fifu_image_url' OR meta_key = 'bkp_thumbnail_id')
                    )
                )
            ";
        }
        $sql .= " 
            ORDER BY post_id DESC
            LIMIT {$start},1000
        ";
        return $this->wpdb->get_results($sql);
    }

    function get_posts_su($storage_ids) {
        if (!empty($storage_ids)) {
            $ids = array_values(
                    array_filter(
                            array_map('strval', (array) $storage_ids),
                            static function ($v) {
                                return $v !== '';
                            }
                    )
            );
            if ($ids) {
                $in = implode(',', array_fill(0, count($ids), '%s'));
                $filter_post_image = $this->wpdb->prepare(
                        "AND SUBSTRING_INDEX(SUBSTRING_INDEX(pm.meta_value, '/', 5), '/', -1) IN ($in)", $ids
                );
                $filter_term_image = $this->wpdb->prepare(
                        "AND SUBSTRING_INDEX(SUBSTRING_INDEX(tm.meta_value, '/', 5), '/', -1) IN ($in)", $ids
                );
                $filter_post_video = $this->wpdb->prepare(
                        "AND SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(pm.meta_value, 'fifu-thumb=', 5), 'fifu-thumb=', -1), '/', 5), '/', -1) IN ($in)", $ids
                );
                $filter_term_video = $this->wpdb->prepare(
                        "AND SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(tm.meta_value, 'fifu-thumb=', 5), 'fifu-thumb=', -1), '/', 5), '/', -1) IN ($in)", $ids
                );
            } else {
                $filter_post_image = $filter_term_image = $filter_post_video = $filter_term_video = "";
            }
        } else {
            $filter_post_image = $filter_term_image = $filter_post_video = $filter_term_video = "";
        }

        $sql = "
            (
                SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(pm.meta_value, '/', 5), '/', -1) AS storage_id, 
                    p.post_title, 
                    p.post_date, 
                    pm.meta_id, 
                    pm.post_id, 
                    pm.meta_key, 
                    false AS category
                FROM {$this->postmeta} pm
                INNER JOIN {$this->posts} p ON pm.post_id = p.id
                WHERE pm.meta_key LIKE 'fifu_%image_url%'
                AND pm.meta_value LIKE 'https://cdn.fifu.app/%'
                {$filter_post_image}
            )
            UNION
            (
                SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(pm.meta_value, 'fifu-thumb=', 5), 'fifu-thumb=', -1), '/', 5), '/', -1) AS storage_id, 
                    p.post_title, 
                    p.post_date, 
                    pm.meta_id, 
                    pm.post_id, 
                    pm.meta_key, 
                    false AS category
                FROM {$this->postmeta} pm
                INNER JOIN {$this->posts} p ON pm.post_id = p.id
                WHERE pm.meta_key LIKE 'fifu_video_url%'
                AND pm.meta_value LIKE '%https://cdn.fifu.app/%'
                {$filter_post_video}
            )
        ";

        if (class_exists('WooCommerce')) {
            $sql .= "
                UNION
                (
                    SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(tm.meta_value, '/', 5), '/', -1) AS storage_id, 
                        t.name AS post_title, 
                        NULL AS post_date, 
                        tm.meta_id, 
                        tm.term_id AS post_id, 
                        tm.meta_key, 
                        true AS category
                    FROM {$this->termmeta} tm
                    INNER JOIN {$this->terms} t ON tm.term_id = t.term_id
                    WHERE tm.meta_key = 'fifu_image_url'
                    AND tm.meta_value LIKE 'https://cdn.fifu.app/%'
                    {$filter_term_image}
                )
                UNION
                (
                    SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(tm.meta_value, 'fifu-thumb=', 5), 'fifu-thumb=', -1), '/', 5), '/', -1) AS storage_id,
                        t.name AS post_title, 
                        NULL AS post_date, 
                        tm.meta_id, 
                        tm.term_id AS post_id, 
                        tm.meta_key, 
                        true AS category
                    FROM {$this->termmeta} tm
                    INNER JOIN {$this->terms} t ON tm.term_id = t.term_id
                    WHERE tm.meta_key = 'fifu_video_url'
                    AND tm.meta_value LIKE '%https://cdn.fifu.app/%'
                    {$filter_term_video}
                )
            ";
        }

        return $this->wpdb->get_results($sql);
    }

    /* speed up (add) */

    function add_urls_su($bucket_id, $thumbnails) {
        // custom field
        $this->speed_up_custom_fields($bucket_id, $thumbnails, false);

        // two groups
        $featured_list = array();
        $gallery_list = array();
        foreach ($thumbnails as $thumbnail) {
            if ($thumbnail->meta_key == 'fifu_image_url' || $thumbnail->meta_key == 'fifu_slider_image_url_0' || $thumbnail->meta_key == 'fifu_video_url')
                array_push($featured_list, $thumbnail);
            else
                array_push($gallery_list, $thumbnail);
        }

        // featured group
        if (count($featured_list) > 0) {
            $att_ids_map = $this->get_thumbnail_ids($featured_list, false);
            if (count($att_ids_map) > 0) {
                $this->speed_up_attachments($bucket_id, $featured_list, $att_ids_map);
                $meta_ids_map = $this->get_thumbnail_meta_ids($featured_list, $att_ids_map);
                if (count($meta_ids_map) > 0)
                    $this->speed_up_attachments_meta($bucket_id, $featured_list, $meta_ids_map);
            }
        }

        // gallery group
        if (count($gallery_list) > 0) {
            $att_ids_map = $this->get_thumbnail_ids_gallery($gallery_list, false);
            if (count($att_ids_map) > 0) {
                $this->speed_up_attachments($bucket_id, $gallery_list, $att_ids_map);
                $meta_ids_map = $this->get_thumbnail_meta_ids($gallery_list, $att_ids_map);
                if (count($meta_ids_map) > 0)
                    $this->speed_up_attachments_meta($bucket_id, $gallery_list, $meta_ids_map);
            }
        }

        // lists
        $list_ids_map = $this->get_list_ids($thumbnails, $bucket_id, false, null);
        $this->speed_up_list_custom_fields($bucket_id, $thumbnails, $list_ids_map);
    }

    function ctgr_add_urls_su($bucket_id, $thumbnails) {
        // custom field
        $this->speed_up_custom_fields($bucket_id, $thumbnails, true);

        $featured_list = array();
        foreach ($thumbnails as $thumbnail)
            array_push($featured_list, $thumbnail);

        // featured group
        if (count($featured_list) > 0) {
            $att_ids_map = $this->get_thumbnail_ids($featured_list, true);
            if (count($att_ids_map) > 0) {
                $this->speed_up_attachments($bucket_id, $featured_list, $att_ids_map);
                $meta_ids_map = $this->get_thumbnail_meta_ids($featured_list, $att_ids_map);
                if (count($meta_ids_map) > 0)
                    $this->speed_up_attachments_meta($bucket_id, $featured_list, $meta_ids_map);
            }
        }
    }

    function get_su_url($bucket_id, $storage_id) {
        return 'https://cdn.fifu.app/' . $bucket_id . '/' . $storage_id;
    }

    function speed_up_custom_fields($bucket_id, $thumbnails, $is_ctgr) {
        $table = $is_ctgr ? $this->termmeta : $this->postmeta;

        $values = [];
        $args = [];

        foreach ($thumbnails as $thumbnail) {
            $su_url = $this->get_su_url($bucket_id, $thumbnail->storage_id);

            if ($thumbnail->video_url && strpos($thumbnail->video_url, 'fifu-thumb=') === false) {
                $qp = parse_url($thumbnail->video_url, PHP_URL_QUERY);
                $del = $qp ? '&' : '?';
                $su_url = rtrim($thumbnail->video_url, " &?\n\r\t\v\0") . $del . 'fifu-thumb=' . $su_url;
            } elseif ($thumbnail->video_url && strpos($thumbnail->video_url, 'fifu-thumb=') !== false) {
                $su_url = $thumbnail->video_url;
            }

            $values[] = '(%d,%s)';
            $args[] = (int) $thumbnail->meta_id;
            $args[] = $su_url;
        }

        if (!$values)
            return 0;

        $query = "
            INSERT INTO {$table} (meta_id, meta_value)
            VALUES " . implode(', ', $values) . "
            ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)
        ";

        return $this->wpdb->query($this->wpdb->prepare($query, $args));
    }

    function speed_up_list_custom_fields($bucket_id, $thumbnails, $list_ids_map) {
        $map1_image = $map2_image = array();
        $map1_video = $map2_video = array();

        foreach ($thumbnails as $thumbnail) {
            if (!isset($list_ids_map[$thumbnail->meta_id])) {
                continue;
            }

            $su_url = $this->get_su_url($bucket_id, $thumbnail->storage_id);

            if ($thumbnail->video_url && strpos($thumbnail->video_url, 'fifu-thumb=') === false) {
                $qp = parse_url($thumbnail->video_url, PHP_URL_QUERY);
                $del = $qp ? '&' : '?';
                $su_url = rtrim($thumbnail->video_url, " &?\n\r\t\v\0") . $del . 'fifu-thumb=' . $su_url;
                $url = $thumbnail->video_url;
                $map1_video[$thumbnail->post_id] = str_replace(
                        $url,
                        $su_url,
                        isset($map1_video[$thumbnail->post_id]) ? $map1_video[$thumbnail->post_id] : $list_ids_map[$thumbnail->meta_id][1]
                );
                $map2_video[$list_ids_map[$thumbnail->meta_id][0]] = $thumbnail->post_id;
            } else {
                $url = $thumbnail->meta_value;
                $map1_image[$thumbnail->post_id] = str_replace(
                        $url,
                        $su_url,
                        isset($map1_image[$thumbnail->post_id]) ? $map1_image[$thumbnail->post_id] : $list_ids_map[$thumbnail->meta_id][1]
                );
                $map2_image[$list_ids_map[$thumbnail->meta_id][0]] = $thumbnail->post_id;
            }
        }

        if (!empty($map1_image)) {
            $query = "INSERT INTO {$this->postmeta} (meta_id, meta_value) VALUES ";
            $values = array();
            $count = 0;
            foreach ($map2_image as $key => $value) {
                if ($count++ != 0) {
                    $query .= ", ";
                }
                $query .= $this->wpdb->prepare("(%d, %s)", $key, $map1_image[$value]);
            }
            $query .= " ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)";
            $this->wpdb->query($query);
        }

        if (!empty($map1_video)) {
            $query = "INSERT INTO {$this->postmeta} (meta_id, meta_value) VALUES ";
            $values = array();
            $count = 0;
            foreach ($map2_video as $key => $value) {
                if ($count++ != 0) {
                    $query .= ", ";
                }
                $query .= $this->wpdb->prepare("(%d, %s)", $key, $map1_video[$value]);
            }
            $query .= " ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)";
            $this->wpdb->query($query);
        }
    }

    function get_thumbnail_ids($thumbnails, $is_ctgr) {
        // join post_ids (sanitized)
        $ids_list = array();
        foreach ($thumbnails as $thumbnail)
            $ids_list[] = (int) $thumbnail->post_id;
        $ids = $this->sanitize_ids_csv($ids_list);

        // get featured ids
        if ($is_ctgr) {
            $result = $this->wpdb->get_results("
                SELECT term_id AS post_id, meta_value AS att_id
                FROM {$this->termmeta} 
                WHERE term_id IN ({$ids}) 
                AND meta_key = 'thumbnail_id'
            ");
        } else {
            $result = $this->wpdb->get_results("
                SELECT post_id, meta_value AS att_id
                FROM {$this->postmeta} 
                WHERE post_id IN ({$ids}) 
                AND meta_key = '_thumbnail_id'
            ");
        }

        // map featured ids
        $featured_map = array();
        foreach ($result as $res)
            $featured_map[$res->post_id] = $res->att_id;

        // map thumbnails
        $map = array();
        foreach ($thumbnails as $thumbnail) {
            if (isset($featured_map[$thumbnail->post_id])) {
                $att_id = $featured_map[$thumbnail->post_id];
                $map[$thumbnail->meta_id] = $att_id;
            }
        }
        // meta_id -> att_id
        return $map;
    }

    function get_thumbnail_ids_gallery($thumbnails, $is_delete) {
        // join post_ids (sanitized)
        $ids_list = array();
        foreach ($thumbnails as $thumbnail)
            $ids_list[] = (int) $thumbnail->post_id;
        $ids = $this->sanitize_ids_csv($ids_list);

        // get gallery ids
        $result = $this->wpdb->get_results("
            SELECT post_id, meta_key, meta_value AS att_ids
            FROM {$this->postmeta} 
            WHERE post_id IN ({$ids}) 
            AND meta_key IN ('_product_image_gallery', '_wc_additional_variation_images')
        ");

        // map gallery ids
        $gallery_map = array();
        foreach ($result as $res)
            $gallery_map[$res->post_id] = $res->att_ids;

        // map thumbnails
        $map = array();
        $done = array(); // for duplicated URLs
        foreach ($thumbnails as $thumbnail) {
            if (!isset($gallery_map[$thumbnail->post_id])) // no metadata, only custom field
                continue;
            $att_ids = $gallery_map[$thumbnail->post_id];

            if ($is_delete) {
                $att_ids_csv = $this->sanitize_ids_csv($att_ids);
                $result = $this->wpdb->get_results("
                    SELECT post_id, SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(meta_value, '/', 5), '/', -1), '?', 1) AS storage_id
                    FROM {$this->postmeta} 
                    WHERE post_id IN ({$att_ids_csv})
                ");
                $storage_ids = array();
                foreach ($result as $res)
                    $storage_ids[$res->storage_id] = $res->post_id;
                $att_id = $storage_ids[$thumbnail->storage_id];
            } else {
                $att_ids_csv = $this->sanitize_ids_csv($att_ids);
                $result = $this->wpdb->get_results("
                    SELECT post_id, meta_value
                    FROM {$this->postmeta} 
                    WHERE meta_key = '_wp_attached_file'
                    AND post_id IN ({$att_ids_csv})
                ");
                $values = array();
                foreach ($result as $res) {
                    if (!isset($done[$res->post_id]))
                        $values[$res->meta_value] = $res->post_id;
                }
                $att_id = $values[$thumbnail->meta_value];
                $done[$att_id] = true;
            }
            $map[$thumbnail->meta_id] = $att_id;
        }
        return $map;
    }

    function speed_up_attachments($bucket_id, $thumbnails, $att_ids_map) {
        $count = 0;
        $query = "
        INSERT INTO {$this->posts} (id, post_content_filtered) VALUES ";

        foreach ($thumbnails as $thumbnail) {
            if (!isset($att_ids_map[$thumbnail->meta_id])) // no metadata, only custom field
                continue;

            $su_url = $this->get_su_url($bucket_id, $thumbnail->storage_id);

            if ($thumbnail->video_url && strpos($thumbnail->video_url, 'video-thumb=') === false)
                $su_url .= '?video-thumb=' . fifu_video_img_small($thumbnail->video_url);

            if ($count++ != 0)
                $query .= ", ";

            $query .= $this->wpdb->prepare("(%d, %s)", $att_ids_map[$thumbnail->meta_id], $su_url) . " ";
        }
        $query .= "ON DUPLICATE KEY UPDATE post_content_filtered=VALUES(post_content_filtered)";
        return $this->wpdb->get_results($query);
    }

    function get_thumbnail_meta_ids($thumbnails, $att_ids_map) {
        // Collect distinct numeric attachment post_ids
        $ids_arr = array();
        foreach ($thumbnails as $thumbnail) {
            if (!isset($att_ids_map[$thumbnail->meta_id])) // no metadata, only custom field
                continue;
            $ids_arr[] = (int) $att_ids_map[$thumbnail->meta_id];
        }
        $ids_arr = array_values(array_unique(array_filter($ids_arr, function ($v) {
                            return $v > 0;
                        })));

        // No IDs -> nothing to query
        if (empty($ids_arr)) {
            return array();
        }

        // Build prepared IN(...) and run the safe query
        $placeholders = implode(',', array_fill(0, count($ids_arr), '%d'));
        $sql = "
            SELECT meta_id, post_id
            FROM {$this->postmeta}
            WHERE post_id IN ($placeholders)
            AND meta_key = %s
        ";
        $params = array_merge($ids_arr, array('_wp_attached_file'));
        $result = $this->wpdb->get_results($this->wpdb->prepare($sql, $params));

        // map att_id -> meta_id
        $attid_metaid_map = array();
        foreach ($result as $res) {
            $attid_metaid_map[$res->post_id] = $res->meta_id;
        }

        // map meta_id (fifu metadata) -> meta_id (attachment metadata)
        $map = array();
        foreach ($thumbnails as $thumbnail) {
            if (!isset($att_ids_map[$thumbnail->meta_id])) // no metadata, only custom field
                continue;
            $att_id = (int) $att_ids_map[$thumbnail->meta_id];
            if (!isset($attid_metaid_map[$att_id])) // no attachment metadata
                continue;
            $map[$thumbnail->meta_id] = $attid_metaid_map[$att_id];
        }

        return $map;
    }

    function speed_up_attachments_meta($bucket_id, $thumbnails, $meta_ids_map) {
        $count = 0;
        $query = "
            INSERT INTO {$this->postmeta} (meta_id, meta_value) VALUES ";

        foreach ($thumbnails as $thumbnail) {
            if (!isset($meta_ids_map[$thumbnail->meta_id])) // no metadata, only custom field
                continue;

            $su_url = $this->get_su_url($bucket_id, $thumbnail->storage_id);

            if ($thumbnail->video_url && strpos($thumbnail->video_url, 'video-thumb=') === false)
                $su_url .= '?video-thumb=' . fifu_video_img_small($thumbnail->video_url);

            if ($count++ != 0)
                $query .= ", ";

            // Minimal change: use prepare to safely build each VALUES tuple
            $query .= $this->wpdb->prepare("(%d, %s)", $meta_ids_map[$thumbnail->meta_id], $su_url) . " ";
        }

        $query .= "ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)";
        return $this->wpdb->get_results($query);
    }

    function get_list_ids($thumbnails, $bucket_id, $is_delete, $video_urls) {
        // join post_ids (sanitized)
        $post_ids_normal_arr = array();
        $post_ids_slider_arr = array();
        foreach ($thumbnails as $thumbnail) {
            $is_slider = strpos($thumbnail->meta_key, 'slider') !== false;
            if ($is_slider)
                $post_ids_slider_arr[] = (int) $thumbnail->post_id;
            else
                $post_ids_normal_arr[] = (int) $thumbnail->post_id;
        }
        $post_ids_slider = $post_ids_slider_arr ? $this->sanitize_ids_csv($post_ids_slider_arr) : '';
        $post_ids_normal = $post_ids_normal_arr ? $this->sanitize_ids_csv($post_ids_normal_arr) : '';

        // get slider ids
        if ($post_ids_slider) {
            $result_slider = $this->wpdb->get_results("
                SELECT meta_id, post_id, meta_value
                FROM {$this->postmeta} 
                WHERE post_id IN ({$post_ids_slider}) 
                AND meta_key = 'fifu_slider_list_url'
            ");
        } else
            $result_slider = array();

        // get normal ids
        if ($post_ids_normal) {
            $result_normal = $this->wpdb->get_results("
                SELECT meta_id, post_id, meta_value
                FROM {$this->postmeta} 
                WHERE post_id IN ({$post_ids_normal}) 
                AND meta_key IN ('fifu_list_url', 'fifu_list_video_url')
            ");
        } else
            $result_normal = array();

        // map slider ids
        $slider_map = array();
        foreach ($result_slider as $res)
            $slider_map[$res->post_id] = array($res->meta_id, $res->meta_value);

        // map normal ids (post_id: array(array(meta_id, meta_value), arr...)
        // an array for images, another one for videos
        $normal_map = array();
        foreach ($result_normal as $res) {
            if (!isset($normal_map[$res->post_id]))
                $normal_map[$res->post_id] = array();
            array_push($normal_map[$res->post_id], array($res->meta_id, $res->meta_value));
        }

        // map thumbnails
        $map = array();
        foreach ($thumbnails as $thumbnail) {
            $arr = null;
            $is_slider = strpos($thumbnail->meta_key, 'slider') !== false;
            if ($is_slider) {
                if (isset($slider_map[$thumbnail->post_id]))
                    $arr = $slider_map[$thumbnail->post_id];
            } else {
                if (isset($normal_map[$thumbnail->post_id])) {
                    foreach ($normal_map[$thumbnail->post_id] as $list) {
                        if ($is_delete) {
                            $image_url = $this->get_su_url($bucket_id, $thumbnail->storage_id);
                            $video_url = $video_urls && isset($video_urls[$thumbnail->storage_id]) ? $video_urls[$thumbnail->storage_id] : null;
                        } else {
                            $image_url = $thumbnail->meta_value;
                            $video_url = $thumbnail->video_url;
                        }
                        $url = $video_url ? $video_url : $image_url;
                        if (strpos($list[1], $url) !== false) {
                            $arr = $list;
                            break;
                        }
                    }
                }
            }
            if ($arr)
                $map[$thumbnail->meta_id] = $arr;
        }
        return $map;
    }

    /* speed up (remove) */

    function remove_urls_su($bucket_id, $thumbnails, $urls, $video_urls) {
        foreach ($thumbnails as $thumbnail) {
            // post removed
            if (!$thumbnail->meta_id)
                unset($urls[$thumbnail->storage_id]);
        }

        if (empty($urls))
            return;

        // custom field
        $this->revert_custom_fields($thumbnails, $urls, $video_urls, false);

        // two groups
        $featured_list = array();
        $gallery_list = array();
        foreach ($thumbnails as $thumbnail) {
            if ($thumbnail->meta_key == 'fifu_image_url' || $thumbnail->meta_key == 'fifu_slider_image_url_0' || $thumbnail->meta_key == 'fifu_video_url')
                array_push($featured_list, $thumbnail);
            else
                array_push($gallery_list, $thumbnail);
        }

        // featured group
        if (count($featured_list) > 0) {
            $att_ids_map_featured = $this->get_thumbnail_ids($featured_list, false);
            if (count($att_ids_map_featured) > 0) {
                $this->revert_attachments($urls, $featured_list, $att_ids_map_featured);
                $meta_ids_map_featured = $this->get_thumbnail_meta_ids($featured_list, $att_ids_map_featured);
                if (count($meta_ids_map_featured) > 0)
                    $this->revert_attachments_meta($urls, $featured_list, $meta_ids_map_featured);
            }
        }

        // gallery group
        $att_ids_map_gallery = array();
        if (count($gallery_list) > 0) {
            $att_ids_map_gallery = $this->get_thumbnail_ids_gallery($gallery_list, true);
            if (count($att_ids_map_gallery) > 0) {
                $this->revert_attachments($urls, $gallery_list, $att_ids_map_gallery);
                $meta_ids_map_gallery = $this->get_thumbnail_meta_ids($gallery_list, $att_ids_map_gallery);
                if (count($meta_ids_map_gallery) > 0)
                    $this->revert_attachments_meta($urls, $gallery_list, $meta_ids_map_gallery);
            }
        }

        // lists
        $list_ids_map = $this->get_list_ids($thumbnails, $bucket_id, true, $video_urls);
        $this->revert_list_custom_fields($bucket_id, $urls, $video_urls, $thumbnails, $list_ids_map);

        $this->revert_featured_to_local($featured_list, false);
        $this->revert_gallery_to_local($gallery_list, $att_ids_map_gallery);
    }

    function ctgr_remove_urls_su($bucket_id, $thumbnails, $urls, $video_urls) {
        foreach ($thumbnails as $thumbnail) {
            // post removed
            if (!$thumbnail->meta_id)
                unset($urls[$thumbnail->storage_id]);
        }

        if (empty($urls))
            return;

        // custom field
        $this->revert_custom_fields($thumbnails, $urls, $video_urls, true);

        $featured_list = array();
        foreach ($thumbnails as $thumbnail)
            array_push($featured_list, $thumbnail);

        // featured group
        if (count($featured_list) > 0) {
            $att_ids_map = $this->get_thumbnail_ids($featured_list, true);
            if (count($att_ids_map) > 0) {
                $this->revert_attachments($urls, $featured_list, $att_ids_map);
                $meta_ids_map = $this->get_thumbnail_meta_ids($featured_list, $att_ids_map);
                if (count($meta_ids_map) > 0)
                    $this->revert_attachments_meta($urls, $featured_list, $meta_ids_map);
            }
        }

        $this->revert_featured_to_local($featured_list, true);
    }

    function revert_featured_to_local($featured_list, $is_ctgr) {
        if (empty($featured_list))
            return;

        foreach ($featured_list as $item) {
            // support objects/arrays with different property names
            $post_id = isset($item->post_id) ? intval($item->post_id) : null;
            if (!$post_id)
                continue;

            if ($is_ctgr) {
                $bkp_id = get_term_meta($post_id, 'bkp_thumbnail_id', true);
                $fifu_url = get_term_meta($post_id, 'fifu_image_url', true);
                $current_thumb = get_term_meta($post_id, 'thumbnail_id', true);
            } else {
                $bkp_id = get_post_meta($post_id, 'bkp_thumbnail_id', true);
                $fifu_url = get_post_meta($post_id, 'fifu_image_url', true);
                $current_thumb = get_post_meta($post_id, '_thumbnail_id', true);
            }

            if (!$bkp_id || !$fifu_url || !$current_thumb)
                continue;

            $bkp_id = intval($bkp_id);
            if ($bkp_id <= 0)
                continue;

            // verify attachment file exists on disk
            $attachment_exists = false;
            if (function_exists('get_attached_file')) {
                $file = get_attached_file($bkp_id);
                if ($file && file_exists($file)) {
                    $attachment_exists = true;
                }
            }

            if (!$attachment_exists) {
                if ($is_ctgr)
                    delete_term_meta($post_id, 'bkp_thumbnail_id');
                else
                    delete_post_meta($post_id, 'bkp_thumbnail_id');
                continue;
            }

            // perform revert: call dev helper, update thumb and remove backup
            if ($is_ctgr) {
                if (function_exists('fifu_dev_set_category_image')) {
                    fifu_dev_set_category_image($post_id, null);
                }
                update_term_meta($post_id, 'thumbnail_id', $bkp_id);
                delete_term_meta($post_id, 'bkp_thumbnail_id');
            } else {
                if (function_exists('fifu_dev_set_image')) {
                    fifu_dev_set_image($post_id, null);
                }
                update_post_meta($post_id, '_thumbnail_id', $bkp_id);
                delete_post_meta($post_id, 'bkp_thumbnail_id');
            }
        }
    }

    function revert_gallery_to_local($gallery_list, $att_ids_map_gallery) {
        if (empty($gallery_list))
            return;

        foreach ($gallery_list as $item) {
            $post_id = isset($item->post_id) ? intval($item->post_id) : null;
            if (!$post_id)
                continue;

            $bkp_gallery = get_post_meta($post_id, 'bkp_product_image_gallery', true);
            $current_gallery = get_post_meta($post_id, '_product_image_gallery', true);

            if (!$bkp_gallery || !$current_gallery)
                continue;

            // Recover att_id using meta_id and att_ids_map_gallery
            $meta_id = isset($item->meta_id) ? intval($item->meta_id) : null;
            $att_id = ($meta_id && isset($att_ids_map_gallery[$meta_id])) ? intval($att_ids_map_gallery[$meta_id]) : null;
            if (!$att_id || $att_id <= 0) {
                continue;
            }

            $bkp_gallery_arr = explode(',', $bkp_gallery);
            $current_gallery_arr = explode(',', $current_gallery);

            if (!in_array($att_id, $current_gallery_arr)) {
                continue;
            }

            // Get the URL of the current att_id
            $cur_url = wp_get_attachment_url($att_id);
            if (!$cur_url) {
                continue;
            }

            // Find which backup att_id matches the current URL
            $matched_bkp_att_id = null;
            foreach ($bkp_gallery_arr as $bkp_att_id) {
                $bkp_url = wp_get_attachment_url($bkp_att_id);
                if ($bkp_url && strpos($cur_url, $bkp_url) !== false) {
                    $matched_bkp_att_id = $bkp_att_id;
                    break;
                }
            }

            if (function_exists('get_attached_file')) {
                $file = get_attached_file($matched_bkp_att_id);
                if (!$file || !file_exists($file)) {
                    continue;
                }
            }

            // Replace the current att_id with the matched backup att_id
            $current_gallery_arr[array_search($att_id, $current_gallery_arr)] = $matched_bkp_att_id;

            // Remove the matched backup att id from the backup gallery list
            $bkp_gallery_arr = array_diff($bkp_gallery_arr, [$matched_bkp_att_id]);

            // Update the gallery meta
            $new_gallery = implode(',', $current_gallery_arr);
            update_post_meta($post_id, '_product_image_gallery', $new_gallery);

            // Find and delete the corresponding fifu_image_url_N meta
            $meta_keys = $this->wpdb->get_col(
                    $this->wpdb->prepare(
                            "SELECT meta_key FROM {$this->postmeta} WHERE post_id = %d AND meta_key LIKE 'fifu_image_url_%%'",
                            $post_id
                    )
            );
            foreach ($meta_keys as $meta_key) {
                $meta_value = get_post_meta($post_id, $meta_key, true);
                if ($meta_value === $cur_url) {
                    delete_post_meta($post_id, $meta_key);
                }
            }

            if ($att_id) {
                // Delete the attachment post from wp_posts
                wp_delete_attachment($att_id, true);

                // Delete all wp_postmeta entries for this attachment
                $this->wpdb->query(
                        $this->wpdb->prepare(
                                "DELETE FROM {$this->postmeta} WHERE post_id = %d",
                                $att_id
                        )
                );
            }

            // If backup gallery is now empty, delete it
            if (empty($bkp_gallery_arr)) {
                delete_post_meta($post_id, 'bkp_product_image_gallery');
            } else {
                // Otherwise, update the backup gallery meta
                update_post_meta($post_id, 'bkp_product_image_gallery', implode(',', $bkp_gallery_arr));
            }
        }
    }

    public function usage_verification_su($hex_ids) {
        $postmeta_results = $this->wpdb->get_col("
            SELECT meta_value
            FROM {$this->postmeta}
            WHERE meta_key LIKE 'fifu_%'
            AND meta_value LIKE 'https://cdn.fifu.app/%'
        ");

        $termmeta_results = $this->wpdb->get_col("
            SELECT meta_value
            FROM {$this->termmeta}
            WHERE meta_key LIKE 'fifu_%'
            AND meta_value LIKE 'https://cdn.fifu.app/%'
        ");

        $all_results = array_merge($postmeta_results, $termmeta_results);

        // Filter results using PHP
        $filtered_results = array_filter($all_results, function ($meta_value) use ($hex_ids) {
            // Split by "-" and take the first part
            $dash_split = explode('-', $meta_value);
            $first_part = $dash_split[0] ?? '';

            // Split the first part by "/" and take the last segment
            $slash_split = explode('/', $first_part);
            $hex_id = end($slash_split);

            // Check if the extracted hex_id is in the provided list
            return in_array($hex_id, $hex_ids, true);
        });

        return $filtered_results;
    }

    /* speed up (backup att ids) */

    function backup_att_ids($post_ids) {
        $ids_csv = $this->sanitize_ids_csv($post_ids);
        $this->wpdb->query("
            INSERT INTO {$this->postmeta} (post_id, meta_key, meta_value) ( 
                SELECT pm.post_id, CONCAT('bkp', pm.meta_key) AS meta_key, pm.meta_value 
                FROM {$this->postmeta} pm
                WHERE pm.post_id IN ({$ids_csv})
                AND pm.meta_key IN ('_thumbnail_id', '_product_image_gallery')
                AND NOT EXISTS (
                    SELECT 1
                    FROM {$this->postmeta} pm2
                    WHERE pm2.post_id = pm.post_id 
                    AND pm2.meta_key IN ('bkp_thumbnail_id', 'bkp_product_image_gallery')
                )
            )
        ");
    }

    function ctgr_backup_att_ids($term_ids) {
        $ids_csv = $this->sanitize_ids_csv($term_ids);
        $this->wpdb->query("
            INSERT INTO {$this->termmeta} (term_id, meta_key, meta_value) ( 
                SELECT tm.term_id, CONCAT('bkp_', tm.meta_key) AS meta_key, tm.meta_value 
                FROM {$this->termmeta} tm
                WHERE tm.term_id IN ({$ids_csv})
                AND tm.meta_key = 'thumbnail_id'
                AND NOT EXISTS (
                    SELECT 1
                    FROM {$this->termmeta} tm2
                    WHERE tm2.term_id = tm.term_id 
                    AND tm2.meta_key = 'bkp_thumbnail_id'
                )
            )
        ");
    }

    /* speed up (delete att ids) */

    function delete_att_ids($post_ids) {
        $ids_csv = $this->sanitize_ids_csv($post_ids);
        $this->wpdb->query("
            DELETE FROM {$this->postmeta}
            WHERE post_id IN ({$ids_csv})
            AND meta_key IN ('_thumbnail_id', '_product_image_gallery')
        ");
        // Clear meta cache for affected posts
        if (is_array($post_ids)) {
            foreach ($post_ids as $post_id) {
                wp_cache_delete($post_id, 'post_meta');
            }
        } elseif (is_string($post_ids)) {
            $ids = explode(',', $post_ids);
            foreach ($ids as $post_id) {
                $post_id = trim($post_id);
                if (is_numeric($post_id)) {
                    wp_cache_delete((int) $post_id, 'post_meta');
                }
            }
        }
    }

    function ctgr_delete_att_ids($term_ids) {
        $ids_csv = $this->sanitize_ids_csv($term_ids);
        $this->wpdb->query("
            DELETE FROM {$this->termmeta}
            WHERE term_id IN ({$ids_csv})
            AND meta_key = 'thumbnail_id'
        ");
        // Clear meta cache for affected terms
        if (is_array($term_ids)) {
            foreach ($term_ids as $term_id) {
                wp_cache_delete($term_id, 'term_meta');
            }
        } elseif (is_string($term_ids)) {
            $ids = explode(',', $term_ids);
            foreach ($ids as $term_id) {
                $term_id = trim($term_id);
                if (is_numeric($term_id)) {
                    wp_cache_delete((int) $term_id, 'term_meta');
                }
            }
        }
    }

    /* speed up (add custom fields) */

    function add_custom_fields($values) {
        $query = "
            INSERT INTO {$this->postmeta} (post_id, meta_key, meta_value) VALUES {$values}";
        return $this->wpdb->get_results($query);
    }

    function ctgr_add_custom_fields($values) {
        $query = "
            INSERT INTO {$this->termmeta} (term_id, meta_key, meta_value) VALUES {$values}";
        return $this->wpdb->get_results($query);
    }

    function get_internal_urls($post_ids) {
        $this->wpdb->query("SET SESSION group_concat_max_len = 1048576;"); // because GROUP_CONCAT is limited to 1024 characters
        $ids_csv = $this->sanitize_ids_csv($post_ids);
        return $this->wpdb->get_results("
            SELECT p.id AS att_id, p.guid AS url
            FROM {$this->posts} p
            WHERE FIND_IN_SET(p.id, 
                (
                    SELECT GROUP_CONCAT(pm.meta_value) AS att_ids
                    FROM {$this->postmeta} pm
                    WHERE pm.post_id IN ({$ids_csv})
                    AND meta_key IN ('bkp_thumbnail_id', 'bkp_product_image_gallery')
                )
            )
        ");
    }

    function get_ctgr_internal_urls($term_ids) {
        $this->wpdb->query("SET SESSION group_concat_max_len = 1048576;"); // because GROUP_CONCAT is limited to 1024 characters
        $ids_csv = $this->sanitize_ids_csv($term_ids);
        return $this->wpdb->get_results("
            SELECT p.id AS att_id, p.guid AS url
            FROM {$this->posts} p
            WHERE FIND_IN_SET(p.id, 
                (
                    SELECT GROUP_CONCAT(tm.meta_value) AS att_ids
                    FROM {$this->termmeta} tm
                    WHERE tm.term_id IN ({$ids_csv})
                    AND meta_key = 'bkp_thumbnail_id'
                )
            )
        ");
    }

    function revert_custom_fields($thumbnails, $urls, $video_urls, $is_ctgr) {
        $table = $is_ctgr ? $this->termmeta : $this->postmeta;

        // Return early if no thumbnails to process
        if (empty($thumbnails)) {
            return null;
        }

        $query = "
            INSERT INTO {$table} (meta_id, meta_value) VALUES ";
        $count = 0;

        foreach ($thumbnails as $thumbnail) {
            if ($count++ != 0) {
                $query .= ", ";
            }

            $video_url = isset($video_urls[$thumbnail->storage_id]) ? $video_urls[$thumbnail->storage_id] : null;
            $url = $video_url ? $video_url : (isset($urls[$thumbnail->storage_id]) ? $urls[$thumbnail->storage_id] : '');

            // Minimal change: build each VALUES tuple with prepare to avoid SQL injection
            $query .= $this->wpdb->prepare("(%d, %s)", (int) $thumbnail->meta_id, $url);
        }

        $query .= " ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)";
        return $this->wpdb->get_results($query);
    }

    function revert_attachments($urls, $thumbnails, $att_ids_map) {
        // Handle null or invalid parameters
        if ($urls === null || !is_array($urls))
            $urls = [];
        if ($thumbnails === null || !is_array($thumbnails))
            $thumbnails = [];
        if ($att_ids_map === null || !is_array($att_ids_map))
            $att_ids_map = [];

        $count = 0;
        $query = "
            INSERT INTO {$this->posts} (id, post_content_filtered) VALUES ";

        foreach ($thumbnails as $thumbnail) {
            if (!isset($att_ids_map[$thumbnail->meta_id])) // no metadata, only custom field
                continue;

            if ($count++ != 0)
                $query .= ", ";

            $url = isset($urls[$thumbnail->storage_id]) ? $urls[$thumbnail->storage_id] : '';

            // Minimal change: use prepare to safely build each VALUES tuple
            $query .= $this->wpdb->prepare("(%d, %s)", (int) $att_ids_map[$thumbnail->meta_id], $url);
        }

        // If no thumbnails were processed, return early
        if ($count == 0) {
            return array(); // Return empty array instead of running invalid query
        }

        $query .= "ON DUPLICATE KEY UPDATE post_content_filtered=VALUES(post_content_filtered)";
        return $this->wpdb->get_results($query);
    }

    function revert_attachments_meta($urls, $thumbnails, $meta_ids_map) {
        $count = 0;
        $query = "
            INSERT INTO {$this->postmeta} (meta_id, meta_value) VALUES ";

        foreach ($thumbnails as $thumbnail) {
            if (!isset($meta_ids_map[$thumbnail->meta_id])) // no metadata, only custom field
                continue;

            if ($count++ != 0)
                $query .= ", ";

            $url = isset($urls[$thumbnail->storage_id]) ? $urls[$thumbnail->storage_id] : '';

            // Minimal change: safely build each VALUES tuple
            $query .= $this->wpdb->prepare("(%d, %s)", (int) $meta_ids_map[$thumbnail->meta_id], $url);
        }

        // Only execute query if there are valid operations to perform
        if ($count > 0) {
            $query .= "ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)";
            return $this->wpdb->get_results($query);
        }

        // Return empty array if no valid operations were found
        return array();
    }

    function revert_list_custom_fields($bucket_id, $urls, $video_urls, $thumbnails, $list_ids_map) {
        $map1_image = $map2_image = array();
        $map1_video = $map2_video = array();

        foreach ($thumbnails as $thumbnail) {
            if (!isset($list_ids_map[$thumbnail->meta_id]))
                continue;

            $video_url = isset($video_urls[$thumbnail->storage_id]) ? $video_urls[$thumbnail->storage_id] : null;

            if ($video_url) {
                $str_list = isset($map1_video[$thumbnail->post_id]) ? $map1_video[$thumbnail->post_id] : $list_ids_map[$thumbnail->meta_id][1];
                $pattern = '/' . str_replace('/', '\/', preg_quote($video_url)) . '.fifu-thumb=[^|]+/';
                $map1_video[$thumbnail->post_id] = preg_replace($pattern, $video_url, $str_list);
                $map2_video[$list_ids_map[$thumbnail->meta_id][0]] = $thumbnail->post_id;
            } else {
                $str_list = isset($map1_image[$thumbnail->post_id]) ? $map1_image[$thumbnail->post_id] : $list_ids_map[$thumbnail->meta_id][1];
                $map1_image[$thumbnail->post_id] = str_replace($this->get_su_url($bucket_id, $thumbnail->storage_id), $urls[$thumbnail->storage_id], $str_list);
                $map2_image[$list_ids_map[$thumbnail->meta_id][0]] = $thumbnail->post_id;
            }
        }

        if (!empty($map1_image)) {
            $query = "
                INSERT INTO {$this->postmeta} (meta_id, meta_value) VALUES ";
            $count = 0;
            foreach ($map2_image as $key => $value) {
                if ($count++ != 0)
                    $query .= ", ";
                // Minimal change: safely build tuple
                $query .= $this->wpdb->prepare("(%d, %s)", (int) $key, $map1_image[$value]) . " ";
            }
            $query .= "ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)";
            $this->wpdb->query($query);
        }

        if (!empty($map1_video)) {
            $query = "
                INSERT INTO {$this->postmeta} (meta_id, meta_value) VALUES ";
            $count = 0;
            foreach ($map2_video as $key => $value) {
                if ($count++ != 0)
                    $query .= ", ";
                // Minimal change: safely build tuple
                $query .= $this->wpdb->prepare("(%d, %s)", (int) $key, $map1_video[$value]) . " ";
            }
            $query .= "ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)";
            $this->wpdb->query($query);
        }
    }

    // speed up (db)

    function create_table_invalid_media_su() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        maybe_create_table($this->fifu_invalid_media_su, "
            CREATE TABLE {$this->fifu_invalid_media_su} (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, 
                md5 VARCHAR(32) NOT NULL,
                attempts INT NOT NULL,
                UNIQUE KEY (md5)
            )
        ");
    }

    function insert_invalid_media_su($url) {
        if ($url === null || $url === '')
            return;
        if ($this->get_attempts_invalid_media_su($url)) {
            $this->update_invalid_media_su($url);
            return;
        }

        $md5 = md5($url);
        $this->wpdb->query(
                $this->wpdb->prepare(
                        "INSERT INTO {$this->fifu_invalid_media_su} (md5, attempts) VALUES (%s, 1)",
                        $md5
                )
        );
    }

    function update_invalid_media_su($url) {
        if ($url === null || $url === '')
            return;
        $md5 = md5($url);
        $this->wpdb->query(
                $this->wpdb->prepare(
                        "UPDATE {$this->fifu_invalid_media_su} SET attempts = attempts + 1 WHERE md5 = %s",
                        $md5
                )
        );
    }

    function get_attempts_invalid_media_su($url) {
        if ($url === null || $url === '')
            return 0;
        $md5 = md5($url);
        $result = $this->wpdb->get_row(
                $this->wpdb->prepare(
                        "SELECT attempts FROM {$this->fifu_invalid_media_su} WHERE md5 = %s",
                        $md5
                )
        );
        return $result ? (int) $result->attempts : 0;
    }

    function delete_invalid_media_su($url) {
        if ($url === null || $url === '')
            return;
        $md5 = md5($url);
        $this->wpdb->query(
                $this->wpdb->prepare(
                        "DELETE FROM {$this->fifu_invalid_media_su} WHERE md5 = %s",
                        $md5
                )
        );
    }

    ///////////////////////////////////////////////////////////////////////////////////

    function count_available_images() {
        $total = 0;

        $featured = $this->wpdb->get_results("
            SELECT COUNT(1) AS total
            FROM {$this->postmeta}
            WHERE meta_key = '_thumbnail_id'
        ");

        $total += (int) $featured[0]->total;

        if (class_exists('WooCommerce')) {
            $gallery = $this->wpdb->get_results("
                SELECT SUM(LENGTH(meta_value) - LENGTH(REPLACE(meta_value, ',', '')) + 1) AS total
                FROM {$this->postmeta}
                WHERE meta_key = '_product_image_gallery'
            ");

            $total += (int) $gallery[0]->total;

            $category = $this->wpdb->get_results("
                SELECT COUNT(1) AS total
                FROM {$this->termmeta}
                WHERE meta_key = 'thumbnail_id'
            ");

            $total += (int) $category[0]->total;
        }

        return $total;
    }

    /* insert attachment */

    function insert_attachment_by($value) {
        // $value should be a list of PREPARED tuples (e.g., from get_formatted_value), joined by ', '
        $values_sql = is_array($value) ? implode(', ', $value) : (string) $value;

        $sql = "
            INSERT INTO {$this->posts}
                (post_author, guid, post_title, post_excerpt, post_mime_type, post_type, post_status, post_parent,
                post_date, post_date_gmt, post_modified, post_modified_gmt, post_content, to_ping, pinged, post_content_filtered)
            VALUES {$values_sql}";
        return $this->wpdb->query($sql);
    }

    function insert_ctgr_attachment_by($value) {
        // $value should be a list of PREPARED tuples (e.g., from get_ctgr_formatted_value), joined by ', '
        $values_sql = is_array($value) ? implode(', ', $value) : (string) $value;

        $sql = "
            INSERT INTO {$this->posts}
                (post_author, guid, post_title, post_excerpt, post_mime_type, post_type, post_status, post_parent,
                post_date, post_date_gmt, post_modified, post_modified_gmt, post_content, to_ping, pinged, post_content_filtered, post_name)
            VALUES {$values_sql}";
        return $this->wpdb->query($sql);
    }

    function get_formatted_value($url, $alt, $post_parent) {
        $alt = $alt ?? '';
        // Return a PREPARED tuple; caller concatenates multiple with ", "
        return $this->wpdb->prepare(
                        "(%d, %s, %s, %s, %s, %s, %s, %d, NOW(), NOW(), NOW(), NOW(), %s, %s, %s, %s)",
                        (int) $this->author, // post_author
                        '', // guid
                        $alt, // post_title
                        $alt, // post_excerpt
                        'image/jpeg', // post_mime_type
                        'attachment', // post_type
                        'inherit', // post_status
                        (int) $post_parent, // post_parent
                        '', // post_content
                        '', // to_ping
                        '', // pinged
                        $url                           // post_content_filtered
                );
    }

    function get_ctgr_formatted_value($url, $alt, $post_parent) {
        $alt = $alt ?? '';
        // Return a PREPARED tuple; caller concatenates multiple with ", "
        return $this->wpdb->prepare(
                        "(%d, %s, %s, %s, %s, %s, %s, %d, NOW(), NOW(), NOW(), NOW(), %s, %s, %s, %s, %s)",
                        (int) $this->author, // post_author
                        '', // guid
                        $alt, // post_title
                        $alt, // post_excerpt
                        'image/jpeg', // post_mime_type
                        'attachment', // post_type
                        'inherit', // post_status
                        (int) $post_parent, // post_parent
                        '', // post_content
                        '', // to_ping
                        '', // pinged
                        $url, // post_content_filtered
                        'fifu-category-' . (int) $post_parent// post_name
                );
    }

    /* product variation */

    function get_product_image_gallery($post_id) {
        return rtrim(get_post_meta($post_id, '_product_image_gallery', true), ',');
    }

    function get_thumbnail_id($post_id, $is_ctgr) {
        $ctgr_sql = $is_ctgr ? "AND post_name LIKE 'fifu-category%'" : "";

        $sql = $this->wpdb->prepare(
                "SELECT MIN(id) AS id 
             FROM {$this->posts} 
             WHERE post_parent = %d 
             {$ctgr_sql}  
             AND post_type = 'attachment'",
                (int) $post_id
        );
        $result = $this->wpdb->get_results($sql);
        return $result ? $result[0]->id : null;
    }

    function get_attachments($post_id, $is_ctgr) {
        $ctgr_sql = $is_ctgr ? "AND post_name LIKE 'fifu-category%'" : "";

        $ids = null;
        $i = 1;
        $sql = $this->wpdb->prepare(
                "SELECT id 
             FROM {$this->posts} 
             WHERE post_parent = %d 
             {$ctgr_sql}  
             AND post_type = 'attachment'",
                (int) $post_id
        );
        $result = $this->wpdb->get_results($sql);
        foreach ($result as $res)
            $ids = ($i++ == 1) ? $res->id : ($ids . "," . $res->id);
        return $ids;
    }

    function insert_attachment_list($post_id, $urls, $alts, $is_slider, $video_urls) {
        $post_id = (int) $post_id;

        $i = 0;
        $video_i = $urls ? 1 : 0;

        // Ensure urls is an array
        if (!$urls) {
            $urls = [];
        }

        // merge the lists of urls
        if ($video_urls) {
            foreach ($video_urls as $video_url) {
                $urls[] = $video_url;
            }
        }

        $query_meta = "INSERT INTO {$this->postmeta} (post_id, meta_key, meta_value) VALUES ";

        foreach ($urls as $url) {
            $url = esc_url_raw(trim((string) $url));
            $alt = ($alts && count($alts) > $i) ? trim((string) $alts[$i]) : '';

            $is_video_url = fifu_is_video($url);
            $image_url = $is_video_url ? fifu_video_img_large($url, $post_id, false) : $url;

            // Insert attachment (tuple should be PREPARED inside get_formatted_value)
            $value = $this->get_formatted_value($image_url, $alt, $post_id);
            if ($value) {
                // insert_attachment_by must accept already-prepared tuples (no extra quoting inside)
                $this->insert_attachment_by($value);
                $att_id = (int) $this->wpdb->insert_id;
                update_post_meta($att_id, '_wp_attached_file', $image_url);
                if ($alt !== '') {
                    update_post_meta($att_id, '_wp_attachment_image_alt', $alt);
                }
            }

            // urls meta
            if ($is_slider) {
                $meta_key_url = "fifu_slider_image_url_{$i}";
            } elseif ($is_video_url) {
                $meta_key_url = 'fifu_video_url' . ($video_i == 0 ? '' : '_' . ($video_i - 1));
            } else {
                $meta_key_url = 'fifu_image_url' . ($i == 0 ? '' : '_' . ($i - 1));
            }

            $tuple_url = $this->wpdb->prepare("(%d, %s, %s)", $post_id, $meta_key_url, $url);
            $this->wpdb->query($query_meta . $tuple_url);

            // alt meta (skip for video URL)
            if ($is_slider) {
                $meta_key_alt = "fifu_slider_image_alt_{$i}";
            } elseif ($is_video_url) {
                $meta_key_alt = null;
            } else {
                $meta_key_alt = 'fifu_image_alt' . ($i == 0 ? '' : '_' . ($i - 1));
            }

            if ($alt !== '' && $meta_key_alt) {
                $tuple_alt = $this->wpdb->prepare("(%d, %s, %s)", $post_id, $meta_key_alt, $alt);
                $this->wpdb->query($query_meta . $tuple_alt);
            }

            $i++;
            if ($is_video_url)
                $video_i++;
        }

        $thumbnail_id = (int) $this->get_thumbnail_id($post_id, false);
        update_post_meta($post_id, '_thumbnail_id', $thumbnail_id);

        delete_post_meta($post_id, '_product_image_gallery');

        // Rebuild _product_image_gallery safely
        $sql_gallery = $this->wpdb->prepare("
            INSERT INTO {$this->postmeta} (post_id, meta_key, meta_value)
            (
                SELECT p.post_parent, %s, GROUP_CONCAT(p.id)
                FROM {$this->posts} p
                WHERE p.post_parent = %d
                AND p.post_name NOT LIKE %s
                AND p.id <> %d
                AND p.post_type = %s
                GROUP BY p.post_parent
            )
        ",
                '_product_image_gallery',
                $post_id,
                'fifu-category%',
                $thumbnail_id,
                'attachment'
        );
        $this->wpdb->query($sql_gallery);
    }

    function update_attachment_list($post_id, $urls, $alts, $is_slider, $video_urls) {
        $attachments = $this->get_attachments($post_id, false);
        if ($attachments) {
            $ids_csv = $this->sanitize_ids_csv($attachments);
            $this->wpdb->query("DELETE FROM {$this->postmeta} WHERE post_id IN ({$ids_csv})");
            $this->wpdb->query("DELETE FROM {$this->posts} WHERE id IN ({$ids_csv})");
        }
        $this->wpdb->query($this->wpdb->prepare("DELETE FROM {$this->postmeta} WHERE post_id = %d AND meta_key IN ('_product_image_gallery', '_thumbnail_id')", (int) $post_id));
        $this->wpdb->query($this->wpdb->prepare("DELETE FROM {$this->postmeta} WHERE post_id = %d AND meta_key LIKE 'fifu_%'", (int) $post_id));
        if (!empty($urls) && !empty($urls[0]))
            $this->insert_attachment_list($post_id, $urls, $alts, $is_slider, $video_urls);
    }

    /* variation gallery */

    function update_wc_additional_variation_images($post_id) {
        wp_cache_flush();
        $ids = '';
        foreach ($this->get_variantion_products($post_id) as $res) {
            $gallery_ids = get_post_meta($res->id, '_product_image_gallery', true);
            if ($gallery_ids)
                update_post_meta($res->id, '_wc_additional_variation_images', $gallery_ids);
            else {
                $additional_ids = get_post_meta($res->id, '_wc_additional_variation_images');
                if ($additional_ids) {
                    $additional_ids = explode(',', $additional_ids[0]);
                    foreach ($additional_ids as $id) {
                        if (get_post($id) == null)
                            update_post_meta($res->id, '_wc_additional_variation_images', '');
                    }
                }
            }
        }
    }

    /* auto set category image */

    function insert_auto_category_image() {
        $this->delete_empty_urls();
        $this->delete_empty_urls_category();

        $this->insert_category_images_auto();

        $this->update_category_images_auto();

        $this->insert_attachment_category();
        $this->insert_auto_subcategory_image();
    }

    function insert_auto_subcategory_image() {
        foreach ($this->get_child_category() as $i) {
            if ($this->exists_child_with_attachment($i->term_id, $i->parent)) {
                $att_id = get_term_meta($i->term_id, 'thumbnail_id', true);
                update_term_meta($i->parent, 'thumbnail_id', $att_id);
            }
        }
    }

    /* insert fake internal featured image */

    function insert_attachment_category() {
        foreach ($this->get_categories_without_meta() as $res) {
            $term_id = $res->post_id;
            $alt = get_term_meta($term_id, 'fifu_image_alt', true);
            $url = get_term_meta($term_id, 'fifu_video_url', true);
            $url = $url ? fifu_video_img_large($url, $term_id, true) : get_term_meta($term_id, 'fifu_image_url', true);
            $url = htmlspecialchars_decode($url);
            $value = $this->get_ctgr_formatted_value($url, $alt, $term_id);
            $this->insert_ctgr_attachment_by($value);
            $att_id = $this->wpdb->insert_id;
            update_term_meta($term_id, 'thumbnail_id', $att_id);
            update_post_meta($att_id, '_wp_attached_file', $url);
            $alt && update_post_meta($att_id, '_wp_attachment_image_alt', $alt);
        }
    }

    function insert_attachment() {
        foreach ($this->get_posts_without_meta() as $res) {
            $post_id = $res->post_id;
            $alt = get_post_meta($post_id, 'fifu_image_alt', true);
            $url = fifu_main_image_url($post_id, false);
            $value = $this->get_formatted_value($url, $alt, $post_id);
            $this->insert_attachment_by($value);
            $att_id = $this->wpdb->insert_id;
            update_post_meta($post_id, '_thumbnail_id', $att_id);
            update_post_meta($att_id, '_wp_attached_file', $url);
            $alt && update_post_meta($att_id, '_wp_attachment_image_alt', $alt);
        }
    }

    function insert_attachment_gallery() {
        $ids = null;
        $i = 1;
        foreach ($this->get_posts_with_external_gallery_without_meta() as $res) {
            $post_id = $res->post_id;
            $ids = ($i == 1) ? $post_id : ($ids . "," . $post_id);
            foreach ($this->get_gallery_urls($post_id) as $res2) {
                $url = $res2->meta_value;
                $url = fifu_is_video($url) ? fifu_video_img_large($url, $post_id, false) : $url;
                $url = htmlspecialchars_decode($url);
                $value = $this->get_formatted_value($url, '', $post_id);
                $this->insert_attachment_by($value);
                $att_id = $this->wpdb->insert_id;
                update_post_meta($att_id, '_wp_attached_file', $url);
                $i++;
            }
            $this->delete_product_image_gallery_by($ids, false);
            $this->insert_product_image_gallery($ids, false);
            $this->insert_wc_additional_variation_images($ids, false);
        }
    }

    /* auto set: update all */

    function create_table_content() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        maybe_create_table($this->fifu_content, "
            CREATE TABLE {$this->fifu_content} (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                post_ids TEXT NOT NULL,
                type VARCHAR(8) NOT NULL
            )
        ");
    }

    function prepare_content() {
        $this->wpdb->query("SET SESSION group_concat_max_len = 1048576;"); // because GROUP_CONCAT is limited to 1024 characters

        $last_insert_id = null;

        // post (cpt)
        // Create a temporary table with an AUTO_INCREMENT column to generate row numbers
        $this->wpdb->query("
            CREATE TEMPORARY TABLE temp_post_content (
                id INT AUTO_INCREMENT PRIMARY KEY,
                post_id INT
            );
        ");

        if (fifu_is_on('fifu_ovw_first')) {
            // Insert distinct post_ids into the temporary table, applying the necessary conditions
            $sql = "
                INSERT INTO temp_post_content (post_id)
                SELECT DISTINCT p.ID AS post_id
                FROM {$this->posts} p
                WHERE p.post_type IN ('{$this->types}')
                AND post_status NOT IN ('auto-draft', 'trash')
                AND NOT EXISTS (
                    SELECT 1 
                    FROM {$this->postmeta} pm 
                    WHERE p.id = pm.post_id 
                    AND pm.meta_key = '_thumbnail_id'
                    AND pm.meta_value IN (
                        SELECT p2.id
                        FROM {$this->posts} p2
                        WHERE p2.post_author <> %d
                    )
                )
                ORDER BY p.ID
            ";
            $this->wpdb->query($this->wpdb->prepare($sql, $this->author));
        } else {
            $this->wpdb->query("
                INSERT INTO temp_post_content (post_id)
                SELECT p.ID AS post_id
                FROM {$this->posts} p
                WHERE p.post_type IN ('$this->types')
                AND post_status NOT IN ('auto-draft', 'trash')
                AND NOT EXISTS (
                    SELECT 1 
                    FROM {$this->postmeta} pm 
                    WHERE p.id = pm.post_id 
                    AND pm.meta_key IN ('fifu_image_url', 'fifu_video_url', '_thumbnail_id')
                )
                ORDER BY p.ID
            ");
        }

        // Insert into the final table from the temporary table and group by row number
        $this->wpdb->query("
            INSERT INTO {$this->fifu_content} (post_ids, type)
            SELECT GROUP_CONCAT(post_id ORDER BY post_id SEPARATOR ','), 'post'
            FROM temp_post_content
            GROUP BY FLOOR((id - 1) / 100);
        ");

        // Drop the temporary table
        $this->wpdb->query("
            DROP TEMPORARY TABLE temp_post_content;
        ");

        $last_insert_id = $this->wpdb->insert_id;
        if ($last_insert_id) {
            $this->log_prepare($last_insert_id, $this->fifu_content);
        }
    }

    function get_content() {
        return $this->wpdb->get_results("
            SELECT id AS post_id
            FROM {$this->fifu_content}
        ");
    }

    function insert_content($id) {
        $result = $this->wpdb->get_results(
                $this->wpdb->prepare(
                        "SELECT post_ids FROM {$this->fifu_content} WHERE id = %d",
                        (int) $id
                )
        );

        $this->wpdb->query(
                $this->wpdb->prepare(
                        "DELETE FROM {$this->fifu_content} WHERE id = %d",
                        (int) $id
                )
        );

        fifu_plugin_log(['insert_content' => ['id' => $id,]]);

        if (count($result) == 0)
            return false;

        // insert 1 attachment for each selected post
        $ids = $result[0]->post_ids;
        $post_ids = explode(",", $ids);
        foreach ($post_ids as $post_id) {
            $post_content = get_post_field('post_content', $post_id);
            $content = html_entity_decode($post_content);

            // set featured image
            $image_url = fifu_first_url_in_content($post_id, $content, false);
            $video_url = fifu_first_url_in_content($post_id, $content, true);

            if ($image_url || $video_url) {
                $url = null;
                $is_image = false;
                $is_video = false;

                if ($image_url && $video_url) {
                    if (get_option('fifu_html_media') != 'image') {
                        $is_video = true;
                    } else {
                        $is_image = true;
                    }
                } elseif ($image_url) {
                    $is_image = true;
                } elseif ($video_url) {
                    $is_video = true;
                }

                if ($is_image) {
                    fifu_dev_set_image($post_id, $image_url);
                    fifu_dev_set_video($post_id, '');
                } elseif ($is_video) {
                    fifu_dev_set_image($post_id, '');
                    fifu_dev_set_video($post_id, $video_url);
                }
            }
        }

        fifu_set_transient('fifu_content_counter', fifu_get_transient('fifu_content_counter') - count($post_ids), 0);

        return true;
    }

    /* dimensions: clean all */

    function clean_dimensions_all() {
        // Ensure author ID is numeric
        $author_id = (int) $this->author;

        // Build a prepared statement with placeholders
        $query = $this->wpdb->prepare(
                "
            DELETE FROM {$this->postmeta} pm
            WHERE pm.meta_key = %s
            AND EXISTS (
                SELECT 1
                FROM {$this->posts} p
                WHERE p.id = pm.post_id
                AND p.post_author = %d
            )
            ",
                '_wp_attachment_metadata', // %s placeholder for meta_key
                $author_id                    // %d placeholder for author
        );

        // Execute the prepared query
        $this->wpdb->query($query);
    }

    /* save 1 post */

    function update_fake_attach_id($post_id) {
        $att_id = get_post_thumbnail_id($post_id);
        $url = fifu_main_image_url($post_id, false);
        $custom_video_url = get_post_meta($post_id, 'fifu_custom_video_url', true);

        $has_fifu_attachment = $att_id ? ($this->is_fifu_attachment($att_id) && get_option('fifu_default_attach_id') != $att_id) : false;
        // delete
        if (!$url || ($url == get_option('fifu_default_url') && !$custom_video_url)) {
            if ($has_fifu_attachment) {
                wp_delete_attachment($att_id);
                delete_post_thumbnail($post_id);
                if (fifu_get_default_url() && fifu_is_valid_default_cpt($post_id))
                    set_post_thumbnail($post_id, get_option('fifu_default_attach_id'));
            } else {
                // when an external image is removed and an internal is added at the same time
                $attachments = $this->get_attachments_without_post($post_id);
                if ($attachments) {
                    $this->delete_attachment_meta_url_and_alt($attachments);
                    $this->delete_attachments($attachments);
                }

                if (fifu_get_default_url() && fifu_is_valid_default_cpt($post_id)) {
                    $post_thumbnail_id = get_post_thumbnail_id($post_id);
                    $hasInternal = $post_thumbnail_id && get_post_field('post_author', $post_thumbnail_id) != $this->author;
                    if (!$hasInternal)
                        set_post_thumbnail($post_id, get_option('fifu_default_attach_id'));
                }
            }
        } else {
            // update
            $alt = get_post_meta($post_id, 'fifu_image_alt', true);

            if ($has_fifu_attachment) {
                update_post_meta($att_id, '_wp_attached_file', $url);
                $alt ? update_post_meta($att_id, '_wp_attachment_image_alt', $alt) : delete_post_meta($att_id, '_wp_attachment_image_alt');
                $this->wpdb->update($this->posts, $set = array('post_title' => $alt, 'post_excerpt' => $alt, 'post_content_filtered' => $url), $where = array('id' => $att_id), null, null);
            }
            // insert
            else {
                $value = $this->get_formatted_value($url, $alt, $post_id);
                $this->insert_attachment_by($value);
                $att_id = $this->wpdb->insert_id;
                update_post_meta($post_id, '_thumbnail_id', $att_id);
                update_post_meta($att_id, '_wp_attached_file', $url);
                $alt && update_post_meta($att_id, '_wp_attachment_image_alt', $alt);
                $attachments = $this->get_attachments_without_post($post_id);
                if ($attachments) {
                    $this->delete_attachment_meta_url_and_alt($attachments);
                    $this->delete_attachments($attachments);
                }
            }
        }

        if (!fifu_is_from_speedup($url) && get_post_meta($post_id, 'bkp_thumbnail_id', true)) {
            delete_post_meta($post_id, 'bkp_thumbnail_id');
        }
    }

    /* save 1 gallery */

    function update_fake_attach_id_gallery($post_id) {
        $video_enabled = fifu_is_on('fifu_video');
        $i = 0;
        clean_post_cache($post_id);
        $attach_ids = rtrim(get_post_meta($post_id, '_product_image_gallery', true), ',');

        if (!$attach_ids) {
            $attach_ids = rtrim(get_post_meta($post_id, 'fifu_tmp_product_image_gallery', true), ',');
            delete_post_meta($post_id, 'fifu_tmp_product_image_gallery');
        }

        if ($attach_ids) {
            $this->delete_attachment_meta_url_and_alt($attach_ids);
            $this->delete_attachments($attach_ids);
        }
        $urls = $this->get_gallery_urls($post_id);
        $alts = $this->get_gallery_alts($post_id);

        $has_fifu_cloud_url = false; // Track if any image is from FIFU Cloud

        while ($i < sizeof($urls)) {
            $field_url = $urls[$i]->meta_key;
            $url = $urls[$i]->meta_value;
            $alt = $alts && isset($alts[$i]->meta_value) ? $alts[$i]->meta_value : '';
            $i++;
            if ($video_enabled) {
                if (fifu_is_video($url)) {
                    // for slider, where the url is stored like this: {video_url}#{image_url}
                    if (strpos($url, '#http') !== false) {
                        $parts = explode('#', $url);
                        if (isset($parts[1]) && strpos($parts[1], 'http') === 0) {
                            $url = $parts[1];
                        }
                    } else {
                        $url = fifu_video_img_large($url, $post_id, false);
                    }
                }
            }
            if (fifu_is_google_drive_file($url)) {
                $url = fifu_google_drive_url($url);
                update_post_meta($post_id, $field_url, $url);
            }

            if (fifu_is_from_speedup($url)) {
                $has_fifu_cloud_url = true;
            }

            $value = $this->get_formatted_value($url, $alt, $post_id);
            $this->insert_attachment_by($value);
            $att_id = $this->wpdb->insert_id;
            update_post_meta($att_id, '_wp_attached_file', $url);
            $alt && update_post_meta($att_id, '_wp_attachment_image_alt', $alt);
        }
        if ($attach_ids) {
            if (sizeof($urls) > 0)
                $this->delete_any_attachments($attach_ids); // even local

            $this->delete_product_image_gallery_by_attach_ids($post_id, $attach_ids);
        } else
            delete_post_meta($post_id, '_product_image_gallery', '');
        $this->insert_product_image_gallery($post_id, false);
        $this->insert_wc_additional_variation_images($post_id, false);

        if (!$has_fifu_cloud_url) {
            delete_post_meta($post_id, 'bkp_product_image_gallery');
        }
    }

    /* save 1 category */

    function ctgr_update_fake_attach_id($term_id) {
        $att_id = get_term_meta($term_id, 'thumbnail_id');
        $att_id = $att_id ? $att_id[0] : null;
        $has_fifu_attachment = $att_id ? $this->is_fifu_attachment($att_id) : false;

        $url = null;
        if (fifu_is_on('fifu_video')) {
            $url = get_term_meta($term_id, 'fifu_video_url', true);
            $url = $url ? fifu_video_img_large($url, $term_id, true) : null;
            if (fifu_is_youtube_thumb($url) && strpos($url, 'maxresdefault') !== false) {
                if (wp_remote_get($url)['http_response']->get_response_object()->status_code == 404)
                    $url = str_replace('maxresdefault', 'mqdefault', $url);
            }
        }
        $url = $url ? $url : get_term_meta($term_id, 'fifu_image_url', true);

        $is_wvs = fifu_is_woo_variation_swatches_taxonomy($term_id);

        // delete
        if (!$url) {
            if ($has_fifu_attachment) {
                wp_delete_attachment($att_id);
                update_term_meta($term_id, 'thumbnail_id', 0);
                if ($is_wvs)
                    delete_term_meta($term_id, 'product_attribute_image');
            }
        } else {
            // update
            $alt = get_term_meta($term_id, 'fifu_image_alt', true);
            if ($has_fifu_attachment) {
                update_post_meta($att_id, '_wp_attached_file', $url);
                $alt ? update_post_meta($att_id, '_wp_attachment_image_alt', $alt) : delete_post_meta($att_id, '_wp_attachment_image_alt');
                $this->wpdb->update($this->posts, $set = array('post_content_filtered' => $url, 'post_title' => $alt, 'post_excerpt' => $alt), $where = array('id' => $att_id), null, null);
            }
            // insert
            else {
                $value = $this->get_ctgr_formatted_value($url, $alt, $term_id);
                $this->insert_ctgr_attachment_by($value);
                $att_id = $this->wpdb->insert_id;
                update_term_meta($term_id, 'thumbnail_id', $att_id);
                if ($is_wvs)
                    update_term_meta($term_id, 'product_attribute_image', $att_id);
                update_post_meta($att_id, '_wp_attached_file', $url);
                $alt && update_post_meta($att_id, '_wp_attachment_image_alt', $alt);
                $attachments = $this->get_ctgr_attachments_without_post($term_id);
                if ($attachments) {
                    $this->delete_attachment_meta_url_and_alt($attachments);
                    $this->delete_attachments($attachments);
                }
            }
        }

        if (!fifu_is_from_speedup($url) && get_term_meta($term_id, 'bkp_thumbnail_id', true)) {
            delete_term_meta($term_id, 'bkp_thumbnail_id');
        }
    }

    /* default url */

    function create_attachment($url) {
        $value = $this->get_formatted_value($url, null, null);
        $this->insert_attachment_by($value);
        return $this->wpdb->insert_id;
    }

    function set_default_url() {
        $att_id = (int) get_option('fifu_default_attach_id');
        if (!$att_id)
            return;

        $post_types_csv = $this->sanitize_post_types_list((string) get_option('fifu_default_cpt'));

        $tuples = [];
        foreach ($this->get_posts_without_featured_image($post_types_csv) as $res) {
            // (%d, %s, %d) -> (post_id, meta_key, meta_value)
            $tuples[] = $this->wpdb->prepare("(%d, %s, %d)", (int) $res->id, '_thumbnail_id', $att_id);
        }

        if ($tuples) {
            $this->insert_default_thumbnail_id(implode(',', $tuples));
            update_post_meta($att_id, '_wp_attached_file', (string) get_option('fifu_default_url'));
        }
    }

    function update_default_url($url) {
        $att_id = (int) get_option('fifu_default_attach_id');
        if ($url != wp_get_attachment_url($att_id)) {
            $this->wpdb->update($this->posts, $set = array('post_content_filtered' => $url), $where = array('id' => $att_id), null, null);
            update_post_meta($att_id, '_wp_attached_file', $url);
        }
    }

    function delete_default_url() {
        $att_id = (int) get_option('fifu_default_attach_id');
        wp_delete_attachment($att_id);
        delete_option('fifu_default_attach_id');
        $this->wpdb->delete($this->postmeta, array('meta_key' => '_thumbnail_id', 'meta_value' => $att_id));
    }

    function add_default_image($post_id) {
        if (fifu_is_off('fifu_enable_default_url'))
            return;
        $att_id = (int) get_option('fifu_default_attach_id');
        $value = "({$post_id}, '_thumbnail_id', {$att_id})";
        $this->insert_default_thumbnail_id($value);
        update_post_meta($att_id, '_wp_attached_file', get_option('fifu_default_url'));
    }

    /* delete post */

    function before_delete_post($post_id) {
        $default_url_enabled = fifu_is_on('fifu_enable_default_url');
        $default_att_id = $default_url_enabled ? (int) get_option('fifu_default_attach_id') : null;
        $result = $this->get_featured_and_gallery_ids($post_id);
        if ($result) {
            $aux = $result[0]->ids;
            $ids = $aux ? explode(',', $aux) : array();
            $value = null;
            foreach ($ids as $id) {
                if ($id && $id != $default_att_id)
                    $value = ($value == null) ? $id : $value . ',' . $id;
            }
            if ($value) {
                $this->delete_attachment_meta_url_and_alt($value);
                $this->delete_attachments($value);
            }
        }
    }

    function delete_category_image($post_id) {
        if (fifu_is_off('fifu_auto_category'))
            return;

        foreach ($this->get_category_id($post_id) as $i) {
            $term_id = $i->term_id;
            if ($term_id) {
                $this->delete_image_url_category($term_id);
                $aux = $this->get_category_thumbnail_id($term_id);
                $att_id = $aux ? (int) $aux->meta_value : 0;
                if ($att_id) {
                    wp_delete_attachment($att_id);
                }
                update_term_meta($term_id, 'thumbnail_id', 0);
            }
        }
    }

    /* clean metadata */

    function enable_clean() {
        $this->delete_garbage();
        fifu_disable_fake();
        update_option('fifu_fake', 'toggleoff', 'no');
    }

    function clear_meta_in() {
        $this->wpdb->query("DELETE FROM {$this->fifu_meta_in} WHERE 1=1");
    }

    function clear_meta_out() {
        $this->wpdb->query("DELETE FROM {$this->fifu_meta_out} WHERE 1=1");
    }

    /* delete all urls */

    function delete_all() {
        sleep(3);
        if (fifu_is_on('fifu_run_delete_all') && get_option('fifu_run_delete_all_time') && FIFU_DELETE_ALL_URLS) {
            $this->wpdb->query("
                DELETE FROM {$this->postmeta} 
                WHERE meta_key LIKE 'fifu_%'
            ");
        }
    }

    /* md5 */

    function create_table_md5() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        maybe_create_table($this->fifu_md5, "
            CREATE TABLE {$this->fifu_md5} (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, 
                md5 VARCHAR(32) NOT NULL,
                thumbnail_id INT NOT NULL,
                UNIQUE KEY (md5)
            )
        ");
    }

    function insert_md5($url, $thumbnail_id) {
        if ($this->get_thumbnail_id_by_md5($url))
            return;

        $md5 = md5($url);
        $this->wpdb->query(
                $this->wpdb->prepare(
                        "INSERT INTO {$this->fifu_md5} (md5, thumbnail_id) VALUES (%s, %d)",
                        $md5,
                        (int) $thumbnail_id
                )
        );
    }

    function get_thumbnail_id_by_md5($url) {
        $md5 = md5($url);
        $result = $this->wpdb->get_row(
                $this->wpdb->prepare(
                        "SELECT thumbnail_id FROM {$this->fifu_md5} WHERE md5 = %s",
                        $md5
                )
        );
        return $result ? $result->thumbnail_id : null;
    }

    function delete_md5_by_thumbnail_id($thumbnail_id) {
        $this->wpdb->query(
                $this->wpdb->prepare(
                        "DELETE FROM {$this->fifu_md5} WHERE thumbnail_id = %d",
                        (int) $thumbnail_id
                )
        );
    }

    /* video_oembed */

    // Error: "Specified key was too long; max key length is 1000 bytes".
    // Possible solution: reduce the maximum length of the video_url, image_url, and embed_url columns to 250 characters or less.

    function create_table_video_oembed() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        maybe_create_table($this->fifu_video_oembed, "
            CREATE TABLE {$this->fifu_video_oembed} (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                video_url VARCHAR(255) NOT NULL,
                image_url VARCHAR(255) NOT NULL,
                embed_url VARCHAR(255) NOT NULL,
                UNIQUE KEY (video_url),
                INDEX index_fifu_video_oembed_image_url (image_url) USING HASH,
                INDEX index_fifu_video_oembed_embed_url (embed_url) USING HASH
            )
        ");
    }

    function insert_video_oembed($video_url, $image_url, $embed_url) {
        $video_url = esc_url_raw($video_url);
        $image_url = esc_url_raw($image_url);
        $embed_url = esc_url_raw($embed_url);
        if ($this->video_oembed_exists($video_url))
            return;
        $this->wpdb->query(
                $this->wpdb->prepare(
                        "INSERT INTO {$this->fifu_video_oembed} (video_url, image_url, embed_url) VALUES (%s, %s, %s)",
                        $video_url,
                        $image_url,
                        $embed_url
                )
        );
    }

    function video_oembed_exists($video_url) {
        $video_url = esc_url_raw($video_url);
        $result = $this->wpdb->get_results(
                $this->wpdb->prepare(
                        "SELECT COUNT(1) AS amount FROM {$this->fifu_video_oembed} WHERE video_url = %s",
                        $video_url
                )
        );
        return $result ? intval($result[0]->amount) > 0 : false;
    }

    function delete_video_oembed_by_video_url($video_url) {
        $video_url = esc_url_raw($video_url);
        $this->wpdb->query(
                $this->wpdb->prepare(
                        "DELETE FROM {$this->fifu_video_oembed} WHERE video_url = %s",
                        $video_url
                )
        );
    }

    function delete_video_oembed_by_image_url($image_url) {
        $image_url = esc_url_raw($image_url);
        $this->wpdb->query(
                $this->wpdb->prepare(
                        "DELETE FROM {$this->fifu_video_oembed} WHERE image_url = %s",
                        $image_url
                )
        );
    }

    function get_image_url_by_video_url($video_url) {
        $video_url = esc_url_raw($video_url);
        $result = $this->wpdb->get_row(
                $this->wpdb->prepare(
                        "SELECT image_url FROM {$this->fifu_video_oembed} WHERE video_url = %s",
                        $video_url
                )
        );
        return $result ? $result->image_url : null;
    }

    function get_embed_url_by_image_url($image_url) {
        $image_url = esc_url_raw($image_url);
        $result = $this->wpdb->get_row(
                $this->wpdb->prepare(
                        "SELECT embed_url FROM {$this->fifu_video_oembed} WHERE image_url = %s",
                        $image_url
                )
        );
        return $result ? $result->embed_url : null;
    }

    function get_embed_url_by_video_url($video_url) {
        $video_url = esc_url_raw($video_url);
        $result = $this->wpdb->get_row(
                $this->wpdb->prepare(
                        "SELECT embed_url FROM {$this->fifu_video_oembed} WHERE video_url = %s",
                        $video_url
                )
        );
        return $result ? $result->embed_url : null;
    }

    function update_image_url_by_video_url($video_url, $image_url) {
        $video_url = esc_url_raw($video_url);
        $image_url = esc_url_raw($image_url);
        $this->wpdb->query(
                $this->wpdb->prepare(
                        "UPDATE {$this->fifu_video_oembed} SET image_url = %s WHERE video_url = %s",
                        $image_url,
                        $video_url
                )
        );
    }

    /* metadata */

    function create_table_meta_in() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        maybe_create_table($this->fifu_meta_in, "
            CREATE TABLE {$this->fifu_meta_in} (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                post_ids TEXT NOT NULL,
                type VARCHAR(8) NOT NULL
            )
        ");
    }

    function create_table_meta_out() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        maybe_create_table($this->fifu_meta_out, "
            CREATE TABLE {$this->fifu_meta_out} (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                post_ids TEXT NOT NULL,
                type VARCHAR(16) NOT NULL
            )
        ");
    }

    function prepare_meta_in($post_ids_str, $is_ctgr) {
        $this->wpdb->query("SET SESSION group_concat_max_len = 1048576;"); // because GROUP_CONCAT is limited to 1024 characters

        $import_ids = $post_ids_str ? "a.post_id IN ({$post_ids_str}) AND" : "";

        $last_insert_id = null;

        if (!$post_ids_str || ($import_ids && !$is_ctgr)) {
            // post (cpt)
            // Create a temporary table with an AUTO_INCREMENT column to generate row numbers
            $this->wpdb->query("
                CREATE TEMPORARY TABLE temp_post_in (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    post_id INT
                );
            ");

            // Insert distinct post_ids into the temporary table, applying the necessary conditions
            $this->wpdb->query("
                INSERT INTO temp_post_in (post_id)
                SELECT DISTINCT a.post_id
                FROM {$this->postmeta} AS a
                WHERE {$import_ids}
                a.meta_key IN ('fifu_image_url', 'fifu_video_url', 'fifu_slider_image_url_0')
                AND a.meta_value IS NOT NULL
                AND a.meta_value <> ''
                AND NOT EXISTS (
                    SELECT 1 
                    FROM {$this->postmeta} AS b
                    WHERE a.post_id = b.post_id
                    AND b.meta_key = '_thumbnail_id'
                    AND b.meta_value <> 0
                )
                ORDER BY a.post_id;
            ");

            // Insert into the final table from the temporary table and group by row number
            $this->wpdb->query("
                INSERT INTO {$this->fifu_meta_in} (post_ids, type)
                SELECT GROUP_CONCAT(post_id ORDER BY post_id SEPARATOR ','), 'post'
                FROM temp_post_in
                GROUP BY FLOOR((id - 1) / 5000);
            ");

            // Drop the temporary table
            $this->wpdb->query("
                DROP TEMPORARY TABLE temp_post_in;
            ");

            $last_insert_id = $this->wpdb->insert_id;
            if ($last_insert_id) {
                $this->log_prepare($last_insert_id, $this->fifu_meta_in);
            }

            // woo (woocommerce gallery)
            // Create a temporary table with an AUTO_INCREMENT column to generate row numbers
            $this->wpdb->query("
                CREATE TEMPORARY TABLE temp_woo_in (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    post_id INT
                );
            ");

            // Insert distinct post_ids into the temporary table, applying the necessary conditions
            $this->wpdb->query("
                INSERT INTO temp_woo_in (post_id)
                SELECT DISTINCT a.post_id
                FROM {$this->postmeta} AS a
                WHERE {$import_ids}
                (
                    a.meta_key LIKE 'fifu_image_url_%'
                    OR a.meta_key LIKE 'fifu_video_url_%'
                    OR a.meta_key LIKE 'fifu_slider_image_url_%'
                )
                AND a.meta_value IS NOT NULL
                AND a.meta_value <> ''
                AND NOT EXISTS (
                    SELECT 1 
                    FROM (
                        SELECT post_id
                        FROM {$this->postmeta}
                        WHERE meta_key IN ('_product_image_gallery', '_wc_additional_variation_images')
                        AND meta_value IS NOT NULL
                        AND meta_value <> ''
                    ) AS b
                    WHERE a.post_id = b.post_id 
                )
                ORDER BY a.post_id;
            ");

            // Insert into the final table from the temporary table and group by row number
            $this->wpdb->query("
                INSERT INTO {$this->fifu_meta_in} (post_ids, type)
                SELECT GROUP_CONCAT(post_id ORDER BY post_id SEPARATOR ','), 'woo'
                FROM temp_woo_in
                GROUP BY FLOOR((id - 1) / 2500);
            ");

            // Drop the temporary table
            $this->wpdb->query("
                DROP TEMPORARY TABLE temp_woo_in;
            ");

            $prev_insert_id = $last_insert_id;
            $last_insert_id = $this->wpdb->insert_id;
            if ($last_insert_id && $prev_insert_id != $last_insert_id) {
                $this->log_prepare($last_insert_id, $this->fifu_meta_in);
            }
        }

        $import_ids = $post_ids_str ? "a.term_id IN ({$post_ids_str}) AND" : "";

        if (!$post_ids_str || ($import_ids && $is_ctgr)) {
            // term (woocommerce category)
            // Create a temporary table with an AUTO_INCREMENT column to generate row numbers
            $this->wpdb->query("
                CREATE TEMPORARY TABLE temp_term_in (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    term_id INT
                );
            ");

            // Insert distinct term_ids into the temporary table, applying the necessary conditions
            $this->wpdb->query("
                INSERT INTO temp_term_in (term_id)
                SELECT DISTINCT a.term_id
                FROM {$this->termmeta} AS a
                WHERE {$import_ids}
                a.meta_key IN ('fifu_image_url', 'fifu_video_url')
                AND a.meta_value IS NOT NULL
                AND a.meta_value <> ''
                AND NOT EXISTS (
                    SELECT 1 
                    FROM {$this->termmeta} AS b
                    WHERE a.term_id = b.term_id 
                    AND (
                        (b.meta_key = 'thumbnail_id' AND b.meta_value <> 0)
                        OR b.meta_key IN ('fifu_metadataterm_sent')
                    )
                )
                ORDER BY a.term_id;
            ");

            // Insert into the final table from the temporary table and group by row number
            $this->wpdb->query("
                INSERT INTO {$this->fifu_meta_in} (post_ids, type)
                SELECT GROUP_CONCAT(term_id ORDER BY term_id SEPARATOR ','), 'term'
                FROM temp_term_in
                GROUP BY FLOOR((id - 1) / 5000);
            ");

            // Drop the temporary table
            $this->wpdb->query("
                DROP TEMPORARY TABLE temp_term_in;
            ");

            $prev_insert_id = $last_insert_id;
            $last_insert_id = $this->wpdb->insert_id;
            if ($last_insert_id && $prev_insert_id != $last_insert_id) {
                $this->log_prepare($last_insert_id, $this->fifu_meta_in);
            }
        }
    }

    function prepare_meta_out($post_ids_str, $is_ctgr) {
        $this->wpdb->query("SET SESSION group_concat_max_len = 1048576;"); // because GROUP_CONCAT is limited to 1024 characters

        $import_ids = $post_ids_str ? "post_parent IN ({$post_ids_str}) AND" : "";
        $ctgr = $is_ctgr ? "post_name LIKE 'fifu-category%' AND" : "";

        $last_insert_id = null;

        $sql = "
            INSERT INTO {$this->fifu_meta_out} (post_ids, type)
            SELECT GROUP_CONCAT(DISTINCT id ORDER BY id SEPARATOR ','), 'att'
            FROM {$this->posts} 
            WHERE {$import_ids}
            {$ctgr}
            post_author = %d
            GROUP BY FLOOR(id / 5000)
        ";
        $this->wpdb->query($this->wpdb->prepare($sql, $this->author));

        $last_insert_id = $this->wpdb->insert_id;
        if ($last_insert_id) {
            $this->log_prepare($last_insert_id, $this->fifu_meta_out);
        }

        $import_ids = $post_ids_str ? "post_id IN ({$post_ids_str}) AND" : "";

        if (!$post_ids_str || ($import_ids && !$is_ctgr)) {
            // Create a temporary table with an AUTO_INCREMENT column to generate row numbers
            $this->wpdb->query("
                CREATE TEMPORARY TABLE temp_woo_out (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    post_id INT
                );
            ");

            // Insert distinct post_ids into the temporary table, applying the necessary conditions
            $this->wpdb->query("
                INSERT INTO temp_woo_out (post_id)
                SELECT DISTINCT post_id
                FROM {$this->postmeta}
                WHERE {$import_ids}
                (
                    meta_key LIKE 'fifu_image_url_%' 
                    OR meta_key LIKE 'fifu_video_url_%'
                    OR meta_key LIKE 'fifu_slider_image_url_%'
                )
                ORDER BY post_id;
            ");

            // Insert into the final table from the temporary table and group by row number
            $this->wpdb->query("
                INSERT INTO {$this->fifu_meta_out} (post_ids, type)
                SELECT GROUP_CONCAT(post_id ORDER BY post_id SEPARATOR ','), 'woo'
                FROM temp_woo_out
                GROUP BY FLOOR((id - 1) / 5000);
            ");

            // Drop the temporary table
            $this->wpdb->query("
                DROP TEMPORARY TABLE temp_woo_out;
            ");

            $prev_insert_id = $last_insert_id;
            $last_insert_id = $this->wpdb->insert_id;
            if ($last_insert_id && $prev_insert_id != $last_insert_id) {
                $this->log_prepare($last_insert_id, $this->fifu_meta_out);
            }
        }

        $import_ids = $post_ids_str ? "term_id IN ({$post_ids_str}) AND" : "";

        if (!$post_ids_str || ($import_ids && $is_ctgr)) {
            // Create a temporary table with an AUTO_INCREMENT column to generate row numbers
            $this->wpdb->query("
                CREATE TEMPORARY TABLE temp_term_out (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    term_id INT
                );
            ");

            // Insert distinct term_ids into the temporary table, applying the necessary conditions
            $this->wpdb->query("
                INSERT INTO temp_term_out (term_id)
                SELECT DISTINCT term_id
                FROM {$this->termmeta}
                WHERE {$import_ids}
                meta_key IN ('fifu_image_url', 'fifu_video_url')
                AND meta_value IS NOT NULL
                AND meta_value <> ''
                ORDER BY term_id;
            ");

            // Insert into the final table from the temporary table and group by row number
            $this->wpdb->query("
                INSERT INTO {$this->fifu_meta_out} (post_ids, type)
                SELECT GROUP_CONCAT(term_id ORDER BY term_id SEPARATOR ','), 'term'
                FROM temp_term_out
                GROUP BY FLOOR((id - 1) / 5000);
            ");

            // Drop the temporary table
            $this->wpdb->query("
                DROP TEMPORARY TABLE temp_term_out;
            ");

            $prev_insert_id = $last_insert_id;
            $last_insert_id = $this->wpdb->insert_id;
            if ($last_insert_id && $prev_insert_id != $last_insert_id) {
                $this->log_prepare($last_insert_id, $this->fifu_meta_out);
            }
        }
    }

    function get_meta_in() {
        return $this->wpdb->get_results("
            SELECT id AS post_id
            FROM {$this->fifu_meta_in}
        ");
    }

    function get_meta_out() {
        return $this->wpdb->get_results("
            SELECT id AS post_id
            FROM {$this->fifu_meta_out}
        ");
    }

    function get_meta_in_last($type) {
        $sql = $this->wpdb->prepare(
                "SELECT id FROM {$this->fifu_meta_in} WHERE type = %s ORDER BY id DESC LIMIT 1",
                $type
        );
        return $this->wpdb->get_results($sql);
    }

    function get_meta_out_last($type) {
        $sql = $this->wpdb->prepare(
                "SELECT id FROM {$this->fifu_meta_out} WHERE type = %s ORDER BY id DESC LIMIT 1",
                $type
        );
        return $this->wpdb->get_results($sql);
    }

    function get_type_meta_in($id) {
        $query = $this->wpdb->prepare("
            SELECT type
            FROM {$this->fifu_meta_in}
            WHERE id = %d",
                $id
        );
        return $this->wpdb->get_var($query);
    }

    function log_prepare($last_insert_id, $table) {
        $inserted_records = $this->wpdb->get_results(
                $this->wpdb->prepare(
                        "SELECT id, post_ids, type FROM {$table} WHERE id = %d",
                        (int) $last_insert_id
                )
        );

        foreach ($inserted_records as $record) {
            fifu_plugin_log([$table => [
                    'id' => $record->id,
                    'post_ids' => $record->post_ids,
                    'type' => $record->type
            ]]);
        }
    }

    function get_type_meta_out($id) {
        $query = $this->wpdb->prepare("
            SELECT type
            FROM {$this->fifu_meta_out}
            WHERE id = %d",
                $id
        );
        return $this->wpdb->get_var($query);
    }

    function insert_postmeta($id) {
        $result = $this->wpdb->get_results(
                $this->wpdb->prepare(
                        "SELECT post_ids FROM {$this->fifu_meta_in} WHERE id = %d",
                        (int) $id
                )
        );

        $this->wpdb->query(
                $this->wpdb->prepare(
                        "DELETE FROM {$this->fifu_meta_in} WHERE id = %d",
                        (int) $id
                )
        );

        fifu_plugin_log(['insert_postmeta' => ['id' => $id,]]);

        if (count($result) == 0)
            return false;

        // insert 1 attachment for each selected post
        $value_arr = array();
        $ids = $result[0]->post_ids;
        $meta_data = $this->get_fifu_fields($ids);
        $post_ids = explode(",", $ids);
        foreach ($post_ids as $post_id) {
            $url = $this->get_main_image_url($meta_data[$post_id], $post_id);
            $aux = $this->get_formatted_value($url, $meta_data[$post_id]['fifu_image_alt'], $post_id);
            array_push($value_arr, $aux);
        }
        $value = implode(",", $value_arr);
        wp_cache_flush();
        $this->insert_postmeta2($value, $ids);

        fifu_set_transient('fifu_metadata_counter', fifu_get_transient('fifu_metadata_counter') - count($post_ids), 0);

        return true;
    }

    function delete_attmeta($id) {
        $result = $this->wpdb->get_results(
                $this->wpdb->prepare(
                        "SELECT post_ids FROM {$this->fifu_meta_out} WHERE id = %d",
                        (int) $id
                )
        );

        $this->wpdb->query(
                $this->wpdb->prepare(
                        "DELETE FROM {$this->fifu_meta_out} WHERE id = %d",
                        (int) $id
                )
        );

        fifu_plugin_log(['delete_attmeta' => ['id' => $id,]]);

        if (count($result) == 0)
            return false;

        $ids = $result[0]->post_ids;
        $post_ids = explode(",", $ids);
        wp_cache_flush();
        $this->delete_attmeta2($ids);

        fifu_set_transient('fifu_metadata_counter', fifu_get_transient('fifu_metadata_counter') - count($post_ids), 0);

        return true;
    }

    function delete_garbage() {
        wp_cache_flush();

        // Cast option-derived IDs to integers to avoid SQL injection
        $fake_attach_id = (int) get_option('fifu_fake_attach_id');
        $default_attach_id = (int) get_option('fifu_default_attach_id');

        $this->wpdb->query('START TRANSACTION');

        try {
            $fake_attach_sql = $fake_attach_id ? "OR meta_value = {$fake_attach_id}" : "";
            $default_attach_sql = $default_attach_id ? "OR meta_value = {$default_attach_id}" : "";

            // default
            $this->wpdb->query("
                DELETE FROM {$this->postmeta} 
                WHERE meta_key IN ('_thumbnail_id', '_product_image_gallery', '_wc_additional_variation_images')
                AND (
                    meta_value = -1
                    {$fake_attach_sql}
                    {$default_attach_sql}
                    OR meta_value IS NULL 
                    OR meta_value LIKE 'fifu:%'
                )
            ");

            // duplicated
            $this->wpdb->query("
                DELETE FROM {$this->termmeta}
                WHERE meta_key = 'fifu_image_url'
                AND meta_id NOT IN (
                    SELECT * FROM (
                        SELECT MAX(tm.meta_id) AS meta_id
                        FROM {$this->termmeta} tm
                        WHERE tm.meta_key = 'fifu_image_url'
                        GROUP BY tm.term_id
                    ) aux
                )
            ");

            // oembed
            $this->wpdb->query("
                DELETE FROM {$this->fifu_video_oembed}
            ");

            $global_media_sql = fifu_is_multisite_global_media_active() ? "AND meta_value NOT LIKE '100000%'" : "";

            $this->wpdb->query("
                DELETE FROM {$this->postmeta} 
                WHERE meta_key = '_thumbnail_id' 
                {$global_media_sql}
                AND NOT EXISTS (
                    SELECT 1 
                    FROM {$this->posts} p 
                    WHERE p.id = meta_value
                )
            ");

            $this->wpdb->query("
                DELETE FROM {$this->postmeta} 
                WHERE meta_key IN ('_wp_attached_file', '_wp_attachment_image_alt', '_wp_attachment_metadata') 
                AND NOT EXISTS (
                    SELECT 1
                    FROM {$this->posts} p 
                    WHERE p.id = post_id
                )
            ");

            $this->wpdb->query("
                DELETE FROM {$this->postmeta} 
                WHERE meta_key LIKE 'fifu_%'
                AND (
                    meta_value = ''
                    OR meta_value is NULL
                )
            ");

            $this->wpdb->query("
                DELETE FROM {$this->termmeta} 
                WHERE meta_key = 'thumbnail_id' 
                AND NOT EXISTS (
                    SELECT 1 
                    FROM {$this->posts} p 
                    WHERE p.id = meta_value
                )
            ");

            $this->wpdb->query("
                DELETE FROM {$this->termmeta} 
                WHERE meta_key LIKE 'fifu_%'
                AND (
                    meta_value = ''
                    OR meta_value is NULL
                )
            ");

            $this->wpdb->query('COMMIT');
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
        }

        wp_delete_attachment($fake_attach_id);
        wp_delete_attachment($default_attach_id);
        delete_option('fifu_fake_attach_id');
        delete_option('fifu_default_attach_id');

        return true;
    }

    function delete_garbage_wai() {
        $this->wpdb->query('START TRANSACTION');
        try {
            $author = $this->author;
            $sql1 = $this->wpdb->prepare("
                DELETE FROM {$this->postmeta} 
                WHERE meta_key IN ('_wp_attached_file', '_wp_attachment_image_alt', '_wp_attachment_metadata')
                AND post_id IN (
                    SELECT ID
                    FROM {$this->posts}
                    WHERE ID = post_id
                    AND post_author = %d
                    AND post_parent = 0
                )
            ", $author);
            $this->wpdb->query($sql1);

            $sql2 = $this->wpdb->prepare("
                DELETE FROM {$this->posts}
                WHERE post_author = %d
                AND post_parent = 0
            ", $author);
            $this->wpdb->query($sql2);

            $this->wpdb->query('COMMIT');
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
        }
        return true;
    }

    function delete_woometa($id) {
        $result = $this->wpdb->get_results(
                $this->wpdb->prepare(
                        "SELECT post_ids FROM {$this->fifu_meta_out} WHERE id = %d",
                        (int) $id
                )
        );

        $this->wpdb->query(
                $this->wpdb->prepare(
                        "DELETE FROM {$this->fifu_meta_out} WHERE id = %d",
                        (int) $id
                )
        );

        fifu_plugin_log(['delete_woometa' => ['id' => $id,]]);

        if (count($result) == 0)
            return false;

        $ids = $result[0]->post_ids;
        $post_ids = explode(",", $ids);
        wp_cache_flush();
        $this->delete_woometa2($ids);

        fifu_set_transient('fifu_metadata_counter', fifu_get_transient('fifu_metadata_counter') - count($post_ids), 0);

        return true;
    }

    function delete_termmeta($id) {
        $result = $this->wpdb->get_results(
                $this->wpdb->prepare(
                        "SELECT post_ids FROM {$this->fifu_meta_out} WHERE id = %d",
                        (int) $id
                )
        );

        $this->wpdb->query(
                $this->wpdb->prepare(
                        "DELETE FROM {$this->fifu_meta_out} WHERE id = %d",
                        (int) $id
                )
        );

        fifu_plugin_log(['delete_termmeta' => ['id' => $id,]]);

        if (count($result) == 0)
            return false;

        $ids = $result[0]->post_ids;
        $term_ids = explode(",", $ids);
        wp_cache_flush();
        $this->delete_termmeta2($ids);

        fifu_set_transient('fifu_metadata_counter', fifu_get_transient('fifu_metadata_counter') - count($term_ids), 0);

        return true;
    }

    function insert_postmeta2($value, $ids) {
        $this->wpdb->query('START TRANSACTION');
        $ids_csv = $this->sanitize_ids_csv($ids);

        try {
            $this->wpdb->query(
                    "INSERT INTO {$this->posts} (post_author, guid, post_title, post_excerpt, post_mime_type, post_type, post_status, post_parent, post_date, post_date_gmt, post_modified, post_modified_gmt, post_content, to_ping, pinged, post_content_filtered) 
                VALUES " . $value
            );

            $author = $this->author;
            $sql_thumb = $this->wpdb->prepare("
                INSERT INTO {$this->postmeta} (post_id, meta_key, meta_value) (
                    SELECT p.post_parent, '_thumbnail_id', p.id 
                    FROM {$this->posts} p
                    WHERE p.post_parent IN ({$ids_csv}) 
                    AND p.post_author = %d 
                )
            ", $author);
            $this->wpdb->query($sql_thumb);

            $sql_file = $this->wpdb->prepare("
                INSERT INTO {$this->postmeta} (post_id, meta_key, meta_value) (
                    SELECT p.id, '_wp_attached_file', p.post_content_filtered
                    FROM {$this->posts} p 
                    WHERE p.post_parent IN ({$ids_csv}) 
                    AND p.post_author = %d 
                )
            ", $author);
            $this->wpdb->query($sql_file);

            $sql_alt = $this->wpdb->prepare("
                INSERT INTO {$this->postmeta} (post_id, meta_key, meta_value) (
                    SELECT p.id, '_wp_attachment_image_alt', p.post_title 
                    FROM {$this->posts} p
                    WHERE p.post_parent IN ({$ids_csv}) 
                    AND p.post_author = %d 
                    AND p.post_title IS NOT NULL 
                    AND p.post_title != ''
                )
            ", $author);
            $this->wpdb->query($sql_alt);

            $this->wpdb->query('COMMIT');
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
        }
    }

    function delete_attmeta2($ids) {
        $this->wpdb->query('START TRANSACTION');
        $ids_csv = $this->sanitize_ids_csv($ids);

        try {
            $this->wpdb->query("
                DELETE FROM {$this->postmeta} 
                WHERE meta_key = '_thumbnail_id' 
                AND meta_value IN (0, {$ids_csv})
            ");

            $author = $this->author;
            $sql_del_posts = $this->wpdb->prepare("
                DELETE FROM {$this->posts} 
                WHERE id IN ({$ids_csv})
                AND post_author = %d
            ", $author);
            $this->wpdb->query($sql_del_posts);

            $this->wpdb->query("
                DELETE FROM {$this->postmeta} 
                WHERE meta_key IN ('_wp_attached_file', '_wp_attachment_image_alt', '_wp_attachment_metadata') 
                AND post_id IN ({$ids_csv})
            ");

            $this->wpdb->query('COMMIT');
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
        }
    }

    function delete_woometa2($ids) {
        $this->wpdb->query('START TRANSACTION');
        $ids_csv = $this->sanitize_ids_csv($ids);

        try {
            $this->wpdb->query("
                DELETE FROM {$this->postmeta}
                WHERE meta_key IN ('_product_image_gallery', '_wc_additional_variation_images')
                AND post_id IN ({$ids_csv})
            ");

            if (fifu_is_houzez_active()) {
                $this->wpdb->query("
                    DELETE FROM {$this->postmeta}
                    WHERE meta_key = 'fave_property_images'
                    AND post_id IN ({$ids_csv})
                ");
            }

            if (fifu_is_wpresidence_active()) {
                $this->wpdb->query("
                    DELETE FROM {$this->postmeta}
                    WHERE meta_key = 'wpestate_property_gallery'
                    AND post_id IN ({$ids_csv})
                ");
            }

            $this->wpdb->query('COMMIT');
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
        }
    }

    function delete_termmeta2($ids) {
        $this->wpdb->query('START TRANSACTION');
        $ids_csv = $this->sanitize_ids_csv($ids);

        try {
            $this->wpdb->query("
                DELETE FROM {$this->termmeta} 
                WHERE meta_key = 'thumbnail_id' 
                AND term_id IN ({$ids_csv})
            ");

            $author = $this->author;
            $sql_del_pm = $this->wpdb->prepare("
                DELETE pm
                FROM {$this->postmeta} pm JOIN {$this->posts} p ON pm.post_id = p.id
                WHERE pm.meta_key IN ('_wp_attached_file', '_wp_attachment_image_alt', '_wp_attachment_metadata')
                AND p.post_parent IN ({$ids_csv})
                AND p.post_author = %d 
                AND p.post_name LIKE 'fifu-category%'
            ", $author);
            $this->wpdb->query($sql_del_pm);

            $this->wpdb->query('COMMIT');
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
        }
    }

    function insert_woometa($id) {
        $result = $this->wpdb->get_results(
                $this->wpdb->prepare(
                        "SELECT post_ids FROM {$this->fifu_meta_in} WHERE id = %d",
                        (int) $id
                )
        );

        $this->wpdb->query(
                $this->wpdb->prepare(
                        "DELETE FROM {$this->fifu_meta_in} WHERE id = %d",
                        (int) $id
                )
        );

        fifu_plugin_log(['insert_woometa' => ['id' => $id,]]);

        if (count($result) == 0)
            return false;

        // insert 1 attachment for each selected url
        $value_arr = array();
        $ids = $result[0]->post_ids;
        $post_ids = explode(",", $ids);
        $gallery_urls_map = $this->get_gallery_urls_map($ids);
        foreach ($post_ids as $post_id) {
            if (array_key_exists($post_id, $gallery_urls_map)) {
                foreach ($gallery_urls_map[$post_id] as $url) {
                    $url = fifu_is_video($url) ? fifu_video_img_large($url, $post_id, false) : $url;
                    $url = htmlspecialchars_decode($url);
                    $aux = $this->get_formatted_value($url, '', $post_id);
                    array_push($value_arr, $aux);
                }
            }
        }
        $value = implode(",", $value_arr);
        wp_cache_flush();
        $this->insert_woometa2($value, $ids);

        fifu_set_transient('fifu_metadata_counter', fifu_get_transient('fifu_metadata_counter') - count($post_ids), 0);

        return true;
    }

    function insert_woometa2($value, $ids) {
        $this->wpdb->query('START TRANSACTION');
        $ids_csv = $this->sanitize_ids_csv($ids);

        try {
            $this->wpdb->query(
                    "INSERT INTO {$this->posts} (post_author, guid, post_title, post_excerpt, post_mime_type, post_type, post_status, post_parent, post_date, post_date_gmt, post_modified, post_modified_gmt, post_content, to_ping, pinged, post_content_filtered) 
                VALUES " . $value
            );

            $thumb_ids = $this->get_thumb_ids_str($ids);
            $thumb_ids_csv = $this->sanitize_ids_csv($thumb_ids);
            $author = $this->author;

            $sql_ins_file = $this->wpdb->prepare("
                INSERT INTO {$this->postmeta} (post_id, meta_key, meta_value) (
                    SELECT p.id, '_wp_attached_file', p.post_content_filtered
                    FROM {$this->posts} p 
                    WHERE p.post_parent IN ({$ids_csv}) 
                    AND p.id NOT IN ({$thumb_ids_csv})
                    AND p.post_author = %d 
                )
            ", $author);
            $this->wpdb->query($sql_ins_file);

            $sql_ins_alt = $this->wpdb->prepare("
                INSERT INTO {$this->postmeta} (post_id, meta_key, meta_value) (
                    SELECT p.id, '_wp_attachment_image_alt', p.post_title 
                    FROM {$this->posts} p
                    WHERE p.post_parent IN ({$ids_csv}) 
                    AND p.id NOT IN ({$thumb_ids_csv})
                    AND p.post_author = %d 
                    AND p.post_title IS NOT NULL 
                    AND p.post_title != ''
                )
            ", $author);
            $this->wpdb->query($sql_ins_alt);

            $this->wpdb->query("
                DELETE pm
                FROM {$this->postmeta} pm
                WHERE pm.post_id IN ({$ids_csv})
                AND pm.meta_key IN ('_product_image_gallery', '_wc_additional_variation_images')
            ");

            $sql_ins_gallery = $this->wpdb->prepare("
                INSERT INTO {$this->postmeta} (post_id, meta_key, meta_value) (
                    SELECT post_parent, '_product_image_gallery', GROUP_CONCAT(id ORDER BY id) 
                    FROM {$this->posts} p 
                    WHERE p.post_parent IN ({$ids_csv})
                    AND p.id NOT IN ({$thumb_ids_csv})
                    AND p.post_author = %d
                    GROUP BY post_parent
                )
            ", $author);
            $this->wpdb->query($sql_ins_gallery);

            if (fifu_is_houzez_active()) {
                $sql_houzez = $this->wpdb->prepare("
                    INSERT INTO {$this->postmeta} (post_id, meta_key, meta_value) (
                        SELECT post_parent, 'fave_property_images', id
                        FROM {$this->posts} p 
                        WHERE p.post_parent IN ({$ids_csv})
                        AND p.id NOT IN ({$thumb_ids_csv})
                        AND p.post_author = %d
                    )
                ", $author);
                $this->wpdb->query($sql_houzez);
            }

            if (fifu_is_wpresidence_active()) {
                $sql_wpestate = $this->wpdb->prepare("
                    INSERT INTO {$this->postmeta} (post_id, meta_key, meta_value) (
                        SELECT post_parent, 'wpestate_property_gallery', GROUP_CONCAT(id ORDER BY id) 
                        FROM {$this->posts} p 
                        WHERE p.post_parent IN ({$ids_csv})
                        AND p.post_author = %d
                        GROUP BY post_parent
                    )
                ", $author);
                $this->wpdb->query($sql_wpestate);

                $posts_with_galleries = $this->wpdb->get_results("
                    SELECT post_id, GROUP_CONCAT(meta_value) as attachment_ids
                    FROM {$this->postmeta}
                    WHERE post_id IN ({$ids_csv})
                    AND meta_key = 'wpestate_property_gallery'
                    GROUP BY post_id
                ");

                foreach ($posts_with_galleries as $post) {
                    $gallery_ids = explode(',', $post->attachment_ids);
                    $formatted_gallery = array_combine(range(1, count($gallery_ids)), $gallery_ids);
                    update_post_meta($post->post_id, 'wpestate_property_gallery', $formatted_gallery);
                }
            }

            $sql_wc_additional = $this->wpdb->prepare("
                INSERT INTO {$this->postmeta} (post_id, meta_key, meta_value) (
                    SELECT post_parent, '_wc_additional_variation_images', GROUP_CONCAT(id ORDER BY id) 
                    FROM {$this->posts} p 
                    WHERE p.post_parent IN ({$ids_csv})
                    AND p.id NOT IN ({$thumb_ids_csv})
                    AND p.post_author = %d 
                    AND (SELECT post_type FROM {$this->posts} WHERE id = p.post_parent) = 'product_variation'
                    GROUP BY post_parent
                )
            ", $author);
            $this->wpdb->query($sql_wc_additional);

            $this->wpdb->query('COMMIT');
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
        }
    }

    function insert_termmeta($id) {
        $result = $this->wpdb->get_results(
                $this->wpdb->prepare(
                        "SELECT post_ids FROM {$this->fifu_meta_in} WHERE id = %d",
                        (int) $id
                )
        );

        $this->wpdb->query(
                $this->wpdb->prepare(
                        "DELETE FROM {$this->fifu_meta_in} WHERE id = %d",
                        (int) $id
                )
        );

        fifu_plugin_log(['insert_termmeta' => ['id' => $id,]]);

        if (count($result) == 0)
            return false;

        // insert 1 attachment for each selected category
        $value_arr = array();
        $ids = $result[0]->post_ids;
        $term_ids = explode(",", $ids);
        foreach ($term_ids as $term_id) {
            $url = get_term_meta($term_id, 'fifu_video_url', true);
            $url = $url ? fifu_video_img_large($url, $term_id, true) : get_term_meta($term_id, 'fifu_image_url', true);
            $url = htmlspecialchars_decode($url);
            $aux = $this->get_ctgr_formatted_value($url, get_term_meta($term_id, 'fifu_image_alt', true), $term_id);
            array_push($value_arr, $aux);
        }
        $value = implode(",", $value_arr);
        wp_cache_flush();
        $this->insert_termmeta2($value, $ids);

        fifu_set_transient('fifu_metadata_counter', fifu_get_transient('fifu_metadata_counter') - count($term_ids), 0);

        return true;
    }

    function insert_termmeta2($value, $ids) {
        $this->wpdb->query('START TRANSACTION');
        $ids_csv = $this->sanitize_ids_csv($ids);

        try {
            $this->wpdb->query(
                    "INSERT INTO {$this->posts} (post_author, guid, post_title, post_excerpt, post_mime_type, post_type, post_status, post_parent, post_date, post_date_gmt, post_modified, post_modified_gmt, post_content, to_ping, pinged, post_content_filtered, post_name) 
                VALUES " . $value
            );

            $author = $this->author;
            $sql_term_thumbnail = $this->wpdb->prepare("
                INSERT INTO {$this->termmeta} (term_id, meta_key, meta_value) (
                    SELECT p.post_parent, 'thumbnail_id', p.id 
                    FROM {$this->posts} p
                    WHERE p.post_parent IN ({$ids_csv}) 
                    AND p.post_author = %d 
                    AND p.post_name LIKE 'fifu-category%'
                )
            ", $author);
            $this->wpdb->query($sql_term_thumbnail);

            $sql_term_file = $this->wpdb->prepare("
                INSERT INTO {$this->postmeta} (post_id, meta_key, meta_value) (
                    SELECT p.id, '_wp_attached_file', p.post_content_filtered
                    FROM {$this->posts} p 
                    WHERE p.post_parent IN ({$ids_csv}) 
                    AND p.post_author = %d 
                    AND p.post_name LIKE 'fifu-category%'
                )
            ", $author);
            $this->wpdb->query($sql_term_file);

            $sql_term_alt = $this->wpdb->prepare("
                INSERT INTO {$this->postmeta} (post_id, meta_key, meta_value) (
                    SELECT p.id, '_wp_attachment_image_alt', p.post_title 
                    FROM {$this->posts} p
                    WHERE p.post_parent IN ({$ids_csv}) 
                    AND p.post_author = %d 
                    AND p.post_title IS NOT NULL 
                    AND p.post_title != ''
                    AND p.post_name LIKE 'fifu-category%'
                )
            ", $author);
            $this->wpdb->query($sql_term_alt);

            $this->wpdb->query('COMMIT');
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
        }
    }

    function get_fifu_fields($ids) {
        $ids_csv = $this->sanitize_ids_csv($ids);
        $results = $this->wpdb->get_results("
            SELECT post_id, meta_key, meta_value
            FROM {$this->postmeta}
            WHERE post_id IN ({$ids_csv})
            AND meta_key IN ('fifu_image_url', 'fifu_image_alt', 'fifu_slider_image_url_0', 'fifu_video_url')
        ");

        $post_ids = explode(",", $ids);

        $data = [];
        foreach ($post_ids as $id) {
            $data[$id] = [
                'fifu_image_url' => "",
                'fifu_image_alt' => "",
                'fifu_slider_image_url_0' => "",
                'fifu_video_url' => ""
            ];
        }

        // Populate the results
        foreach ($results as $row) {
            if (isset($data[$row->post_id]))
                $data[$row->post_id][$row->meta_key] = $row->meta_value;
        }

        return $data;
    }

    function get_main_image_url($meta_data, $post_id) {
        $url = $meta_data['fifu_slider_image_url_0'] ?? '';
        if (strpos($url, '#http') !== false)
            $url = substr($url, strpos($url, '#http') + 1);

        if (!$url)
            $url = $meta_data['fifu_image_url'] ?? '';

        if (!$url) {
            $video_url = $meta_data['fifu_video_url'] ?? '';
            if ($video_url)
                $url = fifu_video_img_large($video_url, $post_id, false);
        }

        if (!$url && fifu_no_internal_image($post_id) && (get_option('fifu_default_url') && fifu_is_on('fifu_enable_default_url'))) {
            if (fifu_is_valid_default_cpt($post_id))
                $url = get_option('fifu_default_url');
        }

        if (!$url)
            return null;

        $url = htmlspecialchars_decode($url);

        return str_replace("'", "%27", $url);
    }

    // get urls from external gallery
    function get_gallery_urls_map($post_ids) {
        $ids_csv = $this->sanitize_ids_csv($post_ids);
        $results = $this->wpdb->get_results("
            SELECT post_id, meta_value
            FROM {$this->postmeta} a
            WHERE a.post_id IN ({$ids_csv})
            AND (
                a.meta_key LIKE 'fifu_image_url_%'
                OR a.meta_key LIKE 'fifu_video_url_%'
                OR (
                    a.meta_key LIKE 'fifu_slider_image_url_%'
                    AND a.meta_key <> 'fifu_slider_image_url_0' 
                )
            )
            AND a.meta_value <> ''
            ORDER BY meta_key
        ");
        $map = [];
        foreach ($results as $result) {
            if (!isset($map[$result->post_id]))
                $map[$result->post_id] = [];
            array_push($map[$result->post_id], $result->meta_value);
        }
        return $map;
    }

    function get_thumb_ids_str($ids) {
        $ids_csv = $this->sanitize_ids_csv($ids);
        $results = $this->wpdb->get_results("
            SELECT meta_value
            FROM {$this->postmeta}
            WHERE post_id IN ({$ids_csv})
            AND meta_key = '_thumbnail_id'",
                ARRAY_A
        );
        return implode(',', array_column($results, 'meta_value'));
    }

    /* import */

    function create_table_import() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        maybe_create_table($this->fifu_import, "
            CREATE TABLE {$this->fifu_import} (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                post_id INT NOT NULL
            )
        ");

        $column_exists = $this->wpdb->get_results($this->wpdb->prepare(
                        "SHOW COLUMNS FROM {$this->fifu_import} LIKE %s",
                        'category'
                ));
        if (empty($column_exists))
            $this->wpdb->query("ALTER TABLE {$this->fifu_import} ADD category TINYINT(1) NOT NULL DEFAULT 0");

        // Check if the unique constraint already exists and add it if missing
        $unique_exists = $this->wpdb->get_results($this->wpdb->prepare(
                        "SHOW INDEX FROM {$this->fifu_import} WHERE Key_name = %s",
                        'post_id'
                ));

        if (empty($unique_exists)) {
            $this->wpdb->query("ALTER TABLE {$this->fifu_import} ADD UNIQUE (post_id)");
        }
    }

    function insert_import($post_id, $is_ctgr) {
        $is_ctgr = (int) $is_ctgr;
        $sql = $this->wpdb->prepare(
                "INSERT INTO {$this->fifu_import} (post_id, category) VALUES (%d, %d)
             ON DUPLICATE KEY UPDATE post_id = VALUES(post_id), category = VALUES(category)",
                (int) $post_id,
                $is_ctgr
        );
        $this->wpdb->query($sql);
    }

    function get_all_posts_to_import() {
        return $this->wpdb->get_results("
            SELECT DISTINCT post_id
            FROM {$this->fifu_import} i
            WHERE category = 0
            AND NOT EXISTS (
                SELECT 1
                FROM {$this->postmeta} pm
                WHERE pm.post_id = i.post_id
                AND pm.meta_key = 'fifu_importpost_sent'
            )
        ");
    }

    function get_all_terms_to_import() {
        return $this->wpdb->get_results("
            SELECT DISTINCT post_id
            FROM {$this->fifu_import} i
            WHERE category = 1
            AND NOT EXISTS (
                SELECT 1
                FROM {$this->termmeta} tm
                WHERE tm.term_id = i.post_id
                AND tm.meta_key = 'fifu_importterm_sent'
            )
        ");
    }

    function import($post_ids, $meta_key, $is_ctgr) {
        $ctgr = (int) $is_ctgr;

        if (!$post_ids)
            return 0;

        $total = count($post_ids);

        fifu_plugin_log(['import' => ['count' => $total, 'ctgr' => $is_ctgr,]]);

        $post_ids_str = $this->sanitize_ids_csv($post_ids);

        $this->prepare_meta_out($post_ids_str, $is_ctgr);

        $result = $this->get_meta_out_last('att');
        if (!empty($result) && isset($result[0]->id)) {
            $id = $result[0]->id;
            $this->delete_attmeta($id);
        }

        if (!$is_ctgr) {
            $result = $this->get_meta_out_last('woo');
            if (!empty($result) && isset($result[0]->id)) {
                $id = $result[0]->id;
                $this->delete_woometa($id);
            }
        } else {
            $result = $this->get_meta_out_last('term');
            if (!empty($result) && isset($result[0]->id)) {
                $id = $result[0]->id;
                $this->delete_termmeta($id);
            }
        }

        $this->prepare_meta_in($post_ids_str, $is_ctgr);

        if (!$is_ctgr) {
            $result = $this->get_meta_in_last('post');
            if (!empty($result) && isset($result[0]->id)) {
                $id = $result[0]->id;
                $this->insert_postmeta($id);
            }

            $result = $this->get_meta_in_last('woo');
            if (!empty($result) && isset($result[0]->id)) {
                $id = $result[0]->id;
                $this->insert_woometa($id);
            }
        } else {
            $result = $this->get_meta_in_last('term');
            if (!empty($result) && isset($result[0]->id)) {
                $id = $result[0]->id;
                $this->insert_termmeta($id);
            }
        }

        $this->wpdb->query(
                $this->wpdb->prepare(
                        "DELETE FROM {$this->fifu_import} WHERE post_id IN ({$post_ids_str}) AND category = %d",
                        $ctgr
                )
        );

        if (!$is_ctgr) {
            $this->wpdb->query(
                    $this->wpdb->prepare(
                            "DELETE FROM {$this->postmeta} WHERE post_id IN ({$post_ids_str}) AND meta_key = %s",
                            $meta_key
                    )
            );
        } else {
            $this->wpdb->query(
                    $this->wpdb->prepare(
                            "DELETE FROM {$this->termmeta} WHERE term_id IN ({$post_ids_str}) AND meta_key = %s",
                            $meta_key
                    )
            );
        }

        return $total;
    }

    function get_valid_post_ids_import($post_ids, $meta_key) {
        $post_ids_str = $this->sanitize_ids_csv($post_ids);
        $sql = $this->wpdb->prepare(
                "SELECT DISTINCT fi.post_id
             FROM {$this->fifu_import} fi
             WHERE fi.post_id IN ($post_ids_str)
               AND fi.category = 0
               AND EXISTS (
                   SELECT 1 FROM {$this->postmeta} pm
                   WHERE pm.post_id = fi.post_id AND pm.meta_key = %s
               )",
                $meta_key
        );
        return $this->wpdb->get_col($sql);
    }

    function get_valid_term_ids_import($term_ids, $meta_key) {
        $term_ids_str = $this->sanitize_ids_csv($term_ids);
        $sql = $this->wpdb->prepare(
                "SELECT DISTINCT fi.post_id
             FROM {$this->fifu_import} fi
             WHERE fi.post_id IN ($term_ids_str)
               AND fi.category = 1
               AND EXISTS (
                   SELECT 1 FROM {$this->termmeta} tm
                   WHERE tm.term_id = fi.post_id AND tm.meta_key = %s
               )",
                $meta_key
        );
        return $this->wpdb->get_col($sql);
    }

    /* aawp */

    function get_aawp_asins($type, $keywords) {
        $result = $this->wpdb->get_row(
                $this->wpdb->prepare(
                        "SELECT product_asins FROM {$this->aawp_lists} WHERE type = %s AND keywords = %s",
                        $type,
                        $keywords
                )
        );
        return $result ? $result->product_asins : null;
    }

    function get_aawp_image_ids($asin) {
        $result = $this->wpdb->get_row(
                $this->wpdb->prepare(
                        "SELECT image_ids FROM {$this->aawp_products} WHERE asin = %s",
                        $asin
                )
        );
        return $result ? $result->image_ids : null;
    }

    /* delete local images */

    function delete_not_used_local_images_from_products() {
        $this->wpdb->query('START TRANSACTION');

        try {
            $author = (int) $this->author;
            $sql = $this->wpdb->prepare(
                    "
                SELECT p1.ID
                FROM {$this->posts} p1
                INNER JOIN {$this->posts} p2 ON p1.post_parent = p2.ID
                WHERE p1.post_type = %s
                AND p1.post_mime_type LIKE %s
                AND p1.post_author != %d
                AND p2.post_type = %s
                ",
                    'attachment', // p1.post_type
                    'image%', // p1.post_mime_type LIKE pattern
                    $author, // p1.post_author !=
                    'product'       // p2.post_type
            );

            $attachment_ids = $this->wpdb->get_col($sql);

            if (empty($attachment_ids)) {
                $this->wpdb->query('COMMIT');
                return 0;
            }

            $trashed = 0;
            foreach ($attachment_ids as $attachment_id) {
                if (wp_delete_attachment((int) $attachment_id, false)) {
                    $trashed++;
                }
            }

            $this->wpdb->query('COMMIT');
            return $trashed;
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
            error_log('FIFU error trashing local product images: ' . $e->getMessage());
            return -1;
        }
    }
}

/* rest api */

function fifu_db_insert($post_id, $urls, $alts, $is_slider, $video_urls) {
    $db = new FifuDb();
    if ($urls || $video_urls)
        $db->insert_attachment_list($post_id, $urls, $alts, $is_slider, $video_urls);
    else
        $db->add_default_image($post_id);
}

function fifu_db_update($post_id, $urls, $alts, $is_slider, $video_urls) {
    $db = new FifuDb();
    if ($urls && $urls[0])
        $db->update_attachment_list($post_id, $urls, $alts, $is_slider, $video_urls);
    else
        $db->add_default_image($post_id);
}

function fifu_db_variantion_products($post_id) {
    $db = new FifuDb();
    return $db->get_variantion_products($post_id);
}

/* auto set category image */

function fifu_db_insert_auto_category_image() {
    $db = new FifuDb();
    $db->insert_auto_category_image();
}

/* fake internal featured image */

function fifu_db_insert_attachment_gallery() {
    $db = new FifuDb();
    $db->insert_attachment_gallery();
}

function fifu_db_insert_attachment_category() {
    $db = new FifuDb();
    $db->insert_attachment_category();
}

function fifu_db_insert_attachment() {
    $db = new FifuDb();
    $db->insert_attachment();
}

/* product variation gallery */

function fifu_db_update_wc_additional_variation_images($post_id) {
    $db = new FifuDb();
    return $db->update_wc_additional_variation_images($post_id);
}

/* auto set: update all */

function fifu_db_maybe_create_table_content() {
    $db = new FifuDb();
    $db->create_table_content();
}

function fifu_db_prepare_content() {
    $db = new FifuDb();
    $db->prepare_content();
}

function fifu_db_get_content() {
    $db = new FifuDb();
    return $db->get_content();
}

function fifu_db_insert_content($id) {
    $db = new FifuDb();
    return $db->insert_content($id);
}

/* clean depracted data */

function fifu_db_delete_deprecated_data() {
    $db = new FifuDb();
    $db->delete_deprecated_options();
}

/* dimensions: clean all */

function fifu_db_clean_dimensions_all() {
    $db = new FifuDb();
    return $db->clean_dimensions_all();
}

/* dimensions: amount */

function fifu_db_missing_dimensions() {
    $db = new FifuDb();

    $aux = $db->get_count_posts_without_dimensions()[0];
    return $aux ? $aux->amount : -1;
}

/* count: metadata */

function fifu_db_count_urls_with_metadata() {
    $db = new FifuDb();
    $aux = $db->get_count_urls_with_metadata()[0];
    return $aux ? $aux->amount : 0;
}

function fifu_db_count_metadata_operations() {
    $db = new FifuDb();
    $total_amount = $db->get_count_metadata_operations();
    return $total_amount ? $total_amount : 0;
}

function fifu_db_count_content_operations() {
    $db = new FifuDb();
    $total_amount = $db->get_count_content_operations();
    return $total_amount ? $total_amount : 0;
}

/* count: urls */

function fifu_db_count_urls() {
    $db = new FifuDb();
    $aux = $db->get_count_urls();
    return $aux ? $aux : 0;
}

function fifu_db_get_count_wp_posts() {
    $db = new FifuDb();
    $aux = $db->get_count_wp_posts()[0];
    return $aux ? $aux->amount : 0;
}

function fifu_db_get_count_wp_postmeta() {
    $db = new FifuDb();
    $aux = $db->get_count_wp_postmeta()[0];
    return $aux ? $aux->amount : 0;
}

function fifu_db_get_count_wp_posts_fifu() {
    $db = new FifuDb();
    $aux = $db->get_count_wp_posts_fifu()[0];
    return $aux ? $aux->amount : 0;
}

function fifu_db_get_count_wp_postmeta_fifu() {
    $db = new FifuDb();
    $aux = $db->get_count_wp_postmeta_fifu()[0];
    return $aux ? $aux->amount : 0;
}

function fifu_db_tables_created() {
    $db = new FifuDb();
    return $db->tables_created();
}

/* clean metadata */

function fifu_db_enable_clean() {
    if (fifu_is_on('fifu_cron_metadata')) {
        update_option('fifu_cron_metadata', 'toggleoff', 'no');
        $request = new WP_REST_Request();
        $request->set_param('toggle', 'fifu_toggle_cron_metadata');
        fifu_api_cron_delete($request);
    }

    $db = new FifuDb();
    $db->clear_meta_in();
    $db->enable_clean();
}

function fifu_db_clear_meta_in() {
    $db = new FifuDb();
    $db->clear_meta_in();
}

function fifu_db_clear_meta_out() {
    $db = new FifuDb();
    $db->clear_meta_out();
}

/* delete all urls */

function fifu_db_delete_all() {
    $db = new FifuDb();
    return $db->delete_all();
}

/* save post */

function fifu_db_update_fake_attach_id($post_id) {
    $db = new FifuDb();
    $db->update_fake_attach_id($post_id);
}

function fifu_db_update_fake_attach_id_gallery($post_id) {
    $db = new FifuDb();
    $db->update_fake_attach_id_gallery($post_id);
}

/* save category */

function fifu_db_ctgr_update_fake_attach_id($term_id) {
    $db = new FifuDb();
    $db->ctgr_update_fake_attach_id($term_id);
}

/* default url */

function fifu_db_create_attachment($url) {
    $db = new FifuDb();
    return $db->create_attachment($url);
}

function fifu_db_set_default_url() {
    $db = new FifuDb();
    return $db->set_default_url();
}

function fifu_db_update_default_url($url) {
    $db = new FifuDb();
    return $db->update_default_url($url);
}

function fifu_db_delete_default_url() {
    $db = new FifuDb();
    return $db->delete_default_url();
}

/* delete post */

function fifu_db_before_delete_post($post_id) {
    $db = new FifuDb();
    $db->before_delete_post($post_id);
}

function fifu_db_delete_category_image($post_id) {
    $db = new FifuDb();
    $db->delete_category_image($post_id);
}

/* number of posts */

function fifu_db_number_of_posts() {
    $db = new FifuDb();
    return $db->get_number_of_posts();
}

/* all urls */

function fifu_db_get_featured_and_gallery_urls($post_id) {
    $db = new FifuDb();
    return $db->get_featured_and_gallery_urls($post_id);
}

function fifu_db_delete_featured_and_gallery_urls($post_id) {
    $db = new FifuDb();
    return $db->delete_featured_and_gallery_urls($post_id);
}

/* speed up */

function fifu_db_get_all_urls($page, $type, $keyword) {
    $db = new FifuDb();
    return $db->get_all_urls($page, $type, $keyword);
}

function fifu_db_get_all_hex_ids() {
    $db = new FifuDb();
    return $db->get_all_hex_ids();
}

function fifu_db_get_posts_with_internal_featured_image($page, $type, $keyword) {
    $db = new FifuDb();
    return $db->get_posts_with_internal_featured_image($page, $type, $keyword);
}

function fifu_get_posts_su($storage_ids) {
    $db = new FifuDb();
    return $db->get_posts_su($storage_ids);
}

function fifu_add_urls_su($bucket_id, $thumbnails) {
    $db = new FifuDb();
    return $db->add_urls_su($bucket_id, $thumbnails);
}

function fifu_ctgr_add_urls_su($bucket_id, $thumbnails) {
    $db = new FifuDb();
    return $db->ctgr_add_urls_su($bucket_id, $thumbnails);
}

function fifu_remove_urls_su($bucket_id, $thumbnails, $urls, $video_urls) {
    $db = new FifuDb();
    return $db->remove_urls_su($bucket_id, $thumbnails, $urls, $video_urls);
}

function fifu_ctgr_remove_urls_su($bucket_id, $thumbnails, $urls, $video_urls) {
    $db = new FifuDb();
    return $db->ctgr_remove_urls_su($bucket_id, $thumbnails, $urls, $video_urls);
}

function fifu_usage_verification_su($hex_ids) {
    $db = new FifuDb();
    return $db->usage_verification_su($hex_ids);
}

function fifu_backup_att_ids($post_ids) {
    $db = new FifuDb();
    $db->backup_att_ids($post_ids);
}

function fifu_ctgr_backup_att_ids($term_ids) {
    $db = new FifuDb();
    $db->ctgr_backup_att_ids($term_ids);
}

function fifu_delete_att_ids($post_ids) {
    $db = new FifuDb();
    $db->delete_att_ids($post_ids);
}

function fifu_ctgr_delete_att_ids($term_ids) {
    $db = new FifuDb();
    $db->ctgr_delete_att_ids($term_ids);
}

function fifu_add_custom_fields($values) {
    $db = new FifuDb();
    return $db->add_custom_fields($values);
}

function fifu_ctgr_add_custom_fields($values) {
    $db = new FifuDb();
    return $db->ctgr_add_custom_fields($values);
}

function fifu_db_get_internal_urls($post_ids) {
    $db = new FifuDb();
    return $db->get_internal_urls($post_ids);
}

function fifu_db_get_ctgr_internal_urls($term_ids) {
    $db = new FifuDb();
    return $db->get_ctgr_internal_urls($term_ids);
}

function fifu_db_count_available_images() {
    $db = new FifuDb();
    return $db->count_available_images();
}

/* invalid media */

function fifu_db_create_table_invalid_media_su() {
    $db = new FifuDb();
    return $db->create_table_invalid_media_su();
}

function fifu_db_insert_invalid_media_su($url) {
    $db = new FifuDb();
    return $db->insert_invalid_media_su($url);
}

function fifu_db_delete_invalid_media_su($url) {
    $db = new FifuDb();
    return $db->delete_invalid_media_su($url);
}

function fifu_db_get_attempts_invalid_media_su($url) {
    $db = new FifuDb();
    return $db->get_attempts_invalid_media_su($url);
}

/* schedule */

function fifu_db_get_all_posts_without_meta() {
    $db = new FifuDb();
    return $db->get_all_posts_without_meta();
}

function fifu_db_get_categories_without_meta() {
    $db = new FifuDb();
    return $db->get_categories_without_meta();
}

function fifu_db_get_post_types_without_featured_image($post_types) {
    $db = new FifuDb();
    return $db->get_post_types_without_featured_image($post_types);
}

function fifu_db_get_isbns_without_featured_image() {
    $db = new FifuDb();
    return $db->get_isbns_without_featured_image();
}

function fifu_db_get_asins_without_featured_image() {
    $db = new FifuDb();
    return $db->get_asins_without_featured_image();
}

function fifu_db_get_customfields_without_featured_image() {
    $db = new FifuDb();
    return $db->get_customfields_without_featured_image();
}

function fifu_db_get_posts_types_without_screenshot() {
    $db = new FifuDb();
    return $db->get_posts_types_without_screenshot();
}

function fifu_db_get_finders_without_featured_image() {
    $db = new FifuDb();
    return $db->get_finders_without_featured_image();
}

function fifu_db_get_tags_without_featured_image() {
    $db = new FifuDb();
    return $db->get_tags_without_featured_image();
}

/* get last urls */

function fifu_db_get_last($meta_key) {
    $db = new FifuDb();
    return $db->get_last($meta_key);
}

function fifu_db_get_last_image() {
    $db = new FifuDb();
    return $db->get_last_image();
}

/* wordpress importer */

function fifu_db_delete_thumbnail_id_without_attachment() {
    $db = new FifuDb();
    return $db->delete_thumbnail_id_without_attachment();
}

/* att_id */

function fifu_db_get_att_id($post_parent, $url, $is_ctgr) {
    $db = new FifuDb();
    return $db->get_att_id($post_parent, $url, $is_ctgr);
}

/* media library */

function fifu_db_get_posts_types_with_url_to_upload() {
    $db = new FifuDb();
    return $db->get_posts_types_with_url_to_upload();
}

function fifu_db_get_image_gallery_urls($post_id) {
    $db = new FifuDb();
    return $db->get_image_gallery_urls($post_id);
}

function fifu_db_get_terms_with_url_to_upload() {
    $db = new FifuDb();
    return $db->get_terms_with_url_to_upload();
}

function fifu_db_create_table_md5() {
    $db = new FifuDb();
    return $db->create_table_md5();
}

function fifu_db_get_thumbnail_id_by_md5($url) {
    $db = new FifuDb();
    return $db->get_thumbnail_id_by_md5($url);
}

function fifu_db_delete_md5_by_thumbnail_id($thumbnail_id) {
    $db = new FifuDb();
    return $db->delete_md5_by_thumbnail_id($thumbnail_id);
}

function fifu_db_insert_md5($url, $thumbnail_id) {
    $db = new FifuDb();
    return $db->insert_md5($url, $thumbnail_id);
}

/* video */

function fifu_db_create_table_video_oembed() {
    $db = new FifuDb();
    return $db->create_table_video_oembed();
}

function fifu_db_insert_video_oembed($video_url, $image_url, $embed_url) {
    if ($video_url && $image_url && $embed_url) {
        $db = new FifuDb();
        $db->delete_video_oembed_by_image_url($image_url);
        return $db->insert_video_oembed($video_url, $image_url, $embed_url);
    }
}

function fifu_db_delete_video_oembed_by_video_url($video_url) {
    $db = new FifuDb();
    return $db->delete_video_oembed_by_video_url($video_url);
}

function fifu_db_delete_video_oembed_by_image_url($image_url) {
    $db = new FifuDb();
    return $db->delete_video_oembed_by_image_url($image_url);
}

function fifu_db_get_image_url_by_video_url($video_url) {
    $db = new FifuDb();
    return $db->get_image_url_by_video_url($video_url);
}

function fifu_db_get_embed_url_by_image_url($image_url) {
    $db = new FifuDb();
    return $db->get_embed_url_by_image_url($image_url);
}

function fifu_db_get_embed_url_by_video_url($video_url) {
    $db = new FifuDb();
    return $db->get_embed_url_by_video_url($video_url);
}

function fifu_db_video_oembed_exists($video_url) {
    $db = new FifuDb();
    return $db->video_oembed_exists($video_url);
}

function fifu_db_update_image_url_by_video_url($video_url, $image_url) {
    $db = new FifuDb();
    return $db->update_image_url_by_video_url($video_url, $image_url);
}

/* metadata */

function fifu_db_maybe_create_table_meta_in() {
    $db = new FifuDb();
    $db->create_table_meta_in();
}

function fifu_db_maybe_create_table_meta_out() {
    $db = new FifuDb();
    $db->create_table_meta_out();
}

function fifu_db_prepare_meta_in() {
    $db = new FifuDb();
    $db->prepare_meta_in(null, null);
}

function fifu_db_prepare_meta_out() {
    $db = new FifuDb();
    $db->prepare_meta_out(null, null);
}

function fifu_db_get_meta_in() {
    $db = new FifuDb();
    return $db->get_meta_in();
}

function fifu_db_get_meta_out() {
    $db = new FifuDb();
    return $db->get_meta_out();
}

function fifu_db_get_type_meta_in($id) {
    $db = new FifuDb();
    return $db->get_type_meta_in($id);
}

function fifu_db_get_type_meta_out($id) {
    $db = new FifuDb();
    return $db->get_type_meta_out($id);
}

function fifu_db_insert_postmeta($id) {
    $db = new FifuDb();
    return $db->insert_postmeta($id);
}

function fifu_db_insert_woometa($id) {
    $db = new FifuDb();
    return $db->insert_woometa($id);
}

function fifu_db_insert_termmeta($id) {
    $db = new FifuDb();
    return $db->insert_termmeta($id);
}

function fifu_db_delete_attmeta($id) {
    $db = new FifuDb();
    return $db->delete_attmeta($id);
}

function fifu_db_delete_woometa($id) {
    $db = new FifuDb();
    return $db->delete_woometa($id);
}

function fifu_db_delete_termmeta($id) {
    $db = new FifuDb();
    return $db->delete_termmeta($id);
}

/* import */

function fifu_db_create_table_import() {
    $db = new FifuDb();
    $db->create_table_import();
}

function fifu_db_insert_import($post_id, $is_ctgr) {
    $db = new FifuDb();
    $db->insert_import($post_id, $is_ctgr);
}

function fifu_db_get_all_posts_to_import() {
    $db = new FifuDb();
    return $db->get_all_posts_to_import();
}

function fifu_db_get_all_terms_to_import() {
    $db = new FifuDb();
    return $db->get_all_terms_to_import();
}

function fifu_db_import($post_ids, $meta_key, $is_ctgr) {
    $db = new FifuDb();
    return $db->import($post_ids, $meta_key, $is_ctgr);
}

function fifu_db_get_valid_post_ids_import($post_ids, $meta_key) {
    $db = new FifuDb();
    return $db->get_valid_post_ids_import($post_ids, $meta_key);
}

function fifu_db_get_valid_term_ids_import($term_ids, $meta_key) {
    $db = new FifuDb();
    return $db->get_valid_term_ids_import($term_ids, $meta_key);
}

function fifu_db_delete_garbage_wai() {
    $db = new FifuDb();
    return $db->delete_garbage_wai();
}

/* fifu gallery */

function fifu_db_get_variation_attributes($post_id) {
    $db = new FifuDb();
    return $db->get_variation_attributes($post_id);
}

function fifu_db_get_variation_att_ids($post_id) {
    $db = new FifuDb();
    return $db->get_variation_att_ids($post_id);
}

/* grid */

function fifu_db_get_slider_urls($post_id) {
    $db = new FifuDb();
    return $db->get_slider_urls($post_id);
}

/* product gallery */

function fifu_get_gallery_urls($post_id) {
    $db = new FifuDb();
    return $db->get_gallery_urls($post_id);
}

function fifu_get_gallery_alts($post_id) {
    $db = new FifuDb();
    return $db->get_gallery_alts($post_id);
}

/* aawp plugin */

function fifu_get_aawp_asins($type, $keywords) {
    $db = new FifuDb();
    return $db->get_aawp_asins($type, $keywords);
}

function fifu_get_aawp_image_ids($asin) {
    $db = new FifuDb();
    return $db->get_aawp_image_ids($asin);
}

/* houzez theme */

function fifu_db_get_post_ids_for_houzez() {
    $db = new FifuDb();
    return $db->get_post_ids_for_houzez();
}

function fifu_db_delete_fave_property_images($post_ids) {
    $db = new FifuDb();
    return $db->delete_fave_property_images($post_ids);
}

function fifu_db_add_fave_property_images($post_ids) {
    $db = new FifuDb();
    return $db->add_fave_property_images($post_ids);
}

/* wp_options */

function fifu_db_insert_option($name, $value) {
    $db = new FifuDb();
    $db->insert_option($name, $value);
}

function fifu_db_select_option($name) {
    $db = new FifuDb();
    $aux = $db->select_option($name);
    return $aux ? $aux[0]->option_value : null;
}

function fifu_db_delete_option($name) {
    $db = new FifuDb();
    $db->delete_option($name);
}

function fifu_db_select_option_prefix($prefix) {
    $db = new FifuDb();
    return $db->select_option_prefix($prefix);
}

function fifu_db_delete_option_prefix($prefix) {
    $db = new FifuDb();
    return $db->delete_option_prefix($prefix);
}

/* debug */

function fifu_db_debug_slug($slug) {
    $db = new FifuDb();
    return $db->debug_slug($slug);
}

function fifu_db_debug_postmeta($post_id) {
    $db = new FifuDb();
    return $db->debug_postmeta($post_id);
}

function fifu_db_debug_posts($id) {
    $db = new FifuDb();
    return $db->debug_posts($id);
}

function fifu_db_debug_metain() {
    $db = new FifuDb();
    return $db->debug_metain();
}

function fifu_db_debug_metaout() {
    $db = new FifuDb();
    return $db->debug_metaout();
}

/* delete local images (careful!) */

function fifu_db_delete_not_used_local_images_from_products() {
    $db = new FifuDb();
    return $db->delete_not_used_local_images_from_products();
}

