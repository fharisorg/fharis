<?php
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}


if (!class_exists('Taxopress_Linked_Terms_List')) {

    class Taxopress_Linked_Terms_List extends WP_List_Table
    {

        /** Class constructor */
        public function __construct()
        {

            parent::__construct([
                'singular' => 'Linked Term', //singular name of the listed records
                'plural'   => 'Linked Terms', //plural name of the listed records
                'ajax'     => true //does this table support ajax?
            ]);
        }

        public function get_all_linked_terms($count = false)
        {
            global $wpdb;
        
            $table_name = TaxoPress_Linked_Terms_Schema::tableName();
        
            $search = (!empty($_REQUEST['s'])) ? '%' . $wpdb->esc_like(sanitize_text_field($_REQUEST['s'])) . '%' : '';
        
            $orderby = (!empty($_REQUEST['orderby'])) ? sanitize_text_field($_REQUEST['orderby']) : 'id';
            $order = (!empty($_REQUEST['order'])) ? sanitize_text_field($_REQUEST['order']) : 'desc';
        
            $items_per_page = $this->get_items_per_page('st_linked_terms_per_page', 20);
            $page = $this->get_pagenum();
            $offset = ($page - 1) * $items_per_page;
        
            $selected_taxonomy = (!empty($_REQUEST['terms_filter_taxonomy'])) ? sanitize_text_field($_REQUEST['terms_filter_taxonomy']) : '';
        
            if ($count) {
                $query = "SELECT COUNT(*) FROM $table_name WHERE 1 = 1";
            } else {
                $query = "SELECT * FROM $table_name WHERE 1 = 1";
            }

            $placeholders = [];
        
            if (!empty($selected_taxonomy)) {
                $query .= " AND (term_taxonomy = %s OR linked_term_taxonomy = %s)";
                $placeholders[] = $selected_taxonomy;
                $placeholders[] = $selected_taxonomy;
            }
            if (!empty($search)) {
                $query .= " AND (term_name LIKE %s OR linked_term_name LIKE %s)";
                $placeholders[] = $search;
                $placeholders[] = $search;
            }
        
            $query .= " ORDER BY {$orderby} {$order}";
        
            if (!$count) {
                $query .= " LIMIT %d, %d";
                $placeholders[] = $offset;
                $placeholders[] = $items_per_page;
            }
        
            $prepared_query = !empty($placeholders) ? $wpdb->prepare($query, $placeholders) : $query;
        
            if ($count) {
                $linked_terms = $wpdb->get_var($prepared_query);
            } else {
                $linked_terms = $wpdb->get_results($prepared_query);
            }

        
            return $linked_terms;
        }        

        /**
         * Retrieve st_Terms data from the database
         *
         * @param int $per_page
         * @param int $page_number
         *
         * @return mixed
         */
        public function get_st_Terms()
        {
            return $this->get_all_linked_terms();
        }

        /**
         * Returns the count of records in the database.
         *
         * @return null|string
         */
        public function record_count()
        {
            return $this->get_all_linked_terms(true);
        }

        /**
         * Show single row item
         *
         * @param array $item
         */
        public function single_row($item)
        {
            $class = ['st-linked-terms-tr'];
            $id    = 'linked-term-' . $item->id . '';
            echo sprintf('<tr id="%s" class="%s">', esc_attr($id), esc_attr(implode(' ', $class)));
            $this->single_row_columns($item);
            echo '</tr>';
        }

        /**
         *  Associative array of columns
         *
         * @return array
         */
        function get_columns()
        {
            $columns = [
                'cb'                        => '<input type="checkbox" />',
                'term_name'                 => esc_html__('Primary Term Name', 'taxopress-pro'),
                'linked_term_name'          => esc_html__('Secondary Term Name', 'taxopress-pro'),
                'term_taxonomy'             => esc_html__('Primary Term Taxonomy', 'taxopress-pro'),
                'linked_term_taxonomy'      => esc_html__('Secondary Term Taxonomy', 'taxopress-pro'),
                'term_counts'               => esc_html__('Primary Term Post Count', 'taxopress-pro'),
                'linked_term_counts'        => esc_html__('Secondary Term Post Count', 'taxopress-pro')
            ];

            return $columns;
        }

        /**
         * Columns to make sortable.
         *
         * @return array
         */
        protected function get_sortable_columns()
        {
            $sortable_columns = [
                'term_name'            => ['term_name', true],
                'linked_term_name'     => ['linked_term_name', true],
                'term_taxonomy'        => ['term_taxonomy', true],
                'linked_term_taxonomy' => ['linked_term_taxonomy', true],
            ];

            return $sortable_columns;
        }

        /**
         * Render the bulk edit checkbox
         *
         * @param array $item
         *
         * @return string
         */
        function column_cb($item)
        {
            return sprintf('<input type="checkbox" name="%1$s[]" value="%2$s" />', 'taxopress_linked_terms', $item->id);
        }

        /**
         * Get the bulk actions to show in the top page dropdown
         *
         * @return array
         */
        protected function get_bulk_actions()
        {
            $actions = [
                'taxopress-linked-terms-add-linked-terms' => esc_html__('Add primary term to posts with secondary term', 'taxopress-pro'),
                'taxopress-linked-terms-add-terms' => esc_html__('Add secondary term to posts with primary term', 'taxopress-pro'),
                'taxopress-linked-terms-delete-relationship' => esc_html__('Delete Relationship', 'taxopress-pro')
            ];

            return $actions;
        }

        /**
         * Add custom filter to tablenav
         *
         * @param string $which
         */
        protected function extra_tablenav($which)
        {

            if ('top' === $which) {
                $taxonomies = get_all_taxopress_taxonomies_request();
                 $selected_taxonomy = (!empty($_REQUEST['terms_filter_taxonomy'])) ? sanitize_text_field($_REQUEST['terms_filter_taxonomy']) : '';
            ?>


                <div class="alignleft actions autoterms-terms-table-filter">

                    <select class="auto-terms-terms-filter-select" name="terms_filter_select_taxonomy" id="terms_filter_select_taxonomy">
                        <option value=""><?php esc_html_e('Taxonomy', 'taxopress-pro'); ?></option>
                        <?php
                        foreach ($taxonomies as $taxonomy) {
                            echo '<option value="' . esc_attr($taxonomy->name) . '" ' . selected($selected_taxonomy, $taxonomy->name, false) . '>' . esc_html($taxonomy->labels->name) . '</option>';
                        }
                        ?>
                    </select>

                    <a href="javascript:void(0)" class="taxopress-terms-tablenav-filter button"><?php esc_html_e('Filter', 'taxopress-pro'); ?></a>

                </div>
            <?php
            }
        }

        /**
         * Process bulk actions
         */
        public function process_bulk_action()
        {
            global $wpdb;

            $table_name = TaxoPress_Linked_Terms_Schema::tableName();

            $query_arg = '_wpnonce';
            $action = 'bulk-' . $this->_args['plural'];
            $checked = isset($_REQUEST[$query_arg]) ? wp_verify_nonce(sanitize_key($_REQUEST[$query_arg]), $action) : false;

            if (!$checked || !current_user_can('simple_tags') || empty($_REQUEST['taxopress_linked_terms'])) {
                return;
            }
            $taxopress_linked_terms = array_map('sanitize_text_field', (array)$_REQUEST['taxopress_linked_terms']);

            $action_acount = 0;
            $action_message = '';
            $message_sucess = false;
            foreach ($taxopress_linked_terms as $taxopress_linked_term) {
                if ($this->current_action() === 'taxopress-linked-terms-delete-relationship') {
                    $delete =  $wpdb->query($wpdb->prepare(
                        "DELETE FROM $table_name WHERE id = %d",
                        $taxopress_linked_term
                    ));
                    if ($delete) {
                        $action_acount++;
                    }
                    if ($action_acount === 0) {
                        $action_message = esc_html__('Error deleting linked term relationship.', 'taxopress-pro');
                        $message_sucess = false;
                    } elseif ($action_acount > 1) {
                        $action_message = esc_html__('Linked terms deleted successfully.', 'taxopress-pro');
                        $message_sucess = true;
                    } else {
                        $action_message = esc_html__('Linked term deleted successfully.', 'taxopress-pro');
                        $message_sucess = true;
                    }
                } elseif(in_array($this->current_action(), ['taxopress-linked-terms-add-linked-terms', 'taxopress-linked-terms-add-terms'])) {
                    $linked_term_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $taxopress_linked_term));
                    if (is_object($linked_term_data) && isset($linked_term_data->id)) {
                        if ($this->current_action() == 'taxopress-linked-terms-add-linked-terms') {
                            //Add main term to posts with Linked Term
                            $not_linked_posts = $this->get_linked_terms_relationship_non_linked_posts(
                                $linked_term_data->linked_term_taxonomy,
                                $linked_term_data->linked_term_id, 
                                $linked_term_data->term_taxonomy,
                                $linked_term_data->term_id
                            );
                            $new_term_name      = $linked_term_data->term_name;
                            $new_term_taxonomy  = $linked_term_data->term_taxonomy;
                        } else {
                            //Add Linked Term to post with main term
                            $not_linked_posts = $this->get_linked_terms_relationship_non_linked_posts(
                                $linked_term_data->term_taxonomy, 
                                $linked_term_data->term_id, 
                                $linked_term_data->linked_term_taxonomy,
                                $linked_term_data->linked_term_id
                            );
                            $new_term_name      = $linked_term_data->linked_term_name;
                            $new_term_taxonomy  = $linked_term_data->linked_term_taxonomy;
                        }
                        if (!empty($not_linked_posts)) {
                            foreach ($not_linked_posts as $not_linked_post) {
                                wp_set_object_terms($not_linked_post, [$new_term_name], $new_term_taxonomy, true);
                            }
                            $action_acount = $action_acount + count($not_linked_posts);
                            $action_message = sprintf(esc_html__('%d posts updated successfully.', 'taxopress-pro'), $action_acount);
                            $message_sucess = true;
                        } elseif(empty($action_message)) {
                            $action_message = esc_html__('0 post update.', 'taxopress-pro');
                            $message_sucess = false;
                        }
                    }
                }
            }

            if (!empty($action_message)) {
                echo taxopress_admin_notices_helper($action_message, $message_sucess); 
            }
            
        }

        public function get_linked_terms_relationship_non_linked_posts($term_taxonomy, $term_id, $not_in_term_taxonomy, $not_in_term_id) {
            $args = array(
                'post_type'      => array_keys(get_post_types(array('public' => true), 'names')),
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'tax_query'      => array(
                    'relation' => 'AND',
                    array(
                        'taxonomy' => $term_taxonomy,
                        'field'    => 'id',
                        'terms'    => $term_id,
                    ),
                    array(
                        'taxonomy' => $not_in_term_taxonomy,
                        'field'    => 'id',
                        'terms'    => $not_in_term_id,
                        'operator' => 'NOT IN',
                    ),
                ),
            );
        
            $post_ids = get_posts($args);
        
            return $post_ids;
        }
        

        /**
         * Render a column when no column specific method exist.
         *
         * @param array $item
         * @param string $column_name
         *
         * @return mixed
         */
        public function column_default($item, $column_name)
        {
            return !empty($item->$column_name) ? $item->$column_name : '&mdash;';
        }

        /** Text displayed when no stterm data is available */
        public function no_items()
        {
            esc_html_e('No linked terms found.', 'taxopress-pro');
        }

        /**
         * Displays the search box.
         *
         * @param string $text The 'submit' button label.
         * @param string $input_id ID attribute value for the search input field.
         *
         *
         */
        public function search_box($text, $input_id)
        {
            if (empty($_REQUEST['s']) && !$this->has_items()) {
                //return;
            }

            $input_id = $input_id . '-search-input';

            if (!empty($_REQUEST['orderby'])) {
                echo '<input type="hidden" name="orderby" value="' . esc_attr(sanitize_text_field($_REQUEST['orderby'])) . '" />';
            }
            if (!empty($_REQUEST['order'])) {
                echo '<input type="hidden" name="order" value="' . esc_attr(sanitize_text_field($_REQUEST['order'])) . '" />';
            }
            if (!empty($_REQUEST['page'])) {
                echo '<input type="hidden" name="page" value="' . esc_attr(sanitize_text_field($_REQUEST['page'])) . '" />';
            }

            $custom_filters = ['terms_filter_taxonomy'];

            foreach ($custom_filters as  $custom_filter) {
                $filter_value = !empty($_REQUEST[$custom_filter]) ? sanitize_text_field($_REQUEST[$custom_filter]) : '';
                echo '<input type="hidden" name="' . esc_attr($custom_filter) . '" value="' . esc_attr($filter_value) . '" />';
            }
            ?>
            <p class="search-box">
                <label class="screen-reader-text" for="<?php echo esc_attr($input_id); ?>"><?php echo esc_html($text); ?>:</label>
                <input type="search" id="<?php echo esc_attr($input_id); ?>" name="s" value="<?php _admin_search_query(); ?>" />
                <?php submit_button($text, '', '', false, ['id' => 'taxopress-terms-search-submit']); ?>
            </p>
        <?php
        }

        /**
         * Sets up the items (roles) to list.
         */
        public function prepare_items()
        {

            $this->_column_headers = $this->get_column_info();
            $this->process_bulk_action();

            /**
             * First, lets decide how many records per page to show
             */
            $per_page = $this->get_items_per_page('st_linked_terms_per_page', 20);

            /**
             * Fetch the data
             */
            $data = $this->get_st_Terms();

            /**
             * Pagination.
             */
            $current_page = $this->get_pagenum();
            $total_items  = $this->record_count();

            /**
             * Now we can add the data to the items property, where it can be used by the rest of the class.
             */
            $this->items = $data;

            /**
             * We also have to register our pagination options & calculations.
             */
            $this->set_pagination_args([
                'total_items' => $total_items,                      //calculate the total number of items
                'per_page'    => $per_page,                         //determine how many items to show on a page
                'total_pages' => ceil($total_items / $per_page)   //calculate the total number of pages
            ]);
        }

        /**
         * Generates and display row actions links for the list table.
         *
         * @param object $item The item being acted upon.
         * @param string $column_name Current column name.
         * @param string $primary Primary column name.
         *
         * @return string The row actions HTML, or an empty string if the current column is the primary column.
         */
        protected function handle_row_actions($item, $column_name, $primary)
        {
            //Build row actions
            $actions = [];

            if (current_user_can('edit_term', $item->term_id)) {

                $actions['add_linked_term_post'] = sprintf(
                    '<a href="%s">%s</a>',
                    add_query_arg(
                        [
                            'page'                   => 'st_linked_terms',
                            'action'                 => 'taxopress-linked-terms-add-linked-terms',
                            'taxopress_linked_terms' => esc_attr($item->id),
                            '_wpnonce'               => wp_create_nonce('bulk-' . $this->_args['plural'])
                        ],
                        admin_url('admin.php')
                    ),
                    esc_html__('Add primary term to posts with secondary term', 'taxopress-pro')
                );

                $actions['add_term_post'] = sprintf(
                    '<a href="%s">%s</a>',
                    add_query_arg(
                        [
                            'page'                   => 'st_linked_terms',
                            'action'                 => 'taxopress-linked-terms-add-terms',
                            'taxopress_linked_terms' => esc_attr($item->id),
                            '_wpnonce'               => wp_create_nonce('bulk-' . $this->_args['plural'])
                        ],
                        admin_url('admin.php')
                    ),
                    esc_html__('Add secondary term to posts with primary term', 'taxopress-pro')
                );

                $actions['delete'] = sprintf(
                    '<a href="%s" class="delete-terms">%s</a>',
                    add_query_arg(
                        [
                            'page'                   => 'st_linked_terms',
                            'action'                 => 'taxopress-linked-terms-delete-relationship',
                            'taxopress_linked_terms' => esc_attr($item->id),
                            '_wpnonce'               => wp_create_nonce('bulk-' . $this->_args['plural'])
                        ],
                        admin_url('admin.php')
                    ),
                    esc_html__('Delete Relationship', 'taxopress-pro')
                );
            }

            return $column_name === $primary ? $this->row_actions($actions, false) : '';
        }

        /**
         * Method for term_name column
         *
         * @param array $item
         *
         * @return string
         */
        protected function column_term_name($item)
        {

            $title = sprintf(
                '<a href="%1$s"><strong><span class="row-title">%2$s</span></strong></a>',
                add_query_arg(
                    [
                        'taxonomy' => $item->term_taxonomy,
                        'tag_ID' => $item->term_id,
                    ],
                    admin_url('term.php')
                ),
                esc_html($item->term_name)
            );

            return $title;
        }

        /**
         * Method for linked_term_name column
         *
         * @param array $item
         *
         * @return string
         */
        protected function column_linked_term_name($item)
        {

            $title = sprintf(
                '<a href="%1$s"><strong><span class="row-title">%2$s</span></strong></a>',
                add_query_arg(
                    [
                        'taxonomy' => $item->linked_term_taxonomy,
                        'tag_ID' => $item->linked_term_id,
                    ],
                    admin_url('term.php')
                ),
                esc_html($item->linked_term_name)
            );

            return $title;
        }

        /**
         * Method for term_taxonomy column
         *
         * @param array $item
         *
         * @return string
         */
        protected function column_term_taxonomy($item)
        {
            $taxonomy = get_taxonomy($item->term_taxonomy);

            if ($taxonomy) {
                $return = sprintf(
                    '<a href="%1$s">%2$s</a>',
                    add_query_arg(
                        [
                            'page' => 'st_taxonomies',
                            'add' => 'taxonomy',
                            'action' => 'edit',
                            'taxopress_taxonomy' => $taxonomy->name,
                        ],
                        taxopress_admin_url('admin.php')
                    ),
                    esc_html($taxonomy->labels->name)
                );
            } else {
                $return = $item->term_taxonomy;
            }

            return $return;
        }

        /**
         * Method for linked_term_taxonomy column
         *
         * @param array $item
         *
         * @return string
         */
        protected function column_linked_term_taxonomy($item)
        {
            $taxonomy = get_taxonomy($item->linked_term_taxonomy);

            if ($taxonomy) {
                $return = sprintf(
                    '<a href="%1$s">%2$s</a>',
                    add_query_arg(
                        [
                            'page' => 'st_taxonomies',
                            'add' => 'taxonomy',
                            'action' => 'edit',
                            'taxopress_taxonomy' => $taxonomy->name,
                        ],
                        taxopress_admin_url('admin.php')
                    ),
                    esc_html($taxonomy->labels->name)
                );
            } else {
                $return = $item->linked_term_taxonomy;
            }

            return $return;
        }

        /**
         * The term_counts column
         *
         * @param $item
         *
         * @return string
         */
        protected function column_term_counts($item)
        {
            $term_counts = $this->count_posts_by_term($item->term_id, $item->term_taxonomy);

            return sprintf(
                '<a href="%s" class="">%s</a>',
                add_query_arg(
                    [
                        'page' => 'st_posts',
                        'posts_term_filter' => (int) $item->term_id,
                    ],
                    admin_url('admin.php')
                ),
                number_format_i18n($term_counts)
            );
        }

        /**
         * The linked_term_counts column
         *
         * @param $item
         *
         * @return string
         */
        protected function column_linked_term_counts($item)
        {
            $term_counts = $this->count_posts_by_term($item->linked_term_id, $item->linked_term_taxonomy);

            return sprintf(
                '<a href="%s" class="">%s</a>',
                add_query_arg(
                    [
                        'page' => 'st_posts',
                        'posts_term_filter' => (int) $item->linked_term_id,
                    ],
                    admin_url('admin.php')
                ),
                number_format_i18n($term_counts)
            );
        }

        protected function count_posts_by_term($term_id, $taxonomy) {
            
            $args = array(
                'post_type' => array_keys(get_post_types(array('public' => true), 'names')),
                'post_status' => 'any',
                'posts_per_page' => 1,
                'tax_query' => array(
                    'relation' => 'AND',
                    array(
                        'taxonomy' => $taxonomy,
                        'field' => 'id',
                        'terms' => $term_id,
                    ),
                ),
            );
        
            $term_count = new WP_Query($args);

            if ($term_count->have_posts()) {
                return $term_count->found_posts;
            } else {
                return 0;
            }
        }

    }
}