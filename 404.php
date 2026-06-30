<?php get_header(); ?>
<div class="ow-404">
    <div class="ow-container">
        <div class="ow-404__code">404</div>
        <h1 class="ow-404__title"><?php _e('الصفحة غير موجودة', 'onwatch'); ?></h1>
        <p class="ow-404__desc"><?php _e('عذراً، الصفحة التي تبحث عنها غير موجودة أو تم نقلها.', 'onwatch'); ?></p>
        <a href="<?php echo esc_url(home_url('/')); ?>" class="ow-btn ow-btn--primary ow-btn--lg"><?php _e('العودة إلى الرئيسية', 'onwatch'); ?></a>
    </div>
</div>
<?php get_footer();
