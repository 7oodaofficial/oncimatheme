<div class="ow-modal" x-show="reportModal" x-transition:enter="ow-fade-enter-active" x-transition:enter-start="ow-fade-enter-from" x-cloak @click.away="reportModal = false">
    <div class="ow-modal__backdrop" @click="reportModal = false"></div>
    <div class="ow-modal__dialog ow-modal__dialog--sm">
        <button class="ow-modal__close" @click="reportModal = false" aria-label="<?php _e('إغلاق', 'onwatch'); ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
        <h3 class="ow-modal__title"><?php _e('الإبلاغ عن مشكلة', 'onwatch'); ?></h3>
        <form @submit.prevent="submitReport" style="display:flex;flex-direction:column;gap:0.75rem;">
            <select class="ow-input" x-model="reportData.type" required>
                <option value=""><?php _e('اختر نوع المشكلة', 'onwatch'); ?></option>
                <option value="broken"><?php _e('الرابط لا يعمل', 'onwatch'); ?></option>
                <option value="wrong"><?php _e('محتوى خاطئ', 'onwatch'); ?></option>
                <option value="quality"><?php _e('جودة سيئة', 'onwatch'); ?></option>
                <option value="other"><?php _e('أخرى', 'onwatch'); ?></option>
            </select>
            <textarea class="ow-input" x-model="reportData.message" rows="3" placeholder="<?php _e('تفاصيل إضافية (اختياري)', 'onwatch'); ?>"></textarea>
            <button type="submit" class="ow-btn ow-btn--primary ow-btn--full"><?php _e('إرسال البلاغ', 'onwatch'); ?></button>
            <p class="ow-auth__error" x-text="reportMessage" x-show="reportMessage"></p>
        </form>
    </div>
</div>
