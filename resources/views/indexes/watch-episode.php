<?php
$term = $args['object'] ?? get_queried_object();
$term_id = $term->term_id;
$series_id = get_term_meta($term_id, 'tr_id_post', true);
$season_num = get_term_meta($term_id, 'season_number', true);
$ep_num = get_term_meta($term_id, 'episode_number', true);
$series_title = $series_id ? get_the_title($series_id) : '';
$series_url = $series_id ? get_permalink($series_id) : '';
$backdrop = $series_id ? onwatch_get_backdrop($series_id, 'w1280') : '';
$synopsis = $term->description;

$series_rating = $series_id ? onwatch_get_rating($series_id) : 0;
$series_year = $series_id ? onwatch_get_year($series_id) : '';
$series_genres = $series_id ? onwatch_get_term_list($series_id, 'category') : [];
$series_cast = $series_id ? onwatch_get_term_list($series_id, 'cast_tv') : [];
$series_directors = $series_id ? onwatch_get_term_list($series_id, 'directors_tv') : [];

$air_date = get_term_meta($term_id, 'air_date', true);
$poster_url = $series_id ? onwatch_get_poster($series_id, 'w185') : '';

$player_opts = get_option('onwatch_player', []);
$player_width = isset($player_opts['player_width']) ? absint($player_opts['player_width']) : 100;
$player_height = $player_opts['player_height'] ?? '480px';

$seasons = $series_id ? onwatch_get_seasons($series_id) : [];
$all_season_data = [];
$current_season_id = 0;
foreach ($seasons as $s) {
    $sn = get_term_meta($s->term_id, 'season_number', true);
    $eps = onwatch_get_episodes($series_id, (int)$sn);
    $all_season_data[$s->term_id] = ['season' => $s, 'number' => $sn, 'episodes' => $eps];
    if ($sn == $season_num) $current_season_id = $s->term_id;
}
if (!$current_season_id && !empty($all_season_data)) {
    $keys = array_keys($all_season_data);
    $current_season_id = $keys[0];
}

$prev_ep = null;
$next_ep = null;
if ($series_id && $season_num !== '' && $ep_num !== '') {
    $prev_ep = get_terms([
        'taxonomy' => 'episodes', 'hide_empty' => false, 'number' => 1,
        'meta_query' => [
            ['key' => 'tr_id_post', 'value' => $series_id],
            ['key' => 'season_number', 'value' => $season_num],
            ['key' => 'episode_number', 'value' => $ep_num - 1],
        ]
    ]);
    $prev_ep = !empty($prev_ep) && !is_wp_error($prev_ep) ? $prev_ep[0] : null;
    $next_ep = get_terms([
        'taxonomy' => 'episodes', 'hide_empty' => false, 'number' => 1,
        'meta_query' => [
            ['key' => 'tr_id_post', 'value' => $series_id],
            ['key' => 'season_number', 'value' => $season_num],
            ['key' => 'episode_number', 'value' => $ep_num + 1],
        ]
    ]);
    $next_ep = !empty($next_ep) && !is_wp_error($next_ep) ? $next_ep[0] : null;
}
?>
<div x-data="{
    activeSeason: <?php echo $current_season_id; ?>,
    isWatched(id) { return false }
}">
    <section class="ow-details-hero" style="background-image: url('<?php echo esc_url($backdrop); ?>')">
        <div class="ow-details-hero__gradient"></div>
        <div class="ow-container ow-details-hero__content">
            <div class="ow-watch-breadcrumb">
                <?php get_template_part('resources/views/components/breadcrumb'); ?>
            </div>

            <div class="ow-watch-hero-info">
                <?php if ($poster_url): ?>
                <div class="ow-watch-hero-poster">
                    <img src="<?php echo esc_url($poster_url); ?>" alt="<?php echo esc_attr($series_title); ?>" width="80" height="120" onerror="this.onerror=null;this.src='<?php echo get_template_directory_uri(); ?>/resources/assets/img/placeholder.svg'">
                </div>
                <?php endif; ?>
                <div class="ow-watch-hero-meta">
                    <a href="<?php echo esc_url($series_url); ?>" class="ow-watch-series-link"><?php echo esc_html($series_title); ?></a>
                    <div class="ow-watch-hero-tags">
                        <?php if ($series_rating > 0): ?>
                        <span class="ow-watch-hero-rating">★ <?php echo esc_html($series_rating); ?></span>
                        <?php endif; ?>
                        <?php foreach (array_slice($series_genres, 0, 3) as $g): ?>
                        <a href="<?php echo esc_url($g['url']); ?>" class="ow-chip ow-chip--xs"><?php echo esc_html($g['name']); ?></a>
                        <?php endforeach; ?>
                    </div>
                    <?php if (!empty($series_cast)): ?>
                    <div class="ow-watch-hero-cast">
                        <span><?php _e('الممثلون:', 'onwatch'); ?></span>
                        <?php $cast_names = array_map(function($c) { return $c['name']; }, array_slice($series_cast, 0, 4)); ?>
                        <span><?php echo esc_html(implode('، ', $cast_names)); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ow-details-hero__info">
                <span class="ow-watch-badge">S<?php echo str_pad($season_num, 2, '0', STR_PAD_LEFT); ?>E<?php echo str_pad($ep_num, 2, '0', STR_PAD_LEFT); ?></span>
                <h1 class="ow-details-hero__title"><?php echo esc_html($term->name); ?></h1>
                <?php if ($synopsis): ?>
                <p class="ow-watch-synopsis"><?php echo esc_html($synopsis); ?></p>
                <?php endif; ?>
                <?php if ($air_date): ?>
                <span class="ow-watch-date"><?php echo esc_html($air_date); ?></span>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <div class="ow-container">
        <div class="ow-watch-player-area" style="--player-w: <?php echo max(1, $player_width); ?>%; --player-h: <?php echo esc_attr($player_height); ?>;">
            <?php get_template_part('resources/views/components/player', 'tabs', ['post_id' => $series_id, 'is_episode' => true, 'term_id' => $term_id]); ?>

            <div class="ow-watch-nav">
                <?php if ($prev_ep): ?>
                <a href="<?php echo esc_url(get_term_link($prev_ep)); ?>" class="ow-btn ow-btn--outline ow-btn--sm"><?php _e('← السابقة', 'onwatch'); ?></a>
                <?php endif; ?>
                <a href="<?php echo esc_url($series_url); ?>" class="ow-btn ow-btn--ghost ow-btn--sm"><?php _e('عرض المسلسل', 'onwatch'); ?></a>
                <?php if ($next_ep): ?>
                <a href="<?php echo esc_url(get_term_link($next_ep)); ?>" class="ow-btn ow-btn--primary ow-btn--sm"><?php _e('التالية →', 'onwatch'); ?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($seasons)): ?>
    <div class="ow-container ow-watch-section">
        <div class="ow-watch-section-header">
            <h2 class="ow-watch-section-title"><?php _e('المواسم', 'onwatch'); ?></h2>
        </div>
        <div class="ow-watch-season-tabs">
            <?php foreach ($all_season_data as $sid => $sdata): ?>
            <button class="ow-season-tab <?php echo $sid === $current_season_id ? 'is-active' : ''; ?>"
                @click="activeSeason = <?php echo $sid; ?>"
                :class="{'is-active': activeSeason === <?php echo $sid; ?>}">
                <?php echo sprintf(__('الموسم %d', 'onwatch'), $sdata['number']); ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="ow-container ow-watch-section">
        <div class="ow-watch-section-header">
            <h2 class="ow-watch-section-title"><?php _e('الحلقات', 'onwatch'); ?></h2>
        </div>
        <?php foreach ($all_season_data as $sid => $sdata): ?>
        <div x-show="activeSeason === <?php echo $sid; ?>" x-transition:enter="ow-fade-enter-active" x-transition:enter-start="ow-fade-enter-from">
            <?php if (empty($sdata['episodes'])): ?>
            <div class="ow-empty"><p><?php _e('لا توجد حلقات لهذا الموسم.', 'onwatch'); ?></p></div>
            <?php else: ?>
            <div class="ow-episode-grid">
                <?php foreach ($sdata['episodes'] as $ep):
                    get_template_part('resources/views/components/card', 'episode', ['term_id' => $ep->term_id]);
                endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
