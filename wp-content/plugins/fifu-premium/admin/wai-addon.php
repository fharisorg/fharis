<?php

include 'rapid-addon.php';

final class FIFU_Add_On {

    protected static $instance;
    protected $add_on;

    static public function get_instance() {
        if (self::$instance == NULL) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function fifu_add_field_wai($cf, $title, $info) {
        $this->add_on->add_field($cf, "<div title=\"{$cf}\">{$title}</div>", 'text', null, $info, false, null);
    }

    protected function __construct() {
        $this->add_on = new RapidAddonFifu('<div style="color:#777"><span class="dashicons dashicons-camera" style="font-size:30px;padding-right:10px"></span> FIFU</div>', 'fifu_wai_addon');

        $fifu = fifu_get_strings_wai();
        $this->fifu_add_field_wai('fifu_image_url', $fifu['title']['image'](), null);
        $this->fifu_add_field_wai('fifu_image_alt', $fifu['title']['title'](), $fifu['info']['alt']());
        $this->fifu_add_field_wai('fifu_video_url', $fifu['title']['video'](), null);
        $this->fifu_add_field_wai('fifu_list_url', $fifu['title']['images'](), $fifu['info']['delimited']());
        $this->fifu_add_field_wai('fifu_list_alt', $fifu['title']['titles'](), $fifu['info']['alts']());
        $this->fifu_add_field_wai('fifu_list_video_url', $fifu['title']['videos'](), $fifu['info']['delimited']());
        $this->fifu_add_field_wai('fifu_slider_list_url', $fifu['title']['slider'](), $fifu['info']['delimited']());
        $this->fifu_add_field_wai('fifu_delimiter', $fifu['title']['delimiter'](), $fifu['info']['default']());
        $this->fifu_add_field_wai('fifu_isbn', $fifu['title']['isbn'](), null);
        $this->fifu_add_field_wai('fifu_asin', $fifu['title']['asin'](), null);
        $this->fifu_add_field_wai('fifu_finder_url', $fifu['title']['finder'](), $fifu['info']['finder']());
        $this->fifu_add_field_wai('fifu_redirection_url', $fifu['title']['redirection'](), null);

        $this->add_on->set_import_function([$this, 'fifu_wai_addon_save']);
        add_action('init', [$this, 'init']);
    }

    public function init() {
        $this->add_on->run();
    }

    public function fifu_wai_addon_save($post_id, $data, $import_options, $article) {
        $delimiter = $data['fifu_delimiter'] ?? '';
        $delimiter = empty($delimiter) ? '|' : $delimiter;

        $fields = array();

        /* if fifu_list_url, ignore fifu_image_url */
        if (empty($data['fifu_list_url'] ?? '')) {
            if (!empty($data['fifu_image_url'] ?? '') && empty($data['fifu_video_url'] ?? '') && empty($data['fifu_slider_list_url'] ?? ''))
                array_push($fields, 'fifu_image_url');
        } else
            array_push($fields, 'fifu_list_url');

        /* if fifu_list_alt, ignore fifu_image_alt */
        if (empty($data['fifu_list_alt'] ?? '')) {
            if (!empty($data['fifu_image_alt'] ?? ''))
                array_push($fields, 'fifu_image_alt');
        } else
            array_push($fields, 'fifu_list_alt');

        /* if fifu_list_video_url, ignore fifu_video_url */
        if (empty($data['fifu_list_video_url'] ?? '')) {
            if (!empty($data['fifu_video_url'] ?? '') && empty($data['fifu_slider_list_url'] ?? ''))
                array_push($fields, 'fifu_video_url');
        } else {
            array_push($fields, 'fifu_list_video_url');
        }

        /* if fifu_list_url or fifu_list_video_url, ignore fifu_slider_list_url */
        if (empty($data['fifu_list_url'] ?? '') && empty($data['fifu_list_video_url'] ?? '')) {
            if (!empty($data['fifu_slider_list_url'] ?? ''))
                array_push($fields, 'fifu_slider_list_url');
        }

        /* isbn */
        if (!empty($data['fifu_isbn'] ?? ''))
            array_push($fields, 'fifu_isbn');

        /* asin */
        if (!empty($data['fifu_asin'] ?? ''))
            array_push($fields, 'fifu_asin');

        /* finder */
        if (!empty($data['fifu_finder_url'] ?? ''))
            array_push($fields, 'fifu_finder_url');

        /* redirection */
        if (!empty($data['fifu_redirection_url'] ?? ''))
            array_push($fields, 'fifu_redirection_url');

        /* default */
        if (empty($fields)) {
            if (fifu_is_off('fifu_enable_default_url'))
                return;
        }

        $is_ctgr = ($article['post_type'] ?? '') == 'taxonomies';
        $update = false;
        foreach ($fields as $field) {
            $current_value = get_post_meta($post_id, $field, true);
            if ($current_value != ($data[$field] ?? '')) {
                $update = true;
                if (in_array($field, array('fifu_list_url', 'fifu_list_alt', 'fifu_list_video_url', 'fifu_slider_list_url')))
                    $value = str_replace($delimiter, '|', ($data[$field] ?? ''));
                else
                    $value = ($data[$field] ?? '');
                if ($is_ctgr)
                    update_term_meta($post_id, $field, $value);
                else
                    update_post_meta($post_id, $field, $value);
            }
        }

        if (!$update && !$this->add_on->can_update_image($import_options))
            return;

        fifu_wai_save($post_id, $is_ctgr, null);
        fifu_wai_video_save($post_id, $is_ctgr, null);
        fifu_slider_wai_save($post_id);

        fifu_db_insert_import($post_id, $is_ctgr);

        if (!fifu_get_transient('fifu_import_running')) {
            $request = new WP_REST_Request();
            $request->set_param('toggle', $is_ctgr ? 'fifu_toggle_importterm' : 'fifu_toggle_importpost');
            fifu_api_cron_add($request);
        }
        fifu_set_transient('fifu_import_running', true, 15);
    }
}

add_action('init', function () {
    FIFU_Add_On::get_instance();
}, 0);

