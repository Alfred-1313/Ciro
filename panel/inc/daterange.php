<?php
// 🗓 One date filter for every list of the panel.
//
// The address says what is asked, in the same few words on every page:
//   ?range=today | yesterday | month | lastmonth      a day or a month
//   ?range=days&n=7                                    the last N days (today counts)
//   ?range=custom&from=2026-10-01&to=2026-10-24        from one day to another (either may be left out)
// Days are Gregorian ISO in the address (the calendar on the page shows the
// Persian one as well); a range runs from 00:00:00 of its first day to
// 23:59:59 of its last, in the zone the dates of the panel are shown in.
// Every page keeps what it already had (search, status, order, language tab)
// and carries this along in its pager links (dr_params()).

const DR_MAX_DAYS = 3650;

function dr_day_ok(string $d): bool
{
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1
        && checkdate((int) substr($d, 5, 2), (int) substr($d, 8, 2), (int) substr($d, 0, 4));
}

// what the address asks: the range as seconds (null = open on that side), and
// the pieces the page needs to draw itself again
function dr_read(?array $src = null): array
{
    $src = $src ?? $_GET;
    $key = (string) ($src['range'] ?? '');
    $n = 0;
    $fromDay = $toDay = '';
    $from = $to = null;
    $today = strtotime('today');
    switch ($key) {
        case 'today':
            $from = $today;
            break;
        case 'yesterday':
            $from = strtotime('yesterday');
            $to = $today - 1;
            break;
        case 'month':
            $from = strtotime('first day of this month 00:00:00');
            break;
        case 'lastmonth':
            $from = strtotime('first day of last month 00:00:00');
            $to = strtotime('first day of this month 00:00:00') - 1;
            break;
        case 'days':
            $n = max(1, min(DR_MAX_DAYS, (int) ($src['n'] ?? 0)));
            if ((int) ($src['n'] ?? 0) < 1) {
                $key = '';
                $n = 0;
                break;
            }
            $from = strtotime('today -' . ($n - 1) . ' days');
            break;
        case 'custom':
            $f = (string) ($src['from'] ?? '');
            $t = (string) ($src['to'] ?? '');
            $f = dr_day_ok($f) ? $f : '';
            $t = dr_day_ok($t) ? $t : '';
            if ($f !== '' && $t !== '' && $f > $t) {
                [$f, $t] = [$t, $f];
            }
            if ($f === '' && $t === '') {
                $key = '';
                break;
            }
            $fromDay = $f;
            $toDay = $t;
            $from = $f !== '' ? strtotime($f . ' 00:00:00') : null;
            $to = $t !== '' ? strtotime($t . ' 23:59:59') : null;
            break;
        default:
            $key = '';
    }
    if ($key !== 'custom' && $from !== null) {
        $fromDay = date('Y-m-d', $from);
        $toDay = $to !== null ? date('Y-m-d', $to) : date('Y-m-d');
    }
    return ['key' => $key, 'n' => $n, 'from' => $from, 'to' => $to, 'fromDay' => $fromDay, 'toDay' => $toDay, 'active' => $key !== ''];
}

// the SQL that keeps a row whose time (in seconds, as an expression) is in the
// range; '' when no range is asked. Adds its values to $params.
function dr_cond(array $dr, string $secondsExpr, array &$params): string
{
    $parts = [];
    if ($dr['from'] !== null) {
        $parts[] = "$secondsExpr >= ?";
        $params[] = (int) $dr['from'];
    }
    if ($dr['to'] !== null) {
        $parts[] = "$secondsExpr <= ?";
        $params[] = (int) $dr['to'];
    }
    return $parts ? '(' . implode(' AND ', $parts) . ')' : '';
}

// a time column kept as text: its seconds when it is a plain number
function dr_secs(string $col): string
{
    return "(CASE WHEN $col REGEXP '^[0-9]+$' THEN CAST($col AS UNSIGNED) ELSE NULL END)";
}

// a payment's time as seconds: a unix time, or - for one an admin added by hand -
// «2026/10/21 10:00:00» in the zone the dates are shown in
function dr_pay_secs(string $col): string
{
    return "(CASE WHEN $col REGEXP '^[0-9]+$' THEN CAST($col AS UNSIGNED)"
        . " ELSE TIMESTAMPDIFF(SECOND, '1970-01-01 00:00:00', STR_TO_DATE($col, '%Y/%m/%d %H:%i:%s')) - " . (int) date('Z') . " END)";
}

// the address parts to carry into links (the pager, the sort, the tabs)
function dr_params(array $dr): array
{
    if (!$dr['active']) {
        return [];
    }
    $q = ['range' => $dr['key']];
    if ($dr['key'] === 'days') {
        $q['n'] = $dr['n'];
    } elseif ($dr['key'] === 'custom') {
        $q['from'] = $dr['fromDay'];
        $q['to'] = $dr['toDay'];
    }
    return $q;
}

// a text for the range, without a script: «۷ روز اخیر», «2026-10-01 → 2026-10-24»
function dr_text(array $dr): string
{
    global $textbotlang;
    $p = $textbotlang['panel'];
    switch ($dr['key']) {
        case 'today':
            return $p['drToday'];
        case 'yesterday':
            return $p['drYesterday'];
        case 'month':
            return $p['drMonth'];
        case 'lastmonth':
            return $p['drLastMonth'];
        case 'days':
            return sprintf($p['drLastN'], $dr['n']);
        case 'custom':
            return ($dr['fromDay'] !== '' ? $dr['fromDay'] : '…') . ' → ' . ($dr['toDay'] !== '' ? $dr['toDay'] : '…');
    }
    return $p['drAll'];
}

// the control: a button naming the range, and what opens from it. It sits
// inside the page's own GET form, so what is chosen joins the search, the
// status and the rest, and the form goes with them (its script, js/daterange.js,
// adds the hidden fields and submits). $id: one per control on a page.
function dr_control(array $dr, string $id = 'dr'): string
{
    global $textbotlang;
    $p = $textbotlang['panel'];
    static $assets = false;
    $out = '';
    if (!$assets) {
        // its styles come with every page (layout_head); the script only where there is a control
        $assets = true;
        $out .= '<script defer src="js/daterange.js?v=' . (int) @filemtime(__DIR__ . '/../js/daterange.js') . '"></script>';
    }
    $e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    $presets = [
        ['today', 0, $p['drToday']], ['yesterday', 0, $p['drYesterday']],
        ['days', 2, sprintf($p['drLastN'], 2)], ['days', 7, sprintf($p['drLastN'], 7)], ['days', 30, sprintf($p['drLastN'], 30)],
        ['month', 0, $p['drMonth']], ['lastmonth', 0, $p['drLastMonth']],
    ];
    $out .= '<div class="dr" id="' . $e($id) . '" data-dr data-range="' . $e($dr['key']) . '" data-n="' . (int) $dr['n'] . '"'
        . ' data-from="' . $e($dr['fromDay']) . '" data-to="' . $e($dr['toDay']) . '" data-today="' . date('Y-m-d') . '">'
        . '<button type="button" class="dr-btn' . ($dr['active'] ? ' on' : '') . '" aria-haspopup="dialog" aria-expanded="false" data-dr-open>'
        . '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="5" width="17" height="15.5" rx="3"/><path d="M3.5 10h17M8 3v4M16 3v4"/></svg>'
        . '<span class="dr-btn-t" data-dr-text>' . $e(dr_text($dr)) . '</span>'
        . '</button>';
    // while a range is on it is part of the page's form, so the page's other filters keep it
    foreach (dr_params($dr) as $k => $v) {
        $out .= '<input type="hidden" name="' . $e($k) . '" value="' . $e($v) . '" data-dr-h>';
    }
    if ($dr['active']) {
        $out .= '<button type="button" class="dr-x" data-dr-clear aria-label="' . $e($p['drClear']) . '" title="' . $e($p['drClear']) . '">✕</button>';
    }
    $out .= '<div class="dr-pop" role="dialog" aria-label="' . $e($p['drLabel']) . '">'
        . '<div class="dr-chips" role="group">';
    foreach ($presets as [$k, $n, $label]) {
        $on = $dr['key'] === $k && ($k !== 'days' || $dr['n'] === $n);
        $out .= '<button type="button" class="dr-chip' . ($on ? ' on' : '') . '" data-dr-pick="' . $k . '"' . ($n ? ' data-n="' . $n . '"' : '') . '>' . $e($label) . '</button>';
    }
    $out .= '</div>'
        . '<div class="dr-span" data-dr-span></div>'
        . '<div class="dr-row"><label class="dr-n">' . $e($p['drLastLabel']) . ' <input type="number" min="1" max="' . DR_MAX_DAYS . '" inputmode="numeric" class="dr-num" data-dr-n value="' . ($dr['key'] === 'days' ? (int) $dr['n'] : '') . '" placeholder="N"> ' . $e($p['drDays']) . '</label>'
        . '<button type="button" class="dr-go" data-dr-days>' . $e($p['drApply']) . '</button></div>'
        . '<div class="dr-sep"><span>' . $e($p['drOrRange']) . '</span></div>'
        . '<div class="dr-fields">'
        . '<button type="button" class="dr-field" data-dr-field="from"><small>' . $e($p['drFrom']) . '</small><b>—</b><i></i></button>'
        . '<span class="dr-arrow" aria-hidden="true">→</span>'
        . '<button type="button" class="dr-field" data-dr-field="to"><small>' . $e($p['drTo']) . '</small><b>—</b><i></i></button>'
        . '</div>'
        . '<div class="dr-cal" data-dr-cal></div>'
        . '<div class="dr-foot"><button type="button" class="dr-link" data-dr-clear>' . $e($p['drClear']) . '</button>'
        . '<button type="button" class="dr-link dr-link-ac" data-dr-today>' . $e($p['jsDrGoToday']) . '</button>'
        . '<button type="button" class="dr-go dr-go-main" data-dr-range disabled>' . $e($p['drApply']) . '</button></div>'
        . '</div></div>';
    return $out;
}
