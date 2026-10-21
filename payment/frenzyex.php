<?php
// FrenzyEx's own signed payment.completed callback (POST, raw JSON body + an
// X-Frenzy-Signature header) - unlike payment/zarinpal.php, this is NOT a
// browser redirect: it is a server-to-server notification FrenzyEx can also
// resend, so the credit is claimed atomically before DirectPayment() runs -
// the same guard function.php's own sms_forward_claim_order() already uses
// for card-to-card, so a resent notification can never double-credit.
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

// Every early exit below used to be silent, so a payment that was not
// credited left nothing to read afterwards. Now each one writes a line to
// payment/error_log, and the ones that mean "FrenzyEx says a customer paid
// and this shop did not credit it" also tell the admin in the report group.
$fxLog = function ($msg) {
    error_log('frenzyex callback: ' . $msg);
};
$fxAlert = function ($msg) use ($setting) {
    if (strlen((string) ($setting['Channel_Report'] ?? '')) === 0) {
        return;
    }
    $topic = select("topicid", "idreport", "report", "errorreport", "select")['idreport'] ?? null;
    telegram('sendmessage', [
        'chat_id' => $setting['Channel_Report'],
        'message_thread_id' => $topic,
        'text' => $msg,
        'parse_mode' => "HTML",
    ]);
};

// 1) verify the signature over the EXACT raw body - never a re-encoded copy
$rawBody = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_FRENZY_SIGNATURE'] ?? '';
$secret = (string) (select("PaySetting", "ValuePay", "NamePay", "frenzyex_callback_secret", "select")['ValuePay'] ?? '');

if ($secret === '' || $secret === '0' || $signature === '' || !hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature)) {
    $fxLog('rejected (401) - ' . ($secret === '' || $secret === '0' ? 'the callback secret is not set in the bot' : ($signature === '' ? 'no X-Frenzy-Signature header' : 'signature does not match the callback secret')));
    http_response_code(401);
    exit('Invalid signature');
}

$data = json_decode($rawBody, true);
if (!is_array($data)) {
    http_response_code(400);
    exit('Invalid body');
}

// a connectivity test - acknowledged, never fulfils a real order
if (($data['event'] ?? '') === 'payment.test' || !empty($data['test'])) {
    http_response_code(200);
    exit('OK');
}

// 2) only a completed, paid event is ever acted on ('finished' is the
// guide's own documented compatibility alias for payment_status)
$status = (string) ($data['status'] ?? '');
$paymentStatus = (string) ($data['payment_status'] ?? '');
if (($data['event'] ?? '') !== 'payment.completed' || ($status !== 'paid' && $paymentStatus !== 'finished')) {
    $fxLog('ignored - event=' . ($data['event'] ?? '') . ' status=' . $status . ' payment_status=' . $paymentStatus . ' request_id=' . ($data['request_id'] ?? ''));
    http_response_code(200);
    exit('OK');
}

// 3) look up the pending row this request belongs to
$requestId = (string) ($data['request_id'] ?? '');
$Payment_report = $requestId !== '' ? select("Payment_report", "*", "dec_not_confirmed", $requestId, "select") : null;
if (!is_array($Payment_report) || empty($Payment_report)) {
    // unknown here (a different merchant's request, or never created by this
    // shop) - acknowledged so FrenzyEx stops resending, nothing fulfilled
    $fxLog('no invoice with request_id=' . $requestId . ' in this shop');
    http_response_code(200);
    exit('OK');
}

// cross-check the confirmed amount against what was actually asked for
// (per the guide: never trust the callback amount blindly)
$priceAmount = $data['price_amount'] ?? null;
$priceCurrency = (string) ($data['price_currency'] ?? '');
if ($priceCurrency !== 'TMN' || $priceAmount === null || (float) $priceAmount != (float) $Payment_report['price']) {
    $fxLog(sprintf('amount mismatch - request_id=%s order=%s asked=%s confirmed=%s %s', $requestId, $Payment_report['id_order'], (string) $Payment_report['price'], (string) $priceAmount, $priceCurrency));
    $fxAlert(sprintf(
        "⚠️ FrenzyEx: مبلغ فاکتور با مبلغ ثبت‌شده جور درنیومد.\nrequest_id: %s\nسفارش: %s\nمبلغ ثبت‌شده: %s\nمبلغ تایید‌شده: %s %s",
        $requestId,
        $Payment_report['id_order'],
        (string) $Payment_report['price'],
        (string) $priceAmount,
        $priceCurrency
    ));
    http_response_code(409);
    exit('Amount mismatch');
}

$invoice_id = $Payment_report['id_order'];

// 4) claim before crediting - only the request that actually flips the row
// goes on to call DirectPayment(), exactly the same idempotency shape
// sms_forward_claim_order() already uses elsewhere in this codebase
//
// 'expire' counts too: cronbot/payment_expire.php closes an invoice after its
// window (an hour by default), but FrenzyEx settles through a forex/crypto
// processor and its confirmation can arrive after that. The customer HAS paid
// by then - skipping the row (as this did) left a paid order never credited.
$claim = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'paid' WHERE id_order = ? AND payment_Status IN ('Unpaid', 'expire')");
$claim->execute([$invoice_id]);
if ($claim->rowCount() !== 1) {
    // already credited - this is exactly what makes a resent notification
    // safe. Any other state (rejected by an admin, ...) is not a repeat: the
    // admin has to look at it.
    $fxNow = (string) (select("Payment_report", "payment_Status", "id_order", $invoice_id, "select")['payment_Status'] ?? '');
    if ($fxNow !== 'paid') {
        $fxLog(sprintf('not credited - order=%s is "%s"', $invoice_id, $fxNow));
        $fxAlert(sprintf("⚠️ FrenzyEx: پرداخت تایید شد ولی سفارش شارژ نشد.\nسفارش: %s\nrequest_id: %s\nوضعیت سفارش در ربات: %s", $invoice_id, $requestId, $fxNow));
    }
    http_response_code(200);
    exit('OK');
}

try {
    DirectPayment($invoice_id, "../images.jpg");
} catch (\Throwable $e) {
    // the row is already 'paid', so a resend cannot credit it twice - and
    // cannot finish it either. The admin has to, knowing exactly which order.
    $fxLog(sprintf('DirectPayment failed - order=%s: %s', $invoice_id, $e->getMessage()));
    $fxAlert(sprintf("❌ FrenzyEx: پرداخت تایید شد ولی شارژ با خطا روبه‌رو شد.\nسفارش: %s\nrequest_id: %s\nخطا: %s", $invoice_id, $requestId, htmlspecialchars($e->getMessage())));
    http_response_code(200);
    exit('OK');
}

// optional cashback (FR-015), same shape as payment/zarinpal.php's own block
$pricecashback = select("PaySetting", "ValuePay", "NamePay", "chashbackfrenzyex", "select")['ValuePay'];
$Balance_id = select("user", "*", "id", $Payment_report['id_user'], "select");
if ($pricecashback != "0") {
    $result = ($Payment_report['price'] * $pricecashback) / 100;
    // into the wallet the payment was made in
    wallet_credit($Balance_id['id'], $result, payment_currency($Payment_report));
    $text_report = sprintf($textbotlang['paymentGateway']['giftReport'], $result);
    sendmessage($Balance_id['id'], $text_report, null, 'HTML');
}

$paymentreports = select("topicid", "idreport", "report", "paymentreport", "select")['idreport'];
$price_fmt = number_format($Payment_report['price']);
$text_report = sprintf(
    $textbotlang['hardcoded']['frenzyexPaymentLog'],
    $Payment_report['id_user'],
    $Balance_id['username'] ?? '-',
    $price_fmt,
    $Payment_report['id_order'],
    $requestId
);
if (strlen((string) ($setting['Channel_Report'] ?? '')) > 0) {
    telegram('sendmessage', [
        'chat_id' => $setting['Channel_Report'],
        'message_thread_id' => $paymentreports,
        'text' => $text_report,
        'parse_mode' => "HTML",
    ]);
}

http_response_code(200);
echo 'OK';
