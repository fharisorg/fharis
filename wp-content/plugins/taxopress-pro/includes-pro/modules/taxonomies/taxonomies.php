<?php

if (!class_exists('TaxoPress_Pro_Taxonomies')) {
    /**
     * class TaxoPress_Pro_Taxonomies
     */
    class TaxoPress_Pro_Taxonomies
    {
        // class instance
        static $instance;

        /**
         * Construct the TaxoPress_Pro_Taxonomies class
         */
        public function __construct()
        {
            add_action('taxopress_terms_order', [$this, 'taxopress_terms_order_pro']);
        }


        /** Singleton instance */
        public static function get_instance()
        {
            if (!isset(self::$instance)) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        public function taxopress_terms_order_pro($current){

            $ui = new taxopress_admin_ui();
                               
            $orderby_options = [
                [ 'attr' => 'term_id', 'text' => esc_attr__( 'ID', 'simple-tags' ), 'default' => true ],
                [ 'attr' => 'name', 'text' => esc_attr__( 'Name', 'simple-tags' ) ],
                [ 'attr' => 'count', 'text' => esc_attr__( 'Counter', 'simple-tags') ],
                [ 'attr' => 'random', 'text' => esc_attr__( 'Random', 'simple-tags' ) ],
                [ 'attr' => 'taxopress_term_order', 'text' => esc_attr__( 'Term Order', 'simple-tags' ) ],
            ];
            $selected_orderby = isset($current['orderby']) ? $current['orderby'] : '';
            $name = isset($current['name']) ? $current['name'] : '';
            $terms_table_url = esc_url(admin_url('admin.php?page=st_terms&taxopress_terms_taxonomy=' . urlencode($name) . '&taxopress_show_all=1'));

            $taxonomy_label = '';
            $all_taxonomies = function_exists('taxopress_get_all_taxonomies') ? taxopress_get_all_taxonomies() : [];
            if (isset($all_taxonomies[$name]) && is_object($all_taxonomies[$name]) && !empty($all_taxonomies[$name]->label)) {
                $taxonomy_label = $all_taxonomies[$name]->label;
            }
            if (empty($taxonomy_label)) {
                $taxonomy_label = esc_html__('Taxonomy', 'simple-tags');
            }
            ?>
            <tr>
                <th scope="row"><?php echo esc_html__('Method for choosing terms for display', 'simple-tags'); ?></th>
                <td>
                    <?php foreach ($orderby_options as $option): ?>
                        <label style="display:block;margin-bottom:4px;">
                            <input type="radio"
                                name="cpt_custom_tax[orderby]"
                                value="<?php echo esc_attr($option['attr']); ?>"
                                <?php echo ($selected_orderby === $option['attr'] || (empty($selected_orderby) && !empty($option['default']))) ? 'checked="checked"' : ''; ?>
                            />
                            <?php echo esc_html($option['text']); ?>
                        </label>
                        <?php if ($option['attr'] === 'taxopress_term_order'): ?>
                            <?php
                                $is_id = ($option['attr'] === 'term_id');
                                $checked = ($selected_orderby === $option['attr'] || (empty($selected_orderby) && $is_id)) ? 'checked="checked"' : '';
                            ?>
                            <div class="taxopress-field-description description">
                                <?php echo esc_html__('If you select "Term Order", you can manually order terms in the ', 'simple-tags'); ?>
                                <a href="<?php echo $terms_table_url; ?>" target="_blank">
                                    <?php echo esc_html(sprintf(__('%s Order Screen', 'simple-tags'), $taxonomy_label)); ?>
                                </a>.
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </td>
            </tr>
            <?php
            
            $order_options = [
                [ 'attr' => 'asc', 'text' => esc_attr__( 'Ascending', 'simple-tags' ), 'default' => true ],
                [ 'attr' => 'desc', 'text' => esc_attr__( 'Descending', 'simple-tags') ],
            ];
            $selected_order = isset($current['order']) ? $current['order'] : '';
            ?>
            <tr>
                <th scope="row"><?php echo esc_html__('Ordering for choosing terms for display', 'simple-tags'); ?></th>
                <td>
                    <?php foreach ($order_options as $option): ?>
                        <?php
                            $is_asc = ($option['attr'] === 'asc');
                            $checked = ($selected_order === $option['attr'] || (empty($selected_order) && $is_asc)) ? 'checked="checked"' : '';
                        ?>
                        <label style="display:block;margin-bottom:4px;">
                            <input type="radio"
                                name="cpt_custom_tax[order]"
                                value="<?php echo esc_attr($option['attr']); ?>"
                                <?php echo ($selected_order === $option['attr'] || (empty($selected_order) && !empty($option['default']))) ? 'checked="checked"' : ''; ?>
                            />
                            <?php echo esc_html($option['text']); ?>
                        </label>
                    <?php endforeach; ?>
                </td>
            </tr>
            <?php

        }


    }
}    