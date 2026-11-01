<?php
// 📥 ایمپورت بکاپ - a backup the admin hands the bot (or uploads in the web
// panel) is put back the way the install script's Restore (menu 7) puts it
// back - by that very code. This side only takes the file, works out what it
// holds (a database dump, the bot's folder), asks for its password when it has
// one and for a yes; the root watcher (Ciro selfupdate-watch, every minute)
// does the restore, because only root can import the database and lay files
// over the bot. Everything the admin sends is deleted once it has been used.

if (!function_exists('bkimp_inbox')) {
    // what the bot can fetch from Telegram (getFile stops at 20 MB)
    define('BKIMP_MAX_BOT', 20 * 1024 * 1024);

    // where the files wait: inside the bot (the watcher only looks there), shut to the web
    function bkimp_inbox()
    {
        $d = __DIR__ . '/storage/restore';
        if (!is_dir($d)) {
            @mkdir($d, 0770, true);
        }
        if (!is_file($d . '/.htaccess')) {
            @file_put_contents($d . '/.htaccess', "Require all denied\n");
            @file_put_contents($d . '/index.html', '');
        }
        return $d;
    }

    function bkimp_texts()
    {
        return panel_texts()['Admin']['BackupImport'];
    }

    // a watcher that knows restores is running: it says so every minute
    function bkimp_watcher_ready()
    {
        $f = __DIR__ . '/watch_caps.json';
        $j = is_file($f) ? json_decode((string) @file_get_contents($f), true) : null;
        return is_array($j) && in_array('restore', (array) ($j['caps'] ?? []), true) && time() - (int) ($j['at'] ?? 0) < 600;
    }

    // one restore at a time
    function bkimp_busy()
    {
        return is_file(__DIR__ . '/restore_request') || is_file(__DIR__ . '/restore_request.running');
    }

    // what one admin has handed in so far: [items => [...], msg => summary message id]
    function bkimp_state($owner, $set = null)
    {
        $f = bkimp_inbox() . '/state_' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) $owner) . '.json';
        if ($set === null) {
            $j = is_file($f) ? json_decode((string) @file_get_contents($f), true) : null;
            return is_array($j) ? $j + ['items' => [], 'msg' => 0] : ['items' => [], 'msg' => 0];
        }
        @file_put_contents($f, json_encode($set, JSON_UNESCAPED_UNICODE));
        @chmod($f, 0660);
        return $set;
    }

    // forget it all, the files too
    function bkimp_reset($owner)
    {
        $st = bkimp_state($owner);
        foreach ($st['items'] as $it) {
            $p = bkimp_inbox() . '/' . basename((string) ($it['file'] ?? ''));
            if (is_file($p)) {
                @unlink($p);
            }
        }
        @unlink(bkimp_inbox() . '/state_' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) $owner) . '.json');
        // and whatever an abandoned import left behind for more than a day
        foreach ((array) glob(bkimp_inbox() . '/*.{zip,sql}', GLOB_BRACE) as $old) {
            if (is_file($old) && time() - (int) @filemtime($old) > 86400) {
                @unlink($old);
            }
        }
    }

    function bkimp_size($bytes)
    {
        $b = (float) $bytes;
        return $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, (int) round($b / 1024)) . ' KB';
    }

    // the install script's own test for a dump: a dump header, or statements, in its first MB
    function bkimp_is_sql($head)
    {
        return (bool) preg_match('/^(-- MySQL dump|-- MariaDB dump|-- phpMyAdmin SQL Dump|CREATE TABLE|INSERT INTO|DROP TABLE)/mi', (string) $head);
    }

    // the database a dump was taken from, from its header ('' when it does not say)
    function bkimp_sql_source($head)
    {
        $lines = implode("\n", array_slice(explode("\n", (string) $head, 41), 0, 40));
        return preg_match('/Database: `?([A-Za-z0-9_]+)/', $lines, $m) ? $m[1] : '';
    }

    // up to $max bytes of one entry: [true, data] or [false, 'password'|'unsupported'|'damaged']
    function bkimp_zip_read($path, $entry, $pw, $max = 1048576)
    {
        if (!class_exists('ZipArchive')) {
            return [false, 'unsupported'];
        }
        $z = new ZipArchive();
        if ($z->open($path, ZipArchive::RDONLY) !== true) {
            return [false, 'damaged'];
        }
        if ($pw !== null && $pw !== '') {
            $z->setPassword((string) $pw);
        }
        $s = $z->getStream($entry);
        if (!$s) {
            $m = (string) $z->getStatusString();
            $z->close();
            return [false, stripos($m, 'password') !== false ? 'password' : (stripos($m, 'not supported') !== false ? 'unsupported' : 'damaged')];
        }
        $data = '';
        while (!feof($s) && strlen($data) < $max) {
            $c = fread($s, min(65536, $max - strlen($data)));
            if ($c === false) {
                break;
            }
            $data .= $c;
        }
        fclose($s);
        $m = (string) $z->getStatusString();
        $z->close();
        if (stripos($m, 'password') !== false || stripos($m, 'CRC') !== false) {
            return [false, stripos($m, 'password') !== false ? 'password' : 'damaged'];
        }
        // an empty read of a non-empty entry is a wrong password the stream did not report
        if ($data === '') {
            $z2 = new ZipArchive();
            if ($z2->open($path, ZipArchive::RDONLY) === true) {
                $info = $z2->statName($entry);
                $z2->close();
                if (is_array($info) && (int) $info['size'] > 0) {
                    return [false, 'password'];
                }
            }
        }
        return [true, $data];
    }

    // What a file is, from its name list (which needs no password).
    // kind: 'db' (a dump, or a zip with dumps), 'files' (the bot's folder) or ''.
    function bkimp_analyze($path, $name, $size)
    {
        $it = ['id' => substr(bin2hex(random_bytes(4)), 0, 6), 'name' => (string) $name, 'file' => basename($path), 'size' => (int) $size,
            'kind' => '', 'zip' => false, 'dumps' => [], 'entry' => '', 'prefix' => '', 'locked' => false, 'pw' => '',
            'src' => '', 'ver' => '', 'cfgdb' => '', 'why' => ''];
        $fh = @fopen($path, 'rb');
        $magic = $fh ? (string) fread($fh, 4) : '';
        if ($fh) {
            fclose($fh);
        }
        if ($magic !== "PK\x03\x04") {
            $head = (string) @file_get_contents($path, false, null, 0, 1048576);
            if (bkimp_is_sql($head)) {
                $it['kind'] = 'db';
                $it['src'] = bkimp_sql_source($head);
            } else {
                $it['why'] = 'notbackup';
            }
            return $it;
        }
        $it['zip'] = true;
        if (!class_exists('ZipArchive')) {
            $it['why'] = 'unsupported';
            return $it;
        }
        $z = new ZipArchive();
        if ($z->open($path, ZipArchive::RDONLY) !== true || $z->numFiles < 1) {
            $it['why'] = 'damaged';
            return $it;
        }
        $names = [];
        for ($i = 0; $i < $z->numFiles; $i++) {
            $names[] = (string) $z->getNameIndex($i);
        }
        $dumps = array_values(array_filter($names, fn($n) => preg_match('/\.sql$/i', $n) && !preg_match('#(^|/)(vendor|node_modules)/#', $n)));
        // a bot folder: index.php beside function.php, the shallowest one, not an agent's bot
        $set = array_flip($names);
        $bots = [];
        foreach ($names as $n) {
            if (!preg_match('#(^|/)function\.php$#', $n)) {
                continue;
            }
            $p = substr($n, 0, -strlen('function.php'));
            if (!isset($set[$p . 'index.php']) || preg_match('#(^|/)(vendor|vpnbot)/#', '/' . $p)) {
                continue;
            }
            $bots[] = $p;
        }
        usort($bots, fn($a, $b) => substr_count($a, '/') <=> substr_count($b, '/'));
        if ($dumps) {
            $it['kind'] = 'db';
            $it['dumps'] = $dumps;
            $it['entry'] = count($dumps) === 1 ? $dumps[0] : '';
            $probe = $dumps[0];
        } elseif ($bots) {
            $it['kind'] = 'files';
            $it['prefix'] = $bots[0];
            $probe = $bots[0] . 'index.php';
        } else {
            $z->close();
            $it['why'] = 'notbackup';
            return $it;
        }
        $stat = $z->statName($probe);
        $it['locked'] = is_array($stat) && (int) ($stat['encryption_method'] ?? 0) !== 0;
        $z->close();
        if (!$it['locked']) {
            bkimp_fill($it);
        }
        return $it;
    }

    // what can only be read once the zip is open: whose database, which version
    function bkimp_fill(array &$it)
    {
        $path = bkimp_inbox() . '/' . $it['file'];
        if ($it['kind'] === 'db' && $it['zip']) {
            $entry = $it['entry'] !== '' ? $it['entry'] : $it['dumps'][0];
            [$ok, $data] = bkimp_zip_read($path, $entry, $it['pw'], 65536);
            if ($ok) {
                $it['src'] = bkimp_sql_source($data);
                if (!bkimp_is_sql($data)) {
                    $it['why'] = 'notbackup';
                }
            } else {
                $it['why'] = $data;
            }
            // each dump's database, for the list to choose from
            if (count($it['dumps']) > 1) {
                $it['dumpsrc'] = [];
                foreach (array_slice($it['dumps'], 0, 8) as $k => $d) {
                    [$ok2, $h] = bkimp_zip_read($path, $d, $it['pw'], 16384);
                    $it['dumpsrc'][$k] = $ok2 ? bkimp_sql_source($h) : '';
                }
            }
        } elseif ($it['kind'] === 'files') {
            [$ok, $cfg] = bkimp_zip_read($path, $it['prefix'] . 'config.php', $it['pw'], 65536);
            if ($ok && preg_match('/\$dbname\s*=\s*[\'"]([^\'"]*)[\'"]/', $cfg, $m)) {
                $it['cfgdb'] = $m[1];
            }
            [$ok, $ver] = bkimp_zip_read($path, $it['prefix'] . 'version', $it['pw'], 64);
            $it['ver'] = $ok ? substr(preg_replace('/[^A-Za-z0-9._-]/', '', $ver), 0, 40) : '';
            [$ok, $idx] = bkimp_zip_read($path, $it['prefix'] . 'index.php', $it['pw'], 4096);
            if (!$ok) {
                $it['why'] = $idx;
            }
        }
    }

    // A password for a locked zip: as typed, without a stray line end, without
    // outer spaces - the same three tries the script makes. true, or why not.
    function bkimp_unlock(array &$it, $pw)
    {
        $path = bkimp_inbox() . '/' . $it['file'];
        $probe = $it['kind'] === 'db' ? $it['dumps'][0] : $it['prefix'] . 'index.php';
        $why = 'password';
        foreach (array_unique([(string) $pw, str_replace(["\r", "\n"], '', (string) $pw), trim((string) $pw)]) as $v) {
            if ($v === '') {
                continue;
            }
            [$ok, $res] = bkimp_zip_read($path, $probe, $v, 4096);
            if ($ok) {
                $it['pw'] = $v;
                $it['locked'] = false;
                bkimp_fill($it);
                return true;
            }
            $why = $res;
            if ($res !== 'password') {
                break;
            }
        }
        return $why;
    }

    function bkimp_bot_version()
    {
        return trim((string) @file_get_contents(__DIR__ . '/version'));
    }

    // a folder older than this bot (or the original project, with no version)
    // is not laid over it - the script keeps only its data
    function bkimp_folder_is_old($ver)
    {
        $cur = ltrim(bkimp_bot_version(), 'vV');
        return $ver === '' || ($cur !== '' && version_compare(ltrim($ver, 'vV'), $cur, '<'));
    }

    // One file from Telegram into the inbox: [path, ''] or ['', why]
    function bkimp_download($fileId, $ext)
    {
        global $APIKEY;
        $r = telegram('getFile', ['file_id' => $fileId]);
        if (empty($r['ok']) || empty($r['result']['file_path'])) {
            return ['', (string) ($r['description'] ?? 'getFile')];
        }
        $path = bkimp_inbox() . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
        $fh = @fopen($path, 'wb');
        if (!$fh) {
            return ['', 'write'];
        }
        $ch = curl_init('https://api.telegram.org/file/bot' . $APIKEY . '/' . $r['result']['file_path']);
        curl_setopt_array($ch, [CURLOPT_FILE => $fh, CURLOPT_TIMEOUT => 120, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_FOLLOWLOCATION => true]);
        $ok = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = (string) curl_error($ch);
        curl_close($ch);
        fclose($fh);
        if (!$ok || $code !== 200 || (int) @filesize($path) === 0) {
            @unlink($path);
            return ['', $err !== '' ? $err : 'HTTP ' . $code];
        }
        @chmod($path, 0640);
        return [$path, ''];
    }

    // A file the web panel received, into the inbox under a name for what it
    // holds (a dump or a zip, whatever it was called): its path, or ''
    function bkimp_upload($tmp)
    {
        $fh = @fopen($tmp, 'rb');
        $magic = $fh ? (string) fread($fh, 4) : '';
        if ($fh) {
            fclose($fh);
        }
        $path = bkimp_inbox() . '/' . bin2hex(random_bytes(16)) . ($magic === "PK\x03\x04" ? '.zip' : '.sql');
        if (!@move_uploaded_file($tmp, $path)) {
            return '';
        }
        @chmod($path, 0640);
        return $path;
    }

    // a received file into the set: one database, one folder - a second of a kind replaces the first
    function bkimp_add($owner, array $it)
    {
        $st = bkimp_state($owner);
        $keep = [];
        foreach ($st['items'] as $old) {
            if ($old['kind'] === $it['kind']) {
                @unlink(bkimp_inbox() . '/' . basename($old['file']));
                continue;
            }
            $keep[] = $old;
        }
        $keep[] = $it;
        // the database first: it goes in first
        usort($keep, fn($a, $b) => ($a['kind'] === 'db' ? 0 : 1) <=> ($b['kind'] === 'db' ? 0 : 1));
        $st['items'] = $keep;
        bkimp_state($owner, $st);
        return $st;
    }

    // the first file still waiting for its password
    function bkimp_locked(array $st)
    {
        foreach ($st['items'] as $k => $it) {
            if (!empty($it['locked'])) {
                return $k;
            }
        }
        return null;
    }

    // a reason in words
    function bkimp_why($why, $t)
    {
        return $t['why'][$why] ?? $t['why']['damaged'];
    }

    // The one message the import lives in: the prompt while nothing has come
    // in, then what each file is, what will happen, and the buttons. $note
    // (a file that was turned away, a wrong password) goes on top.
    function bkimp_screen(array $st, $note = '')
    {
        global $dbname;
        $t = bkimp_texts();
        $cancel = [['text' => $t['cancelBtn'], 'callback_data' => 'bkimp_cancel', 'style' => 'danger']];
        if (empty($st['items'])) {
            return [($note !== '' ? $note . "\n\n" : '') . $t['askFile'], json_encode(['inline_keyboard' => [$cancel]])];
        }
        $lines = [$t['sumTitle']];
        if ($note !== '') {
            $lines[] = $note;
        }
        $kb = [];
        $ready = true;
        foreach ($st['items'] as $it) {
            $size = bkimp_size($it['size']);
            if ($it['kind'] === 'db') {
                $line = strtr($t['sumDb'], ['{name}' => htmlspecialchars($it['name']), '{size}' => $size]);
            } else {
                $line = strtr($t['sumFiles'], ['{name}' => htmlspecialchars($it['name']), '{size}' => $size,
                    '{ver}' => htmlspecialchars($it['locked'] ? '?' : ($it['ver'] !== '' ? $it['ver'] : $t['sumNoVersion']))]);
            }
            if (!empty($it['locked'])) {
                $ready = false;
                $lines[] = $line . "\n\n" . $t['sumLocked'];
                continue;
            }
            if ($it['kind'] === 'db') {
                if (count($it['dumps']) > 1) {
                    if ($it['entry'] === '') {
                        $ready = false;
                        $line .= "\n" . strtr($t['sumPick'], ['{n}' => count($it['dumps'])]);
                        foreach (array_slice($it['dumps'], 0, 8) as $k => $d) {
                            $src = $it['dumpsrc'][$k] ?? '';
                            $kb[] = [['text' => mb_substr(basename($d), 0, 40) . ($src !== '' ? ' (' . $src . ')' : ''), 'callback_data' => 'bkimp_pick|' . $it['id'] . '|' . $k, 'style' => 'primary']];
                        }
                    } else {
                        $line .= "\n" . strtr($t['sumEntry'], ['{entry}' => htmlspecialchars($it['entry'])]);
                    }
                }
                if ($it['src'] !== '' && !(count($it['dumps']) > 1 && $it['entry'] === '')) {
                    $line .= "\n" . strtr($t['sumFrom'], ['{db}' => htmlspecialchars($it['src'])]);
                    if ($it['src'] !== (string) $dbname) {
                        $line .= "\n" . $t['sumOtherDb'];
                    }
                }
                $line .= "\n" . $t['sumDbWhat'];
            } else {
                $old = bkimp_folder_is_old($it['ver']);
                $line .= "\n" . ($old ? strtr($t['sumFilesOld'], ['{cur}' => htmlspecialchars(bkimp_bot_version())]) : $t['sumFilesWhat']);
                if (!$old && $it['cfgdb'] !== '' && $it['cfgdb'] !== (string) $dbname) {
                    $line .= "\n" . $t['sumOtherBot'];
                }
            }
            if ($it['pw'] !== '') {
                $line .= "\n" . $t['sumUnlocked'];
            }
            $lines[] = $line;
        }
        $kinds = array_column($st['items'], 'kind');
        if (count($kinds) < 2 && $ready) {
            $lines[] = in_array('db', $kinds, true) ? $t['sumMoreFiles'] : $t['sumMoreDb'];
        }
        if ($ready) {
            $lines[] = $t['sumWarn'];
            $kb[] = [['text' => $t['goBtn'], 'callback_data' => 'bkimp_go', 'style' => 'success']];
        }
        $kb[] = $cancel;
        return [implode("\n\n", $lines), json_encode(['inline_keyboard' => $kb])];
    }

    // the last word before anything is touched
    function bkimp_confirm_screen(array $st)
    {
        $t = bkimp_texts();
        $what = [];
        foreach ($st['items'] as $it) {
            $what[] = $it['kind'] === 'db' ? $t['confirmDb'] : (bkimp_folder_is_old($it['ver']) ? $t['confirmFilesOld'] : $t['confirmFiles']);
        }
        return [strtr($t['confirm'], ['{what}' => implode("\n", $what)]), json_encode(['inline_keyboard' => [
            [['text' => $t['yesBtn'], 'callback_data' => 'bkimp_yes', 'style' => 'danger']],
            [['text' => $t['backBtn'], 'callback_data' => 'bkimp_back', 'style' => 'primary']],
        ]])];
    }

    // ready to go: every file known, unlocked, with its dump chosen
    function bkimp_ready(array $st)
    {
        if (empty($st['items'])) {
            return false;
        }
        foreach ($st['items'] as $it) {
            if (!empty($it['locked']) || !in_array($it['kind'], ['db', 'files'], true) || ($it['kind'] === 'db' && count($it['dumps']) > 1 && $it['entry'] === '')) {
                return false;
            }
        }
        return true;
    }

    // A file handed in (already in the inbox): analyzed and added - [state, '']
    // - or turned away and deleted: [state, why]. $name is what it was called.
    function bkimp_take($owner, $path, $name)
    {
        $it = bkimp_analyze($path, $name, (int) @filesize($path));
        if ($it['kind'] === '' || ($it['why'] !== '' && $it['why'] !== 'password')) {
            @unlink($path);
            return [bkimp_state($owner), $it['why'] !== '' ? $it['why'] : 'notbackup'];
        }
        if ($it['why'] === 'password') {
            // the name list said open, the data said otherwise: ask
            $it['locked'] = true;
            $it['why'] = '';
        }
        return [bkimp_add($owner, $it), ''];
    }

    // A password for a locked file (the first one, or the one with this id).
    // Returns [state, why, name, dropped]: why '' when it opened, 'password'
    // when it was wrong, else why it would not open; dropped when it opened
    // and held no backup after all (it is gone from the set then).
    function bkimp_password($owner, $pw, $id = null)
    {
        $st = bkimp_state($owner);
        $k = null;
        foreach ($st['items'] as $i => $it) {
            if (!empty($it['locked']) && ($id === null || $it['id'] === $id)) {
                $k = $i;
                break;
            }
        }
        if ($k === null) {
            return [$st, '', '', false];
        }
        $it = $st['items'][$k];
        $res = bkimp_unlock($it, $pw);
        if ($res !== true) {
            return [$st, $res, $it['name'], false];
        }
        if ($it['why'] !== '') {
            @unlink(bkimp_inbox() . '/' . $it['file']);
            array_splice($st['items'], $k, 1);
            bkimp_state($owner, $st);
            return [$st, $it['why'], $it['name'], true];
        }
        $st['items'][$k] = $it;
        bkimp_state($owner, $st);
        return [$st, '', $it['name'], false];
    }

    // one file out of the set, deleted
    function bkimp_remove($owner, $id)
    {
        $st = bkimp_state($owner);
        foreach ($st['items'] as $k => $it) {
            if ($it['id'] === $id) {
                @unlink(bkimp_inbox() . '/' . basename($it['file']));
                array_splice($st['items'], $k, 1);
                break;
            }
        }
        bkimp_state($owner, $st);
        return $st;
    }

    // What the watcher last said (restore_status.json): state queued (the
    // request still waits), running (with pct), done, failed or stale, at a
    // time, with each file's result - or null when it has said nothing yet.
    function bkimp_run_status()
    {
        $f = __DIR__ . '/restore_status.json';
        $j = is_file($f) ? json_decode((string) @file_get_contents($f), true) : null;
        if (is_file(__DIR__ . '/restore_request')) {
            return ['state' => 'queued', 'at' => time(), 'pct' => 0, 'results' => []];
        }
        if (!is_array($j)) {
            return is_file(__DIR__ . '/restore_request.running') ? ['state' => 'running', 'at' => time(), 'pct' => 0, 'results' => []] : null;
        }
        return ['state' => (string) ($j['state'] ?? ''), 'at' => (int) ($j['at'] ?? 0), 'pct' => (int) ($j['pct'] ?? 0), 'results' => is_array($j['results'] ?? null) ? $j['results'] : []];
    }

    // a dump chosen from a zip with several
    function bkimp_pick($owner, $id, $k)
    {
        $st = bkimp_state($owner);
        foreach ($st['items'] as &$it) {
            if ($it['id'] === $id && isset($it['dumps'][(int) $k])) {
                $it['entry'] = $it['dumps'][(int) $k];
                // whose database it is: the chosen dump's own header
                $it['src'] = (string) ($it['dumpsrc'][(int) $k] ?? $it['src']);
            }
        }
        unset($it);
        bkimp_state($owner, $st);
        return $st;
    }

    // the progress bar the watcher draws, at 0
    function bkimp_queued_text()
    {
        return strtr(bkimp_texts()['queued'], ['{bar}' => str_repeat('░', 10)]);
    }

    // The request for the watcher, and the message it will report in ($chat
    // null: none, the web panel). Written for root to read: a bare file name,
    // a kind, a dump name, a password.
    function bkimp_request(array $st, $chat = null, $msg = 0)
    {
        $t = bkimp_texts();
        $items = [];
        foreach ($st['items'] as $it) {
            if (!in_array($it['kind'], ['db', 'files'], true) || !preg_match('/^[a-f0-9]{32}\.(zip|sql)$/', $it['file'])) {
                return false;
            }
            $items[] = ['kind' => $it['kind'], 'file' => $it['file'], 'entry' => (string) $it['entry'], 'pw' => (string) $it['pw']];
        }
        if (!$items) {
            return false;
        }
        $c = __DIR__ . '/restore_progress.json';
        $r = __DIR__ . '/restore_request';
        // from the web panel ($chat null) there is no message to edit: its page reads restore_status.json
        @unlink($c);
        if ($chat !== null) {
            $ctx = ['chat_id' => (string) $chat, 'message_id' => (string) $msg, 'lang' => 'fa',
                'progress' => $t['progress'], 'done' => $t['donePlain'], 'fail' => $t['failPlain']];
            if (@file_put_contents($c, json_encode($ctx, JSON_UNESCAPED_UNICODE)) === false) {
                return false;
            }
        }
        if (@file_put_contents($r, json_encode(['items' => $items, 'at' => time()], JSON_UNESCAPED_UNICODE)) === false) {
            @unlink($c);
            @unlink($r);
            return false;
        }
        @chmod($r, 0660);
        return true;
    }

    // Called by the watcher (as root, in the bot's folder) once the restore is
    // over: what happened to each file, in the admin's message.
    function bkimp_finish_edit($chat, $msg, $lang, $resultsJson, $ok)
    {
        $t = bkimp_texts();
        $res = json_decode((string) $resultsJson, true);
        $lines = [$ok ? $t['doneTitle'] : $t['failTitle']];
        foreach (is_array($res) ? $res : [] as $r) {
            $what = ($r['kind'] ?? '') === 'files' ? $t['whatFiles'] : $t['whatDb'];
            if (!empty($r['ok'])) {
                if (($r['note'] ?? '') === 'old') {
                    $kept = [];
                    if ((int) ($r['agents'] ?? 0) > 0) {
                        $kept[] = strtr($t['keptAgents'], ['{n}' => (int) $r['agents']]);
                    }
                    if ((int) ($r['token'] ?? 0) > 0) {
                        $kept[] = $t['keptToken'];
                    }
                    $line = $t['resFilesOld'] . ($kept ? "\n" . strtr($t['resKept'], ['{what}' => implode('، ', $kept)]) : '');
                } else {
                    $line = ($r['kind'] ?? '') === 'files' ? $t['resFiles'] : $t['resDb'];
                }
            } else {
                $line = strtr($t['resFail'], ['{what}' => $what, '{why}' => bkimp_why((string) ($r['fail'] ?? ''), $t)]);
            }
            // where what the bot had before now lies (a failed import that could not put it back says so too)
            if (($r['safe'] ?? '') !== '') {
                $line .= "\n" . strtr($t['resSafe'], ['{path}' => htmlspecialchars((string) $r['safe'])]);
            }
            $lines[] = $line;
        }
        if ($ok) {
            $lines[] = $t['doneHint'];
        }
        telegram('editMessageText', [
            'chat_id' => $chat,
            'message_id' => (int) $msg,
            'text' => implode("\n\n", $lines),
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => [[['text' => $t['hubBtn'], 'callback_data' => 'bkimp_hub', 'style' => 'primary']]]]),
        ]);
        // the restored database knows nothing of this conversation
        if (function_exists('step')) {
            step('home', $chat);
        }
    }
}
