<?php

/*
  function fifu_bbp_extra_fields() {
  if (fifu_is_off('fifu_bbpress_fields'))
  return;

  $fifu = fifu_get_strings_meta_box_php();

  $image_url = get_post_meta(bbp_get_topic_id(), 'fifu_image_url', true);
  echo '<span class="dashicons dashicons-camera" style="font-size:17px"></span><label for="fifu_input_url">' . $fifu['title']['post']['image']() . '</label><br>';
  echo "<input type='text' name='fifu_input_url' value='" . $image_url . "' placeholder='" . $fifu['common']['image']() . "'><br>";

  $video_url = get_post_meta(bbp_get_topic_id(), 'fifu_video_url', true);
  echo '<span class="dashicons dashicons-video-alt3" style="font-size:17px"></span><label for="fifu_video_input_url">' . $fifu['title']['post']['video']() . '</label><br>';
  echo "<input type='text' name='fifu_video_input_url' value='" . $video_url . "' placeholder='" . $fifu['common']['video']() . "'>";
  }

  add_action('bbp_theme_before_topic_form_content', 'fifu_bbp_extra_fields');
 */

// forum/topic > image title

function fifu_bbp_theme_before_title() {
    if (fifu_is_off('fifu_bbpress_fields'))
        return;

    fifu_bbp_display();
}

add_action('bbp_theme_before_forum_title', 'fifu_bbp_theme_before_title');
add_action('bbp_theme_before_topic_title', 'fifu_bbp_theme_before_title');

function fifu_bbp_display() {
    global $post;
    if (!isset($post) || !isset($post->ID)) {
        return;
    }
    $url = fifu_main_image_url($post->ID, true);
    if (!$url)
        return;

    $img_tag = '<img src="' . $url . '" style="height:100px;width:100px;object-fit:cover" class="fifu-bbpress-img">';

    if (fifu_is_video_thumb($url)) {
        $video_url = get_post_meta($post->ID, 'fifu_video_url', true);
        $icon = '<span style="position:absolute;bottom:0px;left:0;text-decoration:none;background-color:black;color:white;" class="dashicons dashicons-external"></span>';
        echo '<a href="' . $video_url . '" target="_blank"><div class="alignleft" style="float:left;position:relative;padding-right:5px">' . $icon . $img_tag . '</div></a>';
        return;
    } else {
        echo '<div class="alignleft" style="float:left;position:relative;padding-right:5px">' . $img_tag . '</div>';
        return;
    }
}

// topic/reply > image content

function fifu_bbp_theme_after_content() {
    if (fifu_is_off('fifu_bbpress_fields'))
        return;

    $post_id = bbp_get_reply_id();
    $post_id = $post_id ?: bbp_get_topic_id();

    $url = fifu_main_image_url($post_id, true);
    if (!$url)
        return;

    $img_tag = '<img src="' . $url . '" style="width:100%;max-height:90vh;object-fit:contain" class="fifu-bbpress-img">';
    echo '<div style="width:100%">' . $img_tag . '</div>';
}

add_action('bbp_theme_after_reply_content', 'fifu_bbp_theme_after_content');
add_action('bbp_theme_after_topic_content', 'fifu_bbp_theme_after_content');

// topic/reply > URL

function fifu_bbp_theme_before_form_content() {
    if (fifu_is_off('fifu_bbpress_fields'))
        return;

    $fifu = fifu_get_strings_meta_box_php();

    $image_url = get_post_meta(bbp_get_topic_id(), 'fifu_image_url', true);
    echo "<input type='text' name='fifu_input_url' size='40' value='" . $image_url . "' placeholder='" . $fifu['common']['image']() . "'><br>";
}

add_action('bbp_theme_before_reply_form_tags', 'fifu_bbp_theme_before_form_content');
add_action('bbp_theme_before_topic_form_tags', 'fifu_bbp_theme_before_form_content');

