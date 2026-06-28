<?php
//include licence
require_once (TAXOPRESS_ABSPATH . '/includes-pro/classes/licence.php');
//include pro modules
require_once (TAXOPRESS_ABSPATH . '/includes-pro/modules/taxonomy-synonyms/taxonomy-synonyms.php');
require_once (TAXOPRESS_ABSPATH . '/includes-pro/modules/linked-terms/linked-terms.php');
require_once (TAXOPRESS_ABSPATH . '/includes-pro/modules/tag-clouds/tag-clouds.php');
require_once (TAXOPRESS_ABSPATH . '/includes-pro/modules/related-posts/related-posts.php');
require_once (TAXOPRESS_ABSPATH . '/includes-pro/modules/post-tags/post-tags.php');
require_once (TAXOPRESS_ABSPATH . '/includes-pro/modules/autolinks/autolinks.php');
require_once (TAXOPRESS_ABSPATH . '/includes-pro/modules/autoterms/autoterms.php');
require_once (TAXOPRESS_ABSPATH . '/includes-pro/modules/autoterms/schedule.php');
require_once (TAXOPRESS_ABSPATH . '/includes-pro/modules/autoterms/schedule-logs-table.php');
require_once (TAXOPRESS_ABSPATH . '/includes-pro/modules/taxopress-ai/taxopress-ai.php');
require_once (TAXOPRESS_ABSPATH . '/includes-pro/modules/terms/terms.php');
require_once (TAXOPRESS_ABSPATH . '/includes-pro/modules/taxonomies/taxonomies.php');
require_once (TAXOPRESS_ABSPATH . '/includes-pro/modules/taxonomies/class-taxonomies-table.php');

if (!class_exists('TaxoPress_Pro_Init')) {
    /**
     * class TaxoPress_Pro_Init
     */
    class TaxoPress_Pro_Init
    {
        // class instance
        public static $instance;

        /**
         * Construct the TaxoPress_Pro_Init class
         */
        public function __construct()
        {
            add_action( 'init', [$this, 'taxopress_load_module_classes'] );
            add_action( 'init', [$this, 'taxopress_load_admin_licence_menu'] );
            add_filter( 'taxopress_admin_pages', [$this, 'taxopress_pro_admin_pages'] );
            add_filter( 'taxopress_dashboard_features', [$this, 'taxopress_pro_dashboard_features'] );
            add_action( 'taxopress_admin_class_before_assets_register', [$this, 'taxopress_load_admin_pro_assets'] );
            add_action( 'taxopress_admin_class_after_styles_enqueue', [$this, 'taxopress_load_admin_pro_styles'] );
            add_filter( 'taxopress_post_tags_create_limit', [$this, 'taxopress_action_is_false'] );
            add_filter( 'taxopress_related_posts_create_limit', [$this, 'taxopress_action_is_false'] );
            add_filter( 'taxopress_tag_clouds_create_limit', [$this, 'taxopress_action_is_false'] );
            add_filter( 'taxopress_autolinks_create_limit', [$this, 'taxopress_action_is_false'] );
            add_filter( 'taxopress_autoterms_create_limit', [$this, 'taxopress_action_is_false'] );
            add_action( 'admin_init', [$this, 'taxopress_pro_only_upgrade_function'] );
            add_action('wp_ajax_taxopress_blocks_search', [$this, 'handle_blocks_search']);
            add_action('init', [$this, 'init_pro_translation']);
        }

        /** Singleton instance */
        public static function get_instance()
        {
            if (!isset(self::$instance)) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        public function init_pro_translation(){
            load_plugin_textdomain( 'taxopress-pro', false, basename( TAXOPRESS_ABSPATH ) . '/languages' );
        }  

        public function taxopress_load_module_classes(){
            if (taxopress_is_synonyms_enabled()) {
                TaxoPress_Taxonomy_Synonyms::get_instance();
            }
            if (taxopress_is_linked_terms_enabled()) {
                TaxoPress_Linked_Terms::get_instance();
            }
            if (1 === (int) SimpleTags_Plugin::get_option_value('active_auto_links')) {
                TaxoPress_Pro_Auto_Links::get_instance();
            }
            if (1 === (int) SimpleTags_Plugin::get_option_value('active_terms_display')){
                TaxoPress_Pro_Tag_Clouds::get_instance();
            }
            if (1 === (int) SimpleTags_Plugin::get_option_value('active_taxonomies')){
                TaxoPress_Pro_Taxonomies::get_instance();
                TaxoPress_Pro_Taxonomies_Table::get_instance();
            }
            if (1 === (int) SimpleTags_Plugin::get_option_value('active_related_posts')){
                TaxoPress_Pro_Related_Posts::get_instance();
            }
            if (1 === (int) SimpleTags_Plugin::get_option_value('active_post_tags')){
                TaxoPress_Pro_Post_Tags::get_instance();
            }
            if (1 === (int) SimpleTags_Plugin::get_option_value('active_auto_terms')) {
                TaxoPress_Pro_Auto_Terms::get_instance();
                TaxoPress_Pro_Auto_Terms_Schedule::get_instance();
            }
            if (1 === (int) SimpleTags_Plugin::get_option_value('active_st_terms')){
                TaxoPress_Pro_Terms::get_instance();
            }
            TaxoPress_Pro_AI_Module::get_instance();
        }

        public function taxopress_load_admin_licence_menu(){
            TaxoPress_License::get_instance();
        }

        public function taxopress_pro_admin_pages($taxopress_pages){

            $taxopress_pages[] = 'st_licence';
            $taxopress_pages[] = 'st_linked_terms';
            $taxopress_pages[] = 'st_autoterms_schedule';

            return $taxopress_pages;
        }

        public function taxopress_pro_dashboard_features($features){

            $features['st_features_synonyms'] = [
                'label'        => esc_html__('Synonyms', 'taxopress-pro'),
                'description'  => esc_html__('This feature allows you to associate additional words with each term. For example, "website" can have synonyms such as "websites", "web site", and "web pages".', 'taxopress-pro'),
                'option_key'   => 'active_features_synonyms',
            ];

            $linked_terms_feature = [
                'label'        => esc_html__('Linked Terms', 'taxopress-pro'),
                'description'  => esc_html__('This feature allows you to connect terms. When the main term or any of these terms are added to the post, all the other terms will be added also.', 'taxopress-pro'),
                'option_key'   => 'active_features_linked_terms',
            ];

            // add linked term after terms in dashboard
            $index = array_search('st_terms', array_keys($features));
            if ($index !== false) {
                $features = array_slice($features, 0, $index + 1, true) + 
                    array('st_linked_terms' => $linked_terms_feature) + 
                    array_slice($features, $index + 1, count($features) - 1, true);
            } else {
                $features['st_linked_terms'] = $linked_terms_feature;
            }

            return $features;
        }

        public function taxopress_load_admin_pro_assets(){
            wp_register_style( 'st-admin-pro', STAGS_URL . '/includes-pro/assets/css/pro.css', array(), STAGS_VERSION, 'all' );
            wp_register_script( 'st-admin-pro', STAGS_URL . '/includes-pro/assets/js/pro.js', array( 'jquery' ), STAGS_VERSION );
        }

        public function taxopress_load_admin_pro_styles(){
            wp_enqueue_style( 'st-admin-pro' );
            wp_enqueue_script( 'st-admin-pro' );
        }

        public function taxopress_action_is_false($limit){
            return false;
        }

        public function taxopress_pro_only_upgrade_function()
        {

            if (!get_option('taxopress_pro_3_5_2_upgraded')) {
                //this upgrade is neccessary due to free version uninstall removing role for author
                if ( function_exists( 'get_role' ) ) {
                    $role = get_role( 'administrator' );
                    if ( null !== $role && ! $role->has_cap( 'simple_tags' ) ) {
                        $role->add_cap( 'simple_tags' );
                    }

                    if ( null !== $role && ! $role->has_cap( 'admin_simple_tags' ) ) {
                        $role->add_cap( 'admin_simple_tags' );
                    }

                    $role = get_role( 'editor' );
                    if ( null !== $role && ! $role->has_cap( 'simple_tags' ) ) {
                        $role->add_cap( 'simple_tags' );
                    }
                }
              update_option('taxopress_pro_3_5_2_upgraded', true);
           }

           if (
                empty(get_option('taxopress_pro_last_version')) 
                || version_compare(get_option('taxopress_pro_last_version'), '3.26.0', '<')
            ) {
                // migrate taxopress ai/metabox settings and apis to auto term
                if (function_exists('taxopress_get_autoterm_data')) {
                    $autoterm_datas = taxopress_get_autoterm_data();
                    $tas = get_option('st_taxopress_ai_settings');
                    if (is_array($tas) && !empty($tas) && is_array($autoterm_datas) && !empty($autoterm_datas)) {
                        // Existing Terms
                        $existing_terms_maximum_terms = isset($tas['existing_terms_maximum_terms']) ? $tas['existing_terms_maximum_terms'] : 45;
                        $existing_terms_orderby = isset($tas['existing_terms_orderby']) ? $tas['existing_terms_orderby'] : 'count';
                        $existing_terms_order = isset($tas['existing_terms_order']) ? $tas['existing_terms_order'] : 'desc';
                        $existing_terms_show_post_count = isset($tas['existing_terms_show_post_count']) ? $tas['existing_terms_show_post_count'] : 0;
                        // open ai
                        $open_ai_api_key = isset($tas['open_ai_api_key']) ? $tas['open_ai_api_key'] : '';
                        $open_ai_model = isset($tas['open_ai_model']) ? $tas['open_ai_model'] : 'gpt-3.5-turbo';
                        $open_ai_tag_prompt = isset($tas['open_ai_tag_prompt']) ? stripslashes_deep($tas['open_ai_tag_prompt']) : "Extract tags from the following content: '{content}'. Tags:";
                        $open_ai_cache_result = isset($tas['open_ai_cache_result']) ? $tas['open_ai_cache_result'] : 0;
                        $open_ai_show_post_count = isset($tas['open_ai_show_post_count']) ? $tas['open_ai_show_post_count'] : 0;
                        // ibm watson
                        $ibm_watson_api_url = isset($tas['ibm_watson_api_url']) ? $tas['ibm_watson_api_url'] : '';
                        $ibm_watson_api_key = isset($tas['ibm_watson_api_key']) ? $tas['ibm_watson_api_key'] : '';
                        $ibm_watson_cache_result = isset($tas['ibm_watson_cache_result']) ? $tas['ibm_watson_cache_result'] : 0;
                        $ibm_watson_show_post_count = isset($tas['ibm_watson_show_post_count']) ? $tas['ibm_watson_show_post_count'] : 0;
                        // dandelion
                        $dandelion_api_token = isset($tas['dandelion_api_token']) ? $tas['dandelion_api_token'] : '';
                        $dandelion_api_confidence_value = isset($tas['dandelion_api_confidence_value']) ? $tas['dandelion_api_confidence_value'] : '0.6';
                        $dandelion_cache_result = isset($tas['dandelion_cache_result']) ? $tas['dandelion_cache_result'] : 0;
                        $dandelion_show_post_count = isset($tas['dandelion_show_post_count']) ? $tas['dandelion_show_post_count'] : 0;
                        // open calais
                        $open_calais_api_key = isset($tas['open_calais_api_key']) ? $tas['open_calais_api_key'] : '';
                        $open_calais_cache_result = isset($tas['open_calais_cache_result']) ? $tas['open_calais_cache_result'] : 0;
                        $open_calais_show_post_count = isset($tas['open_calais_show_post_count']) ? $tas['open_calais_show_post_count'] : 0;

                        foreach ($autoterm_datas as $index => $data) {
                            // add Existing Terms
                            $autoterm_datas[$index]['existing_terms_maximum_terms'] = $existing_terms_maximum_terms;
                            $autoterm_datas[$index]['suggest_local_terms_orderby'] = $existing_terms_orderby;
                            $autoterm_datas[$index]['suggest_local_terms_order'] = $existing_terms_order;
                            $autoterm_datas[$index]['suggest_local_terms_show_post_count'] = $existing_terms_show_post_count;

                            // add open ai
                            $autoterm_datas[$index]['open_ai_api_key'] = $open_ai_api_key;
                            $autoterm_datas[$index]['open_ai_model'] = $open_ai_model;
                            $autoterm_datas[$index]['open_ai_tag_prompt'] = $open_ai_tag_prompt;
                            $autoterm_datas[$index]['open_ai_cache_result'] = $open_ai_cache_result;
                            $autoterm_datas[$index]['open_ai_show_post_count'] = $open_ai_show_post_count;

                            // add ibm watson
                            $autoterm_datas[$index]['ibm_watson_api_url'] = $ibm_watson_api_url;
                            $autoterm_datas[$index]['ibm_watson_api_key'] = $ibm_watson_api_key;
                            $autoterm_datas[$index]['ibm_watson_show_post_count'] = $ibm_watson_show_post_count;
                            $autoterm_datas[$index]['ibm_watson_cache_result'] = $ibm_watson_cache_result;

                            // add dandelion
                            $autoterm_datas[$index]['dandelion_api_token'] = $dandelion_api_token;
                            $autoterm_datas[$index]['dandelion_api_confidence_value'] = $dandelion_api_confidence_value;
                            $autoterm_datas[$index]['dandelion_show_post_count'] = $dandelion_show_post_count;
                            $autoterm_datas[$index]['dandelion_cache_result'] = $dandelion_cache_result;

                             // add open calais
                            $autoterm_datas[$index]['open_calais_api_key'] = $open_calais_api_key;
                            $autoterm_datas[$index]['open_calais_show_post_count'] = $open_calais_show_post_count;
                            $autoterm_datas[$index]['open_calais_cache_result'] = $open_calais_cache_result;
                        }
                        update_option('taxopress_autoterms', $autoterm_datas);
                    }
                }
                update_option('taxopress_pro_last_version', TAXOPRESS_PRO_VERSION);
          }

        }

        /**
         * Handle an ajax request to search blocks
         */
        public static function handle_blocks_search()
        {
            header('Content-Type: application/javascript');
    
            if (empty($_GET['nonce'])
                || !wp_verify_nonce(sanitize_key($_GET['nonce']), 'taxopress-blocks-search')
            ) {
                wp_send_json_error(null, 403);
            }
    
            if (! current_user_can('simple_tags')) {
                wp_send_json_error(null, 403);
            }

            $search = !empty($_GET['q']) ? sanitize_text_field($_GET['q']) : '';

            $blocks  = self::get_all_registered_blocks();
            $results = [];
            foreach ($blocks as $block_name => $block_object) {
                $block_title = !empty($block_object->title) ? $block_object->title : $block_object->name;
                if (empty($search) || stripos( $block_title, $search ) !== false) {
                    $results[] = [
                        'id'   => $block_object->name,
                        'text' => $block_title,
                    ];
                }
            }

            $response = [
                'results' => $results,
            ];
            echo wp_json_encode($response);
            exit;
        }

        public static function get_all_registered_blocks() {

            // Ensure the function exists (it should be available in WordPress 5.0+)
            if ( ! class_exists( 'WP_Block_Type_Registry' ) ) {
                return [];
            }
        
            // Get the instance of the block type registry
            $registry = WP_Block_Type_Registry::get_instance();
        
            // Get all registered block types
            $blocks = $registry->get_all_registered();
        
            return $blocks;
        }

    }
}

// Initialize the module
TaxoPress_Pro_Init::get_instance();
