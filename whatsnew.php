<?php
// 🆕 What each update added - told to every admin once, right after the update
// (bot_whatsnew_announce(), called at the end of table.php).
//
// This is a HISTORY, oldest first. With every update, ADD an entry at the end:
// the next 'n', today's 'date', and what that update added and where to find
// it (Telegram HTML, one <blockquote> per item, no header - the bot adds it).
// Leave the earlier entries: an admin who skipped some updates is told about
// every one he missed, in order. Drop the oldest once there are more than ~15.
return [
    [
        'n' => 1,
        'date' => '2026-10-21',
        'fa' => "<blockquote>☠️ <b>مسدود کردن + حذف همهٔ سرویس‌ها</b>\n"
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
        'en' => "<blockquote>☠️ <b>Block + delete all services</b>\n"
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
    ],
    [
        'n' => 2,
        'date' => '2026-10-22',
        'fa' => "<blockquote>✅ <b>«آخرین نسخه را داری»</b>\n"
            . "📍 /update یا پنل ادمین ← 🔄 آپدیت ربات\n"
            . "صفحهٔ آپدیت حالا نسخهٔ جدیدتر را از گیت‌هاب می‌پرسد: اگر ربات روی جدیدترین نسخه باشد همین را می‌گوید، و اگر نسخهٔ جدیدتری آماده باشد شمارهٔ آن را نشان می‌دهد.</blockquote>",
        'en' => "<blockquote>✅ <b>\"You're on the latest version\"</b>\n"
            . "📍 /update or Admin panel → 🔄 Update bot\n"
            . "The update screen now asks GitHub for the newest version: if the bot is on it, it says so, and if a newer one is ready it shows its number.</blockquote>",
    ],
    [
        'n' => 3,
        'date' => '2026-10-22',
        'fa' => "<blockquote>🔙 <b>اصلاح دکمهٔ بازگشت در «تنظیم نمایش و کپشن کانفیگ»</b>\n"
            . "📍 🎨 شخصی‌سازی پیام‌ها ← نحوهٔ نمایش کانفیگ (خرید سرویس / اکانت تست / هدیهٔ دعوت)\n"
            . "بعد از عوض‌کردن ترتیب ستون‌ها یا ویرایش یک دکمه، 🔙 گاهی به صفحهٔ «کپشن دریافت کانفیگ» می‌رفت. حالا همیشه به همان صفحه‌ای برمی‌گردد که از آن آمده‌ای.</blockquote>",
        'en' => "<blockquote>🔙 <b>Back button fixed in \"Config display and caption\"</b>\n"
            . "📍 🎨 Customize messages → Config display (purchase / test account / invite gift)\n"
            . "After changing the column order or editing a button, 🔙 sometimes went to the \"config caption\" page. It now always returns to the page you came from.</blockquote>",
    ],
    [
        'n' => 4,
        'date' => '2026-10-22',
        'fa' => "<blockquote>🏬 <b>دستور سریع /shop</b>\n"
            . "📍 هر جا بفرستی ← «🏬 تنظیمات فروشگاه» باز می‌شود. در راهنمای پنل مدیریت هم هست.</blockquote>\n\n"
            . "<blockquote>✅ <b>پیام پایان آپدیت</b>\n"
            . "📍 بعد از هر آپدیت\n"
            . "اعلان آپدیت (دامنه، نسخه، زمان، مسیر بکاپ) و همین «تغییرات این آپدیت» حالا در یک پیام می‌آید.</blockquote>",
        'en' => "<blockquote>🏬 <b>Quick command /shop</b>\n"
            . "📍 Send it anywhere → \"🏬 Shop settings\" opens. It is in the admin panel help too.</blockquote>\n\n"
            . "<blockquote>✅ <b>Update-finished message</b>\n"
            . "📍 After every update\n"
            . "The update notice (domain, version, time, backup path) and this \"What's new\" now arrive in one message.</blockquote>",
    ],
    [
        'n' => 5,
        'date' => '2026-10-22',
        'fa' => "<blockquote>🎁 <b>هدیهٔ شارژ در تأیید دستی رسید</b>\n"
            . "📍 💵 رسیدهای تأییدنشده ← ✅\n"
            . "فاکتور کارت‌به‌کارت (و ارز دیجیتال آفلاین) هدیه‌ای را که موقع ساخته‌شدنش وعده داده یادداشت می‌کند. اگر رسید دیر تأیید شود و تخفیف در این فاصله تمام شده یا سهمیه‌اش پر شده باشد، ✅ باز هم همان مبلغ + همان هدیه را اضافه می‌کند. «افزودن مبلغ دستی» مثل قبل دقیقاً همان مبلغ را بدون هدیه می‌دهد.</blockquote>",
        'en' => "<blockquote>🎁 <b>Top-up gift on manual receipt approval</b>\n"
            . "📍 💵 Unconfirmed receipts → ✅\n"
            . "A card-to-card (and offline crypto) invoice now remembers the gift it promised when it was created. If the receipt is approved late and the discount has ended or its quota is used up meanwhile, ✅ still adds the amount + that gift. \"Add a manual amount\" works as before: exactly the typed amount, no gift.</blockquote>",
    ],
    [
        'n' => 6,
        'date' => '2026-10-23',
        'fa' => "<blockquote>📦 <b>هدیهٔ خودکار فقط روی بسته‌ها</b>\n"
            . "📍 🏬 تنظیمات فروشگاه ← 🏦 بسته‌های شارژ ← 🎁 تخفیف شارژ ← (درگاه یا دسته) ← 🎯 تخفیف خودکار ← «📦 اعمال روی کدوم مبلغ‌ها»\n"
            . "هدیهٔ خودکار را می‌شود روی «همهٔ مبلغ‌ها»، «فقط بسته‌های آماده» یا «فقط یک بسته» گذاشت (هم برای هر درگاه، هم برای تخفیف گروهی و همگانی). کدهای تخفیف همان‌طور که بودند می‌مانند.\n"
            . "وقتی روشنش کنی کپشن‌های کاربر خودشان اعلام می‌کنند: کنار تخفیف در لیست بسته‌ها و روش پرداخت، و یادآوری برای مبلغ دلخواه و فاکتور.</blockquote>\n\n"
            . "<blockquote>🌐 <b>وب‌پنل و 🎨 شخصی‌سازی</b>\n"
            . "📍 وب‌پنل ← مدیریت ← تخفیف شارژ ← «اعمال روی»\n"
            . "📍 🎨 شخصی‌سازی ← 🎁 پیام‌های تخفیف ← «📦 جمله‌ی تخفیف — وقتی فقط روی بسته‌ها ست شده»\n"
            . "همین فیلتر در وب‌پنل هم هست و چهار متنی که کاربر می‌بیند از ربات و وب‌پنل قابل ویرایش است.</blockquote>",
        'en' => "<blockquote>📦 <b>Automatic gift on packages only</b>\n"
            . "📍 🏬 Shop settings → 🏦 Top-up packages → 🎁 Top-up discount → (gateway or category) → 🎯 Automatic discount → «📦 Applies to»\n"
            . "The automatic gift can be set to «every amount», «ready-made packages only» or «one package only» (per gateway, and for the category and all-gateways discounts). Discount codes stay as they were.\n"
            . "When it is on, the customer's captions say so: next to the discount in the packages list and on the payment-method screen, and a note on the custom amount screen and the invoice.</blockquote>\n\n"
            . "<blockquote>🌐 <b>Web panel and 🎨 customization</b>\n"
            . "📍 Web panel → Management → Top-up discount → «Applies to»\n"
            . "📍 🎨 Customize → 🎁 Discount messages → «📦 Discount sentence — when limited to packages»\n"
            . "The same filter is in the web panel, and the four texts the customer sees can be edited from the bot and the web panel.</blockquote>",
    ],
    [
        'n' => 7,
        'date' => '2026-10-24',
        'fa' => "<blockquote>🛠 <b>اصلاح پاپ‌آپ‌ها (Alert)</b>\n"
            . "📍 مثلاً «✅ تایید تمدید» وقتی موجودی کافی نیست\n"
            . "اگر متن پاپ‌آپ را در 🎨 شخصی‌سازی با ایموجی پریمیوم یا بولد نوشته بودی، تلگرام تگ‌هایش را (مثل <code>&lt;tg-emoji …&gt;</code>) عیناً نشان می‌داد. حالا فقط خود متن می‌آید و ایموجی پریمیوم به شکل ایموجی معمولی‌اش دیده می‌شود. روی صفحهٔ ویرایش (ربات و وب‌پنل) هم یادآوری گذاشتم که پاپ‌آپ فقط متن ساده می‌پذیرد و تا ۲۰۰ حرف.</blockquote>",
        'en' => "<blockquote>🛠 <b>Popups (alerts) fixed</b>\n"
            . "📍 e.g. «✅ Confirm renewal» when the balance is not enough\n"
            . "If a popup's text was written in 🎨 Customize with a premium emoji or bold, Telegram showed its tags (like <code>&lt;tg-emoji …&gt;</code>) as typed. Now only the text comes through, and a premium emoji shows as its ordinary emoji. The edit screens (bot and web panel) also remind that a popup takes plain text only, up to 200 characters.</blockquote>",
    ],
    [
        'n' => 8,
        'date' => '2026-10-24',
        'fa' => "<blockquote>🎁 <b>هدیهٔ شارژ در پرداخت مستقیم</b>\n"
            . "📍 مشتری: تمدید / حجم اضافه / زمان اضافه / خرید ← پرداخت از درگاه یا کارت‌به‌کارت\n"
            . "وقتی مشتری موجودی کم داشت و مبلغ را همان‌جا از بخش واریز می‌پرداخت، سرویس خودکار تمدید می‌شد (یا حجم/زمان اضافه می‌شد یا سرویس ساخته می‌شد) ولی هدیهٔ تخفیف شارژ را نمی‌گرفت، در حالی که فاکتور وعده‌اش را داده بود. حالا هدیه بعد از انجام موفق کار به کیف پولش اضافه می‌شود و پیام «🎁 تخفیف اعمال شد!» را می‌بیند. اگر انجام کار با خطا برگردد هدیه‌ای داده نمی‌شود.</blockquote>\n\n"
            . "<blockquote>🌐 <b>گزارش ادمین و 🎨 شخصی‌سازی</b>\n"
            . "📍 🏬 تنظیمات فروشگاه ← 🏦 بسته‌های شارژ ← 🎁 تخفیف شارژ\n"
            . "📍 🎨 شخصی‌سازی ← 💲 پیام و دکمه‌ی تایید شارژ کیف پول ← 🎁 ویرایش متن بلوک تخفیف\n"
            . "قوانین هدیه همان قوانین تخفیف خودکار است (درصد، سقف، محدودیت هر کاربر، فیلتر بسته‌ها) و گزارش مصرف برای ادمین هم می‌آید و در آن نوشته می‌شود که پرداخت مستقیم برای سرویس بوده و فقط هدیه به کیف پول اضافه شده. متن پیام همان متن تخفیف شارژ است که از ربات و وب‌پنل قابل ویرایش است.</blockquote>",
        'en' => "<blockquote>🎁 <b>Top-up gift on direct payment</b>\n"
            . "📍 Customer: renew / extra volume / extra time / purchase → pay from the gateway or card-to-card\n"
            . "When a customer was short of balance and paid the amount right there, the service was renewed (or extra volume/time added, or the service created) automatically, but the top-up gift was never given even though the invoice had promised it. Now the gift is added to their wallet once the job has gone through, and they see «🎁 Discount applied!». If the job fails, no gift is given.</blockquote>\n\n"
            . "<blockquote>🌐 <b>Admin report and 🎨 customization</b>\n"
            . "📍 🏬 Shop settings → 🏦 Top-up packages → 🎁 Top-up discount\n"
            . "📍 🎨 Customize → 💲 Wallet top-up confirmation message and button → 🎁 Edit discount block text\n"
            . "The gift follows the automatic discount rules (percent, cap, per-user limit, package filter), and the admin's per-use report says it was a direct payment for a service and only the gift went to the wallet. The message is the usual top-up discount text, editable from the bot and the web panel.</blockquote>",
    ],
    [
        'n' => 9,
        'date' => '2026-10-24',
        'fa' => "<blockquote>💎 <b>برگشت پول در حجم/زمان اضافهٔ ناموفق</b>\n"
            . "📍 مشتری: سرویس ← 🔋 حجم اضافه / ⏳ زمان اضافه ← پرداخت مستقیم از درگاه یا کارت‌به‌کارت\n"
            . "اگر پنل درخواست حجم یا زمان اضافه را قبول نمی‌کرد، کیف پول مشتری خالی شده بود و پولی برنمی‌گشت. حالا مثل تمدید، هم مبلغ پرداختی و هم موجودی قبلی به کیف پولش برمی‌گردد و پیامی می‌گیرد که مبلغ و موجودی فعلی را می‌گوید. در این حالت هدیهٔ شارژ هم داده نمی‌شود.</blockquote>\n\n"
            . "<blockquote>🌐 <b>وب‌پنل و 🎨 شخصی‌سازی</b>\n"
            . "گزارش خطا برای ادمین مثل قبل در کانال گزارش خطا می‌آید. متن پیام برگشت پول جزو متن‌های داخلی ربات است و تنظیم جدیدی ندارد.</blockquote>",
        'en' => "<blockquote>💎 <b>Refund when extra volume/time fails</b>\n"
            . "📍 Customer: service → 🔋 Extra volume / ⏳ Extra time → pay directly from the gateway or card-to-card\n"
            . "If the panel refused the extra volume or time, the customer's wallet had been emptied and nothing came back. Now, as with renewals, both the payment and the previous balance are returned to the wallet and the customer gets a message with the amount and current balance. No top-up gift is given in that case.</blockquote>\n\n"
            . "<blockquote>🌐 <b>Web panel and 🎨 customization</b>\n"
            . "The error report still goes to the error-report channel for admins. The refund message is one of the bot's built-in texts and has no setting of its own.</blockquote>",
    ],
    [
        'n' => 10,
        'date' => '2026-10-24',
        'fa' => "<blockquote>🛠 <b>گزارش خرید زمان اضافه با درگاه</b>\n"
            . "📍 کانال گزارش ← «کاربر زمان اضافه خریده است»\n"
            . "وقتی مشتری زمان اضافه را با درگاه می‌خرید، در گزارش ادمین تعداد روزِ خریداری‌شده خالی می‌آمد. حالا مثل کارت‌به‌کارت پر می‌شود.</blockquote>",
        'en' => "<blockquote>🛠 <b>Extra-time purchase report via gateway</b>\n"
            . "📍 Report channel → «A user bought extra time»\n"
            . "When a customer bought extra time through a gateway, the admin report left the number of days empty. It is now filled in, as with card-to-card.</blockquote>",
    ],
];
