<?php
// 🆕 What the LATEST update added - shown to every admin once, right after the
// update (bot_whatsnew_announce(), called at the end of table.php).
//
// Rewrite this file with each update: change 'id' (any new string) and replace
// the notes with what THIS update added and where to find it - only this
// update, not the history. Same 'id' as the last one sent = nothing is sent.
// Telegram HTML; one <blockquote> per item. Keep each text under 4000 chars.
return [
    'id' => '2026-10-21-a',
    'fa' => "🆕 <b>تغییرات این آپدیت</b>\n\n"
        . "<blockquote>☠️ <b>مسدود کردن + حذف همهٔ سرویس‌ها</b>\n"
        . "📍 مدیریت کاربر ← 🔐 دسترسی و احراز\n"
        . "کاربر را در جا مسدود می‌کند و همهٔ سرویس‌هایش را هم از پنل و هم از ربات پاک می‌کند. پیش از اجرا یک بار تأیید می‌گیرد؛ سرویسی که پنل حذفش نکرد با دلیلش گزارش می‌شود.</blockquote>\n\n"
        . "<blockquote>🗑 <b>اصلاح حذف سرویس</b>\n"
        . "📍 مدیریت کاربر ← 🛍 سرویس‌ها و سفارش‌ها\n"
        . "قبلاً اگر پنل جواب نمی‌داد، سرویس در ربات «حذف‌شده» ثبت می‌شد ولی در پنل می‌ماند. حالا فقط وقتی پنل واقعاً حذفش کرد ثبت می‌شود، وگرنه دلیل خطا را می‌بینی و می‌توانی دوباره امتحان کنی.</blockquote>\n\n"
        . "<blockquote>📢 <b>ارسال همگانی هر نوع پیام</b>\n"
        . "📍 پنل ادمین ← ارسال همگانی\n"
        . "نظرسنجی، چک‌لیست، عکس و ویدیو با کپشن، استیکر و متن‌های با قالب‌بندی خاص همان‌طور که هستند برای همه فرستاده می‌شوند. قبل از شروع پیش‌نمایش می‌بینی و آخرش گزارش ارسال/بلاک/ناموفق می‌آید.</blockquote>\n\n"
        . "<blockquote>💵 <b>درگاه FrenzyEx</b>\n"
        . "📍 تنظیمات پرداخت ← 🌐 FrenzyEx\n"
        . "پرداختی که بعد از منقضی‌شدن فاکتور تأیید می‌شود هم دیگر شارژ می‌شود. هر پرداخت تأییدشده‌ای که شارژ نشود در گروه گزارش (تاپیک خطا) خبرش می‌آید.</blockquote>",
    'en' => "🆕 <b>What's new in this update</b>\n\n"
        . "<blockquote>☠️ <b>Block + delete all services</b>\n"
        . "📍 Manage user → 🔐 Access\n"
        . "Blocks the user on the spot and removes every service of theirs from both the panel and the bot. Asks once before it runs; a service the panel would not delete is reported with the reason.</blockquote>\n\n"
        . "<blockquote>🗑 <b>Service deletion fixed</b>\n"
        . "📍 Manage user → 🛍 Services and orders\n"
        . "When the panel did not answer, the service used to be marked deleted in the bot but stay in the panel. Now it is marked only once the panel really removed it; otherwise you see the reason and can try again.</blockquote>\n\n"
        . "<blockquote>📢 <b>Broadcast any kind of message</b>\n"
        . "📍 Admin panel → Broadcast\n"
        . "Polls, checklists, photos and videos with captions, stickers and specially formatted texts go out exactly as they are. You see a preview first, and a sent / blocked / failed report at the end.</blockquote>\n\n"
        . "<blockquote>💵 <b>FrenzyEx gateway</b>\n"
        . "📍 Payment settings → 🌐 FrenzyEx\n"
        . "A payment confirmed after its invoice expired is now credited too. Any confirmed payment that is not credited is reported in the report group (errors topic).</blockquote>",
];
