<?php
// Reconciliation for HooshPay (درگاه ارزی ریالی هوش‌پی): the signed callback
// (payment/hooshpay.php) is the main way an invoice is confirmed - this only
// asks HooshPay about the open invoices whose callback never arrived. A look
// first (GET /invoices/{uid}); only a paid one is verified and credited, and
// that goes through the very same hooshpay_settle() the callback uses, which
// claims the row before crediting - whichever gets there first wins.
//
// Same requires as cronbot/tronado.php, for the same reason: DirectPayment()
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

if (hooshpay_setting('hooshpay_api_key') === '') {
    return; // never configured - nothing to reconcile
}

$rows = $pdo->query("SELECT * FROM Payment_report WHERE payment_Status = 'Unpaid' AND Payment_Method = 'hooshpay'")
    ->fetchAll(PDO::FETCH_ASSOC);
// An invoice the bot closed ('expire', cronbot/payment_expire.php) can still be
// paid on HooshPay's page. So the last two days of expired ones are asked about
// too, one run in five (every fifteen minutes), newest first, at most forty.
if ((int) floor(time() / 180) % 5 === 0) {
    $expired = $pdo->query("SELECT * FROM Payment_report WHERE payment_Status = 'expire' AND Payment_Method = 'hooshpay' ORDER BY id DESC LIMIT 200")
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

$hpLogged = false;
foreach ($rows as $row) {
    $uid = trim(explode('|', (string) ($row['dec_not_confirmed'] ?? ''))[0]);
    // a mismatch has already been reported once - nothing more to ask
    if ($uid === '' || strpos((string) $row['dec_not_confirmed'], '| MISMATCH') !== false) {
        continue;
    }
    $look = hooshpay_invoice_status($uid);
    if (!is_array($look)) {
        // could not ask right now - next run. A wrong API key shows up here on
        // every row, so it is said once per run rather than forty times.
        if (!$hpLogged) {
            error_log('hooshpay cron: status check failed for invoice=' . $uid . ' (no answer, or the API key is refused)');
            $hpLogged = true;
        }
        continue;
    }
    $status = (string) ($look['data']['status'] ?? ($look['status'] ?? ''));
    if ($status !== 'paid') {
        // pending, expired, cancelled, failed - nothing to credit
        continue;
    }
    $verify = hooshpay_invoice_verify($uid);
    if (!is_array($verify)) {
        continue;
    }
    $result = hooshpay_settle($row, $verify);
    if (!in_array($result, ['unpaid', 'credited', 'already', 'mismatch'], true)) {
        error_log('hooshpay cron: order=' . $row['id_order'] . ' - ' . $result);
    }
}
