<?php
// 📋 کپی QR: the page the «📋 کپی QR» button of a service opens (🛍 سرویس‌های من,
// the purchase / test / 🎁 messages and ⏱). A telegram bot cannot put a picture on
// someone's clipboard, so the picture is copied from here: the QR of the service's
// subscription and a tap on it copies it. Its
// address names the service and carries the bot's signature (qrcopy_link), never
// the subscription itself, so a link the bot did not hand out shows nothing; the
// page and its words are in the service owner's language (🎨 → 📲 → 📋 کپی QR).
// ?img=1 is the picture alone (png).
ini_set('error_log', 'error_log');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Marzban.php';
require_once __DIR__ . '/function.php';
require_once __DIR__ . '/panels.php';

header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');

$qc_id = (string) ($_GET['i'] ?? '');
$qc_inv = false;
if (preg_match('/^[A-Za-z0-9]{1,64}$/', $qc_id) && hash_equals(qrcopy_sig($qc_id), (string) ($_GET['s'] ?? ''))) {
    $qc_inv = select("invoice", "*", "id_invoice", $qc_id, "select");
}
$qc_owner = is_array($qc_inv) ? select("user", "*", "id", (string) $qc_inv['id_user'], "select") : false;
$qc_lang = (is_array($qc_owner) && in_array($qc_owner['lang'] ?? '', panel_langs(), true)) ? $qc_owner['lang'] : 'fa';
$qc_t = lang_tab_texts($qc_lang)['users']['qrcopy'];
$qc_sub = '';
if (is_array($qc_inv) && in_array($qc_inv['Status'], ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'], true)) {
    $qc_panel = select("marzban_panel", "*", "name_panel", $qc_inv['Service_location'], "select");
    if (is_array($qc_panel) && !in_array($qc_panel['type'], ['ibsng', 'mikrotik'], true)) {
        $qc_data = (new ManagePanel())->DataUser($qc_inv['Service_location'], $qc_inv['username']);
        $qc_sub = (string) ($qc_data['subscription_url'] ?? '');
    }
}
$qc_ok = strpos($qc_sub, 'http') === 0;

// the picture alone: the QR of the subscription, plain and with room around it - a few
// KB that copies at once and scans from anywhere it is pasted (the bot's own photo is
// the same code on a 1 MB background)
if ($qc_ok && isset($_GET['img'])) {
    try {
        $png = createqrcode($qc_sub, 640, 36)->getString();
        header('Content-Type: image/png');
        header('Content-Length: ' . strlen($png));
        echo $png;
    } catch (Throwable $e) {
        error_log('qrcopy.php: ' . $e->getMessage());
        http_response_code(500);
    }
    exit;
}

header('Content-Type: text/html; charset=utf-8');
if (!$qc_ok) {
    http_response_code(404);
}
$qc_rtl = $qc_lang === 'fa';
$qc_look = subapp_look($qc_lang);
$qc_theme = subapp_theme($qc_lang);
$qc_esc = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$qc_txt = fn($k) => $qc_esc(strip_tags((string) ($qc_t[$k] ?? '')));
$qc_img = 'qrcopy.php?' . http_build_query(['i' => $qc_id, 's' => qrcopy_sig($qc_id), 'img' => 1, 'v' => time()]);
$qc_json = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<!doctype html>
<html lang="<?= $qc_esc($qc_lang) ?>" dir="<?= $qc_rtl ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="color-scheme" content="<?= $qc_theme === 'auto' ? 'dark light' : $qc_theme ?>">
<title><?= $qc_ok ? $qc_txt('pageTitle') : '⛔️' ?></title>
<link rel="preload" href="subapp_assets/fonts/estedad-<?= $qc_rtl ? 'arabic' : 'latin' ?>.woff2" as="font" type="font/woff2" crossorigin>
<style>
@font-face{font-family:Estedad;font-weight:100 900;font-display:swap;src:url(subapp_assets/fonts/estedad-arabic.woff2) format("woff2");unicode-range:U+0600-06FF,U+200C-200E,U+2010-2011,U+204F,U+2E41,U+FB50-FDFF,U+FE80-FEFC}
@font-face{font-family:Estedad;font-weight:100 900;font-display:swap;src:url(subapp_assets/fonts/estedad-latin.woff2) format("woff2");unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+2000-206F,U+2074,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD}
:root{
  --bg:#0a0e16;--glass:rgba(255,255,255,.065);--line:rgba(255,255,255,.12);--text:#eef2fb;--muted:#a3aec4;--chip:rgba(255,255,255,.07);
  --accent:#4f6df5;--accent2:#7da2ff;--ok:#3ddc97;--cardline:rgba(255,255,255,.12);--sat:1;
  --glowA:#1b2a66;--glowB:#16356e;--glowC:#14224d;--shadow:0 28px 60px -26px rgba(0,0,0,.65);
  --out:cubic-bezier(.23,1,.32,1);
}
<?php ob_start(); ?>
  --bg:#f1f4fa;--glass:rgba(255,255,255,.5);--line:rgba(16,24,40,.1);--text:#101828;--muted:#566277;--chip:rgba(16,24,40,.05);--ok:#0f9d63;
  --cardline:rgba(255,255,255,.85);--sat:1.6;--glowA:#c9d3ff;--glowB:#cfe0ff;--glowC:#dbe3ff;
  --shadow:0 28px 60px -26px rgba(16,24,40,.38),inset 0 1px 0 rgba(255,255,255,.9);<?php $qc_light = ob_get_clean(); ?>
@media (prefers-color-scheme:light){[data-theme=auto]{<?= $qc_light ?>}}
[data-theme=light]{<?= $qc_light ?>;color-scheme:light}
[data-theme=dark]{color-scheme:dark}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html{-webkit-text-size-adjust:100%}
body{margin:0;min-height:100vh;min-height:100dvh;display:flex;align-items:center;justify-content:center;padding:max(20px,env(safe-area-inset-top)) 20px max(20px,env(safe-area-inset-bottom));color:var(--text);
  font:400 16px/1.8 Estedad,system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif;
  background:radial-gradient(75% 46% at 10% 0,var(--glowA) 0,transparent 72%),radial-gradient(62% 40% at 100% 34%,var(--glowB) 0,transparent 74%),radial-gradient(80% 36% at 30% 100%,var(--glowC) 0,transparent 76%),var(--bg);background-attachment:fixed}
.card{width:100%;max-width:400px;display:flex;flex-direction:column;align-items:center;text-align:center;padding:28px 22px 22px;border-radius:30px;background:var(--glass);border:1px solid var(--cardline);box-shadow:var(--shadow);-webkit-backdrop-filter:blur(22px) saturate(var(--sat));backdrop-filter:blur(22px) saturate(var(--sat));animation:rise .5s var(--out) both}
[data-look=minimal]{--bg:#0b0b10;--chip:#17171e;--line:rgba(255,255,255,.09);--text:#f4f4f8;--muted:#a0a0ae;background:var(--bg)}
@media (prefers-color-scheme:light){[data-look=minimal][data-theme=auto]{--bg:#fff;--chip:#f4f6fa;--line:rgba(15,23,42,.09);--text:#0f172a;--muted:#586377}}
[data-look=minimal][data-theme=light]{--bg:#fff;--chip:#f4f6fa;--line:rgba(15,23,42,.09);--text:#0f172a;--muted:#586377}
[data-look=minimal] .card{background:none;border:0;box-shadow:none;-webkit-backdrop-filter:none;backdrop-filter:none;padding:8px 2px}
.err{padding:34px 22px}.err p{margin:0;font-size:15.5px;color:var(--muted)}
h1{margin:0;font-size:22px;font-weight:800;line-height:1.4}
.chip{margin-top:8px;display:inline-flex;align-items:center;max-width:100%;padding:4px 14px;border-radius:999px;background:var(--chip);font-size:13.5px;color:var(--text);direction:ltr;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
/* the picture is the button: a tap on it copies it */
.qr{position:relative;margin-top:18px;width:100%;max-width:320px;aspect-ratio:1;padding:0;border:0;border-radius:22px;overflow:hidden;background:#fff;cursor:pointer;box-shadow:0 18px 40px -18px rgba(0,0,0,.55);transition:transform .16s var(--out)}
.qr:active{transform:scale(.97)}
.qr img{display:block;width:100%;height:100%;object-fit:cover}
.qr:focus-visible,.btn:focus-visible{outline:2px solid var(--accent2);outline-offset:3px}
.tick{position:absolute;top:50%;left:50%;width:84px;height:84px;margin:-42px 0 0 -42px;border-radius:50%;display:grid;place-items:center;background:var(--ok);color:#fff;box-shadow:0 12px 30px -8px rgba(0,0,0,.45);opacity:0;transform:scale(.7);transition:opacity .18s ease-out,transform .28s var(--out);pointer-events:none}
.tick svg{width:40px;height:40px;fill:none;stroke:currentColor;stroke-width:3;stroke-linecap:round;stroke-linejoin:round}
[data-state=copied] .tick{opacity:1;transform:none}
.tap{margin:14px 6px 0;font-size:13.5px;color:var(--muted);min-height:1.8em}
.btn{margin-top:14px;display:flex;align-items:center;justify-content:center;gap:10px;width:100%;min-height:54px;padding:0 18px;border-radius:17px;border:0;background:linear-gradient(135deg,var(--accent2),var(--accent));color:#fff;font:inherit;font-size:16px;font-weight:700;cursor:pointer;box-shadow:0 16px 30px -14px var(--accent);transition:transform .14s var(--out),background-color .2s var(--out),filter .2s ease;-webkit-user-select:none;user-select:none}
.btn:active{transform:scale(.97)}
.btn svg{flex:none;width:19px;height:19px;fill:none;stroke:currentColor;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}
[data-state=copied] .btn{background:var(--ok);box-shadow:0 16px 30px -14px var(--ok)}
[data-look=minimal] .btn{border-radius:999px;background:var(--accent);min-height:56px}
.fail{margin-top:16px;width:100%;padding:14px;border-radius:16px;background:var(--chip);text-align:start}
.fail p{margin:0 0 10px;font-size:13.5px;line-height:1.95;color:var(--muted)}
.fail .btn{margin-top:0;min-height:48px;font-size:15px;font-weight:600;background:var(--chip);color:var(--text);border:1px solid var(--line);box-shadow:none}
.fail[hidden]{display:none}
@keyframes rise{from{opacity:0;transform:translateY(12px) scale(.985)}to{opacity:1;transform:none}}
@media (hover:hover) and (pointer:fine){.btn:hover{filter:brightness(1.06)}.fail .btn:hover{background:var(--glass);filter:none}}
@media (prefers-reduced-motion:reduce){.card{animation:none}.qr,.btn,.tick{transition:opacity .2s ease}.qr:active,.btn:active{transform:none}[data-state=copied] .tick{transform:none}}
</style>
</head>
<body data-look="<?= $qc_esc($qc_look) ?>" data-theme="<?= $qc_esc($qc_theme) ?>">
<?php if ($qc_ok): ?>
<main class="card" id="card" data-state="idle">
  <h1><?= $qc_txt('pageTitle') ?></h1>
  <div class="chip"><?= $qc_esc((string) $qc_inv['username']) ?></div>
  <button type="button" class="qr" id="qr" aria-label="<?= $qc_txt('pageCopy') ?>">
    <img id="img" src="<?= $qc_esc($qc_img) ?>" alt="" width="320" height="320" decoding="async">
    <span class="tick" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg></span>
  </button>
  <p class="tap" id="tap" role="status" aria-live="polite"><?= $qc_txt('pageTap') ?></p>
  <button type="button" class="btn" id="copy"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="8" y="8" width="12" height="12" rx="3"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/></svg><span id="copyTx"><?= $qc_txt('pageCopy') ?></span></button>
  <div class="fail" id="fail" hidden>
    <p><?= $qc_txt('pageFail') ?></p>
    <button type="button" class="btn" id="save"><?= $qc_txt('pageSave') ?></button>
  </div>
</main>
<script>
(function () {
  var words = <?= json_encode(['tap' => strip_tags((string) $qc_t['pageTap']), 'copy' => strip_tags((string) $qc_t['pageCopy']), 'copied' => strip_tags((string) $qc_t['pageCopied'])], $qc_json) ?>;
  var card = document.getElementById('card'), img = document.getElementById('img'), tap = document.getElementById('tap');
  var copyTx = document.getElementById('copyTx'), fail = document.getElementById('fail'), timer = 0, blobP = null;
  // the picture, fetched once and kept: a copy has to start inside the tap
  function blob() {
    if (!blobP) {
      blobP = fetch(img.getAttribute('src'), { cache: 'no-store' }).then(function (r) {
        if (!r.ok) throw new Error('img');
        return r.blob();
      });
      blobP.catch(function () { blobP = null; });
    }
    return blobP;
  }
  blob();
  function done() {
    clearTimeout(timer);
    card.setAttribute('data-state', 'copied');
    copyTx.textContent = words.copied;
    tap.textContent = words.copied;
    fail.hidden = true;
    timer = setTimeout(function () {
      card.setAttribute('data-state', 'idle');
      copyTx.textContent = words.copy;
      tap.textContent = words.tap;
    }, 2200);
  }
  function failed() { fail.hidden = false; }
  function copy() {
    if (!navigator.clipboard || !window.ClipboardItem || !navigator.clipboard.write) { failed(); return; }
    // the promise goes in unresolved - Safari wants the copy started within the tap
    navigator.clipboard.write([new ClipboardItem({ 'image/png': blob() })]).then(done, failed);
  }
  document.getElementById('qr').addEventListener('click', copy);
  document.getElementById('copy').addEventListener('click', copy);
  // no clipboard for a picture here (Telegram's own browser, an old phone): share or save it
  document.getElementById('save').addEventListener('click', function () {
    blob().then(function (b) {
      var file = new File([b], 'qr.png', { type: 'image/png' });
      if (navigator.canShare && navigator.canShare({ files: [file] }) && navigator.share) {
        return navigator.share({ files: [file] }).catch(function () {});
      }
      var a = document.createElement('a');
      a.href = URL.createObjectURL(b); a.download = 'qr.png';
      document.body.appendChild(a); a.click(); a.remove();
      setTimeout(function () { URL.revokeObjectURL(a.href); }, 4000);
    });
  });
})();
</script>
<?php else: ?>
<main class="card err"><p><?= $qc_txt('pageError') ?></p></main>
<?php endif; ?>
</body>
</html>
