<?php get_header();
$term = get_queried_object();
?>
<div class="ow-container">
    <header class="ow-section__header" style="padding-top: 1rem;">
        <h1 class="ow-section__title"><?php echo esc_html($term->name); ?></h1>
        <?php if (!empty($term->description)): ?>
        <p class="ow-text-muted ow-mb-2"><?php echo esc_html($term->description); ?></p>
        <?php endif; ?>
        <span class="ow-archive__count"><?php echo $wp_query->found_posts; ?> <?php _e('نتيجة', 'onwatch'); ?></span>
    </header>
    <div class="ow-grid">
        <?php if (have_posts()): while (have_posts()): the_post();
            $pt = get_post_type();
            if ($pt === 'movies') get_template_part('resources/views/components/card', 'movie');
            else get_template_part('resources/views/components/card', 'series');
        endwhile; endif; ?>
    </div>
    <?php if (!have_posts()): ?>
    <div class="ow-empty">
        <div class="ow-empty__title"><?php _e('لا توجد نتائج', 'onwatch'); ?></div>
    </div>
    <?php endif; ?>
    <?php get_template_part('resources/views/components/pagination'); ?>
</div>
<?php get_footer();
