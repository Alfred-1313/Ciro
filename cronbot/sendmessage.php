<?php
date_default_timezone_set('Asia/Tehran');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';
$textbotlang = languagechange();
if(!is_file('info'))return;
if(!is_file('users.json'))return;


$userid = json_decode(file_get_contents('users.json'));
if(is_file('info')){
$info = json_decode(file_get_contents('info'),true);
}
$count = 0;
if(count($userid) == 0){
    if(isset($info['id_admin'])){
    deletemessage($info['id_admin'], $info['id_message']);
    sendmessage($info['id_admin'], $textbotlang['hardcoded']['bulkMessageDone'], null, 'HTML');
    // how it went: sent, blocked the bot, failed - and why (the commonest reasons)
    $bm_sent = (int) ($info['sent'] ?? 0);
    $bm_blocked = (int) ($info['blocked'] ?? 0);
    $bm_failed = (int) ($info['failed'] ?? 0);
    if ($bm_sent + $bm_blocked + $bm_failed > 0 && isset($textbotlang['hardcoded']['bulkMessageStats'])) {
        $bm_reasons = (array) ($info['reasons'] ?? []);
        arsort($bm_reasons);
        $bm_why = '';
        foreach (array_slice($bm_reasons, 0, 3, true) as $bm_r => $bm_n) {
            $bm_why .= "\n• " . htmlspecialchars((string) $bm_r) . ' × ' . (int) $bm_n;
        }
        sendmessage($info['id_admin'], sprintf($textbotlang['hardcoded']['bulkMessageStats'], $bm_sent, $bm_blocked, $bm_failed, $bm_why), null, 'HTML');
    }
    unlink('info');
    unlink('users.json');
    }
    return;
    
}
// the buttons under the message are in its readers' language (the whole
// list is one language tab's users); the admin's progress stays Persian
$bm_lang = in_array($info['lang'] ?? '', panel_langs(), true) ? $info['lang'] : 'fa';
$bm_tx = lang_tab_texts($bm_lang);
$count_remein = count($userid);
$textprocces = sprintf($textbotlang['hardcoded']['bulkMessageProgress'], $count_remein);
$cancelmessage = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['keyboard']['cancelOperation'], 'callback_data' => 'cancel_sendmessage'],
            ],
        ]
    ]);
Editmessagetext($info['id_admin'], $info['id_message'],$textprocces, $cancelmessage);
// each button as that language's tab has it in 🎨 (👤 پیام‌ها و دکمه‌های مدیریت کاربر)
$bm_btn = function ($alias) use ($bm_lang, $bm_tx) {
    $def = genbtn_defs($alias, $bm_tx)[0];
    // marked with which button it is, for its own tap sticker (genbtn_tap_cb)
    return json_encode(['inline_keyboard' => [[genbtn_render($def, genbtn_override($bm_lang, genbtn_alias_to_key($alias), 0), genbtn_tap_cb($alias, $def['callback_data']))]]]);
};
$keyboardbuy = $bm_btn('b1');
$keyboardstart = $bm_btn('b2');
$keyboardusertest = $bm_btn('b3');
$keyboardhelpbtn = $bm_btn('b4');
$keyboardaffiliates = $bm_btn('b5');
$keyboardaddbalance = $bm_btn('b6');
// a broadcast the admin wrote goes out in this tab's 🎨 wrapping ({message} is
// the admin's text) with that item's sticker; the top-up gift has an item of
// its own and goes as it is
$bm_hint = !empty($info['hint']) ? $info['hint'] : 'users.broadcast.message';
if (empty($info['hint']) && ($info['type'] == "sendmessage" or $info['type'] == "xdaynotmessage")) {
    $info['message'] = um_bcast_wrap($bm_tx, $info['message']);
}
$bm_kbFor = [
    'none' => null,
    'buy' => $keyboardbuy,
    'start' => $keyboardstart,
    'usertestbtn' => $keyboardusertest,
    'helpbtn' => $keyboardhelpbtn,
    'affiliatesbtn' => $keyboardaffiliates,
    'addbalance' => $keyboardaddbalance,
];
$bm_copy = !empty($info['copy']) && ($info['type'] == "sendmessage" or $info['type'] == "xdaynotmessage");
// a forwarded or copied message cannot be wrapped, but its sticker can go before it
$bm_fwdSticker = '';
if (($info['type'] == "forwardmessage" || $bm_copy) && function_exists('bt_effective_sticker')) {
    $bm_layout = json_decode((string) (select("setting", "*", null, null, "select")['keyboardmain'] ?? ''), true);
    $bm_fwdSticker = bt_effective_sticker(is_array($bm_layout['text_stickers'] ?? null) ? $bm_layout['text_stickers'] : [], 'users.broadcast.message', $bm_lang);
}
for ($i = 0; $i < 20; $i++) {
    // twenty a run, in order, and no further than the list goes - it used to
    // skip every other user and then send to nobody when fewer were left
    if (empty($userid)) {
        break;
    }
    $iduser = array_shift($userid);
    $meesage = null;
    if ($info['type'] == "unpinmessage") {
        unpinmessage($iduser->id);
    } elseif ($bm_copy) {
        // the admin's own message, as it is: any kind, its formatting kept
        if ($bm_fwdSticker !== '') {
            sticker_value_send($iduser->id, $bm_fwdSticker);
        }
        $meesage = copyMessage($iduser->id, $info['id_admin'], $info['message'], $bm_kbFor[$info['btnmessage'] ?? 'none'] ?? null);
        if (is_array($meesage) && ($meesage['description'] ?? '') == "Forbidden: bot was blocked by the user") {
            $invoicecount = select("invoice", "*", "id_user", $iduser->id, "count");
            $userinfo = select("user", "Balance", "id", $iduser->id, "select");
            if ($invoicecount == 0 and $userinfo['Balance'] == 0) {
                $stmt = $pdo->prepare("DELETE FROM user WHERE id = :mp1");
                $stmt->execute([':mp1' => $iduser->id]);
            }
        }
        if (!empty($meesage['ok']) && ($info['pingmessage'] ?? 'no') == "yes") {
            pinmessage($iduser->id, $meesage['result']['message_id']);
        }
    } elseif ($info['type'] == "sendmessage" or $info['type'] == "xdaynotmessage") {
        // its 🎨 sticker, in each reader's language
        if (function_exists('bottext_extras_key_hint')) {
            bottext_extras_key_hint($bm_hint);
        }
        if ($info['btnmessage'] == "none") {
            $meesage = sendmessage($iduser->id, $info['message'], null, 'HTML');
        } elseif ($info['btnmessage'] == "buy") {
            $meesage = sendmessage($iduser->id, $info['message'], $keyboardbuy, 'HTML');
        } elseif ($info['btnmessage'] == "start") {
            $meesage = sendmessage($iduser->id, $info['message'], $keyboardstart, 'HTML');
        } elseif ($info['btnmessage'] == "usertestbtn") {
            $meesage = sendmessage($iduser->id, $info['message'], $keyboardusertest, 'HTML');
        } elseif ($info['btnmessage'] == "helpbtn") {
            $meesage = sendmessage($iduser->id, $info['message'], $keyboardhelpbtn, 'HTML');
        } elseif ($info['btnmessage'] == "affiliatesbtn") {
            $meesage = sendmessage($iduser->id, $info['message'], $keyboardaffiliates, 'HTML');
        } elseif ($info['btnmessage'] == "addbalance") {
            $meesage = sendmessage($iduser->id, $info['message'], $keyboardaddbalance, 'HTML');
        }

        if ($meesage['ok'] == false and $meesage['description'] == "Forbidden: bot was blocked by the user") {
            $invoicecount = select("invoice", "*", "id_user", $iduser->id, "count");
            $userinfo = select("user", "Balance", "id", $iduser->id, "select");
            if ($invoicecount == 0 and $userinfo['Balance'] == 0) {
                $Id_user = $iduser->id;
                $stmt = $pdo->prepare("DELETE FROM user WHERE id = :mp1");
                $stmt->execute([':mp1' => $Id_user]);
            }
        }

        if ($meesage['ok'] and $info['pingmessage'] == "yes") {
            pinmessage($iduser->id, $meesage['result']['message_id']);
        }
    } elseif ($info['type'] == "forwardmessage") {
        if ($bm_fwdSticker !== '') {
            sticker_value_send($iduser->id, $bm_fwdSticker);
        }
        $meesage = forwardMessage($info['id_admin'], $info['message'], $iduser->id);
        if ($meesage['ok'] and $info['pingmessage'] == "yes") {
            pinmessage($iduser->id, $meesage['result']['message_id']);
        }
    }
    if (is_array($meesage)) {
        if (!empty($meesage['ok'])) {
            $info['sent'] = (int) ($info['sent'] ?? 0) + 1;
        } elseif (stripos((string) ($meesage['description'] ?? ''), 'blocked by the user') !== false
            || stripos((string) ($meesage['description'] ?? ''), 'user is deactivated') !== false) {
            $info['blocked'] = (int) ($info['blocked'] ?? 0) + 1;
        } else {
            $info['failed'] = (int) ($info['failed'] ?? 0) + 1;
            $bm_why = mb_substr((string) ($meesage['description'] ?? 'error'), 0, 120);
            $info['reasons'][$bm_why] = (int) ($info['reasons'][$bm_why] ?? 0) + 1;
        }
    }
}
// the counts go back into the run's own file - unless the admin cancelled
// meanwhile (that deletes it, and it must stay deleted)
if (is_file('info')) {
    file_put_contents('info', json_encode($info, JSON_UNESCAPED_UNICODE));
}

file_put_contents('users.json',json_encode($userid,true));