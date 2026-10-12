<?php
// 📲 افزودن به برنامه: the page one app of a service's app list opens
// (🛍 سرویس‌های من → 📲). Its address names the service and the app and
// carries the bot's signature, never the subscription itself (subapp_link),
// so a link the bot did not hand out opens nothing. The page sends the phone
// on to the app with the subscription in it, with a button to do the same by
// hand and the app's download page under it - in the service owner's
// language, its words from 🎨 (🛍 سرویس‌های من → 🌐 صفحه‌ی باز کردن برنامه).
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
if (preg_match('/^[A-Za-z0-9]{1,64}$/', $sa_id) && isset($sa_apps[$sa_appId]) && hash_equals(subapp_sig($sa_id, $sa_appId), (string) ($_GET['s'] ?? ''))) {
    $sa_inv = select("invoice", "*", "id_invoice", $sa_id, "select");
}
$sa_owner = is_array($sa_inv) ? select("user", "*", "id", (string) $sa_inv['id_user'], "select") : false;
$sa_lang = (is_array($sa_owner) && in_array($sa_owner['lang'] ?? '', panel_langs(), true)) ? $sa_owner['lang'] : 'fa';
$sa_t = lang_tab_texts($sa_lang)['users']['subapps'];
$sa_sub = '';
if (is_array($sa_inv) && in_array($sa_inv['Status'], ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'], true)) {
    $sa_panel = select("marzban_panel", "*", "name_panel", $sa_inv['Service_location'], "select");
    if (is_array($sa_panel) && !in_array($sa_panel['type'], ['ibsng', 'mikrotik'], true)) {
        $sa_data = (new ManagePanel())->DataUser($sa_inv['Service_location'], $sa_inv['username']);
        $sa_sub = (string) ($sa_data['subscription_url'] ?? '');
    }
}
$sa_ok = strpos($sa_sub, 'http') === 0;
$sa_app = $sa_apps[$sa_appId] ?? ['name' => ''];
// 🎨's words as plain text: {app} is the app's name
$sa_txt = fn($k) => htmlspecialchars(strtr(strip_tags((string) $sa_t[$k]), ['{app}' => $sa_app['name']]), ENT_QUOTES, 'UTF-8');
if ($sa_ok) {
    $sa_deep = subapp_deep_link($sa_appId, $sa_sub, $sa_inv['username']);
    // its download page on this phone
    $sa_ua = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $sa_os = preg_match('/iphone|ipad|ipod|macintosh/', $sa_ua) ? 'ios' : (strpos($sa_ua, 'android') !== false ? 'android' : 'other');
    $sa_dl = $sa_app[$sa_os] !== '' ? $sa_app[$sa_os] : $sa_app['other'];
} else {
    http_response_code(404);
}
?><!doctype html>
<html lang="<?= $sa_lang ?>" dir="<?= $sa_lang === 'fa' ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= $sa_ok ? htmlspecialchars($sa_app['name']) : '⛔️' ?></title>
<style>
:root{--bg:#eef2f8;--card:#fff;--text:#18202e;--muted:#5d6779;--line:#dde3ee;--accent:#2b6cf0}
@media (prefers-color-scheme:dark){:root{--bg:#0f141c;--card:#18202b;--text:#e7ecf4;--muted:#9aa4b6;--line:#2a3342;--accent:#4f86ff}}
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:16px;background:var(--bg);color:var(--text);font:16px/1.7 system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif}
.card{width:100%;max-width:380px;background:var(--card);border:1px solid var(--line);border-radius:20px;padding:28px 22px;text-align:center}
.icon{font-size:44px;line-height:1}
h1{margin:10px 0 4px;font-size:1.35rem}
p{margin:0 0 6px;color:var(--muted);font-size:.95rem}
.btn{display:block;margin-top:14px;padding:14px 16px;border-radius:14px;text-decoration:none;font-weight:700}
.open{background:var(--accent);color:#fff}
.dl{border:1px solid var(--line);color:var(--text)}
.hint{margin-top:18px;font-size:.85rem}
</style>
</head>
<body>
<main class="card">
<?php if ($sa_ok): ?>
  <div class="icon">📲</div>
  <h1><?= htmlspecialchars($sa_app['name']) ?></h1>
  <p><?= $sa_txt('pageOpening') ?></p>
  <a class="btn open" id="open" href="<?= htmlspecialchars($sa_deep, ENT_QUOTES) ?>"><?= $sa_txt('pageButton') ?></a>
  <p class="hint"><?= $sa_txt('pageHint') ?></p>
  <a class="btn dl" href="<?= htmlspecialchars($sa_dl, ENT_QUOTES) ?>" rel="noopener noreferrer"><?= $sa_txt('pageDownload') ?></a>
<?php else: ?>
  <p><?= $sa_txt('pageError') ?></p>
<?php endif; ?>
</main>
<?php if ($sa_ok): ?>
<script>setTimeout(function () { window.location.href = <?= json_encode($sa_deep, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>; }, 400);</script>
<?php endif; ?>
</body>
</html>
