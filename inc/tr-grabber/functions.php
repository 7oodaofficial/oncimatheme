<?php
defined('ABSPATH') || exit;

if (!function_exists('trgrabber_info')):
function trgrabber_info($show = NULL, $tag = 'span', $class = '', $display = TRUE) {
    global $post;
    $return = '';
    $class_attr = $class == '' ? '' : ' class="' . $class . '"';
    if ($show == 'year') {
        $date_field = get_post_meta($post->ID, TR_GRABBER_FIELD_DATE, true);
        $year = '';
        if ($date_field != '') {
            $parts = explode('-', $date_field);
            $year = $parts[0] ?? '';
        }
        $year = $year == '' ? __('Unknown', 'onwatch') : $year;
        $return .= '<' . $tag . $class_attr . '>' . $year . '</' . $tag . '>';
    }
    if ($show == 'runtime') {
        $runtime_field = get_post_meta($post->ID, TR_GRABBER_FIELD_RUNTIME, true);
        if (tr_grabber_type($post->ID) == 2 && is_array($runtime_field) && !empty($runtime_field)) {
            $runtime_field = implode('m, ', $runtime_field) . 'm ';
        } elseif (tr_grabber_type($post->ID) == 2 && !is_array($runtime_field) && !empty($runtime_field)) {
            $runtime_field = implode('m, ', explode(',', $runtime_field)) . 'm';
        } elseif (!empty($runtime_field)) {
            $runtime_field = $runtime_field;
        } else {
            $runtime_field = __('Unknown', 'onwatch');
        }
        if ($runtime_field != '') {
            $return .= '<' . $tag . $class_attr . '>' . $runtime_field . '</' . $tag . '>';
        }
    }
    if ($display == TRUE) { echo $return; } else { return $return; }
}
endif;

if (!function_exists('trgrabber_img')):
function trgrabber_img($id, $size, $title = NULL, $taxonomy = NULL, $text = 0, $exclude = NULL) {
    $return = '';
    if ($taxonomy == 'episodes') {
        $image_hotlink = get_term_meta($id, 'still_path_hotlink', true);
        $image = get_term_meta($id, 'still_path', true);
        if (!empty($image)) {
            $return = $image;
        } elseif (!empty($image_hotlink)) {
            $s = $size == 'episode' ? 'w185' : ($size == 'episodes' ? 'w92' : $size);
            if (filter_var($image_hotlink, FILTER_VALIDATE_URL) === FALSE) {
                $return = '<img src="//image.tmdb.org/t/p/' . $s . $image_hotlink . '" alt="' . sprintf(__('Image %s', 'onwatch'), $title) . '">';
            } else {
                $return = '<img src="' . $image_hotlink . '" alt="' . sprintf(__('Image %s', 'onwatch'), $title) . '">';
            }
        }
    } elseif ($taxonomy == 'seasons') {
        $image_hotlink = get_term_meta($id, 'poster_path_hotlink', true);
        $image = get_term_meta($id, 'poster_path', true);
        if (!empty($image)) {
            $return = $image;
        } elseif (!empty($image_hotlink)) {
            $s = $size == 'thumbnail' ? 'w185' : $size;
            if (filter_var($image_hotlink, FILTER_VALIDATE_URL) === FALSE) {
                $return = '<img src="//image.tmdb.org/t/p/' . $s . $image_hotlink . '" alt="' . sprintf(__('Image %s', 'onwatch'), $title) . '">';
            } else {
                $return = '<img src="' . $image_hotlink . '" alt="' . sprintf(__('Image %s', 'onwatch'), $title) . '">';
            }
        }
    } else {
        if (get_the_post_thumbnail($id, $size)) {
            $return = get_the_post_thumbnail($id, $size);
        } elseif (get_post_meta($id, TR_GRABBER_POSTER_HOTLINK, true) != '') {
            $s = $size == 'thumbnail' ? 'w185' : ($size == 'widget' ? 'w92' : $size);
            $hotlink = get_post_meta($id, TR_GRABBER_POSTER_HOTLINK, true);
            if (filter_var($hotlink, FILTER_VALIDATE_URL) === FALSE) {
                $return = '<img src="//image.tmdb.org/t/p/' . $s . $hotlink . '" alt="' . sprintf(__('Image %s', 'onwatch'), get_the_title($id)) . '">';
            } else {
                $return = '<img src="' . $hotlink . '" alt="' . sprintf(__('Image %s', 'onwatch'), get_the_title($id)) . '">';
            }
        }
    }
    return empty($return) ? '<img src="' . get_template_directory_uri() . '/resources/assets/img/placeholder.svg" alt="' . $title . '">' : $return;
}
endif;

if (!function_exists('tr_posts_shortcode')):
function tr_posts_shortcode($atts) {
    $atts = shortcode_atts(array(
        'post_type'    => 'movies',
        'number'       => 10,
        'orderby'      => 'date',
        'order'        => 'DESC',
        'category'     => '',
        'title'        => '',
        'show_title'   => 'true',
    ), $atts);
    $args = array(
        'post_type'      => explode(',', $atts['post_type']),
        'posts_per_page' => intval($atts['number']),
        'orderby'        => $atts['orderby'],
        'order'          => $atts['order'],
        'post_status'    => 'publish',
    );
    if (!empty($atts['category'])) {
        $args['tax_query'] = array(array(
            'taxonomy' => 'category',
            'field'    => 'slug',
            'terms'    => explode(',', $atts['category']),
        ));
    }
    $query = new WP_Query($args);
    ob_start();
    if ($query->have_posts()) {
        if ($atts['show_title'] == 'true' && !empty($atts['title'])) {
            echo '<h2 class="tr-posts-title">' . esc_html($atts['title']) . '</h2>';
        }
        echo '<div class="tr-posts-grid">';
        while ($query->have_posts()) {
            $query->the_post();
            $pid = get_the_ID();
            $pt = get_post_type();
            echo '<div class="tr-posts-item">';
            if ($pt == 'movies') {
                get_template_part('resources/views/components/card', 'movie');
            } else {
                get_template_part('resources/views/components/card', 'series');
            }
            echo '</div>';
        }
        echo '</div>';
        wp_reset_postdata();
    }
    return ob_get_clean();
}
endif;
add_shortcode('trposts', 'tr_posts_shortcode');

if (!function_exists('trgrabber_count_seasons')):
function trgrabber_count_seasons($post_id, $display = true, $rest = false) {
    $term_list = wp_get_post_terms($post_id, 'seasons', array("fields" => "all"));
    $return = 0;
    if (!is_wp_error($term_list) && isset($term_list)) {
        $total_terms = count($term_list);
        if ($rest == true && $total_terms > 0) { $total_terms = $total_terms - 1; }
        $return = $total_terms;
    }
    if ($display == true) { echo $return; } else { return $return; }
}
endif;

if (!function_exists('trgrabber_count_episodes')):
function trgrabber_count_episodes($post_id, $season_current = NULL, $display = true, $rest = false) {
    $term_list = wp_get_post_terms($post_id, 'episodes', array("fields" => "all"));
    $return = 0;
    if (!is_wp_error($term_list) && isset($term_list)) {
        if (isset($season_current)) {
            $array_episodes_season = array();
            foreach ($term_list as &$count_episode_season) {
                if (get_term_meta($count_episode_season->term_id, 'season_number', true) == $season_current) {
                    $array_episodes_season[] = $count_episode_season->term_id;
                }
            }
            $return = count($array_episodes_season);
            if ($rest == true && $return > 0) { $return = $return - 1; }
        } else {
            $return = count($term_list);
            if ($rest == true && $return > 0) { $return = $return - 1; }
        }
    }
    if ($display == true) { echo $return; } else { return $return; }
}
endif;

if (!function_exists('trgrabber_base64en')):
function trgrabber_base64en($string) { return base64_encode($string); }
endif;

if (!function_exists('trgrabber_base64de')):
function trgrabber_base64de($string) { return base64_decode($string); }
endif;

if (!function_exists('tr_grabber_list_episodes')):
function tr_grabber_list_episodes($post_id, $season = NULL) {
    $meta_query = array(
        'relation' => 'AND',
        array('key' => 'tr_id_post', 'value' => $post_id, 'compare' => '='),
    );
    if ($season !== NULL) {
        $season_number = $season === 'special' ? 1 : intval($season);
        $season_key = $season === 'special' ? 'season_special' : 'season_number';
        $meta_query[] = array('key' => $season_key, 'value' => $season_number, 'compare' => '=');
    }
    $args = array(
        'taxonomy'   => 'episodes',
        'hide_empty' => false,
        'orderby'    => 'meta_value_num',
        'order'      => 'ASC',
        'meta_query' => $meta_query,
        'meta_key'   => 'episode_number',
    );
    return get_terms($args);
}
endif;

if (!function_exists('tr_grabber_list_seasons')):
function tr_grabber_list_seasons($post_id, $season = NULL) {
    $meta_query = array(
        'relation' => 'AND',
        array('key' => 'tr_id_post', 'value' => $post_id, 'compare' => '='),
    );
    if ($season !== NULL) {
        $meta_query[] = array('key' => 'season_number', 'value' => intval($season), 'compare' => '=');
    }
    $args = array(
        'taxonomy'   => 'seasons',
        'hide_empty' => false,
        'orderby'    => 'meta_value_num',
        'order'      => 'ASC',
        'meta_query' => $meta_query,
        'meta_key'   => 'season_number',
    );
    return get_terms($args);
}
endif;