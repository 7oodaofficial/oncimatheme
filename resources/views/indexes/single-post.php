<article class="ow-post">
    <div class="ow-container">
        <?php if (have_posts()): while (have_posts()): the_post(); ?>
        <header class="ow-post__header">
            <h1 class="ow-post__title"><?php the_title(); ?></h1>
            <div class="ow-post__meta">
                <span><?php echo get_the_date(); ?></span>
                <span><?php the_author(); ?></span>
            </div>
        </header>
        <div class="ow-post__content">
            <?php the_content(); ?>
        </div>
        <?php if (comments_open() || get_comments_number()): comments_template(); endif; ?>
        <?php endwhile; endif; ?>
    </div>
</article>