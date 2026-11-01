<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/langsync.php';
require_auth();

// 🧩 حجم دلخواه, one panel at a time - the very settings of the bot's
// ✏️ مدیریت پنل ← 💰 قیمت‌گذاری ← 🧩 تنظیمات حجم دلخواه (cvol.php), saved
// through the same cvol_save(), which also writes the old columns the
// resellers' bots read. Checked by the bot's rules: it does not go on without
// a price, and a GB and a day are never both free.
$t = $textbotlang['panel'];
$lang = web_lang_pick();
$panels = array_values(array_filter(
    db_fetchAll($pdo, "SELECT * FROM marzban_panel ORDER BY id"),
    fn($p) => ($p['type'] ?? '') !== 'Manualsale'
));
$code = (string) ($_GET['panel'] ?? '');
$panel = null;
foreach ($panels as $p) {
    if ((string) $p['code_panel'] === $code) {
        $panel = $p;
    }
}
if ($panel === null && $panels) {
    $panel = $panels[0];
}
$code = $panel !== null ? (string) $panel['code_panel'] : '';
$self = fn($card = '', $l = null) => 'customvol.php?' . http_build_query(['panel' => $code, 'lang' => $l ?? $lang]) . ($card !== '' ? '#' . $card : '');
$lvName = ['f' => $t['cvLvlF'], 'n' => $t['cvLvlN'], 'n2' => $t['cvLvlN2']];
$lvIcon = ['f' => 'user', 'n' => 'briefcase', 'n2' => 'star'];
// what a person types: Persian / Arabic digits, thousands separators
$digits = fn($s) => strtr(trim((string) $s), ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٫' => '.']);
$number = fn($s) => str_replace([',', '٬', ' '], '', $digits($s));
$numList = function ($s) use ($digits) {
    $s = trim(preg_replace('/[\s,،٬]+/u', ' ', $digits($s)));
    if ($s === '') {
        return [];
    }
    return preg_match('/^\d+( \d+)*$/', $s) ? array_map('intval', explode(' ', $s)) : null;
};
$roundOpts = function ($cur) {
    return ((int) (currency_get($cur)['decimals'] ?? 0)) > 0 ? [0, 0.1, 0.5, 1, 5] : [0, 100, 500, 1000, 5000, 10000];
};
$plainNum = fn($v) => rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $card = (string) ($_POST['card'] ?? '');
    $err = null;
    if ($panel === null || (string) ($_POST['panel'] ?? '') !== $code) {
        $err = $t['cvErrPanel'];
    } else {
        $c = cvol_cfg($panel);
        if ($card === 'status') {
            $wasOn = $c['on'];
            $wantOn = ($_POST['on'] ?? '0') === '1';
            $picked = (array) ($_POST['levels'] ?? []);
            foreach (cvol_levels() as $lv) {
                $c['levels'][$lv] = in_array($lv, $picked, true);
            }
            if (in_array($_POST['mode'] ?? '', ['both', 'vol', 'days'], true)) {
                $c['mode'] = $_POST['mode'];
            }
            $c['nudge'] = ($_POST['nudge'] ?? '0') === '1';
            if ($wantOn && !$wasOn) {
                $priced = false;
                foreach (panel_langs() as $l) {
                    if ($c['langs'][$l]['show'] && cvol_lang_priced($c, $l)) {
                        $priced = true;
                    }
                }
                if (!$priced) {
                    $err = $t['cvErrCantOn'];
                } elseif (!array_filter($c['levels'])) {
                    // turned on with nobody ticked: the bot's switch does the same
                    $c['levels']['f'] = true;
                }
            }
            $c['on'] = $wantOn;
        } elseif ($card === 'vol' || $card === 'days') {
            $sec = $card;
            $fields = $card === 'vol'
                ? ['min' => $t['cvVolMin'], 'max' => $t['cvVolMax'], 'step' => $t['cvVolStep'], 'start' => $t['cvVolStart'], 'fixed' => $t['cvVolFixed']]
                : ['min' => $t['cvDaysMin'], 'max' => $t['cvDaysMax'], 'step' => $t['cvDaysStep'], 'start' => $t['cvDaysStart'], 'fixed' => $t['cvDaysFixed']];
            foreach ($fields as $k => $label) {
                $v = $number($_POST[$k] ?? '');
                if (!ctype_digit($v) || (int) $v < 1) {
                    $err = sprintf($t['cvErrBad'], $label);
                    break;
                }
                $c[$sec][$k] = (int) $v;
            }
            if ($err === null && $c[$sec]['max'] < $c[$sec]['min']) {
                $err = sprintf($t['cvErrMaxMin'], $fields['max']);
            }
            if ($err === null) {
                $list = $numList($_POST['list'] ?? '');
                if ($list === null || in_array(0, (array) $list, true)) {
                    $err = sprintf($t['cvErrBad'], $card === 'vol' ? $t['cvVolChips'] : $t['cvDaysList']);
                } elseif ($card === 'vol') {
                    $c['vol']['chips'] = $list;
                } else {
                    $c['days']['pick'] = ($_POST['pick'] ?? '') === 'list' ? 'list' : 'step';
                    if ($c['days']['pick'] === 'list' && !$list) {
                        $err = sprintf($t['cvErrBad'], $t['cvDaysList']);
                    } else {
                        $c['days']['list'] = $list;
                    }
                }
            }
        } elseif ($card === 'prices') {
            $li = $c['langs'][$lang];
            $li['show'] = ($_POST['show'] ?? '0') === '1';
            foreach (['gb' => $t['cvPerGb'], 'day' => $t['cvPerDay']] as $dim => $dimLabel) {
                foreach (cvol_levels() as $lv) {
                    $v = $number($_POST["{$dim}_{$lv}"] ?? '');
                    if ($v !== '' && !preg_match('/^\d+(\.\d{1,4})?$/', $v)) {
                        $err = sprintf($t['cvErrBad'], strip_tags($lvName[$lv]) . ' / ' . $dimLabel);
                        break 2;
                    }
                    $li[$dim][$lv] = $v;
                }
            }
            if ($err === null) {
                $c['langs'][$lang] = $li;
                foreach (cvol_levels() as $lv) {
                    $rg = cvol_rate($c, $lang, 'gb', $lv);
                    $rd = cvol_rate($c, $lang, 'day', $lv);
                    if ($rg !== null && $rd !== null && $rg <= 0 && $rd <= 0) {
                        $err = sprintf($t['cvErrFree'], strip_tags($lvName[$lv]));
                        break;
                    }
                }
            }
            if ($err === null) {
                $mb = $number($_POST['minbal'] ?? '');
                $rd = $number($_POST['round'] ?? '0');
                if ($mb !== '' && !preg_match('/^\d+(\.\d{1,4})?$/', $mb)) {
                    $err = sprintf($t['cvErrBad'], sprintf($t['cvMinbal'], currency_get(currency_for_lang($lang))['title'] ?? ''));
                } elseif (!preg_match('/^\d+(\.\d{1,4})?$/', $rd)) {
                    $err = sprintf($t['cvErrBad'], $t['cvRound']);
                } else {
                    $c['langs'][$lang]['minbal'] = (float) ($mb === '' ? 0 : $mb);
                    $c['langs'][$lang]['round'] = (float) $rd;
                }
            }
        } elseif ($card === 'tiers') {
            $tiers = [];
            foreach ((array) ($_POST['tier'] ?? []) as $i => $row) {
                $from = $number($row['from'] ?? '');
                $pct = $number($row['pct'] ?? '');
                if ($from === '' && $pct === '') {
                    continue;
                }
                if (!ctype_digit($from) || !ctype_digit($pct) || (int) $from < 1 || (int) $pct < 1 || (int) $pct > 90) {
                    $err = sprintf($t['cvErrBad'], sprintf($t['cvTierRow'], (int) $i + 1));
                    break;
                }
                $tiers[] = [(int) $from, (int) $pct];
            }
            $c['tiers'] = $tiers;
        } elseif ($card === 'sale') {
            $pct = $number($_POST['pct'] ?? '');
            $days = $number($_POST['days'] ?? '');
            $days = $days === '' ? '0' : $days;
            if (!ctype_digit($pct) || (int) $pct > 90) {
                $err = sprintf($t['cvErrBad'], $t['cvSalePct']);
            } elseif (!ctype_digit($days)) {
                $err = sprintf($t['cvErrBad'], $t['cvSaleDays']);
            } else {
                $c['sale'] = (int) $pct === 0 ? ['pct' => 0, 'until' => 0] : ['pct' => (int) $pct, 'until' => (int) $days > 0 ? time() + (int) $days * 86400 : 0];
            }
        } else {
            $err = $t['cvErrPanel'];
        }
        if ($err === null) {
            cvol_save($panel, $c);
        }
    }
    flash($err === null ? 'success' : 'error', $err === null ? $t['cvSaved'] : strip_tags($err));
    header('Location: ' . $self($card));
    exit;
}

$c = $panel !== null ? cvol_cfg($panel) : cvol_normalize([]);
$cur = currency_for_lang($lang);
$curRow = currency_get($cur);
$curTitle = $curRow['title'] ?? $cur;
$li = $c['langs'][$lang];

// what keeps it from being seen - the bot's hub says the same
$warn = [];
if ($panel !== null) {
    if (($panel['status'] ?? '') !== 'active') {
        $warn[] = $t['cvWarnPanelOff'];
    }
    if ($c['on'] && !array_filter($c['levels'])) {
        $warn[] = $t['cvWarnNoLevel'];
    }
    foreach (panel_langs() as $l) {
        if ($c['langs'][$l]['show'] && !cvol_lang_priced($c, $l)) {
            $warn[] = sprintf($t['cvWarnNoPrice'], web_langs()[$l] ?? $l);
        }
    }
    foreach (cvol_cheaper_warnings($panel, 5) as [$l, $p, $cp]) {
        $lc = currency_for_lang($l);
        $warn[] = sprintf($t['cvWarnCheaper'], (string) $p['name_product'], cvol_num($p['Volume_constraint']), (int) $p['Service_time'], money($p['price_product'], $lc), money($cp, $lc));
    }
}
$stats = $panel !== null ? cvol_stats($panel) : ['n' => 0, 'n30' => 0, 'sum' => [], 'top' => []];
$sale = $c['sale'];
$saleActive = $sale['pct'] > 0 && ($sale['until'] == 0 || $sale['until'] > time());
$saleNow = $sale['pct'] <= 0 ? $t['cvSaleNone'] : (!$saleActive ? $t['cvSaleEnded']
    : ($sale['until'] > 0 ? sprintf($t['cvSaleOn'], $sale['pct'], cvol_date($sale['until'], 'fa')) : sprintf($t['cvSaleForever'], $sale['pct'])));
$modeName = ['both' => $t['cvModeBoth'], 'vol' => $t['cvModeVol'], 'days' => $t['cvModeDays']];
[$calcGb, $calcDays] = cvol_fit($c, $c['vol']['start'], $c['days']['start']);
// the calculator's rules: the saved ranges, discounts and groups; the prices
// and the rounding are read from the form as they are typed
$calcCfg = [
    'mode' => $c['mode'],
    'vol' => ['min' => $c['vol']['min'], 'max' => $c['vol']['max'], 'fixed' => $c['vol']['fixed']],
    'days' => ['pick' => $c['days']['pick'], 'list' => $c['days']['list'], 'min' => $c['days']['min'], 'max' => $c['days']['max'], 'fixed' => $c['days']['fixed']],
    'tiers' => $c['tiers'],
    'sale' => $saleActive ? (int) $sale['pct'] : 0,
    'levels' => $c['levels'],
    'show' => (bool) $li['show'],
    'dec' => (int) ($curRow['decimals'] ?? 0),
    'sym' => (string) ($curRow['symbol'] ?? ''),
    'before' => ($curRow['symbol_position'] ?? '') === 'before',
];

$field = function ($label, $ctl, $hint = '', $attr = '') {
    return '<div class="set-row"' . $attr . '><div><div class="set-label">' . htmlspecialchars(ui_plain($label)) . '</div>' . ($hint !== '' ? '<div class="set-hint">' . htmlspecialchars(ui_plain($hint)) . '</div>' : '') . '</div><div class="set-ctl">' . $ctl . '</div></div>';
};
$switch = function ($name, $on, $label, $hint = '') use ($t, $field) {
    return $field($label, '<label class="lang-chip" style="justify-content:center"><input type="hidden" name="' . $name . '" value="0"><input type="checkbox" name="' . $name . '" value="1"' . ($on ? ' checked' : '') . '> ' . htmlspecialchars($t['refOn']) . '</label>', $hint);
};
$input = fn($name, $value, $extra = '') => '<input type="text" class="input" name="' . $name . '" value="' . htmlspecialchars((string) $value) . '" ' . $extra . '>';
$num = fn($name, $value) => $input($name, $value, 'inputmode="numeric" dir="ltr" autocomplete="off"');
$dec = fn($name, $value, $ph = '') => $input($name, $value, 'inputmode="decimal" dir="ltr" autocomplete="off"' . ($ph !== '' ? ' placeholder="' . htmlspecialchars($ph) . '"' : ''));
$select = function ($name, $options, $value) {
    $h = '<select class="select" name="' . $name . '">';
    foreach ($options as $v => $label) {
        $h .= '<option value="' . htmlspecialchars((string) $v) . '"' . ((string) $v === (string) $value ? ' selected' : '') . '>' . htmlspecialchars(ui_plain($label)) . '</option>';
    }
    return $h . '</select>';
};
$cardHead = fn($title, $sub) => '<div class="card-head"><div><div class="card-title">' . $title . '</div><div class="card-subtitle">' . $sub . '</div></div></div>';
$formOpen = fn($card) => '<form method="POST" action="' . htmlspecialchars($self()) . '"><input type="hidden" name="_csrf" value="' . csrf_token() . '"><input type="hidden" name="card" value="' . $card . '"><input type="hidden" name="panel" value="' . htmlspecialchars($code) . '">';
$saveBtn = '<div class="modal-foot" style="border-radius:0 0 10px 10px"><button type="submit" class="btn btn-primary">' . icon('check', 13) . ' ' . $t['bottextSaveBtn'] . '</button></div>';
$hidden = fn($name, $value) => '<input type="hidden" name="' . $name . '" value="' . htmlspecialchars((string) $value) . '">';

$pageTitle = $t['cvPageTitle'];
$pageLede = $t['cvPageLede'];
$activeNav = 'customvol';
include __DIR__ . '/inc/layout_head.php';
echo web_lang_assets();
?>
<style>
.cv-state{font-size:.72rem;font-weight:600;padding:2px 7px;border-radius:999px;background:var(--sf3);color:var(--mute);margin-inline-start:6px}
.cv-state.on{background:color-mix(in srgb,var(--ok) 16%,transparent);color:var(--ok)}
.cv-warn{margin:12px 16px 0;padding:10px 12px;border-radius:10px;border:1px solid color-mix(in srgb,var(--warn) 45%,var(--bd));background:color-mix(in srgb,var(--warn) 9%,transparent);font-size:.8rem;line-height:1.9}
.cv-warn b{display:block;margin-bottom:2px}
.cv-levels{display:flex;flex-wrap:wrap;gap:8px}
.cv-price{display:grid;grid-template-columns:minmax(120px,1fr) minmax(0,1fr) minmax(0,1fr);gap:8px 10px;align-items:center;padding:12px 0;border-top:1px solid var(--bd)}
.cv-price .h{font-size:.74rem;color:var(--mute);font-weight:600}
.cv-price .lv{font-weight:600;font-size:.86rem;display:flex;align-items:center;gap:6px}
.cv-price .lv small{display:block;font-weight:400;color:var(--mute);font-size:.72rem}
.cv-calc-in{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:12px}
.cv-calc-in label{display:flex;flex-direction:column;gap:5px;font-size:.78rem;color:var(--mute);min-width:140px;flex:1}
.cv-calc-out{display:grid;gap:6px}
.cv-calc-row{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:9px 12px;border-radius:10px;background:var(--sf2);border:1px solid var(--bd);font-size:.86rem}
.cv-calc-row .v{font-weight:700;font-variant-numeric:tabular-nums;direction:ltr}
.cv-calc-row .v.mute{font-weight:500;color:var(--mute);direction:rtl}
.cv-tier{display:grid;grid-template-columns:auto minmax(0,1fr) minmax(0,1fr);gap:8px 10px;align-items:center;padding:8px 0}
.cv-tier .n{font-size:.78rem;color:var(--mute);min-width:52px}
.cv-top{display:flex;justify-content:space-between;gap:10px;padding:8px 0;border-top:1px solid var(--bd);font-size:.84rem}
.cv-top:first-child{border-top:0}
.cv-cfg{display:none}
.set-row[hidden]{display:none}
@media (max-width:640px){.cv-price{grid-template-columns:minmax(0,1fr) minmax(0,1fr)}.cv-price .lv,.cv-price .h0{grid-column:1/-1}}
</style>

<?php if (!$panels): ?>
<div class="card fade-up"><div class="card-body"><div class="empty"><?= htmlspecialchars($t['cvNoPanels']) ?> <a href="servers.php"><?= htmlspecialchars($t['srvPageTitle']) ?></a></div></div></div>
<?php include __DIR__ . '/inc/layout_foot.php'; return; endif; ?>

<?php if (count($panels) > 1): ?>
<div class="lang-tabs fade-up" role="tablist">
  <?php foreach ($panels as $p): $pc = (string) $p['code_panel']; $pOn = cvol_cfg($p)['on']; ?>
    <a href="<?= htmlspecialchars('customvol.php?' . http_build_query(['panel' => $pc, 'lang' => $lang])) ?>" class="lang-tab<?= $pc === $code ? ' on' : '' ?>"<?= $pc === $code ? ' aria-current="page"' : '' ?>><?= icon('server', 14) ?> <?= htmlspecialchars((string) $p['name_panel']) ?><span class="cv-state<?= $pOn ? ' on' : '' ?>"><?= htmlspecialchars($pOn ? $t['cvStateOn'] : $t['cvStateOff']) ?></span></a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card fade-up" id="status" style="margin-bottom:14px">
  <?= $cardHead('⟦i:puzzle⟧ ' . $t['cvStatusTitle'] . '<span class="cv-state' . ($c['on'] ? ' on' : '') . '">' . htmlspecialchars($c['on'] ? $t['cvStateOn'] : $t['cvStateOff']) . '</span>', htmlspecialchars(sprintf($t['cvStatusSub'], (string) $panel['name_panel']))) ?>
  <?php if ($warn): ?>
    <div class="cv-warn"><b>⟦i:triangle-alert:warn⟧ <?= htmlspecialchars($t['cvWarnTitle']) ?></b><?= implode('<br>', array_map('htmlspecialchars', $warn)) ?></div>
  <?php endif; ?>
  <?= $formOpen('status') ?>
  <div class="card-body">
    <?= $switch('on', $c['on'], $t['cvOn'], $t['cvOnHint']) ?>
    <?php ob_start(); ?><div class="cv-levels"><?php foreach (cvol_levels() as $lv): ?><label class="lang-chip"><input type="checkbox" name="levels[]" value="<?= $lv ?>"<?= $c['levels'][$lv] ? ' checked' : '' ?>> <?= icon($lvIcon[$lv], 14) ?> <?= htmlspecialchars($lvName[$lv]) ?></label><?php endforeach; ?></div><?php $levelsCtl = ob_get_clean(); ?>
    <?= $field($t['cvLevels'], $levelsCtl, $t['cvLevelsHint']) ?>
    <?= $field($t['cvMode'], $select('mode', $modeName, $c['mode'])) ?>
    <?= $switch('nudge', $c['nudge'], $t['cvNudge'], $t['cvNudgeHint']) ?>
  </div>
  <?= $saveBtn ?></form>
</div>

<?= web_lang_tabs($lang, fn($l) => $self('prices', $l)) ?>

<div class="card fade-up<?= $li['show'] ? '' : ' set-off' ?>" id="prices" style="margin-bottom:14px">
  <?= $cardHead('⟦i:banknote⟧ ' . htmlspecialchars(sprintf($t['cvPricesTitle'], web_langs()[$lang] ?? $lang)), htmlspecialchars(sprintf($t['cvPricesSub'], $curTitle))) ?>
  <?= $formOpen('prices') ?>
  <div class="card-body">
    <?= $switch('show', $li['show'], $t['cvShow']) ?>
    <div class="cv-price" aria-hidden="true"><span class="h h0"></span><span class="h"><?= htmlspecialchars($t['cvPerGb']) ?></span><span class="h"><?= htmlspecialchars($t['cvPerDay']) ?></span></div>
    <?php foreach (cvol_levels() as $lv): $ph = $lv === 'f' ? $t['cvUnsetHint'] : $t['cvSameHint']; ?>
      <div class="cv-price">
        <div class="lv"><?= icon($lvIcon[$lv], 15) ?><div><?= htmlspecialchars($lvName[$lv]) ?><small><?= htmlspecialchars($ph) ?></small></div></div>
        <?= $dec("gb_{$lv}", $li['gb'][$lv], $lv === 'f' ? '' : (string) $li['gb']['f']) ?>
        <?= $dec("day_{$lv}", $li['day'][$lv], $lv === 'f' ? '' : (string) $li['day']['f']) ?>
      </div>
    <?php endforeach; ?>
    <?= $field(sprintf($t['cvMinbal'], $curTitle), $dec('minbal', $li['minbal'] > 0 ? $plainNum($li['minbal']) : '0'), $t['cvMinbalHint']) ?>
    <?php $ro = []; foreach ($roundOpts($cur) as $o) { $ro[$plainNum($o)] = $o == 0 ? $t['cvRoundOff'] : money($o, $cur); } if (!isset($ro[$plainNum($li['round'])])) { $ro[$plainNum($li['round'])] = money($li['round'], $cur); } ?>
    <?= $field($t['cvRound'], $select('round', $ro, $plainNum($li['round'])), $t['cvRoundHint']) ?>
  </div>
  <?= $saveBtn ?></form>
</div>

<div class="card fade-up" id="calc" style="margin-bottom:14px">
  <?= $cardHead('⟦i:scale⟧ ' . $t['cvCalcTitle'], htmlspecialchars($t['cvCalcSub'])) ?>
  <div class="card-body">
    <span class="cv-cfg"><?= htmlspecialchars(json_encode($calcCfg, JSON_UNESCAPED_UNICODE)) ?></span>
    <div class="cv-calc-in">
      <label><?= htmlspecialchars($t['cvCalcGb']) ?><input type="number" class="input" id="cv-gb" min="1" step="1" value="<?= (int) $calcGb ?>" dir="ltr"<?= $c['mode'] === 'days' ? ' disabled' : '' ?>></label>
      <label><?= htmlspecialchars($t['cvCalcDays']) ?>
        <?php if ($c['mode'] !== 'vol' && $c['days']['pick'] === 'list'): ?>
          <select class="select" id="cv-days"><?php foreach ($c['days']['list'] as $d): ?><option value="<?= (int) $d ?>"<?= (int) $d === (int) $calcDays ? ' selected' : '' ?>><?= (int) $d ?></option><?php endforeach; ?></select>
        <?php else: ?>
          <input type="number" class="input" id="cv-days" min="1" step="1" value="<?= (int) $calcDays ?>" dir="ltr"<?= $c['mode'] === 'vol' ? ' disabled' : '' ?>>
        <?php endif; ?>
      </label>
    </div>
    <div class="cv-calc-out" data-ajax-ignore aria-live="polite">
      <?php foreach (cvol_levels() as $lv): ?>
        <div class="cv-calc-row" data-lv="<?= $lv ?>"><span><?= icon($lvIcon[$lv], 14) ?> <?= htmlspecialchars($lvName[$lv]) ?></span><span class="v">…</span></div>
      <?php endforeach; ?>
    </div>
    <div class="field-hint" style="margin-top:10px"><?= htmlspecialchars($t['cvCalcNote']) ?></div>
  </div>
</div>

<div class="card fade-up" id="vol" style="margin-bottom:14px">
  <?= $cardHead('⟦i:hard-drive⟧ ' . $t['cvVolTitle'], htmlspecialchars($t['cvVolSub'] . ' · ' . $modeName[$c['mode']])) ?>
  <?= $formOpen('vol') ?>
  <div class="card-body">
    <?php if ($c['mode'] === 'days'): ?>
      <?= $field($t['cvVolFixed'], $num('fixed', $c['vol']['fixed']), $t['cvVolFixedHint']) ?>
      <?= $hidden('min', $c['vol']['min']) . $hidden('max', $c['vol']['max']) . $hidden('step', $c['vol']['step']) . $hidden('start', $c['vol']['start']) . $hidden('list', implode(' ', $c['vol']['chips'])) ?>
    <?php else: ?>
      <?= $field($t['cvVolMin'], $num('min', $c['vol']['min'])) ?>
      <?= $field($t['cvVolMax'], $num('max', $c['vol']['max'])) ?>
      <?= $field($t['cvVolStep'], $num('step', $c['vol']['step'])) ?>
      <?= $field($t['cvVolStart'], $num('start', $c['vol']['start']), $t['cvVolStartHint']) ?>
      <?= $field($t['cvVolChips'], $input('list', implode(' ', $c['vol']['chips']), 'dir="ltr" autocomplete="off" placeholder="10 20 50 100"'), $t['cvVolChipsHint']) ?>
      <?= $hidden('fixed', $c['vol']['fixed']) ?>
    <?php endif; ?>
  </div>
  <?= $saveBtn ?></form>
</div>

<div class="card fade-up" id="days" style="margin-bottom:14px">
  <?= $cardHead('⟦i:calendar-days⟧ ' . $t['cvDaysTitle'], htmlspecialchars($t['cvDaysSub'] . ' · ' . $modeName[$c['mode']])) ?>
  <?= $formOpen('days') ?>
  <div class="card-body">
    <?php $d = $c['days']; if ($c['mode'] === 'vol'): ?>
      <?= $field($t['cvDaysFixed'], $num('fixed', $d['fixed']), $t['cvDaysFixedHint']) ?>
      <?= $hidden('pick', $d['pick']) . $hidden('list', implode(' ', $d['list'])) . $hidden('min', $d['min']) . $hidden('max', $d['max']) . $hidden('step', $d['step']) . $hidden('start', $d['start']) ?>
    <?php else: ?>
      <?= $field($t['cvDaysPick'], $select('pick', ['list' => $t['cvPickList'], 'step' => $t['cvPickStep']], $d['pick'])) ?>
      <?= $field($t['cvDaysList'], $input('list', implode(' ', $d['list']), 'dir="ltr" autocomplete="off" placeholder="30 60 90"'), $t['cvDaysListHint'], ' data-pick="list"' . ($d['pick'] === 'list' ? '' : ' hidden')) ?>
      <?= $field($t['cvDaysMin'], $num('min', $d['min']), '', ' data-pick="step"' . ($d['pick'] === 'step' ? '' : ' hidden')) ?>
      <?= $field($t['cvDaysMax'], $num('max', $d['max']), '', ' data-pick="step"' . ($d['pick'] === 'step' ? '' : ' hidden')) ?>
      <?= $field($t['cvDaysStep'], $num('step', $d['step']), '', ' data-pick="step"' . ($d['pick'] === 'step' ? '' : ' hidden')) ?>
      <?= $field($t['cvDaysStart'], $num('start', $d['start']), '', ' data-pick="step"' . ($d['pick'] === 'step' ? '' : ' hidden')) ?>
      <?= $hidden('fixed', $d['fixed']) ?>
    <?php endif; ?>
  </div>
  <?= $saveBtn ?></form>
</div>

<div class="card fade-up" id="tiers" style="margin-bottom:14px">
  <?= $cardHead('⟦i:badge-percent⟧ ' . $t['cvTiersTitle'], htmlspecialchars($t['cvTiersSub'])) ?>
  <?= $formOpen('tiers') ?>
  <div class="card-body">
    <div class="cv-tier" aria-hidden="true"><span class="n"></span><span class="h set-hint"><?= htmlspecialchars($t['cvTierFrom']) ?></span><span class="h set-hint"><?= htmlspecialchars($t['cvTierPct']) ?></span></div>
    <?php for ($i = 0; $i < 5; $i++): $tr = $c['tiers'][$i] ?? null; ?>
      <div class="cv-tier">
        <span class="n"><?= htmlspecialchars(sprintf($t['cvTierRow'], $i + 1)) ?></span>
        <?= $input("tier[{$i}][from]", $tr ? $tr[0] : '', 'inputmode="numeric" dir="ltr" autocomplete="off" aria-label="' . htmlspecialchars(sprintf($t['cvTierRow'], $i + 1) . ' — ' . $t['cvTierFrom']) . '"') ?>
        <?= $input("tier[{$i}][pct]", $tr ? $tr[1] : '', 'inputmode="numeric" dir="ltr" autocomplete="off" aria-label="' . htmlspecialchars(sprintf($t['cvTierRow'], $i + 1) . ' — ' . $t['cvTierPct']) . '"') ?>
      </div>
    <?php endfor; ?>
  </div>
  <?= $saveBtn ?></form>
</div>

<div class="card fade-up" id="sale" style="margin-bottom:14px">
  <?= $cardHead('⟦i:sparkles⟧ ' . $t['cvSaleTitle'], htmlspecialchars(sprintf($t['cvSaleSub'], $saleNow))) ?>
  <?= $formOpen('sale') ?>
  <div class="card-body">
    <?= $field($t['cvSalePct'], $num('pct', $saleActive ? $sale['pct'] : 0), $t['cvSalePctHint']) ?>
    <?= $field($t['cvSaleDays'], $num('days', $saleActive && $sale['until'] > 0 ? (int) ceil(($sale['until'] - time()) / 86400) : 0), $t['cvSaleDaysHint']) ?>
  </div>
  <?= $saveBtn ?></form>
</div>

<div class="card fade-up" id="stats" style="margin-bottom:14px">
  <?= $cardHead('⟦i:chart-line⟧ ' . $t['cvStatsTitle'], htmlspecialchars($t['cvStatsSub'])) ?>
  <div class="card-body">
    <div class="stats" style="margin-bottom:12px">
      <div class="stat"><div class="stat-label">⟦i:shopping-cart⟧ <?= htmlspecialchars($t['cvStatsCount']) ?></div><div class="stat-num"><?= number_format($stats['n']) ?></div></div>
      <div class="stat"><div class="stat-label">⟦i:calendar-clock⟧ <?= htmlspecialchars($t['cvStats30']) ?></div><div class="stat-num"><?= number_format($stats['n30']) ?></div></div>
      <div class="stat ok"><div class="stat-label">⟦i:coins⟧ <?= htmlspecialchars($t['cvStatsSum']) ?></div><div class="stat-num"<?= count($stats['sum']) > 1 ? ' style="font-size:1.15rem;line-height:1.6"' : '' ?>><?= $stats['sum'] ? web_money_lines($stats['sum']) : '0' ?></div></div>
    </div>
    <div class="set-label" style="margin-bottom:4px"><?= htmlspecialchars($t['cvStatsTop']) ?></div>
    <?php if (!$stats['top']): ?><div class="set-hint"><?= htmlspecialchars($t['cvStatsEmpty']) ?></div><?php endif; ?>
    <?php foreach ($stats['top'] as $r): ?>
      <div class="cv-top"><span><?= htmlspecialchars(sprintf($t['cvStatsTopRow'], cvol_num($r['v']), (int) $r['d'])) ?></span><span class="set-hint" style="margin:0"><?= htmlspecialchars(sprintf($t['cvStatsTimes'], (int) $r['c'])) ?></span></div>
    <?php endforeach; ?>
  </div>
</div>

<div class="card fade-up" style="margin-bottom:14px"><div class="card-body">
  <div class="set-row"><div class="set-hint">⟦i:palette⟧ <?= htmlspecialchars($t['cvLooksHint']) ?></div>
    <div class="set-ctl" style="display:flex;gap:6px;flex-wrap:wrap">
      <a class="btn btn-ghost btn-sm" href="bottext.php?<?= http_build_query(['lang' => $lang, 'group' => 'buyflow', 'q' => 'users.cvol']) ?>"><?= htmlspecialchars($t['cvLooksTexts']) ?></a>
      <a class="btn btn-ghost btn-sm" href="buttons.php?<?= http_build_query(['lang' => $lang]) ?>#g-cv"><?= htmlspecialchars($t['cvLooksBtns']) ?></a>
      <a class="btn btn-ghost btn-sm" href="buttons.php?<?= http_build_query(['lang' => $lang]) ?>#g-ce"><?= htmlspecialchars($t['cvLooksEntry']) ?></a>
    </div>
  </div>
  <div class="set-row"><div class="set-hint">⟦i:bot⟧ <?= htmlspecialchars($t['cvBotPath']) ?></div></div>
</div></div>

<script>
(function () {
  'use strict';
  // the days options or the −/+ fields, as the pick changes - before saving
  document.addEventListener('change', function (e) {
    var sel = e.target;
    if (!sel.matches || !sel.matches('#days select[name=pick]')) return;
    sel.form.querySelectorAll('[data-pick]').forEach(function (row) { row.hidden = row.getAttribute('data-pick') !== sel.value; });
  });

  // ⟦calculator⟧ the same sum as the bot's (cvol_quote): volume × GB price
  // less the volume discount, plus days × day price, less the sale, rounded
  var L = <?= json_encode(['hidden' => $t['cvCalcHidden'], 'noPrice' => $t['cvCalcNoPrice'], 'bad' => $t['cvCalcBad']], JSON_UNESCAPED_UNICODE) ?>;
  function cfg() {
    var el = document.querySelector('#calc .cv-cfg');
    try { return JSON.parse(el ? el.textContent : 'null'); } catch (e) { return null; }
  }
  function num(s) {
    s = String(s == null ? '' : s).trim()
      .replace(/[۰-۹]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); })
      .replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); })
      .replace(/٫/g, '.').replace(/[,٬\s]/g, '');
    if (s === '') return null;
    return /^\d+(\.\d+)?$/.test(s) ? parseFloat(s) : NaN;
  }
  function field(name) {
    var f = document.querySelector('#prices [name="' + name + '"]');
    return f ? f.value : '';
  }
  function rate(dim, lv) {
    var v = num(field(dim + '_' + lv));
    if (v === null && lv !== 'f') v = num(field(dim + '_f'));
    return v;
  }
  function money(x, c) {
    var dec = c.dec, s = x.toFixed(dec);
    var parts = s.split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    s = parts.join('.');
    if (dec > 0 && s.indexOf('.') !== -1) s = s.replace(/0+$/, '').replace(/\.$/, '');
    return c.sym === '' ? s : (c.before ? c.sym + s : s + ' ' + c.sym);
  }
  function allowed(c, gb, days) {
    if (!(gb >= 1 && Math.floor(gb) === gb && days >= 1 && Math.floor(days) === days)) return false;
    if (c.mode === 'days' ? gb !== c.vol.fixed : (gb < c.vol.min || gb > c.vol.max)) return false;
    if (c.mode === 'vol') return days === c.days.fixed;
    if (c.days.pick === 'list') return c.days.list.indexOf(days) !== -1;
    return days >= c.days.min && days <= c.days.max;
  }
  function draw() {
    var c = cfg();
    var box = document.querySelector('#calc .cv-calc-out');
    if (!c || !box) return;
    var gbEl = document.getElementById('cv-gb'), dEl = document.getElementById('cv-days');
    var gb = c.mode === 'days' ? c.vol.fixed : num(gbEl && gbEl.value);
    var days = c.mode === 'vol' ? c.days.fixed : num(dEl && dEl.value);
    var round = num(field('round')) || 0;
    var show = (document.querySelector('#prices input[type=checkbox][name=show]') || {}).checked;
    box.querySelectorAll('[data-lv]').forEach(function (row) {
      var lv = row.getAttribute('data-lv'), out = row.querySelector('.v'), text = '', mute = true;
      var rg = rate('gb', lv), rd = rate('day', lv);
      if (!c.levels[lv] || show === false) text = L.hidden;
      else if (rg !== rg || rd !== rd || ((rg || 0) <= 0 && (rd || 0) <= 0)) text = L.noPrice;
      else if (!allowed(c, gb, days)) text = L.bad;
      else {
        var tier = 0;
        c.tiers.forEach(function (t) { if (gb >= t[0]) tier = t[1]; });
        var sum = gb * (rg || 0) * (100 - tier) / 100 + days * (rd || 0);
        sum = sum * (100 - c.sale) / 100;
        if (round > 0) {
          var r = Math.round(sum / round) * round;
          if (r <= 0 && sum > 0) r = round;
          sum = r;
        }
        text = money(sum, c) + (tier ? '  (−' + tier + '%)' : '');
        mute = false;
      }
      if (out.textContent !== text) out.textContent = text;
      out.classList.toggle('mute', mute);
    });
  }
  document.addEventListener('input', function (e) {
    if (e.target.closest && (e.target.closest('#prices') || e.target.closest('#calc'))) draw();
  });
  document.addEventListener('change', function (e) {
    if (e.target.closest && (e.target.closest('#prices') || e.target.closest('#calc'))) draw();
  });
  // the prices form comes back from a save as a fresh copy
  var pricesCard = document.getElementById('prices');
  if (pricesCard && window.MutationObserver) new MutationObserver(draw).observe(pricesCard, { childList: true });
  draw();
})();
</script>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
