<?php

if (!class_exists('TaxoPress_Pro_Auto_Terms')) {
    /**
     * class TaxoPress_Pro_Auto_Terms
     */
    class TaxoPress_Pro_Auto_Terms
    {
        // class instance
        static $instance;

        /**
         * Construct the TaxoPress_Pro_Auto_Terms class
         */
        public function __construct()
        {
            add_action( 'taxopress_autoterms_after_autoterm_terms_to_use', [$this, 'taxopress_autoterms_after_autoterm_terms_to_use_field'] );
            add_action( 'taxopress_autoterms_after_autoterm_advanced', [$this, 'taxopress_pro_autoterm_advanced_field'] );
            add_action( 'taxopress_autoterms_schedule_autoterm_terms_to_use', [$this, 'taxopress_autoterms_schedule_autoterm_terms_to_use_field'] );

            add_action('admin_init', [$this, 'taxopress_pro_copy_autoterm']);
            add_filter('taxopress_autoterm_row_actions', [$this, 'taxopress_pro_copy_action'], 10, 2);
        }


        /** Singleton instance */
        public static function get_instance()
        {
            if (!isset(self::$instance)) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        public function taxopress_autoterms_schedule_autoterm_terms_to_use_field($current)
        {
            $ui = new taxopress_admin_ui();

            $default_select     = [
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

            $selected           = (isset($current) && isset($current['autoterm_for_schedule'])) ? taxopress_disp_boolean($current['autoterm_for_schedule']) : '';
            $default_select['selected'] = !empty($selected) ? $current['autoterm_for_schedule'] : '';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $ui->get_select_checkbox_input([
                'namearray'  => 'taxopress_autoterm',
                'name'       => 'autoterm_for_schedule',
                'class'      => 'autoterm_for_schedule autoterm-terms-when-to-field autoterm-terms-when-schedule fields-control',
                'labeltext'  => esc_html__('Schedule', 'taxopress-pro'),
                'aftertext'  => esc_html__('Enable Auto Terms for the "Schedule" feature.', 'taxopress-pro'),
                'selections' => $default_select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                'required'    => false,
            ]);
            
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $ui->get_number_input([
                'namearray' => 'taxopress_autoterm',
                'name'      => 'schedule_terms_limit',
                'textvalue' => isset($current['schedule_terms_limit']) ? esc_attr($current['schedule_terms_limit']) : '5',
                'class'      => 'autoterm_for_schedule autoterm-terms-when-to-field autoterm-terms-when-schedule',
                'labeltext' => esc_html__('Auto Terms Limit',
                    'taxopress-pro'),
                'helptext'  => esc_html__('Limit the number of generated Auto Terms. \'0\' for unlimited terms', 'taxopress-pro'),
                'min'       => '0',
                'required'  => false,
            ]);

            
            $selected           = (isset($current) && isset($current['schedule_autoterm_target'])) ? taxopress_disp_boolean($current['schedule_autoterm_target']) : '';
            $default_select['selected'] = !empty($selected) ? $current['schedule_autoterm_target'] : '';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $ui->get_select_checkbox_input([
                'namearray'  => 'taxopress_autoterm',
                'name'       => 'schedule_autoterm_target',
                'class'      => 'autoterm_for_schedule autoterm-terms-when-to-field autoterm-terms-when-schedule',
                'labeltext'  => esc_html__('Target content', 'taxopress-pro'),
                'aftertext'  => esc_html__('Only use Auto Terms on schedules with no added terms.', 'taxopress-pro'),
                'selections' => $default_select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ]);

            
            $selected           = (isset($current) && isset($current['schedule_autoterm_word'])) ? taxopress_disp_boolean($current['schedule_autoterm_word']) : '';
            $default_select['selected'] = !empty($selected) ? $current['schedule_autoterm_word'] : '';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $ui->get_select_checkbox_input([
                'namearray'  => 'taxopress_autoterm',
                'name'       => 'schedule_autoterm_word',
                'class'      => 'autoterm_for_schedule autoterm-terms-when-to-field autoterm-terms-when-schedule',
                'labeltext'  => esc_html__('Whole words', 'taxopress-pro'),
                'aftertext'  => esc_html__('Only add terms when the word is an exact match. Do not make matches for partial words.', 'taxopress-pro'),
                'selections' => $default_select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ]);

            
            $selected           = (isset($current) && isset($current['schedule_autoterm_hash'])) ? taxopress_disp_boolean($current['schedule_autoterm_hash']) : '';
            $default_select['selected'] = !empty($selected) ? $current['schedule_autoterm_hash'] : '';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $ui->get_select_checkbox_input([
                'namearray'  => 'taxopress_autoterm',
                'name'       => 'schedule_autoterm_hash',
                'class'      => 'autoterm_for_schedule autoterm-terms-when-to-field autoterm-terms-when-schedule',
                'labeltext'  => esc_html__('Hashtags', 'taxopress-pro'),
                'aftertext'  => esc_html__('Support hashtags symbols # in Auto Terms.', 'taxopress-pro'),
                'selections' => $default_select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ]);

            $taxonomy_replace_options     = [
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

            $selected   = (isset($current) && isset($current['schedule_replace_type'])) ? taxopress_disp_boolean($current['schedule_replace_type']) : '';
            $taxonomy_replace_options['selected'] = !empty($selected) ? $current['schedule_replace_type'] : '';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $ui->get_radio_input([
                'namearray'  => 'taxopress_autoterm',
                'name'       => 'schedule_replace_type',
                'class'      => 'autoterm_for_schedule autoterm-terms-when-to-field autoterm-terms-when-schedule',
                'labeltext'  => esc_html__('Auto Terms replacement settings',
                    'taxopress-pro'),
                    'aftertext'  => esc_html__('This option determines what happens when adding new terms to posts.', 'taxopress-pro'),
                'selections' => $taxonomy_replace_options,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ]);
        
        }

        public function taxopress_autoterms_after_autoterm_terms_to_use_field($current)
        {
            $taxopress_ai_settings = admin_url('admin.php?page=st_taxopress_ai');
            $ui = new taxopress_admin_ui();
            
            ?>
            <tr class="autoterm-description-tr">
                <td colspan="2">
                    <p class="taxopress-field-description description autoterm-terms-use-openai-notice">
                        <?php printf(esc_html__('OpenAI is an external service that can scan your content and suggest relevant terms. %1sClick here for details%2s.', 'taxopress-pro'), '<a target="blank" href="https://taxopress.com/docs/register-openai/">', '</a>'); ?>
                    </p>
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
            $selected           = (isset($current) && isset($current['autoterm_use_open_ai'])) ? taxopress_disp_boolean($current['autoterm_use_open_ai']) : '';
            $select['selected'] = !empty($selected) ? $current['autoterm_use_open_ai'] : '';

            $description_text = esc_html__('This will automatically add new terms from the OpenAI service. Before use, please test carefully using the preview feature.', 'taxopress-pro');
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $ui->get_select_checkbox_input([
                'namearray'  => 'taxopress_autoterm',
                'name'       => 'autoterm_use_open_ai',
                'class'      => 'autoterm_use_open_ai  autoterm-terms-to-use-field autoterm-terms-use-openai fields-control',
                'labeltext'  => esc_html__('Enable OpenAI', 'taxopress-pro'),
                'aftertext'  => $description_text,
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                'selections' => $select,
            ]);
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $ui->get_text_input([
                    'namearray' => 'taxopress_autoterm',
                    'name'      => 'open_ai_api_key',
                    'class'      => 'autoterm-terms-to-use-field autoterm-terms-use-openai',
                    'textvalue' => isset($current['open_ai_api_key']) ? esc_attr($current['open_ai_api_key']) : '',
                    'labeltext' => esc_html__('API Key', 'taxopress-pro'),
                    'helptext' => esc_html__('Enter your OpenAI API Key.', 'taxopress-pro'),
                    'required'  => false,
            ]);
            
            $options = [];
            $open_ai_models = [
                'gpt-3.5-turbo'     => esc_html__('gpt-3.5-turbo', 'taxopress-pro'),
                'gpt-4o-mini'       => esc_html__('gpt-4o-mini', 'taxopress-pro'),
                'gpt-4o'            => esc_html__('gpt-4o', 'taxopress-pro'),
                'chatgpt-4o-latest' => esc_html__('chatgpt-4o-latest', 'taxopress-pro'),
                'gpt-4.5-preview'            => esc_html__('gpt-4.5-preview', 'taxopress-pro'),
                'o3-mini'            => esc_html__('o3-mini', 'taxopress-pro'),
                'o1-mini'            => esc_html__('o1-mini', 'taxopress-pro'),
                'o1'            => esc_html__('o1', 'taxopress-pro'),
            ];
            foreach ($open_ai_models as $model_name => $model_label) {
                if ($model_name == 'gpt-3.5-turbo') {
                    $options[] = [
                        'attr'    => $model_name,
                        'text'    => $model_label,
                        'default' => 'true',
                    ];
                } else {
                    $options[] = [
                        'attr' => $model_name,
                        'text' => $model_label,
                    ];
                }
            }

            $select             = [
                'options' => $options,
            ];
            $selected           = isset($current) && !empty($current['open_ai_model']) ? taxopress_disp_boolean($current['open_ai_model']) : '';
            $select['selected'] = !empty($selected) ? $current['open_ai_model'] : '';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $ui->get_select_checkbox_input_main([
                'namearray'  => 'taxopress_autoterm',
                'name'       => 'open_ai_model',
                'class'      => 'autoterm-terms-to-use-field autoterm-terms-use-openai',
                'labeltext'  => esc_html__('OpenAI Models', 'taxopress-pro'),
                'aftertext'  => esc_html__('Some models availability depends on your subscription and access.', 'taxopress-pro'),
                'required'   => false,
                'selections' => $select,
            ]);

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
        $selected           = ( isset($current) && isset($current['open_ai_show_post_count']) ) ? taxopress_disp_boolean($current['open_ai_show_post_count']) : '';
        $select['selected'] = !empty($selected) ? $current['open_ai_show_post_count'] : '';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $ui->get_select_checkbox_input([
                'namearray'  => 'taxopress_autoterm',
                'name'       => 'open_ai_show_post_count',
                'labeltext'  => esc_html__('Show Term Post Count', 'taxopress-pro'),
                'aftertext'  => esc_html__('This will show the number of posts attached to the terms.', 'taxopress-pro'),
                'class'      => 'autoterm-terms-to-use-field autoterm-terms-use-openai',
                'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ]);


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
            $selected           = ( isset($current) && isset($current['open_ai_cache_result']) ) ? taxopress_disp_boolean($current['open_ai_cache_result']) : '';
            $select['selected'] = !empty($selected) ? $current['open_ai_cache_result'] : '';
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo $ui->get_select_checkbox_input([
                    'namearray'  => 'taxopress_autoterm',
                    'name'       => 'open_ai_cache_result',
                    'labeltext'  => esc_html__('Cache Results', 'taxopress-pro'),
                    'aftertext'  => esc_html__('By caching the results locally, new API requests will not be made unless the post title or content changes. This saves API usage.', 'taxopress-pro'),
                    'class'      => 'autoterm-terms-to-use-field autoterm-terms-use-openai',
                    'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                ]);

                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo $ui->get_textarea_input([
                    'namearray' => 'taxopress_autoterm',
                    'name'      => 'open_ai_tag_prompt',
                    'rows'      => '4',
                    'cols'      => '40',
                    'class'     => 'autoterm-terms-to-use-field autoterm-terms-use-openai',
                    'textvalue' => isset($current['open_ai_tag_prompt']) && !empty($current['open_ai_tag_prompt']) ? esc_attr($current['open_ai_tag_prompt']) : "Extract tags from the following content: '{content}'. Tags:",
                    'labeltext' => esc_html__(
                        'OpenAI Prompt (Beta)',
                        'taxopress-pro'
                    ),
                    'helptext'  => sprintf(esc_html__('%1s Click here for prompt documentation. %2s', 'taxopress-pro'), '<a target="_blank" href="https://taxopress.com/docs/openai-prompts/">', '</a>'),
                    'required'  => false,
                ]);

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
                $selected           = ( isset($current) && isset($current['open_ai_exclude_post_terms']) ) ? taxopress_disp_boolean($current['open_ai_exclude_post_terms']) : '';
                $select['selected'] = !empty($selected) ? $current['open_ai_exclude_post_terms'] : '';
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo $ui->get_select_checkbox_input([
                        'namearray'  => 'taxopress_autoterm',
                        'name'       => 'open_ai_exclude_post_terms',
                        'labeltext'  => esc_html__('Use only existing terms (Beta)', 'taxopress-pro'),
                        'aftertext'  => esc_html__('This will request that OpenAI only suggests existing terms. Check this box and include {post_terms} in the prompt.', 'taxopress-pro'),
                        'class'      => 'autoterm-terms-to-use-field autoterm-terms-use-openai',
                        'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    ]);
            ?>
            
            <?php
            if (!empty(SimpleTags_Plugin::get_option_value('enable_ibm_watson_ai_source'))) {
                ?>
            <tr class="autoterm-description-tr">
                <td colspan="2">
                    <p class="taxopress-field-description description autoterm-terms-use-ibm-watson-notice">
                        <?php printf(esc_html__('IBM Watson is an external service that can scan your content and suggest relevant terms. %1sClick here for details%2s.', 'taxopress-pro'), '<a target="blank" href="https://taxopress.com/docs/register-ibm/">', '</a>'); ?>
                    </p>
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
            $selected           = (isset($current) && isset($current['autoterm_use_ibm_watson'])) ? taxopress_disp_boolean($current['autoterm_use_ibm_watson']) : '';
            $select['selected'] = !empty($selected) ? $current['autoterm_use_ibm_watson'] : '';

            $description_text = esc_html__('This will automatically add new terms from the IBM Watson service. Before use, please test carefully using the preview feature.', 'taxopress-pro');
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $ui->get_select_checkbox_input([
                'namearray'  => 'taxopress_autoterm',
                'name'       => 'autoterm_use_ibm_watson',
                'class'      => 'autoterm_use_ibm_watson  autoterm-terms-to-use-field autoterm-terms-use-ibm-watson fields-control',
                'labeltext'  => esc_html__('Enable IBM Watson', 'taxopress-pro'),
                'aftertext'  => $description_text,
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                'selections' => $select,
            ]);

            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $ui->get_text_input([
                'namearray' => 'taxopress_autoterm',
                'name'      => 'ibm_watson_api_url',
                'class'      => 'autoterm-terms-to-use-field autoterm-terms-use-ibm-watson',
                'textvalue' => isset($current['ibm_watson_api_url']) ? esc_attr($current['ibm_watson_api_url']) : '',
                'labeltext' => esc_html__('API URL', 'taxopress-pro'),
                'helptext' => esc_html__('Enter your IBM Watson API URL.', 'taxopress-pro'),
                'required'  => false,
            ]);

            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $ui->get_text_input([
                'namearray' => 'taxopress_autoterm',
                'name'      => 'ibm_watson_api_key',
                'class'      => 'autoterm-terms-to-use-field autoterm-terms-use-ibm-watson',
                'textvalue' => isset($current['ibm_watson_api_key']) ? esc_attr($current['ibm_watson_api_key']) : '',
                'labeltext' => esc_html__('API Key', 'taxopress-pro'),
                'helptext' => esc_html__('Enter your IBM Watson API Key.', 'taxopress-pro'),
                'required'  => false,
            ]);

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
            $selected           = ( isset($current) && isset($current['ibm_watson_show_post_count']) ) ? taxopress_disp_boolean($current['ibm_watson_show_post_count']) : '';
            $select['selected'] = !empty($selected) ? $current['ibm_watson_show_post_count'] : '';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $ui->get_select_checkbox_input([
            'namearray'  => 'taxopress_autoterm',
            'name'       => 'ibm_watson_show_post_count',
            'labeltext'  => esc_html__('Show Term Post Count', 'taxopress-pro'),
            'aftertext'  => esc_html__('This will show the number of posts attached to the terms.', 'taxopress-pro'),
            'class'      => 'autoterm-terms-to-use-field autoterm-terms-use-ibm-watson',
            'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ]);
            
            
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
            $selected           = ( isset($current) && isset($current['ibm_watson_cache_result']) ) ? taxopress_disp_boolean($current['ibm_watson_cache_result']) : '';
            $select['selected'] = !empty($selected) ? $current['ibm_watson_cache_result'] : '';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $ui->get_select_checkbox_input([
                'namearray'  => 'taxopress_autoterm',
                'name'       => 'ibm_watson_cache_result',
                'labeltext'  => esc_html__('Cache Results', 'taxopress-pro'),
                'aftertext'  => esc_html__('By caching the results locally, new API requests will not be made unless the post title or content changes. This saves API usage.', 'taxopress-pro'),
                'class'      => 'autoterm-terms-to-use-field autoterm-terms-use-ibm-watson',
                'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ]);
            } else {
                 ?>
            <tr class="autoterm-description-tr">
                <td colspan="2">
                    <p class="taxopress-field-description description autoterm-terms-use-ibm-watson-notice">
                        <?php printf(esc_html__('This integration is no longer actively supported. If you still need to use it, you can enable it in the Legacy AI Sources settings.', 'taxopress-pro')); ?>
                    </p>
                </td>
            </tr>
            <?php
            }
            ?>
            <?php
            if (!empty(SimpleTags_Plugin::get_option_value('enable_dandelion_ai_source'))) {
                ?>
            <tr class="autoterm-description-tr">
                <td colspan="2">
                    <p class="taxopress-field-description description autoterm-terms-use-dandelion-notice">
                        <?php printf(esc_html__('Dandelion is an external service that can scan your content and suggest relevant terms. %1sClick here for details%2s.', 'taxopress-pro'), '<a target="blank" href="https://taxopress.com/docs/register-dandelion/">', '</a>'); ?>
                    </p>
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
            $selected           = (isset($current) && isset($current['autoterm_use_dandelion'])) ? taxopress_disp_boolean($current['autoterm_use_dandelion']) : '';
            $select['selected'] = !empty($selected) ? $current['autoterm_use_dandelion'] : '';

            $description_text = esc_html__('This will automatically add new terms from the Dandelion service. Before use, please test carefully using the preview feature.', 'taxopress-pro');
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $ui->get_select_checkbox_input([
                'namearray'  => 'taxopress_autoterm',
                'name'       => 'autoterm_use_dandelion',
                'class'      => 'autoterm_use_dandelion  autoterm-terms-to-use-field autoterm-terms-use-dandelion fields-control',
                'labeltext'  => esc_html__('Enable Dandelion', 'taxopress-pro'),
                'aftertext'  => $description_text,
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                'selections' => $select,
            ]);

            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $ui->get_text_input([
                'namearray' => 'taxopress_autoterm',
                'name'      => 'dandelion_api_token',
                'class'      => 'autoterm-terms-to-use-field autoterm-terms-use-dandelion',
                'textvalue' => isset($current['dandelion_api_token']) ? esc_attr($current['dandelion_api_token']) : '',
                'labeltext' => esc_html__('API Token', 'taxopress-pro'),
                'helptext' => esc_html__('Enter your Dandelion API Key.', 'taxopress-pro'),
                'required'  => false,
            ]);

            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $ui->get_number_input([
                'namearray' => 'taxopress_autoterm',
                'name'      => 'dandelion_api_confidence_value',
                'class'      => 'autoterm-terms-to-use-field autoterm-terms-use-dandelion',
                'textvalue' => isset($current['dandelion_api_confidence_value']) ? esc_attr($current['dandelion_api_confidence_value']) : '0.6',
                'labeltext' => esc_html__('API Confidence Value', 'taxopress-pro'),
                'helptext'  => esc_html__('Choose a value between 0 and 1. A high value such as 0.8 will provide a few, accurate suggestions. A low value such as 0.2 will produce more suggestions, but they may be less accurate.', 'taxopress-pro'),
                'other_attr' => 'step=".1" min="0" max="1"',
                'min' => '0',
                'required'  => false,
            ]);

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
            $selected           = ( isset($current) && isset($current['dandelion_show_post_count']) ) ? taxopress_disp_boolean($current['dandelion_show_post_count']) : '';
            $select['selected'] = !empty($selected) ? $current['dandelion_show_post_count'] : '';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $ui->get_select_checkbox_input([
            'namearray'  => 'taxopress_autoterm',
            'name'       => 'dandelion_show_post_count',
            'labeltext'  => esc_html__('Show Term Post Count', 'taxopress-pro'),
            'aftertext'  => esc_html__('This will show the number of posts attached to the terms.', 'taxopress-pro'),
            'class'      => 'autoterm-terms-to-use-field autoterm-terms-use-dandelion',
            'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ]);
            
            
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
            $selected           = ( isset($current) && isset($current['dandelion_cache_result']) ) ? taxopress_disp_boolean($current['dandelion_cache_result']) : '';
            $select['selected'] = !empty($selected) ? $current['dandelion_cache_result'] : '';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $ui->get_select_checkbox_input([
                'namearray'  => 'taxopress_autoterm',
                'name'       => 'dandelion_cache_result',
                'labeltext'  => esc_html__('Cache Results', 'taxopress-pro'),
                'aftertext'  => esc_html__('By caching the results locally, new API requests will not be made unless the post title or content changes. This saves API usage.', 'taxopress-pro'),
                'class'      => 'autoterm-terms-to-use-field autoterm-terms-use-dandelion',
                'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ]);
        } else {
                ?>
            <tr class="autoterm-description-tr">
                <td colspan="2">
                    <p class="taxopress-field-description description autoterm-terms-use-dandelion-notice">
                        <?php printf(esc_html__('This integration is no longer actively supported. If you still need to use it, you can enable it in the Legacy AI Sources settings.', 'taxopress-pro')); ?>
                    </p>
                </td>
            </tr>
            <?php
        }
            ?>
            <?php
            if (!empty(SimpleTags_Plugin::get_option_value('enable_lseg_ai_source'))) {
                ?>
            <tr class="autoterm-description-tr">
                <td colspan="2">
                    <p class="taxopress-field-description description autoterm-terms-use-lseg-refinitiv-notice">
                        <?php printf(esc_html__('LSEG / Refinitiv is an external service that can scan your content and suggest relevant terms. %1sClick here for details%2s.', 'taxopress-pro'), '<a target="blank" href="https://taxopress.com/docs/register-opencalais/">', '</a>'); ?>
                    </p>
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
            $selected           = (isset($current) && isset($current['autoterm_use_opencalais'])) ? taxopress_disp_boolean($current['autoterm_use_opencalais']) : '';
            $select['selected'] = !empty($selected) ? $current['autoterm_use_opencalais'] : '';

            $description_text = esc_html__('This will automatically add new terms from the LSEG / Refinitiv service. Before use, please test carefully using the preview feature.', 'taxopress-pro');
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $ui->get_select_checkbox_input([
                'namearray'  => 'taxopress_autoterm',
                'name'       => 'autoterm_use_opencalais',
                'class'      => 'autoterm_use_opencalais  autoterm-terms-to-use-field autoterm-terms-use-lseg-refinitiv fields-control',
                'labeltext'  => esc_html__('Enable LSEG / Refinitiv', 'taxopress-pro'),
                'aftertext'  => $description_text,
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                'selections' => $select,
            ]);

            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $ui->get_text_input([
                'namearray' => 'taxopress_autoterm',
                'name'      => 'open_calais_api_key',
                'class'      => 'autoterm-terms-to-use-field autoterm-terms-use-lseg-refinitiv',
                'textvalue' => isset($current['open_calais_api_key']) ? esc_attr($current['open_calais_api_key']) : '',
                'labeltext' => esc_html__('API Key', 'taxopress-pro'),
                'helptext' => esc_html__('Enter your LSEG / Refinitiv API Key.', 'taxopress-pro'),
                'required'  => false,
            ]);

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
            $selected           = ( isset($current) && isset($current['open_calais_show_post_count']) ) ? taxopress_disp_boolean($current['open_calais_show_post_count']) : '';
            $select['selected'] = !empty($selected) ? $current['open_calais_show_post_count'] : '';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $ui->get_select_checkbox_input([
            'namearray'  => 'taxopress_autoterm',
            'name'       => 'open_calais_show_post_count',
            'labeltext'  => esc_html__('Show Term Post Count', 'taxopress-pro'),
            'aftertext'  => esc_html__('This will show the number of posts attached to the terms.', 'taxopress-pro'),
            'class'      => 'autoterm-terms-to-use-field autoterm-terms-use-lseg-refinitiv',
            'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ]);
            
            
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
            $selected           = ( isset($current) && isset($current['open_calais_cache_result']) ) ? taxopress_disp_boolean($current['open_calais_cache_result']) : '';
            $select['selected'] = !empty($selected) ? $current['open_calais_cache_result'] : '';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $ui->get_select_checkbox_input([
                'namearray'  => 'taxopress_autoterm',
                'name'       => 'open_calais_cache_result',
                'labeltext'  => esc_html__('Cache Results', 'taxopress-pro'),
                'aftertext'  => esc_html__('By caching the results locally, new API requests will not be made unless the post title or content changes. This saves API usage.', 'taxopress-pro'),
                'class'      => 'autoterm-terms-to-use-field autoterm-terms-use-lseg-refinitiv',
                'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ]);
        } else {
                ?>
            <tr class="autoterm-description-tr">
                <td colspan="2">
                    <p class="taxopress-field-description description autoterm-terms-use-lseg-refinitiv-notice">
                        <?php printf(esc_html__('This integration is no longer actively supported. If you still need to use it, you can enable it in the Legacy AI Sources settings.', 'taxopress-pro')); ?>
                    </p>
                </td>
            </tr>
            <?php
        }
        }


        public function taxopress_pro_autoterm_advanced_field($current)
        {
            $ui = new taxopress_admin_ui();



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
            $selected           = (isset($current) && isset($current['autoterm_use_regex'])) ? taxopress_disp_boolean($current['autoterm_use_regex']) : '';
            $select['selected'] = !empty($selected) ? $current['autoterm_use_regex'] : '';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $ui->get_select_checkbox_input([
                'namearray'  => 'taxopress_autoterm',
                'name'       => 'autoterm_use_regex',
                'class'      => 'autoterm_use_regex',
                'labeltext'  => esc_html__('Regular Expressions', 'taxopress-pro'),
                'aftertext'  => esc_html__('Use Regular Expressions to change how Auto Terms analyzes your posts. This works for terms added when posts are saved or added via the "Schedule" feature.', 'taxopress-pro'),
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                'selections' => $select,
            ]);

            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $ui->get_text_input([
                'namearray' => 'taxopress_autoterm',
                'name'      => 'terms_regex_code',
                'class'     => 'terms_regex_code',
                'textvalue' => isset($current['terms_regex_code']) ? esc_attr(stripslashes($current['terms_regex_code'])) : '',
                'toplabel' => esc_html__('Regex code', 'taxopress-pro'),
                'labeltext'  => '',
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                'helptext'  => __(
                    'Example: <code>/\b({term})\b/i</code> will match the whole word while <code>/({term})/i</code> will match at any location even if it\'s part of another word. <code>{term}</code> will be replaced with the term name before the regex action.',
                    'taxopress-pro'
                ),
                'required'  => false,
            ]);
        }

        public function taxopress_action_copy_autoterm($autoterm_id) {
            $autoterms = taxopress_get_autoterm_data();

            if (array_key_exists($autoterm_id, $autoterms)) {
                $new_autoterm = $autoterms[$autoterm_id];
                $new_autoterm['title'] .= '-copy';

                $new_id = (int) get_option('taxopress_autoterm_ids_increament') + 1;
                $new_autoterm['ID'] = $new_id;

                $autoterms[$new_id] = $new_autoterm;

                update_option('taxopress_autoterms', $autoterms);
                update_option('taxopress_autoterm_ids_increament', $new_id);
            }

            wp_safe_redirect(
                add_query_arg([
                    'page' => 'st_autoterms',
                    'copied_autoterm' => 1,
                ], taxopress_admin_url('admin.php'))
            );
            exit();
        }

        public function taxopress_autoterms_copy_success_admin_notice() {
            echo taxopress_admin_notices_helper(esc_html__('Auto Terms successfully copied.', 'taxopress-pro'), true);
        }

        public function taxopress_copied_autoterm_filter_removable_query_args(array $args) {
            return array_merge($args, ['copied_autoterm']);
        }

        public function taxopress_copy_autoterm_filter_removable_query_args(array $args) {
            return array_merge($args, ['action', 'taxopress_autoterm', '_wpnonce']);
        }

        public function taxopress_pro_copy_autoterm() {
            if (isset($_GET['copied_autoterm']) && (int) $_GET['copied_autoterm'] === 1) {
                add_action('admin_notices', [$this, 'taxopress_autoterms_copy_success_admin_notice']);
                add_filter('removable_query_args', [$this, 'taxopress_copied_autoterm_filter_removable_query_args']);
            }

            if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'taxopress-copy-autoterm') {
                $nonce = sanitize_text_field($_REQUEST['_wpnonce']);
                if (wp_verify_nonce($nonce, 'autoterm-action-request-nonce')) {
                    $this->taxopress_action_copy_autoterm(sanitize_text_field($_REQUEST['taxopress_autoterm']));
                }
                add_filter('removable_query_args', [$this, 'taxopress_copy_autoterm_filter_removable_query_args']);
            }
        }

        public function taxopress_pro_copy_action($actions, $item) {
            $actions['copy'] = sprintf(
                '<a href="%s" class="copy-autoterm">%s</a>',
                add_query_arg([
                    'page' => 'st_autoterms',
                    'action' => 'taxopress-copy-autoterm',
                    'taxopress_autoterm' => esc_attr($item['ID']),
                    '_wpnonce' => wp_create_nonce('autoterm-action-request-nonce')
                ], admin_url('admin.php')),
                __('Copy', 'taxopress-pro')
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
    }
}