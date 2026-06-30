<?php get_header();
$layout = get_theme_mod('onwatch_archive_layout', 'grid');
$columns = get_theme_mod('onwatch_archive_columns', 4);
$show_filters = get_theme_mod('onwatch_show_filters', true);
$show_pagination = get_theme_mod('onwatch_show_pagination', true);
$infinite_scroll = get_theme_mod('onwatch_infinite_scroll', false);
?>
<div class="ow-container">
    <header class="ow-section__header" style="padding-top: 1rem;">
        <h1 class="ow-section__title"><?php _e('الأفلام', 'onwatch'); ?></h1>
        <span class="ow-archive__count"><?php echo $wp_query->found_posts; ?> <?php _e('فيلم', 'onwatch'); ?></span>
    </header>

    <?php if ($show_filters): ?>
    <form class="ow-filter-bar" method="get">
        <select name="genre" class="ow-input">
            <option value=""><?php _e('النوع', 'onwatch'); ?></option>
            <?php
            $genres = get_terms(['taxonomy' => 'category', 'hide_empty' => true]);
            foreach ($genres as $g):
                $selected = isset($_GET['genre']) && $_GET['genre'] == $g->slug ? 'selected' : '';
            ?>
            <option value="<?php echo esc_attr($g->slug); ?>" <?php echo $selected; ?>><?php echo esc_html($g->name); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="country" class="ow-input">
            <option value=""><?php _e('البلد', 'onwatch'); ?></option>
            <?php
            $countries = get_terms(['taxonomy' => 'country', 'hide_empty' => true]);
            foreach ($countries as $c):
                $selected = isset($_GET['country']) && $_GET['country'] == $c->slug ? 'selected' : '';
            ?>
            <option value="<?php echo esc_attr($c->slug); ?>" <?php echo $selected; ?>><?php echo esc_html($c->name); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="year" class="ow-input">
            <option value=""><?php _e('السنة', 'onwatch'); ?></option>
            <?php for ($y = date('Y'); $y >= 2000; $y--):
                $selected = isset($_GET['year']) && $_GET['year'] == $y ? 'selected' : '';
            ?>
            <option value="<?php echo $y; ?>" <?php echo $selected; ?>><?php echo $y; ?></option>
            <?php endfor; ?>
        </select>
        <select name="orderby" class="ow-input">
            <option value="date" <?php echo (isset($_GET['orderby']) && $_GET['orderby'] == 'date') ? 'selected' : ''; ?>><?php _e('الأحدث', 'onwatch'); ?></option>
            <option value="rating" <?php echo (isset($_GET['orderby']) && $_GET['orderby'] == 'rating') ? 'selected' : ''; ?>><?php _e('التقييم', 'onwatch'); ?></option>
            <option value="title" <?php echo (isset($_GET['orderby']) && $_GET['orderby'] == 'title') ? 'selected' : ''; ?>><?php _e('الترتيب الأبجدي', 'onwatch'); ?></option>
        </select>
        <button type="submit" class="ow-btn ow-btn--primary"><?php _e('تصفية', 'onwatch'); ?></button>
    </form>
    <?php endif; ?>

    <div class="ow-grid" data-archive-grid data-infinite="<?php echo $infinite_scroll ? 'true' : 'false'; ?>">
        <?php if (have_posts()): while (have_posts()): the_post(); get_template_part('resources/views/components/card', 'movie'); endwhile; endif; ?>
    </div>

    <?php if (!have_posts()): ?>
    <div class="ow-empty">
        <div class="ow-empty__title"><?php _e('لا توجد أفلام', 'onwatch'); ?></div>
        <p class="ow-empty__desc"><?php _e('لم يتم إضافة أي أفلام بعد.', 'onwatch'); ?></p>
    </div>
    <?php endif; ?>

    <?php if ($show_pagination && !$infinite_scroll): ?>
    <?php get_template_part('resources/views/components/pagination'); ?>
    <?php endif; ?>
</div>
<?php get_footer();