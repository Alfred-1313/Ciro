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
    [
        'n' => 11,
        'date' => '2026-10-24',
        'fa' => "<blockquote>🛒 <b>دکمه‌های مدیریت سرویس زیر پیام خرید</b>\n"
            . "📍 مشتری: بعد از خرید، پیامی که کانفیگ را می‌فرستد\n"
            . "زیر آن پیام حالا همان دکمه‌های «🛍 سرویس‌های من» هست: 🔗 لینک اشتراک، 🔰 دریافت کانفیگ، 💊 تمدید، ⚙️ تغییر لینک، ➕ حجم/⏳ زمان اضافه و ... — هر کدام فقط اگر برای آن پنل و تنظیمات فروشگاه کاربرد داشته باشد. دکمهٔ 📚 آموزش (اگر روشن باشد) زیر آن‌ها می‌آید.</blockquote>\n\n"
            . "<blockquote>🎨 <b>تنظیم جدا از «سرویس‌های من»</b>\n"
            . "📍 🎨 شخصی‌سازی ← 🛍️ پیام بعد از خرید ← «🔘 ویرایش دکمه‌های مدیریت سرویس»\n"
            . "📍 وب‌پنل ← پیام‌ها و دکمه‌های سرویس ← «دکمه‌های پیام خرید»\n"
            . "نام، رنگ، ایموجی، ترتیب و پنهان‌کردنِ هر دکمه برای پیام خرید جدا تنظیم می‌شود و با تنظیمات صفحهٔ «وضعیت سرویس» یکی نیست. کپشن همین پیام هم مثل قبل از «✏️ ویرایش کپشن» همان صفحه تنظیم می‌شود.</blockquote>",
        'en' => "<blockquote>🛒 <b>Service buttons under the purchase message</b>\n"
            . "📍 Customer: the message that sends the config after buying\n"
            . "That message now carries the same buttons as «🛍 My services»: 🔗 Subscription link, 🔰 Get config, 💊 Renew, ⚙️ Change link, ➕ Extra volume / ⏳ extra time and so on — each one only where it applies to that panel and the shop's settings. The 📚 tutorial button (when it is on) comes below them.</blockquote>\n\n"
            . "<blockquote>🎨 <b>Set apart from «My services»</b>\n"
            . "📍 🎨 Customize → 🛍️ Message after purchase → «🔘 Edit service buttons»\n"
            . "📍 Web panel → Messages and service buttons → «Purchase message buttons»\n"
            . "The name, colour, emoji, order and visibility of each button are set for the purchase message on their own and are not the ones on the service status screen. This message's caption is still edited from «✏️ Edit caption» on the same page.</blockquote>",
    ],
    [
        'n' => 12,
        'date' => '2026-10-24',
        'fa' => "<blockquote>🌐 <b>ورود به سابسکریپشن و 📲 افزودن به برنامه زیر پیام خرید</b>\n"
            . "📍 مشتری: پیامی که بعد از خرید کانفیگ را می‌فرستد\n"
            . "دو دکمهٔ بالای «🛍 سرویس‌های من» هم حالا بالای دکمه‌های مدیریت سرویس همین پیام آمده‌اند. با زدن 📲 پیام خرید (که کانفیگ را دارد) سر جایش می‌ماند و لیست برنامه‌ها پایینش فرستاده می‌شود.</blockquote>\n\n"
            . "<blockquote>🎨 <b>تنظیم جدا</b>\n"
            . "📍 🎨 شخصی‌سازی ← 🛍️ پیام بعد از خرید ← «🌐 دکمه‌های سابسکریپشن و 📲 افزودن به برنامه»\n"
            . "📍 وب‌پنل ← ظاهر دکمه‌ها ← «(پیام خرید)» (و لینکش در تب «دکمه‌های پیام خرید»)\n"
            . "نام، رنگ، ایموجی، چیدمان و پنهان‌کردنِ این دو دکمه برای پیام خرید جدا از دکمه‌های همین دو در «سرویس‌های من» تنظیم می‌شود. لیست برنامه‌هایی که 📲 باز می‌کند همان لیست «خرید» است.</blockquote>",
        'en' => "<blockquote>🌐 <b>Open subscription page and 📲 Add to app under the purchase message</b>\n"
            . "📍 Customer: the message that sends the config after buying\n"
            . "The two buttons on top of «🛍 My services» now sit above the service buttons of this message too. Tapping 📲 leaves the purchase message (which has the config) where it is and sends the app list below it.</blockquote>\n\n"
            . "<blockquote>🎨 <b>Set apart</b>\n"
            . "📍 🎨 Customize → 🛍️ Message after purchase → «🌐 Subscription and 📲 Add to app buttons»\n"
            . "📍 Web panel → Button looks → «(purchase message)» (linked from the «Purchase message buttons» tab)\n"
            . "The name, colour, emoji, layout and visibility of these two buttons are set for the purchase message on their own, apart from the same two in «My services». The app list 📲 opens is the «purchase» one.</blockquote>",
    ],
    [
        'n' => 13,
        'date' => '2026-10-24',
        'fa' => "<blockquote>🔘 <b>دکمه‌های یکسان در هر ۵ جا</b>\n"
            . "📍 پیام خرید، پیام اکانت تست، پیام هدیهٔ رفرال، صفحهٔ ساعتی و 🛍 سرویس‌های من\n"
            . "همه‌جا 🌐 ورود به سابسکریپشن، 📲 افزودن به برنامه، ♻️ بروزرسانی، 🔰 دریافت کانفیگ، 💡 روشن/خاموش و 📊 گزارش مصرف هست. پیام اکانت تست و هدیهٔ رفرال هم حالا دکمه‌های مدیریت سرویس را دارند. 🔗 لینک اشتراک از پیام خرید برداشته شد (خود پیام لینک را دارد).</blockquote>\n\n"
            . "<blockquote>⚙️ <b>پیش‌فرض‌های تازه</b>\n"
            . "📍 🏬 تنظیمات فروشگاه ← وضعیت قابلیت‌ها ← تغییر وضعیت اکانت\n"
            . "💡 روشن/خاموش حالا پیش‌فرض روشن است و یک‌بار برای همه روشن شد (اگر بعداً خاموشش کنی، خاموش می‌ماند). 📝 یادداشت، ❌ بازگشت وجه و ⚠️ گزارش اختلال پیش‌فرض پنهان‌اند و 📚 آموزش هم مثل قبل پیش‌فرض پنهان است؛ هرکدام را از 🎨 می‌شود نشان داد.</blockquote>\n\n"
            . "<blockquote>⏱ <b>💡 برای اشتراک ساعتی</b>\n"
            . "مشتری می‌تواند اشتراک ساعتی را خاموش کند؛ تا وقتی خاموش است هزینه‌ای کم نمی‌شود و حذف هم نمی‌شود. روشن‌کردن دوباره به همان موجودی‌ای نیاز دارد که برای شروع لازم است. 📊 گزارش مصرف هم به صفحه‌اش اضافه شد.</blockquote>\n\n"
            . "<blockquote>🎨 <b>تنظیم جدا و اصلاح نمایش</b>\n"
            . "📍 🎨 شخصی‌سازی ← پیام بعد از دریافت اکانت تست / پیام کامل کانفیگ هدیه ← «🔘 ویرایش دکمه‌های مدیریت سرویس» و «🌐 دکمه‌های سابسکریپشن»\n"
            . "📍 وب‌پنل ← پیام‌ها و دکمه‌های سرویس ← «دکمه‌های پیام دریافت» (خرید / اکانت تست / هدیهٔ دعوت)\n"
            . "در «✏️ نام و نمایش دکمه‌ها» علامت کنار هر دکمه حالا وضعیت واقعی را نشان می‌دهد: 👁 دیده می‌شود، 🚫 پنهان است. 🔒 یعنی قابلیتش در تنظیمات فروشگاه خاموش است.</blockquote>",
        'en' => "<blockquote>🔘 <b>The same buttons in all 5 places</b>\n"
            . "📍 Purchase message, test account message, referral gift message, hourly screen and 🛍 My services\n"
            . "Every one now has 🌐 Open subscription page, 📲 Add to app, ♻️ Update, 🔰 Get config, 💡 On/off and 📊 Usage report. The test account and referral gift messages now carry the service buttons too. 🔗 Subscription link is gone from the purchase message (the message itself has the link).</blockquote>\n\n"
            . "<blockquote>⚙️ <b>New defaults</b>\n"
            . "📍 🏬 Shop settings → Feature status → Change account status\n"
            . "💡 On/off is now on by default and was turned on once for everyone (turn it off later and it stays off). 📝 Note, ❌ Refund and ⚠️ Disruption report ship hidden, and 📚 Tutorial is still hidden by default; 🎨 can show any of them.</blockquote>\n\n"
            . "<blockquote>⏱ <b>💡 for hourly subscriptions</b>\n"
            . "Customers can turn an hourly subscription off; nothing is charged and it is not deleted while it is off. Turning it on again needs the same balance a start needs. 📊 Usage report was added to its screen too.</blockquote>\n\n"
            . "<blockquote>🎨 <b>Set apart, and shown right</b>\n"
            . "📍 🎨 Customize → Message after a test account / Full referral gift message → «🔘 Edit service buttons» and «🌐 Subscription buttons»\n"
            . "📍 Web panel → Messages and service buttons → «Delivery message buttons» (purchase / test account / referral gift)\n"
            . "In «✏️ Names and visibility» the mark next to each button now shows its real state: 👁 shown, 🚫 hidden. 🔒 means its feature is off in the shop settings.</blockquote>",
    ],
    [
        'n' => 14,
        'date' => '2026-10-24',
        'fa' => "<blockquote>💡 <b>رنگ دکمهٔ روشن/خاموش</b>\n"
            . "📍 هر ۵ جا: پیام خرید، اکانت تست، هدیهٔ رفرال، صفحهٔ ساعتی و 🛍 سرویس‌های من\n"
            . "وقتی اشتراک روشن است دکمه آبی است و وقتی خاموش شد قرمز می‌شود. اگر از 🎨 برای این دکمه رنگ دیگری انتخاب کرده باشی، همان رنگ می‌ماند.</blockquote>\n\n"
            . "<blockquote>🛍 <b>چیدمان تازهٔ «پیام‌ها و ظاهر سرویس‌های من»</b>\n"
            . "📍 🎨 شخصی‌سازی ← پیام‌ها و ظاهر سرویس‌های من\n"
            . "📍 وب‌پنل ← متن‌های ربات ← 🛍 پیام‌ها و ظاهر سرویس‌های من\n"
            . "موارد به ترتیبی که مشتری می‌بیند مرتب شدند: 1️⃣ لیست سرویس‌ها، 2️⃣ صفحهٔ هر سرویس (کپشن، دکمه‌ها و 🌐 / 📲)، 3️⃣ صفحه‌هایی که دکمه‌ها باز می‌کنند و ⚠️ خطاها و پاپ‌آپ‌ها.</blockquote>\n\n"
            . "<blockquote>🔙 <b>بازگشت به منوی قبل</b>\n"
            . "صفحه‌هایی که از دو جا باز می‌شوند (مثل کپشن‌های درگاه، فاکتور خرید و دکمه‌های دریافت کانفیگ) حالا با 🔙 به همان صفحه‌ای برمی‌گردند که از آن آمده بودی، حتی بعد از ذخیرهٔ تغییر.</blockquote>\n\n"
            . "<blockquote>🛠 <b>رفع اشکال‌های 🎨</b>\n"
            . "• متنی که با فرمت تلگرام (بولد، ایتالیک، لینک، نقل‌قول و...) فرستاده شود، با همان فرمت ذخیره می‌شود.\n"
            . "• ذخیرهٔ استیکر و ری‌اکشن روی بعضی پیام‌ها بی‌صدا شکست می‌خورد؛ درست شد.\n"
            . "• تغییر نام و ایموجی 🌐 / 📲 در پیام خرید، اکانت تست و هدیهٔ رفرال ذخیره نمی‌شد؛ درست شد.\n"
            . "• چهار دکمهٔ رنگ و نام دکمه‌های فاکتور کارت‌به‌کارت کار نمی‌کردند؛ درست شدند.\n"
            . "• وقتی تنظیمی بلند می‌شد ربات خطا می‌داد؛ حالا جای ذخیره خودش بزرگ‌تر می‌شود.</blockquote>",
        'en' => "<blockquote>💡 <b>On/off button colour</b>\n"
            . "📍 All 5 places: purchase message, test account, referral gift, hourly screen and 🛍 My services\n"
            . "The button is blue while the subscription is on and turns red once it is off. A colour you picked for it in 🎨 still wins.</blockquote>\n\n"
            . "<blockquote>🛍 <b>«My services messages and look», reordered</b>\n"
            . "📍 🎨 Customize → My services messages and look\n"
            . "📍 Web panel → Bot Texts → 🛍 My services messages and look\n"
            . "Items now follow what the customer sees: 1️⃣ the list of services, 2️⃣ one service's screen (caption, buttons and 🌐 / 📲), 3️⃣ the screens its buttons open, and ⚠️ errors and popups.</blockquote>\n\n"
            . "<blockquote>🔙 <b>Back to the previous menu</b>\n"
            . "Screens that open from two places (gateway captions, the purchase invoice, the get-config buttons and more) now go 🔙 back to the screen you came from, even after saving a change.</blockquote>\n\n"
            . "<blockquote>🛠 <b>🎨 fixes</b>\n"
            . "• Text sent with Telegram formatting (bold, italic, links, quotes...) is saved with that formatting.\n"
            . "• Saving a sticker or reaction on some messages failed silently; fixed.\n"
            . "• Renaming 🌐 / 📲 or changing their emoji on the purchase, test account and referral gift messages was not saved; fixed.\n"
            . "• Four colour and name buttons of the card-to-card invoice did nothing; fixed.\n"
            . "• The bot errored once a setting grew long; its storage now grows by itself.</blockquote>",
    ],
    [
        'n' => 15,
        'date' => '2026-10-24',
        'fa' => "<blockquote>💡 <b>روشن/خاموش روی سرویسی که هنوز وصل نشده</b>\n"
            . "📍 هر ۵ جا: پیام خرید، اکانت تست، هدیهٔ رفرال، صفحهٔ ساعتی و 🛍 سرویس‌های من\n"
            . "اگر مشتری روی «💡 روشن/خاموش» سرویسی بزند که هنوز بهش وصل نشده (OnHold)، دیگر پیام جدید زیر صفحه فرستاده نمی‌شود؛ یک پاپ‌آپ (Alert) روی همان صفحه نشان داده می‌شود که می‌گوید اول باید یک‌بار وصل شود و بعد می‌تواند استفاده کند. در اشتراک ساعتی هم همین حالت پاپ‌آپ دارد.</blockquote>\n\n"
            . "<blockquote>🎨 <b>متن پاپ‌آپ قابل تنظیم است</b>\n"
            . "📍 🎨 شخصی‌سازی ← پیام‌ها و ظاهر سرویس‌های من ← ⚠️ خطاها و پاپ‌آپ‌ها ← «⏳ Alert هنوز به سرویس وصل نشدی»\n"
            . "📍 وب‌پنل ← متن‌های ربات ← 🛍 پیام‌ها و ظاهر سرویس‌های من\n"
            . "پاپ‌آپ فقط متن ساده می‌گیرد و تا ۲۰۰ حرف نشان داده می‌شود. متن پیش‌فرض هم تازه و روان‌تر شد.</blockquote>",
        'en' => "<blockquote>💡 <b>On/off on a service that is not connected yet</b>\n"
            . "📍 All 5 places: purchase message, test account, referral gift, hourly screen and 🛍 My services\n"
            . "When a customer taps «💡 On/off» on a service they have not connected to yet (OnHold), no new message is sent under the screen any more; a popup (Alert) shows on that same screen telling them to connect once first and then use it. The hourly subscription has the same popup for this state.</blockquote>\n\n"
            . "<blockquote>🎨 <b>The popup text can be edited</b>\n"
            . "📍 🎨 Customize → My services messages and look → ⚠️ Errors and popups → «⏳ Alert: not connected yet»\n"
            . "📍 Web panel → Bot Texts → 🛍 My services messages and look\n"
            . "A popup takes plain text only and shows up to 200 characters. The default text is also new and friendlier.</blockquote>",
    ],
    [
        'n' => 16,
        'date' => '2026-10-24',
        'fa' => "<blockquote>🧹 <b>فقط فارسی و انگلیسی</b>\n"
            . "زبان‌های روسی، چینی و ترکمنی کامل از ربات و وب‌پنل برداشته شد: فایل‌های زبانشان، دکمه‌ها و گزینه‌های انتخاب زبان، پیش‌شماره‌ی تلفن، ارز پیش‌فرض و ساعت هر زبان.\n"
            . "کاربرانی که روی یکی از این زبان‌ها بودند خودکار روی فارسی می‌روند (زبانی که هر متن بدون ترجمه هم به آن برمی‌گردد). تنظیمات ذخیره‌شده‌ی این زبان‌ها دست نخورده می‌ماند و هیچ جا نشان داده نمی‌شود.</blockquote>",
        'en' => "<blockquote>🧹 <b>Persian and English only</b>\n"
            . "Russian, Chinese and Turkmen are fully removed from the bot and the web panel: their language files, the buttons and choices for picking a language, the phone dial codes, the default currency and the time zone of each.\n"
            . "Users who were on one of them move to Persian automatically (the language every untranslated text already falls back to). Settings saved for those languages are left in place and are not shown anywhere.</blockquote>",
    ],
    [
        'n' => 17,
        'date' => '2026-10-24',
        'fa' => "<blockquote>💡 <b>رنگ دکمهٔ روشن/خاموش: سبز، قرمز، آبی</b>\n"
            . "📍 هر ۵ جا: پیام خرید، اکانت تست، هدیهٔ رفرال، صفحهٔ ساعتی و 🛍 سرویس‌های من\n"
            . "🟢 سبز وقتی اشتراک روشن است، 🔴 قرمز وقتی خاموش شد (یا موجودی اشتراک ساعتی تمام شد) و 🔵 آبی تا وقتی هنوز به کانفیگ وصل نشده (OnHold). پیام‌های تحویل (خرید، تست، هدیه) هم حالا وضعیت واقعی سرویس را از پنل می‌پرسند، پس سرویس OnHold همان لحظهٔ اول آبی است.\n"
            . "رنگی که از 🎨 برای این دکمه انتخاب کرده باشی همان می‌ماند.</blockquote>",
        'en' => "<blockquote>💡 <b>On/off button colour: green, red, blue</b>\n"
            . "📍 All 5 places: purchase message, test account, referral gift, hourly screen and 🛍 My services\n"
            . "🟢 Green while the subscription is on, 🔴 red once it is off (or an hourly subscription ran out of balance) and 🔵 blue while it has not connected to the config yet (OnHold). The delivery messages (purchase, test, gift) now ask the panel for the real state too, so an OnHold service is blue from the first moment.\n"
            . "A colour you picked for this button in 🎨 still wins.</blockquote>",
    ],
    [
        'n' => 18,
        'date' => '2026-10-24',
        'fa' => "<blockquote>💡 <b>روشن/خاموش بدون تاییدیه</b>\n"
            . "📍 پیام خرید، اکانت تست، هدیهٔ رفرال و 🛍 سرویس‌های من (اشتراک ساعتی از قبل بدون تاییدیه بود)\n"
            . "با یک بار زدن روی «💡 روشن/خاموش» سرویس همان لحظه روشن یا خاموش می‌شود؛ سؤال «مطمئنی؟» و صفحهٔ جداگانهٔ تایید حذف شد. یک پیام کوتاه (روشن شد / خاموش شد) نشان داده می‌شود و خود دکمه همان‌جا عوض می‌شود: 🟢 سبز وقتی روشن است، 🔴 قرمز وقتی خاموش. در 🛍 سرویس‌های من کل صفحه (از جمله خط وضعیت) هم تازه می‌شود و در پیام خرید، تست و هدیه فقط خود دکمه عوض می‌شود.\n"
            . "دکمه‌های تایید در پیام‌های قدیمی هنوز کار می‌کنند. تایید در پنل مدیر (مدیریت سفارش کاربر) تغییری نکرده است.</blockquote>",
        'en' => "<blockquote>💡 <b>On/off without a confirmation</b>\n"
            . "📍 Purchase message, test account, referral gift and 🛍 My services (the hourly subscription already had none)\n"
            . "One tap on «💡 On/off» switches the service right away; the «are you sure?» question and its separate confirm screen are gone. A short note (turned on / turned off) is shown and the button itself changes on the spot: 🟢 green while it is on, 🔴 red while it is off. In 🛍 My services the whole screen (status line included) is refreshed too; in the purchase, test and gift messages only the button changes.\n"
            . "Confirm buttons on older messages still work. The confirmation in the admin panel (managing a user's order) is unchanged.</blockquote>",
    ],
    [
        'n' => 19,
        'date' => '2026-10-24',
        'fa' => "<blockquote>⚡️ <b>روشن/خاموش کردن اشتراک سریع‌تر شد</b>\n"
            . "📍 پیام خرید، اکانت تست، هدیهٔ رفرال و 🛍 سرویس‌های من\n"
            . "قبلاً هر بار زدن 💡 پنل را چند بار از اول صدا می‌زد (چهار بار وضعیت سرویس را می‌پرسید). حالا یک بار وضعیت را می‌پرسد، همان را برای تغییر و برای رسم دوبارهٔ صفحه به کار می‌برد و فقط یک درخواست تغییر می‌فرستد. پیام «روشن/خاموش شد» و رنگ تازهٔ دکمه اول می‌آید و بعد صفحهٔ کامل در همان پیام تازه می‌شود.\n"
            . "اگر پنل تغییر را قبول نکند، دکمه عوض نمی‌شود و پیام «امکان تغییر وجود ندارد» می‌آید (قبلاً گاهی خطای پنل دیده نمی‌شد).</blockquote>",
        'en' => "<blockquote>⚡️ <b>Turning a subscription on/off is faster</b>\n"
            . "📍 Purchase message, test account, referral gift and 🛍 My services\n"
            . "Every tap on 💡 used to call the panel over and over (it asked for the service's status four times). Now it asks once, uses that answer for the switch and for redrawing the screen, and sends a single change request. The «turned on/off» note and the button's new colour come first, then the full screen is refreshed in the same message.\n"
            . "If the panel refuses the change, the button stays as it was and a «could not be changed» note is shown (a panel error used to go unnoticed at times).</blockquote>",
    ],
    [
        'n' => 20,
        'date' => '2026-10-24',
        'fa' => "<blockquote>💡 <b>رنگ دکمهٔ روشن/خاموش: آبی و قرمز</b>\n"
            . "📍 هر ۵ جا: پیام خرید، اکانت تست، هدیهٔ رفرال، صفحهٔ ساعتی و 🛍 سرویس‌های من\n"
            . "🔵 آبی وقتی اشتراک روشن است (یا هنوز به کانفیگ وصل نشده، OnHold) و 🔴 قرمز وقتی خاموش شد. رنگ سبز برداشته شد. رنگی که از 🎨 برای این دکمه انتخاب کرده باشی همان می‌ماند.</blockquote>",
        'en' => "<blockquote>💡 <b>On/off button colour: blue and red</b>\n"
            . "📍 All 5 places: purchase message, test account, referral gift, hourly screen and 🛍 My services\n"
            . "🔵 Blue while the subscription is on (or has not connected to the config yet, OnHold) and 🔴 red once it is turned off. Green is gone. A colour you picked for this button in 🎨 still wins.</blockquote>",
    ],
    [
        'n' => 21,
        'date' => '2026-10-24',
        'fa' => "<blockquote>📲 <b>«ورود به سابسکریپشن» و «افزودن به برنامه»: یک تنظیم برای همه‌ی بخش‌ها</b>\n"
            . "📍 🎨 شخصی‌سازی ← پیام‌ها و ظاهر سرویس‌های من ← «🌐 دکمه‌های سابسکریپشن و 📲 افزودن به برنامه» و «📲 لیست برنامه‌ها»\n"
            . "📍 وب‌پنل ← ظاهر دکمه‌ها ← همان دو کارت\n"
            . "پیام خرید، پیام اکانت تست، پیام کانفیگ هدیهٔ رفرال، صفحهٔ ساعتی و 🛍 سرویس‌های من حالا همه یک جفت دکمه و یک لیست برنامه دارند. هرچه در تب فارسی عوض کنی (نام، ایموجی، رنگ، چیدمان، مخفی کردن، کپشن لیست و دکمه‌های برنامه‌ها) در هر ۵ جا با همان تغییر دیده می‌شود؛ تب انگلیسی تنظیم جدای خودش را دارد.\n"
            . "هر پیام (بعد از خرید، اکانت تست، هدیه، گروه رفرال و ساعتی) فقط یک ردیف دارد که به همین تنظیم مشترک می‌رود و 🔙 به همان صفحه‌ای برمی‌گردد که از آن آمده‌ای.\n"
            . "آنچه قبلاً برای هر بخش جدا ذخیره کرده بودی، به تنظیم مشترک همان زبان منتقل شد.</blockquote>",
        'en' => "<blockquote>📲 <b>«Open subscription page» and «Add to app»: one setting for every place</b>\n"
            . "📍 🎨 Customize → My services messages and look → «🌐 Subscription and 📲 Add to app buttons» and «📲 The app list»\n"
            . "📍 Web panel → Button looks → those two cards\n"
            . "The purchase message, the test account message, the referral gift config message, the hourly screen and 🛍 My services now all have one pair of buttons and one app list. Whatever you change on the Persian tab (name, emoji, colour, layout, hiding, the list's caption and the app buttons) shows in all 5 places; the English tab has a setting of its own.\n"
            . "Each message (after purchase, test account, gift, the referral group and the hourly one) has just one row that opens this shared setting, and 🔙 goes back to the screen you came from.\n"
            . "What you had saved separately for each place was moved to that language's shared setting.</blockquote>",
    ],
    [
        'n' => 22,
        'date' => '2026-10-24',
        'fa' => "<blockquote>🌐 <b>ظاهر دکمه‌های هشدار و اتمام: جدا برای هر زبان</b>\n"
            . "📍 🎨 شخصی‌سازی ← 🔋 پیام‌های هشدار و اتمام سرویس؛ وب‌پنل ← ظاهر سرویس ← هشدارها\n"
            . "رنگ، ایموجی، جای دکمه، حالت ساده و مخفی بودنِ دکمهٔ هر هشدار (و دکمهٔ «اکانت تست تمام شد») قبلاً بین فارسی و انگلیسی مشترک بود؛ حالا هر زبان ظاهر خودش را دارد. چیزی که تا حالا نمایش داده می‌شد برای هر دو زبان کپی شد، پس تا وقتی عوضش نکنی تغییری نمی‌بینی.\n"
            . "فقط آستانه‌ها (درصد، گیگ، روز) بین زبان‌ها مشترک مانده‌اند، چون تعیین می‌کنند پیام کی فرستاده شود.</blockquote>",
        'en' => "<blockquote>🌐 <b>Warning and end-of-service button looks: separate per language</b>\n"
            . "📍 🎨 Customize → 🔋 Warning and end-of-service messages; Web panel → Service look → Warnings\n"
            . "The colour, emoji, side, simple mode and hiding of each warning's button (and the «test account ended» button) used to be shared by Persian and English; each language has its own look now. What was showing was copied to both languages, so nothing changes until you change it.\n"
            . "Only the thresholds (percent, GB, days) stay shared between languages, since they decide when a message is sent.</blockquote>",
    ],
    [
        'n' => 23,
        'date' => '2026-10-24',
        'fa' => "<blockquote>📲 <b>صفحه‌ی باز کردن برنامه: طرح شیشه‌ای تازه</b>\n"
            . "📍 🎨 شخصی‌سازی ← 📲 افزودن سرویس به برنامه؛ وب‌پنل ← پیام‌های ربات ← 📲 افزودن سرویس به برنامه\n"
            . "صفحه‌ای که با زدن یک برنامه در «📲 افزودن به برنامه» باز می‌شود تازه شد: طرح شیشه‌ای با فونت فارسی «استعداد» (از خود سرور لود می‌شود، بدون فیلترشکن و CDN)، آیکون و رنگ مخصوص هر برنامه و انیمیشنِ رفتن اشتراک به داخل برنامه؛ اگر برنامه روی گوشی نبود صفحه همان‌جا نشان می‌دهد و راه‌حل می‌دهد.\n"
            . "زیر دکمه‌ی دانلود لینک فروشگاه‌ها هست: برای اندروید Google Play، و برای برنامه‌ای که در گوگل‌پلی نیست (مثل v2rayNG و Karing) آخرین نسخه‌ی GitHub؛ برای آیفون App Store؛ و سایت رسمی برنامه. متن‌های جدید صفحه (سرویس، برنامه روی گوشیت پیدا نشد، دوباره امتحان کن و …) هم برای فارسی و انگلیسی جدا قابل ویرایش‌اند.</blockquote>",
        'en' => "<blockquote>📲 <b>App-opening page: a new glass design</b>\n"
            . "📍 🎨 Customize → 📲 Add service to app; Web panel → Bot messages → Add service to app\n"
            . "The page that opens when a customer taps an app in «📲 Add to app» is redesigned: a glass look with the Persian font Estedad (served from your own server - no VPN or CDN needed), each app's own icon and colour, and an animation of the subscription travelling into the app; if the app is not on the phone the page says so right there and shows what to do.\n"
            . "Under the download button are the stores: Google Play on Android, the latest GitHub release for an app that is not on Google Play (v2rayNG, Karing), the App Store on iPhone, and the app's own site. The new page texts (service, app not found on your phone, try again, …) are editable separately for Persian and English.</blockquote>",
    ],
    [
        'n' => 24,
        'date' => '2026-10-24',
        'fa' => "<blockquote>📲 <b>«افزودن سرویس به برنامه» یک بخش جدا در 🎨 شخصی‌سازی دارد</b>\n"
            . "📍 🎨 شخصی‌سازی ← 📲 افزودن سرویس به برنامه؛ وب‌پنل ← پیام‌های ربات ← 📲 افزودن سرویس به برنامه\n"
            . "چون «🌐 سابسکریپشن / 📲 افزودن به برنامه» برای هر ۵ مسیر (خرید، تست، هدیه‌ی دعوت، اشتراک ساعتی و سرویس‌های من) یکی است، تنظیماتش از «🛍 سرویس‌های من» بیرون آمد و جای خودش را دارد: دکمه‌های زیر سرویس، پیام لیست برنامه‌ها و دکمه‌هایش، صفحه‌ی وبی که برنامه را باز می‌کند (باز شدن، برنامه پیدا نشد، دانلود و فروشگاه‌ها) و پاپ‌آپ‌ها، همه در یک جا و برای فارسی و انگلیسی جدا.\n"
            . "توی هر ۵ بخش (پیام بعد از خرید، اکانت تست، هدیه‌ی دعوت، اشتراک ساعتی و سرویس‌های من) هم زیر عنوان «🌐 سابسکریپشن و 📲 افزودن به برنامه» یک دکمه‌ی «📲 افزودن سرویس به برنامه — زبان» هست که همین بخش را باز می‌کند و 🔙 برت می‌گرداند همان‌جا که بودی. هیچ تنظیمی عوض نشد.</blockquote>",
        'en' => "<blockquote>📲 <b>«Add service to app» has its own section in 🎨 Customize</b>\n"
            . "📍 🎨 Customize → 📲 Add service to app; Web panel → Bot messages → Add service to app\n"
            . "«🌐 Subscription / 📲 Add to app» is one and the same for all 5 paths (purchase, test, invite gift, hourly subscription and My services), so its settings left «🛍 My services» and have a place of their own: the buttons under a service, the app-list message and its buttons, the web page that opens the app (opening, app not found, download and stores) and the popups - all in one place, separate for Persian and English.\n"
            . "Each of the 5 paths (purchase message, test account, invite gift, hourly subscription and My services) shows a «📲 Add service to app — language» button under the «🌐 Subscription and 📲 Add to app» heading that opens this section, and 🔙 takes you back to where you were. No setting changed.</blockquote>",
    ],
];
