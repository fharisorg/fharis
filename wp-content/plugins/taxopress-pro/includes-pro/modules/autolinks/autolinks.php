<?php

if (!class_exists('TaxoPress_Pro_Auto_Links')) {
    /**
     * class TaxoPress_Pro_Auto_Links
     */
    class TaxoPress_Pro_Auto_Links
    {
        // class instance
        static $instance;

        /**
         * Construct the TaxoPress_Pro_Auto_Links class
         */
        public function __construct()
        {
            
            add_action('taxopress_autolinks_after_html_exclusions', [$this, 'taxopress_pro_autolinks_after_html_exclusions'], 10, 2);
            add_action('taxopress_autolinks_after_html_exclusions_tr', [$this, 'taxopress_pro_autolinks_after_html_exclusions_tr'], 10, 2);
            add_action('admin_init', [$this, 'taxopress_pro_copy_autolink']);
            add_filter('taxopress_autolink_row_actions', [$this, 'taxopress_pro_copy_action'], 10, 2);
        }


        /** Singleton instance */
        public static function get_instance()
        {
            if (!isset(self::$instance)) {
                self::$instance = new self();
            }

            return self::$instance;
        }


        public function taxopress_pro_autolinks_after_html_exclusions_tr($current, $ui){

           
            $shortcodes_exclusion_entries = (!empty($current['shortcodes_exclusion_entries']) && is_array($current['shortcodes_exclusion_entries'])) ? $current['shortcodes_exclusion_entries'] : [];
            $blocks_exclusion_entries = (!empty($current['blocks_exclusion_entries']) && is_array($current['blocks_exclusion_entries'])) ? $current['blocks_exclusion_entries'] : [];

            echo '<tr valign="top"><th scope="row"><label>' . esc_html__(
                'Prevent Auto Links on Shortcodes and Blocks',
                'simple-tags'
            ) . '</label><br /><small style=" color: #646970;">' . esc_html__(
                'Terms inside these shortcodes and blocks will not have Auto Links applied.',
                'simple-tags'
            ) . '</small></th><td>
            <table class="visbile-table st-custom-exclusion-table">';
            

            echo '<tr valign="top" class="html-exclusions-customs-row"><th style="padding: 0;" scope="row"><hr /></th><td style="padding: 0;"><hr /></td></tr>';
            // Shortcodes
            if (!empty($shortcodes_exclusion_entries)) : 
                foreach ($shortcodes_exclusion_entries as $shortcodes_exclusion_entry) :
                    echo '<tr valign="top" class="html-exclusions-customs-row"><th scope="row"><label for="' . esc_attr($shortcodes_exclusion_entry) . '">[' . esc_html($shortcodes_exclusion_entry) . ']</label></th><td>';
                    echo '<input type="hidden" name="shortcodes_exclusion_entries[]" value="' . esc_attr($shortcodes_exclusion_entry) . '" />';

                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo $ui->get_check_input([
                        'checkvalue' => $shortcodes_exclusion_entry,
                        'checked'    => (!empty($current['shortcodes_exclusion']) && is_array($current['shortcodes_exclusion']) && in_array(
                            $shortcodes_exclusion_entry,
                            $current['shortcodes_exclusion'],
                            true
                        )) ? 'true' : 'false',
                        'name'       => esc_attr($shortcodes_exclusion_entry),
                        'namearray'  => 'shortcodes_exclusion',
                        'textvalue'  => esc_attr($shortcodes_exclusion_entry),
                        'labeltext'  => '',
                        'add_delete' => true,
                        'wrap'       => false,
                    ]);
                    echo '</td></tr>';
                endforeach;
            endif;

            echo '<tr valign="top" class="html-exclusions-customs-row shortcodes-exclusions-placeholder"></tr>';
            echo '<tr valign="top" class="html-exclusions-customs-row"><th colspan="2" style="padding: 0; padding-top: 15px;"><span>' . esc_html__('Shortcode Name', 'taxopress-pro') . '</span> </th></tr>';
            echo '<tr valign="top" class="html-exclusions-customs-row shortcodes-exclusions-form st-exclusion-custom-form"><th colspan="2" style="padding: 0;margin: 0;padding: 0;" scope="row"><div class="flex-parent"><input style="width: 100%;" type="text" class="shortcode-name" placeholder="E.g: read_more" /> <button class="new-element-submit button" style="">' . esc_html__('Add', 'taxopress-pro') . '</button></div></th></tr>';
            echo '<tr valign="top" class="html-exclusions-customs-row"><td colspan="2" style="padding: 0;"><span style="color: #646970;">' . esc_html__('Enter the shortcode name without [ ] or parameters.', 'taxopress-pro') . '</span></td></tr>';


            echo '<tr valign="top" class="html-exclusions-customs-row"><th style="padding: 0;" scope="row"><hr /></th><td style="padding: 0;"><hr /></td></tr>';

            // Blocks
            if (!empty($blocks_exclusion_entries) && !empty($blocks_exclusion_entries['name'])) : 
                $block_entries_names    = $blocks_exclusion_entries['name'];
                $block_entries_labels   = $blocks_exclusion_entries['label'];
                $block_entries_slugs    = $blocks_exclusion_entries['slug'];
                foreach ($block_entries_names as $entry_index => $entry_name) :
                    $entry_name     = $block_entries_names[$entry_index];
                    $entry_label    = $block_entries_labels[$entry_index];
                    $entry_slug     = $block_entries_slugs[$entry_index];

                    echo '<tr valign="top" class="html-exclusions-customs-row"><th scope="row"><label for="' . esc_attr($entry_slug) . '">' . esc_html($entry_label) . '</label></th><td>';
                    echo '<input type="hidden" name="blocks_exclusion_entries[name][]" value="' . esc_attr($entry_name) . '" />';
                    echo '<input type="hidden" name="blocks_exclusion_entries[label][]" value="' . esc_attr($entry_label) . '" />';
                    echo '<input type="hidden" name="blocks_exclusion_entries[slug][]" value="' . esc_attr($entry_slug) . '" />';

                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo $ui->get_check_input([
                        'checkvalue' => $entry_name,
                        'checked'    => (!empty($current['blocks_exclusion']) && is_array($current['blocks_exclusion']) && in_array(
                            $entry_name,
                            $current['blocks_exclusion'],
                            true
                        )) ? 'true' : 'false',
                        'name'       => esc_attr($entry_name),
                        'namearray'  => 'blocks_exclusion',
                        'textvalue'  => esc_attr($entry_name),
                        'labeltext'  => '',
                        'add_delete' => true,
                        'wrap'       => false,
                    ]);
                    echo '</td></tr>';
                endforeach;
            endif;

            echo '<tr valign="top" class="html-exclusions-customs-row blocks-exclusions-placeholder"></tr>';
            echo '<tr valign="top" class="html-exclusions-customs-row"><th colspan="2" style="padding: 0; padding-top: 15px;"><span>' . esc_html__('Search Block', 'taxopress-pro') . '</span> </th></tr>';
            echo '<tr valign="top" class="html-exclusions-customs-row blocks-exclusions-form st-exclusion-custom-form"><th colspan="2" style="padding: 0;margin: 0;padding: 0;" scope="row"><div class="flex-parent"><select style="width: 100%;" class="block-name" data-placeholder="' . esc_html__("Search...", "taxopress-pro") .'" data-nonce="'. esc_attr(wp_create_nonce('taxopress-blocks-search')) .'"></select></th></tr>';
            echo '<tr valign="top" class="html-exclusions-customs-row"><td colspan="2" style="padding: 0;"><span style="color: #646970;">' . esc_html__('Search and select blocks.', 'taxopress-pro') . '</span></td></tr>';

            // end table
            echo '</table></td></tr>';

        }


        public function taxopress_pro_autolinks_after_html_exclusions($current, $ui){

            $html_exclusions_customs = (!empty($current['html_exclusion_customs_entry']) && is_array($current['html_exclusion_customs_entry'])) ? $current['html_exclusion_customs_entry'] : [];

            
            // add line break
            echo '<tr valign="top" class="html-exclusions-customs-row"><th style="padding: 0;" scope="row"><hr /></th><td style="padding: 0;"><hr /></td></tr>';

            if (!empty($html_exclusions_customs)) : 
                foreach ($html_exclusions_customs as $html_exclusions_custom) :
                    echo '<tr valign="top" class="html-exclusions-customs-row"><th scope="row"><label for="' . esc_attr($html_exclusions_custom) . '">' . esc_html($html_exclusions_custom) . '</label></th><td>';
                    echo '<input type="hidden" name="html_exclusion_customs_entry[]" value="' . esc_attr($html_exclusions_custom) . '" />';

                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo $ui->get_check_input([
                        'checkvalue' => $html_exclusions_custom,
                        'checked'    => (!empty($current['html_exclusion_customs']) && is_array($current['html_exclusion_customs']) && in_array(
                            $html_exclusions_custom,
                            $current['html_exclusion_customs'],
                            true
                        )) ? 'true' : 'false',
                        'name'       => esc_attr($html_exclusions_custom),
                        'namearray'  => 'html_exclusion_customs',
                        'textvalue'  => esc_attr($html_exclusions_custom),
                        'labeltext'  => esc_html($html_exclusions_custom),
                        'labeldescription' => true,
                        'add_delete' => true,
                        'wrap'       => false,
                    ]);

                    echo '</td></tr>';
                endforeach;
            endif;

            //add new form
            echo '<tr valign="top" class="html-exclusions-customs-row html-exclusions-customs-form" style="display: none;"><th style="padding: 0;" scope="row"><br />' . esc_html__('Element tag', 'taxopress-pro') . '</th><td style="padding: 0;display: flex;"><input style="width: 100%;margin-top: 15px;" type="text" class="element-name" placeholder="E.g: blockquote" /> <button class="new-element-submit button" style="margin-top: 15px;">' . esc_html__('Add', 'taxopress-pro') . '</button></td></tr>';

            //add new button
            echo '<tr valign="top" class="html-exclusions-customs-row html-exclusions-customs-add"><th style="padding: 0;" scope="row"><br />' . esc_html__('Add Element', 'taxopress-pro') . '</th><td style="padding: 0;text-align: right;"><br /><button class="button show-autolink-custom-html-exclusions">' . esc_html__('New Element', 'taxopress-pro') . '</button></td></tr>';


        }

        public function taxopress_pro_copy_autolink()
        {
            if (isset($_GET['copied_autolink']) && (int) $_GET['copied_autolink'] === 1) {
                add_action('admin_notices', [$this, 'taxopress_autolink_copy_success_admin_notice']);
                add_filter('removable_query_args', [$this, 'taxopress_copied_autolink_filter_removable_query_args']);
            }

            if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'taxopress-copy-autolink') {
                $nonce = sanitize_text_field($_REQUEST['_wpnonce']);
                if (wp_verify_nonce($nonce, 'autolink-action-request-nonce')) {
                    $this->taxopress_action_copy_autolink(sanitize_text_field($_REQUEST['taxopress_autolink']));
                }
                add_filter('removable_query_args', [$this, 'taxopress_copy_autolink_filter_removable_query_args']);
            }
        }

        public function taxopress_action_copy_autolink($autolink_id)
        {
            if (!taxopress_is_pro_version()) {
                wp_safe_redirect(admin_url('admin.php?page=st_autolinks&add=new_item'));
                exit;
            }

            $autolinks = taxopress_get_autolink_data();

            if (array_key_exists($autolink_id, $autolinks)) {
                $new_autolink = $autolinks[$autolink_id];
                $new_autolink['title'] .= '-copy';

                $new_id = (int) get_option('taxopress_autolink_ids_increament') + 1;
                $new_autolink['ID'] = $new_id;

                $autolinks[$new_id] = $new_autolink;

                update_option('taxopress_autolinks', $autolinks);
                update_option('taxopress_autolink_ids_increament', $new_id);
            }

            wp_safe_redirect(
                add_query_arg([
                    'page'             => 'st_autolinks',
                    'copied_autolink'  => 1,
                ], taxopress_admin_url('admin.php'))
            );
            exit();
        }

        public function taxopress_autolink_copy_success_admin_notice()
        {
            echo taxopress_admin_notices_helper(esc_html__('Autolink successfully copied.', 'simple-tags'), true);
        }

        public function taxopress_copied_autolink_filter_removable_query_args(array $args)
        {
            return array_merge($args, ['copied_autolink']);
        }

        public function taxopress_copy_autolink_filter_removable_query_args(array $args)
        {
            return array_merge($args, ['action', 'taxopress_autolink', '_wpnonce']);
        }

        public function taxopress_pro_copy_action($actions, $item)
        {
            $actions['copy'] = sprintf(
                '<a href="%s" class="copy-autolink">%s</a>',
                add_query_arg([
                    'page'             => 'st_autolinks',
                    'action'           => 'taxopress-copy-autolink',
                    'taxopress_autolink' => esc_attr($item['ID']),
                    '_wpnonce'         => wp_create_nonce('autolink-action-request-nonce')
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
    }
}
