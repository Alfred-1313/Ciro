<?php
// Reconciliation for FrenzyEx (درگاه ارزی ریالی FrenzyEx): the signed
// payment.completed callback (payment/frenzyex.php) is the primary way a
// payment is confirmed - this cron is only the guide's own documented
// recovery path for a callback that never arrives or is delayed. It just
// ASKS FrenzyEx for the current status; the actual credit still goes
// through the exact same claim-before-DirectPayment() guard the callback
// itself uses (payment_Status='Unpaid' -> 'paid', atomically), so whichever
// of the two - a late callback or this poll - gets there first wins, and
// the other is safely a no-op.
//
// Same set cronbot/trx.php takes, and for the same reason: DirectPayment()
// creates the service through the panel classes, so panels.php and the
// composer autoloader have to be here. admin.php must NOT be required from
// a cron - see cronbot/trx.php's own note on that.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../jdf.php';
require __DIR__ . '/../vendor/autoload.php';

$setting = select("setting", "*", null, null, "select");
$textbotlang = languagechange(null, 'fa');
$GLOBALS['textbotlang'] = $textbotlang;

$apiKey = (string) (select("PaySetting", "ValuePay", "NamePay", "frenzyex_api_key", "select")['ValuePay'] ?? '');
if ($apiKey === '' || $apiKey === '0') {
    return; // never configured - nothing to reconcile
}

$rows = $pdo->query("SELECT * FROM Payment_report WHERE payment_Status = 'Unpaid' AND Payment_Method = 'frenzyex'")
    ->fetchAll(PDO::FETCH_ASSOC);
// An invoice the bot closed ('expire', cronbot/payment_expire.php) can still
// be paid: FrenzyEx settles through a forex/crypto processor, and a callback
// that was dropped meanwhile would leave a paid order never credited. So the
// last two days of expired ones are asked about too - one run in five (every
// fifteen minutes) and newest first, so the polling stays small.
if ((int) floor(time() / 180) % 5 === 0) {
    $expired = $pdo->query("SELECT * FROM Payment_report WHERE payment_Status = 'expire' AND Payment_Method = 'frenzyex' ORDER BY id DESC LIMIT 200")
        ->fetchAll(PDO::FETCH_ASSOC);
    $since = time() - 2 * 86400;
    $expired = array_filter($expired, function ($r) use ($since) {
        $t = strtotime((string) ($r['time'] ?? ''));
        return $t !== false && $t >= $since;
    });
    $rows = array_merge($rows, array_slice(array_values($expired), 0, 40));
}
if (empty($rows)) {
    return;
}
$fxHttpLogged = false;

foreach ($rows as $row) {
    $requestId = (string) ($row['dec_not_confirmed'] ?? '');
    if ($requestId === '') {
        continue;
    }
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://frenzy.fastsnap.info/api/v1/payment-requests/{$requestId}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
    ]);
    $response = curl_exec($curl);
    $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($httpCode < 200 || $httpCode >= 300) {
        // could not check right now - try again next tick. A wrong or revoked
        // API key shows up here as 401/403 on every row, and used to leave no
        // trace anywhere: say so once per run.
        if (!$fxHttpLogged && $httpCode !== 404) {
            error_log('frenzyex cron: status check answered HTTP ' . $httpCode . ' for request_id=' . $requestId . ($httpCode === 0 ? ' (no answer)' : ''));
            $fxHttpLogged = true;
        }
        continue;
    }
    $body = json_decode((string) $response, true);
    // the same two ways the callback recognises a paid request
    if (!is_array($body) || (($body['status'] ?? '') !== 'paid' && ($body['payment_status'] ?? '') !== 'finished')) {
        // pending stays pending; an expired/canceled request is left for
        // cronbot/payment_expire.php's own generic cutoff to mark - nothing
        // to credit either way
        continue;
    }
    $priceAmount = $body['price_amount'] ?? ($body['amount'] ?? null);
    if ($priceAmount === null || (float) $priceAmount != (float) $row['price']) {
        error_log(sprintf('frenzyex cron: paid but the amount differs - request_id=%s order=%s asked=%s confirmed=%s', $requestId, $row['id_order'], (string) $row['price'], (string) $priceAmount));
        continue; // same amount guard the callback itself applies
    }
    // claim before crediting - identical guard payment/frenzyex.php's own
    // callback uses
    $claim = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'paid' WHERE id_order = ? AND payment_Status IN ('Unpaid', 'expire')");
    $claim->execute([$row['id_order']]);
    if ($claim->rowCount() !== 1) {
        continue;
    }
    try {
        DirectPayment($row['id_order'], "../images.jpg");
    } catch (\Throwable $e) {
        // already 'paid' - a later run cannot credit it twice, nor finish it
        error_log(sprintf('frenzyex cron: DirectPayment failed - order=%s: %s', $row['id_order'], $e->getMessage()));
        if (strlen((string) ($setting['Channel_Report'] ?? '')) > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => select("topicid", "idreport", "report", "errorreport", "select")['idreport'] ?? null,
                'text' => sprintf("❌ FrenzyEx: پرداخت تایید شد ولی شارژ با خطا روبه‌رو شد.\nسفارش: %s\nrequest_id: %s\nخطا: %s", $row['id_order'], $requestId, htmlspecialchars($e->getMessage())),
                'parse_mode' => "HTML",
            ]);
        }
        continue;
    }
    $pricecashback = select("PaySetting", "ValuePay", "NamePay", "chashbackfrenzyex", "select")['ValuePay'];
    $reportUser = select("user", "*", "id", $row['id_user'], "select");
    if ($pricecashback != "0") {
        $result = ($row['price'] * $pricecashback) / 100;
        // into the wallet the payment was made in
        wallet_credit($reportUser['id'], $result, payment_currency($row));
        $text_report = sprintf($textbotlang['paymentGateway']['giftReport'], $result);
        sendmessage($reportUser['id'], $text_report, null, 'HTML');
    }
    if (strlen((string) ($setting['Channel_Report'] ?? '')) > 0) {
        $paymentreports = select("topicid", "idreport", "report", "paymentreport", "select")['idreport'];
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => sprintf(
                $textbotlang['hardcoded']['frenzyexPaymentLog'],
                $row['id_user'],
                $reportUser['username'] ?? '-',
                number_format($row['price']),
                $row['id_order'],
                $requestId
            ),
            'parse_mode' => "HTML",
        ]);
    }
}
