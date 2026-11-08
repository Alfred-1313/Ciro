<?php
// Reconciliation for Tronado (درگاه ارزی ریالی ترونادو): the signed IPN
// (payment/tronado_ipn.php) is the main way an order is confirmed - this only
// asks Tronado (/Order/GetStatusByPaymentID) about the open orders whose IPN
// never arrived: a domain not registered with Tronado's support yet, a
// moment the shop was down. The credit itself goes through the very same
// tronado_settle() the IPN uses, which claims the row before crediting, so
// whichever of the two gets there first wins and the other is a no-op.
//
// Same requires as cronbot/frenzyex.php, for the same reason: DirectPayment()
// builds the service through the panel classes. admin.php must NOT be
// required from a cron.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../jdf.php';
require __DIR__ . '/../vendor/autoload.php';

$setting = select("setting", "*", null, null, "select");
$textbotlang = languagechange(null, 'fa');
$GLOBALS['textbotlang'] = $textbotlang;

if (tronado_setting('tronado_api_key') === '') {
    return; // never configured - nothing to reconcile
}

$rows = $pdo->query("SELECT * FROM Payment_report WHERE payment_Status = 'Unpaid' AND Payment_Method = 'tronado'")
    ->fetchAll(PDO::FETCH_ASSOC);
// An invoice the bot closed ('expire', cronbot/payment_expire.php) can still
// be paid on Tronado's page - the customer pays a card and waits for Tronado
// to accept the receipt. So the last two days of expired ones are asked about
// too, one run in five (every fifteen minutes), newest first, at most forty.
if ((int) floor(time() / 180) % 5 === 0) {
    $expired = $pdo->query("SELECT * FROM Payment_report WHERE payment_Status = 'expire' AND Payment_Method = 'tronado' ORDER BY id DESC LIMIT 200")
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

$trLogged = false;
foreach ($rows as $row) {
    $status = tronado_order_status($row['id_order']);
    if (!is_array($status)) {
        // could not ask right now - next run. A wrong API key shows up here on
        // every row, so it is said once per run rather than forty times.
        if (!$trLogged) {
            error_log('tronado cron: status check failed for order=' . $row['id_order'] . ' (no answer, or the API key is refused)');
            $trLogged = true;
        }
        continue;
    }
    if (isset($status['Error'])) {
        // Tronado does not know this PaymentID (yet) - nothing to credit
        continue;
    }
    $result = tronado_settle($row, $status);
    // 'short' has already been logged and reported once, by tronado_settle()
    if (!in_array($result, ['unpaid', 'credited', 'already', 'short'], true)) {
        error_log('tronado cron: order=' . $row['id_order'] . ' - ' . $result);
    }
}
