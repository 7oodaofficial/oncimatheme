</main>

<footer class="ow-footer">
    <div class="ow-container">
        <div class="ow-footer__grid" style="--cols: <?php echo absint(get_theme_mod('onwatch_footer_columns', 3)); ?>">
            <div class="ow-footer__brand">
                <a href="<?php echo esc_url(home_url('/')); ?>" class="ow-footer__logo">
                    <?php
                    $logo_id = get_theme_mod('onwatch_logo');
                    if ($logo_id) {
                        echo wp_get_attachment_image($logo_id, 'full', false, ['width' => 160, 'alt' => get_bloginfo('name')]);
                    } else {
                        echo '<img src="' . ONWATCH_DIR_URI . 'resources/assets/img/onwatch-logo.svg" width="160" height="36" alt="ONWatch">';
                    }
                    ?>
                </a>
                <?php $footer_text = get_theme_mod('onwatch_footer_text', ''); ?>
                <?php if (!empty($footer_text)): ?>
                    <p class="ow-footer__desc"><?php echo wp_kses_post($footer_text); ?></p>
                <?php endif; ?>
            </div>

            <?php if (has_nav_menu('footer')): ?>
            <nav class="ow-footer__nav">
                <h4 class="ow-footer__title"><?php _e('روابط سريعة', 'onwatch'); ?></h4>
                <?php
                wp_nav_menu([
                    'theme_location' => 'footer',
                    'container'      => false,
                    'menu_class'     => 'ow-footer__menu',
                    'depth'          => 1,
                    'fallback_cb'    => false,
                ]);
                ?>
            </nav>
            <?php endif; ?>

            <div class="ow-footer__social-wrap">
                <h4 class="ow-footer__title"><?php _e('تابعنا', 'onwatch'); ?></h4>
                <div class="ow-footer__social">
                    <?php
                    $socials = [
                        'onwatch_footer_facebook' => ['label' => 'Facebook', 'icon' => 'M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z'],
                        'onwatch_footer_twitter'  => ['label' => 'Twitter', 'icon' => 'M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z'],
                        'onwatch_footer_telegram' => ['label' => 'Telegram', 'icon' => 'M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z'],
                        'onwatch_footer_youtube'  => ['label' => 'YouTube', 'icon' => 'M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 12a29 29 0 0 0 .46 5.58 2.78 2.78 0 0 0 1.94 2C5.12 20 12 20 12 20s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2A29 29 0 0 0 23 12a29 29 0 0 0-.46-5.58zM10 15.5V8.5l6 3.5-6 3.5z'],
                        'onwatch_footer_instagram' => ['label' => 'Instagram', 'icon' => 'M16 4H8a4 4 0 0 0-4 4v8a4 4 0 0 0 4 4h8a4 4 0 0 0 4-4V8a4 4 0 0 0-4-4zM12 9a3 3 0 1 1 0 6 3 3 0 0 1 0-6zM17 6.5h.01'],
                        'onwatch_footer_tiktok'   => ['label' => 'TikTok', 'icon' => 'M9 12a4 4 0 1 0 0 8 4 4 0 0 0 0-8zM9 4v8a4 4 0 0 0 0 8M21 8a4 4 0 0 1-4-4M21 8H9'],
                    ];
                    foreach ($socials as $key => $s):
                        $url = get_theme_mod($key, '');
                        if (empty($url)) continue;
                    ?>
                    <a href="<?php echo esc_url($url); ?>" class="ow-footer__social-link" target="_blank" rel="noopener" aria-label="<?php echo esc_attr($s['label']); ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="<?php echo $s['icon']; ?>"/></svg>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="ow-footer__bottom">
            <p class="ow-footer__copyright"><?php echo wp_kses_post(get_theme_mod('onwatch_copyright', '&copy; ONWatch جميع الحقوق محفوظة')); ?></p>
        </div>
    </div>
</footer>

<?php if (!get_theme_mod('onwatch_disable_auth', false) && !is_user_logged_in()): ?>
    <?php get_template_part('resources/views/modals/modal', 'auth'); ?>
<?php endif; ?>

<?php get_template_part('resources/views/modals/modal', 'trailer'); ?>
<?php if (get_theme_mod('onwatch_show_download_btn', true)): ?>
<?php get_template_part('resources/views/modals/modal', 'download'); ?>
<?php endif; ?>
<?php if (get_theme_mod('onwatch_show_report_btn', true)): ?>
<?php get_template_part('resources/views/modals/modal', 'report'); ?>
<?php endif; ?>

<div id="onwatch-toasts"></div>

<?php wp_footer(); ?>
</div>
</body>
</html>
