<?php

if (!class_exists('TaxoPress_Pro_Taxonomies_Table')) {
    /**
     * class TaxoPress_Pro_Taxonomies_Table
     */
    class TaxoPress_Pro_Taxonomies_Table
    {
        // class instance
        static $instance;

        /**
         * Construct the TaxoPress_Pro_Taxonomies_Table class
         */
        public function __construct()
        {
            add_filter('taxopress_order_column', [$this, 'taxopress_order_column_pro']);
        }


        /** Singleton instance */
        public static function get_instance()
        {
            if (!isset(self::$instance)) {
                self::$instance = new self();
            }

            return self::$instance;
        }

       
        public function taxopress_order_column_pro($item)
        {
            // Get the orderby value and ordering enabled flag from the taxonomy object
            $taxonomies = taxopress_get_all_edited_taxonomy_data();
            $order_value = '';
            $ordering_enabled = false;

            if (isset($taxonomies[$item->name]['orderby'])) {
                $order_value = $taxonomies[$item->name]['orderby'];
            }
            if (!empty($taxonomies[$item->name]['enable_taxopress_ordering'])) {
                $ordering_enabled = true;
            }

            $orderby_options = [
                'name' => esc_html__('Name', 'simple-tags'),
                'term_id' => esc_html__('ID', 'simple-tags'),
                'count' => esc_html__('Counter', 'simple-tags'),
                'random' => esc_html__('Random', 'simple-tags'),
                'taxopress_term_order' => esc_html__('Term Order', 'simple-tags'),
            ];

            // Build the terms table URL
            $terms_table_url = esc_url(
                add_query_arg(
                    [
                        'page' => 'st_terms',
                        'taxopress_terms_taxonomy' => $item->name,
                        'taxopress_show_all' => 1
                    ],
                    admin_url('admin.php')
                )
            );

            if (!$ordering_enabled) {
                // Show Disabled with tooltip
                return sprintf(
                    '<div class="pp-tooltips-library" data-toggle="tooltip">
                        <span class="taxopress-order-disabled">%s</span>
                        <div class="taxopress tooltip-text">%s</div>
                    </div>',
                    esc_html__('Disabled', 'simple-tags'),
                    esc_html__('You have disabled TaxoPress ordering', 'simple-tags')
                );
            }

            $order_label = isset($orderby_options[$order_value]) ? $orderby_options[$order_value] : esc_html__('ID', 'simple-tags');
            $tooltip = sprintf(
                esc_html__('Terms in this taxonomy are ordered by %s', 'simple-tags'),
                $order_label
            );

            if ($order_value === 'taxopress_term_order') {
                // Make "Term Order" a link, open in new tab, with tooltip
                return sprintf(
                    '<div class="pp-tooltips-library" data-toggle="tooltip">
                        <a href="%s" target="_blank">%s</a>
                        <div class="taxopress tooltip-text">%s</div>
                    </div>',
                    $terms_table_url,
                    esc_html($orderby_options[$order_value]),
                    esc_html($tooltip)
                );
            }

            // For other orderby, just show  label with tooltip
            return sprintf(
                '<div class="pp-tooltips-library" data-toggle="tooltip">
                    %s
                    <div class="taxopress tooltip-text">%s</div>
                </div>',
                esc_html($order_label),
                esc_html($tooltip)
            );
        }


        }
}    

