<?php
$pid = get_the_ID();
$backdrop = onwatch_get_backdrop($pid, 'w1280');
$poster = onwatch_get_poster($pid, 'w342');
$rating = onwatch_get_rating($pid);
$year = onwatch_get_year($pid);
$quality = onwatch_get_quality($pid);
$genres = onwatch_get_term_list($pid, 'category');
$cast = onwatch_get_term_list($pid, 'cast_tv');
$directors = onwatch_get_term_list($pid, 'directors_tv');
$original_title = get_post_meta($pid, 'field_title', true);
$imdb_id = get_post_meta($pid, 'field_imdbid', true);
$tmdb_id = get_post_meta($pid, 'field_id', true);
$trailer = onwatch_get_trailer($pid);
$user_rating = onwatch_get_user_rating($pid);
$in_production = get_post_meta($pid, 'field_inproduction', true);
$status = get_post_meta($pid, 'status', true);
$num_seasons = get_post_meta($pid, 'number_of_seasons', true);
$num_episodes = get_post_meta($pid, 'number_of_episodes', true);
$seasons = onwatch_get_seasons($pid);
$links = onwatch_get_links($pid);

$show_meta      = get_theme_mod('onwatch_show_meta_info', true);
$show_poster_actions = get_theme_mod('onwatch_show_poster_actions', true);
$show_share     = get_theme_mod('onwatch_show_share_btns', true);
$show_rating_stars = get_theme_mod('onwatch_show_rating_stars', true);
$show_trailer   = get_theme_mod('onwatch_show_trailer_btn', true);
$show_cast      = get_theme_mod('onwatch_show_cast', true);
$show_related   = get_theme_mod('onwatch_show_related', true);
$show_comments  = get_theme_mod('onwatch_show_comments', true);
$show_player    = get_theme_mod('onwatch_show_player_section', true);
?>
<section class="ow-details-hero" style="background-image: url('<?php echo esc_url($backdrop ?: ''); ?>')">
    <div class="ow-details-hero__gradient"></div>
    <div class="ow-container ow-details-hero__content">
        <div class="ow-details-hero__layout">
            <div class="ow-details-hero__poster">
                <img src="<?php echo esc_url($poster ?: get_template_directory_uri() . '/resources/assets/img/placeholder.svg'); ?>" alt="<?php the_title_attribute(); ?>" width="280" height="420">
                <?php if ($show_poster_actions): ?>
                <div class="ow-details-hero__poster-actions">
                    <button class="ow-btn ow-btn--secondary" @click="toggleFav(<?php echo $pid; ?>, '<?php echo esc_js(get_the_title()); ?>', '<?php echo esc_js(get_permalink()); ?>', '<?php echo esc_js($poster); ?>')"><?php _e('إضافة للمفضلة', 'onwatch'); ?></button>
                    <?php if ($show_share): ?>
                    <button class="ow-btn ow-btn--secondary" @click="share('<?php echo esc_js(get_the_title()); ?>', '<?php echo esc_js(get_permalink()); ?>')"><?php _e('مشاركة', 'onwatch'); ?></button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="ow-details-hero__info">
                <h1 class="ow-details-hero__title"><?php the_title(); ?></h1>
                <?php if ($original_title && $original_title !== get_the_title()): ?>
                <p class="ow-details-hero__original"><?php echo esc_html($original_title); ?></p>
                <?php endif; ?>

                <?php if ($show_meta): ?>
                <div class="ow-details-hero__meta">
                    <span><?php echo esc_html($rating); ?> تقييم</span>
                    <span><?php echo esc_html($year); ?></span>
                    <?php if ($num_seasons): ?><span><?php echo sprintf(__('%d مواسم', 'onwatch'), $num_seasons); ?></span><?php endif; ?>
                    <?php if ($num_episodes): ?><span><?php echo sprintf(__('%d حلقة', 'onwatch'), $num_episodes); ?></span><?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="ow-details-hero__status">
                    <?php if ($in_production === 'true' || $in_production === '1'): ?>
                    <span class="ow-status ow-status--active"><?php _e('مستمر', 'onwatch'); ?></span>
                    <?php else: ?>
                    <span class="ow-status ow-status--ended"><?php _e('منتهي', 'onwatch'); ?></span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($genres)): ?>
                <div class="ow-details-hero__genres">
                    <?php foreach ($genres as $g): ?>
                    <a href="<?php echo esc_url($g['url']); ?>" class="ow-tag"><?php echo esc_html($g['name']); ?></a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="ow-details-hero__synopsis"><?php the_content(); ?></div>

                <div class="ow-details-hero__actions">
                    <?php if (!empty($seasons)): ?>
                    <a href="#seasons" class="ow-btn ow-btn--primary ow-btn--lg"><?php _e('الحلقات', 'onwatch'); ?></a>
                    <?php endif; ?>
                    <?php if ($trailer && $show_trailer): ?>
                    <button class="ow-btn ow-btn--outline ow-btn--lg" @click="openTrailer('https://www.youtube.com/embed/<?php echo esc_js($trailer); ?>')"><?php _e('مقط دعائي', 'onwatch'); ?></button>
                    <?php endif; ?>
                </div>

                <?php if ($show_rating_stars): ?>
                <div class="ow-rating" x-data="{ userRating: 0 }">
                    <span class="ow-rating__label"><?php _e('تقييمك:', 'onwatch'); ?></span>
                    <?php for ($s = 1; $s <= 5; $s++): ?>
                    <button class="ow-rating__star" :class="{'is-active': userRating >= <?php echo $s; ?>}" @click="submitRating(<?php echo $pid; ?>, <?php echo $s; ?>)">★</button>
                    <?php endfor; ?>
                    <span class="ow-rating__avg"><?php echo $user_rating['average']; ?> (<?php echo sprintf(__('%d تقييم', 'onwatch'), $user_rating['count']); ?>)</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($seasons)): ?>
<section id="seasons" class="ow-section" x-data="{ activeSeason: <?php echo !empty($seasons) ? (int)$seasons[0]->term_id : 0; ?>, seasonCache: {} }">
    <div class="ow-container">
        <header class="ow-section__header">
            <h2 class="ow-section__title"><?php _e('المواسم والحلقات', 'onwatch'); ?></h2>
        </header>

        <div class="ow-season-tabs" x-show="<?php echo count($seasons); ?> <= 3 || showAllSeasons" x-cloak>
            <?php foreach ($seasons as $s):
                $s_num = get_term_meta($s->term_id, 'season_number', true);
                $label = ($s_num == 0) ? __('خاص', 'onwatch') : sprintf(__('الموسم %d', 'onwatch'), (int)$s_num);
            ?>
            <button class="ow-season-tab" :class="{'is-active': activeSeason === <?php echo $s->term_id; ?>}" @click="switchSeason(<?php echo $s->term_id; ?>, <?php echo $pid; ?>)"><?php echo esc_html($label); ?></button>
            <?php endforeach; ?>
        </div>

        <?php if (count($seasons) > 3): ?>
        <button class="ow-btn ow-btn--ghost" x-show="!showAllSeasons" @click="showAllSeasons = true"><?php _e('عرض جميع المواسم', 'onwatch'); ?></button>
        <?php endif; ?>

        <div class="ow-episode-grid" x-html="seasonContent" x-init="switchSeason(<?php echo (int)$seasons[0]->term_id; ?>, <?php echo $pid; ?>)"></div>
    </div>
</section>
<?php endif; ?>

<div class="ow-container">
    <?php if ($show_cast && !empty($cast)): ?>
    <section class="ow-section">
        <h3 style="margin-bottom: 0.75rem;"><?php _e('طاقم العمل', 'onwatch'); ?></h3>
        <div class="ow-chips">
            <?php foreach ($cast as $c): ?>
            <a href="<?php echo esc_url($c['url']); ?>" class="ow-chip"><?php echo esc_html($c['name']); ?></a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($show_related):
    $terms = get_the_terms($pid, 'category');
    $term_ids = $terms ? wp_list_pluck($terms, 'term_id') : [];
    $related_by = get_option('onwatch_single', [])['related_by'] ?? 'category';
    $related_args = [
        'post_type'      => 'series',
        'posts_per_page' => absint(get_theme_mod('onwatch_related_count', 6)),
        'post_status'    => 'publish',
        'post__not_in'   => [$pid],
        'no_found_rows'  => true,
    ];
    if ($related_by === 'year' && $year) {
        $related_args['meta_key'] = 'field_release_year';
        $related_args['meta_value'] = $year;
    } elseif (!empty($term_ids)) {
        $related_args['tax_query'] = [[
            'taxonomy' => 'category',
            'field'    => 'term_id',
            'terms'    => $term_ids,
        ]];
    }
    $related = new WP_Query($related_args);
    if ($related->have_posts()):
    ?>
    <section class="ow-section">
        <header class="ow-section__header">
            <h2 class="ow-section__title"><?php _e('مسلسلات مشابهة', 'onwatch'); ?></h2>
        </header>
        <div class="ow-grid">
            <?php while ($related->have_posts()): $related->the_post(); get_template_part('resources/views/components/card', 'series'); endwhile; wp_reset_postdata(); ?>
        </div>
    </section>
    <?php endif; endif; ?>

    <?php if ($show_comments && (comments_open() || get_comments_number())): ?>
    <section class="ow-section">
        <?php comments_template(); ?>
    </section>
    <?php endif; ?>
</div>
