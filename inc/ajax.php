<?php

add_action('wp_ajax_onwatch_load_more', 'onwatch_ajax_load_more');
add_action('wp_ajax_nopriv_onwatch_load_more', 'onwatch_ajax_load_more');

add_action('wp_ajax_onwatch_live_search', 'onwatch_ajax_live_search');
add_action('wp_ajax_nopriv_onwatch_live_search', 'onwatch_ajax_live_search');

add_action('wp_ajax_onwatch_get_season', 'onwatch_ajax_get_season');
add_action('wp_ajax_nopriv_onwatch_get_season', 'onwatch_ajax_get_season');

add_action('wp_ajax_onwatch_genre_tab', 'onwatch_ajax_genre_tab');
add_action('wp_ajax_nopriv_onwatch_genre_tab', 'onwatch_ajax_genre_tab');

add_action('wp_ajax_onwatch_submit_report', 'onwatch_ajax_submit_report');
add_action('wp_ajax_nopriv_onwatch_submit_report', 'onwatch_ajax_submit_report');

add_action('wp_ajax_onwatch_submit_rating', 'onwatch_ajax_submit_rating');

function onwatch_ajax_load_more() {
    check_ajax_referer('onwatch-nonce', 'nonce');

    $page = absint($_POST['page'] ?? 1);
    $query_vars = json_decode(stripslashes($_POST['query_vars'] ?? '{}'), true);
    if (!is_array($query_vars)) $query_vars = [];

    $query_vars['paged'] = $page;
    $query_vars['post_status'] = 'publish';

    $query = new WP_Query($query_vars);
    ob_start();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_type = get_post_type();
            if ($post_type === 'movies') {
                get_template_part('resources/views/components/card', 'movie');
            } elseif ($post_type === 'series') {
                get_template_part('resources/views/components/card', 'series');
            }
        }
    }

    $html = ob_get_clean();
    wp_reset_postdata();

    wp_send_json_success([
        'html'    => $html,
        'has_more' => $query->max_num_pages > $page
    ]);
}

function onwatch_ajax_live_search() {
    check_ajax_referer('onwatch-nonce', 'nonce');

    $s = sanitize_text_field($_POST['s'] ?? '');
    if (mb_strlen($s) < 2) {
        wp_send_json_success(['results' => []]);
    }

    $query = new WP_Query([
        's'              => $s,
        'post_type'      => ['movies', 'series'],
        'posts_per_page' => 8,
        'post_status'    => 'publish',
        'no_found_rows'  => true,
    ]);

    $results = [];
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $pid = get_the_ID();
            $results[] = [
                'id'         => $pid,
                'title'      => get_the_title(),
                'year'       => onwatch_get_year($pid),
                'type'       => get_post_type(),
                'poster_url' => onwatch_get_poster($pid, 'w185'),
                'url'        => get_permalink(),
            ];
        }
    }
    wp_reset_postdata();

    wp_send_json_success(['results' => $results]);
}

function onwatch_ajax_get_season() {
    check_ajax_referer('onwatch-nonce', 'nonce');

    $series_id = absint($_POST['series_id'] ?? 0);
    $season_number = absint($_POST['season_number'] ?? 0);

    if (!$series_id) {
        wp_send_json_error(['message' => 'Invalid series ID']);
    }

    $meta_season = get_term_meta($season_number, 'season_number', true);
    if ($meta_season !== '') {
        $season_number = (int)$meta_season;
    }

    $episodes = onwatch_get_episodes($series_id, $season_number);
    ob_start();

    if (!empty($episodes)) {
        foreach ($episodes as $ep) {
            $term_id = $ep->term_id;
            $series_id = get_term_meta($term_id, 'tr_id_post', true);
            $still = onwatch_get_still($term_id, 'w300');
            $ep_num = get_term_meta($term_id, 'episode_number', true);
            ?>
            <a href="<?php echo esc_url(get_term_link($ep)); ?>" class="ow-episode-card">
                <div class="ow-episode-card__thumb">
                    <?php if ($still): ?>
                    <img src="<?php echo esc_url($still); ?>" loading="lazy" alt="<?php echo esc_attr($ep->name); ?>" width="300" height="169" onerror="this.onerror=null;this.src='<?php echo get_template_directory_uri(); ?>/resources/assets/img/placeholder.svg'">
                    <?php else: ?>
                    <div class="ow-skeleton ow-skeleton--episode"></div>
                    <?php endif; ?>
                    <span class="ow-episode-card__number">S<?php echo str_pad($season_number, 2, '0', STR_PAD_LEFT); ?>E<?php echo str_pad($ep_num, 2, '0', STR_PAD_LEFT); ?></span>
                </div>
                <div class="ow-episode-card__info">
                    <h3 class="ow-episode-card__title"><?php echo esc_html($ep->name); ?></h3>
                </div>
            </a>
            <?php
        }
    }

    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
}

function onwatch_ajax_genre_tab() {
    check_ajax_referer('onwatch-nonce', 'nonce');

    $genre_slug = sanitize_text_field($_POST['genre_slug'] ?? '');
    $post_type = sanitize_text_field($_POST['post_type'] ?? '');
    if (empty($genre_slug)) {
        wp_send_json_error(['message' => 'No genre slug']);
    }

    $args = [
        'post_type'      => !empty($post_type) ? [$post_type] : ['movies', 'series'],
        'posts_per_page' => 10,
        'post_status'    => 'publish',
        'no_found_rows'  => true,
        'tax_query'      => [[
            'taxonomy' => 'category',
            'field'    => 'slug',
            'terms'    => $genre_slug
        ]]
    ];

    $transient_key = 'onwatch_genre_' . $genre_slug . '_' . $post_type;
    $cached = get_transient($transient_key);
    if ($cached !== false) {
        wp_send_json_success(['html' => $cached]);
    }

    $query = new WP_Query($args);
    ob_start();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $pt = get_post_type();
            if ($pt === 'movies') {
                get_template_part('resources/views/components/card', 'movie');
            } else {
                get_template_part('resources/views/components/card', 'series');
            }
        }
    }
    wp_reset_postdata();

    $html = ob_get_clean();
    set_transient($transient_key, $html, HOUR_IN_SECONDS);

    wp_send_json_success(['html' => $html]);
}

function onwatch_ajax_submit_report() {
    check_ajax_referer('onwatch-nonce', 'nonce');

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $transient_key = 'onwatch_report_' . md5($ip);

    if (get_transient($transient_key)) {
        wp_send_json_error(['message' => __('يمكنك الإبلاغ مرة واحدة كل 10 دقائق', 'onwatch')]);
    }

    $post_id = absint($_POST['post_id'] ?? 0);
    $type = sanitize_text_field($_POST['type'] ?? '');
    $message = sanitize_textarea_field($_POST['message'] ?? '');

    if (!$post_id || !$type) {
        wp_send_json_error(['message' => __('بيانات غير صالحة', 'onwatch')]);
    }

    $reports = get_option('onwatch_reports', []);
    $reports[] = [
        'date'    => current_time('mysql'),
        'post_id' => $post_id,
        'type'    => $type,
        'message' => $message,
        'ip'      => $ip
    ];
    update_option('onwatch_reports', $reports);
    set_transient($transient_key, 1, 10 * MINUTE_IN_SECONDS);

    wp_send_json_success(['message' => __('شكراً! تم إرسال البلاغ', 'onwatch')]);
}

function onwatch_ajax_submit_rating() {
    check_ajax_referer('onwatch-nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => __('يرجى تسجيل الدخول أولاً', 'onwatch')]);
    }

    $post_id = absint($_POST['post_id'] ?? 0);
    $score = absint($_POST['score'] ?? 0);
    if (!$post_id || $score < 1 || $score > 5) {
        wp_send_json_error(['message' => __('تقييم غير صالح', 'onwatch')]);
    }

    $user_id = get_current_user_id();
    $ratings = get_post_meta($post_id, 'onwatch_user_ratings', true);
    if (!is_array($ratings)) $ratings = [];

    $ratings[$user_id] = $score;
    update_post_meta($post_id, 'onwatch_user_ratings', $ratings);

    $count = count($ratings);
    $avg = array_sum($ratings) / $count;
    update_post_meta($post_id, 'onwatch_avg_rating', $avg);
    update_post_meta($post_id, 'onwatch_rating_count', $count);

    wp_send_json_success([
        'average' => round($avg, 1),
        'count'   => $count,
        'message' => __('تم حفظ تقييمك', 'onwatch')
    ]);
}

function onwatch_purge_transients($post_id) {
    delete_transient('onwatch_trending');
    delete_transient('onwatch_latest_episodes');
    global $wpdb;
    $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_onwatch_genre_%'");
    $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_onwatch_genre_%'");
}
add_action('save_post', 'onwatch_purge_transients');
add_action('edit_term', 'onwatch_purge_transients');
