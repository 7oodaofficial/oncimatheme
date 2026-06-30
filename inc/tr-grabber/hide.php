<?php
defined('ABSPATH') || exit;

$title = get_theme_mod('onwatch_hide_title', __('ONWatch Player', 'onwatch'));
$text = get_theme_mod('onwatch_hide_msg', __('Checking that you are not a bot', 'onwatch'));
$color = get_theme_mod('onwatch_hide_color', '#bf5bf3');
$background = get_theme_mod('onwatch_hide_img', 'https://image.tmdb.org/t/p/w780/mhdeE1yShHTaDbJVdWyTlzFvNkr.jpg');
$bg_html = $background ? '<img src="' . $background . '" alt="backdrop" class="tt-bg">' : '';
$home = esc_url(home_url('/?trhide=1'));
$id = get_query_var('tid') != '' ? get_query_var('tid') : '';

if (get_query_var('trhex') && in_array(tr_grabber_get_domain_from_url(hex2bin(get_query_var('trhex'))), tr_grabber_frame_servers())) {
    wp_redirect(esc_url_raw(hex2bin(get_query_var('trhex'))));
    exit();
}
?>
<html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo $title; ?></title>
        <meta name="robots" content="noindex, nofollow">
        <style>
            *{box-sizing:border-box;margin:0;padding:0}
            body,.tt-play{background-color:<?php echo $color; ?>}
            body{overflow:hidden;font-family:sans-serif;font-size:1rem;color:#fff}
            .tt-bg{position:absolute;left:0;top:0;width:100%;height:100%;object-fit:cover;filter:opacity(.3) grayscale(100%) contrast(130%) blur(3px)}
            .tt-all{height:100vh;display:flex;justify-content:center;align-items:center;position:relative;z-index:2;background-color:rgba(0,0,0,.5);text-align:center;padding:1rem}
            .tt-play{width:7rem;height:5rem;cursor:pointer;display:inline-block;border-radius:.5rem;margin-bottom:2rem;position:relative;box-shadow:inset 0 0 0 5px rgba(255,255,255,.7),0 0 30px rgba(0,0,0,.5)}
            .tt-play:after{content:'';position:absolute;left:.3rem;top:0;right:0;bottom:0;margin:auto;width:0;height:0;border-top:1rem solid transparent;border-left:1.7rem solid #fff;border-bottom:1rem solid transparent}
            .tt-play:hover{transform:scale(1.2)}
            .title{font-size:1.5rem;font-weight:700;margin-bottom:.5rem;text-transform:uppercase}
            .msg{font-size:.75rem;opacity:.5;margin-bottom:1rem}
            .tt-load{height:3rem;width:3rem;margin:auto;display:inline-block}
            #MainPopupIframe{display:none;position:absolute;top:0;left:0;right:0;bottom:0;width:100%;height:100%;z-index:10;border:0}
            svg path,svg rect{fill:<?php echo $color; ?>}
        </style>
    </head>
    <body oncontextmenu="return false;">
        <div class="tt-all">
            <div class="tt-bx" id="ttbx">
                <span style="display:none" onclick="tr_play();" class="tt-play" id="tt-play"></span>
                <div id="hd">
                    <h1 class="title"><?php echo $title; ?></h1>
                    <p class="msg"><?php echo $text; ?> <span id="tt-load"></span></p>
                    <div class="tt-load">
                        <svg version="1.1" viewBox="0 0 50 50">
                            <path d="M43.935,25.145c0-10.318-8.364-18.683-18.683-18.683c-10.318,0-18.683,8.365-18.683,18.683h4.068c0-8.071,6.543-14.615,14.615-14.615c8.072,0,14.615,6.543,14.615,14.615H43.935z">
                                <animateTransform attributeType="xml" attributeName="transform" type="rotate" from="0 25 25" to="360 25 25" dur="0.6s" repeatCount="indefinite"/>
                            </path>
                        </svg>
                    </div>
                </div>
                <div style="display:none" id="msg" class="tt-load">
                    <svg version="1.1" viewBox="0 0 50 50">
                        <path d="M43.935,25.145c0-10.318-8.364-18.683-18.683-18.683c-10.318,0-18.683,8.365-18.683,18.683h4.068c0-8.071,6.543-14.615,14.615-14.615c8.072,0,14.615,6.543,14.615,14.615H43.935z">
                            <animateTransform attributeType="xml" attributeName="transform" type="rotate" from="0 25 25" to="360 25 25" dur="0.6s" repeatCount="indefinite"/>
                        </path>
                    </svg>
                </div>
            </div>
        </div>
        <?php echo $bg_html; ?>
        <script>
            function trde($this) {
                var str = $this.length, trde = '';
                for (; str >= 0;) { trde = trde + $this.charAt(str); str--; }
                return trde;
            }
            setTimeout(function() {
                document.getElementById('tt-play').style.display = "block";
                document.getElementById('hd').style.display = "none";
            }, 3000);
            function tr_play() {
                var id = trde('<?php echo $id; ?>');
                document.getElementById('tt-play').style.display = "none";
                document.getElementById('msg').style.display = "block";
                var iframe = document.createElement('iframe');
                iframe.id = 'MainPopupIframe';
                iframe.onload = function() { iframe.style.display = "block"; };
                iframe.src = '<?php echo $home; ?>&trhex=' + id;
                iframe.setAttribute('allowFullScreen', '');
                document.body.appendChild(iframe);
            }
        </script>
    </body>
</html>