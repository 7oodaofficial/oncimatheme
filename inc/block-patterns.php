<?php

if (!get_theme_mod('onwatch_disable_emojis', false)) {
    return;
}

add_action('init', 'onwatch_register_block_patterns');

function onwatch_register_block_patterns() {
    register_block_pattern_category('onwatch', [
        'label' => __('ONWatch', 'onwatch'),
    ]);

    register_block_pattern('onwatch/hero-search', [
        'title'       => __('Hero Banner with Search', 'onwatch'),
        'description' => __('Large hero section with background overlay and search field.', 'onwatch'),
        'categories'  => ['onwatch'],
        'content'     => '<!-- wp:cover {"url":"' . esc_url(ONWATCH_DIR_URI . 'resources/assets/img/placeholder.svg') . '","hasParallax":true,"dimRatio":60,"minHeight":400,"align":"full"} -->
<div class="wp-block-cover alignfull has-parallax" style="background-image:url(' . esc_url(ONWATCH_DIR_URI . 'resources/assets/img/placeholder.svg') . ');min-height:400px"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-60 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:group {"layout":{"type":"constrained","contentSize":"800px"}} --><div class="wp-block-group"><!-- wp:heading {"textAlign":"center","level":1,"textColor":"white"} -->
<h1 class="wp-block-heading has-text-align-center has-white-color has-text-color">' . __('Discover Movies & Series', 'onwatch') . '</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","textColor":"white","fontSize":"medium"} -->
<p class="has-text-align-center has-white-color has-text-color has-medium-font-size">' . __('Watch the latest movies and TV series online in HD quality.', 'onwatch') . '</p>
<!-- /wp:paragraph -->

<!-- wp:search {"label":"","placeholder":"' . __('Search movies, series...', 'onwatch') . '","width":100,"buttonText":"' . __('Search', 'onwatch') . '","buttonUseIcon":true,"align":"center"} /--></div></div></div>
<!-- /wp:cover -->',
    ]);

    register_block_pattern('onwatch/featured-grid', [
        'title'       => __('Featured Content Grid', 'onwatch'),
        'description' => __('Grid of featured content items with titles and descriptions.', 'onwatch'),
        'categories'  => ['onwatch'],
        'content'     => '<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"60px","bottom":"60px"}}},"backgroundColor":"background"} -->
<div class="wp-block-group alignfull has-background-background-color has-background" style="padding-top:60px;padding-bottom:60px"><!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center">' . __('Featured Content', 'onwatch') . '</h2>
<!-- /wp:heading -->

<!-- wp:columns {"align":"wide"} -->
<div class="wp-block-columns alignwide"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:cover {"minHeight":250} -->
<div class="wp-block-cover" style="min-height:250px"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-40 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:heading {"level":3,"textColor":"white"} -->
<h3 class="wp-block-heading has-white-color has-text-color">' . __('Title One', 'onwatch') . '</h3>
<!-- /wp:heading --></div></div>
<!-- /wp:cover --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:cover {"minHeight":250} -->
<div class="wp-block-cover" style="min-height:250px"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-40 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:heading {"level":3,"textColor":"white"} -->
<h3 class="wp-block-heading has-white-color has-text-color">' . __('Title Two', 'onwatch') . '</h3>
<!-- /wp:heading --></div></div>
<!-- /wp:cover --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:cover {"minHeight":250} -->
<div class="wp-block-cover" style="min-height:250px"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-40 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:heading {"level":3,"textColor":"white"} -->
<h3 class="wp-block-heading has-white-color has-text-color">' . __('Title Three', 'onwatch') . '</h3>
<!-- /wp:heading --></div></div>
<!-- /wp:cover --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->',
    ]);

    register_block_pattern('onwatch/cta-gradient', [
        'title'       => __('Call to Action with Gradient', 'onwatch'),
        'description' => __('Call to action section with gradient background and two buttons.', 'onwatch'),
        'categories'  => ['onwatch'],
        'content'     => '<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"80px","bottom":"80px"}}},"gradient":"primary-to-surface"} -->
<div class="wp-block-group alignfull has-primary-to-surface-gradient-background has-background" style="padding-top:80px;padding-bottom:80px"><!-- wp:heading {"textAlign":"center","textColor":"white"} -->
<h2 class="wp-block-heading has-text-align-center has-white-color has-text-color">' . __('Start Watching Now', 'onwatch') . '</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","textColor":"white","fontSize":"large"} -->
<p class="has-text-align-center has-white-color has-text-color has-large-font-size">' . __('Thousands of movies and series at your fingertips.', 'onwatch') . '</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button {"backgroundColor":"white","textColor":"primary"} -->
<div class="wp-block-button"><a class="wp-block-button__link has-primary-color has-white-background-color has-text-color has-background wp-element-button">' . __('Browse Movies', 'onwatch') . '</a></div>
<!-- /wp:button -->

<!-- wp:button {"style":{"color":{"text":"#ffffff"}},"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link has-text-color wp-element-button" style="color:#ffffff">' . __('View Series', 'onwatch') . '</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->',
    ]);

    register_block_pattern('onwatch/stats-counter', [
        'title'       => __('Stats Counter Strip', 'onwatch'),
        'description' => __('Row of statistics counters for showcasing content numbers.', 'onwatch'),
        'categories'  => ['onwatch'],
        'content'     => '<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"50px","bottom":"50px"}}},"backgroundColor":"surface"} -->
<div class="wp-block-group alignfull has-surface-background-color has-background" style="padding-top:50px;padding-bottom:50px"><!-- wp:columns {"align":"wide"} -->
<div class="wp-block-columns alignwide"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"textAlign":"center","level":1,"textColor":"primary"} -->
<h1 class="wp-block-heading has-text-align-center has-primary-color has-text-color">5000+</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","textColor":"text-secondary"} -->
<p class="has-text-align-center has-text-secondary-color has-text-color">' . __('Movies', 'onwatch') . '</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"textAlign":"center","level":1,"textColor":"primary"} -->
<h1 class="wp-block-heading has-text-align-center has-primary-color has-text-color">2000+</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","textColor":"text-secondary"} -->
<p class="has-text-align-center has-text-secondary-color has-text-color">' . __('Series', 'onwatch') . '</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"textAlign":"center","level":1,"textColor":"primary"} -->
<h1 class="wp-block-heading has-text-align-center has-primary-color has-text-color">50+</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","textColor":"text-secondary"} -->
<p class="has-text-align-center has-text-secondary-color has-text-color">' . __('Genres', 'onwatch') . '</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"textAlign":"center","level":1,"textColor":"primary"} -->
<h1 class="wp-block-heading has-text-align-center has-primary-color has-text-color">100K+</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","textColor":"text-secondary"} -->
<p class="has-text-align-center has-text-secondary-color has-text-color">' . __('Users', 'onwatch') . '</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->',
    ]);

    register_block_pattern('onwatch/newsletter', [
        'title'       => __('Newsletter / Subscribe', 'onwatch'),
        'description' => __('Newsletter subscription section with email input.', 'onwatch'),
        'categories'  => ['onwatch'],
        'content'     => '<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"60px","bottom":"60px"}}},"backgroundColor":"card-bg"} -->
<div class="wp-block-group alignfull has-card-bg-background-color has-background" style="padding-top:60px;padding-bottom:60px"><!-- wp:group {"layout":{"type":"constrained","contentSize":"600px"}} -->
<div class="wp-block-group"><!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center">' . __('Stay Updated', 'onwatch') . '</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","textColor":"text-secondary"} -->
<p class="has-text-align-center has-text-secondary-color has-text-color">' . __('Subscribe to get notified about new releases and updates.', 'onwatch') . '</p>
<!-- /wp:paragraph -->

<!-- wp:search {"label":"","placeholder":"' . __('Your email address', 'onwatch') . '","buttonText":"' . __('Subscribe', 'onwatch') . '","align":"center"} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->',
    ]);

    register_block_pattern('onwatch/movie-showcase', [
        'title'       => __('Movie/Series Showcase', 'onwatch'),
        'description' => __('Two-column layout with poster on one side and details on the other.', 'onwatch'),
        'categories'  => ['onwatch'],
        'content'     => '<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"60px","bottom":"60px"}}},"backgroundColor":"background"} -->
<div class="wp-block-group alignfull has-background-background-color has-background" style="padding-top:60px;padding-bottom:60px"><!-- wp:columns {"align":"wide"} -->
<div class="wp-block-columns alignwide"><!-- wp:column {"width":"33%"} -->
<div class="wp-block-column" style="flex-basis:33%"><!-- wp:cover {"minHeight":400} -->
<div class="wp-block-cover" style="min-height:400px"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-30 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:paragraph {"placeholder":"' . __('Poster image background', 'onwatch') . '","align":"center","textColor":"white"} -->
<p class="has-text-align-center has-white-color has-text-color">' . __('Poster', 'onwatch') . '</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:cover --></div>
<!-- /wp:column -->

<!-- wp:column {"width":"66%"} -->
<div class="wp-block-column" style="flex-basis:66%"><!-- wp:heading -->
<h2 class="wp-block-heading">' . __('Content Title', 'onwatch') . '</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"text-secondary"} -->
<p class="has-text-secondary-color has-text-color">2024 · 2h 15m · Action, Drama</p>
<!-- /wp:paragraph -->

<!-- wp:separator {"className":"is-style-wide"} -->
<hr class="wp-block-separator has-alpha-channel-opacity is-style-wide"/>
<!-- /wp:separator -->

<!-- wp:paragraph -->
<p>' . __('Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.', 'onwatch') . '</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"backgroundColor":"primary"} -->
<div class="wp-block-button"><a class="wp-block-button__link has-primary-background-color has-background wp-element-button">' . __('Watch Now', 'onwatch') . '</a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button">' . __('More Info', 'onwatch') . '</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->',
    ]);
}

add_action('after_setup_theme', 'onwatch_register_block_colors');

function onwatch_register_block_colors() {
    add_theme_support('editor-color-palette', [
        ['name' => __('Background', 'onwatch'), 'slug' => 'background', 'color' => '#1a1a2e'],
        ['name' => __('Card BG', 'onwatch'), 'slug' => 'card-bg', 'color' => '#16213e'],
        ['name' => __('Surface', 'onwatch'), 'slug' => 'surface', 'color' => '#0f3460'],
        ['name' => __('Primary', 'onwatch'), 'slug' => 'primary', 'color' => '#bf5bf3'],
        ['name' => __('Text Primary', 'onwatch'), 'slug' => 'text-primary', 'color' => '#ffffff'],
        ['name' => __('Text Secondary', 'onwatch'), 'slug' => 'text-secondary', 'color' => '#a0a0b0'],
        ['name' => __('Footer BG', 'onwatch'), 'slug' => 'footer-bg', 'color' => '#0d1117'],
    ]);

    add_theme_support('editor-gradient-presets', [
        ['name' => __('Primary to Surface', 'onwatch'), 'slug' => 'primary-to-surface', 'gradient' => 'linear-gradient(135deg, #bf5bf3 0%, #0f3460 100%)'],
        ['name' => __('Dark to Surface', 'onwatch'), 'slug' => 'dark-to-surface', 'gradient' => 'linear-gradient(135deg, #1a1a2e 0%, #0f3460 100%)'],
        ['name' => __('Primary to Dark', 'onwatch'), 'slug' => 'primary-to-dark', 'gradient' => 'linear-gradient(135deg, #bf5bf3 0%, #1a1a2e 100%)'],
    ]);
}

add_action('wp_enqueue_scripts', 'onwatch_editor_styles_frontend');
function onwatch_editor_styles_frontend() {
    if (has_block('core/cover') || has_block('core/columns') || has_block('core/group') || has_block('core/buttons')) {
        wp_enqueue_style('wp-block-library');
        wp_enqueue_style('wp-block-library-theme');
    }
}
