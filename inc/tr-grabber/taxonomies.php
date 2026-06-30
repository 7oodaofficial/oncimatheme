<?php
defined('ABSPATH') || exit;

if (!function_exists('onwatch_register_taxonomies')):
function onwatch_register_taxonomies() {
    if (!taxonomy_exists('server'))
        register_taxonomy('server', '', array(
            'hierarchical' => true, 'public' => true, 'query_var' => true,
            'labels' => array('name' => __('Servers','onwatch'), 'singular_name' => __('Server','onwatch'), 'menu_name' => __('Servers','onwatch')),
            'rewrite' => array('slug' => 'server', 'with_front' => true, 'hierarchical' => true),
        ));
    if (!taxonomy_exists('language'))
        register_taxonomy('language', '', array(
            'hierarchical' => true, 'public' => true, 'query_var' => true,
            'labels' => array('name' => __('Languages','onwatch'), 'singular_name' => __('Language','onwatch'), 'menu_name' => __('Languages','onwatch')),
            'rewrite' => array('slug' => 'lang', 'with_front' => true, 'hierarchical' => true),
        ));
    if (!taxonomy_exists('quality'))
        register_taxonomy('quality', '', array(
            'hierarchical' => true, 'public' => true, 'query_var' => true,
            'labels' => array('name' => __('Quality','onwatch'), 'singular_name' => __('Quality','onwatch'), 'menu_name' => __('Quality','onwatch')),
            'rewrite' => array('slug' => 'quality', 'with_front' => true, 'hierarchical' => true),
        ));
    if (!taxonomy_exists('letters'))
        register_taxonomy('letters', array('movies', 'series'), array(
            'hierarchical' => true, 'public' => true, 'query_var' => true,
            'labels' => array('name' => __('Letters','onwatch'), 'singular_name' => __('Letter','onwatch'), 'menu_name' => __('Letters','onwatch')),
            'rewrite' => array('slug' => TR_GRABBER_FIELD_LETTERS, 'with_front' => true, 'hierarchical' => true),
        ));
    if (!taxonomy_exists('seasons'))
        register_taxonomy('seasons', '', array(
            'hierarchical' => false, 'public' => true, 'query_var' => true,
            'labels' => array('name' => __('Seasons','onwatch'), 'singular_name' => __('Season','onwatch'), 'menu_name' => __('Seasons','onwatch')),
            'rewrite' => array('slug' => TR_GRABBER_PREFIX_SEASON, 'with_front' => true, 'hierarchical' => true),
        ));
    if (!taxonomy_exists('episodes'))
        register_taxonomy('episodes', '', array(
            'hierarchical' => false, 'public' => true, 'query_var' => true,
            'labels' => array('name' => __('Episodes','onwatch'), 'singular_name' => __('Episode','onwatch'), 'menu_name' => __('Episodes','onwatch')),
            'rewrite' => array('slug' => TR_GRABBER_PREFIX_EPISODE, 'with_front' => true, 'hierarchical' => true),
        ));
    if (!taxonomy_exists('country'))
        register_taxonomy('country', 'movies', array(
            'hierarchical' => false, 'public' => true, 'query_var' => true,
            'labels' => array('name' => __('Countries','onwatch'), 'singular_name' => __('Country','onwatch'), 'menu_name' => __('Countries','onwatch')),
            'rewrite' => array('slug' => 'country', 'with_front' => true, 'hierarchical' => true),
        ));
    if (!taxonomy_exists('annee'))
        register_taxonomy('annee', array('movies', 'series'), array(
            'hierarchical' => true, 'public' => true, 'query_var' => true,
            'labels' => array('name' => __('Years','onwatch'), 'singular_name' => __('Year','onwatch'), 'menu_name' => __('Years','onwatch')),
            'rewrite' => array('slug' => TR_GRABBER_PREFIX_YEAR, 'with_front' => true, 'hierarchical' => true),
        ));
    if (!taxonomy_exists('directors'))
        register_taxonomy('directors', 'movies', array(
            'hierarchical' => false, 'public' => true, 'query_var' => true,
            'labels' => array('name' => __('Directors','onwatch'), 'singular_name' => __('Director','onwatch'), 'menu_name' => __('Directors','onwatch')),
            'rewrite' => array('slug' => TR_GRABBER_PREFIX_DIRECTOR, 'with_front' => true, 'hierarchical' => true),
        ));
    if (!taxonomy_exists('cast'))
        register_taxonomy('cast', 'movies', array(
            'hierarchical' => false, 'public' => true, 'query_var' => true,
            'labels' => array('name' => __('Cast','onwatch'), 'singular_name' => __('Cast','onwatch'), 'menu_name' => __('Cast','onwatch')),
            'rewrite' => array('slug' => TR_GRABBER_PREFIX_CAST, 'with_front' => true, 'hierarchical' => true),
        ));
    if (!taxonomy_exists('directors_tv'))
        register_taxonomy('directors_tv', 'series', array(
            'hierarchical' => false, 'public' => true, 'query_var' => true,
            'labels' => array('name' => __('Directors','onwatch'), 'singular_name' => __('Director','onwatch'), 'menu_name' => __('Directors','onwatch')),
            'rewrite' => array('slug' => TR_GRABBER_PREFIX_DIRECTORTV, 'with_front' => true, 'hierarchical' => true),
        ));
    if (!taxonomy_exists('cast_tv'))
        register_taxonomy('cast_tv', 'series', array(
            'hierarchical' => false, 'public' => true, 'query_var' => true,
            'labels' => array('name' => __('Cast','onwatch'), 'singular_name' => __('Cast','onwatch'), 'menu_name' => __('Cast','onwatch')),
            'rewrite' => array('slug' => TR_GRABBER_PREFIX_CASTTV, 'with_front' => true, 'hierarchical' => true),
        ));
}
endif;

add_action('init', 'onwatch_register_taxonomies', 0);

add_action('init', 'onwatch_add_category_to_movies_series');
function onwatch_add_category_to_movies_series() {
    register_taxonomy_for_object_type('category', 'movies');
    register_taxonomy_for_object_type('category', 'series');
}