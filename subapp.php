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
// the look of the owner's language - the words and the motion are the same in both
$sa_look = $sa_demo !== '' ? $sa_demo : subapp_look($sa_lang);
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
$sa_dl = $sa_stores ? $sa_stores[0]['url'] : '';
// what the page says in each of its three states (the first is what it opens in;
// a preview can ask for another)
$sa_words = [
    'opening' => ['status' => $sa_raw('pageOpening'), 'note' => $sa_raw('pageHint')],
    'missing' => ['status' => $sa_raw('pageMissingTitle'), 'note' => $sa_raw('pageMissing')],
    'opened' => ['status' => '', 'note' => $sa_raw('pageOpened')],
];
$sa_state = ($sa_demo !== '' && isset($sa_words[(string) ($_GET['st'] ?? '')])) ? (string) $_GET['st'] : 'opening';
$sa_min = $sa_look === 'minimal';
?><!doctype html>
<html lang="<?= $sa_lang ?>" dir="<?= $sa_rtl ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="color-scheme" content="dark light">
<meta name="theme-color" content="<?= $sa_min ? '#0b0b10' : '#0a0e16' ?>" media="(prefers-color-scheme: dark)">
<meta name="theme-color" content="<?= $sa_min ? '#f3f5f9' : '#f1f4fa' ?>" media="(prefers-color-scheme: light)">
<title><?= $sa_ok ? htmlspecialchars($sa_app['name']) : '⛔️' ?></title>
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
@media (prefers-color-scheme:light){:root{
  --bg:#f1f4fa;--glass:rgba(255,255,255,.74);--line:rgba(16,24,40,.1);--text:#101828;--muted:#566277;--chip:rgba(16,24,40,.05);--warn:#b86e00;--ok:#0f9d63;
  --glowA:<?= $sa_mix($sa_app['accent'], '#f1f4fa', .52) ?>;--glowB:<?= $sa_mix($sa_app['accent2'], '#f1f4fa', .46) ?>;--glowC:<?= $sa_mix($sa_app['accent'], '#f1f4fa', .3) ?>;
  --glass:rgba(255,255,255,.5);--cardline:rgba(255,255,255,.85);--sat:1.6;
  --shadow:0 28px 60px -26px rgba(16,24,40,.38),inset 0 1px 0 rgba(255,255,255,.9);
}}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html{-webkit-text-size-adjust:100%}
body{margin:0;min-height:100vh;min-height:100dvh;display:flex;align-items:center;justify-content:center;padding:max(20px,env(safe-area-inset-top)) 20px max(20px,env(safe-area-inset-bottom));color:var(--text);
  font:400 16px/1.8 Estedad,system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif;
  background:radial-gradient(75% 46% at 10% 0,var(--glowA) 0,transparent 72%),radial-gradient(62% 40% at 100% 34%,var(--glowB) 0,transparent 74%),radial-gradient(80% 36% at 30% 100%,var(--glowC) 0,transparent 76%),var(--bg);background-attachment:fixed}
.card{width:100%;max-width:400px;display:flex;flex-direction:column;align-items:center;text-align:center;padding:30px 22px 22px;border-radius:30px;background:var(--glass);border:1px solid var(--cardline);box-shadow:var(--shadow);-webkit-backdrop-filter:blur(22px) saturate(var(--sat));backdrop-filter:blur(22px) saturate(var(--sat))}
.err{padding:34px 22px}
.err p{margin:0;font-size:15.5px;color:var(--muted)}

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
.dl .tx small,.dl .chev{display:none}
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

/* B - minimal: flat colours instead of the glass, a pill for the main button, a
   row for the download. The same font, words, stage and motion as A. */
[data-look=minimal]{--bg:#0b0b10;--chip:#17171e;--line:rgba(255,255,255,.09);--text:#f4f4f8;--muted:#a0a0ae;--soft:rgba(255,255,255,.08);--accentText:<?= $sa_mix($sa_app['accent'], '#ffffff', .5) ?>;--ico:112px;--rad:32px;background:var(--bg)}
@media (prefers-color-scheme:light){[data-look=minimal]{--bg:#f3f5f9;--chip:#fff;--line:rgba(15,23,42,.08);--text:#0f172a;--muted:#586377;--soft:<?= $sa_mix($sa_app['accent'], '#ffffff', .12) ?>;--accentText:<?= $sa_app['accent'] ?>}}
[data-look=minimal] .card{background:none;border:0;box-shadow:none;-webkit-backdrop-filter:none;backdrop-filter:none;padding:8px 2px}
[data-look=minimal] .node{background:var(--soft);border-color:transparent}
[data-look=minimal] .app{font-size:46px;background:linear-gradient(150deg,var(--accent2),var(--accent));box-shadow:inset 0 1.5px 0 rgba(255,255,255,.38),0 22px 40px -16px var(--accent)}
[data-look=minimal] h1{margin-top:24px;font-size:31px;letter-spacing:-.3px}
[data-look=minimal] .chip{margin-top:6px;padding:0;background:none;font-size:15px}
[data-look=minimal] .primary,[data-look=minimal] [data-state=missing] .dl{border:0;border-radius:999px;min-height:56px;font-size:17px;background:var(--accent);color:#fff;box-shadow:0 14px 26px -14px var(--accent)}
[data-look=minimal] .dl{justify-content:flex-start;gap:13px;min-height:0;padding:14px 16px;border-radius:20px;background:var(--chip);color:var(--text);font-weight:700;font-size:15.5px;box-shadow:none;text-align:start}
[data-look=minimal] .dl .ic{display:grid;place-items:center;flex:none;width:38px;height:38px;border-radius:12px;background:var(--soft);color:var(--accentText)}
[data-look=minimal] .dl .tx{display:flex;flex:1;min-width:0;flex-direction:column;align-items:flex-start;line-height:1.55}
[data-look=minimal] .dl .tx small{display:block;font-size:12.5px;font-weight:500;color:var(--muted)}
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
<body data-look="<?= $sa_look ?>">
<?php if ($sa_ok): ?>
<main class="card enter" id="card" data-state="<?= $sa_state ?>">
  <div class="stage" aria-hidden="true">
    <div class="node"><svg viewBox="0 0 24 24"><path d="M12 3 4.5 6v5.5c0 4.6 3.1 8.1 7.5 9.5 4.4-1.4 7.5-4.9 7.5-9.5V6z"/><path d="m9 12 2.2 2.2L15.2 10"/></svg></div>
    <div class="track"><i></i><i></i><i></i></div>
    <div class="appwrap">
      <div class="app"><span><?= htmlspecialchars(strtoupper(substr($sa_app['name'], 0, 1))) ?></span><img src="subapp_assets/icons/<?= htmlspecialchars($sa_app['icon']) ?>.webp" alt="" width="96" height="96" onerror="this.remove()"></div>
      <div class="badge"><svg class="b-ok" viewBox="0 0 24 24"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg><svg class="b-warn" viewBox="0 0 24 24"><path d="M12 6.5v7M12 17.5h.01"/></svg></div>
    </div>
  </div>
  <h1><?= htmlspecialchars($sa_app['name']) ?></h1>
  <div class="chip"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/></svg><span><?= $sa_txt('pageService') ?></span><b><?= htmlspecialchars((string) $sa_inv['username']) ?></b></div>
  <div class="status" role="status" aria-live="polite"><i class="dot"></i><span id="status"><?= htmlspecialchars($sa_words[$sa_state]['status'], ENT_QUOTES, 'UTF-8') ?></span></div>
  <div class="col">
    <a class="btn primary" id="open" href="<?= htmlspecialchars($sa_deep, ENT_QUOTES) ?>"><span><?= $sa_txt('pageButton') ?></span><svg class="flip" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
    <p class="note" id="note"><?= htmlspecialchars($sa_words[$sa_state]['note'], ENT_QUOTES, 'UTF-8') ?></p>
<?php if ($sa_dl !== ''): ?>
    <div class="dlbox">
      <a class="btn dl" href="<?= htmlspecialchars($sa_dl, ENT_QUOTES) ?>" target="_blank" rel="noopener noreferrer"><span class="ic"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v11M7 11l5 5 5-5M5 20h14"/></svg></span><span class="tx"><b><?= $sa_txt('pageDownload') ?></b><small><?= $sa_stores[0]['label'] ?></small></span><svg class="chev flip" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg></a>
      <div class="stores">
        <span class="cap"><?= $sa_txt('pageStoreCaption') ?></span>
        <div class="row">
<?php foreach ($sa_stores as $st): ?>
          <a class="store" href="<?= htmlspecialchars($st['url'], ENT_QUOTES) ?>" target="_blank" rel="noopener noreferrer"><?= $sa_glyph[$st['k']] ?><span><?= $st['label'] ?></span></a>
<?php endforeach; ?>
        </div>
<?php if ($sa_noplat): ?>
        <span class="cap"><?= $sa_txt('pageNoPlatform') ?></span>
<?php endif; ?>
      </div>
    </div>
<?php endif; ?>
    <button type="button" class="btn" id="retry"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 12a8 8 0 1 1-2.3-5.7M20 4v5h-5"/></svg><span><?= $sa_txt('pageRetry') ?></span></button>
  </div>
</main>
<script>
(function () {
  var deep = <?= json_encode($sa_deep, $sa_json) ?>;
  var words = <?= json_encode($sa_words, $sa_json) ?>;
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
})();
</script>
<?php else: ?>
<main class="card err enter" id="card"><p><?= $sa_txt('pageError') ?></p></main>
<?php endif; ?>
</body>
</html>
