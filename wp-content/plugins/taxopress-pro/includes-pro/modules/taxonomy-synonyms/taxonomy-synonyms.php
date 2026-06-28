<?php

if (!class_exists('TaxoPress_Taxonomy_Synonyms')) {
    /**
     * class TaxoPress_Taxonomy_Synonyms
     */
    class TaxoPress_Taxonomy_Synonyms
    {

        // class instance
        static $instance;

        const TERM_SYNONYMS_FIELD = '_taxopress_term_synonyms';

        /**
         * Construct the TaxoPress_Taxonomy_Synonyms class
         */
        public function __construct()
        {


            add_action('admin_init', function () {
                $synonyms_taxonomies = SimpleTags_Plugin::get_option_value('synonyms_taxonomies');
                if (is_array($synonyms_taxonomies)) {
                    foreach ($synonyms_taxonomies as $taxonomy) {
                        add_action($taxonomy . '_add_form_fields', [$this, 'add_term_fields']);
                        add_action($taxonomy . '_edit_form_fields', [$this, 'edit_term_fields'], 10, 2);
                        add_action('created_' . $taxonomy, [$this, 'save_term_fields']);
                        add_action('edited_' . $taxonomy, [$this, 'save_term_fields']);
                    }
                }
            }, 19);

            add_filter('pre_insert_term', [$this, 'filter_pre_insert_term'], 10, 2);

            add_action('wp_ajax_duplicate_synonyms_validation', [$this, 'handle_duplicate_synonyms_validation']);
        }


        /** Singleton instance */
        public static function get_instance()
        {
            if (!isset(self::$instance)) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        public function taxopress_load_taxonomy_synonyms_assets()
        {
            wp_enqueue_style(
                'taxopress-synonyms-css',
                plugins_url('', __FILE__) . '/assets/css/taxonomy-synonyms.css',
                [],
                STAGS_VERSION,
                'all'
            );
            wp_enqueue_script(
                'taxopress-synonyms-js',
                plugins_url('', __FILE__) . '/assets/js/taxonomy-synonyms.js',
                ['jquery', 'jquery-ui-sortable'],
                STAGS_VERSION
            );

            wp_localize_script(
                'taxopress-synonyms-js',
                'synonymsRequestAction',
                array(
                    'nonce' => wp_create_nonce('ajax-duplicate-nonce')
                )
            );
        }

        public function add_term_fields($taxonomy)
        {
            wp_nonce_field('taxopress_term_synonyms', 'taxopress_term_synonyms_nonce');
?>
            <div class="form-field">
                <label for="text"><?php esc_html_e('Term Synonyms', 'taxopress-pro'); ?></label>
                <input type="text" class="taxopress-synonyms-input term-synonyms" name="taxopress_term_synonyms[]" placeholder="<?php esc_attr_e('Type the synonym name and then click Enter or Return.', 'taxopress-pro'); ?>" />
                <p><?php esc_html_e('If TaxoPress scans your content and finds a synonym, it will act as if it has found the main term.', 'taxopress-pro'); ?></p>
                <ul class="taxopress-term-synonyms wrapper"></ul>
            </div>
        <?php
            $this->taxopress_load_taxonomy_synonyms_assets();
        }

        public function edit_term_fields($term, $taxonomy)
        {
            wp_nonce_field('taxopress_term_synonyms', 'taxopress_term_synonyms_nonce');

            // get meta data value
            $term_synonyms = taxopress_get_term_synonyms($term->term_id);

        ?><tr class="form-field">
                <th>
                    <label for="text"><?php esc_html_e('Term Synonyms', 'taxopress-pro'); ?></label>
                </th>
                <td>
                    <input type="text" class="taxopress-synonyms-input term-synonyms" name="taxopress_term_synonyms[]" placeholder="<?php esc_attr_e('Type the synonym name and then click Enter or Return.', 'taxopress-pro'); ?>" />
                    <p><?php esc_html_e('If TaxoPress scans your content and finds a synonym, it will act as if it has found the main term.', 'taxopress-pro'); ?></p>
                    <ul class="taxopress-term-synonyms wrapper">
                        <?php if (!empty($term_synonyms)) : ?>
                            <?php foreach ($term_synonyms as $term_synonym) : ?>
                                <li>
                                    <span class="display-text"><?php echo esc_html($term_synonym); ?></span>
                                    <span class="remove-synonym">
                                        <span class="dashicons dashicons-no-alt"></span>
                                    </span>
                                    <input type="hidden" class="term-synonyms" name="taxopress_term_synonyms[]" value="<?php echo esc_attr($term_synonym); ?>">
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </td>
            </tr>
<?php
            $this->taxopress_load_taxonomy_synonyms_assets();
        }

        public function save_term_fields($term_id)
        {

            if (!isset($_POST['taxopress_term_synonyms_nonce']) || !wp_verify_nonce(sanitize_key($_POST['taxopress_term_synonyms_nonce']), 'taxopress_term_synonyms')) {
                return;
            }

            $taxopress_term_synonyms = array_map('sanitize_text_field', $_POST['taxopress_term_synonyms']);
            $taxopress_term_synonyms = array_filter($taxopress_term_synonyms);

            update_term_meta(
                $term_id,
                self::TERM_SYNONYMS_FIELD,
                $taxopress_term_synonyms
            );
        }

        public static function find_terms_with_synonyms($synonyms)
        {

            $synonyms_terms = [];
            foreach ($synonyms as $meta_value) {
            
                $synonyms_args = [
                    'hide_empty' => false,
                    'meta_query' => []
                ];
                $synonyms_args['meta_query']['relation'] = 'OR';
                $synonyms_args['meta_query'][] = [
                    'key' => self::TERM_SYNONYMS_FIELD,
                    'value' => serialize(strval($meta_value)),
                    'compare' => 'LIKE'
                ];
                $synonyms_term = get_terms($synonyms_args);
                if (!empty($synonyms_term)) {
                    $synonyms_term = $synonyms_term[0];
                    $synonyms_term->name = ucwords($meta_value);
                    $synonyms_terms[] = $synonyms_term;
                }
            }
            
            return $synonyms_terms;
        }

        public static function duplicate_synonyms_terms($synonyms)
        {
            $duplicate_synonyms_terms = [];
            foreach ($synonyms as $meta_value) {
                $check_term = term_exists($meta_value);
                if ($check_term) {
                    $duplicate_synonyms_terms[] = ucwords($meta_value);
                }
            }

            return $duplicate_synonyms_terms;
        }

        /**
         * Convert synonyms array to sentence
         *
         * @param array $array
         * @return string
         */
        public static function format_synonyms_list($array) {

            $array = array_values($array);

            $count = count($array);
            
            if ($count === 0) {
                return "";
            } elseif ($count === 1) {
                return '"' . $array[0] . '"';
            } elseif ($count === 2) {
                return '"' . implode('" or "', $array) . '"';
            } else {
                $lastElement = array_pop($array);
                $formattedArray = '"' . implode('", "', $array) . '" or "' . $lastElement . '"';
                array_push($array, $lastElement); // Restore the original array
                return $formattedArray;
            }
        }

        /**
         * Prevent duplicate Synonyms
         *
         * @param string|WP_Error $term The term name to add, or a WP_Error object if there's an error.
         * @param string $taxonomy Taxonomy slug.
         *
         * @return array|WP_Error
         */
        public function filter_pre_insert_term($term, $taxonomy)
        {
            if (!empty($_POST) && isset($_POST['taxopress_term_synonyms']) && !empty($_POST['taxopress_term_synonyms'])) {
                $meta_values = array_map('sanitize_text_field', $_POST['taxopress_term_synonyms']);
                $meta_values = array_filter($meta_values);
                if (!empty($meta_values)) {
                    $duplicate_terms = self::find_terms_with_synonyms($meta_values);
                    $duplicate_synonyms = self::duplicate_synonyms_terms($meta_values);

                    if (!empty($duplicate_synonyms)) {
                        if (count($duplicate_synonyms) === 1) {
                            $error_message = sprintf(
                                esc_html__('This synonym can\'t be added because %1$s is a term on this site.', 'taxopress-pro'),
                                self::format_synonyms_list($duplicate_synonyms)
                            );
                        } else {
                            $error_message = sprintf(
                                esc_html__('These synonyms can\'t be added because %1$s are terms on this site.', 'taxopress-pro'),
                                self::format_synonyms_list($duplicate_synonyms)
                            );
                        }

                        return new WP_Error(
                            'taxopress_taxonomy_synonyms_exists',
                            $error_message
                        );
                    }
                    
                    if (!is_wp_error($duplicate_terms) && count($duplicate_terms) > 0) {

                        $duplicate_terms_names = array_map(function($term) {
                            return $term->name;
                        }, $duplicate_terms);

                        if (count($duplicate_terms_names) === 1) {
                            $error_message = sprintf(
                                esc_html__('This synonym can\'t be added because %1$s is already added to another term.', 'taxopress-pro'),
                                self::format_synonyms_list($duplicate_terms_names)
                            );
                        } else {
                            $error_message = sprintf(
                                esc_html__('These synonyms can\'t be added because %1$s are already added to another term.', 'taxopress-pro'),
                                self::format_synonyms_list($duplicate_terms_names)
                            );
                        }

                        return new WP_Error(
                            'taxopress_taxonomy_synonyms_exists',
                            $error_message
                        );
                    }
                }
            }

            //Prevent users from adding terms if it exists as a synonym
            if (!is_wp_error($term)) {
                $existing_term_synonyms = self::find_terms_with_synonyms([$term]);
                if (!is_wp_error($existing_term_synonyms) && count($existing_term_synonyms) > 0) {
                    $existing_term_synonym_names = array_column($existing_term_synonyms, 'name');

                    $error_message = sprintf(
                        esc_html__('%1$s can\'t be added as a tag because %2$s is already used as a synonym.', 'taxopress-pro'),
                        $term,
                        join(', ', $existing_term_synonym_names)
                    );

                    return new WP_Error(
                        'taxopress_taxonomy_synonyms_exists',
                        $error_message
                    );
                }
            }

            return $term;
        }

        /**
         * Handle a request to validate mapped author.
         */
        public static function handle_duplicate_synonyms_validation()
        {

            $response['status']  = 'success';
            $response['content'] = esc_html__('Request status.', 'taxopress-pro');

            //do not process request if nonce validation failed
            if (
                empty($_POST['nonce'])
                || !wp_verify_nonce(sanitize_key($_POST['nonce']), 'ajax-duplicate-nonce')
            ) {
                $response['status']  = 'error';
                $response['content'] = esc_html__(
                    'Security error. Kindly reload this page and try again',
                    'taxopress-pro'
                );
            } else {
                $term_synonyms = !empty($_POST['term_synonyms']) ? array_map('sanitize_text_field', $_POST['term_synonyms']) : [];
                $term_id     = !empty($_POST['term_id']) ? (int) $_POST['term_id'] : 0;

                if ($term_id > 0 && !empty($term_synonyms)) {

                    $duplicate_terms = self::find_terms_with_synonyms($term_synonyms);
                    $duplicate_synonyms = self::duplicate_synonyms_terms($term_synonyms);

                    if (!empty($duplicate_synonyms)) {
                        if (count($duplicate_synonyms) === 1) {
                            $error_message = sprintf(
                                esc_html__('This synonym can\'t be added because %1$s is a term on this site.', 'taxopress-pro'),
                                self::format_synonyms_list($duplicate_synonyms)
                            );
                        } else {
                            $error_message = sprintf(
                                esc_html__('These synonyms can\'t be added because %1$s are terms on this site.', 'taxopress-pro'),
                                self::format_synonyms_list($duplicate_synonyms)
                            );
                        }
                        $response['status']  = 'error';
                        $response['content'] = $error_message;
                    }

                    if (!is_wp_error($duplicate_terms) && count($duplicate_terms) > 0) {
                        $duplicate_terms_ids = [];
                        $duplicate_terms_names = [];
                        foreach ($duplicate_terms as $duplicate_term) {
                            if ($duplicate_term->term_id !== $term_id) {
                                $duplicate_terms_ids[] = $duplicate_term->term_id;
                                $duplicate_terms_names[] = $duplicate_term->name;
                            }
                        }
                        if (!empty($duplicate_terms_ids)) {
                            if (count($duplicate_terms_names) === 1) {
                                $error_message = sprintf(
                                    esc_html__('This synonym can\'t be added because %1$s is already added to another term.', 'taxopress-pro'),
                                    self::format_synonyms_list($duplicate_terms_names)
                                );
                            } else {
                                $error_message = sprintf(
                                    esc_html__('These synonyms can\'t be added because %1$s are already added to another term.', 'taxopress-pro'),
                                    self::format_synonyms_list($duplicate_terms_names)
                                );
                            }
                            $response['status']  = 'error';
                            $response['content'] = $error_message;
                        }
                    }
                }
            }

            wp_send_json($response);
            exit;
        }
    }
}
