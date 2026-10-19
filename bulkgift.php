<?php
// 🎁 حجم یا زمان گروهی: some volume or time added at once to a lot of
// subscriptions - a fixed amount, or a percentage of each one's total or of
// what it has left - for the bot's own services only, or for every account
// on the panels. Opened from 🏬 تنظیمات فروشگاه (and 👤 مدیریت کاربر's old
// 🎁 button), and from the web panel's 🎁 page; both start the same run.
//
// A run is one bulk_gift_job, with one bulk_gift_item for every account it
// reaches. cronbot/gift.php works through it a few minutes at a time - right
// after it is started, then by itself until it is done, the 5-minute cron
// behind it - and every item is claimed before its panel is touched. A run
// cut off halfway goes on from where it stopped, and the one account it was
// in the middle of is reported as ❓ نامشخص for the admin to look at: it is
// never given its gift a second time.
//
// ⏱ hourly accounts get nothing - their volume and time are what their
// wallet pays for - and the admin's report lists them; 🧪 test accounts and
// ♾ unlimited ones are left out the same way. An account that has not been
// started yet (on hold) gets its time on its length and stays on hold.
//
// 🔜 «از الان تا چند ساعت دیگه» makes a run a purchase campaign instead: it
// starts empty and, until its time is up, takes in every new service sold in
// the bot - a purchase confirmed later (card-to-card) still counts for a day
// after the end. One campaign and one other run can go on side by side.
//
// Required by function.php, so every entry point (bot, crons, web panel) has it.

if (!function_exists('bulkgift_ensure_schema')) {
    // bulk_gift_job: one row per run - its settings, how far it has got, the
    // admin's progress message. bulk_gift_item: one row per account a run
    // reaches and what became of it. bulk_gift_draft: the settings an admin is
    // putting together in the bot, until they start it.
    function bulkgift_ensure_schema()
    {
        static $done = false;
        global $pdo;
        if ($done || !($pdo instanceof PDO)) {
            return;
        }
        $done = true;
        $pdo->query("CREATE TABLE IF NOT EXISTS bulk_gift_job (
            id INT AUTO_INCREMENT PRIMARY KEY,
            params TEXT NOT NULL,
            state VARCHAR(20) NOT NULL DEFAULT 'queued',
            prepared TINYINT NOT NULL DEFAULT 0,
            prep TEXT NULL,
            cancel_req TINYINT NOT NULL DEFAULT 0,
            origin VARCHAR(10) NOT NULL DEFAULT 'bot',
            created_by VARCHAR(100) NOT NULL DEFAULT '',
            admin_msg BIGINT NOT NULL DEFAULT 0,
            notes TEXT NULL,
            created_at INT NOT NULL DEFAULT 0,
            started_at INT NOT NULL DEFAULT 0,
            finished_at INT NOT NULL DEFAULT 0,
            shown_at INT NOT NULL DEFAULT 0,
            shown_hash VARCHAR(40) NOT NULL DEFAULT '',
            KEY bulk_gift_state (state))
            ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        // shown_hash came after the first version: the progress text last shown,
        // so the same one is not sent again every round
        try {
            $has = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bulk_gift_job' AND COLUMN_NAME = 'shown_hash'")->fetchColumn();
            if (!$has) {
                $pdo->exec("ALTER TABLE bulk_gift_job ADD shown_hash VARCHAR(40) NOT NULL DEFAULT '' AFTER shown_at");
            }
        } catch (Exception $e) {
            error_log('bulk gift shown_hash column: ' . $e->getMessage());
        }
        // ukey: the account (panel + username) - one row per account per run
        $pdo->query("CREATE TABLE IF NOT EXISTS bulk_gift_item (
            id INT AUTO_INCREMENT PRIMARY KEY,
            job_id INT NOT NULL,
            ukey CHAR(40) NOT NULL,
            panel VARCHAR(500) NOT NULL,
            username VARCHAR(300) NOT NULL,
            src VARCHAR(10) NOT NULL DEFAULT 'bot',
            id_invoice VARCHAR(200) NULL,
            id_user VARCHAR(200) NULL,
            start_ts INT NOT NULL DEFAULT 0,
            snap TEXT NULL,
            state VARCHAR(20) NOT NULL DEFAULT 'pending',
            reason VARCHAR(30) NOT NULL DEFAULT '',
            amount VARCHAR(40) NOT NULL DEFAULT '',
            info TEXT NULL,
            updated_at INT NOT NULL DEFAULT 0,
            UNIQUE KEY bulk_gift_one (job_id, ukey),
            KEY bulk_gift_todo (job_id, state))
            ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->query("CREATE TABLE IF NOT EXISTS bulk_gift_draft (
            admin_id VARCHAR(100) NOT NULL PRIMARY KEY,
            params TEXT NOT NULL,
            ask_msg BIGINT NOT NULL DEFAULT 0,
            updated_at INT NOT NULL DEFAULT 0)
            ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
    }
}

if (!function_exists('bulkgift_params')) {
    // the admin's words for all of it: Persian, like the rest of the panel
    function bulkgift_texts()
    {
        return panel_texts()['Admin']['BulkGift'];
    }
    // a service that is still its owner's, the way every other part of the
    // bot counts them
    function bulkgift_live_statuses()
    {
        return ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'];
    }
    // the panels whose own list of accounts can be read, for «همه‌ی کاربران
    // پنل» - on any other a run reaches the bot's services only
    function bulkgift_listable($panel)
    {
        return is_array($panel) && in_array((string) ($panel['type'] ?? ''), ['marzban', 'rebecca'], true);
    }
    // Persian and Arabic digits, as the web panel's form may send them (the
    // bot's own messages arrive with them changed already)
    function bulkgift_digits($s)
    {
        return trim(strtr((string) $s, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '٫' => '.',
        ]));
    }
    // the states an account can be in, in the order the screens show them:
    // 🟢 active, ⏸ not started yet, 📦 volume used up, ⏰ time over, 🚫 disabled
    function bulkgift_statuses()
    {
        return ['active', 'on_hold', 'limited', 'expired', 'disabled'];
    }
    // what is picked when nothing says otherwise: every subscription still
    // going, started or not
    function bulkgift_default_statuses()
    {
        return ['active', 'on_hold'];
    }
    // the longest 🔜 campaign, in hours (30 days)
    function bulkgift_max_next_hours()
    {
        return 720;
    }
    // 🔜 a purchase campaign rather than one run over what is there now
    function bulkgift_is_live(array $p)
    {
        return $p['buy'] === 'next';
    }
    // A run's settings from anything - a draft, a job's row, a web form:
    //   kind      volume | time
    //   mode      fixed (value: GB, or whole days) | total | left (value: percent)
    //   scope     bot (the bot's own services) | panel (every account on the panels)
    //   lang      all | a language - the bot's users of that language (scope bot)
    //   panels    code_panel list, empty = every panel
    //   buy       all | lasth | days | months (bought in the last buyn hours/
    //             days/months) | hours (bought buyn hours ago or earlier) |
    //             next (🔜 every purchase from the start until buyn hours later)
    //   statuses  the account states that get it (bulkgift_statuses())
    //   notify    the owner of each of the bot's services is told
    // A started campaign also keeps its window: win_from / win_to / late_until.
    // A value that does not fit its kind and mode is dropped, not guessed at.
    function bulkgift_params($raw)
    {
        $p = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);
        $o = [];
        $o['kind'] = ($p['kind'] ?? '') === 'time' ? 'time' : 'volume';
        $o['mode'] = in_array($p['mode'] ?? '', ['fixed', 'total', 'left'], true) ? $p['mode'] : 'fixed';
        $v = $p['value'] ?? null;
        $v = is_numeric($v) && (float) $v > 0 ? (float) $v : null;
        if ($v !== null) {
            if ($o['mode'] !== 'fixed') {
                $v = ($v >= 0.1 && $v <= 100) ? round($v, 2) : null;
            } elseif ($o['kind'] === 'time') {
                // days, to the hour: 1/24 is an hour (a run from before hours
                // could be given keeps its whole days)
                $h = bulkgift_hours_of($v);
                $v = ($h >= 1 && $h <= 3650 * 24 && abs($v * 24 - $h) < 0.01) ? ($h % 24 === 0 ? intdiv($h, 24) : round($h / 24, 6)) : null;
            } else {
                $v = $v >= 0.01 ? round($v, 4) : null;
            }
        }
        $o['value'] = $v;
        $o['scope'] = ($p['scope'] ?? '') === 'panel' ? 'panel' : 'bot';
        $o['lang'] = in_array($p['lang'] ?? '', panel_langs(), true) ? $p['lang'] : 'all';
        $o['panels'] = array_values(array_unique(array_filter(array_map('strval', (array) ($p['panels'] ?? [])), 'strlen')));
        $o['buy'] = in_array($p['buy'] ?? '', ['lasth', 'days', 'months', 'hours', 'next'], true) ? $p['buy'] : 'all';
        $o['buyn'] = max(0, min($o['buy'] === 'next' ? bulkgift_max_next_hours() : 100000, (int) ($p['buyn'] ?? 0)));
        if ($o['buy'] === 'all' || $o['buyn'] < 1) {
            $o['buy'] = 'all';
            $o['buyn'] = 0;
        }
        // what is sold is the bot's: a campaign is about the bot's own services
        if ($o['buy'] === 'next') {
            $o['scope'] = 'bot';
        }
        // the old single switch: on = still going, off = everything
        if (array_key_exists('statuses', $p)) {
            $o['statuses'] = array_values(array_intersect(bulkgift_statuses(), array_map('strval', (array) $p['statuses'])));
        } elseif (array_key_exists('active', $p)) {
            $o['statuses'] = $p['active'] ? bulkgift_default_statuses() : bulkgift_statuses();
        } else {
            $o['statuses'] = bulkgift_default_statuses();
        }
        // a campaign's purchases are new - started or waiting to start - and its
        // screen has no states to pick: one left from before must not bite
        if ($o['buy'] === 'next') {
            $o['statuses'] = bulkgift_default_statuses();
        }
        $o['notify'] = array_key_exists('notify', $p) ? (bool) $p['notify'] : true;
        // a job also keeps the names its panels had when it was started
        $o['pnames'] = array_values(array_filter(array_map('strval', (array) ($p['pnames'] ?? [])), 'strlen'));
        foreach (['win_from', 'win_to', 'late_until'] as $k) {
            if (isset($p[$k]) && is_numeric($p[$k])) {
                $o[$k] = (int) $p[$k];
            }
        }
        return $o;
    }
    // The amount someone typed: [value, null] or [null, the text of why not]
    function bulkgift_parse_value($kind, $mode, $text)
    {
        $t = bulkgift_texts();
        $s = strtr(bulkgift_digits($text), [',' => '.', '،' => '.']);
        if ($mode !== 'fixed') {
            $s = trim(preg_replace('/[%٪\s]+$/u', '', $s));
            if (!preg_match('/^\d+(\.\d+)?$/', $s) || (float) $s < 0.1 || (float) $s > 100) {
                return [null, $t['badPercent']];
            }
            return [round((float) $s, 2), null];
        }
        if ($kind === 'volume') {
            // gigabytes, what comes after the point as megabytes the way the
            // owner says it: 5.5 = 5 GB and 500 MB, 0.1 = 100 MB; «500 مگ» too
            if (!preg_match('/^(\d+(?:\.\d+)?)\s*(mb|m|مگ|مگابایت|gb|g|گیگ|گیگابایت)?$/iu', $s, $m)) {
                return [null, $t['badVolume']];
            }
            $unit = mb_strtolower($m[2] ?? '');
            $gb = (float) $m[1] / ($unit !== '' && in_array(mb_substr($unit, 0, 1), ['m', 'م'], true) ? 1000 : 1);
            if ($gb < 0.01 || $gb > 100000) {
                return [null, $t['badVolume']];
            }
            return [round($gb, 3), null];
        }
        // days, and after the point hours: 1 = a day, 0.1 = an hour, 2.12 = 2
        // days and 12 hours; «12 ساعت» and «3 روز» too
        if (preg_match('/^(\d+)\s*(ساعت|h|hours?)$/iu', $s, $m)) {
            $h = (int) $m[1];
        } elseif (preg_match('/^(\d+)\s*(روز|d|days?)$/iu', $s, $m)) {
            $h = (int) $m[1] * 24;
        } elseif (preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $s, $m) && (int) ($m[2] ?? 0) <= 23) {
            $h = (int) $m[1] * 24 + (int) ($m[2] ?? 0);
        } else {
            return [null, $t['badDays']];
        }
        if ($h < 1 || $h > 3650 * 24) {
            return [null, $t['badDays']];
        }
        return [$h % 24 === 0 ? intdiv($h, 24) : round($h / 24, 6), null];
    }
    // a time gift's days (as kept) in whole hours
    function bulkgift_hours_of($days)
    {
        return (int) round((float) $days * 24);
    }
    // «چند روز/ماه/ساعت» for the purchase filter: a whole number, or null
    function bulkgift_parse_count($text)
    {
        $s = bulkgift_digits($text);
        if (!preg_match('/^\d+$/', $s) || (int) $s < 1 || (int) $s > 100000) {
            return null;
        }
        return (int) $s;
    }
    // [from, to]: the unix times a purchase has to fall between (null = open)
    function bulkgift_buy_window(array $p, $now = null)
    {
        $now = $now ?? time();
        $n = (int) $p['buyn'];
        if ($p['buy'] === 'next') {
            // a campaign: from its start, up to now, never past its end
            $from = (int) ($p['win_from'] ?? $now);
            return [$from, min($now, (int) ($p['win_to'] ?? ($from + $n * 3600)))];
        }
        if ($p['buy'] === 'lasth') {
            return [$now - $n * 3600, null];
        }
        if ($p['buy'] === 'days') {
            return [$now - $n * 86400, null];
        }
        if ($p['buy'] === 'months') {
            return [(int) strtotime("-{$n} months", $now), null];
        }
        if ($p['buy'] === 'hours') {
            return [null, $now - $n * 3600];
        }
        return [null, null];
    }
}

if (!function_exists('bulkgift_all_panels')) {
    // every panel the bot has, in its own order
    function bulkgift_all_panels()
    {
        global $pdo;
        return $pdo->query("SELECT * FROM marzban_panel ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    }
    // the panels a run is for: the ones picked, or all of them
    function bulkgift_panels_of(array $p)
    {
        $all = bulkgift_all_panels();
        if (!$p['panels']) {
            return $all;
        }
        return array_values(array_filter($all, fn($r) => in_array((string) $r['code_panel'], $p['panels'], true)));
    }
    // ⏱ / 🧪 / anything else, for one of the bot's services
    function bulkgift_kind_of(array $inv)
    {
        $k = subapp_kind(['payg' => $inv['payg'] ?? 0, 'name_product' => $inv['name_product'] ?? '']);
        return $k === 'h' ? 'payg' : ($k === 't' ? 'test' : 'ok');
    }
    // 🔜 was this service sold? Not a 🧪 test, a 🎁 referral reward or a
    // panel account an admin handed to a user (🔗 واگذاری) - ⏱ is a sale, and
    // is left out and listed like everywhere else
    function bulkgift_is_sale(array $inv)
    {
        if (bulkgift_kind_of($inv) === 'test' || (int) ($inv['affrw'] ?? 0) === 1) {
            return false;
        }
        foreach (panel_langs() as $l) {
            if ((string) ($inv['name_product'] ?? '') === (string) (lang_tab_texts($l)['users']['status']['assignedServiceName'] ?? "\0")) {
                return false;
            }
        }
        return true;
    }
    // the bot's own services a run is for, as SQL over invoice i
    // ($names: the panels', always given - a service on a panel the bot no
    // longer has is nobody's to give to)
    function bulkgift_invoice_where(array $p, array $names, $now = null)
    {
        $w = ["i.Status IN ('" . implode("', '", bulkgift_live_statuses()) . "')"];
        $a = [];
        $w[] = 'i.Service_location IN (' . implode(', ', array_fill(0, max(1, count($names)), '?')) . ')';
        $a = array_merge($a, $names ?: ['']);
        if ($p['scope'] === 'bot' && $p['lang'] !== 'all') {
            [$lw, $lp] = user_lang_where($p['lang'], 'u');
            $w[] = "i.id_user IN (SELECT u.id FROM user u WHERE {$lw})";
            $a = array_merge($a, $lp);
        }
        [$from, $to] = bulkgift_buy_window($p, $now);
        if ($from !== null || $to !== null) {
            $w[] = "i.time_sell REGEXP '^[0-9]+$'";
            if ($from !== null) {
                $w[] = 'CAST(i.time_sell AS UNSIGNED) >= ?';
                $a[] = $from;
            }
            if ($to !== null) {
                $w[] = 'CAST(i.time_sell AS UNSIGNED) <= ?';
                $a[] = $to;
            }
        }
        return [implode(' AND ', $w), $a];
    }
    // What a run with these settings finds among the bot's own services, before
    // it is started: 'n' about how many get it, 'h' ⏱ and 't' 🧪 left out, and for «همه‌ی
    // کاربران پنل» the panels whose own list cannot be read ('unsupported') and
    // whether any can ('listable'). One account counts once.
    function bulkgift_preview(array $p)
    {
        global $pdo;
        if (function_exists('payg_ensure_schema')) {
            payg_ensure_schema();
        }
        $panels = bulkgift_panels_of($p);
        $names = array_map(fn($r) => (string) $r['name_panel'], $panels);
        $out = ['n' => 0, 'h' => 0, 't' => 0, 'unsupported' => [], 'listable' => false, 'panels' => count($panels)];
        // a campaign's purchases have not been made yet
        if ($names && !bulkgift_is_live($p)) {
            [$w, $a] = bulkgift_invoice_where($p, $names);
            $st = $pdo->prepare("SELECT i.Service_location, i.username, i.payg, i.name_product, i.Status FROM invoice i WHERE {$w}");
            $st->execute($a);
            $seen = [];
            // 'n' is about how many will get it: the state the bot last saw of
            // each (the run reads the panel itself, so the report has the
            // exact number)
            $state = ['active' => 'active', 'sendedwarn' => 'active', 'send_on_hold' => 'on_hold', 'end_of_volume' => 'limited', 'end_of_time' => 'expired'];
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $key = bulkgift_ukey($r['Service_location'], $r['username']);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $k = bulkgift_kind_of($r);
                if ($k === 'payg' || $k === 'test') {
                    $out[$k === 'payg' ? 'h' : 't']++;
                } elseif (in_array($state[(string) $r['Status']] ?? 'other', $p['statuses'], true)) {
                    $out['n']++;
                }
            }
        }
        if ($p['scope'] === 'panel') {
            foreach ($panels as $r) {
                if (bulkgift_listable($r)) {
                    $out['listable'] = true;
                } else {
                    $out['unsupported'][] = (string) $r['name_panel'];
                }
            }
        }
        return $out;
    }
    function bulkgift_ukey($panel, $username)
    {
        return sha1((string) $panel . "\n" . strtolower((string) $username));
    }
}

if (!function_exists('bulkgift_amount_label')) {
    // a number without the zeros after it: 1.5, 10, 0.25
    function bulkgift_num($n)
    {
        $s = rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
        return $s === '' ? '0' : $s;
    }
    function bulkgift_lang_name($lang)
    {
        return lang_tab_texts('fa')['bottext']['langs'][$lang] ?? $lang;
    }
    // «5 گیگ و 500 مگابایت», «2 روز و 12 ساعت», «10٪ از حجم باقی‌مونده»,
    // «— (هنوز تعیین نشده)»
    function bulkgift_amount_label(array $p)
    {
        $t = bulkgift_texts();
        if ($p['value'] === null) {
            return $t['valueUnset'];
        }
        if ($p['mode'] === 'fixed') {
            return $p['kind'] === 'time' ? bulkgift_hours_text(bulkgift_hours_of($p['value'])) : bulkgift_gb_text($p['value']);
        }
        $key = ($p['kind'] === 'time' ? 'time' : 'vol') . ucfirst($p['mode']);
        return strtr($t[$key], ['{n}' => bulkgift_num($p['value'])]);
    }
    // «5 گیگ حجم», «2 روز زمان», «10٪ از حجم باقی‌مونده»: how much of what
    function bulkgift_what_text(array $p)
    {
        return bulkgift_amount_label($p) . ($p['mode'] === 'fixed' && $p['value'] !== null ? ' ' . bulkgift_texts()['kindName' . ucfirst($p['kind'])] : '');
    }
    // «5 گیگ», «5 گیگ و 500 مگابایت», «100 مگابایت»: what comes after the
    // point as megabytes, the way it is typed (5.5 = 5 GB and 500 MB)
    function bulkgift_gb_text($gb, $lang = 'fa')
    {
        $u = lang_tab_texts($lang)['common']['units'] ?? [];
        $mb = (int) round((float) $gb * 1000);
        $parts = [];
        if (intdiv($mb, 1000) > 0) {
            $parts[] = intdiv($mb, 1000) . ' ' . ($u['gb'] ?? 'GB');
        }
        if ($mb % 1000 > 0 || !$parts) {
            $parts[] = ($mb % 1000) . ' ' . ($u['mbShort'] ?? 'MB');
        }
        return implode($u['andJoin'] ?? ' ', $parts);
    }
    // «5 روز», «1 روز و 6 ساعت», «6 ساعت» - in $lang's words
    function bulkgift_hours_text($hours, $lang = 'fa')
    {
        $u = lang_tab_texts($lang)['common']['units'] ?? [];
        $h = max(0, (int) $hours);
        $parts = [];
        if (intdiv($h, 24) > 0) {
            $d = intdiv($h, 24);
            $parts[] = $d . ' ' . ($d === 1 ? ($u['dayOne'] ?? 'day') : ($u['dayMany'] ?? 'days'));
        }
        if ($h % 24 > 0 || !$parts) {
            $parts[] = ($h % 24) . ' ' . ($h % 24 === 1 ? ($u['hourOne'] ?? 'hour') : ($u['hourMany'] ?? 'hours'));
        }
        return implode($u['andJoin'] ?? ' ', $parts);
    }
    // the panels by name (a job's: the names they had when it started)
    function bulkgift_panels_label(array $p)
    {
        $t = bulkgift_texts();
        if (!$p['panels']) {
            return $t['panelsAllShort'];
        }
        $names = $p['pnames'] ?: array_map(fn($r) => (string) $r['name_panel'], bulkgift_panels_of($p));
        if (!$names) {
            return $t['panelsAllShort'];
        }
        return implode('، ', array_map(fn($n) => aff_bidi($n, htmlspecialchars($n)), $names));
    }
    // a moment the admin reads: the Jalali date and time, as the bot's reports
    // write them
    function bulkgift_when($ts)
    {
        if (!function_exists('jdate')) {
            require_once __DIR__ . '/jdf.php';
        }
        return jdate('Y/m/d H:i', (int) $ts);
    }
    function bulkgift_buy_label(array $p)
    {
        $t = bulkgift_texts();
        if ($p['buy'] === 'all') {
            return $t['buyAllShort'];
        }
        // a started campaign: its own window
        if ($p['buy'] === 'next' && isset($p['win_from'], $p['win_to'])) {
            return strtr($t['buyNextRun'], ['{from}' => bulkgift_when($p['win_from']), '{to}' => bulkgift_when($p['win_to'])]);
        }
        return strtr($t['buy' . ucfirst($p['buy'])], ['{n}' => number_format($p['buyn'])]);
    }
    // «🟢 فعال، ⏸ شروع‌نشده» - or «همه» when every state is picked
    function bulkgift_status_label(array $p)
    {
        $t = bulkgift_texts();
        if (count($p['statuses']) === count(bulkgift_statuses())) {
            return $t['statusAllShort'];
        }
        if (!$p['statuses']) {
            return $t['statusNone'];
        }
        return implode('، ', array_map(fn($s) => $t['st_' . $s], $p['statuses']));
    }
    // «📦 5 گیگ», «⏰ 10٪ از زمان باقی‌مونده», «📦 حجم — مقدارش هنوز تعیین نشده»
    function bulkgift_what_label(array $p)
    {
        $t = bulkgift_texts();
        $kind = $p['kind'] === 'time' ? $t['kindTime'] : $t['kindVolume'];
        if ($p['value'] === null) {
            return strtr($t['whatUnset'], ['{kind}' => $kind]);
        }
        return ($p['kind'] === 'time' ? '⏰ ' : '📦 ') . bulkgift_amount_label($p);
    }
    // «🤖 سرویس‌های ربات · 🇬🇧 English», «🌐 همه‌ی حساب‌های پنل», «🛒 هر کی از ربات بخره»
    function bulkgift_who_label(array $p)
    {
        $t = bulkgift_texts();
        if ($p['scope'] === 'panel') {
            return $t['whoPanel'];
        }
        $who = bulkgift_is_live($p) ? $t['whoBuyers'] : $t['whoBot'];
        return $p['lang'] === 'all' ? $who : $who . ' · ' . bulkgift_lang_name($p['lang']);
    }
    // 🔜 how long a campaign takes purchases: from its start, or its window once started
    function bulkgift_camp_label(array $p)
    {
        $t = bulkgift_texts();
        if (isset($p['win_from'], $p['win_to'])) {
            return strtr($t['campRun'], ['{from}' => bulkgift_when($p['win_from']), '{to}' => bulkgift_when($p['win_to'])]);
        }
        return strtr($t['campFor'], ['{n}' => number_format($p['buyn'])]);
    }
    // ▫️ one line for each part of the settings, the way the screen groups them
    function bulkgift_summary_rows(array $p)
    {
        $t = bulkgift_texts();
        $live = bulkgift_is_live($p);
        $rows = [
            strtr($t['rowJob'], ['{v}' => $live ? $t['jobCamp'] : $t['jobNow']]),
            strtr($t['rowWhat'], ['{v}' => bulkgift_what_label($p)]),
            strtr($t['rowWho'], ['{v}' => bulkgift_who_label($p)]),
            strtr($t['rowPanels'], ['{v}' => bulkgift_panels_label($p)]),
            $live ? strtr($t['rowCamp'], ['{v}' => bulkgift_camp_label($p)]) : strtr($t['rowBuy'], ['{v}' => bulkgift_buy_label($p)]),
        ];
        // a campaign's purchases are new: started or waiting to start
        if (!$live) {
            $rows[] = strtr($t['rowStatus'], ['{v}' => bulkgift_status_label($p)]);
        }
        $rows[] = strtr($t['rowNotify'], ['{v}' => $p['notify'] ? $t['notifyOn'] : $t['notifyOff']]);
        return implode("\n", $rows);
    }
}

if (!function_exists('bulkgift_job')) {
    function bulkgift_job($id)
    {
        global $pdo;
        bulkgift_ensure_schema();
        $st = $pdo->prepare("SELECT * FROM bulk_gift_job WHERE id = ?");
        $st->execute([(int) $id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }
    // the runs going on now: never more than one ordinary run and one 🔜
    // campaign
    function bulkgift_active_jobs()
    {
        global $pdo;
        bulkgift_ensure_schema();
        return $pdo->query("SELECT * FROM bulk_gift_job WHERE state IN ('queued', 'running') ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    }
    // the one going on now ($live: true = the campaign, false = the other,
    // null = whichever started first), or null
    function bulkgift_active_job($live = null)
    {
        foreach (bulkgift_active_jobs() as $j) {
            if ($live === null || bulkgift_is_live(bulkgift_job_params($j)) === $live) {
                return $j;
            }
        }
        return null;
    }
    // 🔜 ended campaigns still waiting for purchases of their time that are
    // confirmed late (card-to-card) - they get their gift without a report
    function bulkgift_late_jobs()
    {
        global $pdo;
        bulkgift_ensure_schema();
        $out = [];
        foreach ($pdo->query("SELECT * FROM bulk_gift_job WHERE state = 'done' AND finished_at > " . (time() - 3 * 86400) . " ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $j) {
            $p = bulkgift_job_params($j);
            if (bulkgift_is_live($p) && (int) ($p['late_until'] ?? 0) > time()) {
                $out[] = $j;
            }
        }
        return $out;
    }
    function bulkgift_recent($limit = 10)
    {
        global $pdo;
        bulkgift_ensure_schema();
        return $pdo->query("SELECT * FROM bulk_gift_job ORDER BY id DESC LIMIT " . max(1, (int) $limit))->fetchAll(PDO::FETCH_ASSOC);
    }
    function bulkgift_job_params(array $job)
    {
        return bulkgift_params(json_decode((string) $job['params'], true));
    }
    // A new run: ['id' => ...] or ['error' => text]. $createdBy: the Telegram
    // id of the admin who gets its progress and its report.
    function bulkgift_create(array $raw, $createdBy, $origin = 'bot')
    {
        global $pdo;
        bulkgift_ensure_schema();
        $t = bulkgift_texts();
        $p = bulkgift_params($raw);
        if ($p['value'] === null) {
            return ['error' => $t['noValue']];
        }
        if (!$p['statuses']) {
            return ['error' => $t['noStatus']];
        }
        $panels = bulkgift_panels_of($p);
        if (!$panels) {
            return ['error' => $t['noPanels']];
        }
        $p['pnames'] = $p['panels'] ? array_map(fn($r) => (string) $r['name_panel'], $panels) : [];
        $live = bulkgift_is_live($p);
        if ($live) {
            // its window starts now; a purchase of it confirmed later still
            // counts for a day after its end
            $now = time();
            $p['win_from'] = $now;
            $p['win_to'] = $now + (int) $p['buyn'] * 3600;
            $p['late_until'] = $p['win_to'] + 86400;
        }
        // two admins pressing at once must not start two
        $got = (int) $pdo->query("SELECT GET_LOCK('mirza_bulkgift_new', 5)")->fetchColumn();
        try {
            if (bulkgift_active_job($live) !== null) {
                return ['error' => $live ? $t['busyLive'] : $t['busy']];
            }
            // a campaign has nothing to read first: it takes purchases in as they come
            $pdo->prepare("INSERT INTO bulk_gift_job (params, state, prepared, origin, created_by, created_at, started_at) VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([json_encode($p, JSON_UNESCAPED_UNICODE), $live ? 'running' : 'queued', $live ? 1 : 0, $origin === 'web' ? 'web' : 'bot', (string) $createdBy, time(), $live ? time() : 0]);
            return ['id' => (int) $pdo->lastInsertId()];
        } finally {
            if ($got === 1) {
                $pdo->query("SELECT RELEASE_LOCK('mirza_bulkgift_new')");
            }
        }
    }
    // how many accounts of a run are where, and why the left-out ones were
    function bulkgift_counts($jobId)
    {
        global $pdo;
        $c = ['total' => 0, 'pending' => 0, 'claimed' => 0, 'done' => 0, 'skipped' => 0, 'failed' => 0, 'unknown' => 0, 'cancelled' => 0, 'reasons' => []];
        $st = $pdo->prepare("SELECT state, reason, COUNT(*) n FROM bulk_gift_item WHERE job_id = ? GROUP BY state, reason");
        $st->execute([(int) $jobId]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $n = (int) $r['n'];
            $c['total'] += $n;
            if (isset($c[$r['state']]) && $r['state'] !== 'reasons') {
                $c[$r['state']] += $n;
            }
            if ($r['state'] === 'skipped') {
                $c['reasons'][$r['reason']] = ($c['reasons'][$r['reason']] ?? 0) + $n;
            }
        }
        return $c;
    }
    // «🚫 لغو»: what has not been reached is dropped, what was given stays.
    // With nobody working on the run right now it ends here and then; otherwise
    // whoever is ends it after the account they are on. false when there was
    // nothing running to cancel.
    function bulkgift_cancel($jobId)
    {
        global $pdo;
        bulkgift_ensure_schema();
        $job = bulkgift_job($jobId);
        if ($job === null || !in_array($job['state'], ['queued', 'running'], true)) {
            return false;
        }
        $pdo->prepare("UPDATE bulk_gift_job SET cancel_req = 1 WHERE id = ?")->execute([(int) $jobId]);
        $pdo->prepare("UPDATE bulk_gift_item SET state = 'cancelled', reason = 'cancelled', updated_at = ? WHERE job_id = ? AND state = 'pending'")
            ->execute([time(), (int) $jobId]);
        if ((int) $pdo->query("SELECT GET_LOCK('mirza_bulkgift', 0)")->fetchColumn() === 1) {
            try {
                bulkgift_stale_claims($jobId);
                $job = bulkgift_job($jobId);
                if ($job !== null) {
                    bulkgift_finish($job);
                }
            } finally {
                $pdo->query("SELECT RELEASE_LOCK('mirza_bulkgift')");
            }
        }
        return true;
    }
    // An account claimed by a run that never got to the end of it: the run was
    // cut off in the middle - maybe before the panel was asked, maybe after.
    // It is not tried again; the report asks the admin to look at it.
    // (Only called while holding the run's lock, so no run is on it now.)
    function bulkgift_stale_claims($jobId)
    {
        global $pdo;
        $pdo->prepare("UPDATE bulk_gift_item SET state = 'unknown', reason = 'unknown', updated_at = ? WHERE job_id = ? AND state = 'claimed'")
            ->execute([time(), (int) $jobId]);
    }
}

if (!function_exists('bulkgift_prepare')) {
    // accounts into a run, many to a statement; one already in it stays as it is
    function bulkgift_add_items($jobId, array $rows)
    {
        global $pdo;
        foreach (array_chunk($rows, 200) as $chunk) {
            $ph = [];
            $a = [];
            foreach ($chunk as $r) {
                $ph[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
                array_push(
                    $a,
                    (int) $jobId,
                    bulkgift_ukey($r['panel'], $r['username']),
                    (string) $r['panel'],
                    (string) $r['username'],
                    (string) $r['src'],
                    $r['id_invoice'] ?? null,
                    $r['id_user'] ?? null,
                    (int) ($r['start_ts'] ?? 0),
                    $r['snap'] ?? null,
                    (string) ($r['state'] ?? 'pending'),
                    (string) ($r['reason'] ?? ''),
                    time()
                );
            }
            $pdo->prepare("INSERT IGNORE INTO bulk_gift_item (job_id, ukey, panel, username, src, id_invoice, id_user, start_ts, snap, state, reason, updated_at) VALUES " . implode(', ', $ph))
                ->execute($a);
        }
    }
    // a time a panel wrote: a unix time, or a date the panels give in UTC
    // without saying so
    function bulkgift_ts($v)
    {
        if ($v === null || $v === '' || $v === false) {
            return 0;
        }
        if (is_numeric($v)) {
            return (int) $v;
        }
        $s = trim((string) $v);
        if (!preg_match('/(Z|[+-]\d{2}:?\d{2})$/i', $s)) {
            $s .= 'Z';
        }
        $ts = strtotime($s);
        return $ts === false ? 0 : (int) $ts;
    }
    // One page of a panel's own accounts: ['users' => [...], 'total' => n or
    // null] or ['error' => text]. Marzban and Rebecca answer
    // GET /api/users?offset=&limit= with {"users": [...], "total": n}.
    function bulkgift_panel_page(array $panel, $offset, $limit)
    {
        $url = rtrim((string) $panel['url_panel'], '/') . '/api/users?offset=' . (int) $offset . '&limit=' . (int) $limit;
        for ($try = 0; $try < 2; $try++) {
            if ($panel['type'] === 'marzban') {
                // the second time with a new login: the kept one may be stale
                $tok = token_panel($panel['code_panel'], $try === 0);
                if (!is_array($tok) || empty($tok['access_token'])) {
                    $err = is_array($tok) && !empty($tok['error']) ? (string) $tok['error'] : 'login failed';
                    continue;
                }
                $token = (string) $tok['access_token'];
            } else {
                $token = (string) $panel['password_panel'];
            }
            $req = new CurlRequest($url);
            $req->setHeaders(['accept: application/json']);
            $req->setBearerToken($token);
            $req->setTimeout(30000);
            $res = $req->get();
            if (!empty($res['error'])) {
                $err = (string) $res['error'];
                continue;
            }
            $code = (int) ($res['status'] ?? 0);
            $body = json_decode((string) ($res['body'] ?? ''), true);
            if ($code >= 400 || $code === 0) {
                $detail = is_array($body) && isset($body['detail']) ? $body['detail'] : null;
                $err = $detail !== null ? (is_string($detail) ? $detail : json_encode($detail, JSON_UNESCAPED_UNICODE)) : ('HTTP ' . $code);
                continue;
            }
            if (is_array($body) && isset($body['users']) && is_array($body['users'])) {
                return ['users' => $body['users'], 'total' => isset($body['total']) ? (int) $body['total'] : null];
            }
            if (is_array($body) && ($body === [] || isset($body[0]))) {
                return ['users' => $body, 'total' => null];
            }
            $err = 'unexpected answer';
        }
        return ['error' => $err ?? 'unknown error'];
    }
    // One panel's own list, from where an earlier run left off ($prep['off']):
    // the accounts the bot does not have as services go into the run, as they
    // are now ('snap'), with when they were made. false when time ran out
    // before the end of the list.
    function bulkgift_list_panel(array $job, array $p, array $panel, array &$prep, $deadline, array &$notes)
    {
        global $pdo;
        // the bot's own services there came in with the bot's part (or were
        // left out by its filters) - not a second time as accounts of the panel
        $mine = [];
        $st = $pdo->prepare("SELECT username FROM invoice WHERE Service_location = ? AND Status IN ('" . implode("', '", bulkgift_live_statuses()) . "')");
        $st->execute([$panel['name_panel']]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $u) {
            $mine[strtolower((string) $u)] = true;
        }
        [$from, $to] = bulkgift_buy_window($p);
        $limit = 200;
        $off = (int) ($prep['off'] ?? 0);
        while (true) {
            if (time() >= $deadline) {
                return false;
            }
            $page = bulkgift_panel_page($panel, $off, $limit);
            if (isset($page['error'])) {
                $notes[] = ['k' => 'listError', 'panel' => (string) $panel['name_panel'], 'error' => mb_substr($page['error'], 0, 150)];
                return true;
            }
            $rows = [];
            foreach ($page['users'] as $u) {
                $name = is_array($u) ? (string) ($u['username'] ?? '') : '';
                if ($name === '' || isset($mine[strtolower($name)])) {
                    continue;
                }
                $made = bulkgift_ts($u['created_at'] ?? null);
                $row = [
                    'panel' => (string) $panel['name_panel'],
                    'username' => $name,
                    'src' => 'panel',
                    'start_ts' => $made,
                    'snap' => json_encode([
                        's' => (string) ($u['status'] ?? ''),
                        'l' => (float) ($u['data_limit'] ?? 0),
                        'u' => (float) ($u['used_traffic'] ?? 0),
                        'e' => bulkgift_ts($u['expire'] ?? null),
                        // not started yet: how long it will run once it is
                        'h' => (int) ($u['on_hold_expire_duration'] ?? 0),
                    ]),
                ];
                // made in the panel: when it was made is its purchase
                if ($from !== null || $to !== null) {
                    if ($made <= 0) {
                        $row['state'] = 'skipped';
                        $row['reason'] = 'nobuy';
                    } elseif (($from !== null && $made < $from) || ($to !== null && $made > $to)) {
                        continue;
                    }
                }
                $rows[] = $row;
            }
            bulkgift_add_items($job['id'], $rows);
            $n = count($page['users']);
            $off += $n;
            $prep['off'] = $off;
            $pdo->prepare("UPDATE bulk_gift_job SET prep = ? WHERE id = ?")->execute([json_encode($prep), $job['id']]);
            if ($n < $limit || ($page['total'] !== null && $off >= $page['total'])) {
                return true;
            }
        }
    }
    // What a run is about, written down before anything is given: the bot's
    // own services first, then - for «همه‌ی کاربران پنل» - each panel's own
    // list, a page at a time. Where it got to is kept, so a long list can take
    // more than one run. true once all of it is there.
    function bulkgift_prepare(array $job, $deadline)
    {
        global $pdo;
        $p = bulkgift_job_params($job);
        $prep = json_decode((string) ($job['prep'] ?? ''), true) ?: [];
        $notes = json_decode((string) ($job['notes'] ?? ''), true) ?: [];
        $byName = [];
        foreach (bulkgift_all_panels() as $r) {
            $byName[(string) $r['name_panel']] = $r;
        }
        // the panels it was started for, those of them still there
        $names = array_values(array_filter($p['panels'] ? $p['pnames'] : array_keys($byName), fn($n) => isset($byName[$n])));
        if (empty($prep['bot'])) {
            if (function_exists('payg_ensure_schema')) {
                payg_ensure_schema();
            }
            if ($names) {
                [$w, $a] = bulkgift_invoice_where($p, $names);
                $st = $pdo->prepare("SELECT i.id_invoice, i.id_user, i.username, i.Service_location, i.time_sell, i.name_product, i.payg FROM invoice i WHERE {$w} ORDER BY i.id_invoice");
                $st->execute($a);
                $rows = [];
                while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                    $k = bulkgift_kind_of($r);
                    $rows[] = [
                        'panel' => (string) $r['Service_location'],
                        'username' => (string) $r['username'],
                        'src' => 'bot',
                        'id_invoice' => (string) $r['id_invoice'],
                        'id_user' => (string) $r['id_user'],
                        'start_ts' => ctype_digit((string) $r['time_sell']) ? (int) $r['time_sell'] : 0,
                        // ⏱ and 🧪 are left out here and now, and counted
                        'state' => $k === 'ok' ? 'pending' : 'skipped',
                        'reason' => $k === 'ok' ? '' : $k,
                    ];
                    if (count($rows) >= 1000) {
                        bulkgift_add_items($job['id'], $rows);
                        $rows = [];
                    }
                }
                bulkgift_add_items($job['id'], $rows);
            }
            $prep['bot'] = 1;
            $pdo->prepare("UPDATE bulk_gift_job SET prep = ?, started_at = ? WHERE id = ?")->execute([json_encode($prep), time(), $job['id']]);
        }
        if ($p['scope'] === 'panel') {
            for ($i = (int) ($prep['pi'] ?? 0); $i < count($names); $i++) {
                $panel = $byName[$names[$i]];
                if (!bulkgift_listable($panel)) {
                    $notes[] = ['k' => 'unsupported', 'panel' => (string) $panel['name_panel'], 'type' => (string) $panel['type']];
                } elseif (!bulkgift_list_panel($job, $p, $panel, $prep, $deadline, $notes)) {
                    $prep['pi'] = $i;
                    $pdo->prepare("UPDATE bulk_gift_job SET prep = ?, notes = ? WHERE id = ?")->execute([json_encode($prep), json_encode(bulkgift_unique_notes($notes), JSON_UNESCAPED_UNICODE), $job['id']]);
                    return false;
                }
                $prep['pi'] = $i + 1;
                $prep['off'] = 0;
                $pdo->prepare("UPDATE bulk_gift_job SET prep = ?, notes = ? WHERE id = ?")->execute([json_encode($prep), json_encode(bulkgift_unique_notes($notes), JSON_UNESCAPED_UNICODE), $job['id']]);
            }
        }
        $pdo->prepare("UPDATE bulk_gift_job SET prepared = 1, state = 'running', notes = ? WHERE id = ? AND state = 'queued'")
            ->execute([json_encode(bulkgift_unique_notes($notes), JSON_UNESCAPED_UNICODE), $job['id']]);
        return true;
    }
    // 🔜 A campaign's new purchases since it last looked: every service sold
    // in its window that is a service by now - a payment confirmed late (card-
    // to-card) included - with the ⏱ ones left out and listed. One already
    // taken in is not taken again. How many came in.
    function bulkgift_live_pickup(array $job, $now = null)
    {
        global $pdo;
        $p = bulkgift_job_params($job);
        if (function_exists('payg_ensure_schema')) {
            payg_ensure_schema();
        }
        $byName = [];
        foreach (bulkgift_all_panels() as $r) {
            $byName[(string) $r['name_panel']] = $r;
        }
        $names = array_values(array_filter($p['panels'] ? $p['pnames'] : array_keys($byName), fn($n) => isset($byName[$n])));
        if (!$names) {
            return 0;
        }
        [$w, $a] = bulkgift_invoice_where($p, $names, $now);
        $st = $pdo->prepare("SELECT i.* FROM invoice i WHERE {$w} AND NOT EXISTS (SELECT 1 FROM bulk_gift_item b WHERE b.job_id = ? AND b.id_invoice = i.id_invoice) ORDER BY CAST(i.time_sell AS UNSIGNED)");
        $st->execute(array_merge($a, [(int) $job['id']]));
        $rows = [];
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            if (!bulkgift_is_sale($r)) {
                continue;
            }
            $payg = bulkgift_kind_of($r) === 'payg';
            $rows[] = [
                'panel' => (string) $r['Service_location'],
                'username' => (string) $r['username'],
                'src' => 'bot',
                'id_invoice' => (string) $r['id_invoice'],
                'id_user' => (string) $r['id_user'],
                'start_ts' => (int) $r['time_sell'],
                'state' => $payg ? 'skipped' : 'pending',
                'reason' => $payg ? 'payg' : '',
            ];
        }
        bulkgift_add_items($job['id'], $rows);
        return count($rows);
    }
    // a run read again after a cut-off writes its notes again
    function bulkgift_unique_notes(array $notes)
    {
        $out = [];
        foreach ($notes as $n) {
            $out[($n['k'] ?? '') . '|' . ($n['panel'] ?? '')] = $n;
        }
        return array_values($out);
    }
}

if (!function_exists('bulkgift_work')) {
    function bulkgift_manage_panel()
    {
        global $ManagePanel;
        if (!($ManagePanel instanceof ManagePanel)) {
            $ManagePanel = new ManagePanel();
        }
        return $ManagePanel;
    }
    // a panel's error as one line of text
    function bulkgift_msg($m)
    {
        $s = is_scalar($m) ? (string) $m : json_encode($m, JSON_UNESCAPED_UNICODE);
        return mb_substr(trim(strip_tags((string) $s)), 0, 300);
    }
    // The state an account is really in: what its panel says, checked against
    // its own numbers - «active» with its time or its volume gone is over (a
    // panel can be a few minutes behind). One of bulkgift_statuses(), or
    // 'other' for anything a panel calls its own.
    function bulkgift_status(array $d, $now = null)
    {
        $now = $now ?? time();
        $s = strtolower((string) ($d['status'] ?? ''));
        // Marzneshin's own spelling, as DataUser() passes it on
        $s = ['limtied' => 'limited'][$s] ?? $s;
        if ($s === 'active') {
            $expire = (int) ($d['expire'] ?? 0);
            $limit = (float) ($d['data_limit'] ?? 0);
            if ($expire > 0 && $expire <= $now) {
                $s = 'expired';
            } elseif ($limit > 0 && (float) ($d['used_traffic'] ?? 0) >= $limit) {
                $s = 'limited';
            }
        }
        return in_array($s, bulkgift_statuses(), true) ? $s : 'other';
    }
    // How long a not-started account will run once it starts, in seconds, as
    // its panel keeps it - null where the panel does not say, or keeps it in
    // a way the bot cannot change (it is then left out of a time gift).
    function bulkgift_hold_seconds(array $panel, $username)
    {
        $type = (string) ($panel['type'] ?? '');
        if ($type === 'marzban' || $type === 'rebecca') {
            $r = $type === 'marzban' ? getuser($username, $panel['name_panel']) : getuser_rebecca($username, $panel['name_panel']);
            $b = json_decode((string) ($r['body'] ?? ''), true);
            $v = is_array($b) ? ($b['on_hold_expire_duration'] ?? null) : null;
        } elseif ($type === 'marzneshin') {
            $r = getuserm($username, $panel['name_panel']);
            $b = json_decode((string) ($r['body'] ?? ''), true);
            $v = is_array($b) && ($b['expire_strategy'] ?? '') === 'start_on_first_use' ? ($b['usage_duration'] ?? null) : null;
        } elseif ($type === 'x-ui_single') {
            // a negative expiryTime: milliseconds from the first connection
            $r = get_clinets($username, $panel);
            $b = json_decode((string) ($r['body'] ?? ''), true);
            $x = is_array($b) ? ($b['obj']['client']['expiryTime'] ?? null) : null;
            $v = is_numeric($x) && (float) $x < -10000 ? (int) round(-(float) $x / 1000) : null;
        } else {
            return null;
        }
        return is_numeric($v) && (int) $v > 0 ? (int) $v : null;
    }
    // ...and what gives it a new length while it keeps waiting - the very
    // fields the bot makes such an account with
    function bulkgift_hold_config(array $panel, $seconds)
    {
        $s = (int) $seconds;
        switch ((string) ($panel['type'] ?? '')) {
            case 'marzban':
            case 'rebecca':
                return ['on_hold_expire_duration' => $s];
            case 'marzneshin':
                return ['usage_duration' => $s, 'expire_strategy' => 'start_on_first_use'];
            case 'x-ui_single':
                return ['expiryTime' => -$s * 1000];
        }
        return null;
    }
    // What one account gets: ['gb' => x] or ['hours' => n] (+ 'hold' for one
    // not started yet), or ['skip' => why]. $d: what the panel says about it
    // (DataUser's shape, plus 'hold': bulkgift_hold_seconds()); $start: when
    // it was bought (or made, in the panel), 0 when not known.
    function bulkgift_eval(array $p, array $d, $start, $now = null)
    {
        $now = $now ?? time();
        $status = bulkgift_status($d, $now);
        $limit = (float) ($d['data_limit'] ?? 0);
        $used = (float) ($d['used_traffic'] ?? 0);
        $expire = (int) ($d['expire'] ?? 0);
        if (!in_array($status, $p['statuses'], true)) {
            return ['skip' => 'inactive'];
        }
        if ($p['kind'] === 'time') {
            if ($status === 'on_hold') {
                // its clock starts at the first connection: the time goes on its
                // length (all of it is still ahead - total and what is left are
                // the same), and it keeps waiting
                $hold = (int) ($d['hold'] ?? 0);
                if ($hold <= 0) {
                    return ['skip' => 'onhold'];
                }
                $hours = $p['mode'] === 'fixed' ? bulkgift_hours_of($p['value']) : (int) round($hold / 3600 * $p['value'] / 100);
                return $hours < 1 ? ['skip' => 'small'] : ['hours' => $hours, 'hold' => $hold];
            }
            if ($expire <= 0) {
                return ['skip' => 'unlimited'];
            }
            if ($p['mode'] === 'fixed') {
                $hours = bulkgift_hours_of($p['value']);
            } elseif ($p['mode'] === 'total') {
                if ($start <= 0 || $expire <= $start) {
                    return ['skip' => 'nototal'];
                }
                $hours = (int) round(($expire - $start) / 3600 * $p['value'] / 100);
            } else {
                $left = $expire - $now;
                if ($left <= 0) {
                    return ['skip' => 'nothing'];
                }
                $hours = (int) round($left / 3600 * $p['value'] / 100);
            }
            return $hours < 1 ? ['skip' => 'small'] : ['hours' => $hours];
        }
        // no limit to add to - and a volume added to it would set one
        if ($limit <= 0) {
            return ['skip' => 'unlimited'];
        }
        if ($p['mode'] === 'fixed') {
            $gb = (float) $p['value'];
        } elseif ($p['mode'] === 'total') {
            $gb = round($limit / pow(1024, 3) * $p['value'] / 100, 2);
        } else {
            $left = $limit - $used;
            if ($left <= 0) {
                return ['skip' => 'nothing'];
            }
            $gb = round($left / pow(1024, 3) * $p['value'] / 100, 2);
        }
        return $gb < 0.01 ? ['skip' => 'small'] : ['gb' => $gb];
    }
    // One of the bot's services: read from its panel now, then given its gift
    // through the very calls a customer's own «حجم/زمان اضافه» goes through.
    // [state, reason, amount, info]
    function bulkgift_give_bot(array $p, array $item)
    {
        global $pdo;
        $st = $pdo->prepare("SELECT * FROM invoice WHERE id_invoice = ?");
        $st->execute([$item['id_invoice']]);
        $inv = $st->fetch(PDO::FETCH_ASSOC);
        if (!$inv || !in_array((string) $inv['Status'], bulkgift_live_statuses(), true)) {
            return ['skipped', 'missing'];
        }
        // turned into a ⏱ service since the run started
        if (bulkgift_kind_of($inv) === 'payg') {
            return ['skipped', 'payg'];
        }
        $panel = select("marzban_panel", "*", "name_panel", $inv['Service_location'], "select");
        if (!is_array($panel)) {
            return ['failed', 'failed', '', 'panel not found'];
        }
        $mp = bulkgift_manage_panel();
        $d = $mp->DataUser($panel['name_panel'], $inv['username']);
        if (!is_array($d) || ($d['status'] ?? '') === 'Unsuccessful') {
            $msg = is_array($d) ? bulkgift_msg($d['msg'] ?? '') : '';
            return strcasecmp($msg, 'user not found') === 0 ? ['skipped', 'missing'] : ['failed', 'failed', '', $msg];
        }
        if ($p['kind'] === 'time' && bulkgift_status($d) === 'on_hold' && in_array('on_hold', $p['statuses'], true)) {
            $d['hold'] = bulkgift_hold_seconds($panel, $inv['username']) ?? 0;
        }
        $ev = bulkgift_eval($p, $d, (int) $item['start_ts']);
        if (isset($ev['skip'])) {
            return ['skipped', $ev['skip']];
        }
        // these two take whole days only: the hours go to the nearest day
        if (isset($ev['hours']) && in_array((string) $panel['type'], ['hiddify', 'mirza_agent'], true)) {
            $ev['hours'] = (int) round($ev['hours'] / 24) * 24;
            if ($ev['hours'] < 24) {
                return ['skipped', 'small'];
            }
        }
        if (isset($ev['hold'])) {
            // not started yet: extra_time() would start its clock today - the
            // time goes on its length instead, and it keeps waiting
            $res = $mp->Modifyuser($inv['username'], $panel['name_panel'], bulkgift_hold_config($panel, $ev['hold'] + (int) $ev['hours'] * 3600));
            if (!is_array($res) || empty($res['status'])) {
                return ['failed', 'failed', '', bulkgift_msg(is_array($res) ? ($res['msg'] ?? '') : '')];
            }
            $amount = (string) (int) $ev['hours'];
            bulkgift_log_service($inv, $p, $ev, $d);
            if ($p['notify']) {
                bulkgift_notify($inv, $p, $amount);
            }
            return ['done', 'done', $amount, null];
        }
        // extra_volume() and extra_time() mark every invoice of this username
        // active: an old, removed service with the same name would come back
        $st = $pdo->prepare("SELECT id_invoice, Status FROM invoice WHERE username = ? AND id_invoice <> ?");
        $st->execute([$inv['username'], $inv['id_invoice']]);
        $others = $st->fetchAll(PDO::FETCH_ASSOC);
        if ($p['kind'] === 'time') {
            // extra_time() counts in days: an hour is 1/24 of one
            $h = (int) $ev['hours'];
            $res = $mp->extra_time($inv['username'], $panel['code_panel'], $h % 24 === 0 ? intdiv($h, 24) : $h / 24);
        } else {
            $res = $mp->extra_volume($inv['username'], $panel['code_panel'], $ev['gb']);
        }
        $fix = $pdo->prepare("UPDATE invoice SET Status = ? WHERE id_invoice = ?");
        foreach ($others as $o) {
            $fix->execute([$o['Status'], $o['id_invoice']]);
        }
        // still waiting for its first connection: the bot's reminder about that
        // was sent already, and «active» would send it again
        if ((string) $inv['Status'] === 'send_on_hold' && ($d['status'] ?? '') === 'on_hold') {
            $fix->execute(['send_on_hold', $inv['id_invoice']]);
        }
        clearSelectCache('invoice');
        if (!is_array($res) || empty($res['status'])) {
            return ['failed', 'failed', '', bulkgift_msg(is_array($res) ? ($res['msg'] ?? '') : '')];
        }
        $amount = $p['kind'] === 'time' ? (string) (int) $ev['hours'] : volume_store($ev['gb']);
        bulkgift_log_service($inv, $p, $ev, $d);
        if ($p['notify']) {
            bulkgift_notify($inv, $p, $amount);
        }
        return ['done', 'done', $amount, null];
    }
    // An account the bot has no service for: decided on what the panel's list
    // said, read again right before it is changed, and only its volume or its
    // expiry sent - nothing else of it is the bot's to touch.
    function bulkgift_give_panel(array $p, array $item)
    {
        $snap = json_decode((string) ($item['snap'] ?? ''), true) ?: [];
        $pre = bulkgift_eval($p, ['status' => $snap['s'] ?? '', 'data_limit' => $snap['l'] ?? 0, 'used_traffic' => $snap['u'] ?? 0, 'expire' => $snap['e'] ?? 0, 'hold' => $snap['h'] ?? 0], (int) $item['start_ts']);
        if (isset($pre['skip'])) {
            return ['skipped', $pre['skip']];
        }
        $panel = select("marzban_panel", "*", "name_panel", $item['panel'], "select");
        if (!is_array($panel)) {
            return ['failed', 'failed', '', 'panel not found'];
        }
        $mp = bulkgift_manage_panel();
        $d = $mp->DataUser($panel['name_panel'], $item['username']);
        if (!is_array($d) || ($d['status'] ?? '') === 'Unsuccessful') {
            $msg = is_array($d) ? bulkgift_msg($d['msg'] ?? '') : '';
            return strcasecmp($msg, 'user not found') === 0 ? ['skipped', 'missing'] : ['failed', 'failed', '', $msg];
        }
        if ($p['kind'] === 'time' && bulkgift_status($d) === 'on_hold' && in_array('on_hold', $p['statuses'], true)) {
            $d['hold'] = bulkgift_hold_seconds($panel, $item['username']) ?? 0;
        }
        $ev = bulkgift_eval($p, $d, (int) $item['start_ts']);
        if (isset($ev['skip'])) {
            return ['skipped', $ev['skip']];
        }
        if (isset($ev['hold'])) {
            // not started yet: a longer wait-to-start, still waiting
            $cfg = bulkgift_hold_config($panel, $ev['hold'] + (int) $ev['hours'] * 3600);
        } elseif ($p['kind'] === 'time') {
            $cfg = ['expire' => max(time(), (int) $d['expire']) + (int) $ev['hours'] * 3600];
        } else {
            $cfg = ['data_limit' => (int) round((float) $d['data_limit'] + $ev['gb'] * pow(1024, 3))];
        }
        $res = $mp->Modifyuser($item['username'], $panel['name_panel'], $cfg);
        if (!is_array($res) || empty($res['status'])) {
            return ['failed', 'failed', '', bulkgift_msg(is_array($res) ? ($res['msg'] ?? '') : '')];
        }
        return ['done', 'done', $p['kind'] === 'time' ? (string) (int) $ev['hours'] : volume_store($ev['gb']), null];
    }
    // the service's history (👁 of the order in 👤 مدیریت کاربر), as every
    // other change to a service is kept
    function bulkgift_log_service(array $inv, array $p, array $ev, array $d)
    {
        global $pdo;
        $value = $p['kind'] === 'time'
            ? ['time_value' => $ev['hours'] % 24 === 0 ? intdiv((int) $ev['hours'], 24) : round($ev['hours'] / 24, 2), 'time_hours' => (int) $ev['hours'], 'old_volume' => $d['data_limit'] ?? 0, 'expire_old' => $d['expire'] ?? 0]
            : ['volume_value' => $ev['gb'], 'old_volume' => $d['data_limit'] ?? 0, 'expire_old' => $d['expire'] ?? 0];
        try {
            $pdo->prepare("INSERT INTO service_other (id_user, username, value, type, time, price, status, output) VALUES (?, ?, ?, ?, ?, '0', 'paid', ?)")
                ->execute([(string) $inv['id_user'], (string) $inv['username'], json_encode($value), $p['kind'] === 'time' ? 'gift_time' : 'gift_volume', date('Y/m/d H:i:s'), json_encode(['bulk_gift' => true])]);
        } catch (Exception $e) {
            error_log('bulk gift history: ' . $e->getMessage());
        }
    }
    // 🎁 the owner is told, in their own language, with 🎨's text and sticker
    // (👤 مدیریت کاربر ← پیام‌های سرویس‌ها)
    function bulkgift_notify(array $inv, array $p, $amount)
    {
        global $pdo;
        $id = (string) ($inv['id_user'] ?? '');
        if ($id === '' || !function_exists('sendmessage')) {
            return;
        }
        $st = $pdo->prepare("SELECT lang FROM user WHERE id = ?");
        $st->execute([$id]);
        $lang = (string) $st->fetchColumn();
        $lang = in_array($lang, panel_langs(), true) ? $lang : 'fa';
        $key = $p['kind'] === 'time' ? 'bulkGiftTime' : 'bulkGiftVolume';
        $tpl = (string) (lang_tab_texts($lang)['users']['status'][$key] ?? '');
        if ($tpl === '') {
            return;
        }
        bottext_extras_key_hint('users.status.' . $key);
        sendmessage($id, bulkgift_fill($tpl, $p['kind'], $p['kind'] === 'time' ? bulkgift_hours_text((int) $amount, $lang) : volume_num($amount), htmlspecialchars((string) $inv['username'])), null, 'HTML');
    }
    // 🎨's text with the amount in: a time's {amount} says its own unit («2
    // روز و 12 ساعت»), so a text from before hours, with «{amount} روز»
    // after it, does not say it twice
    function bulkgift_fill($tpl, $kind, $amount, $username)
    {
        if ($kind === 'time') {
            $tpl = str_replace(['{amount} روز', '{amount} days', '{amount} day'], '{amount}', (string) $tpl);
        }
        return strtr((string) $tpl, ['{amount}' => $amount, '{username}' => $username]);
    }
    // A run's next accounts, until its time is up: claimed one at a time, then
    // given, left out or failed. How many it got through.
    function bulkgift_work(array $job, $deadline, $maxItems = null)
    {
        global $pdo;
        $p = bulkgift_job_params($job);
        $n = 0;
        $next = $pdo->prepare("SELECT * FROM bulk_gift_item WHERE job_id = ? AND state = 'pending' ORDER BY id LIMIT 1");
        $claim = $pdo->prepare("UPDATE bulk_gift_item SET state = 'claimed', updated_at = ? WHERE id = ? AND state = 'pending'");
        $save = $pdo->prepare("UPDATE bulk_gift_item SET state = ?, reason = ?, amount = ?, info = ?, updated_at = ? WHERE id = ?");
        $stop = $pdo->prepare("SELECT cancel_req FROM bulk_gift_job WHERE id = ?");
        while (time() < $deadline && ($maxItems === null || $n < $maxItems)) {
            $stop->execute([$job['id']]);
            if ((int) $stop->fetchColumn() === 1) {
                break;
            }
            $next->execute([$job['id']]);
            $item = $next->fetch(PDO::FETCH_ASSOC);
            $next->closeCursor();
            if (!$item) {
                break;
            }
            $claim->execute([time(), $item['id']]);
            if ($claim->rowCount() !== 1) {
                continue;
            }
            try {
                $r = $item['src'] === 'panel' ? bulkgift_give_panel($p, $item) : bulkgift_give_bot($p, $item);
            } catch (Throwable $e) {
                error_log('bulk gift item ' . $item['id'] . ': ' . $e->getMessage());
                $r = ['failed', 'failed', '', bulkgift_msg($e->getMessage())];
            }
            $r += [null, '', '', null];
            $save->execute([$r[0], (string) $r[1], (string) $r[2], $r[3] === null ? null : (string) $r[3], time(), $item['id']]);
            $n++;
            bulkgift_progress($job['id']);
        }
        return $n;
    }
}

if (!function_exists('bulkgift_progress_payload')) {
    // ▰▰▰▱▱▱▱▱▱▱
    function bulkgift_bar($pct)
    {
        $full = (int) max(0, min(10, floor($pct / 10)));
        return str_repeat('▰', $full) . str_repeat('▱', 10 - $full);
    }
    // the admin's message while a run goes on, with its 🚫 لغو
    function bulkgift_progress_payload(array $job)
    {
        $t = bulkgift_texts();
        $p = bulkgift_job_params($job);
        $kind = $p['kind'] === 'time' ? $t['kindNameTime'] : $t['kindNameVolume'];
        if (bulkgift_is_live($p)) {
            // 🔜 a campaign: how long it still takes purchases, and what they got
            $c = bulkgift_counts($job['id']);
            $lines = [
                strtr($t['liveTitle'], ['{kind}' => $kind]),
                '➖➖➖➖➖➖➖➖➖➖',
                strtr($t['rowWhat'], ['{v}' => bulkgift_what_label($p)]),
                strtr($t['liveUntil'], ['{to}' => bulkgift_when($p['win_to'] ?? time())]),
                '',
                strtr($t['liveCount'], ['{n}' => number_format($c['done'])]),
                strtr($t['cntSkipped'], ['{n}' => number_format($c['skipped'])]),
                strtr($t['cntFailed'], ['{n}' => number_format($c['failed'] + $c['unknown'])]),
            ];
        } else {
            $lines = [
                strtr($t['progressTitle'], ['{kind}' => $kind]),
                '➖➖➖➖➖➖➖➖➖➖',
                strtr($t['rowWhat'], ['{v}' => bulkgift_what_label($p)]),
                strtr($t['rowWho'], ['{v}' => bulkgift_who_label($p)]),
                '',
            ];
        }
        if (bulkgift_is_live($p)) {
            // nothing more to add
        } elseif (!(int) $job['prepared']) {
            $lines[] = $t['queued'];
        } else {
            $c = bulkgift_counts($job['id']);
            $done = $c['total'] - $c['pending'] - $c['claimed'];
            $pct = $c['total'] > 0 ? (int) floor($done * 100 / $c['total']) : 100;
            $lines[] = strtr($t['progressLine'], ['{done}' => number_format($done), '{total}' => number_format($c['total']), '{bar}' => bulkgift_bar($pct), '{pct}' => $pct]);
            $lines[] = strtr($t['cntDone'], ['{n}' => number_format($c['done'])]);
            $lines[] = strtr($t['cntSkipped'], ['{n}' => number_format($c['skipped'])]);
            $lines[] = strtr($t['cntFailed'], ['{n}' => number_format($c['failed'] + $c['unknown'])]);
        }
        if ($job['origin'] === 'web') {
            $lines[] = '';
            $lines[] = $t['fromWeb'];
        }
        $kb = json_encode(['inline_keyboard' => [[['text' => $t['cancelBtn'], 'callback_data' => 'bgc:' . (int) $job['id'], 'style' => 'danger']]]]);
        return [implode("\n", $lines), $kb];
    }
    // the progress message brought up to date - not more than every 10
    // seconds, unless $force
    function bulkgift_progress($jobId, $force = false)
    {
        global $pdo;
        if (!function_exists('Editmessagetext')) {
            return;
        }
        $job = bulkgift_job($jobId);
        if ($job === null || (int) $job['admin_msg'] <= 0 || (string) $job['created_by'] === '') {
            return;
        }
        if (!$force && time() - (int) $job['shown_at'] < 10) {
            return;
        }
        [$text, $kb] = bulkgift_progress_payload($job);
        // nothing new since the last one: Telegram would refuse it anyway
        $hash = sha1($text . $kb);
        if ($hash === (string) ($job['shown_hash'] ?? '')) {
            return;
        }
        $pdo->prepare("UPDATE bulk_gift_job SET shown_at = ?, shown_hash = ? WHERE id = ?")->execute([time(), $hash, $job['id']]);
        Editmessagetext($job['created_by'], (int) $job['admin_msg'], $text, $kb, 'HTML');
    }
    // the message in the admin's chat that shows the run's progress
    function bulkgift_set_admin_msg($jobId, $msgId)
    {
        global $pdo;
        $pdo->prepare("UPDATE bulk_gift_job SET admin_msg = ?, shown_at = ? WHERE id = ?")->execute([(int) $msgId, time(), (int) $jobId]);
    }
    // a run started on the web gets its progress message in the bot too, the
    // first time a run works on it (one started in the bot has one already)
    function bulkgift_admin_msg($jobId)
    {
        global $pdo;
        $job = bulkgift_job($jobId);
        if ($job === null || (int) $job['admin_msg'] !== 0 || (string) $job['created_by'] === '' || !function_exists('sendmessage')) {
            return;
        }
        [$text, $kb] = bulkgift_progress_payload($job);
        $r = sendmessage($job['created_by'], $text, $kb, 'HTML');
        $id = (int) ($r['result']['message_id'] ?? 0);
        $pdo->prepare("UPDATE bulk_gift_job SET admin_msg = ?, shown_at = ? WHERE id = ?")->execute([$id > 0 ? $id : -1, time(), $job['id']]);
    }
    // «⏱ مدت: 3 دقیقه»
    function bulkgift_duration_text(array $job)
    {
        $t = bulkgift_texts();
        $from = (int) $job['started_at'] ?: (int) $job['created_at'];
        $sec = max(0, (int) $job['finished_at'] - $from);
        return strtr($t['duration'], ['{d}' => $sec < 60 ? $t['durSeconds'] : strtr($t['durMinutes'], ['{n}' => number_format((int) round($sec / 60))])]);
    }
    // one account of a list in the report: ‏• user · panel (: why)
    function bulkgift_item_line(array $r, $withInfo = false)
    {
        $line = "\u{200F}• <code>" . htmlspecialchars((string) $r['username']) . '</code> · ' . aff_bidi((string) $r['panel'], htmlspecialchars((string) $r['panel']));
        if ($withInfo && trim((string) $r['info']) !== '') {
            $line .= ': <i>' . htmlspecialchars(mb_substr((string) $r['info'], 0, 90)) . '</i>';
        }
        return $line;
    }
    // The report the admin gets at the end: the settings, how many went where
    // and why, the ⏱ accounts that were left out, what failed, what is
    // unknown, the notes on panels - kept inside one Telegram message.
    function bulkgift_report(array $job, $listMax = 25)
    {
        global $pdo;
        $t = bulkgift_texts();
        $p = bulkgift_job_params($job);
        $c = bulkgift_counts($job['id']);
        $kind = $p['kind'] === 'time' ? $t['kindNameTime'] : $t['kindNameVolume'];
        $live = bulkgift_is_live($p);
        $title = $live
            ? ($job['state'] === 'cancelled' ? $t['liveCancelledTitle'] : $t['liveDoneTitle'])
            : ($job['state'] === 'cancelled' ? $t['cancelledTitle'] : $t['doneTitle']);
        $parts = [
            strtr($title, ['{kind}' => $kind]) . "\n➖➖➖➖➖➖➖➖➖➖\n" . bulkgift_summary_rows($p),
        ];
        if ($c['total'] === 0) {
            $parts[] = $live ? $t['liveEmpty'] : $t['emptyResult'];
        } else {
            $res = [$t['resultHead']];
            $line = fn($label, $n) => strtr($t['resultLine'], ['{label}' => $label, '{n}' => number_format($n)]);
            $res[] = $line($t['reasonDone'], $c['done']);
            foreach (['payg', 'test', 'inactive', 'onhold', 'unlimited', 'nothing', 'nototal', 'small', 'nobuy', 'missing'] as $why) {
                if (!empty($c['reasons'][$why])) {
                    $res[] = $line($t['reason' . ucfirst($why)], $c['reasons'][$why]);
                }
            }
            foreach (['failed' => 'reasonFailed', 'unknown' => 'reasonUnknown', 'cancelled' => 'reasonCancelled'] as $state => $label) {
                if ($c[$state] > 0) {
                    $res[] = $line($t[$label], $c[$state]);
                }
            }
            $parts[] = implode("\n", $res);
        }
        $lists = [
            ['payg', "state = 'skipped' AND reason = 'payg'", $t['listPayg'], false],
            ['failed', "state = 'failed'", $t['listFailed'], true],
            ['unknown', "state = 'unknown'", $t['listUnknown'], false],
        ];
        foreach ($lists as [$key, $where, $head, $withInfo]) {
            $st = $pdo->prepare("SELECT username, panel, info FROM bulk_gift_item WHERE job_id = ? AND {$where} ORDER BY id LIMIT " . ((int) $listMax + 1));
            $st->execute([$job['id']]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows) {
                continue;
            }
            $all = $key === 'payg' ? ($c['reasons']['payg'] ?? 0) : $c[$key];
            $out = array_map(fn($r) => bulkgift_item_line($r, $withInfo), array_slice($rows, 0, $listMax));
            if ($all > $listMax) {
                $out[] = strtr($t['moreItems'], ['{n}' => number_format($all - $listMax)]);
            }
            $parts[] = $head . "\n<blockquote>" . implode("\n", $out) . '</blockquote>';
        }
        $notes = json_decode((string) ($job['notes'] ?? ''), true) ?: [];
        if ($notes) {
            $out = [];
            foreach (array_slice($notes, 0, 10) as $n) {
                $panel = aff_bidi((string) ($n['panel'] ?? ''), htmlspecialchars((string) ($n['panel'] ?? '')));
                $out[] = ($n['k'] ?? '') === 'unsupported'
                    ? strtr($t['noteUnsupported'], ['{panel}' => $panel, '{type}' => htmlspecialchars((string) ($n['type'] ?? ''))])
                    : strtr($t['noteListError'], ['{panel}' => $panel, '{error}' => htmlspecialchars((string) ($n['error'] ?? ''))]);
            }
            if (count($notes) > 10) {
                $out[] = strtr($t['moreItems'], ['{n}' => number_format(count($notes) - 10)]);
            }
            $parts[] = $t['notesHead'] . "\n<blockquote>" . implode("\n", $out) . '</blockquote>';
        }
        // a campaign that ran its time: purchases of it confirmed late still get theirs
        if ($live && $job['state'] !== 'cancelled' && isset($p['late_until'])) {
            $parts[] = strtr($t['liveLateNote'], ['{until}' => bulkgift_when($p['late_until'])]);
        }
        $tail = bulkgift_duration_text($job) . ($job['origin'] === 'web' ? "\n" . $t['fromWeb'] : '');
        $parts[] = $tail;
        $text = implode("\n\n", $parts);
        // one message holds 4096 letters: shorter lists until it fits
        if (mb_strlen(strip_tags($text)) > 3900 && $listMax > 3) {
            return bulkgift_report($job, (int) floor($listMax / 2));
        }
        return $text;
    }
    // The end of a run - all of it reached, or cancelled: the progress message
    // goes, the report comes. Ends a run once only; false if it had ended.
    function bulkgift_finish(array $job)
    {
        global $pdo;
        $job = bulkgift_job($job['id']);
        if ($job === null) {
            return false;
        }
        $cancelled = (int) $job['cancel_req'] === 1;
        if ($cancelled) {
            $pdo->prepare("UPDATE bulk_gift_item SET state = 'cancelled', reason = 'cancelled', updated_at = ? WHERE job_id = ? AND state = 'pending'")
                ->execute([time(), $job['id']]);
        }
        $st = $pdo->prepare("UPDATE bulk_gift_job SET state = ?, finished_at = ? WHERE id = ? AND state IN ('queued', 'running')");
        $st->execute([$cancelled ? 'cancelled' : 'done', time(), $job['id']]);
        if ($st->rowCount() !== 1) {
            return false;
        }
        $job = bulkgift_job($job['id']);
        $to = (string) $job['created_by'];
        if ($to !== '' && function_exists('sendmessage')) {
            if ((int) $job['admin_msg'] > 0) {
                deletemessage($to, (int) $job['admin_msg']);
            }
            sendmessage($to, bulkgift_report($job), null, 'HTML');
        }
        return true;
    }
}

if (!function_exists('bulkgift_cron')) {
    // How long one run may work: under the cron's 280 seconds, or under PHP's
    // own limit when it cannot be raised
    function bulkgift_budget()
    {
        if (PHP_SAPI === 'cli') {
            return 240;
        }
        @set_time_limit(300);
        $max = (int) ini_get('max_execution_time');
        return ($max <= 0 || $max >= 300) ? 240 : max(5, $max - 15);
    }
    // The next run, now instead of at the next cron: a request to
    // cronbot/gift.php that is not waited for (gift.php keeps going after it
    // is dropped). Only a nudge - the cron gets there anyway if it is lost.
    function bulkgift_kick()
    {
        global $domainhosts;
        if (PHP_SAPI === 'cli' || empty($domainhosts) || !function_exists('curl_init')) {
            return;
        }
        $ch = curl_init('https://' . $domainhosts . '/cronbot/gift.php');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_CONNECTTIMEOUT_MS => 1500,
            CURLOPT_TIMEOUT_MS => 2000,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
    // One run's turn: [what became of it, how many accounts it got through].
    // An ordinary run: 'preparing' | 'more' | 'finished'. A 🔜 campaign: it
    // takes in what was sold since it last looked - 'live' until its time is
    // up and all of that is done, then 'finished'; an ended one still waiting
    // for late payments: 'late'.
    function bulkgift_cron_job(array $job, $deadline, $maxItems = null, $now = null)
    {
        global $pdo;
        $p = bulkgift_job_params($job);
        $live = bulkgift_is_live($p);
        bulkgift_stale_claims($job['id']);
        if ($live && $job['state'] === 'done') {
            bulkgift_live_pickup($job, $now);
            return ['late', bulkgift_work($job, $deadline, $maxItems)];
        }
        if (!$live && !(int) $job['prepared'] && !(int) $job['cancel_req']) {
            bulkgift_admin_msg($job['id']);
            if (!bulkgift_prepare($job, $deadline)) {
                bulkgift_progress($job['id'], true);
                return ['preparing', 0];
            }
        }
        $job = bulkgift_job($job['id']);
        bulkgift_admin_msg($job['id']);
        if ($live && !(int) $job['cancel_req']) {
            bulkgift_live_pickup($job, $now);
        }
        $worked = bulkgift_work($job, $deadline, $maxItems);
        $job = bulkgift_job($job['id']);
        $st = $pdo->prepare("SELECT COUNT(*) FROM bulk_gift_item WHERE job_id = ? AND state = 'pending'");
        $st->execute([$job['id']]);
        $pending = (int) $st->fetchColumn();
        $over = !$live || ($now ?? time()) >= (int) ($p['win_to'] ?? 0);
        if ((int) $job['cancel_req'] === 1 || ($pending === 0 && $over)) {
            bulkgift_finish($job);
            return ['finished', $worked];
        }
        bulkgift_progress($job['id'], true);
        return [$live ? 'live' : 'more', $worked];
    }
    // cronbot/gift.php: works on the runs going on - one worker at a time (a
    // MySQL lock, so the cron and a kick never overlap), for at most $budget
    // seconds ($maxItems a run and $now: for tests). A campaign goes first
    // (it is quick), then the ordinary run, whose rest is handed straight on
    // to the next request. Says what became of the ordinary run, or of the
    // campaign when there is none:
    // 'idle' | 'busy' | 'preparing' | 'more' | 'live' | 'late' | 'finished'
    function bulkgift_cron($budget = null, $maxItems = null, $now = null)
    {
        global $pdo;
        bulkgift_ensure_schema();
        if (!bulkgift_active_jobs() && !bulkgift_late_jobs()) {
            return 'idle';
        }
        if ((int) $pdo->query("SELECT GET_LOCK('mirza_bulkgift', 0)")->fetchColumn() !== 1) {
            return 'busy';
        }
        $result = 'idle';
        $kick = false;
        try {
            $deadline = time() + ($budget ?? bulkgift_budget());
            $jobs = array_merge(bulkgift_active_jobs(), bulkgift_late_jobs());
            usort($jobs, fn($a, $b) => (int) bulkgift_is_live(bulkgift_job_params($b)) <=> (int) bulkgift_is_live(bulkgift_job_params($a)));
            foreach ($jobs as $job) {
                if (time() >= $deadline) {
                    break;
                }
                [$r, $worked] = bulkgift_cron_job($job, $deadline, $maxItems, $now);
                if (!bulkgift_is_live(bulkgift_job_params($job)) || $result === 'idle') {
                    $result = $r;
                }
                if ($r === 'preparing' || ($r === 'more' && $worked > 0)) {
                    $kick = true;
                }
            }
            return $result;
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('mirza_bulkgift')");
            if ($kick) {
                bulkgift_kick();
            }
        }
    }
}

if (!function_exists('bulkgift_hub_payload')) {
    // the settings an admin is putting together in the bot, kept apart from
    // the steps of every other flow
    function bulkgift_draft($adminId)
    {
        global $pdo;
        bulkgift_ensure_schema();
        $st = $pdo->prepare("SELECT params FROM bulk_gift_draft WHERE admin_id = ?");
        $st->execute([(string) $adminId]);
        $raw = $st->fetchColumn();
        return bulkgift_params($raw === false ? [] : json_decode((string) $raw, true));
    }
    function bulkgift_draft_save($adminId, array $p)
    {
        global $pdo;
        bulkgift_ensure_schema();
        $pdo->prepare("INSERT INTO bulk_gift_draft (admin_id, params, updated_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE params = VALUES(params), updated_at = VALUES(updated_at)")
            ->execute([(string) $adminId, json_encode(bulkgift_params($p), JSON_UNESCAPED_UNICODE), time()]);
    }
    // the message a question replaced the screen in, to put the screen back in
    function bulkgift_draft_ask($adminId, $msgId = null)
    {
        global $pdo;
        bulkgift_ensure_schema();
        if ($msgId !== null) {
            $pdo->prepare("UPDATE bulk_gift_draft SET ask_msg = ? WHERE admin_id = ?")->execute([(int) $msgId, (string) $adminId]);
            return (int) $msgId;
        }
        $st = $pdo->prepare("SELECT ask_msg FROM bulk_gift_draft WHERE admin_id = ?");
        $st->execute([(string) $adminId]);
        return (int) $st->fetchColumn();
    }
    // the settings as they stand, short - a start button carries it, so what
    // starts is what the admin last saw
    function bulkgift_sig(array $p)
    {
        unset($p['pnames']);
        return hash('crc32b', json_encode($p));
    }
    // a button; $style null = white (Telegram's own)
    function bulkgift_btn($text, $cb, $style = 'primary')
    {
        $b = ['text' => $text, 'callback_data' => $cb];
        if ($style !== null) {
            $b['style'] = $style;
        }
        return $b;
    }
    // one choice of several: ✅ and green when it is the one
    function bulkgift_pick($on, $text, $cb)
    {
        return bulkgift_btn(($on ? '✅ ' : '') . $text, $cb, $on ? 'success' : 'primary');
    }
    // 🏬 ← 🎁, the first screen: the usual job - something for every active
    // subscription of the bot - is a tap and a number away, a campaign is the
    // third button, 📖 says how it all goes; everything else waits behind
    // «🎯 تنظیمات بیشتر» on the summary
    function bulkgift_start_payload()
    {
        $t = bulkgift_texts();
        $rows = [
            [bulkgift_btn($t['addVolumeBtn'], 'bgh:k:volume'), bulkgift_btn($t['addTimeBtn'], 'bgh:k:time')],
            [bulkgift_btn($t['campBtn'], 'bgh:camp', null)],
            [bulkgift_btn($t['helpBtn'], 'bgh:help', null), bulkgift_btn($t['closeBtn'], 'bgh:x', 'danger')],
        ];
        return [$t['title'] . "\n➖➖➖➖➖➖➖➖➖➖\n" . $t['startAsk'], json_encode(['inline_keyboard' => $rows])];
    }
    // 🔜 a campaign, the way a gift goes: what each purchase gets and how
    // much, then how long it runs
    function bulkgift_camp_payload()
    {
        $t = bulkgift_texts();
        $rows = [
            [bulkgift_btn($t['campVolumeBtn'], 'bgh:ck:volume'), bulkgift_btn($t['campTimeBtn'], 'bgh:ck:time')],
            [bulkgift_btn($t['backBtn'], 'bgh', 'danger')],
        ];
        return [$t['campTitle'] . "\n➖➖➖➖➖➖➖➖➖➖\n" . $t['campAsk'], json_encode(['inline_keyboard' => $rows])];
    }
    // 📖 how it all goes, in one message; 🔙 to where it was opened from
    function bulkgift_help_payload($back = 'bgh')
    {
        $t = bulkgift_texts();
        return [$t['help'], json_encode(['inline_keyboard' => [[bulkgift_btn($t['backBtn'], $back, 'danger')]]])];
    }
    // the panels picked, by name, as plain text for a button
    function bulkgift_panel_names(array $p)
    {
        return $p['pnames'] ?: array_map(fn($r) => (string) $r['name_panel'], $p['panels'] ? bulkgift_panels_of($p) : []);
    }
    // ▫️ the settings that are not the usual ones, one line each
    function bulkgift_nondefault_rows(array $p)
    {
        $t = bulkgift_texts();
        $live = bulkgift_is_live($p);
        $rows = [];
        if ($p['scope'] === 'bot' && $p['lang'] !== 'all') {
            $rows[] = strtr($t['rowLang'], ['{v}' => bulkgift_lang_name($p['lang'])]);
        }
        if ($p['panels']) {
            $rows[] = strtr($t['rowPanels'], ['{v}' => bulkgift_panels_label($p)]);
        }
        if (!$live && $p['buy'] !== 'all') {
            $rows[] = strtr($t['rowBuy'], ['{v}' => bulkgift_buy_label($p)]);
        }
        if (!$live && $p['statuses'] !== bulkgift_default_statuses()) {
            $rows[] = strtr($t['rowStatus'], ['{v}' => bulkgift_status_label($p)]);
        }
        if (!$p['notify']) {
            $rows[] = strtr($t['rowNotify'], ['{v}' => $t['notifyOff']]);
        }
        return implode("\n", $rows);
    }
    // The summary before anything starts: what, to about how many, every
    // setting that is not the usual one, and 🚀 - which carries the settings'
    // crc, so what starts is what this screen says. Nothing found: no 🚀.
    function bulkgift_summary_payload(array $p)
    {
        $t = bulkgift_texts();
        $live = bulkgift_is_live($p);
        $what = ['{icon}' => $p['kind'] === 'time' ? '⏰' : '📦', '{what}' => bulkgift_what_text($p)];
        $notes = [];
        if ($live) {
            $head = $t['campTitle'];
            $line = strtr($t['sumCampLine'], $what + ['{hours}' => number_format($p['buyn'])]);
            $notes[] = $t['sumCampNote'];
            $can = true;
        } else {
            $pv = bulkgift_preview($p);
            $head = $t['title'];
            $can = $pv['n'] > 0 || ($p['scope'] === 'panel' && $pv['listable']);
            $key = $p['scope'] === 'panel' ? 'sumLinePanel' : ($p['statuses'] === bulkgift_default_statuses() ? 'sumLine' : 'sumLineAny');
            $line = strtr($can ? $t[$key] : $t['sumEmpty'], $what + ['{n}' => number_format($pv['n'])]);
            if ($pv['h'] + $pv['t'] > 0) {
                $notes[] = strtr($t['sumSkip'], ['{h}' => number_format($pv['h']), '{t}' => number_format($pv['t'])]);
            }
            if ($p['scope'] === 'panel') {
                $notes[] = $t['sumPanelNote'];
                if ($pv['unsupported']) {
                    $notes[] = strtr($t['sumUnsupported'], ['{list}' => implode('، ', array_map(fn($n) => aff_bidi($n, '<b>' . htmlspecialchars($n) . '</b>'), $pv['unsupported']))]);
                }
            }
            if ($can) {
                $notes[] = $t['sumExact'];
            }
        }
        $extra = bulkgift_nondefault_rows($p);
        $text = $head . "\n➖➖➖➖➖➖➖➖➖➖\n" . $line
            . ($extra !== '' ? "\n\n" . $extra : '')
            . ($notes ? "\n<blockquote>" . implode("\n", $notes) . '</blockquote>' : '');
        $rows = [];
        if ($can) {
            $rows[] = [bulkgift_btn($live ? $t['startCampBtn'] : $t['startBtn'], 'bgh:start:' . bulkgift_sig($p), 'success')];
        }
        $rows[] = [bulkgift_btn($t['moreBtn'], 'bgh:more')];
        $rows[] = [bulkgift_btn($t['helpBtn'], 'bgh:help', null), bulkgift_btn($t['cancelDraftBtn'], 'bgh:x', 'danger')];
        return [$text, json_encode(['inline_keyboard' => $rows])];
    }
    // 🎯 تنظیمات بیشتر: one button for each of the rest, in plain words - what
    // it does while it is the usual one, ✅ and what it is set to once it is
    // not; 🔙 to the summary
    function bulkgift_more_payload(array $p)
    {
        $t = bulkgift_texts();
        $live = bulkgift_is_live($p);
        $row = fn($set, $usual, $changed, $cb) => [bulkgift_btn($set ? $changed : $usual, $cb, $set ? 'success' : 'primary')];
        $names = bulkgift_panel_names($p);
        $rows = [];
        $rows[] = $row($p['mode'] !== 'fixed', $t['moreMode'], strtr($t['moreModeSet'], ['{v}' => bulkgift_amount_label($p)]), 'bgh:sec:mode');
        if ($p['scope'] === 'bot') {
            $rows[] = $row($p['lang'] !== 'all', $t['moreLang'], strtr($t['moreLangSet'], ['{v}' => bulkgift_lang_name($p['lang'])]), 'bgh:sec:lang');
        }
        $rows[] = $row((bool) $p['panels'], $t['morePanels'], strtr($t['morePanelsSet'], ['{v}' => count($names) > 2 ? strtr($t['panelsCount'], ['{n}' => count($names)]) : implode('، ', $names)]), 'bgh:sec:panels');
        if ($live) {
            $rows[] = [bulkgift_btn(strtr($t['moreHoursBtn'], ['{n}' => number_format($p['buyn'])]), 'bgh:hours')];
        } else {
            $rows[] = $row($p['buy'] !== 'all', $t['moreBuy'], strtr($t['moreBuySet'], ['{v}' => bulkgift_buy_label($p)]), 'bgh:sec:buy');
            $rows[] = $row($p['statuses'] !== bulkgift_default_statuses(), $t['moreSt'], strtr($t['moreStSet'], ['{v}' => bulkgift_status_label($p)]), 'bgh:sec:st');
            $rows[] = $row($p['scope'] === 'panel', $t['moreScope'], $t['moreScopeSet'], 'bgh:sc');
        }
        $rows[] = $row(!$p['notify'], $t['moreNotify'], $t['moreNotifySet'], 'bgh:n');
        $rows[] = [bulkgift_btn($t['previewBtn'], 'bgh:pv', null)];
        $rows[] = [bulkgift_btn($t['backSumBtn'], 'bgh:sum', 'danger')];
        return [$t['moreTitle'] . "\n➖➖➖➖➖➖➖➖➖➖\n" . $t['moreHint'], json_encode(['inline_keyboard' => $rows])];
    }
    // One setting of 🎯 on a screen of its own - mode, lang, panels, buy, st:
    // its choices one under the other, and 🔙 to the summary
    function bulkgift_sub_payload(array $p, $sec)
    {
        $t = bulkgift_texts();
        $line = "\n➖➖➖➖➖➖➖➖➖➖\n";
        $rows = [];
        if ($sec === 'mode') {
            foreach (['fixed', 'total', 'left'] as $m) {
                $rows[] = [bulkgift_pick($p['mode'] === $m, $t['mode' . ucfirst($m) . 'Btn'], 'bgh:m:' . $m)];
            }
            $text = $t['modeTitle'] . $line . $t['modeHint'];
        } elseif ($sec === 'lang') {
            $rows[] = [bulkgift_pick($p['lang'] === 'all', $t['langAll'], 'bgh:l:all')];
            foreach (panel_langs() as $l) {
                $rows[] = [bulkgift_pick($p['lang'] === $l, bulkgift_lang_name($l), 'bgh:l:' . $l)];
            }
            $text = $t['langTitle'] . $line . $t['langHint'];
        } elseif ($sec === 'panels') {
            $rows[] = [bulkgift_pick(!$p['panels'], $t['panelsAll'], 'bgh:p:all')];
            $pair = [];
            // Telegram refuses a message with more than 100 buttons: past the
            // first 90 panels, one is picked on the web panel's page
            foreach (array_slice(bulkgift_all_panels(), 0, 90) as $r) {
                $name = (string) $r['name_panel'] . ($p['scope'] === 'panel' && !bulkgift_listable($r) ? $t['panelBotOnly'] : '');
                $pair[] = bulkgift_pick(in_array((string) $r['code_panel'], $p['panels'], true), $name, 'bgh:p:' . (int) $r['id']);
                if (count($pair) === 2) {
                    $rows[] = $pair;
                    $pair = [];
                }
            }
            if ($pair) {
                $rows[] = $pair;
            }
            $text = $t['panelsTitle'] . $line . $t['panelsHint'];
        } elseif ($sec === 'buy') {
            foreach (['all' => $t['buyAll'], 'lasth' => $t['buyLasthBtn'], 'days' => $t['buyDaysBtn'], 'months' => $t['buyMonthsBtn'], 'hours' => $t['buyHoursBtn']] as $k => $label) {
                $rows[] = [bulkgift_pick($p['buy'] === $k, $p['buy'] === $k && $k !== 'all' ? bulkgift_buy_label($p) : $label, 'bgh:b:' . $k)];
            }
            $text = $t['buyTitle'] . $line . $t['buyHint'];
        } else {
            foreach (bulkgift_statuses() as $s) {
                $rows[] = [bulkgift_pick(in_array($s, $p['statuses'], true), $t['st_' . $s], 'bgh:t:' . $s)];
            }
            $text = $t['stTitle'] . $line . $t['stHint'];
        }
        $rows[] = [bulkgift_btn($t['backSumBtn'], 'bgh:sum', 'danger')];
        return [$text, json_encode(['inline_keyboard' => $rows])];
    }
    // a question that took the screen's place, with its way back ($back: the
    // screen it was asked from)
    function bulkgift_ask_payload($text, $back = 'bgh')
    {
        return [$text, json_encode(['inline_keyboard' => [[bulkgift_btn(bulkgift_texts()['backBtn'], $back, 'danger')]]])];
    }
    // the question for the amount: gigabytes, days and hours, or a percent
    // ($mode: a way of counting picked but not saved yet)
    function bulkgift_value_ask(array $p, $mode = null)
    {
        $t = bulkgift_texts();
        return ($mode ?? $p['mode']) !== 'fixed' ? $t['askPercent'] : ($p['kind'] === 'time' ? $t['askDays'] : $t['askVolume']);
    }
    // 👁 the message a customer of $lang gets: a percentage is shown for a 10
    // GB, 30-day subscription
    function bulkgift_sample_text(array $p, $lang)
    {
        $key = $p['kind'] === 'time' ? 'bulkGiftTime' : 'bulkGiftVolume';
        $v = (float) ($p['value'] ?? 0);
        if ($p['kind'] === 'time') {
            $h = $p['mode'] === 'fixed' ? bulkgift_hours_of($v) : (int) round(30 * 24 * $v / 100);
            $amount = bulkgift_hours_text(max(1, $h), $lang);
        } else {
            $amount = volume_num(max(0.01, $p['mode'] === 'fixed' ? $v : round(10 * $v / 100, 2)));
        }
        return bulkgift_fill(lang_tab_texts($lang)['users']['status'][$key], $p['kind'], $amount, 'user_1234');
    }
}
