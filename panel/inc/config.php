<?php

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

require __DIR__ . '/../../config.php';
require __DIR__ . '/../../function.php';

// Panel UI language strings (loaded from lang/fa.php via languagechange())
$textbotlang = languagechange();

function db_query(PDO $pdo, string $sql, array $params = []): PDOStatement
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function db_fetch(PDO $pdo, string $sql, array $params = []): ?array
{
    return db_query($pdo, $sql, $params)->fetch() ?: null;
}

function db_fetchAll(PDO $pdo, string $sql, array $params = []): array
{
    return db_query($pdo, $sql, $params)->fetchAll();
}

function db_count(PDO $pdo, string $sql, array $params = []): int
{
    return (int) db_query($pdo, $sql, $params)->fetchColumn();
}
function require_auth(): void
{
    if (session_status() === PHP_SESSION_NONE)
        session_start();
    global $pdo;
    // «🤖 پنل تحت وب: خاموش ❌» in the bot: nobody in, a session already open included
    if (!webpanel_enabled()) {
        $_SESSION = [];
        session_destroy();
        header('Location: login.php');
        exit;
    }
    if (empty($_SESSION['admin_user'])) {
        // no session (it was swept, or the browser was closed): the saved login
        $name = remember_restore();
        if ($name === null) {
            header('Location: login.php');
            exit;
        }
        session_regenerate_id(true);
        $_SESSION['admin_user'] = $name;
        $_SESSION['login_time'] = time();
    }
    try {
        $admin = db_fetch($pdo, "SELECT id_admin, rule FROM admin WHERE username = ?", [$_SESSION['admin_user']]);
        if (!$admin || $admin['rule'] !== 'administrator') {
            session_destroy();
            header('Location: login.php');
            exit;
        }
    } catch (Exception $e) {
        session_destroy();
        header('Location: login.php');
        exit;
    }
}

// ── «ذخیره ورود»: stay signed in for 24 hours, from the same IP ──
// A signed cookie, not a PHP session: the server's session files are swept
// after 24 idle minutes (Debian's cron does it whatever the script asks), so a
// session cannot hold a day. The cookie carries the admin's name, its expiry
// and the IP it was given to, signed with a key made from the bot's token and
// the admin's own password hash - a changed password, a moved IP, a deleted
// admin or a forged cookie each end it.
const PANEL_REMEMBER_COOKIE = 'panel_remember';
const PANEL_REMEMBER_SECONDS = 86400;

function panel_client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function panel_cookie_options(int $expires): array
{
    return [
        'expires' => $expires,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function remember_key(string $pwHash): string
{
    global $APIKEY;
    return hash_hmac('sha256', $pwHash, (string) $APIKEY . '|panel-remember');
}

function remember_issue(string $username, string $pwHash): void
{
    $exp = time() + PANEL_REMEMBER_SECONDS;
    $payload = rtrim(strtr(base64_encode(json_encode(['u' => $username, 'e' => $exp, 'ip' => panel_client_ip()])), '+/', '-_'), '=');
    $sig = hash_hmac('sha256', $payload, remember_key($pwHash));
    setcookie(PANEL_REMEMBER_COOKIE, $payload . '.' . $sig, panel_cookie_options($exp));
}

function remember_forget(): void
{
    setcookie(PANEL_REMEMBER_COOKIE, '', panel_cookie_options(time() - 3600));
    unset($_COOKIE[PANEL_REMEMBER_COOKIE]);
}

// the admin's name when the cookie is whole, unexpired, for this IP and for an
// administrator who still has the password it was signed with; otherwise null
function remember_restore(): ?string
{
    global $pdo;
    $cookie = (string) ($_COOKIE[PANEL_REMEMBER_COOKIE] ?? '');
    if ($cookie === '' || substr_count($cookie, '.') !== 1) {
        return null;
    }
    [$payload, $sig] = explode('.', $cookie, 2);
    $data = json_decode((string) base64_decode(strtr($payload, '-_', '+/'), true), true);
    if (!is_array($data) || !is_string($data['u'] ?? null) || (int) ($data['e'] ?? 0) < time()
        || !hash_equals((string) ($data['ip'] ?? ''), panel_client_ip())) {
        remember_forget();
        return null;
    }
    try {
        $admin = db_fetch($pdo, "SELECT username, password, rule FROM admin WHERE username = ?", [$data['u']]);
    } catch (Exception $e) {
        return null;
    }
    if (!$admin || $admin['rule'] !== 'administrator'
        || !hash_equals(hash_hmac('sha256', $payload, remember_key((string) $admin['password'])), $sig)) {
        remember_forget();
        return null;
    }
    return (string) $admin['username'];
}

function csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE)
        session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

// A session that never showed a form has no token - and an empty token
// "matched" it, so anything went through without one. Both must be there.
function csrf_check_value(string $token): bool
{
    $own = (string) ($_SESSION['csrf'] ?? '');
    return $own !== '' && $token !== '' && hash_equals($own, $token);
}

function csrf_check_post(): void
{
    global $textbotlang;
    $token = (string) ($_POST['_csrf'] ?? '');
    if (!csrf_check_value($token)) {
        http_response_code(403);
        die($textbotlang['panel']['configInvalidRequest']);
    }
}

function csrf_check_get(): void
{
    global $textbotlang;
    $token = (string) ($_GET['_csrf'] ?? '');
    if (!csrf_check_value($token)) {
        http_response_code(403);
        die($textbotlang['panel']['configInvalidRequest']);
    }
}

function flash(string $key, string $msg): void
{
    if (session_status() === PHP_SESSION_NONE)
        session_start();
    $_SESSION["flash_{$key}"] = $msg;
}

function get_flash(string $key): ?string
{
    if (session_status() === PHP_SESSION_NONE)
        session_start();
    $msg = $_SESSION["flash_{$key}"] ?? null;
    unset($_SESSION["flash_{$key}"]);
    return $msg;
}

function trunc(string $str, int $max = 30): string
{
    return mb_strlen($str, 'UTF-8') > $max
        ? mb_substr($str, 0, $max, 'UTF-8') . '…'
        : $str;
}

function safe_date($ts, string $fmt = 'Y/m/d'): string
{
    if (!$ts)
        return '—';
    if (!is_numeric($ts))
        return htmlspecialchars((string) $ts);
    return date($fmt, (int) $ts);
}
// A user's id that opens the profile: the number as text, and a link when it is a real
// id (a «0» or a word stays as it is). One look for every list that names a user.
function user_link($id): string
{
    global $textbotlang;
    $s = trim((string) $id);
    if ($s === '' || $s === '0' || !ctype_digit($s)) {
        return $s === '' ? '—' : htmlspecialchars($s);
    }
    return '<a class="ulink" href="user.php?id=' . $s . '" title="' . htmlspecialchars($textbotlang['panel']['ulOpenProfile'], ENT_QUOTES, 'UTF-8') . '">' . $s . '</a>';
}

function check_login_rate(string $ip): bool
{
    $file = sys_get_temp_dir() . '/panel_login_' . md5($ip);
    $data = @json_decode(@file_get_contents($file) ?: '{}', true) ?: [];
    $now = time();
    $data = array_filter($data, fn($t) => ($now - $t) < 900);
    if (count($data) >= 10)
        return false;
    $data[] = $now;
    @file_put_contents($file, json_encode(array_values($data)), LOCK_EX);
    return true;
}

function clear_login_rate(string $ip): void
{
    @unlink(sys_get_temp_dir() . '/panel_login_' . md5($ip));
}

function user_role_label(string $agent): string
{
    global $textbotlang;
    return match ($agent) {
        'n' => $textbotlang['panel']['configRoleN'],
        'n2' => $textbotlang['panel']['configRoleN2'],
        'all' => $textbotlang['panel']['configRoleAll'],
        default => $textbotlang['panel']['configRoleDefault'],
    };
}

function user_role_tag(string $agent): string
{
    return match ($agent) {
        'f' => 'tag-info',
        'n' => 'tag-info',
        'n2' => 'tag-warn',
        'all' => 'tag-ok',
        default => 'tag-plain',
    };
}
