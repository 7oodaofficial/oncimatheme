<?php

add_action('admin_menu', 'onwatch_add_admin_submenu', 20);
add_action('admin_init', 'onwatch_settings_init');

function onwatch_add_admin_submenu() {
    add_submenu_page(
        'onwatch-settings',
        __('Theme Settings', 'onwatch'),
        __('Theme Settings', 'onwatch'),
        'manage_options',
        'onwatch-theme-settings',
        'onwatch_theme_settings_page'
    );
}

function onwatch_settings_init() {
    $tabs = onwatch_get_settings_tabs();
    foreach ($tabs as $tab_id => $tab) {
        $option_group = 'onwatch_' . $tab_id;
        $section_id = 'onwatch_section_' . $tab_id;

        register_setting($option_group, $option_group, [
            'sanitize_callback' => 'onwatch_sanitize_' . $tab_id,
        ]);

        add_settings_section(
            $section_id,
            '',
            null,
            $option_group
        );

        foreach ($tab['fields'] as $field_id => $field) {
            $full_id = $option_group . '[' . $field_id . ']';
            add_settings_field(
                $full_id,
                $field['label'],
                'onwatch_render_field',
                $option_group,
                $section_id,
                [
                    'id'          => $field_id,
                    'option_group' => $option_group,
                    'type'        => $field['type'],
                    'default'     => $field['default'] ?? '',
                    'options'     => $field['options'] ?? [],
                    'description' => $field['description'] ?? '',
                    'placeholder' => $field['placeholder'] ?? '',
                ]
            );
        }
    }
}

function onwatch_get_settings_tabs() {
    return [
        'general' => [
            'title'  => __('General', 'onwatch'),
            'icon'   => 'dashicons-admin-generic',
            'fields' => [
                'tmdb_api_key' => [
                    'label'       => __('TMDB API Key', 'onwatch'),
                    'type'        => 'password',
                    'default'     => defined('TR_GRABBER_API_KEY') ? TR_GRABBER_API_KEY : '',
                    'description' => __('Enter your TheMovieDatabase API v3 key.', 'onwatch'),
                ],
                'tmdb_lang' => [
                    'label'       => __('TMDB Language', 'onwatch'),
                    'type'        => 'select',
                    'default'     => 'ar-AR',
                    'options'     => [
                        'ar-AR' => 'العربية',
                        'en-US' => 'English',
                        'fr-FR' => 'Français',
                        'de-DE' => 'Deutsch',
                        'es-ES' => 'Español',
                        'tr-TR' => 'Türkçe',
                        'ur-PK' => 'اردو',
                        'ku-TR' => 'Kurdî',
                    ],
                    'description' => __('Default language for TMDB metadata.', 'onwatch'),
                ],
                'upload_images' => [
                    'label'       => __('Upload Images Locally', 'onwatch'),
                    'type'        => 'checkbox',
                    'default'     => false,
                    'description' => __('Upload poster/backdrop images to WordPress media library instead of hotlinking.', 'onwatch'),
                ],
                'default_post_status' => [
                    'label'       => __('Default Post Status', 'onwatch'),
                    'type'        => 'select',
                    'default'     => 'publish',
                    'options'     => [
                        'publish' => __('Published', 'onwatch'),
                        'draft'   => __('Draft', 'onwatch'),
                        'pending' => __('Pending', 'onwatch'),
                    ],
                    'description' => __('Status for imported movies/series.', 'onwatch'),
                ],
                'loading_screen' => [
                    'label'       => __('Show Page Loader', 'onwatch'),
                    'type'        => 'checkbox',
                    'default'     => true,
                    'description' => __('Show a loading screen with logo while the page loads.', 'onwatch'),
                ],
                'loading_timeout' => [
                    'label'       => __('Loader Max Timeout (seconds)', 'onwatch'),
                    'type'        => 'number',
                    'default'     => 5,
                    'description' => __('Max seconds to show the loading screen before fading out.', 'onwatch'),
                ],
            ],
        ],
        'homepage' => [
            'title'  => __('Homepage', 'onwatch'),
            'icon'   => 'dashicons-admin-home',
            'fields' => [
                'hero_fullwidth' => [
                    'label'       => __('Full-width Hero', 'onwatch'),
                    'type'        => 'checkbox',
                    'default'     => true,
                    'description' => __('Make hero slider span full viewport width.', 'onwatch'),
                ],
                'hero_overlay_gradient' => [
                    'label'       => __('Hero Gradient Direction', 'onwatch'),
                    'type'        => 'select',
                    'default'     => 'left',
                    'options'     => [
                        'left'   => __('Left to Right', 'onwatch'),
                        'right'  => __('Right to Left', 'onwatch'),
                        'bottom' => __('Bottom to Top', 'onwatch'),
                        'top'    => __('Top to Bottom', 'onwatch'),
                    ],
                    'description' => __('Direction of the gradient overlay on hero slides.', 'onwatch'),
                ],
                'section_order' => [
                    'label'       => __('Homepage Section Order', 'onwatch'),
                    'type'        => 'textarea',
                    'default'     => "hero\nmovies\nseries\ntrending\ngenres\nepisodes\ncontinue",
                    'description' => __('Order of sections. One per line: hero, movies, series, trending, genres, episodes, continue', 'onwatch'),
                ],
            ],
        ],
        'single' => [
            'title'  => __('Single Pages', 'onwatch'),
            'icon'   => 'dashicons-media-document',
            'fields' => [
                'show_breadcrumb' => [
                    'label'       => __('Show Breadcrumb', 'onwatch'),
                    'type'        => 'checkbox',
                    'default'     => true,
                    'description' => __('Display breadcrumb on single movie/series pages.', 'onwatch'),
                ],
                'show_player_section' => [
                    'label'       => __('Show Player Section', 'onwatch'),
                    'type'        => 'checkbox',
                    'default'     => true,
                    'description' => __('Show embedded player/watch section on single pages.', 'onwatch'),
                ],
                'poster_click_action' => [
                    'label'       => __('Poster Click Action', 'onwatch'),
                    'type'        => 'select',
                    'default'     => 'lightbox',
                    'options'     => [
                        'lightbox' => __('Open in Lightbox', 'onwatch'),
                        'nothing'  => __('Do Nothing', 'onwatch'),
                        'link'     => __('Go to Page', 'onwatch'),
                    ],
                ],
                'related_by' => [
                    'label'       => __('Related Items By', 'onwatch'),
                    'type'        => 'select',
                    'default'     => 'category',
                    'options'     => [
                        'category' => __('Same Genre/Category', 'onwatch'),
                        'cast'     => __('Same Cast', 'onwatch'),
                        'year'     => __('Same Year', 'onwatch'),
                    ],
                    'description' => __('How to find related movies/series.', 'onwatch'),
                ],
            ],
        ],
        'player' => [
            'title'  => __('Player', 'onwatch'),
            'icon'   => 'dashicons-video-alt3',
            'fields' => [
                'player_width' => [
                    'label'       => __('Player Width (%)', 'onwatch'),
                    'type'        => 'number',
                    'default'     => 100,
                    'description' => __('Width of the player iframe in percentage.', 'onwatch'),
                ],
                'player_height' => [
                    'label'       => __('Player Height', 'onwatch'),
                    'type'        => 'text',
                    'default'     => '480px',
                    'description' => __('Height of the player area. Supports units: px, vh, %, rem, em (e.g., 480px, 100vh, 30rem).', 'onwatch'),
                ],
                'player_preload' => [
                    'label'       => __('Preload Player', 'onwatch'),
                    'type'        => 'checkbox',
                    'default'     => false,
                    'description' => __('Load the first server/player automatically on page load.', 'onwatch'),
                ],
                'enable_download' => [
                    'label'       => __('Enable Download Feature', 'onwatch'),
                    'type'        => 'checkbox',
                    'default'     => true,
                    'description' => __('Show download links/buttons for movies & episodes.', 'onwatch'),
                ],
                'enable_report' => [
                    'label'       => __('Enable Report Links', 'onwatch'),
                    'type'        => 'checkbox',
                    'default'     => true,
                    'description' => __('Allow users to report broken links.', 'onwatch'),
                ],
                'show_player_loader' => [
                    'label'       => __('Show Player Loader', 'onwatch'),
                    'type'        => 'checkbox',
                    'default'     => true,
                    'description' => __('Show animated logo loader while player iframe loads.', 'onwatch'),
                ],
                'show_player_watermark' => [
                    'label'       => __('Show Player Watermark', 'onwatch'),
                    'type'        => 'checkbox',
                    'default'     => false,
                    'description' => __('Display semi-transparent logo watermark on the player.', 'onwatch'),
                ],
                'player_watermark_position' => [
                    'label'       => __('Watermark Position', 'onwatch'),
                    'type'        => 'select',
                    'default'     => 'br',
                    'options'     => [
                        'bl' => __('Bottom Left', 'onwatch'),
                        'br' => __('Bottom Right', 'onwatch'),
                        'tl' => __('Top Left', 'onwatch'),
                        'tr' => __('Top Right', 'onwatch'),
                    ],
                    'description' => __('Corner position of the watermark logo.', 'onwatch'),
                ],
                'player_ad_code' => [
                    'label'       => __('Pre-Roll Ad Code', 'onwatch'),
                    'type'        => 'textarea',
                    'default'     => '',
                    'description' => __('HTML/iframe code for a skip-able ad shown before the video (like YouTube).', 'onwatch'),
                ],
                'player_ad_skip_time' => [
                    'label'       => __('Ad Skip Delay (seconds)', 'onwatch'),
                    'type'        => 'number',
                    'default'     => 5,
                    'description' => __('Seconds before the skip button appears on the pre-roll ad.', 'onwatch'),
                ],
                'player_watermark_size' => [
                    'label'       => __('Watermark Size (px)', 'onwatch'),
                    'type'        => 'number',
                    'default'     => 90,
                    'description' => __('Width of the watermark logo in pixels.', 'onwatch'),
                ],
                'player_loader_size' => [
                    'label'       => __('Loader Logo Size (px)', 'onwatch'),
                    'type'        => 'number',
                    'default'     => 80,
                    'description' => __('Width of the player loader logo in pixels.', 'onwatch'),
                ],
            ],
        ],
        'custom_code' => [
            'title'  => __('Custom Code', 'onwatch'),
            'icon'   => 'dashicons-editor-code',
            'fields' => [
                'custom_css' => [
                    'label'       => __('Custom CSS', 'onwatch'),
                    'type'        => 'textarea',
                    'default'     => '',
                    'description' => __('Additional CSS rules (will be output in &lt;head&gt;).', 'onwatch'),
                ],
                'custom_js_head' => [
                    'label'       => __('Custom JS (Head)', 'onwatch'),
                    'type'        => 'textarea',
                    'default'     => '',
                    'description' => __('JavaScript in &lt;head&gt; (e.g., tracking codes).', 'onwatch'),
                ],
                'custom_js_footer' => [
                    'label'       => __('Custom JS (Footer)', 'onwatch'),
                    'type'        => 'textarea',
                    'default'     => '',
                    'description' => __('JavaScript before &lt;/body&gt;.', 'onwatch'),
                ],
            ],
        ],
        'import_export' => [
            'title'  => __('Import / Export', 'onwatch'),
            'icon'   => 'dashicons-database',
            'fields' => [
                'export_settings' => [
                    'label'       => __('Export Settings', 'onwatch'),
                    'type'        => 'export',
                    'default'     => '',
                    'description' => __('Download all ONWatch theme settings as a JSON file.', 'onwatch'),
                ],
                'import_settings' => [
                    'label'       => __('Import Settings', 'onwatch'),
                    'type'        => 'import',
                    'default'     => '',
                    'description' => __('Upload a previously exported JSON file to restore settings.', 'onwatch'),
                ],
            ],
        ],
    ];
}

function onwatch_sanitize_general($input) {
    if (!is_array($input)) return [];
    $output = [];
    $output['tmdb_api_key'] = sanitize_text_field($input['tmdb_api_key'] ?? '');
    $output['tmdb_lang']    = sanitize_text_field($input['tmdb_lang'] ?? 'ar-AR');
    $output['upload_images'] = !empty($input['upload_images']);
    $output['default_post_status'] = in_array($input['default_post_status'] ?? '', ['publish', 'draft', 'pending'])
        ? $input['default_post_status'] : 'publish';
    $output['loading_screen']  = !empty($input['loading_screen']);
    $output['loading_timeout'] = absint($input['loading_timeout'] ?? 5);
    return $output;
}

function onwatch_sanitize_homepage($input) {
    if (!is_array($input)) return [];
    $output = [];
    $output['hero_fullwidth']       = !empty($input['hero_fullwidth']);
    $output['hero_overlay_gradient'] = sanitize_text_field($input['hero_overlay_gradient'] ?? 'left');
    $output['section_order']        = sanitize_textarea_field($input['section_order'] ?? "hero\nmovies\nseries\ntrending\ngenres\nepisodes\ncontinue");
    return $output;
}

function onwatch_sanitize_single($input) {
    if (!is_array($input)) return [];
    $output = [];
    $output['show_breadcrumb']     = !empty($input['show_breadcrumb']);
    $output['show_player_section'] = !empty($input['show_player_section']);
    $output['poster_click_action'] = sanitize_text_field($input['poster_click_action'] ?? 'lightbox');
    $output['related_by']          = sanitize_text_field($input['related_by'] ?? 'category');
    return $output;
}

function onwatch_sanitize_css_unit($value, $default = '480px') {
    if (!is_string($value) || trim($value) === '') return $default;
    $value = trim($value);
    if (preg_match('/^(\d+(?:\.\d+)?)\s*(px|vh|%|rem|em|vw)$/i', $value, $m)) {
        return $m[1] . strtolower($m[2]);
    }
    if (is_numeric($value)) {
        return floatval($value) . 'px';
    }
    return $default;
}

function onwatch_sanitize_player($input) {
    if (!is_array($input)) return [];
    $output = [];
    $output['player_width']            = absint($input['player_width'] ?? 100);
    $output['player_height']           = onwatch_sanitize_css_unit($input['player_height'] ?? '', '480px');
    $output['player_preload']          = !empty($input['player_preload']);
    $output['enable_download']         = !empty($input['enable_download']);
    $output['enable_report']           = !empty($input['enable_report']);
    $output['show_player_loader']      = !empty($input['show_player_loader']);
    $output['show_player_watermark']   = !empty($input['show_player_watermark']);
    $output['player_watermark_position'] = isset($input['player_watermark_position']) && in_array($input['player_watermark_position'], ['bl', 'br', 'tl', 'tr'])
        ? $input['player_watermark_position'] : 'br';
    $output['player_ad_code']           = $input['player_ad_code'] ?? '';
    $output['player_ad_skip_time']      = absint($input['player_ad_skip_time'] ?? 5);
    $output['player_watermark_size']    = absint($input['player_watermark_size'] ?? 90);
    $output['player_loader_size']       = absint($input['player_loader_size'] ?? 80);
    return $output;
}

function onwatch_sanitize_custom_code($input) {
    if (!is_array($input)) return [];
    $output = [];
    $output['custom_css']      = wp_strip_all_tags($input['custom_css'] ?? '');
    $output['custom_js_head']  = $input['custom_js_head'] ?? '';
    $output['custom_js_footer'] = $input['custom_js_footer'] ?? '';
    return $output;
}

function onwatch_render_field($args) {
    $option_group = $args['option_group'];
    $options = get_option($option_group, []);
    $value = $options[$args['id']] ?? $args['default'];
    $name = $option_group . '[' . $args['id'] . ']';
    $type = $args['type'];
    $desc = !empty($args['description']) ? '<p class="description">' . $args['description'] . '</p>' : '';

    switch ($type) {
        case 'text':
        case 'password':
        case 'number':
            echo '<input type="' . $type . '" name="' . $name . '" value="' . esc_attr($value) . '" class="regular-text" placeholder="' . esc_attr($args['placeholder'] ?? '') . '">' . $desc;
            break;
        case 'textarea':
            echo '<textarea name="' . $name . '" rows="6" class="large-text">' . esc_textarea($value) . '</textarea>' . $desc;
            break;
        case 'checkbox':
            echo '<label><input type="checkbox" name="' . $name . '" value="1" ' . checked(1, $value, false) . '> ' . __('Enable', 'onwatch') . '</label>' . $desc;
            break;
        case 'select':
            echo '<select name="' . $name . '">';
            foreach ($args['options'] as $opt_val => $opt_label) {
                echo '<option value="' . esc_attr($opt_val) . '" ' . selected($value, $opt_val, false) . '>' . esc_html($opt_label) . '</option>';
            }
            echo '</select>' . $desc;
            break;
        case 'export':
            echo '<button type="button" class="button button-secondary" id="onwatch-export-settings">' . __('Download Settings', 'onwatch') . '</button>';
            echo '<p class="description">' . $args['description'] . '</p>';
            break;
        case 'import':
            echo '<input type="file" name="onwatch_import_file" id="onwatch-import-file" accept=".json">';
            echo '<button type="button" class="button button-secondary" id="onwatch-import-settings">' . __('Import Settings', 'onwatch') . '</button>';
            echo '<p class="description">' . $args['description'] . '</p>';
            break;
    }
}

function onwatch_theme_settings_page() {
    if (!current_user_can('manage_options')) return;

    $current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
    $tabs = onwatch_get_settings_tabs();

    if (isset($_POST['onwatch_import_action']) && check_admin_referer('onwatch_import_settings')) {
        if (!empty($_FILES['onwatch_import_file']['tmp_name'])) {
            $json = file_get_contents($_FILES['onwatch_import_file']['tmp_name']);
            $data = json_decode($json, true);
            if (is_array($data)) {
                foreach ($data as $key => $values) {
                    update_option('onwatch_' . $key, $values);
                }
                echo '<div class="notice notice-success"><p>' . __('Settings imported successfully!', 'onwatch') . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . __('Invalid settings file.', 'onwatch') . '</p></div>';
            }
        }
    }
    ?>
    <div class="wrap onwatch-settings-wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <nav class="nav-tab-wrapper onwatch-settings-tabs">
            <?php foreach ($tabs as $tab_id => $tab): ?>
                <a href="?page=onwatch-theme-settings&tab=<?php echo esc_attr($tab_id); ?>"
                   class="nav-tab <?php echo $current_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons <?php echo esc_attr($tab['icon']); ?>"></span>
                    <?php echo esc_html($tab['title']); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="onwatch-settings-content">
            <?php if (isset($tabs[$current_tab])): ?>
                <form method="post" action="options.php" enctype="multipart/form-data" class="onwatch-settings-form">
                    <?php
                    settings_fields('onwatch_' . $current_tab);
                    echo '<input type="hidden" name="_wp_http_referer" value="' . esc_attr(admin_url('admin.php?page=onwatch-theme-settings&tab=' . $current_tab)) . '">';
                    do_settings_sections('onwatch_' . $current_tab);
                    if ($current_tab !== 'import_export') {
                        submit_button();
                    }
                    ?>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
    jQuery(function($) {
        $('#onwatch-export-settings').on('click', function() {
            var data = {};
            <?php foreach ($tabs as $tab_id => $tab): ?>
                data['<?php echo $tab_id; ?>'] = <?php echo json_encode(get_option('onwatch_' . $tab_id, [])); ?>;
            <?php endforeach; ?>
            var blob = new Blob([JSON.stringify(data, null, 2)], {type: 'application/json'});
            var link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'onwatch-settings-export.json';
            link.click();
            URL.revokeObjectURL(link.href);
        });

        $('#onwatch-import-settings').on('click', function() {
            var file = $('#onwatch-import-file')[0].files[0];
            if (!file) { alert('<?php _e('Please select a file first.', 'onwatch'); ?>'); return; }
            var form = $('<form method="post" enctype="multipart/form-data">')
                .append('<?php wp_nonce_field('onwatch_import_settings'); ?>')
                .append('<input type="hidden" name="onwatch_import_action" value="1">')
                .append($('#onwatch-import-file').clone());
            $('body').append(form);
            form.submit();
        });

        $('.onwatch-settings-tabs a').on('click', function(e) {
            e.preventDefault();
            window.location.href = $(this).attr('href');
        });
    });
    </script>
    <?php
}

add_action('updated_option', 'onwatch_sync_settings_to_theme_mods', 10, 3);
function onwatch_sync_settings_to_theme_mods($option, $old_value, $new_value) {
    if ($option === 'onwatch_homepage' && is_array($new_value) && isset($new_value['section_order'])) {
        set_theme_mod('onwatch_section_order', $new_value['section_order']);
    }
    if ($option === 'onwatch_single' && is_array($new_value)) {
        if (isset($new_value['show_player_section'])) {
            set_theme_mod('onwatch_show_player_section', !empty($new_value['show_player_section']));
        }
        if (isset($new_value['show_breadcrumb'])) {
            set_theme_mod('onwatch_show_breadcrumb', !empty($new_value['show_breadcrumb']));
        }
        if (isset($new_value['poster_click_action'])) {
            set_theme_mod('onwatch_poster_click_action', $new_value['poster_click_action']);
        }
        if (isset($new_value['related_by'])) {
            set_theme_mod('onwatch_related_by', $new_value['related_by']);
        }
    }
}
