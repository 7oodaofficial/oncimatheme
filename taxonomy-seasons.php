<?php
$term = get_queried_object();
$series_id = get_term_meta($term->term_id, 'tr_id_post', true);
if ($series_id) {
    wp_redirect(get_permalink($series_id) . '#seasons');
    exit;
}
wp_redirect(home_url());
exit;
