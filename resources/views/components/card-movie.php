<?php
$pid = get_the_ID();
$title = get_the_title();
$permalink = get_permalink();
$poster = onwatch_get_poster($pid, 'w342');
$rating = onwatch_get_rating($pid);
$year = onwatch_get_year($pid);
$quality = onwatch_get_quality($pid);
$is_new = onwatch_is_new($pid);
$genres = onwatch_get_term_list($pid, 'category', 1);
$country = onwatch_get_term_list($pid, 'country', 1);
?>
<article class="ow-card" data-post-id="<?php echo $pid; ?>">
    <a href="<?php echo esc_url($permalink); ?>" class="ow-card__link">
        <div class="ow-card__image">
            <?php if ($poster): ?>
            <img src="<?php echo esc_url($poster); ?>" loading="lazy" alt="<?php echo esc_attr($title); ?>" width="342" height="513">
            <?php else: ?>
            <div class="ow-card__placeholder"></div>
            <?php endif; ?>

            <?php if ($quality): ?>
            <span class="ow-card__badge ow-card__badge--tr"><?php echo esc_html($quality); ?></span>
            <?php endif; ?>

            <?php if ($is_new): ?>
            <span class="ow-card__badge ow-card__badge--tl"><?php _e('جديد', 'onwatch'); ?></span>
            <?php endif; ?>

            <?php if ($rating > 0): ?>
            <span class="ow-card__rating"><?php echo esc_html($rating); ?></span>
            <?php endif; ?>

            <div class="ow-card__overlay">
                <span class="ow-btn ow-btn--sm ow-btn--primary"><?php _e('مشاهدة', 'onwatch'); ?></span>
                <span class="ow-btn ow-btn--sm ow-btn--secondary" @click.prevent="toggleFav(<?php echo $pid; ?>, '<?php echo esc_js($title); ?>', '<?php echo esc_url($permalink); ?>', '<?php echo esc_url($poster); ?>')"><?php _e('مفضلة', 'onwatch'); ?></span>
            </div>

            <div class="ow-card__progress" style="display:none" :data-progress="<?php echo $pid; ?>">
                <span class="ow-card__progress-bar" style="width:0%"></span>
            </div>
        </div>
        <h3 class="ow-card__title"><?php echo esc_html($title); ?></h3>
        <div class="ow-card__meta">
            <?php if ($year): ?><span><?php echo esc_html($year); ?></span><?php endif; ?>
            <?php if (!empty($genres)): ?><span><?php echo esc_html($genres[0]['name']); ?></span><?php endif; ?>
            <?php if (!empty($country)): ?><span><?php echo esc_html($country[0]['name']); ?></span><?php endif; ?>
        </div>
    </a>
</article>
