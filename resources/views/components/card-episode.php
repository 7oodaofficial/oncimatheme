<?php
$term_id = $args['term_id'] ?? get_queried_object_id();
$term = get_term($term_id);
if (!$term || is_wp_error($term)) return;

$series_id = get_term_meta($term_id, 'tr_id_post', true);
$season_num = get_term_meta($term_id, 'season_number', true);
$ep_num = get_term_meta($term_id, 'episode_number', true);
$still = onwatch_get_still($term_id, 'w300');
$title = $term->name;
$link = get_term_link($term);
?>
<a href="<?php echo esc_url($link); ?>" class="ow-episode-card">
    <div class="ow-episode-card__thumb">
        <img src="<?php echo esc_url($still ?: get_template_directory_uri() . '/resources/assets/img/placeholder.svg'); ?>" loading="lazy" alt="<?php echo esc_attr($title); ?>" width="300" height="169" onerror="this.onerror=null;this.src='<?php echo get_template_directory_uri(); ?>/resources/assets/img/placeholder.svg'">
        <span class="ow-episode-card__number">S<?php echo str_pad($season_num, 2, '0', STR_PAD_LEFT); ?>E<?php echo str_pad($ep_num, 2, '0', STR_PAD_LEFT); ?></span>
    </div>
    <div class="ow-episode-card__info">
        <h3 class="ow-episode-card__title"><?php echo esc_html($title); ?></h3>
    </div>
</a>
