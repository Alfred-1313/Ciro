<?php
require_once __DIR__ . '/inc/config.php';
require_auth();
require_once __DIR__ . '/inc/admin_profile.php';

// The signed-in admin's Telegram profile, for the panel's sidebar.
//   avatar.php?info=1  refreshes it from Telegram when it is old and answers
//                      with the name and the photo's address (JSON)
//   avatar.php         the photo itself: it lives in storage/cache, which the
//                      web server does not serve, so it is sent from here, to
//                      the signed-in admin only
if (isset($_GET['info'])) {
    $p = panel_admin_profile(true);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode([
        'name' => $p['name'],
        'username' => $p['username'],
        'src' => $p['photo'] !== '' ? 'avatar.php?v=' . $p['v'] : '',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
$p = panel_admin_profile();
if ($p['photo'] === '' || !is_file($p['photo'])) {
    http_response_code(404);
    exit;
}
$head = (string) @file_get_contents($p['photo'], false, null, 0, 4);
header('Content-Type: ' . (strncmp($head, "\x89PNG", 4) === 0 ? 'image/png' : 'image/jpeg'));
header('Content-Length: ' . filesize($p['photo']));
// the address carries the photo's version, so a new photo is a new address
header('Cache-Control: private, max-age=86400');
header('X-Content-Type-Options: nosniff');
readfile($p['photo']);
