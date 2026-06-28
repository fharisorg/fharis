<?php

if (!class_exists('TaxoPress_Pro_Related_Posts')) {
    /**
     * class TaxoPress_Pro_Related_Posts
     */
    class TaxoPress_Pro_Related_Posts
    {
        // class instance
        static $instance;

        /**
         * Construct the TaxoPress_Pro_Related_Posts class
         */
        public function __construct()
        {
            add_action('admin_init', [$this, 'taxopress_pro_copy_relatedpost']);
            add_filter('taxopress_relatedpost_row_actions', [$this, 'taxopress_pro_copy_action'], 10, 2);
        }


        /** Singleton instance */
        public static function get_instance()
        {
            if (!isset(self::$instance)) {
                self::$instance = new self();
            }

            return self::$instance;
        }

public function taxopress_action_copy_relatedpost($relatedpost_id) {
    $relatedposts = taxopress_get_relatedpost_data();

    if (array_key_exists($relatedpost_id, $relatedposts)) {
        $new_relatedpost = $relatedposts[$relatedpost_id];
        $new_relatedpost['title'] .= '-copy';

        $new_id = (int)get_option('taxopress_relatedpost_ids_increament') + 1;
        $new_relatedpost['ID'] = $new_id;

        $relatedposts[$new_id] = $new_relatedpost;

        update_option('taxopress_relatedposts', $relatedposts);
        update_option('taxopress_relatedpost_ids_increament', $new_id);
    }

    wp_safe_redirect(
        add_query_arg([
            'page'                => 'st_related_posts',
            'copied_relatedpost'  => 1,
        ], taxopress_admin_url('admin.php'))
    );
    exit();
}

public function taxopress_relatedposts_copy_success_admin_notice() {
    echo taxopress_admin_notices_helper(esc_html__('Related Posts successfully copied.', 'simple-tags'), true);
}

public function taxopress_copied_relatedpost_filter_removable_query_args(array $args) {
    return array_merge($args, ['copied_relatedpost']);
}

public function taxopress_copy_relatedpost_filter_removable_query_args(array $args) {
    return array_merge($args, ['action', 'taxopress_relatedposts', '_wpnonce']);
}

public function taxopress_pro_copy_relatedpost() {
    if (isset($_GET['copied_relatedpost']) && (int)$_GET['copied_relatedpost'] === 1) {
        add_action('admin_notices', [$this, 'taxopress_relatedposts_copy_success_admin_notice']);
        add_filter('removable_query_args', [$this, 'taxopress_copied_relatedpost_filter_removable_query_args']);
    }

    if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'taxopress-copy-relatedpost') {
        $nonce = sanitize_text_field($_REQUEST['_wpnonce']);
        if (wp_verify_nonce($nonce, 'relatedpost-action-request-nonce')) {
            $this->taxopress_action_copy_relatedpost(sanitize_text_field($_REQUEST['taxopress_relatedposts']));
        }
        add_filter('removable_query_args', [$this, 'taxopress_copy_relatedpost_filter_removable_query_args']);
    }
}

public function taxopress_pro_copy_action($actions, $item) {
    $actions['copy'] = sprintf(
        '<a href="%s" class="copy-relatedpost">%s</a>',
        add_query_arg([
            'page'                   => 'st_related_posts',
            'action'                 => 'taxopress-copy-relatedpost',
            'taxopress_relatedposts' => esc_attr($item['ID']),
            '_wpnonce'               => wp_create_nonce('relatedpost-action-request-nonce')
        ], admin_url('admin.php')),
        esc_html__('Copy', 'simple-tags')
    );

    if (isset($actions['delete'])) {
        $new_actions = [];
        foreach ($actions as $key => $action) {
            if ($key === 'delete') {
                $new_actions['copy'] = $actions['copy'];
            }
            $new_actions[$key] = $action;
        }
        return $new_actions;
    }

    return $actions;
}

    }
}        