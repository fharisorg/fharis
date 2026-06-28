<?php

if (!class_exists('TaxoPress_Pro_Terms')) {
    /**
     * class TaxoPress_Pro_Terms
     */
    class TaxoPress_Pro_Terms 
    {
        // class instance
        static $instance;

        /**
         * Construct the TaxoPress_Pro_Terms class
         */
        public function __construct()
        {
            add_action('admin_init', [$this, 'taxopress_pro_copy_terms']);
            add_filter('taxopress_terms_row_actions', [$this, 'taxopress_pro_copy_action'], 10, 2);
            add_action('wp_ajax_taxopress_save_term_order', [$this, 'taxopress_save_term_order_callback']);
        }

        /** Singleton instance */
        public static function get_instance()
        {
            if (!isset(self::$instance)) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        public function taxopress_pro_copy_terms()
        {
            if (isset($_GET['copied_term']) && (int)$_GET['copied_term'] === 1) {
                add_action('admin_notices', [$this, 'taxopress_term_copy_success_admin_notice']);
                add_filter('removable_query_args', [$this, 'taxopress_copied_term_filter_removable_query_args']);
            }

            if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'taxopress-copy-term-with-meta') {
                $nonce = sanitize_text_field($_REQUEST['_wpnonce']);
                if (wp_verify_nonce($nonce, 'terms-action-request-nonce')) {
                    $this->taxopress_action_copy_term_with_meta(sanitize_text_field($_REQUEST['taxopress_terms']));
                }
                add_filter('removable_query_args', [$this, 'taxopress_copy_term_filter_removable_query_args']);
            }
        }

        public function taxopress_action_copy_term_with_meta($term_id)
        {
            $term = get_term($term_id);

            // Get taxonomy if term is not an object
            if (!is_object($term)) {
                $term_taxonomy = wp_get_object_terms($term_id, get_taxonomies());
                if (!empty($term_taxonomy) && !is_wp_error($term_taxonomy)) {
                    $term = $term_taxonomy[0];
                }
            }

            if ($term && !is_wp_error($term)) {
                $new_name = $term->name . ' Copy';
                $base_slug = $term->slug . '-copy';
                $new_slug = taxopress_get_unique_term_slug($base_slug, $term->taxonomy);

                $new_term_data = wp_insert_term($new_name, $term->taxonomy, [
                    'slug' => $new_slug,
                    'description' => $term->description,
                    'parent' => $term->parent,
                ]);

                if (!is_wp_error($new_term_data)) {
                    $new_term = get_term($new_term_data['term_id'], $term->taxonomy);
                    if ($new_term && !is_wp_error($new_term)) {
                        $meta = get_term_meta($term->term_id);
                        foreach ($meta as $key => $values) {
                            foreach ($values as $value) {
                                add_term_meta($new_term->term_id, $key, maybe_unserialize($value));
                            }
                        }

                        // add support for yoast SEO (most seo data already covered by term meta)
                        $yoast_meta = get_option('wpseo_taxonomy_meta');
                        if (!empty($yoast_meta[$term->taxonomy][$term->term_id])) {
                            $original_yoast_data = $yoast_meta[$term->taxonomy][$term->term_id];

                            $yoast_meta[$term->taxonomy][$new_term->term_id] = $original_yoast_data;

                            update_option('wpseo_taxonomy_meta', $yoast_meta);
                        }

                        $args = [
                            'post_type' => 'any',
                            'posts_per_page' => -1,
                            'tax_query' => [[
                                'taxonomy' => $term->taxonomy,
                                'field' => 'id',
                                'terms' => $term->term_id,
                            ]],
                        ];
                        $posts = get_posts($args);
                        foreach ($posts as $post) {
                            wp_set_object_terms($post->ID, $new_term->term_id, $term->taxonomy, true);
                        }

                        clean_term_cache($new_term->term_id, $term->taxonomy);
                        delete_transient('taxopress_terms_' . $term->taxonomy);
                    }
                }
            }

            wp_safe_redirect(
                add_query_arg([
                    'page'         => 'st_terms',
                    'copied_term'  => 1,
                ], admin_url('admin.php'))
            );
            exit();
        }

        public function taxopress_term_copy_success_admin_notice()
        {
            echo taxopress_admin_notices_helper(esc_html__('Term successfully copied with metadata.', 'taxopress-pro'), true);
        }

        public function taxopress_copied_term_filter_removable_query_args(array $args)
        {
            return array_merge($args, ['copied_term']);
        }

        public function taxopress_copy_term_filter_removable_query_args(array $args)
        {
            return array_merge($args, ['action', 'taxopress_terms', '_wpnonce']);
        }

        public function taxopress_pro_copy_action($actions, $item)
        {
            $actions['copy_term_with_meta'] = sprintf(
                '<a href="%s">%s</a>',
                add_query_arg([
                    'page'            => 'st_terms',
                    'action'          => 'taxopress-copy-term-with-meta',
                    'taxopress_terms' => esc_attr($item->term_id),
                    '_wpnonce'        => wp_create_nonce('terms-action-request-nonce'),
                ], admin_url('admin.php')),
                esc_html__('Copy with Metadata', 'taxopress-pro')
            );

            if (isset($actions['copy'])) {
                $new_actions = [];
                foreach ($actions as $key => $action) {
                    if ($key === 'copy') {
                        $new_actions['copy_term_with_meta'] = $actions['copy_term_with_meta'];
                    }
                    $new_actions[$key] = $action;
                }
                return $new_actions;
            }

            return $actions;
        }

        public function taxopress_save_term_order_callback()
        {
    
            if (!current_user_can('simple_tags')) {
                wp_send_json_error(['message' => esc_html__('Permission denied.', 'simple-tags')]);
            }

            if ( !isset($_POST['nonce']) || !wp_verify_nonce(sanitize_key($_POST['nonce']), 'st-admin-js')) {
                wp_send_json_error(['message' => esc_html__('Invalid nonce.', 'simple-tags')]);
            }

            $taxonomy = isset($_POST['taxonomy']) ? sanitize_text_field($_POST['taxonomy']) : '';
            $order    = isset($_POST['order']) && is_array($_POST['order']) ? array_map('intval', $_POST['order']) : [];

            // Only allow saving if taxonomy is set and not empty
            if (empty($taxonomy) || empty($order)) {
                wp_send_json_error(['message' => esc_html__('Invalid taxonomy or order.', 'simple-tags')]);
            }

            // Get the current order setting for this taxonomy
            $taxonomy_settings = taxopress_get_all_edited_taxonomy_data();
            $order_setting = isset($taxonomy_settings[$taxonomy]['order']) ? $taxonomy_settings[$taxonomy]['order'] : 'desc';

            // If order is desc, reverse the array before saving
            if ($order_setting === 'desc') {
                $order = array_reverse($order);
            }

            update_option('taxopress_term_order_' . $taxonomy, $order);

            wp_send_json_success(['message' => esc_html__('Order saved.', 'simple-tags')]);
        }
    }
}
