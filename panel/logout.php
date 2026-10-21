<?php
require_once __DIR__ . '/inc/config.php';
session_start();
$_SESSION = [];
session_destroy();
// the saved login ends with it
remember_forget();
header('Location: login.php');
exit;
