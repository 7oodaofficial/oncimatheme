<?php
$show_hero       = get_theme_mod('onwatch_show_hero', true);
$show_movies     = get_theme_mod('onwatch_show_movies_row', true);
$show_series     = get_theme_mod('onwatch_show_series_row', true);
$show_trending   = get_theme_mod('onwatch_show_trending', true);
$show_genres     = get_theme_mod('onwatch_show_genre_tabs', true);
$show_ep_latest  = get_theme_mod('onwatch_show_ep_latest', true);
$show_continue   = get_theme_mod('onwatch_show_continue', true);
$show_titles     = get_theme_mod('onwatch_show_section_titles', true);

$hero_count = get_theme_mod('onwatch_hero_count', 8);
$hero_speed = get_theme_mod('onwatch_hero_speed', 6000);
$latest_movies_count = get_theme_mod('onwatch_latest_movies', 10);
$latest_series_count = get_theme_mod('onwatch_latest_series', 10);
$trending_count = get_theme_mod('onwatch_trending_count', 6);
$ep_latest_count = get_theme_mod('onwatch_ep_latest_count', 10);
$trending_source = get_theme_mod('onwatch_trending_source', 'rating');
$cache_ttl = get_theme_mod('onwatch_cache_ttl', 3600);
$card_style = get_theme_mod('onwatch_card_style', 'default');
$hero_style = get_theme_mod('onwatch_hero_style', 'slider');

$section_order = explode("\n", str_replace("\r", "", get_theme_mod('onwatch_section_order', "hero\nmovies\nseries\ntrending\ngenres\nepisodes\ncontinue")));
$section_order = array_map('trim', $section_order);
$section_order = array_filter($section_order);

if ($show_hero):
$hero_query = new WP_Query([
    'post_type'      => ['movies', 'series'],
    'posts_per_page' => $hero_count,
    'post_status'    => 'publish',
    'meta_query'     => [
        ['key' => 'backdrop_hotlink', 'compare' => 'EXISTS'],
        ['key' => 'field_id', 'compare' => 'EXISTS'],
    ],
    'orderby'        => 'date',
    'order'          => 'DESC',
    'no_found_rows'  => true,
]);
?>

<?php if ($hero_style === 'slider'): ?>
<section class="ow-hero" x-data="{ activeSlide: 0, autoAdvance: null }" x-init="autoAdvance = setInterval(() => { activeSlide = (activeSlide + 1) % <?php echo $hero_query->post_count ?: 1; ?> }, <?php echo absint($hero_speed); ?>)" @mouseenter="clearInterval(autoAdvance)" @mouseleave="autoAdvance = setInterval(() => { activeSlide = (activeSlide + 1) % <?php echo $hero_query->post_count ?: 1; ?> }, <?php echo absint($hero_speed); ?>)">
    <div class="ow-hero__slides">
        <?php if ($hero_query->have_posts()): $i = 0; while ($hero_query->have_posts()): $hero_query->the_post();
            $pid = get_the_ID();
            $backdrop = onwatch_get_backdrop($pid, 'w1280');
            $post_type = get_post_type();
            $quality = onwatch_get_quality($pid);
            $type_label = $post_type === 'movies' ? __('فيلم', 'onwatch') : __('مسلسل', 'onwatch');
            $rating = onwatch_get_rating($pid);
            $year = onwatch_get_year($pid);
            $runtime = onwatch_get_runtime($pid);
            $genres = onwatch_get_term_list($pid, 'category', 3);
            $excerpt = wp_trim_words(get_the_excerpt() ?: get_the_content(), 20);
        ?>
        <div class="ow-hero__slide" x-show="activeSlide === <?php echo $i; ?>" x-cloak style="background-image: url('<?php echo esc_url($backdrop ?: ''); ?>')">
            <div class="ow-hero__gradient"></div>
            <div class="ow-container ow-hero__content">
                <div class="ow-hero__badges">
                    <?php if ($quality): ?><span class="ow-badge ow-badge--default"><?php echo esc_html($quality); ?></span><?php endif; ?>
                    <span class="ow-badge ow-badge--default"><?php echo esc_html($type_label); ?></span>
                </div>
                <h2 class="ow-hero__title"><?php the_title(); ?></h2>
                <?php if (get_theme_mod('onwatch_show_meta_info', true)): ?>
                <div class="ow-hero__meta">
                    <?php if ($rating > 0): ?><span><?php echo esc_html($rating); ?> تقييم</span><?php endif; ?>
                    <?php if ($year): ?><span><?php echo esc_html($year); ?></span><?php endif; ?>
                    <?php if ($runtime > 0): ?><span><?php echo sprintf(__('%d دقيقة', 'onwatch'), $runtime); ?></span><?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($genres)): ?>
                <div class="ow-hero__genres">
                    <?php foreach ($genres as $g): ?>
                    <a href="<?php echo esc_url($g['url']); ?>" class="ow-hero__genre"><?php echo esc_html($g['name']); ?></a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <p class="ow-hero__excerpt"><?php echo esc_html($excerpt); ?></p>
                <div class="ow-hero__actions">
                    <a href="<?php the_permalink(); ?>" class="ow-btn ow-btn--primary ow-btn--lg"><?php _e('مشاهدة الآن', 'onwatch'); ?></a>
                    <a href="<?php the_permalink(); ?>" class="ow-btn ow-btn--outline ow-btn--lg"><?php _e('التفاصيل', 'onwatch'); ?></a>
                </div>
            </div>
        </div>
        <?php $i++; endwhile; wp_reset_postdata(); endif; ?>
    </div>

    <div class="ow-hero__dots">
        <?php for ($d = 0; $d < $hero_query->post_count; $d++): ?>
        <button class="ow-hero__dot" :class="{'is-active': activeSlide === <?php echo $d; ?>}" @click="activeSlide = <?php echo $d; ?>"></button>
        <?php endfor; ?>
    </div>
</section>
<?php elseif ($hero_style === 'grid'): ?>
<section class="ow-section">
    <div class="ow-container">
        <div class="ow-grid ow-grid--4">
            <?php if ($hero_query->have_posts()): $i = 0; while ($hero_query->have_posts() && $i < 4): $hero_query->the_post();
                $pid = get_the_ID();
                $poster = onwatch_get_poster($pid, 'w342');
            ?>
            <a href="<?php the_permalink(); ?>" class="ow-card" style="grid-column: span <?php echo $i === 0 ? 2 : 1; ?>">
                <div class="ow-card__image">
                    <img src="<?php echo esc_url($poster); ?>" loading="lazy" alt="<?php the_title_attribute(); ?>" width="342" height="513" onerror="this.onerror=null;this.src='<?php echo get_template_directory_uri(); ?>/resources/assets/img/placeholder.svg'">
                </div>
                <h3 class="ow-card__title"><?php the_title(); ?></h3>
            </a>
            <?php $i++; endwhile; wp_reset_postdata(); endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>
<?php endif; ?>

<div class="ow-container">

<?php foreach ($section_order as $section): $section = trim($section); switch ($section):

    case 'movies':
    if (!$show_movies) break;
    $movies_query = new WP_Query([
        'post_type'      => 'movies',
        'posts_per_page' => $latest_movies_count,
        'post_status'    => 'publish',
        'no_found_rows'  => true,
    ]);
    if ($movies_query->have_posts()):
    ?>
    <section class="ow-section ow-section--compact">
        <?php if ($show_titles): ?>
        <header class="ow-section__header">
            <h2 class="ow-section__title"><?php _e('أحدث الأفلام', 'onwatch'); ?></h2>
            <a href="<?php echo get_post_type_archive_link('movies'); ?>" class="ow-section__link"><?php _e('عرض الكل', 'onwatch'); ?> ←</a>
        </header>
        <?php endif; ?>
        <div class="ow-row" x-ref="moviesRow" @mousedown="dragStart" @mousemove="dragMove" @mouseup="dragEnd" @mouseleave="dragEnd">
            <?php while ($movies_query->have_posts()): $movies_query->the_post(); get_template_part('resources/views/components/card', 'movie'); endwhile; wp_reset_postdata(); ?>
        </div>
    </section>
    <?php endif;
    break;

    case 'series':
    if (!$show_series) break;
    $series_query = new WP_Query([
        'post_type'      => 'series',
        'posts_per_page' => $latest_series_count,
        'post_status'    => 'publish',
        'no_found_rows'  => true,
    ]);
    if ($series_query->have_posts()):
    ?>
    <section class="ow-section ow-section--compact">
        <?php if ($show_titles): ?>
        <header class="ow-section__header">
            <h2 class="ow-section__title"><?php _e('أحدث المسلسلات', 'onwatch'); ?></h2>
            <a href="<?php echo get_post_type_archive_link('series'); ?>" class="ow-section__link"><?php _e('عرض الكل', 'onwatch'); ?> ←</a>
        </header>
        <?php endif; ?>
        <div class="ow-row" x-ref="seriesRow" @mousedown="dragStart" @mousemove="dragMove" @mouseup="dragEnd" @mouseleave="dragEnd">
            <?php while ($series_query->have_posts()): $series_query->the_post(); get_template_part('resources/views/components/card', 'series'); endwhile; wp_reset_postdata(); ?>
        </div>
    </section>
    <?php endif;
    break;

    case 'trending':
    if (!$show_trending) break;
    $trending = get_transient('onwatch_trending');
    if (false === $trending) {
        $trending_args = [
            'post_type'      => ['movies', 'series'],
            'posts_per_page' => $trending_count,
            'post_status'    => 'publish',
            'no_found_rows'  => true,
        ];
        if ($trending_source === 'rating') {
            $trending_args['meta_key'] = 'rating';
            $trending_args['orderby'] = 'meta_value_num';
            $trending_args['order'] = 'DESC';
        } elseif ($trending_source === 'date') {
            $trending_args['orderby'] = 'date';
            $trending_args['order'] = 'DESC';
        } elseif ($trending_source === 'random') {
            $trending_args['orderby'] = 'rand';
        }
        $trending_query = new WP_Query($trending_args);
        $trending = $trending_query;
        set_transient('onwatch_trending', $trending_query, $cache_ttl);
    }
    if ($trending->have_posts()):
    ?>
    <section class="ow-section">
        <?php if ($show_titles): ?>
        <header class="ow-section__header">
            <h2 class="ow-section__title"><?php _e('الأكثر مشاهدة', 'onwatch'); ?></h2>
        </header>
        <?php endif; ?>
        <div class="ow-grid">
            <?php while ($trending->have_posts()): $trending->the_post(); get_template_part('resources/views/components/card', 'movie'); endwhile; wp_reset_postdata(); ?>
        </div>
    </section>
    <?php endif;
    break;

    case 'genres':
    if (!$show_genres) break;
    ?>
    <?php
    $genres = get_terms(['taxonomy' => 'category', 'hide_empty' => true, 'number' => 6, 'orderby' => 'count', 'order' => 'DESC']);
    $first_genre_slug = !empty($genres) && !is_wp_error($genres) ? $genres[0]->slug : '';
    ?>
    <section class="ow-section ow-genre-section"<?php if ($first_genre_slug): ?> data-first-genre="<?php echo esc_attr($first_genre_slug); ?>"<?php endif; ?>>
        <?php if ($show_titles): ?>
        <header class="ow-section__header">
            <h2 class="ow-section__title"><?php _e('تصفح حسب النوع', 'onwatch'); ?></h2>
        </header>
        <?php endif; ?>
        <?php if (!empty($genres) && !is_wp_error($genres)): ?>
        <div class="ow-genre-tabs">
            <?php foreach ($genres as $g): ?>
            <button class="ow-genre-tab" :class="{'is-active': activeGenre === '<?php echo esc_js($g->slug); ?>'}" @click="activeGenre = '<?php echo esc_js($g->slug); ?>'; loadGenre('<?php echo esc_js($g->slug); ?>')"><?php echo esc_html($g->name); ?></button>
            <?php endforeach; ?>
        </div>
        <div class="ow-genre-content" x-html="genreContent" x-show="genreContent"></div>
        <?php endif; ?>
    </section>
    <?php
    break;

    case 'episodes':
    if (!$show_ep_latest) break;
    $ep_latest = get_transient('onwatch_latest_episodes');
    if (false === $ep_latest) {
        $ep_terms = get_terms([
            'taxonomy'   => 'episodes',
            'hide_empty' => false,
            'number'     => $ep_latest_count,
            'orderby'    => 'term_id',
            'order'      => 'DESC',
        ]);
        $ep_latest = $ep_terms;
        set_transient('onwatch_latest_episodes', $ep_terms, $cache_ttl);
    }
    if (!empty($ep_latest) && !is_wp_error($ep_latest)):
    ?>
    <section class="ow-section">
        <?php if ($show_titles): ?>
        <header class="ow-section__header">
            <h2 class="ow-section__title"><?php _e('آخر الحلقات المضافة', 'onwatch'); ?></h2>
        </header>
        <?php endif; ?>
        <div class="ow-watch-list">
            <?php foreach ($ep_latest as $ep):
                $series_id = get_term_meta($ep->term_id, 'tr_id_post', true);
                $season_num = get_term_meta($ep->term_id, 'season_number', true);
                $ep_num = get_term_meta($ep->term_id, 'episode_number', true);
                $still = onwatch_get_still($ep->term_id, 'w300');
            ?>
            <a href="<?php echo esc_url(get_term_link($ep)); ?>" class="ow-watch-item">
                <div class="ow-watch-item__thumb">
                    <img src="<?php echo esc_url($still ?: get_template_directory_uri() . '/resources/assets/img/placeholder.svg'); ?>" loading="lazy" width="120" height="68" onerror="this.onerror=null;this.src='<?php echo get_template_directory_uri(); ?>/resources/assets/img/placeholder.svg'">
                </div>
                <div class="ow-watch-item__info">
                    <strong><?php echo $series_id ? esc_html(get_the_title($series_id)) : ''; ?></strong>
                    <span>S<?php echo str_pad($season_num, 2, '0', STR_PAD_LEFT); ?>E<?php echo str_pad($ep_num, 2, '0', STR_PAD_LEFT); ?> — <?php echo esc_html($ep->name); ?></span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif;
    break;

    case 'continue':
    if (!$show_continue) break;
    ?>
    <section class="ow-section" x-show="continueWatching.length > 0" x-cloak>
        <?php if ($show_titles): ?>
        <header class="ow-section__header">
            <h2 class="ow-section__title"><?php _e('مشاهدة مستمرة', 'onwatch'); ?></h2>
            <button class="ow-btn ow-btn--ghost" @click="clearHistory"><?php _e('مسح', 'onwatch'); ?></button>
        </header>
        <?php endif; ?>
        <div class="ow-row" x-html="continueHtml"></div>
    </section>
    <?php
    break;

endswitch; endforeach; ?>

</div>
