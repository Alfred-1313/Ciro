<?php
// ⚙️ تنظیم پروتکل و اینباند on a Rebecca panel: the panel's own services
// (Rebecca groups inbounds into services; a user lives in exactly one), listed
// from the panel to pick from - instead of a username of a user already
// sitting in the service. Two panels of the bot on the same Rebecca each keep
// their own service, and test accounts can have a service of their own.
//
// Where it is kept: the purchase service in marzban_panel.proxies (as it always
// was, [id] - adduser_rebecca and the other screens read it there); the test
// accounts' one in marzban_panel.rb_test_service (empty = the purchase one).
// Other panel types keep the username way.

if (!function_exists('rbsvc_list')) {
    function rbsvc_ensure_schema()
    {
        static $done = false;
        if (!$done) {
            $done = true;
            addFieldToTable('marzban_panel', 'rb_test_service', null, 'VARCHAR(20) NULL');
        }
    }

    // The services the panel's token can see: [list, ''] or [null, why].
    // A list entry: id, name, users, hosts, broken (no working host: a user
    // made in it gets no config). Kept for the request: panels A and B of the
    // bot on one Rebecca ask once.
    function rbsvc_list(array $panel, $fresh = false)
    {
        static $memo = [];
        $key = rtrim((string) $panel['url_panel'], '/') . '|' . md5((string) $panel['password_panel']);
        if (!$fresh && isset($memo[$key])) {
            return $memo[$key];
        }
        $r = rbn_req($panel, 'GET', '/api/v2/services?offset=0&limit=200', null, 15);
        if (!rbn_ok($r)) {
            return $memo[$key] = [null, rbn_why($r)];
        }
        $rows = is_array($r['json']) ? ($r['json']['services'] ?? null) : null;
        if (!is_array($rows)) {
            return $memo[$key] = [null, panel_texts()['Admin']['RbSvc']['errShape']];
        }
        $out = [];
        foreach ($rows as $s) {
            if (!is_array($s) || !isset($s['id'])) {
                continue;
            }
            $out[] = [
                'id' => (int) $s['id'],
                'name' => (string) ($s['name'] ?? ('#' . $s['id'])),
                'users' => (int) ($s['user_count'] ?? 0),
                'hosts' => (int) ($s['host_count'] ?? 0),
                'broken' => !empty($s['broken']) || (array_key_exists('has_hosts', $s) && empty($s['has_hosts'])),
            ];
        }
        return $memo[$key] = [$out, ''];
    }

    // the service purchases are made in (0 = none set)
    function rbsvc_buy_id(array $panel)
    {
        $v = json_decode((string) ($panel['proxies'] ?? ''), true);
        $id = is_array($v) ? reset($v) : $v;
        return is_numeric($id) ? (int) $id : 0;
    }
    // the test accounts' own service (0 = the purchase one)
    function rbsvc_test_id(array $panel)
    {
        $v = (string) ($panel['rb_test_service'] ?? '');
        return ctype_digit($v) ? (int) $v : 0;
    }
    // the service a new user is made in: $test for a test account
    function rbsvc_for_new(array $panel, $test)
    {
        if ($test && rbsvc_test_id($panel) > 0) {
            return rbsvc_test_id($panel);
        }
        return rbsvc_buy_id($panel);
    }
    function rbsvc_set(array $panel, $kind, $id)
    {
        $id = max(0, (int) $id);
        if ($kind === 'b') {
            update("marzban_panel", "proxies", json_encode([$id]), "code_panel", $panel['code_panel']);
        } else {
            rbsvc_ensure_schema();
            update("marzban_panel", "rb_test_service", $id > 0 ? (string) $id : null, "code_panel", $panel['code_panel']);
        }
        clearSelectCache('marzban_panel');
    }

    // a service as a line: its name, or «#5» when the panel no longer has it
    function rbsvc_label($list, $id)
    {
        $t = panel_texts()['Admin']['RbSvc'];
        if ($id <= 0) {
            return $t['unset'];
        }
        if ($list === null) {
            // the list could not be read: its number is all that is known
            return strtr($t['idOnly'], ['{id}' => (int) $id]);
        }
        foreach ($list as $s) {
            if ($s['id'] === (int) $id) {
                return htmlspecialchars($s['name']) . ($s['broken'] ? ' ' . $t['brokenMark'] : '');
            }
        }
        return strtr($t['gone'], ['{id}' => (int) $id]);
    }

    // the bot's screen - [text, keyboard]; $tab b (purchase) or t (test accounts)
    function rbsvc_screen(array $panel, $tab = 'b', $note = '', $fresh = false)
    {
        $t = panel_texts()['Admin']['RbSvc'];
        $pc = (string) $panel['code_panel'];
        [$list, $why] = rbsvc_list($panel, $fresh);
        $buy = rbsvc_buy_id($panel);
        $test = rbsvc_test_id($panel);
        $b = fn($text, $cb, $style = 'primary') => ['text' => $text, 'callback_data' => $cb, 'style' => $style];
        if ($list === null) {
            $text = strtr($t['error'], ['{panel}' => htmlspecialchars((string) $panel['name_panel']), '{why}' => $why, '{buy}' => rbsvc_label(null, $buy), '{test}' => $test > 0 ? rbsvc_label(null, $test) : $t['sameAsBuy']]);
            $rows = [
                [$b($t['btnRefresh'], "rbsv|{$pc}|r|{$tab}")],
                [$b($t['btnByUsername'], "rbsv|{$pc}|u")],
                [$b($t['btnClose'], "rbsv|{$pc}|x", 'danger')],
            ];
        } else {
            $text = strtr($t['screen'], [
                '{panel}' => htmlspecialchars((string) $panel['name_panel']),
                '{buy}' => rbsvc_label($list, $buy),
                '{test}' => $test > 0 ? rbsvc_label($list, $test) : $t['sameAsBuy'] . ($buy > 0 ? ' (' . rbsvc_label($list, $buy) . ')' : ''),
                '{hint}' => $tab === 't' ? $t['hintTest'] : $t['hintBuy'],
            ]);
            $rows = [[
                $b(($tab === 'b' ? '✓ ' : '') . $t['tabBuy'], "rbsv|{$pc}|v|b", $tab === 'b' ? 'success' : 'primary'),
                $b(($tab === 't' ? '✓ ' : '') . $t['tabTest'], "rbsv|{$pc}|v|t", $tab === 't' ? 'success' : 'primary'),
            ]];
            $cur = $tab === 't' ? $test : $buy;
            if ($tab === 't') {
                $rows[] = [$b(($test === 0 ? '✅ ' : '') . $t['sameAsBuy'], "rbsv|{$pc}|s|t|0", $test === 0 ? 'success' : 'primary')];
            }
            foreach ($list as $s) {
                $on = $s['id'] === $cur;
                $label = ($on ? '✅ ' : ($s['broken'] ? '⚠️ ' : '')) . $s['name'] . ' · ' . strtr($t['users'], ['{n}' => number_format($s['users'])]);
                $rows[] = [$b(mb_substr($label, 0, 60), "rbsv|{$pc}|s|{$tab}|{$s['id']}", $on ? 'success' : 'primary')];
            }
            if (!$list) {
                $text .= "\n\n" . $t['empty'];
            }
            $rows[] = [$b($t['btnRefresh'], "rbsv|{$pc}|r|{$tab}"), $b($t['btnClose'], "rbsv|{$pc}|x", 'danger')];
        }
        if ($note !== '') {
            $text = $note . "\n\n" . $text;
        }
        return [$text, json_encode(['inline_keyboard' => $rows])];
    }

    // The admin's taps. true when handled.
    function rbsvc_admin_dispatch()
    {
        global $from_id, $datain, $text, $message_id, $user, $callback_query_id, $textbotlang, $adminrulecheck, $backadmin;
        if (($adminrulecheck['rule'] ?? '') !== 'administrator') {
            return false;
        }
        $data = (string) $datain;
        // ✏️ مدیریت پنل ← a Rebecca panel ← ⚙️ تنظیم پروتکل و اینباند: the list
        if ($data === '' && $text === ($textbotlang['keyboard']['setProtocolInbound'] ?? null)) {
            $panel = select("marzban_panel", "*", "name_panel", (string) $user['Processing_value'], "select");
            if (!is_array($panel) || ($panel['type'] ?? '') !== 'rebecca') {
                return false;
            }
            [$x, $k] = rbsvc_screen($panel);
            sendmessage($from_id, $x, $k, 'HTML');
            return true;
        }
        if (!preg_match('/^rbsv\|([^|]+)\|([a-z])(?:\|([bt]))?(?:\|(\d+))?$/', $data, $m)) {
            return false;
        }
        $t = panel_texts()['Admin']['RbSvc'];
        $answer = function ($msg = '', $alert = false) use ($callback_query_id) {
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => $msg, 'show_alert' => $alert]);
        };
        $panel = select("marzban_panel", "*", "code_panel", $m[1], "select");
        if (!is_array($panel) || ($panel['type'] ?? '') !== 'rebecca') {
            $answer($t['noPanel'], true);
            return true;
        }
        $act = $m[2];
        $tab = ($m[3] ?? '') === 't' ? 't' : 'b';
        $show = function (array $s) use ($from_id, $message_id) {
            Editmessagetext($from_id, $message_id, $s[0], $s[1]);
        };
        if ($act === 'x') {
            deletemessage($from_id, $message_id);
            $answer();
            return true;
        }
        if ($act === 'u') {
            // the old way, for a panel whose list cannot be read
            deletemessage($from_id, $message_id);
            update("user", "Processing_value", (string) $panel['name_panel'], "id", $from_id);
            sendmessage($from_id, $t['askUsername'], $backadmin, 'HTML');
            step("setinboundandprotocol", $from_id);
            $answer();
            return true;
        }
        if ($act === 's' && isset($m[4])) {
            $id = (int) $m[4];
            [$list] = rbsvc_list($panel);
            $pick = null;
            foreach ((array) $list as $s) {
                if ($s['id'] === $id) {
                    $pick = $s;
                }
            }
            if ($id > 0 && $pick === null) {
                // gone from the panel since the list was drawn
                $answer($t['notFound'], true);
                $show(rbsvc_screen($panel, $tab, '', true));
                return true;
            }
            if ($pick !== null && $pick['broken']) {
                $answer(strtr($t['brokenAlert'], ['{name}' => $pick['name']]), true);
                return true;
            }
            rbsvc_set($panel, $tab, $id);
            $panel = select("marzban_panel", "*", "code_panel", $m[1], "select", ['cache' => false]);
            $answer($t['saved']);
            $show(rbsvc_screen($panel, $tab));
            return true;
        }
        $answer();
        $show(rbsvc_screen($panel, $tab, '', $act === 'r'));
        return true;
    }
}
