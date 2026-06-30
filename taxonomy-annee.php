<?php get_header();

$current = get_queried_object();
$current_year = (int)($current->slug ?? date('Y'));
$years = range(date('Y'), 2000);
?>
<div class="ow-container">
    <nav class="ow-year-strip">
        <?php foreach ($years as $y): ?>
        <a href="<?php echo esc_url(get_term_link((string)$y, 'annee')); ?>" class="ow-year-link <?php echo $y === $current_year ? 'is-active' : ''; ?>"><?php echo $y; ?></a>
        <?php endforeach; ?>
    </nav>

    <header class="ow-section__header">
        <h1 class="ow-section__title"><?php printf(__('إنتاج %d', 'onwatch'), $current_year); ?></h1>
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
