<?php
// 🧩 حجم دلخواه - the customer builds the plan: volume and days are picked on
// one screen (➖/➕, ready numbers, or typed) and the price is worked out as
// they change; the purchase then goes on through the shop's own invoice.
// It replaces the old «⚙️ سرویس دلخواه», which asked for two typed numbers and
// showed the price only on the invoice.
//
// One config per panel (marzban_panel.cvol, JSON), off until an admin turns it
// on. Prices are per language - each language has its own currency - and per
// user group; a reseller group's empty price is a customer's. The old columns
// (customvolume, pricecustomvolume, mainvolume ...) are written along with it:
// the resellers' own bots (vpnbot/) and the API still read them.

if (!function_exists('cvol_cfg')) {
    function cvol_levels()
    {
        return ['f', 'n', 'n2'];
    }

    // the columns this needs, for a bot whose table.php has not run since
    function cvol_ensure_schema()
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        addFieldToTable('marzban_panel', 'cvol', null, 'TEXT NULL');
        addFieldToTable('invoice', 'cvol', null, 'VARCHAR(4) NULL');
        addFieldToTable('user', 'cvol_state', null, 'TEXT NULL');
    }

    function cvol_defaults()
    {
        $langs = [];
        foreach (panel_langs() as $l) {
            $langs[$l] = ['show' => true, 'gb' => ['f' => '', 'n' => '', 'n2' => ''], 'day' => ['f' => '', 'n' => '', 'n2' => ''], 'minbal' => 0, 'round' => 0];
        }
        return [
            'v' => 1,
            'on' => false,
            'levels' => ['f' => true, 'n' => true, 'n2' => true],
            // both: volume and days are the customer's; vol: only the volume
            // (the days are fixed); days: only the days (the volume is fixed)
            'mode' => 'both',
            // steps: each is a −/+ pair of buttons (up to 3; none = no −/+, the
            // volume is picked from the ready buttons or typed)
            'vol' => ['min' => 1, 'max' => 500, 'steps' => [1, 10], 'start' => 20, 'fixed' => 50, 'chips' => [10, 20, 50, 100]],
            // pick: step (−/+ between min and max) or list (one of a few)
            'days' => ['min' => 1, 'max' => 365, 'steps' => [1, 7], 'start' => 30, 'fixed' => 30, 'pick' => 'list', 'list' => [30, 60, 90]],
            // [[from GB, % off the volume part], ...], smallest first
            'tiers' => [],
            'sale' => ['pct' => 0, 'until' => 0],
            'nudge' => true,
            'langs' => $langs,
        ];
    }

    // up to three different whole numbers from 1 to $max, smallest first
    function cvol_steps_clean($raw, $max)
    {
        $out = [];
        foreach ((array) $raw as $x) {
            if (is_numeric($x) && (float) $x == (int) $x && (int) $x >= 1 && (int) $x <= $max) {
                $out[(int) $x] = (int) $x;
            }
        }
        ksort($out);
        return array_slice(array_values($out), 0, 3);
    }
    // «±1، ±10» - how a list of steps is said
    function cvol_steps_text(array $steps, $sep = '، ')
    {
        return implode($sep, array_map(function ($x) {
            return '±' . $x;
        }, $steps));
    }

    // every field in range, every list clean - whatever was stored or posted
    function cvol_normalize($c)
    {
        $d = cvol_defaults();
        $c = is_array($c) ? $c : [];
        $o = $d;
        $o['on'] = !empty($c['on']);
        foreach (cvol_levels() as $l) {
            $o['levels'][$l] = array_key_exists($l, (array) ($c['levels'] ?? [])) ? !empty($c['levels'][$l]) : true;
        }
        $o['mode'] = in_array($c['mode'] ?? '', ['both', 'vol', 'days'], true) ? $c['mode'] : 'both';
        $int = function ($v, $def) {
            return (is_numeric($v) && (float) $v == (int) $v) ? (int) $v : $def;
        };
        // Only min ≤ max ties the fields together. A start, a step or ready
        // buttons outside today's range are kept as they were set (the screen
        // brings them into range when it uses them), so narrowing the range
        // for a while loses nothing; the fixed volume is a mode of its own.
        $abs = function ($x, $hi) {
            return max(1, min($hi, (int) $x));
        };
        $v = is_array($c['vol'] ?? null) ? $c['vol'] : [];
        $vmin = max(1, min(100000, $int($v['min'] ?? null, $d['vol']['min'])));
        $vmax = max($vmin, min(100000, $int($v['max'] ?? null, max($vmin, $d['vol']['max']))));
        $chips = [];
        foreach ((array) ($v['chips'] ?? $d['vol']['chips']) as $x) {
            if (is_numeric($x) && (int) $x >= 1 && (int) $x <= 100000) {
                $chips[(int) $x] = (int) $x;
            }
        }
        ksort($chips);
        // saved before the steps were a list: that one step, and the ×5 one the
        // screen drew beside it (a step of 0 meant none)
        $vsteps = $v['steps'] ?? null;
        if (!is_array($vsteps)) {
            $ls = array_key_exists('step', $v) ? $int($v['step'], 5) : null;
            $vsteps = $ls === null ? $d['vol']['steps'] : ($ls > 0 ? [$ls, $ls * 5] : []);
        }
        $o['vol'] = [
            'min' => $vmin,
            'max' => $vmax,
            'steps' => cvol_steps_clean($vsteps, 100000),
            'start' => $abs($int($v['start'] ?? null, $d['vol']['start']), 100000),
            'fixed' => $abs($int($v['fixed'] ?? null, $d['vol']['fixed']), 100000),
            'chips' => array_slice(array_values($chips), 0, 6),
        ];
        $t = is_array($c['days'] ?? null) ? $c['days'] : [];
        $dmin = max(1, min(3650, $int($t['min'] ?? null, $d['days']['min'])));
        $dmax = max($dmin, min(3650, $int($t['max'] ?? null, max($dmin, $d['days']['max']))));
        // the options are a way of their own to pick (min and max are the ➖/➕ way's)
        $list = [];
        foreach ((array) ($t['list'] ?? $d['days']['list']) as $x) {
            if (is_numeric($x) && (int) $x >= 1 && (int) $x <= 3650) {
                $list[(int) $x] = (int) $x;
            }
        }
        ksort($list);
        $list = array_slice(array_values($list), 0, 8);
        $pick = in_array($t['pick'] ?? '', ['step', 'list'], true) ? $t['pick'] : $d['days']['pick'];
        if ($pick === 'list' && !$list) {
            $pick = 'step';
        }
        $dsteps = $t['steps'] ?? null;
        if (!is_array($dsteps)) {
            $ls = array_key_exists('step', $t) ? $int($t['step'], 5) : null;
            $dsteps = $ls === null ? $d['days']['steps'] : ($ls > 0 ? [$ls, $ls * 5] : []);
        }
        $o['days'] = [
            'min' => $dmin,
            'max' => $dmax,
            'steps' => cvol_steps_clean($dsteps, 3650),
            'start' => $abs($int($t['start'] ?? null, $d['days']['start']), 3650),
            'fixed' => $abs($int($t['fixed'] ?? null, $d['days']['fixed']), 3650),
            'pick' => $pick,
            'list' => $list,
        ];
        $tiers = [];
        foreach ((array) ($c['tiers'] ?? []) as $x) {
            if (is_array($x) && isset($x[0], $x[1]) && is_numeric($x[0]) && is_numeric($x[1]) && (int) $x[0] >= 1 && (int) $x[1] >= 1 && (int) $x[1] <= 90) {
                $tiers[(int) $x[0]] = [(int) $x[0], (int) $x[1]];
            }
        }
        ksort($tiers);
        $o['tiers'] = array_slice(array_values($tiers), 0, 5);
        $s = is_array($c['sale'] ?? null) ? $c['sale'] : [];
        $o['sale'] = [
            'pct' => max(0, min(90, $int($s['pct'] ?? null, 0))),
            'until' => max(0, $int($s['until'] ?? null, 0)),
        ];
        $o['nudge'] = array_key_exists('nudge', $c) ? !empty($c['nudge']) : true;
        $price = function ($x) {
            $x = trim((string) $x);
            return ($x !== '' && is_numeric($x) && (float) $x >= 0) ? rtrim(rtrim(number_format((float) $x, 4, '.', ''), '0'), '.') : '';
        };
        foreach (panel_langs() as $l) {
            $li = is_array($c['langs'][$l] ?? null) ? $c['langs'][$l] : [];
            $row = $d['langs'][$l];
            $row['show'] = array_key_exists('show', $li) ? !empty($li['show']) : true;
            foreach (['gb', 'day'] as $dim) {
                foreach (cvol_levels() as $lv) {
                    $row[$dim][$lv] = $price($li[$dim][$lv] ?? '');
                }
            }
            $row['minbal'] = max(0, (float) (is_numeric($li['minbal'] ?? null) ? $li['minbal'] : 0));
            $row['round'] = max(0, (float) (is_numeric($li['round'] ?? null) ? $li['round'] : 0));
            $o['langs'][$l] = $row;
        }
        return $o;
    }

    // A panel row by its code (or, failing that, its name)
    function cvol_panel($codeOrName)
    {
        if (is_array($codeOrName)) {
            return $codeOrName;
        }
        $p = select("marzban_panel", "*", "code_panel", (string) $codeOrName, "select");
        if (!is_array($p)) {
            $p = select("marzban_panel", "*", "name_panel", (string) $codeOrName, "select");
        }
        return is_array($p) ? $p : null;
    }

    // The old feature's columns, as the new one's first settings - nothing is
    // switched on: the old switches only say who it was meant for.
    function cvol_from_legacy(array $panel)
    {
        $c = cvol_defaults();
        $j = function ($col) use ($panel) {
            $x = json_decode((string) ($panel[$col] ?? ''), true);
            return is_array($x) ? $x : [];
        };
        $flags = $j('customvolume');
        $any = false;
        foreach (cvol_levels() as $l) {
            if ((string) ($flags[$l] ?? '0') === '1') {
                $any = true;
            }
        }
        if ($any) {
            foreach (cvol_levels() as $l) {
                $c['levels'][$l] = (string) ($flags[$l] ?? '0') === '1';
            }
        }
        $num = function ($a) {
            return (isset($a['f']) && is_numeric($a['f'])) ? (float) $a['f'] : null;
        };
        if (($x = $num($j('mainvolume'))) !== null) {
            $c['vol']['min'] = max(1, (int) ceil($x));
        }
        if (($x = $num($j('maxvolume'))) !== null) {
            $c['vol']['max'] = max($c['vol']['min'], (int) floor($x));
        }
        $daysSet = false;
        if (($x = $num($j('maintime'))) !== null) {
            $c['days']['min'] = max(1, (int) $x);
            $daysSet = true;
        }
        if (($x = $num($j('maxtime'))) !== null) {
            $c['days']['max'] = max($c['days']['min'], (int) $x);
            $daysSet = true;
        }
        // the old feature took any number of days in its range: so does this one
        if ($daysSet) {
            $c['days']['pick'] = 'step';
            $c['days']['steps'] = ($c['days']['max'] - $c['days']['min']) <= 60 ? [1, 7] : [7, 30];
        }
        $span = $c['vol']['max'] - $c['vol']['min'];
        $c['vol']['steps'] = $span <= 50 ? [1, 5] : ($span <= 300 ? [5, 25] : [10, 50]);
        // the old prices were in the bot's own currency
        $pgb = $j('pricecustomvolume');
        $pday = $j('pricecustomtime');
        $def = currency_default_code();
        foreach (panel_langs() as $l) {
            if (currency_for_lang($l) !== $def) {
                continue;
            }
            foreach ([['gb', $pgb], ['day', $pday]] as [$dim, $src]) {
                $f = isset($src['f']) && is_numeric($src['f']) ? (string) (float) $src['f'] : '';
                $c['langs'][$l][$dim]['f'] = $f;
                foreach (['n', 'n2'] as $lv) {
                    $v = isset($src[$lv]) && is_numeric($src[$lv]) ? (string) (float) $src[$lv] : '';
                    $c['langs'][$l][$dim][$lv] = $v === $f ? '' : $v;
                }
            }
        }
        return $c;
    }

    // $panel's settings (a row, a code or a name). The first time, they are
    // made from the old feature's columns and saved.
    function cvol_cfg($panel)
    {
        $panel = cvol_panel($panel);
        if (!is_array($panel)) {
            return cvol_normalize([]);
        }
        $code = (string) ($panel['code_panel'] ?? '');
        $hit = cvol_memo($code);
        if ($hit !== null) {
            return $hit;
        }
        if (!array_key_exists('cvol', $panel)) {
            cvol_ensure_schema();
            clearSelectCache('marzban_panel');
            $panel = cvol_panel($code) ?: $panel;
        }
        $raw = json_decode((string) ($panel['cvol'] ?? ''), true);
        if (!is_array($raw)) {
            $c = cvol_normalize(cvol_from_legacy($panel));
            cvol_save($panel, $c);
            return cvol_memo($code, $c);
        }
        return cvol_memo($code, cvol_normalize($raw));
    }
    // one request's copy of each panel's settings - a save replaces it
    function cvol_memo($code, $set = null)
    {
        static $memo = [];
        if ($set !== null) {
            $memo[(string) $code] = $set;
        }
        return $memo[(string) $code] ?? null;
    }

    // the language the old columns' prices are mirrored from: the first one
    // in the bot's own currency
    function cvol_base_lang()
    {
        $def = currency_default_code();
        foreach (panel_langs() as $l) {
            if (currency_for_lang($l) === $def) {
                return $l;
            }
        }
        return 'fa';
    }

    // Saved, and mirrored into the old columns the resellers' bots read.
    function cvol_save($panel, array $c)
    {
        global $pdo;
        $panel = cvol_panel($panel);
        if (!is_array($panel)) {
            return false;
        }
        cvol_ensure_schema();
        $c = cvol_normalize($c);
        $l0 = cvol_base_lang();
        $eff = $pv = $pt = $mv = $xv = $mt = $xt = [];
        foreach (cvol_levels() as $lv) {
            $eff[$lv] = ($c['on'] && $c['levels'][$lv]) ? '1' : '0';
            $pv[$lv] = (string) (cvol_rate($c, $l0, 'gb', $lv) ?? 0);
            $pt[$lv] = (string) (cvol_rate($c, $l0, 'day', $lv) ?? 0);
            if ($c['mode'] === 'days') {
                $mv[$lv] = $xv[$lv] = (string) $c['vol']['fixed'];
            } else {
                $mv[$lv] = (string) $c['vol']['min'];
                $xv[$lv] = (string) $c['vol']['max'];
            }
            if ($c['mode'] === 'vol') {
                $mt[$lv] = $xt[$lv] = (string) $c['days']['fixed'];
            } elseif ($c['days']['pick'] === 'list') {
                $mt[$lv] = (string) min($c['days']['list']);
                $xt[$lv] = (string) max($c['days']['list']);
            } else {
                $mt[$lv] = (string) $c['days']['min'];
                $xt[$lv] = (string) $c['days']['max'];
            }
        }
        $st = $pdo->prepare("UPDATE marzban_panel SET cvol = ?, customvolume = ?, pricecustomvolume = ?, pricecustomtime = ?, mainvolume = ?, maxvolume = ?, maintime = ?, maxtime = ? WHERE code_panel = ?");
        $st->execute([json_encode($c, JSON_UNESCAPED_UNICODE), json_encode($eff), json_encode($pv), json_encode($pt), json_encode($mv), json_encode($xv), json_encode($mt), json_encode($xt), (string) $panel['code_panel']]);
        clearSelectCache('marzban_panel');
        cvol_memo((string) $panel['code_panel'], $c);
        return true;
    }

    // a group's own price for one GB or one day in $lang's currency, a
    // customer's when the group has none of its own; null = not set
    function cvol_rate(array $c, $lang, $dim, $level)
    {
        $map = $c['langs'][$lang][$dim] ?? [];
        $v = (string) ($map[$level] ?? '');
        if ($v === '' && $level !== 'f') {
            $v = (string) ($map['f'] ?? '');
        }
        return ($v !== '' && is_numeric($v)) ? max(0.0, (float) $v) : null;
    }
    // a price that is not zero for both - a free service is never on offer
    function cvol_lang_priced(array $c, $lang, $level = 'f')
    {
        return (cvol_rate($c, $lang, 'gb', $level) ?? 0) > 0 || (cvol_rate($c, $lang, 'day', $level) ?? 0) > 0;
    }
    function cvol_user_lang($userRow)
    {
        $l = is_array($userRow) ? (string) ($userRow['lang'] ?? 'fa') : 'fa';
        return in_array($l, panel_langs(), true) ? $l : 'fa';
    }
    function cvol_level_of($userRow)
    {
        $g = is_array($userRow) ? (string) ($userRow['agent'] ?? 'f') : 'f';
        return in_array($g, cvol_levels(), true) ? $g : 'f';
    }
    // can this user build a plan on this panel right now?
    function cvol_available($panel, $userRow)
    {
        $panel = cvol_panel($panel);
        if (!is_array($panel) || ($panel['type'] ?? '') === 'Manualsale') {
            return false;
        }
        $c = cvol_cfg($panel);
        $lang = cvol_user_lang($userRow);
        $lvl = cvol_level_of($userRow);
        return $c['on'] && !empty($c['levels'][$lvl]) && !empty($c['langs'][$lang]['show']) && cvol_lang_priced($c, $lang, $lvl);
    }

    // is there a panel at all where this user can build a plan?
    function cvol_any_available($userRow)
    {
        global $pdo;
        $st = $pdo->prepare("SELECT * FROM marzban_panel WHERE status = 'active' AND (agent = ? OR agent = 'all')");
        $st->execute([(string) ($userRow['agent'] ?? 'f')]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $p) {
            if (cvol_available($p, $userRow)) {
                return true;
            }
        }
        return false;
    }
    // A purchase's plan list on one panel (the shop's own, category step off),
    // drawn again - what 🔙 on the screen goes back to when the list has no
    // callback of its own (one panel, picked without asking)
    function cvol_product_list(array $u, array $panel, $mid)
    {
        global $textbotlang, $setting;
        $location = (string) $panel['name_panel'];
        $lang = (string) ($u['lang'] ?? 'fa');
        $query = "SELECT * FROM product WHERE (Location = '" . addslashes($location) . "' OR Location = '/all') AND agent = '" . addslashes((string) $u['agent']) . "' AND (FIND_IN_SET('" . addslashes($lang) . "', lang) OR lang = 'all' OR lang IS NULL OR lang = '')";
        $named = username_method_is($panel['MethodUsername'], 'users.customusername') || username_method_is($panel['MethodUsername'], 'keyboard.customUsernameRandom');
        $back = feature_value('statusnamecustom', $lang, $setting['statusnamecustom']) == 'onnamecustom' ? 'buyback' : 'backuser';
        sell_screen($u['id'], $mid, $textbotlang['users']['sell']['serviceSelectFirst'], KeyboardProduct($location, $query, $u['pricediscount'], $named ? 'prodcutservices_' : 'prodcutservice_', cvol_available($panel, $u), $back, null, cvol_entry_cb('b', $panel['code_panel'], 'cvpl')), 3);
    }

    // the least a wallet must hold to use it (a level-2 reseller buys on credit)
    function cvol_minbal($panel, $userRow)
    {
        $panel = cvol_panel($panel);
        if (!is_array($panel) || cvol_level_of($userRow) === 'n2') {
            return 0.0;
        }
        return (float) (cvol_cfg($panel)['langs'][cvol_user_lang($userRow)]['minbal'] ?? 0);
    }
    function cvol_minbal_ok($panel, $userRow)
    {
        $min = cvol_minbal($panel, $userRow);
        return $min <= 0 || (float) ($userRow['Balance'] ?? 0) >= $min;
    }

    // which of the two the customer chooses in this mode
    function cvol_free(array $c)
    {
        return ['vol' => $c['mode'] !== 'days', 'days' => $c['mode'] !== 'vol'];
    }
    function cvol_is_int($v)
    {
        return is_int($v) || (is_string($v) && ctype_digit($v)) || (is_float($v) && $v == (int) $v);
    }
    // a volume and days this config allows
    function cvol_check(array $c, $gb, $days)
    {
        if (!cvol_is_int($gb) || !cvol_is_int($days)) {
            return false;
        }
        $gb = (int) $gb;
        $days = (int) $days;
        if ($c['mode'] === 'days') {
            if ($gb !== (int) $c['vol']['fixed']) {
                return false;
            }
        } elseif ($gb < $c['vol']['min'] || $gb > $c['vol']['max']) {
            return false;
        }
        if ($c['mode'] === 'vol') {
            return $days === (int) $c['days']['fixed'];
        }
        if ($c['days']['pick'] === 'list') {
            return in_array($days, $c['days']['list'], true);
        }
        return $days >= $c['days']['min'] && $days <= $c['days']['max'];
    }
    // the nearest the config allows
    function cvol_fit(array $c, $gb, $days)
    {
        $gb = (int) $gb;
        $days = (int) $days;
        $gb = $c['mode'] === 'days' ? (int) $c['vol']['fixed'] : max($c['vol']['min'], min($c['vol']['max'], $gb));
        if ($c['mode'] === 'vol') {
            $days = (int) $c['days']['fixed'];
        } elseif ($c['days']['pick'] === 'list') {
            if (!in_array($days, $c['days']['list'], true)) {
                $best = $c['days']['list'][0];
                foreach ($c['days']['list'] as $x) {
                    if (abs($x - $days) < abs($best - $days)) {
                        $best = $x;
                    }
                }
                $days = $best;
            }
        } else {
            $days = max($c['days']['min'], min($c['days']['max'], $days));
        }
        return [$gb, $days];
    }

    function cvol_tier_pct(array $c, $gb)
    {
        $p = 0;
        foreach ($c['tiers'] as [$from, $pct]) {
            if ($gb >= $from) {
                $p = $pct;
            }
        }
        return $p;
    }
    function cvol_tier_from(array $c, $gb)
    {
        $f = 0;
        foreach ($c['tiers'] as [$from, $pct]) {
            if ($gb >= $from) {
                $f = $from;
            }
        }
        return $f;
    }
    function cvol_next_tier(array $c, $gb)
    {
        foreach ($c['tiers'] as [$from, $pct]) {
            if ($gb < $from) {
                return [$from, $pct];
            }
        }
        return null;
    }
    // the special offer while it lasts
    function cvol_sale_pct(array $c)
    {
        $s = $c['sale'];
        return ($s['pct'] > 0 && ($s['until'] == 0 || $s['until'] > time())) ? (int) $s['pct'] : 0;
    }
    // to the nearest $step (never down to nothing), else to the currency's own coin
    function cvol_round_amount($amount, $step, $cur)
    {
        $dec = (int) (currency_get($cur)['decimals'] ?? 0);
        $step = (float) $step;
        if ($step > 0) {
            $r = round($amount / $step) * $step;
            if ($r <= 0 && $amount > 0) {
                $r = $step;
            }
            return round($r, max($dec, 4));
        }
        return round($amount, $dec);
    }

    // What $gb GB for $days days costs a group in $lang (before the customer's
    // own account discount, which the invoice takes off as it does for every
    // plan). null when the config does not allow it.
    function cvol_quote(array $c, $lang, $level, $gb, $days)
    {
        if (!cvol_check($c, $gb, $days)) {
            return null;
        }
        $rg = cvol_rate($c, $lang, 'gb', $level) ?? 0.0;
        $rd = cvol_rate($c, $lang, 'day', $level) ?? 0.0;
        if ($rg <= 0 && $rd <= 0) {
            return null;
        }
        $gb = (int) $gb;
        $days = (int) $days;
        $tier = cvol_tier_pct($c, $gb);
        $sum = $gb * $rg * (100 - $tier) / 100 + $days * $rd;
        $sale = cvol_sale_pct($c);
        $sum = $sum * (100 - $sale) / 100;
        $cur = currency_for_lang($lang);
        $price = cvol_round_amount($sum, $c['langs'][$lang]['round'] ?? 0, $cur);
        return ['price' => $price, 'tier' => $tier, 'sale' => $sale, 'rg' => $rg, 'rd' => $rd, 'cur' => $cur, 'gb' => $gb, 'days' => $days];
    }
    // the same, for this user on this panel - null when it is not on offer to them
    function cvol_quote_for($panel, $userRow, $gb, $days)
    {
        $panel = cvol_panel($panel);
        if (!cvol_available($panel, $userRow)) {
            return null;
        }
        return cvol_quote(cvol_cfg($panel), cvol_user_lang($userRow), cvol_level_of($userRow), $gb, $days);
    }
    // «customvolume_DAYS_GB», the product code a plan travels as
    function cvol_parse_code($code)
    {
        $p = explode('_', (string) $code);
        if (count($p) !== 3 || $p[0] !== 'customvolume' || !ctype_digit($p[1]) || !ctype_digit($p[2])) {
            return null;
        }
        return ['days' => (int) $p[1], 'gb' => (int) $p[2]];
    }

    // the caption's pieces and the screen's popups: 🎨 items of their own,
    // but not messages - no sticker, no message to react to
    function cvol_fragment_keys()
    {
        return ['title', 'unitGb', 'unitDay', 'countLine', 'tierLine', 'tierNext', 'saleLine', 'saleUntil', 'userDiscLine', 'shortLine', 'nudgeLine',
            'typeHowBoth', 'typeHowVol', 'typeHowDays', 'rangeVol', 'rangeDays', 'rangeDaysList', 'atMax', 'atMin', 'expired', 'unavailable'];
    }
    function cvol_tx($lang)
    {
        $t = lang_tab_texts($lang);
        return is_array($t['users']['cvol'] ?? null) ? $t['users']['cvol'] : lang_tab_texts('fa')['users']['cvol'];
    }
    // the name a bought plan has on its invoice
    function cvol_title($lang)
    {
        return (string) (cvol_tx($lang)['title'] ?? '🧩');
    }
    // a rate as it was set: 0.008 stays 0.008 even where the currency itself
    // has two decimals (money() would show $0.01)
    function cvol_money_rate($amount, $cur)
    {
        $c = currency_get($cur);
        $dec = (int) ($c['decimals'] ?? 0);
        $raw = rtrim(rtrim(number_format((float) $amount, 4, '.', ''), '0'), '.');
        $own = strpos($raw, '.') !== false ? strlen($raw) - strpos($raw, '.') - 1 : 0;
        if ($own <= $dec) {
            return money($amount, $cur);
        }
        $num = number_format((float) $amount, $own, '.', ',');
        if (($c['symbol'] ?? '') === '') {
            return $num;
        }
        return ($c['symbol_position'] ?? '') === 'before' ? $c['symbol'] . $num : $num . ' ' . $c['symbol'];
    }
    function cvol_num($n)
    {
        return number_format((float) $n, ((float) $n == (int) $n) ? 0 : 2, '.', ',');
    }
    // the names the old feature gave a bought service
    function cvol_legacy_names()
    {
        return ['⚙️ سرویس دلخواه', '🛍 حجم دلخواه', '⚙️ Custom service', '🛍 Custom volume'];
    }
    // was this service bought as a custom plan (now or with the old feature)?
    function cvol_invoice_is_custom($inv)
    {
        if (!is_array($inv)) {
            return false;
        }
        if ((string) ($inv['cvol'] ?? '') === '1') {
            return true;
        }
        return in_array((string) ($inv['name_product'] ?? ''), cvol_legacy_names(), true);
    }
    // ♻️ a renewal priced as a custom plan, in the shape the renewal code
    // uses - null when the panel does not offer one to this user (any more)
    function cvol_is_renew_code($code)
    {
        return in_array((string) $code, ['customvolume', 'custom_volume'], true);
    }
    function cvol_renew_product($panel, $userRow, array $inv, $gb, $days, $code = 'custom_volume')
    {
        $panel = cvol_panel($panel);
        $q = is_array($panel) ? cvol_quote_for($panel, $userRow, $gb, $days) : null;
        if ($q === null) {
            return null;
        }
        return ['code_product' => $code, 'name_product' => $inv['name_product'], 'note' => '', 'price_product' => $q['price'], 'Service_time' => $q['days'], 'Volume_constraint' => $q['gb'], 'inbounds' => $panel['inboundid'] ?? null];
    }
    function cvol_mark_invoice($idInvoice)
    {
        global $pdo;
        cvol_ensure_schema();
        $pdo->prepare("UPDATE invoice SET cvol = '1' WHERE id_invoice = ?")->execute([(string) $idInvoice]);
        clearSelectCache('invoice');
    }

    // a panel's name as the customer sees it (🎨 renames it per language)
    function cvol_panel_label(array $panel, $lang)
    {
        $sec = function_exists('help_layout_section') ? help_layout_section($lang, 'panel') : [];
        return (string) ($sec['rename'][(string) $panel['code_panel']] ?? $panel['name_panel']);
    }

    // The cheapest ready plan on this panel that gives at least as much for
    // less - so a customer is not left paying more for building it themselves.
    function cvol_nudge(array $panel, $userRow, array $q)
    {
        global $pdo;
        $lang = cvol_user_lang($userRow);
        $st = $pdo->prepare("SELECT * FROM product WHERE (Location = ? OR Location = '/all') AND agent = ? AND one_buy_status = '0' AND (FIND_IN_SET(?, lang) OR lang = 'all' OR lang IS NULL OR lang = '')");
        $st->execute([(string) $panel['name_panel'], cvol_level_of($userRow), $lang]);
        $best = null;
        $def = currency_default_code();
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $hide = json_decode((string) ($p['hide_panel'] ?? ''), true);
            if (is_array($hide) && in_array($panel['name_panel'], $hide, true)) {
                continue;
            }
            $vol = (float) $p['Volume_constraint'];
            $days = (int) $p['Service_time'];
            if (!(($vol <= 0 || $vol >= $q['gb']) && ($days <= 0 || $days >= $q['days']))) {
                continue;
            }
            if ((($p['currency'] ?? '') ?: $def) !== $q['cur']) {
                continue;
            }
            $price = (float) $p['price_product'];
            if ($price <= 0 || $price >= $q['price']) {
                continue;
            }
            if ($best === null || $price < (float) $best['price_product']) {
                $best = $p;
            }
        }
        return $best;
    }

    // For the admin: ready plans the same thing would undercut (a customer
    // building it pays less than the plan) - [lang, product, custom price].
    function cvol_cheaper_warnings($panel, $limit = 3)
    {
        global $pdo;
        $panel = cvol_panel($panel);
        if (!is_array($panel)) {
            return [];
        }
        $c = cvol_cfg($panel);
        $out = [];
        $def = currency_default_code();
        foreach (panel_langs() as $lang) {
            if (empty($c['langs'][$lang]['show']) || !cvol_lang_priced($c, $lang)) {
                continue;
            }
            $st = $pdo->prepare("SELECT * FROM product WHERE (Location = ? OR Location = '/all') AND agent = 'f' AND (FIND_IN_SET(?, lang) OR lang = 'all' OR lang IS NULL OR lang = '') ORDER BY price_product");
            $st->execute([(string) $panel['name_panel'], $lang]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $p) {
                $vol = (float) $p['Volume_constraint'];
                $days = (int) $p['Service_time'];
                if ($vol <= 0 || $days <= 0 || $vol != (int) $vol) {
                    continue;
                }
                if ((($p['currency'] ?? '') ?: $def) !== currency_for_lang($lang)) {
                    continue;
                }
                $q = cvol_quote($c, $lang, 'f', (int) $vol, $days);
                if ($q !== null && $q['price'] < (float) $p['price_product']) {
                    $out[] = [$lang, $p, $q['price']];
                    if (count($out) >= $limit) {
                        return $out;
                    }
                }
            }
        }
        return $out;
    }

    // What has been sold as a custom plan on this panel
    function cvol_stats($panel)
    {
        global $pdo;
        $panel = cvol_panel($panel);
        cvol_ensure_schema();
        $name = (string) ($panel['name_panel'] ?? '');
        $paid = "cvol = '1' AND Service_location = ? AND Status NOT IN ('unpaid', 'Unpaid')";
        $st = $pdo->prepare("SELECT COUNT(*) n, COALESCE(currency, '') cur, SUM(price_product) s FROM invoice WHERE {$paid} GROUP BY COALESCE(currency, '')");
        $st->execute([$name]);
        $n = 0;
        $sum = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $n += (int) $r['n'];
            $cur = $r['cur'] !== '' ? $r['cur'] : currency_default_code();
            $sum[$cur] = ($sum[$cur] ?? 0) + (float) $r['s'];
        }
        $st = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE {$paid} AND CAST(time_sell AS UNSIGNED) >= ?");
        $st->execute([$name, time() - 30 * 86400]);
        $n30 = (int) $st->fetchColumn();
        $st = $pdo->prepare("SELECT Volume v, Service_time d, COUNT(*) c FROM invoice WHERE {$paid} GROUP BY Volume, Service_time ORDER BY c DESC LIMIT 5");
        $st->execute([$name]);
        return ['n' => $n, 'n30' => $n30, 'sum' => $sum, 'top' => $st->fetchAll(PDO::FETCH_ASSOC)];
    }
}

// ── the customer's screen ─────────────────────────────────────────
if (!function_exists('cvol_state_all')) {
    // What a user has open: 's' the plan screen, 'h' (an admin) the settings
    // hub - in user.cvol_state, apart from Processing_value, which the
    // purchase flow itself uses.
    function cvol_state_all($uid)
    {
        $row = select("user", "*", "id", (string) $uid, "select", ['cache' => false]);
        if (!is_array($row)) {
            return [];
        }
        if (!array_key_exists('cvol_state', $row)) {
            cvol_ensure_schema();
            return [];
        }
        $j = json_decode((string) ($row['cvol_state'] ?? ''), true);
        return is_array($j) ? $j : [];
    }
    function cvol_state_get($uid, $key)
    {
        $v = cvol_state_all($uid)[$key] ?? null;
        return is_array($v) ? $v : null;
    }
    function cvol_state_put($uid, $key, $val)
    {
        $all = cvol_state_all($uid);
        if ($val === null) {
            unset($all[$key]);
        } else {
            $all[$key] = $val;
        }
        update("user", "cvol_state", json_encode($all, JSON_UNESCAPED_UNICODE), "id", (string) $uid);
    }

    // the button under a plan list that opens the screen: ctx b (a purchase)
    // or o (several at once), with the list's own callback to come back to
    function cvol_entry_cb($ctx, $panelCode, $back)
    {
        $cb = "cvs|{$ctx}|{$panelCode}|{$back}";
        return strlen($cb) <= 64 ? $cb : "cvs|{$ctx}|{$panelCode}|backuser";
    }
    function cvol_entry_button($lang, $callback)
    {
        if (bt_button_hidden($lang, 'users.cvol.entryBtn')) {
            return null;
        }
        return bt_button($lang, 'users.cvol.entryBtn', cvol_tx($lang)['entryBtn'], $callback, 'primary');
    }
    // 🧩 The plan screen's entry button is an item of 📐 چیدمان ← محصولات
    // (key 'cvol'), so it can be put where the admin wants among the plans, share
    // a row, be hidden, and take its own name / emoji / colour there. It is
    // listed once any panel offers custom plans; until the admin sets something
    // it renders exactly as 🎨 ← حجم دلخواه draws it, last in the list.
    function cvol_layout_on()
    {
        static $memo = null;
        if ($memo === null) {
            global $pdo;
            $memo = false;
            foreach ($pdo->query("SELECT * FROM marzban_panel")->fetchAll(PDO::FETCH_ASSOC) as $p) {
                if (($p['type'] ?? '') !== 'Manualsale' && cvol_cfg($p)['on']) {
                    $memo = true;
                    break;
                }
            }
        }
        return $memo;
    }
    // what its row in the layout editors reads: the button as a customer sees it
    function cvol_layout_label($lang)
    {
        $b = cvol_entry_button($lang, 'x');
        return is_array($b) && trim((string) ($b['text'] ?? '')) !== '' ? (string) $b['text'] : cvol_tx($lang)['entryBtn'];
    }
    // the layout's own choices laid over the button 🎨 drew
    function cvol_layout_button(array $btn, array $section)
    {
        $name = trim((string) ($section['rename']['cvol'] ?? ''));
        if ($name !== '') {
            $btn['text'] = $name;
        }
        $emo = help_layout_emoji_prefix('cvol', $section);
        if ($emo['icon'] !== '') {
            $btn['icon_custom_emoji_id'] = $emo['icon'];
        } elseif ($emo['prefix'] !== '') {
            unset($btn['icon_custom_emoji_id']);
            $btn['text'] = $emo['prefix'] . $btn['text'];
        }
        $color = (string) ($section['color']['cvol'] ?? '');
        if (in_array($color, ['primary', 'success', 'danger'], true)) {
            $btn['style'] = $color;
        }
        return $btn;
    }
    // one of the screen's own buttons (🎨 alias 'cv'), or null when hidden
    function cvol_btn($lang, $idx, $callback, array $vars = [], $force = false)
    {
        $defs = genbtn_defs('cv', lang_tab_texts($lang));
        $def = $defs[$idx] ?? null;
        if ($def === null) {
            return null;
        }
        $ov = genbtn_override($lang, 'users.cvol.caption', $idx);
        if (!$force && genbtn_hideable('cv', $idx) && genbtn_is_hidden($def, $ov)) {
            return null;
        }
        $b = genbtn_render($def, $ov, $callback);
        $b['text'] = strtr($b['text'], $vars);
        return $b;
    }

    // the sticker 🎨 gives the screen in this chat's language ('' = none)
    function cvol_sticker_for($chat)
    {
        $key = 'users.cvol.caption';
        $lang = function_exists('bottext_receiver_lang') ? bottext_receiver_lang($chat) : 'fa';
        if (function_exists('bt_item_enabled') && !bt_item_enabled($key, $lang)) {
            return '';
        }
        $setting = select("setting", "*", null, null, "select");
        $layout = json_decode((string) ($setting['keyboardmain'] ?? ''), true);
        $map = (is_array($layout) && is_array($layout['text_stickers'] ?? null)) ? $layout['text_stickers'] : [];
        if (function_exists('bt_effective_sticker')) {
            return (string) bt_effective_sticker($map, $key, $lang);
        }
        return function_exists('bt_media_lookup') ? (string) bt_media_lookup($map, $key, $lang) : '';
    }

    // On screen. The first time on a purchase it is a step of its own (4, after
    // the plan list): with a sticker of its own it comes with it, the way the
    // purchase flow's screens do. Every change after that edits the message
    // itself - never through Editmessagetext(), which would send the sticker
    // again on each tap. Returns the screen's message id.
    function cvol_show($chat, $mid, $text, $kb, $first = false, $sticker = false)
    {
        $chat = (string) $chat;
        $mid = (int) $mid;
        if ($first && $sticker) {
            $sv = cvol_sticker_for($chat);
            if ($sv !== '') {
                sell_sticker_retire($chat);
                if ($mid > 0) {
                    deletemessage($chat, $mid);
                }
                $sres = sticker_value_send($chat, $sv);
                $sid = (int) ($sres['result']['message_id'] ?? 0);
                $res = telegram('sendmessage', ['chat_id' => $chat, 'text' => $text, 'reply_markup' => $kb, 'parse_mode' => 'HTML']);
                update("user", "bt_sticker_id", $sid > 0 ? $sid . ':4' : '0', "id", $chat);
                return (int) ($res['result']['message_id'] ?? 0);
            }
        }
        if ($mid > 0) {
            $r = telegram('editMessageText', ['chat_id' => $chat, 'message_id' => $mid, 'text' => $text, 'reply_markup' => $kb, 'parse_mode' => 'HTML']);
            if (!empty($r['ok']) || stripos((string) ($r['description'] ?? ''), 'not modified') !== false) {
                return $mid;
            }
            // a photo screen (a service's, with its QR) cannot become text
            deletemessage($chat, $mid);
        }
        $res = telegram('sendmessage', ['chat_id' => $chat, 'text' => $text, 'reply_markup' => $kb, 'parse_mode' => 'HTML']);
        return (int) ($res['result']['message_id'] ?? 0);
    }

    // {placeholders} filled; a line that was nothing but placeholders and
    // came out empty goes
    function cvol_fill($tpl, array $vars)
    {
        $out = [];
        foreach (explode("\n", (string) $tpl) as $line) {
            $filled = strtr($line, $vars);
            if (trim($filled) === '' && preg_match('/\{[a-z_]+\}/', $line) && trim(preg_replace('/\{[a-z_]+\}/', '', $line)) === '') {
                continue;
            }
            $out[] = $filled;
        }
        return trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $out)));
    }
    function cvol_date($ts, $lang)
    {
        if ($lang === 'fa' && !function_exists('jdate') && is_file(__DIR__ . '/jdf.php')) {
            require_once __DIR__ . '/jdf.php';
        }
        if ($lang === 'fa' && function_exists('jdate')) {
            return jdate('Y/m/d H:i', (int) $ts);
        }
        return date('Y-m-d H:i', (int) $ts);
    }
    // the allowed numbers, said in the customer's words
    function cvol_ranges_text(array $c, array $tx, $lang = 'fa')
    {
        $free = cvol_free($c);
        $l = [];
        if ($free['vol']) {
            $l[] = strtr($tx['rangeVol'], ['{min}' => cvol_num($c['vol']['min']), '{max}' => cvol_num($c['vol']['max'])]);
        }
        if ($free['days']) {
            $l[] = $c['days']['pick'] === 'list'
                ? strtr($tx['rangeDaysList'], ['{list}' => implode($lang === 'fa' ? '، ' : ', ', $c['days']['list'])])
                : strtr($tx['rangeDays'], ['{min}' => $c['days']['min'], '{max}' => $c['days']['max']]);
        }
        return implode("\n", $l);
    }

    // The screen for $st (the state) - [text, keyboard], or null when the plan
    // is not on offer any more.
    function cvol_screen(array $u, array $st, $preview = false)
    {
        $panel = cvol_panel($st['p'] ?? '');
        if (!is_array($panel) || (!$preview && !cvol_available($panel, $u))) {
            return null;
        }
        $c = cvol_cfg($panel);
        $lang = cvol_user_lang($u);
        $tx = cvol_tx($lang);
        $lvl = cvol_level_of($u);
        [$g, $d] = cvol_fit($c, $st['g'] ?? $c['vol']['start'], $st['d'] ?? $c['days']['start']);
        $q = cvol_quote($c, $lang, $lvl, $g, $d);
        if ($q === null) {
            return null;
        }
        $cur = $q['cur'];
        $dec = (int) (currency_get($cur)['decimals'] ?? 0);
        $disc = max(0, min(100, (int) ($u['pricediscount'] ?? 0)));
        $pay = round($q['price'] * (100 - $disc) / 100, $dec);
        $count = ($st['c'] ?? 'b') === 'o' ? max(1, (int) ($st['n'] ?? 1)) : 1;
        $total = $pay * $count;
        $unitG = function ($n) use ($tx) {
            return strtr($tx['unitGb'], ['{n}' => cvol_num($n)]);
        };
        $unitD = function ($n) use ($tx) {
            return strtr($tx['unitDay'], ['{n}' => cvol_num($n)]);
        };
        $lines = [];
        if ($q['tier'] > 0) {
            $lines[] = strtr($tx['tierLine'], ['{pct}' => $q['tier'], '{gb}' => cvol_num(cvol_tier_from($c, $g))]);
        }
        $next = cvol_next_tier($c, $g);
        if ($next !== null && cvol_free($c)['vol']) {
            $lines[] = strtr($tx['tierNext'], ['{gb}' => cvol_num($next[0]), '{pct}' => $next[1]]);
        }
        if ($q['sale'] > 0) {
            $until = $c['sale']['until'] > 0 ? strtr($tx['saleUntil'], ['{date}' => cvol_date($c['sale']['until'], $lang)]) : '';
            $lines[] = strtr($tx['saleLine'], ['{pct}' => $q['sale'], '{until}' => $until]);
        }
        if ($disc > 0) {
            $lines[] = strtr($tx['userDiscLine'], ['{pct}' => $disc]);
        }
        $balance = (float) ($u['Balance'] ?? 0);
        $short = ($lvl !== 'n2' && $total > $balance) ? strtr($tx['shortLine'], ['{need}' => money($total - $balance, $cur)]) : '';
        $nudge = ($c['nudge'] && ($st['c'] ?? 'b') !== 'o') ? cvol_nudge($panel, $u, $q) : null;
        $nudgePay = $nudge ? round((float) $nudge['price_product'] * (100 - $disc) / 100, $dec) : 0;
        $inv = ($st['c'] ?? '') === 'r' ? select("invoice", "*", "id_invoice", (string) ($st['i'] ?? ''), "select") : null;
        $vars = [
            '{panel}' => htmlspecialchars(cvol_panel_label($panel, $lang)),
            '{service}' => htmlspecialchars((string) ($inv['username'] ?? '')),
            '{volume}' => $unitG($g),
            '{days}' => $unitD($d),
            '{price}' => money($pay, $cur),
            '{per_gb}' => cvol_money_rate($q['rg'], $cur),
            '{per_day}' => cvol_money_rate($q['rd'], $cur),
            '{balance}' => money($balance, $cur),
            '{count_line}' => $count > 1 ? strtr($tx['countLine'], ['{count}' => $count, '{total}' => money($total, $cur)]) : '',
            '{discount_lines}' => implode("\n", $lines),
            '{short_line}' => $short,
            '{nudge_line}' => $nudge ? strtr($tx['nudgeLine'], ['{name}' => htmlspecialchars((string) $nudge['name_product']), '{volume}' => (float) $nudge['Volume_constraint'] > 0 ? $unitG($nudge['Volume_constraint']) : '∞', '{days}' => (int) $nudge['Service_time'] > 0 ? $unitD($nudge['Service_time']) : '∞', '{price}' => money($nudgePay, $cur)]) : '',
            '{ranges}' => cvol_ranges_text($c, $tx, $lang),
        ];
        $text = cvol_fill(($st['c'] ?? '') === 'r' ? $tx['captionRenew'] : $tx['caption'], $vars);

        $rows = [];
        $free = cvol_free($c);
        // one −/+ pair per step: the value in the middle for one or two steps;
        // three would crowd it, so it gets a row of its own above them
        $stepper = function ($kind, $val, $min, $max, array $steps, $label) {
            if (!$steps) {
                return [];
            }
            $cb = function ($x) use ($kind, $min, $max) {
                return "cvb|{$kind}|" . max($min, min($max, $x));
            };
            $minus = [];
            $plus = [];
            foreach (array_reverse($steps) as $st) {
                $minus[] = ['text' => '−' . cvol_num($st), 'callback_data' => $cb($val - $st)];
            }
            foreach ($steps as $st) {
                $plus[] = ['text' => '+' . cvol_num($st), 'callback_data' => $cb($val + $st)];
            }
            $mid = ['text' => $label, 'callback_data' => 'cvb|n'];
            return count($steps) <= 2 ? [array_merge($minus, [$mid], $plus)] : [[$mid], array_merge($minus, $plus)];
        };
        $chipsInRange = false;
        if ($free['vol']) {
            foreach ($stepper('v', $g, $c['vol']['min'], $c['vol']['max'], $c['vol']['steps'], '📦 ' . $unitG($g)) as $stepRow) {
                $rows[] = $stepRow;
            }
            $chips = [];
            foreach ($c['vol']['chips'] as $x) {
                if ($x >= $c['vol']['min'] && $x <= $c['vol']['max']) {
                    $chips[] = ['text' => ($x === $g ? '✓ ' : '') . $unitG($x), 'callback_data' => 'cvb|v|' . $x];
                }
            }
            if ($chips) {
                $rows[] = $chips;
                $chipsInRange = true;
            }
        }
        if ($free['days']) {
            if ($c['days']['pick'] === 'list') {
                $row = [];
                foreach ($c['days']['list'] as $x) {
                    $row[] = ['text' => ($x === $d ? '✓ ' : '') . $unitD($x), 'callback_data' => 'cvb|d|' . $x, 'style' => $x === $d ? 'success' : 'primary'];
                    if (count($row) === 3) {
                        $rows[] = $row;
                        $row = [];
                    }
                }
                if ($row) {
                    $rows[] = $row;
                }
            } else {
                foreach ($stepper('d', $d, $c['days']['min'], $c['days']['max'], $c['days']['steps'], '📅 ' . $unitD($d)) as $stepRow) {
                    $rows[] = $stepRow;
                }
            }
        }
        if ($free['vol'] || ($free['days'] && $c['days']['pick'] === 'step')) {
            // with no ➖/➕ and no ready number to tap, typing is the only way to
            // choose the volume - so its button stays even if 🎨 hid it
            $volControl = !empty($c['vol']['steps']) || $chipsInRange;
            $daysControl = $c['days']['pick'] === 'list' || !empty($c['days']['steps']);
            $onlyTyping = ($free['vol'] && !$volControl) || ($free['days'] && !$daysControl);
            if ($b = cvol_btn($lang, 1, 'cvb|t', [], $onlyTyping)) {
                $rows[] = [$b];
            }
        }
        $ctx = $st['c'] ?? 'b';
        $named = username_method_is($panel['MethodUsername'], 'users.customusername') || username_method_is($panel['MethodUsername'], 'keyboard.customUsernameRandom');
        $code = "customvolume_{$d}_{$g}";
        $go = $ctx === 'r' ? 'cvb|r' : (($named ? 'prodcutservices' : 'prodcutservice') . ($ctx === 'o' ? 'om_' : '_') . $code);
        $rows[] = [cvol_btn($lang, 0, $go, ['{price}' => money($ctx === 'o' ? $total : $pay, $cur)])];
        // Short of balance: ✅ ادامه goes on to the confirm step, whose «افزایش موجودی»
        // makes the invoice for exactly what is lacking (shortfall.php) and the
        // purchase follows the payment. A plain top-up button here would not carry
        // the amount, so there is none; the one on the «minimum balance» screen
        // (cvol_open) stays - that one has no purchase to finish.
        if ($nudge) {
            $ncb = $ctx === 'r' ? 'serviceextendselect_' . $nudge['code_product'] : (($named ? 'prodcutservices_' : 'prodcutservice_') . $nudge['code_product']);
            if (strlen($ncb) <= 64 && ($b = cvol_btn($lang, 4, $ncb, ['{name}' => (string) $nudge['name_product'], '{price}' => money($nudgePay, $cur)]))) {
                $rows[] = [$b];
            }
        }
        if ($b = cvol_btn($lang, 2, 'cvb|b')) {
            $rows[] = [$b];
        }
        return [$text, json_encode(['inline_keyboard' => $rows])];
    }

    // Where the screen opens: the user's last plan on this panel (or, for a
    // renewal, the service's own), else the config's own start
    function cvol_start_values(array $c, array $u, array $panel, $ctx, $inv)
    {
        global $pdo;
        $g = $c['vol']['start'];
        $d = $c['days']['start'];
        $row = null;
        if ($ctx === 'r' && $inv !== '') {
            $row = select("invoice", "*", "id_invoice", (string) $inv, "select");
        } else {
            cvol_ensure_schema();
            $st = $pdo->prepare("SELECT Volume, Service_time FROM invoice WHERE id_user = ? AND Service_location = ? AND cvol = '1' AND Status NOT IN ('unpaid', 'Unpaid') ORDER BY CAST(time_sell AS UNSIGNED) DESC LIMIT 1");
            $st->execute([(string) $u['id'], (string) $panel['name_panel']]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if (is_array($row) && is_numeric($row['Volume'] ?? null) && (float) $row['Volume'] > 0 && is_numeric($row['Service_time'] ?? null) && (int) $row['Service_time'] > 0) {
            $g = (int) round((float) $row['Volume']);
            $d = (int) $row['Service_time'];
        }
        return cvol_fit($c, $g, $d);
    }

    // Opens the screen for $u on $panel. false when it is not on offer to them.
    function cvol_open(array $u, $panel, $ctx, $back, $mid, $inv = '')
    {
        $panel = cvol_panel($panel);
        if (!is_array($panel) || !cvol_available($panel, $u)) {
            return false;
        }
        $uid = (string) $u['id'];
        $c = cvol_cfg($panel);
        $lang = cvol_user_lang($u);
        $tx = cvol_tx($lang);
        $cur = currency_for_lang($lang);
        $min = cvol_minbal($panel, $u);
        if (!cvol_minbal_ok($panel, $u)) {
            $kb = [];
            if ($b = cvol_btn($lang, 3, 'Add_Balance')) {
                $kb[] = [$b];
            }
            if ($b = cvol_btn($lang, 2, $back)) {
                $kb[] = [$b];
            }
            cvol_show($uid, $mid, strtr($tx['minBalance'], ['{min}' => money($min, $cur), '{balance}' => money($u['Balance'] ?? 0, $cur)]), json_encode(['inline_keyboard' => $kb]));
            return true;
        }
        [$g, $d] = cvol_start_values($c, $u, $panel, $ctx, $inv);
        $st = ['c' => $ctx, 'p' => (string) $panel['code_panel'], 'g' => $g, 'd' => $d, 'k' => (string) $back, 'i' => (string) $inv, 'at' => time()];
        if ($ctx === 'o') {
            $st['n'] = max(1, (int) ($u['Processing_value_four'] ?? 1));
        }
        $screen = cvol_screen($u, $st);
        if ($screen === null) {
            return false;
        }
        $st['m'] = cvol_show($uid, $mid, $screen[0], $screen[1], true, $ctx === 'b');
        cvol_state_put($uid, 's', $st);
        return true;
    }

    function cvol_alert($text)
    {
        global $callback_query_id;
        if ($callback_query_id) {
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => function_exists('alert_plain_text') ? alert_plain_text($text) : strip_tags($text), 'show_alert' => true]);
        }
    }

    // The customer's taps and typing on the screen. true: handled; a string:
    // the callback to carry on with (🔙 hands the tap to the screen it goes
    // back to); false: not this screen's.
    function cvol_customer_dispatch()
    {
        global $from_id, $datain, $text, $message_id, $user, $callback_query_id, $textbotlang;
        $data = (string) $datain;
        $lang = cvol_user_lang($user);
        // a plan list's button
        if (preg_match('/^cvs\|([bo])\|([^|]+)\|(.+)$/', $data, $m)) {
            $panel = cvol_panel($m[2]);
            if ($m[1] === 'b' && is_array($panel)) {
                savedata('save', 'name_panel', $panel['name_panel']);
            }
            if (!cvol_open($user, $panel, $m[1], $m[3], $message_id)) {
                cvol_alert(cvol_tx($lang)['unavailable']);
            }
            return true;
        }
        // ♻️ a renewal's list: the service's own panel, back to the service
        if (preg_match('/^cvs\|r\|([A-Za-z0-9_]+)$/', $data, $m)) {
            $inv = select("invoice", "*", "id_invoice", $m[1], "select");
            if (!is_array($inv) || (string) $inv['id_user'] !== (string) $from_id || !cvol_open($user, cvol_panel($inv['Service_location']), 'r', 'product_' . $inv['id_invoice'], $message_id, $inv['id_invoice'])) {
                cvol_alert(cvol_tx($lang)['unavailable']);
            }
            return true;
        }
        // a button from before this screen existed
        if ($data === 'customsellvolume' || $data === 'customsellvolumeom') {
            $pd = json_decode((string) $user['Processing_value'], true);
            $panel = $data === 'customsellvolume' ? cvol_panel(is_array($pd) ? ($pd['name_panel'] ?? '') : '') : cvol_panel((string) $user['Processing_value']);
            $ctx = $data === 'customsellvolume' ? 'b' : 'o';
            $back = is_array($panel) ? ($ctx === 'b' ? 'location_' . $panel['code_panel'] : 'locationom_' . $panel['code_panel']) : 'backuser';
            if (!is_array($panel) || !cvol_open($user, $panel, $ctx, $back, $message_id)) {
                cvol_alert(cvol_tx($lang)['unavailable']);
            }
            return true;
        }
        // a number typed for the screen
        if ($user['step'] === 'cvol_type' && $data === '') {
            if (!preg_match_all('/\d+/', (string) $text, $nm)) {
                // a menu button or anything else: the question is dropped
                step('home', $from_id);
                return false;
            }
            $st = cvol_state_get($from_id, 's');
            $panel = $st ? cvol_panel($st['p']) : null;
            if (!$st || !is_array($panel) || !cvol_available($panel, $user)) {
                step('home', $from_id);
                sendmessage($from_id, cvol_tx($lang)['expired'], null, 'HTML');
                return true;
            }
            $c = cvol_cfg($panel);
            $tx = cvol_tx($lang);
            $nums = array_map('intval', $nm[0]);
            $free = cvol_free($c);
            $g = (int) $st['g'];
            $d = (int) $st['d'];
            if ($free['vol']) {
                $g = $nums[0];
                if (isset($nums[1]) && $free['days']) {
                    $d = $nums[1];
                }
            } elseif ($free['days']) {
                $d = $nums[0];
            }
            deletemessage($from_id, $message_id);
            if (!cvol_check($c, $g, $d)) {
                if (!empty($st['pm'])) {
                    telegram('editMessageText', ['chat_id' => $from_id, 'message_id' => (int) $st['pm'], 'text' => strtr($tx['typeInvalid'], ['{ranges}' => cvol_ranges_text($c, $tx, $lang)]), 'reply_markup' => json_encode(['inline_keyboard' => [[cvol_btn($lang, 5, 'cvb|x')]]]), 'parse_mode' => 'HTML']);
                }
                return true;
            }
            if (!empty($st['pm'])) {
                deletemessage($from_id, (int) $st['pm']);
            }
            $st['g'] = $g;
            $st['d'] = $d;
            unset($st['pm']);
            step('home', $from_id);
            $screen = cvol_screen($user, $st);
            if ($screen !== null) {
                $st['m'] = cvol_show($from_id, (int) ($st['m'] ?? 0), $screen[0], $screen[1]);
            }
            cvol_state_put($from_id, 's', $st);
            return true;
        }
        if (strpos($data, 'cvb|') !== 0) {
            return false;
        }
        $st = cvol_state_get($from_id, 's');
        $parts = explode('|', $data);
        $act = $parts[1] ?? '';
        if ($act === 'n') {
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id]);
            return true;
        }
        if (!$st || time() - (int) ($st['at'] ?? 0) > 6 * 3600) {
            cvol_alert(cvol_tx($lang)['expired']);
            return true;
        }
        if ($act === 'b') {
            // back: this screen's sticker goes, and the screen it came from takes the tap
            [$sid, $owner] = sell_sticker_owner($from_id);
            if ($sid > 0 && $owner >= 4) {
                sell_sticker_retire($from_id);
            }
            if ($user['step'] === 'cvol_type') {
                step('home', $from_id);
            }
            cvol_state_put($from_id, 's', null);
            if ((string) $st['k'] === 'cvpl') {
                $panel = cvol_panel($st['p']);
                if (is_array($panel)) {
                    cvol_product_list($user, $panel, $message_id);
                    return true;
                }
                return 'backuser';
            }
            return (string) $st['k'];
        }
        $panel = cvol_panel($st['p']);
        if (!is_array($panel) || !cvol_available($panel, $user)) {
            cvol_alert(cvol_tx($lang)['unavailable']);
            return true;
        }
        $c = cvol_cfg($panel);
        $tx = cvol_tx($lang);
        if ($act === 'x') {
            if (!empty($st['pm'])) {
                deletemessage($from_id, (int) $st['pm']);
            }
            unset($st['pm']);
            cvol_state_put($from_id, 's', $st);
            step('home', $from_id);
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id]);
            return true;
        }
        if ($act === 't') {
            $free = cvol_free($c);
            $how = $free['vol'] && $free['days'] && $c['days']['pick'] === 'step' ? 'typeHowBoth' : ($free['vol'] ? 'typeHowVol' : 'typeHowDays');
            $example = $how === 'typeHowBoth' ? "{$st['g']} {$st['d']}" : ($free['vol'] ? (string) $st['g'] : (string) $st['d']);
            $body = strtr($tx['typePrompt'], ['{how}' => strtr($tx[$how], ['{example}' => $example]), '{ranges}' => cvol_ranges_text($c, $tx, $lang)]);
            if (!empty($st['pm'])) {
                deletemessage($from_id, (int) $st['pm']);
            }
            $r = telegram('sendmessage', ['chat_id' => $from_id, 'text' => $body, 'reply_markup' => json_encode(['inline_keyboard' => [[cvol_btn($lang, 5, 'cvb|x')]]]), 'parse_mode' => 'HTML']);
            $st['pm'] = (int) ($r['result']['message_id'] ?? 0);
            $st['m'] = (int) $message_id;
            cvol_state_put($from_id, 's', $st);
            step('cvol_type', $from_id);
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id]);
            return true;
        }
        if ($act === 'v' || $act === 'd') {
            $val = (int) ($parts[2] ?? 0);
            $key = $act === 'v' ? 'g' : 'd';
            $cur = (int) $st[$key];
            $lim = $act === 'v' ? $c['vol'] : $c['days'];
            if ($val === $cur) {
                // a ➖ or ➕ that cannot move any further says so
                if ($val <= $lim['min']) {
                    cvol_alert(strtr($tx['atMin'], ['{min}' => $act === 'v' ? strtr($tx['unitGb'], ['{n}' => cvol_num($lim['min'])]) : strtr($tx['unitDay'], ['{n}' => $lim['min']])]));
                } elseif ($val >= $lim['max']) {
                    cvol_alert(strtr($tx['atMax'], ['{max}' => $act === 'v' ? strtr($tx['unitGb'], ['{n}' => cvol_num($lim['max'])]) : strtr($tx['unitDay'], ['{n}' => $lim['max']])]));
                } else {
                    telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id]);
                }
                return true;
            }
            [$g, $d] = cvol_fit($c, $key === 'g' ? $val : $st['g'], $key === 'd' ? $val : $st['d']);
            $st['g'] = $g;
            $st['d'] = $d;
            $screen = cvol_screen($user, $st);
            if ($screen === null) {
                cvol_alert($tx['unavailable']);
                return true;
            }
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id]);
            $st['m'] = cvol_show($from_id, $message_id, $screen[0], $screen[1]);
            cvol_state_put($from_id, 's', $st);
            return true;
        }
        if ($act === 'r' && ($st['c'] ?? '') === 'r') {
            // ♻️ the renewal invoice, as for a plan picked from the list
            $inv = select("invoice", "*", "id_invoice", (string) $st['i'], "select");
            if (!is_array($inv) || (string) $inv['id_user'] !== (string) $from_id) {
                cvol_alert($tx['expired']);
                return true;
            }
            $q = cvol_quote_for($panel, $user, $st['g'], $st['d']);
            if ($q === null) {
                cvol_alert($tx['unavailable']);
                return true;
            }
            $product = ['name_product' => $inv['name_product'], 'code_product' => 'customvolume', 'note' => '', 'price_product' => $q['price'], 'Service_time' => $q['days'], 'Volume_constraint' => $q['gb']];
            savedata("clear", "id_invoice", $inv['id_invoice']);
            savedata("save", "time", $q['days']);
            savedata("save", "data_limit", $q['gb']);
            savedata("save", "price_product", $q['price']);
            savedata("save", "code_product", "customvolume");
            [$t, $k] = render_extend_invoice_from_product($user, $textbotlang, $inv, $product);
            Editmessagetext($from_id, $message_id, $t, $k);
            cvol_state_put($from_id, 's', null);
            step('home', $from_id);
            return true;
        }
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id]);
        return true;
    }
}

// ── the admin's hub ─────────────────────────────────────────────
if (!function_exists('cvol_hub')) {
    function cvol_atx()
    {
        return panel_texts()['Admin']['Cvol'];
    }
    function cvol_lang_label($l)
    {
        $n = lang_tab_texts('fa')['bottext']['langs'][$l] ?? null;
        return is_string($n) ? $n : $l;
    }
    function cvol_cur_label($cur)
    {
        $c = currency_get($cur);
        return (string) (($c['title'] ?? '') !== '' ? $c['title'] : $cur);
    }
    function cvol_kb(array $rows)
    {
        return json_encode(['inline_keyboard' => array_values(array_filter($rows))]);
    }
    function cvol_b($text, $cb, $style = 'primary')
    {
        return ['text' => $text, 'callback_data' => $cb, 'style' => $style];
    }
    // a raw price as the hub shows it: '—' when not set
    function cvol_price_label(array $c, $lang, $dim, $lv, array $t)
    {
        $raw = (string) ($c['langs'][$lang][$dim][$lv] ?? '');
        if ($raw === '') {
            return $lv === 'f' ? $t['unset'] : $t['priceSame'];
        }
        return cvol_money_rate((float) $raw, currency_for_lang($lang));
    }

    // what each filter says right now ('' = not set)
    function cvol_filter_lines(array $c, array $t)
    {
        $minbal = [];
        $round = [];
        foreach (panel_langs() as $l) {
            $cur = currency_for_lang($l);
            if ($c['langs'][$l]['minbal'] > 0) {
                $minbal[] = cvol_lang_label($l) . ': ' . money($c['langs'][$l]['minbal'], $cur);
            }
            if ($c['langs'][$l]['round'] > 0) {
                $round[] = cvol_lang_label($l) . ': ' . money($c['langs'][$l]['round'], $cur);
            }
        }
        $tiers = [];
        foreach ($c['tiers'] as [$g, $p]) {
            $tiers[] = strtr($t['tierItem'], ['{gb}' => $g, '{pct}' => $p]);
        }
        $s = $c['sale'];
        $ended = $s['pct'] > 0 && $s['until'] > 0 && $s['until'] <= time();
        $sale = ($s['pct'] <= 0 || $ended) ? '' : ($s['until'] > 0
            ? strtr($t['saleUntil'], ['{pct}' => $s['pct'], '{date}' => cvol_date($s['until'], 'fa')])
            : strtr($t['saleForever'], ['{pct}' => $s['pct']]));
        return [
            'minbal' => implode(' · ', $minbal),
            'round' => implode(' · ', $round),
            'tiers' => implode(' · ', $tiers),
            'sale' => $sale,
            'saleEnded' => $ended,
            'nudge' => (bool) $c['nudge'],
        ];
    }

    // a panel's hub - [text, keyboard]
    function cvol_hub(array $panel, $note = '')
    {
        $t = cvol_atx();
        $c = cvol_cfg($panel);
        $pc = (string) $panel['code_panel'];
        $lv = [];
        foreach (cvol_levels() as $l) {
            if ($c['levels'][$l]) {
                $lv[] = $t['icon_' . $l] . ' ' . $t['lvl_' . $l];
            }
        }
        $mode = $c['mode'] === 'vol' ? strtr($t['modeVol'], ['{days}' => $c['days']['fixed']]) : ($c['mode'] === 'days' ? strtr($t['modeDays'], ['{gb}' => $c['vol']['fixed']]) : $t['modeBoth']);
        $volSteps = cvol_steps_text($c['vol']['steps']);
        $vol = $c['mode'] === 'days' ? strtr($t['volFixed'], ['{gb}' => $c['vol']['fixed']])
            : strtr($volSteps !== '' ? $t['volFree'] : $t['volFreeNoStep'], ['{min}' => $c['vol']['min'], '{max}' => $c['vol']['max'], '{steps}' => $volSteps, '{start}' => $c['vol']['start']])
            . ($c['vol']['chips'] ? strtr($t['volChips'], ['{list}' => implode('، ', $c['vol']['chips'])]) : '');
        $daysSteps = cvol_steps_text($c['days']['steps']);
        $days = $c['mode'] === 'vol' ? strtr($t['daysFixed'], ['{days}' => $c['days']['fixed']])
            : ($c['days']['pick'] === 'list' ? strtr($t['daysList'], ['{list}' => implode('، ', $c['days']['list'])])
                : strtr($daysSteps !== '' ? $t['daysStep'] : $t['daysStepNo'], ['{min}' => $c['days']['min'], '{max}' => $c['days']['max'], '{steps}' => $daysSteps, '{start}' => $c['days']['start']]));
        $prices = [];
        $langs = [];
        $warn = [];
        foreach (panel_langs() as $l) {
            $cur = currency_for_lang($l);
            $head = strtr($t['priceLang'], ['{lang}' => cvol_lang_label($l), '{cur}' => cvol_cur_label($cur)]);
            $langs[] = cvol_lang_label($l) . ' ' . ($c['langs'][$l]['show'] ? '✅' : '❌');
            if (!$c['langs'][$l]['show']) {
                $prices[] = $head . ' ' . $t['priceHidden'];
                continue;
            }
            if (!cvol_lang_priced($c, $l)) {
                $prices[] = $head . ' ' . $t['priceNone'];
                $warn[] = strtr($t['warnNoPrice'], ['{lang}' => cvol_lang_label($l)]);
                continue;
            }
            $prices[] = $head;
            foreach (cvol_levels() as $x) {
                $same = $x !== 'f' && (string) $c['langs'][$l]['gb'][$x] === '' && (string) $c['langs'][$l]['day'][$x] === '';
                $prices[] = $same ? '      ' . $t['icon_' . $x] . ' ' . $t['lvl_' . $x] . ': ' . $t['priceSame']
                    : strtr($t['priceLevel'], ['{icon}' => $t['icon_' . $x], '{level}' => $t['lvl_' . $x], '{gb}' => cvol_price_label($c, $l, 'gb', $x, $t), '{day}' => cvol_price_label($c, $l, 'day', $x, $t)]);
            }
        }
        if ($c['on'] && !$lv) {
            array_unshift($warn, $t['warnNoLevel']);
        }
        foreach (cvol_cheaper_warnings($panel) as [$l, $p, $cp]) {
            $cur = currency_for_lang($l);
            $warn[] = strtr($t['warnCheaper'], ['{name}' => htmlspecialchars((string) $p['name_product']), '{gb}' => cvol_num($p['Volume_constraint']), '{days}' => (int) $p['Service_time'], '{price}' => money($p['price_product'], $cur), '{cprice}' => money($cp, $cur)]);
        }
        // only the filters that are on: the rest is one tap away (🔎)
        $fl = cvol_filter_lines($c, $t);
        $flLines = [];
        foreach ([['flMinbal', $fl['minbal']], ['flRound', $fl['round']], ['flTiers', $fl['tiers']], ['flSale', $fl['sale']]] as [$k, $v]) {
            if ($v !== '') {
                $flLines[] = $t[$k] . ' — ' . $v;
            }
        }
        if ($fl['nudge']) {
            $flLines[] = $t['flNudge'];
        }
        $manual = ($panel['type'] ?? '') === 'Manualsale';
        $text = strtr($t['hub'], [
            '{panel}' => htmlspecialchars((string) $panel['name_panel']),
            '{status}' => $manual ? $t['manualNote'] : ($c['on'] ? $t['statusOn'] : $t['statusOff']),
            '{levels}' => $lv ? implode('، ', $lv) : $t['levelsNone'],
            '{mode}' => $mode,
            '{vol}' => $vol,
            '{days}' => $days,
            '{prices}' => implode("\n", $prices),
            '{filters}' => $flLines ? "\n▫️ " . implode("\n▫️ ", $flLines) : ' ' . $t['none'],
            '{langs}' => implode(' · ', $langs),
            '{warnings}' => $warn ? "\n\n" . $t['warnTitle'] . "\n" . implode("\n", $warn) : '',
        ]);
        if ($note !== '') {
            $text = $note . "\n\n" . $text;
        }
        $h = "cvh|{$pc}|";
        $rows = [];
        if (!$manual) {
            $rows[] = [$c['on'] ? cvol_b($t['btnTurnOff'], $h . 'on', 'success') : cvol_b($t['btnTurnOn'], $h . 'on', 'danger')];
        }
        $rows[] = [cvol_b($t['btnPrices'], $h . 'pr|' . panel_langs()[0]), cvol_b($t['btnMode'], $h . 'mo')];
        $rows[] = [cvol_b($t['btnVol'], $h . 'vo'), cvol_b($t['btnDays'], $h . 'dy')];
        $rows[] = [cvol_b($t['btnFilters'], $h . 'fl'), cvol_b($t['btnAccess'], $h . 'ac')];
        $rows[] = [cvol_b($t['btnPreview'], $h . 'pv'), cvol_b($t['btnStats'], $h . 'st')];
        // 🎨: the screen's words, buttons and sticker, one tab per language
        $looks = [];
        foreach (panel_langs() as $l) {
            $looks[] = cvol_b(strtr($t['btnLooksLang'], ['{lang}' => cvol_lang_label($l)]), "bt_group|{$l}|cvol");
        }
        $rows[] = $looks;
        $rows[] = [cvol_b($t['btnHelp'], $h . 'hl')];
        $rows[] = [cvol_b($t['btnBack'], $h . 'bk', 'danger')];
        return [$text, cvol_kb($rows)];
    }

    // What a customer of $lang in group $lvl would see - the screen itself,
    // with buttons that only say they are a preview - and the controls to look
    // at another language or group. [text, keyboard]
    function cvol_preview(array $panel, $lang, $lvl)
    {
        global $from_id;
        $t = cvol_atx();
        $c = cvol_cfg($panel);
        $pc = (string) $panel['code_panel'];
        $lang = in_array($lang, panel_langs(), true) ? $lang : panel_langs()[0];
        $lvl = in_array($lvl, cvol_levels(), true) ? $lvl : 'f';
        $head = strtr($t['previewHead'], ['{panel}' => htmlspecialchars((string) $panel['name_panel']), '{lang}' => cvol_lang_label($lang), '{level}' => $t['icon_' . $lvl] . ' ' . $t['lvl_' . $lvl]]);
        [$g, $d] = cvol_fit($c, $c['vol']['start'], $c['days']['start']);
        $q = cvol_quote($c, $lang, $lvl, $g, $d);
        $rows = [];
        if ($q === null) {
            $body = $t['previewNoPrice'];
        } else {
            // a balance that covers it: the shortage line is another story
            $u = ['id' => (string) $from_id, 'lang' => $lang, 'agent' => $lvl, 'Balance' => $q['price'] * 10, 'pricediscount' => 0, 'Processing_value_four' => 1];
            $screen = cvol_screen($u, ['c' => 'b', 'p' => $pc, 'g' => $g, 'd' => $d], true);
            if ($screen === null) {
                $body = $t['previewNoPrice'];
            } else {
                $body = $screen[0];
                $kb = json_decode($screen[1], true);
                foreach ((array) ($kb['inline_keyboard'] ?? []) as $row) {
                    foreach ($row as &$btn) {
                        $btn['callback_data'] = "cvh|{$pc}|pvx";
                    }
                    unset($btn);
                    $rows[] = $row;
                }
            }
        }
        $langRow = [];
        foreach (panel_langs() as $l) {
            $langRow[] = cvol_b(($l === $lang ? '✓ ' : '') . cvol_lang_label($l), "cvh|{$pc}|pw|{$l}|{$lvl}", $l === $lang ? 'success' : 'primary');
        }
        $lvRow = [];
        foreach (cvol_levels() as $x) {
            $lvRow[] = cvol_b(($x === $lvl ? '✓ ' : '') . $t['icon_' . $x] . ' ' . $t['lvl_' . $x], "cvh|{$pc}|pw|{$lang}|{$x}", $x === $lvl ? 'success' : 'primary');
        }
        if (count($langRow) > 1) {
            $rows[] = $langRow;
        }
        $rows[] = $lvRow;
        $rows[] = [cvol_b($t['btnClosePreview'], 'cvl|x', 'danger')];
        return [$head . $body, cvol_kb($rows)];
    }

    // One of the hub's own screens. $act names it, $a and $b are its arguments.
    function cvol_hub_screen(array $panel, $act, $a = '', $b = '', $note = '')
    {
        $t = cvol_atx();
        $c = cvol_cfg($panel);
        $pc = (string) $panel['code_panel'];
        $h = "cvh|{$pc}|";
        $name = htmlspecialchars((string) $panel['name_panel']);
        $back = [cvol_b($t['btnBack'], $h . 'm', 'danger')];
        // the filters' screens go back to the filters
        $backFl = [cvol_b($t['btnBack'], $h . 'fl', 'danger')];
        $mark = function ($on) {
            return $on ? '✅ ' : '';
        };
        $langTabs = function ($act, $cur) use ($h) {
            $row = [];
            foreach (panel_langs() as $l) {
                $row[] = cvol_b(($l === $cur ? '✓ ' : '') . cvol_lang_label($l), $h . $act . '|' . $l, $l === $cur ? 'success' : 'primary');
            }
            return $row;
        };
        $out = null;
        switch ($act) {
            case 'mo':
                // the number a mode keeps the same for everyone, one tap away
                $fixedRow = $c['mode'] === 'days' ? [cvol_b(strtr($t['modeFixedVolBtn'], ['{v}' => $c['vol']['fixed']]), $h . 'in|vfixed')]
                    : ($c['mode'] === 'vol' ? [cvol_b(strtr($t['modeFixedDaysBtn'], ['{v}' => $c['days']['fixed']]), $h . 'in|dfixed')] : null);
                $out = [strtr($t['mode'], ['{panel}' => $name, '{days}' => $c['days']['fixed'], '{gb}' => $c['vol']['fixed']]), cvol_kb([
                    [cvol_b($mark($c['mode'] === 'both') . $t['modeBothBtn'], $h . 'ms|both', $c['mode'] === 'both' ? 'success' : 'primary')],
                    [cvol_b($mark($c['mode'] === 'vol') . $t['modeVolBtn'], $h . 'ms|vol', $c['mode'] === 'vol' ? 'success' : 'primary'), cvol_b($mark($c['mode'] === 'days') . $t['modeDaysBtn'], $h . 'ms|days', $c['mode'] === 'days' ? 'success' : 'primary')],
                    $fixedRow,
                    $back,
                ])];
                break;
            case 'vo':
                $v = $c['vol'];
                if ($c['mode'] === 'days') {
                    // «فقط مدت»: the volume is the same for everyone - that one number is all there is to set
                    $out = [strtr($t['volFixedOnly'], ['{panel}' => $name, '{fixed}' => $v['fixed']]), cvol_kb([
                        [cvol_b(strtr($t['volFixedBtn'], ['{v}' => $v['fixed']]), $h . 'in|vfixed')],
                        $back,
                    ])];
                    break;
                }
                $stepLine = $v['steps'] ? strtr($t['volStepLine'], ['{list}' => cvol_steps_text($v['steps'])]) : $t['volStepOff'];
                $out = [strtr($t['vol'], ['{panel}' => $name, '{min}' => $v['min'], '{max}' => $v['max'], '{step_line}' => $stepLine, '{start}' => $v['start'], '{chips}' => $v['chips'] ? implode('، ', $v['chips']) : $t['none']]), cvol_kb([
                    [cvol_b(strtr($t['volMinBtn'], ['{v}' => $v['min']]), $h . 'in|vmin'), cvol_b(strtr($t['volMaxBtn'], ['{v}' => $v['max']]), $h . 'in|vmax')],
                    [cvol_b($v['steps'] ? strtr($t['stepsBtn'], ['{list}' => cvol_steps_text($v['steps'])]) : $t['stepsBtnOff'], $h . 'sp|v'), cvol_b(strtr($t['volStartBtn'], ['{v}' => $v['start']]), $h . 'in|vstart')],
                    [cvol_b($t['volChipsBtn'], $h . 'in|vchips')],
                    $back,
                ])];
                break;
            case 'dy':
                $d = $c['days'];
                if ($c['mode'] === 'vol') {
                    // «فقط حجم»: the days are the same for everyone
                    $out = [strtr($t['daysFixedOnly'], ['{panel}' => $name, '{fixed}' => $d['fixed']]), cvol_kb([
                        [cvol_b(strtr($t['daysFixedBtn'], ['{v}' => $d['fixed']]), $h . 'in|dfixed')],
                        $back,
                    ])];
                    break;
                }
                // only what the way of picking uses: the options, or the range, its steps and its start
                if ($d['pick'] === 'list') {
                    $body = strtr($t['daysBodyList'], ['{list}' => $d['list'] ? implode('، ', $d['list']) : $t['none']]);
                } else {
                    $stepLine = $d['steps'] ? strtr($t['daysStepLine'], ['{list}' => cvol_steps_text($d['steps'])]) : $t['daysStepOff'];
                    $body = strtr($t['daysBodyStep'], ['{min}' => $d['min'], '{max}' => $d['max'], '{step_line}' => $stepLine, '{start}' => $d['start']]);
                }
                $kb = [[cvol_b($mark($d['pick'] === 'list') . $t['pickListBtn'], $h . 'dp|list', $d['pick'] === 'list' ? 'success' : 'primary'), cvol_b($mark($d['pick'] === 'step') . $t['pickStepBtn'], $h . 'dp|step', $d['pick'] === 'step' ? 'success' : 'primary')]];
                if ($d['pick'] === 'list') {
                    $kb[] = [cvol_b(strtr($t['daysListBtn'], ['{v}' => $d['list'] ? implode(', ', $d['list']) : '—']), $h . 'in|dlist')];
                } else {
                    $kb[] = [cvol_b(strtr($t['daysMinBtn'], ['{v}' => $d['min']]), $h . 'in|dmin'), cvol_b(strtr($t['daysMaxBtn'], ['{v}' => $d['max']]), $h . 'in|dmax')];
                    $kb[] = [cvol_b($d['steps'] ? strtr($t['stepsBtn'], ['{list}' => cvol_steps_text($d['steps'])]) : $t['stepsBtnOff'], $h . 'sp|d'), cvol_b(strtr($t['daysStartBtn'], ['{v}' => $d['start']]), $h . 'in|dstart')];
                }
                $kb[] = $back;
                $out = [strtr($t['days'], ['{panel}' => $name, '{pick}' => $d['pick'] === 'list' ? $t['pickList'] : $t['pickStep'], '{body}' => $body]), cvol_kb($kb)];
                break;
            case 'pr':
                $l = in_array($a, panel_langs(), true) ? $a : panel_langs()[0];
                $cur = currency_for_lang($l);
                $rows = [];
                $kb = [$langTabs('pr', $l)];
                foreach (cvol_levels() as $x) {
                    $rows[] = strtr($t['priceRow'], ['{icon}' => $t['icon_' . $x], '{level}' => $t['lvl_' . $x], '{gb}' => cvol_price_label($c, $l, 'gb', $x, $t), '{day}' => cvol_price_label($c, $l, 'day', $x, $t)]);
                    $kb[] = [cvol_b(strtr($t['priceGbBtn'], ['{icon}' => $t['icon_' . $x], '{level}' => $t['lvl_' . $x]]), $h . "in|gb|{$l}|{$x}"), cvol_b(strtr($t['priceDayBtn'], ['{icon}' => $t['icon_' . $x], '{level}' => $t['lvl_' . $x]]), $h . "in|day|{$l}|{$x}")];
                }
                $kb[] = $back;
                $out = [strtr($t['prices'], ['{panel}' => $name, '{lang}' => cvol_lang_label($l), '{cur}' => cvol_cur_label($cur), '{rows}' => implode("\n", $rows)]), cvol_kb($kb)];
                break;
            case 'mb':
                $l = in_array($a, panel_langs(), true) ? $a : panel_langs()[0];
                $cur = currency_for_lang($l);
                $li = $c['langs'][$l];
                $dec = (int) (currency_get($cur)['decimals'] ?? 0);
                $opts = $dec > 0 ? [0, 0.1, 0.5, 1, 5] : [0, 100, 500, 1000, 5000, 10000];
                $kb = [$langTabs('mb', $l), [cvol_b($t['minbalBtn'], $h . "in|minbal|{$l}")]];
                $row = [];
                foreach ($opts as $o) {
                    $sel = abs((float) $li['round'] - $o) < 1e-9;
                    $row[] = cvol_b(($sel ? '✓ ' : '') . ($o == 0 ? $t['roundOff'] : money($o, $cur, false)), $h . "rd|{$l}|{$o}", $sel ? 'success' : 'primary');
                    if (count($row) === 3) {
                        $kb[] = $row;
                        $row = [];
                    }
                }
                if ($row) {
                    $kb[] = $row;
                }
                $kb[] = $backFl;
                $out = [strtr($t['minbal'], ['{panel}' => $name, '{lang}' => cvol_lang_label($l), '{cur}' => cvol_cur_label($cur), '{minbal}' => $li['minbal'] > 0 ? money($li['minbal'], $cur) : $t['none'], '{round}' => $li['round'] > 0 ? money($li['round'], $cur) : $t['roundOff']]), cvol_kb($kb)];
                break;
            case 'ti':
                $list = [];
                $kb = [];
                foreach ($c['tiers'] as $i => [$g, $p]) {
                    $list[] = strtr($t['tierRow'], ['{gb}' => $g, '{pct}' => $p]);
                    $kb[] = [cvol_b(strtr($t['tierDelBtn'], ['{gb}' => $g, '{pct}' => $p]), $h . 'tr|' . $i, 'danger')];
                }
                if (count($c['tiers']) < 5) {
                    $kb[] = [cvol_b($t['tierAddBtn'], $h . 'in|tier', 'success')];
                }
                $kb[] = $backFl;
                $out = [strtr($t['tiers'], ['{panel}' => $name, '{list}' => $list ? implode("\n", $list) : $t['none']]), cvol_kb($kb)];
                break;
            case 'sa':
                $s = $c['sale'];
                $sale = $s['pct'] <= 0 ? $t['none'] : ($s['until'] > 0 && $s['until'] <= time() ? $t['saleEnded']
                    : ($s['until'] > 0 ? strtr($t['saleUntil'], ['{pct}' => $s['pct'], '{date}' => cvol_date($s['until'], 'fa')]) : strtr($t['saleForever'], ['{pct}' => $s['pct']])));
                $kb = [[cvol_b($t['saleSetBtn'], $h . 'in|sale', 'success')]];
                if ($s['pct'] > 0) {
                    $kb[] = [cvol_b($t['saleDelBtn'], $h . 'sx', 'danger')];
                }
                $kb[] = $backFl;
                $out = [strtr($t['sale'], ['{panel}' => $name, '{sale}' => $sale]), cvol_kb($kb)];
                break;
            case 'fl':
                $fl = cvol_filter_lines($c, $t);
                $none = $t['none'];
                $out = [strtr($t['filters'], [
                    '{panel}' => $name,
                    '{minbal}' => $fl['minbal'] !== '' ? $fl['minbal'] : $none,
                    '{round}' => $fl['round'] !== '' ? $fl['round'] : $none,
                    '{tiers}' => $fl['tiers'] !== '' ? $fl['tiers'] : $none,
                    '{sale}' => $fl['sale'] !== '' ? $fl['sale'] : ($fl['saleEnded'] ? $t['saleEnded'] : $none),
                    '{nudge}' => $fl['nudge'] ? $t['on'] : $t['off'],
                ]), cvol_kb([
                    [cvol_b($t['flBtnMinbal'], $h . 'mb|' . panel_langs()[0]), cvol_b($t['btnTiers'], $h . 'ti')],
                    [cvol_b($t['btnSale'], $h . 'sa'), cvol_b(strtr($t['btnNudge'], ['{state}' => $fl['nudge'] ? '✅' : '❌']), $h . 'nu|fl', $fl['nudge'] ? 'success' : 'danger')],
                    $back,
                ])];
                break;
            case 'ac':
            case 'lg':
                $lvRow = [];
                $lvOn = [];
                foreach (cvol_levels() as $l) {
                    $lvRow[] = cvol_b(strtr($t['btnLevel'], ['{icon}' => $t['icon_' . $l], '{level}' => $t['lvl_' . $l], '{state}' => $c['levels'][$l] ? '✅' : '❌']), $h . 'lv|' . $l, $c['levels'][$l] ? 'success' : 'danger');
                    if ($c['levels'][$l]) {
                        $lvOn[] = $t['icon_' . $l] . ' ' . $t['lvl_' . $l];
                    }
                }
                $kb = [$lvRow];
                $lgOn = [];
                foreach (panel_langs() as $l) {
                    $on = $c['langs'][$l]['show'];
                    $kb[] = [cvol_b(strtr($t['langBtn'], ['{lang}' => cvol_lang_label($l), '{state}' => $on ? '✅' : '❌']), $h . 'lt|' . $l, $on ? 'success' : 'danger')];
                    $lgOn[] = cvol_lang_label($l) . ' ' . ($on ? '✅' : '❌');
                }
                $kb[] = $back;
                $out = [strtr($t['access'], ['{panel}' => $name, '{levels}' => $lvOn ? implode('، ', $lvOn) : $t['levelsNone'], '{langs}' => implode(' · ', $lgOn)]), cvol_kb($kb)];
                break;
            case 'sp':
                // a volume's or a day count's −/+ steps: change, delete, add
                $k = $a === 'd' ? 'd' : 'v';
                $sec = $k === 'v' ? 'vol' : 'days';
                $steps = $c[$sec]['steps'];
                $unitW = $k === 'v' ? $t['unitGbW'] : $t['unitDayW'];
                $mid = ($k === 'v' ? '📦 ' : '📅 ') . $c[$sec]['start'] . ' ' . $unitW;
                $minus = array_map(function ($x) {
                    return '−' . $x;
                }, array_reverse($steps));
                $plus = array_map(function ($x) {
                    return '+' . $x;
                }, $steps);
                $prev = !$steps ? $t['stepsNone'] : (count($steps) <= 2 ? implode(' | ', array_merge($minus, [$mid], $plus)) : $mid . "\n" . implode(' | ', array_merge($minus, $plus)));
                $kb = [];
                foreach ($steps as $i => $x) {
                    $kb[] = [cvol_b(strtr($t['stepEditBtn'], ['{v}' => $x]), $h . "in|{$k}sedit|{$i}"), cvol_b(strtr($t['stepDelBtn'], ['{v}' => $x]), $h . "sd|{$k}|{$i}", 'danger')];
                }
                if (count($steps) < 3) {
                    $kb[] = [cvol_b($t['stepAddBtn'], $h . "in|{$k}sadd", 'success')];
                }
                $kb[] = [cvol_b($t['btnBack'], $h . ($k === 'v' ? 'vo' : 'dy'), 'danger')];
                $out = [strtr($t['steps'], ['{panel}' => $name, '{what}' => $k === 'v' ? $t['stepsWhatVol'] : $t['stepsWhatDays'], '{unit}' => $unitW, '{list}' => $steps ? cvol_steps_text($steps) : $t['none'], '{preview}' => $prev]), cvol_kb($kb)];
                break;
            case 'st':
                $s = cvol_stats($panel);
                $sum = [];
                foreach ($s['sum'] as $cur => $v) {
                    $sum[] = money($v, $cur);
                }
                $top = [];
                foreach ($s['top'] as $i => $r) {
                    $top[] = strtr($t['topRow'], ['{i}' => $i + 1, '{gb}' => cvol_num($r['v']), '{days}' => (int) $r['d'], '{c}' => (int) $r['c']]);
                }
                $out = [strtr($t['stats'], ['{panel}' => $name, '{n}' => $s['n'], '{n30}' => $s['n30'], '{sum}' => $sum ? implode(' · ', $sum) : '0', '{top}' => $top ? implode("\n", $top) : $t['statsEmpty']]), cvol_kb([$back])];
                break;
            case 'hl':
                $out = [$t['help'], cvol_kb([$back])];
                break;
        }
        if ($out === null) {
            return cvol_hub($panel, $note);
        }
        if ($note !== '') {
            $out[0] = $note . "\n\n" . $out[0];
        }
        return $out;
    }

    // the question a field asks, and the screen it goes back to
    function cvol_field_prompt(array $panel, $f, $a = '', $b = '')
    {
        $t = cvol_atx();
        $c = cvol_cfg($panel);
        $pc = (string) $panel['code_panel'];
        $screen = in_array($f, ['vmin', 'vmax', 'vstart', 'vfixed', 'vchips'], true) ? 'vo'
            : (in_array($f, ['dmin', 'dmax', 'dstart', 'dfixed', 'dlist'], true) ? 'dy'
                : (in_array($f, ['vsadd', 'vsedit'], true) ? 'sp|v' : (in_array($f, ['dsadd', 'dsedit'], true) ? 'sp|d'
                    : ($f === 'gb' || $f === 'day' ? 'pr|' . $a : ($f === 'minbal' ? 'mb|' . $a : ($f === 'tier' ? 'ti' : 'sa'))))));
        $now = ['vmin' => $c['vol']['min'], 'vmax' => $c['vol']['max'], 'vstart' => $c['vol']['start'], 'vfixed' => $c['vol']['fixed'],
            'dmin' => $c['days']['min'], 'dmax' => $c['days']['max'], 'dstart' => $c['days']['start'], 'dfixed' => $c['days']['fixed']];
        if (isset($now[$f])) {
            $what = $t['f_' . $f];
            $hint = strtr($t['hintNumber'], ['{now}' => $now[$f]]);
        } elseif (in_array($f, ['vsadd', 'vsedit', 'dsadd', 'dsedit'], true)) {
            $what = $t['f_' . $f];
            $hint = $t['hintStepNew'];
            if ($f === 'vsedit' || $f === 'dsedit') {
                $old = $c[$f[0] === 'v' ? 'vol' : 'days']['steps'][(int) $a] ?? null;
                $hint = ($old !== null ? strtr($t['hintStepNow'], ['{old}' => $old]) . ' ' : '') . $hint;
            }
        } elseif ($f === 'vchips' || $f === 'dlist') {
            $what = $t['f_' . $f];
            $hint = strtr($t['hintList'], ['{example}' => $f === 'vchips' ? '10 20 50 100' : '30 60 90']);
        } elseif ($f === 'gb' || $f === 'day') {
            $cur = currency_for_lang($a);
            $what = strtr($t['f_' . $f], ['{level}' => $t['lvl_' . $b] ?? $b, '{lang}' => cvol_lang_label($a), '{cur}' => cvol_cur_label($cur)]);
            $hint = strtr($t['hintPrice'], ['{example}' => ((int) (currency_get($cur)['decimals'] ?? 0)) > 0 ? '0.05' : ($f === 'gb' ? '3000' : '500')]) . ($b !== 'f' ? "\n" . $t['hintPriceLevel'] : '');
        } elseif ($f === 'minbal') {
            $what = strtr($t['f_minbal'], ['{lang}' => cvol_lang_label($a), '{cur}' => cvol_cur_label(currency_for_lang($a))]);
            $hint = $t['hintMinbal'];
        } elseif ($f === 'tier') {
            $what = $t['f_tier'];
            $hint = $t['hintTier'];
        } else {
            $what = $t['f_sale'];
            $hint = $t['hintSale'];
        }
        return [strtr($t['ask'], ['{what}' => $what, '{hint}' => $hint]), cvol_kb([[cvol_b($t['btnBack'], "cvh|{$pc}|" . $screen, 'danger')]]), $screen];
    }

    // A typed value into the config: the new config, or a string saying why not
    function cvol_apply_field(array $c, $f, $a, $b, $raw)
    {
        $t = cvol_atx();
        $raw = trim(strtr((string) $raw, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '٬' => '', ',' => ' ', '،' => ' ']));
        $ints = preg_match_all('/\d+/', $raw, $m) ? array_map('intval', $m[0]) : [];
        $one = (count($ints) === 1 && preg_match('/^\d+$/', $raw)) ? $ints[0] : null;
        $map = ['vmin' => ['vol', 'min'], 'vmax' => ['vol', 'max'], 'vstart' => ['vol', 'start'], 'vfixed' => ['vol', 'fixed'],
            'dmin' => ['days', 'min'], 'dmax' => ['days', 'max'], 'dstart' => ['days', 'start'], 'dfixed' => ['days', 'fixed']];
        // a step: one more, or one changed (its place in the list is $a)
        if (in_array($f, ['vsadd', 'vsedit', 'dsadd', 'dsedit'], true)) {
            $sec = $f[0] === 'v' ? 'vol' : 'days';
            if ($one === null || $one < 1 || $one > ($sec === 'vol' ? 100000 : 3650)) {
                return $t['bad'];
            }
            $steps = $c[$sec]['steps'];
            if (substr($f, 2) === 'edit') {
                if (!isset($steps[(int) $a])) {
                    return $t['bad'];
                }
                unset($steps[(int) $a]);
            } elseif (count($steps) >= 3) {
                return $t['stepsFull'];
            }
            if (in_array($one, $steps, true)) {
                return $t['stepDup'];
            }
            $steps[] = $one;
            sort($steps);
            $c[$sec]['steps'] = array_values($steps);
            return $c;
        }
        if (isset($map[$f])) {
            if ($one === null || $one < 1) {
                return $t['bad'];
            }
            [$sec, $k] = $map[$f];
            $c[$sec][$k] = $one;
            // a minimum above the maximum takes the maximum along
            if ($k === 'min' && $c[$sec]['max'] < $one) {
                $c[$sec]['max'] = $one;
            }
            if ($k === 'max' && $c[$sec]['min'] > $one) {
                return strtr($t['badMaxMin'], ['{min}' => $c[$sec]['min']]);
            }
            return $c;
        }
        if ($f === 'vchips' || $f === 'dlist') {
            $list = ($raw === '0') ? [] : array_values(array_filter($ints, fn($x) => $x > 0));
            if ($raw !== '0' && !$list) {
                return $t['bad'];
            }
            if ($f === 'vchips') {
                $c['vol']['chips'] = $list;
            } else {
                $c['days']['list'] = $list;
            }
            return $c;
        }
        if ($f === 'gb' || $f === 'day') {
            if (!in_array($a, panel_langs(), true) || !in_array($b, cvol_levels(), true)) {
                return $t['bad'];
            }
            if ($raw === '-' && $b !== 'f') {
                $c['langs'][$a][$f][$b] = '';
                return $c;
            }
            $v = str_replace(' ', '', $raw);
            if (!preg_match('/^\d+(\.\d{1,4})?$/', $v)) {
                return $t['bad'];
            }
            $c['langs'][$a][$f][$b] = $v;
            // a GB and a day both at zero would be a free plan - never sold
            $rg = cvol_rate($c, $a, 'gb', $b);
            $rd = cvol_rate($c, $a, 'day', $b);
            if ($rg !== null && $rd !== null && $rg <= 0 && $rd <= 0) {
                return $t['freeBlocked'];
            }
            return $c;
        }
        if ($f === 'minbal') {
            $v = str_replace(' ', '', $raw);
            if (!in_array($a, panel_langs(), true) || !preg_match('/^\d+(\.\d{1,4})?$/', $v)) {
                return $t['bad'];
            }
            $c['langs'][$a]['minbal'] = (float) $v;
            return $c;
        }
        if ($f === 'tier') {
            if (count($ints) !== 2 || $ints[0] < 1 || $ints[1] < 1 || $ints[1] > 90) {
                return $t['bad'];
            }
            if (count($c['tiers']) >= 5) {
                return $t['tierFull'];
            }
            $c['tiers'][] = [$ints[0], $ints[1]];
            return $c;
        }
        if ($f === 'sale') {
            if (count($ints) < 1 || count($ints) > 2 || $ints[0] < 1 || $ints[0] > 90) {
                return $t['bad'];
            }
            $days = $ints[1] ?? 0;
            $c['sale'] = ['pct' => $ints[0], 'until' => $days > 0 ? time() + $days * 86400 : 0];
            return $c;
        }
        return $t['bad'];
    }

    // 🏬 تنظیمات فروشگاه's way in: every panel with its state
    function cvol_shop_list()
    {
        global $pdo;
        $t = cvol_atx();
        $rows = [];
        foreach ($pdo->query("SELECT * FROM marzban_panel ORDER BY name_panel")->fetchAll(PDO::FETCH_ASSOC) as $p) {
            if (($p['type'] ?? '') === 'Manualsale') {
                continue;
            }
            $on = cvol_cfg($p)['on'];
            $rows[] = [cvol_b(strtr($t['shopRow'], ['{state}' => $on ? '✅' : '❌', '{panel}' => (string) $p['name_panel']]), "cvh|{$p['code_panel']}|m|s", $on ? 'success' : 'primary')];
        }
        $rows[] = [cvol_b($t['btnClose'], 'cvl|x', 'danger')];
        return [$t['shopList'] . ($rows ? '' : "\n\n" . $t['noPanels']), cvol_kb($rows)];
    }

    // ✏️ مدیریت پنل ← 💰 قیمت‌گذاری, in glass buttons
    function cvol_pricing_menu(array $panel, array $items)
    {
        $t = cvol_atx();
        $k = panel_texts()['keyboard'];
        $c = cvol_cfg($panel);
        $f = function ($col) use ($panel) {
            $x = json_decode((string) ($panel[$col] ?? ''), true);
            return is_array($x) && isset($x['f']) ? money($x['f']) : '—';
        };
        $text = strtr($t['pricing'], [
            '{panel}' => htmlspecialchars((string) $panel['name_panel']),
            '{cvol}' => ($panel['type'] ?? '') === 'Manualsale' ? '—' : ($c['on'] ? $t['on'] : $t['off']),
            '{xv}' => $f('priceextravolume'),
            '{xt}' => $f('priceextratime'),
            '{cl}' => is_numeric($panel['priceChangeloc'] ?? null) ? money($panel['priceChangeloc']) : '—',
        ]);
        $map = [$t['featureSettingsBtn'] => 'cvh|' . $panel['code_panel'] . '|m|p', $k['extraVolumePrice'] => 'ppr_xv', $k['extraTimePrice'] => 'ppr_xt', $k['changeLocationPrice'] => 'ppr_cl'];
        $rows = [];
        foreach ($items as $label) {
            if (isset($map[$label])) {
                $rows[] = [cvol_b($label, $map[$label], $map[$label] === 'cvh|' . $panel['code_panel'] . '|m|p' ? 'success' : 'primary')];
            }
        }
        $rows[] = [cvol_b($t['btnClose'], 'ppr_x', 'danger')];
        return [$text, cvol_kb($rows)];
    }

    // The admin's taps and typing in the hub. true when handled.
    function cvol_admin_dispatch()
    {
        global $from_id, $datain, $text, $message_id, $user, $callback_query_id, $textbotlang, $adminrulecheck, $panel_menu_flat;
        if (($adminrulecheck['rule'] ?? '') !== 'administrator') {
            return false;
        }
        $t = cvol_atx();
        $data = (string) $datain;
        $answer = function ($msg = '', $alert = false) use ($callback_query_id) {
            if ($callback_query_id) {
                telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => $msg, 'show_alert' => $alert]);
            }
        };
        // 🏬 تنظیمات فروشگاه ← 🧩 حجم دلخواه
        if ($data === '' && $text === $t['btn']) {
            [$x, $k] = cvol_shop_list();
            sendmessage($from_id, $x, $k, 'HTML');
            return true;
        }
        // the item in a panel's own menu: that panel's hub
        if ($data === '' && $text === $t['featureSettingsBtn']) {
            $panel = cvol_panel((string) $user['Processing_value']);
            if (!is_array($panel)) {
                return false;
            }
            cvol_state_put($from_id, 'h', ['o' => 'p', 'pc' => (string) $panel['code_panel']]);
            [$x, $k] = cvol_hub($panel);
            sendmessage($from_id, $x, $k, 'HTML');
            return true;
        }
        if ($data === 'cvl') {
            [$x, $k] = cvol_shop_list();
            Editmessagetext($from_id, $message_id, $x, $k);
            return true;
        }
        if ($data === 'cvl|x' || $data === 'ppr_x') {
            deletemessage($from_id, $message_id);
            return true;
        }
        // a value typed for the hub
        if ($user['step'] === 'cvh_in' && $data === '') {
            $h = cvol_state_get($from_id, 'h');
            $panel = $h ? cvol_panel($h['pc'] ?? '') : null;
            step('home', $from_id);
            if (!is_array($panel) || empty($h['f'])) {
                return false;
            }
            deletemessage($from_id, $message_id);
            $res = cvol_apply_field(cvol_cfg($panel), $h['f'], $h['a'] ?? '', $h['b'] ?? '', (string) $text);
            [$ptext, $pkb, $screen] = cvol_field_prompt($panel, $h['f'], $h['a'] ?? '', $h['b'] ?? '');
            if (is_string($res)) {
                // asked again, with what was wrong on top
                step('cvh_in', $from_id);
                telegram('editMessageText', ['chat_id' => $from_id, 'message_id' => (int) ($h['m'] ?? 0), 'text' => $res . "\n\n" . $ptext, 'reply_markup' => $pkb, 'parse_mode' => 'HTML']);
                return true;
            }
            cvol_save($panel, $res);
            $sp = explode('|', $screen);
            [$x, $k] = cvol_hub_screen(cvol_panel($panel['code_panel']), $sp[0], $sp[1] ?? '', '', $t['saved']);
            unset($h['f'], $h['a'], $h['b']);
            cvol_state_put($from_id, 'h', $h);
            telegram('editMessageText', ['chat_id' => $from_id, 'message_id' => (int) ($h['m'] ?? 0), 'text' => $x, 'reply_markup' => $k, 'parse_mode' => 'HTML']);
            return true;
        }
        // ➕/⏳/🌐 in the glass 💰 قیمت‌گذاری: the panel's own questions, as typed
        if (in_array($data, ['ppr_xv', 'ppr_xt', 'ppr_cl'], true)) {
            $k = panel_texts()['keyboard'];
            $text = ['ppr_xv' => $k['extraVolumePrice'], 'ppr_xt' => $k['extraTimePrice'], 'ppr_cl' => $k['changeLocationPrice']][$data];
            $datain = '';
            $answer();
            return false;
        }
        if (!preg_match('/^cvh\|([^|]+)\|([a-z]+)(?:\|([^|]*))?(?:\|([^|]*))?(?:\|([^|]*))?$/', $data, $m)) {
            return false;
        }
        $panel = cvol_panel($m[1]);
        if (!is_array($panel)) {
            $answer($t['noPanels'], true);
            return true;
        }
        $act = $m[2];
        $a = $m[3] ?? '';
        $b = $m[4] ?? '';
        $c = cvol_cfg($panel);
        $h = cvol_state_get($from_id, 'h') ?? [];
        $h['pc'] = (string) $panel['code_panel'];
        $h['m'] = (int) $message_id;
        if ($user['step'] === 'cvh_in') {
            step('home', $from_id);
        }
        $show = function (array $screen) use ($from_id, $message_id) {
            Editmessagetext($from_id, $message_id, $screen[0], $screen[1]);
        };
        $note = '';
        switch ($act) {
            case 'm':
                if (in_array($a, ['s', 'p', 'f'], true)) {
                    $h['o'] = $a;
                }
                cvol_state_put($from_id, 'h', $h);
                $show(cvol_hub($panel));
                $answer();
                return true;
            case 'bk':
                cvol_state_put($from_id, 'h', null);
                $o = $h['o'] ?? '';
                if ($o === 's') {
                    $show(cvol_shop_list());
                } elseif ($o === 'p') {
                    $flat = $panel_menu_flat[$panel['type'] ?? ''] ?? $panel_menu_flat['marzban'];
                    $items = [];
                    foreach (panel_menu_split($flat, panel_texts()) as $id => $grp) {
                        if ($id === 'pricing') {
                            $items = $grp['items'];
                        }
                    }
                    $show(cvol_pricing_menu($panel, $items));
                } elseif ($o === 'f') {
                    Editmessagetext($from_id, $message_id, panel_feature_caption($panel, panel_texts()), json_encode(['inline_keyboard' => panel_feature_keyboard($panel, panel_texts())]));
                } else {
                    deletemessage($from_id, $message_id);
                }
                $answer();
                return true;
            case 'on':
                if (!$c['on']) {
                    $priced = false;
                    foreach (panel_langs() as $l) {
                        if ($c['langs'][$l]['show'] && cvol_lang_priced($c, $l)) {
                            $priced = true;
                        }
                    }
                    if (!$priced) {
                        $answer($t['cantOn'], true);
                        return true;
                    }
                    if (!array_filter($c['levels'])) {
                        $c['levels']['f'] = true;
                    }
                }
                $c['on'] = !$c['on'];
                cvol_save($panel, $c);
                $answer($c['on'] ? $t['turnedOn'] : $t['turnedOff']);
                $show(cvol_hub($panel));
                return true;
            case 'lv':
                if (in_array($a, cvol_levels(), true)) {
                    $c['levels'][$a] = !$c['levels'][$a];
                    cvol_save($panel, $c);
                }
                $answer();
                $show(cvol_hub_screen($panel, 'ac'));
                return true;
            case 'nu':
                $c['nudge'] = !$c['nudge'];
                cvol_save($panel, $c);
                $answer();
                $show($a === 'fl' ? cvol_hub_screen($panel, 'fl') : cvol_hub($panel));
                return true;
            case 'sd':
                // 🗑 one of the −/+ steps
                $sec = $a === 'd' ? 'days' : 'vol';
                if (isset($c[$sec]['steps'][(int) $b])) {
                    array_splice($c[$sec]['steps'], (int) $b, 1);
                    cvol_save($panel, $c);
                }
                $answer($t['saved']);
                $show(cvol_hub_screen($panel, 'sp', $a === 'd' ? 'd' : 'v'));
                return true;
            case 'pv':
                // 👁 a message of its own: the hub stays where it is
                [$x, $k] = cvol_preview($panel, panel_langs()[0], 'f');
                sendmessage($from_id, $x, $k, 'HTML');
                $answer();
                return true;
            case 'pw':
                [$x, $k] = cvol_preview($panel, $a, $b);
                Editmessagetext($from_id, $message_id, $x, $k);
                $answer();
                return true;
            case 'pvx':
                $answer($t['previewPopup']);
                return true;
            case 'ms':
                if (in_array($a, ['both', 'vol', 'days'], true)) {
                    $c['mode'] = $a;
                    cvol_save($panel, $c);
                }
                $answer($t['saved']);
                $show(cvol_hub_screen($panel, 'mo'));
                return true;
            case 'dp':
                if ($a === 'list' && !$c['days']['list']) {
                    $c['days']['list'] = [30, 60, 90];
                }
                $c['days']['pick'] = $a === 'list' ? 'list' : 'step';
                cvol_save($panel, $c);
                $answer($t['saved']);
                $show(cvol_hub_screen($panel, 'dy'));
                return true;
            case 'rd':
                if (in_array($a, panel_langs(), true) && is_numeric($b)) {
                    $c['langs'][$a]['round'] = max(0, (float) $b);
                    cvol_save($panel, $c);
                }
                $answer($t['saved']);
                $show(cvol_hub_screen($panel, 'mb', $a));
                return true;
            case 'tr':
                if (isset($c['tiers'][(int) $a])) {
                    array_splice($c['tiers'], (int) $a, 1);
                    cvol_save($panel, $c);
                }
                $answer($t['saved']);
                $show(cvol_hub_screen($panel, 'ti'));
                return true;
            case 'sx':
                $c['sale'] = ['pct' => 0, 'until' => 0];
                cvol_save($panel, $c);
                $answer($t['saved']);
                $show(cvol_hub_screen($panel, 'sa'));
                return true;
            case 'lt':
                if (in_array($a, panel_langs(), true)) {
                    $c['langs'][$a]['show'] = !$c['langs'][$a]['show'];
                    cvol_save($panel, $c);
                }
                $answer();
                $show(cvol_hub_screen($panel, 'ac'));
                return true;
            case 'in':
                $h['f'] = $a;
                $h['a'] = $b;
                $h['b'] = $m[5] ?? '';
                cvol_state_put($from_id, 'h', $h);
                [$x, $k] = cvol_field_prompt($panel, $a, $b, $m[5] ?? '');
                Editmessagetext($from_id, $message_id, $x, $k);
                step('cvh_in', $from_id);
                $answer();
                return true;
            default:
                cvol_state_put($from_id, 'h', $h);
                $show(cvol_hub_screen($panel, $act, $a, $b));
                $answer();
                return true;
        }
    }
}
