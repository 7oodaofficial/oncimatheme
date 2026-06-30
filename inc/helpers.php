<?php

function onwatch_tmdb_img($path, $size = 'w500') {
    if (empty($path)) return '';
    if (filter_var($path, FILTER_VALIDATE_URL)) return esc_url($path);
    return 'https://image.tmdb.org/t/p/' . $size . $path;
}

function onwatch_get_links($id, $taxonomy = null) {
    $links = [];
    $i = 0;
    while (true) {
        $raw = $taxonomy === 'episodes'
            ? get_term_meta($id, 'trglinks_' . $i, true)
            : get_post_meta($id, 'trglinks_' . $i, true);
        if (empty($raw)) break;
        $link = maybe_unserialize($raw);
        if (is_array($link) && !empty($link['link'])) {
            $link['url'] = base64_decode($link['link']);
            $links[] = $link;
        }
        $i++;
    }
    return $links;
}

function onwatch_get_seasons($series_id) {
    $seasons = get_terms([
        'taxonomy'   => 'seasons',
        'hide_empty' => false,
        'meta_key'   => 'season_number',
        'orderby'    => 'meta_value_num',
        'order'      => 'ASC',
        'meta_query' => [[
            'key'   => 'tr_id_post',
            'value' => $series_id
        ]]
    ]);
    if (is_wp_error($seasons)) return [];
    return $seasons;
}

function onwatch_get_episodes($series_id, $season_number) {
    $episodes = get_terms([
        'taxonomy'   => 'episodes',
        'hide_empty' => false,
        'meta_key'   => 'episode_number',
        'orderby'    => 'meta_value_num',
        'order'      => 'ASC',
        'meta_query' => [
            ['key' => 'tr_id_post', 'value' => $series_id],
            ['key' => 'season_number', 'value' => $season_number]
        ]
    ]);
    if (is_wp_error($episodes)) return [];
    return $episodes;
}

function onwatch_get_poster($post_id, $size = 'w342') {
    $poster = get_post_meta($post_id, 'poster', true);
    if ($poster) {
        $src = wp_get_attachment_image_url($poster, 'medium');
        if ($src) return $src;
    }
    $hotlink = get_post_meta($post_id, 'poster_hotlink', true);
    if ($hotlink) return onwatch_tmdb_img($hotlink, $size);
    return '';
}

function onwatch_get_backdrop($post_id, $size = 'w1280') {
    $backdrop = get_post_meta($post_id, 'field_backdrop', true);
    if ($backdrop) {
        $src = wp_get_attachment_image_url($backdrop, 'full');
        if ($src) return $src;
    }
    $hotlink = get_post_meta($post_id, 'backdrop_hotlink', true);
    if ($hotlink) return onwatch_tmdb_img($hotlink, $size);
    return '';
}

function onwatch_get_quality($post_id) {
    $links = onwatch_get_links($post_id);
    if (!empty($links)) {
        $qly = $links[0]['quality'] ?? '';
        if (!empty($qly)) {
            $term = get_term((int)$qly, 'quality');
            if ($term && !is_wp_error($term)) return $term->name;
        }
    }
    return '';
}

function onwatch_get_rating($post_id) {
    $rating = get_post_meta($post_id, 'rating', true);
    return $rating ? round((float)$rating, 1) : 0;
}

function onwatch_get_year($post_id) {
    $year = get_post_meta($post_id, 'field_release_year', true);
    if (!$year) {
        $date = get_post_meta($post_id, 'field_date', true);
        if ($date) $year = substr($date, 0, 4);
    }
    return $year;
}

function onwatch_get_runtime($post_id) {
    $runtime = get_post_meta($post_id, 'field_runtime', true);
    return $runtime ? (int)$runtime : 0;
}

function onwatch_get_trailer($post_id) {
    $trailer = get_post_meta($post_id, 'field_trailer', true);
    if (!empty($trailer)) return $trailer;
    return '';
}

function onwatch_is_new($post_id, $days = 7) {
    $post = get_post($post_id);
    if (!$post) return false;
    $age = time() - strtotime($post->post_date);
    return $age < $days * DAY_IN_SECONDS;
}

function onwatch_embed_url($link_index, $post_id) {
    return home_url('/?trembed=' . (int)$link_index . '&trid=' . (int)$post_id);
}

function onwatch_download_url($link_index, $post_id) {
    return home_url('/?trdownload=' . (int)$link_index . '&trid=' . (int)$post_id);
}

function onwatch_embed_url_episode($link_index, $term_id) {
    return home_url('/?trembed=' . (int)$link_index . '&trid=' . (int)$term_id . '&t=ser');
}

function onwatch_download_url_episode($link_index, $term_id) {
    return home_url('/?trdownload=' . (int)$link_index . '&trid=' . (int)$term_id . '&t=ser');
}

function onwatch_get_term_list($post_id, $taxonomy, $limit = 0) {
    $terms = get_the_terms($post_id, $taxonomy);
    if (!$terms || is_wp_error($terms)) return [];
    $out = [];
    foreach ($terms as $i => $t) {
        if ($limit > 0 && $i >= $limit) break;
        $out[] = [
            'name' => $t->name,
            'slug' => $t->slug,
            'url'  => get_term_link($t)
        ];
    }
    return $out;
}

function onwatch_get_post_type_meta($post_id) {
    $type = get_post_meta($post_id, 'tr_post_type', true);
    if ($type == 2) return 'series';
    return 'movies';
}

function onwatch_get_still($term_id, $size = 'w300') {
    $still = get_term_meta($term_id, 'still_path', true);
    if ($still) {
        $src = wp_get_attachment_image_url($still, 'medium');
        if ($src) return $src;
    }
    $hotlink = get_term_meta($term_id, 'still_path_hotlink', true);
    if ($hotlink) return onwatch_tmdb_img($hotlink, $size);
    return '';
}

function onwatch_get_user_rating($post_id) {
    $avg = get_post_meta($post_id, 'onwatch_avg_rating', true);
    $cnt = get_post_meta($post_id, 'onwatch_rating_count', true);
    return [
        'average' => $avg ? round((float)$avg, 1) : 0,
        'count'   => $cnt ? (int)$cnt : 0
    ];
}
