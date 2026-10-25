<?php
// 🚀 سرعت گروهی: one speed limit put on many accounts at once - or taken off
// them (0) - on the Rebecca panels, the only ones the bot can give a speed
// limit (speed_limit_kbps: Mbps × 1000, the way Rebecca's own form sends it).
// For the bot's own services of the kinds picked - 🛒 purchases, ⏱ hourly,
// 🎁 the free configs given for referrals, 🧪 tests - or every service of the
// bot, or every account on the panels. Opened from 🚀 مدیریت سرعت in 🏬
// تنظیمات فروشگاه, next to 🎁 حجم یا زمان گروهی (only while there is a Rebecca
// panel) - the screen that also has every other speed setting (a product's,
// a test account's, a free referral config's) in one place - and from the web
// panel's 🚀 page; both start the same run.
//
// A run is one bulk_speed_job with one bulk_speed_item for every account it
// reaches, worked through by cronbot/gift.php a few minutes at a time the way
// 🎁's runs are (bulkgift.php): the list first, then one account at a time. A
// speed is the same however many times it is sent, so an account a run was
// cut off on, or one its panel did not answer for, is simply sent again - a
// network error twice more, 2 and 10 minutes later; what still failed is in
// the report, with 🔁.
//
// Required by function.php after bulkgift.php, whose helpers it uses.

if (!function_exists('bulkspeed_ensure_schema')) {
    // bulk_speed_job: one row per run - its settings, how far it has got, the
    // admin's progress message. bulk_speed_item: one row per account a run
    // reaches and what became of it. bulk_speed_draft: the settings an admin is
    // putting together in the bot, until they start it.
    function bulkspeed_ensure_schema()
    {
        static $done = false;
        global $pdo;
        if ($done || !($pdo instanceof PDO)) {
            return;
        }
        $done = true;
        $pdo->query("CREATE TABLE IF NOT EXISTS bulk_speed_job (
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
            KEY bulk_speed_state (state))
            ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        // ukey: the account (panel + username) - one row per account per run;
        // grp: what kind of the bot's services it is, or 'panel' for one the
        // bot has no service for
        $pdo->query("CREATE TABLE IF NOT EXISTS bulk_speed_item (
            id INT AUTO_INCREMENT PRIMARY KEY,
            job_id INT NOT NULL,
            ukey CHAR(40) NOT NULL,
            panel VARCHAR(500) NOT NULL,
            username VARCHAR(300) NOT NULL,
            src VARCHAR(10) NOT NULL DEFAULT 'bot',
            grp VARCHAR(10) NOT NULL DEFAULT '',
            id_invoice VARCHAR(200) NULL,
            state VARCHAR(20) NOT NULL DEFAULT 'pending',
            reason VARCHAR(30) NOT NULL DEFAULT '',
            info TEXT NULL,
            tries TINYINT NOT NULL DEFAULT 0,
            next_at INT NOT NULL DEFAULT 0,
            updated_at INT NOT NULL DEFAULT 0,
            UNIQUE KEY bulk_speed_one (job_id, ukey),
            KEY bulk_speed_todo (job_id, state))
            ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->query("CREATE TABLE IF NOT EXISTS bulk_speed_draft (
            admin_id VARCHAR(100) NOT NULL PRIMARY KEY,
            params TEXT NOT NULL,
            ask_msg BIGINT NOT NULL DEFAULT 0,
            updated_at INT NOT NULL DEFAULT 0)
            ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
    }
}

if (!function_exists('bulkspeed_params')) {
    // the admin's words for all of it: Persian, like the rest of the panel
    function bulkspeed_texts()
    {
        return panel_texts()['Admin']['BulkSpeed'];
    }
    // the kinds of the bot's services, in the order the screens show them
    function bulkspeed_groups()
    {
        return ['sale', 'payg', 'ref', 'test'];
    }
    // A run's settings from anything - a draft, a job's row, a web form:
    //   speed   Mbps, 0 = no limit (null: not given yet)
    //   who     groups (the bot's services of the kinds in groups) | bot (every
    //           service of the bot) | panel (every account on the panels)
    //   groups  sale | payg | ref | test
    //   panels  code_panel list, empty = every Rebecca panel
    //   pnames  a started run's: the names its panels had then
    function bulkspeed_params($raw)
    {
        $p = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);
        $s = $p['speed'] ?? null;
        return [
            'speed' => is_numeric($s) && (float) $s >= 0 && (float) $s <= 100000 ? round((float) $s, 2) : null,
            'who' => in_array($p['who'] ?? '', ['groups', 'bot', 'panel'], true) ? $p['who'] : 'groups',
            'groups' => array_values(array_intersect(bulkspeed_groups(), array_map('strval', (array) ($p['groups'] ?? [])))),
            'panels' => array_values(array_unique(array_filter(array_map('strval', (array) ($p['panels'] ?? [])), 'strlen'))),
            'pnames' => array_values(array_filter(array_map('strval', (array) ($p['pnames'] ?? [])), 'strlen')),
        ];
    }
    // somebody is picked: a kind of the bot's services, all of them, or the panels'
    function bulkspeed_who_set(array $p)
    {
        return $p['who'] !== 'groups' || $p['groups'];
    }
    // the Rebecca panels, in the bot's own order
    function bulkspeed_all_panels()
    {
        global $pdo;
        return $pdo->query("SELECT * FROM marzban_panel WHERE type = 'rebecca' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    }
    // there is something to set a speed on: the 🚀 button and page show only then
    function bulkspeed_available()
    {
        global $pdo;
        try {
            return (bool) $pdo->query("SELECT 1 FROM marzban_panel WHERE type = 'rebecca' LIMIT 1")->fetchColumn();
        } catch (Exception $e) {
            return false;
        }
    }
    // the panels a run is for: the Rebecca ones picked, or all of them
    function bulkspeed_panels_of(array $p)
    {
        $all = bulkspeed_all_panels();
        if (!$p['panels']) {
            return $all;
        }
        return array_values(array_filter($all, fn($r) => in_array((string) $r['code_panel'], $p['panels'], true)));
    }
    // what kind one of the bot's services is: sale, payg, ref or test - or
    // other, an account an admin handed to a user (🔗 واگذاری)
    function bulkspeed_group_of(array $inv)
    {
        $k = bulkgift_kind_of($inv);
        if ($k === 'payg' || $k === 'test') {
            return $k;
        }
        if ((int) ($inv['affrw'] ?? 0) === 1) {
            return 'ref';
        }
        return bulkgift_is_sale($inv) ? 'sale' : 'other';
    }
    // is a service of this kind one the run is for
    function bulkspeed_wants(array $p, $group)
    {
        return $p['who'] !== 'groups' || in_array($group, $p['groups'], true);
    }
    // the bot's services on these panels that are still their owners', read
    // one at a time; null when there are no panels
    function bulkspeed_invoice_stmt(array $names)
    {
        global $pdo;
        if (!$names) {
            return null;
        }
        if (function_exists('payg_ensure_schema')) {
            payg_ensure_schema();
        }
        if (function_exists('affrw_ensure_table')) {
            affrw_ensure_table();
        }
        $st = $pdo->prepare("SELECT i.id_invoice, i.id_user, i.username, i.Service_location, i.name_product, i.payg, i.affrw FROM invoice i WHERE i.Status IN ('" . implode("', '", bulkgift_live_statuses()) . "') AND i.Service_location IN (" . implode(', ', array_fill(0, count($names), '?')) . ") ORDER BY i.id_invoice");
        $st->execute(array_values($names));
        return $st;
    }
    // What a run with these settings finds among the bot's services before it
    // is started: 'n' how many get it (one account counts once), 'g' of which
    // kind, 'panels' how many Rebecca panels it is for
    function bulkspeed_preview(array $p)
    {
        $panels = bulkspeed_panels_of($p);
        $out = ['n' => 0, 'g' => array_fill_keys(array_merge(bulkspeed_groups(), ['other']), 0), 'panels' => count($panels)];
        $st = bulkspeed_invoice_stmt(array_map(fn($r) => (string) $r['name_panel'], $panels));
        if ($st === null) {
            return $out;
        }
        $seen = [];
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $key = bulkgift_ukey($r['Service_location'], $r['username']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $g = bulkspeed_group_of($r);
            if (bulkspeed_wants($p, $g)) {
                $out['n']++;
                $out['g'][$g]++;
            }
        }
        return $out;
    }
}

if (!function_exists('bulkspeed_speed_label')) {
    // «10 Mbps», «♾ برداشتن محدودیت», «— (هنوز تعیین نشده)»
    function bulkspeed_speed_label(array $p)
    {
        $t = bulkspeed_texts();
        if ($p['speed'] === null) {
            return $t['speedUnset'];
        }
        return (float) $p['speed'] > 0 ? speed_limit_label($p['speed'], '') : $t['speedRemove'];
    }
    // «🛒 خرید عادی، ⏱ ساعتی», «🤖 همه‌ی کاربران ربات», «🌐 همه‌ی یوزرهای پنل»
    function bulkspeed_who_label(array $p)
    {
        $t = bulkspeed_texts();
        if ($p['who'] === 'bot') {
            return $t['wBot'];
        }
        if ($p['who'] === 'panel') {
            return $t['wPanel'];
        }
        if (!$p['groups']) {
            return $t['whoUnset'];
        }
        return implode('، ', array_map(fn($g) => $t['g' . ucfirst($g)], $p['groups']));
    }
    // the panels by name (a job's: the names they had when it started)
    function bulkspeed_panels_label(array $p)
    {
        $t = bulkspeed_texts();
        $names = $p['pnames'] ?: ($p['panels'] ? array_map(fn($r) => (string) $r['name_panel'], bulkspeed_panels_of($p)) : []);
        if (!$p['panels'] || !$names) {
            return $t['panelsAllShort'];
        }
        return implode('، ', array_map(fn($n) => aff_bidi($n, htmlspecialchars($n)), $names));
    }
    // ▫️ the settings, one line each, up to and with $upto (speed, who, panels)
    function bulkspeed_rows(array $p, $upto = 'panels')
    {
        $t = bulkspeed_texts();
        $rows = [strtr($t['rowSpeed'], ['{v}' => bulkspeed_speed_label($p)])];
        if ($upto !== 'speed') {
            $rows[] = strtr($t['rowWho'], ['{v}' => bulkspeed_who_label($p)]);
        }
        if ($upto === 'panels') {
            $rows[] = strtr($t['rowPanels'], ['{v}' => bulkspeed_panels_label($p)]);
        }
        return implode("\n", $rows);
    }
}

if (!function_exists('bulkspeed_job')) {
    function bulkspeed_job($id)
    {
        global $pdo;
        bulkspeed_ensure_schema();
        $st = $pdo->prepare("SELECT * FROM bulk_speed_job WHERE id = ?");
        $st->execute([(int) $id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }
    // the run going on now - one at a time - in a list, or none
    function bulkspeed_active_jobs()
    {
        global $pdo;
        bulkspeed_ensure_schema();
        return $pdo->query("SELECT * FROM bulk_speed_job WHERE state IN ('queued', 'running') ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    }
    function bulkspeed_active_job()
    {
        return bulkspeed_active_jobs()[0] ?? null;
    }
    function bulkspeed_recent($limit = 10)
    {
        global $pdo;
        bulkspeed_ensure_schema();
        return $pdo->query("SELECT * FROM bulk_speed_job ORDER BY id DESC LIMIT " . max(1, (int) $limit))->fetchAll(PDO::FETCH_ASSOC);
    }
    function bulkspeed_job_params(array $job)
    {
        return bulkspeed_params(json_decode((string) $job['params'], true));
    }
    // A new run: ['id' => ...] or ['error' => text]. $createdBy: the Telegram
    // id of the admin who gets its progress and its report.
    function bulkspeed_create(array $raw, $createdBy, $origin = 'bot')
    {
        global $pdo;
        bulkspeed_ensure_schema();
        $t = bulkspeed_texts();
        $p = bulkspeed_params($raw);
        if ($p['speed'] === null) {
            return ['error' => $t['noSpeed']];
        }
        if (!bulkspeed_who_set($p)) {
            return ['error' => $t['noWho']];
        }
        $panels = bulkspeed_panels_of($p);
        if (!$panels) {
            return ['error' => $p['panels'] ? $t['noPanels'] : $t['noRebecca']];
        }
        $p['pnames'] = $p['panels'] ? array_map(fn($r) => (string) $r['name_panel'], $panels) : [];
        // two admins pressing at once must not start two
        $got = (int) $pdo->query("SELECT GET_LOCK('ciro_bulkspeed_new', 5)")->fetchColumn();
        try {
            if (bulkspeed_active_job() !== null) {
                return ['error' => $t['busy']];
            }
            $pdo->prepare("INSERT INTO bulk_speed_job (params, state, origin, created_by, created_at) VALUES (?, 'queued', ?, ?, ?)")
                ->execute([json_encode($p, JSON_UNESCAPED_UNICODE), $origin === 'web' ? 'web' : 'bot', (string) $createdBy, time()]);
            return ['id' => (int) $pdo->lastInsertId()];
        } finally {
            if ($got === 1) {
                $pdo->query("SELECT RELEASE_LOCK('ciro_bulkspeed_new')");
            }
        }
    }
    // how many accounts of a run are where, and why the left-out ones were
    function bulkspeed_counts($jobId)
    {
        global $pdo;
        bulkspeed_ensure_schema();
        $c = ['total' => 0, 'pending' => 0, 'claimed' => 0, 'done' => 0, 'skipped' => 0, 'failed' => 0, 'cancelled' => 0, 'reasons' => []];
        $st = $pdo->prepare("SELECT state, reason, COUNT(*) n FROM bulk_speed_item WHERE job_id = ? GROUP BY state, reason");
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
    // «🚫 لغو»: what has not been reached is dropped, what was set stays. With
    // nobody working on the run right now it ends here and then; otherwise
    // whoever is ends it after the account they are on. false when there was
    // nothing running to cancel.
    function bulkspeed_cancel($jobId)
    {
        global $pdo;
        $job = bulkspeed_job($jobId);
        if ($job === null || !in_array($job['state'], ['queued', 'running'], true)) {
            return false;
        }
        $pdo->prepare("UPDATE bulk_speed_job SET cancel_req = 1 WHERE id = ?")->execute([(int) $jobId]);
        $pdo->prepare("UPDATE bulk_speed_item SET state = 'cancelled', reason = 'cancelled', updated_at = ? WHERE job_id = ? AND state = 'pending'")
            ->execute([time(), (int) $jobId]);
        if ((int) $pdo->query("SELECT GET_LOCK('ciro_bulkspeed', 0)")->fetchColumn() === 1) {
            try {
                bulkspeed_stale_claims($jobId);
                $job = bulkspeed_job($jobId);
                if ($job !== null) {
                    bulkspeed_finish($job);
                }
            } finally {
                $pdo->query("SELECT RELEASE_LOCK('ciro_bulkspeed')");
            }
        }
        return true;
    }
    // An account claimed by a run that never got to the end of it: a speed
    // sent twice is the same speed, so it goes back in line - or, once the run
    // is being cancelled, is not reached. (Only called while holding the run's
    // lock, so no run is on it now.)
    function bulkspeed_stale_claims($jobId)
    {
        global $pdo;
        $job = bulkspeed_job($jobId);
        $cancel = $job !== null && (int) $job['cancel_req'] === 1;
        $pdo->prepare("UPDATE bulk_speed_item SET state = ?, reason = ?, updated_at = ? WHERE job_id = ? AND state = 'claimed'")
            ->execute([$cancel ? 'cancelled' : 'pending', $cancel ? 'cancelled' : '', time(), (int) $jobId]);
    }
}

if (!function_exists('bulkspeed_prepare')) {
    // accounts into a run, many to a statement; one already in it stays as it is
    // (so an account of the panel the bot has a service for is that service)
    function bulkspeed_add_items($jobId, array $rows)
    {
        global $pdo;
        foreach (array_chunk($rows, 200) as $chunk) {
            $ph = [];
            $a = [];
            foreach ($chunk as $r) {
                $ph[] = '(?, ?, ?, ?, ?, ?, ?, ?)';
                array_push($a, (int) $jobId, bulkgift_ukey($r['panel'], $r['username']), (string) $r['panel'], (string) $r['username'], (string) $r['src'], (string) $r['grp'], $r['id_invoice'] ?? null, time());
            }
            $pdo->prepare("INSERT IGNORE INTO bulk_speed_item (job_id, ukey, panel, username, src, grp, id_invoice, updated_at) VALUES " . implode(', ', $ph))
                ->execute($a);
        }
    }
    // What a run is about, written down before anything is set: the bot's own
    // services of the kinds picked first, then - for «همه‌ی یوزرهای پنل» -
    // each panel's own list, a page at a time. Where it got to is kept, so a
    // long list can take more than one run. true once all of it is there.
    function bulkspeed_prepare(array $job, $deadline)
    {
        global $pdo;
        $p = bulkspeed_job_params($job);
        $prep = json_decode((string) ($job['prep'] ?? ''), true) ?: [];
        $notes = json_decode((string) ($job['notes'] ?? ''), true) ?: [];
        $byName = [];
        foreach (bulkspeed_all_panels() as $r) {
            $byName[(string) $r['name_panel']] = $r;
        }
        // the panels it was started for, those of them still there (and still Rebecca)
        $names = array_values(array_filter($p['panels'] ? $p['pnames'] : array_keys($byName), fn($n) => isset($byName[$n])));
        if (empty($prep['bot'])) {
            $st = bulkspeed_invoice_stmt($names);
            $rows = [];
            while ($st !== null && ($r = $st->fetch(PDO::FETCH_ASSOC))) {
                $g = bulkspeed_group_of($r);
                if (!bulkspeed_wants($p, $g)) {
                    continue;
                }
                $rows[] = ['panel' => (string) $r['Service_location'], 'username' => (string) $r['username'], 'src' => 'bot', 'grp' => $g, 'id_invoice' => (string) $r['id_invoice']];
                if (count($rows) >= 1000) {
                    bulkspeed_add_items($job['id'], $rows);
                    $rows = [];
                }
            }
            bulkspeed_add_items($job['id'], $rows);
            $prep['bot'] = 1;
            $pdo->prepare("UPDATE bulk_speed_job SET prep = ?, started_at = ? WHERE id = ?")->execute([json_encode($prep), time(), $job['id']]);
        }
        if ($p['who'] === 'panel') {
            for ($i = (int) ($prep['pi'] ?? 0); $i < count($names); $i++) {
                $panel = $byName[$names[$i]];
                $off = (int) ($prep['off'] ?? 0);
                while (true) {
                    if (time() >= $deadline) {
                        $prep['pi'] = $i;
                        $prep['off'] = $off;
                        $pdo->prepare("UPDATE bulk_speed_job SET prep = ?, notes = ? WHERE id = ?")->execute([json_encode($prep), json_encode($notes, JSON_UNESCAPED_UNICODE), $job['id']]);
                        return false;
                    }
                    $page = bulkgift_panel_page($panel, $off, 200);
                    if (isset($page['error'])) {
                        $notes[] = ['k' => 'listError', 'panel' => (string) $panel['name_panel'], 'error' => mb_substr($page['error'], 0, 150)];
                        break;
                    }
                    $rows = [];
                    foreach ($page['users'] as $u) {
                        $name = is_array($u) ? (string) ($u['username'] ?? '') : '';
                        if ($name !== '') {
                            $rows[] = ['panel' => (string) $panel['name_panel'], 'username' => $name, 'src' => 'panel', 'grp' => 'panel'];
                        }
                    }
                    bulkspeed_add_items($job['id'], $rows);
                    $n = count($page['users']);
                    $off += $n;
                    if ($n < 200 || ($page['total'] !== null && $off >= $page['total'])) {
                        break;
                    }
                }
                $prep['pi'] = $i + 1;
                $prep['off'] = 0;
                $pdo->prepare("UPDATE bulk_speed_job SET prep = ?, notes = ? WHERE id = ?")->execute([json_encode($prep), json_encode($notes, JSON_UNESCAPED_UNICODE), $job['id']]);
            }
        }
        $pdo->prepare("UPDATE bulk_speed_job SET prepared = 1, state = 'running', notes = ? WHERE id = ? AND state = 'queued'")
            ->execute([json_encode($notes, JSON_UNESCAPED_UNICODE), $job['id']]);
        return true;
    }
}

if (!function_exists('bulkspeed_work')) {
    // a change that got no answer may have been made anyway: the account's
    // speed now, read back
    function bulkspeed_is_set(array $panel, $username, $kbps)
    {
        $r = getuser_rebecca($username, $panel['name_panel']);
        $b = json_decode((string) ($r['body'] ?? ''), true);
        return is_array($b) && isset($b['username']) && (int) ($b['speed_limit_kbps'] ?? 0) === (int) $kbps;
    }
    // One account given the run's speed: [state, reason, info]
    function bulkspeed_set(array $p, array $item)
    {
        global $pdo;
        // one of the bot's services: still its owner's
        if ($item['src'] === 'bot') {
            $st = $pdo->prepare("SELECT Status FROM invoice WHERE id_invoice = ?");
            $st->execute([$item['id_invoice']]);
            $status = $st->fetchColumn();
            if ($status === false || !in_array((string) $status, bulkgift_live_statuses(), true)) {
                return ['skipped', 'missing', null];
            }
        }
        $panel = select("marzban_panel", "*", "name_panel", $item['panel'], "select");
        if (!is_array($panel) || ($panel['type'] ?? '') !== 'rebecca') {
            return ['failed', 'failed', 'panel not found'];
        }
        $kbps = speed_limit_kbps($p['speed']);
        $res = bulkgift_manage_panel()->Modifyuser($item['username'], $panel['name_panel'], ['speed_limit_kbps' => $kbps]);
        if (is_array($res) && !empty($res['status'])) {
            // a gateway's page in place of the account is no answer
            if (is_array($res['data'] ?? null) && isset($res['data']['username'])) {
                return ['done', 'done', null];
            }
            $msg = 'bad answer';
        } else {
            $msg = bulkgift_msg(is_array($res) ? ($res['msg'] ?? '') : '');
            if (strcasecmp($msg, 'user not found') === 0) {
                return ['skipped', 'missing', null];
            }
        }
        if (bulkgift_net_kind($msg) === 'maybe' && bulkspeed_is_set($panel, $item['username'], $kbps)) {
            return ['done', 'done', null];
        }
        return ['failed', 'failed', $msg];
    }
    // A run's next accounts, until its time is up: claimed one at a time, then
    // set, left out or failed - a network error waits and is tried again.
    // How many it got through.
    function bulkspeed_work(array $job, $deadline, $maxItems = null)
    {
        global $pdo;
        $p = bulkspeed_job_params($job);
        $n = 0;
        $delays = bulkgift_retry_delays();
        $next = $pdo->prepare("SELECT * FROM bulk_speed_item WHERE job_id = ? AND state = 'pending' AND next_at <= ? ORDER BY id LIMIT 1");
        $claim = $pdo->prepare("UPDATE bulk_speed_item SET state = 'claimed', updated_at = ? WHERE id = ? AND state = 'pending'");
        $save = $pdo->prepare("UPDATE bulk_speed_item SET state = ?, reason = ?, info = ?, updated_at = ? WHERE id = ?");
        $later = $pdo->prepare("UPDATE bulk_speed_item SET state = 'pending', tries = tries + 1, next_at = ?, info = ?, updated_at = ? WHERE id = ?");
        $stop = $pdo->prepare("SELECT cancel_req FROM bulk_speed_job WHERE id = ?");
        while (time() < $deadline && ($maxItems === null || $n < $maxItems)) {
            $stop->execute([$job['id']]);
            if ((int) $stop->fetchColumn() === 1) {
                break;
            }
            $next->execute([$job['id'], time()]);
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
                $r = bulkspeed_set($p, $item);
            } catch (Throwable $e) {
                error_log('bulk speed item ' . $item['id'] . ': ' . $e->getMessage());
                $r = ['failed', 'failed', bulkgift_msg($e->getMessage())];
            }
            $tries = (int) $item['tries'];
            if ($r[0] === 'failed' && bulkgift_net_kind((string) $r[2]) !== null && $tries < count($delays)) {
                $later->execute([time() + $delays[$tries], (string) $r[2], time(), $item['id']]);
            } else {
                $save->execute([$r[0], (string) $r[1], $r[2] === null ? null : (string) $r[2], time(), $item['id']]);
            }
            $n++;
            bulkspeed_progress($job['id']);
        }
        return $n;
    }
}

if (!function_exists('bulkspeed_progress_payload')) {
    // accounts waiting for another try after a network error
    function bulkspeed_waiting($jobId)
    {
        global $pdo;
        $st = $pdo->prepare("SELECT COUNT(*) FROM bulk_speed_item WHERE job_id = ? AND state = 'pending' AND tries > 0");
        $st->execute([(int) $jobId]);
        return (int) $st->fetchColumn();
    }
    // the admin's message while a run goes on, with its 🚫 لغو
    function bulkspeed_progress_payload(array $job)
    {
        $t = bulkspeed_texts();
        $p = bulkspeed_job_params($job);
        $lines = [$t['progressTitle'], '➖➖➖➖➖➖➖➖➖➖', '<blockquote>' . bulkspeed_rows($p) . '</blockquote>'];
        if (!(int) $job['prepared']) {
            $lines[] = $t['queued'];
        } else {
            $c = bulkspeed_counts($job['id']);
            $done = $c['total'] - $c['pending'] - $c['claimed'];
            $pct = $c['total'] > 0 ? (int) floor($done * 100 / $c['total']) : 100;
            $lines[] = strtr($t['progressLine'], ['{done}' => number_format($done), '{total}' => number_format($c['total']), '{bar}' => bulkgift_bar($pct), '{pct}' => $pct]);
            $lines[] = strtr($t['cntDone'], ['{n}' => number_format($c['done'])]);
            $lines[] = strtr($t['cntSkipped'], ['{n}' => number_format($c['skipped'])]);
            $lines[] = strtr($t['cntFailed'], ['{n}' => number_format($c['failed'])]);
            $wait = bulkspeed_waiting($job['id']);
            if ($wait > 0) {
                $lines[] = '';
                $lines[] = strtr($t['retryWaiting'], ['{n}' => number_format($wait)]);
            }
        }
        if ($job['origin'] === 'web') {
            $lines[] = '';
            $lines[] = $t['fromWeb'];
        }
        $kb = json_encode(['inline_keyboard' => [[['text' => $t['cancelBtn'], 'callback_data' => 'bsc:' . (int) $job['id'], 'style' => 'danger']]]]);
        return [implode("\n", $lines), $kb];
    }
    // the progress message brought up to date - not more than every 10
    // seconds, unless $force
    function bulkspeed_progress($jobId, $force = false)
    {
        global $pdo;
        if (!function_exists('Editmessagetext')) {
            return;
        }
        $job = bulkspeed_job($jobId);
        if ($job === null || (int) $job['admin_msg'] <= 0 || (string) $job['created_by'] === '') {
            return;
        }
        if (!$force && time() - (int) $job['shown_at'] < 10) {
            return;
        }
        [$text, $kb] = bulkspeed_progress_payload($job);
        // nothing new since the last one: Telegram would refuse it anyway
        $hash = sha1($text . $kb);
        if ($hash === (string) $job['shown_hash']) {
            return;
        }
        $pdo->prepare("UPDATE bulk_speed_job SET shown_at = ?, shown_hash = ? WHERE id = ?")->execute([time(), $hash, $job['id']]);
        Editmessagetext($job['created_by'], (int) $job['admin_msg'], $text, $kb, 'HTML');
    }
    // the message in the admin's chat that shows the run's progress
    function bulkspeed_set_admin_msg($jobId, $msgId)
    {
        global $pdo;
        $pdo->prepare("UPDATE bulk_speed_job SET admin_msg = ?, shown_at = ? WHERE id = ?")->execute([(int) $msgId, time(), (int) $jobId]);
    }
    // a run started on the web gets its progress message in the bot too, the
    // first time a run works on it (one started in the bot has one already)
    function bulkspeed_admin_msg($jobId)
    {
        global $pdo;
        $job = bulkspeed_job($jobId);
        if ($job === null || (int) $job['admin_msg'] !== 0 || (string) $job['created_by'] === '' || !function_exists('sendmessage')) {
            return;
        }
        [$text, $kb] = bulkspeed_progress_payload($job);
        $r = sendmessage($job['created_by'], $text, $kb, 'HTML');
        $id = (int) ($r['result']['message_id'] ?? 0);
        $pdo->prepare("UPDATE bulk_speed_job SET admin_msg = ?, shown_at = ? WHERE id = ?")->execute([$id > 0 ? $id : -1, time(), $job['id']]);
    }
    // The report the admin gets at the end: the settings, how many went where,
    // what failed and why, the notes on panels - kept inside one Telegram message.
    function bulkspeed_report(array $job, $listMax = 25)
    {
        global $pdo;
        $t = bulkspeed_texts();
        $p = bulkspeed_job_params($job);
        $c = bulkspeed_counts($job['id']);
        $parts = [($job['state'] === 'cancelled' ? $t['cancelledTitle'] : $t['doneTitle']) . "\n➖➖➖➖➖➖➖➖➖➖\n<blockquote>" . bulkspeed_rows($p) . '</blockquote>'];
        if ($c['total'] === 0) {
            $parts[] = $t['emptyResult'];
        } else {
            $line = fn($label, $n) => strtr($t['resultLine'], ['{label}' => $label, '{n}' => number_format($n)]);
            $res = [$t['resultHead'], $line((float) $p['speed'] > 0 ? $t['rDone'] : $t['rRemoved'], $c['done'])];
            if (!empty($c['reasons']['missing'])) {
                $res[] = $line($t['rMissing'], $c['reasons']['missing']);
            }
            if ($c['failed'] > 0) {
                $res[] = $line($t['rFailed'], $c['failed']);
            }
            if ($c['cancelled'] > 0) {
                $res[] = $line($t['rCancelled'], $c['cancelled']);
            }
            $parts[] = implode("\n", $res);
        }
        if ($c['failed'] > 0) {
            $st = $pdo->prepare("SELECT username, panel, info FROM bulk_speed_item WHERE job_id = ? AND state = 'failed' ORDER BY id");
            $st->execute([$job['id']]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            $out = array_map(fn($r) => bulkgift_item_line($r, true), array_slice($rows, 0, $listMax));
            if (count($rows) > $listMax) {
                $out[] = strtr($t['moreItems'], ['{n}' => number_format(count($rows) - $listMax)]);
            }
            $parts[] = $t['listFailed'] . "\n<blockquote>" . implode("\n", $out) . '</blockquote>';
            // the errors that were the network's: what they mean, and the 🔁
            if (array_filter($rows, fn($r) => bulkgift_net_kind($r['info']) !== null)) {
                $parts[] = $t['netNote'];
            }
        }
        $notes = json_decode((string) ($job['notes'] ?? ''), true) ?: [];
        if ($notes) {
            $out = [];
            foreach (array_slice($notes, 0, 10) as $n) {
                $out[] = strtr($t['noteListError'], ['{panel}' => aff_bidi((string) ($n['panel'] ?? ''), htmlspecialchars((string) ($n['panel'] ?? ''))), '{error}' => htmlspecialchars((string) ($n['error'] ?? ''))]);
            }
            $parts[] = $t['notesHead'] . "\n<blockquote>" . implode("\n", $out) . '</blockquote>';
        }
        $parts[] = bulkgift_duration_text($job) . ($job['origin'] === 'web' ? "\n" . $t['fromWeb'] : '');
        $text = implode("\n\n", $parts);
        // one message holds 4096 letters: a shorter list until it fits
        if (mb_strlen(strip_tags($text)) > 3900 && $listMax > 3) {
            return bulkspeed_report($job, (int) floor($listMax / 2));
        }
        return $text;
    }
    // The end of a run - all of it reached, or cancelled: the progress message
    // goes, the report comes. Ends a run once only; false if it had ended.
    function bulkspeed_finish(array $job)
    {
        global $pdo;
        $job = bulkspeed_job($job['id']);
        if ($job === null) {
            return false;
        }
        $cancelled = (int) $job['cancel_req'] === 1;
        if ($cancelled) {
            $pdo->prepare("UPDATE bulk_speed_item SET state = 'cancelled', reason = 'cancelled', updated_at = ? WHERE job_id = ? AND state = 'pending'")
                ->execute([time(), $job['id']]);
        }
        $st = $pdo->prepare("UPDATE bulk_speed_job SET state = ?, finished_at = ? WHERE id = ? AND state IN ('queued', 'running')");
        $st->execute([$cancelled ? 'cancelled' : 'done', time(), $job['id']]);
        if ($st->rowCount() !== 1) {
            return false;
        }
        $job = bulkspeed_job($job['id']);
        $to = (string) $job['created_by'];
        if ($to !== '' && function_exists('sendmessage')) {
            if ((int) $job['admin_msg'] > 0) {
                deletemessage($to, (int) $job['admin_msg']);
            }
            sendmessage($to, bulkspeed_report($job), bulkspeed_report_kb($job), 'HTML');
        }
        return true;
    }
    // 🔁 under the report while some failed: a speed sent again is the same
    // speed, so every one of them can be tried again
    function bulkspeed_failed_count($jobId)
    {
        global $pdo;
        bulkspeed_ensure_schema();
        $st = $pdo->prepare("SELECT COUNT(*) FROM bulk_speed_item WHERE job_id = ? AND state = 'failed'");
        $st->execute([(int) $jobId]);
        return (int) $st->fetchColumn();
    }
    function bulkspeed_report_kb(array $job)
    {
        if ($job['state'] !== 'done') {
            return null;
        }
        $n = bulkspeed_failed_count($job['id']);
        return $n > 0 ? json_encode(['inline_keyboard' => [[bulkgift_btn(strtr(bulkspeed_texts()['retryBtn'], ['{n}' => number_format($n)]), 'bsr:' . (int) $job['id'])]]]) : null;
    }
    // The run goes on with just its failed accounts - the others are not
    // touched again: ['n' => how many] or ['error' => why not]
    function bulkspeed_retry($jobId)
    {
        global $pdo;
        $t = bulkspeed_texts();
        $job = bulkspeed_job((int) $jobId);
        if ($job === null || $job['state'] !== 'done') {
            return ['error' => $t['retryNone']];
        }
        $got = (int) $pdo->query("SELECT GET_LOCK('ciro_bulkspeed_new', 5)")->fetchColumn();
        try {
            if (bulkspeed_active_job() !== null) {
                return ['error' => $t['busy']];
            }
            $n = bulkspeed_failed_count($job['id']);
            if ($n === 0) {
                return ['error' => $t['retryNone']];
            }
            $now = time();
            $pdo->prepare("UPDATE bulk_speed_item SET state = 'pending', reason = '', tries = 0, next_at = 0, updated_at = ? WHERE job_id = ? AND state = 'failed'")
                ->execute([$now, $job['id']]);
            // its progress message and its report come again, for this pass
            $pdo->prepare("UPDATE bulk_speed_job SET state = 'running', cancel_req = 0, admin_msg = 0, shown_at = 0, shown_hash = '', started_at = ?, finished_at = 0 WHERE id = ? AND state = 'done'")
                ->execute([$now, $job['id']]);
            return ['n' => $n];
        } finally {
            if ($got === 1) {
                $pdo->query("SELECT RELEASE_LOCK('ciro_bulkspeed_new')");
            }
        }
    }
}

if (!function_exists('bulkspeed_cron')) {
    // One run's turn: [what became of it, how many accounts it got through]:
    // 'preparing' | 'more' | 'finished'
    function bulkspeed_cron_job(array $job, $deadline, $maxItems = null)
    {
        global $pdo;
        bulkspeed_stale_claims($job['id']);
        if (!(int) $job['prepared'] && !(int) $job['cancel_req']) {
            bulkspeed_admin_msg($job['id']);
            if (!bulkspeed_prepare($job, $deadline)) {
                bulkspeed_progress($job['id'], true);
                return ['preparing', 0];
            }
        }
        $job = bulkspeed_job($job['id']);
        bulkspeed_admin_msg($job['id']);
        $worked = bulkspeed_work($job, $deadline, $maxItems);
        $job = bulkspeed_job($job['id']);
        $st = $pdo->prepare("SELECT COUNT(*) FROM bulk_speed_item WHERE job_id = ? AND state = 'pending'");
        $st->execute([$job['id']]);
        if ((int) $job['cancel_req'] === 1 || (int) $st->fetchColumn() === 0) {
            bulkspeed_finish($job);
            return ['finished', $worked];
        }
        bulkspeed_progress($job['id'], true);
        return ['more', $worked];
    }
    // cronbot/gift.php, after 🎁's: works on the run going on - one worker at a
    // time (a MySQL lock), for at most $budget seconds ($maxItems: for tests) -
    // and hands what is left straight on to the next request.
    // 'idle' | 'busy' | 'preparing' | 'more' | 'finished'
    function bulkspeed_cron($budget = null, $maxItems = null)
    {
        global $pdo;
        bulkspeed_ensure_schema();
        $job = bulkspeed_active_job();
        if ($job === null) {
            return 'idle';
        }
        if ((int) $pdo->query("SELECT GET_LOCK('ciro_bulkspeed', 0)")->fetchColumn() !== 1) {
            return 'busy';
        }
        $kick = false;
        try {
            [$r, $worked] = bulkspeed_cron_job($job, time() + ($budget ?? bulkgift_budget()), $maxItems);
            $kick = $r === 'preparing' || ($r === 'more' && $worked > 0);
            return $r;
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('ciro_bulkspeed')");
            if ($kick) {
                bulkgift_kick();
            }
        }
    }
}

if (!function_exists('bulkspeed_step1_payload')) {
    // the settings an admin is putting together in the bot, kept apart from
    // the steps of every other flow
    function bulkspeed_draft($adminId)
    {
        global $pdo;
        bulkspeed_ensure_schema();
        $st = $pdo->prepare("SELECT params FROM bulk_speed_draft WHERE admin_id = ?");
        $st->execute([(string) $adminId]);
        $raw = $st->fetchColumn();
        return bulkspeed_params($raw === false ? [] : json_decode((string) $raw, true));
    }
    function bulkspeed_draft_save($adminId, array $p)
    {
        global $pdo;
        bulkspeed_ensure_schema();
        $pdo->prepare("INSERT INTO bulk_speed_draft (admin_id, params, updated_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE params = VALUES(params), updated_at = VALUES(updated_at)")
            ->execute([(string) $adminId, json_encode(bulkspeed_params($p), JSON_UNESCAPED_UNICODE), time()]);
    }
    // the message the speed is asked in, to take it away once it is answered
    function bulkspeed_draft_ask($adminId, $msgId = null)
    {
        global $pdo;
        bulkspeed_ensure_schema();
        if ($msgId !== null) {
            // an admin with no draft yet (an old screen's button) gets an empty one
            $pdo->prepare("INSERT INTO bulk_speed_draft (admin_id, params, ask_msg, updated_at) VALUES (?, '{}', ?, ?) ON DUPLICATE KEY UPDATE ask_msg = VALUES(ask_msg)")->execute([(string) $adminId, (int) $msgId, time()]);
            return (int) $msgId;
        }
        $st = $pdo->prepare("SELECT ask_msg FROM bulk_speed_draft WHERE admin_id = ?");
        $st->execute([(string) $adminId]);
        return (int) $st->fetchColumn();
    }
    // the settings as they stand, short - the start button carries it, so what
    // starts is what the admin last saw
    function bulkspeed_sig(array $p)
    {
        unset($p['pnames']);
        return hash('crc32b', json_encode($p));
    }
    // «🚀 سرعت گروهی · مرحله ۲ از ۴»
    function bulkspeed_title($i)
    {
        $t = bulkspeed_texts();
        return $t['title'] . strtr($t['stepOf'], ['{i}' => $i, '{n}' => 4]);
    }
    // step 1: how fast - typed, or ♾ to take the limit off; 🔙 to 🚀 مدیریت
    // سرعت it was opened from
    function bulkspeed_step1_payload(array $p)
    {
        $t = bulkspeed_texts();
        $rows = [
            [bulkgift_btn($t['removeBtn'], 'bsp:v0')],
            [bulkgift_btn($t['helpBtn'], 'bsp:help', null), bulkgift_btn($t['backBtn'], 'bsh', 'danger')],
        ];
        $text = bulkspeed_title(1) . "\n➖➖➖➖➖➖➖➖➖➖\n"
            . ($p['speed'] !== null ? '<blockquote>' . bulkspeed_rows($p, 'speed') . "</blockquote>\n" : '')
            . $t['askSpeed'];
        return [$text, json_encode(['inline_keyboard' => $rows])];
    }
    // step 2: whose - kinds of the bot's services ticked together, or all of
    // the bot's, or every account on the panels
    function bulkspeed_who_payload(array $p)
    {
        $t = bulkspeed_texts();
        $g = fn($k) => bulkgift_pick($p['who'] === 'groups' && in_array($k, $p['groups'], true), $t['g' . ucfirst($k)], 'bsp:g:' . $k);
        $rows = [
            [$g('sale'), $g('payg')],
            [$g('ref'), $g('test')],
            [bulkgift_pick($p['who'] === 'bot', $t['wBot'], 'bsp:w:bot')],
            [bulkgift_pick($p['who'] === 'panel', $t['wPanel'], 'bsp:w:panel')],
            [bulkgift_btn($t['nextBtn'], 'bsp:s3', 'success')],
            [bulkgift_btn($t['backBtn'], 'bsp:s1', 'danger')],
        ];
        return [bulkspeed_title(2) . "\n➖➖➖➖➖➖➖➖➖➖\n<blockquote>" . bulkspeed_rows($p, 'speed') . "</blockquote>\n" . $t['whoAsk'], json_encode(['inline_keyboard' => $rows])];
    }
    // step 3: which Rebecca panels - none ticked is every one of them
    function bulkspeed_panels_payload(array $p)
    {
        $t = bulkspeed_texts();
        $rows = [[bulkgift_pick(!$p['panels'], $t['panelsAll'], 'bsp:p:all')]];
        $pair = [];
        // Telegram refuses a message with more than 100 buttons
        foreach (array_slice(bulkspeed_all_panels(), 0, 90) as $r) {
            $pair[] = bulkgift_pick(in_array((string) $r['code_panel'], $p['panels'], true), (string) $r['name_panel'], 'bsp:p:' . (int) $r['id']);
            if (count($pair) === 2) {
                $rows[] = $pair;
                $pair = [];
            }
        }
        if ($pair) {
            $rows[] = $pair;
        }
        $rows[] = [bulkgift_btn($t['nextBtn'], 'bsp:s4', 'success')];
        $rows[] = [bulkgift_btn($t['backBtn'], 'bsp:s2', 'danger')];
        return [bulkspeed_title(3) . "\n➖➖➖➖➖➖➖➖➖➖\n<blockquote>" . bulkspeed_rows($p, 'who') . "</blockquote>\n" . $t['panelsAsk'], json_encode(['inline_keyboard' => $rows])];
    }
    // step 4, the summary: what, to about how many, everything it is set to in
    // bold, and 🚀 - which carries the settings' crc, so what starts is what
    // this screen says. Nothing found: no 🚀.
    function bulkspeed_summary_payload(array $p)
    {
        $t = bulkspeed_texts();
        $pv = bulkspeed_preview($p);
        $remove = (float) $p['speed'] <= 0;
        $vars = ['{speed}' => bulkspeed_speed_label($p), '{n}' => number_format($pv['n'])];
        $notes = [];
        if ($pv['panels'] === 0) {
            // the panels ticked are gone, or are not Rebecca any more
            $can = false;
            $line = $t['sumNoPanels'];
        } elseif ($p['who'] === 'panel') {
            $can = true;
            $line = strtr($remove ? $t['sumLinePanelRemove'] : $t['sumLinePanelSet'], $vars);
            $notes[] = $t['sumPanelNote'];
        } else {
            $can = $pv['n'] > 0;
            $line = $can ? strtr($remove ? $t['sumLineRemove'] : $t['sumLineSet'], $vars) : $t['sumEmpty'];
        }
        if ($can) {
            if (!$remove) {
                $notes[] = $t['sumRenewNote'];
            }
            $notes[] = $t['sumExact'];
        }
        $text = bulkspeed_title(4) . "\n➖➖➖➖➖➖➖➖➖➖\n" . $line
            . "\n<blockquote>" . bulkspeed_rows($p) . '</blockquote>'
            . ($notes ? "\n<blockquote>" . implode("\n", $notes) . '</blockquote>' : '');
        $rows = [];
        if ($can) {
            $rows[] = [bulkgift_btn($t['startBtn'], 'bsp:go:' . bulkspeed_sig($p), 'success')];
        }
        $rows[] = [bulkgift_btn($t['helpBtn'], 'bsp:help', null)];
        $rows[] = [bulkgift_btn($t['backBtn'], 'bsp:s3', 'danger'), bulkgift_btn($t['cancelDraftBtn'], 'bsp:x', 'danger')];
        return [$text, json_encode(['inline_keyboard' => $rows])];
    }
    // 📖 how it all goes, in one message; 🔙 to where it was opened from
    function bulkspeed_help_payload($back)
    {
        $t = bulkspeed_texts();
        return [$t['help'], json_encode(['inline_keyboard' => [[bulkgift_btn($t['backBtn'], $back, 'danger')]]])];
    }
}

if (!function_exists('bulkspeed_hub_payload')) {
    // 🏬 ← 🚀 مدیریت سرعت: every speed setting in one place. The first screen
    // says what each is set to; 🛍 / 🧪 / 🎁 open a list to change them, and
    // 🚀 سرعت گروهی is the run above (what is there now). The settings stay
    // where they were too (✏️ ویرایش محصول, 🎁 اکانت تست, 👥 ← 🎁 کانفیگ رایگان
    // با دعوت) - it is the same data, so a change in one shows in the other.
    // Rebecca panels only: nothing else can hold a speed.

    // 🛍 the products that can hold one: on a Rebecca panel, or on «همه پنل ها»
    // - every user type, panels in the bot's order and «همه پنل ها» last
    function bulkspeed_hub_products()
    {
        global $pdo;
        $names = array_map(fn($r) => (string) $r['name_panel'], bulkspeed_all_panels());
        if (!$names) {
            return [];
        }
        $st = $pdo->prepare("SELECT * FROM product WHERE Location = '/all' OR Location IN (" . implode(', ', array_fill(0, count($names), '?')) . ") ORDER BY (Location = '/all'), Location, id");
        $st->execute($names);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
    // 🎁 each language whose free config is made on a Rebecca panel:
    // [lang => ['panel' => its row, 'speed' => Mbps]]
    function bulkspeed_hub_refs()
    {
        $out = [];
        foreach (panel_langs() as $l) {
            $cfg = affrw_cfg($l);
            $panel = affrw_panel($cfg);
            if (is_array($panel) && ($panel['type'] ?? '') === 'rebecca') {
                $out[$l] = ['panel' => $panel, 'speed' => (float) $cfg['speed']];
            }
        }
        return $out;
    }
    // «10 Mbps», or the words for none
    function bulkspeed_hub_label($mbps)
    {
        return speed_limit_label($mbps, bulkspeed_texts()['hubNone']);
    }
    // the same for a button: «🚀 10 Mbps» or ♾
    function bulkspeed_hub_short($mbps)
    {
        return (float) $mbps > 0 ? '🚀 ' . speed_limit_store($mbps) . ' Mbps' : '♾';
    }
    // a name in a line of text
    function bulkspeed_hub_name($n)
    {
        return aff_bidi((string) $n, htmlspecialchars((string) $n));
    }
    // «3 تا با سرعت · 5 تا بدون محدودیت»
    function bulkspeed_hub_count(array $speeds)
    {
        $with = count(array_filter($speeds, fn($s) => (float) $s > 0));
        return strtr(bulkspeed_texts()['hubCount'], ['{with}' => number_format($with), '{none}' => number_format(count($speeds) - $with)]);
    }
    // the first screen: what every speed is set to, bold in a quote, and the
    // four ways in
    function bulkspeed_hub_payload()
    {
        $t = bulkspeed_texts();
        $rows = [];
        $prod = bulkspeed_hub_products();
        $rows[] = strtr($t['hubRowProducts'], ['{v}' => $prod ? bulkspeed_hub_count(array_column($prod, 'speed_limit')) : $t['hubNoProducts']]);
        $panels = bulkspeed_all_panels();
        // a few panels by name, many as a count
        $rows[] = strtr($t['hubRowTests'], ['{v}' => count($panels) <= 3
            ? implode(' · ', array_map(fn($r) => bulkspeed_hub_name($r['name_panel']) . ' ' . bulkspeed_hub_label($r['speed_usertest'] ?? 0), $panels))
            : bulkspeed_hub_count(array_column($panels, 'speed_usertest'))]);
        $refs = bulkspeed_hub_refs();
        if ($refs) {
            $rows[] = strtr($t['hubRowRefs'], ['{v}' => implode(' · ', array_map(fn($l, $r) => bulkgift_lang_name($l) . ' ' . bulkspeed_hub_label($r['speed']), array_keys($refs), $refs))]);
        }
        $kb = [
            [bulkgift_btn($t['hubProductsBtn'], 'bsh:pl:0')],
            [bulkgift_btn($t['hubTestsBtn'], 'bsh:tl')],
        ];
        if ($refs) {
            $kb[] = [bulkgift_btn($t['hubRefsBtn'], 'bsh:rl')];
        }
        $kb[] = [bulkgift_btn($t['groupBtn'], 'bsh:grp', 'success')];
        $kb[] = [bulkgift_btn($t['closeBtn'], 'bsh:x', 'danger')];
        return [$t['hubTitle'] . "\n➖➖➖➖➖➖➖➖➖➖\n<blockquote>" . implode("\n", $rows) . "</blockquote>\n" . $t['hubIntro'], json_encode(['inline_keyboard' => $kb])];
    }
    // 🛍 one page of the products, a button each with its speed; $note: a line
    // about what was just done
    function bulkspeed_hub_products_payload($page = 0, $note = '')
    {
        $t = bulkspeed_texts();
        $k = panel_texts()['keyboard'];
        $prod = bulkspeed_hub_products();
        $per = 25;
        $pages = max(1, (int) ceil(count($prod) / $per));
        $page = max(0, min($pages - 1, (int) $page));
        // the panel is named when it could be mistaken for another
        $several = count(bulkspeed_all_panels()) > 1;
        $agents = ['n' => $k['normalAgent'], 'n2' => $k['advancedAgent']];
        $kb = [];
        foreach (array_slice($prod, $page * $per, $per) as $r) {
            $label = (string) $r['name_product'];
            if (isset($agents[$r['agent']])) {
                $label .= ' · ' . $agents[$r['agent']];
            }
            if ($r['Location'] === '/all') {
                $label .= ' · ' . $k['allPanels'];
            } elseif ($several) {
                $label .= ' · ' . $r['Location'];
            }
            $kb[] = [bulkgift_btn($label . ' · ' . bulkspeed_hub_short($r['speed_limit'] ?? 0), 'bsh:a:' . bulkspeed_hub_code(['k' => 'p', 'id' => $r['id'], 'pg' => $page]))];
        }
        if ($pages > 1) {
            $nav = [];
            if ($page > 0) {
                $nav[] = bulkgift_btn('◀️', 'bsh:pl:' . ($page - 1), null);
            }
            $nav[] = bulkgift_btn(strtr($t['hubPage'], ['{i}' => $page + 1, '{n}' => $pages]), 'bsh:pl:' . $page, null);
            if ($page < $pages - 1) {
                $nav[] = bulkgift_btn('▶️', 'bsh:pl:' . ($page + 1), null);
            }
            $kb[] = $nav;
        }
        $kb[] = [bulkgift_btn($t['backBtn'], 'bsh', 'danger')];
        $text = $t['hubProductsTitle'] . "\n➖➖➖➖➖➖➖➖➖➖\n" . ($note !== '' ? $note . "\n\n" : '');
        if (!$prod) {
            return [$text . $t['hubProductsEmpty'], json_encode(['inline_keyboard' => $kb])];
        }
        $with = count(array_filter($prod, fn($r) => (float) ($r['speed_limit'] ?? 0) > 0));
        $text .= $t['hubProductsAsk'] . "\n<blockquote>" . strtr($t['hubProductsStat'], ['{with}' => number_format($with), '{none}' => number_format(count($prod) - $with)]) . "</blockquote>\n" . $t['hubProductsNote'];
        return [$text, json_encode(['inline_keyboard' => $kb])];
    }
    // 🧪 each Rebecca panel, a button with its test account's speed
    function bulkspeed_hub_tests_payload($note = '')
    {
        $t = bulkspeed_texts();
        $kb = [];
        foreach (array_slice(bulkspeed_all_panels(), 0, 90) as $r) {
            $kb[] = [bulkgift_btn($r['name_panel'] . ' · ' . bulkspeed_hub_short($r['speed_usertest'] ?? 0), 'bsh:a:' . bulkspeed_hub_code(['k' => 't', 'id' => $r['id']]))];
        }
        $kb[] = [bulkgift_btn($t['backBtn'], 'bsh', 'danger')];
        return [$t['hubTestsTitle'] . "\n➖➖➖➖➖➖➖➖➖➖\n" . ($note !== '' ? $note . "\n\n" : '') . $t['hubTestsAsk'], json_encode(['inline_keyboard' => $kb])];
    }
    // 🎁 each language whose free config is made on a Rebecca panel
    function bulkspeed_hub_refs_payload($note = '')
    {
        $t = bulkspeed_texts();
        $kb = [];
        foreach (bulkspeed_hub_refs() as $l => $r) {
            $kb[] = [bulkgift_btn(bulkgift_lang_name($l) . ' · ' . $r['panel']['name_panel'] . ' · ' . bulkspeed_hub_short($r['speed']), 'bsh:a:' . bulkspeed_hub_code(['k' => 'r', 'lang' => $l]))];
        }
        $kb[] = [bulkgift_btn($t['backBtn'], 'bsh', 'danger')];
        return [$t['hubRefsTitle'] . "\n➖➖➖➖➖➖➖➖➖➖\n" . ($note !== '' ? $note . "\n\n" : '') . $t['hubRefsAsk'], json_encode(['inline_keyboard' => $kb])];
    }
    // What a tap on a list picks: ['k' => 'p', 'id' => product, 'pg' => the page
    // of the list] | ['k' => 't', 'id' => panel] | ['k' => 'r', 'lang' => fa].
    // Anything else (an older screen's, a thing deleted since): null.
    function bulkspeed_hub_target($raw)
    {
        if (!is_array($raw)) {
            return null;
        }
        if (($raw['k'] ?? '') === 'p' && ctype_digit((string) ($raw['id'] ?? ''))) {
            return ['k' => 'p', 'id' => (int) $raw['id'], 'pg' => max(0, (int) ($raw['pg'] ?? 0))];
        }
        if (($raw['k'] ?? '') === 't' && ctype_digit((string) ($raw['id'] ?? ''))) {
            return ['k' => 't', 'id' => (int) $raw['id']];
        }
        if (($raw['k'] ?? '') === 'r' && in_array($raw['lang'] ?? '', panel_langs(), true)) {
            return ['k' => 'r', 'lang' => (string) $raw['lang']];
        }
        return null;
    }
    // A target as the short code a button carries: p123_2 (product 123, on page
    // 2 of its list), t5 (panel 5), rfa (language fa) - so a button always acts
    // on the thing it was drawn for, however many questions are in the chat
    function bulkspeed_hub_code(array $target)
    {
        if ($target['k'] === 'p') {
            return 'p' . (int) $target['id'] . '_' . (int) ($target['pg'] ?? 0);
        }
        return $target['k'] === 't' ? 't' . (int) $target['id'] : 'r' . $target['lang'];
    }
    // ...and back; null for anything that is not one
    function bulkspeed_hub_decode($code)
    {
        $code = (string) $code;
        $pick = explode('_', substr($code, 1));
        $kind = $code === '' ? '' : $code[0];
        if ($kind === 'p') {
            return bulkspeed_hub_target(['k' => 'p', 'id' => $pick[0], 'pg' => $pick[1] ?? 0]);
        }
        if ($kind === 't') {
            return bulkspeed_hub_target(['k' => 't', 'id' => $pick[0]]);
        }
        return $kind === 'r' ? bulkspeed_hub_target(['k' => 'r', 'lang' => $pick[0]]) : null;
    }
    // The thing a target names, as it is now: ['name' => html, 'facts' => the
    // lines the question shows, 'speed' => Mbps, 'title' => , 'back' => the
    // list's callback] - or null when it is gone, or no Rebecca panel holds it.
    function bulkspeed_hub_find(array $target)
    {
        $t = bulkspeed_texts();
        if ($target['k'] === 'p') {
            $r = select("product", "*", "id", $target['id'], "select");
            if (!is_array($r) || !speed_limit_applies($r['Location'])) {
                return null;
            }
            return [
                'name' => bulkspeed_hub_name($r['name_product']), 'speed' => (float) ($r['speed_limit'] ?? 0),
                'title' => $t['hubProductsTitle'], 'back' => 'bsh:pl:' . (int) ($target['pg'] ?? 0),
                'facts' => [strtr($t['rowProduct'], ['{v}' => bulkspeed_hub_name($r['name_product'])]), strtr($t['rowPanel'], ['{v}' => $r['Location'] === '/all' ? panel_texts()['keyboard']['allPanels'] : bulkspeed_hub_name($r['Location'])])],
            ];
        }
        if ($target['k'] === 't') {
            $r = select("marzban_panel", "*", "id", $target['id'], "select");
            if (!is_array($r) || ($r['type'] ?? '') !== 'rebecca') {
                return null;
            }
            return [
                'name' => bulkspeed_hub_name($r['name_panel']), 'speed' => (float) ($r['speed_usertest'] ?? 0),
                'title' => $t['hubTestsTitle'], 'back' => 'bsh:tl',
                'facts' => [strtr($t['rowPanel'], ['{v}' => bulkspeed_hub_name($r['name_panel'])])],
            ];
        }
        $refs = bulkspeed_hub_refs();
        if (!isset($refs[$target['lang']])) {
            return null;
        }
        $r = $refs[$target['lang']];
        return [
            'name' => bulkgift_lang_name($target['lang']), 'speed' => $r['speed'],
            'title' => $t['hubRefsTitle'], 'back' => 'bsh:rl',
            'facts' => [strtr($t['rowLangOne'], ['{v}' => bulkgift_lang_name($target['lang'])]), strtr($t['rowPanel'], ['{v}' => bulkspeed_hub_name($r['panel']['name_panel'])])],
        ];
    }
    // the question for one thing's speed: what it is, what it is now, how to
    // answer; ♾ for none and a red 🔙 to its list
    function bulkspeed_hub_ask_payload(array $target)
    {
        $t = bulkspeed_texts();
        $f = bulkspeed_hub_find($target);
        if ($f === null) {
            return null;
        }
        $facts = array_merge($f['facts'], [strtr($t['rowNow'], ['{v}' => bulkspeed_hub_label($f['speed'])])]);
        $text = $f['title'] . "\n➖➖➖➖➖➖➖➖➖➖\n<blockquote>" . implode("\n", $facts) . "</blockquote>\n" . $t['hubAsk'];
        return [$text, json_encode(['inline_keyboard' => [[bulkgift_btn($t['zeroBtn'], 'bsh:v0:' . bulkspeed_hub_code($target))], [bulkgift_btn($t['backBtn'], $f['back'], 'danger')]]])];
    }
    // The list a target belongs to, with $note on top
    function bulkspeed_hub_list_payload(array $target, $note = '')
    {
        if ($target['k'] === 'p') {
            return bulkspeed_hub_products_payload($target['pg'] ?? 0, $note);
        }
        return $target['k'] === 't' ? bulkspeed_hub_tests_payload($note) : bulkspeed_hub_refs_payload($note);
    }
    // Writes one thing's speed (Mbps, 0 = none) - the very columns and settings
    // the other screens read; false when it is gone since, or no Rebecca panel
    // holds it (the bot's and the web page's saves both go through here)
    function bulkspeed_hub_write(array $target, $mbps)
    {
        global $pdo;
        if (bulkspeed_hub_find($target) === null) {
            return false;
        }
        $store = speed_limit_store($mbps);
        if ($target['k'] === 'p') {
            $pdo->prepare("UPDATE product SET speed_limit = ? WHERE id = ?")->execute([$store, (int) $target['id']]);
            clearSelectCache('product');
        } elseif ($target['k'] === 't') {
            $pdo->prepare("UPDATE marzban_panel SET speed_usertest = ? WHERE id = ?")->execute([$store, (int) $target['id']]);
            clearSelectCache('marzban_panel');
        } else {
            feature_setting_set('affrw_speed', $target['lang'], $store);
        }
        return true;
    }
    // Saves one thing's speed and says so: [text, keyboard] of its list with
    // the news on top - or, when the thing is gone since, the list with why.
    function bulkspeed_hub_save(array $target, $mbps)
    {
        $t = bulkspeed_texts();
        $f = bulkspeed_hub_find($target);
        if ($f === null || !bulkspeed_hub_write($target, $mbps)) {
            return bulkspeed_hub_list_payload($target, $t['hubGone']);
        }
        return bulkspeed_hub_list_payload($target, strtr($t['hubSaved'], ['{name}' => $f['name'], '{speed}' => bulkspeed_hub_label($mbps)]));
    }
}
