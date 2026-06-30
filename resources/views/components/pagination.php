<?php
global $wp_query;
if ($wp_query->max_num_pages <= 1) return;
$infinite = get_theme_mod('onwatch_infinite_scroll', false);
?>
<div class="ow-pagination" x-data="{ loading: false }">
    <?php if ($infinite): ?>
    <div x-intersect="loadMore">
        <div class="ow-skeleton ow-skeleton--card" x-show="loading"></div>
    </div>
    <?php else: ?>
    <div class="ow-pagination__list">
        <?php
        $big = 999999999;
        echo '<div class="ow-pagination__list">';
        echo paginate_links([
            'base'      => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
            'format'    => '?paged=%#%',
            'current'   => max(1, get_query_var('paged')),
            'total'     => $wp_query->max_num_pages,
            'prev_text' => '←',
            'next_text' => '→',
        ]);
        echo '</div>';
        ?>
    </div>
    <?php endif; ?>
</div>
