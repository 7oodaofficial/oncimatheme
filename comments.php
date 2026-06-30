<div class="ow-comments">
<?php if (have_comments()): ?>
    <h3 class="ow-comments__title">
        <?php
        printf(
            _n('تعليق واحد', '%1$s تعليقات', get_comments_number(), 'onwatch'),
            number_format_i18n(get_comments_number())
        );
        ?>
    </h3>
    <ol class="ow-comments__list">
        <?php
        wp_list_comments([
            'style'       => 'ol',
            'short_ping'  => true,
            'avatar_size' => 48,
        ]);
        ?>
    </ol>
    <?php if (get_comment_pages_count() > 1 && get_option('page_comments')): ?>
    <nav class="ow-comments__nav">
        <div class="nav-previous"><?php previous_comments_link(__('السابق', 'onwatch')); ?></div>
        <div class="nav-next"><?php next_comments_link(__('التالي', 'onwatch')); ?></div>
    </nav>
    <?php endif; ?>
<?php endif; ?>

<?php if (!comments_open() && get_comments_number() && post_type_supports(get_post_type(), 'comments')): ?>
    <p class="ow-comments__closed"><?php _e('التعليقات مغلقة', 'onwatch'); ?></p>
<?php endif; ?>

<?php
$args = [
    'title_reply'         => __('اترك تعليقاً', 'onwatch'),
    'label_submit'        => __('إرسال', 'onwatch'),
    'comment_field'       => '<textarea id="comment" name="comment" class="ow-input" rows="4" placeholder="' . __('تعليقك...', 'onwatch') . '" required></textarea>',
    'fields'              => [
        'author' => '<input id="author" name="author" class="ow-input" placeholder="' . __('الاسم', 'onwatch') . '" type="text" required>',
        'email'  => '<input id="email" name="email" class="ow-input" placeholder="' . __('البريد الإلكتروني', 'onwatch') . '" type="email" required>',
    ],
    'class_submit'        => 'ow-btn ow-btn--primary',
    'submit_button'       => '<button type="submit" class="%s">%s</button>',
];
comment_form($args);
?>
</div>
