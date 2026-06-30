<?php
$pid = get_the_ID();
$backdrop = onwatch_get_backdrop($pid, 'w1280');
$poster = onwatch_get_poster($pid, 'w342');
$rating = onwatch_get_rating($pid);
$year = onwatch_get_year($pid);
$runtime = onwatch_get_runtime($pid);
$country = onwatch_get_term_list($pid, 'country', 1);
$quality = onwatch_get_quality($pid);
$genres = onwatch_get_term_list($pid, 'category');
$cast = onwatch_get_term_list($pid, 'cast');
$directors = onwatch_get_term_list($pid, 'directors');
$original_title = get_post_meta($pid, 'field_title', true);
$imdb_id = get_post_meta($pid, 'field_imdbid', true);
$tmdb_id = get_post_meta($pid, 'field_id', true);
$trailer = onwatch_get_trailer($pid);
$user_rating = onwatch_get_user_rating($pid);
$links = onwatch_get_links($pid);
$country_name = !empty($country) ? $country[0]['name'] : '';

$show_meta      = get_theme_mod('onwatch_show_meta_info', true);
$show_poster_actions = get_theme_mod('onwatch_show_poster_actions', true);
$show_share     = get_theme_mod('onwatch_show_share_btns', true);
$show_rating_stars = get_theme_mod('onwatch_show_rating_stars', true);
$show_trailer   = get_theme_mod('onwatch_show_trailer_btn', true);
$show_download  = get_theme_mod('onwatch_show_download_btn', true);
$show_cast      = get_theme_mod('onwatch_show_cast', true);
$show_related   = get_theme_mod('onwatch_show_related', true);
$show_comments  = get_theme_mod('onwatch_show_comments', true);
$show_player    = get_theme_mod('onwatch_show_player_section', true);
$related_count  = get_theme_mod('onwatch_related_count', 6);
?>
<section class="ow-details-hero" style="background-image: url('<?php echo esc_url($backdrop ?: ''); ?>')">
    <div class="ow-details-hero__gradient"></div>
    <div class="ow-container ow-details-hero__content">
        <div class="ow-details-hero__layout">
            <div class="ow-details-hero__poster">
                <img src="<?php echo esc_url($poster ?: get_template_directory_uri() . '/resources/assets/img/placeholder.svg'); ?>" alt="<?php the_title_attribute(); ?>" width="280" height="420" onerror="this.onerror=null;this.src='<?php echo get_template_directory_uri(); ?>/resources/assets/img/placeholder.svg'">
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
                    <?php if ($year): ?><span><?php echo esc_html($year); ?></span><?php endif; ?>
                    <?php if ($runtime > 0): ?><span><?php echo sprintf(__('%d دقيقة', 'onwatch'), $runtime); ?></span><?php endif; ?>
                    <?php if ($country_name): ?><span><?php echo esc_html($country_name); ?></span><?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($quality): ?>
                <div class="ow-details-hero__badges ow-flex ow-gap-1 ow-mb-1">
                    <span class="ow-badge ow-badge--accent"><?php echo esc_html($quality); ?></span>
                </div>
                <?php endif; ?>

                <?php if (!empty($genres)): ?>
                <div class="ow-details-hero__genres">
                    <?php foreach ($genres as $g): ?>
                    <a href="<?php echo esc_url($g['url']); ?>" class="ow-tag"><?php echo esc_html($g['name']); ?></a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="ow-details-hero__synopsis">
                    <?php the_content(); ?>
                </div>

                <div class="ow-details-hero__actions">
                    <?php if (!empty($links)): ?>
                    <a href="#player" class="ow-btn ow-btn--primary ow-btn--lg"><?php _e('مشاهدة الآن', 'onwatch'); ?></a>
                    <?php endif; ?>
                    <?php if ($trailer && $show_trailer): ?>
                    <button class="ow-btn ow-btn--outline ow-btn--lg" @click="openTrailer('https://www.youtube.com/embed/<?php echo esc_js($trailer); ?>')"><?php _e('مقط دعائي', 'onwatch'); ?></button>
                    <?php endif; ?>
                    <?php if (!empty($links) && $show_download): ?>
                    <button class="ow-btn ow-btn--outline ow-btn--lg" @click="openDownload(<?php echo $pid; ?>)"><?php _e('تحميل', 'onwatch'); ?></button>
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

<div class="ow-container">
    <?php if (!empty($links) && $show_player): ?>
    <section id="player" class="ow-section">
        <?php get_template_part('resources/views/components/player', 'tabs', ['post_id' => $pid, 'is_episode' => false]); ?>
    </section>
    <?php endif; ?>

    <section class="ow-section">
        <div class="ow-details-grid">
            <?php if ($show_cast): ?>
            <div>
                <h3><?php _e('طاقم العمل', 'onwatch'); ?></h3>
                <?php if (!empty($cast)): ?>
                <div class="ow-chips">
                    <?php foreach ($cast as $c): ?>
                    <a href="<?php echo esc_url($c['url']); ?>" class="ow-chip"><?php echo esc_html($c['name']); ?></a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <div>
                <h3><?php _e('المعلومات', 'onwatch'); ?></h3>
                <ul class="ow-details-list">
                    <?php if (!empty($directors)): ?>
                    <li><span><?php _e('المخرج', 'onwatch'); ?></span> <span><?php echo esc_html(implode(', ', array_column($directors, 'name'))); ?></span></li>
                    <?php endif; ?>
                    <?php if ($country_name): ?>
                    <li><span><?php _e('الدولة', 'onwatch'); ?></span> <span><?php echo esc_html($country_name); ?></span></li>
                    <?php endif; ?>
                    <?php if ($imdb_id): ?>
                    <li><span>IMDb</span> <span><a href="https://www.imdb.com/title/<?php echo esc_attr($imdb_id); ?>" target="_blank"><?php echo esc_html($imdb_id); ?></a></span></li>
                    <?php endif; ?>
                    <?php if ($tmdb_id): ?>
                    <li><span>TMDb</span> <span><a href="https://www.themoviedb.org/<?php echo get_post_type() === 'movies' ? 'movie' : 'tv'; ?>/<?php echo esc_attr($tmdb_id); ?>" target="_blank"><?php echo esc_html($tmdb_id); ?></a></span></li>
                    <?php endif; ?>
                    <?php if ($runtime > 0): ?>
                    <li><span><?php _e('المدة', 'onwatch'); ?></span> <span><?php echo sprintf(__('%d دقيقة', 'onwatch'), $runtime); ?></span></li>
                    <?php endif; ?>
                    <li><span><?php _e('تاريخ الإصدار', 'onwatch'); ?></span> <span><?php echo esc_html(get_post_meta($pid, 'field_date', true)); ?></span></li>
                </ul>
            </div>
        </div>
    </section>

    <?php if ($show_related):
    $terms = get_the_terms($pid, 'category');
    $term_ids = $terms ? wp_list_pluck($terms, 'term_id') : [];
    $related_by = get_option('onwatch_single', [])['related_by'] ?? 'category';
    $related_args = [
        'post_type'      => 'movies',
        'posts_per_page' => absint(get_theme_mod('onwatch_related_count', 6)),
        'post_status'    => 'publish',
        'post__not_in'   => [$pid],
        'no_found_rows'  => true,
    ];
    if ($related_by === 'cast' && !empty($cast)) {
        $cast_ids = wp_list_pluck($cast, 'slug');
        $related_args['tax_query'] = [[
            'taxonomy' => 'cast',
            'field'    => 'slug',
            'terms'    => $cast_ids,
        ]];
    } elseif ($related_by === 'year' && $year) {
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
            <h2 class="ow-section__title"><?php _e('أفلام مشابهة', 'onwatch'); ?></h2>
        </header>
        <div class="ow-grid">
            <?php while ($related->have_posts()): $related->the_post(); get_template_part('resources/views/components/card', 'movie'); endwhile; wp_reset_postdata(); ?>
        </div>
    </section>
    <?php endif; endif; ?>

    <?php if ($show_comments && (comments_open() || get_comments_number())): ?>
    <section class="ow-section">
        <?php comments_template(); ?>
    </section>
    <?php endif; ?>
</div>
