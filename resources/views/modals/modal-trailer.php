<div class="ow-modal" x-show="trailerModal" x-transition:enter="ow-fade-enter-active" x-transition:enter-start="ow-fade-enter-from" x-cloak @click.away="trailerModal = false">
    <div class="ow-modal__backdrop" @click="trailerModal = false"></div>
    <div class="ow-modal__dialog ow-modal__dialog--lg">
        <button class="ow-modal__close" @click="trailerModal = false" aria-label="<?php _e('إغلاق', 'onwatch'); ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
        <div class="ow-player-wrapper" x-show="!playerLoading" x-cloak x-transition:enter="ow-fade-enter-active" x-transition:enter-start="ow-fade-enter-from">
            <div class="ow-player">
                <iframe width="100%" height="100%" :src="trailerUrl" frameborder="0" allow="autoplay; encrypted-media; fullscreen" @load="playerLoading = false"></iframe>
            </div>
        </div>
        <?php
        $player_opts = get_option('onwatch_player', []);
        $show_loader = $player_opts['show_player_loader'] ?? true;
        $logo_url = get_template_directory_uri() . '/resources/assets/img/logo.svg';
        ?>
        <?php if ($show_loader): ?>
        <div class="ow-player__loader" x-show="playerLoading">
            <div class="ow-player__loader-inner">
                <img src="<?php echo esc_url($logo_url); ?>" alt="" class="ow-player__loader-logo" width="80" height="18">
                <div class="ow-player__loader-ring"></div>
            </div>
        </div>
        <?php else: ?>
        <div class="ow-player__placeholder" x-show="playerLoading">
            <div class="ow-skeleton ow-skeleton--episode"></div>
        </div>
        <?php endif; ?>
    </div>
</div>
