---
name: telegram-bot-customization
description: Build a complete admin-editable customization system (the "🎨 شخصی‌سازی" menu) into a Telegram bot, so the bot owner can change every customer-facing message, button, sticker, reaction, emoji (including premium emoji), colour, order and on/off switch per language, from inside the bot and from a web admin panel. Use this whenever you build or extend any Telegram bot (in any language or framework - PHP, Python, Node, Go), add a new customer-facing message, button, screen or feature to a bot, design an admin panel or settings menu for a bot, add a self-update / «🔄 آپدیت ربات» / rollback / what's-new feature to a bot, make a bot multilingual, or the user mentions 🎨, شخصی‌سازی, customization, editable texts, button colours, stickers, premium emoji, "per language", or a web panel for a bot - even if they do not say "customization".
---

# Telegram bot customization (🎨)

The idea in one sentence: **nothing a customer sees is hard-wired.** Every message and every
button has a stable key, a default text per language, and an optional per-language override that
the admin edits from a 🎨 menu inside the bot (and the same data from a web panel). A new feature
is not finished until its texts and buttons are in that system.

This came out of building a large Telegram shop bot (Ciro) where the owner kept asking for "this
should be editable too" one screen at a time. Building it in from the start is far cheaper than
retrofitting 160 messages later, so treat it as part of every feature, not as an extra.

## When you start on a bot

1. Find (or create) the text layer: one file per language with nested keys
   (`users.Balance.shortLine`, `users.sell.service_sell`). No customer text inline in code.
2. Find (or create) the override store and the resolver (see `references/data-model.md`).
3. Find (or create) the registry: the single list that says which keys and button groups appear
   in 🎨, under which group and section, with which admin label.
4. Read `references/checklist.md` before calling any feature done.

If the bot already has some of this under other names, use what exists and extend it. Do not
build a second parallel system.

## The five things that must be editable

| What | Per language | Stored as | Notes |
|---|---|---|---|
| Message text | yes | override text, or none = default | keep placeholders like `{price}` working; HTML or entities |
| Message extras | yes | sticker file_id, reaction emoji, media | sticker is sent before the message and removed when that screen is replaced |
| Buttons of a message | yes | per (message key, button index): name, emoji, premium emoji id, emoji side, plain mode, colour, hidden | back / close buttons can be restyled but never hidden |
| Layout | yes | order of buttons and how many per row | the "📐 چیدمان" editor |
| Feature switches | yes, unless truly global | `feature[lang][key]` | new features default to **off** |

Admin-only texts (the admin menus themselves) do not need per-language overrides; they live in
the admin's language only. Customer texts always exist in every supported language.

## Resolution rules (get these right first)

- `text(key, lang)` = override for (lang, key) if non-empty, else the default text of that
  language, else the default of the primary language. Never return an empty string: Telegram
  refuses empty messages, and a blank bubble looks broken.
- Everything is resolved in the **receiver's** language. A message to a referrer, an approval
  result sent to a customer by an admin, a cron notice: use the recipient's language and their
  overrides, not the language of whoever triggered the request.
- A change on one language tab never changes another language. If something must be shared
  (one payment-processor account for all languages), mark it visibly (🌐) on every tab.
- Buttons are built through one function (`render(def, override, callback)`), so colour, emoji,
  premium emoji and hiding work the same on every screen. Do not hand-build buttons for customer
  screens.
- Overrides are data, never code. Validate on save: placeholders that the default has must still
  be there (warn, do not silently break), HTML must be balanced, length within Telegram limits.

## The 🎨 menu the admin sees

Main screen: language tabs on top (`✅🇮🇷 فارسی | 🇬🇧 English`), then groups. White header buttons
are section titles (tapping one only shows a short alert explaining that section). A green
button means "this item is customized"; a legend explains the markers:
`✏️ text changed  ❤️ has reaction  🖼 has sticker  🔘 buttons changed`. At the bottom:
`🔁 reset all to default` (with confirmation) and `❌ close`.

Item screen (one message):

```
📝 <b>👋 متن خوش‌آمدید</b>
➖➖➖➖➖➖➖➖➖➖
✏️ متن: پیش‌فرض
🖼 استیکر: ندارد ❌
❤️ ری‌اکشن: ندارد ❌
➖➖➖➖➖➖➖➖➖➖
👁 <b>متن فعلی:</b>
<blockquote>سلام خوش آمدید</blockquote>
👇 بخشی که می‌خوای تنظیم کنی رو انتخاب کن:
[📋 کپشن پیش‌فرض] [✏️ ویرایش کپشن]
[🖼 استیکر] [❤️ ری‌اکشن]
[🔘 دکمه‌های این پیام]
[🔁 ریست همه‌ی تنظیمات این پیام]
[🔙 بازگشت به منوی قبل] [🔙 برگشت به لیست]
[❌ بستن]
```

Editing a text: the admin sends the new text; premium emoji and formatting are kept (store the
entities or convert them to `<tg-emoji emoji-id="…">` HTML). Show the placeholders this text
supports right in the prompt. After saving, redraw the item screen in place (edit the same
message).

Button screen: list the message's buttons; for each: rename, emoji (normal or premium), emoji
side (start/end), plain (no emoji), colour (`success` green / `danger` red / `primary` blue /
default), hide/show (not for back/close), reset. Show state, not action: a hidden button shows
"🙈 پنهان", not "show".

Layout screen (📐): the buttons in their current rows; tap one to select, then move it
up/down/left/right or change how many sit in a row. Items added later (a new feature button)
must appear in the layout automatically, at a sensible default position, and must be movable.

More detail, including every screen and callback naming, in `references/admin-screens.md`.

## Feature switches

- A features screen per scope: "global" (whole bot) and "per language" (this tab only). Each row
  is `[✅ روشن | name]` or `[❌ خاموش | name]`, green/red, and one tap flips it and redraws the
  same message. No confirmation step for a plain on/off.
- Every new feature ships **off**. Turning it on must never change what existing customers see
  until the admin decides.
- A feature with its own settings gets a `⚙️ تنظیمات …` button next to its switch, and its hub
  repeats the language tabs.

## Web panel parity

Whatever the bot's 🎨 can edit, the web panel can edit too, from the same registry and the same
store, so the two never disagree. Pages: texts (group tabs, search, "only customized" filter,
sticker id field), buttons, looks/layout (drag to reorder, live preview), features (cards with
switches). Save without reloading the page (fetch + toast), show the new state immediately. See
`references/web-panel.md`.

## Telegram UI conventions that go with it

- Edit the current message in place when moving between screens of one flow; send a new message
  only when the old one cannot become the new one (media type change, a sticker in between).
- Remove stale screens (an invoice that was paid, a menu that was replaced) so nothing old can be
  tapped. If an old button is tapped anyway, answer with an alert and a way forward, not a crash
  or a silent no-op.
- Navigation buttons at the bottom: `🔙 back` (red or default), `❌ close`. Confirm buttons green.
- `<blockquote>` for notes and explanations, `<b>` for the values that matter, `<code>` for
  anything the user may copy (IDs, links, amounts).
- Errors and refusals: `answerCallbackQuery` with `show_alert` for short ones; a short message
  with the fix for longer ones. Never "unknown error".
- Every list that can grow: paging, and search when long.

## Self-update

If the bot needs an update feature (an admin button that updates the bot, keeps config and
data, snapshots first, rolls back on failure, and announces what is new), build it exactly as
described in `references/self-update.md`: the `version` file, the "🔄 آپدیت ربات" screen,
request files picked up by root's cron watcher, the ten update steps shared with the server's
management command, rollback to a snapshot from the bot, and `whatsnew.php` announced to every
admin in their own language.

## Before you say it is done

Run through `references/checklist.md`. In short: texts in every language, registry entry, 🎨
item opens and edits, buttons through the shared renderer, switch defaults off, web panel shows
it, receiver-language checked, test on a rig with a fake Telegram API, and a "what's new" note
for the admin that says where to find it (menu path, step by step).
