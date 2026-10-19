<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

// 🚀 سرعت گروهی - the very run the bot's 🏬 تنظیمات فروشگاه ← 🚀 starts
// (bulkspeed.php): set up, counted and started here, then followed here until
// it ends. Its progress message and its report come to the admin in the bot
// as well, with the bot's own 🚫 لغو. Rebecca panels only - the others cannot
// hold a speed limit, so they are not offered.
$t = $textbotlang['panel'];
$r = bulkspeed_texts();
$panels = bulkspeed_all_panels();
$adminId = (string) (db_fetch($pdo, "SELECT id_admin FROM admin WHERE username = ?", [$_SESSION['admin_user']])['id_admin'] ?? '');

// what the form says, as it was sent - it is shown again as it was
$raw = ['speed' => '', 'who' => 'groups', 'groups' => [], 'panels' => []];
$err = null;
$preview = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'cancel') {
        // what was set stays; the report goes to the bot. Whatever the
        // Telegram code prints on the way is not this page's: it would stop
        // the redirect below.
        ob_start();
        require_once __DIR__ . '/../botapi.php';
        $ok = bulkspeed_cancel((int) ($_POST['id'] ?? 0));
        ob_end_clean();
        flash($ok ? 'success' : 'error', $ok ? $t['bgCancelled'] : strip_tags($r['cancelGone']));
        header('Location: bulkspeed.php');
        exit;
    }
    if ($action === 'retry') {
        // 🔁 a finished run's failed accounts once more (its new report goes
        // to the bot)
        ob_start();
        require_once __DIR__ . '/../botapi.php';
        $res = bulkspeed_retry((int) ($_POST['id'] ?? 0));
        if (!isset($res['error'])) {
            bulkgift_kick();
        }
        ob_end_clean();
        flash(isset($res['error']) ? 'error' : 'success', isset($res['error']) ? strip_tags($res['error']) : sprintf($t['bgRetried'], number_format($res['n'])));
        header('Location: bulkspeed.php');
        exit;
    }
    $raw['speed'] = trim((string) ($_POST['speed'] ?? ''));
    $raw['who'] = in_array($_POST['who'] ?? '', ['groups', 'bot', 'panel'], true) ? (string) $_POST['who'] : 'groups';
    $raw['groups'] = array_values(array_intersect(bulkspeed_groups(), array_map('strval', (array) ($_POST['groups'] ?? []))));
    $raw['panels'] = array_values(array_map('strval', (array) ($_POST['panels'] ?? [])));
    $speed = $raw['speed'] === '' ? null : speed_limit_parse($raw['speed']);
    $p = bulkspeed_params(['speed' => $speed, 'who' => $raw['who'], 'groups' => $raw['groups'], 'panels' => $raw['panels']]);
    if ($speed === null) {
        $err = strip_tags($raw['speed'] === '' ? $r['noSpeed'] : $r['badSpeed']);
    } elseif (!bulkspeed_who_set($p)) {
        $err = strip_tags($r['noWho']);
    } elseif (!bulkspeed_panels_of($p)) {
        $err = strip_tags($p['panels'] ? $r['noPanels'] : $r['noRebecca']);
    } elseif ($action === 'start') {
        $new = bulkspeed_create($p, $adminId, 'web');
        if (isset($new['error'])) {
            $err = strip_tags($new['error']);
        } else {
            bulkgift_kick();
            flash('success', $t['bgStarted']);
            header('Location: bulkspeed.php');
            exit;
        }
    } else {
        // «تعداد رو ببین»: what the bot's summary shows
        $preview = bulkspeed_preview($p);
    }
}

$jobs = bulkspeed_active_jobs();
$activeIds = array_map(fn($j) => (int) $j['id'], $jobs);
$history = array_values(array_filter(bulkspeed_recent(10), fn($j) => !in_array((int) $j['id'], $activeIds, true)));
$stateName = ['queued' => $t['bgStateQueued'], 'running' => $t['bgStateRunning'], 'done' => $t['bgStateDone'], 'cancelled' => $t['bgStateCancelled']];
$plain = fn($s) => trim(strip_tags((string) $s));
$cardHead = fn($title, $sub) => '<div class="card-head"><div><div class="card-title">' . $title . '</div><div class="card-subtitle">' . $sub . '</div></div></div>';
$formOpen = fn($extra = '') => '<form method="POST" action="bulkspeed.php"' . $extra . '><input type="hidden" name="_csrf" value="' . csrf_token() . '">';
// a run's settings in a line
$jobLine = function (array $j) use ($plain) {
    $p = bulkspeed_job_params($j);
    return $plain(bulkspeed_speed_label($p)) . ' · ' . $plain(bulkspeed_who_label($p)) . ' · ' . $plain(bulkspeed_panels_label($p));
};

$pageTitle = $t['bsPageTitle'];
$pageLede = $t['bsPageLede'];
$activeNav = 'bulkspeed';
include __DIR__ . '/inc/layout_head.php';
?>

<?php if ($err !== null): ?>
  <div class="notice notice-no"><?= htmlspecialchars($err) ?></div>
<?php endif; ?>

<?php foreach ($jobs as $job): $counts = bulkspeed_counts($job['id']);
  $done = $counts['total'] - $counts['pending'] - $counts['claimed']; $pct = $counts['total'] > 0 ? (int) floor($done * 100 / $counts['total']) : 0; ?>
<div class="card fade-up" id="now<?= (int) $job['id'] ?>" style="margin-bottom:14px">
  <?= $cardHead('⏳ ' . htmlspecialchars($t['bgNowTitle']), htmlspecialchars($t['bgNowSub'])) ?>
  <div class="card-body">
    <div class="set-row">
      <div>
        <div class="set-label"><?= htmlspecialchars($jobLine($job)) ?></div>
        <div class="set-hint"><?= htmlspecialchars(sprintf($t['bgRowHint'], $stateName[$job['state']] ?? $job['state'], safe_date((int) ($job['started_at'] ?: $job['created_at']), 'Y/m/d H:i'))) ?></div>
      </div>
      <div class="set-ctl">
        <?= $formOpen(' onsubmit="return confirm(' . htmlspecialchars(json_encode($t['bsCancelConfirm'], JSON_UNESCAPED_UNICODE)) . ')"') ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= (int) $job['id'] ?>"><button class="btn btn-no btn-sm" type="submit"><?= icon('close', 13) ?> <?= htmlspecialchars($t['bgCancelBtn']) ?></button></form>
      </div>
    </div>
    <?php if ((int) $job['prepared']): ?>
    <div class="stats" style="margin-top:12px">
      <div class="stat"><div class="stat-label"><?= htmlspecialchars($t['bgProgress']) ?></div><div class="stat-num"><?= $pct ?>٪</div><div class="stat-meta"><?= number_format($done) ?> / <?= number_format($counts['total']) ?></div></div>
      <div class="stat ok"><div class="stat-label"><?= htmlspecialchars($t['bsDone']) ?></div><div class="stat-num"><?= number_format($counts['done']) ?></div></div>
      <div class="stat warn"><div class="stat-label"><?= htmlspecialchars($t['bgSkipped']) ?></div><div class="stat-num"><?= number_format($counts['skipped']) ?></div></div>
      <div class="stat <?= $counts['failed'] > 0 ? 'no' : '' ?>"><div class="stat-label"><?= htmlspecialchars($t['bsFailed']) ?></div><div class="stat-num"><?= number_format($counts['failed']) ?></div></div>
    </div>
    <?php else: ?>
    <div class="cf" style="margin-top:10px"><?= htmlspecialchars($plain($r['queued'])) ?></div>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
<?php if ($jobs): ?>
<script>
  // up to date every few seconds - unless the form below is being filled in
  (function () {
    var touched = false;
    document.addEventListener('input', function () { touched = true; }, true);
    document.addEventListener('change', function () { touched = true; }, true);
    setTimeout(function () { if (!touched) { location.href = 'bulkspeed.php'; } }, 5000);
  })();
</script>
<?php endif; ?>

<?php if (!$panels): ?>
  <div class="notice notice-warn fade-up"><?= htmlspecialchars($t['bsNoRebecca']) ?></div>
<?php else: ?>
<div class="card fade-up" id="form" style="margin-bottom:14px">
  <?= $cardHead('🚀 ' . htmlspecialchars($t['bsFormTitle']), htmlspecialchars($t['bsFormSub'])) ?>
  <?php if ($preview !== null): ?>
    <div class="notice notice-ok" style="margin:12px 16px 0">
      <?= htmlspecialchars(sprintf($t['bsCountResult'], number_format($preview['n']))) ?>
      <?php if ($raw['who'] === 'panel'): ?><br><?= htmlspecialchars($t['bsCountPanel']) ?><?php endif; ?>
    </div>
  <?php endif; ?>
  <div class="notice notice-warn" style="margin:12px 16px 0"><?= htmlspecialchars($plain($r['sumRenewNote'])) ?></div>
  <?= $formOpen(' id="bsform"') ?>
  <div class="card-body">
    <div class="set-row"><div><div class="set-label"><?= htmlspecialchars($t['bsSpeed']) ?></div><div class="set-hint"><?= htmlspecialchars($t['bsSpeedHint']) ?></div></div>
      <div class="set-ctl"><input type="text" class="input" name="speed" value="<?= htmlspecialchars($raw['speed']) ?>" inputmode="decimal" dir="ltr" style="max-width:160px" placeholder="10"></div>
    </div>
    <div class="set-row"><div><div class="set-label"><?= htmlspecialchars($t['bsWho']) ?></div><div class="set-hint"><?= htmlspecialchars($t['bsWhoHint']) ?></div></div>
      <div class="set-ctl" style="display:flex;flex-direction:column;gap:8px;align-items:flex-start">
        <label class="lang-chip"><input type="radio" name="who" value="groups"<?= $raw['who'] === 'groups' ? ' checked' : '' ?>> <?= htmlspecialchars($t['bsWhoGroups']) ?></label>
        <div class="lang-chips" data-who="groups">
          <?php foreach (bulkspeed_groups() as $g): ?>
            <label class="lang-chip"><input type="checkbox" name="groups[]" value="<?= $g ?>"<?= in_array($g, $raw['groups'], true) ? ' checked' : '' ?>> <?= htmlspecialchars($plain($r['g' . ucfirst($g)])) ?></label>
          <?php endforeach; ?>
        </div>
        <label class="lang-chip"><input type="radio" name="who" value="bot"<?= $raw['who'] === 'bot' ? ' checked' : '' ?>> <?= htmlspecialchars($plain($r['wBot'])) ?></label>
        <label class="lang-chip"><input type="radio" name="who" value="panel"<?= $raw['who'] === 'panel' ? ' checked' : '' ?>> <?= htmlspecialchars($plain($r['wPanel'])) ?></label>
      </div>
    </div>
    <div class="set-row"><div><div class="set-label"><?= htmlspecialchars($t['bsPanels']) ?></div><div class="set-hint"><?= htmlspecialchars($t['bsPanelsHint']) ?></div></div>
      <div class="set-ctl"><div class="lang-chips">
        <?php foreach ($panels as $pn): ?>
          <label class="lang-chip"><input type="checkbox" name="panels[]" value="<?= htmlspecialchars((string) $pn['code_panel']) ?>"<?= in_array((string) $pn['code_panel'], $raw['panels'], true) ? ' checked' : '' ?>> <?= htmlspecialchars((string) $pn['name_panel']) ?></label>
        <?php endforeach; ?>
      </div></div>
    </div>
  </div>
  <div class="modal-foot" style="border-radius:0 0 10px 10px;display:flex;gap:8px;flex-wrap:wrap">
    <button type="submit" name="action" value="count" class="btn btn-ghost"><?= icon('eye', 13) ?> <?= htmlspecialchars($t['bgCountBtn']) ?></button>
    <button type="submit" name="action" value="start" class="btn btn-primary" onclick="return confirm(<?= htmlspecialchars(json_encode($t['bsStartConfirm'], JSON_UNESCAPED_UNICODE)) ?>)"><?= icon('check', 13) ?> <?= htmlspecialchars($t['bgStartBtn']) ?></button>
  </div>
  </form>
</div>
<script>
  // the four kinds count only with «فقط این‌ها»
  (function () {
    var f = document.getElementById('bsform');
    if (!f) { return; }
    function sync() {
      var who = (f.querySelector('input[name="who"]:checked') || {}).value || 'groups';
      f.querySelectorAll('[data-who] input').forEach(function (el) { el.disabled = who !== 'groups'; });
      f.querySelectorAll('[data-who]').forEach(function (el) { el.style.opacity = who === 'groups' ? '' : '.45'; });
    }
    f.addEventListener('change', sync);
    sync();
  })();
</script>
<?php endif; ?>

<div class="card fade-up" id="history" style="margin-bottom:14px">
  <?= $cardHead('📋 ' . htmlspecialchars($t['bgHistoryTitle']), '') ?>
  <div class="card-body">
    <?php if (!$history): ?><div class="cf"><?= htmlspecialchars($t['bgHistoryEmpty']) ?></div><?php endif; ?>
    <?php foreach ($history as $h): $hc = bulkspeed_counts($h['id']); $hr = $h['state'] === 'done' ? bulkspeed_failed_count($h['id']) : 0; ?>
      <div class="set-row">
        <div>
          <div class="set-label"><?= htmlspecialchars($jobLine($h)) ?></div>
          <div class="set-hint"><?= htmlspecialchars(sprintf($t['bgRowHint'], $stateName[$h['state']] ?? $h['state'], safe_date((int) ($h['started_at'] ?: $h['created_at']), 'Y/m/d H:i'))) ?></div>
        </div>
        <div class="set-ctl" style="text-align:end;white-space:nowrap">
          <span title="<?= htmlspecialchars($t['bsDone']) ?>">✅ <?= number_format($hc['done']) ?></span>
          &nbsp;<span title="<?= htmlspecialchars($t['bgSkipped']) ?>">⏭ <?= number_format($hc['skipped']) ?></span>
          &nbsp;<span title="<?= htmlspecialchars($t['bsFailed']) ?>">❌ <?= number_format($hc['failed']) ?></span>
          <?php if ($hr > 0): ?>
            <?= $formOpen(' style="display:inline-block;margin-inline-start:8px"') ?><input type="hidden" name="action" value="retry"><input type="hidden" name="id" value="<?= (int) $h['id'] ?>"><button class="btn btn-ghost btn-sm" type="submit">🔁 <?= htmlspecialchars(sprintf($t['bgRetryBtn'], number_format($hr))) ?></button></form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
