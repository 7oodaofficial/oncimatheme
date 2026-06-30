<?php

add_action('wp_enqueue_scripts', 'onwatch_enqueue_styles');
add_action('wp_enqueue_scripts', 'onwatch_enqueue_scripts');
add_action('admin_enqueue_scripts', 'onwatch_admin_enqueue');

function onwatch_enqueue_styles() {
    $css_path = get_template_directory() . '/resources/assets/css/onwatch.css';
    $css_ver  = file_exists($css_path) ? filemtime($css_path) : ONWATCH_VERSION;

    wp_enqueue_style(
        'onwatch',
        get_template_directory_uri() . '/resources/assets/css/onwatch.css',
        [],
        $css_ver
    );

    $accent     = esc_attr(get_theme_mod('onwatch_accent_color', '#bf5bf3'));
    $secondary  = esc_attr(get_theme_mod('onwatch_secondary_color', '#8a2be2'));
    $text_pri   = esc_attr(get_theme_mod('onwatch_text_color', '#ffffff'));
    $text_sec   = esc_attr(get_theme_mod('onwatch_text_secondary_color', '#a0a0b0'));
    $text_muted = esc_attr(get_theme_mod('onwatch_text_muted_color', '#6b6b7b'));
    $bg_color   = esc_attr(get_theme_mod('onwatch_bg_color', '#121212'));
    $bg_deep    = esc_attr(get_theme_mod('onwatch_bg_deep_color', '#0a0a0a'));
    $card_bg    = esc_attr(get_theme_mod('onwatch_card_bg_color', '#1e1e1e'));
    $card_hover = esc_attr(get_theme_mod('onwatch_card_hover_color', '#252535'));
    $surface    = esc_attr(get_theme_mod('onwatch_surface_color', '#0a0a0a'));
    $footer_bg  = esc_attr(get_theme_mod('onwatch_footer_bg_color', '#0d1117'));
    $btn_bg     = esc_attr(get_theme_mod('onwatch_btn_primary_bg', '#bf5bf3'));
    $btn_hover  = esc_attr(get_theme_mod('onwatch_btn_primary_hover', '#a84adf'));
    $btn_text   = esc_attr(get_theme_mod('onwatch_btn_text_color', '#ffffff'));
    $container  = absint(get_theme_mod('onwatch_container_width', 1280));
    $font_fam   = esc_attr(get_theme_mod('onwatch_font_family', 'Cairo'));
    $hero_overlay = absint(get_theme_mod('onwatch_hero_overlay', 60)) / 100;
    $body_weight  = absint(get_theme_mod('onwatch_body_weight', 400));
    $heading_weight = absint(get_theme_mod('onwatch_heading_weight', 700));
    $base_font   = absint(get_theme_mod('onwatch_base_font_size', 15));
    $section_title_size = absint(get_theme_mod('onwatch_section_title_size', 20));
    $letter_spacing = floatval(get_theme_mod('onwatch_title_letter_spacing', 0));
    $card_radius = absint(get_theme_mod('onwatch_card_radius', 12));

    $custom_css = ":root{
--primary:{$accent};
--secondary:{$secondary};
--text:{$text_pri};
--text-secondary:{$text_sec};
--text-muted:{$text_muted};
--bg:{$bg_color};
--bg-deep:{$bg_deep};
--card-bg:{$card_bg};
--card-hover:{$card_hover};
--surface:{$surface};
--footer-bg:{$footer_bg};
--btn-bg:{$btn_bg};
--btn-hover:{$btn_hover};
--btn-text:{$btn_text};
--container:{$container}px;
--font:'{$font_fam}','Inter',sans-serif;
--body-weight:{$body_weight};
--heading-weight:{$heading_weight};
--base-font:{$base_font}px;
--section-title-size:{$section_title_size}px;
--letter-spacing:{$letter_spacing}px;
--radius-md:{$card_radius}px;
--hero-overlay:{$hero_overlay};
}";

    wp_add_inline_style('onwatch', $custom_css);
}

function onwatch_enqueue_scripts() {
    $js_path = get_template_directory() . '/resources/assets/js/onwatch.js';
    $js_ver  = file_exists($js_path) ? filemtime($js_path) : ONWATCH_VERSION;

    wp_enqueue_script(
        'onwatch',
        get_template_directory_uri() . '/resources/assets/js/onwatch.js',
        [],
        $js_ver,
        true
    );

    wp_enqueue_script(
        'alpinejs',
        'https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js',
        ['onwatch'],
        '3.14.8',
        true
    );

    wp_localize_script('onwatch', 'onwatchVars', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('onwatch-nonce'),
        'url'     => get_template_directory_uri(),
        'strings' => [
            'loadMore'     => __('تحميل المزيد', 'onwatch'),
            'noResults'    => __('لا توجد نتائج', 'onwatch'),
            'addedFav'     => __('تمت الإضافة إلى المفضلة', 'onwatch'),
            'removedFav'   => __('تمت الإزالة من المفضلة', 'onwatch'),
            'copied'       => __('تم نسخ الرابط', 'onwatch'),
            'searchTitle'  => __('بحث', 'onwatch'),
        ]
    ]);
}

function onwatch_admin_enqueue($hook) {
    if ($hook === 'tools_page_onwatch-reports' || $hook === 'toplevel_page_onwatch-settings' || $hook === 'settings_page_onwatch-theme-settings') {
        wp_enqueue_style('onwatch-admin', get_template_directory_uri() . '/resources/assets/css/admin.css', [], ONWATCH_VERSION);
    }
    if ($hook === 'settings_page_onwatch-theme-settings') {
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
    }
}
