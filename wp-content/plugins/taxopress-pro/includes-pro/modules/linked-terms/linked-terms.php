<?php
require_once plugin_dir_path(__FILE__) . 'linked-terms-schema.php';
require_once plugin_dir_path(__FILE__) . 'linked-terms-table.php';

if (!class_exists('TaxoPress_Linked_Terms')) {
    /**
     * class TaxoPress_Linked_Terms
     */
    class TaxoPress_Linked_Terms
    {
        const MENU_SLUG = 'st_options';

        // class instance
        static $instance;

        // WP_List_Table object
        public $terms_table;

        /**
         * Construct the TaxoPress_Linked_Terms class
         */
        public function __construct()
        {
            add_action('admin_init', [$this, 'run_installer_task']);

            add_action('admin_init', function () {
                $linked_terms_taxonomies = SimpleTags_Plugin::get_option_value('linked_terms_taxonomies');
                if (is_array($linked_terms_taxonomies)) {
                    foreach ($linked_terms_taxonomies as $taxonomy) {
                        add_action($taxonomy . '_add_form_fields', [$this, 'add_term_fields']);
                        add_action($taxonomy . '_edit_form_fields', [$this, 'edit_term_fields'], 10, 2);
                        add_action('created_' . $taxonomy, [$this, 'save_term_fields']);
                        add_action('edited_' . $taxonomy, [$this, 'save_term_fields']);
                        add_action('delete_' . $taxonomy, [$this, 'delete_linked_term_relation'], 10, 3);
                    }
                }
            }, 19);

            // Add linked term to post
            add_action('save_post', [$this, 'add_linked_term_to_post'], 100, 2);

            add_filter('set-screen-option', [$this, 'set_screen'], 10, 3);
            // Admin menu
            add_action('admin_menu', [$this, 'admin_menu']);

            add_filter('removable_query_args', [$this, 'remove_args']);

        }

        /** Singleton instance */
        public static function get_instance()
        {
            if (!isset(self::$instance)) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * Run installer task
         */
        public function run_installer_task() {
            if (!get_option('taxopress_linked_terms_table_installed')) {
                // create linked terms table if not exist
                TaxoPress_Linked_Terms_Schema::createTableIfNotExists();
                // run migration for legacy linked terms
                self::migrate_legacy_linked_terms();
                update_option('taxopress_linked_terms_table_installed', true);
           }
        }

        public function remove_args ($args) {
            if (isset($_GET['page']) && $_GET['page'] === 'st_linked_terms') {
                $args = array_merge($args, [
                    'action',
                    'taxopress_linked_terms',
                    '_wpnonce'
                ]);
            }
            return $args;
        }

        public function set_screen($status, $option, $value)
        {
            return $value;
        }

        /**
         * Screen options
         */
        public function screen_option()
        {
    
            $option = 'per_page';
            $args   = [
                'label'   => esc_html__('Number of items per page', 'taxopress-pro'),
                'default' => 20,
                'option'  => 'st_linked_terms_per_page'
            ];
    
            add_screen_option($option, $args);
    
            $this->terms_table = new Taxopress_Linked_Terms_List();
        }

        /**
         * Add WP admin menu for Tags
         *
         * @return void
         */
        public function admin_menu()
        {
            $hook = add_submenu_page(
                self::MENU_SLUG,
                esc_html__('Linked Terms', 'taxopress-pro'),
                esc_html__('Linked Terms', 'taxopress-pro'),
                'simple_tags',
                'st_linked_terms',
                [
                    $this,
                    'page_manage_linked_terms',
                ]
            );
    
            add_action("load-$hook", [$this, 'screen_option']);
        }

        /**
         * Method for build the page HTML manage tags
         *
         * @return void
         * @author Olatechpro
         */
        public function page_manage_linked_terms()
        {
            // Default order
            if (!isset($_GET['order'])) {
                $_GET['order'] = 'name-asc';
            }
    
            settings_errors(__CLASS__);
        ?>
            <div class="wrap st_wrap st-manage-taxonomies-page manage-taxopress-linked-terms">
    
                <div id="">
                    <h1 class="wp-heading-inline"><?php esc_html_e('Linked Terms', 'taxopress-pro'); ?></h1>
                    <div class="taxopress-description">
                        <?php esc_html_e('This feature allows you to connect terms. When the primary or secondary term is added to a post, the other term can be added also.', 'taxopress-pro'); ?>
                    </div>
    
                    <?php
                    if (isset($_REQUEST['s']) && $search = esc_attr(sanitize_text_field(wp_unslash($_REQUEST['s'])))) {
                        /* translators: %s: search keywords */
                        printf(' <span class="subtitle">' . esc_html__(
                            'Search results for &#8220;%s&#8221;',
                            'taxopress-pro'
                        ) . '</span>', esc_html($search));
                    }
                    ?>
                    <?php
    
                    //the terms table instance
                    $this->terms_table->prepare_items();
                    ?>
    
    
                    <hr class="wp-header-end">
                    <div id="ajax-response"></div>
                    <form class="search-form wp-clearfix st-taxonomies-search-form" method="get">
                        <?php $this->terms_table->search_box(esc_html__('Search Terms', 'taxopress-pro'), 'term'); ?>
                    </form>
                    <div class="clear"></div>
    
                    <div id="col-container" class="wp-clearfix">
    
                        <div class="col-wrap">
                            <form action="<?php echo esc_url(add_query_arg('', '')); ?>" method="post">
                                <?php $this->terms_table->display(); //Display the table 
                                ?>
                            </form>
                            <div class="form-wrap edit-term-notes">
                                <p><?php esc_html__('Description here.', 'taxopress-pro') ?></p>
                            </div>
                        </div>
    
    
                    </div>
    
    
                </div>
                <?php SimpleTags_Admin::printAdminFooter(); ?>
            </div>
    <?php
    }

        /**
         * Run migration for legacy linked terms
         */
        public static function migrate_legacy_linked_terms() {
            global $wpdb;

            $query = "
                SELECT t.*, tt.*, tm.meta_value
                FROM $wpdb->terms AS t
                INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id
                INNER JOIN $wpdb->termmeta AS tm ON t.term_id = tm.term_id
                WHERE tm.meta_key = '_taxopress_linked_terms'
            ";

            $terms = $wpdb->get_results($query);

            if ($terms && !empty($terms)) {
                foreach ($terms as $term) {
                    $linked_terms = maybe_unserialize($term->meta_value);
                    if (is_array($linked_terms) && !empty($linked_terms)) {

                        foreach ($linked_terms as $linked_term) {
                            $linked_term_details = get_term_by('name', $linked_term, $term->taxonomy);
                            if (is_object($linked_term_details) && isset($linked_term_details->term_id)) {
                                self::addLinkedTermsRelation($term->term_id, $term->name, $term->taxonomy, $linked_term_details->term_id, $linked_term_details->name, $linked_term_details->taxonomy);
                            }
                        }

                    }
                    // delete relation either it's empty or not after migration
                    delete_term_meta($term->term_id, '_taxopress_linked_terms');
                }
            }

        }

        /**
         * Add linked terms relation
         *
         * @param int $term_id
         * @param string $term_name
         * @param string $term_taxonomy
         * @param int $linked_term_id
         * @param string $linked_term_name
         * @param string $linked_term_taxonomy
         *
         * @return integer|bool
         */
        public static function addLinkedTermsRelation($term_id, $term_name, $term_taxonomy, $linked_term_id, $linked_term_name, $linked_term_taxonomy) {
            global $wpdb;

            $table_name = TaxoPress_Linked_Terms_Schema::tableName();

            $existing_entry = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM $table_name WHERE (term_id = %d AND linked_term_id = %d) OR (term_id = %d AND linked_term_id = %d)",
                    $term_id,
                    $linked_term_id,
                    $linked_term_id,
                    $term_id
                )
            );

            if ($existing_entry) {
                return $existing_entry->id;
            } else {
                $inserted = $wpdb->insert(
                    $table_name,
                    [
                        'term_id'               => $term_id,
                        'linked_term_id'        => $linked_term_id,
                        'term_name'             => $term_name,
                        'linked_term_name'      => $linked_term_name,
                        'term_taxonomy'         => $term_taxonomy,
                        'linked_term_taxonomy'  => $linked_term_taxonomy,
                    ],
                    [
                        '%d',
                        '%d',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                    ]
                );
            
                if ($inserted) {
                    return $wpdb->insert_id;
                } else {
                    return false;
                }
            }
        }


        /**
         * Delete linked terms relation
         *
         * @param int $term_id
         * @param mixed $linked_term_id
         *
         * @return integer|bool
         */
        public function deleteLinkedTermsRelation($term_id, $linked_term_id = false) {
            global $wpdb;

            $table_name = TaxoPress_Linked_Terms_Schema::tableName();

            if (!$linked_term_id) {
                $query = $wpdb->prepare(
                    "DELETE FROM $table_name WHERE term_id = %d OR linked_term_id = %d",
                    $term_id,
                    $term_id
                );
            } elseif($linked_term_id) {
                $query = $wpdb->prepare(
                    "DELETE FROM $table_name WHERE (term_id = %d AND linked_term_id = %d) OR (term_id = %d AND linked_term_id = %d)",
                    $term_id,
                    $linked_term_id,
                    $linked_term_id,
                    $term_id
                );
            }

            return $wpdb->query($query);
        }

        /**
         * Linked terms assets
         */
        public function taxopress_load_taxonomy_linked_terms_assets()
        {
            wp_enqueue_style(
                'taxopress-linked-terms-css',
                plugins_url('', __FILE__) . '/assets/css/taxonomy-linked-terms.css',
                [],
                STAGS_VERSION,
                'all'
            );
            wp_enqueue_script(
                'taxopress-linked-terms-js',
                plugins_url('', __FILE__) . '/assets/js/taxonomy-linked-terms.js',
                ['jquery', 'jquery-ui-sortable', 'jquery-ui-autocomplete'],
                STAGS_VERSION
            );

            wp_localize_script(
                'taxopress-linked-terms-js',
                'linkedTermsRequestAction',
                array(
                    'taxonomy' => !empty($_GET['taxonomy']) ? sanitize_key($_GET['taxonomy']) : 'post_tag',
                    'term_id'  => !empty($_GET['tag_ID']) ? sanitize_key($_GET['tag_ID']) : 0,
                )
            );
        }

        public function add_term_fields($taxonomy)
        {
            wp_nonce_field('taxopress_linked_terms', 'taxopress_linked_terms_nonce');
?>
            <div class="form-field">
                <label for="text"><?php esc_html_e('Linked Terms', 'taxopress-pro'); ?></label>
                <input type="text" class="taxopress-linked-terms-input term-linked-terms linked-term-autocomplete-input" placeholder="<?php esc_attr_e('Start typing to choose existing terms.', 'taxopress-pro'); ?>" />
                <p><?php esc_html_e('These terms are linked to the main term. When the primary or secondary term is added to a post, the other term can be added also.', 'taxopress-pro'); ?></p>
                <ul class="taxopress-term-linked-terms wrapper"></ul>
            </div>
        <?php
            $this->taxopress_load_taxonomy_linked_terms_assets();
        }

        /**
         * Add linked term field when terms are been edited
         */
        public function edit_term_fields($term, $taxonomy)
        {
            wp_nonce_field('taxopress_linked_terms', 'taxopress_linked_terms_nonce');

            $linked_terms = taxopress_get_linked_terms($term->term_id);
            $existing_linked_term_ids = [];
        ?><tr class="form-field">
                <th>
                    <label for="text"><?php esc_html_e('Linked Terms', 'taxopress-pro'); ?></label>
                </th>
                <td>
                    <input type="text" class="taxopress-linked-terms-input term-linked-terms linked-term-autocomplete-input" placeholder="<?php esc_attr_e('Start typing to choose existing terms.', 'taxopress-pro'); ?>" />
                    <p><?php esc_html_e('These terms are linked to the main term. When the primary or secondary term is added to a post, the other term can be added also.', 'taxopress-pro'); ?></p>
                    <ul class="taxopress-term-linked-terms wrapper">
                        <?php if (!empty($linked_terms)) : ?>
                            <?php foreach ($linked_terms as $linked_term_option) : 
                            $linked_term_tag = ((int)$linked_term_option->term_id === (int)$term->term_id) ? '2' : '1';
                            $actual_linked_term_data = taxopress_get_linked_term_data($linked_term_option, $term->term_id);
                            
                            $taxopress_linked_term_id       = $actual_linked_term_data->term_id;
                            $taxopress_linked_term_name     = $actual_linked_term_data->term_name;
                            $taxopress_linked_term_taxonomy = $actual_linked_term_data->term_taxonomy;

                            $existing_linked_term_ids[] = $taxopress_linked_term_id;
                                ?>
                                <li class="taxopress-term-li <?php echo esc_attr($taxopress_linked_term_taxonomy); ?>-<?php echo esc_attr($taxopress_linked_term_id); ?>" data-position="<?php echo esc_attr($linked_term_tag); ?>">
                                    <span class="display-text"><?php echo esc_html($taxopress_linked_term_name); ?> (<?php echo esc_html($taxopress_linked_term_taxonomy); ?>)</span>
                                    <span class="remove-linked_term">
                                        <span class="dashicons dashicons-no-alt"></span>
                                    </span>
                                    <input type="hidden" class="term-linked-terms id" name="taxopress_linked_term_id[]" value="<?php echo esc_attr($taxopress_linked_term_id); ?>">
                                    <input type="hidden" class="term-linked-terms name" name="taxopress_linked_term_name[]" value="<?php echo esc_attr($taxopress_linked_term_name); ?>">
                                    <input type="hidden" class="term-linked-terms taxonomy" name="taxopress_linked_term_taxonomy[]" value="<?php echo esc_attr($taxopress_linked_term_taxonomy); ?>">
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                    <?php foreach ($existing_linked_term_ids as $existing_linked_term_id) : ?>
                        <input type="hidden" name="taxopress_existing_linked_term_id[]" value="<?php echo esc_attr($existing_linked_term_id); ?>">
                    <?php endforeach; ?>
                    <?php if (!empty($linked_terms)) : ?>
                        <p class="taxopress-term-position-msg">
                            <?php 
                            printf(
                                /* translators: %1$s and %2$s are superscript numbers. */
                                __('The superscript %1$s indicates the linked term being the primary term, while %2$s is shown when the linked term is the secondary term. You can delete the relationship and re-add it from your preferred term edit screen to make them the primary term.', 'taxopress-pro'),
                                '<strong>1</strong>',
                                '<strong>2</strong>'
                            ); 
                            ?>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
<?php
            $this->taxopress_load_taxonomy_linked_terms_assets();
        }

        public function save_term_fields($term_id)
        {

            if (!isset($_POST['taxopress_linked_terms_nonce']) || !wp_verify_nonce(sanitize_key($_POST['taxopress_linked_terms_nonce']), 'taxopress_linked_terms')) {
                return;
            }

            if (!empty($_POST['taxopress_linked_term_id'])) {
                $taxopress_linked_term_id       = array_map('sanitize_text_field', $_POST['taxopress_linked_term_id']);
                $taxopress_linked_term_name     = array_map('sanitize_text_field', $_POST['taxopress_linked_term_name']);
                $taxopress_linked_term_taxonomy = array_map('sanitize_text_field', $_POST['taxopress_linked_term_taxonomy']);
                $existing_linked_term_ids       = [0];
                if (!empty($_POST['taxopress_existing_linked_term_id'])) {
                    // delete removed linked term
                    $existing_linked_term_ids = array_map('sanitize_text_field', $_POST['taxopress_existing_linked_term_id']);

                    $old_removed_relations = array_diff($existing_linked_term_ids, $taxopress_linked_term_id);
                    if (!empty($old_removed_relations)) {
                        foreach ($old_removed_relations as $old_removed_relation) {
                            self::deleteLinkedTermsRelation($term_id, $old_removed_relation);
                        }
                    }
                }

                // add new linked terms
                $current_term           = get_term($term_id);
                foreach ($taxopress_linked_term_id as $index => $linked_term_id) {
                    if (!in_array($linked_term_id, $existing_linked_term_ids)) {
                        $linked_term_name = $taxopress_linked_term_name[$index];
                        $linked_term_taxonomy = $taxopress_linked_term_taxonomy[$index];
                        self::addLinkedTermsRelation($current_term->term_id, $current_term->name, $current_term->taxonomy, $linked_term_id, $linked_term_name, $linked_term_taxonomy);
                    }
                }

            } elseif (!empty($_POST['taxopress_existing_linked_term_id'])) {
                // all linked terms are removed
                self::deleteLinkedTermsRelation($term_id);
            }
        }

        /**
         * Delete linked term relation when a term id deleted
         * @param int     $term         Term ID.
         * @param int     $tt_id        Term taxonomy ID.
         * @param WP_Term $deleted_term Copy of the already-deleted term.
         */
         public function delete_linked_term_relation($term, $tt_id, $deleted_term) {
            self::deleteLinkedTermsRelation($deleted_term->term_id);
         }

        /**
         * Add post linked terms
         *
         * @param integer $post_id
         * @param object $post
         * @return void
         */
        public function add_linked_term_to_post($post_id, $post) {
            // Check if the post is being updated
            if (wp_is_post_revision($post_id)) {
                return;
            }
    
            // Get all taxonomies associated with the post
            $taxonomies = get_object_taxonomies($post);

            foreach ($taxonomies as $taxonomy) {
                // Get all terms for the current taxonomy
                $terms = get_the_terms($post_id, $taxonomy);
                
                if (!empty($terms)) {
                    foreach ($terms as $term) {
                        // Add linked term
                        $linked_terms_type = SimpleTags_Plugin::get_option_value('linked_terms_type');
                        $linked_terms = taxopress_get_linked_terms($term->term_id, '', false, $linked_terms_type);
                        if (!empty($linked_terms)) {
                            foreach($linked_terms as $linked_term) {
                                $linked_term_data = taxopress_get_linked_term_data($linked_term, $term->term_id);
                                if (in_array($linked_term_data->term_taxonomy, $taxonomies)) {
                                    wp_set_object_terms($post_id, [$linked_term_data->term_name], $linked_term_data->term_taxonomy, true);
                                }
                            }
                        }
                    }
                }
            }
        }

    }
}
