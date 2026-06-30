<?php get_header(); ?>
<div class="ow-container">
    <header class="ow-section__header" style="padding-top: 1rem;">
        <h1 class="ow-section__title"><?php printf(__('نتائج البحث عن: %s', 'onwatch'), get_search_query()); ?></h1>
        <span class="ow-archive__count"><?php echo $wp_query->found_posts; ?> <?php _e('نتيجة', 'onwatch'); ?></span>
    </header>
    <div class="ow-grid">
        <?php if (have_posts()): while (have_posts()): the_post();
            $pt = get_post_type();
            if ($pt === 'movies'):
                get_template_part('resources/views/components/card', 'movie');
            elseif ($pt === 'series'):
                get_template_part('resources/views/components/card', 'series');
            else: ?>
            <article class="ow-card" style="background:var(--card-bg);padding:1rem;border-radius:var(--radius-md)">
                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                <p class="ow-text-muted ow-mt-1"><?php echo wp_trim_words(get_the_excerpt(), 20); ?></p>
            </article>
            <?php endif;
        endwhile; endif; ?>
    </div>
    <?php if (!have_posts()): ?>
    <div class="ow-empty">
        <div class="ow-empty__title"><?php _e('لا توجد نتائج', 'onwatch'); ?></div>
        <p class="ow-empty__desc"><?php _e('لم نعثر على نتائج تطابق بحثك.', 'onwatch'); ?></p>
    </div>
    <?php endif; ?>
    <?php get_template_part('resources/views/components/pagination'); ?>
</div>
<?php get_footer();
