<?php

add_action('customize_register', 'onwatch_customize_register');

function onwatch_customize_register($wp_customize) {
    $wp_customize->add_panel('onwatch_panel', [
        'title'    => 'ONWatch Theme',
        'priority' => 30,
    ]);

    onwatch_customize_section_site_identity($wp_customize);
    onwatch_customize_section_layout($wp_customize);
    onwatch_customize_section_colors($wp_customize);
    onwatch_customize_section_typography($wp_customize);
    onwatch_customize_section_homepage($wp_customize);
    onwatch_customize_section_single($wp_customize);
    onwatch_customize_section_archive($wp_customize);
    onwatch_customize_section_player($wp_customize);
    onwatch_customize_section_auth($wp_customize);
    onwatch_customize_section_seo($wp_customize);
    onwatch_customize_section_performance($wp_customize);
    onwatch_customize_section_footer($wp_customize);
    onwatch_customize_section_hide($wp_customize);
}

function onwatch_add_setting($wp_customize, $key, $default, $sanitize = 'sanitize_text_field', $args = []) {
    $wp_customize->add_setting($key, array_merge([
        'default' => $default,
        'sanitize_callback' => $sanitize,
    ], $args));
}

function onwatch_add_control($wp_customize, $key, $label, $section, $type = 'text', $input_attrs = []) {
    $wp_customize->add_control($key, [
        'label'       => $label,
        'section'     => $section,
        'type'        => $type,
        'input_attrs' => $input_attrs,
    ]);
}

function onwatch_section_site_identity($wp_customize) {
    $s = 'onwatch_site_identity';
    $wp_customize->add_section($s, [
        'title'    => __('Site Identity', 'onwatch'),
        'panel'    => 'onwatch_panel',
        'priority' => 1,
    ]);

    onwatch_add_setting($wp_customize, 'onwatch_logo', 0, 'absint');
    $wp_customize->add_control(new WP_Customize_Media_Control($wp_customize, 'onwatch_logo', [
        'label'    => __('Logo', 'onwatch'),
        'section'  => $s,
        'settings' => 'onwatch_logo',
        'mime_type' => 'image',
    ]));

    onwatch_add_setting($wp_customize, 'onwatch_logo_width', 160, 'absint');
    onwatch_add_control($wp_customize, 'onwatch_logo_width', __('Logo Width (px)', 'onwatch'), $s, 'range', ['min' => 60, 'max' => 300, 'step' => 5]);

    onwatch_add_setting($wp_customize, 'onwatch_favicon', 0, 'absint');
    $wp_customize->add_control(new WP_Customize_Media_Control($wp_customize, 'onwatch_favicon', [
        'label'    => __('Favicon', 'onwatch'),
        'section'  => $s,
        'settings' => 'onwatch_favicon',
        'mime_type' => 'image',
    ]));
}

function onwatch_customize_section_site_identity($wp_customize) {
    onwatch_section_site_identity($wp_customize);
}

function onwatch_customize_section_layout($wp_customize) {
    $s = 'onwatch_layout';
    $wp_customize->add_section($s, [
        'title'    => __('Layout & Design', 'onwatch'),
        'panel'    => 'onwatch_panel',
        'priority' => 2,
    ]);

    onwatch_add_setting($wp_customize, 'onwatch_layout_style', 'fullwidth', 'sanitize_text_field');
    $wp_customize->add_control('onwatch_layout_style', [
        'label'   => __('Layout Style', 'onwatch'),
        'section' => $s,
        'type'    => 'select',
        'choices' => [
            'fullwidth' => __('Full Width', 'onwatch'),
            'boxed'     => __('Boxed', 'onwatch'),
        ],
    ]);

    onwatch_add_setting($wp_customize, 'onwatch_container_width', 1280, 'absint');
    onwatch_add_control($wp_customize, 'onwatch_container_width', __('Container Width (px)', 'onwatch'), $s, 'range', ['min' => 960, 'max' => 1400, 'step' => 10]);

    onwatch_add_setting($wp_customize, 'onwatch_header_style', 'sticky', 'sanitize_text_field');
    $wp_customize->add_control('onwatch_header_style', [
        'label'   => __('Header Style', 'onwatch'),
        'section' => $s,
        'type'    => 'select',
        'choices' => [
            'sticky' => __('Sticky', 'onwatch'),
            'static' => __('Static', 'onwatch'),
            'fixed'  => __('Fixed', 'onwatch'),
        ],
    ]);

    onwatch_add_setting($wp_customize, 'onwatch_card_style', 'default', 'sanitize_text_field');
    $wp_customize->add_control('onwatch_card_style', [
        'label'   => __('Card Style', 'onwatch'),
        'section' => $s,
        'type'    => 'select',
        'choices' => [
            'default' => __('Default (with overlay)', 'onwatch'),
            'compact' => __('Compact (smaller)', 'onwatch'),
            'minimal' => __('Minimal (no overlay)', 'onwatch'),
        ],
    ]);

    onwatch_add_setting($wp_customize, 'onwatch_card_radius', 8, 'absint');
    onwatch_add_control($wp_customize, 'onwatch_card_radius', __('Card Border Radius (px)', 'onwatch'), $s, 'range', ['min' => 0, 'max' => 24, 'step' => 1]);

    onwatch_add_setting($wp_customize, 'onwatch_hero_style', 'slider', 'sanitize_text_field');
    $wp_customize->add_control('onwatch_hero_style', [
        'label'   => __('Hero Section Style', 'onwatch'),
        'section' => $s,
        'type'    => 'select',
        'choices' => [
            'slider' => __('Slider', 'onwatch'),
            'grid'   => __('Featured Grid', 'onwatch'),
            'banner' => __('Single Banner', 'onwatch'),
        ],
    ]);

    onwatch_add_setting($wp_customize, 'onwatch_hero_overlay', 60, 'absint');
    onwatch_add_control($wp_customize, 'onwatch_hero_overlay', __('Hero Overlay Opacity (%)', 'onwatch'), $s, 'range', ['min' => 0, 'max' => 100, 'step' => 5]);
}

function onwatch_customize_section_hide($wp_customize) {
    $s = 'onwatch_hide';
    $wp_customize->add_section($s, [
        'title'    => __('Anti-Bot Page', 'onwatch'),
        'panel'    => 'onwatch_panel',
        'priority' => 13,
    ]);

    onwatch_add_setting($wp_customize, 'onwatch_hide_title', 'ONWatch Player', 'sanitize_text_field');
    onwatch_add_control($wp_customize, 'onwatch_hide_title', __('Page Title', 'onwatch'), $s);

    onwatch_add_setting($wp_customize, 'onwatch_hide_msg', 'Checking that you are not a bot', 'sanitize_text_field');
    onwatch_add_control($wp_customize, 'onwatch_hide_msg', __('Message', 'onwatch'), $s);

    onwatch_add_setting($wp_customize, 'onwatch_hide_color', '#bf5bf3', 'sanitize_hex_color');
    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'onwatch_hide_color', [
        'label'   => __('Accent Color', 'onwatch'),
        'section' => $s,
    ]));

    onwatch_add_setting($wp_customize, 'onwatch_hide_img', '', 'esc_url_raw');
    onwatch_add_control($wp_customize, 'onwatch_hide_img', __('Background Image URL', 'onwatch'), $s);
}

function onwatch_customize_section_colors($wp_customize) {
    $s = 'onwatch_colors';
    $wp_customize->add_section($s, [
        'title'    => __('Colors', 'onwatch'),
        'panel'    => 'onwatch_panel',
        'priority' => 3,
    ]);

    $colors = [
        'onwatch_accent_color'         => ['#bf5bf3', __('Accent Color', 'onwatch')],
        'onwatch_secondary_color'      => ['#8a2be2', __('Secondary Color', 'onwatch')],
        'onwatch_bg_color'             => ['#121212', __('Background Color', 'onwatch')],
        'onwatch_bg_deep_color'        => ['#0a0a0a', __('Deep Background', 'onwatch')],
        'onwatch_card_bg_color'        => ['#1e1e1e', __('Card Background', 'onwatch')],
        'onwatch_card_hover_color'     => ['#252535', __('Card Hover', 'onwatch')],
        'onwatch_surface_color'        => ['#0a0a0a', __('Header/Surface Color', 'onwatch')],
        'onwatch_text_color'           => ['#ffffff', __('Text Primary', 'onwatch')],
        'onwatch_text_secondary_color' => ['#a0a0b0', __('Text Secondary', 'onwatch')],
        'onwatch_text_muted_color'     => ['#6b6b7b', __('Text Muted', 'onwatch')],
        'onwatch_btn_primary_bg'       => ['#bf5bf3', __('Button Primary BG', 'onwatch')],
        'onwatch_btn_primary_hover'    => ['#a84adf', __('Button Primary Hover', 'onwatch')],
        'onwatch_btn_text_color'       => ['#ffffff', __('Button Text Color', 'onwatch')],
        'onwatch_footer_bg_color'      => ['#0d1117', __('Footer Background', 'onwatch')],
    ];

    foreach ($colors as $key => $data) {
        onwatch_add_setting($wp_customize, $key, $data[0], 'sanitize_hex_color');
        $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, $key, [
            'label'   => $data[1],
            'section' => $s,
        ]));
    }
}

function onwatch_customize_section_typography($wp_customize) {
    $s = 'onwatch_typography';
    $wp_customize->add_section($s, [
        'title'    => __('Typography', 'onwatch'),
        'panel'    => 'onwatch_panel',
        'priority' => 4,
    ]);

    onwatch_add_setting($wp_customize, 'onwatch_font_family', 'Cairo', 'sanitize_text_field');
    $wp_customize->add_control('onwatch_font_family', [
        'label'   => __('Font Family', 'onwatch'),
        'section' => $s,
        'type'    => 'select',
        'choices' => [
            'Cairo'     => 'Cairo',
            'Almarai'   => 'Almarai',
            'Tajawal'   => 'Tajawal',
            'Noto Sans Arabic' => 'Noto Sans Arabic',
            'Readex Pro' => 'Readex Pro',
        ],
    ]);

    onwatch_add_setting($wp_customize, 'onwatch_base_font_size', 16, 'absint');
    onwatch_add_control($wp_customize, 'onwatch_base_font_size', __('Base Font Size (px)', 'onwatch'), $s, 'range', ['min' => 12, 'max' => 22, 'step' => 1]);

    onwatch_add_setting($wp_customize, 'onwatch_section_title_size', 22, 'absint');
    onwatch_add_control($wp_customize, 'onwatch_section_title_size', __('Section Title Size (px)', 'onwatch'), $s, 'range', ['min' => 16, 'max' => 36, 'step' => 1]);

    onwatch_add_setting($wp_customize, 'onwatch_heading_weight', 700, 'absint');
    $wp_customize->add_control('onwatch_heading_weight', [
        'label'   => __('Heading Font Weight', 'onwatch'),
        'section' => $s,
        'type'    => 'select',
        'choices' => [
            400 => 'Normal (400)',
            600 => 'Semi Bold (600)',
            700 => 'Bold (700)',
            800 => 'Extra Bold (800)',
        ],
    ]);

    onwatch_add_setting($wp_customize, 'onwatch_body_weight', 400, 'absint');
    $wp_customize->add_control('onwatch_body_weight', [
        'label'   => __('Body Font Weight', 'onwatch'),
        'section' => $s,
        'type'    => 'select',
        'choices' => [
            300 => 'Light (300)',
            400 => 'Normal (400)',
            500 => 'Medium (500)',
            600 => 'Semi Bold (600)',
        ],
    ]);

    onwatch_add_setting($wp_customize, 'onwatch_title_letter_spacing', 0, 'absint');
    onwatch_add_control($wp_customize, 'onwatch_title_letter_spacing', __('Title Letter Spacing (px)', 'onwatch'), $s, 'range', ['min' => 0, 'max' => 5, 'step' => 0.5]);
}

function onwatch_customize_section_homepage($wp_customize) {
    $s = 'onwatch_homepage';
    $wp_customize->add_section($s, [
        'title'    => __('Homepage', 'onwatch'),
        'panel'    => 'onwatch_panel',
        'priority' => 5,
    ]);

    $fields = [
        'onwatch_hero_count'        => ['default' => 8, 'label' => __('Hero Slides Count', 'onwatch')],
        'onwatch_hero_speed'        => ['default' => 6000, 'label' => __('Hero Autoplay Speed (ms)', 'onwatch')],
        'onwatch_latest_movies'     => ['default' => 10, 'label' => __('Latest Movies Count', 'onwatch')],
        'onwatch_latest_series'     => ['default' => 10, 'label' => __('Latest Series Count', 'onwatch')],
        'onwatch_trending_count'    => ['default' => 6, 'label' => __('Trending Count', 'onwatch')],
        'onwatch_ep_latest_count'   => ['default' => 10, 'label' => __('Latest Episodes Count', 'onwatch')],
    ];

    foreach ($fields as $key => $f) {
        onwatch_add_setting($wp_customize, $key, $f['default'], 'absint');
        onwatch_add_control($wp_customize, $key, $f['label'], $s, 'number');
    }

    $toggles = [
        'onwatch_show_hero'      => ['default' => true, 'label' => __('Show Hero Section', 'onwatch')],
        'onwatch_show_movies_row' => ['default' => true, 'label' => __('Show Latest Movies Row', 'onwatch')],
        'onwatch_show_series_row' => ['default' => true, 'label' => __('Show Latest Series Row', 'onwatch')],
        'onwatch_show_trending'  => ['default' => true, 'label' => __('Show Trending Section', 'onwatch')],
        'onwatch_show_genre_tabs' => ['default' => true, 'label' => __('Show Genre Tabs', 'onwatch')],
        'onwatch_show_ep_latest' => ['default' => true, 'label' => __('Show Latest Episodes', 'onwatch')],
        'onwatch_show_continue'  => ['default' => true, 'label' => __('Show Continue Watching', 'onwatch')],
    ];

    foreach ($toggles as $key => $f) {
        onwatch_add_setting($wp_customize, $key, $f['default'], 'wp_validate_boolean');
        onwatch_add_control($wp_customize, $key, $f['label'], $s, 'checkbox');
    }

    onwatch_add_setting($wp_customize, 'onwatch_trending_source', 'rating', 'sanitize_text_field');
    $wp_customize->add_control('onwatch_trending_source', [
        'label'   => __('Trending Source', 'onwatch'),
        'section' => $s,
        'type'    => 'select',
        'choices' => [
            'rating' => __('By Rating', 'onwatch'),
            'views'  => __('By Views (most viewed)', 'onwatch'),
            'date'   => __('By Date (newest)', 'onwatch'),
            'random' => __('Random', 'onwatch'),
        ],
    ]);

    onwatch_add_setting($wp_customize, 'onwatch_cache_ttl', 3600, 'absint');
    onwatch_add_control($wp_customize, 'onwatch_cache_ttl', __('Cache TTL (seconds)', 'onwatch'), $s, 'number', ['min' => 60, 'max' => 86400, 'step' => 60]);

    onwatch_add_setting($wp_customize, 'onwatch_show_section_titles', true, 'wp_validate_boolean');
    onwatch_add_control($wp_customize, 'onwatch_show_section_titles', __('Show Section Titles', 'onwatch'), $s, 'checkbox');

    onwatch_add_setting($wp_customize, 'onwatch_section_order', "hero\nmovies\nseries\ntrending\ngenres\nepisodes\ncontinue", 'sanitize_textarea_field');
    $wp_customize->add_control('onwatch_section_order', [
        'label'   => __('Section Order', 'onwatch'),
        'section' => $s,
        'type'    => 'textarea',
        'description' => __('One per line: hero, movies, series, trending, genres, episodes, continue', 'onwatch'),
    ]);
}

function onwatch_customize_section_single($wp_customize) {
    $s = 'onwatch_single';
    $wp_customize->add_section($s, [
        'title'    => __('Single Pages', 'onwatch'),
        'panel'    => 'onwatch_panel',
        'priority' => 6,
    ]);

    $toggles = [
        'onwatch_show_related'      => ['default' => true, 'label' => __('Show Related Items', 'onwatch')],
        'onwatch_show_cast'         => ['default' => true, 'label' => __('Show Cast Section', 'onwatch')],
        'onwatch_show_comments'     => ['default' => true, 'label' => __('Show Comments', 'onwatch')],
        'onwatch_show_share_btns'   => ['default' => true, 'label' => __('Show Share Buttons', 'onwatch')],
        'onwatch_show_rating_stars' => ['default' => true, 'label' => __('Show Rating Stars', 'onwatch')],
        'onwatch_show_download_btn' => ['default' => true, 'label' => __('Show Download Button', 'onwatch')],
        'onwatch_show_report_btn'   => ['default' => true, 'label' => __('Show Report Button', 'onwatch')],
        'onwatch_show_poster_actions' => ['default' => true, 'label' => __('Show Poster Actions (fav/share)', 'onwatch')],
        'onwatch_show_trailer_btn'  => ['default' => true, 'label' => __('Show Trailer Button', 'onwatch')],
        'onwatch_show_meta_info'    => ['default' => true, 'label' => __('Show Meta Info (year/runtime/genre)', 'onwatch')],
        'onwatch_show_player_section' => ['default' => true, 'label' => __('Show Player Section', 'onwatch')],
        'onwatch_show_report_btn'   => ['default' => true, 'label' => __('Show Report Button', 'onwatch')],
    ];

    foreach ($toggles as $key => $f) {
        onwatch_add_setting($wp_customize, $key, $f['default'], 'wp_validate_boolean');
        onwatch_add_control($wp_customize, $key, $f['label'], $s, 'checkbox');
    }

    onwatch_add_setting($wp_customize, 'onwatch_related_count', 6, 'absint');
    onwatch_add_control($wp_customize, 'onwatch_related_count', __('Related Items Count', 'onwatch'), $s, 'number', ['min' => 2, 'max' => 20, 'step' => 1]);

    onwatch_add_setting($wp_customize, 'onwatch_single_layout', 'full', 'sanitize_text_field');
    $wp_customize->add_control('onwatch_single_layout', [
        'label'   => __('Single Page Layout', 'onwatch'),
        'section' => $s,
        'type'    => 'select',
        'choices' => [
            'full'    => __('Full Width', 'onwatch'),
            'sidebar' => __('With Sidebar', 'onwatch'),
        ],
    ]);
}

function onwatch_customize_section_archive($wp_customize) {
    $s = 'onwatch_archive';
    $wp_customize->add_section($s, [
        'title'    => __('Archive Pages', 'onwatch'),
        'panel'    => 'onwatch_panel',
        'priority' => 7,
    ]);

    $fields = [
        'onwatch_per_page_movies'   => ['default' => 24, 'label' => __('Movies per page', 'onwatch')],
        'onwatch_per_page_series'   => ['default' => 24, 'label' => __('Series per page', 'onwatch')],
        'onwatch_per_page_episodes' => ['default' => 24, 'label' => __('Episodes per page', 'onwatch')],
    ];

    foreach ($fields as $key => $f) {
        onwatch_add_setting($wp_customize, $key, $f['default'], 'absint');
        onwatch_add_control($wp_customize, $key, $f['label'], $s, 'number');
    }

    onwatch_add_setting($wp_customize, 'onwatch_archive_layout', 'grid', 'sanitize_text_field');
    $wp_customize->add_control('onwatch_archive_layout', [
        'label'   => __('Archive Layout', 'onwatch'),
        'section' => $s,
        'type'    => 'select',
        'choices' => [
            'grid' => __('Grid', 'onwatch'),
            'list' => __('List', 'onwatch'),
        ],
    ]);

    onwatch_add_setting($wp_customize, 'onwatch_archive_columns', 4, 'absint');
    $wp_customize->add_control('onwatch_archive_columns', [
        'label'   => __('Grid Columns', 'onwatch'),
        'section' => $s,
        'type'    => 'select',
        'choices' => [2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6],
    ]);

    onwatch_add_setting($wp_customize, 'onwatch_archive_order', 'date', 'sanitize_text_field');
    $wp_customize->add_control('onwatch_archive_order', [
        'label'   => __('Default Sort Order', 'onwatch'),
        'section' => $s,
        'type'    => 'select',
        'choices' => [
            'date'  => __('Date (newest)', 'onwatch'),
            'title' => __('Title (A-Z)', 'onwatch'),
            'rating' => __('Rating (highest)', 'onwatch'),
        ],
    ]);

    $archive_toggles = [
        'onwatch_show_filters'     => ['default' => true, 'label' => __('Show Filter Bar', 'onwatch')],
        'onwatch_show_pagination'  => ['default' => true, 'label' => __('Show Pagination', 'onwatch')],
        'onwatch_infinite_scroll'  => ['default' => false, 'label' => __('Enable Infinite Scroll (replaces pagination)', 'onwatch')],
    ];

    foreach ($archive_toggles as $key => $f) {
        onwatch_add_setting($wp_customize, $key, $f['default'], 'wp_validate_boolean');
        onwatch_add_control($wp_customize, $key, $f['label'], $s, 'checkbox');
    }
}

function onwatch_customize_section_player($wp_customize) {
    $s = 'onwatch_player';
    $wp_customize->add_section($s, [
        'title'    => __('Player', 'onwatch'),
        'panel'    => 'onwatch_panel',
        'priority' => 8,
    ]);

    onwatch_add_setting($wp_customize, 'onwatch_autoplay_next', true, 'wp_validate_boolean');
    onwatch_add_control($wp_customize, 'onwatch_autoplay_next', __('Autoplay Next Episode (series)', 'onwatch'), $s, 'checkbox');

    onwatch_add_setting($wp_customize, 'onwatch_default_player_tab', 'embed', 'sanitize_text_field');
    $wp_customize->add_control('onwatch_default_player_tab', [
        'label'   => __('Default Player Tab', 'onwatch'),
        'section' => $s,
        'type'    => 'select',
        'choices' => [
            'embed'    => __('Embed (Watch)', 'onwatch'),
            'download' => __('Download', 'onwatch'),
        ],
    ]);
}

function onwatch_customize_section_auth($wp_customize) {
    $s = 'onwatch_auth';
    $wp_customize->add_section($s, [
        'title'    => __('Auth', 'onwatch'),
        'panel'    => 'onwatch_panel',
        'priority' => 9,
    ]);

    onwatch_add_setting($wp_customize, 'onwatch_disable_auth', false, 'wp_validate_boolean');
    onwatch_add_control($wp_customize, 'onwatch_disable_auth', __('Disable Auth (hide login/register)', 'onwatch'), $s, 'checkbox');

    onwatch_add_setting($wp_customize, 'onwatch_open_register', false, 'wp_validate_boolean');
    onwatch_add_control($wp_customize, 'onwatch_open_register', __('Open Register Tab by Default', 'onwatch'), $s, 'checkbox');
}

function onwatch_customize_section_seo($wp_customize) {
    $s = 'onwatch_seo';
    $wp_customize->add_section($s, [
        'title'    => __('SEO & Analytics', 'onwatch'),
        'panel'    => 'onwatch_panel',
        'priority' => 10,
    ]);

    onwatch_add_setting($wp_customize, 'onwatch_home_meta_title', '', 'sanitize_text_field');
    onwatch_add_control($wp_customize, 'onwatch_home_meta_title', __('Homepage Meta Title', 'onwatch'), $s);

    onwatch_add_setting($wp_customize, 'onwatch_home_meta_desc', '', 'sanitize_textarea_field');
    $wp_customize->add_control('onwatch_home_meta_desc', [
        'label'   => __('Homepage Meta Description', 'onwatch'),
        'section' => $s,
        'type'    => 'textarea',
    ]);

    onwatch_add_setting($wp_customize, 'onwatch_enable_schema', true, 'wp_validate_boolean');
    onwatch_add_control($wp_customize, 'onwatch_enable_schema', __('Enable Schema.org Markup', 'onwatch'), $s, 'checkbox');

    onwatch_add_setting($wp_customize, 'onwatch_enable_og_meta', true, 'wp_validate_boolean');
    onwatch_add_control($wp_customize, 'onwatch_enable_og_meta', __('Enable Open Graph / Twitter Meta', 'onwatch'), $s, 'checkbox');

    onwatch_add_setting($wp_customize, 'onwatch_ga_id', '', 'sanitize_text_field');
    onwatch_add_control($wp_customize, 'onwatch_ga_id', __('Google Analytics ID (G-XXXXX)', 'onwatch'), $s);

    onwatch_add_setting($wp_customize, 'onwatch_head_scripts', '', 'wp_kses_post');
    $wp_customize->add_control('onwatch_head_scripts', [
        'label'   => __('Custom Head Scripts', 'onwatch'),
        'section' => $s,
        'type'    => 'textarea',
        'description' => __('Will be output before </head>', 'onwatch'),
    ]);

    onwatch_add_setting($wp_customize, 'onwatch_footer_scripts', '', 'wp_kses_post');
    $wp_customize->add_control('onwatch_footer_scripts', [
        'label'   => __('Custom Footer Scripts', 'onwatch'),
        'section' => $s,
        'type'    => 'textarea',
        'description' => __('Will be output before </body>', 'onwatch'),
    ]);
}

function onwatch_customize_section_performance($wp_customize) {
    $s = 'onwatch_performance';
    $wp_customize->add_section($s, [
        'title'    => __('Performance', 'onwatch'),
        'panel'    => 'onwatch_panel',
        'priority' => 11,
    ]);

    onwatch_add_setting($wp_customize, 'onwatch_lazyload', true, 'wp_validate_boolean');
    onwatch_add_control($wp_customize, 'onwatch_lazyload', __('Enable Lazy Loading for Images', 'onwatch'), $s, 'checkbox');

    onwatch_add_setting($wp_customize, 'onwatch_disable_emojis', false, 'wp_validate_boolean');
    onwatch_add_control($wp_customize, 'onwatch_disable_emojis', __('Disable WordPress Emojis', 'onwatch'), $s, 'checkbox');

    onwatch_add_setting($wp_customize, 'onwatch_disable_embeds', false, 'wp_validate_boolean');
    onwatch_add_control($wp_customize, 'onwatch_disable_embeds', __('Disable WordPress Embeds', 'onwatch'), $s, 'checkbox');

    onwatch_add_setting($wp_customize, 'onwatch_disable_wlw', false, 'wp_validate_boolean');
    onwatch_add_control($wp_customize, 'onwatch_disable_wlw', __('Disable WLW Manifest', 'onwatch'), $s, 'checkbox');

    onwatch_add_setting($wp_customize, 'onwatch_disable_rsd', false, 'wp_validate_boolean');
    onwatch_add_control($wp_customize, 'onwatch_disable_rsd', __('Disable RSD Link', 'onwatch'), $s, 'checkbox');

    onwatch_add_setting($wp_customize, 'onwatch_disable_shortlink', false, 'wp_validate_boolean');
    onwatch_add_control($wp_customize, 'onwatch_disable_shortlink', __('Disable Shortlink', 'onwatch'), $s, 'checkbox');

    onwatch_add_setting($wp_customize, 'onwatch_remove_wp_version', false, 'wp_validate_boolean');
    onwatch_add_control($wp_customize, 'onwatch_remove_wp_version', __('Remove WP Version from Head', 'onwatch'), $s, 'checkbox');
}

function onwatch_customize_section_footer($wp_customize) {
    $s = 'onwatch_footer';
    $wp_customize->add_section($s, [
        'title'    => __('Footer', 'onwatch'),
        'panel'    => 'onwatch_panel',
        'priority' => 12,
    ]);

    onwatch_add_setting($wp_customize, 'onwatch_footer_text', '', 'wp_kses_post');
    $wp_customize->add_control('onwatch_footer_text', [
        'label'   => __('About Text', 'onwatch'),
        'section' => $s,
        'type'    => 'textarea',
    ]);

    $social = [
        'onwatch_footer_facebook' => 'Facebook',
        'onwatch_footer_twitter'  => 'Twitter',
        'onwatch_footer_telegram' => 'Telegram',
        'onwatch_footer_youtube'  => 'YouTube',
        'onwatch_footer_instagram' => 'Instagram',
        'onwatch_footer_tiktok'   => 'TikTok',
    ];

    foreach ($social as $key => $label) {
        onwatch_add_setting($wp_customize, $key, '', 'esc_url_raw');
        onwatch_add_control($wp_customize, $key, $label, $s, 'url');
    }

    onwatch_add_setting($wp_customize, 'onwatch_copyright', '&copy; ONWatch جميع الحقوق محفوظة', 'wp_kses_post');
    onwatch_add_control($wp_customize, 'onwatch_copyright', __('Copyright', 'onwatch'), $s);

    onwatch_add_setting($wp_customize, 'onwatch_footer_columns', 3, 'absint');
    $wp_customize->add_control('onwatch_footer_columns', [
        'label'   => __('Footer Columns', 'onwatch'),
        'section' => $s,
        'type'    => 'select',
        'choices' => [1 => 1, 2 => 2, 3 => 3, 4 => 4],
    ]);
}
