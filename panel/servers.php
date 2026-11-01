<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/langsync.php';
require_auth();

// 🖥 which languages see each panel (server) when they buy - the column the
// bot's 🌐 مدیریت نمایش بر اساس زبان ← پنل‌ها sets. The panels themselves are
// added and set up in the bot.
$t = $textbotlang['panel'];
$view = in_array($_GET['view'] ?? '', panel_langs(), true) ? $_GET['view'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['card'] ?? '') === 'rbsv') {
    // ⚙️ a Rebecca panel's service (rbsvc.php) - the bot's own screen, checked the same way
    csrf_check_post();
    $rp = db_fetch($pdo, "SELECT * FROM marzban_panel WHERE code_panel = ? AND type = 'rebecca'", [(string) ($_POST['code_panel'] ?? '')]);
    $err = null;
    if ($rp === null) {
        $err = $t['rbsvPanelMissing'];
    } else {
        [$list, $why] = rbsvc_list($rp, true);
        $byId = [];
        foreach ((array) $list as $sv) {
            $byId[$sv['id']] = $sv;
        }
        $want = ['b' => (int) ($_POST['buy'] ?? 0), 't' => (int) ($_POST['test'] ?? 0)];
        foreach ($want as $kind => $id) {
            if ($id <= 0 || ($kind === 'b' ? rbsvc_buy_id($rp) : rbsvc_test_id($rp)) === $id) {
                continue; // nothing new: kept as it is, even while the list cannot be read
            }
            if ($list === null) {
                $err = sprintf($t['rbsvError'], html_entity_decode(strip_tags($why), ENT_QUOTES, 'UTF-8'));
            } elseif (!isset($byId[$id])) {
                $err = $t['rbsvNotFound'];
            } elseif ($byId[$id]['broken']) {
                $err = sprintf($t['rbsvBrokenErr'], $byId[$id]['name']);
            }
        }
        if ($err === null) {
            if ($want['b'] > 0) {
                rbsvc_set($rp, 'b', $want['b']);
            }
            rbsvc_set($rp, 't', $want['t']);
        }
    }
    flash($err === null ? 'success' : 'error', $err ?? $t['rbsvSaved']);
    header('Location: servers.php?' . http_build_query(['view' => $view ?: null]) . '#rbsv');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $code = (string) ($_POST['code_panel'] ?? '');
    if ($code === '' || !db_count($pdo, "SELECT COUNT(*) FROM marzban_panel WHERE code_panel = ?", [$code])) {
        flash('error', $t['srvMissing']);
    } else {
        db_query($pdo, "UPDATE marzban_panel SET lang = ? WHERE code_panel = ?", [web_lang_value($_POST['langs'] ?? []), $code]);
        flash('success', $t['refSaved']);
    }
    header('Location: servers.php?' . http_build_query(['view' => $view ?: null]));
    exit;
}

$panels = array_values(array_filter(db_fetchAll($pdo, "SELECT * FROM marzban_panel ORDER BY id"), fn($p) => $view === '' || web_lang_has($p['lang'] ?? 'all', $view)));
$prodCount = [];
foreach (db_fetchAll($pdo, "SELECT Location, COUNT(*) AS n FROM product GROUP BY Location") as $r) {
    $prodCount[(string) $r['Location']] = (int) $r['n'];
}

$pageTitle = $t['srvPageTitle'];
$pageLede = $t['srvPageLede'];
$activeNav = 'servers';
include __DIR__ . '/inc/layout_head.php';
echo web_lang_assets();
?>

<?= web_lang_tabs($view, fn($c) => 'servers.php?' . http_build_query(['view' => $c ?: null]), true) ?>

<div class="card fade-up">
  <?php if (!$panels): ?>
    <div class="empty" style="padding:40px 20px"><p><?= $t['srvEmpty'] ?></p></div>
  <?php else: ?>
    <div class="tbl-wrap">
      <table class="tbl-xl">
        <thead><tr><th style="width:50px">#</th><th><?= $t['srvColName'] ?></th><th><?= $t['srvColType'] ?></th><th><?= $t['srvColStatus'] ?></th><th><?= $t['srvColProducts'] ?></th><th><?= $t['prodColLangs'] ?></th></tr></thead>
        <tbody>
        <?php foreach ($panels as $i => $p): ?>
          <tr>
            <td class="cf"><?= $i + 1 ?></td>
            <td class="cs"><?= htmlspecialchars((string) $p['name_panel']) ?></td>
            <td><span class="tag tag-plain"><?= htmlspecialchars((string) $p['type']) ?></span></td>
            <td><?= ($p['status'] ?? '') === 'active' ? '<span class="tag tag-ok">' . $t['srvActive'] . '</span>' : '<span class="tag tag-no">' . $t['srvInactive'] . '</span>' ?></td>
            <td class="cf"><?= $prodCount[(string) $p['name_panel']] ?? 0 ?></td>
            <td>
              <form method="POST" action="servers.php?<?= http_build_query(['view' => $view ?: null]) ?>" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="code_panel" value="<?= htmlspecialchars((string) $p['code_panel']) ?>">
                <?= web_lang_checkboxes('langs', $p['lang'] ?? 'all', 'srv' . (int) $p['id']) ?>
                <button type="submit" class="btn btn-primary btn-sm"><?= icon('check', 13) ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <div class="card-body"><div class="field-hint"><?= $t['srvHint'] ?></div></div>
</div>

<?php $rbPanels = array_values(array_filter(db_fetchAll($pdo, "SELECT * FROM marzban_panel WHERE type = 'rebecca' ORDER BY id"))); if ($rbPanels): ?>
<div class="card fade-up" id="rbsv" style="margin-top:14px">
  <div class="card-head"><div><div class="card-title">⟦i:layers⟧ <?= htmlspecialchars($t['rbsvTitle']) ?></div><div class="card-subtitle"><?= htmlspecialchars($t['rbsvSub']) ?></div></div></div>
  <div class="card-body">
    <?php foreach ($rbPanels as $rp): [$rl, $rwhy] = rbsvc_list($rp); $rb = rbsvc_buy_id($rp); $rt = rbsvc_test_id($rp);
      $optLabel = function ($sv) use ($t) {
          return $sv['name'] . ' · ' . sprintf($t['rbsvUsers'], number_format($sv['users'])) . ($sv['broken'] ? ' · ' . $t['rbsvBroken'] : '');
      };
      $opts = function ($cur, $first) use ($rl, $optLabel, $t) {
          $h = '<option value="0"' . ($cur === 0 ? ' selected' : '') . '>' . htmlspecialchars($first) . '</option>';
          $seen = false;
          foreach ((array) $rl as $sv) {
              $seen = $seen || $sv['id'] === $cur;
              $h .= '<option value="' . $sv['id'] . '"' . ($sv['id'] === $cur ? ' selected' : '') . ($sv['broken'] ? ' disabled' : '') . '>' . htmlspecialchars($optLabel($sv)) . '</option>';
          }
          if ($cur > 0 && !$seen) {
              $h .= '<option value="' . $cur . '" selected>' . htmlspecialchars(sprintf($rl === null ? $t['rbsvIdOnly'] : $t['rbsvGone'], $cur)) . '</option>';
          }
          return $h;
      }; ?>
      <form method="POST" action="servers.php?<?= http_build_query(['view' => $view ?: null]) ?>" class="set-row" style="flex-wrap:wrap">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>"><input type="hidden" name="card" value="rbsv">
        <input type="hidden" name="code_panel" value="<?= htmlspecialchars((string) $rp['code_panel']) ?>">
        <div style="min-width:160px">
          <div class="set-label">⟦i:server⟧ <?= htmlspecialchars((string) $rp['name_panel']) ?></div>
          <?php if ($rl === null): ?><div class="set-hint" style="color:var(--no)"><?= htmlspecialchars(sprintf($t['rbsvError'], html_entity_decode(strip_tags($rwhy), ENT_QUOTES, 'UTF-8'))) ?></div><?php endif; ?>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
          <label class="set-hint" style="display:flex;flex-direction:column;gap:4px;margin:0"><?= htmlspecialchars($t['rbsvBuy']) ?><select class="select" name="buy"><?= $opts($rb, $t['rbsvUnset']) ?></select></label>
          <label class="set-hint" style="display:flex;flex-direction:column;gap:4px;margin:0"><?= htmlspecialchars($t['rbsvTest']) ?><select class="select" name="test"><?= $opts($rt, $t['rbsvSame']) ?></select></label>
          <button type="submit" class="btn btn-primary btn-sm"><?= icon('check', 13) ?> <?= $t['bottextSaveBtn'] ?></button>
        </div>
      </form>
    <?php endforeach; ?>
    <div class="field-hint" style="margin-top:8px">⟦i:bot⟧ <?= htmlspecialchars($t['rbsvBotPath']) ?></div>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
