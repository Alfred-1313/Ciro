<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/langsync.php';
require_once __DIR__ . '/inc/genbtn_card.php';
require_once __DIR__ . '/inc/btfield.php';
require_auth();

// 📲 افزودن سرویس به برنامه, all in one place and one language at a time - the
// same group the bot's 🎨 شخصی‌سازی has: the look of the page an app opens
// through, the 🌐 / 📲 buttons under a service, the list of apps and its
// buttons, the page's own words (opening, app not found, download and the
// stores) and the popups. One setting for the five places that show it (a
// bought service, a test account, a 🎁 gift config, ⏱ and 🛍 سرویس‌های من).
// The buttons are saved by ./buttons.php and the words by ./bottext.php (their
// own stores and rules), each coming back here.
$t = $textbotlang['panel'];
$bt = $textbotlang['bottext'];
$lang = web_lang_pick();
$tx = lang_tab_texts($lang);
$groupKeys = subapps_group_keys();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'look') {
        $look = (string) ($_POST['subapp_look'] ?? '');
        if (isset(subapp_looks()[$look])) {
            feature_setting_set('subapp_look', $lang, $look);
        }
        $theme = (string) ($_POST['subapp_theme'] ?? '');
        if (isset(subapp_themes()[$theme])) {
            feature_setting_set('subapp_theme', $lang, $theme);
        }
        flash('success', $t['refSaved']);
    } elseif ($action === 'links') {
        // each app's stores, for this language: empty = the default, «-» = none
        $err = null;
        $posted = (array) ($_POST['links'] ?? []);
        $current = subapp_links($lang);
        foreach (subapp_apps() as $app) {
            foreach (subapp_link_kinds() as $kind) {
                $v = trim((string) ($posted[$app['id']][$kind] ?? ($current[$app['id']][$kind] ?? '')));
                if ($v !== '' && $v !== '-' && !subapp_link_valid($v)) {
                    $err = sprintf($t['seLinkInvalid'], $v);
                    continue;
                }
                if ($v !== (string) ($current[$app['id']][$kind] ?? '')) {
                    subapp_link_set($lang, $app['id'], $kind, $v);
                }
            }
        }
        flash($err === null ? 'success' : 'error', $err ?? $t['seLinksSaved']);
    } elseif ($action === 'reset') {
        // what 🔁 «ریست همه به پیش‌فرض» does in the bot's group, and the look too
        bottext_reset_keys($groupKeys, $lang);
        feature_setting_set('subapp_look', $lang, 'glass');
        subapp_layout_set($lang, subapp_layout_defaults());
        feature_setting_set('subapp_links', $lang, '');
        feature_setting_set('subapp_theme', $lang, 'auto');
        flash('success', $t['saResetDone']);
    }
    header('Location: subapps.php?' . http_build_query(['lang' => $lang]));
    exit;
}

// the words: what the language says before any change, and what the shop has made of it
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
$row = db_fetch($pdo, "SELECT text_edit, keyboardmain FROM setting LIMIT 1") ?? [];
$map = json_decode((string) ($row['text_edit'] ?? ''), true);
$map = is_array($map) ? $map : [];
$layout = json_decode((string) ($row['keyboardmain'] ?? ''), true);
$stickers = is_array($layout['text_stickers'] ?? null) ? $layout['text_stickers'] : [];
$reactions = is_array($layout['text_reactions'] ?? null) ? $layout['text_reactions'] : [];
$noSticker = array_flip(bt_nosticker_keys());
$canReact = array_flip(bt_react_keys());
$switchable = bt_item_switch_defaults();
$isAlert = array_flip(bt_alert_keys());
// the sections of the group, in the bot's order; the 🌐 / 📲 buttons' own text is the buttons' card
$bySection = [];
$changedCount = 0;
foreach ($bt['items'] as $it) {
    $key = (string) ($it['key'] ?? '');
    if (($it['group'] ?? '') !== 'subapps' || $key === '' || $key === 'users.subapps.entryBuy') {
        continue;
    }
    $default = $dig($defaults, $key);
    if ($default === null) {
        continue;
    }
    $own = $dig($map[$lang] ?? [], $key);
    $ownSticker = bt_media_lookup_own($stickers, $key, $lang);
    $ownReaction = bt_media_lookup_own($reactions, $key, $lang);
    $changed = $own !== null || $ownSticker !== '' || $ownReaction !== '';
    $changedCount += $changed ? 1 : 0;
    $bySection[(string) ($it['section'] ?? '')][$key] = ['label' => $it['label'] ?? $key, 'current' => $own ?? $default, 'default' => $default, 'textChanged' => $own !== null,
        'changed' => $changed, 'sticker' => $ownSticker, 'reaction' => $ownReaction];
}

$curLook = subapp_look($lang);
$curTheme = subapp_theme($lang);
$colors = ['' => $t['menuColorDefault'], 'primary' => '🔵 ' . $t['menuColorBlue'], 'success' => '🟢 ' . $t['menuColorGreen'], 'danger' => '🔴 ' . $t['menuColorRed']];
$itemLabels = [];
foreach ($bt['items'] as $it) {
    if (!empty($it['key'])) {
        $itemLabels[$it['key']] = $it['label'] ?? $it['key'];
    }
}
$cards = [];
foreach (['gb', 'kb'] as $alias) {
    $cards[$alias] = [genbtn_alias_to_key($alias), genbtn_defs($alias, $tx)];
}
$sectionTitle = fn($sec) => $bt['msSections'][$sec] ?? bt_section_meta($sec)['label'];
$stateLinks = ['' => $t['saPreviewOpening'], 'missing' => $t['saPreviewMissing'], 'opened' => $t['saPreviewOpened'], 'downloading' => $t['saPreviewDownloading']];

$pageTitle = $t['saPageTitle'];
$pageLede = $t['saPageLede'];
$activeNav = 'subapps';
include __DIR__ . '/inc/layout_head.php';
echo web_lang_assets();
?>

<?= web_lang_tabs($lang, fn($code) => 'subapps.php?' . http_build_query(['lang' => $code])) ?>

<div class="card fade-up" style="margin-bottom:14px"><div class="card-body"><div class="set-row">
  <div><div class="set-label">✏️ <?= $t['seOpenEditor'] ?></div><div class="set-hint"><?= $t['seOpenEditorHint'] ?></div></div>
  <div class="set-ctl"><a class="btn btn-primary btn-sm" href="subapp_edit.php?<?= http_build_query(['lang' => $lang]) ?>"><?= $t['seOpenEditor'] ?></a></div>
</div></div></div>

<form method="POST" action="subapps.php?<?= http_build_query(['lang' => $lang]) ?>" class="card fade-up" id="look" data-autosave data-ajax="quiet" style="margin-bottom:14px">
  <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
  <input type="hidden" name="action" value="look">
  <div class="card-head"><div><div class="card-title">🖼 <?= $t['saLookLabel'] ?></div><div class="card-subtitle"><?= $t['saLookHint'] ?></div></div></div>
  <div class="card-body">
    <?php foreach (subapp_looks() as $lk => $ln): ?>
      <div class="set-row" style="flex-wrap:wrap">
        <label class="lang-chip" style="min-width:200px"><input type="radio" name="subapp_look" value="<?= htmlspecialchars($lk) ?>"<?= $curLook === $lk ? ' checked' : '' ?>> <?= htmlspecialchars($ln) ?></label>
        <div class="lang-chips" style="flex:1;justify-content:flex-end">
          <?php foreach ($stateLinks as $st => $label): ?>
            <a class="btn btn-ghost btn-sm" href="<?= htmlspecialchars(subapp_preview_link($lk, $lang, 'karing', $st)) ?>" target="_blank" rel="noopener noreferrer">👁 <?= htmlspecialchars($label) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
    <div class="set-row" style="flex-wrap:wrap">
      <div style="min-width:200px"><div class="set-label">🌓 <?= $t['saThemeLabel'] ?></div><div class="set-hint"><?= $t['saThemeHint'] ?></div></div>
      <div class="lang-chips" style="flex:1;justify-content:flex-end">
        <?php foreach (subapp_themes() as $tk => $tn): ?>
          <label class="lang-chip"><input type="radio" name="subapp_theme" value="<?= htmlspecialchars($tk) ?>"<?= $curTheme === $tk ? ' checked' : '' ?>> <?= htmlspecialchars($tn) ?></label>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="modal-foot" style="border-radius:0 0 10px 10px"><button type="submit" class="btn btn-primary"><?= icon('check', 13) ?> <?= $t['bottextSaveBtn'] ?></button></div>
</form>

<form method="POST" action="subapps.php?<?= http_build_query(['lang' => $lang]) ?>" class="card fade-up" id="links" style="margin-bottom:14px">
  <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
  <input type="hidden" name="action" value="links">
  <div class="card-head"><div><div class="card-title">🔗 <?= $t['seLinksTitle'] ?></div><div class="card-subtitle"><?= $t['seLinksHint'] ?></div></div></div>
  <div class="card-body" style="display:flex;flex-direction:column;gap:16px">
    <?php $myLinks = subapp_links($lang); foreach (subapp_apps() as $app): ?>
      <div class="field">
        <label><?= htmlspecialchars($app['name']) ?></label>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:8px">
          <?php foreach (['play' => 'seLinkPlay', 'github' => 'seLinkGithub', 'ios' => 'seLinkIos', 'site' => 'seLinkSite'] as $kind => $tk): ?>
            <label style="display:flex;flex-direction:column;gap:3px;font-size:.78rem;color:var(--mute)"><?= $t[$tk] ?>
              <input type="text" class="input" dir="ltr" name="links[<?= htmlspecialchars($app['id']) ?>][<?= $kind ?>]" value="<?= htmlspecialchars((string) ($myLinks[$app['id']][$kind] ?? '')) ?>" placeholder="<?= htmlspecialchars($app[$kind] !== '' ? $app[$kind] : '-') ?>" maxlength="300"></label>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="modal-foot" style="border-radius:0 0 10px 10px"><button type="submit" class="btn btn-primary"><?= icon('check', 13) ?> <?= $t['bottextSaveBtn'] ?></button></div>
</form>

<h3 style="margin:18px 2px 8px;font-size:.95rem"><?= htmlspecialchars($sectionTitle('subapps_entry')) ?></h3>
<?php genbtn_card('gb', $cards['gb'][0], $cards['gb'][1], $lang, $t, $colors, $itemLabels, 'subapps'); ?>

<h3 style="margin:18px 2px 8px;font-size:.95rem"><?= htmlspecialchars($sectionTitle('subapps_list')) ?></h3>
<?php genbtn_card('kb', $cards['kb'][0], $cards['kb'][1], $lang, $t, $colors, $itemLabels, 'subapps'); ?>

<form method="POST" action="bottext.php?<?= http_build_query(['lang' => $lang, 'group' => 'subapps']) ?>"
      onsubmit="this.querySelectorAll('textarea').forEach(function (x) { x.disabled = x.value === x.defaultValue; })" class="card fade-up" id="texts" style="overflow:visible;margin-bottom:14px">
  <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
  <input type="hidden" name="return" value="subapps">
  <div class="card-head"><div><div class="card-title">📝 <?= $t['saTextsTitle'] ?> <small>(<?= $changedCount ?> <?= $t['bottextChangedLabel'] ?>)</small></div><div class="card-subtitle"><?= $t['saTextsHint'] ?></div></div></div>
  <div class="card-body" style="display:flex;flex-direction:column;gap:18px">
    <?php foreach ($bySection as $sec => $items): ?>
      <h3 style="margin:6px 2px -6px;font-size:.95rem"><?= htmlspecialchars($sectionTitle($sec)) ?></h3>
      <?php foreach ($items as $key => $v) { bt_web_field($key, $v, $lang, $t, $isAlert, $switchable, $canReact, $noSticker); } ?>
    <?php endforeach; ?>
    <div class="field-hint"><?= $t['btStickerHint'] ?> <?= $t['btStickerDefault'] ?></div>
  </div>
  <div class="modal-foot" style="position:sticky;bottom:0;z-index:5;border-radius:0 0 10px 10px;box-shadow:0 -8px 20px rgba(0,0,0,.25)">
    <button type="submit" class="btn btn-primary"><?= icon('check', 13) ?> <?= $t['bottextSaveBtn'] ?></button>
  </div>
</form>

<form method="POST" action="subapps.php?<?= http_build_query(['lang' => $lang]) ?>" class="card fade-up" id="reset" style="margin-bottom:14px"
      onsubmit="return confirm(<?= htmlspecialchars(json_encode($t['saResetConfirm'], JSON_UNESCAPED_UNICODE)) ?>)">
  <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
  <input type="hidden" name="action" value="reset">
  <div class="card-body"><div class="set-row">
    <div><div class="set-label">🔁 <?= $t['saResetTitle'] ?></div><div class="set-hint"><?= $t['saResetHint'] ?></div></div>
    <div class="set-ctl"><button type="submit" class="btn btn-ghost btn-sm"><?= $t['saResetBtn'] ?></button></div>
  </div></div>
</form>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
