<?php

if (!class_exists('TaxoPress_Pro_AI_Module')) {
    /**
     * class TaxoPress_Pro_AI_Module
     */
    class TaxoPress_Pro_AI_Module
    {
        // class instance
        static $instance;

        /**
         * Construct the TaxoPress_Pro_AI_Module class
         */
        public function __construct()
        {

            add_filter('taxopress_settings_post_type_ai_fields', [$this, 'filter_settings_post_type_ai_fields'], 10, 2);

            add_filter('taxopress_admin_options', [$this, 'add_legacy_ai_sources_tab'], 20);
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
         * Filter post type taxopress ai fields
         *
         * @return array
         */
        public function filter_settings_post_type_ai_fields($taxopress_ai_fields, $post_type)
        {

            $default_taxonomy_display_options = [
                'default' => esc_html__('Default', 'taxopress-pro'),
                'dropdown' => esc_html__('Dropdown', 'taxopress-pro'),
                'checkbox' => esc_html__('Checkbox', 'taxopress-pro'),
            ];
            
            // add taxonomy display option after taxopress_ai_{$post_type}_metabox_default_taxonomy
            $new_entry = array(
                'taxopress_ai_' . $post_type . '_metabox_display_option',
                '<div class="taxopress-ai-tab-content-sub taxopress-settings-subtab-title taxopress-ai-'. $post_type .'-content-sub enable_taxopress_ai_' . $post_type . '_post_terms_tab_field st-subhide-content">' . esc_html__('Metabox Taxonomy Display', 'taxopress-pro') . '</div>',
                'select',
                $default_taxonomy_display_options,
                '',
                'taxopress-ai-tab-content-sub taxopress-ai-'. $post_type .'-content-sub enable_taxopress_ai_' . $post_type . '_post_terms_tab_field st-subhide-content'
            );

            // Get the index of 'taxopress_ai_post_metabox_default_taxonomy' if it exists
            $field_to_find = 'taxopress_ai_' . $post_type . '_metabox_default_taxonomy';
            $keys = array_column($taxopress_ai_fields, 0);
            $insert_after_key = array_search($field_to_find, $keys);
        
            // Determine the insertion position adding fallback incase the setting doesn't exist
            $position = ($insert_after_key !== false) ? $insert_after_key + 1 : count($taxopress_ai_fields);
        
            // Insert new entry at the determined position
            $taxopress_ai_fields = array_merge(
                array_slice($taxopress_ai_fields, 0, $position, true),
                [$new_entry],
                array_slice($taxopress_ai_fields, $position, null, true)
            );

            return $taxopress_ai_fields;
        }

         /**
         * Add the Legacy AI Sources tab to the settings
         */
        public function add_legacy_ai_sources_tab($options)
        {
            $legacy_ai_sources = array(
                array(
                    'enable_ibm_watson_ai_source',
                    __('Enable IBM Watson integration', 'taxopress-pro'),
                    'checkbox',
                    '1',
                    __('Show IBM Watson as an AI source for Auto Terms', 'taxopress-pro'),
                    ''
                ),
                array(
                                        'enable_dandelion_ai_source',
                    __('Enable Dandelion integration', 'taxopress-pro'),
                    'checkbox',
                    '1',
                    __('Show Dandelion as an AI source for Auto Terms.', 'taxopress-pro'),
                    ''
                ),
                array(
                    'enable_lseg_ai_source',
                    __('Enable LSEG/Refinitiv integration', 'taxopress-pro'),
                    'checkbox',
                    '1',
                    __('Show LSEG/Refinitiv as an AI source for Auto Terms', 'taxopress-pro'),
                    ''
                ),
            );

            $keys = array_keys($options);
            $insert_before_key = array_search('hidden_terms', $keys);

            $position = ($insert_before_key !== false) ? $insert_before_key : count($options);

            // Split and merge the arrays to insert at the correct position
            $result = array_merge(
                array_slice($options, 0, $position, true),
                ['legacy_ai_sources' => $legacy_ai_sources],
                array_slice($options, $position, null, true)
            );

            return $result;
        }
    }
}
