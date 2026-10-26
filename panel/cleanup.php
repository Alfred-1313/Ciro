<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/langsync.php';
require_auth();

// 🧹 پاکسازی و ریست - the very tools of the bot's 👤 مدیریت کاربر ← 🧹 (cleanup.php):
// the dashboard's three figures starting over, every balance to zero (and
// back), every زیرمجموعه to zero, and the services the bot sold taken out of
// their panels. The tab on top says whose: one language, or all of them.
$t = $textbotlang['panel'];
$lang = web_lang_pick('lang', '');
$langName = $lang === '' ? $t['langAllTab'] : (web_langs()[$lang] ?? $lang);
$back = fn($card = '') => 'cleanup.php?' . http_build_query(array_filter(['lang' => $lang])) . ($card !== '' ? '#' . $card : '');
$by = (string) ($_SESSION['admin_user'] ?? '');
$statNames = ['rev' => $t['dashTotalRevenue'], 'act' => $t['dashActiveService'], 'tx' => $t['dashTodayTransaction']];
clean_ensure_schema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $card = (string) ($_POST['card'] ?? '');
    $act = (string) ($_POST['act'] ?? '');
    $isOn = fn($k) => isset($_POST[$k]) && $_POST[$k] === '1';
    $err = null;
    $okMsg = '';
    if ($card === 'stat') {
        $key = (string) ($_POST['key'] ?? '');
        if (!in_array($key, clean_stat_keys(), true)) {
            $err = $t['configInvalidRequest'];
        } elseif ($act === 'clear') {
            clean_stat_clear([$key]);
            $okMsg = sprintf($t['clnStatBackDone'], $statNames[$key]);
        } else {
            clean_stat_reset([$key]);
            $okMsg = sprintf($t['clnStatResetDone'], $statNames[$key]);
        }
    } elseif ($card === 'bal' && $act === 'undo') {
        [$n] = clean_balance_undo();
        if ($n > 0) {
            $okMsg = sprintf($t['clnBalUndoDone'], number_format($n));
        } else {
            $err = $t['clnBalUndoNone'];
        }
    } elseif ($card === 'bal') {
        if (!$isOn('sure')) {
            $err = $t['clnNeedSure'];
        } else {
            [$n] = clean_balance_zero($lang, $by);
            if ($n > 0) {
                $okMsg = sprintf($t['clnBalDone'], number_format($n));
            } else {
                $err = $t['clnBalNone'];
            }
        }
    } elseif ($card === 'ref') {
        if (!$isOn('sure')) {
            $err = $t['clnNeedSure'];
        } else {
            $n = clean_ref_reset($lang);
            $okMsg = sprintf($t['clnRefDone'], number_format($n));
        }
    } elseif ($card === 'svc' && $act === 'stop') {
        // the report of a stopped run goes to the bot
        ob_start();
        require_once __DIR__ . '/../botapi.php';
        $ok = clean_svc_stop();
        $job = clean_job_current();
        if ($ok && $job) {
            clean_job_notify((int) $job['id']);
        }
        ob_end_clean();
        if ($ok) {
            $okMsg = $t['clnJobStopped'];
        } else {
            $err = $t['clnJobNone'];
        }
    } elseif ($card === 'svc' && $act === 'retry') {
        $n = clean_svc_retry();
        if ($n > 0) {
            bulkgift_kick();
            $okMsg = sprintf($t['clnJobRetried'], number_format($n));
        } else {
            $err = $t['clnJobNone'];
        }
    } elseif ($card === 'svc') {
        if (!clean_word_ok($_POST['word'] ?? '')) {
            $err = $t['clnSvcBadWord'];
        } else {
            [$job, $why] = clean_svc_start($lang, $by, '');
            if ($job > 0) {
                bulkgift_kick();
                $okMsg = sprintf($t['clnSvcStarted'], number_format((int) (clean_job_current()['total'] ?? 0)));
            } else {
                $err = $why === 'busy' ? $t['clnSvcBusy'] : $t['clnSvcNone'];
            }
        }
    }
    flash($err === null ? 'success' : 'error', $err ?? $okMsg);
    // a figure started over from the dashboard goes back to the dashboard
    header('Location: ' . (($_POST['back'] ?? '') === 'dash' ? 'index.php' . ($lang !== '' ? '?lang=' . $lang : '') : $back($card)));
    exit;
}

$bal = clean_balance_preview($lang);
$ref = clean_ref_preview($lang);
$svc = clean_svc_preview($lang);
$lastBal = clean_balance_last();
$job = clean_job_current();
$running = $job !== null && $job['state'] === 'running';
$jc = $job ? clean_job_counts($job['id']) : ['wait' => 0, 'ok' => 0, 'fail' => 0, 'removed' => 0, 'absent' => 0, 'nopanel' => 0, 'unk' => 0];

$cardHead = fn($title, $sub) => '<div class="card-head"><div><div class="card-title">' . $title . '</div><div class="card-subtitle">' . $sub . '</div></div></div>';
$formOpen = fn($card, $extra = '') => '<form method="POST" data-ajax="off" action="cleanup.php?' . http_build_query(array_filter(['lang' => $lang])) . '"' . $extra . '><input type="hidden" name="_csrf" value="' . csrf_token() . '"><input type="hidden" name="card" value="' . $card . '">';
$confirmJs = fn($msg) => ' onsubmit="return confirm(' . htmlspecialchars(json_encode($msg, JSON_UNESCAPED_UNICODE)) . ')"';
$sure = function ($label) use ($t) { return '<div class="set-row"><div><div class="set-label">⚠️ ' . htmlspecialchars($label) . '</div></div><div class="set-ctl"><label class="lang-chip"><input type="hidden" name="sure" value="0"><input type="checkbox" name="sure" value="1"> ' . htmlspecialchars($t['clnSure']) . '</label></div></div>'; };

$pageTitle = $t['clnPageTitle'];
$pageLede = $t['clnPageLede'];
$activeNav = 'cleanup';
include __DIR__ . '/inc/layout_head.php';
echo web_lang_assets();
$cnt = um_lang_counts();
?>

<?= web_lang_tabs($lang, fn($c) => 'cleanup.php?' . http_build_query(array_filter(['lang' => $c])), true, ['' => $cnt['total']] + array_intersect_key($cnt, array_flip(panel_langs()))) ?>

<?php if ($job !== null && ($running || $jc['fail'] > 0 || $job['state'] === 'stopped' || ($jc['removed'] === 0 && time() - (int) ($job['finished_at'] ?: $job['created_at']) < 3 * 86400))):
    $done = $jc['ok'] + $jc['fail'];
    $total = max(1, (int) $job['total']);
    $pct = (int) floor($done * 100 / $total);
    $stateName = ['running' => $t['clnJobRunning'], 'done' => $t['clnJobDone'], 'stopped' => $t['clnJobStoppedState']]; ?>
<div class="card fade-up" id="job" style="margin-bottom:14px">
  <?= $cardHead('⏳ ' . htmlspecialchars($t['clnJobTitle']), htmlspecialchars(sprintf($t['clnJobMeta'], $stateName[$job['state']] ?? $job['state'], $job['scope'] === '' ? $t['langAllTab'] : (web_langs()[$job['scope']] ?? $job['scope']), safe_date((int) $job['created_at'], 'Y/m/d H:i')))) ?>
  <div class="card-body">
    <div class="stats">
      <div class="stat"><div class="stat-label"><?= htmlspecialchars($t['bgProgress']) ?></div><div class="stat-num"><?= $pct ?>٪</div><div class="stat-meta"><?= number_format($done) ?> / <?= number_format((int) $job['total']) ?></div></div>
      <div class="stat ok"><div class="stat-label"><?= htmlspecialchars($t['clnJobOk']) ?></div><div class="stat-num"><?= number_format($jc['removed']) ?></div></div>
      <div class="stat"><div class="stat-label"><?= htmlspecialchars($t['clnJobOnlyBot']) ?></div><div class="stat-num"><?= number_format($jc['absent'] + $jc['nopanel'] + $jc['unk']) ?></div></div>
      <div class="stat warn"><div class="stat-label"><?= htmlspecialchars($t['clnJobWait']) ?></div><div class="stat-num"><?= number_format($jc['wait']) ?></div></div>
      <div class="stat <?= $jc['fail'] > 0 ? 'no' : '' ?>"><div class="stat-label"><?= htmlspecialchars($t['clnJobFail']) ?></div><div class="stat-num"><?= number_format($jc['fail']) ?></div></div>
    </div>
    <?php if (!$running && $jc['removed'] === 0 && $jc['unk'] === 0 && $jc['ok'] > 0): ?>
    <div class="notice notice-warn" style="margin-top:12px"><?= htmlspecialchars($t['clnJobNoneRemoved']) ?></div>
    <?php endif; ?>
    <?php if ($jc['fail'] > 0):
        $failed = db_fetchAll($pdo, "SELECT username, location, why FROM clean_item WHERE job_id = ? AND state = 'fail' ORDER BY id LIMIT 20", [$job['id']]); ?>
    <div class="tbl-wrap" style="margin-top:12px"><table class="tbl-sm"><thead><tr><th><?= htmlspecialchars($t['clnJobColService']) ?></th><th><?= htmlspecialchars($t['clnJobColPanel']) ?></th><th><?= htmlspecialchars($t['clnJobColWhy']) ?></th></tr></thead><tbody>
      <?php foreach ($failed as $f): ?><tr><td><code><?= htmlspecialchars((string) $f['username']) ?></code></td><td><?= htmlspecialchars((string) $f['location']) ?></td><td><?= htmlspecialchars((string) $f['why']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
      <?php if ($running): ?>
        <?= $formOpen('svc', $confirmJs($t['clnJobStopConfirm'])) ?><input type="hidden" name="act" value="stop"><button class="btn btn-no btn-sm" type="submit"><?= icon('close', 13) ?> <?= htmlspecialchars($t['clnJobStop']) ?></button></form>
      <?php elseif ($jc['fail'] > 0 || $jc['wait'] > 0): ?>
        <?= $formOpen('svc') ?><input type="hidden" name="act" value="retry"><button class="btn btn-primary btn-sm" type="submit">🔁 <?= htmlspecialchars($t['clnJobRetry']) ?></button></form>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php if ($running): ?>
<script>
  // up to date every few seconds - unless something is being typed
  (function () {
    var touched = false;
    document.addEventListener('input', function () { touched = true; }, true);
    document.addEventListener('change', function () { touched = true; }, true);
    setTimeout(function () { if (!touched) { location.reload(); } }, 6000);
  })();
</script>
<?php endif; ?>
<?php endif; ?>

<div class="card fade-up" id="stat" style="margin-bottom:14px">
  <?= $cardHead('📊 ' . htmlspecialchars($t['clnStatTitle']), htmlspecialchars($t['clnStatSub'])) ?>
  <div class="card-body">
    <?php foreach ($statNames as $key => $label): $base = clean_stat_base($key); ?>
      <div class="set-row">
        <div>
          <div class="set-label"><?= htmlspecialchars($label) ?></div>
          <div class="set-hint"><?= $base > 0 ? htmlspecialchars(sprintf($t['clnStatSince'], safe_date($base, 'Y/m/d H:i'))) : htmlspecialchars($t['clnStatAll']) ?></div>
        </div>
        <div class="set-ctl" style="display:flex;gap:8px;flex-wrap:wrap">
          <?= $formOpen('stat', $confirmJs($t['clnStatConfirm'])) ?><input type="hidden" name="key" value="<?= $key ?>"><input type="hidden" name="act" value="reset"><button class="btn btn-no btn-sm" type="submit">↺ <?= htmlspecialchars($t['clnStatReset']) ?></button></form>
          <?php if ($base > 0): ?>
            <?= $formOpen('stat') ?><input type="hidden" name="key" value="<?= $key ?>"><input type="hidden" name="act" value="clear"><button class="btn btn-sm" type="submit"><?= htmlspecialchars($t['clnStatBack']) ?></button></form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="card fade-up" id="bal" style="margin-bottom:14px">
  <?= $cardHead('💰 ' . htmlspecialchars($t['clnBalTitle']), htmlspecialchars(sprintf($t['clnBalSub'], $langName))) ?>
  <?= $formOpen('bal') ?>
  <div class="card-body">
    <div class="field-hint"><?= $bal['users'] > 0
        ? htmlspecialchars(sprintf($t['clnBalCount'], number_format($bal['users']), number_format($bal['positive']), number_format($bal['negative']), number_format($bal['stash'])))
        : '✨ ' . htmlspecialchars($t['clnBalNone']) ?></div>
    <?php if ($lastBal !== null): ?>
      <div class="notice notice-warn" style="margin-top:10px"><?= htmlspecialchars(sprintf($t['clnBalLast'], safe_date((int) $lastBal['created_at'], 'Y/m/d H:i'), $lastBal['scope'] === '' ? $t['langAllTab'] : (web_langs()[$lastBal['scope']] ?? $lastBal['scope']), number_format((int) $lastBal['users']))) ?></div>
    <?php endif; ?>
    <?= $sure($t['clnBalSure']) ?>
  </div>
  <div class="modal-foot" style="border-radius:0 0 10px 10px;display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap">
    <?php if ($lastBal !== null): ?>
      <button type="submit" name="act" value="undo" class="btn" formnovalidate onclick="return confirm(<?= htmlspecialchars(json_encode($t['clnBalUndoConfirm'], JSON_UNESCAPED_UNICODE)) ?>)">↩️ <?= htmlspecialchars($t['clnBalUndo']) ?></button>
    <?php endif; ?>
    <button type="submit" class="btn btn-no"<?= $bal['users'] === 0 ? ' disabled' : '' ?>>💰 <?= htmlspecialchars($t['clnBalBtn']) ?></button>
  </div>
  </form>
</div>

<div class="card fade-up" id="ref" style="margin-bottom:14px">
  <?= $cardHead('👥 ' . htmlspecialchars($t['clnRefTitle']), htmlspecialchars(sprintf($t['clnRefSub'], $langName))) ?>
  <?= $formOpen('ref') ?>
  <div class="card-body">
    <div class="field-hint"><?= $ref['users'] > 0
        ? htmlspecialchars(sprintf($t['clnRefCount'], number_format($ref['invited']), number_format($ref['inviters'])))
        : '✨ ' . htmlspecialchars($t['clnRefNone']) ?></div>
    <?= $sure($t['clnRefSure']) ?>
  </div>
  <div class="modal-foot" style="border-radius:0 0 10px 10px"><button type="submit" class="btn btn-no"<?= $ref['users'] === 0 ? ' disabled' : '' ?>>👥 <?= htmlspecialchars($t['clnRefBtn']) ?></button></div>
  </form>
</div>

<div class="card fade-up" id="svc" style="margin-bottom:14px">
  <?= $cardHead('🗑 ' . htmlspecialchars($t['clnSvcTitle']), htmlspecialchars(sprintf($t['clnSvcSub'], $langName))) ?>
  <?= $formOpen('svc') ?>
  <div class="card-body">
    <div class="field-hint"><?= $svc['services'] > 0
        ? htmlspecialchars(sprintf($t['clnSvcCount'], number_format($svc['services']), number_format($svc['users']), number_format($svc['panels'])))
        : '✨ ' . htmlspecialchars($t['clnSvcNone']) ?></div>
    <?php if ($svc['nopanel'] > 0): ?><div class="notice notice-warn" style="margin-top:10px"><?= htmlspecialchars(sprintf($t['clnSvcNoPanel'], number_format($svc['nopanel']))) ?></div><?php endif; ?>
    <div class="set-row"><div><div class="set-label">⚠️ <?= htmlspecialchars(sprintf($t['clnSvcType'], $t['clnSvcWord'])) ?></div><div class="set-hint"><?= htmlspecialchars($t['clnSvcKeeps']) ?></div></div>
      <div class="set-ctl"><input type="text" class="input" name="word" value="" autocomplete="off" <?= $running ? 'disabled' : '' ?>></div></div>
  </div>
  <div class="modal-foot" style="border-radius:0 0 10px 10px"><button type="submit" class="btn btn-no"<?= ($svc['services'] === 0 || $running) ? ' disabled' : '' ?>>🗑 <?= htmlspecialchars($t['clnSvcBtn']) ?></button></div>
  </form>
</div>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
