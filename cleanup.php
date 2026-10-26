<?php
// 🧹 پاکسازی و ریست: the maintenance tools an owner needs to start a shop
// clean - after tests, or when a copy of an old bot becomes a new one. Used by
// the bot (👤 مدیریت کاربر ← 🧹) and the web panel (ابزارها ← 🧹, and the three
// reset buttons of the dashboard); both call the same functions here.
//
//   💰 balances   every user's balance (and the wallets waiting in other
//                 currencies) to zero. The old figures are saved first, so the
//                 last reset can be given back (clean_balance_undo).
//   👥 referrals  everybody's زیرمجموعه to zero: who invited whom, the counts,
//                 the 🎁 free-config progress and the join gifts not yet taken.
//   🗑 services   every service the BOT sold (the invoices - not the accounts
//                 someone made by hand in a panel) taken out of its panel and
//                 marked removed here. Done by a run, like 🎁 حجم یا زمان
//                 گروهی: the list first, then one account at a time by
//                 cronbot/gift.php, so a panel that answers slowly or a long
//                 list never holds a request (or Telegram's webhook) open.
//   📊 counters   the dashboard's income / today's transactions / active
//                 services start counting from now. Nothing is deleted: the
//                 payments and orders stay, only the figure restarts.
//
// Every tool takes a SCOPE: one language tab ('fa', 'en') or '' for all of them.
// Required by function.php after bulkspeed.php, whose bulkgift_kick() it uses.

if (!function_exists('clean_ensure_schema')) {
    // clean_snapshot: what a reset took away, to give it back. clean_job /
    // clean_item: a service-removal run and one row for every service it reaches.
    function clean_ensure_schema()
    {
        static $done = false;
        global $pdo;
        if ($done || !($pdo instanceof PDO)) {
            return;
        }
        $done = true;
        $pdo->query("CREATE TABLE IF NOT EXISTS clean_snapshot (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kind VARCHAR(20) NOT NULL,
            scope VARCHAR(10) NOT NULL DEFAULT '',
            created_at INT NOT NULL,
            by_user VARCHAR(40) NULL,
            users INT NOT NULL DEFAULT 0,
            payload LONGTEXT NULL,
            undone TINYINT NOT NULL DEFAULT 0
        ) DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->query("CREATE TABLE IF NOT EXISTS clean_job (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kind VARCHAR(20) NOT NULL DEFAULT 'svc',
            scope VARCHAR(10) NOT NULL DEFAULT '',
            state VARCHAR(12) NOT NULL DEFAULT 'running',
            total INT NOT NULL DEFAULT 0,
            by_user VARCHAR(40) NULL,
            chat_id VARCHAR(40) NULL,
            created_at INT NOT NULL,
            finished_at INT NULL,
            notified TINYINT NOT NULL DEFAULT 0
        ) DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->query("CREATE TABLE IF NOT EXISTS clean_item (
            id INT AUTO_INCREMENT PRIMARY KEY,
            job_id INT NOT NULL,
            invoice_id VARCHAR(190) NOT NULL,
            username VARCHAR(190) NULL,
            location VARCHAR(190) NULL,
            state VARCHAR(8) NOT NULL DEFAULT 'wait',
            kind VARCHAR(8) NULL,
            why VARCHAR(255) NULL,
            tries INT NOT NULL DEFAULT 0,
            retry_after INT NOT NULL DEFAULT 0,
            KEY job_state (job_id, state)
        ) DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        // a table made before: how each removal ended (see clean_panel_remove)
        addFieldToTable('clean_item', 'kind', null, 'VARCHAR(8) NULL');
    }

    // a scope as it is kept: '' (all languages) or a language the bot has
    function clean_scope($scope)
    {
        $scope = (string) $scope;
        return in_array($scope, panel_langs(), true) ? $scope : '';
    }

    // the users of a scope, as SQL: [condition, positional params]
    function clean_user_where($scope)
    {
        $scope = clean_scope($scope);
        return $scope === '' ? ['1 = 1', []] : user_lang_where($scope);
    }

    function clean_count($sql, array $params = [])
    {
        global $pdo;
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return (int) $st->fetchColumn();
    }

    // ---------------------------------------------------------------- 💰
    // «has money in some wallet»: the active one (Balance) or a waiting one
    function clean_balance_cond()
    {
        return "(Balance != 0 OR (wallets IS NOT NULL AND wallets NOT IN ('', '{}', '[]', 'null')))";
    }

    // what a balance reset of the scope would take away
    function clean_balance_preview($scope)
    {
        global $pdo;
        wallet_ensure_schema();
        [$w, $p] = clean_user_where($scope);
        $c = clean_balance_cond();
        $st = $pdo->prepare("SELECT COUNT(*) n, COALESCE(SUM(Balance > 0), 0) pos, COALESCE(SUM(Balance < 0), 0) neg,
            COALESCE(SUM(wallets IS NOT NULL AND wallets NOT IN ('', '{}', '[]', 'null')), 0) stash,
            COALESCE(SUM(Balance), 0) total FROM user WHERE {$w} AND {$c}");
        $st->execute($p);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'users' => (int) ($r['n'] ?? 0),
            'positive' => (int) ($r['pos'] ?? 0),
            'negative' => (int) ($r['neg'] ?? 0),
            'stash' => (int) ($r['stash'] ?? 0),
            'sum' => round((float) ($r['total'] ?? 0), 2),
        ];
    }

    // Every balance of the scope to zero. What was there is saved first (the
    // user, the balance, the waiting wallets) so clean_balance_undo can give it
    // back. [users changed, snapshot id]
    function clean_balance_zero($scope, $by = '')
    {
        global $pdo;
        clean_ensure_schema();
        wallet_ensure_schema();
        $scope = clean_scope($scope);
        [$w, $p] = clean_user_where($scope);
        $c = clean_balance_cond();
        $st = $pdo->prepare("SELECT id, Balance, wallets FROM user WHERE {$w} AND {$c}");
        $st->execute($p);
        $saved = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $saved[(string) $r['id']] = [round((float) $r['Balance'], 2), (string) ($r['wallets'] ?? '')];
        }
        if (!$saved) {
            return [0, 0];
        }
        $pdo->prepare("INSERT INTO clean_snapshot (kind, scope, created_at, by_user, users, payload) VALUES ('balance', ?, ?, ?, ?, ?)")
            ->execute([$scope, time(), (string) $by, count($saved), json_encode($saved)]);
        $snap = (int) $pdo->lastInsertId();
        $up = $pdo->prepare("UPDATE user SET Balance = 0, wallets = '{}' WHERE {$w} AND {$c}");
        $up->execute($p);
        clearSelectCache('user');
        return [$up->rowCount(), $snap];
    }

    // the newest balance reset that has not been given back, or null
    function clean_balance_last()
    {
        global $pdo;
        clean_ensure_schema();
        $r = $pdo->query("SELECT id, scope, created_at, by_user, users, undone FROM clean_snapshot WHERE kind = 'balance' AND undone = 0 ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        return is_array($r) ? $r : null;
    }

    // Gives back what the last balance reset took: added to what each user has
    // now, so money that came in since is kept, and never twice. [users, scope]
    function clean_balance_undo()
    {
        global $pdo;
        $last = clean_balance_last();
        if ($last === null) {
            return [0, ''];
        }
        // claimed first: a second tap at the same moment gives nothing back twice
        $claim = $pdo->prepare("UPDATE clean_snapshot SET undone = 1 WHERE id = ? AND undone = 0");
        $claim->execute([$last['id']]);
        if ($claim->rowCount() !== 1) {
            return [0, ''];
        }
        $payload = $pdo->prepare("SELECT payload FROM clean_snapshot WHERE id = ?");
        $payload->execute([$last['id']]);
        $saved = json_decode((string) $payload->fetchColumn(), true);
        $n = 0;
        if (is_array($saved)) {
            $get = $pdo->prepare("SELECT wallets FROM user WHERE id = ?");
            $put = $pdo->prepare("UPDATE user SET Balance = Balance + ?, wallets = ? WHERE id = ?");
            foreach ($saved as $uid => $row) {
                $get->execute([(string) $uid]);
                $now = $get->fetch(PDO::FETCH_ASSOC);
                if (!is_array($now)) {
                    continue; // the user is gone
                }
                $merged = wallet_stash(['wallets' => $now['wallets'] ?? '']);
                foreach (wallet_stash(['wallets' => $row[1] ?? '']) as $cur => $amount) {
                    $merged[$cur] = round(($merged[$cur] ?? 0) + $amount, 2);
                }
                $put->execute([round((float) ($row[0] ?? 0), 2), $merged ? json_encode($merged) : '{}', (string) $uid]);
                $n++;
            }
        }
        clearSelectCache('user');
        return [$n, (string) $last['scope']];
    }

    // ---------------------------------------------------------------- 👥
    function clean_ref_cond()
    {
        return "(affiliates NOT IN ('0', '') OR affiliatescount NOT IN ('0', '')"
            . " OR (aff_rw IS NOT NULL AND aff_rw NOT IN ('', '0')) OR (aff_pending IS NOT NULL AND aff_pending NOT IN ('', '0')))";
    }

    // what a referral reset of the scope would clear
    function clean_ref_preview($scope)
    {
        refv_ensure_schema();
        [$w, $p] = clean_user_where($scope);
        return [
            'invited' => clean_count("SELECT COUNT(*) FROM user WHERE {$w} AND (affiliates NOT IN ('0', '') OR (aff_rw IS NOT NULL AND aff_rw NOT IN ('', '0')))", $p),
            'inviters' => clean_count("SELECT COUNT(*) FROM user WHERE {$w} AND affiliatescount NOT IN ('0', '')", $p),
            'users' => clean_count("SELECT COUNT(*) FROM user WHERE {$w} AND " . clean_ref_cond(), $p),
        ];
    }

    // Everybody's زیرمجموعه of the scope to zero, in both plans: who was invited
    // by whom (💼 and 🎁), the counts, a 🛡 check still waiting, the join gifts
    // not yet taken (one already taken stays on record, so it cannot be taken
    // twice), and every 🎁 free-config progress starting over (a gift being
    // made right now is left to finish). Commission settings are not touched.
    // For one language the ties across it go too: someone of another language
    // who came through this one's link, and the inviter outside it who loses
    // the invitee. Returns the users changed.
    function clean_ref_reset($scope)
    {
        global $pdo;
        refv_ensure_schema();
        affrw_ensure_table();
        $scope = clean_scope($scope);
        [$w, $p] = clean_user_where($scope);
        if ($scope !== '') {
            // inviters outside lose the invitees that were inside
            $st = $pdo->prepare("SELECT affiliates, COUNT(*) c FROM user WHERE {$w} AND affiliates NOT IN ('0', '') GROUP BY affiliates");
            $st->execute($p);
            $dec = $pdo->prepare("UPDATE user SET affiliatescount = GREATEST(CAST(affiliatescount AS SIGNED) - ?, 0) WHERE id = ?");
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $dec->execute([(int) $r['c'], (string) $r['affiliates']]);
            }
            // invitees outside who came through someone inside
            $in = "SELECT id FROM (SELECT id FROM user WHERE {$w}) clean_in";
            foreach (['affiliates' => "'0'", 'aff_rw' => 'NULL', 'aff_pending' => 'NULL'] as $col => $to) {
                $pdo->prepare("UPDATE user SET {$col} = {$to} WHERE {$col} IN ({$in})")->execute($p);
            }
        }
        $up = $pdo->prepare("UPDATE user SET affiliates = '0', affiliatescount = '0', aff_rw = NULL, aff_pending = NULL WHERE {$w} AND " . clean_ref_cond());
        $up->execute($p);
        $n = $up->rowCount();
        $pdo->prepare("DELETE FROM reagent_report WHERE (get_gift = 0 OR get_gift IS NULL)
            AND (user_id IN (SELECT id FROM user WHERE {$w}) OR reagent COLLATE utf8mb4_unicode_ci IN (SELECT id FROM user WHERE {$w}))")
            ->execute(array_merge($p, $p));
        $now = time();
        $pdo->prepare("UPDATE affiliate_reward SET status = 'open', invites = 0, times = 0, since = ?, time = ?, members = '[]', left_ids = '[]', suspended = 0
            WHERE status <> 'giving' AND user_id IN (SELECT id FROM user WHERE {$w})")
            ->execute(array_merge([$now, $now], $p));
        clearSelectCache();
        return $n;
    }

    // ---------------------------------------------------------------- 🗑
    // The services the bot sold that still exist: every invoice that is not an
    // unpaid one and not already removed (removebyadmin, removebyuser,
    // removeTime ...), of the scope's language. Accounts made by hand in a
    // panel have no invoice, so they are never in it.
    function clean_svc_where($scope, $alias = 'i')
    {
        wallet_ensure_schema();
        $a = $alias !== '' ? $alias . '.' : '';
        $cond = "{$a}Status <> 'Unpaid' AND {$a}Status NOT LIKE 'remove%'";
        $params = [];
        $scope = clean_scope($scope);
        if ($scope !== '') {
            [$sw, $sp] = stats_lang_where($scope, $alias);
            $cond .= " AND {$sw}";
            $params = $sp;
        }
        return [$cond, $params];
    }

    function clean_svc_preview($scope)
    {
        [$c, $p] = clean_svc_where($scope);
        return [
            'services' => clean_count("SELECT COUNT(*) FROM invoice i WHERE {$c}", $p),
            'panels' => clean_count("SELECT COUNT(DISTINCT i.Service_location) FROM invoice i WHERE {$c}", $p),
            'users' => clean_count("SELECT COUNT(DISTINCT i.id_user) FROM invoice i WHERE {$c}", $p),
            // belonging to a panel the bot no longer has: nothing to delete on any panel
            'nopanel' => clean_count("SELECT COUNT(*) FROM invoice i WHERE {$c} AND i.Service_location COLLATE utf8mb4_unicode_ci NOT IN (SELECT name_panel COLLATE utf8mb4_unicode_ci FROM marzban_panel)", $p),
        ];
    }

    // the run going on, or null
    function clean_job_active()
    {
        global $pdo;
        clean_ensure_schema();
        $r = $pdo->query("SELECT * FROM clean_job WHERE state = 'running' ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        return is_array($r) ? $r : null;
    }

    // the run to show: the one going on, else the newest
    function clean_job_current()
    {
        global $pdo;
        $a = clean_job_active();
        if ($a !== null) {
            return $a;
        }
        $r = $pdo->query("SELECT * FROM clean_job ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        return is_array($r) ? $r : null;
    }

    // how a run stands: wait / ok / fail, and of the ok ones how each ended -
    // removed (the panel deleted it), absent (the panel answered that there is
    // no such account), nopanel (its panel is not in the bot any more, so no
    // panel was asked), unk (a run from before this was told apart)
    function clean_job_counts($jobId)
    {
        global $pdo;
        $out = ['wait' => 0, 'ok' => 0, 'fail' => 0, 'removed' => 0, 'absent' => 0, 'nopanel' => 0, 'unk' => 0];
        $st = $pdo->prepare("SELECT state, kind, COUNT(*) c FROM clean_item WHERE job_id = ? GROUP BY state, kind");
        $st->execute([(int) $jobId]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[$r['state']] += (int) $r['c'];
            if ($r['state'] === 'ok') {
                $k = in_array($r['kind'], ['removed', 'absent', 'nopanel'], true) ? $r['kind'] : 'unk';
                $out[$k] += (int) $r['c'];
            }
        }
        return $out;
    }

    // Starts a run on the scope's services. Only one at a time. $chat: who is
    // told when it ends (a Telegram id), '' for none. [job id, ''] or [0, why]
    // with why 'busy' or 'none'.
    function clean_svc_start($scope, $by = '', $chat = '')
    {
        global $pdo;
        clean_ensure_schema();
        $scope = clean_scope($scope);
        // one worker at a time decides whether a run is going on
        $got = (int) $pdo->query("SELECT GET_LOCK('ciro_clean_start', 5)")->fetchColumn();
        try {
            if (clean_job_active() !== null) {
                return [0, 'busy'];
            }
            [$c, $p] = clean_svc_where($scope);
            $pdo->prepare("INSERT INTO clean_job (kind, scope, state, total, by_user, chat_id, created_at) VALUES ('svc', ?, 'running', 0, ?, ?, ?)")
                ->execute([$scope, (string) $by, (string) $chat, time()]);
            $job = (int) $pdo->lastInsertId();
            $ins = $pdo->prepare("INSERT INTO clean_item (job_id, invoice_id, username, location)
                SELECT ?, i.id_invoice, i.username, i.Service_location FROM invoice i WHERE {$c}");
            $ins->execute(array_merge([$job], $p));
            $total = $ins->rowCount();
            if ($total === 0) {
                $pdo->prepare("DELETE FROM clean_job WHERE id = ?")->execute([$job]);
                return [0, 'none'];
            }
            $pdo->prepare("UPDATE clean_job SET total = ? WHERE id = ?")->execute([$total, $job]);
        } finally {
            if ($got === 1) {
                $pdo->query("SELECT RELEASE_LOCK('ciro_clean_start')");
            }
        }
        return [$job, ''];
    }

    // stops the run going on: what is left is not touched (what was removed stays removed)
    function clean_svc_stop()
    {
        global $pdo;
        $job = clean_job_active();
        if ($job === null) {
            return false;
        }
        $pdo->prepare("UPDATE clean_job SET state = 'stopped', finished_at = ? WHERE id = ? AND state = 'running'")->execute([time(), $job['id']]);
        return true;
    }

    // the services a finished run could not remove, tried again as a new turn
    function clean_svc_retry()
    {
        global $pdo;
        clean_ensure_schema();
        $job = $pdo->query("SELECT * FROM clean_job WHERE state IN ('done', 'stopped') ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!is_array($job) || clean_job_active() !== null) {
            return 0;
        }
        // counted first: rowCount() of an update only says how many rows CHANGED
        $n = clean_count("SELECT COUNT(*) FROM clean_item WHERE job_id = ? AND state IN ('fail', 'wait')", [$job['id']]);
        if ($n > 0) {
            $pdo->prepare("UPDATE clean_item SET state = 'wait', tries = 0, retry_after = 0, why = NULL WHERE job_id = ? AND state IN ('fail', 'wait')")->execute([$job['id']]);
            $pdo->prepare("UPDATE clean_job SET state = 'running', finished_at = NULL, notified = 0 WHERE id = ?")->execute([$job['id']]);
        }
        return $n;
    }

    // Took a service out of its panel? [ok, reason, kind]:
    //   [true, '', 'removed']  the panel deleted the account
    //   [true, '', 'absent']   the panel answered that there is no such account
    //                          (a 404 included: it cannot tell a missing
    //                          account from a panel address that no longer
    //                          answers properly) - nothing to delete there
    //   [true, '', 'nopanel']  the service's panel is not in the bot any more, so
    //                          no panel was asked and none was changed
    //   [false, reason, '']    the panel refused or did not answer
    function clean_panel_remove(array $inv)
    {
        global $ManagePanel;
        $panel = select("marzban_panel", "*", "name_panel", $inv['Service_location'], "select");
        if (!is_array($panel)) {
            return [true, '', 'nopanel'];
        }
        if (($panel['type'] ?? '') === 'hiddify') {
            return clean_hiddify_remove($inv);
        }
        $r = $ManagePanel->RemoveUser($inv['Service_location'], $inv['username']);
        if (is_array($r) && ($r['status'] ?? '') === 'successful') {
            return [true, '', 'removed'];
        }
        $msg = is_array($r) ? ($r['msg'] ?? $r) : 'no answer';
        $msg = is_string($msg) || is_numeric($msg) ? (string) $msg : json_encode($msg, JSON_UNESCAPED_UNICODE);
        if (preg_match('/not\s*found|no such|does not exist|doesn.t exist|404/i', $msg)) {
            return [true, '', 'absent'];
        }
        return [false, mb_substr($msg, 0, 160), ''];
    }

    // Hiddify: ManagePanel->RemoveUser says «successful» whatever the panel
    // answered (it never reads the answer), so a removal there would always
    // look done. Asked here instead: the account is looked up, and deleted only
    // when it is there, and the delete's own answer is read.
    function clean_hiddify_remove(array $inv)
    {
        $d = getdatauser($inv['username'], $inv['Service_location']);
        if (is_array($d) && !empty($d['uuid'])) {
            $r = removeuserhi($inv['Service_location'], $d['uuid']);
            $code = is_array($r) ? (int) ($r['status'] ?? 0) : 0;
            if ($code >= 200 && $code < 300) {
                return [true, '', 'removed'];
            }
            return [false, is_array($r) && !empty($r['error']) ? mb_substr((string) $r['error'], 0, 160) : 'HTTP ' . $code, ''];
        }
        // a list that came back without this account: it is not there. No list,
        // an empty one or an error message: the panel did not really answer.
        if ($d === null) {
            return [true, '', 'absent'];
        }
        return [false, is_array($d) && !empty($d['message']) ? mb_substr((string) $d['message'], 0, 160) : 'no answer', ''];
    }

    // cronbot/gift.php: works on the run going on for up to $budget seconds -
    // one worker at a time. A service whose panel did not answer is tried
    // again 2 and 4 minutes later; one that still fails is left for the report.
    // Returns true when it ran out of time with more to do (and has asked for
    // the next turn).
    function clean_cron($budget = 60)
    {
        global $pdo;
        clean_ensure_schema();
        if ((int) $pdo->query("SELECT GET_LOCK('ciro_clean_worker', 0)")->fetchColumn() !== 1) {
            return true;
        }
        $more = false;
        try {
            $job = clean_job_active();
            if ($job === null) {
                return false;
            }
            $deadline = time() + max(5, (int) $budget);
            $pick = $pdo->prepare("SELECT * FROM clean_item WHERE job_id = ? AND state = 'wait' AND retry_after <= ? ORDER BY id LIMIT 20");
            $inv = $pdo->prepare("SELECT * FROM invoice WHERE id_invoice = ? LIMIT 1");
            $okItem = $pdo->prepare("UPDATE clean_item SET state = 'ok', kind = ?, why = NULL WHERE id = ?");
            $badItem = $pdo->prepare("UPDATE clean_item SET state = ?, why = ?, tries = tries + 1, retry_after = ? WHERE id = ?");
            $out = false;
            while (!$out) {
                $pick->execute([$job['id'], time()]);
                $items = $pick->fetchAll(PDO::FETCH_ASSOC);
                if (!$items) {
                    break;
                }
                foreach ($items as $it) {
                    if (time() >= $deadline) {
                        $out = true;
                        $more = true;
                        break;
                    }
                    // stopped meanwhile? leave the rest alone
                    if (clean_job_active() === null) {
                        $out = true;
                        break;
                    }
                    $inv->execute([$it['invoice_id']]);
                    $row = $inv->fetch(PDO::FETCH_ASSOC);
                    if (!is_array($row) || stripos((string) $row['Status'], 'remove') === 0) {
                        $okItem->execute(['absent', $it['id']]);
                        continue;
                    }
                    // one odd panel must not stop the whole run: an error is that service's failure
                    try {
                        [$ok, $why, $kind] = clean_panel_remove($row);
                    } catch (Throwable $e) {
                        [$ok, $why, $kind] = [false, mb_substr($e->getMessage(), 0, 160), ''];
                    }
                    if ($ok) {
                        $pdo->prepare("UPDATE invoice SET Status = 'removebyadmin' WHERE id_invoice = ?")->execute([$it['invoice_id']]);
                        $okItem->execute([$kind, $it['id']]);
                    } else {
                        $tries = (int) $it['tries'] + 1;
                        $badItem->execute([$tries >= 3 ? 'fail' : 'wait', $why, time() + 120 * $tries, $it['id']]);
                    }
                }
            }
            clearSelectCache('invoice');
            $c = clean_job_counts($job['id']);
            $live = clean_job_active();
            if ($live !== null && $c['wait'] === 0) {
                $pdo->prepare("UPDATE clean_job SET state = 'done', finished_at = ? WHERE id = ? AND state = 'running'")->execute([time(), $job['id']]);
                clean_job_notify((int) $job['id']);
            } elseif ($live === null) {
                clean_job_notify((int) $job['id']);
            }
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('ciro_clean_worker')");
            // out of time with more to do: the next turn now, not at the next cron
            if ($more) {
                bulkgift_kick();
            }
        }
        return $more;
    }

    // The word that confirms the removal, typed in either language's form.
    function clean_word_ok($input)
    {
        $w = mb_strtolower(trim((string) $input));
        return $w === 'حذف' || $w === 'delete';
    }

    // what a run did (or is doing), in the words of $texts (a language's
    // texts): the line for the admin - how many accounts the panels really
    // deleted, apart from the ones only the bot's list lost - with the
    // services that failed listed below it
    function clean_job_report(array $job, array $texts)
    {
        global $pdo;
        $t = $texts['Admin']['CleanTools'];
        $c = clean_job_counts($job['id']);
        $total = max(1, (int) $job['total']);
        $fmt = fn($key, $n) => strtr($t[$key], ['{n}' => number_format($n)]);
        $head = strtr(['running' => $t['svcRunning'], 'stopped' => $t['svcStopped']][$job['state']] ?? $t['svcDone'], [
            '{total}' => number_format((int) $job['total']),
            '{pct}' => (int) floor(($c['ok'] + $c['fail']) * 100 / $total),
        ]);
        $lines = [$fmt('svcLineRemoved', $c['removed'])];
        if ($c['absent'] > 0) {
            $lines[] = $fmt('svcLineAbsent', $c['absent']);
        }
        if ($c['nopanel'] > 0) {
            $lines[] = $fmt('svcLineNopanel', $c['nopanel']);
        }
        if ($c['unk'] > 0) {
            $lines[] = $fmt('svcLineUnknown', $c['unk']);
        }
        if ($job['state'] !== 'done' || $c['wait'] > 0) {
            $lines[] = $fmt('svcLineLeft', $c['wait']);
        }
        $lines[] = $fmt('svcLineFail', $c['fail']);
        $out = $head . "\n\n" . implode("\n", $lines);
        // nothing at all taken out of a panel: said outright, not left to be worked out
        if ($job['state'] !== 'running' && $c['removed'] === 0 && $c['unk'] === 0 && $c['ok'] > 0) {
            $out .= "\n\n" . $t['svcNoneRemoved'];
        }
        if ($c['fail'] > 0) {
            $st = $pdo->prepare("SELECT username, location, why FROM clean_item WHERE job_id = ? AND state = 'fail' ORDER BY id LIMIT 10");
            $st->execute([$job['id']]);
            $list = '';
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $list .= "\n• <code>" . htmlspecialchars((string) $r['username']) . '</code> (' . htmlspecialchars((string) $r['location']) . '): ' . htmlspecialchars((string) $r['why']);
            }
            $out .= "\n\n" . $t['svcFailedHead'] . $list . ($c['fail'] > 10 ? "\n…" : '');
        }
        return $out;
    }

    // told once, to who started the run (a Telegram id) - when it began on the
    // web, to the bot's main admin
    function clean_job_notify($jobId)
    {
        global $pdo, $adminnumber;
        // the web panel loads botapi.php only when it has something to say
        if (!function_exists('telegram')) {
            return;
        }
        $st = $pdo->prepare("UPDATE clean_job SET notified = 1 WHERE id = ? AND notified = 0");
        $st->execute([(int) $jobId]);
        if ($st->rowCount() !== 1) {
            return;
        }
        $q = $pdo->prepare("SELECT * FROM clean_job WHERE id = ?");
        $q->execute([(int) $jobId]);
        $job = $q->fetch(PDO::FETCH_ASSOC);
        if (!is_array($job)) {
            return;
        }
        $to = trim((string) $job['chat_id']);
        if ($to === '' || !ctype_digit($to)) {
            $to = trim((string) ($adminnumber ?? ''));
        }
        if ($to === '' || !ctype_digit($to)) {
            return;
        }
        // the admin's screens are Persian for every reader (panel_texts)
        telegram('sendmessage', ['chat_id' => $to, 'text' => clean_job_report($job, panel_texts()), 'parse_mode' => 'HTML']);
    }

    // ---------------------------------------------------------------- 🤖
    // The bot's screens (👤 مدیریت کاربر ← 🧹). A scope travels in the buttons
    // as 'all' or a language code. Each builder returns [text, keyboard json];
    // $tx is the admin's texts.
    function clean_scope_code($scope)
    {
        $scope = clean_scope($scope);
        return $scope === '' ? 'all' : $scope;
    }

    function clean_scope_from_code($code)
    {
        return $code === 'all' ? '' : clean_scope($code);
    }

    function clean_scope_name($scope, array $tx)
    {
        $scope = clean_scope($scope);
        return $scope === '' ? $tx['Admin']['CleanTools']['scopeAll'] : ($tx['bottext']['langs'][$scope] ?? $scope);
    }

    function clean_kb(array $rows)
    {
        return json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
    }

    function clean_menu_screen($scope, array $tx)
    {
        $t = $tx['Admin']['CleanTools'];
        $scope = clean_scope($scope);
        $code = clean_scope_code($scope);
        $chips = [];
        foreach (array_merge(['all'], panel_langs()) as $c) {
            $name = $c === 'all' ? $t['scopeAll'] : ($tx['bottext']['langs'][$c] ?? $c);
            $chips[] = ['text' => ($c === $code ? '✅ ' : '') . $name, 'callback_data' => "cln|menu|{$c}", 'style' => $c === $code ? 'success' : 'primary'];
        }
        $rows = [
            $chips,
            [['text' => $t['balBtn'], 'callback_data' => "cln|bal|{$code}", 'style' => 'danger']],
            [['text' => $t['refBtn'], 'callback_data' => "cln|ref|{$code}", 'style' => 'danger']],
            [['text' => $t['svcBtn'], 'callback_data' => "cln|svc|{$code}", 'style' => 'danger']],
        ];
        if (clean_balance_last() !== null) {
            $rows[] = [['text' => $t['undoBtn'], 'callback_data' => "cln|undo|{$code}", 'style' => 'primary']];
        }
        $job = clean_job_current();
        $note = '';
        if ($job !== null) {
            $rows[] = [['text' => $t['jobBtn'], 'callback_data' => "cln|job|{$code}", 'style' => 'primary']];
            if ($job['state'] === 'running') {
                $note = "\n\n" . $t['menuRunning'];
            }
        }
        $rows[] = [['text' => $t['backBtn'], 'callback_data' => 'backlistuser', 'style' => 'danger']];
        return [strtr($t['menuCaption'], ['{scope}' => clean_scope_name($scope, $tx)]) . $note, clean_kb($rows)];
    }

    // «are you sure» under the screens that change things: the go button and a cancel
    function clean_confirm_rows($goText, $goData, $code, array $t)
    {
        return [
            [['text' => $goText, 'callback_data' => $goData, 'style' => 'danger']],
            [['text' => $t['cancelBtn'], 'callback_data' => "cln|menu|{$code}", 'style' => 'primary']],
        ];
    }

    function clean_back_rows($code, array $t)
    {
        return [[['text' => $t['backToCleanBtn'], 'callback_data' => "cln|menu|{$code}", 'style' => 'danger']]];
    }

    function clean_balance_screen($scope, array $tx)
    {
        $t = $tx['Admin']['CleanTools'];
        $code = clean_scope_code($scope);
        $p = clean_balance_preview($scope);
        if ($p['users'] === 0) {
            return [strtr($t['balAsk'], ['{scope}' => clean_scope_name($scope, $tx), '{count}' => $t['balNone']]), clean_kb(clean_back_rows($code, $t))];
        }
        $count = strtr($t['balCount'], ['{n}' => number_format($p['users']), '{pos}' => number_format($p['positive']), '{neg}' => number_format($p['negative']), '{stash}' => number_format($p['stash'])]);
        return [strtr($t['balAsk'], ['{scope}' => clean_scope_name($scope, $tx), '{count}' => $count]), clean_kb(clean_confirm_rows($t['balGo'], "cln|bal|{$code}|go", $code, $t))];
    }

    function clean_undo_screen(array $tx)
    {
        $t = $tx['Admin']['CleanTools'];
        $last = clean_balance_last();
        if ($last === null) {
            return [$t['undoNone'], clean_kb(clean_back_rows('all', $t))];
        }
        $code = clean_scope_code($last['scope']);
        $text = strtr($t['undoAsk'], [
            '{when}' => date('Y/m/d H:i', (int) $last['created_at']),
            '{scope}' => clean_scope_name($last['scope'], $tx),
            '{n}' => number_format((int) $last['users']),
        ]);
        return [$text, clean_kb(clean_confirm_rows($t['undoGo'], 'cln|undo|' . $code . '|go', $code, $t))];
    }

    function clean_ref_screen($scope, array $tx)
    {
        $t = $tx['Admin']['CleanTools'];
        $code = clean_scope_code($scope);
        $p = clean_ref_preview($scope);
        if ($p['users'] === 0) {
            return [strtr($t['refAsk'], ['{scope}' => clean_scope_name($scope, $tx), '{count}' => $t['refNone']]), clean_kb(clean_back_rows($code, $t))];
        }
        $count = strtr($t['refCount'], ['{invited}' => number_format($p['invited']), '{inviters}' => number_format($p['inviters'])]);
        return [strtr($t['refAsk'], ['{scope}' => clean_scope_name($scope, $tx), '{count}' => $count]), clean_kb(clean_confirm_rows($t['refGo'], "cln|ref|{$code}|go", $code, $t))];
    }

    // the services screen: what would go, and the step that asks for the word
    function clean_svc_screen($scope, array $tx)
    {
        $t = $tx['Admin']['CleanTools'];
        $code = clean_scope_code($scope);
        if (clean_job_active() !== null) {
            return [$t['svcBusy'], clean_kb([[['text' => $t['jobBtn'], 'callback_data' => "cln|job|{$code}", 'style' => 'primary']], ...clean_back_rows($code, $t)])];
        }
        $p = clean_svc_preview($scope);
        if ($p['services'] === 0) {
            return [strtr($t['svcAsk'], ['{scope}' => clean_scope_name($scope, $tx), '{count}' => $t['svcNone']]), clean_kb(clean_back_rows($code, $t))];
        }
        $count = strtr($t['svcCount'], ['{n}' => number_format($p['services']), '{users}' => number_format($p['users']), '{panels}' => number_format($p['panels'])]);
        if ($p['nopanel'] > 0) {
            $count .= "\n\n" . strtr($t['svcNoPanel'], ['{n}' => number_format($p['nopanel'])]);
        }
        return [strtr($t['svcAsk'], ['{scope}' => clean_scope_name($scope, $tx), '{count}' => $count]), clean_kb(clean_confirm_rows($t['svcGo'], "cln|svc|{$code}|ask", $code, $t))];
    }

    // the run's progress, with stop / retry / refresh
    function clean_job_screen($scope, array $tx)
    {
        $t = $tx['Admin']['CleanTools'];
        $code = clean_scope_code($scope);
        $job = clean_job_current();
        if ($job === null) {
            return [$t['jobNone'], clean_kb(clean_back_rows($code, $t))];
        }
        $c = clean_job_counts($job['id']);
        $rows = [];
        if ($job['state'] === 'running') {
            $rows[] = [['text' => $t['jobRefresh'], 'callback_data' => "cln|job|{$code}", 'style' => 'primary'], ['text' => $t['jobStop'], 'callback_data' => "cln|job|{$code}|stop", 'style' => 'danger']];
        } elseif ($c['fail'] > 0 || $c['wait'] > 0) {
            $rows[] = [['text' => $t['jobRetry'], 'callback_data' => "cln|job|{$code}|retry", 'style' => 'success']];
        }
        return [clean_job_report($job, $tx), clean_kb(array_merge($rows, clean_back_rows($code, $t)))];
    }

    // ---------------------------------------------------------------- 📊
    // The dashboard's three figures, and the moment each starts counting from
    // (0 = since the beginning). Reset is NOT a deletion: the payments and
    // orders stay where they are, and clean_stat_clear brings the old figure
    // back. One moment for every language tab, so the tabs always add up.
    function clean_stat_keys()
    {
        return ['rev', 'act', 'tx'];
    }

    function clean_stat_base($key)
    {
        return in_array($key, clean_stat_keys(), true) ? (int) feature_setting_value('stats_base_' . $key, 'all', 0) : 0;
    }

    function clean_stat_reset(array $keys)
    {
        foreach (array_intersect($keys, clean_stat_keys()) as $k) {
            feature_setting_set('stats_base_' . $k, 'all', (string) time());
        }
    }

    function clean_stat_clear(array $keys)
    {
        foreach (array_intersect($keys, clean_stat_keys()) as $k) {
            feature_setting_set('stats_base_' . $k, 'all', '0');
        }
    }
}
