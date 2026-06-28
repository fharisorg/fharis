<?php

if (!class_exists('TaxoPress_Pro_Tag_Clouds')) {
    /**
     * class TaxoPress_Pro_Tag_Clouds
     */
    class TaxoPress_Pro_Tag_Clouds
    {
        // class instance
        static $instance;

        /**
         * Construct the TaxoPress_Pro_Tag_Clouds class
         */
        public function __construct()
        {
            add_action('admin_init', [$this, 'taxopress_pro_copy_tagcloud']);
            add_filter('taxopress_tagclouds_row_actions', [$this, 'taxopress_pro_copy_action'], 10, 2);
            add_action('taxopress_tagcloud_ordering_method', [$this, 'taxopress_pro_tagcloud_ordering_method']);
        }


        /** Singleton instance */
        public static function get_instance()
        {
            if (!isset(self::$instance)) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        public function taxopress_pro_copy_tagcloud() {
            if (isset($_GET['copied_tagcloud']) && (int) $_GET['copied_tagcloud'] === 1) {
                add_action('admin_notices', [$this, 'taxopress_termsdisplay_copy_success_admin_notice']);
                add_filter('removable_query_args', [$this, 'taxopress_copied_tagcloud_filter_removable_query_args']);
            }

            if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'taxopress-copy-tagcloud') {
                $nonce = sanitize_text_field($_REQUEST['_wpnonce']);
                if (wp_verify_nonce($nonce, 'tagcloud-action-request-nonce')) {
                    $this->taxopress_action_copy_tagcloud(sanitize_text_field($_REQUEST['taxopress_termsdisplay']));
                }
                add_filter('removable_query_args', [$this, 'taxopress_copy_tagcloud_filter_removable_query_args']);
            }

        }

        public function taxopress_action_copy_tagcloud($tagcloud_id) {

            $tagclouds = taxopress_get_tagcloud_data();

            if (array_key_exists($tagcloud_id, $tagclouds)) {
                $new_tagcloud = $tagclouds[$tagcloud_id];
                $new_tagcloud['title'] .= '-copy';
                
                $new_id = (int)get_option('taxopress_tagcloud_ids_increament') + 1;
                $new_tagcloud['ID'] = $new_id;
                
                $tagclouds[$new_id] = $new_tagcloud;
                
                update_option('taxopress_tagclouds', $tagclouds);
                update_option('taxopress_tagcloud_ids_increament', $new_id);
            }
        
            wp_safe_redirect(
                add_query_arg([
                    'page'             => 'st_terms_display',
                    'copied_tagcloud'  => 1,
                ], taxopress_admin_url('admin.php'))
            );   
            exit();
        }

        function taxopress_termsdisplay_copy_success_admin_notice()
        {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo taxopress_admin_notices_helper(esc_html__('Terms Display successfully copied.', 'simple-tags'), true);
        }
        
        function taxopress_copied_tagcloud_filter_removable_query_args(array $args)
        {
            return array_merge($args, [
                'copied_tagcloud',
            ]);
        }

        function taxopress_copy_tagcloud_filter_removable_query_args(array $args)
        {
            return array_merge($args, [
                'action',
                'taxopress_termsdisplay',
                '_wpnonce',
            ]);
        }

        public function taxopress_pro_copy_action($actions, $item) {
            
            $actions['copy'] = sprintf(
                '<a href="%s" class="copy-tagcloud">%s</a>',
                add_query_arg([
                    'page' => 'st_terms_display',
                    'action' => 'taxopress-copy-tagcloud',
                    'taxopress_termsdisplay' => esc_attr($item['ID']), // FIX: Ensure key matches expected parameter
                    '_wpnonce' => wp_create_nonce('tagcloud-action-request-nonce')
                ], admin_url('admin.php')),
                __('Copy', 'simple-tags')
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

        function taxopress_pro_tagcloud_ordering_method($current){
            $ui = new taxopress_admin_ui();

            $select = [
                'options' => [
                    [ 'attr' => 'name', 'text' => esc_attr__( 'Name', 'simple-tags' ) ],
                    [ 'attr' => 'count', 'text' => esc_attr__( 'Counter', 'simple-tags') ],
                    [ 'attr' => 'random', 'text' => esc_attr__( 'Random', 'simple-tags' ), 'default' => 'true' ],
                    [ 'attr' => 'taxopress_term_order', 'text' => esc_attr__( 'Term Order', 'simple-tags' ) ],
                ],
            ];
            $selected = isset( $current ) ? taxopress_disp_boolean( $current['orderby'] ) : '';
            $select['selected'] = ! empty( $selected ) ? $current['orderby'] : '';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $ui->get_select_checkbox_input_main( [
                    'namearray'  => 'taxopress_tag_cloud',
                    'name'       => 'orderby',
                    'labeltext'  => esc_html__( 'Method for choosing terms for display', 'simple-tags' ),
                    'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ] );
        }

    }
}    