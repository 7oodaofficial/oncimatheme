<div class="ow-modal" x-show="downloadModal" x-transition:enter="ow-fade-enter-active" x-transition:enter-start="ow-fade-enter-from" x-cloak @click.away="downloadModal = false">
    <div class="ow-modal__backdrop" @click="downloadModal = false"></div>
    <div class="ow-modal__dialog">
        <button class="ow-modal__close" @click="downloadModal = false" aria-label="<?php _e('إغلاق', 'onwatch'); ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
        <h3 class="ow-modal__title"><?php _e('روابط التحميل', 'onwatch'); ?></h3>
        <div class="ow-download-links" x-html="downloadLinksHtml"></div>
    </div>
</div>
