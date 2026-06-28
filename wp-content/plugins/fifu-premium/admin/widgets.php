<?php

class Fifu_Widget_Image extends WP_Widget {

    public function __construct() {
        $fifu = fifu_get_strings_widget();
        parent::__construct(
                'fifu_widget_image', // Base ID
                '(FIFU) ' . $fifu['title']['media'](), // Name
                array('description' => $fifu['description']['media'](),) // Args
        );
    }

    public function widget($args, $instance) {
        if (isset($args['before_widget'])) {
            echo $args['before_widget'];
        }
        global $post;
        if (!isset($post->ID)) {
            if (isset($args['after_widget'])) {
                echo $args['after_widget'];
            }
            return;
        }

        $post_id = $post->ID;
        $url = fifu_main_image_url($post_id, true);
        fifu_process_external_url($url, get_post_thumbnail_id($post_id), null);

        echo '<img src="' . $url . '">';
        if (isset($args['after_widget'])) {
            echo $args['after_widget'];
        }
    }

    public function form($instance) {
        include 'html/widget-image.html';
    }

    public function update($new_instance, $old_instance) {
        $instance = array();
        return $instance;
    }
}

class Fifu_Widget_Grid extends WP_Widget {

    public function __construct() {
        $fifu = fifu_get_strings_widget();
        parent::__construct(
                'fifu_widget_grid', // Base ID
                '(FIFU) ' . $fifu['title']['grid'](), // Name
                array('description' => $fifu['description']['grid'](),) // Args
        );
    }

    public function widget($args, $instance) {
        extract($args);
        if (isset($args['before_widget'])) {
            echo $args['before_widget'];
        }

        if (!isset($instance['rows']) || !isset($instance['columns'])) {
            if (isset($args['after_widget'])) {
                echo $args['after_widget'];
            }
            return;
        }

        global $post;
        $post_id = $post->ID;
        $url = fifu_main_image_url($post_id, true);
        fifu_process_external_url($url, get_post_thumbnail_id($post_id), null);

        wp_register_style('fifu-grid', plugins_url('/html/css/grid.css', __FILE__), array(), fifu_version_number_enq());
        wp_enqueue_style('fifu-grid');

        $urls = fifu_db_get_slider_urls($post_id);
        if (!$urls) {
            if (isset($args['after_widget'])) {
                echo $args['after_widget'];
            }
            return;
        }

        // params
        $rows = (int) ($instance['rows'] ?? 1);
        $columns = (int) ($instance['columns'] ?? 1);
        $total = sizeof($urls);

        $width = (int) (100 / ($columns > $total ? $total : $columns));
        $style = sprintf('-ms-flex: %d%%; flex: %d%%; max-width: %d%%;', $width, $width, $width);

        echo '<div class="fifu-grid-row">';

        for ($i = 0; $i < $columns; $i++) {
            echo '<div class="fifu-grid-column" style="' . $style . '">';
            for ($j = $i; $j < $total && $j < $rows * $columns; $j += $columns) {
                echo '<img class="fifu-grid-img" src="' . ($urls[$j]->meta_value ?? '') . '" style="width:100%">';
            }
            echo '</div>';
        }

        echo '</div>';

        if (isset($args['after_widget'])) {
            echo $args['after_widget'];
        }
    }

    public function form($instance) {
        $fifu = fifu_get_strings_widget();
        $rows = ($instance['rows'] ?? 1);
        $columns = ($instance['columns'] ?? 1);
        include 'html/widget-grid.html';
    }

    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['rows'] = ($new_instance['rows'] ?? 1);
        $instance['columns'] = ($new_instance['columns'] ?? 1);
        return $instance;
    }
}

class Fifu_Widget_Gallery extends WP_Widget {

    public function __construct() {
        $fifu = fifu_get_strings_widget();
        parent::__construct(
                'fifu_widget_gallery', // Base ID
                '(FIFU) ' . $fifu['title']['gallery'](), // Name
                array('description' => $fifu['description']['gallery'](),) // Args
        );
    }

    public function widget($args, $instance) {
        if (isset($args['before_widget'])) {
            echo $args['before_widget'];
        }
        global $post;
        if (!isset($post->ID)) {
            if (isset($args['after_widget'])) {
                echo $args['after_widget'];
            }
            return;
        }

        echo fifu_gallery_get_html(
                $post->ID, null,
                'fifu-woo-gallery woocommerce-product-gallery--with-images woocommerce-product-gallery--columns-4 images',
                ''
        );
        if (isset($args['after_widget'])) {
            echo $args['after_widget'];
        }
    }

    public function form($instance) {
        $fifu = fifu_get_strings_widget();
        include 'html/widget-gallery.html';
    }

    public function update($new_instance, $old_instance) {
        $instance = array();
        return $instance;
    }
}

add_action('widgets_init', 'fifu_register_widgets');

function fifu_register_widgets() {
    register_widget('Fifu_Widget_Image');
    register_widget('Fifu_Widget_Grid');
    register_widget('Fifu_Widget_Gallery');
}

add_action('admin_head-widgets.php', 'fifu_add_icon_to_custom_widget');

function fifu_add_icon_to_custom_widget() {
    echo
    '
        <style>
            *[id*="fifu_widget_"] > div.widget-top > div.widget-title > h3:before {
                font-family: "dashicons";
                content: "\f306";
                width:18px;
                float:left;
                height:6px;
                font-size:15px;
            }
		</style>
    ';
}

