<?php
defined('ABSPATH') || exit;

if (!function_exists('onwatch_register_post_types')):
function onwatch_register_post_types() {
    if (post_type_exists('movies')) return;

    $labels_movies = array(
        'name'                  => _x('Movies', 'Post Type General Name', 'onwatch'),
        'singular_name'         => _x('Movie', 'Post Type Singular Name', 'onwatch'),
        'menu_name'             => __('Movies', 'onwatch'),
        'name_admin_bar'        => __('Movies', 'onwatch'),
        'archives'              => __('Movie Archives', 'onwatch'),
        'all_items'             => __('All Movies', 'onwatch'),
        'add_new_item'          => __('Add New Movie', 'onwatch'),
        'add_new'               => __('Add New', 'onwatch'),
        'new_item'              => __('New Movie', 'onwatch'),
        'edit_item'             => __('Edit Movie', 'onwatch'),
        'update_item'           => __('Update Movie', 'onwatch'),
        'view_item'             => __('View Movie', 'onwatch'),
        'view_items'            => __('View Movies', 'onwatch'),
        'search_items'          => __('Search Movies', 'onwatch'),
        'not_found'             => __('Not found', 'onwatch'),
        'featured_image'        => __('Poster', 'onwatch'),
        'set_featured_image'    => __('Set poster', 'onwatch'),
        'remove_featured_image' => __('Remove poster', 'onwatch'),
        'use_featured_image'    => __('Use as poster', 'onwatch'),
    );
    $args_movies = array(
        'label'              => __('Movie', 'onwatch'),
        'description'        => __('Movies post type', 'onwatch'),
        'labels'             => $labels_movies,
        'supports'           => array('title', 'editor', 'thumbnail', 'comments', 'trackbacks', 'revisions', 'custom-fields', 'page-attributes'),
        'taxonomies'         => array('category', 'post_tag'),
        'hierarchical'       => false,
        'public'             => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'menu_position'      => 5,
        'menu_icon'          => 'dashicons-video-alt2',
        'can_export'         => true,
        'has_archive'        => true,
        'exclude_from_search'=> false,
        'publicly_queryable' => true,
        'capability_type'    => 'post',
        'rewrite'            => array('slug' => TR_GRABBER_SLUG_MOVIES, 'with_front' => true),
    );
    register_post_type('movies', $args_movies);

    if (post_type_exists('series')) return;

    $labels_series = array(
        'name'                  => _x('Series', 'Post Type General Name', 'onwatch'),
        'singular_name'         => _x('Serie', 'Post Type Singular Name', 'onwatch'),
        'menu_name'             => __('Series', 'onwatch'),
        'name_admin_bar'        => __('Series', 'onwatch'),
        'all_items'             => __('All Series', 'onwatch'),
        'add_new_item'          => __('Add New Series', 'onwatch'),
        'add_new'               => __('Add New', 'onwatch'),
        'new_item'              => __('New Series', 'onwatch'),
        'edit_item'             => __('Edit Series', 'onwatch'),
        'update_item'           => __('Update Series', 'onwatch'),
        'view_item'             => __('View Series', 'onwatch'),
        'view_items'            => __('View Series', 'onwatch'),
        'search_items'          => __('Search Series', 'onwatch'),
        'not_found'             => __('Not found', 'onwatch'),
        'featured_image'        => __('Poster', 'onwatch'),
        'set_featured_image'    => __('Set poster', 'onwatch'),
        'remove_featured_image' => __('Remove poster', 'onwatch'),
        'use_featured_image'    => __('Use as poster', 'onwatch'),
    );
    $args_series = array(
        'label'              => __('Serie', 'onwatch'),
        'description'        => __('Series post type', 'onwatch'),
        'labels'             => $labels_series,
        'supports'           => array('title', 'editor', 'thumbnail', 'comments', 'trackbacks', 'revisions', 'custom-fields', 'page-attributes'),
        'taxonomies'         => array('category', 'post_tag'),
        'hierarchical'       => false,
        'public'             => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'menu_position'      => 5,
        'menu_icon'          => 'dashicons-editor-video',
        'can_export'         => true,
        'has_archive'        => true,
        'exclude_from_search'=> false,
        'publicly_queryable' => true,
        'capability_type'    => 'post',
        'rewrite'            => array('slug' => TR_GRABBER_SLUG_SERIES, 'with_front' => true),
    );
    register_post_type('series', $args_series);
}
endif;

add_action('init', 'onwatch_register_post_types', 0);