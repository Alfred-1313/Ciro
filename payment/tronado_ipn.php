<?php
// Tronado's IPN (درگاه ارزی ریالی ترونادو, API v5): a server-to-server POST
// for EVERY status change of an order, signed with X-Tronado-Sig =
// hex(HMAC-SHA512(raw body, IpnSigningKey)). Also accepts Tronado's optional
// Dispute Callback at this same address (Event = DisputeAccepted).
//
// The body is never trusted on its own: a paid order is credited only after
// Tronado's own API (/Order/GetStatusByPaymentID, with this shop's API key)
// says so, through tronado_settle() - the same function cronbot/tronado.php
// uses, which claims the row before crediting, so a repeated IPN, the cron
// and an IPN at the same moment can never credit twice.
//
// Any 2xx tells Tronado to stop; anything else makes it try again, which is
// what a moment when Tronado's own API could not be asked needs (503).
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

$trLog = function ($msg) {
    error_log('tronado ipn: ' . $msg);
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('POST only');
}

// 1) the signature, over the EXACT raw body. Required once the admin has set
// the IpnSigningKey. Without one, the body is still only a hint - nothing is
// credited unless Tronado's own API confirms it below - so a shop that has not
// received its key from Tronado's support yet still works.
$rawBody = (string) file_get_contents('php://input');
$ipnKey = tronado_setting('tronado_ipn_key');
if ($ipnKey !== '') {
    $sig = strtolower(trim((string) ($_SERVER['HTTP_X_TRONADO_SIG'] ?? '')));
    if ($sig === '' || !hash_equals(hash_hmac('sha512', $rawBody, $ipnKey), $sig)) {
        $trLog('rejected (401) - ' . ($sig === '' ? 'no X-Tronado-Sig header' : 'signature does not match the IpnSigningKey'));
        http_response_code(401);
        exit('Invalid signature');
    }
}

$data = json_decode($rawBody, true);
if (!is_array($data)) {
    http_response_code(400);
    exit('Invalid body');
}

// 2) a Dispute Callback: reported to the admin, nothing changed by itself
if (isset($data['Event'])) {
    if ((string) $data['Event'] === 'DisputeAccepted') {
        if ($ipnKey === '') {
            // unlike an IPN there is nothing to ask Tronado's API about, so an
            // unsigned one cannot be told apart from a forged one
            $trLog('dispute ignored - the IpnSigningKey is not set, so it cannot be verified');
        } else {
            tronado_dispute_report($data);
        }
    } else {
        $trLog('unknown event ' . (string) $data['Event'] . ' - logged only');
    }
    http_response_code(200);
    exit('OK');
}

// 3) the order this IPN is about - PaymentID is this bot's id_order
$paymentId = (string) ($data['PaymentId'] ?? ($data['PaymentID'] ?? ''));
$row = $paymentId !== '' ? select("Payment_report", "*", "id_order", $paymentId, "select") : null;
if (!is_array($row) || empty($row) || ($row['Payment_Method'] ?? '') !== 'tronado') {
    // not an order of this gateway (another shop's, Tronado's own test IPN) -
    // acknowledged so Tronado stops resending, nothing fulfilled
    $trLog('no tronado order with PaymentId=' . $paymentId . ' in this shop');
    http_response_code(200);
    exit('OK');
}
if ($row['payment_Status'] === 'paid') {
    http_response_code(200);
    exit('OK');
}

// 4) only a payment the body itself calls finished is worth asking about -
// waiting for the receipt (20, 25, 27), rejected (40), cancelled (200)
$bodyStatus = (int) ($data['OrderStatusID'] ?? 0);
if (!tronado_is_paid($data)) {
    $trLog(sprintf('order=%s status=%d - nothing to credit yet', $paymentId, $bodyStatus));
    http_response_code(200);
    exit('OK');
}

// 5) Tronado's own word, then the one settle path
$status = tronado_order_status($paymentId);
if (!is_array($status) || isset($status['Error'])) {
    $trLog('could not confirm order=' . $paymentId . ' with Tronado (' . (is_array($status) ? (string) $status['Error'] : 'no answer') . ') - asking for a retry');
    http_response_code(503);
    exit('Retry later');
}
$result = tronado_settle($row, $status, $data);
if (!in_array($result, ['credited', 'already', 'short'], true)) {
    $trLog('order=' . $paymentId . ' - ' . $result);
}
http_response_code(200);
echo 'OK';
