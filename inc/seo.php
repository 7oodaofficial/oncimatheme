<?php

add_action('wp_head', 'onwatch_seo_meta', 1);

function onwatch_seo_meta() {
    $enable_schema = get_theme_mod('onwatch_enable_schema', true);
    $enable_og     = get_theme_mod('onwatch_enable_og_meta', true);

    if (!is_singular(['movies', 'series']) && !is_tax('episodes')) {
        if (is_singular()) onwatch_seo_output_breadcrumb();
        return;
    }

    $post_id = get_queried_object_id();
    $post_type = get_post_type();

    if (is_tax('episodes')) {
        onwatch_seo_episode();
        return;
    }

    $title = single_post_title('', false);
    $desc  = wp_trim_words(get_the_excerpt() ?: get_the_content(), 25);
    $img   = onwatch_get_poster($post_id, 'w500');
    $url   = get_permalink($post_id);

    if ($enable_og) {
        echo '<meta property="og:title" content="' . esc_attr($title) . '" />' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($desc) . '" />' . "\n";
        if ($img) echo '<meta property="og:image" content="' . esc_url($img) . '" />' . "\n";
        echo '<meta property="og:url" content="' . esc_url($url) . '" />' . "\n";
        echo '<meta property="og:type" content="' . ($post_type === 'movies' ? 'video.movie' : 'video.tv_show') . '" />' . "\n";
        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($title) . '" />' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr($desc) . '" />' . "\n";
        if ($img) echo '<meta name="twitter:image" content="' . esc_url($img) . '" />' . "\n";
    }
    echo '<link rel="canonical" href="' . esc_url($url) . '" />' . "\n";

    if ($enable_schema) {
        onwatch_seo_breadcrumb_jsonld();
        onwatch_seo_movie_jsonld($post_id, $post_type);
    }
}

function onwatch_seo_episode() {
    $term = get_queried_object();
    $series_id = get_term_meta($term->term_id, 'tr_id_post', true);
    $series_title = $series_id ? get_the_title($series_id) : '';
    $title = $term->name;
    $desc = '';
    $url = get_term_link($term);
    $img = onwatch_get_still($term->term_id, 'w500');

    echo '<meta property="og:title" content="' . esc_attr($title) . '" />' . "\n";
    echo '<meta property="og:url" content="' . esc_url($url) . '" />' . "\n";
    echo '<meta property="og:type" content="video.episode" />' . "\n";
    if ($img) echo '<meta property="og:image" content="' . esc_url($img) . '" />' . "\n";
    echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
    echo '<link rel="canonical" href="' . esc_url($url) . '" />' . "\n";

    onwatch_seo_breadcrumb_jsonld();
    onwatch_seo_episode_jsonld($term, $series_id, $series_title);
}

function onwatch_seo_output_breadcrumb() {
    if (function_exists('yoast_breadcrumb')) {
        yoast_breadcrumb('<nav class="ow-breadcrumb">', '</nav>');
    } elseif (function_exists('rank_math_the_breadcrumbs')) {
        rank_math_the_breadcrumbs();
    }
}

function onwatch_seo_breadcrumb_jsonld() {
    $crumbs = [];
    if (is_singular(['movies', 'series'])) {
        $post_type_obj = get_post_type_object(get_post_type());
        $archive_link = get_post_type_archive_link(get_post_type());
        if ($archive_link) {
            $crumbs[] = ['@type' => 'ListItem', 'position' => 1, 'name' => $post_type_obj->labels->name, 'item' => $archive_link];
        }
        $crumbs[] = ['@type' => 'ListItem', 'position' => 2, 'name' => get_the_title()];
    } elseif (is_tax('episodes')) {
        $crumbs[] = ['@type' => 'ListItem', 'position' => 1, 'name' => __('مسلسلات', 'onwatch'), 'item' => get_post_type_archive_link('series')];
        $crumbs[] = ['@type' => 'ListItem', 'position' => 2, 'name' => get_the_title(get_term_meta(get_queried_object()->term_id, 'tr_id_post', true))];
        $crumbs[] = ['@type' => 'ListItem', 'position' => 3, 'name' => get_queried_object()->name];
    } else {
        return;
    }

    echo '<script type="application/ld+json">' . json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $crumbs
    ], JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
}

function onwatch_seo_movie_jsonld($post_id, $post_type) {
    $schema_type = $post_type === 'movies' ? 'Movie' : 'TVSeries';
    $title = get_the_title();
    $desc = get_the_excerpt() ?: get_the_content();
    $img = onwatch_get_poster($post_id, 'w500');
    $date = get_post_meta($post_id, 'field_date', true);
    $rating = onwatch_get_rating($post_id);
    $runtime = onwatch_get_runtime($post_id) * 60;

    $data = [
        '@context' => 'https://schema.org',
        '@type' => $schema_type,
        'name' => $title,
        'description' => wp_trim_words($desc, 30),
        'image' => $img ?: '',
        'url' => get_permalink($post_id),
    ];

    if ($date) $data['datePublished'] = $date;
    if ($runtime > 0) $data['duration'] = 'PT' . $runtime . 'S';
    if ($rating > 0) {
        $data['aggregateRating'] = [
            '@type' => 'AggregateRating',
            'ratingValue' => $rating,
            'bestRating' => '10',
            'worstRating' => '0',
            'ratingCount' => get_post_meta($post_id, 'onwatch_rating_count', true) ?: 1
        ];
    }

    echo '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
}

function onwatch_seo_episode_jsonld($term, $series_id, $series_title) {
    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'TVEpisode',
        'name' => $term->name,
        'url' => get_term_link($term),
        'partOfSeries' => [
            '@type' => 'TVSeries',
            'name' => $series_title,
            'url' => $series_id ? get_permalink($series_id) : ''
        ]
    ];

    $season_num = get_term_meta($term->term_id, 'season_number', true);
    $ep_num = get_term_meta($term->term_id, 'episode_number', true);
    if ($season_num !== '') $data['partOfSeason'] = ['@type' => 'TVSeason', 'seasonNumber' => (int)$season_num];
    if ($ep_num !== '') $data['episodeNumber'] = (int)$ep_num;

    echo '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
}
