<?php get_header();
$term = get_queried_object();
?>
<div class="ow-container">
    <header class="ow-section__header">
        <h1 class="ow-section__title"><?php printf(__('أفلام %s', 'onwatch'), esc_html($term->name)); ?></h1>
        <?php if (!empty($term->description)): ?>
        <p class="ow-text-muted"><?php echo esc_html($term->description); ?></p>
        <?php endif; ?>
    </header>
    <div class="ow-grid">
        <?php if (have_posts()): while (have_posts()): the_post(); get_template_part('resources/views/components/card', 'movie'); endwhile; endif; ?>
    </div>
    <?php get_template_part('resources/views/components/pagination'); ?>
</div>
<?php get_footer();
