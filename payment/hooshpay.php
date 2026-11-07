<?php
// HooshPay's payment.success callback (درگاه ارزی ریالی هوش‌پی): a
// server-to-server POST with a JSON body, signed with X-HooshPay-Signature
// (HMAC-SHA256 with the account's Secret - see hooshpay_signature_ok()).
//
// The body is never trusted on its own: an invoice is credited only after
// HooshPay's own API (POST /invoices/{uid}/verify, with this shop's API key)
// says it is paid, through hooshpay_settle() - the same function
// cronbot/hooshpay.php uses, which claims the row before crediting, so a
// resent callback, the cron and a callback at the same moment can never
// credit twice.
//
// HooshPay retries anything but 200, which is what a moment its API could
// not be asked needs (503); everything that is settled or ignored gets 200.
ini_set('error_log', 'error_log');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../Marzban.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../keyboard.php';
require_once __DIR__ . '/../panels.php';
require __DIR__ . '/../vendor/autoload.php';

$ManagePanel = new ManagePanel();
$setting = select("setting", "*");
$textbotlang = languagechange(null, 'fa');

$hpLog = function ($msg) {
    error_log('hooshpay callback: ' . $msg);
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('POST only');
}

// 1) the signature. Required once the admin has set the Secret. Without
// one the body is only a hint - nothing is credited unless HooshPay's own
// API confirms it below - so a shop that has not copied its Secret yet still
// gets paid.
$rawBody = (string) file_get_contents('php://input');
$secret = hooshpay_setting('hooshpay_secret');
if ($secret !== '' && !hooshpay_signature_ok($rawBody, $_SERVER['HTTP_X_HOOSHPAY_SIGNATURE'] ?? '', $secret)) {
    $hpLog('rejected (401) - ' . (($_SERVER['HTTP_X_HOOSHPAY_SIGNATURE'] ?? '') === '' ? 'no X-HooshPay-Signature header' : 'signature does not match the Secret'));
    http_response_code(401);
    exit('Invalid signature');
}

$data = json_decode($rawBody, true);
if (!is_array($data)) {
    http_response_code(400);
    exit('Invalid body');
}

// 2) only a successful payment is acted on
if ((string) ($data['event'] ?? '') !== 'payment.success') {
    $hpLog('ignored - event=' . (string) ($data['event'] ?? '') . ' invoice=' . (string) ($data['invoice'] ?? ''));
    http_response_code(200);
    exit('OK');
}

// 3) the order it is about - order_id is this bot's id_order, and the
// invoice uid stored with it has to be the one the callback names
$orderId = (string) ($data['order_id'] ?? '');
$uid = (string) ($data['invoice'] ?? '');
$row = $orderId !== '' ? select("Payment_report", "*", "id_order", $orderId, "select") : null;
$storedUid = is_array($row) ? trim(explode('|', (string) ($row['dec_not_confirmed'] ?? ''))[0]) : '';
if (!is_array($row) || empty($row) || ($row['Payment_Method'] ?? '') !== 'hooshpay' || $uid === '' || $storedUid !== $uid) {
    // not an invoice of this shop (or not this one) - acknowledged so
    // HooshPay stops resending, nothing fulfilled
    $hpLog('no hooshpay order ' . $orderId . ' with invoice ' . $uid . ' in this shop');
    http_response_code(200);
    exit('OK');
}
if ($row['payment_Status'] === 'paid') {
    http_response_code(200);
    exit('OK');
}

// 4) HooshPay's own word, then the one settle path
$verify = hooshpay_invoice_verify($uid);
if (!is_array($verify)) {
    $hpLog('could not verify invoice ' . $uid . ' (order ' . $orderId . ') with HooshPay - asking for a retry');
    http_response_code(503);
    exit('Retry later');
}
$result = hooshpay_settle($row, $verify);
if (!in_array($result, ['credited', 'already', 'mismatch'], true)) {
    $hpLog('order=' . $orderId . ' - ' . $result);
}
http_response_code(200);
echo 'OK';
