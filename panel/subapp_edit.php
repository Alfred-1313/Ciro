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
$elNames = [];
foreach (subapp_layout_elements() as $el) {
    $elNames[$el] = $t['seEl' . ucfirst($el)];
}
$blockEls = ['stage' => ['logo', 'flow'], 'title' => ['title'], 'chip' => ['chip'], 'status' => ['status'], 'actions' => ['note', 'download', 'stores', 'retry']];
$blockNames = [];
foreach (subapp_layout_blocks() as $b) {
    $blockNames[$b] = $t['seBlock' . ucfirst($b)];
}
$choices = [
    'size' => ['sm' => $t['seSizeSm'], 'md' => $t['seSizeMd'], 'lg' => $t['seSizeLg'], 'xl' => $t['seSizeXl']],
    'shape' => ['squircle' => $t['seShapeSquircle'], 'circle' => $t['seShapeCircle'], 'square' => $t['seShapeSquare']],
    'stageAlign' => ['center' => $t['seAlignCenter'], 'start' => $t['seAlignStart'], 'end' => $t['seAlignEnd']],
    'align' => ['center' => $t['seAlignCenter'], 'start' => $t['seAlignStart']],
];
$choiceTitles = ['size' => $t['seSize'], 'shape' => $t['seShape'], 'stageAlign' => $t['seStageAlign'], 'align' => $t['seTextAlign']];
$groupTitles = ['opening' => $t['seStateOpening'], 'missing' => $t['seStateMissing'], 'opened' => $t['seStateOpened'], 'stores' => $t['seElStores']];

$pageTitle = $t['seTitle'];
$pageLede = $t['seLede'];
$activeNav = 'subapps';
include __DIR__ . '/inc/layout_head.php';
echo web_lang_assets();
?>
<style>
.se-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,390px);gap:14px;align-items:start}
.se-stick{position:sticky;top:12px}
.se-bar{display:flex;flex-wrap:wrap;gap:10px 16px;align-items:center;padding:12px 14px;border-bottom:1px solid var(--bd)}
.se-bar .grp{display:flex;align-items:center;gap:6px;font-size:.78rem;color:var(--mute)}
.se-frame{margin:14px auto;width:390px;max-width:100%;height:760px;border-radius:26px;overflow:hidden;border:1px solid var(--bd);background:#0b0b10}
.se-frame[data-dev=desk]{width:100%;height:620px;border-radius:12px}
.se-frame iframe{width:100%;height:100%;border:0;display:block}
.se-row{display:flex;align-items:center;gap:10px;padding:8px 0;border-top:1px solid var(--bd)}
.se-row:first-child{border-top:0}
.se-row .nm{flex:1;font-weight:600;font-size:.86rem}
.se-els{display:flex;flex-wrap:wrap;gap:6px}
.se-mv{display:flex;gap:4px}
.se-mv button{width:30px;height:30px;padding:0;display:grid;place-items:center}
.se-hit{animation:seHit 900ms var(--ease-out)}
@keyframes seHit{from{background:var(--acs)}to{background:transparent}}
.se-w textarea{min-height:42px}
.se-w.on label{color:var(--ach)}
@media(max-width:1000px){.se-grid{grid-template-columns:1fr}.se-stick{position:static}}
@media(prefers-reduced-motion:reduce){.se-hit{animation:none}}
</style>

<?= web_lang_tabs($lang, fn($code) => 'subapp_edit.php?' . http_build_query(['lang' => $code])) ?>

<div class="se-grid">
  <section class="card fade-up se-stick">
    <div class="card-head"><div><div class="card-title">👁 <?= $t['sePreview'] ?></div></div></div>
    <div class="se-bar">
      <label class="grp"><?= $t['seApp'] ?>
        <select class="select" id="se-app" style="width:auto"><?php foreach (subapp_apps() as $app): ?><option value="<?= htmlspecialchars($app['id']) ?>"><?= htmlspecialchars($app['name']) ?></option><?php endforeach; ?></select></label>
      <div class="grp"><?= $t['seState'] ?>
        <div class="lang-chips" id="se-state">
          <?php foreach (['opening' => 'seStateOpening', 'missing' => 'seStateMissing', 'opened' => 'seStateOpened'] as $k => $tk): ?>
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
    </div>
    <div class="se-frame" id="se-frame" data-dev="phone"><iframe id="se-pv" title="<?= htmlspecialchars($t['sePreview']) ?>" src="about:blank"></iframe></div>
  </section>

  <form method="POST" action="subapp_edit.php?<?= http_build_query(['lang' => $lang]) ?>" id="se-form" data-ajax="quiet" class="fade-up"
        onsubmit="document.getElementById('lay_json').value = JSON.stringify(window.SE.lay)">
    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="lay_json" id="lay_json" value="">

    <div class="card" style="margin-bottom:14px">
      <div class="card-head"><div><div class="card-title">🧩 <?= $t['seBlocks'] ?></div><div class="card-subtitle"><?= $t['seBlocksHint'] ?></div></div></div>
      <div class="card-body" id="se-blocks"></div>
    </div>

    <div class="card" style="margin-bottom:14px">
      <div class="card-head"><div><div class="card-title">🖼 <?= $t['seLogo'] ?></div></div></div>
      <div class="card-body">
        <?php foreach ($choices as $k => $opts): ?>
          <div class="set-row" style="flex-wrap:wrap">
            <div class="set-label"><?= $choiceTitles[$k] ?></div>
            <div class="lang-chips" data-choice="<?= $k ?>">
              <?php foreach ($opts as $v => $label): ?>
                <label class="lang-chip"><input type="radio" name="se_<?= $k ?>" value="<?= $v ?>"<?= $lay[$k] === $v ? ' checked' : '' ?>> <?= htmlspecialchars($label) ?></label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card" style="margin-bottom:14px">
      <div class="card-head"><div><div class="card-title">📝 <?= $t['seTexts'] ?></div><div class="card-subtitle"><?= $t['seTextsHint'] ?></div></div></div>
      <div class="card-body" style="display:flex;flex-direction:column;gap:14px">
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
    </div>

    <div class="modal-foot" style="position:sticky;bottom:0;z-index:5;border-radius:10px;box-shadow:0 -8px 20px rgba(0,0,0,.25);margin-bottom:14px;display:flex;gap:8px;justify-content:space-between">
      <button type="submit" class="btn btn-primary" style="width:auto"><?= icon('check', 13) ?> <?= $t['seSave'] ?></button>
      <button type="button" class="btn btn-ghost" style="width:auto" id="se-reset"><?= $t['seResetLayout'] ?></button>
    </div>
  </form>
</div>

<form method="POST" action="subapp_edit.php?<?= http_build_query(['lang' => $lang]) ?>" id="se-reset-form" data-ajax="off" style="display:none">
  <input type="hidden" name="_csrf" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="reset">
</form>

<script>
window.SE = {
  lay: <?= json_encode($lay, JSON_UNESCAPED_UNICODE) ?>,
  urls: <?= json_encode($urls, JSON_UNESCAPED_SLASHES) ?>,
  els: <?= json_encode($elNames, JSON_UNESCAPED_UNICODE) ?>,
  blocks: <?= json_encode($blockNames, JSON_UNESCAPED_UNICODE) ?>,
  blockEls: <?= json_encode($blockEls) ?>,
  confirm: <?= json_encode($t['seResetConfirm'], JSON_UNESCAPED_UNICODE) ?>
};
(function () {
  var S = window.SE, pv = document.getElementById('se-pv'), ui = { app: 'karing', st: 'opening', os: 'android' };
  var $ = function (id) { return document.getElementById(id); };
  var listed = $('se-blocks');

  // ---- the preview: its address, and what it is told as it loads and as things change ----
  function src() {
    var u = S.urls[$('se-look').value][ui.app] + '&ed=1&st=' + ui.st + '&os=' + ui.os;
    return u + '&th=' + $('se-th').value;
  }
  function tell(m) { if (pv.contentWindow) pv.contentWindow.postMessage(m, location.origin); }
  function words() {
    document.querySelectorAll('.se-w textarea').forEach(function (ta) { tell({ t: 'text', key: ta.closest('.se-w').getAttribute('data-key'), val: ta.value }); });
  }
  function push() { tell({ t: 'layout', lay: S.lay }); words(); }
  function reload() { pv.src = src(); }
  pv.addEventListener('load', push);

  // ---- the parts and their order ----
  function drawBlocks() {
    listed.innerHTML = '';
    S.lay.order.forEach(function (b, i) {
      var row = document.createElement('div');
      row.className = 'se-row'; row.setAttribute('data-block', b);
      var els = S.blockEls[b].map(function (el) {
        var on = S.lay.hide.indexOf(el) < 0;
        return '<label class="lang-chip" data-el="' + el + '"><input type="checkbox" data-el="' + el + '"' + (on ? ' checked' : '') + '> ' + S.els[el] + '</label>';
      }).join('');
      row.innerHTML = '<div style="flex:1;min-width:0"><div class="nm">' + S.blocks[b] + '</div><div class="se-els" style="margin-top:6px">' + els + '</div></div>' +
        '<div class="se-mv"><button type="button" class="btn btn-ghost btn-sm" data-mv="-1"' + (i === 0 ? ' disabled' : '') + ' aria-label="▲">▲</button>' +
        '<button type="button" class="btn btn-ghost btn-sm" data-mv="1"' + (i === S.lay.order.length - 1 ? ' disabled' : '') + ' aria-label="▼">▼</button></div>';
      listed.appendChild(row);
    });
  }
  listed.addEventListener('click', function (e) {
    var b = e.target.closest('[data-mv]');
    if (!b) return;
    var row = b.closest('[data-block]'), name = row.getAttribute('data-block'), i = S.lay.order.indexOf(name), j = i + parseInt(b.getAttribute('data-mv'), 10);
    if (j < 0 || j >= S.lay.order.length) return;
    S.lay.order.splice(i, 1); S.lay.order.splice(j, 0, name);
    drawBlocks(); tell({ t: 'layout', lay: S.lay });
    var again = listed.querySelector('[data-block="' + name + '"] [data-mv="' + b.getAttribute('data-mv') + '"]');
    if (again && !again.disabled) again.focus();
  });
  listed.addEventListener('change', function (e) {
    var el = e.target.getAttribute && e.target.getAttribute('data-el');
    if (!el) return;
    var i = S.lay.hide.indexOf(el);
    if (e.target.checked && i >= 0) S.lay.hide.splice(i, 1);
    if (!e.target.checked && i < 0) S.lay.hide.push(el);
    tell({ t: 'layout', lay: S.lay });
  });
  document.querySelectorAll('[data-choice]').forEach(function (g) {
    g.addEventListener('change', function (e) {
      S.lay[g.getAttribute('data-choice')] = e.target.value;
      tell({ t: 'layout', lay: S.lay });
    });
  });

  // ---- words: written here, seen there; tapped there, found here ----
  document.querySelectorAll('.se-w textarea').forEach(function (ta) {
    var w = ta.closest('.se-w');
    ta.addEventListener('input', function () { tell({ t: 'text', key: w.getAttribute('data-key'), val: ta.value }); });
    ta.addEventListener('focus', function () {
      var g = w.getAttribute('data-group');
      document.querySelectorAll('.se-w.on').forEach(function (x) { x.classList.remove('on'); });
      w.classList.add('on');
      if (['opening', 'missing', 'opened'].indexOf(g) >= 0 && g !== ui.st) { setState(g); }
      tell({ t: 'select', tk: w.getAttribute('data-key') });
    });
  });
  window.addEventListener('message', function (e) {
    if (e.origin !== location.origin || !e.data || e.data.t !== 'pick') return;
    var m = e.data;
    if (m.tk) {
      var w = document.querySelector('.se-w[data-key="' + m.tk + '"] textarea');
      if (w) { w.scrollIntoView({ block: 'center' }); w.focus({ preventScroll: true }); }
    } else if (m.el) {
      var chip = listed.querySelector('label[data-el="' + m.el + '"]');
      if (chip) { chip.scrollIntoView({ block: 'center' }); chip.classList.remove('se-hit'); void chip.offsetWidth; chip.classList.add('se-hit'); }
      tell({ t: 'select', el: m.el });
    }
  });

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
  $('se-reset').addEventListener('click', function () { if (confirm(S.confirm)) $('se-reset-form').submit(); });

  drawBlocks();
  reload();
})();
</script>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
