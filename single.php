<?php get_header();

if (is_single()) {
    $post_type = get_post_type();
    switch ($post_type) {
        case 'movies':
            get_template_part('resources/views/indexes/single', 'movie');
            break;
        case 'series':
            get_template_part('resources/views/indexes/single', 'series');
            break;
        case 'post':
        default:
            get_template_part('resources/views/indexes/single', 'post');
            break;
    }
}

get_footer();
