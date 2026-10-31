<?php
// 🖥 نودهای ربکا ← 🔔 پایش: every two minutes, every Rebecca panel's nodes
// against what they were (rbnodes.php, rbn_watch_tick) - each change kept, a
// node going down or coming back reported.
ini_set('error_log', 'error_log');
date_default_timezone_set('Asia/Tehran');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../jdf.php';
$textbotlang = languagechange();
$setting = select("setting", "*", null, null, "select");

rbn_watch_tick();
