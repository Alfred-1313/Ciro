<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/langsync.php';
require_auth();

// 🎁 حجم یا زمان گروهی - the very run the bot's 🏬 تنظیمات فروشگاه ← 🎁
// starts (bulkgift.php): set up, counted and started here, then followed
// here until it ends. Its progress message and its report come to the admin
// in the bot as well, with the bot's own 🚫 لغو. A 🔜 purchase campaign and
// one other run can go on side by side.
$t = $textbotlang['panel'];
$r = bulkgift_texts();
$panels = bulkgift_all_panels();
$adminId = (string) (db_fetch($pdo, "SELECT id_admin FROM admin WHERE username = ?", [$_SESSION['admin_user']])['id_admin'] ?? '');

// what the form says, as it was sent - it is shown again as it was
$raw = [
    'job' => 'now', 'kind' => 'volume', 'mode' => 'fixed', 'value' => '', 'scope' => 'bot', 'lang' => 'all',
    'panels' => [], 'buy' => 'all', 'buyn' => '', 'hours' => '', 'statuses' => bulkgift_default_statuses(), 'refs' => '0', 'notify' => '1',
];
$err = null;
$preview = null;
$previewLive = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'cancel') {
        // what was given stays; the report goes to the bot. Whatever the
        // Telegram code prints on the way is not this page's: it would stop
        // the redirect below.
        ob_start();
        require_once __DIR__ . '/../botapi.php';
        $ok = bulkgift_cancel((int) ($_POST['id'] ?? 0));
        ob_end_clean();
        flash($ok ? 'success' : 'error', $ok ? $t['bgCancelled'] : strip_tags($r['cancelGone']));
        header('Location: bulkgift.php');
        exit;
    }
    if ($action === 'retry') {
        // 🔁 a finished run's failed accounts once more (its new report goes
        // to the bot)
        ob_start();
        require_once __DIR__ . '/../botapi.php';
        $res = bulkgift_retry((int) ($_POST['id'] ?? 0));
        if (!isset($res['error'])) {
            bulkgift_kick();
        }
        ob_end_clean();
        flash(isset($res['error']) ? 'error' : 'success', isset($res['error']) ? strip_tags($res['error']) : sprintf($t['bgRetried'], number_format($res['n'])));
        header('Location: bulkgift.php');
        exit;
    }
    foreach (['job', 'kind', 'mode', 'value', 'scope', 'lang', 'buy', 'buyn', 'hours', 'refs', 'notify'] as $k) {
        $raw[$k] = trim((string) ($_POST[$k] ?? $raw[$k]));
    }
    $raw['panels'] = array_values(array_map('strval', (array) ($_POST['panels'] ?? [])));
    $raw['statuses'] = array_values(array_intersect(bulkgift_statuses(), array_map('strval', (array) ($_POST['statuses'] ?? []))));
    $camp = $raw['job'] === 'camp';
    $kind = $raw['kind'] === 'time' ? 'time' : 'volume';
    $mode = in_array($raw['mode'], ['total', 'left'], true) ? $raw['mode'] : 'fixed';
    [$value, $why] = $raw['value'] === '' ? [null, $r['noValue']] : bulkgift_parse_value($kind, $mode, $raw['value']);
    // 🔜 a campaign: its length in hours; otherwise the purchase filter
    $buy = $camp ? 'next' : (in_array($raw['buy'], ['lasth', 'days', 'months', 'hours'], true) ? $raw['buy'] : 'all');
    $buyn = 0;
    if ($why === null && $camp) {
        $buyn = bulkgift_parse_count($raw['hours']);
        if ($buyn === null || $buyn > bulkgift_max_next_hours()) {
            $why = $r['badNext'] . ' — ' . $t['bgHours'];
        }
    } elseif ($why === null && $buy !== 'all') {
        $buyn = bulkgift_parse_count($raw['buyn']);
        if ($buyn === null) {
            $why = $r['badCount'] . ' — ' . $t['bgBuyN'];
        }
    }
    if ($why === null && !$camp && !$raw['statuses']) {
        $why = $r['noStatus'];
    }
    $p = bulkgift_params([
        'kind' => $kind, 'mode' => $mode, 'value' => $value,
        'scope' => $raw['scope'], 'lang' => $raw['lang'], 'panels' => $raw['panels'],
        'buy' => $buy, 'buyn' => (int) $buyn,
        'statuses' => $raw['statuses'], 'refs' => $raw['refs'] === '1', 'notify' => $raw['notify'] === '1',
    ]);
    if ($why !== null) {
        $err = strip_tags($why);
    } elseif ($p['panels'] && !bulkgift_panels_of($p)) {
        $err = strip_tags($r['noPanels']);
    } elseif ($action === 'start') {
        $new = bulkgift_create($p, $adminId, 'web');
        if (isset($new['error'])) {
            $err = strip_tags($new['error']);
        } else {
            bulkgift_kick();
            flash('success', $t['bgStarted']);
            header('Location: bulkgift.php');
            exit;
        }
    } else {
        // «تعداد رو ببین»: what the bot's summary shows
        $preview = bulkgift_preview($p);
        $previewLive = bulkgift_is_live($p);
    }
}

$jobs = bulkgift_active_jobs();
$activeIds = array_map(fn($j) => (int) $j['id'], $jobs);
$history = array_values(array_filter(bulkgift_recent(10), fn($j) => !in_array((int) $j['id'], $activeIds, true)));
$stateName = ['queued' => $t['bgStateQueued'], 'running' => $t['bgStateRunning'], 'done' => $t['bgStateDone'], 'cancelled' => $t['bgStateCancelled']];
$plain = fn($s) => trim(strip_tags((string) $s));

$field = function ($label, $ctl, $hint = '') {
    return '<div class="set-row"><div><div class="set-label">' . htmlspecialchars($label) . '</div>' . ($hint !== '' ? '<div class="set-hint">' . htmlspecialchars($hint) . '</div>' : '') . '</div><div class="set-ctl">' . $ctl . '</div></div>';
};
$select = function ($name, $options, $value) {
    $h = '<select class="select" name="' . $name . '">';
    foreach ($options as $v => $label) {
        $h .= '<option value="' . htmlspecialchars((string) $v) . '"' . ((string) $v === (string) $value ? ' selected' : '') . '>' . htmlspecialchars($label) . '</option>';
    }
    return $h . '</select>';
};
$input = fn($name, $value, $extra = '') => '<input type="text" class="input" name="' . $name . '" value="' . htmlspecialchars((string) $value) . '" ' . $extra . '>';
$cardHead = fn($title, $sub) => '<div class="card-head"><div><div class="card-title">' . $title . '</div><div class="card-subtitle">' . $sub . '</div></div></div>';
$formOpen = fn($extra = '') => '<form method="POST" action="bulkgift.php"' . $extra . '><input type="hidden" name="_csrf" value="' . csrf_token() . '">';
// a run's settings in a line
$jobLine = function (array $j) use ($r, $plain) {
    $p = bulkgift_job_params($j);
    return $plain(bulkgift_what_label($p)) . ' · ' . $plain(bulkgift_is_live($p) ? $r['campBtn'] . ' ' . bulkgift_camp_label($p) : bulkgift_who_label($p));
};
// 🎯 opens by itself when something in it is not the usual setting, the way
// the bot's summary lists those
$moreOpen = $raw['scope'] !== 'bot' || $raw['lang'] !== 'all' || $raw['notify'] !== '1'
    || ($raw['job'] !== 'camp' && ($raw['buy'] !== 'all' || $raw['statuses'] !== bulkgift_default_statuses() || $raw['refs'] === '1'));
$langOptions = ['all' => $plain($r['langAll'])];
foreach (web_langs() as $code => $name) {
    $langOptions[$code] = $name;
}

$pageTitle = $t['bgPageTitle'];
$pageLede = $t['bgPageLede'];
$activeNav = 'bulkgift';
include __DIR__ . '/inc/layout_head.php';
?>

<?php if ($err !== null): ?>
  <div class="notice notice-no"><?= htmlspecialchars($err) ?></div>
<?php endif; ?>

<?php foreach ($jobs as $job): $jp = bulkgift_job_params($job); $live = bulkgift_is_live($jp); $counts = bulkgift_counts($job['id']);
  $done = $counts['total'] - $counts['pending'] - $counts['claimed']; $pct = $counts['total'] > 0 ? (int) floor($done * 100 / $counts['total']) : 0; ?>
<div class="card fade-up" id="now<?= (int) $job['id'] ?>" style="margin-bottom:14px">
  <?= $cardHead(($live ? '🔜 ' . htmlspecialchars($t['bgLiveTitle']) : '⏳ ' . htmlspecialchars($t['bgNowTitle'])), htmlspecialchars($live ? sprintf($t['bgLiveSub'], $plain(bulkgift_when($jp['win_to'] ?? time()))) : $t['bgNowSub'])) ?>
  <div class="card-body">
    <div class="set-row">
      <div>
        <div class="set-label"><?= htmlspecialchars($jobLine($job)) ?></div>
        <div class="set-hint"><?= htmlspecialchars(sprintf($t['bgRowHint'], $stateName[$job['state']] ?? $job['state'], safe_date((int) ($job['started_at'] ?: $job['created_at']), 'Y/m/d H:i'))) ?></div>
      </div>
      <div class="set-ctl">
        <?= $formOpen(' onsubmit="return confirm(' . htmlspecialchars(json_encode($t['bgCancelConfirm'], JSON_UNESCAPED_UNICODE)) . ')"') ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= (int) $job['id'] ?>"><button class="btn btn-no btn-sm" type="submit"><?= icon('close', 13) ?> <?= htmlspecialchars($t['bgCancelBtn']) ?></button></form>
      </div>
    </div>
    <?php if ((int) $job['prepared']): ?>
    <div class="stats" style="margin-top:12px">
      <?php if (!$live): ?>
      <div class="stat"><div class="stat-label"><?= htmlspecialchars($t['bgProgress']) ?></div><div class="stat-num"><?= $pct ?>٪</div><div class="stat-meta"><?= number_format($done) ?> / <?= number_format($counts['total']) ?></div></div>
      <?php endif; ?>
      <div class="stat ok"><div class="stat-label"><?= htmlspecialchars($t['bgDone']) ?></div><div class="stat-num"><?= number_format($counts['done']) ?></div></div>
      <div class="stat warn"><div class="stat-label"><?= htmlspecialchars($t['bgSkipped']) ?></div><div class="stat-num"><?= number_format($counts['skipped']) ?></div></div>
      <div class="stat <?= $counts['failed'] + $counts['unknown'] > 0 ? 'no' : '' ?>"><div class="stat-label"><?= htmlspecialchars($t['bgFailed']) ?></div><div class="stat-num"><?= number_format($counts['failed'] + $counts['unknown']) ?></div></div>
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
    setTimeout(function () { if (!touched) { location.href = 'bulkgift.php'; } }, 5000);
  })();
</script>
<?php endif; ?>

<style>
  #bgmore > summary::-webkit-details-marker { display: none; }
  #bgmore .bg-chev { display: inline-block; font-size: 1.1rem; color: var(--mute); transition: transform .15s; }
  #bgmore[open] .bg-chev { transform: rotate(180deg); }
</style>
<div class="card fade-up" id="form" style="margin-bottom:14px">
  <?= $cardHead('🎁 ' . htmlspecialchars($t['bgFormTitle']), htmlspecialchars($t['bgFormSub'])) ?>
  <details id="bgguide" class="notice" style="margin:12px 16px 0"><summary style="cursor:pointer;font-weight:700">📖 <?= htmlspecialchars($t['bgGuide']) ?></summary><div style="margin-top:8px;line-height:1.9"><?= nl2br(htmlspecialchars($t['bgGuideText'])) ?></div></details>
  <?php if ($preview !== null): ?>
    <div class="notice notice-ok" style="margin:12px 16px 0">
      <?php if ($previewLive): ?>
        <?= htmlspecialchars($plain(strtr($r['sumCampLine'], ['{hours}' => number_format($p['buyn']), '{what}' => bulkgift_what_text($p)]))) ?>
        <br><?= htmlspecialchars($plain($r['sumCampNote'])) ?>
      <?php else: ?>
        <?= htmlspecialchars(sprintf($t['bgCountResult'], number_format($preview['n']), number_format($preview['h']), number_format($preview['t']))) ?>
        <?php if ($preview['r'] > 0): ?><br><?= htmlspecialchars(sprintf($t['bgCountRefs'], number_format($preview['r']))) ?><?php endif; ?>
        <?php if ($raw['scope'] === 'panel'): ?><br><?= htmlspecialchars($plain($r['sumPanelNote'])) ?><?php endif; ?>
        <?php if ($preview['unsupported']): ?><br><?= htmlspecialchars($plain(strtr($r['sumUnsupported'], ['{list}' => implode('، ', $preview['unsupported'])]))) ?><?php endif; ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <div class="notice notice-warn" style="margin:12px 16px 0"><?= nl2br(htmlspecialchars($plain($r['skipNote']))) ?></div>
  <?= $formOpen(' id="bgform"') ?>
  <div class="card-body">
    <?= $field($t['bgJob'], $select('job', ['now' => $plain($r['jobNow']), 'camp' => $plain($r['campBtn'])], $raw['job']), $t['bgJobHint']) ?>
    <?= $field($t['bgKind'], $select('kind', ['volume' => $plain($r['kindVolume']), 'time' => $plain($r['kindTime'])], $raw['kind'])) ?>
    <?= $field($t['bgMode'], $select('mode', ['fixed' => $plain($r['modeFixedBtn']), 'total' => $plain($r['modeTotalBtn']), 'left' => $plain($r['modeLeftBtn'])], $raw['mode'])) ?>
    <?= $field($t['bgValue'], $input('value', $raw['value'], 'inputmode="decimal" dir="ltr" style="max-width:160px" placeholder="5"'), $t['bgValueHint']) ?>
    <div class="set-row"><div><div class="set-label"><?= htmlspecialchars($t['bgPanels']) ?></div><div class="set-hint"><?= htmlspecialchars($t['bgPanelsHint']) ?></div></div>
      <div class="set-ctl"><div class="lang-chips">
        <?php foreach ($panels as $pn): ?>
          <label class="lang-chip"><input type="checkbox" name="panels[]" value="<?= htmlspecialchars((string) $pn['code_panel']) ?>"<?= in_array((string) $pn['code_panel'], $raw['panels'], true) ? ' checked' : '' ?>> <?= htmlspecialchars((string) $pn['name_panel']) ?><?= bulkgift_listable($pn) ? '' : htmlspecialchars($r['panelBotOnly']) ?></label>
        <?php endforeach; ?>
      </div></div>
    </div>
    <div data-job="camp"><?= $field($t['bgHours'], $input('hours', $raw['hours'], 'inputmode="numeric" dir="ltr" style="max-width:110px" placeholder="24"'), $t['bgHoursHint']) ?></div>
    <details id="bgmore"<?= $moreOpen ? ' open' : '' ?>>
      <summary class="set-row" style="cursor:pointer;list-style:none"><div><div class="set-label">🎯 <?= htmlspecialchars($t['bgMore']) ?></div><div class="set-hint"><?= htmlspecialchars($t['bgMoreHint']) ?></div></div><div class="set-ctl"><span class="bg-chev" aria-hidden="true">▾</span></div></summary>
      <div data-job="now"><?= $field($t['bgScope'], $select('scope', ['bot' => $plain($r['whoBot']), 'panel' => $plain($r['whoPanel'])], $raw['scope'])) ?></div>
      <div data-scope="bot"><?= $field($t['bgLang'], $select('lang', $langOptions, $raw['lang']), $t['bgLangHint']) ?></div>
      <div data-job="now">
        <?= $field($t['bgBuy'], '<div style="display:flex;gap:6px;flex-wrap:wrap">' . $select('buy', [
            'all' => $plain($r['buyAll']), 'lasth' => $plain($r['buyLasthBtn']),
            'days' => $plain($r['buyDaysBtn']), 'months' => $plain($r['buyMonthsBtn']), 'hours' => $plain($r['buyHoursBtn']),
        ], $raw['buy']) . $input('buyn', $raw['buyn'], 'inputmode="numeric" dir="ltr" style="max-width:110px" placeholder="1"') . '</div>', $t['bgBuyHint']) ?>
        <div class="set-row"><div><div class="set-label"><?= htmlspecialchars($t['bgStatus']) ?></div><div class="set-hint"><?= htmlspecialchars($t['bgStatusHint']) ?></div></div>
          <div class="set-ctl"><div class="lang-chips">
            <?php foreach (bulkgift_statuses() as $s): ?>
              <label class="lang-chip"><input type="checkbox" name="statuses[]" value="<?= $s ?>"<?= in_array($s, $raw['statuses'], true) ? ' checked' : '' ?>> <?= htmlspecialchars($plain($r['st_' . $s])) ?></label>
            <?php endforeach; ?>
          </div></div>
        </div>
        <div class="set-row"><div><div class="set-label">🎁 <?= htmlspecialchars($t['bgRefs']) ?></div><div class="set-hint"><?= htmlspecialchars($t['bgRefsHint']) ?></div></div>
          <div class="set-ctl"><label class="lang-chip" style="justify-content:center"><input type="hidden" name="refs" value="0"><input type="checkbox" name="refs" value="1"<?= $raw['refs'] === '1' ? ' checked' : '' ?>> <?= htmlspecialchars($t['bgRefsOn']) ?></label></div>
        </div>
      </div>
      <div class="set-row"><div><div class="set-label"><?= htmlspecialchars($t['bgNotify']) ?></div><div class="set-hint"><?= htmlspecialchars($t['bgNotifyHint']) ?></div></div>
        <div class="set-ctl"><label class="lang-chip" style="justify-content:center"><input type="hidden" name="notify" value="0"><input type="checkbox" name="notify" value="1"<?= $raw['notify'] === '1' ? ' checked' : '' ?>> <?= htmlspecialchars($t['refOn']) ?></label></div>
      </div>
    </details>
  </div>
  <div class="modal-foot" style="border-radius:0 0 10px 10px;display:flex;gap:8px;flex-wrap:wrap">
    <button type="submit" name="action" value="count" class="btn btn-ghost"><?= icon('eye', 13) ?> <?= htmlspecialchars($t['bgCountBtn']) ?></button>
    <button type="submit" name="action" value="start" class="btn btn-primary" onclick="return confirm(<?= htmlspecialchars(json_encode($t['bgStartConfirm'], JSON_UNESCAPED_UNICODE)) ?>)"><?= icon('check', 13) ?> <?= htmlspecialchars($t['bgStartBtn']) ?></button>
  </div>
  </form>
</div>
<script>
  // only what this kind of run uses: a campaign has its length, the other
  // its purchase filter and states; the language is for the bot's users
  (function () {
    var f = document.getElementById('bgform');
    if (!f) { return; }
    function sync() {
      var job = f.elements['job'].value, scope = f.elements['scope'].value;
      f.querySelectorAll('[data-job]').forEach(function (el) { el.style.display = el.getAttribute('data-job') === job ? '' : 'none'; });
      f.querySelectorAll('[data-scope]').forEach(function (el) { el.style.display = (job === 'camp' || scope === el.getAttribute('data-scope')) ? '' : 'none'; });
    }
    f.addEventListener('change', sync);
    sync();
  })();
</script>

<div class="card fade-up" id="history" style="margin-bottom:14px">
  <?= $cardHead('📋 ' . htmlspecialchars($t['bgHistoryTitle']), '') ?>
  <div class="card-body">
    <?php if (!$history): ?><div class="cf"><?= htmlspecialchars($t['bgHistoryEmpty']) ?></div><?php endif; ?>
    <?php foreach ($history as $h): $hc = bulkgift_counts($h['id']); $hr = $h['state'] === 'done' && !bulkgift_is_live(bulkgift_job_params($h)) ? count(bulkgift_retryable_ids($h['id'])) : 0; ?>
      <div class="set-row">
        <div>
          <div class="set-label"><?= htmlspecialchars($jobLine($h)) ?></div>
          <div class="set-hint"><?= htmlspecialchars(sprintf($t['bgRowHint'], $stateName[$h['state']] ?? $h['state'], safe_date((int) ($h['started_at'] ?: $h['created_at']), 'Y/m/d H:i'))) ?></div>
        </div>
        <div class="set-ctl" style="text-align:end;white-space:nowrap">
          <span title="<?= htmlspecialchars($t['bgDone']) ?>">✅ <?= number_format($hc['done']) ?></span>
          &nbsp;<span title="<?= htmlspecialchars($t['bgSkipped']) ?>">⏭ <?= number_format($hc['skipped']) ?></span>
          &nbsp;<span title="<?= htmlspecialchars($t['bgFailed']) ?>">❌ <?= number_format($hc['failed'] + $hc['unknown']) ?></span>
          <?php if ($hr > 0): ?>
            <?= $formOpen(' style="display:inline-block;margin-inline-start:8px"') ?><input type="hidden" name="action" value="retry"><input type="hidden" name="id" value="<?= (int) $h['id'] ?>"><button class="btn btn-ghost btn-sm" type="submit">🔁 <?= htmlspecialchars(sprintf($t['bgRetryBtn'], number_format($hr))) ?></button></form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
