<!doctype html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="profile" href="https://gmpg.org/xfn/11">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<?php
$font_family = get_theme_mod('onwatch_font_family', 'Cairo');
$body_w = get_theme_mod('onwatch_body_weight', 400);
$heading_w = get_theme_mod('onwatch_heading_weight', 700);
$font_weights = $body_w . ';' . $heading_w;
?>
<link href="https://fonts.googleapis.com/css2?family=<?php echo str_replace(' ', '+', $font_family); ?>:wght@<?php echo $font_weights; ?>&display=swap" rel="stylesheet">
<?php
$favicon_id = get_theme_mod('onwatch_favicon', 0);
if ($favicon_id) {
    $favicon_url = wp_get_attachment_image_url($favicon_id, 'full');
    if ($favicon_url) {
        echo '<link rel="icon" href="' . esc_url($favicon_url) . '" sizes="32x32">' . "\n";
    }
}

$home_title = get_theme_mod('onwatch_home_meta_title', '');
$home_desc  = get_theme_mod('onwatch_home_meta_desc', '');
if (is_front_page() && !empty($home_title)) {
    echo '<title>' . esc_html($home_title) . '</title>' . "\n";
}
if (is_front_page() && !empty($home_desc)) {
    echo '<meta name="description" content="' . esc_attr($home_desc) . '">' . "\n";
}
?>
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?> x-data="onwatch" @keydown.escape="closeAll()">
<div id="onwatch-app">

<?php
$gen_opts = get_option('onwatch_general', []);
$show_loader = !empty($gen_opts['loading_screen']);
$loader_timeout = isset($gen_opts['loading_timeout']) ? absint($gen_opts['loading_timeout']) : 5;
if ($show_loader):
?>
<div id="ow-page-loader">
  <div class="ow-page-loader__content">
    <img src="<?php echo ONWATCH_DIR_URI; ?>resources/assets/img/onwatch-logo.svg" alt="ONWatch" class="ow-page-loader__logo" width="120" height="27">
    <div class="ow-page-loader__spinner"></div>
  </div>
</div>
<script>
(function(){var l=document.getElementById('ow-page-loader');if(!l)return;var t=<?php echo $loader_timeout; ?>*1000,d=!1;function h(){if(d)return;d=!0;l.style.opacity='0';l.style.pointerEvents='none';setTimeout(function(){l.style.display='none'},400)}window.addEventListener('load',h);setTimeout(h,t)})();
</script>
<?php endif; ?>

<header class="ow-header" :class="{ 'ow-header--solid': headerSolid }">
    <div class="ow-container ow-header__inner">
        <button class="ow-header__menu-btn" @click="menuOpen = !menuOpen" aria-label="<?php _e('القائمة', 'onwatch'); ?>">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>

        <a href="<?php echo esc_url(home_url('/')); ?>" class="ow-header__logo">
            <?php
            $logo_id = get_theme_mod('onwatch_logo');
            $logo_width = get_theme_mod('onwatch_logo_width', 160);
            if ($logo_id) {
                echo wp_get_attachment_image($logo_id, 'full', false, ['width' => $logo_width, 'alt' => get_bloginfo('name')]);
            } else {
                echo '<img src="' . ONWATCH_DIR_URI . 'resources/assets/img/onwatch-logo.svg" width="' . $logo_width . '" height="36" alt="ONWatch">';
            }
            ?>
        </a>

        <nav class="ow-header__nav" :class="{ 'is-open': menuOpen }">
            <?php
            if (has_nav_menu('header')) {
                wp_nav_menu([
                    'theme_location' => 'header',
                    'container'      => false,
                    'menu_class'     => 'ow-nav',
                    'depth'          => 2,
                    'fallback_cb'    => false,
                ]);
            }
            ?>
        </nav>

        <div class="ow-nav-overlay" x-show="menuOpen" @click="menuOpen = false" x-cloak></div>

        <div class="ow-header__actions">
            <button class="ow-header__search-btn" @click="searchOpen = !searchOpen; if(searchOpen) $nextTick(() => { if($refs.searchInput) $refs.searchInput.focus() })" aria-label="<?php _e('بحث', 'onwatch'); ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </button>

            <?php if (!get_theme_mod('onwatch_disable_auth', false)): ?>
                <?php if (is_user_logged_in()): ?>
                    <a href="<?php echo esc_url(get_edit_user_link()); ?>" class="ow-header__user-btn">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </a>
                <?php else: ?>
                    <button class="ow-header__user-btn" @click="openAuth('login')" aria-label="<?php _e('تسجيل الدخول', 'onwatch'); ?>">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                    </button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="ow-container" x-show="searchOpen" x-cloak style="padding-block: 0.75rem;">
        <?php get_template_part('formsearch'); ?>
    </div>
</header>
<main id="onwatch-main" style="padding-top: var(--header-height);">
