<?php

class Elementor_FIFU_Video_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'fifu-video-elementor';
    }

    public function get_title() {
        $strings = fifu_get_strings_elementor();
        return '(FIFU) ' . $strings['title']['video']();
    }

    public function get_icon() {
        return 'eicon-youtube';
    }

    public function get_categories() {
        return ['basic'];
    }

    protected function register_controls() {
        $strings = fifu_get_strings_elementor();

        $this->start_controls_section(
                'content_section_video',
                [
                    'label' => $strings['section']['video'](),
                    'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
                ]
        );
        $this->add_control(
                'fifu_video_input_url',
                [
                    'label' => $strings['control']['video'](),
                    'show_label' => true,
                    'label_block' => true,
                    'type' => \Elementor\Controls_Manager::TEXT,
                    'input_type' => 'url',
                    'placeholder' => 'https://youtube.com/watch?v=ID',
                ]
        );
        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $video_url = esc_url($settings['fifu_video_input_url'] ?? '');

        if ($video_url) {
            $image_url = fifu_video_img_large($video_url, null, null);
            $image_url = esc_url($image_url); // sanitize before output
            if ($image_url) {
                echo '<div style="width:100%;text-align:center;">';
                echo '<img class="oembed-elementor-widget fifu-elementor-image" src="' . $image_url . '" alt="" />';
                echo '</div>';
            }
        }
    }
}

