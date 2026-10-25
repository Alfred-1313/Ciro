<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/langsync.php';
require_auth();

// ✏️ The page an app of 📲's list opens (subapp.php), edited on the page itself:
// a live preview of it, and beside it what can change - the words (tap one on
// the preview, or write it here), the logo's size, shape and place, which parts
// show, in which order. What is changed reaches the preview as it is typed
// (postMessage), and Save writes it where the bot reads it: the words in
// setting.text_edit, the layout in feature_lang_settings 'subapp_layout' - per
// language, one for all five places that show the page.
$t = $textbotlang['panel'];
$lang = web_lang_pick();
$pageKeys = subapp_page_keys();
// the words that are in the page (the popups and the link-error line are not)
$wordGroups = [
    'opening' => ['pageService', 'pageOpening', 'pageButton', 'pageHint'],
    'missing' => ['pageMissingTitle', 'pageMissing', 'pageRetry'],
    'opened' => ['pageOpened'],
    'downloading' => ['pageDownloadingTitle', 'pageDownloading', 'pageStep1', 'pageStep2', 'pageStep3', 'pageBack'],
    'stores' => ['pageDownload', 'pageStoreCaption', 'pageStoreGoogle', 'pageStoreGithub', 'pageStoreApple', 'pageStoreSite', 'pageNoPlatform'],
];
$allWords = array_merge(...array_values($wordGroups));

$defaults = require dirname(__DIR__) . '/lang/' . $lang . '.php';
if ($lang !== 'fa') {
    $defaults = bt_lang_fill_defaults($defaults, require dirname(__DIR__) . '/lang/fa.php');
}
$dig = function ($arr, $key) {
    foreach (explode('.', $key) as $p) {
        if (!is_array($arr) || !array_key_exists($p, $arr)) {
            return null;
        }
        $arr = $arr[$p];
    }
    return is_string($arr) ? $arr : null;
};
$row = db_fetch($pdo, "SELECT text_edit FROM setting LIMIT 1") ?? [];
$map = json_decode((string) ($row['text_edit'] ?? ''), true);
$map = is_array($map) ? $map : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'save') {
        $lay = json_decode((string) ($_POST['lay_json'] ?? ''), true);
        if (is_array($lay)) {
            subapp_layout_set($lang, $lay);
        }
        foreach ((array) ($_POST['texts'] ?? []) as $w => $value) {
            $w = (string) $w;
            $key = 'users.subapps.' . $w;
            $default = in_array($w, $allWords, true) ? $dig($defaults, $key) : null;
            if ($default === null || !is_string($value)) {
                continue;
            }
            $value = trim(str_replace("\r\n", "\n", $value));
            if (!isset($map[$lang]) || !is_array($map[$lang])) {
                $map[$lang] = [];
            }
            // empty or the default again: back to the default
            if ($value === '' || $value === $default) {
                bottext_dotted_unset($map[$lang], $key);
            } else {
                bottext_dotted_set($map[$lang], $key, $value);
            }
        }
        if (isset($map[$lang]) && empty($map[$lang])) {
            unset($map[$lang]);
        }
        db_query($pdo, "UPDATE setting SET text_edit = ?", [empty($map) ? null : json_encode($map, JSON_UNESCAPED_UNICODE)]);
        flash('success', $t['seSaved']);
    } elseif ($action === 'reset') {
        subapp_layout_set($lang, subapp_layout_defaults());
        bottext_reset_keys(array_map(fn($w) => 'users.subapps.' . $w, $allWords), $lang, ['text']);
        flash('success', $t['seResetDone']);
    }
    header('Location: subapp_edit.php?' . http_build_query(['lang' => $lang]));
    exit;
}

$lay = subapp_layout($lang);
$itemLabel = [];
foreach ($textbotlang['bottext']['items'] as $it) {
    if (!empty($it['key'])) {
        $itemLabel[$it['key']] = $it['label'] ?? $it['key'];
    }
}
$texts = [];
foreach ($allWords as $w) {
    $key = 'users.subapps.' . $w;
    $default = (string) $dig($defaults, $key);
    $texts[$w] = ['label' => $itemLabel[$key] ?? $w, 'value' => $dig($map[$lang] ?? [], $key) ?? $default, 'default' => $default];
}
// the preview's addresses, signed, one per look and app - made relative so that it is
// the same origin as this page (the two talk to each other)
$urls = [];
foreach (array_keys(subapp_looks()) as $look) {
    foreach (subapp_apps() as $app) {
        $u = subapp_preview_link($look, $lang, $app['id']);
        $urls[$look][$app['id']] = '../subapp.php?' . parse_url($u, PHP_URL_QUERY);
    }
}
$groupTitles = ['opening' => $t['seStateOpening'], 'missing' => $t['seStateMissing'], 'opened' => $t['seStateOpened'], 'downloading' => $t['seStateDownloading'], 'stores' => $t['seElStores']];

$pageTitle = $t['seTitle'];
$pageLede = $t['seLede'];
$activeNav = 'subapps';
include __DIR__ . '/inc/layout_head.php';
echo web_lang_assets();
?>
<style>
.se-bar{display:flex;flex-wrap:wrap;gap:8px 14px;align-items:center;padding:12px 14px;border-bottom:1px solid var(--bd)}
.se-bar .grp{display:flex;align-items:center;gap:6px;font-size:.78rem;color:var(--mute)}
.se-bar .sp{flex:1}
.se-hint{padding:10px 16px;font-size:.8rem;color:var(--mute);display:flex;flex-wrap:wrap;gap:6px 18px;border-bottom:1px solid var(--bd)}
.se-hint b{color:var(--tx);font-weight:600}
.se-stage{padding:18px 14px;background:repeating-conic-gradient(var(--bd) 0 25%,transparent 0 50%) 0 0/16px 16px}
.se-frame{margin:0 auto;width:390px;max-width:100%;height:780px;border-radius:26px;overflow:hidden;border:1px solid var(--bd);background:#0b0b10;box-shadow:0 24px 60px -24px rgba(0,0,0,.6)}
.se-frame[data-dev=desk]{width:100%;height:640px;border-radius:12px}
.se-frame iframe{width:100%;height:100%;border:0;display:block}
/* the place is always kept: a chip that came and went would wrap the bar - the preview would jump under the hand mid-drag */
.se-un{display:inline-flex;align-items:center;gap:6px;font-size:.78rem;color:var(--wr,#f5b544);visibility:hidden;opacity:0;transition:opacity .18s ease,visibility 0s .18s}
.se-un.on{visibility:visible;opacity:1;transition:opacity .18s ease}
@media (prefers-reduced-motion:reduce){.se-un{transition:none}}
.se-un::before{content:"";width:8px;height:8px;border-radius:50%;background:currentColor}
.se-w textarea{min-height:42px}
.se-w.on label{color:var(--ach)}
.se-ico{width:36px;padding:0;display:inline-grid;place-items:center;font-size:1rem}
summary.se-sum{cursor:pointer;padding:14px 16px;font-weight:700;list-style:none}
summary.se-sum::-webkit-details-marker{display:none}
</style>

<?= web_lang_tabs($lang, fn($code) => 'subapp_edit.php?' . http_build_query(['lang' => $code])) ?>

<form method="POST" action="subapp_edit.php?<?= http_build_query(['lang' => $lang]) ?>" id="se-form" data-ajax="quiet" class="fade-up"
      onsubmit="document.getElementById('lay_json').value = JSON.stringify(window.SE.lay)">
  <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="lay_json" id="lay_json" value="">

  <section class="card" style="margin-bottom:14px;overflow:visible">
    <div class="se-bar">
      <button type="button" class="btn btn-ghost btn-sm se-ico" id="se-undo" title="<?= htmlspecialchars($t['seUndo']) ?>" aria-label="<?= htmlspecialchars($t['seUndo']) ?>" disabled>↶</button>
      <button type="button" class="btn btn-ghost btn-sm se-ico" id="se-redo" title="<?= htmlspecialchars($t['seRedo']) ?>" aria-label="<?= htmlspecialchars($t['seRedo']) ?>" disabled>↷</button>
      <label class="grp"><?= $t['seApp'] ?>
        <select class="select" id="se-app" style="width:auto"><?php foreach (subapp_apps() as $app): ?><option value="<?= htmlspecialchars($app['id']) ?>"><?= htmlspecialchars($app['name']) ?></option><?php endforeach; ?></select></label>
      <div class="grp"><?= $t['seState'] ?>
        <div class="lang-chips" id="se-state">
          <?php foreach (['opening' => 'seStateOpening', 'missing' => 'seStateMissing', 'opened' => 'seStateOpened', 'downloading' => 'seStateDownloading', 'back' => 'seStateBack'] as $k => $tk): ?>
            <label class="lang-chip"><input type="radio" name="se_state" value="<?= $k ?>"<?= $k === 'opening' ? ' checked' : '' ?>> <?= $t[$tk] ?></label>
          <?php endforeach; ?>
        </div></div>
      <div class="grp"><?= $t['seDevice'] ?>
        <div class="lang-chips" id="se-dev">
          <label class="lang-chip"><input type="radio" name="se_dev" value="phone" checked> <?= $t['seDevPhone'] ?></label>
          <label class="lang-chip"><input type="radio" name="se_dev" value="desk"> <?= $t['seDevDesk'] ?></label>
        </div></div>
      <label class="grp"><?= $t['seStores'] ?>
        <select class="select" id="se-os" style="width:auto"><option value="android"><?= $t['seOsAndroid'] ?></option><option value="ios"><?= $t['seOsIos'] ?></option><option value="other"><?= $t['seOsOther'] ?></option></select></label>
      <label class="grp"><?= $t['saLookLabel'] ?>
        <select class="select" id="se-look" style="width:auto"><?php foreach (subapp_looks() as $lk => $ln): ?><option value="<?= htmlspecialchars($lk) ?>"<?= subapp_look($lang) === $lk ? ' selected' : '' ?>><?= htmlspecialchars($ln) ?></option><?php endforeach; ?></select></label>
      <label class="grp">🌓
        <select class="select" id="se-th" style="width:auto"><?php foreach (subapp_themes() as $tk => $tn): ?><option value="<?= htmlspecialchars($tk) ?>"<?= subapp_theme($lang) === $tk ? ' selected' : '' ?>><?= htmlspecialchars($tn) ?></option><?php endforeach; ?></select></label>
      <span class="sp"></span>
      <span class="se-un" id="se-un"><?= $t['seUnsaved'] ?></span>
      <button type="button" class="btn btn-ghost btn-sm" style="width:auto" id="se-reset"><?= $t['seResetLayout'] ?></button>
      <button type="submit" class="btn btn-primary btn-sm" style="width:auto"><?= icon('check', 13) ?> <?= $t['seSave'] ?></button>
    </div>
    <div class="se-hint">
      <span>✋ <b><?= $t['seHintDragT'] ?></b> <?= $t['seHintDrag'] ?></span>
      <span>↔️ <b><?= $t['seHintSizeT'] ?></b> <?= $t['seHintSize'] ?></span>
      <span>✏️ <b><?= $t['seHintTextT'] ?></b> <?= $t['seHintText'] ?></span>
      <span>🗑 <b><?= $t['seHintDelT'] ?></b> <?= $t['seHintDel'] ?></span>
    </div>
    <div class="se-stage"><div class="se-frame" id="se-frame" data-dev="phone"><iframe id="se-pv" title="<?= htmlspecialchars($t['sePreview']) ?>" src="about:blank"></iframe></div></div>
  </section>

  <details class="card" style="margin-bottom:14px">
    <summary class="se-sum">📝 <?= $t['seTexts'] ?> <span class="set-hint" style="font-weight:400"><?= $t['seTextsHint'] ?></span></summary>
    <div class="card-body" style="display:flex;flex-direction:column;gap:14px;border-top:1px solid var(--bd)">
      <?php foreach ($wordGroups as $g => $words): ?>
        <h3 style="margin:4px 2px -4px;font-size:.9rem"><?= htmlspecialchars($groupTitles[$g]) ?></h3>
        <?php foreach ($words as $w): ?>
          <div class="field se-w" data-key="<?= $w ?>" data-group="<?= $g ?>">
            <label><?= htmlspecialchars($texts[$w]['label']) ?></label>
            <textarea class="textarea" dir="auto" rows="<?= min(5, substr_count($texts[$w]['value'], "\n") + 1) ?>" name="texts[<?= $w ?>]" data-default="<?= htmlspecialchars($texts[$w]['default']) ?>"><?= htmlspecialchars($texts[$w]['value']) ?></textarea>
          </div>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </div>
  </details>
</form>

<form method="POST" action="subapp_edit.php?<?= http_build_query(['lang' => $lang]) ?>" id="se-reset-form" data-ajax="off" style="display:none">
  <input type="hidden" name="_csrf" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="reset">
</form>

<script>
window.SE = {
  lay: <?= json_encode($lay, JSON_UNESCAPED_UNICODE) ?>,
  urls: <?= json_encode($urls, JSON_UNESCAPED_SLASHES) ?>,
  L: <?= json_encode(['del' => $t['seDelLabel'], 'back' => $t['seBackLabel'], 'shape' => $t['seShapeLabel']], JSON_UNESCAPED_UNICODE) ?>,
  confirm: <?= json_encode($t['seResetConfirm'], JSON_UNESCAPED_UNICODE) ?>
};
(function () {
  var S = window.SE, pv = document.getElementById('se-pv'), ui = { app: 'karing', st: 'opening', os: 'android' };
  var $ = function (id) { return document.getElementById(id); };
  var clone = function (o) { return JSON.parse(JSON.stringify(o)); };
  var fields = {}, history = [], at = -1;
  document.querySelectorAll('.se-w').forEach(function (w) { fields[w.getAttribute('data-key')] = w.querySelector('textarea'); });
  var snapshot = function () { var t = {}; Object.keys(fields).forEach(function (k) { t[k] = fields[k].value; }); return { lay: clone(S.lay), texts: t }; };
  var baseline = JSON.stringify(snapshot());

  // ---- the preview: its address, and what it is told as it loads and as things change ----
  function src() { return S.urls[$('se-look').value][ui.app] + '&ed=1&st=' + ui.st + '&os=' + ui.os + '&th=' + $('se-th').value; }
  function tell(m) { if (pv.contentWindow) pv.contentWindow.postMessage(m, location.origin); }
  function sendAll() {
    tell({ t: 'i18n', L: S.L });
    tell({ t: 'layout', lay: S.lay });
    Object.keys(fields).forEach(function (k) { tell({ t: 'text', key: k, val: fields[k].value }); });
  }
  function reload() { pv.src = src(); }
  pv.addEventListener('load', sendAll);

  // ---- history: every change is a step, and a step can be taken back ----
  function dirty() {
    var d = JSON.stringify(snapshot()) !== baseline;
    $('se-un').classList.toggle('on', d);
    $('se-undo').disabled = at <= 0;
    $('se-redo').disabled = at >= history.length - 1;
    return d;
  }
  function record() {
    var s = snapshot(), cur = history[at];
    if (cur && JSON.stringify(cur) === JSON.stringify(s)) { dirty(); return; }
    history = history.slice(0, at + 1); history.push(s); at = history.length - 1;
    if (history.length > 100) { history.shift(); at--; }
    dirty();
  }
  function restore(s) {
    S.lay = clone(s.lay);
    Object.keys(fields).forEach(function (k) { fields[k].value = s.texts[k]; });
    sendAll(); dirty();
  }
  function step(d) { var n = at + d; if (n < 0 || n >= history.length) return; at = n; restore(history[at]); }
  history.push(snapshot()); at = 0;
  $('se-undo').addEventListener('click', function () { step(-1); });
  $('se-redo').addEventListener('click', function () { step(1); });

  // ---- what the page says it changed ----
  window.addEventListener('message', function (e) {
    if (e.origin !== location.origin || !e.data) return;
    var m = e.data;
    if (m.t === 'lay') { S.lay = clone(m.lay); record(); }
    else if (m.t === 'text' && fields[m.key]) { fields[m.key].value = m.val; record(); }
    else if (m.t === 'undo') step(m.redo ? 1 : -1);
  });
  // the words written here show there at once; a step is taken when the field is left
  Object.keys(fields).forEach(function (k) {
    var ta = fields[k], w = ta.closest('.se-w');
    ta.addEventListener('input', function () { tell({ t: 'text', key: k, val: ta.value }); dirty(); });
    ta.addEventListener('change', record);
    ta.addEventListener('focus', function () {
      var g = k === 'pageBack' ? 'back' : w.getAttribute('data-group');
      if (['opening', 'missing', 'opened', 'downloading', 'back'].indexOf(g) >= 0 && g !== ui.st) setState(g);
    });
  });
  document.addEventListener('keydown', function (e) {
    if (!(e.ctrlKey || e.metaKey) || (e.target.closest && e.target.closest('textarea,input,select'))) return;
    if (e.key === 'z' || e.key === 'Z') { e.preventDefault(); step(e.shiftKey ? 1 : -1); }
    else if (e.key === 'y') { e.preventDefault(); step(1); }
  });

  // ---- saved: what is on screen is now what the customers see ----
  document.addEventListener('ajaxform:saved', function (e) { if (e.detail && e.detail.form === $('se-form')) { baseline = JSON.stringify(snapshot()); dirty(); } });
  window.addEventListener('beforeunload', function (e) { if (dirty()) { e.preventDefault(); e.returnValue = ''; } });

  // ---- the preview's own controls ----
  function setState(s) {
    ui.st = s;
    var r = document.querySelector('#se-state input[value="' + s + '"]'); if (r) r.checked = true;
    tell({ t: 'state', v: s });
  }
  document.querySelectorAll('#se-state input').forEach(function (r) { r.addEventListener('change', function () { setState(r.value); }); });
  document.querySelectorAll('#se-dev input').forEach(function (r) { r.addEventListener('change', function () { $('se-frame').setAttribute('data-dev', r.value); }); });
  $('se-app').addEventListener('change', function () { ui.app = this.value; reload(); });
  $('se-os').addEventListener('change', function () { ui.os = this.value; reload(); });
  $('se-look').addEventListener('change', reload);
  $('se-th').addEventListener('change', reload);
  $('se-reset').addEventListener('click', function () { if (confirm(S.confirm)) { baseline = JSON.stringify(snapshot()); $('se-reset-form').submit(); } });

  dirty();
  reload();
})();
</script>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
