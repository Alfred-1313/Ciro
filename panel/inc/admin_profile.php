<?php
// The admin signed in to the panel, as Telegram knows them: their name and
// profile picture, fetched through the bot (getChat) and cached under
// storage/cache for 6 hours. A page only reads the cache; when it is old the
// page asks avatar.php?info=1 after it has loaded, which goes to Telegram, so
// a slow Telegram never holds a page up. If Telegram does not answer, or the
// person's privacy settings hide the photo, the panel username and an
// initial are shown instead, as before.

if (!function_exists('panel_admin_profile')) {
    // the profile is fetched again after this long
    define('PANEL_PROFILE_TTL', 6 * 3600);
    // a lookup that failed is not tried again for this long
    define('PANEL_PROFILE_RETRY', 3600);

    function panel_profile_cache($id, $ext)
    {
        $d = __DIR__ . '/../../storage/cache';
        if (!is_dir($d)) {
            @mkdir($d, 0775, true);
        }
        return $d . '/admin_profile_' . preg_replace('/[^0-9]/', '', (string) $id) . '.' . $ext;
    }

    // one Bot API call with short timeouts; a page load never waits long for Telegram
    function panel_tg_call($method, array $params)
    {
        global $APIKEY;
        $ch = curl_init('https://api.telegram.org/bot' . $APIKEY . '/' . $method);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $params, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 5]);
        $raw = curl_exec($ch);
        curl_close($ch);
        $j = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($j) && !empty($j['ok']) ? $j['result'] : null;
    }

    // a photo's file, saved as $dest; true when it is there
    function panel_tg_fetch_file($fileId, $dest)
    {
        global $APIKEY;
        $f = panel_tg_call('getFile', ['file_id' => $fileId]);
        if (!is_array($f) || empty($f['file_path'])) {
            return false;
        }
        $ch = curl_init('https://api.telegram.org/file/bot' . $APIKEY . '/' . $f['file_path']);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 8]);
        $data = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        // a picture and nothing else: JPEG or PNG, under 1 MB
        if (!is_string($data) || $code !== 200 || strlen($data) > 1048576
            || (strncmp($data, "\xFF\xD8\xFF", 3) !== 0 && strncmp($data, "\x89PNG", 4) !== 0)) {
            return false;
        }
        $tmp = $dest . '.tmp';
        if (@file_put_contents($tmp, $data) === false) {
            return false;
        }
        return @rename($tmp, $dest);
    }

    // The signed-in admin: ['name', 'username' (no @), 'photo' (a cached
    // file's path, or ''), 'v' (its version, for the address), 'stale' (the
    // cache wants refreshing)]. Only $refresh goes to Telegram.
    function panel_admin_profile($refresh = false)
    {
        global $pdo;
        static $memo = null;
        if ($memo !== null && !$refresh) {
            return $memo;
        }
        $login = (string) ($_SESSION['admin_user'] ?? '');
        $memo = ['name' => $login, 'username' => '', 'photo' => '', 'v' => 0, 'stale' => false];
        try {
            $row = db_fetch($pdo, "SELECT id_admin FROM admin WHERE username = ?", [$login]);
        } catch (Throwable $e) {
            $row = null;
        }
        $id = (string) ($row['id_admin'] ?? '');
        if (!preg_match('/^[0-9]{1,20}$/', $id)) {
            return $memo;
        }
        $jf = panel_profile_cache($id, 'json');
        $pf = panel_profile_cache($id, 'jpg');
        $c = is_file($jf) ? json_decode((string) @file_get_contents($jf), true) : null;
        $age = is_array($c) ? time() - (int) ($c['at'] ?? 0) : PHP_INT_MAX;
        $stale = !is_array($c) || $age > (!empty($c['ok']) ? PANEL_PROFILE_TTL : PANEL_PROFILE_RETRY);
        if ($stale && !$refresh) {
            $memo['stale'] = true;
            $c = is_array($c) ? $c : ['name' => '', 'username' => ''];
        } elseif ($stale) {
            $c = ['at' => time(), 'ok' => false, 'name' => (string) ($c['name'] ?? ''), 'username' => (string) ($c['username'] ?? '')];
            $chat = panel_tg_call('getChat', ['chat_id' => $id]);
            if (is_array($chat)) {
                $c['ok'] = true;
                $c['name'] = trim((string) ($chat['first_name'] ?? '') . ' ' . (string) ($chat['last_name'] ?? ''));
                $c['username'] = (string) ($chat['username'] ?? '');
                // the photo in getChat, else the newest of their profile photos the bot may see
                $fid = (string) ($chat['photo']['small_file_id'] ?? '');
                if ($fid === '') {
                    $ph = panel_tg_call('getUserProfilePhotos', ['user_id' => $id, 'limit' => 1]);
                    $sizes = $ph['photos'][0] ?? [];
                    // the smallest that is still sharp in a 36-pixel circle
                    foreach ($sizes as $s) {
                        if ((int) ($s['width'] ?? 0) >= 120) {
                            $fid = (string) $s['file_id'];
                            break;
                        }
                    }
                    if ($fid === '' && $sizes) {
                        $fid = (string) end($sizes)['file_id'];
                    }
                }
                if ($fid === '' || !panel_tg_fetch_file($fid, $pf)) {
                    // no photo any more (removed or hidden): none is shown
                    if ($fid === '') {
                        @unlink($pf);
                    }
                }
            }
            @file_put_contents($jf, json_encode($c, JSON_UNESCAPED_UNICODE));
        }
        if ((string) ($c['name'] ?? '') !== '') {
            $memo['name'] = $c['name'];
        }
        $memo['username'] = (string) ($c['username'] ?? '');
        if (is_file($pf)) {
            $memo['photo'] = $pf;
            $memo['v'] = (int) @filemtime($pf);
        }
        return $memo;
    }
}
