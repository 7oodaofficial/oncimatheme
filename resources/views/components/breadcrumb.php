<nav class="ow-breadcrumb" aria-label="<?php _e('مسارات التنقل', 'onwatch'); ?>">
    <ol class="ow-breadcrumb__list">
        <li class="ow-breadcrumb__item"><a href="<?php echo esc_url(home_url('/')); ?>"><?php _e('الرئيسية', 'onwatch'); ?></a></li>
        <?php
        if (is_singular(['movies', 'series'])) {
            $post_type = get_post_type();
            $archive_link = get_post_type_archive_link($post_type);
            $archive_label = $post_type === 'movies' ? __('أفلام', 'onwatch') : __('مسلسلات', 'onwatch');
            if ($archive_link) {
                echo '<li class="ow-breadcrumb__item"><a href="' . esc_url($archive_link) . '">' . $archive_label . '</a></li>';
            }
            echo '<li class="ow-breadcrumb__item ow-breadcrumb__item--current">' . get_the_title() . '</li>';
        } elseif (is_tax('episodes')) {
            $term = get_queried_object();
            $series_id = get_term_meta($term->term_id, 'tr_id_post', true);
            echo '<li class="ow-breadcrumb__item"><a href="' . get_post_type_archive_link('series') . '">' . __('مسلسلات', 'onwatch') . '</a></li>';
            if ($series_id) {
                echo '<li class="ow-breadcrumb__item"><a href="' . get_permalink($series_id) . '">' . get_the_title($series_id) . '</a></li>';
            }
            echo '<li class="ow-breadcrumb__item ow-breadcrumb__item--current">' . $term->name . '</li>';
        } elseif (is_post_type_archive('movies')) {
            echo '<li class="ow-breadcrumb__item ow-breadcrumb__item--current">' . __('أفلام', 'onwatch') . '</li>';
        } elseif (is_post_type_archive('series')) {
            echo '<li class="ow-breadcrumb__item ow-breadcrumb__item--current">' . __('مسلسلات', 'onwatch') . '</li>';
        } elseif (is_search()) {
            echo '<li class="ow-breadcrumb__item ow-breadcrumb__item--current">' . sprintf(__('بحث: %s', 'onwatch'), get_search_query()) . '</li>';
        } elseif (is_category()) {
            echo '<li class="ow-breadcrumb__item"><a href="' . get_post_type_archive_link('movies') . '">' . __('أفلام', 'onwatch') . '</a></li>';
            echo '<li class="ow-breadcrumb__item ow-breadcrumb__item--current">' . single_cat_title('', false) . '</li>';
        } elseif (is_tax()) {
            $term = get_queried_object();
            echo '<li class="ow-breadcrumb__item ow-breadcrumb__item--current">' . $term->name . '</li>';
        } elseif (is_tag()) {
            echo '<li class="ow-breadcrumb__item ow-breadcrumb__item--current">' . single_tag_title('', false) . '</li>';
        }
        ?>
    </ol>
</nav>
