<?php
// 📲 افزودن به برنامه: the page one app of a service's app list opens
// (🛍 سرویس‌های من → 📲). Its address names the service and the app and
// carries the bot's signature, never the subscription itself (subapp_link),
// so a link the bot did not hand out opens nothing. The page sends the phone
// on to the app with the subscription in it, shows the app's own icon while
// it does, and - when the app is not on the phone - the stores to get it
// from (Google Play, else the latest GitHub release, the App Store, the app's
// own site), in the service owner's language, its words from 🎨
// (📲 افزودن سرویس به برنامه). The font and the icons are
// served from subapp_assets/ - nothing is fetched from another host, as the
// bot's customers are often where Google Fonts and CDNs do not load.
ini_set('error_log', 'error_log');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Marzban.php';
require_once __DIR__ . '/function.php';
require_once __DIR__ . '/panels.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');

$sa_apps = array_column(subapp_apps(), null, 'id');
$sa_id = (string) ($_GET['i'] ?? '');
$sa_appId = (string) ($_GET['a'] ?? '');
$sa_inv = false;
// a preview the admin opens from 🎨 to see a look before choosing it: made-up
// data, signed like a real link (subapp_preview_link), and it opens nothing
$sa_demo = '';
$sa_dl_look = (string) ($_GET['demo'] ?? '');
$sa_dl_lang = (string) ($_GET['l'] ?? '');
if (isset(subapp_looks()[$sa_dl_look]) && in_array($sa_dl_lang, panel_langs(), true) && isset($sa_apps[$sa_appId])
    && hash_equals(subapp_sig('demo' . $sa_dl_look . $sa_dl_lang, $sa_appId), (string) ($_GET['s'] ?? ''))) {
    $sa_demo = $sa_dl_look;
}
if ($sa_demo === '' && preg_match('/^[A-Za-z0-9]{1,64}$/', $sa_id) && isset($sa_apps[$sa_appId]) && hash_equals(subapp_sig($sa_id, $sa_appId), (string) ($_GET['s'] ?? ''))) {
    $sa_inv = select("invoice", "*", "id_invoice", $sa_id, "select");
}
$sa_owner = is_array($sa_inv) ? select("user", "*", "id", (string) $sa_inv['id_user'], "select") : false;
$sa_lang = (is_array($sa_owner) && in_array($sa_owner['lang'] ?? '', panel_langs(), true)) ? $sa_owner['lang'] : 'fa';
if ($sa_demo !== '') {
    $sa_lang = $sa_dl_lang;
    $sa_inv = ['username' => 'demo'];
}
$sa_t = lang_tab_texts($sa_lang)['users']['subapps'];
// the apps with the stores this language's admin chose for them
$sa_apps = array_column(subapp_apps_for($sa_lang), null, 'id');
// the admin's page editor: a preview that talks to it (its own signed link only)
$sa_edit = $sa_demo !== '' && isset($_GET['ed']);
// what the admin hid, moved or resized on this page (the editor shows it live, too)
$sa_lay = subapp_layout($sa_lang);
// the look of the owner's language - the words and the motion are the same in both
$sa_look = $sa_demo !== '' ? $sa_demo : subapp_look($sa_lang);
// dark / light: the shop's choice for this language - 'auto' follows the device
// (a preview may ask for another to show it)
$sa_theme = subapp_theme($sa_lang);
if ($sa_demo !== '' && isset(subapp_themes()[(string) ($_GET['th'] ?? '')])) {
    $sa_theme = (string) $_GET['th'];
}
$sa_sub = $sa_demo !== '' ? 'https://example.com/sub/demo' : '';
if ($sa_demo === '' && is_array($sa_inv) && in_array($sa_inv['Status'], ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'], true)) {
    $sa_panel = select("marzban_panel", "*", "name_panel", $sa_inv['Service_location'], "select");
    if (is_array($sa_panel) && !in_array($sa_panel['type'], ['ibsng', 'mikrotik'], true)) {
        $sa_data = (new ManagePanel())->DataUser($sa_inv['Service_location'], $sa_inv['username']);
        $sa_sub = (string) ($sa_data['subscription_url'] ?? '');
    }
}
$sa_ok = strpos($sa_sub, 'http') === 0;
$sa_app = $sa_apps[$sa_appId] ?? ['name' => '', 'icon' => '', 'accent' => '#2467b8', 'accent2' => '#5aa9ff'];
// 🎨's words as plain text: {app} is the app's name
$sa_raw = fn($k) => strtr(strip_tags((string) ($sa_t[$k] ?? '')), ['{app}' => $sa_app['name']]);
$sa_txt = fn($k) => htmlspecialchars($sa_raw($k), ENT_QUOTES, 'UTF-8');
$sa_json = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$sa_stores = [];
$sa_noplat = false;
if ($sa_ok) {
    // a preview opens nothing - not an app with a made-up subscription in it
    $sa_deep = $sa_demo !== '' ? '#demo' : subapp_deep_link($sa_appId, $sa_sub, $sa_inv['username']);
    // where to get it on this phone, in this order (Android: Google Play, and
    // the GitHub build for one that is not on it); an app with no version for
    // the phone shows its other ones with a line saying so
    $sa_ua = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $sa_os = preg_match('/iphone|ipad|ipod|macintosh/', $sa_ua) ? 'ios' : (strpos($sa_ua, 'android') !== false ? 'android' : 'other');
    if ($sa_demo !== '' && in_array((string) ($_GET['os'] ?? ''), ['android', 'ios', 'other'], true)) {
        $sa_os = (string) $_GET['os'];
    }
    $sa_order = ['android' => ['play', 'github', 'site'], 'ios' => ['ios', 'site'], 'other' => ['github', 'site', 'play', 'ios']][$sa_os];
    $sa_names = ['play' => 'pageStoreGoogle', 'github' => 'pageStoreGithub', 'ios' => 'pageStoreApple', 'site' => 'pageStoreSite'];
    foreach ($sa_order as $k) {
        if (($sa_app[$k] ?? '') !== '') {
            $sa_stores[] = ['k' => $k, 'url' => $sa_app[$k], 'label' => $sa_txt($sa_names[$k])];
        }
    }
    if (!$sa_stores) {
        $sa_noplat = true;
        foreach (['github', 'site', 'play', 'ios'] as $k) {
            if (($sa_app[$k] ?? '') !== '') {
                $sa_stores[] = ['k' => $k, 'url' => $sa_app[$k], 'label' => $sa_txt($sa_names[$k])];
            }
        }
    }
} else {
    http_response_code(404);
}
// the little marks in front of the stores
$sa_glyph = [
    'play' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#00c4ff" d="M3.6 2.3 13.4 12l-9.8 9.7c-.4-.3-.6-.8-.6-1.4V3.7c0-.6.2-1.1.6-1.4z"/><path fill="#00e676" d="M3.6 2.3c.4-.3 1-.4 1.6-.1l11.4 7.2L13.4 12z"/><path fill="#ff3d57" d="m13.4 12 3.2 2.6-11.4 7.2c-.6.3-1.2.2-1.6-.1z"/><path fill="#ffd600" d="m16.6 9.4 3.4 2.1c.7.4.7 1 0 1.4l-3.4 2.1L13.4 12z"/></svg>',
    'github' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 .3a12 12 0 0 0-3.8 23.4c.6.1.8-.3.8-.6v-2c-3.3.7-4-1.6-4-1.6-.6-1.4-1.4-1.8-1.4-1.8-1-.7.1-.7.1-.7 1.2.1 1.8 1.2 1.8 1.2 1.1 1.8 2.8 1.3 3.5 1 .1-.8.4-1.3.8-1.6-2.7-.3-5.5-1.3-5.5-5.9 0-1.3.5-2.4 1.2-3.2-.1-.3-.5-1.5.1-3.2 0 0 1-.3 3.3 1.2a11.5 11.5 0 0 1 6 0C17.3 4.7 18.3 5 18.3 5c.7 1.7.3 2.9.1 3.2.8.8 1.2 1.9 1.2 3.2 0 4.6-2.8 5.6-5.5 5.9.4.4.8 1.1.8 2.2v3.3c0 .3.2.7.8.6A12 12 0 0 0 12 .3z"/></svg>',
    'ios' => '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="5"/><path d="m8.4 17 3.6-9.4 3.6 9.4M9.9 13.4h4.2"/></svg>',
    'site' => '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9.5"/><path d="M2.5 12h19M12 2.5c2.6 2.7 3.9 5.9 3.9 9.5s-1.3 6.8-3.9 9.5c-2.6-2.7-3.9-5.9-3.9-9.5s1.3-6.8 3.9-9.5z"/></svg>',
];
$sa_rtl = $sa_lang === 'fa';
// the glow colours: the app's own, mixed into the page's - worked out here,
// as color-mix() is not in the older WebViews of the phones this opens on
$sa_mix = function ($a, $b, $t) {
    $x = sscanf($a, '#%02x%02x%02x');
    $y = sscanf($b, '#%02x%02x%02x');
    return sprintf('#%02x%02x%02x', ...array_map(fn($i) => (int) round($y[$i] + ($x[$i] - $y[$i]) * $t), [0, 1, 2]));
};
// the big button is the first place to get it; the small ones under it are the OTHERS - the same link is never shown twice
$sa_dl = $sa_stores ? $sa_stores[0]['url'] : '';
$sa_more = array_slice($sa_stores, 1);
// what the page says in each of its three states (the first is what it opens in;
// a preview can ask for another)
$sa_words = [
    'opening' => ['status' => $sa_raw('pageOpening'), 'note' => $sa_raw('pageHint')],
    'missing' => ['status' => $sa_raw('pageMissingTitle'), 'note' => $sa_raw('pageMissing')],
    'opened' => ['status' => '', 'note' => $sa_raw('pageOpened')],
];
$sa_state = ($sa_demo !== '' && isset($sa_words[(string) ($_GET['st'] ?? '')])) ? (string) $_GET['st'] : 'opening';
$sa_min = $sa_look === 'minimal';
// the blocks' own words, for the editor to know what a click on a word is
$sa_tk = ['opening' => ['status' => 'pageOpening', 'note' => 'pageHint'], 'missing' => ['status' => 'pageMissingTitle', 'note' => 'pageMissing'], 'opened' => ['status' => '', 'note' => 'pageOpened']];
// in the admin's editor a hidden part stays (faded) so that it can be brought back
$sa_hid = fn($el) => in_array($el, $sa_lay['hide'], true) ? ($sa_edit ? ' data-off="1"' : ' data-hide="1"') : '';
$sa_side = fn($b) => ['center' => 'align-self:center;text-align:center', 'start' => 'align-self:flex-start;text-align:start', 'end' => 'align-self:flex-end;text-align:end'][$sa_lay['balign'][$b] ?? $sa_lay['align']];
$sa_ord = array_flip($sa_lay['order']);
$sa_stageHidden = in_array('logo', $sa_lay['hide'], true) && in_array('flow', $sa_lay['hide'], true);
$sa_stageAttr = $sa_stageHidden ? ($sa_edit ? ' data-off="1"' : ' data-hide="1"') : '';
// the icon's size and shape: only what differs from the look's own is written
$sa_bodyStyle = '';
$sa_sizes = ['sm' => 72, 'lg' => 128, 'xl' => 152];
if ($sa_lay['px'] > 0) {
    $sa_bodyStyle .= '--ico:' . $sa_lay['px'] . 'px;';
} elseif (isset($sa_sizes[$sa_lay['size']])) {
    $sa_bodyStyle .= '--ico:' . $sa_sizes[$sa_lay['size']] . 'px;';
}
if ($sa_lay['shape'] !== 'squircle' || $sa_lay['size'] !== 'md' || $sa_lay['px'] > 0) {
    $sa_bodyStyle .= '--rad:calc(var(--ico,' . ($sa_min ? '112' : '96') . 'px)*' . ['squircle' => '.27', 'circle' => '.5', 'square' => '.1'][$sa_lay['shape']] . ');';
}
$sa_justify = ['center' => 'center', 'start' => 'flex-start', 'end' => 'flex-end'][$sa_lay['stageAlign']];
?><!doctype html>
<html lang="<?= $sa_lang ?>" dir="<?= $sa_rtl ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<?php $sa_bgDark = $sa_min ? '#0b0b10' : '#0a0e16'; $sa_bgLight = $sa_min ? '#ffffff' : '#f1f4fa'; ?>
<?php if ($sa_theme === 'auto'): ?>
<meta name="color-scheme" content="dark light">
<meta name="theme-color" content="<?= $sa_bgDark ?>" media="(prefers-color-scheme: dark)">
<meta name="theme-color" content="<?= $sa_bgLight ?>" media="(prefers-color-scheme: light)">
<?php else: ?>
<meta name="color-scheme" content="<?= $sa_theme ?>">
<meta name="theme-color" content="<?= $sa_theme === 'dark' ? $sa_bgDark : $sa_bgLight ?>">
<?php endif; ?>
<title><?= $sa_ok ? htmlspecialchars($sa_app['name']) : '⛔️' ?></title>
<?php
// the app's own icon in the browser's tab (and on a phone's home screen): a rounded one for the tab, a full square for the phone
$sa_tab = 'subapp_assets/icons/tab/' . $sa_app['icon'];
if ($sa_ok && $sa_app['icon'] !== '' && is_file(__DIR__ . '/' . $sa_tab . '-64.png')): ?>
<link rel="icon" type="image/png" sizes="64x64" href="<?= htmlspecialchars($sa_tab, ENT_QUOTES) ?>-64.png">
<link rel="apple-touch-icon" sizes="180x180" href="<?= htmlspecialchars($sa_tab, ENT_QUOTES) ?>-180.png">
<?php endif; ?>
<link rel="preload" href="subapp_assets/fonts/estedad-<?= $sa_rtl ? 'arabic' : 'latin' ?>.woff2" as="font" type="font/woff2" crossorigin>
<style>
@font-face{font-family:Estedad;font-weight:100 900;font-display:swap;src:url(subapp_assets/fonts/estedad-arabic.woff2) format("woff2");unicode-range:U+0600-06FF,U+200C-200E,U+2010-2011,U+204F,U+2E41,U+FB50-FDFF,U+FE80-FEFC}
@font-face{font-family:Estedad;font-weight:100 900;font-display:swap;src:url(subapp_assets/fonts/estedad-latin.woff2) format("woff2");unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+2000-206F,U+2074,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD}
:root{
  --bg:#0a0e16;--glass:rgba(255,255,255,.065);--line:rgba(255,255,255,.12);--text:#eef2fb;--muted:#a3aec4;--chip:rgba(255,255,255,.07);
  --accent:<?= $sa_app['accent'] ?>;--accent2:<?= $sa_app['accent2'] ?>;--warn:#f5b544;--ok:#3ddc97;
  --glowA:<?= $sa_mix($sa_app['accent'], '#0a0e16', .38) ?>;--glowB:<?= $sa_mix($sa_app['accent2'], '#0a0e16', .26) ?>;--glowC:<?= $sa_mix($sa_app['accent'], '#0a0e16', .16) ?>;
  --cardline:rgba(255,255,255,.12);--sat:1;
  --shadow:0 28px 60px -26px rgba(0,0,0,.65);
  --dx:<?= $sa_rtl ? '-1' : '1' ?>;
  --out:cubic-bezier(.23,1,.32,1);--inout:cubic-bezier(.77,0,.175,1);
}
<?php ob_start(); ?>
  --bg:#f1f4fa;--glass:rgba(255,255,255,.74);--line:rgba(16,24,40,.1);--text:#101828;--muted:#566277;--chip:rgba(16,24,40,.05);--warn:#b86e00;--ok:#0f9d63;
  --glowA:<?= $sa_mix($sa_app['accent'], '#f1f4fa', .52) ?>;--glowB:<?= $sa_mix($sa_app['accent2'], '#f1f4fa', .46) ?>;--glowC:<?= $sa_mix($sa_app['accent'], '#f1f4fa', .3) ?>;
  --glass:rgba(255,255,255,.5);--cardline:rgba(255,255,255,.85);--sat:1.6;
  --shadow:0 28px 60px -26px rgba(16,24,40,.38),inset 0 1px 0 rgba(255,255,255,.9);<?php $sa_light = ob_get_clean(); ?>
@media (prefers-color-scheme:light){[data-theme=auto]{<?= $sa_light ?>}}
[data-theme=light]{<?= $sa_light ?>;color-scheme:light}
[data-theme=dark]{color-scheme:dark}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html{-webkit-text-size-adjust:100%}
body{margin:0;min-height:100vh;min-height:100dvh;display:flex;align-items:center;justify-content:center;padding:max(20px,env(safe-area-inset-top)) 20px max(20px,env(safe-area-inset-bottom));color:var(--text);
  font:400 16px/1.8 Estedad,system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif;
  background:radial-gradient(75% 46% at 10% 0,var(--glowA) 0,transparent 72%),radial-gradient(62% 40% at 100% 34%,var(--glowB) 0,transparent 74%),radial-gradient(80% 36% at 30% 100%,var(--glowC) 0,transparent 76%),var(--bg);background-attachment:fixed}
.card{width:100%;max-width:400px;display:flex;flex-direction:column;align-items:center;text-align:center;padding:30px 22px 22px;border-radius:30px;background:var(--glass);border:1px solid var(--cardline);box-shadow:var(--shadow);-webkit-backdrop-filter:blur(22px) saturate(var(--sat));backdrop-filter:blur(22px) saturate(var(--sat))}
.err{padding:34px 22px}
.err p{margin:0;font-size:15.5px;color:var(--muted)}

/* in the admin's editor (panel/subapp_edit.php): the page is the tool - parts are
   picked, dragged, resized and edited in place */
.ed .card.enter,.ed .enter .stage,.ed .enter h1,.ed .enter .chip,.ed .enter .status,.ed .enter .col{animation:none}
.ed [data-el]{cursor:grab;touch-action:none;-webkit-user-select:none;user-select:none}
.ed [data-tk]{cursor:text}
.ed [data-el]:hover{outline:1.5px dashed color-mix(in srgb,var(--accent2) 70%,transparent);outline-offset:4px;border-radius:6px}
.ed .sel{outline:2px solid var(--accent2)!important;outline-offset:4px;border-radius:6px}
.ed [data-off]{opacity:.3;filter:grayscale(1)}
.ed .dragging{cursor:grabbing!important;z-index:20;position:relative;box-shadow:0 18px 40px -14px rgba(0,0,0,.45);opacity:.96;transition:none!important;outline:2px solid var(--accent2);outline-offset:4px;border-radius:10px;background:color-mix(in srgb,var(--bg) 70%,transparent)}
.ed [contenteditable=true],.ed [contenteditable=plaintext-only]{outline:2px solid var(--accent2)!important;outline-offset:3px;border-radius:4px;cursor:text;-webkit-user-select:text;user-select:text;min-width:2ch}
.ed-zones{position:fixed;top:0;bottom:0;display:none;pointer-events:none;z-index:15}
.ed-zones i{position:absolute;top:0;bottom:0;width:33.333%;border-inline:1px dashed color-mix(in srgb,var(--accent2) 50%,transparent);transition:background-color .14s ease}
.ed-zones i.on{background:color-mix(in srgb,var(--accent2) 14%,transparent)}
.ed-gap{position:fixed;height:3px;border-radius:3px;background:var(--accent2);pointer-events:none;z-index:16;display:none;box-shadow:0 0 0 3px color-mix(in srgb,var(--accent2) 25%,transparent)}
.ed-bar{position:fixed;z-index:30;display:none;gap:2px;padding:3px;border-radius:12px;background:#17171e;border:1px solid rgba(255,255,255,.14);box-shadow:0 10px 26px -8px rgba(0,0,0,.6)}
.ed-bar button{width:34px;height:34px;border:0;border-radius:9px;background:none;color:#fff;font:inherit;font-size:16px;line-height:1;cursor:pointer;transition:background-color .14s ease,transform .12s var(--out)}
.ed-bar button:active{transform:scale(.92)}
@media (hover:hover) and (pointer:fine){.ed-bar button:hover{background:rgba(255,255,255,.14)}}
.ed-h{position:fixed;z-index:30;display:none;width:20px;height:20px;margin:-10px 0 0 -10px;border-radius:50%;background:#fff;border:3px solid var(--accent2);box-shadow:0 2px 8px rgba(0,0,0,.4);cursor:nwse-resize;touch-action:none}
.ed-tip{position:fixed;z-index:31;display:none;padding:3px 9px;border-radius:8px;background:#17171e;color:#fff;font:600 12px/1.6 Estedad,system-ui;pointer-events:none}
@media (prefers-reduced-motion:reduce){.ed-zones i,.ed-bar button{transition:none}}
/* what the shop's admin hid on this page, and the text aligned to the start */
[data-hide]{display:none!important}
[data-align=start] .card{text-align:start}
[data-align=start] .stores{align-items:flex-start}
[data-align=start] .row{justify-content:flex-start}
[data-align=end] .card{text-align:end}
[data-align=end] .stores{align-items:flex-end}
[data-align=end] .row{justify-content:flex-end}
.stage{width:100%}
/* app icon between the service and the app, with the subscription travelling over */
.stage{display:flex;align-items:center;justify-content:center;gap:6px;margin-bottom:6px}
.node{flex:none;width:52px;height:52px;border-radius:50%;display:grid;place-items:center;background:var(--chip);border:1px solid var(--line);color:var(--muted)}
.node svg{width:24px;height:24px;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
.track{position:relative;width:64px;height:10px;flex:none}
.track i{position:absolute;top:1px;left:0;width:8px;height:8px;border-radius:50%;background:var(--accent2);opacity:0;animation:travel 1.6s linear infinite}
.track i:nth-child(2){animation-delay:-.53s}
[dir=rtl] .track i{left:auto;right:0}
.track i:nth-child(3){animation-delay:-1.07s}
.appwrap{position:relative;flex:none;width:var(--ico,96px);height:var(--ico,96px)}
.appwrap::before,.appwrap::after{content:"";position:absolute;top:0;left:0;right:0;bottom:0;border-radius:calc(var(--rad,25px) + 2px);border:2px solid var(--accent2);opacity:0;animation:ping 2s var(--out) infinite}
.appwrap::after{animation-delay:1s}
.app{position:relative;width:var(--ico,96px);height:var(--ico,96px);border-radius:var(--rad,25px);overflow:hidden;display:grid;place-items:center;background:linear-gradient(145deg,var(--accent2),var(--accent));color:#fff;font-weight:800;font-size:40px;line-height:1;box-shadow:0 16px 32px -12px var(--accent)}
.app img{position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover}
.badge{position:absolute;right:-6px;bottom:-6px;width:30px;height:30px;border-radius:50%;display:grid;place-items:center;color:#fff;border:3px solid var(--bg);opacity:0;transform:scale(.6);transition:opacity .2s var(--out),transform .28s var(--out)}
[dir=rtl] .badge{right:auto;left:-6px}
.badge svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:3;stroke-linecap:round;stroke-linejoin:round}
.badge .b-ok,.badge .b-warn{display:none}
[data-state=opened] .badge{background:var(--ok);opacity:1;transform:none}
[data-state=opened] .badge .b-ok{display:block}
[data-state=missing] .badge{background:var(--warn);opacity:1;transform:none}
[data-state=missing] .badge .b-warn{display:block}
[data-state=missing] .track i,[data-state=opened] .track i{animation:none;opacity:.28;transform:translateX(calc(var(--dx)*calc(var(--n,0)*24px)))}
[data-state=missing] .track i:nth-child(1){--n:0}[data-state=missing] .track i:nth-child(2){--n:1}[data-state=missing] .track i:nth-child(3){--n:2}
[data-state=opened] .track i:nth-child(1){--n:0}[data-state=opened] .track i:nth-child(2){--n:1}[data-state=opened] .track i:nth-child(3){--n:2}
[data-state=missing] .appwrap::before,[data-state=missing] .appwrap::after,[data-state=opened] .appwrap::before,[data-state=opened] .appwrap::after{animation:none}
[data-state=missing] .app{animation:nudge .36s var(--inout) 1}
@keyframes travel{0%{opacity:0;transform:translateX(0)}15%{opacity:1}85%{opacity:1}100%{opacity:0;transform:translateX(calc(var(--dx)*56px))}}
@keyframes ping{0%{opacity:.5;transform:scale(1)}100%{opacity:0;transform:scale(1.5)}}
@keyframes nudge{25%{transform:translateX(-4px)}75%{transform:translateX(4px)}}

h1{margin:18px 0 0;font-size:28px;font-weight:800;line-height:1.35;letter-spacing:-.2px}
.chip{margin-top:8px;display:inline-flex;align-items:center;gap:8px;max-width:100%;padding:5px 14px;border-radius:999px;background:var(--chip);font-size:13.5px;color:var(--muted)}
.chip b{color:var(--text);font-weight:700;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;direction:ltr}
.chip svg{flex:none;width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.status{margin-top:16px;display:flex;align-items:center;gap:9px;font-size:15px;font-weight:500;color:var(--text)}
.dot{flex:none;width:8px;height:8px;border-radius:50%;background:var(--accent2);animation:pulse 1.2s ease-in-out infinite}
[data-state=missing] .dot{background:var(--warn);animation:none}
[data-state=opened] .dot{background:var(--ok);animation:none}
@keyframes pulse{0%,100%{opacity:.35}50%{opacity:1}}
.note{margin:12px 6px 0;font-size:13.5px;line-height:1.95;color:var(--muted)}
[data-state=opened] .status{display:none}
[data-state=opened] .note{--o:0;margin-top:0;font-size:14.5px;color:var(--text)}
[data-state=opened] #open{margin-top:12px}

/* buttons: the order of the column follows the state */
.col{align-self:stretch;display:flex;flex-direction:column;margin-top:14px}
.col>*{order:var(--o)}
#open{--o:1}.note{order:var(--o,2)}.dlbox{--o:3}#retry{--o:5}
[data-state=missing] #open{display:none}
[data-state=missing] .note{--o:1;margin-top:0;font-size:14.5px}
[data-state=missing] .dlbox{--o:2}
[data-state=missing] #retry{display:flex}
.btn{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;min-height:54px;padding:0 18px;border-radius:17px;border:1px solid var(--line);background:var(--chip);color:var(--text);font:inherit;font-size:16px;font-weight:700;text-decoration:none;cursor:pointer;transition:transform .14s var(--out),background-color .2s var(--out);-webkit-user-select:none;user-select:none}
.btn:active{transform:scale(.97)}
.btn:focus-visible,.store:focus-visible{outline:2px solid var(--accent2);outline-offset:3px}
.btn svg{flex:none;width:19px;height:19px;fill:none;stroke:currentColor;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}
.btn .flip{transform:<?= $sa_rtl ? 'scaleX(-1)' : 'none' ?>}
.primary,[data-state=missing] .dl{border:0;background:linear-gradient(135deg,var(--accent2),var(--accent));color:#fff;box-shadow:0 16px 30px -14px var(--accent)}
.dl{font-weight:600;font-size:15.5px;min-height:50px}
.dl .ic,.dl .tx{display:contents}
.dl .chev{display:none}
.dl .tx small{font-size:12.5px;font-weight:500;opacity:.62}
.dl .tx small::before{content:"\00b7";margin-inline-end:10px}
.dl .tx b{font-weight:inherit}
[data-state=missing] .dl{font-weight:700;font-size:16px;min-height:54px}
#retry{display:none;margin-top:14px;min-height:48px;font-size:15px;font-weight:600}
.dlbox{margin-top:14px;display:flex;flex-direction:column}
.stores{margin-top:12px;display:flex;flex-direction:column;align-items:center;gap:8px}
.cap{font-size:12.5px;color:var(--muted)}
.row{display:flex;flex-wrap:wrap;justify-content:center;gap:8px}
.store{display:inline-flex;align-items:center;gap:8px;min-height:40px;padding:0 14px;border-radius:999px;border:1px solid var(--line);background:var(--chip);color:var(--text);font-size:13.5px;font-weight:600;text-decoration:none;transition:transform .14s var(--out),background-color .2s var(--out)}
.store svg{flex:none;width:18px;height:18px}
.store:active{transform:scale(.96)}
@media (hover:hover) and (pointer:fine){.btn:hover,.store:hover{background-color:var(--glass)}.primary:hover,[data-state=missing] .dl:hover{filter:brightness(1.06);background-color:transparent}}

/* B - minimal: flat colours instead of the glass, a pill for the main button, a row
   for the download. The same font, words, stage and motion as A, and the same
   dark / light (the device's, or the one the shop chose). */
[data-look=minimal]{--bg:#0b0b10;--chip:#17171e;--line:rgba(255,255,255,.09);--text:#f4f4f8;--muted:#a0a0ae;--soft:rgba(255,255,255,.08);--accentText:<?= $sa_mix($sa_app['accent'], '#ffffff', .5) ?>;--ico:112px;--rad:32px;background:var(--bg)}
<?php ob_start(); ?>--bg:#fff;--chip:#f4f6fa;--line:rgba(15,23,42,.09);--text:#0f172a;--muted:#586377;--warn:#b86e00;--ok:#0f9d63;--soft:<?= $sa_mix($sa_app['accent'], '#ffffff', .12) ?>;--accentText:<?= $sa_app['accent'] ?><?php $sa_minLight = ob_get_clean(); ?>
@media (prefers-color-scheme:light){[data-look=minimal][data-theme=auto]{<?= $sa_minLight ?>}}
[data-look=minimal][data-theme=light]{<?= $sa_minLight ?>}
[data-look=minimal] .card{background:none;border:0;box-shadow:none;-webkit-backdrop-filter:none;backdrop-filter:none;padding:8px 2px}
[data-look=minimal] .node{background:var(--soft);border-color:transparent}
[data-look=minimal] .app{font-size:46px;background:linear-gradient(150deg,var(--accent2),var(--accent));box-shadow:inset 0 1.5px 0 rgba(255,255,255,.38),0 22px 40px -16px var(--accent)}
[data-look=minimal] h1{margin-top:24px;font-size:31px;letter-spacing:-.3px}
[data-look=minimal] .chip{margin-top:6px;padding:0;background:none;font-size:15px}
[data-look=minimal] .primary,[data-look=minimal] [data-state=missing] .dl{border:0;border-radius:999px;min-height:56px;font-size:17px;background:var(--accent);color:#fff;box-shadow:0 14px 26px -14px var(--accent)}
[data-look=minimal] .dl{justify-content:flex-start;gap:13px;min-height:0;padding:14px 16px;border-radius:20px;background:var(--chip);color:var(--text);font-weight:700;font-size:15.5px;box-shadow:none;text-align:start}
[data-look=minimal] .dl .ic{display:grid;place-items:center;flex:none;width:38px;height:38px;border-radius:12px;background:var(--soft);color:var(--accentText)}
[data-look=minimal] .dl .tx{display:flex;flex:1;min-width:0;flex-direction:column;align-items:flex-start;line-height:1.55}
[data-look=minimal] .dl .tx small{display:block;font-size:12.5px;font-weight:500;color:var(--muted);opacity:1}
[data-look=minimal] .dl .tx small::before{content:none}
[data-look=minimal] .dl .chev{display:block;width:18px;height:18px;color:var(--muted)}
[data-look=minimal] [data-state=missing] .dl{justify-content:center;gap:10px;padding:0 18px;text-align:center}
[data-look=minimal] [data-state=missing] .dl .ic{display:contents}
[data-look=minimal] [data-state=missing] .dl .tx{display:contents}
[data-look=minimal] [data-state=missing] .dl .tx small,[data-look=minimal] [data-state=missing] .dl .chev{display:none}
[data-look=minimal] #retry{border:0;border-radius:999px;background:none;color:var(--accentText);font-weight:700;font-size:15.5px}
@media (hover:hover) and (pointer:fine){[data-look=minimal] .btn:hover,[data-look=minimal] .store:hover{background-color:var(--soft)}[data-look=minimal] .primary:hover,[data-look=minimal] [data-state=missing] .dl:hover{background-color:var(--accent)}}

/* the page comes up once: the card, then what is in it one by one */
.card.enter{animation:rise .5s var(--out) both}
.enter .stage,.enter h1,.enter .chip,.enter .status,.enter .col{animation:rise .5s var(--out) both}
.enter .stage{animation-delay:.05s}.enter h1{animation-delay:.11s}.enter .chip{animation-delay:.16s}.enter .status{animation-delay:.21s}.enter .col{animation-delay:.27s}
@keyframes rise{from{opacity:0;transform:translateY(12px) scale(.985)}to{opacity:1;transform:none}}
.swap{animation:swap .26s var(--out) both}
@keyframes swap{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
@media (prefers-reduced-motion:reduce){
  .card.enter,.enter .stage,.enter h1,.enter .chip,.enter .status,.enter .col{animation:fade .3s ease both;animation-delay:0s}
  .swap{animation:fade .2s ease both}
  .track i{animation:none;opacity:.35;transform:translateX(calc(var(--dx)*calc(var(--n,0)*24px)))}
  .track i:nth-child(1){--n:0}.track i:nth-child(2){--n:1}.track i:nth-child(3){--n:2}
  .appwrap::before,.appwrap::after{animation:none}
  .dot{animation:none}
  [data-state=missing] .app{animation:none}
  .badge,.btn,.store{transition:opacity .2s ease,background-color .2s ease}
  .btn:active,.store:active{transform:none}
  @keyframes fade{from{opacity:0}to{opacity:1}}
}
</style>
</head>
<body data-look="<?= $sa_look ?>" data-theme="<?= $sa_theme ?>" data-align="<?= $sa_lay['align'] ?>"<?= $sa_bodyStyle !== '' ? ' style="' . $sa_bodyStyle . '"' : '' ?>>
<?php if ($sa_ok): ?>
<main class="card enter" id="card" data-state="<?= $sa_state ?>">
  <div class="stage" data-el="stage" aria-hidden="true" style="order:<?= $sa_ord['stage'] ?>;justify-content:<?= $sa_justify ?>"<?= $sa_stageAttr ?>>
    <div class="node" data-el="flow"<?= $sa_hid('flow') ?>><svg viewBox="0 0 24 24"><path d="M12 3 4.5 6v5.5c0 4.6 3.1 8.1 7.5 9.5 4.4-1.4 7.5-4.9 7.5-9.5V6z"/><path d="m9 12 2.2 2.2L15.2 10"/></svg></div>
    <div class="track" data-el="flow"<?= $sa_hid('flow') ?>><i></i><i></i><i></i></div>
    <div class="appwrap" data-el="logo"<?= $sa_hid('logo') ?>>
      <div class="app"><span><?= htmlspecialchars(strtoupper(substr($sa_app['name'], 0, 1))) ?></span><img src="subapp_assets/icons/<?= htmlspecialchars($sa_app['icon']) ?>.webp" alt="" width="96" height="96" onerror="this.remove()"></div>
      <div class="badge"><svg class="b-ok" viewBox="0 0 24 24"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg><svg class="b-warn" viewBox="0 0 24 24"><path d="M12 6.5v7M12 17.5h.01"/></svg></div>
    </div>
  </div>
  <h1 data-el="title" style="order:<?= $sa_ord['title'] ?>;<?= $sa_side('title') ?>"<?= $sa_hid('title') ?>><?= htmlspecialchars($sa_app['name']) ?></h1>
  <div class="chip" data-el="chip" style="order:<?= $sa_ord['chip'] ?>;<?= $sa_side('chip') ?>"<?= $sa_hid('chip') ?>><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/></svg><span data-tk="pageService"><?= $sa_txt('pageService') ?></span><b><?= htmlspecialchars((string) $sa_inv['username']) ?></b></div>
  <div class="status" data-el="status" style="order:<?= $sa_ord['status'] ?>;<?= $sa_side('status') ?>"<?= $sa_hid('status') ?> role="status" aria-live="polite"><i class="dot"></i><span id="status" data-tk="<?= $sa_tk[$sa_state]['status'] ?>"><?= htmlspecialchars($sa_words[$sa_state]['status'], ENT_QUOTES, 'UTF-8') ?></span></div>
  <div class="col" data-el="actions" style="order:<?= $sa_ord['actions'] ?>">
    <a class="btn primary" id="open" href="<?= htmlspecialchars($sa_deep, ENT_QUOTES) ?>"><span data-tk="pageButton"><?= $sa_txt('pageButton') ?></span><svg class="flip" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
    <p class="note" id="note" data-el="note" data-tk="<?= $sa_tk[$sa_state]['note'] ?>"<?= $sa_hid('note') ?>><?= htmlspecialchars($sa_words[$sa_state]['note'], ENT_QUOTES, 'UTF-8') ?></p>
<?php if ($sa_dl !== ''): ?>
    <div class="dlbox">
      <a class="btn dl" data-el="download"<?= $sa_hid('download') ?> href="<?= htmlspecialchars($sa_dl, ENT_QUOTES) ?>" target="_blank" rel="noopener noreferrer"><span class="ic"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v11M7 11l5 5 5-5M5 20h14"/></svg></span><span class="tx"><b data-tk="pageDownload"><?= $sa_txt('pageDownload') ?></b><small data-tk="<?= $sa_names[$sa_stores[0]['k']] ?>"><?= $sa_stores[0]['label'] ?></small></span><svg class="chev flip" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg></a>
<?php if ($sa_more || $sa_noplat): ?>
      <div class="stores" data-el="stores"<?= $sa_hid('stores') ?>>
<?php if ($sa_more): ?>
        <span class="cap" data-tk="pageStoreCaption"><?= $sa_txt('pageStoreCaption') ?></span>
        <div class="row">
<?php foreach ($sa_more as $st): ?>
          <a class="store" href="<?= htmlspecialchars($st['url'], ENT_QUOTES) ?>" target="_blank" rel="noopener noreferrer"><?= $sa_glyph[$st['k']] ?><span data-tk="<?= $sa_names[$st['k']] ?>"><?= $st['label'] ?></span></a>
<?php endforeach; ?>
        </div>
<?php endif; ?>
<?php if ($sa_noplat): ?>
        <span class="cap" data-tk="pageNoPlatform"><?= $sa_txt('pageNoPlatform') ?></span>
<?php endif; ?>
      </div>
<?php endif; ?>
    </div>
<?php endif; ?>
    <button type="button" class="btn" id="retry" data-el="retry"<?= $sa_hid('retry') ?>><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 12a8 8 0 1 1-2.3-5.7M20 4v5h-5"/></svg><span data-tk="pageRetry"><?= $sa_txt('pageRetry') ?></span></button>
  </div>
</main>
<script>
(function () {
  var deep = <?= json_encode($sa_deep, $sa_json) ?>;
  var words = <?= json_encode($sa_words, $sa_json) ?>;
  window.__words = words;
  var demo = <?= $sa_demo !== '' ? 'true' : 'false' ?>;
  var card = document.getElementById('card'), status = document.getElementById('status'), note = document.getElementById('note');
  var timer = 0, left = false;
  function show(state) {
    if (card.getAttribute('data-state') === state) return;
    card.setAttribute('data-state', state);
    status.textContent = words[state].status;
    note.textContent = words[state].note;
    [status.parentNode, note].forEach(function (el) {
      el.classList.remove('swap'); void el.offsetWidth; el.classList.add('swap');
    });
  }
  // sends the phone on to the app; if the page is still in front a few
  // seconds later, nothing took the address - the app is not installed
  function go() {
    left = false;
    clearTimeout(timer);
    show('opening');
    if (demo) return;
    window.location.href = deep;
    timer = setTimeout(function () { if (!left) show('missing'); }, 3000);
  }
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) {
      if (card.getAttribute('data-state') === 'opening') { left = true; clearTimeout(timer); }
    } else if (left) {
      left = false;
      show('opened');
    }
  });
  window.addEventListener('pagehide', function () { if (card.getAttribute('data-state') === 'opening') left = true; });
  document.getElementById('open').addEventListener('click', function (e) { e.preventDefault(); go(); });
  document.getElementById('retry').addEventListener('click', go);
  if (!demo) setTimeout(go, 600);

<?php if ($sa_edit): ?>
  // The admin's editor (panel/subapp_edit.php) - the page is the tool. A part is
  // picked by touching it, dragged to move it (up and down: its place; to the
  // sides: its side), the logo is pulled by its corner to resize, a word is edited
  // by double-tapping it, Delete takes a part away and a tap on a faded one brings
  // it back. Every change goes to the editor (which keeps the history and saves).
  (function () {
    var KEYS = <?= json_encode($sa_tk, $sa_json) ?>, APP = <?= json_encode($sa_app['name'], $sa_json) ?>;
    var lay = <?= json_encode($sa_lay, $sa_json) ?>;
    var SIZES = { sm: 72, lg: 128, xl: 152 }, RAD = { squircle: .27, circle: .5, square: .1 };
    var BLOCK = { stage: '.stage', title: 'h1', chip: '.chip', status: '.status', actions: '.col' };
    var OWNER = { logo: 'stage', flow: 'stage', title: 'title', chip: 'chip', status: 'status', actions: 'actions', note: 'actions', download: 'actions', stores: 'actions', retry: 'actions' };
    var SIDED = { title: 1, chip: 1, status: 1 };
    var RTL = document.documentElement.dir === 'rtl', MIN = 56, MAX = 200;
    var EASE = 'cubic-bezier(.23,1,.32,1)';
    var reduced = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
    var L = { del: 'Remove', back: 'Bring back', shape: 'Shape' };
    var B = document.body, sel = '', drag = null, resize = null, editing = null, zonesEl, gapEl, barEl, handleEl, tipEl;
    B.classList.add('ed');
    var el = function (id) { return document.querySelector('[data-el="' + id + '"]'); };
    var els = function (id) { return document.querySelectorAll('[data-el="' + id + '"]'); };
    var blockEl = function (b) { return document.querySelector(BLOCK[b]); };
    var isOff = function (id) { return lay.hide.indexOf(id) >= 0; };
    var ui = function (tag, cls, parent) { var n = document.createElement(tag); n.className = cls; (parent || B).appendChild(n); return n; };
    function tell(m) { parent.postMessage(m, location.origin); }
    function tellLay() { tell({ t: 'lay', lay: lay }); }

    // ---- the layout, applied (also what the editor sends back: undo, redo) ----
    function sideOf(b) { return b === 'stage' ? lay.stageAlign : (lay.balign && lay.balign[b]) || lay.align; }
    function apply() {
      document.querySelectorAll('[data-el]').forEach(function (n) {
        var id = n.getAttribute('data-el');
        if (id === 'stage') return;
        if (isOff(id)) n.setAttribute('data-off', '1'); else n.removeAttribute('data-off');
      });
      var st = blockEl('stage');
      if (isOff('logo') && isOff('flow')) st.setAttribute('data-off', '1'); else st.removeAttribute('data-off');
      lay.order.forEach(function (b, i) { blockEl(b).style.order = i; });
      st.style.justifyContent = { center: 'center', start: 'flex-start', end: 'flex-end' }[lay.stageAlign];
      Object.keys(SIDED).forEach(function (b) {
        var n = blockEl(b), v = sideOf(b);
        n.style.alignSelf = { center: 'center', start: 'flex-start', end: 'flex-end' }[v];
        n.style.textAlign = v;
      });
      B.setAttribute('data-align', lay.align);
      var px = lay.px > 0 ? lay.px : SIZES[lay.size], min = B.getAttribute('data-look') === 'minimal';
      if (px) B.style.setProperty('--ico', px + 'px'); else B.style.removeProperty('--ico');
      if (lay.shape !== 'squircle' || lay.size !== 'md' || lay.px > 0) B.style.setProperty('--rad', 'calc(var(--ico,' + (min ? 112 : 96) + 'px)*' + RAD[lay.shape] + ')'); else B.style.removeProperty('--rad');
      place();
    }
    // a change of layout whose parts glide from where they were (FLIP) - not a jump
    function glide(change, keep) {
      var names = Object.keys(BLOCK), before = {};
      names.forEach(function (b) { before[b] = blockEl(b).getBoundingClientRect(); });
      change();
      if (reduced) return;
      names.forEach(function (b) {
        if (b === keep) return;
        var n = blockEl(b), a = n.getBoundingClientRect(), dx = before[b].left - a.left, dy = before[b].top - a.top;
        if (Math.abs(dx) < 1 && Math.abs(dy) < 1) return;
        n.animate([{ transform: 'translate(' + dx + 'px,' + dy + 'px)' }, { transform: 'none' }], { duration: 240, easing: EASE });
        if (OWNER[sel] === b) ride(dx, dy, 240);
      });
    }
    // the bar and the handle go with the part they belong to: placed where it ends up, they glide from where it was
    function ride(dx, dy, ms) {
      if (reduced) return;
      [barEl, handleEl].forEach(function (n) {
        if (n && n.style.display !== 'none') n.animate([{ transform: 'translate(' + dx + 'px,' + dy + 'px)' }, { transform: 'none' }], { duration: ms, easing: EASE });
      });
    }
    function change(fn, keep) { glide(function () { fn(); apply(); }, keep); tellLay(); }

    // ---- what is picked: an outline, a small bar, the logo's handle ----
    function select(id) {
      document.querySelectorAll('.sel').forEach(function (x) { x.classList.remove('sel'); });
      sel = id || '';
      if (sel) els(sel).forEach(function (x) { x.classList.add('sel'); });
      place();
      tell({ t: 'sel', el: sel });
    }
    function rectOf(id) {
      var r = null;
      els(id).forEach(function (n) {
        var q = n.getBoundingClientRect();
        if (!q.width) return;
        r = r ? { left: Math.min(r.left, q.left), top: Math.min(r.top, q.top), right: Math.max(r.right, q.right), bottom: Math.max(r.bottom, q.bottom) } : { left: q.left, top: q.top, right: q.right, bottom: q.bottom };
      });
      return r;
    }
    function place() {
      if (!barEl) return;
      var r = sel ? rectOf(sel) : null;
      // the block of buttons is moved, never taken away: the way in to the app stays
      barEl.style.display = r && sel !== 'actions' && !drag && !resize && !editing ? 'flex' : 'none';
      handleEl.style.display = r && sel === 'logo' && !isOff('logo') && !drag && !editing ? 'block' : 'none';
      if (!r) return;
      var w = barEl.offsetWidth || 80, left = Math.max(6, Math.min(innerWidth - w - 6, (r.left + r.right) / 2 - w / 2));
      barEl.style.left = left + 'px';
      barEl.style.top = (r.top - 52 > 6 ? r.top - 52 : r.bottom + 10) + 'px';
      barEl.querySelector('[data-a=del]').textContent = isOff(sel) ? '↩' : '🗑';
      barEl.querySelector('[data-a=del]').setAttribute('aria-label', isOff(sel) ? L.back : L.del);
      barEl.querySelector('[data-a=shape]').style.display = sel === 'logo' && !isOff('logo') ? '' : 'none';
      handleEl.style.left = Math.min(r.right, innerWidth - 14) + 'px';
      handleEl.style.top = Math.min(r.bottom, innerHeight - 14) + 'px';
    }
    function toggleOff(id) {
      if (id === 'actions') return;
      change(function () { var i = lay.hide.indexOf(id); if (i >= 0) lay.hide.splice(i, 1); else lay.hide.push(id); });
    }

    // ---- words: written where they are, with a double tap ----
    function words(key, val) {
      var v = String(val).replace(/\{app\}/g, APP);
      Object.keys(KEYS).forEach(function (st) {
        ['status', 'note'].forEach(function (f) { if (KEYS[st][f] === key) words_[st][f] = v; });
      });
      document.querySelectorAll('[data-tk="' + key + '"]').forEach(function (n) { n.textContent = v; });
    }
    var words_ = window.__words;
    function startEdit(n) {
      if (editing || isOff(n.closest('[data-el]') ? n.closest('[data-el]').getAttribute('data-el') : '')) return;
      editing = { n: n, orig: n.textContent, key: n.getAttribute('data-tk') };
      try { n.contentEditable = 'plaintext-only'; } catch (x) {}
      if (n.contentEditable !== 'plaintext-only') n.contentEditable = 'true';
      n.focus();
      var r = document.createRange(); r.selectNodeContents(n);
      var s = getSelection(); s.removeAllRanges(); s.addRange(r);
      place();
    }
    function endEdit(commit) {
      if (!editing) return;
      var e = editing; editing = null;
      e.n.contentEditable = 'false'; e.n.removeAttribute('contenteditable');
      var val = e.n.textContent.trim();
      if (!commit || val === '' || val === e.orig) { e.n.textContent = e.orig; place(); return; }
      var back = APP ? val.split(APP).join('{app}') : val;
      words(e.key, back);
      tell({ t: 'text', key: e.key, val: back });
      place();
    }

    // ---- drag: 1:1 under the finger from where it was grabbed; the others make way ----
    function visibleBlocks() {
      return lay.order.filter(function (b) { var n = blockEl(b); return n && !n.hasAttribute('data-hide') && n.getBoundingClientRect().height > 0; });
    }
    function zoneOf(x) {
      var c = blockEl('stage').parentNode.getBoundingClientRect(), k = Math.max(0, Math.min(2, Math.floor((x - c.left) / (c.width / 3))));
      return k;
    }
    // a zone is a place on the screen; a side is the page's own start / end
    function sideOfZone(k) { return k === 1 ? 'center' : ((k === 0) !== RTL ? 'start' : 'end'); }
    function zoneOfSide(v) { return v === 'center' ? 1 : ((v === 'start') !== RTL ? 0 : 2); }
    function dragStart(e) {
      var d = drag; d.on = true;
      d.be.classList.add('dragging');
      select(d.id);
      if (d.blk !== 'actions') {
        var c = d.be.parentNode.getBoundingClientRect();
        zonesEl.style.display = 'block'; zonesEl.style.left = c.left + 'px'; zonesEl.style.width = c.width + 'px';
      }
      place();
    }
    function dragMove(e) {
      var d = drag, r = d.be.getBoundingClientRect(), bl = r.left - d.tx, bt = r.top - d.ty;
      d.tx = e.clientX - d.gx - bl; d.ty = e.clientY - d.gy - bt;
      d.be.style.transform = 'translate(' + d.tx + 'px,' + d.ty + 'px)';
      // the others make way: the dragged part takes the place its pointer is at
      var vis = visibleBlocks(), others = vis.filter(function (b) { return b !== d.blk; }), idx = 0;
      others.forEach(function (b) { var q = blockEl(b).getBoundingClientRect(); if (q.top + q.height / 2 < e.clientY) idx++; });
      var cur = vis.indexOf(d.blk);
      if (idx !== cur) {
        glide(function () {
          var o = lay.order.filter(function (b) { return b !== d.blk; });
          // the place among all blocks, hidden ones included: after the idx-th visible other
          var at = idx === 0 ? 0 : o.indexOf(others[idx - 1]) + 1;
          o.splice(at, 0, d.blk); lay.order = o;
          d.be.style.transform = 'none'; apply();
          var r2 = d.be.getBoundingClientRect();
          d.tx = e.clientX - d.gx - (r2.left); d.ty = e.clientY - d.gy - (r2.top);
          d.be.style.transform = 'translate(' + d.tx + 'px,' + d.ty + 'px)';
        }, d.blk);
      }
      if (d.blk !== 'actions') {
        var k = zoneOf(e.clientX);
        zonesEl.querySelectorAll('i').forEach(function (z, i) { z.classList.toggle('on', i === k); });
        d.zone = k;
      }
    }
    function dragEnd(commit) {
      var d = drag; drag = null;
      zonesEl.style.display = 'none';
      var from = d.be.getBoundingClientRect();
      d.be.classList.remove('dragging'); d.be.style.transform = '';
      if (commit && d.blk !== 'actions' && d.zone !== undefined) {
        var v = sideOfZone(d.zone);
        if (d.blk === 'stage') lay.stageAlign = v; else { lay.balign = lay.balign || {}; if (v === lay.align) delete lay.balign[d.blk]; else lay.balign[d.blk] = v; }
      }
      if (!commit) lay = JSON.parse(JSON.stringify(d.snap));
      apply();
      // it settles from where it was let go (no seam between the hand and the animation)
      var to = d.be.getBoundingClientRect(), dx = from.left - to.left, dy = from.top - to.top;
      place();
      if (!reduced && (Math.abs(dx) > 1 || Math.abs(dy) > 1)) {
        d.be.animate([{ transform: 'translate(' + dx + 'px,' + dy + 'px)' }, { transform: 'none' }], { duration: 260, easing: EASE });
        ride(dx, dy, 260);
      }
      if (commit && JSON.stringify(lay) !== JSON.stringify(d.snap)) tellLay(); else if (!commit) tellLay();
    }
    function pick(t) { var n = t.closest('[data-el]'); return n && n.getAttribute('data-el') !== 'stage' ? n.getAttribute('data-el') : ''; }
    card.addEventListener('pointerdown', function (e) {
      if (editing) { if (!e.target.closest('[contenteditable]')) endEdit(true); else return; }
      if (e.button > 0 || e.target.closest('.ed-bar,.ed-h')) return;
      var id = pick(e.target) || (e.target.closest('.stage') ? 'logo' : '');
      if (!id) { select(''); return; }
      select(id);
      var blk = OWNER[id], be = blockEl(blk), r = be.getBoundingClientRect();
      drag = { id: id, blk: blk, be: be, sx: e.clientX, sy: e.clientY, gx: e.clientX - r.left, gy: e.clientY - r.top, tx: 0, ty: 0, on: false, pid: e.pointerId, snap: JSON.parse(JSON.stringify(lay)) };
      try { card.setPointerCapture(e.pointerId); } catch (x) {}
    });
    card.addEventListener('pointermove', function (e) {
      if (!drag || e.pointerId !== drag.pid) return;
      if (!drag.on) { if (Math.hypot(e.clientX - drag.sx, e.clientY - drag.sy) < 6) return; dragStart(e); }
      dragMove(e);
    });
    // a second tap on a word, near the first, opens it for writing. (The browser's own dblclick is aimed at the
    // part that holds the pointer - the card - not at the word, and does not come from a finger at all.)
    var lastTap = { t: 0, x: 0, y: 0 };
    function editAt(x, y) {
      var t = document.elementFromPoint(x, y), n = t && t.closest ? t.closest('[data-tk]') : null;
      if (n && card.contains(n)) startEdit(n);
    }
    function tapped(e) {
      var near = Math.hypot(e.clientX - lastTap.x, e.clientY - lastTap.y) < 24;
      if (e.timeStamp - lastTap.t < 400 && near) { lastTap.t = 0; editAt(e.clientX, e.clientY); }
      else lastTap = { t: e.timeStamp, x: e.clientX, y: e.clientY };
    }
    var up = function (commit) { return function (e) { if (drag && e.pointerId === drag.pid) { if (drag.on) dragEnd(commit); else { drag = null; if (commit) tapped(e); } } }; };
    card.addEventListener('pointerup', up(true));
    card.addEventListener('pointercancel', up(false));

    // ---- resize: the logo's corner, 1:1 ----
    function resizeStart(e) {
      var w = el('logo').getBoundingClientRect().width;
      resize = { sx: e.clientX, sy: e.clientY, w0: w, pid: e.pointerId };
      handleEl.setPointerCapture(e.pointerId);
      place();
    }
    function resizeMove(e) {
      var dx = e.clientX - resize.sx, dy = e.clientY - resize.sy, d = Math.abs(dx) > Math.abs(dy) ? dx : dy;
      var px = Math.round(Math.max(MIN, Math.min(MAX, resize.w0 + d * 2)));
      lay.px = px; apply();
      var r = rectOf('logo');
      handleEl.style.display = 'block'; barEl.style.display = 'none';
      handleEl.style.left = Math.min(r.right, innerWidth - 14) + 'px'; handleEl.style.top = Math.min(r.bottom, innerHeight - 14) + 'px';
      tipEl.style.display = 'block'; tipEl.textContent = px + 'px';
      tipEl.style.left = Math.max(6, Math.min(r.right - 70, innerWidth - 66)) + 'px'; tipEl.style.top = Math.max(6, r.top - 34) + 'px';
    }
    function resizeEnd() { resize = null; tipEl.style.display = 'none'; place(); tellLay(); }

    // ---- keys: the same things without a pointer ----
    function nudge(dir) {
      var id = sel, blk = OWNER[id]; if (!blk) return;
      if (dir === 'up' || dir === 'down') {
        var vis = visibleBlocks(), i = vis.indexOf(blk), j = dir === 'up' ? i - 1 : i + 1;
        if (j < 0 || j >= vis.length) return;
        change(function () { var a = lay.order.indexOf(blk), b = lay.order.indexOf(vis[j]); lay.order[a] = vis[j]; lay.order[b] = blk; });
      } else if (blk !== 'actions') {
        var k = Math.max(0, Math.min(2, zoneOfSide(sideOf(blk)) + (dir === 'right' ? 1 : -1))), v = sideOfZone(k);
        change(function () { if (blk === 'stage') lay.stageAlign = v; else { lay.balign = lay.balign || {}; if (v === lay.align) delete lay.balign[blk]; else lay.balign[blk] = v; } });
      }
    }
    document.addEventListener('keydown', function (e) {
      if (editing) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); endEdit(true); }
        else if (e.key === 'Escape') { e.preventDefault(); endEdit(false); }
        return;
      }
      if ((e.ctrlKey || e.metaKey) && (e.key === 'z' || e.key === 'y' || e.key === 'Z')) { e.preventDefault(); tell({ t: 'undo', redo: e.key === 'y' || e.shiftKey }); return; }
      if (!sel) return;
      if (e.key === 'Delete' || e.key === 'Backspace') { e.preventDefault(); toggleOff(sel); }
      else if (e.key === 'Escape') select('');
      else if (e.key === 'Enter' || e.key === 'F2') { var tk = el(sel).querySelector('[data-tk]') || (el(sel).hasAttribute('data-tk') ? el(sel) : null); if (tk) { e.preventDefault(); startEdit(tk); } }
      else if (e.key.indexOf('Arrow') === 0) { e.preventDefault(); nudge({ ArrowUp: 'up', ArrowDown: 'down', ArrowLeft: 'left', ArrowRight: 'right' }[e.key]); }
    });
    card.addEventListener('dblclick', function (e) { if (!drag) { e.preventDefault(); editAt(e.clientX, e.clientY); } });
    card.addEventListener('click', function (e) { e.preventDefault(); }, true);
    document.addEventListener('pointerdown', function (e) { if (!e.target.closest('#card,.ed-bar,.ed-h')) select(''); });

    // ---- the tools, built once ----
    zonesEl = ui('div', 'ed-zones'); zonesEl.innerHTML = '<i></i><i></i><i></i>';
    gapEl = ui('div', 'ed-gap');
    barEl = ui('div', 'ed-bar'); barEl.innerHTML = '<button type="button" data-a="shape">◯</button><button type="button" data-a="del">🗑</button>';
    handleEl = ui('div', 'ed-h'); tipEl = ui('div', 'ed-tip');
    barEl.addEventListener('pointerdown', function (e) { e.stopPropagation(); });
    barEl.addEventListener('click', function (e) {
      var a = e.target.closest('button'); if (!a) return;
      if (a.getAttribute('data-a') === 'del') toggleOff(sel);
      else if (a.getAttribute('data-a') === 'shape') change(function () { lay.shape = { squircle: 'circle', circle: 'square', square: 'squircle' }[lay.shape]; });
    });
    handleEl.addEventListener('pointerdown', function (e) { e.preventDefault(); e.stopPropagation(); resizeStart(e); });
    handleEl.addEventListener('pointermove', function (e) { if (resize && e.pointerId === resize.pid) resizeMove(e); });
    handleEl.addEventListener('pointerup', function () { if (resize) resizeEnd(); });
    handleEl.addEventListener('pointercancel', function () { if (resize) resizeEnd(); });
    window.addEventListener('resize', place); window.addEventListener('scroll', place, true);
    document.querySelectorAll('[data-el]').forEach(function (n) { n.tabIndex = 0; n.addEventListener('focus', function () { if (!drag) select(n.getAttribute('data-el') === 'stage' ? 'logo' : n.getAttribute('data-el')); }); });

    // ---- the editor talks to the page ----
    window.addEventListener('message', function (e) {
      if (e.origin !== location.origin || !e.data) return;
      var m = e.data;
      if (m.t === 'layout') { lay = m.lay; glide(apply); }
      else if (m.t === 'text') words(m.key, m.val);
      else if (m.t === 'state') { show(m.v); var k = KEYS[card.getAttribute('data-state')]; status.setAttribute('data-tk', k.status); note.setAttribute('data-tk', k.note); place(); }
      else if (m.t === 'i18n') { L = Object.assign(L, m.L); }
      else if (m.t === 'select') select(m.el || '');
    });
    window.addEventListener('load', function () { apply(); tell({ t: 'ready' }); });
  })();
<?php endif; ?>
})();
</script>
<?php else: ?>
<main class="card err enter" id="card"><p><?= $sa_txt('pageError') ?></p></main>
<?php endif; ?>
</body>
</html>
