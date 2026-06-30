<?php
defined('ABSPATH') || exit;

function onwatch_get_legacy_option($new_key, $legacy_key, $default = '') {
    $new = get_option('onwatch_general', []);
    $val = $new[$new_key] ?? get_option($legacy_key, null);
    return $val !== null ? $val : $default;
}

function onwatch_get_legacy_api_key()    { return onwatch_get_legacy_option('tmdb_api_key', 'onwatch_api_key', defined('TR_GRABBER_API_KEY') ? TR_GRABBER_API_KEY : ''); }
function onwatch_get_legacy_api_lang()   { return onwatch_get_legacy_option('tmdb_lang', 'onwatch_api_lang', 'ar-AR'); }
function onwatch_get_legacy_upload()     { return (bool)onwatch_get_legacy_option('upload_images', 'onwatch_upload_images', 0); }

if (!defined('TR_GRABBER_API_KEY'))     define('TR_GRABBER_API_KEY', '');
if (!defined('TR_GRABBER_LANG'))        define('TR_GRABBER_LANG', 'ar-AR');
if (!defined('TR_GRABBER_POST_STATUS')) define('TR_GRABBER_POST_STATUS', 'publish');
if (!defined('TR_GRABBER_UPLOAD_IMAGES')) define('TR_GRABBER_UPLOAD_IMAGES', 0);
if (!defined('TR_GRABBER_BACKDROP_WIDTH'))  define('TR_GRABBER_BACKDROP_WIDTH', 780);
if (!defined('TR_GRABBER_BACKDROP_HEIGHT')) define('TR_GRABBER_BACKDROP_HEIGHT', 440);
if (!defined('TR_GRABBER_SEASON_WIDTH'))    define('TR_GRABBER_SEASON_WIDTH', 185);
if (!defined('TR_GRABBER_SEASON_HEIGHT'))   define('TR_GRABBER_SEASON_HEIGHT', 278);
if (!defined('TR_GRABBER_EPISODE_WIDTH'))   define('TR_GRABBER_EPISODE_WIDTH', 185);
if (!defined('TR_GRABBER_EPISODE_HEIGHT'))  define('TR_GRABBER_EPISODE_HEIGHT', 104);

if (!function_exists('trgrabber_curl')):
function trgrabber_curl($url) {
    $response = wp_remote_get($url, array('sslverify' => false, 'timeout' => 30));
    if (is_array($response)) return $response['body'];
    return '';
}
endif;

if (!function_exists('tr_grabber_select_taxonomy')):
function tr_grabber_select_taxonomy($tax, $select = 0) {
    $terms = get_terms(array('taxonomy' => $tax, 'hide_empty' => false, 'orderby' => 'name'));
    $return = '';
    foreach ($terms as $t) {
        $return .= '<option ' . selected($select, $t->term_id, false) . ' value="' . $t->term_id . '">' . $t->name . '</option>';
    }
    return $return;
}
endif;

if (!function_exists('tr_grabber_type')):
function tr_grabber_type($id = null) {
    if ($id) {
        $pt = get_post_type($id);
        if ($pt == 'movies') return 1;
        if ($pt == 'series') return 2;
        return 0;
    }
    $screen = get_current_screen();
    if ($screen) {
        if ($screen->post_type == 'movies') return 1;
        if ($screen->post_type == 'series') return 2;
    }
    if (isset($_GET['post_type']) && $_GET['post_type'] == 'movies') return 1;
    if (isset($_GET['post_type']) && $_GET['post_type'] == 'series') return 2;
    return 0;
}
endif;

add_action('admin_enqueue_scripts', 'onwatch_admin_enqueue_scripts');
function onwatch_admin_enqueue_scripts($hook) {
    $screen = get_current_screen();
    $is_onwatch = ($screen && in_array($screen->post_type, array('movies', 'series'))) || strpos($hook, 'onwatch-episodes') !== false || strpos($hook, 'onwatch-settings') !== false || strpos($hook, 'onwatch-quick-links') !== false;

    if (!$is_onwatch) return;

    wp_enqueue_media();
    wp_enqueue_script('jquery-ui-sortable');
    wp_enqueue_style('wp-color-picker');

    $manual_nonce = wp_create_nonce('onwatch_manual');
    $vars = array(
        'ajaxurl'       => admin_url('admin-ajax.php'),
        'nonce'         => wp_create_nonce('trstring'),
        'live_nonce'    => wp_create_nonce('trgrabberlive'),
        'manual_nonce'  => $manual_nonce,
        'language'      => get_bloginfo('language'),
        'api_key'       => TR_GRABBER_API_KEY,
        'post_id'       => isset($_GET['post']) ? intval($_GET['post']) : 0,
        'tmdb_id'       => isset($_GET['post']) ? intval(get_post_meta($_GET['post'], TR_GRABBER_FIELD_ID, true)) : 0,
        'post_type'     => $screen ? $screen->post_type : '',
        'loading'       => __('Loading...', 'onwatch'),
        'empty'         => __('Enter an ID', 'onwatch'),
        'url'           => admin_url('admin-ajax.php'),
        'none'          => __('There were no results', 'onwatch'),
        'none_season'   => __('You must fill in the season field', 'onwatch'),
        'none_episode'  => __('You must fill in the episode field', 'onwatch'),
        'none_type'     => __('You must fill in the type field', 'onwatch'),
        'none_lang'     => __('You must fill in the field language', 'onwatch'),
        'none_quality'  => __('You must fill in the quality field', 'onwatch'),
        'none_links'    => __('You must fill in the links field', 'onwatch'),
        'done'          => __('Done!', 'onwatch'),
        'prompt_season'  => __('Enter season number:', 'onwatch'),
        'prompt_episode' => __('Enter episode number(s) (comma separated):', 'onwatch'),
        'prompt_links'   => __('Paste links (one per line):', 'onwatch'),
        'prompt_type'    => __('Link type (1=embed, 2=download):', 'onwatch'),
        'save_first'     => __('Save the post first, then import here.', 'onwatch'),
        'season_exists'  => __('This season already exists.', 'onwatch'),
        'episode_exists' => __('This episode already exists for this season.', 'onwatch'),
    );

    wp_enqueue_script('onwatch-admin', ONWATCH_DIR_URI . 'resources/assets/js/admin.js', array('jquery', 'jquery-ui-sortable', 'wp-color-picker'), ONWATCH_VERSION, true);
    wp_localize_script('onwatch-admin', 'OnwatchAdmin', $vars);

    wp_enqueue_style('onwatch-admin', ONWATCH_DIR_URI . 'resources/assets/css/admin.css', array(), ONWATCH_VERSION);
}

add_action('admin_menu', 'onwatch_admin_menu_page');
function onwatch_admin_menu_page() {
    add_menu_page('ONWatch', 'ONWatch', 'manage_options', 'onwatch-settings', 'onwatch_settings_page', 'dashicons-admin-settings', 2);
    add_submenu_page('onwatch-settings', __('Settings', 'onwatch'), __('Settings', 'onwatch'), 'manage_options', 'onwatch-settings', 'onwatch_settings_page');
    add_submenu_page('onwatch-settings', __('Episodes', 'onwatch'), __('Episodes', 'onwatch'), 'manage_options', 'onwatch-episodes', 'onwatch_episodes_page');
    add_submenu_page('onwatch-settings', __('Reports', 'onwatch'), __('Reports', 'onwatch'), 'manage_options', 'onwatch-reports', 'onwatch_reports_page');
    add_submenu_page('onwatch-settings', __('Quick Links', 'onwatch'), __('Quick Links', 'onwatch'), 'manage_options', 'onwatch-quick-links', 'onwatch_quick_links_page');
}

function onwatch_settings_page() {
    if (!current_user_can('manage_options')) return;
    ?>
    <div class="wrap">
        <h1><?php _e('ONWatch Settings', 'onwatch'); ?></h1>
        <form method="post" action="options.php">
            <?php settings_fields('onwatch_settings'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="onwatch_api_key"><?php _e('TMDb API Key', 'onwatch'); ?></label></th>
                    <td><input type="text" id="onwatch_api_key" name="onwatch_api_key" value="<?php echo esc_attr(onwatch_get_legacy_api_key()); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="onwatch_api_lang"><?php _e('TMDb Language', 'onwatch'); ?></label></th>
                    <td>
                        <select id="onwatch_api_lang" name="onwatch_api_lang">
                            <?php
                            $langs = array('ar-AR' => 'العربية', 'en-EN' => 'English', 'fr-FR' => 'Français', 'de-DE' => 'Deutsch', 'es-ES' => 'Español', 'tr-TR' => 'Türkçe', 'fa-IR' => 'فارسی');
                            $current = onwatch_get_legacy_api_lang();
                            foreach ($langs as $code => $label) {
                                echo '<option value="' . $code . '" ' . selected($current, $code, false) . '>' . $label . '</option>';
                            }
                            ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="onwatch_upload_images"><?php _e('Upload Images to Media Library', 'onwatch'); ?></label></th>
                    <td><input type="checkbox" id="onwatch_upload_images" name="onwatch_upload_images" value="1" <?php checked(onwatch_get_legacy_upload(), 1); ?>></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <hr>
        <h2><?php _e('Permalink Slugs', 'onwatch'); ?></h2>
        <form method="post" action="options.php">
            <?php settings_fields('onwatch_permalinks'); ?>
            <table class="form-table">
                <?php
                $slugs = array(
                    'onwatch_slug_movies'  => array('label' => __('Movies', 'onwatch'), 'default' => 'movie'),
                    'onwatch_slug_series'  => array('label' => __('Series', 'onwatch'), 'default' => 'serie'),
                    'onwatch_slug_season'  => array('label' => __('Season', 'onwatch'), 'default' => 'season'),
                    'onwatch_slug_episode' => array('label' => __('Episode', 'onwatch'), 'default' => 'episode'),
                    'onwatch_slug_letter'  => array('label' => __('Letter', 'onwatch'), 'default' => 'letter'),
                    'onwatch_slug_cast'    => array('label' => __('Cast', 'onwatch'), 'default' => 'cast'),
                    'onwatch_slug_casttv'  => array('label' => __('Cast TV', 'onwatch'), 'default' => 'cast_tv'),
                    'onwatch_slug_director' => array('label' => __('Director', 'onwatch'), 'default' => 'director'),
                    'onwatch_slug_directortv' => array('label' => __('Director TV', 'onwatch'), 'default' => 'director_tv'),
                    'onwatch_slug_year'    => array('label' => __('Year', 'onwatch'), 'default' => 'release'),
                );
                foreach ($slugs as $key => $s):
                    $val = get_option($key, $s['default']);
                ?>
                <tr>
                    <th><label for="<?php echo $key; ?>"><?php echo $s['label']; ?></label></th>
                    <td><code><?php echo home_url('/'); ?></code><input type="text" id="<?php echo $key; ?>" name="<?php echo $key; ?>" value="<?php echo esc_attr($val); ?>" class="regular-text code"></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php submit_button(__('Save Slugs', 'onwatch')); ?>
        </form>
    </div>
    <?php
}

add_action('admin_init', 'onwatch_register_settings');
function onwatch_register_settings() {
    register_setting('onwatch_settings', 'onwatch_api_key', 'sanitize_text_field');
    register_setting('onwatch_settings', 'onwatch_api_lang', 'sanitize_text_field');
    register_setting('onwatch_settings', 'onwatch_upload_images', 'intval');

    register_setting('onwatch_permalinks', 'onwatch_slug_movies', 'sanitize_title');
    register_setting('onwatch_permalinks', 'onwatch_slug_series', 'sanitize_title');
    register_setting('onwatch_permalinks', 'onwatch_slug_season', 'sanitize_title');
    register_setting('onwatch_permalinks', 'onwatch_slug_episode', 'sanitize_title');
    register_setting('onwatch_permalinks', 'onwatch_slug_letter', 'sanitize_title');
    register_setting('onwatch_permalinks', 'onwatch_slug_cast', 'sanitize_title');
    register_setting('onwatch_permalinks', 'onwatch_slug_casttv', 'sanitize_title');
    register_setting('onwatch_permalinks', 'onwatch_slug_director', 'sanitize_title');
    register_setting('onwatch_permalinks', 'onwatch_slug_directortv', 'sanitize_title');
    register_setting('onwatch_permalinks', 'onwatch_slug_year', 'sanitize_title');

    add_settings_section('onwatch_permalinks_section', '', '__return_empty_string', 'permalink');
}

add_action('edit_form_after_title', 'onwatch_tmdb_field');
function onwatch_tmdb_field() {
    global $post, $pagenow;
    $type = tr_grabber_type($post->ID);
    if (!$type) return;

    $example = $type == 2
        ? 'https://www.themoviedb.org/tv/<strong>1418</strong>-the-big-bang-theory'
        : 'https://www.themoviedb.org/movie/<strong>284052</strong>-doctor-strange';
    ?>
    <div id="tmdb_grabber" class="tridfrm">
        <label for="trgrabber_id">
            <span>TMDB <span>#ID</span></span>
            <input name="trgrabber_id" id="trgrabber_id" type="text" value="">
            <button type="button" name="trgrabber_api" class="button tr_grabber_go"><span class="dashicons dashicons-yes"></span><?php _e('Go', 'onwatch'); ?></button>
        </label>
        <span class="ttp" style="display:inline">
            <span><span><?php _e('EXAMPLE', 'onwatch'); ?></span> <?php echo $example; ?></span>
            <span class="dashicons dashicons-warning"></span>
        </span>
    </div>
    <div id="tmdb_actions" style="display:none;margin:10px 0;flex-wrap:wrap;gap:8px">
        <button type="button" class="button button-primary" id="tmdb_import_all"><span class="dashicons dashicons-update"></span> <?php _e('Import All Seasons & Episodes', 'onwatch'); ?></button>
        <button type="button" class="button" id="tmdb_import_seasons"><span class="dashicons dashicons-screenoptions"></span> <?php _e('Import Seasons', 'onwatch'); ?></button>
        <button type="button" class="button" id="tmdb_import_episodes"><span class="dashicons dashicons-list-view"></span> <?php _e('Import Episodes', 'onwatch'); ?></button>
        <button type="button" class="button" id="tmdb_quick_links"><span class="dashicons dashicons-admin-links"></span> <?php _e('Quick Links', 'onwatch'); ?></button>
        <span id="tmdb_import_status" style="line-height:30px;color:#666"></span>
    </div>
    <?php
}

if (!function_exists('show_additional_information_meta_box')):
function show_additional_information_meta_box() {
    global $post;
    $type = tr_grabber_type($post->ID);
    $original = esc_textarea(get_post_meta($post->ID, TR_GRABBER_ORIGINAL_TITLE, true));
    $duration = get_post_meta($post->ID, TR_GRABBER_FIELD_RUNTIME, true);
    $duration = is_array($duration) ? implode(', ', $duration) : $duration;
    $release = get_post_meta($post->ID, TR_GRABBER_FIELD_DATE, true);
    $trailer = html_entity_decode(get_post_meta($post->ID, TR_GRABBER_FIELD_TRAILER, true));
    $poster_hotlink = get_post_meta($post->ID, TR_GRABBER_POSTER_HOTLINK, true);
    $backdrop_hotlink = get_post_meta($post->ID, TR_GRABBER_FIELD_BACKDROP_HOTLINK, true);
    $backdrop_id = get_post_meta($post->ID, TR_GRABBER_FIELD_BACKDROP, true);
    $rating = get_post_meta($post->ID, TR_GRABBER_FIELD_RATING, true);
    ?>
    <table class="form-table tr_grabber_content">
        <tbody>
            <tr>
                <th><label><span class="dashicons dashicons-format-aside"></span> <?php _e('Original Title', 'onwatch'); ?></label></th>
                <td><input type="text" name="original_title" value="<?php echo $original; ?>" class="regular-text" placeholder="<?php _e('Original Title', 'onwatch'); ?>"></td>
            </tr>
            <tr>
                <th><label><span class="dashicons dashicons-admin-links"></span> <?php _e('Poster Hotlink', 'onwatch'); ?></label></th>
                <td><input type="text" name="poster_hotlink" value="<?php echo esc_attr($poster_hotlink); ?>" class="regular-text" placeholder="/path/to/poster.jpg"></td>
            </tr>
            <tr>
                <th><label><span class="dashicons dashicons-admin-links"></span> <?php _e('Backdrop Hotlink', 'onwatch'); ?></label></th>
                <td>
                    <input type="text" name="backrop_hotlink" value="<?php echo esc_attr($backdrop_hotlink); ?>" class="regular-text" placeholder="/path/to/backdrop.jpg">
                    <input type="hidden" name="backdrop_id" id="trgrabber_backdrop_id" value="<?php echo esc_attr($backdrop_id); ?>">
                </td>
            </tr>
            <tr>
                <th><label><span class="dashicons dashicons-clock"></span> <?php _e('Duration', 'onwatch'); ?></label></th>
                <td><input type="text" name="duration" value="<?php echo esc_attr($duration); ?>" placeholder="2h 30m"></td>
            </tr>
            <tr>
                <th><label><span class="dashicons dashicons-star-filled"></span> <?php _e('Rating', 'onwatch'); ?></label></th>
                <td><input type="text" name="rating" value="<?php echo esc_attr($rating); ?>" placeholder="7.5"></td>
            </tr>
            <tr>
                <th><label><span class="dashicons dashicons-calendar-alt"></span> <?php _e('Release Date', 'onwatch'); ?></label></th>
                <td><input type="date" name="release_date" value="<?php echo esc_attr($release); ?>"></td>
            </tr>
            <?php if ($type == 2): ?>
            <tr>
                <th><label><?php _e('First Air Date', 'onwatch'); ?></label></th>
                <td><input type="date" name="first_air_date" value="<?php echo esc_attr($release); ?>"></td>
            </tr>
            <tr>
                <th><label><?php _e('Last Air Date', 'onwatch'); ?></label></th>
                <td><input type="date" name="last_air_date" value="<?php echo esc_attr(get_post_meta($post->ID, TR_GRABBER_FIELD_DATE_LAST, true)); ?>"></td>
            </tr>
            <tr>
                <th><label><?php _e('Status', 'onwatch'); ?></label></th>
                <td><input type="text" name="status" value="<?php echo esc_attr(get_post_meta($post->ID, TR_GRABBER_FIELD_STATUS, true)); ?>" placeholder="Returning Series"></td>
            </tr>
            <tr>
                <th><label><?php _e('In Production', 'onwatch'); ?></label></th>
                <td><input type="checkbox" name="in_production" value="1" <?php checked(get_post_meta($post->ID, TR_GRABBER_FIELD_INPRODUCTION, true), '1'); ?>></td>
            </tr>
            <?php endif; ?>
            <tr>
                <th><label><span class="dashicons dashicons-format-video"></span> <?php _e('Trailer', 'onwatch'); ?></label></th>
                <td><textarea name="trailer" rows="4" class="large-text" placeholder="<?php _e('Insert iframe embed code here', 'onwatch'); ?>"><?php echo esc_textarea($trailer); ?></textarea></td>
            </tr>
        </tbody>
    </table>
    <input type="hidden" name="tr_post_type" value="<?php echo $type; ?>">
    <?php
}
endif;

if (!function_exists('show_links_meta_box')):
function show_links_meta_box() {
    global $post;
    $total_links = get_post_meta($post->ID, 'trgrabber_tlinks', true);
    $exist = $total_links;
    $total_links = $total_links ? $total_links - 1 : 0;
    ?>
    <div class="links_options">
        <button id="trgrabber_addlink" type="button" class="button"><span class="dashicons dashicons-plus-alt"></span> <?php _e('Add link', 'onwatch'); ?></button>
        <button id="trgrabber_quiclinks" type="button" class="button"><span class="dashicons dashicons-plus-alt"></span> <?php _e('Quick Links', 'onwatch'); ?></button>
    </div>
    <div class="TrGrabber-tblcn">
        <table class="wp-list-table widefat fixed striped TrGrabber-tbl">
            <thead>
                <tr>
                    <th style="width:50px"><?php _e('Order', 'onwatch'); ?></th>
                    <th style="width:80px"><?php _e('Type', 'onwatch'); ?></th>
                    <th style="width:120px"><?php _e('Language', 'onwatch'); ?></th>
                    <th style="width:100px"><?php _e('Quality', 'onwatch'); ?></th>
                    <th><?php _e('Link', 'onwatch'); ?></th>
                    <th style="width:120px"><?php _e('Options', 'onwatch'); ?></th>
                </tr>
            </thead>
            <tbody id="tr-grabber-content-links">
                <?php for ($i = 0; $i <= $total_links; $i++):
                    $link = maybe_unserialize(get_post_meta($post->ID, 'trglinks_' . $i, true));
                    if (!$link && !$exist && $i > 0) continue;
                    $type = isset($link['type']) ? $link['type'] : 1;
                    $lang = isset($link['lang']) ? $link['lang'] : 0;
                    $quality = isset($link['quality']) ? $link['quality'] : 0;
                    $linkk = isset($link['link']) ? base64_decode($link['link']) : '';
                    $date = isset($link['date']) ? $link['date'] : '';
                ?>
                <tr class="tr-grabber-row">
                    <td class="moved"><span class="dashicons dashicons-sort"></span></td>
                    <td>
                        <button type="button" class="button trgrabberbt_a <?php echo $type == 1 ? 'current' : ''; ?>" data-id="1"><span class="dashicons dashicons-format-video"></span></button>
                        <button type="button" class="button trgrabberbt_b <?php echo $type == 2 ? 'current' : ''; ?>" data-id="2"><span class="dashicons dashicons-download"></span></button>
                        <input type="hidden" name="trgrabber_type[]" value="<?php echo $type; ?>">
                    </td>
                    <td>
                        <select name="trgrabber_lang[]">
                            <option value=""><?php _e('Select', 'onwatch'); ?></option>
                            <?php echo tr_grabber_select_taxonomy('language', $lang); ?>
                        </select>
                    </td>
                    <td>
                        <select name="trgrabber_quality[]">
                            <option value=""><?php _e('Select', 'onwatch'); ?></option>
                            <?php echo tr_grabber_select_taxonomy('quality', $quality); ?>
                        </select>
                    </td>
                    <td><input type="text" name="trgrabber_link[]" value="<?php echo esc_attr($linkk); ?>" class="regular-text" style="width:100%"></td>
                    <td class="tdoptns">
                        <input type="text" name="trgrabber_date[]" value="<?php echo esc_attr($date); ?>" placeholder="dd/mm/YYYY" style="width:100px">
                        <button type="button" class="button trgrabber_removelink"><span class="dashicons dashicons-dismiss"></span></button>
                    </td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>
    <?php
}
endif;

add_action('add_meta_boxes', 'onwatch_meta_boxes');
function onwatch_meta_boxes() {
    add_meta_box('tr_grabber_featured_meta_box', __('Backdrop', 'onwatch'), 'onwatch_backdrop_meta_box', array('movies', 'series'), 'side', 'low');
    add_meta_box('additional_information_meta_box', __('Additional Information', 'onwatch'), 'show_additional_information_meta_box', array('movies', 'series'), 'normal', 'high');
    add_meta_box('links_meta_box', __('Links', 'onwatch'), 'show_links_meta_box', array('movies'), 'normal', 'low');
    add_meta_box('onwatch_quick_links_box', __('Quick Links (Episodes)', 'onwatch'), 'onwatch_quick_links_meta_box', array('series'), 'side', 'low');
    add_meta_box('onwatch_manual_episodes_box', __('Manual Seasons & Episodes', 'onwatch'), 'onwatch_manual_episodes_meta_box', array('series'), 'normal', 'low');
    add_meta_box('onwatch_bulk_links_box', __('Bulk Link Adder', 'onwatch'), 'onwatch_bulk_links_meta_box', array('movies', 'series'), 'normal', 'low');
}

function onwatch_backdrop_meta_box($post) {
    $backdrop_id = get_post_meta($post->ID, TR_GRABBER_FIELD_BACKDROP, true);
    $image = $backdrop_id ? wp_get_attachment_image($backdrop_id, 'medium', false, array('style' => 'width:100%;height:auto;')) : __('Set backdrop image', 'onwatch');
    $hide = $backdrop_id ? '' : 'style="display:none"';
    ?>
    <p class="hide-if-no-js">
        <a href="#" class="onwatch-add-media" data-title="<?php _e('Backdrop', 'onwatch'); ?>" data-button="<?php _e('Use as backdrop', 'onwatch'); ?>" data-id="backdrop" data-postid="<?php echo $post->ID; ?>">
            <?php echo $image; ?>
        </a>
    </p>
    <p <?php echo $hide; ?>><a href="#" class="onwatch-media-delete"><?php _e('Remove backdrop', 'onwatch'); ?></a></p>
    <?php
}

add_action('save_post', 'onwatch_save_post_movies');
function onwatch_save_post_movies($post_id) {
    if (wp_is_post_revision($post_id)) $post_id = wp_is_post_revision($post_id);
    if (get_post_type($post_id) != 'movies') return;
    if (isset($_REQUEST['action']) && ($_REQUEST['action'] == 'trash' || $_REQUEST['action'] == 'untrash')) return;

    remove_action('save_post', 'onwatch_save_post_movies');

    $api_key = onwatch_get_legacy_api_key();
    $api_lang = onwatch_get_legacy_api_lang();
    $upload_images = onwatch_get_legacy_upload();

    if (isset($_POST['trgrabber_id']) && !empty($_POST['trgrabber_id']) && $api_key) {
        $tmdb_id = intval($_POST['trgrabber_id']);
        $grabber = json_decode(trgrabber_curl("https://api.themoviedb.org/3/movie/{$tmdb_id}?api_key={$api_key}&language={$api_lang}"), true);
        $credits = json_decode(trgrabber_curl("https://api.themoviedb.org/3/movie/{$tmdb_id}/credits?api_key={$api_key}&language={$api_lang}"), true);

        if ($grabber && isset($grabber['title'])) {
            $post_data = array('ID' => $post_id, 'post_status' => 'publish');
            if (isset($grabber['title'])) $post_data['post_title'] = $grabber['title'];
            if (isset($grabber['overview'])) $post_data['post_content'] = $grabber['overview'];
            wp_update_post($post_data);

            $meta = array(
                TR_GRABBER_ORIGINAL_TITLE => $grabber['original_title'] ?? '',
                TR_GRABBER_FIELD_ID       => $grabber['id'] ?? '',
                TR_GRABBER_FIELD_IMDBID   => $grabber['imdb_id'] ?? '',
                TR_GRABBER_FIELD_DATE     => $grabber['release_date'] ?? '',
                TR_GRABBER_FIELD_YEAR     => $grabber['release_date'] ?? '',
                TR_GRABBER_FIELD_RUNTIME  => isset($grabber['runtime']) ? $grabber['runtime'] . ' min' : '',
                TR_GRABBER_FIELD_RATING   => $grabber['vote_average'] ?? '',
                TR_GRABBER_POSTER_HOTLINK => $grabber['poster_path'] ?? '',
                TR_GRABBER_FIELD_BACKDROP_HOTLINK => $grabber['backdrop_path'] ?? '',
            );

            if (isset($grabber['release_date'])) {
                $year = explode('-', $grabber['release_date'])[0];
                wp_set_object_terms($post_id, $year, 'annee');
            }
            if (isset($grabber['genres'])) {
                wp_set_object_terms($post_id, array_column($grabber['genres'], 'name'), 'category');
            }
            if (isset($grabber['production_countries'])) {
                wp_set_object_terms($post_id, array_column($grabber['production_countries'], 'name'), 'country');
            }
            if (isset($credits['crew'])) {
                $directors = array();
                foreach ($credits['crew'] as $c) {
                    if ($c['department'] == 'Directing') $directors[] = $c['name'];
                }
                if ($directors) wp_set_object_terms($post_id, $directors, 'directors');
            }
            if (isset($credits['cast'])) {
                $cast_names = array();
                $cast_images = array();
                foreach ($credits['cast'] as $c) {
                    $cast_names[] = $c['name'];
                    $cast_images[] = $c['profile_path'] ?? '';
                }
                $term_ids = wp_set_object_terms($post_id, $cast_names, 'cast');
                if (!is_wp_error($term_ids)) {
                    foreach ($term_ids as $k => $tid) {
                        if (!empty($cast_images[$k])) {
                            update_term_meta($tid, 'image_hotlink', $cast_images[$k]);
                        }
                    }
                }
            }

            if ($upload_images) {
                $upload_dir = wp_upload_dir();
                if (!empty($grabber['poster_path'])) {
                    $poster_url = 'https://image.tmdb.org/t/p/w780' . $grabber['poster_path'];
                    onwatch_upload_from_url($poster_url, $post_id, 'poster');
                }
                if (!empty($grabber['backdrop_path'])) {
                    $backdrop_url = 'https://image.tmdb.org/t/p/original' . $grabber['backdrop_path'];
                    $attach_id = onwatch_upload_from_url($backdrop_url, $post_id, 'backdrop');
                    if ($attach_id) $meta[TR_GRABBER_FIELD_BACKDROP] = $attach_id;
                }
            }

            foreach ($meta as $key => $value) {
                update_post_meta($post_id, $key, $value);
            }
        }
    } else {
        $fields = array(
            TR_GRABBER_ORIGINAL_TITLE => 'original_title',
            TR_GRABBER_FIELD_TRAILER  => 'trailer',
            TR_GRABBER_FIELD_DATE     => 'release_date',
            TR_GRABBER_FIELD_YEAR     => 'release_date',
            TR_GRABBER_FIELD_RUNTIME  => 'duration',
            TR_GRABBER_FIELD_BACKDROP => 'backdrop_id',
            TR_GRABBER_FIELD_BACKDROP_HOTLINK => 'backrop_hotlink',
            TR_GRABBER_POSTER_HOTLINK => 'poster_hotlink',
            TR_GRABBER_FIELD_RATING   => 'rating',
        );
        foreach ($fields as $meta_key => $post_key) {
            if (isset($_POST[$post_key])) {
                update_post_meta($post_id, $meta_key, $_POST[$post_key]);
            }
        }
    }

    update_post_meta($post_id, 'tr_post_type', 1);

    if (isset($_POST['trgrabber_link']) && is_array($_POST['trgrabber_link'])) {
        $existing = intval(get_post_meta($post_id, 'trgrabber_tlinks', true));
        if ($existing > 0 && count(array_filter($_POST['trgrabber_link'])) < $existing) {
            for ($i = 0; $i <= $existing; $i++) delete_post_meta($post_id, 'trglinks_' . $i);
            delete_post_meta($post_id, 'trgrabber_tlinks');
        }

        $links = array_filter($_POST['trgrabber_link']);
        if (!empty($links)) {
            $i = 0;
            foreach ($links as $k => $link) {
                preg_match('@((https?://)?([-\w]+\.[-\w\.]+)+\w(:\d+)?(/([-/\w\.]*(\?\S+)?)?)*)@', $link, $a);
                $server_id = '';
                if (!empty($a[0])) {
                    $url = wp_parse_url(str_replace(array('https://www.', 'http://www.'), array('https://', 'http://'), $a[0]));
                    if (!empty($url['host'])) {
                        $parts = explode('.', $url['host']);
                        $term = term_exists(ucwords($parts[0]), 'server');
                        if ($term && $term !== 0 && $term !== null) {
                            $server_id = $term['term_id'];
                        } else {
                            $new = wp_insert_term(ucwords($parts[0]), 'server');
                            if (!is_wp_error($new)) $server_id = $new['term_id'];
                        }
                    }
                }

                $link_data = array(
                    'type'    => isset($_POST['trgrabber_type'][$k]) ? intval($_POST['trgrabber_type'][$k]) : 1,
                    'server'  => $server_id,
                    'lang'    => isset($_POST['trgrabber_lang'][$k]) ? intval($_POST['trgrabber_lang'][$k]) : '',
                    'quality' => isset($_POST['trgrabber_quality'][$k]) ? intval($_POST['trgrabber_quality'][$k]) : '',
                    'link'    => base64_encode(stripslashes(esc_textarea($link))),
                    'date'    => !empty($_POST['trgrabber_date'][$k]) ? $_POST['trgrabber_date'][$k] : date('d/m/Y'),
                );
                update_post_meta($post_id, 'trglinks_' . $i, serialize($link_data));
                $i++;
            }
            if ($i > 0) update_post_meta($post_id, 'trgrabber_tlinks', $i);
        }
    }

    add_action('save_post', 'onwatch_save_post_movies');
}

if (!function_exists('onwatch_bulk_links_meta_box')):
function onwatch_bulk_links_meta_box($post) {
    $type = tr_grabber_type($post->ID);
    $saved = $type > 0;
    $langs = get_terms(array('taxonomy' => 'language', 'hide_empty' => false));
    $quals = get_terms(array('taxonomy' => 'quality', 'hide_empty' => false));
    $post_type = get_post_type($post->ID);
    ?>
    <div class="onwatch-bulk-links">
        <?php if ($post_type == 'series'): ?>
        <p><strong><?php _e('Add links to multiple episodes at once.', 'onwatch'); ?></strong></p>
        <table class="form-table">
            <tr>
                <th><label for="bl_season"><?php _e('Season Number', 'onwatch'); ?></label></th>
                <td><input type="number" id="bl_season" class="small-text" value="1" min="0"></td>
            </tr>
            <tr>
                <th><label for="bl_ep_start"><?php _e('Episode Range (start - end)', 'onwatch'); ?></label></th>
                <td>
                    <input type="number" id="bl_ep_start" class="small-text" value="1" min="1" style="width:70px">
                    <span>—</span>
                    <input type="number" id="bl_ep_end" class="small-text" value="1" min="1" style="width:70px">
                    <p class="description"><?php _e('Each link will be assigned to one episode in sequence.', 'onwatch'); ?></p>
                </td>
            </tr>
        </table>
        <?php else: ?>
        <p><strong><?php _e('Add multiple watch links to this movie at once.', 'onwatch'); ?></strong></p>
        <input type="hidden" id="bl_ep_start" value="1">
        <input type="hidden" id="bl_ep_end" value="1">
        <input type="hidden" id="bl_season" value="0">
        <?php endif; ?>

        <table class="form-table">
            <tr>
                <th><label for="bl_type"><?php _e('Link Type', 'onwatch'); ?></label></th>
                <td>
                    <select id="bl_type">
                        <option value="1"><?php _e('Embed', 'onwatch'); ?></option>
                        <option value="2"><?php _e('Download', 'onwatch'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="bl_lang"><?php _e('Language', 'onwatch'); ?></label></th>
                <td>
                    <select id="bl_lang">
                        <option value="">— <?php _e('None', 'onwatch'); ?> —</option>
                        <?php foreach ($langs as $l): ?>
                        <option value="<?php echo $l->term_id; ?>"><?php echo esc_html($l->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="bl_quality"><?php _e('Quality', 'onwatch'); ?></label></th>
                <td>
                    <select id="bl_quality">
                        <option value="">— <?php _e('None', 'onwatch'); ?> —</option>
                        <?php foreach ($quals as $q): ?>
                        <option value="<?php echo $q->term_id; ?>"><?php echo esc_html($q->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="bl_links"><?php _e('Links (one per line)', 'onwatch'); ?></label></th>
                <td>
                    <textarea id="bl_links" rows="6" class="large-text" placeholder="https://example.com/embed/123&#10;https://example.com/embed/456"></textarea>
                    <p class="description"><?php _e('Paste one URL per line. Each is assigned to the next episode in the range.', 'onwatch'); ?></p>
                </td>
            </tr>
        </table>
        <p>
            <button type="button" class="button button-primary" id="onwatch_bl_go"><?php _e('Add Links', 'onwatch'); ?></button>
            <span id="onwatch_bl_status" style="margin-left:10px;color:#666;"></span>
        </p>
    </div>
    <script>
    jQuery('#onwatch_bl_go').on('click', function() {
        var links = jQuery('#bl_links').val();
        if (!links.trim()) { alert('<?php _e('Paste at least one link.', 'onwatch'); ?>'); return; }
        jQuery('#onwatch_bl_status').text('<?php _e('Processing...', 'onwatch'); ?>');
        jQuery.post(OnwatchAdmin.ajaxurl, {
            action: 'onwatch_bulk_add_links',
            nonce: OnwatchAdmin.manual_nonce,
            post_id: <?php echo $post->ID; ?>,
            post_type: '<?php echo $post_type; ?>',
            season: jQuery('#bl_season').val(),
            episode_start: jQuery('#bl_ep_start').val(),
            episode_end: jQuery('#bl_ep_end').val(),
            links: links,
            link_type: jQuery('#bl_type').val(),
            lang_id: jQuery('#bl_lang').val(),
            quality_id: jQuery('#bl_quality').val()
        }).done(function(r) {
            if (r.success) {
                jQuery('#onwatch_bl_status').text(r.data.message);
                jQuery('#bl_links').val('');
            } else {
                jQuery('#onwatch_bl_status').text(r.data);
            }
        }).fail(function() {
            jQuery('#onwatch_bl_status').text('<?php _e('Error', 'onwatch'); ?>');
        });
    });
    </script>
    <?php
}
endif;

if (!function_exists('onwatch_get_series_seasons_html')):
function onwatch_get_series_seasons_html($post_id) {
    $seasons = wp_get_object_terms($post_id, 'seasons', array('orderby' => 'term_group', 'order' => 'ASC'));
    if (is_wp_error($seasons) || empty($seasons)) return '<p class="description">' . __('No seasons yet. Add one above.', 'onwatch') . '</p>';
    $html = '<div class="onwatch-ms-list">';
    foreach ($seasons as $season) {
        $sn = get_term_meta($season->term_id, 'season_number', true);
        $sdate = get_term_meta($season->term_id, 'air_date', true);
        $episodes = get_terms(array(
            'taxonomy' => 'episodes', 'hide_empty' => false,
            'meta_query' => array(
                array('key' => 'season_number', 'value' => $sn),
                array('key' => 'tr_id_post', 'value' => $post_id),
            ),
            'orderby' => 'meta_value_num', 'meta_key' => 'episode_number', 'order' => 'ASC',
        ));
        $html .= '<div class="onwatch-ms-season">';
        $html .= '<div class="onwatch-ms-season-title">';
        $html .= '<strong>' . esc_html($season->name) . '</strong>';
        if ($sdate) $html .= ' <span class="description">(' . esc_html($sdate) . ')</span>';
        $html .= ' <button type="button" class="button button-small onwatch-ms-delete" data-id="' . $season->term_id . '" data-tax="seasons" style="color:#a00;margin-left:4px;">' . __('Delete', 'onwatch') . '</button>';
        $html .= '</div>';
        if (!empty($episodes) && !is_wp_error($episodes)) {
            $html .= '<ul class="onwatch-ms-episodes">';
            foreach ($episodes as $ep) {
                $en = get_term_meta($ep->term_id, 'episode_number', true);
                $e_date = get_term_meta($ep->term_id, 'air_date', true);
                $html .= '<li>';
                $html .= '<span class="onwatch-ms-ep-num">' . sprintf(__('Ep %s', 'onwatch'), $en) . '</span>';
                $html .= ' <span class="onwatch-ms-ep-name">' . esc_html($ep->name) . '</span>';
                if ($e_date) $html .= ' <span class="description">(' . esc_html($e_date) . ')</span>';
                $html .= ' <button type="button" class="button button-small onwatch-ms-delete" data-id="' . $ep->term_id . '" data-tax="episodes" style="color:#a00;margin-left:4px;">' . __('Delete', 'onwatch') . '</button>';
                $html .= '</li>';
            }
            $html .= '</ul>';
        } else {
            $html .= '<p class="description" style="margin:4px 0 8px 20px;">' . __('No episodes.', 'onwatch') . '</p>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}
endif;

if (!function_exists('onwatch_manual_episodes_meta_box')):
function onwatch_manual_episodes_meta_box($post) {
    $type = tr_grabber_type($post->ID);
    $saved = $type > 0;
    ?>
    <div class="onwatch-manual-episodes">
        <?php if (!$saved): ?>
        <p><em><?php _e('Save the post first, then add seasons and episodes.', 'onwatch'); ?></em></p>
        <?php return; endif; ?>

        <div class="onwatch-me-section">
            <h4><?php _e('Add Season', 'onwatch'); ?></h4>
            <table class="form-table">
                <tr>
                    <th><label for="me_season_number"><?php _e('Season Number', 'onwatch'); ?></label></th>
                    <td><input type="number" id="me_season_number" class="small-text" value="1" min="1"></td>
                </tr>
                <tr>
                    <th><label for="me_season_name"><?php _e('Season Name', 'onwatch'); ?></label></th>
                    <td><input type="text" id="me_season_name" class="regular-text" placeholder="<?php esc_attr_e('e.g., Season 1', 'onwatch'); ?>"></td>
                </tr>
                <tr>
                    <th><label for="me_season_date"><?php _e('Air Date', 'onwatch'); ?></label></th>
                    <td><input type="date" id="me_season_date" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="me_season_overview"><?php _e('Overview', 'onwatch'); ?></label></th>
                    <td><textarea id="me_season_overview" rows="3" class="large-text"></textarea></td>
                </tr>
            </table>
            <p>
                <button type="button" class="button button-primary" id="onwatch_ms_add_season"><?php _e('Add Season', 'onwatch'); ?></button>
                <span id="onwatch_ms_season_status" style="margin-left:10px;color:#666;"></span>
            </p>
        </div>

        <hr>

        <div class="onwatch-me-section">
            <h4><?php _e('Add Episode', 'onwatch'); ?></h4>
            <table class="form-table">
                <tr>
                    <th><label for="me_ep_season"><?php _e('Season', 'onwatch'); ?></label></th>
                    <td>
                        <select id="me_ep_season">
                            <option value=""><?php _e('— Select Season —', 'onwatch'); ?></option>
                            <?php
                            $seasons = wp_get_object_terms($post->ID, 'seasons', array('orderby' => 'term_group', 'order' => 'ASC'));
                            if (!is_wp_error($seasons)) {
                                foreach ($seasons as $s) {
                                    $sn = get_term_meta($s->term_id, 'season_number', true);
                                    echo '<option value="' . $s->term_id . '" data-sn="' . esc_attr($sn) . '">' . esc_html($sn ? sprintf(__('Season %s', 'onwatch'), $sn) : $s->name) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="me_ep_number"><?php _e('Episode Number', 'onwatch'); ?></label></th>
                    <td><input type="number" id="me_ep_number" class="small-text" value="1" min="1"></td>
                </tr>
                <tr>
                    <th><label for="me_ep_name"><?php _e('Episode Name', 'onwatch'); ?></label></th>
                    <td><input type="text" id="me_ep_name" class="regular-text" placeholder="<?php esc_attr_e('e.g., Pilot', 'onwatch'); ?>"></td>
                </tr>
                <tr>
                    <th><label for="me_ep_date"><?php _e('Air Date', 'onwatch'); ?></label></th>
                    <td><input type="date" id="me_ep_date" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="me_ep_still"><?php _e('Still Image URL', 'onwatch'); ?></label></th>
                    <td><input type="url" id="me_ep_still" class="regular-text" placeholder="https://image.tmdb.org/..."></td>
                </tr>
                <tr>
                    <th><label for="me_ep_overview"><?php _e('Overview', 'onwatch'); ?></label></th>
                    <td><textarea id="me_ep_overview" rows="3" class="large-text"></textarea></td>
                </tr>
            </table>
            <p>
                <button type="button" class="button button-primary" id="onwatch_ms_add_episode"><?php _e('Add Episode', 'onwatch'); ?></button>
                <span id="onwatch_ms_episode_status" style="margin-left:10px;color:#666;"></span>
            </p>
        </div>

        <hr>

        <div class="onwatch-me-section">
            <h4><?php _e('Seasons &amp; Episodes', 'onwatch'); ?></h4>
            <div id="onwatch_ms_list"><?php echo onwatch_get_series_seasons_html($post->ID); ?></div>
        </div>
    </div>
    <?php
}
endif;

if (!function_exists('onwatch_quick_links_meta_box')):
function onwatch_quick_links_meta_box($post) {
    $type = tr_grabber_type($post->ID);
    if ($type != 2) { echo '<p>' . __('Save the post first to use Quick Links.', 'onwatch') . '</p>'; return; }
    $tmdb_id = get_post_meta($post->ID, TR_GRABBER_FIELD_ID, true);
    if (!$tmdb_id) { echo '<p>' . __('Import from TMDB first.', 'onwatch') . '</p>'; return; }
    ?>
    <p><?php _e('Add watch links to episodes directly.', 'onwatch'); ?></p>
    <p>
        <label><?php _e('Season number:', 'onwatch'); ?><br>
        <input type="number" id="ql_season" class="widefat" value="1" min="0"></label>
    </p>
    <p>
        <label><?php _e('Starting episode:', 'onwatch'); ?><br>
        <input type="number" id="ql_episode" class="widefat" value="1" min="1"></label>
    </p>
    <p>
        <label><?php _e('Links (one per line):', 'onwatch'); ?><br>
        <textarea id="ql_links" class="widefat" rows="5" placeholder="https://example.com/embed/..."></textarea></label>
    </p>
    <p>
        <label><?php _e('Link type:', 'onwatch'); ?><br>
        <select id="ql_type" class="widefat">
            <option value="1"><?php _e('Embed', 'onwatch'); ?></option>
            <option value="2"><?php _e('Download', 'onwatch'); ?></option>
        </select></label>
    </p>
    <button type="button" class="button button-primary" id="onwatch_ql_go"><?php _e('Add Quick Links', 'onwatch'); ?></button>
    <span id="onwatch_ql_status" style="display:inline-block;margin-left:8px;color:#666"></span>
    <script>
    jQuery('#onwatch_ql_go').on('click', function() {
        var sn = jQuery('#ql_season').val();
        var en = jQuery('#ql_episode').val();
        var ls = jQuery('#ql_links').val();
        var tt = jQuery('#ql_type').val();
        if (!sn || !en) { alert('<?php _e('Fill all fields', 'onwatch'); ?>'); return; }
        if (!ls) { alert('<?php _e('Paste at least one link', 'onwatch'); ?>'); return; }
        jQuery('#onwatch_ql_status').text('<?php _e('Loading...', 'onwatch'); ?>');
        jQuery.post(OnwatchAdmin.ajaxurl, {
            action: 'trgrabberlive',
            nonce: OnwatchAdmin.live_nonce,
            type: 6, season: sn, episode: en, links: ls, typel: tt,
            id: OnwatchAdmin.post_id,
            lang: 0, quality: 0
        }).done(function() {
            jQuery('#onwatch_ql_status').text('<?php _e('Done!', 'onwatch'); ?>');
        }).fail(function() {
            jQuery('#onwatch_ql_status').text('<?php _e('Error', 'onwatch'); ?>');
        });
    });
    </script>
    <?php
}
endif;

add_action('save_post', 'onwatch_save_post_series');
function onwatch_save_post_series($post_id) {
    if (wp_is_post_revision($post_id)) $post_id = wp_is_post_revision($post_id);
    if (get_post_type($post_id) != 'series') return;
    if (isset($_REQUEST['action']) && ($_REQUEST['action'] == 'trash' || $_REQUEST['action'] == 'untrash')) return;

    remove_action('save_post', 'onwatch_save_post_series');

    $api_key = onwatch_get_legacy_api_key();
    $api_lang = onwatch_get_legacy_api_lang();
    $upload_images = onwatch_get_legacy_upload();

    if (isset($_POST['trgrabber_id']) && !empty($_POST['trgrabber_id']) && $api_key) {
        $tmdb_id = intval($_POST['trgrabber_id']);
        $grabber = json_decode(trgrabber_curl("https://api.themoviedb.org/3/tv/{$tmdb_id}?api_key={$api_key}&language={$api_lang}"), true);
        $videos = json_decode(trgrabber_curl("https://api.themoviedb.org/3/tv/{$tmdb_id}/videos?api_key={$api_key}&language={$api_lang}"), true);
        $credits = json_decode(trgrabber_curl("https://api.themoviedb.org/3/tv/{$tmdb_id}/credits?api_key={$api_key}&language={$api_lang}"), true);

        if ($grabber && isset($grabber['name'])) {
            $post_data = array('ID' => $post_id, 'post_status' => 'publish');
            if (isset($grabber['name'])) $post_data['post_title'] = $grabber['name'];
            if (isset($grabber['overview'])) $post_data['post_content'] = $grabber['overview'];
            wp_update_post($post_data);

            $trailer = '';
            if (!empty($videos['results'][0]['key']) && $videos['results'][0]['site'] == 'YouTube') {
                $trailer = '<iframe width="560" height="315" src="https://www.youtube.com/embed/' . $videos['results'][0]['key'] . '" frameborder="0" allowfullscreen></iframe>';
            }

            $meta = array(
                TR_GRABBER_ORIGINAL_TITLE     => $grabber['original_name'] ?? '',
                TR_GRABBER_FIELD_TRAILER      => $trailer,
                TR_GRABBER_FIELD_ID           => $grabber['id'] ?? '',
                TR_GRABBER_FIELD_IMDBID       => $grabber['imdb_id'] ?? '',
                TR_GRABBER_FIELD_INPRODUCTION => $grabber['in_production'] ?? '',
                TR_GRABBER_FIELD_STATUS       => $grabber['status'] ?? '',
                TR_GRABBER_POSTER_HOTLINK     => $grabber['poster_path'] ?? '',
                TR_GRABBER_FIELD_BACKDROP_HOTLINK => $grabber['backdrop_path'] ?? '',
                TR_GRABBER_FIELD_DATE         => $grabber['first_air_date'] ?? '',
                TR_GRABBER_FIELD_DATE_LAST    => $grabber['last_air_date'] ?? '',
                TR_GRABBER_FIELD_RUNTIME      => isset($grabber['episode_run_time'][0]) ? $grabber['episode_run_time'][0] : '',
                TR_GRABBER_FIELD_NEPISODES    => 0,
                TR_GRABBER_FIELD_NSEASONS     => 0,
                TR_GRABBER_FIELD_RATING       => $grabber['vote_average'] ?? '',
            );

            if (isset($grabber['first_air_date'])) {
                $year = explode('-', $grabber['first_air_date'])[0];
                wp_set_object_terms($post_id, $year, 'annee');
            }
            if (isset($grabber['genres'])) {
                wp_set_object_terms($post_id, array_column($grabber['genres'], 'name'), 'category');
            }
            if (isset($grabber['created_by'])) {
                wp_set_object_terms($post_id, array_column($grabber['created_by'], 'name'), 'directors_tv');
            }
            if (isset($credits['cast'])) {
                $cast_names = array();
                $cast_images = array();
                foreach ($credits['cast'] as $c) {
                    $cast_names[] = $c['name'];
                    $cast_images[] = $c['profile_path'] ?? '';
                }
                $term_ids = wp_set_object_terms($post_id, $cast_names, 'cast_tv');
                if (!is_wp_error($term_ids)) {
                    foreach ($term_ids as $k => $tid) {
                        if (!empty($cast_images[$k])) update_term_meta($tid, 'image_hotlink', $cast_images[$k]);
                    }
                }
            }

            if ($upload_images) {
                if (!empty($grabber['poster_path'])) {
                    onwatch_upload_from_url('https://image.tmdb.org/t/p/w780' . $grabber['poster_path'], $post_id, 'poster');
                }
                if (!empty($grabber['backdrop_path'])) {
                    $attach_id = onwatch_upload_from_url('https://image.tmdb.org/t/p/original' . $grabber['backdrop_path'], $post_id, 'backdrop');
                    if ($attach_id) $meta[TR_GRABBER_FIELD_BACKDROP] = $attach_id;
                }
            }

            foreach ($meta as $key => $value) {
                update_post_meta($post_id, $key, $value);
            }
        }
    } else {
        $fields = array(
            TR_GRABBER_ORIGINAL_TITLE     => 'original_title',
            TR_GRABBER_FIELD_TRAILER      => 'trailer',
            TR_GRABBER_FIELD_INPRODUCTION => 'in_production',
            TR_GRABBER_FIELD_STATUS       => 'status',
            TR_GRABBER_FIELD_BACKDROP_HOTLINK => 'backrop_hotlink',
            TR_GRABBER_POSTER_HOTLINK     => 'poster_hotlink',
            TR_GRABBER_FIELD_RUNTIME      => 'duration',
            TR_GRABBER_FIELD_DATE         => 'first_air_date',
            TR_GRABBER_FIELD_DATE_LAST    => 'last_air_date',
            TR_GRABBER_FIELD_RATING       => 'rating',
        );
        foreach ($fields as $meta_key => $post_key) {
            if (isset($_POST[$post_key])) {
                $val = $_POST[$post_key];
                if ($post_key == 'duration' && is_array($val)) $val = implode(', ', $val);
                update_post_meta($post_id, $meta_key, $val);
            }
        }
    }

    update_post_meta($post_id, 'tr_post_type', 2);

    if (isset($_POST['trgrabber_link']) && is_array($_POST['trgrabber_link'])) {
        $existing = intval(get_post_meta($post_id, 'trgrabber_tlinks', true));
        if ($existing > 0 && count(array_filter($_POST['trgrabber_link'])) < $existing) {
            for ($i = 0; $i <= $existing; $i++) delete_post_meta($post_id, 'trglinks_' . $i);
            delete_post_meta($post_id, 'trgrabber_tlinks');
        }

        $links = array_filter($_POST['trgrabber_link']);
        if (!empty($links)) {
            $i = 0;
            foreach ($links as $k => $link) {
                preg_match('@((https?://)?([-\w]+\.[-\w\.]+)+\w(:\d+)?(/([-/\w\.]*(\?\S+)?)*)?)@', $link, $a);
                $server_id = '';
                if (!empty($a[0])) {
                    $url = wp_parse_url(str_replace(array('https://www.', 'http://www.'), array('https://', 'http://'), $a[0]));
                    if (!empty($url['host'])) {
                        $parts = explode('.', $url['host']);
                        $term = term_exists(ucwords($parts[0]), 'server');
                        if ($term && $term !== 0 && $term !== null) {
                            $server_id = $term['term_id'];
                        } else {
                            $new = wp_insert_term(ucwords($parts[0]), 'server');
                            if (!is_wp_error($new)) $server_id = $new['term_id'];
                        }
                    }
                }

                $link_data = array(
                    'type'    => isset($_POST['trgrabber_type'][$k]) ? intval($_POST['trgrabber_type'][$k]) : 1,
                    'server'  => $server_id,
                    'lang'    => isset($_POST['trgrabber_lang'][$k]) ? intval($_POST['trgrabber_lang'][$k]) : '',
                    'quality' => isset($_POST['trgrabber_quality'][$k]) ? intval($_POST['trgrabber_quality'][$k]) : '',
                    'link'    => base64_encode(stripslashes(esc_textarea($link))),
                    'date'    => !empty($_POST['trgrabber_date'][$k]) ? $_POST['trgrabber_date'][$k] : date('d/m/Y'),
                );
                update_post_meta($post_id, 'trglinks_' . $i, serialize($link_data));
                $i++;
            }
            if ($i > 0) update_post_meta($post_id, 'trgrabber_tlinks', $i);
        }
    }

    add_action('save_post', 'onwatch_save_post_series');
}

function onwatch_upload_from_url($url, $post_id, $type = 'poster') {
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $tmp = download_url($url);
    if (is_wp_error($tmp)) return false;

    $filename = sanitize_title(get_the_title($post_id)) . '-' . $post_id . '-' . $type . '.jpg';
    $file = array(
        'name'     => $filename,
        'type'     => 'image/jpeg',
        'tmp_name' => $tmp,
        'error'    => 0,
        'size'     => filesize($tmp),
    );

    $attach_id = media_handle_sideload($file, $post_id);
    if (is_wp_error($attach_id)) {
        @unlink($tmp);
        return false;
    }

    if ($type == 'poster') set_post_thumbnail($post_id, $attach_id);
    return $attach_id;
}

add_action('admin_footer', 'onwatch_series_lightbox');
function onwatch_series_lightbox() {
    $type = tr_grabber_type();
    if ($type != 2) return;
    ?>
    <div id="trgrabber_seasons_lg" style="display:none;">
        <div class="lgtbxcn" style="max-width:180px;margin:auto;padding:20px;background:#fff;">
            <div id="grabber_iframe"></div>
        </div>
    </div>
    <?php
}

add_action('admin_footer-edit.php', 'onwatch_admin_nav');
add_action('admin_footer-post.php', 'onwatch_admin_nav');
add_action('admin_footer-post-new.php', 'onwatch_admin_nav');
add_action('admin_footer-edit-tags.php', 'onwatch_admin_nav');
add_action('admin_footer-term.php', 'onwatch_admin_nav');
function onwatch_admin_nav() {
    $type = tr_grabber_type();
    if (!$type) return;
    ?>
    <ul class="subsubsub onwatch-admin-nav" id="onwatch-admin-nav">
        <li><a href="<?php echo admin_url('edit.php?post_type=' . ($type == 1 ? 'movies' : 'series')); ?>"><?php _e('All', 'onwatch'); ?></a> |</li>
        <li><a href="<?php echo admin_url('post-new.php?post_type=' . ($type == 1 ? 'movies' : 'series')); ?>"><?php _e('Add New', 'onwatch'); ?></a> |</li>
        <?php if ($type == 1): ?>
        <li><a href="<?php echo admin_url('edit-tags.php?taxonomy=directors&post_type=movies'); ?>"><?php _e('Directors', 'onwatch'); ?></a> |</li>
        <li><a href="<?php echo admin_url('edit-tags.php?taxonomy=cast&post_type=movies'); ?>"><?php _e('Cast', 'onwatch'); ?></a> |</li>
        <li><a href="<?php echo admin_url('edit-tags.php?taxonomy=country&post_type=movies'); ?>"><?php _e('Countries', 'onwatch'); ?></a> |</li>
        <?php else: ?>
        <li><a href="<?php echo admin_url('edit-tags.php?taxonomy=directors_tv&post_type=series'); ?>"><?php _e('Directors', 'onwatch'); ?></a> |</li>
        <li><a href="<?php echo admin_url('edit-tags.php?taxonomy=cast_tv&post_type=series'); ?>"><?php _e('Cast', 'onwatch'); ?></a> |</li>
        <?php endif; ?>
        <li><a href="<?php echo admin_url('edit-tags.php?taxonomy=server'); ?>"><?php _e('Servers', 'onwatch'); ?></a> |</li>
        <li><a href="<?php echo admin_url('edit-tags.php?taxonomy=language'); ?>"><?php _e('Languages', 'onwatch'); ?></a> |</li>
        <li><a href="<?php echo admin_url('edit-tags.php?taxonomy=quality'); ?>"><?php _e('Quality', 'onwatch'); ?></a> |</li>
        <li><a href="<?php echo admin_url('edit-tags.php?taxonomy=category'); ?>"><?php _e('Categories', 'onwatch'); ?></a></li>
        <?php if ($type == 2): ?>
        <li> | <a href="<?php echo admin_url('edit-tags.php?taxonomy=seasons'); ?>"><?php _e('Seasons', 'onwatch'); ?></a> |</li>
        <li><a href="<?php echo admin_url('edit-tags.php?taxonomy=episodes'); ?>"><?php _e('Episodes', 'onwatch'); ?></a></li>
        <?php endif; ?>
    </ul>
    <style>.onwatch-admin-nav { margin-top: 10px; } .onwatch-admin-nav li { display: inline; }</style>
    <?php
}

add_filter('manage_movies_posts_columns', 'onwatch_admin_columns');
add_filter('manage_series_posts_columns', 'onwatch_admin_columns');
function onwatch_admin_columns($columns) {
    unset($columns['tags'], $columns['author'], $columns['categories'], $columns['comments'], $columns['date']);
    $columns['onwatch_thumb'] = __('Poster', 'onwatch');
    $columns['onwatch_year'] = __('Year', 'onwatch');
    $columns['onwatch_rating'] = __('Rating', 'onwatch');
    $columns['onwatch_options'] = __('Actions', 'onwatch');
    return $columns;
}

add_action('manage_movies_posts_custom_column', 'onwatch_admin_column_content', 10, 2);
add_action('manage_series_posts_custom_column', 'onwatch_admin_column_content', 10, 2);
function onwatch_admin_column_content($column, $post_id) {
    if ($column == 'onwatch_thumb') {
        $img = onwatch_get_poster($post_id, 'w92');
        if ($img) echo '<img src="' . esc_url($img) . '" width="50" height="75" style="object-fit:cover;border-radius:4px" alt="">';
        else echo '<span style="color:#999">—</span>';
    }
    if ($column == 'onwatch_year') {
        echo esc_html(onwatch_get_year($post_id));
    }
    if ($column == 'onwatch_rating') {
        $r = onwatch_get_rating($post_id);
        if ($r) echo '<span style="color:#f5c518">★</span> ' . $r;
        else echo '—';
    }
    if ($column == 'onwatch_options') {
        echo '<a href="' . get_edit_post_link($post_id) . '" class="button button-small" style="margin-right:4px"><span class="dashicons dashicons-edit"></span> ' . __('Edit', 'onwatch') . '</a>';
        echo '<a href="' . get_delete_post_link($post_id) . '" class="button button-small" onclick="return confirm(\'' . __('Are you sure?', 'onwatch') . '\')"><span class="dashicons dashicons-trash"></span> ' . __('Delete', 'onwatch') . '</a>';
    }
}

add_action('episodes_add_form_fields', 'onwatch_episode_meta_fields_add');
add_action('episodes_edit_form_fields', 'onwatch_episode_meta_fields_edit', 10, 2);
add_action('created_episodes', 'onwatch_save_episode_meta');
add_action('edited_episodes', 'onwatch_save_episode_meta');

function onwatch_episode_meta_fields_add() {
    ?>
    <div class="form-field">
        <label for="episode_number"><?php _e('Episode Number', 'onwatch'); ?></label>
        <input type="number" name="episode_number" id="episode_number" value="">
    </div>
    <div class="form-field">
        <label for="season_number"><?php _e('Season Number', 'onwatch'); ?></label>
        <input type="number" name="season_number" id="season_number" value="">
    </div>
    <div class="form-field">
        <label for="tr_id_post"><?php _e('Series Post ID', 'onwatch'); ?></label>
        <input type="number" name="tr_id_post" id="tr_id_post" value="">
    </div>
    <div class="form-field">
        <label for="air_date"><?php _e('Air Date', 'onwatch'); ?></label>
        <input type="date" name="air_date" id="air_date" value="">
    </div>
    <div class="form-field">
        <label for="still_path_hotlink"><?php _e('Still Image Hotlink', 'onwatch'); ?></label>
        <input type="text" name="still_path_hotlink" id="still_path_hotlink" value="">
    </div>
    <?php
}

function onwatch_episode_meta_fields_edit($term) {
    $ep_num = get_term_meta($term->term_id, 'episode_number', true);
    $sea_num = get_term_meta($term->term_id, 'season_number', true);
    $pid = get_term_meta($term->term_id, 'tr_id_post', true);
    $air = get_term_meta($term->term_id, 'air_date', true);
    $still = get_term_meta($term->term_id, 'still_path_hotlink', true);
    ?>
    <tr class="form-field">
        <th><label for="episode_number"><?php _e('Episode Number', 'onwatch'); ?></label></th>
        <td><input type="number" name="episode_number" value="<?php echo esc_attr($ep_num); ?>"></td>
    </tr>
    <tr class="form-field">
        <th><label for="season_number"><?php _e('Season Number', 'onwatch'); ?></label></th>
        <td><input type="number" name="season_number" value="<?php echo esc_attr($sea_num); ?>"></td>
    </tr>
    <tr class="form-field">
        <th><label for="tr_id_post"><?php _e('Series Post ID', 'onwatch'); ?></label></th>
        <td><input type="number" name="tr_id_post" value="<?php echo esc_attr($pid); ?>"></td>
    </tr>
    <tr class="form-field">
        <th><label for="air_date"><?php _e('Air Date', 'onwatch'); ?></label></th>
        <td><input type="date" name="air_date" value="<?php echo esc_attr($air); ?>"></td>
    </tr>
    <tr class="form-field">
        <th><label for="still_path_hotlink"><?php _e('Still Image Hotlink', 'onwatch'); ?></label></th>
        <td><input type="text" name="still_path_hotlink" value="<?php echo esc_attr($still); ?>" class="regular-text"></td>
    </tr>
    <?php
}

function onwatch_save_episode_meta($term_id) {
    foreach (array('episode_number', 'season_number', 'tr_id_post', 'air_date', 'still_path_hotlink') as $key) {
        if (isset($_POST[$key])) {
            update_term_meta($term_id, $key, sanitize_text_field($_POST[$key]));
        }
    }
}

add_action('seasons_add_form_fields', 'onwatch_season_meta_fields_add');
add_action('seasons_edit_form_fields', 'onwatch_season_meta_fields_edit', 10, 2);
add_action('created_seasons', 'onwatch_save_season_meta');
add_action('edited_seasons', 'onwatch_save_season_meta');

function onwatch_season_meta_fields_add() {
    ?>
    <div class="form-field">
        <label for="season_number"><?php _e('Season Number', 'onwatch'); ?></label>
        <input type="number" name="season_number" id="season_number" value="">
    </div>
    <div class="form-field">
        <label for="tr_id_post"><?php _e('Series Post ID', 'onwatch'); ?></label>
        <input type="number" name="tr_id_post" id="tr_id_post" value="">
    </div>
    <div class="form-field">
        <label for="poster_path_hotlink"><?php _e('Poster Hotlink', 'onwatch'); ?></label>
        <input type="text" name="poster_path_hotlink" id="poster_path_hotlink" value="">
    </div>
    <?php
}

function onwatch_season_meta_fields_edit($term) {
    $sea_num = get_term_meta($term->term_id, 'season_number', true);
    $pid = get_term_meta($term->term_id, 'tr_id_post', true);
    $poster = get_term_meta($term->term_id, 'poster_path_hotlink', true);
    ?>
    <tr class="form-field">
        <th><label for="season_number"><?php _e('Season Number', 'onwatch'); ?></label></th>
        <td><input type="number" name="season_number" value="<?php echo esc_attr($sea_num); ?>"></td>
    </tr>
    <tr class="form-field">
        <th><label for="tr_id_post"><?php _e('Series Post ID', 'onwatch'); ?></label></th>
        <td><input type="number" name="tr_id_post" value="<?php echo esc_attr($pid); ?>"></td>
    </tr>
    <tr class="form-field">
        <th><label for="poster_path_hotlink"><?php _e('Poster Hotlink', 'onwatch'); ?></label></th>
        <td><input type="text" name="poster_path_hotlink" value="<?php echo esc_attr($poster); ?>" class="regular-text"></td>
    </tr>
    <?php
}

function onwatch_save_season_meta($term_id) {
    foreach (array('season_number', 'tr_id_post', 'poster_path_hotlink') as $key) {
        if (isset($_POST[$key])) {
            update_term_meta($term_id, $key, sanitize_text_field($_POST[$key]));
        }
    }
}

add_action('cast_add_form_fields', 'onwatch_cast_meta_fields_add');
add_action('cast_edit_form_fields', 'onwatch_cast_meta_fields_edit', 10, 2);
add_action('created_cast', 'onwatch_save_cast_meta');
add_action('edited_cast', 'onwatch_save_cast_meta');
add_action('cast_tv_add_form_fields', 'onwatch_cast_meta_fields_add');
add_action('cast_tv_edit_form_fields', 'onwatch_cast_meta_fields_edit', 10, 2);
add_action('created_cast_tv', 'onwatch_save_cast_meta');
add_action('edited_cast_tv', 'onwatch_save_cast_meta');

function onwatch_cast_meta_fields_add() {
    ?>
    <div class="form-field">
        <label for="image_hotlink"><?php _e('Profile Image Hotlink', 'onwatch'); ?></label>
        <input type="text" name="image_hotlink" id="image_hotlink" value="">
    </div>
    <?php
}

function onwatch_cast_meta_fields_edit($term) {
    $img = get_term_meta($term->term_id, 'image_hotlink', true);
    ?>
    <tr class="form-field">
        <th><label for="image_hotlink"><?php _e('Profile Image Hotlink', 'onwatch'); ?></label></th>
        <td><input type="text" name="image_hotlink" value="<?php echo esc_attr($img); ?>" class="regular-text"></td>
    </tr>
    <?php
}

function onwatch_save_cast_meta($term_id) {
    if (isset($_POST['image_hotlink'])) {
        update_term_meta($term_id, 'image_hotlink', sanitize_text_field($_POST['image_hotlink']));
    }
}

add_action('server_add_form_fields', 'onwatch_server_meta_fields_add');
add_action('server_edit_form_fields', 'onwatch_server_meta_fields_edit', 10, 2);
add_action('created_server', 'onwatch_save_server_meta');
add_action('edited_server', 'onwatch_save_server_meta');

function onwatch_server_meta_fields_add() {
    ?>
    <div class="form-field">
        <label for="server_type"><?php _e('Type', 'onwatch'); ?></label>
        <select name="server_type" id="server_type">
            <option value="1"><?php _e('Online', 'onwatch'); ?></option>
            <option value="2"><?php _e('Download', 'onwatch'); ?></option>
        </select>
    </div>
    <div class="form-field">
        <label for="server_image"><?php _e('Icon Hotlink', 'onwatch'); ?></label>
        <input type="text" name="server_image" id="server_image" value="">
    </div>
    <?php
}

function onwatch_server_meta_fields_edit($term) {
    $type = get_term_meta($term->term_id, 'type', true);
    $img = get_term_meta($term->term_id, 'image_hotlink', true);
    ?>
    <tr class="form-field">
        <th><label for="server_type"><?php _e('Type', 'onwatch'); ?></label></th>
        <td>
            <select name="server_type">
                <option value="1" <?php selected($type, '1'); ?>><?php _e('Online', 'onwatch'); ?></option>
                <option value="2" <?php selected($type, '2'); ?>><?php _e('Download', 'onwatch'); ?></option>
            </select>
        </td>
    </tr>
    <tr class="form-field">
        <th><label for="server_image"><?php _e('Icon Hotlink', 'onwatch'); ?></label></th>
        <td><input type="text" name="server_image" value="<?php echo esc_attr($img); ?>" class="regular-text"></td>
    </tr>
    <?php
}

function onwatch_save_server_meta($term_id) {
    if (isset($_POST['server_type'])) update_term_meta($term_id, 'type', intval($_POST['server_type']));
    if (isset($_POST['server_image'])) update_term_meta($term_id, 'image_hotlink', sanitize_text_field($_POST['server_image']));
}

add_action('language_add_form_fields', 'onwatch_language_meta_fields_add');
add_action('language_edit_form_fields', 'onwatch_language_meta_fields_edit', 10, 2);
add_action('created_language', 'onwatch_save_language_meta');
add_action('edited_language', 'onwatch_save_language_meta');

function onwatch_language_meta_fields_add() {
    ?>
    <div class="form-field">
        <label for="lang_image"><?php _e('Icon Hotlink', 'onwatch'); ?></label>
        <input type="text" name="lang_image" id="lang_image" value="">
    </div>
    <?php
}

function onwatch_language_meta_fields_edit($term) {
    $img = get_term_meta($term->term_id, 'image_hotlink', true);
    ?>
    <tr class="form-field">
        <th><label for="lang_image"><?php _e('Icon Hotlink', 'onwatch'); ?></label></th>
        <td><input type="text" name="lang_image" value="<?php echo esc_attr($img); ?>" class="regular-text"></td>
    </tr>
    <?php
}

function onwatch_save_language_meta($term_id) {
    if (isset($_POST['lang_image'])) update_term_meta($term_id, 'image_hotlink', sanitize_text_field($_POST['lang_image']));
}

add_filter('manage_edit-episodes_columns', 'onwatch_episodes_admin_columns');
function onwatch_episodes_admin_columns($columns) {
    unset($columns['slug'], $columns['posts'], $columns['description']);
    $columns['tr_season'] = __('Season', 'onwatch');
    $columns['tr_episode'] = __('Episode', 'onwatch');
    $columns['tr_post'] = __('Series', 'onwatch');
    return $columns;
}
add_filter('manage_episodes_custom_column', 'onwatch_episodes_admin_column_content', 10, 3);
function onwatch_episodes_admin_column_content($content, $column_name, $term_id) {
    if ($column_name == 'tr_season') $content = get_term_meta($term_id, 'season_number', true) ?: 0;
    if ($column_name == 'tr_episode') $content = get_term_meta($term_id, 'episode_number', true);
    if ($column_name == 'tr_post') {
        $pid = get_term_meta($term_id, 'tr_id_post', true);
        if ($pid) $content = '<a href="' . admin_url('post.php?post=' . $pid . '&action=edit') . '">' . get_the_title($pid) . '</a>';
    }
    return $content;
}

add_filter('manage_edit-seasons_columns', 'onwatch_seasons_admin_columns');
function onwatch_seasons_admin_columns($columns) {
    unset($columns['slug'], $columns['posts'], $columns['description']);
    $columns['tr_season'] = __('Season', 'onwatch');
    $columns['tr_post'] = __('Series', 'onwatch');
    return $columns;
}
add_filter('manage_seasons_custom_column', 'onwatch_seasons_admin_column_content', 10, 3);
function onwatch_seasons_admin_column_content($content, $column_name, $term_id) {
    if ($column_name == 'tr_season') $content = get_term_meta($term_id, 'season_number', true);
    if ($column_name == 'tr_post') {
        $pid = get_term_meta($term_id, 'tr_id_post', true);
        if ($pid) $content = '<a href="' . admin_url('post.php?post=' . $pid . '&action=edit') . '">' . get_the_title($pid) . '</a>';
    }
    return $content;
}

add_action('init', 'onwatch_add_rewrite_tags');
function onwatch_add_rewrite_tags() {
    add_rewrite_tag('%trpage%', '([^&]+)');
}

add_action('wp_ajax_grabberseasons', 'onwatch_ajax_grabber_seasons');
function onwatch_ajax_grabber_seasons() {
    check_ajax_referer('trstring', 'security');
    $tmdb_id = intval($_GET['timdb']);
    $post_id = intval($_GET['id']);
    $api_key = onwatch_get_legacy_api_key();
    $api_lang = onwatch_get_legacy_api_lang();
    if (!$api_key || !$tmdb_id || !$post_id) { echo '<p>' . __('Missing data', 'onwatch') . '</p>'; wp_die(); }

    $data = json_decode(trgrabber_curl("https://api.themoviedb.org/3/tv/{$tmdb_id}?api_key={$api_key}&language={$api_lang}"), true);
    if (!$data || !isset($data['seasons'])) { echo '<p>' . __('No seasons found', 'onwatch') . '</p>'; wp_die(); }

    $existing = get_terms(array('taxonomy' => 'seasons', 'hide_empty' => false, 'meta_query' => array(array('key' => 'tr_id_post', 'value' => $post_id))));
    $existing_nums = array();
    foreach ($existing as $e) $existing_nums[] = get_term_meta($e->term_id, 'season_number', true);

    $imported = 0;
    foreach ($data['seasons'] as $season) {
        $snum = $season['season_number'];
        if (in_array((string)$snum, $existing_nums)) continue;
        $name = $season['name'] ?: sprintf(__('Season %s', 'onwatch'), $snum);
        $term = wp_insert_term($name, 'seasons');
        if (is_wp_error($term)) continue;
        update_term_meta($term['term_id'], 'season_number', $snum);
        update_term_meta($term['term_id'], 'tr_id_post', $post_id);
        update_term_meta($term['term_id'], 'air_date', $season['air_date'] ?? '');
        update_term_meta($term['term_id'], 'overview', $season['overview'] ?? '');
        if (!empty($season['poster_path'])) update_term_meta($term['term_id'], 'poster_path_hotlink', $season['poster_path']);
        if ($snum == 0) update_term_meta($term['term_id'], 'season_special', 1);
        $s_count = $season['episode_count'] ?? 0;
        if ($s_count) update_term_meta($term['term_id'], 'number_of_episodes', $s_count);
        $imported++;
    }

    $total = count($data['seasons']);
    update_post_meta($post_id, TR_GRABBER_FIELD_NSEASONS, $total);
    printf(__('Imported %d of %d seasons', 'onwatch'), $imported, $total);
    wp_die();
}

add_action('wp_ajax_grabberepisodes', 'onwatch_ajax_grabber_episodes');
function onwatch_ajax_grabber_episodes() {
    check_ajax_referer('trstring', 'security');
    $tmdb_id = intval($_GET['timdb']);
    $post_id = intval($_GET['id']);
    $season_number = intval($_GET['season']);
    $api_key = onwatch_get_legacy_api_key();
    $api_lang = onwatch_get_legacy_api_lang();
    if (!$api_key || !$tmdb_id || !$post_id) { echo '<p>' . __('Missing data', 'onwatch') . '</p>'; wp_die(); }

    $data = json_decode(trgrabber_curl("https://api.themoviedb.org/3/tv/{$tmdb_id}/season/{$season_number}?api_key={$api_key}&language={$api_lang}"), true);
    if (!$data || !isset($data['episodes'])) { echo '<p>' . __('No episodes found', 'onwatch') . '</p>'; wp_die(); }

    $existing = get_terms(array('taxonomy' => 'episodes', 'hide_empty' => false, 'meta_query' => array(
        array('key' => 'tr_id_post', 'value' => $post_id),
        array('key' => 'season_number', 'value' => $season_number),
    )));
    $existing_nums = array();
    foreach ($existing as $e) $existing_nums[] = get_term_meta($e->term_id, 'episode_number', true);

    $imported = 0;
    foreach ($data['episodes'] as $ep) {
        $enum = $ep['episode_number'];
        if (in_array((string)$enum, $existing_nums)) continue;
        $name = $ep['name'] ?: sprintf('S%02dE%02d', $season_number, $enum);
        $term = wp_insert_term($name, 'episodes');
        if (is_wp_error($term)) continue;
        update_term_meta($term['term_id'], 'episode_number', $enum);
        update_term_meta($term['term_id'], 'season_number', $season_number);
        update_term_meta($term['term_id'], 'tr_id_post', $post_id);
        update_term_meta($term['term_id'], 'air_date', $ep['air_date'] ?? '');
        update_term_meta($term['term_id'], 'overview', $ep['overview'] ?? '');
        if (!empty($ep['still_path'])) update_term_meta($term['term_id'], 'still_path_hotlink', $ep['still_path']);
        if (!empty($ep['guest_stars'])) {
            $stars = array();
            foreach ($ep['guest_stars'] as $gs) $stars[] = $gs['name'];
            update_term_meta($term['term_id'], 'guest_stars', implode(', ', $stars));
        }
        $imported++;
    }

    printf(__('Imported %d episodes for season %d', 'onwatch'), $imported, $season_number);
    $total = count($data['episodes']);
    $existing_total = intval(get_post_meta($post_id, TR_GRABBER_FIELD_NEPISODES, true));
    update_post_meta($post_id, TR_GRABBER_FIELD_NEPISODES, $existing_total + $imported);
    wp_die();
}

add_action('wp_ajax_trgrabberlive', 'onwatch_ajax_trgrabber_live');
add_action('wp_ajax_nopriv_trgrabberlive', 'onwatch_ajax_trgrabber_live');
function onwatch_ajax_trgrabber_live() {
    if (!wp_verify_nonce($_POST['nonce'], 'trgrabberlive')) exit();

    if (isset($_POST['type']) && $_POST['type'] == 1) {
        $args = array(
            'posts_per_page' => 50,
            's' => $_POST['value'],
            'post_type' => array('movies', 'series'),
            'post_status' => 'publish',
        );
        $the_query = new WP_Query($args);
        if ($the_query->have_posts()) {
            echo '<ul class="trselect trselect_text">';
            while ($the_query->have_posts()) {
                $the_query->the_post();
                echo '<li data-val="' . get_the_title() . '" data-value="' . get_the_ID() . '"><label><button type="button">' . get_the_title() . '</button></label></li>';
                wp_reset_postdata();
            }
            echo '</ul>';
        } else {
            echo '<p>' . __('There were no results. Try another search.', 'onwatch') . '</p>';
        }
    }

    if (isset($_POST['type']) && $_POST['type'] == 2) {
        $array = array();
        if (get_post_status(intval($_POST['serie_id'])) === FALSE) { $array['serie_id'] = __('The post does not exist.', 'onwatch'); }
        if (empty($_POST['tag-name'])) { $array['name'] = __('You must enter a name.', 'onwatch'); }
        $seasons = tr_grabber_list_seasons(intval($_POST['serie_id']));
        $var = 0;
        foreach ($seasons as $value) {
            if (get_term_meta($value->term_id, 'season_number', true) == intval($_POST['season_number'])) { $var = 1; }
        }
        if ($var == 1) { $array['season_number'] = __('The season already exists.', 'onwatch'); }
        $term = term_exists($_POST['tag-name'], 'seasons');
        if ($term !== 0 && $term !== null) { $array['tagname'] = __('There is already a season with this name.', 'onwatch'); }
        echo isset($array) ? json_encode($array) : '';
    }

    if (isset($_POST['type']) && $_POST['type'] == 3) {
        $array = array();
        if (get_post_status(intval($_POST['serie_id'])) === FALSE) { $array['serie_id'] = __('The post does not exist.', 'onwatch'); }
        if (empty($_POST['tag-name'])) { $array['name'] = __('You must enter a name.', 'onwatch'); }
        if (empty($_POST['season_number'])) { $array['season_number'] = __('You must enter a season number.', 'onwatch'); }
        $episodes = tr_grabber_list_episodes(intval($_POST['serie_id']));
        $var = 0;
        foreach ($episodes as $value) {
            if (get_term_meta($value->term_id, 'episode_number', true) == intval($_POST['episode']) && get_term_meta($value->term_id, 'season_number', true) == intval($_POST['season_number'])) { $var = 1; }
        }
        if ($var == 1) { $array['episode'] = __('The episode already exists.', 'onwatch'); }
        elseif (empty($_POST['episode'])) { $array['episode'] = __('You must select an episode.', 'onwatch'); }
        $term = term_exists($_POST['tag-name'], 'episodes');
        if ($term !== 0 && $term !== null) { $array['tagname'] = __('There is already an episode with this name.', 'onwatch'); }
        echo isset($array) ? json_encode($array) : '';
    }

    if (isset($_POST['type']) && $_POST['type'] == 4) {
        $seasons = tr_grabber_list_seasons(intval($_POST['value']));
        if (!empty($seasons)) {
            foreach ($seasons as $s) echo '<option value="' . get_term_meta($s->term_id, 'season_number', true) . '">' . get_term_meta($s->term_id, 'season_number', true) . '</option>';
        } else {
            echo '<option value="">' . __('No results', 'onwatch') . '</option>';
        }
    }
    if (isset($_POST['type']) && $_POST['type'] == 5) {
        $s = intval($_POST['value']);
        if ($s == 0) $s = 'special';
        $eps = tr_grabber_list_episodes(intval($_POST['id']), $s);
        if (!empty($eps)) {
            foreach ($eps as $ep) echo '<option value="' . get_term_meta($ep->term_id, 'episode_number', true) . '">' . get_term_meta($ep->term_id, 'episode_number', true) . '</option>';
        } else {
            echo '<option value="">' . __('No results', 'onwatch') . '</option>';
        }
    }
    if (isset($_POST['type']) && $_POST['type'] == 6) {
        $ttt = intval($_POST['typel']);
        $series_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $explode = explode("\n", $_POST['links']);
        $counts = array_count_values($explode);
        $total = $counts[''] + intval($_POST['episode']);
        $list_episodes = array();
        for ($i = intval($_POST['episode']); $i <= $total; $i++) $list_episodes[] = $i;
        $explode = array_replace($explode, array_fill_keys(array_keys($explode, ''), '---NEW---'));
        $new = explode('---NEW---', json_encode($explode));
        $new = array_map(function($n) { return stripslashes(str_replace('"]', '', str_replace('","', '||', str_replace('["', '', $n)))); }, $new);

        $meta_q = array(
            'relation' => 'AND',
            array('key' => 'episode_number', 'compare' => 'IN', 'value' => $list_episodes),
        );
        if ($series_id) {
            $meta_q[] = array('key' => 'tr_id_post', 'compare' => '=', 'value' => $series_id);
        }
        if ($_POST['season'] == 0) {
            $meta_q[] = array('key' => 'season_special', 'compare' => '=', 'value' => 1);
        } else {
            $meta_q[] = array('key' => 'season_number', 'compare' => '=', 'value' => intval($_POST['season']));
        }
        $episodes_list = get_terms(array('taxonomy' => 'episodes', 'hide_empty' => false, 'orderby' => 'meta_value_num', 'order' => 'ASC', 'meta_query' => $meta_q, 'meta_key' => 'episode_number'));
        $ilinks = 0;
        foreach ($episodes_list as $value) {
            $total_links = get_term_meta($value->term_id, 'trgrabber_tlinks', true);
            foreach (array_filter(explode('||', $new[$ilinks])) as $key => $val) {
                $url = wp_parse_url(str_replace(array('https://www.', 'http://www.'), array('https://', 'http://'), $val));
                $server_id = '';
                if (!empty($url['host'])) {
                    $parts = explode('.', $url['host']);
                    $term_server = term_exists(ucwords($parts[0]), 'server');
                    $server_id = ($term_server !== 0 && $term_server !== null) ? $term_server['term_id'] : wp_insert_term(ucwords($parts[0]), 'server')['term_id'];
                }
                $array_links = array(
                    'type'    => $ttt,
                    'server'  => $server_id,
                    'lang'    => isset($_POST['lang']) ? intval($_POST['lang']) : '',
                    'quality' => isset($_POST['quality']) ? intval($_POST['quality']) : '',
                    'link'    => base64_encode(stripslashes(esc_textarea($val))),
                    'date'    => date('d/m/Y'),
                );
                $sum_field = get_term_meta($value->term_id, 'trgrabber_tlinks', true) ?: 0;
                $sum = $sum_field + 1;
                update_term_meta($value->term_id, 'trglinks_' . $sum_field, serialize($array_links));
                update_term_meta($value->term_id, 'trgrabber_tlinks', $sum);
            }
            $ilinks++;
        }
        echo json_encode(array('msj' => 1));
    }
    wp_die();
}

add_action('wp_ajax_onwatch_tmdb_fetch', 'onwatch_ajax_tmdb_fetch');
function onwatch_ajax_tmdb_fetch() {
    $tmdb_id = isset($_GET['tmdb_id']) ? intval($_GET['tmdb_id']) : 0;
    $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'movie';
    if (!$tmdb_id) { wp_send_json_error('no id'); }

    $api_key = onwatch_get_legacy_api_key();
    if (!$api_key && defined('TR_GRABBER_API_KEY') && TR_GRABBER_API_KEY) $api_key = TR_GRABBER_API_KEY;
    $api_lang = onwatch_get_legacy_api_lang();
    if (!$api_key) { wp_send_json_error('no api key'); }

    $base = $type === 'tv' ? 'tv' : 'movie';
    $details = json_decode(trgrabber_curl("https://api.themoviedb.org/3/{$base}/{$tmdb_id}?api_key={$api_key}&language={$api_lang}&append_to_response=videos,credits"), true);

    if (!$details || ($type === 'tv' && !isset($details['name'])) || ($type === 'movie' && !isset($details['title']))) {
        wp_send_json_error('not found');
    }

    $result = array(
        'tmdb_id'     => $details['id'] ?? '',
        'imdb_id'     => $details['imdb_id'] ?? '',
        'title'       => $type === 'tv' ? ($details['name'] ?? '') : ($details['title'] ?? ''),
        'original_title' => $type === 'tv' ? ($details['original_name'] ?? '') : ($details['original_title'] ?? ''),
        'overview'    => $details['overview'] ?? '',
        'poster'      => !empty($details['poster_path']) ? 'https://image.tmdb.org/t/p/w500' . $details['poster_path'] : '',
        'backdrop'    => !empty($details['backdrop_path']) ? 'https://image.tmdb.org/t/p/original' . $details['backdrop_path'] : '',
        'release_date' => $details['release_date'] ?? ($details['first_air_date'] ?? ''),
        'year'        => '',
        'runtime'     => '',
        'rating'      => $details['vote_average'] ?? '',
        'genres'      => array(),
        'countries'   => array(),
        'directors'   => array(),
        'cast'        => array(),
        'trailer'     => '',
        'type'        => $type,
    );

    if ($result['release_date']) {
        $result['year'] = explode('-', $result['release_date'])[0];
    }

    if ($type === 'movie' && isset($details['runtime'])) {
        $mins = intval($details['runtime']);
        $h = floor($mins / 60);
        $m = $mins % 60;
        $result['runtime'] = ($h ? $h . 'h ' : '') . ($m ? $m . 'm' : '');
    }

    if ($type === 'tv') {
        if (!empty($details['episode_run_time'][0])) {
            $mins = intval($details['episode_run_time'][0]);
            $h = floor($mins / 60);
            $m = $mins % 60;
            $result['runtime'] = ($h ? $h . 'h ' : '') . ($m ? $m . 'm' : '');
        }
        $result['first_air_date'] = $details['first_air_date'] ?? '';
        $result['last_air_date']  = $details['last_air_date'] ?? '';
        $result['status']         = $details['status'] ?? '';
        $result['in_production']  = !empty($details['in_production']);
        $result['number_of_seasons']  = $details['number_of_seasons'] ?? 0;
        $result['number_of_episodes'] = $details['number_of_episodes'] ?? 0;
    }

    if (!empty($details['genres'])) {
        foreach ($details['genres'] as $g) $result['genres'][] = $g['name'];
    }
    if (!empty($details['production_countries'])) {
        foreach ($details['production_countries'] as $c) $result['countries'][] = $c['name'];
    }

    if (!empty($details['credits']['crew'])) {
        foreach ($details['credits']['crew'] as $c) {
            if ($c['department'] === 'Directing') $result['directors'][] = $c['name'];
        }
    }
    if (!empty($details['credits']['cast'])) {
        foreach ($details['credits']['cast'] as $c) {
            $result['cast'][] = array(
                'name'  => $c['name'],
                'image' => $c['profile_path'] ?? '',
            );
        }
    }

    if (!empty($details['videos']['results'])) {
        foreach ($details['videos']['results'] as $v) {
            if ($v['site'] === 'YouTube' && $v['type'] === 'Trailer') {
                $result['trailer'] = '<iframe width="560" height="315" src="https://www.youtube.com/embed/' . $v['key'] . '" frameborder="0" allowfullscreen></iframe>';
                break;
            }
        }
        if (!$result['trailer']) {
            foreach ($details['videos']['results'] as $v) {
                if ($v['site'] === 'YouTube' && $v['type'] === 'Teaser') {
                    $result['trailer'] = '<iframe width="560" height="315" src="https://www.youtube.com/embed/' . $v['key'] . '" frameborder="0" allowfullscreen></iframe>';
                    break;
                }
            }
        }
    }

    wp_send_json_success($result);
}

add_action('wp_ajax_onwatch_import_all', 'onwatch_ajax_import_all');
function onwatch_ajax_import_all() {
    check_ajax_referer('trstring', 'security');
    $tmdb_id = intval($_GET['tmdb_id']);
    $post_id = intval($_GET['post_id']);
    $api_key = onwatch_get_legacy_api_key();
    if (!$api_key && defined('TR_GRABBER_API_KEY') && TR_GRABBER_API_KEY) $api_key = TR_GRABBER_API_KEY;
    $api_lang = onwatch_get_legacy_api_lang();
    if (!$api_key || !$tmdb_id || !$post_id) { echo '<p>' . __('Missing data', 'onwatch') . '</p>'; wp_die(); }

    $data = json_decode(trgrabber_curl("https://api.themoviedb.org/3/tv/{$tmdb_id}?api_key={$api_key}&language={$api_lang}"), true);
    if (!$data || !isset($data['seasons'])) { echo '<p>' . __('No seasons found', 'onwatch') . '</p>'; wp_die(); }

    $total_seasons = count($data['seasons']);
    $total_episodes = 0;
    $imported_seasons = 0;
    $imported_episodes = 0;

    $existing_seasons = get_terms(array('taxonomy' => 'seasons', 'hide_empty' => false, 'meta_query' => array(array('key' => 'tr_id_post', 'value' => $post_id))));
    $existing_nums = array();
    foreach ($existing_seasons as $e) $existing_nums[] = get_term_meta($e->term_id, 'season_number', true);

    foreach ($data['seasons'] as $season) {
        $snum = $season['season_number'];
        if (in_array((string)$snum, $existing_nums)) continue;
        $name = $season['name'] ?: sprintf(__('Season %s', 'onwatch'), $snum);
        $term = wp_insert_term($name, 'seasons');
        if (is_wp_error($term)) continue;
        update_term_meta($term['term_id'], 'season_number', $snum);
        update_term_meta($term['term_id'], 'tr_id_post', $post_id);
        update_term_meta($term['term_id'], 'air_date', $season['air_date'] ?? '');
        update_term_meta($term['term_id'], 'overview', $season['overview'] ?? '');
        if (!empty($season['poster_path'])) update_term_meta($term['term_id'], 'poster_path_hotlink', $season['poster_path']);
        if ($snum == 0) update_term_meta($term['term_id'], 'season_special', 1);
        $s_count = $season['episode_count'] ?? 0;
        if ($s_count) update_term_meta($term['term_id'], 'number_of_episodes', $s_count);
        $imported_seasons++;

        if ($s_count > 0) {
            $eps_data = json_decode(trgrabber_curl("https://api.themoviedb.org/3/tv/{$tmdb_id}/season/{$snum}?api_key={$api_key}&language={$api_lang}"), true);
            if ($eps_data && isset($eps_data['episodes'])) {
                $existing_eps = get_terms(array('taxonomy' => 'episodes', 'hide_empty' => false, 'meta_query' => array(
                    array('key' => 'tr_id_post', 'value' => $post_id),
                    array('key' => 'season_number', 'value' => $snum),
                )));
                $existing_ep_nums = array();
                foreach ($existing_eps as $ep) $existing_ep_nums[] = get_term_meta($ep->term_id, 'episode_number', true);

                foreach ($eps_data['episodes'] as $ep) {
                    $enum = $ep['episode_number'];
                    if (in_array((string)$enum, $existing_ep_nums)) continue;
                    $ep_name = $ep['name'] ?: sprintf('S%02dE%02d', $snum, $enum);
                    $ep_term = wp_insert_term($ep_name, 'episodes');
                    if (is_wp_error($ep_term)) continue;
                    update_term_meta($ep_term['term_id'], 'episode_number', $enum);
                    update_term_meta($ep_term['term_id'], 'season_number', $snum);
                    update_term_meta($ep_term['term_id'], 'tr_id_post', $post_id);
                    update_term_meta($ep_term['term_id'], 'air_date', $ep['air_date'] ?? '');
                    update_term_meta($ep_term['term_id'], 'overview', $ep['overview'] ?? '');
                    if (!empty($ep['still_path'])) update_term_meta($ep_term['term_id'], 'still_path_hotlink', $ep['still_path']);
                    $imported_episodes++;
                    $total_episodes++;
                }
            }
        }
    }

    update_post_meta($post_id, TR_GRABBER_FIELD_NSEASONS, $total_seasons);
    update_post_meta($post_id, TR_GRABBER_FIELD_NEPISODES, $total_episodes);
    printf(__('Imported %d seasons and %d episodes', 'onwatch'), $imported_seasons, $imported_episodes);
    wp_die();
}

add_action('wp_ajax_onwatch_manual_add_season', 'onwatch_ajax_manual_add_season');
function onwatch_ajax_manual_add_season() {
    check_ajax_referer('onwatch_manual', 'nonce');
    $post_id = intval($_POST['post_id']);
    $season_number = intval($_POST['season_number']);
    $season_name = sanitize_text_field($_POST['season_name']);
    $air_date = sanitize_text_field($_POST['air_date']);
    $overview = sanitize_textarea_field($_POST['overview']);

    if (!$post_id || !$season_number) {
        wp_send_json_error(__('Missing required fields.', 'onwatch'));
    }

    $existing = get_terms(array('taxonomy' => 'seasons', 'hide_empty' => false, 'meta_query' => array(
        array('key' => 'season_number', 'value' => $season_number),
        array('key' => 'tr_id_post', 'value' => $post_id),
    )));
    if (!empty($existing) && !is_wp_error($existing)) {
        wp_send_json_error(__('This season already exists.', 'onwatch'));
    }

    $name = $season_name ?: sprintf(__('Season %d', 'onwatch'), $season_number);
    $term = wp_insert_term($name, 'seasons');
    if (is_wp_error($term)) {
        wp_send_json_error($term->get_error_message());
    }

    update_term_meta($term['term_id'], 'season_number', $season_number);
    update_term_meta($term['term_id'], 'tr_id_post', $post_id);
    if ($air_date) update_term_meta($term['term_id'], 'air_date', $air_date);
    if ($overview) update_term_meta($term['term_id'], 'overview', $overview);

    wp_set_object_terms($post_id, $term['term_id'], 'seasons', true);

    wp_send_json_success(array(
        'message' => sprintf(__('Season "%s" created.', 'onwatch'), $name),
        'term_id' => $term['term_id'],
        'html'    => onwatch_get_series_seasons_html($post_id),
    ));
}

add_action('wp_ajax_onwatch_manual_add_episode', 'onwatch_ajax_manual_add_episode');
function onwatch_ajax_manual_add_episode() {
    check_ajax_referer('onwatch_manual', 'nonce');
    $post_id = intval($_POST['post_id']);
    $season_id = intval($_POST['season_id']);
    $season_number = intval($_POST['season_number']);
    $episode_number = intval($_POST['episode_number']);
    $episode_name = sanitize_text_field($_POST['episode_name']);
    $air_date = sanitize_text_field($_POST['air_date']);
    $still_url = esc_url_raw($_POST['still_url']);
    $overview = sanitize_textarea_field($_POST['overview']);

    if (!$post_id || !$season_number || !$episode_number) {
        wp_send_json_error(__('Missing required fields.', 'onwatch'));
    }

    $existing = get_terms(array('taxonomy' => 'episodes', 'hide_empty' => false, 'meta_query' => array(
        array('key' => 'episode_number', 'value' => $episode_number),
        array('key' => 'season_number', 'value' => $season_number),
        array('key' => 'tr_id_post', 'value' => $post_id),
    )));
    if (!empty($existing) && !is_wp_error($existing)) {
        wp_send_json_error(__('This episode already exists for this season.', 'onwatch'));
    }

    $name = $episode_name ?: sprintf('S%02dE%02d', $season_number, $episode_number);
    $term = wp_insert_term($name, 'episodes');
    if (is_wp_error($term)) {
        wp_send_json_error($term->get_error_message());
    }

    update_term_meta($term['term_id'], 'episode_number', $episode_number);
    update_term_meta($term['term_id'], 'season_number', $season_number);
    update_term_meta($term['term_id'], 'tr_id_post', $post_id);
    if ($air_date) update_term_meta($term['term_id'], 'air_date', $air_date);
    if ($still_url) update_term_meta($term['term_id'], 'still_path_hotlink', $still_url);
    if ($overview) update_term_meta($term['term_id'], 'overview', $overview);
    if ($season_id) wp_set_object_terms($post_id, $season_id, 'seasons', true);
    wp_set_object_terms($post_id, $term['term_id'], 'episodes', true);

    wp_send_json_success(array(
        'message' => sprintf(__('Episode "%s" created.', 'onwatch'), $name),
        'term_id' => $term['term_id'],
        'html'    => onwatch_get_series_seasons_html($post_id),
    ));
}

add_action('wp_ajax_onwatch_manual_delete_term', 'onwatch_ajax_manual_delete_term');
function onwatch_ajax_manual_delete_term() {
    check_ajax_referer('onwatch_manual', 'nonce');
    $term_id = intval($_POST['term_id']);
    $taxonomy = sanitize_key($_POST['taxonomy']);

    if (!in_array($taxonomy, array('seasons', 'episodes'))) {
        wp_send_json_error(__('Invalid taxonomy.', 'onwatch'));
    }

    $deleted = wp_delete_term($term_id, $taxonomy);
    if (is_wp_error($deleted)) {
        wp_send_json_error($deleted->get_error_message());
    }
    if (!$deleted) {
        wp_send_json_error(__('Could not delete term.', 'onwatch'));
    }

    $post_id = intval($_POST['post_id']);
    wp_send_json_success(array(
        'message' => __('Deleted.', 'onwatch'),
        'html'    => onwatch_get_series_seasons_html($post_id),
    ));
}

add_action('wp_ajax_onwatch_bulk_add_links', 'onwatch_ajax_bulk_add_links');
function onwatch_ajax_bulk_add_links() {
    check_ajax_referer('onwatch_manual', 'nonce');
    $post_id = intval($_POST['post_id']);
    $post_type = sanitize_key($_POST['post_type']);
    $season = intval($_POST['season']);
    $ep_start = intval($_POST['episode_start']);
    $ep_end = intval($_POST['episode_end']);
    $link_type = intval($_POST['link_type']);
    $lang_id = intval($_POST['lang_id']);
    $quality_id = intval($_POST['quality_id']);
    $raw_links = $_POST['links'];

    if (!$post_id || !$raw_links) {
        wp_send_json_error(__('Missing required fields.', 'onwatch'));
    }

    $links = array_filter(array_map('trim', explode("\n", $raw_links)));
    if (empty($links)) {
        wp_send_json_error(__('No valid links provided.', 'onwatch'));
    }

    $added = 0;

    if ($post_type == 'movies') {
        $total_links = intval(get_post_meta($post_id, 'trgrabber_tlinks', true));
        foreach ($links as $url) {
            $parsed = wp_parse_url(str_replace(array('https://www.', 'http://www.'), array('https://', 'http://'), $url));
            $server_id = '';
            if (!empty($parsed['host'])) {
                $parts = explode('.', $parsed['host']);
                $term = term_exists(ucwords($parts[0]), 'server');
                $server_id = ($term !== 0 && $term !== null) ? $term['term_id'] : wp_insert_term(ucwords($parts[0]), 'server')['term_id'];
            }
            $link_data = array(
                'type'    => $link_type,
                'server'  => $server_id,
                'lang'    => $lang_id,
                'quality' => $quality_id,
                'link'    => base64_encode(stripslashes($url)),
                'date'    => date('d/m/Y'),
            );
            update_post_meta($post_id, 'trglinks_' . $total_links, serialize($link_data));
            $total_links++;
            $added++;
        }
        if ($added > 0) update_post_meta($post_id, 'trgrabber_tlinks', $total_links);
    } elseif ($post_type == 'series') {
        $ep_numbers = range($ep_start, $ep_end);
        $episodes = get_terms(array(
            'taxonomy' => 'episodes', 'hide_empty' => false,
            'meta_query' => array(
                array('key' => 'season_number', 'value' => $season),
                array('key' => 'tr_id_post', 'value' => $post_id),
                array('key' => 'episode_number', 'compare' => 'IN', 'value' => $ep_numbers),
            ),
            'orderby' => 'meta_value_num', 'meta_key' => 'episode_number', 'order' => 'ASC',
        ));

        if (empty($episodes) || is_wp_error($episodes)) {
            wp_send_json_error(__('No episodes found for the specified range. Add episodes first.', 'onwatch'));
        }

        foreach ($episodes as $i => $ep) {
            if (!isset($links[$i])) break;
            $url = $links[$i];
            $parsed = wp_parse_url(str_replace(array('https://www.', 'http://www.'), array('https://', 'http://'), $url));
            $server_id = '';
            if (!empty($parsed['host'])) {
                $parts = explode('.', $parsed['host']);
                $term = term_exists(ucwords($parts[0]), 'server');
                $server_id = ($term !== 0 && $term !== null) ? $term['term_id'] : wp_insert_term(ucwords($parts[0]), 'server')['term_id'];
            }
            $link_data = array(
                'type'    => $link_type,
                'server'  => $server_id,
                'lang'    => $lang_id,
                'quality' => $quality_id,
                'link'    => base64_encode(stripslashes($url)),
                'date'    => date('d/m/Y'),
            );
            $sum_field = intval(get_term_meta($ep->term_id, 'trgrabber_tlinks', true));
            update_term_meta($ep->term_id, 'trglinks_' . $sum_field, serialize($link_data));
            update_term_meta($ep->term_id, 'trgrabber_tlinks', $sum_field + 1);
            $added++;
        }
    } else {
        wp_send_json_error(__('Invalid post type.', 'onwatch'));
    }

    wp_send_json_success(array(
        'message' => sprintf(__('Added %d link(s).', 'onwatch'), $added),
        'count'   => $added,
    ));
}

function onwatch_episodes_page() {
    if (!current_user_can('manage_options')) return;

    if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['episode_id'])) {
        onwatch_episodes_edit();
        return;
    }

    $series_filter = isset($_GET['series_id']) ? intval($_GET['series_id']) : 0;
    $season_filter = isset($_GET['season_id']) ? intval($_GET['season_id']) : 0;
    $search_query  = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    $series_list = get_posts(array('post_type' => 'series', 'numberposts' => -1, 'post_status' => 'publish'));

    if (isset($_POST['bulk_delete']) && check_admin_referer('onwatch_bulk_episodes')) {
        $ids = isset($_POST['episode_ids']) ? array_map('intval', $_POST['episode_ids']) : array();
        foreach ($ids as $id) {
            $ep = get_term($id, 'episodes');
            if ($ep && !is_wp_error($ep)) wp_delete_term($id, 'episodes');
        }
        echo '<div class="notice notice-success"><p>' . sprintf(__('Deleted %d episode(s).', 'onwatch'), count($ids)) . '</p></div>';
    }

    $meta_q = array();
    if ($series_filter) $meta_q[] = array('key' => 'tr_id_post', 'value' => $series_filter);
    if ($season_filter) $meta_q[] = array('key' => 'season_number', 'value' => $season_filter);

    $ep_args = array(
        'taxonomy'   => 'episodes',
        'hide_empty' => false,
        'number'     => 200,
        'orderby'    => 'meta_value_num',
        'meta_key'   => 'episode_number',
        'order'      => 'ASC',
    );
    if (!empty($meta_q)) $ep_args['meta_query'] = $meta_q;
    if ($search_query) $ep_args['name__like'] = $search_query;
    $episodes = get_terms($ep_args);

    $all_episodes = $series_filter ? get_terms(array('taxonomy' => 'episodes', 'hide_empty' => false, 'meta_query' => array(array('key' => 'tr_id_post', 'value' => $series_filter)), 'fields' => 'ids')) : array();
    $total_eps = count($all_episodes);
    $eps_with_links = 0; $eps_without_links = 0;
    foreach ($all_episodes as $eid) {
        intval(get_term_meta($eid, 'trgrabber_tlinks', true)) > 0 ? $eps_with_links++ : $eps_without_links++;
    }

    $seasons_in_series = array();
    if ($series_filter) {
        $all_eps_for_seasons = get_terms(array('taxonomy' => 'episodes', 'hide_empty' => false, 'meta_query' => array(array('key' => 'tr_id_post', 'value' => $series_filter)), 'fields' => 'ids'));
        foreach ($all_eps_for_seasons as $eid) {
            $sn = get_term_meta($eid, 'season_number', true);
            if ($sn !== '') $seasons_in_series[intval($sn)] = intval($sn);
        }
        ksort($seasons_in_series);
    }
    ?>
    <div class="wrap onwatch-episodes-wrap">
        <h1><?php _e('Episodes Management', 'onwatch'); ?>
            <span class="onwatch-title-count"><?php echo $total_eps; ?> <?php _e('total', 'onwatch'); ?></span>
        </h1>

        <?php if ($total_eps > 0): ?>
        <div class="onwatch-stats-bar">
            <div class="onwatch-stat">
                <span class="onwatch-stat-num"><?php echo $total_eps; ?></span>
                <span class="onwatch-stat-label"><?php _e('Total Episodes', 'onwatch'); ?></span>
            </div>
            <div class="onwatch-stat onwatch-stat--success">
                <span class="onwatch-stat-num"><?php echo $eps_with_links; ?></span>
                <span class="onwatch-stat-label"><?php _e('With Links', 'onwatch'); ?></span>
            </div>
            <div class="onwatch-stat onwatch-stat--warn">
                <span class="onwatch-stat-num"><?php echo $eps_without_links; ?></span>
                <span class="onwatch-stat-label"><?php _e('No Links', 'onwatch'); ?></span>
            </div>
            <div class="onwatch-stat">
                <span class="onwatch-stat-num"><?php echo count($seasons_in_series); ?></span>
                <span class="onwatch-stat-label"><?php _e('Seasons', 'onwatch'); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <div class="onwatch-episodes-toolbar">
            <form method="get" class="onwatch-filter-form">
                <input type="hidden" name="page" value="onwatch-episodes">
                <select name="series_id" class="onwatch-select">
                    <option value=""><?php _e('All Series', 'onwatch'); ?></option>
                    <?php foreach ($series_list as $s): ?>
                    <option value="<?php echo $s->ID; ?>" <?php selected($series_filter, $s->ID); ?>><?php echo esc_html($s->post_title); ?></option>
                    <?php endforeach; ?>
                </select>

                <?php if ($series_filter && !empty($seasons_in_series)): ?>
                <select name="season_id" class="onwatch-select">
                    <option value=""><?php _e('All Seasons', 'onwatch'); ?></option>
                    <?php foreach ($seasons_in_series as $sn): ?>
                    <option value="<?php echo $sn; ?>" <?php selected($season_filter, $sn); ?>><?php echo sprintf(__('Season %d', 'onwatch'), $sn); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>

                <input type="text" name="s" class="onwatch-search-input" placeholder="<?php _e('Search episodes...', 'onwatch'); ?>" value="<?php echo esc_attr($search_query); ?>">
                <button type="submit" class="button"><?php _e('Filter', 'onwatch'); ?></button>
                <a href="<?php echo admin_url('admin.php?page=onwatch-episodes'); ?>" class="button"><?php _e('Reset', 'onwatch'); ?></a>
            </form>

            <?php if ($series_filter && $total_eps > 0): ?>
            <form method="post" class="onwatch-bulk-form" onsubmit="return confirm('<?php _e('Delete selected episodes?', 'onwatch'); ?>')">
                <?php wp_nonce_field('onwatch_bulk_episodes'); ?>
                <button type="submit" name="bulk_delete" class="button onwatch-btn-danger" onclick="return confirm('<?php _e('Delete all selected episodes?', 'onwatch'); ?>')"><?php _e('Delete Selected', 'onwatch'); ?></button>
            <?php endif; ?>
        </div>

        <?php if ($series_filter): ?>
        <div class="onwatch-episodes-sidebar">
            <div class="onwatch-sidebar-section">
                <h3><?php _e('Seasons', 'onwatch'); ?></h3>
                <?php if (!empty($seasons_in_series)): ?>
                <ul class="onwatch-season-list">
                    <?php foreach ($seasons_in_series as $sn): ?>
                    <li><a href="<?php echo admin_url('admin.php?page=onwatch-episodes&series_id=' . $series_filter . '&season_id=' . $sn); ?>" class="<?php echo $season_filter == $sn ? 'current' : ''; ?>"><?php echo sprintf(__('Season %d', 'onwatch'), $sn); ?></a></li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <p class="onwatch-muted"><?php _e('No seasons yet.', 'onwatch'); ?></p>
                <?php endif; ?>
            </div>
            <div class="onwatch-sidebar-section">
                <h3><?php _e('Quick Actions', 'onwatch'); ?></h3>
                <div class="onwatch-quick-actions">
                    <a href="<?php echo get_edit_post_link($series_filter); ?>" class="button button-small" target="_blank"><?php _e('Edit Series', 'onwatch'); ?></a>
                    <a href="<?php echo get_permalink($series_filter); ?>" class="button button-small" target="_blank"><?php _e('View Series', 'onwatch'); ?></a>
                </div>
            </div>
            <div class="onwatch-sidebar-section">
                <h3><?php _e('Add New Episode', 'onwatch'); ?></h3>
                <div class="onwatch-add-ep-form" data-series="<?php echo $series_filter; ?>">
                    <div class="onwatch-field">
                        <label><?php _e('Season Number', 'onwatch'); ?></label>
                        <input type="number" class="ep-season-num small-text" value="<?php echo $season_filter ?: 1; ?>" min="1" style="width:100%">
                    </div>
                    <div class="onwatch-field">
                        <label><?php _e('Episode Number', 'onwatch'); ?></label>
                        <input type="number" class="ep-episode-num small-text" value="1" min="1" style="width:100%">
                    </div>
                    <div class="onwatch-field">
                        <label><?php _e('Episode Name', 'onwatch'); ?></label>
                        <input type="text" class="ep-name regular-text" style="width:100%">
                    </div>
                    <div class="onwatch-field">
                        <label><?php _e('Air Date', 'onwatch'); ?></label>
                        <input type="date" class="ep-date regular-text" style="width:100%">
                    </div>
                    <div class="onwatch-field">
                        <label><?php _e('Still Image URL', 'onwatch'); ?></label>
                        <input type="url" class="ep-still regular-text" style="width:100%" placeholder="https://...">
                    </div>
                    <div class="onwatch-field">
                        <label><?php _e('Overview', 'onwatch'); ?></label>
                        <textarea class="ep-overview large-text" rows="3" style="width:100%"></textarea>
                    </div>
                    <p>
                        <button type="button" class="button button-primary onwatch-ep-add-btn"><?php _e('Add Episode', 'onwatch'); ?></button>
                        <span class="onwatch-ep-add-status" style="display:inline-block;margin-left:4px;color:#666;"></span>
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="onwatch-episodes-main">
            <?php if (empty($episodes)): ?>
            <div class="notice notice-info"><p><?php _e('No episodes found matching your criteria.', 'onwatch'); ?></p></div>
            <?php else: ?>
            <table class="wp-list-table widefat fixed striped onwatch-episodes-table">
                <thead>
                    <tr>
                        <th class="onwatch-col-cb"><input type="checkbox" id="onwatch-cb-all"></th>
                        <th class="onwatch-col-id">ID</th>
                        <th class="onwatch-col-still"><?php _e('Still', 'onwatch'); ?></th>
                        <th><?php _e('Name', 'onwatch'); ?></th>
                        <th class="onwatch-col-series"><?php _e('Series', 'onwatch'); ?></th>
                        <th class="onwatch-col-num"><?php _e('S', 'onwatch'); ?></th>
                        <th class="onwatch-col-num"><?php _e('Ep', 'onwatch'); ?></th>
                        <th class="onwatch-col-links"><?php _e('Links', 'onwatch'); ?></th>
                        <th class="onwatch-col-actions"><?php _e('Actions', 'onwatch'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($episodes as $ep):
                        $term_id = $ep->term_id;
                        $sid = get_term_meta($term_id, 'tr_id_post', true);
                        $s_title = $sid ? get_the_title($sid) : '—';
                        $s_num = get_term_meta($term_id, 'season_number', true);
                        $e_num = get_term_meta($term_id, 'episode_number', true);
                        $still = onwatch_get_still($term_id, 'w92');
                        $tlinks = intval(get_term_meta($term_id, 'trgrabber_tlinks', true) ?: 0);
                        $edit_url = admin_url('admin.php?page=onwatch-episodes&action=edit&episode_id=' . $term_id);
                    ?>
                    <tr class="<?php echo $tlinks === 0 ? 'onwatch-row-warning' : ''; ?>">
                        <td class="onwatch-col-cb"><input type="checkbox" name="episode_ids[]" value="<?php echo $term_id; ?>" class="onwatch-cb"></td>
                        <td class="onwatch-col-id"><?php echo $term_id; ?></td>
                        <td class="onwatch-col-still"><img src="<?php echo esc_url($still ?: get_template_directory_uri() . '/resources/assets/img/placeholder.svg'); ?>" width="80" height="45" alt=""></td>
                        <td class="onwatch-col-title"><a href="<?php echo esc_url($edit_url); ?>"><strong><?php echo esc_html($ep->name); ?></strong></a></td>
                        <td class="onwatch-col-series"><?php if ($sid): ?><a href="<?php echo esc_url(get_edit_post_link($sid)); ?>"><?php echo esc_html($s_title); ?></a><?php else: echo '—'; endif; ?></td>
                        <td class="onwatch-col-num"><?php echo $s_num !== '' ? esc_html($s_num) : '—'; ?></td>
                        <td class="onwatch-col-num"><?php echo $e_num !== '' ? esc_html($e_num) : '—'; ?></td>
                        <td class="onwatch-col-links"><span class="onwatch-link-count" data-count="<?php echo $tlinks; ?>"><?php echo $tlinks; ?></span></td>
                        <td class="onwatch-col-actions">
                            <a href="<?php echo esc_url($edit_url); ?>" class="button button-small"><?php _e('Edit', 'onwatch'); ?></a>
                            <a href="<?php echo esc_url(get_term_link($ep)); ?>" class="button button-small" target="_blank"><?php _e('View', 'onwatch'); ?></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            <?php if ($series_filter && $total_eps > 0): ?>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <script>
    jQuery(function($) {
        $('#onwatch-cb-all').on('change', function() { $('.onwatch-cb').prop('checked', $(this).prop('checked')); });
        $('.onwatch-filter-form select').on('change', function() { $(this).closest('form').submit(); });
    });
    </script>
    <?php
}

function onwatch_episodes_edit() {
    $term_id = intval($_GET['episode_id']);
    $term = get_term($term_id, 'episodes');
    if (!$term || is_wp_error($term)) {
        echo '<div class="wrap"><h1>' . __('Episode not found', 'onwatch') . '</h1></div>';
        return;
    }

    $series_id = get_term_meta($term_id, 'tr_id_post', true);
    $season_number = get_term_meta($term_id, 'season_number', true);
    $episode_number = get_term_meta($term_id, 'episode_number', true);
    $air_date = get_term_meta($term_id, 'air_date', true);
    $overview = get_term_meta($term_id, 'overview', true);
    $still_path = get_term_meta($term_id, 'still_path_hotlink', true);
    $tlinks = get_term_meta($term_id, 'trgrabber_tlinks', true) ?: 0;

    $series_title = $series_id ? get_the_title($series_id) : '—';
    $edit_series_url = $series_id ? get_edit_post_link($series_id) : '#';

    $all_episodes = array();
    $prev_id = null; $next_id = null;
    if ($series_id) {
        $all_episodes = get_terms(array('taxonomy' => 'episodes', 'hide_empty' => false, 'meta_query' => array(array('key' => 'tr_id_post', 'value' => $series_id)), 'orderby' => 'meta_value_num', 'meta_key' => 'episode_number', 'order' => 'ASC', 'fields' => 'ids'));
        $pos = array_search($term_id, $all_episodes);
        if ($pos !== false) {
            if ($pos > 0) $prev_id = $all_episodes[$pos - 1];
            if ($pos < count($all_episodes) - 1) $next_id = $all_episodes[$pos + 1];
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['onwatch_episode_save'])) {
        check_admin_referer('onwatch_episode_save_' . $term_id);

        wp_update_term($term_id, 'episodes', array('name' => sanitize_text_field($_POST['ep_name'])));

        if (isset($_POST['season_number'])) update_term_meta($term_id, 'season_number', intval($_POST['season_number']));
        if (isset($_POST['episode_number'])) update_term_meta($term_id, 'episode_number', intval($_POST['episode_number']));
        if (isset($_POST['air_date'])) update_term_meta($term_id, 'air_date', sanitize_text_field($_POST['air_date']));
        if (isset($_POST['overview'])) update_term_meta($term_id, 'overview', sanitize_textarea_field($_POST['overview']));
        if (isset($_POST['still_path_hotlink'])) update_term_meta($term_id, 'still_path_hotlink', esc_url_raw($_POST['still_path_hotlink']));

        if (isset($_POST['trgrabber_link']) && is_array($_POST['trgrabber_link'])) {
            for ($i = 0; $i <= $tlinks; $i++) delete_term_meta($term_id, 'trglinks_' . $i);
            delete_term_meta($term_id, 'trgrabber_tlinks');

            $links = array_filter($_POST['trgrabber_link']);
            $li = 0;
            foreach ($links as $k => $link) {
                $link_data = array(
                    'type'    => isset($_POST['trgrabber_type'][$k]) ? intval($_POST['trgrabber_type'][$k]) : 1,
                    'server'  => isset($_POST['trgrabber_server'][$k]) ? intval($_POST['trgrabber_server'][$k]) : '',
                    'lang'    => isset($_POST['trgrabber_lang'][$k]) ? intval($_POST['trgrabber_lang'][$k]) : '',
                    'quality' => isset($_POST['trgrabber_quality'][$k]) ? intval($_POST['trgrabber_quality'][$k]) : '',
                    'link'    => base64_encode(stripslashes(esc_textarea($link))),
                    'date'    => !empty($_POST['trgrabber_date'][$k]) ? $_POST['trgrabber_date'][$k] : date('d/m/Y'),
                );
                update_term_meta($term_id, 'trglinks_' . $li, serialize($link_data));
                $li++;
            }
            if ($li > 0) update_term_meta($term_id, 'trgrabber_tlinks', $li);
            $tlinks = $li;
        }

        $term = get_term($term_id, 'episodes');
        $season_number = get_term_meta($term_id, 'season_number', true);
        $episode_number = get_term_meta($term_id, 'episode_number', true);
        $air_date = get_term_meta($term_id, 'air_date', true);
        $overview = get_term_meta($term_id, 'overview', true);
        $still_path = get_term_meta($term_id, 'still_path_hotlink', true);
        echo '<div class="notice notice-success"><p>' . __('Episode saved.', 'onwatch') . '</p></div>';
    }
    ?>
    <div class="wrap onwatch-episode-edit-wrap">
        <div class="onwatch-edit-header">
            <h1><?php _e('Edit Episode', 'onwatch'); ?>: <?php echo esc_html($term->name); ?></h1>
            <div class="onwatch-nav-buttons">
                <?php if ($prev_id): ?>
                <a href="<?php echo admin_url('admin.php?page=onwatch-episodes&action=edit&episode_id=' . $prev_id); ?>" class="button">&larr; <?php _e('Previous', 'onwatch'); ?></a>
                <?php endif; ?>
                <?php if ($next_id): ?>
                <a href="<?php echo admin_url('admin.php?page=onwatch-episodes&action=edit&episode_id=' . $next_id); ?>" class="button"><?php _e('Next', 'onwatch'); ?> &rarr;</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="onwatch-edit-breadcrumbs">
            <a href="<?php echo esc_url(admin_url('admin.php?page=onwatch-episodes')); ?>"><?php _e('Episodes', 'onwatch'); ?></a>
            <span class="sep">&rarr;</span>
            <a href="<?php echo esc_url($edit_series_url); ?>"><?php echo esc_html($series_title); ?></a>
            <span class="sep">&rarr;</span>
            <span><?php echo esc_html($term->name); ?></span>
            <span class="onwatch-edit-status">S<?php echo esc_html($season_number); ?>E<?php echo esc_html($episode_number); ?></span>
        </div>

        <form method="post" class="onwatch-edit-form">
            <?php wp_nonce_field('onwatch_episode_save_' . $term_id); ?>
            <input type="hidden" name="onwatch_episode_save" value="1">

            <div class="onwatch-edit-grid">
                <div class="onwatch-edit-panel">
                    <div class="onwatch-edit-panel-header">
                        <span class="dashicons dashicons-video-alt2"></span>
                        <h2><?php _e('Episode Details', 'onwatch'); ?></h2>
                    </div>
                    <div class="onwatch-edit-panel-body">
                        <div class="onwatch-field">
                            <label for="ep_name"><?php _e('Episode Name', 'onwatch'); ?></label>
                            <input type="text" id="ep_name" name="ep_name" value="<?php echo esc_attr($term->name); ?>" class="regular-text">
                        </div>
                        <div class="onwatch-field-row">
                            <div class="onwatch-field">
                                <label for="season_number"><?php _e('Season', 'onwatch'); ?></label>
                                <input type="number" id="season_number" name="season_number" value="<?php echo esc_attr($season_number); ?>" min="0">
                            </div>
                            <div class="onwatch-field">
                                <label for="episode_number"><?php _e('Episode', 'onwatch'); ?></label>
                                <input type="number" id="episode_number" name="episode_number" value="<?php echo esc_attr($episode_number); ?>" min="1">
                            </div>
                            <div class="onwatch-field">
                                <label for="air_date"><?php _e('Air Date', 'onwatch'); ?></label>
                                <input type="date" id="air_date" name="air_date" value="<?php echo esc_attr($air_date); ?>">
                            </div>
                        </div>
                        <div class="onwatch-field">
                            <label for="still_path_hotlink"><?php _e('Still Image URL', 'onwatch'); ?></label>
                            <div class="onwatch-field-with-preview">
                                <input type="text" id="still_path_hotlink" name="still_path_hotlink" value="<?php echo esc_attr($still_path); ?>" class="regular-text" placeholder="https://image.tmdb.org/t/p/w300/...">
                                <?php if ($still_path): ?>
                                <img src="<?php echo esc_url(onwatch_get_still($term_id, 'w300')); ?>" class="onwatch-preview-img" alt="">
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="onwatch-field">
                            <label for="overview"><?php _e('Overview', 'onwatch'); ?></label>
                            <textarea id="overview" name="overview" rows="5" class="large-text"><?php echo esc_textarea($overview); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="onwatch-edit-panel">
                    <div class="onwatch-edit-panel-header">
                        <span class="dashicons dashicons-admin-links"></span>
                        <h2><?php _e('Server Links', 'onwatch'); ?></h2>
                        <button type="button" class="button button-small" id="onwatch_ep_add_link">+ <?php _e('Add Link', 'onwatch'); ?></button>
                    </div>
                    <div class="onwatch-edit-panel-body onwatch-links-panel">
                        <div class="onwatch-links-table-wrap">
                            <table class="wp-list-table widefat fixed striped onwatch-links-table" id="onwatch_ep_links_table">
                                <thead>
                                    <tr>
                                        <th style="width:60px"><?php _e('Type', 'onwatch'); ?></th>
                                        <th style="width:110px"><?php _e('Server', 'onwatch'); ?></th>
                                        <th style="width:100px"><?php _e('Language', 'onwatch'); ?></th>
                                        <th style="width:80px"><?php _e('Quality', 'onwatch'); ?></th>
                                        <th><?php _e('URL', 'onwatch'); ?></th>
                                        <th style="width:90px"><?php _e('Date', 'onwatch'); ?></th>
                                        <th style="width:40px"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php for ($i = 0; $i < $tlinks; $i++):
                                        $raw = get_term_meta($term_id, 'trglinks_' . $i, true);
                                        $link = maybe_unserialize($raw);
                                        if (!$link) continue;
                                        $l_type = $link['type'] ?? 1;
                                        $l_server = $link['server'] ?? '';
                                        $l_lang = $link['lang'] ?? '';
                                        $l_quality = $link['quality'] ?? '';
                                        $l_url = isset($link['link']) ? base64_decode($link['link']) : '';
                                        $l_date = $link['date'] ?? '';
                                    ?>
                                    <tr>
                                        <td>
                                            <select name="trgrabber_type[]" class="onwatch-link-type">
                                                <option value="1" <?php selected($l_type, 1); ?>>Embed</option>
                                                <option value="2" <?php selected($l_type, 2); ?>>DL</option>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="trgrabber_server[]">
                                                <option value="">—</option>
                                                <?php echo tr_grabber_select_taxonomy('server', $l_server); ?>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="trgrabber_lang[]">
                                                <option value="">—</option>
                                                <?php echo tr_grabber_select_taxonomy('language', $l_lang); ?>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="trgrabber_quality[]">
                                                <option value="">—</option>
                                                <?php echo tr_grabber_select_taxonomy('quality', $l_quality); ?>
                                            </select>
                                        </td>
                                        <td><input type="text" name="trgrabber_link[]" value="<?php echo esc_attr($l_url); ?>" class="onwatch-link-url"></td>
                                        <td><input type="text" name="trgrabber_date[]" value="<?php echo esc_attr($l_date); ?>" placeholder="dd/mm/YYYY" class="onwatch-link-date"></td>
                                        <td><button type="button" class="button onwatch_ep_remove_link">&times;</button></td>
                                    </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                        </div>
                        <p class="onwatch-links-empty" style="<?php echo $tlinks > 0 ? 'display:none' : ''; ?>"><?php _e('No links yet. Click "Add Link" to add one.', 'onwatch'); ?></p>
                    </div>
                </div>
            </div>

            <div class="onwatch-edit-footer">
                <button type="submit" class="button button-primary"><?php _e('Save Episode', 'onwatch'); ?></button>
                <a href="<?php echo admin_url('admin.php?page=onwatch-episodes&series_id=' . $series_id); ?>" class="button"><?php _e('Back to Episodes', 'onwatch'); ?></a>
                <a href="<?php echo esc_url(get_term_link($term)); ?>" class="button" target="_blank"><?php _e('View on Site', 'onwatch'); ?></a>
            </div>
        </form>
    </div>
    <script>
    jQuery(function($) {
        $('#onwatch_ep_add_link').on('click', function() {
            var tbody = $('#onwatch_ep_links_table tbody');
            var row = tbody.find('tr').first().clone();
            if (!row.length) {
                row = $('<tr><td><select name="trgrabber_type[]" class="onwatch-link-type"><option value="1">Embed</option><option value="2">DL</option></select></td><td><select name="trgrabber_server[]"><option value="">—</option><?php echo str_replace("'", "\'", tr_grabber_select_taxonomy('server', '')); ?></select></td><td><select name="trgrabber_lang[]"><option value="">—</option><?php echo str_replace("'", "\'", tr_grabber_select_taxonomy('language', '')); ?></select></td><td><select name="trgrabber_quality[]"><option value="">—</option><?php echo str_replace("'", "\'", tr_grabber_select_taxonomy('quality', '')); ?></select></td><td><input type="text" name="trgrabber_link[]" class="onwatch-link-url"></td><td><input type="text" name="trgrabber_date[]" placeholder="dd/mm/YYYY" class="onwatch-link-date"></td><td><button type="button" class="button onwatch_ep_remove_link">&times;</button></td></tr>');
            } else {
                row.find('select').each(function() { $(this).val(''); });
                row.find('input').val('');
            }
            $('.onwatch-links-empty').hide();
            tbody.append(row);
        });
        $(document).on('click', '.onwatch_ep_remove_link', function() {
            if ($('#onwatch_ep_links_table tbody tr').length > 1) {
                $(this).closest('tr').remove();
            }
        });
        $('.onwatch-field-with-preview input').on('change', function() {
            var val = $(this).val();
            var preview = $(this).siblings('img');
            if (val && preview.length) preview.attr('src', val.replace('w300', 'w92'));
        });
    });
    </script>
    <?php
}

function onwatch_quick_links_page() {
    if (!current_user_can('manage_options')) return;
    $movies = get_posts(array('post_type' => 'movies', 'numberposts' => -1, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC'));
    $series_list = get_posts(array('post_type' => 'series', 'numberposts' => -1, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC'));
    $langs = get_terms(array('taxonomy' => 'language', 'hide_empty' => false));
    $quals = get_terms(array('taxonomy' => 'quality', 'hide_empty' => false));
    ?>
    <div class="wrap onwatch-quick-links-wrap">
        <h1><?php _e('Quick Links', 'onwatch'); ?></h1>
        <p><?php _e('Add watch links to movies or episodes in bulk.', 'onwatch'); ?></p>

        <div class="onwatch-ql-tabs">
            <button type="button" class="button button-primary onwatch-ql-tab active" data-type="movie"><?php _e('Movie', 'onwatch'); ?></button>
            <button type="button" class="button onwatch-ql-tab" data-type="series"><?php _e('Series', 'onwatch'); ?></button>
        </div>

        <div class="onwatch-ql-panel onwatch-ql-movie" style="display:block;">
            <table class="form-table">
                <tr>
                    <th><label for="ql_movie_id"><?php _e('Select Movie', 'onwatch'); ?></label></th>
                    <td>
                        <select id="ql_movie_id" class="regular-text onwatch-ql-select">
                            <option value="">— <?php _e('Select', 'onwatch'); ?> —</option>
                            <?php foreach ($movies as $m): ?>
                            <option value="<?php echo $m->ID; ?>"><?php echo esc_html($m->post_title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
        </div>

        <div class="onwatch-ql-panel onwatch-ql-series" style="display:none;">
            <table class="form-table">
                <tr>
                    <th><label for="ql_series_id"><?php _e('Select Series', 'onwatch'); ?></label></th>
                    <td>
                        <select id="ql_series_id" class="regular-text onwatch-ql-select">
                            <option value="">— <?php _e('Select', 'onwatch'); ?> —</option>
                            <?php foreach ($series_list as $s): ?>
                            <option value="<?php echo $s->ID; ?>"><?php echo esc_html($s->post_title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr class="ql-series-fields" style="display:none;">
                    <th><label for="ql_season_num"><?php _e('Season Number', 'onwatch'); ?></label></th>
                    <td><input type="number" id="ql_season_num" class="small-text" value="1" min="0"></td>
                </tr>
                <tr class="ql-series-fields" style="display:none;">
                    <th><label for="ql_ep_start"><?php _e('Episode Range', 'onwatch'); ?></label></th>
                    <td>
                        <input type="number" id="ql_ep_start" class="small-text" value="1" min="1" style="width:70px">
                        <span>—</span>
                        <input type="number" id="ql_ep_end" class="small-text" value="1" min="1" style="width:70px">
                        <p class="description"><?php _e('One link per episode in this range.', 'onwatch'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <table class="form-table">
            <tr>
                <th><label for="ql_link_type"><?php _e('Link Type', 'onwatch'); ?></label></th>
                <td>
                    <select id="ql_link_type">
                        <option value="1"><?php _e('Embed', 'onwatch'); ?></option>
                        <option value="2"><?php _e('Download', 'onwatch'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="ql_lang"><?php _e('Language', 'onwatch'); ?></label></th>
                <td>
                    <select id="ql_lang">
                        <option value="">— <?php _e('None', 'onwatch'); ?> —</option>
                        <?php foreach ($langs as $l): ?>
                        <option value="<?php echo $l->term_id; ?>"><?php echo esc_html($l->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="ql_quality"><?php _e('Quality', 'onwatch'); ?></label></th>
                <td>
                    <select id="ql_quality">
                        <option value="">— <?php _e('None', 'onwatch'); ?> —</option>
                        <?php foreach ($quals as $q): ?>
                        <option value="<?php echo $q->term_id; ?>"><?php echo esc_html($q->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="ql_links_text"><?php _e('Links (one per line)', 'onwatch'); ?></label></th>
                <td>
                    <textarea id="ql_links_text" rows="8" class="large-text" style="max-width:600px;font-family:monospace;" placeholder="https://example.com/embed/123&#10;https://example.com/embed/456"></textarea>
                    <p class="description"><?php _e('Paste one URL per line.', 'onwatch'); ?></p>
                </td>
            </tr>
        </table>

        <p>
            <button type="button" class="button button-primary" id="onwatch_ql_add_btn"><?php _e('Add Links', 'onwatch'); ?></button>
            <span id="onwatch_ql_status" style="margin-left:10px;color:#666;"></span>
        </p>
    </div>
    <script>
    jQuery(function($) {
        $('.onwatch-ql-tab').on('click', function() {
            $('.onwatch-ql-tab').removeClass('active');
            $(this).addClass('active');
            var type = $(this).data('type');
            $('.onwatch-ql-panel').hide();
            $('.onwatch-ql-' + type).show();
        });

        $('#ql_series_id').on('change', function() {
            $('.ql-series-fields').toggle(!!$(this).val());
        });

        $('#onwatch_ql_add_btn').on('click', function() {
            var type = $('.onwatch-ql-tab.active').data('type');
            var post_id = type === 'movie' ? $('#ql_movie_id').val() : $('#ql_series_id').val();
            if (!post_id) { alert('<?php _e('Please select a movie or series.', 'onwatch'); ?>'); return; }
            var links = $('#ql_links_text').val();
            if (!links.trim()) { alert('<?php _e('Paste at least one link.', 'onwatch'); ?>'); return; }
            $('#onwatch_ql_status').text('<?php _e('Processing...', 'onwatch'); ?>');
            $.post(OnwatchAdmin.ajaxurl, {
                action: 'onwatch_bulk_add_links',
                nonce: OnwatchAdmin.manual_nonce,
                post_id: post_id,
                post_type: type === 'movie' ? 'movies' : 'series',
                season: $('#ql_season_num').val() || 0,
                episode_start: $('#ql_ep_start').val() || 1,
                episode_end: $('#ql_ep_end').val() || 1,
                links: links,
                link_type: $('#ql_link_type').val(),
                lang_id: $('#ql_lang').val(),
                quality_id: $('#ql_quality').val()
            }).done(function(r) {
                if (r.success) {
                    $('#onwatch_ql_status').text(r.data.message);
                    $('#ql_links_text').val('');
                } else {
                    $('#onwatch_ql_status').text(r.data);
                }
            }).fail(function() {
                $('#onwatch_ql_status').text('<?php _e('Error', 'onwatch'); ?>');
            });
        });
    });
    </script>
    <?php
}
