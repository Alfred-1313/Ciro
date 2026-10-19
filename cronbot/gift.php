<?php
// 🎁 حجم یا زمان گروهی: works on the run going on, a few minutes at a time,
// and hands the rest straight on to the next request (bulkgift.php,
// bulkgift_cron). Called every 5 minutes by cron, and right away when a run
// is started or there is more to do - so it must not stop when that request
// is dropped. 🚀 سرعت گروهی's run (bulkspeed.php) gets the time 🎁's left.
ignore_user_abort(true);
ini_set('error_log', 'error_log');
date_default_timezone_set('Asia/Tehran');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../function.php';
$textbotlang = languagechange();
$ManagePanel = new ManagePanel();

// what the old version of this page left behind (a file of usernames it went
// through a few at a time) is not picked up again
foreach (['gift', 'username.json'] as $old) {
    if (is_file(__DIR__ . '/' . $old)) {
        @unlink(__DIR__ . '/' . $old);
    }
}

$gift_budget = bulkgift_budget();
$gift_from = time();
bulkgift_cron($gift_budget);
bulkspeed_cron(max(5, $gift_budget - (time() - $gift_from)));
