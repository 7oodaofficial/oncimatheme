<?php
defined('ABSPATH') || exit;

if (!function_exists('tr_grabber_get_domain_from_url')):
function tr_grabber_get_domain_from_url($url) {
    $parsed = wp_parse_url($url);
    return isset($parsed['host']) ? $parsed['host'] : '';
}
endif;

if (!function_exists('tr_grabber_frame_servers')):
function tr_grabber_frame_servers() {
    return array('openload.co', 'streamango.com', 'vidoza.net', 'streamplay.to', 'flashx.tv', 'streamcherry.com', 'thevideo.me', 'www.flashx.tv');
}
endif;

if (!function_exists('trgrabber_head')):
function trgrabber_head() {
    if (get_query_var('tr_post_type') != '' && is_category()) {
        echo '<meta name="robots" content="noindex, follow">' . "\n\r";
    }
}
endif;
add_action('wp_head', 'trgrabber_head');

if (!function_exists('tr_grabber_pregetposts')):
function tr_grabber_pregetposts($query) {
    if ($query->is_main_query() && !is_admin() && is_tax('letters')) {
        global $wp_query;
        $current = $wp_query->queried_object;
        $paged = (get_query_var('trpage')) ? get_query_var('trpage') : 1;
        $query->set('post_type', array('movies', 'series'));
        $query->set('letters', '');
        $query->set('_name__like', $current->slug);
        $query->set('orderby', 'name');
        $query->set('order', 'asc');
        $query->set('paged', $paged);
    }
}
endif;
add_action('pre_get_posts', 'tr_grabber_pregetposts');

add_filter('posts_where', function($where, $q) {
    if ($name__like = $q->get('_name__like')) {
        global $wpdb;
        if ($name__like == '0-9') {
            $where .= $wpdb->prepare(" AND {$wpdb->posts}.post_title REGEXP %s ", $wpdb->esc_like('^[0-9|+|-]'));
        } else {
            $where .= $wpdb->prepare(
                " AND {$wpdb->posts}.post_title LIKE %s ",
                str_replace(array('**', '*'), array('*', '%'), mb_strtolower($wpdb->esc_like($name__like) . '%'))
            );
        }
    }
    return $where;
}, 10, 2);

add_action('init', function() {
    add_rewrite_tag('%trembed%', '([^&]+)');
    add_rewrite_tag('%trdownload%', '([^&]+)');
    add_rewrite_tag('%trid%', '([^&]+)');
    add_rewrite_tag('%trtype%', '([^&]+)');
    add_rewrite_tag('%trhide%', '([^&]+)');
    add_rewrite_tag('%tid%', '([^&]+)');
    add_rewrite_tag('%trpage%', '([^&]+)');
    add_rewrite_tag('%trfilter%', '([^&]+)');
    add_rewrite_tag('%trhex%', '([^&]+)');
    add_rewrite_tag('%tr_post_type%', '([^&]+)');
});

add_filter('query_vars', function($vars) {
    $vars[] = 'trhex';
    $vars[] = 'tid';
    $vars[] = 'tr_post_type';
    return $vars;
});

if (!function_exists('trgrabber_template')):
function trgrabber_template($template) {
    if (get_query_var('trembed') != '' && get_query_var('trid') != '') {
        $template = ONWATCH_DIR_PATH . '/inc/tr-grabber/embed.php';
    }
    if (get_query_var('trhide') != '') {
        $template = ONWATCH_DIR_PATH . '/inc/tr-grabber/hide.php';
    }
    return $template;
}
endif;
add_filter('template_include', 'trgrabber_template', 99);

if (!function_exists('trgrabber_template_redirect')):
function trgrabber_template_redirect() {
    if (get_query_var('trdownload') != '' && get_query_var('trid') != '') {
        $type = isset($_GET['t']) && $_GET['t'] == 'ser'
            ? get_term_meta(intval(get_query_var('trid')), 'tr_id_post', true)
            : '';
        $link = $type == ''
            ? unserialize(get_post_meta(intval(get_query_var('trid')), 'trglinks_' . intval(get_query_var('trdownload')), true))
            : unserialize(get_term_meta(intval(get_query_var('trid')), 'trglinks_' . intval(get_query_var('trdownload')), true));
        $link = base64_decode($link['link']);
        wp_redirect(esc_url_raw($link, array('http', 'https')));
        die;
    }
}
endif;
add_action('template_redirect', 'trgrabber_template_redirect');

add_action('pre_get_posts', function($qry) {
    if (is_admin()) return;
    if (is_tax('server') || is_tax('language') || is_tax('quality')) {
        $qry->set_404();
    }
});

if (!function_exists('trg_filter_search_title')):
function trg_filter_search_title($title) {
    if (get_query_var('trfilter') == '') {
        return $title;
    }
    return __('Advance Search', 'onwatch');
}
endif;
add_filter('pre_get_document_title', 'trg_filter_search_title', 9999);
add_filter('wp_title', 'trg_filter_search_title', 9999, 3);

if (!function_exists('trgrabber_thumbnail_hotlink')):
function trgrabber_thumbnail_hotlink($metadata, $object_id, $meta_key, $single) {
    global $post;
    if ($meta_key == '_thumbnail_id') {
        $custom_fields = get_post_custom($object_id);
        $content = isset($custom_fields['_thumbnail_id']['0']) ? $custom_fields['_thumbnail_id']['0'] : 'hotlink';
        return $content;
    }
    return $metadata;
}
endif;
if (!is_admin()) {
    add_filter('get_post_metadata', 'trgrabber_thumbnail_hotlink', 10, 4);
}

add_filter('get_terms_args', 'trgrabber_get_terms_args', 10, 3);
if (!function_exists('trgrabber_get_terms_args')):
function trgrabber_get_terms_args($args, $taxonomies) {
    global $pagenow;
    if (in_array($pagenow, array('edit-tags.php')) && is_admin() && !empty($args['taxonomy'][0])) {
        $tax = $args['taxonomy'][0];
        if (($tax == 'episodes' || $tax == 'seasons') && isset($_GET['tr_id_post'])) {
            $meta_query = array(
                'relation' => 'and',
                array('key' => 'tr_id_post', 'value' => intval($_GET['tr_id_post']), 'compare' => '='),
            );
            if (isset($_GET['tr_season'])) {
                $field_key = $_GET['tr_season'] == 0 ? 'season_special' : 'season_number';
                $field_value = $_GET['tr_season'] == 0 ? 1 : intval($_GET['tr_season']);
                $meta_query[] = array('key' => $field_key, 'value' => $field_value, 'compare' => '=');
            }
            $args['meta_query'] = $meta_query;
        }
    }
    return $args;
}
endif;