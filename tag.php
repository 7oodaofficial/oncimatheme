<?php get_header();
$term = get_queried_object();
?>
<div class="ow-container">
    <header class="ow-section__header" style="padding-top: 1rem;">
        <h1 class="ow-section__title"><?php printf(__('وسم: %s', 'onwatch'), single_tag_title('', false)); ?></h1>
        <?php if (!empty($term->description)): ?>
        <p class="ow-text-muted ow-mb-2"><?php echo esc_html($term->description); ?></p>
        <?php endif; ?>
    </header>
    <div class="ow-grid">
        <?php if (have_posts()): while (have_posts()): the_post();
            $pt = get_post_type();
            if ($pt === 'movies') get_template_part('resources/views/components/card', 'movie');
            else get_template_part('resources/views/components/card', 'series');
        endwhile; endif; ?>
    </div>
    <?php get_template_part('resources/views/components/pagination'); ?>
</div>
<?php get_footer();
