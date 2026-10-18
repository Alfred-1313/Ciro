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
// ♾ unlimited ones are left out the same way.
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
            KEY bulk_gift_state (state))
            ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
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
    // A run's settings from anything - a draft, a job's row, a web form:
    //   kind    volume | time
    //   mode    fixed (value: GB, or whole days) | total | left (value: percent)
    //   scope   bot (the bot's own services) | panel (every account on the panels)
    //   lang    all | a language - the bot's users of that language (scope bot)
    //   panels  code_panel list, empty = every panel
    //   buy     all | days | months (bought in the last buyn) | hours (bought
    //           buyn hours ago or earlier)
    //   active  only accounts that are active (or not started yet)
    //   notify  the owner of each of the bot's services is told
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
                $v = (floor($v) == $v && $v >= 1 && $v <= 3650) ? (int) $v : null;
            } else {
                $v = $v >= 0.01 ? round($v, 4) : null;
            }
        }
        $o['value'] = $v;
        $o['scope'] = ($p['scope'] ?? '') === 'panel' ? 'panel' : 'bot';
        $o['lang'] = in_array($p['lang'] ?? '', panel_langs(), true) ? $p['lang'] : 'all';
        $o['panels'] = array_values(array_unique(array_filter(array_map('strval', (array) ($p['panels'] ?? [])), 'strlen')));
        $o['buy'] = in_array($p['buy'] ?? '', ['days', 'months', 'hours'], true) ? $p['buy'] : 'all';
        $o['buyn'] = max(0, min(100000, (int) ($p['buyn'] ?? 0)));
        if ($o['buy'] === 'all' || $o['buyn'] < 1) {
            $o['buy'] = 'all';
            $o['buyn'] = 0;
        }
        $o['active'] = array_key_exists('active', $p) ? (bool) $p['active'] : true;
        $o['notify'] = array_key_exists('notify', $p) ? (bool) $p['notify'] : true;
        // a job also keeps the names its panels had when it was started
        $o['pnames'] = array_values(array_filter(array_map('strval', (array) ($p['pnames'] ?? [])), 'strlen'));
        return $o;
    }
    // The amount someone typed: [value, null] or [null, the text of why not]
    function bulkgift_parse_value($kind, $mode, $text)
    {
        $t = bulkgift_texts();
        $s = bulkgift_digits($text);
        if ($mode !== 'fixed') {
            $s = trim(preg_replace('/[%٪\s]+$/u', '', $s));
            if (!preg_match('/^\d+(\.\d+)?$/', $s) || (float) $s < 0.1 || (float) $s > 100) {
                return [null, $t['badPercent']];
            }
            return [round((float) $s, 2), null];
        }
        if ($kind === 'volume') {
            $gb = volume_parse($s, 'gb');
            if ($gb === null || $gb < 0.01) {
                return [null, $t['badVolume']];
            }
            return [round($gb, 4), null];
        }
        if (!preg_match('/^\d+$/', $s) || (int) $s < 1 || (int) $s > 3650) {
            return [null, $t['badDays']];
        }
        return [(int) $s, null];
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
    // the bot's own services a run is for, as SQL over invoice i
    // ($names: the panels', always given - a service on a panel the bot no
    // longer has is nobody's to give to)
    function bulkgift_invoice_where(array $p, array $names)
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
        [$from, $to] = bulkgift_buy_window($p);
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
    // it is started: 'n' to look at, 'h' ⏱ and 't' 🧪 left out, and for «همه‌ی
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
        if ($names) {
            [$w, $a] = bulkgift_invoice_where($p, $names);
            $st = $pdo->prepare("SELECT i.Service_location, i.username, i.payg, i.name_product FROM invoice i WHERE {$w}");
            $st->execute($a);
            $seen = [];
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $key = bulkgift_ukey($r['Service_location'], $r['username']);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $k = bulkgift_kind_of($r);
                $out[$k === 'payg' ? 'h' : ($k === 'test' ? 't' : 'n')]++;
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
    // «5 گیگ», «10٪ از حجم باقی‌مونده», «— (هنوز تعیین نشده)»
    function bulkgift_amount_label(array $p)
    {
        $t = bulkgift_texts();
        if ($p['value'] === null) {
            return $t['valueUnset'];
        }
        $key = ($p['kind'] === 'time' ? 'time' : 'vol') . ucfirst($p['mode']);
        return strtr($t[$key], ['{n}' => bulkgift_num($p['value'])]);
    }
    function bulkgift_scope_label(array $p)
    {
        $t = bulkgift_texts();
        return $p['scope'] === 'panel' ? $t['scopePanel'] : $t['scopeBot'];
    }
    function bulkgift_lang_label(array $p)
    {
        $t = bulkgift_texts();
        if ($p['scope'] === 'panel') {
            return $t['langPanelScope'];
        }
        return $p['lang'] === 'all' ? $t['langAll'] : bulkgift_lang_name($p['lang']);
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
    function bulkgift_buy_label(array $p)
    {
        $t = bulkgift_texts();
        if ($p['buy'] === 'all') {
            return $t['buyAllShort'];
        }
        return strtr($t['buy' . ucfirst($p['buy'])], ['{n}' => number_format($p['buyn'])]);
    }
    // ▫️ one line for each setting
    function bulkgift_summary_rows(array $p)
    {
        $t = bulkgift_texts();
        return implode("\n", [
            strtr($t['rowKind'], ['{v}' => $p['kind'] === 'time' ? $t['kindTime'] : $t['kindVolume']]),
            strtr($t['rowAmount'], ['{v}' => bulkgift_amount_label($p)]),
            strtr($t['rowScope'], ['{v}' => bulkgift_scope_label($p)]),
            strtr($t['rowLang'], ['{v}' => bulkgift_lang_label($p)]),
            strtr($t['rowPanels'], ['{v}' => bulkgift_panels_label($p)]),
            strtr($t['rowBuy'], ['{v}' => bulkgift_buy_label($p)]),
            strtr($t['rowStatus'], ['{v}' => $p['active'] ? $t['statusActiveShort'] : $t['statusAllShort']]),
            strtr($t['rowNotify'], ['{v}' => $p['notify'] ? $t['notifyOn'] : $t['notifyOff']]),
        ]);
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
    // the run going on now, if there is one - there is never more than one
    function bulkgift_active_job()
    {
        global $pdo;
        bulkgift_ensure_schema();
        $r = $pdo->query("SELECT * FROM bulk_gift_job WHERE state IN ('queued', 'running') ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
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
        $panels = bulkgift_panels_of($p);
        if (!$panels) {
            return ['error' => $t['noPanels']];
        }
        $p['pnames'] = $p['panels'] ? array_map(fn($r) => (string) $r['name_panel'], $panels) : [];
        // two admins pressing at once must not start two
        $got = (int) $pdo->query("SELECT GET_LOCK('mirza_bulkgift_new', 5)")->fetchColumn();
        try {
            if (bulkgift_active_job() !== null) {
                return ['error' => $t['busy']];
            }
            $pdo->prepare("INSERT INTO bulk_gift_job (params, state, origin, created_by, created_at) VALUES (?, 'queued', ?, ?, ?)")
                ->execute([json_encode($p, JSON_UNESCAPED_UNICODE), $origin === 'web' ? 'web' : 'bot', (string) $createdBy, time()]);
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
    // What one account gets: ['gb' => x] or ['days' => n], or ['skip' => why].
    // $d: what the panel says about it (DataUser's shape); $start: when it
    // was bought (or made, in the panel), 0 when not known.
    function bulkgift_eval(array $p, array $d, $start, $now = null)
    {
        $now = $now ?? time();
        $status = (string) ($d['status'] ?? '');
        $limit = (float) ($d['data_limit'] ?? 0);
        $used = (float) ($d['used_traffic'] ?? 0);
        $expire = (int) ($d['expire'] ?? 0);
        // not started yet counts as active: it is a subscription nobody used up
        if ($p['active'] && !in_array($status, ['active', 'on_hold'], true)) {
            return ['skip' => 'inactive'];
        }
        if ($p['kind'] === 'time') {
            // its clock starts at the first connection; its time is not a date yet
            if ($status === 'on_hold') {
                return ['skip' => 'onhold'];
            }
            if ($expire <= 0) {
                return ['skip' => 'unlimited'];
            }
            if ($p['mode'] === 'fixed') {
                $days = (int) $p['value'];
            } elseif ($p['mode'] === 'total') {
                if ($start <= 0 || $expire <= $start) {
                    return ['skip' => 'nototal'];
                }
                $days = (int) round(($expire - $start) / 86400 * $p['value'] / 100);
            } else {
                $left = $expire - $now;
                if ($left <= 0) {
                    return ['skip' => 'nothing'];
                }
                $days = (int) round($left / 86400 * $p['value'] / 100);
            }
            return $days < 1 ? ['skip' => 'small'] : ['days' => $days];
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
        $ev = bulkgift_eval($p, $d, (int) $item['start_ts']);
        if (isset($ev['skip'])) {
            return ['skipped', $ev['skip']];
        }
        // extra_volume() and extra_time() mark every invoice of this username
        // active: an old, removed service with the same name would come back
        $st = $pdo->prepare("SELECT id_invoice, Status FROM invoice WHERE username = ? AND id_invoice <> ?");
        $st->execute([$inv['username'], $inv['id_invoice']]);
        $others = $st->fetchAll(PDO::FETCH_ASSOC);
        if ($p['kind'] === 'time') {
            $res = $mp->extra_time($inv['username'], $panel['code_panel'], (int) $ev['days']);
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
        $amount = $p['kind'] === 'time' ? (string) (int) $ev['days'] : volume_store($ev['gb']);
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
        $pre = bulkgift_eval($p, ['status' => $snap['s'] ?? '', 'data_limit' => $snap['l'] ?? 0, 'used_traffic' => $snap['u'] ?? 0, 'expire' => $snap['e'] ?? 0], (int) $item['start_ts']);
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
        $ev = bulkgift_eval($p, $d, (int) $item['start_ts']);
        if (isset($ev['skip'])) {
            return ['skipped', $ev['skip']];
        }
        if ($p['kind'] === 'time') {
            $cfg = ['expire' => max(time(), (int) $d['expire']) + (int) $ev['days'] * 86400];
        } else {
            $cfg = ['data_limit' => (int) round((float) $d['data_limit'] + $ev['gb'] * pow(1024, 3))];
        }
        $res = $mp->Modifyuser($item['username'], $panel['name_panel'], $cfg);
        if (!is_array($res) || empty($res['status'])) {
            return ['failed', 'failed', '', bulkgift_msg(is_array($res) ? ($res['msg'] ?? '') : '')];
        }
        return ['done', 'done', $p['kind'] === 'time' ? (string) (int) $ev['days'] : volume_store($ev['gb']), null];
    }
    // the service's history (👁 of the order in 👤 مدیریت کاربر), as every
    // other change to a service is kept
    function bulkgift_log_service(array $inv, array $p, array $ev, array $d)
    {
        global $pdo;
        $value = $p['kind'] === 'time'
            ? ['time_value' => (int) $ev['days'], 'old_volume' => $d['data_limit'] ?? 0, 'expire_old' => $d['expire'] ?? 0]
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
        sendmessage($id, strtr($tpl, [
            '{amount}' => $p['kind'] === 'time' ? (string) $amount : volume_num($amount),
            '{username}' => htmlspecialchars((string) $inv['username']),
        ]), null, 'HTML');
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
        $lines = [
            strtr($t['progressTitle'], ['{kind}' => $p['kind'] === 'time' ? $t['kindNameTime'] : $t['kindNameVolume']]),
            '➖➖➖➖➖➖➖➖➖➖',
            strtr($t['rowAmount'], ['{v}' => bulkgift_amount_label($p)]),
            strtr($t['rowScope'], ['{v}' => bulkgift_scope_label($p)]),
            '',
        ];
        if (!(int) $job['prepared']) {
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
        $pdo->prepare("UPDATE bulk_gift_job SET shown_at = ? WHERE id = ?")->execute([time(), $job['id']]);
        [$text, $kb] = bulkgift_progress_payload($job);
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
        $parts = [
            strtr($job['state'] === 'cancelled' ? $t['cancelledTitle'] : $t['doneTitle'], ['{kind}' => $kind])
            . "\n➖➖➖➖➖➖➖➖➖➖\n" . bulkgift_summary_rows($p),
        ];
        if ($c['total'] === 0) {
            $parts[] = $t['emptyResult'];
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
    // cronbot/gift.php: works on the run going on, if there is one - one
    // worker at a time (a MySQL lock, so the cron and a kick never overlap),
    // for at most $budget seconds ($maxItems: for tests). Hands the rest to
    // the next run straight away when there is more.
    // 'idle' | 'busy' | 'preparing' | 'more' | 'finished'
    function bulkgift_cron($budget = null, $maxItems = null)
    {
        global $pdo;
        bulkgift_ensure_schema();
        if (bulkgift_active_job() === null) {
            return 'idle';
        }
        if ((int) $pdo->query("SELECT GET_LOCK('mirza_bulkgift', 0)")->fetchColumn() !== 1) {
            return 'busy';
        }
        $result = 'idle';
        $worked = 0;
        try {
            $deadline = time() + ($budget ?? bulkgift_budget());
            $job = bulkgift_active_job();
            if ($job === null) {
                return $result;
            }
            bulkgift_stale_claims($job['id']);
            if (!(int) $job['prepared'] && !(int) $job['cancel_req']) {
                bulkgift_admin_msg($job['id']);
                if (!bulkgift_prepare($job, $deadline)) {
                    $result = 'preparing';
                    bulkgift_progress($job['id'], true);
                    return $result;
                }
            }
            $job = bulkgift_job($job['id']);
            bulkgift_admin_msg($job['id']);
            $worked = bulkgift_work($job, $deadline, $maxItems);
            $job = bulkgift_job($job['id']);
            $st = $pdo->prepare("SELECT COUNT(*) FROM bulk_gift_item WHERE job_id = ? AND state = 'pending'");
            $st->execute([$job['id']]);
            if ((int) $st->fetchColumn() === 0 || (int) $job['cancel_req'] === 1) {
                bulkgift_finish($job);
                $result = 'finished';
            } else {
                bulkgift_progress($job['id'], true);
                $result = 'more';
            }
            return $result;
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('mirza_bulkgift')");
            if ($result === 'preparing' || ($result === 'more' && $worked > 0)) {
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
    // 🏬 ← 🎁: the settings, one button for each - ✅ on what is picked
    function bulkgift_hub_payload(array $p)
    {
        $t = bulkgift_texts();
        $btn = function ($text, $cb, $style = 'primary') {
            $b = ['text' => $text, 'callback_data' => $cb];
            if ($style !== null) {
                $b['style'] = $style;
            }
            return $b;
        };
        $pick = fn($on, $text, $cb) => $btn(($on ? '✅ ' : '') . $text, $cb, $on ? 'success' : 'primary');
        $rows = [];
        $rows[] = [$btn($t['sepWhat'], 'bgh:sep:what', null)];
        $rows[] = [$pick($p['kind'] === 'volume', $t['kindVolume'], 'bgh:k:volume'), $pick($p['kind'] === 'time', $t['kindTime'], 'bgh:k:time')];
        $rows[] = [$pick($p['mode'] === 'fixed', $t['modeFixed'], 'bgh:m:fixed'), $pick($p['mode'] === 'total', $t['modeTotal'], 'bgh:m:total'), $pick($p['mode'] === 'left', $t['modeLeft'], 'bgh:m:left')];
        $rows[] = [$p['value'] === null
            ? $btn($t['valueBtnUnset'], 'bgh:v', 'danger')
            : $btn(strtr($t['valueBtn'], ['{v}' => strip_tags(bulkgift_amount_label($p))]), 'bgh:v', 'success')];
        $rows[] = [$btn($t['sepWho'], 'bgh:sep:who', null)];
        $rows[] = [$pick($p['scope'] === 'bot', $t['scopeBot'], 'bgh:s:bot'), $pick($p['scope'] === 'panel', $t['scopePanel'], 'bgh:s:panel')];
        if ($p['scope'] === 'bot') {
            $row = [$pick($p['lang'] === 'all', $t['langAll'], 'bgh:l:all')];
            foreach (panel_langs() as $l) {
                $row[] = $pick($p['lang'] === $l, bulkgift_lang_name($l), 'bgh:l:' . $l);
            }
            $rows[] = $row;
        }
        $rows[] = [$pick(!$p['panels'], $t['panelsAll'], 'bgh:p:all')];
        $line = [];
        // Telegram refuses a message with more than 100 buttons: past the
        // first 60 panels, one is picked on the web panel's page
        foreach (array_slice(bulkgift_all_panels(), 0, 60) as $r) {
            $name = (string) $r['name_panel'] . ($p['scope'] === 'panel' && !bulkgift_listable($r) ? $t['panelBotOnly'] : '');
            $line[] = $pick(in_array((string) $r['code_panel'], $p['panels'], true), $name, 'bgh:p:' . (int) $r['id']);
            if (count($line) === 2) {
                $rows[] = $line;
                $line = [];
            }
        }
        if ($line) {
            $rows[] = $line;
        }
        $buy = fn($k, $label) => $pick($p['buy'] === $k, $p['buy'] === $k ? bulkgift_buy_label($p) : $label, 'bgh:b:' . $k);
        $rows[] = [$buy('all', $t['buyAll']), $buy('days', $t['buyDaysBtn'])];
        $rows[] = [$buy('months', $t['buyMonthsBtn']), $buy('hours', $t['buyHoursBtn'])];
        $rows[] = [$pick($p['active'], $t['statusActive'], 'bgh:a:1'), $pick(!$p['active'], $t['statusAll'], 'bgh:a:0')];
        $rows[] = [$btn($t['sepMsg'], 'bgh:sep:msg', null)];
        $rows[] = [$btn($p['notify'] ? $t['notifyOnBtn'] : $t['notifyOffBtn'], 'bgh:n', $p['notify'] ? 'success' : 'danger'), $btn($t['previewBtn'], 'bgh:pv')];
        $rows[] = [$btn($t['continueBtn'], 'bgh:go', 'success')];
        $rows[] = [$btn($t['closeBtn'], 'bgh:x', 'danger')];
        $text = $t['title'] . "\n➖➖➖➖➖➖➖➖➖➖\n" . $t['intro'] . "\n\n" . $t['settingsHead'] . "\n" . bulkgift_summary_rows($p) . "\n\n" . $t['skipNote'];
        return [$text, json_encode(['inline_keyboard' => $rows])];
    }
    // «🚀 ادامه»: the settings once more, and what they find among the bot's
    // services right now
    function bulkgift_confirm_payload(array $p)
    {
        $t = bulkgift_texts();
        $pv = bulkgift_preview($p);
        $parts = [
            $t['confirmTitle'] . "\n➖➖➖➖➖➖➖➖➖➖\n" . bulkgift_summary_rows($p),
            strtr($t['confirmCounts'], ['{n}' => number_format($pv['n']), '{h}' => number_format($pv['h']), '{t}' => number_format($pv['t'])]),
        ];
        if ($p['scope'] === 'panel') {
            $parts[] = $t['confirmPanelNote'] . ($pv['unsupported']
                ? "\n" . strtr($t['confirmUnsupported'], ['{list}' => implode("\n", array_map(fn($n) => '▫️ ' . aff_bidi($n, '<b>' . htmlspecialchars($n) . '</b>'), $pv['unsupported']))])
                : '');
        }
        $can = $pv['n'] > 0 || ($p['scope'] === 'panel' && $pv['listable']);
        $parts[] = $can ? $t['confirmLiveNote'] : $t['emptyResult'];
        $rows = [];
        if ($can) {
            $rows[] = [['text' => $t['confirmBtn'], 'callback_data' => 'bgh:start:' . bulkgift_sig($p), 'style' => 'success']];
        }
        $rows[] = [['text' => $t['editBtn'], 'callback_data' => 'bgh', 'style' => 'primary']];
        $rows[] = [['text' => $t['closeBtn'], 'callback_data' => 'bgh:x', 'style' => 'danger']];
        return [implode("\n\n", $parts), json_encode(['inline_keyboard' => $rows])];
    }
    // a question that took the screen's place, with its way back
    function bulkgift_ask_payload($text)
    {
        return [$text, json_encode(['inline_keyboard' => [[['text' => bulkgift_texts()['backBtn'], 'callback_data' => 'bgh', 'style' => 'danger']]]])];
    }
    // 👁 the message a customer of $lang gets: a percentage is shown for a 10
    // GB, 30-day subscription
    function bulkgift_sample_text(array $p, $lang)
    {
        $key = $p['kind'] === 'time' ? 'bulkGiftTime' : 'bulkGiftVolume';
        $v = (float) ($p['value'] ?? 0);
        if ($p['mode'] !== 'fixed') {
            $v = $p['kind'] === 'time' ? max(1, (int) round(30 * $v / 100)) : max(0.01, round(10 * $v / 100, 2));
        }
        $amount = $p['kind'] === 'time' ? (string) max(1, (int) $v) : volume_num($v > 0 ? $v : 1);
        return strtr((string) lang_tab_texts($lang)['users']['status'][$key], ['{amount}' => $amount, '{username}' => 'user_1234']);
    }
}
