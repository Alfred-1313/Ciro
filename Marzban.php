<?php
include('config.php');
require_once 'request.php';
date_default_timezone_set('Asia/Tehran');
#-----------------------------#
function token_panel($code_panel, $verify = true)
{
    $panel = select("marzban_panel", "*", "code_panel", $code_panel, "select");

    // Check if panel data exists
    if ($panel === null) {
        return null;
    }

    $url_get_token = $panel['url_panel'] . '/api/admin/token';
    $username_panel = $panel['username_panel'];
    $password_panel = $panel['password_panel'];
    if ($panel['datelogin'] != null && $verify) {
        $date = json_decode($panel['datelogin'], true);
        if (isset($date['time'])) {
            $start_date = time() - strtotime($date['time']);
            // PasarGuard announces every admin login (log line + Telegram/
            // Discord notice). Its token says itself when it ends (24 hours by
            // default), so it is kept until 5 minutes before that instead of
            // logging in again every hour; a token the panel refuses earlier
            // (password changed) is replaced at once by pasarguard_request().
            if (!empty($date['exp']) && !empty($date['access_token'])) {
                if (time() < (int) $date['exp'] - 300) {
                    return $date;
                }
            } elseif ($start_date <= 3600) {
                return $date;
            }
        }
    }
    $data_token = array(
        'username' => $username_panel,
        'password' => $password_panel
    );
    $headers = array(
        'Content-Type: application/x-www-form-urlencoded',
        'accept: application/json'
    );
    $req = new CurlRequest($url_get_token);
    $req->setHeaders($headers);
    $response = $req->post($data_token);
    if (!empty($response['error'])) {
        return array("error" => $response['error']);
    }
    $body = json_decode($response['body'], true);
    if (isset($body['access_token'])) {
        $time = date('Y/m/d H:i:s');
        $login = array(
            'time' => $time,
            'access_token' => $body['access_token']
        );
        if (pasarguard_panel($panel)) {
            $exp = panel_token_exp($body['access_token']);
            if ($exp !== null) {
                $login['exp'] = $exp;
            }
        }
        update("marzban_panel", "datelogin", json_encode($login), 'name_panel', $panel['name_panel']);
    }
    return $body;
}
#-----------------------------#
// The "exp" claim of a JWT (unix time), or null when it has none - a panel
// set to tokens that never expire, or a token that is not a JWT at all.
function panel_token_exp($jwt)
{
    $parts = explode('.', (string) $jwt);
    if (count($parts) !== 3) {
        return null;
    }
    $b64 = strtr($parts[1], '-_', '+/');
    $b64 .= str_repeat('=', (4 - strlen($b64) % 4) % 4);
    $payload = json_decode((string) base64_decode($b64, true), true);
    if (!is_array($payload) || !isset($payload['exp']) || !is_numeric($payload['exp'])) {
        return null;
    }
    return (int) $payload['exp'];
}
#-----------------------------#
// PasarGuard is stored as type "marzban" with version_panel "1".
//
// Since v5 every per-user route addressed by name - /api/user/{username}/...
// and /api/user/by-username/{username}/... - is deprecated: each call writes a
// DeprecationWarning into the panel's log, and the routes are removed in
// v6.0.0. Only the /api/user/by-id/{id}/... routes are clean. So for
// PasarGuard every per-user call first looks the user up in the user list
// (GET /api/users?username=..., which is not deprecated and also answers with
// the whole user) and then uses the by-id route. Written against PasarGuard
// v5.6.0 (app/routers/user.py, app/operation/user.py).
function pasarguard_panel($panel)
{
    return is_array($panel) && (string) ($panel['version_panel'] ?? '') === "1";
}
// One request to a PasarGuard panel, with the cached login. A token the panel
// refuses (401: password changed, token revoked) is replaced by a fresh login
// and the request is sent once more. Answers in CurlRequest's shape.
function pasarguard_request(array $panel, $method, $path, $payload = null)
{
    $token = token_panel($panel['code_panel']);
    for ($try = 0; $try < 2; $try++) {
        if (!empty($token['error'])) {
            return array('status' => null, 'body' => null, 'error' => $token['error']);
        }
        if (!isset($token['access_token'])) {
            // the login itself was refused: its answer is this request's answer
            return array(
                'status' => 401,
                'body' => json_encode(is_array($token) && $token !== [] ? $token : array('detail' => 'login failed'))
            );
        }
        $req = new CurlRequest(rtrim((string) $panel['url_panel'], '/') . $path);
        $headers = array('accept: application/json');
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
        }
        $req->setHeaders($headers);
        $req->setBearerToken($token['access_token']);
        $body = $payload === null ? null : json_encode($payload);
        switch (strtoupper($method)) {
            case 'POST':
                $response = $req->post($body);
                break;
            case 'PUT':
                $response = $req->put($body);
                break;
            case 'DELETE':
                $response = $req->delete($body);
                break;
            default:
                $response = $req->get();
        }
        if ((int) ($response['status'] ?? 0) !== 401 || $try === 1) {
            return $response;
        }
        $token = token_panel($panel['code_panel'], false);
    }
    return $response;
}
// The user as PasarGuard keeps it, found by name through the user list.
// Answers like CurlRequest: status 200 with the one user as the body, 404 with
// {"detail":"User not found"} (the panel's own words), or the panel's error.
// The id is remembered for the rest of this run, so a lookup followed by a
// change costs one lookup.
function pasarguard_find_user(array $panel, $username, $withSub = false)
{
    $query = 'username=' . rawurlencode((string) $username) . '&limit=1' . ($withSub ? '&load_sub=true' : '');
    $response = pasarguard_request($panel, 'GET', '/api/users?' . $query);
    if (!empty($response['error']) || (int) ($response['status'] ?? 0) !== 200) {
        return $response;
    }
    $list = json_decode((string) $response['body'], true);
    if (!is_array($list) || !isset($list['users']) || !is_array($list['users'])) {
        return array('status' => 502, 'body' => json_encode(array('detail' => 'bad answer')));
    }
    foreach ($list['users'] as $found) {
        if (is_array($found) && isset($found['id']) && (string) ($found['username'] ?? '') === (string) $username) {
            pasarguard_user_id_cache($panel, $username, (int) $found['id']);
            return array('status' => 200, 'body' => json_encode($found), 'id' => (int) $found['id']);
        }
    }
    pasarguard_user_id_cache($panel, $username, null);
    return array('status' => 404, 'body' => json_encode(array('detail' => 'User not found')));
}
function pasarguard_user_id_cache(array $panel, $username, $id = false)
{
    static $ids = array();
    $key = $panel['code_panel'] . '|' . $username;
    if ($id === false) {
        return $ids[$key] ?? null;
    }
    if ($id === null) {
        unset($ids[$key]);
    } else {
        $ids[$key] = $id;
    }
    return $id;
}
// A request to /api/user/by-id/{id}{$suffix} for the user with this name.
// A user that is not on the panel answers 404 {"detail":"User not found"},
// exactly as the old by-name route did.
function pasarguard_user_request(array $panel, $username, $method, $suffix = '', $payload = null)
{
    $id = pasarguard_user_id_cache($panel, $username);
    if ($id === null) {
        $found = pasarguard_find_user($panel, $username);
        if (!isset($found['id'])) {
            return $found;
        }
        $id = $found['id'];
    }
    $response = pasarguard_request($panel, $method, '/api/user/by-id/' . $id . $suffix, $payload);
    if ((int) ($response['status'] ?? 0) === 404) {
        // the id was remembered from earlier in this run and the user has gone since
        pasarguard_user_id_cache($panel, $username, null);
    }
    return $response;
}
// Protocol settings to give a NEW user, taken from a sample user or from the
// location's saved settings: only the choices (vless flow, shadowsocks
// method) are kept, every credential is dropped so the panel makes a fresh
// one for each user. Listing what to keep rather than what to drop matters:
// PasarGuard v5 added hysteria ("auth") and masque ("password"), and
// dropping only the known keys copied one sample user's Hysteria/Masque
// password to every customer. A protocol added later is safe the same way.
function pasarguard_template_proxies($proxies)
{
    $keep = array('vless' => array('flow'), 'shadowsocks' => array('method'));
    if (is_string($proxies)) {
        $proxies = json_decode($proxies, true);
    }
    $out = array();
    foreach ((array) $proxies as $protocol => $settings) {
        $clean = array();
        foreach ((array) $settings as $name => $value) {
            if (in_array($name, $keep[$protocol] ?? array(), true) && $value !== null && $value !== '') {
                $clean[$name] = $value;
            }
        }
        $out[$protocol] = (object) $clean;
    }
    return (object) $out;
}
// The configs of a PasarGuard subscription, one per line.
// {sub}/raw answers them as JSON without counting as the customer's own
// subscription update: the plain link records every fetch (with the bot's
// browser user agent) as the user's latest update, which is what the
// service screen then showed as "last update: just now / Chrome", and with
// HWID on it takes up one of the user's device slots. Older panels without
// /raw, or ones that hide configs from browsers, fall back to the link.
function pasarguard_sub_links($subscriptionUrl)
{
    $subscriptionUrl = (string) $subscriptionUrl;
    if ($subscriptionUrl === '') {
        return array();
    }
    $raw = json_decode((string) outputlink(rtrim($subscriptionUrl, '/') . '/raw'), true);
    $links = is_array($raw) ? ($raw['body']['links'] ?? null) : null;
    if (!is_array($links) || $links === array()) {
        $plain = trim((string) outputlink($subscriptionUrl));
        if ($plain !== '' && isBase64($plain)) {
            $plain = base64_decode($plain);
        }
        $links = explode("\n", $plain);
    }
    $out = array();
    foreach ($links as $link) {
        $link = trim((string) $link);
        // a config is a URI (vless://, ss://, hysteria2://...); anything else
        // is an error page or a JSON refusal, not something to hand a customer
        if ($link !== '' && strpos($link, '://') !== false) {
            $out[] = $link;
        }
    }
    return $out;
}
// PasarGuard answers 204 with no body to a delete; the callers were written
// for Marzban's 200 {"detail":"User successfully deleted"}.
function pasarguard_deleted(array $response, $detail)
{
    if ((int) ($response['status'] ?? 0) === 204) {
        return array('status' => 200, 'body' => json_encode(array('detail' => $detail)));
    }
    return $response;
}
#-----------------------------#

function getuser($username_account, $location)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
    if (pasarguard_panel($marzban_list_get)) {
        return pasarguard_find_user($marzban_list_get, $username_account, true);
    }
    $Check_token = token_panel($marzban_list_get['code_panel']);
    if (!empty($Check_token['error'])) {
        return $Check_token;
    }
    $url = $marzban_list_get['url_panel'] . '/api/user/' . $username_account;
    $headers = array(
        'accept: application/json'
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setBearerToken($Check_token['access_token']);
    $response = $req->get();
    return $response;
}
#-----------------------------#
// Per-node usage for ONE user - not to be confused with Get_usage_Nodes()
// below, which is the whole panel's node traffic. Marzban answers
// {"usages":[{"node_id":..,"node_name":"..","used_traffic":..}, ...]}, the same
// shape Rebecca uses, so panel_user_usage() normalises them identically.
// NOTE: written to Marzban's documented API - this shop runs no Marzban panel,
// so unlike the Rebecca path it could not be verified against a live server.
function getusage($username_account, $location)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
    if (pasarguard_panel($marzban_list_get)) {
        return pasarguard_user_usage($marzban_list_get, $username_account);
    }
    $Check_token = token_panel($marzban_list_get['code_panel']);
    if (!empty($Check_token['error'])) {
        return $Check_token;
    }
    // rtrim so a panel URL stored with a trailing slash cannot produce a double
    // slash, which some panels answer with an unfollowed 301
    $url = rtrim((string) $marzban_list_get['url_panel'], '/') . '/api/user/' . $username_account . '/usage';
    $req = new CurlRequest($url);
    $req->setHeaders(array('accept: application/json'));
    $req->setBearerToken($Check_token['access_token']);
    return $req->get();
}
#-----------------------------#

function Get_Nodes($location)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
    $Check_token = token_panel($marzban_list_get['code_panel']);
    if (!empty($Check_token['error'])) {
        return $Check_token;
    }
    $url = $marzban_list_get['url_panel'] . '/api/nodes';
    $headers = array(
        'accept: application/json'
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setBearerToken($Check_token['access_token']);
    $response = $req->get();
    return $response;
}
function Get_usage_Nodes($location)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
    if (pasarguard_panel($marzban_list_get)) {
        return pasarguard_nodes_usage($marzban_list_get);
    }
    $Check_token = token_panel($marzban_list_get['code_panel']);
    if (!empty($Check_token['error'])) {
        return $Check_token;
    }
    $url = $marzban_list_get['url_panel'] . '/api/nodes/usage';
    $headers = array(
        'accept: application/json'
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setBearerToken($Check_token['access_token']);
    $response = $req->get();
    return $response;
}
function Get_Node($location, $Nodeid)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
    $Check_token = token_panel($marzban_list_get['code_panel']);
    if (!empty($Check_token['error'])) {
        return $Check_token;
    }
    $url = $marzban_list_get['url_panel'] . '/api/node/' . $Nodeid;
    $headers = array(
        'accept: application/json'
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setBearerToken($Check_token['access_token']);
    $response = $req->get();
    return $response;
}

function getusers($location, $status)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
    $Check_token = token_panel($marzban_list_get['code_panel']);
    if (!empty($Check_token['error'])) {
        return $Check_token;
    }
    $url = $marzban_list_get['url_panel'] . '/api/users?status=' . $status;
    if (!isset($Check_token['access_token']))
        return;
    $header_value = 'Bearer ';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: application/json',
        'Authorization: ' . $header_value . $Check_token['access_token']
    ));

    $output = curl_exec($ch);
    $data_useer = json_decode($output, true);
    return $data_useer;
}
#-----------------------------#
function getinbounds($location)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
    $Check_token = token_panel($marzban_list_get['code_panel']);
    $url = $marzban_list_get['url_panel'] . '/api/inbounds';
    $header_value = 'Bearer ';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: application/json',
        'Authorization: ' . $header_value . $Check_token['access_token']
    ));

    $output = curl_exec($ch);
    $inbounds = json_decode($output, true);
    return $inbounds;
}
#-----------------------------#
function ResetUserDataUsage($username_account, $location)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
    if (pasarguard_panel($marzban_list_get)) {
        return pasarguard_user_request($marzban_list_get, $username_account, 'POST', '/reset');
    }
    $Check_token = token_panel($marzban_list_get['code_panel']);
    if (!empty($Check_token['error'])) {
        return $Check_token;
    }
    $url = $marzban_list_get['url_panel'] . '/api/user/' . $username_account . '/reset';

    $headers = array(
        'accept: application/json'
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setBearerToken($Check_token['access_token']);
    $response = $req->post(array());
    return $response;
}
function revoke_sub($username_account, $location)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
    if (pasarguard_panel($marzban_list_get)) {
        return pasarguard_user_request($marzban_list_get, $username_account, 'POST', '/revoke_sub');
    }
    $Check_token = token_panel($marzban_list_get['code_panel']);
    if (!empty($Check_token['error'])) {
        return $Check_token;
    }
    $url = $marzban_list_get['url_panel'] . '/api/user/' . $username_account . '/revoke_sub';
    $headers = array(
        'accept: application/json'
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setBearerToken($Check_token['access_token']);
    $response = $req->post(array());
    return $response;
}
#-----------------------------#
function adduser($location, $data_limit, $username_ac, $timestamp, $note = '', $data_limit_reset = 'no_reset', $name_product = false)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
    $Check_token = token_panel($marzban_list_get['code_panel']);
    if (!empty($Check_token['error'])) {
        return $Check_token;
    }
    $url = $marzban_list_get['url_panel'] . "/api/user";
    if ($marzban_list_get['inbounds'] != null and $marzban_list_get['inbounds'] != "null") {
        if ($name_product != false and $name_product != "usertest") {
            $product = select("product", "*", "name_product", $name_product, "select");
            if ($product == false || $product['inbounds'] == false) {
                $inbounds = json_decode($marzban_list_get['inbounds'], true);
            } else {
                $inbounds = json_decode($product['inbounds'], true);
                $marzban_list_get['proxies'] = $product['proxies'];
            }
        } else {
            $inbounds = json_decode($marzban_list_get['inbounds'], true);
        }
    }
    if (!panelProtocolsConfigured($marzban_list_get['proxies'])) {
        return panelProtocolsMissingError($marzban_list_get['name_panel']);
    }
    if ($marzban_list_get['version_panel'] == "1") {
        $data = array(
            "proxy_settings" => pasarguard_template_proxies($marzban_list_get['proxies']),
            "data_limit" => $data_limit,
            "username" => $username_ac,
            "note" => $note,
            "data_limit_reset_strategy" => $data_limit_reset
        );
        if (isset($inbounds)) {
            $data['group_ids'] = $inbounds;
        }
        if ($name_product == "usertest") {
            if ($marzban_list_get['on_hold_test'] == "0") {
                if ($timestamp == 0) {
                    $data["expire"] = 0;
                } else {
                    $data["expire"] = date('c', $timestamp);
                }
            } else {
                if ($timestamp == 0) {
                    $data["expire"] = 0;
                } else {
                    $data["expire"] = 0;
                    $data["status"] = "on_hold";
                    $data["on_hold_expire_duration"] = $timestamp - time();
                }
            }
        } else {
            if ($marzban_list_get['conecton'] == "offconecton") {
                if ($timestamp == 0) {
                    $data["expire"] = 0;
                } else {
                    $data["expire"] = date('c', $timestamp);
                }
            } else {
                if ($timestamp == 0) {
                    $data["expire"] = 0;
                } else {
                    $data["expire"] = 0;
                    $data["status"] = "on_hold";
                    $data["on_hold_expire_duration"] = $timestamp - time();
                }
            }
        }
    } else {
        $data = array(
            "proxies" => json_decode($marzban_list_get['proxies']),
            "data_limit" => $data_limit,
            "username" => $username_ac,
            "note" => $note,
            "data_limit_reset_strategy" => $data_limit_reset
        );
        if (isset($inbounds)) {
            $data['inbounds'] = $inbounds;
        }
        if ($name_product == "usertest") {
            if ($marzban_list_get['on_hold_test'] == "0") {
                if ($timestamp == 0) {
                    $data["expire"] = 0;
                } else {
                    $data["expire"] = $timestamp;
                }
            } else {
                if ($timestamp == 0) {
                    $data["expire"] = 0;
                } else {
                    $data["expire"] = 0;
                    $data["status"] = "on_hold";
                    $data["on_hold_expire_duration"] = $timestamp - time();
                }
            }
        } else {
            if ($marzban_list_get['conecton'] == "offconecton") {
                if ($timestamp == 0) {
                    $data["expire"] = 0;
                } else {
                    $data["expire"] = $timestamp;
                }
            } else {
                if ($timestamp == 0) {
                    $data["expire"] = 0;
                } else {
                    $data["expire"] = 0;
                    $data["status"] = "on_hold";
                    $data["on_hold_expire_duration"] = $timestamp - time();
                }
            }
        }
    }
    if (pasarguard_panel($marzban_list_get)) {
        $response = pasarguard_request($marzban_list_get, 'POST', '/api/user', $data);
        $made = json_decode((string) ($response['body'] ?? ''), true);
        if (is_array($made) && isset($made['id'], $made['username'])) {
            pasarguard_user_id_cache($marzban_list_get, $made['username'], (int) $made['id']);
        }
        return $response;
    }
    $headers = array(
        'accept: application/json',
        'Content-Type: application/json'
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setBearerToken($Check_token['access_token']);
    $response = $req->post(json_encode($data));
    return $response;
}
//----------------------------------
function Get_System_Stats($location)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
    $Check_token = token_panel($marzban_list_get['code_panel']);
    $url = $marzban_list_get['url_panel'] . '/api/system';
    $header_value = 'Bearer ';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: application/json',
        'Authorization: ' . $header_value . $Check_token['access_token']
    ));

    $output = curl_exec($ch);
    $Get_System_Stats = json_decode($output, true);
    return $Get_System_Stats;
}
//----------------------------------
function removeuser($location, $username)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
    if (pasarguard_panel($marzban_list_get)) {
        $response = pasarguard_deleted(pasarguard_user_request($marzban_list_get, $username, 'DELETE'), 'User successfully deleted');
        pasarguard_user_id_cache($marzban_list_get, $username, null);
        return $response;
    }
    $Check_token = token_panel($marzban_list_get['code_panel']);
    if (!empty($Check_token['error'])) {
        return $Check_token;
    }
    $url = $marzban_list_get['url_panel'] . '/api/user/' . $username;
    $headers = array(
        'accept: application/json'
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setBearerToken($Check_token['access_token']);
    $response = $req->delete();
    return $response;
}
function removenode($location, $nodeid)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
    if (pasarguard_panel($marzban_list_get)) {
        return pasarguard_deleted(pasarguard_request($marzban_list_get, 'DELETE', '/api/node/' . (int) $nodeid), 'Node successfully deleted');
    }
    $Check_token = token_panel($marzban_list_get['code_panel']);
    if (!empty($Check_token['error'])) {
        return $Check_token;
    }
    $url = $marzban_list_get['url_panel'] . '/api/node/' . $nodeid;
    $headers = array(
        'accept: application/json'
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setBearerToken($Check_token['access_token']);
    $response = $req->delete();
    return $response;
}
//----------------------------------
function Modifyuser($location, $username, array $data)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
    if (pasarguard_panel($marzban_list_get)) {
        return pasarguard_user_request($marzban_list_get, $username, 'PUT', '', $data);
    }
    $Check_token = token_panel($marzban_list_get['code_panel']);
    if (!empty($Check_token['error'])) {
        return $Check_token;
    }
    $url = $marzban_list_get['url_panel'] . '/api/user/' . $username;
    $headers = array(
        'accept: application/json',
        'Content-Type: application/json'
    );
    $payload = json_encode($data);
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setBearerToken($Check_token['access_token']);
    $response = $req->put($payload);
    return $response;
}
//----------------------------------

function Modifyuser_node($location, $id_node, array $data)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
    $Check_token = token_panel($marzban_list_get['code_panel']);
    $url = $marzban_list_get['url_panel'] . '/api/node/' . $id_node;
    $payload = json_encode($data);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    $headers = array();
    $headers[] = 'Accept: application/json';
    $headers[] = 'Authorization: Bearer ' . $Check_token['access_token'];
    $headers[] = 'Content-Type: application/json';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    $data_useer = json_decode($result, true);
    return $data_useer;
}
function hosts($location)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
    $Check_token = token_panel($marzban_list_get['code_panel']);
    $url = $marzban_list_get['url_panel'] . '/api/hosts';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    $headers = array();
    $headers[] = 'Accept: application/json';
    $headers[] = 'Authorization: Bearer ' . $Check_token['access_token'];
    $headers[] = 'Content-Type: application/json';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    $data_hosts = $result;
    return $data_hosts;
}
//----------------------------------
function reconnect_node($location, $id_node)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
    $Check_token = token_panel($marzban_list_get['code_panel']);
    if (!empty($Check_token['error'])) {
        return $Check_token;
    }
    $url = $marzban_list_get['url_panel'] . '/api/node/' . $id_node . '/reconnect';
    $headers = array(
        'accept: application/json'
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setBearerToken($Check_token['access_token']);
    $response = $req->post(array());
    return $response;
}

function get_list_update($location, $username)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
    if (pasarguard_panel($marzban_list_get)) {
        return pasarguard_user_request($marzban_list_get, $username, 'GET', '/sub_update?offset=0&limit=1');
    }
    $Check_token = token_panel($marzban_list_get['code_panel']);
    if (!empty($Check_token['error'])) {
        return $Check_token;
    }
    $url = $marzban_list_get['url_panel'] . "/api/user/$username/sub_update?offset=0&limit=1";
    $headers = array(
        'accept: application/json'
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setBearerToken($Check_token['access_token']);
    $response = $req->get();
    return $response;
}
#-----------------------------#
// One user's traffic per node over the last 30 days, in the shape Marzban
// gives and panel_user_usage() reads: {"usages":[{node_id, node_name,
// used_traffic}]}. PasarGuard answers {"stats":{node_id:[{period_start,
// total_traffic}]}} keyed by node id (0 = the panel's own core), so the rows
// are added up per node and named from the node list.
function pasarguard_user_usage(array $panel, $username)
{
    // no start/end: the panel then takes the last 30 days itself (and a start
    // sent from here must carry exactly the panel's own timezone object)
    $response = pasarguard_user_request($panel, $username, 'GET', '/usage?period=day&group_by_node=true');
    if (!empty($response['error']) || (int) ($response['status'] ?? 0) !== 200) {
        return $response;
    }
    $body = json_decode((string) $response['body'], true);
    if (!is_array($body) || !isset($body['stats']) || !is_array($body['stats'])) {
        return array('status' => 502, 'body' => json_encode(array('detail' => 'bad answer')));
    }
    $names = pasarguard_node_names($panel);
    $usages = array();
    foreach ($body['stats'] as $nodeId => $rows) {
        $sum = 0;
        foreach ((array) $rows as $row) {
            $sum += (int) ($row['total_traffic'] ?? 0);
        }
        $nodeId = (int) $nodeId;
        $usages[] = array(
            'node_id' => $nodeId,
            'node_name' => $nodeId === 0 ? 'Master' : ($names[$nodeId] ?? ''),
            'used_traffic' => $sum,
        );
    }
    return array('status' => 200, 'body' => json_encode(array('usages' => $usages)));
}
// id => name of every node, or [] when the panel does not say
function pasarguard_node_names(array $panel)
{
    $response = pasarguard_request($panel, 'GET', '/api/nodes');
    $body = json_decode((string) ($response['body'] ?? ''), true);
    $names = array();
    if ((int) ($response['status'] ?? 0) === 200 && is_array($body)) {
        foreach ((array) ($body['nodes'] ?? array()) as $node) {
            if (is_array($node) && isset($node['id'])) {
                $names[(int) $node['id']] = (string) ($node['name'] ?? '');
            }
        }
    }
    return $names;
}
// Marzban's GET /api/nodes/usage does not exist on PasarGuard (404). Each node
// there carries its own counters, so the same {"usages":[{node_id, node_name,
// uplink, downlink}]} answer is made from the node list.
function pasarguard_nodes_usage(array $panel)
{
    $response = pasarguard_request($panel, 'GET', '/api/nodes');
    if (!empty($response['error']) || (int) ($response['status'] ?? 0) !== 200) {
        return $response;
    }
    $body = json_decode((string) $response['body'], true);
    $usages = array();
    foreach ((array) (is_array($body) ? ($body['nodes'] ?? array()) : array()) as $node) {
        if (!is_array($node) || !isset($node['id'])) {
            continue;
        }
        $usages[] = array(
            'node_id' => (int) $node['id'],
            'node_name' => (string) ($node['name'] ?? ''),
            'uplink' => (int) ($node['uplink'] ?? 0),
            'downlink' => (int) ($node['downlink'] ?? 0),
        );
    }
    return array('status' => 200, 'body' => json_encode(array('usages' => $usages)));
}
