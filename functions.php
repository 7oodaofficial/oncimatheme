<?php

define('ONWATCH_VERSION', wp_get_theme()->get('Version'));

$dir_path = get_template_directory();
$dir_uri  = get_template_directory_uri();

define('ONWATCH_DIR_PATH', $dir_path);
define('ONWATCH_DIR_URI', trailingslashit($dir_uri));

require ONWATCH_DIR_PATH . '/inc/tr-grabber.php';
require ONWATCH_DIR_PATH . '/inc/helpers.php';
require ONWATCH_DIR_PATH . '/inc/seo.php';
require ONWATCH_DIR_PATH . '/inc/ajax.php';
require ONWATCH_DIR_PATH . '/inc/customizer.php';
require ONWATCH_DIR_PATH . '/inc/enqueue.php';
require ONWATCH_DIR_PATH . '/inc/admin-settings.php';
require ONWATCH_DIR_PATH . '/inc/block-patterns.php';

add_filter('body_class', 'onwatch_body_class');
function onwatch_body_class($classes) {
    $layout = get_theme_mod('onwatch_layout_style', 'fullwidth');
    if ($layout === 'boxed') $classes[] = 'ow-layout--boxed';
    $header = get_theme_mod('onwatch_header_style', 'sticky');
    if ($header === 'static') $classes[] = 'ow-header--static';
    if ($header === 'fixed') $classes[] = 'ow-header--fixed';
    return $classes;
}

add_action('after_setup_theme', 'onwatch_setup');

function onwatch_setup() {
    load_theme_textdomain('onwatch', ONWATCH_DIR_PATH . '/languages');

    add_theme_support('automatic-feed-links');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo');

    register_nav_menus([
        'header' => __('Menu Header', 'onwatch'),
        'footer' => __('Menu Footer', 'onwatch'),
    ]);
}

add_action('pre_get_posts', 'onwatch_pre_get_posts');

function onwatch_pre_get_posts($query) {
    if (!$query->is_main_query() || is_admin()) return;

    if ($query->is_post_type_archive('movies')) {
        $per_page = get_theme_mod('onwatch_per_page_movies', 24);
        $query->set('posts_per_page', $per_page);
    }

    if ($query->is_post_type_archive('series')) {
        $per_page = get_theme_mod('onwatch_per_page_series', 24);
        $query->set('posts_per_page', $per_page);
    }

    if ($query->is_category() || $query->is_tax() || $query->is_tag()) {
        $query->set('post_type', ['movies', 'series']);
    }
}

add_action('after_switch_theme', 'onwatch_activation');
function onwatch_activation() {
    flush_rewrite_rules();
}

add_action('admin_menu', 'onwatch_admin_menu');

function onwatch_admin_menu() {
    add_submenu_page(
        'tools.php',
        __('Reports', 'onwatch'),
        __('Reports', 'onwatch'),
        'manage_options',
        'onwatch-reports',
        'onwatch_reports_page'
    );
}

add_action('init', 'onwatch_performance_cleanup');
function onwatch_performance_cleanup() {
    if (get_theme_mod('onwatch_disable_emojis', false)) {
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
        add_filter('emoji_svg_url', '__return_false');
    }

    if (get_theme_mod('onwatch_disable_embeds', false)) {
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
        remove_action('wp_head', 'wp_oembed_add_host_js');
        add_filter('embed_oembed_discover', '__return_false');
        wp_deregister_script('wp-embed');
    }

    if (get_theme_mod('onwatch_disable_wlw', false)) {
        remove_action('wp_head', 'wlwmanifest_link');
    }

    if (get_theme_mod('onwatch_disable_rsd', false)) {
        remove_action('wp_head', 'rsd_link');
    }

    if (get_theme_mod('onwatch_disable_shortlink', false)) {
        remove_action('wp_head', 'wp_shortlink_wp_head');
        remove_action('template_redirect', 'wp_shortlink_header', 11);
    }

    if (get_theme_mod('onwatch_remove_wp_version', false)) {
        remove_action('wp_head', 'wp_generator');
        add_filter('the_generator', '__return_empty_string');
    }
}

add_action('wp_head', 'onwatch_custom_head_scripts', 999);
function onwatch_custom_head_scripts() {
    $ga_id = get_theme_mod('onwatch_ga_id', '');
    if ($ga_id) {
        ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr($ga_id); ?>"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?php echo esc_js($ga_id); ?>');</script>
<?php
    }

    $custom_css = get_option('onwatch_custom_code', [])['custom_css'] ?? '';
    if ($custom_css) {
        echo '<style id="onwatch-custom-css">' . wp_strip_all_tags($custom_css) . '</style>' . "\n";
    }

    $custom_js = get_option('onwatch_custom_code', [])['custom_js_head'] ?? '';
    if ($custom_js) {
        echo '<script>' . $custom_js . '</script>' . "\n";
    }

    $head_scripts = get_theme_mod('onwatch_head_scripts', '');
    if ($head_scripts) {
        echo $head_scripts . "\n";
    }
}

add_filter('wp_lazy_loading_enabled', 'onwatch_lazyload_filter');
function onwatch_lazyload_filter($default) {
    return (bool)get_theme_mod('onwatch_lazyload', true);
}

add_action('wp_footer', 'onwatch_custom_footer_scripts', 999);
function onwatch_custom_footer_scripts() {
    $custom_js = get_option('onwatch_custom_code', [])['custom_js_footer'] ?? '';
    if ($custom_js) {
        echo '<script>' . $custom_js . '</script>' . "\n";
    }

    $footer_scripts = get_theme_mod('onwatch_footer_scripts', '');
    if ($footer_scripts) {
        echo $footer_scripts . "\n";
    }
}

function onwatch_reports_page() {
    if (!current_user_can('manage_options')) return;

    if (isset($_POST['clear_reports']) && check_admin_referer('onwatch_clear_reports')) {
        update_option('onwatch_reports', []);
        echo '<div class="notice notice-success"><p>' . __('All reports cleared.', 'onwatch') . '</p></div>';
    }

    $reports = get_option('onwatch_reports', []);
    ?>
    <div class="wrap">
        <h1><?php _e('Reports', 'onwatch'); ?></h1>
        <form method="post">
            <?php wp_nonce_field('onwatch_clear_reports'); ?>
            <p><button type="submit" name="clear_reports" class="button button-secondary"><?php _e('Clear All', 'onwatch'); ?></button></p>
        </form>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Date', 'onwatch'); ?></th>
                    <th><?php _e('Post', 'onwatch'); ?></th>
                    <th><?php _e('Type', 'onwatch'); ?></th>
                    <th><?php _e('Message', 'onwatch'); ?></th>
                    <th><?php _e('IP', 'onwatch'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reports)): ?>
                <tr><td colspan="5"><?php _e('No reports yet.', 'onwatch'); ?></td></tr>
                <?php else: ?>
                <?php foreach (array_reverse($reports) as $r): ?>
                <tr>
                    <td><?php echo esc_html($r['date']); ?></td>
                    <td><a href="<?php echo get_permalink($r['post_id']); ?>"><?php echo get_the_title($r['post_id']); ?></a></td>
                    <td><?php echo esc_html($r['type']); ?></td>
                    <td><?php echo esc_html($r['message']); ?></td>
                    <td><?php echo esc_html($r['ip']); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}
