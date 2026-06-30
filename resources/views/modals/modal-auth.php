<div class="ow-modal" x-show="authModal" x-transition:enter="ow-fade-enter-active" x-transition:enter-start="ow-fade-enter-from" x-cloak @click.away="authModal = false">
    <div class="ow-modal__backdrop" @click="authModal = false"></div>
    <div class="ow-modal__dialog ow-modal__dialog--sm">
        <button class="ow-modal__close" @click="authModal = false" aria-label="<?php _e('إغلاق', 'onwatch'); ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>

        <div class="ow-auth">
            <div class="ow-auth__tabs">
                <button class="ow-auth__tab" :class="{'is-active': authModal === 'login'}" @click="authModal = 'login'"><?php _e('تسجيل الدخول', 'onwatch'); ?></button>
                <?php if (get_theme_mod('onwatch_open_register', false)): ?>
                <button class="ow-auth__tab" :class="{'is-active': authModal === 'register'}" @click="authModal = 'register'"><?php _e('إنشاء حساب', 'onwatch'); ?></button>
                <?php endif; ?>
            </div>

            <form x-show="authModal === 'login'" class="ow-auth__form" @submit.prevent="submitLogin">
                <label class="ow-label"><?php _e('اسم المستخدم', 'onwatch'); ?></label>
                <input type="text" class="ow-input" x-model="loginData.username" required>

                <label class="ow-label"><?php _e('كلمة المرور', 'onwatch'); ?></label>
                <input type="password" class="ow-input" x-model="loginData.password" required>

                <button type="submit" class="ow-btn ow-btn--primary ow-btn--full"><?php _e('دخول', 'onwatch'); ?></button>
                <p class="ow-auth__error" x-text="loginError" x-show="loginError"></p>
            </form>

            <form x-show="authModal === 'register'" class="ow-auth__form" @submit.prevent="submitRegister">
                <label class="ow-label"><?php _e('اسم المستخدم', 'onwatch'); ?></label>
                <input type="text" class="ow-input" x-model="registerData.username" required>

                <label class="ow-label"><?php _e('البريد الإلكتروني', 'onwatch'); ?></label>
                <input type="email" class="ow-input" x-model="registerData.email" required>

                <label class="ow-label"><?php _e('كلمة المرور', 'onwatch'); ?></label>
                <input type="password" class="ow-input" x-model="registerData.password" required>

                <button type="submit" class="ow-btn ow-btn--primary ow-btn--full"><?php _e('إنشاء حساب', 'onwatch'); ?></button>
                <p class="ow-auth__error" x-text="registerError" x-show="registerError"></p>
            </form>
        </div>
    </div>
</div>
