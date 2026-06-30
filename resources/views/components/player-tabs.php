<?php
$post_id = $args['post_id'] ?? 0;
$is_episode = $args['is_episode'] ?? false;
$term_id = $args['term_id'] ?? 0;

$player_opts = get_option('onwatch_player', []);
$enable_download     = $player_opts['enable_download'] ?? true;
$enable_report       = $player_opts['enable_report'] ?? true;
$show_loader         = $player_opts['show_player_loader'] ?? true;
$show_watermark      = $player_opts['show_player_watermark'] ?? false;
$watermark_pos       = $player_opts['player_watermark_position'] ?? 'br';
$ad_code             = $player_opts['player_ad_code'] ?? '';
$ad_skip_time        = absint($player_opts['player_ad_skip_time'] ?? 5);
$player_width        = isset($player_opts['player_width']) ? absint($player_opts['player_width']) : 100;
$player_height       = $player_opts['player_height'] ?? '480px';
$player_preload      = !empty($player_opts['player_preload']);
$watermark_size      = isset($player_opts['player_watermark_size']) ? absint($player_opts['player_watermark_size']) : 90;
$loader_size         = isset($player_opts['player_loader_size']) ? absint($player_opts['player_loader_size']) : 80;

if ($is_episode && $term_id) {
    $links = onwatch_get_links($term_id, 'episodes');
} else {
    $links = onwatch_get_links($post_id);
}

if (empty($links)) return;

$servers = array();
foreach ($links as $i => $link) {
    $type = $link['type'] ?? '1';
    $url = $type == '1'
        ? ($is_episode ? onwatch_embed_url_episode($i, $term_id) : onwatch_embed_url($i, $post_id))
        : ($is_episode ? onwatch_download_url_episode($i, $term_id) : onwatch_download_url($i, $post_id));
    $servers[] = array(
        'name'     => $link['name'] ?? sprintf(__('سيرفر %d', 'onwatch'), $i + 1),
        'quality'  => $link['quality'] ?? '',
        'language' => $link['language'] ?? '',
        'type'     => $type,
        'url'      => $url,
    );
}
$initial_url = $player_preload && $servers[0]['type'] == '1' ? $servers[0]['url'] : '';
$has_ad = !empty($ad_code);
$logo_url = get_template_directory_uri() . '/resources/assets/img/logo.svg';
?>
<div class="ow-player-section" x-data="{
    activeServer: 0,
    playerLoading: true,
    currentSrc: '<?php echo esc_url($initial_url); ?>',
    showAd: <?php echo $has_ad ? 'true' : 'false'; ?>,
    adCounter: <?php echo $ad_skip_time; ?>,
    adSkippable: false,
    adInterval: null,
    startAdTimer() {
        if (!this.showAd) return;
        this.adCounter = <?php echo $ad_skip_time; ?>;
        this.adSkippable = false;
        this.adInterval = setInterval(() => {
            this.adCounter--;
            if (this.adCounter <= 0) {
                this.adSkippable = true;
                clearInterval(this.adInterval);
            }
        }, 1000);
    },
    skipAd() {
        clearInterval(this.adInterval);
        this.showAd = false;
        this.playerLoading = true;
    },
    onPlayerLoad() {
        this.playerLoading = false;
    }
}" x-init="startAdTimer(); if (!currentSrc) playerLoading = false"
style="--player-w: <?php echo max(1, $player_width); ?>%; --player-h: <?php echo esc_attr($player_height); ?>; --watermark-size: <?php echo $watermark_size; ?>px; --loader-size: <?php echo $loader_size; ?>px;">

    <div class="ow-server-tabs">
        <?php foreach ($servers as $i => $s): ?>
        <button class="ow-server-tab" :class="{'is-active': activeServer === <?php echo $i; ?>}" @click="activeServer = <?php echo $i; ?>; playerLoading = true; showAd = <?php echo $has_ad ? 'true' : 'false'; ?>; startAdTimer(); updateHistory(<?php echo $post_id; ?>); <?php if ($s['type'] == '1'): ?>currentSrc = '<?php echo esc_url($s['url']); ?>'<?php endif; ?>">
            <span class="ow-server-tab__label"><?php echo esc_html($s['name']); ?></span>
            <?php $sub = $s['quality'] ?: $s['language']; if ($sub): ?>
            <span class="ow-server-tab__sub"><?php echo esc_html($sub); ?></span>
            <?php endif; ?>
        </button>
        <?php endforeach; ?>
    </div>

    <div class="ow-player-wrapper">
        <?php if ($has_ad): ?>
        <div class="ow-player-ad" x-show="showAd" x-cloak x-transition:enter="ow-fade-enter-active" x-transition:enter-start="ow-fade-enter-from">
            <div class="ow-player-ad__container">
                <?php echo $ad_code; ?>
            </div>
            <div class="ow-player-ad__bar">
                <span class="ow-player-ad__timer" x-text="'<?php _e('يمكنك التخطي بعد', 'onwatch'); ?> ' + adCounter + 's'"></span>
                <button class="ow-player-ad__skip" :class="{'ow-player-ad__skip--countdown': !adSkippable}" :disabled="!adSkippable" @click="skipAd">
                    <span x-show="adSkippable"><?php _e('تخطي الإعلان', 'onwatch'); ?></span>
                    <span x-show="!adSkippable" x-text="adCounter + 's'"></span>
                </button>
            </div>
        </div>
        <?php endif; ?>

        <div class="ow-player" x-show="!playerLoading && !showAd" x-cloak x-transition:enter="ow-fade-enter-active" x-transition:enter-start="ow-fade-enter-from">
            <iframe :src="currentSrc" frameborder="0" allow="autoplay; encrypted-media; fullscreen" @load="onPlayerLoad()"></iframe>
        </div>

        <?php if ($show_loader): ?>
        <div class="ow-player__loader" x-show="playerLoading || showAd" x-cloak>
            <div class="ow-player__loader-inner">
                <img src="<?php echo esc_url($logo_url); ?>" alt="" class="ow-player__loader-logo" width="80" height="18">
                <div class="ow-player__loader-ring"></div>
            </div>
        </div>
        <?php else: ?>
        <div class="ow-player__placeholder" x-show="playerLoading || showAd" x-cloak>
            <div class="ow-skeleton ow-skeleton--episode"></div>
        </div>
        <?php endif; ?>

        <?php if ($show_watermark): ?>
        <div class="ow-player__watermark ow-player__watermark--<?php echo esc_attr($watermark_pos); ?>">
            <img src="<?php echo esc_url($logo_url); ?>" alt="" width="90" height="20">
        </div>
        <?php endif; ?>

        <?php if ($enable_download): foreach ($servers as $i => $s): if ($s['type'] != '1') { ?>
        <div x-show="activeServer === <?php echo $i; ?> && !showAd" x-cloak style="margin-top: 0.75rem;">
            <a href="<?php echo esc_url($s['url']); ?>" class="ow-btn ow-btn--primary" target="_blank"><?php _e('تحميل', 'onwatch'); ?></a>
        </div>
        <?php } endforeach; endif; ?>
    </div>

    <?php if ($enable_report): ?>
    <button class="ow-report-btn" @click="openReport(<?php echo $post_id; ?>)"><?php _e('الإبلاغ عن مشكلة', 'onwatch'); ?></button>
    <?php endif; ?>
</div>
