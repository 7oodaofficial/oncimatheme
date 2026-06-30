<?php get_header();

$letters = array_merge(['#'], range('A', 'Z'));
$current_letter = get_queried_object();
$current_slug = strtoupper($current_letter->slug ?? '');
?>
<div class="ow-container">
    <div class="ow-a-z">
        <nav class="ow-a-z__strip">
            <?php foreach ($letters as $l): ?>
            <a href="<?php echo esc_url(get_term_link($l, 'letters')); ?>" class="ow-a-z__letter <?php echo $current_slug === $l ? 'is-active' : ''; ?>"><?php echo esc_html($l); ?></a>
            <?php endforeach; ?>
        </nav>

        <header class="ow-section__header">
            <h1 class="ow-section__title"><?php printf(__('حرف %s', 'onwatch'), esc_html($current_letter->name ?? $current_slug)); ?></h1>
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
</div>
<?php get_footer();
