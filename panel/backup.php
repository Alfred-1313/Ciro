<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

// بکاپ ← ایمپورت بکاپ on the web: the bot's 🗄 تنظیمات بکاپ ← 📥 ایمپورت بکاپ
// (backup_import.php) with an upload in place of a Telegram file, so the
// only size limit is the server's own. The files wait in storage/restore; the
// root watcher (Ciro selfupdate-watch) puts them in place the way the
// script's Restore (menu 7) does, and writes how far it is to
// restore_status.json, which this page reads back.
$t = $textbotlang['panel'];
$owner = 'web';
$self = 'backup.php';
$why = fn($code) => $t['bkpWhy_' . $code] ?? $t['bkpWhy_damaged'];
// the smaller of the two limits a single upload meets
$iniBytes = function ($v) {
    $v = trim((string) $v);
    $n = (float) $v;
    switch (strtolower(substr($v, -1))) {
        case 'g': $n *= 1024;
        // no break
        case 'm': $n *= 1024;
        // no break
        case 'k': $n *= 1024;
    }
    return (int) $n;
};
$limits = array_filter([$iniBytes(ini_get('upload_max_filesize')), $iniBytes(ini_get('post_max_size'))]);
$limit = $limits ? min($limits) : 0;
$limitText = $limit > 0 ? bkimp_size($limit) : '—';

// how far the restore is, for the page's own polling
if (isset($_GET['status'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $run = bkimp_run_status();
    echo json_encode(['state' => $run['state'] ?? '', 'pct' => (int) ($run['pct'] ?? 0)]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // a body over post_max_size arrives empty - no token, no files - so it says so before the token is asked for
    if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        flash('error', sprintf($t['bkpTooBig'], $t['bkpUploadLabel'], $limitText));
        header('Location: ' . $self . '#imp');
        exit;
    }
    csrf_check_post();
    $card = (string) ($_POST['card'] ?? '');
    $id = preg_match('/^[a-f0-9]{6}$/', (string) ($_POST['id'] ?? '')) ? (string) $_POST['id'] : '';
    $ok = [];
    $err = [];
    $st = bkimp_state($owner);
    if ($card === 'up') {
        if (bkimp_busy()) {
            $err[] = $t['bkpBusy'];
        } elseif (!bkimp_watcher_ready()) {
            $err[] = $t['bkpNoWatcher'];
        } else {
            $f = $_FILES['files'] ?? null;
            $n = is_array($f['name'] ?? null) ? count($f['name']) : 0;
            $any = false;
            for ($i = 0; $i < $n && $i < 2; $i++) {
                $name = mb_substr(basename((string) $f['name'][$i]), 0, 80);
                $code = (int) $f['error'][$i];
                if ($code === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $any = true;
                if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
                    $err[] = sprintf($t['bkpTooBig'], $name, $limitText);
                    continue;
                }
                $path = $code === UPLOAD_ERR_OK ? bkimp_upload((string) $f['tmp_name'][$i]) : '';
                if ($path === '') {
                    $err[] = sprintf($t['bkpUploadFail'], $name);
                    continue;
                }
                [$st, $w] = bkimp_take($owner, $path, $name);
                if ($w === '') {
                    $ok[] = sprintf($t['bkpAdded'], $name);
                } else {
                    $err[] = sprintf($t['bkpNotBackup'], $name, $why($w));
                }
            }
            if (!$any) {
                $err[] = $t['bkpNoFile'];
            }
        }
    } elseif ($card === 'pass' && $id !== '') {
        [$st, $w, $name, $dropped] = bkimp_password($owner, (string) ($_POST['pw'] ?? ''), $id);
        if ($w === '') {
            $ok[] = $t['bkpUnlocked'];
        } elseif ($dropped) {
            $err[] = sprintf($t['bkpNotBackup'], $name, $why($w));
        } else {
            $err[] = $w === 'password' ? sprintf($t['bkpPassWrong'], $name) : sprintf($t['bkpPassFail'], $name, $why($w));
        }
    } elseif ($card === 'pick' && $id !== '') {
        bkimp_pick($owner, $id, (int) ($_POST['k'] ?? -1));
    } elseif ($card === 'rm' && $id !== '') {
        bkimp_remove($owner, $id);
        $ok[] = $t['bkpRemoved'];
    } elseif ($card === 'cancel') {
        bkimp_reset($owner);
        $ok[] = $t['bkpCancelled'];
    } elseif ($card === 'new') {
        unset($st['run']);
        bkimp_state($owner, $st);
    } elseif ($card === 'go') {
        if (($_POST['sure'] ?? '') !== '1') {
            $err[] = $t['clnNeedSure'];
        } elseif (!bkimp_ready($st)) {
            $err[] = $t['bkpNotReady'];
        } elseif (bkimp_busy()) {
            $err[] = $t['bkpBusy'];
        } elseif (!bkimp_watcher_ready()) {
            $err[] = $t['bkpNoWatcher'];
        } elseif (!bkimp_request($st)) {
            $err[] = $t['bkpQueueFail'];
        } else {
            // the files are the watcher's now; this page only remembers that it asked
            bkimp_state($owner, ['items' => [], 'msg' => 0, 'run' => time()]);
            $ok[] = $t['bkpQueued'];
        }
    }
    if ($err) {
        flash('error', implode(' — ', $err));
    }
    if ($ok) {
        flash('success', implode(' — ', $ok));
    }
    header('Location: ' . $self . (in_array($card, ['go', 'new'], true) ? '' : '#imp'));
    exit;
}

$st = bkimp_state($owner);
$run = bkimp_run_status();
$runAt = (int) ($st['run'] ?? 0);
// a restore this page asked for: in the queue, under way, or how it went
$showRun = $runAt > 0 && $run !== null && ($run['state'] === 'queued' || $run['at'] >= $runAt - 5);
$live = $showRun && in_array($run['state'], ['queued', 'running'], true);
$busy = bkimp_busy();
$watcher = bkimp_watcher_ready();
$ready = bkimp_ready($st);
$kinds = array_column($st['items'], 'kind');

$cardHead = fn($title, $sub) => '<div class="card-head"><div><div class="card-title">' . $title . '</div><div class="card-subtitle">' . $sub . '</div></div></div>';
$formOpen = fn($card, $extra = '') => '<form method="POST" data-ajax="off" action="' . $self . '"' . $extra . '><input type="hidden" name="_csrf" value="' . csrf_token() . '"><input type="hidden" name="card" value="' . $card . '">';
$confirmJs = fn($msg) => ' onsubmit="return confirm(' . htmlspecialchars(json_encode($msg, JSON_UNESCAPED_UNICODE)) . ')"';

$pageTitle = $t['bkpPageTitle'];
$pageLede = $t['bkpPageLede'];
$activeNav = 'backup';
include __DIR__ . '/inc/layout_head.php';
?>
<style>
  .bk-item { display: grid; grid-template-columns: 40px 1fr; gap: 12px; padding: 14px 0; border-bottom: 1px solid var(--bd); }
  .bk-item:last-child { border-bottom: 0; }
  .bk-ico { width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; background: var(--acs); color: var(--ac); }
  .bk-ico.locked { background: var(--warns); color: var(--warn); }
  .bk-name { font-weight: 600; overflow-wrap: anywhere; }
  .bk-meta { color: var(--mute); font-size: 12.5px; margin-top: 2px; }
  .bk-line { font-size: 13px; color: var(--text2); margin-top: 6px; line-height: 1.7; }
  .bk-line.dim { color: var(--mute); }
  .bk-act { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-top: 10px; }
  .bk-act .input, .bk-act select { max-width: 280px; flex: 1 1 180px; }
  .bk-drop { display: grid; gap: 6px; justify-items: center; text-align: center; padding: 22px 16px; border: 1.5px dashed var(--bds); border-radius: 14px; background: var(--soft); cursor: pointer; transition: border-color var(--tf), background-color var(--tf); }
  .bk-drop:hover, .bk-drop.over { border-color: var(--ac); background: var(--acs); }
  .bk-drop input[type=file] { position: absolute; width: 1px; height: 1px; opacity: 0; pointer-events: none; }
  .bk-drop .bk-pick { font-weight: 600; }
  .bk-drop .bk-chosen { color: var(--text2); font-size: 13px; overflow-wrap: anywhere; }
  .bk-bar { height: 10px; border-radius: 99px; background: var(--sf3); overflow: hidden; margin-top: 12px; }
  .bk-bar > i { display: block; height: 100%; width: 0; border-radius: inherit; background: var(--grad, var(--ac)); transform-origin: left center; transition: width 600ms var(--ease-out); }
  .bk-bar.wait > i { width: 30%; animation: bk-slide 1.4s var(--ease-out) infinite; }
  @keyframes bk-slide { from { transform: translateX(-110%); } to { transform: translateX(340%); } }
  [dir=rtl] .bk-bar.wait > i { animation-name: bk-slide-rtl; }
  @keyframes bk-slide-rtl { from { transform: translateX(110%); } to { transform: translateX(-340%); } }
  .bk-res { display: flex; gap: 10px; align-items: flex-start; padding: 10px 0; border-bottom: 1px solid var(--bd); }
  .bk-res:last-child { border-bottom: 0; }
  .bk-res code { font-size: 12px; overflow-wrap: anywhere; }
  @media (prefers-reduced-motion: reduce) { .bk-bar.wait > i { animation: none; width: 100%; opacity: .45; } .bk-bar > i { transition: none; } }
</style>

<?php if ($showRun):
    $state = $run['state'];
    $title = ['queued' => $t['bkpRunQueued'], 'running' => $t['bkpRunRunning'], 'done' => $t['bkpRunDone'], 'failed' => $t['bkpRunFailed'], 'stale' => $t['bkpRunStale']][$state] ?? $t['bkpRunFailed'];
    $ico = ['queued' => 'hourglass', 'running' => 'hourglass', 'done' => 'circle-check:ok', 'failed' => 'triangle-alert:warn', 'stale' => 'circle-x:no'][$state] ?? 'triangle-alert:warn'; ?>
<div class="card fade-up" id="run" style="margin-bottom:14px" data-state="<?= htmlspecialchars($state) ?>">
  <?= $cardHead('⟦i:database-backup⟧ ' . htmlspecialchars($t['bkpRunTitle']), '⟦i:' . $ico . '⟧ <span id="run-title">' . htmlspecialchars($title) . '</span>') ?>
  <div class="card-body">
    <?php if ($live): ?>
      <div class="bk-bar<?= $state === 'queued' || $run['pct'] <= 0 ? ' wait' : '' ?>" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) $run['pct'] ?>" aria-label="<?= htmlspecialchars($t['bkpRunTitle']) ?>"><i id="run-bar" style="<?= $state === 'running' && $run['pct'] > 0 ? 'width:' . (int) $run['pct'] . '%' : '' ?>"></i></div>
      <div class="field-hint" style="margin-top:8px"><span id="run-pct"><?= $state === 'running' && $run['pct'] > 0 ? (int) $run['pct'] . '٪' : '' ?></span></div>
    <?php else: ?>
      <?php foreach ($run['results'] as $r):
          $isFiles = ($r['kind'] ?? '') === 'files';
          $what = $isFiles ? $t['bkpKindFiles'] : $t['bkpKindDb'];
          if (!empty($r['ok'])) {
              if (($r['note'] ?? '') === 'old') {
                  $kept = [];
                  if ((int) ($r['agents'] ?? 0) > 0) { $kept[] = sprintf($t['bkpKeptAgents'], (int) $r['agents']); }
                  if ((int) ($r['token'] ?? 0) > 0) { $kept[] = $t['bkpKeptToken']; }
                  $line = $t['bkpResFilesOld'] . ($kept ? ' ' . sprintf($t['bkpResKept'], implode('، ', $kept)) : '');
              } else {
                  $line = $isFiles ? $t['bkpResFiles'] : $t['bkpResDb'];
              }
          } else {
              $line = $what . ': ' . $why((string) ($r['fail'] ?? ''));
          } ?>
        <div class="bk-res">
          <span><?= !empty($r['ok']) ? '⟦i:circle-check:ok⟧' : '⟦i:circle-x:no⟧' ?></span>
          <div>
            <div><?= htmlspecialchars($line) ?></div>
            <?php if (($r['safe'] ?? '') !== ''): ?><div class="field-hint"><?= htmlspecialchars(sprintf($t['bkpResSafe'], '')) ?><code dir="ltr"><?= htmlspecialchars((string) $r['safe']) ?></code></div><?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if ($state === 'done'): ?><div class="notice notice-ok" style="margin-top:12px"><?= htmlspecialchars($t['bkpDoneHint']) ?></div><?php endif; ?>
    <?php endif; ?>
  </div>
  <?php if (!$live): ?>
  <div class="modal-foot" style="border-radius:0 0 10px 10px"><?= $formOpen('new') ?><button type="submit" class="btn btn-primary"><?= icon('upload', 13) ?> <?= htmlspecialchars($t['bkpNew']) ?></button></form></div>
  <?php endif; ?>
</div>
<?php if ($live): ?>
<script>
  // how far the watcher is, every few seconds; the page is drawn again once it has finished
  (function () {
    var bar = document.getElementById('run-bar'), pct = document.getElementById('run-pct'), title = document.getElementById('run-title');
    var texts = <?= json_encode(['queued' => $t['bkpRunQueued'], 'running' => $t['bkpRunRunning']], JSON_UNESCAPED_UNICODE) ?>;
    function tick() {
      fetch('backup.php?status=1', { cache: 'no-store', credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (s) {
        if (s.state !== 'queued' && s.state !== 'running') { location.reload(); return; }
        if (texts[s.state]) { title.textContent = texts[s.state]; }
        if (s.state === 'running' && s.pct > 0) {
          bar.parentNode.classList.remove('wait');
          bar.style.width = s.pct + '%';
          bar.parentNode.setAttribute('aria-valuenow', s.pct);
          pct.textContent = s.pct + '٪';
        }
        setTimeout(tick, 3000);
      }).catch(function () { setTimeout(tick, 6000); });
    }
    setTimeout(tick, 2500);
  })();
</script>
<?php endif; ?>
<?php endif; ?>

<?php if (!$showRun): /* a finished run's card stays until «ایمپورت جدید» */ ?>
<div class="card fade-up" id="imp" style="margin-bottom:14px">
  <?= $cardHead('⟦i:upload⟧ ' . htmlspecialchars($t['bkpImpTitle']), htmlspecialchars($t['bkpImpSub'])) ?>
  <div class="card-body">
    <?php if (!$watcher): ?><div class="notice notice-warn" style="margin-bottom:12px"><?= htmlspecialchars($t['bkpNoWatcher']) ?></div><?php endif; ?>
    <?php if ($busy): ?><div class="notice notice-warn" style="margin-bottom:12px"><?= htmlspecialchars($t['bkpBusy']) ?></div><?php endif; ?>

    <?php if ($st['items']): ?>
      <div class="set-label" style="margin-bottom:2px"><?= htmlspecialchars($t['bkpFiles']) ?></div>
      <?php foreach ($st['items'] as $it):
          $isDb = $it['kind'] === 'db';
          $locked = !empty($it['locked']);
          $old = !$isDb && !$locked && bkimp_folder_is_old($it['ver']); ?>
        <div class="bk-item">
          <div class="bk-ico<?= $locked ? ' locked' : '' ?>"><?= icon($locked ? 'lock-keyhole' : ($isDb ? 'database' : 'folder-archive'), 20) ?></div>
          <div>
            <div class="bk-name"><bdi><?= htmlspecialchars($it['name']) ?></bdi></div>
            <div class="bk-meta"><?= htmlspecialchars($isDb ? $t['bkpKindDb'] : $t['bkpKindFiles']) ?> · <span dir="ltr"><?= htmlspecialchars(bkimp_size($it['size'])) ?></span><?php if (!$isDb && !$locked): ?> · <?= htmlspecialchars(sprintf($t['bkpVersion'], $it['ver'] !== '' ? $it['ver'] : $t['bkpNoVersion'])) ?><?php endif; ?></div>
            <?php if ($locked): ?>
              <div class="bk-line"><?= htmlspecialchars($t['bkpLocked']) ?></div>
              <div class="bk-act">
                <?= $formOpen('pass') ?><input type="hidden" name="id" value="<?= $it['id'] ?>">
                  <div class="bk-act" style="margin-top:0">
                    <input type="password" class="input" name="pw" autocomplete="off" required aria-label="<?= htmlspecialchars($t['bkpPassLabel']) ?>" placeholder="<?= htmlspecialchars($t['bkpPassLabel']) ?>" dir="ltr">
                    <button type="submit" class="btn btn-primary btn-sm"><?= icon('key-round', 13) ?> <?= htmlspecialchars($t['bkpPassBtn']) ?></button>
                  </div>
                </form>
              </div>
            <?php else: ?>
              <?php if ($isDb && count($it['dumps']) > 1): ?>
                <?php if ($it['entry'] === ''): ?>
                  <div class="bk-line"><?= htmlspecialchars(sprintf($t['bkpPickLabel'], count($it['dumps']))) ?></div>
                  <?= $formOpen('pick') ?><input type="hidden" name="id" value="<?= $it['id'] ?>">
                    <div class="bk-act">
                      <select class="input" name="k" aria-label="<?= htmlspecialchars(sprintf($t['bkpPickLabel'], count($it['dumps']))) ?>" dir="ltr">
                        <?php foreach (array_slice($it['dumps'], 0, 8) as $k => $d): $src = $it['dumpsrc'][$k] ?? ''; ?>
                          <option value="<?= $k ?>"><?= htmlspecialchars($d . ($src !== '' ? ' (' . $src . ')' : '')) ?></option>
                        <?php endforeach; ?>
                      </select>
                      <button type="submit" class="btn btn-primary btn-sm"><?= icon('check', 13) ?> <?= htmlspecialchars($t['bkpPickBtn']) ?></button>
                    </div>
                  </form>
                <?php else: ?>
                  <div class="bk-line"><?= htmlspecialchars(sprintf($t['bkpEntry'], '')) ?><code dir="ltr"><?= htmlspecialchars($it['entry']) ?></code></div>
                <?php endif; ?>
              <?php endif; ?>
              <?php if ($isDb): ?>
                <?php if ($it['src'] !== '' && !(count($it['dumps']) > 1 && $it['entry'] === '')): ?><div class="bk-line dim"><?= htmlspecialchars(sprintf($t['bkpFromDb'], '')) ?><code dir="ltr"><?= htmlspecialchars($it['src']) ?></code><?= $it['src'] !== (string) $dbname ? ' — ' . htmlspecialchars($t['bkpOtherDb']) : '' ?></div><?php endif; ?>
                <div class="bk-line"><?= htmlspecialchars($t['bkpDbWhat']) ?></div>
              <?php else: ?>
                <div class="bk-line"><?= htmlspecialchars($old ? sprintf($t['bkpFilesOld'], bkimp_bot_version()) : $t['bkpFilesWhat']) ?></div>
                <?php if (!$old && $it['cfgdb'] !== '' && $it['cfgdb'] !== (string) $dbname): ?><div class="bk-line dim"><?= htmlspecialchars($t['bkpOtherBot']) ?></div><?php endif; ?>
              <?php endif; ?>
              <?php if ($it['pw'] !== ''): ?><div class="bk-line dim">⟦i:circle-check:ok⟧ <?= htmlspecialchars($t['bkpUnlocked']) ?></div><?php endif; ?>
            <?php endif; ?>
            <div class="bk-act">
              <?= $formOpen('rm') ?><input type="hidden" name="id" value="<?= $it['id'] ?>"><button type="submit" class="btn btn-sm"><?= icon('trash-2', 13) ?> <?= htmlspecialchars($t['bkpRemove']) ?></button></form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if (count($kinds) < 2): ?>
      <?= $formOpen('up', ' enctype="multipart/form-data"') ?>
        <label class="bk-drop" id="bk-drop" style="margin-top:<?= $st['items'] ? '14px' : '0' ?>">
          <?= icon('upload', 22) ?>
          <span class="bk-pick"><?= htmlspecialchars($st['items'] ? (in_array('db', $kinds, true) ? $t['bkpMoreFiles'] : $t['bkpMoreDb']) : $t['bkpUploadLabel']) ?></span>
          <span class="field-hint"><?= htmlspecialchars(sprintf($t['bkpUploadHint'], $limitText)) ?></span>
          <bdi class="bk-chosen" id="bk-chosen"></bdi>
          <input type="file" name="files[]" id="bk-files" accept=".zip,.sql,application/zip,application/sql" multiple <?= (!$watcher || $busy) ? 'disabled' : '' ?>>
        </label>
        <div class="bk-act" style="justify-content:flex-end"><button type="submit" class="btn btn-primary" id="bk-up" <?= (!$watcher || $busy) ? 'disabled' : '' ?>><?= icon('upload', 13) ?> <?= htmlspecialchars($t['bkpUploadBtn']) ?></button></div>
      </form>
    <?php endif; ?>
  </div>
  <?php if ($st['items']): ?>
    <?= $formOpen('go', $confirmJs($t['bkpGoConfirm'])) ?>
    <div class="card-body" style="padding-top:0">
      <?php if ($ready): ?>
        <div class="field-hint"><?= htmlspecialchars($t['bkpSafeNote']) ?></div>
        <div class="set-row"><div><div class="set-label">⟦i:triangle-alert:warn⟧ <?= htmlspecialchars($t['bkpSure']) ?></div></div>
          <div class="set-ctl"><label class="lang-chip"><input type="hidden" name="sure" value="0"><input type="checkbox" name="sure" value="1"> <?= htmlspecialchars($t['clnSure']) ?></label></div></div>
      <?php else: ?>
        <div class="field-hint"><?= htmlspecialchars($t['bkpNotReady']) ?></div>
      <?php endif; ?>
    </div>
    <div class="modal-foot" style="border-radius:0 0 10px 10px;display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap">
      <button type="submit" class="btn" name="card" value="cancel" formnovalidate onclick="this.form.onsubmit=null"><?= icon('x', 13) ?> <?= htmlspecialchars($t['bkpCancel']) ?></button>
      <button type="submit" class="btn btn-no"<?= (!$ready || $busy || !$watcher) ? ' disabled' : '' ?>>⟦i:database-backup⟧ <?= htmlspecialchars($t['bkpGoBtn']) ?></button>
    </div>
    </form>
  <?php endif; ?>
</div>
<script>
  // the chosen files' names under the drop area; a file dropped on it is chosen too
  (function () {
    var drop = document.getElementById('bk-drop'), input = document.getElementById('bk-files'), chosen = document.getElementById('bk-chosen');
    if (!drop || !input) { return; }
    function show() { chosen.textContent = Array.prototype.map.call(input.files || [], function (f) { return f.name; }).join(' · '); }
    input.addEventListener('change', show);
    ['dragenter', 'dragover'].forEach(function (e) { drop.addEventListener(e, function (ev) { ev.preventDefault(); if (!input.disabled) { drop.classList.add('over'); } }); });
    ['dragleave', 'drop'].forEach(function (e) { drop.addEventListener(e, function (ev) { ev.preventDefault(); drop.classList.remove('over'); }); });
    drop.addEventListener('drop', function (ev) { if (!input.disabled && ev.dataTransfer && ev.dataTransfer.files.length) { input.files = ev.dataTransfer.files; show(); } });
  })();
</script>
<?php endif; ?>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
