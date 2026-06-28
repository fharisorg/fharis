<?php

if (!class_exists('TaxoPress_Pro_Post_Tags')) {
    /**
     * Class TaxoPress_Pro_Post_Tags
     */
    class TaxoPress_Pro_Post_Tags
    {
        // Singleton instance
        private static $instance;

        /**
         * Construct the TaxoPress_Pro_Post_Tags class
         */
        public function __construct()
        {
            add_action('admin_init', [$this, 'taxopress_pro_copy_posttags']);
            add_filter('taxopress_posttags_row_actions', [$this, 'taxopress_pro_copy_action'], 10, 2);
            add_filter('taxopress_display_formats', [$this, 'taxopress_render_display_formats_fields']);
            add_action('taxopress_posttags_ordering_method', [$this, 'taxopress_pro_posttags_ordering_method']);
        }

        /** Singleton instance */
        public static function get_instance()
        {
            if (!isset(self::$instance)) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function taxopress_pro_copy_posttags()
        {
            if (isset($_GET['copied_posttags']) && (int) $_GET['copied_posttags'] === 1) {
                add_action('admin_notices', [$this, 'taxopress_posttags_copy_success_admin_notice']);
                add_filter('removable_query_args', [$this, 'taxopress_copied_posttags_filter_removable_query_args']);
            }

            if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'taxopress-copy-posttags') {
                $nonce = sanitize_text_field($_REQUEST['_wpnonce']);
                if (wp_verify_nonce($nonce, 'posttags-action-request-nonce')) {
                    $this->taxopress_action_copy_posttags(sanitize_text_field($_REQUEST['taxopress_posttags']));
                }
                add_filter('removable_query_args', [$this, 'taxopress_copy_posttags_filter_removable_query_args']);
            }
        }

        public function taxopress_action_copy_posttags($posttags_id)
        {
            if (!taxopress_is_pro_version()) {
                wp_safe_redirect(admin_url('admin.php?page=st_post_tags&add=new_item'));
                exit;
            }

            $posttagss = taxopress_get_posttags_data();

            if (array_key_exists($posttags_id, $posttagss)) {
                $new_posttags = $posttagss[$posttags_id];
                $new_posttags['title'] .= '-copy';

                $new_id = (int) get_option('taxopress_posttags_ids_increament') + 1;
                $new_posttags['ID'] = $new_id;

                $posttagss[$new_id] = $new_posttags;

                update_option('taxopress_posttagss', $posttagss);
                update_option('taxopress_posttags_ids_increament', $new_id);
            }

            wp_safe_redirect(
                add_query_arg([
                    'page'             => 'st_post_tags',
                    'copied_posttags'  => 1,
                ], taxopress_admin_url('admin.php'))
            );
            exit();
        }

        public function taxopress_posttags_copy_success_admin_notice()
        {
            echo taxopress_admin_notices_helper(esc_html__('Shortcode entry successfully copied.', 'simple-tags'), true);
        }

        public function taxopress_copied_posttags_filter_removable_query_args(array $args)
        {
            return array_merge($args, ['copied_posttags']);
        }

        public function taxopress_copy_posttags_filter_removable_query_args(array $args)
        {
            return array_merge($args, ['action', 'taxopress_posttags', '_wpnonce']);
        }

        public function taxopress_pro_copy_action($actions, $item)
        {
            $actions['copy'] = sprintf(
                '<a href="%s" class="copy-posttags">%s</a>',
                add_query_arg([
                    'page'               => 'st_post_tags',
                    'action'             => 'taxopress-copy-posttags',
                    'taxopress_posttags' => esc_attr($item['ID']),
                    '_wpnonce'           => wp_create_nonce('posttags-action-request-nonce')
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

        public function taxopress_render_display_formats_fields($current){
            $ui = new taxopress_admin_ui();
             $select = [
                'options' => [
                    [ 'attr' => 'flat', 'text' => esc_attr__( 'Cloud', 'simple-tags' )],
                    [ 'attr' => 'list', 'text' => esc_attr__( 'Unordered List (UL/LI)', 'simple-tags' ) ],
                    [ 'attr' => 'ol', 'text' => esc_attr__( 'Ordered List (OL/LI)', 'simple-tags' ) ],
                    [ 'attr' => 'comma', 'text' => esc_attr__( 'WordPress Default', 'simple-tags' ), 'default' => 'true'],
                    ['attr' => 'table', 'text' => esc_attr__('Table List', 'simple-tags')],
                    ['attr' => 'border', 'text' => esc_attr__('Border Cloud', 'simple-tags')],
                ],
            ];
            $selected = (isset($current) && isset($current['format'])) ? taxopress_disp_boolean($current['format']) : '';
            $select['selected'] = ! empty( $selected ) ? $current['format'] : '';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $ui->get_select_checkbox_input_main( [
                    'namearray'  => 'taxopress_post_tags',
                    'name'       => 'format',
                    'labeltext'  => esc_html__( 'Display format', 'simple-tags' ),
                    'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ] );
        }

         function taxopress_pro_posttags_ordering_method($current){
            $ui = new taxopress_admin_ui();

            $select = [
                'options' => [
                    [ 'attr' => 'name', 'text' => esc_attr__( 'Name', 'simple-tags' ) ],
                    [ 'attr' => 'count', 'text' => esc_attr__( 'Counter', 'simple-tags') ],
                    [ 'attr' => 'random', 'text' => esc_attr__( 'Random', 'simple-tags' ), 'default' => 'true' ],
                    [ 'attr' => 'taxopress_term_order', 'text' => esc_attr__( 'Term Order', 'simple-tags' ) ],
                ],
            ];
            $selected = isset( $current['orderby'] ) ? taxopress_disp_boolean( $current['orderby'] ) : '';
            $select['selected'] = ! empty( $selected ) ? $current['orderby'] : '';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $ui->get_select_checkbox_input_main( [
                    'namearray'  => 'taxopress_post_tags',
                    'name'       => 'orderby',
                    'labeltext'  => esc_html__( 'Method for choosing terms for display', 'simple-tags' ),
                    'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ] );
        }
    }
}
