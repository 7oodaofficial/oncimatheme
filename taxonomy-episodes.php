<?php get_header();

if (is_tax('episodes')) {
    $object = get_queried_object();
    get_template_part('resources/views/indexes/watch', 'episode', ['object' => $object]);
}

get_footer();
