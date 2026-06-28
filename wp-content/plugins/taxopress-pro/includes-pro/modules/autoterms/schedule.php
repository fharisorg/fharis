<?php

if (!class_exists('TaxoPress_Pro_Auto_Terms_Schedule')) {
    /**
     * class TaxoPress_Pro_Auto_Terms_Schedule
     */
    class TaxoPress_Pro_Auto_Terms_Schedule
    {
        const MENU_SLUG = 'st_options';

        // class instance
        static $instance;

        // WP_List_Table object
        public $logs_table;

        /**
         * Construct the TaxoPress_Pro_Auto_Terms_Schedule class
         */
        public function __construct()
        {
            // Admin menu
            add_action('admin_menu', [$this, 'admin_menu']);
            // Javascript
            add_action('admin_enqueue_scripts', [__CLASS__, 'admin_enqueue_scripts'], 11);
            
            add_action( 'taxopress_cron_autoterms_hourly', [$this, 'taxopress_cron_autoterms_hourly_execution'] );
            add_action( 'taxopress_cron_autoterms_daily', [$this, 'taxopress_cron_autoterms_daily_execution'] );
            // Schedule cron events
            add_action( 'init', [$this, 'schedule_taxopress_cron_events'] );
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
         * Init somes JS and CSS need for this feature
         *
         * @return void
         * @author Olatechpro
         */
        public static function admin_enqueue_scripts()
        {
    
            // add JS for manage click tags
            if (isset($_GET['page']) && $_GET['page'] == 'st_autoterms_schedule') {
                wp_enqueue_style('st-taxonomies-css');
            }
        }

        /**
         * Add WP admin menu for Tags
         *
         * @return void
         * @author Olatechpro
         */
        public function admin_menu()
        {
            $hook = add_submenu_page(
                self::MENU_SLUG,
                esc_html__('Schedule', 'taxopress-pro'),
                esc_html__('Schedule', 'taxopress-pro'),
                'simple_tags',
                'st_autoterms_schedule',
                [
                    $this,
                    'page_manage_autoterms_schedule',
                ]
            );
            add_action("load-$hook", [$this, 'save_autoterms_schedule_settings']);
            add_action("load-$hook", [$this, 'screen_option']);
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
                'option'  => 'st_autoterms_schedule_logs_per_page'
            ];
            $this->logs_table = new Autoterms_Schedule_Logs();
    
            add_screen_option($option, $args);
        }

    public function save_autoterms_schedule_settings() {

        if( !empty($_POST['taxopress_autoterm_schedule_submit']) 
            && !empty($_POST['_nonce']) 
            && wp_verify_nonce(sanitize_text_field($_POST['_nonce']), 'taxopress_autoterm_schedule_nonce')
            && current_user_can('simple_tags')
        ) {
            $auto_term_ids = !empty($_POST['taxopress_autoterm_schedule']['autoterm_id']) ? array_map('intval', (array)$_POST['taxopress_autoterm_schedule']['autoterm_id']) : [];

            $cron_schedule = !empty($_POST['taxopress_autoterm_schedule']['cron_schedule']) ? taxopress_sanitize_text_field($_POST['taxopress_autoterm_schedule']['cron_schedule']) : 'disable';
            $schedule_terms_batches = !empty($_POST['taxopress_autoterm_schedule']['schedule_terms_batches']) ? (int)$_POST['taxopress_autoterm_schedule']['schedule_terms_batches'] : '';
            $schedule_terms_sleep = !empty($_POST['taxopress_autoterm_schedule']['schedule_terms_sleep']) ? (int)$_POST['taxopress_autoterm_schedule']['schedule_terms_sleep'] : '';
            $schedule_terms_limit_days = !empty($_POST['taxopress_autoterm_schedule']['schedule_terms_limit_days']) ? taxopress_sanitize_text_field($_POST['taxopress_autoterm_schedule']['schedule_terms_limit_days']) : '';
            $autoterm_schedule_exclude = !empty($_POST['taxopress_autoterm_schedule']['autoterm_schedule_exclude']) ? (int)$_POST['taxopress_autoterm_schedule']['autoterm_schedule_exclude'] : '';

            $response_message = esc_html__('An error occured.', 'taxopress-pro');
            $response_sucess  = false;
            if (empty($schedule_terms_batches)) {
                $response_message = esc_html__('Limit per batches is required.', 'taxopress-pro');
            } elseif (empty($schedule_terms_sleep)) {
                $response_message = esc_html__('Batches wait time is required.', 'taxopress-pro');
            } else {
                $auto_term_schedule_settings = [
                    'autoterm_id' => $auto_term_ids,
                    'cron_schedule' => $cron_schedule,
                    'schedule_terms_batches' => $schedule_terms_batches,
                    'schedule_terms_sleep' => $schedule_terms_sleep,
                    'schedule_terms_limit_days' => $schedule_terms_limit_days,
                    'autoterm_schedule_exclude' => $autoterm_schedule_exclude,
                ];
                update_option('taxopress_autoterms_schedule', $auto_term_schedule_settings); 

                // first delete existing crons
                wp_clear_scheduled_hook('taxopress_cron_autoterms_hourly');
                wp_clear_scheduled_hook('taxopress_cron_autoterms_daily');

                if ($cron_schedule == 'hourly') {
                    // add hourly cron
                    wp_schedule_event( time(), 'hourly', 'taxopress_cron_autoterms_hourly' );
                } elseif ($cron_schedule == 'daily') {
                    // add daily cron
                    wp_schedule_event( time(), 'daily', 'taxopress_cron_autoterms_daily' );
                }

                $autoterm_data = taxopress_get_autoterm_data();
                $autoterm_data_selected = [];

                if (!empty($auto_term_ids)) {
                    foreach ($auto_term_ids as $term_id) {
                        if (isset($autoterm_data[$term_id])) {
                            $autoterm_data_selected[$term_id] = $autoterm_data[$term_id];
                        }
                    }
                }
                $autoterm_data = $autoterm_data_selected;

                $schedule_enabled = false;
                foreach ($autoterm_data_selected as $term_data) {
                    if (!empty($term_data['autoterm_for_schedule'])) {
                        $schedule_enabled = true;
                        break;
                    }
                }

                if (!$schedule_enabled) {    
                    $response_message = esc_html__('Schedule is not enabled for selected Auto Terms.', 'taxopress-pro');
                    $response_sucess  = false;
                } else {            
                    $response_message = esc_html__('Settings updated successfully.', 'taxopress-pro');
                    $response_sucess  = true;
                }
            }

            add_action('admin_notices', function () use($response_message, $response_sucess) {
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo taxopress_admin_notices_helper($response_message, $response_sucess);
            });
        }
    }

    /**
     * Create our settings page output.
     *
     * @internal
     */
    public function page_manage_autoterms_schedule()
    {
        
        // Default order
        if (!isset($_GET['order'])) {
            $_GET['order'] = 'name-asc';
        }

        settings_errors(__CLASS__);
        ?>
        <?php

        $ui = new taxopress_admin_ui();

        $autoterms_schedule = taxopress_get_autoterms_schedule_data();

        ?>
        <div class="wrap taxopress-split-wrap taxopress-autoterm-schedule">
            <h1><?php echo esc_html__('Schedule', 'taxopress-pro'); ?> </h1>
            <div class="taxopress-description">
                <?php esc_html_e('This feature allows you to run the Auto Terms feature on a schedule. This is helpful if you regularly import content into WordPress. TaxoPress can run either daily or hourly and add terms to your imported content.', 'taxopress-pro'); ?>
            </div>
            <div class="wp-clearfix"></div>
            <form method="post" id="auto_term_schedule_form" action="">
                <div id="poststuff">
                    <div id="post-body" class="taxopress-section metabox-holder columns-2">
                        <div class="tp-flex-item">
                            <div id="post-body-content" class="right-body-content" style="position: relative;">
                                <div class="postbox-header">
                                    <h2 class="hndle ui-sortable-handle">
                                        <?php echo esc_html__('Settings', 'taxopress-pro'); ?>
                                    </h2>
                                </div>
                                <div class="main">
                                    <table class="form-table taxopress-table autoterm_schedule">
                                        <?php
                                        $autoterm_data = taxopress_get_autoterm_data();
                                        $selected_autoterm = !empty($autoterms_schedule['autoterm_id']) ? array_map('intval', (array)$autoterms_schedule['autoterm_id']) : [];
                                        if (empty($autoterm_data)) :
                                            $auto_term_opionts = [
                                                [
                                                    'attr' => '',
                                                    'text' => __('Select an option...', 'taxopress-pro')
                                                ]
                                            ];
                                        else :
                                            $auto_term_opionts = [];
                                            foreach ($autoterm_data as $autoterm_settings) {
                                                $current_option = [];
                                                $current_option['attr'] = $autoterm_settings['ID'];
                                                $current_option['text'] = $autoterm_settings['title'];
                                                if (in_array($autoterm_settings['ID'], $selected_autoterm)) {
                                                    $current_option['default'] = 'true';
                                                }
                                                $auto_term_opionts[] = $current_option;
                                            } 
                                        endif;
                                        $select = [];
                                        $select['options']  = $auto_term_opionts;
                                        $select['selected'] = '';
                                        
                                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                        echo $ui->get_select_checkbox_input_main([
                                            'namearray'  => 'taxopress_autoterm_schedule',
                                            'name'       => 'autoterm_id',
                                            'class'      => 'taxopress-multi-select2',
                                            'labeltext'  => esc_html__('Auto Terms setting',
                                                'taxopress-pro'),
                                                'aftertext'  => esc_html__('Select Auto Terms settings to use when running the "Schedule" feature.', 'taxopress-pro') . ' ',
                                            'selections' => $select,
                                            'multiple'   => true, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                        ]);

                                        $cron_options = [
                                            'disable' => __('None', 'taxopress-pro'),
                                            'hourly' => __('Hourly', 'taxopress-pro'),
                                            'daily'  => __('Daily', 'taxopress-pro'),
                                        ];
                                        ?>
                                        <tr valign="top">
                                            <th scope="row"><label><?php echo esc_html__('Cron Schedule', 'taxopress-pro'); ?></label></th>
                            
                                            <td>
                                                <?php
                                                $cron_schedule  = (!empty($autoterms_schedule['cron_schedule'])) ? $autoterms_schedule['cron_schedule'] : 'disable';
                                                foreach ($cron_options as $option => $label) {
                                                    $checked_status = ($option === $cron_schedule)  ? 'checked' : ''; 
                                                    ?>
                                                    <label> 
                                                        <input 
                                                            class="autoterm_cron" 
                                                            type="radio" 
                                                            id="autoterm_cron_<?php echo esc_attr($option); ?>" 
                                                            name="taxopress_autoterm_schedule[cron_schedule]" 
                                                            value="<?php echo esc_attr($option); ?>"
                                                            <?php echo esc_html($checked_status); ?>
                                                        /> <?php echo esc_html($label); ?>
                                                        </label> 
                                                        <br /><br />
                                                <?php
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <?php
                            
                                        $select             = [
                                            'options' => [
                                                [
                                                    'attr'    => '0',
                                                    'text'    => esc_attr__('False', 'taxopress-pro'),
                                                    'default' => 'true',
                                                ],
                                                [
                                                    'attr' => '1',
                                                    'text' => esc_attr__('True', 'taxopress-pro'),
                                                ],
                                            ],
                                        ];
                                        $selected           = (isset($autoterms_schedule) && isset($autoterms_schedule['autoterm_schedule_exclude'])) ? taxopress_disp_boolean($autoterms_schedule['autoterm_schedule_exclude']) : '';
                                        $select['selected'] = !empty($selected) ? $autoterms_schedule['autoterm_schedule_exclude'] : '';
                                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                        echo $ui->get_select_checkbox_input([
                                            'namearray'  => 'taxopress_autoterm_schedule',
                                            'name'       => 'autoterm_schedule_exclude',
                                            'class'      => '',
                                            'labeltext'  => esc_html__('Exclude previously analyzed content', 'taxopress-pro'),
                                            'aftertext'  => esc_html__('This enables you to skip posts that have already been analyzed by the Schedule feature.', 'taxopress-pro'),
                                            'selections' => $select, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                        ]);
                                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                        echo $ui->get_number_input([
                                            'namearray' => 'taxopress_autoterm_schedule',
                                            'name'      => 'schedule_terms_batches',
                                            'textvalue' => isset($autoterms_schedule['schedule_terms_batches']) ? esc_attr($autoterms_schedule['schedule_terms_batches']) : '20',
                                            'labeltext' => esc_html__(
                                                'Limit per batches',
                                                'taxopress-pro'
                                            ),
                                            'helptext'  => esc_html__('This enables your scheduled Auto Terms to run in batches. If you have a lot of content, set this to a lower number to avoid timeouts.', 'taxopress-pro'),
                                            'min'       => '1',
                                            'required'  => true,
                                        ]);
                            
                                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                        echo $ui->get_number_input([
                                            'namearray' => 'taxopress_autoterm_schedule',
                                            'name'      => 'schedule_terms_sleep',
                                            'textvalue' => isset($autoterms_schedule['schedule_terms_sleep']) ? esc_attr($autoterms_schedule['schedule_terms_sleep']) : '10',
                                            'labeltext' => esc_html__('Batches wait time', 'taxopress-pro'),
                                            'helptext'  => esc_html__('This is the wait time (in seconds) between processing batches of Auto Terms. If you have a lot of existing content, set this to a higher number to avoid timeouts.', 'taxopress-pro'),
                                            'min'       => '0',
                                            'required'  => true,
                                        ]);
                            
                                        $select             = [
                                            'options' => [
                                                [
                                                    'attr' => '1',
                                                    'text' => esc_attr__('24 hours ago', 'taxopress-pro')
                                                ],
                                                [
                                                    'attr' => '7',
                                                    'text' => esc_attr__('7 days ago', 'taxopress-pro')
                                                ],
                                                [
                                                    'attr' => '14',
                                                    'text' => esc_attr__('2 weeks ago', 'taxopress-pro')
                                                ],
                                                [
                                                    'attr' => '30',
                                                    'text' => esc_attr__('1 month ago', 'taxopress-pro'),
                                                    'default' => 'true'
                                                ],
                                                [
                                                    'attr' => '180',
                                                    'text' => esc_attr__('6 months ago', 'taxopress-pro')
                                                ],
                                                [
                                                    'attr' => '365',
                                                    'text' => esc_attr__('1 year ago', 'taxopress-pro')
                                                ],
                                                [
                                                    'attr'    => '0',
                                                    'text'    => esc_attr__('No limit', 'taxopress-pro')
                                                ],
                                            ],
                                        ];
                            
                                        if (isset($autoterms_schedule) && is_array($autoterms_schedule)) {
                                            $select             = [
                                                'options' => [
                                                    [
                                                        'attr' => '1',
                                                        'text' => esc_attr__('24 hours ago', 'taxopress-pro')
                                                    ],
                                                    [
                                                        'attr' => '7',
                                                        'text' => esc_attr__('7 days ago', 'taxopress-pro')
                                                    ],
                                                    [
                                                        'attr' => '14',
                                                        'text' => esc_attr__('2 weeks ago', 'taxopress-pro')
                                                    ],
                                                    [
                                                        'attr' => '30',
                                                        'text' => esc_attr__('1 month ago', 'taxopress-pro'),
                                                    ],
                                                    [
                                                        'attr' => '180',
                                                        'text' => esc_attr__('6 months ago', 'taxopress-pro')
                                                    ],
                                                    [
                                                        'attr' => '365',
                                                        'text' => esc_attr__('1 year ago', 'taxopress-pro')
                                                    ],
                                                    [
                                                        'attr'    => '0',
                                                        'text'    => esc_attr__('No limit', 'taxopress-pro'),
                                                        'default' => 'true'
                                                    ],
                                                ],
                                            ];
                                        }
                            
                                        $selected           = (isset($autoterms_schedule) && isset($autoterms_schedule['schedule_terms_limit_days'])) ? taxopress_disp_boolean($autoterms_schedule['schedule_terms_limit_days']) : '';
                                        $select['selected'] = !empty($selected) ? $autoterms_schedule['schedule_terms_limit_days'] : '';
                                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                        echo $ui->get_select_number_select([
                                            'namearray'  => 'taxopress_autoterm_schedule',
                                            'name'       => 'schedule_terms_limit_days',
                                            'labeltext'  => esc_html__(
                                                'Limit Auto Terms, based on published date',
                                                'taxopress-pro'
                                            ),
                                            'aftertext'  => esc_html__('This setting can limit your scheduled Auto Terms query to only recent content. We recommend using this feature to avoid timeouts on large sites.', 'taxopress-pro'),
                                            'selections' => $select, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                        ]);

                                        ?>
                                    </table>
                                </div>
                            </div>
                            <div class="tp-submit-div">
                                <?php wp_nonce_field('taxopress_autoterm_schedule_nonce', '_nonce'); ?>
                                <input type="submit" class="button-primary taxopress-taxonomy-submit taxopress-autoterm-schedule-submit" name="taxopress_autoterm_schedule_submit" value="<?php echo esc_attr__('Save Settings', 'taxopress-pro'); ?>">
                            </div>
                        </div>

                        <div id="postbox-container-1" class="postbox-container tp-flex-item">
                            <div id="side-sortables" class="meta-box-sortables ui-sortable" style="">
                                <div id="submitdiv" class="postbox">
                                    <div class="postbox-header">
                                        <h2 class="hndle ui-sortable-handle post_terms_icon preview-title">
                                            <?php esc_html_e('Recent Schedule Runs', 'taxopress-pro'); ?>
                                        </h2>
                                    </div>
                                    <div class="inside">
                                        <div id="minor-publishing">
                                            <div class="sidebar-body-wrap">
                                                <p class="description"><?php echo sprintf(esc_html__('You can see full log details on the %1s screen', 'taxopress-pro'), '<a target="_blank" href="'.admin_url('admin.php?page=st_autoterms&tab=logs').'">'.esc_html__('Auto Terms Logs', 'taxopress-pro').'</a>'); ?></p>
                                                    <?php
                                                    //the log table instance
                                                    $this->logs_table->prepare_items();
                                                    ?>
                                    
                                                    <div id="col-container" class="wp-clearfix">
                                    
                                                        <div class="col-wrap">
                                                            <form action="<?php echo esc_url(add_query_arg('', '')); ?>" method="post">
                                                                <?php $this->logs_table->display(); //Display the table ?>
                                                            </form>
                                                            <div class="form-wrap edit-term-notes">
                                                                <p><?php esc_html__('Description here.', 'simple-tags') ?></p>
                                                            </div>
                                                        </div>
                                                    </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php //do_action('taxopress_admin_after_sidebar'); ?>
                            </div>

                        </div>
                        <br class="clear">
                    </div>
                </div>
            </form>
        </div>
        <?php SimpleTags_Admin::printAdminFooter(); ?>
        <?php
    }

    public function autoterms_logs_count(){

        $count = taxopress_autoterms_logs_data(1)['counts'];
        return '('. number_format_i18n($count) .')';
    }

        /**
         * Schedule taxopress cron events
         *
         * @return void
         */
        public function schedule_taxopress_cron_events() {
            if ( ! wp_next_scheduled( 'taxopress_cron_autoterms_hourly' ) ) {
                wp_schedule_event( time(), 'hourly', 'taxopress_cron_autoterms_hourly' );
            }            
            
            if ( ! wp_next_scheduled( 'taxopress_cron_autoterms_daily' ) ) {
                wp_schedule_event( time(), 'daily', 'taxopress_cron_autoterms_daily' );
            }
        }

        public function taxopress_cron_autoterms_hourly_execution()
        {

            global $wpdb;

            $autoterms_schedule = taxopress_get_autoterms_schedule_data();
            $autoterms = taxopress_get_autoterm_data();
            $autoterm_schedule_ids = isset($autoterms_schedule['autoterm_id']) ? (array)$autoterms_schedule['autoterm_id'] : [];
            $autoterm_data = [];

            foreach ($autoterm_schedule_ids as $autoterm_schedule_id) {
                if (!empty($autoterms[$autoterm_schedule_id])) {
                    $autoterm_data[$autoterm_schedule_id] = $autoterms[$autoterm_schedule_id];
                }
            } 

            if (!empty($autoterm_data)) {
            
                $cron_schedule = isset($autoterms_schedule['cron_schedule']) ? $autoterms_schedule['cron_schedule'] : 'disable';
                $post_types = isset($autoterm_data['post_types']) ? (array)$autoterm_data['post_types'] : [];
                $post_status = isset($autoterm_data['post_status']) && is_array($autoterm_data['post_status']) ? $autoterm_data['post_status'] : ['publish'];
                $autoterm_schedule_exclude = isset($autoterms_schedule['autoterm_schedule_exclude']) ? (int)$autoterms_schedule['autoterm_schedule_exclude'] : 0;
    
                // make sure some auto terms settings are overriden by schedule specific settings
                $autoterm_data['terms_limit'] = !empty($autoterm_data['schedule_terms_limit']) ? $autoterm_data['schedule_terms_limit'] : '';
                $autoterm_data['autoterm_target'] = !empty($autoterm_data['schedule_autoterm_target']) ? $autoterm_data['schedule_autoterm_target'] : '';
                $autoterm_data['autoterm_word'] = !empty($autoterm_data['schedule_autoterm_word']) ? $autoterm_data['schedule_autoterm_word'] : '';
                $autoterm_data['autoterm_hash'] = !empty($autoterm_data['schedule_autoterm_hash']) ? $autoterm_data['schedule_autoterm_hash'] : '';
                $autoterm_data['replace_type'] = isset($autoterm_data['schedule_replace_type']) ? $autoterm_data['schedule_replace_type'] : '';

                // set auto term settings from schedule settings
                $autoterm_data['autoterm_for_schedule'] = $autoterm_data['cron_schedule'] = $cron_schedule;
                $autoterm_data['autoterm_schedule_exclude'] = !empty($autoterms_schedule['autoterm_schedule_exclude']) ? $autoterms_schedule['autoterm_schedule_exclude'] : '';
                $autoterm_data['schedule_terms_batches'] = !empty($autoterms_schedule['schedule_terms_batches']) ? $autoterms_schedule['schedule_terms_batches'] : '';
                $autoterm_data['schedule_terms_sleep'] = !empty($autoterms_schedule['schedule_terms_sleep']) ? $autoterms_schedule['schedule_terms_sleep'] : '';
                $autoterm_data['schedule_terms_limit_days'] = !empty($autoterms_schedule['schedule_terms_limit_days']) ? $autoterms_schedule['schedule_terms_limit_days'] : '';

            }

            if (empty($autoterm_data) || $autoterm_data['cron_schedule'] !== 'hourly' || empty($autoterm_data['post_types'])) {
                return;
            }

            $schedule_terms_limit_days     = (int) $autoterm_data['schedule_terms_limit_days'];
            $schedule_terms_limit_days_sql = '';
            if ($schedule_terms_limit_days > 0) {
                $schedule_terms_limit_days_sql = 'AND post_date > "' . date('Y-m-d H:i:s', time() - $schedule_terms_limit_days * 86400) . '"';
            }

            $limit = (isset($autoterm_data['schedule_terms_batches']) && (int)$autoterm_data['schedule_terms_batches'] > 0) ? (int)$autoterm_data['schedule_terms_batches'] : 20;

            $sleep = (isset($autoterm_data['schedule_terms_sleep']) && (int)$autoterm_data['schedule_terms_sleep'] > 0) ? (int)$autoterm_data['schedule_terms_sleep'] : 0;
            
            if ($autoterm_schedule_exclude > 0) {
                $objects = (array) $wpdb->get_results("SELECT ID, post_title, post_content FROM {$wpdb->posts} LEFT JOIN {$wpdb->postmeta} ON ( ID = {$wpdb->postmeta}.post_id AND {$wpdb->postmeta}.meta_key = '_taxopress_autotermed' ) WHERE post_type IN ('" . implode("', '", $post_types) . "') AND {$wpdb->postmeta}.post_id IS NULL AND post_status IN ('" . implode("', '", $post_status) . "') {$schedule_terms_limit_days_sql} ORDER BY ID DESC LIMIT {$limit}");
            } else {
                $objects = (array) $wpdb->get_results("SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE post_type IN ('" . implode("', '", $post_types) . "') AND post_status IN ('" . implode("', '", $post_status) . "') {$schedule_terms_limit_days_sql} ORDER BY ID DESC LIMIT {$limit}");
            }

            if (!empty($objects)) {
                $current_post = 0;
                foreach ($objects as $object) {
                    $current_post++;
                        update_post_meta($object->ID, '_taxopress_autotermed', 1);
                        foreach ($autoterm_data as $autoterm) {
                            SimpleTags_Client_Autoterms::auto_terms_post($object, $autoterm['taxonomy'], $autoterm, true, 'hourly_cron_schedule', 'st_autoterms');
                        }  
                        unset($object);
                    if ($sleep > 0 && $current_post % $limit == 0) {
                        sleep($sleep);
                    }
                }
            }
        }

        public function taxopress_cron_autoterms_daily_execution()
        {

            global $wpdb;

            $autoterms_schedule = taxopress_get_autoterms_schedule_data();
            $autoterms = taxopress_get_autoterm_data();
            $autoterm_schedule_ids = isset($autoterms_schedule['autoterm_id']) ? (array)$autoterms_schedule['autoterm_id'] : [];
            $autoterm_data = [];
            foreach ($autoterm_schedule_ids as $autoterm_schedule_id) {
                if (!empty($autoterms[$autoterm_schedule_id])) {
                    $autoterm_data[$autoterm_schedule_id] = $autoterms[$autoterm_schedule_id];
                }
            }

            if (!empty($autoterm_data)) {
                $cron_schedule = isset($autoterms_schedule['cron_schedule']) ? $autoterms_schedule['cron_schedule'] : 'disable';
                $post_types = isset($autoterm_data['post_types']) ? (array)$autoterm_data['post_types'] : [];
                $post_status = isset($autoterm_data['post_status']) && is_array($autoterm_data['post_status']) ? $autoterm_data['post_status'] : ['publish'];
                $autoterm_schedule_exclude = isset($autoterms_schedule['autoterm_schedule_exclude']) ? (int)$autoterms_schedule['autoterm_schedule_exclude'] : 0;
    
                // make sure some auto terms settings are overriden by schedule specific settings
                $autoterm_data['terms_limit'] = !empty($autoterm_data['schedule_terms_limit']) ? $autoterm_data['schedule_terms_limit'] : '';
                $autoterm_data['autoterm_target'] = !empty($autoterm_data['schedule_autoterm_target']) ? $autoterm_data['schedule_autoterm_target'] : '';
                $autoterm_data['autoterm_word'] = !empty($autoterm_data['schedule_autoterm_word']) ? $autoterm_data['schedule_autoterm_word'] : '';
                $autoterm_data['autoterm_hash'] = !empty($autoterm_data['schedule_autoterm_hash']) ? $autoterm_data['schedule_autoterm_hash'] : '';
                $autoterm_data['replace_type'] = isset($autoterm_data['schedule_replace_type']) ? $autoterm_data['schedule_replace_type'] : '';

                // set auto term settings from schedule settings
                $autoterm_data['autoterm_for_schedule'] = $autoterm_data['cron_schedule'] = $cron_schedule;
                $autoterm_data['autoterm_schedule_exclude'] = !empty($autoterms_schedule['autoterm_schedule_exclude']) ? $autoterms_schedule['autoterm_schedule_exclude'] : '';
                $autoterm_data['schedule_terms_batches'] = !empty($autoterms_schedule['schedule_terms_batches']) ? $autoterms_schedule['schedule_terms_batches'] : '';
                $autoterm_data['schedule_terms_sleep'] = !empty($autoterms_schedule['schedule_terms_sleep']) ? $autoterms_schedule['schedule_terms_sleep'] : '';
                $autoterm_data['schedule_terms_limit_days'] = !empty($autoterms_schedule['schedule_terms_limit_days']) ? $autoterms_schedule['schedule_terms_limit_days'] : '';

            }

            if (empty($autoterm_data) || $autoterm_data['cron_schedule'] !== 'daily' || empty($autoterm_data['post_types'])) {
                return;
            }

            $schedule_terms_limit_days     = (int) $autoterm_data['schedule_terms_limit_days'];
            $schedule_terms_limit_days_sql = '';
            if ($schedule_terms_limit_days > 0) {
                $schedule_terms_limit_days_sql = 'AND post_date > "' . date('Y-m-d H:i:s', time() - $schedule_terms_limit_days * 86400) . '"';
            }

            $limit = (isset($autoterm_data['schedule_terms_batches']) && (int)$autoterm_data['schedule_terms_batches'] > 0) ? (int)$autoterm_data['schedule_terms_batches'] : 20;

            $sleep = (isset($autoterm_data['schedule_terms_sleep']) && (int)$autoterm_data['schedule_terms_sleep'] > 0) ? (int)$autoterm_data['schedule_terms_sleep'] : 0;

            if ($autoterm_schedule_exclude > 0) {
                $objects = (array) $wpdb->get_results("SELECT ID, post_title, post_content FROM {$wpdb->posts} LEFT JOIN {$wpdb->postmeta} ON ( ID = {$wpdb->postmeta}.post_id AND {$wpdb->postmeta}.meta_key = '_taxopress_autotermed' ) WHERE post_type IN ('" . implode("', '", $post_types) . "') AND {$wpdb->postmeta}.post_id IS NULL AND post_status IN ('" . implode("', '", $post_status) . "') {$schedule_terms_limit_days_sql} ORDER BY ID DESC LIMIT {$limit}");
            } else {
                $objects = (array) $wpdb->get_results("SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE post_type IN ('" . implode("', '", $post_types) . "') AND post_status IN ('" . implode("', '", $post_status) . "') {$schedule_terms_limit_days_sql} LIMIT {$limit}");
            }

                if (!empty($objects)) {
                    $current_post = 0;
                    foreach ($objects as $object) {
                        $current_post++;
                        update_post_meta($object->ID, '_taxopress_autotermed', 1);
                        SimpleTags_Client_Autoterms::auto_terms_post($object, $autoterm_data['taxonomy'], $autoterm_data, true, 'daily_cron_schedule', 'st_autoterms');
                        unset($object);
                        if ($sleep > 0 && $current_post % $limit == 0) {
                            sleep($sleep);
                        }
                }
            }
        }
    }
}