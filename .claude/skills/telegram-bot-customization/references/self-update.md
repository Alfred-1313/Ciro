# Self-update: "🔄 آپدیت ربات" (build it exactly like this)

When a bot project needs an update feature, build this design, from the Ciro bot. The owner
updates from a button in the bot, sees a progress bar in the same message, never loses
config or data, can go back to the previous version from the bot, and every admin is told
what changed. The same steps also run from the server's management command, so there is
**one** update path, not two.

## Pieces

| Piece | Where | Job |
|---|---|---|
| `version` file | repo root, one line (`1.0.4`) | the installed version; compared with the same file on the main branch |
| Update screen | bot: ⚙️ تنظیمات عمومی ← 🔄 آپدیت ربات | shows versions and state, starts an update or a rollback |
| Request files | the bot's own directory | how the bot (web user) asks root to act |
| Root watcher | cron, every minute: `* * * * * flock -n /tmp/<cmd>_selfupdate.lock /usr/local/bin/<cmd> selfupdate-watch` | picks up requests and runs the real update |
| Management command | `/usr/local/bin/<cmd>` (the installer itself, e.g. `Ciro`) | menu with install / update / backup / restore; `<cmd> update` is the same code the watcher runs |
| `table.php` (migrations) | repo root | only creates missing tables and adds missing columns; safe to run any time |
| `whatsnew.php` | repo root | history of release notes, announced to admins once after each update |

## Why the split

The bot runs as the web-server user and cannot replace its own files or restart anything. So
it never updates itself directly: it writes a small request file in its directory, and root's
watcher (installed by the installer as one crontab line, added once and matched by its command
text) does the work within a minute. Everything that crosses from the bot to root is validated
on both sides (fixed file names, snapshot names matched against a strict pattern).

## The update screen

```
<blockquote><b>🔄 آپدیت ربات</b>
🤖 @botname</blockquote>

نسخه فعلی: <b>1.0.4</b>
آخرین به‌روزرسانی: <b>1405/08/16 14:20</b>      ← Jalali for Persian, Gregorian otherwise

✅ <b>آخرین نسخه را داری</b>                     ← or: 🆕 <b>نسخهٔ جدید آماده است: 1.0.5</b>
تاریخ آخرین تغییرات: <b>2026-11-07</b>           ← date of the last whatsnew entry

<blockquote>✅ تنظیمات، دیتابیس، پنل‌ها و فایل config دست‌نخورده می‌مانند. پیش از شروع یک بکاپ
کامل گرفته می‌شود و اگر آپدیت به مشکل بخورد، ربات خودکار به همین نسخه برمی‌گردد.</blockquote>

[🚀 شروع آپدیت]                 ← primary; on the latest version: [🔁 نصب دوباره‌ی همین نسخه] (success)
[♻️ بازگشت به نسخه قبلی]        ← danger; only when snapshots exist
```

- The latest version is read from the raw `version` file on the main branch, cached for 10
  minutes; a failed read is retried after one minute and the old value is kept meanwhile.
- The start button stays on the latest version too ("reinstall"), because a push without a
  version bump must still be installable.
- While a job is pending or running, no buttons: one line "⏳ یک کار در جریان است. همین پیام
  خودش به‌روز می‌شود." A failed run shows its reason.
- "♻️ بازگشت به نسخه قبلی" opens a list of this bot's snapshots with their dates; picking one
  asks once to confirm, then writes the rollback request. A callback carries only the snapshot's
  date stamp (Telegram limits callback data to 64 bytes); the bot maps it back to the name.

## Request and status files (in the bot directory)

- `update_request` / `rollback_request` (with the snapshot name): the ask. The watcher claims
  one by renaming it to `*.running`; a claim older than 30 minutes is treated as dead and
  removed, so the screen can never stay "busy" for ever after a crash or reboot.
- A context file written by the bot **in the admin's language**: chat id, message id, and the
  three texts the watcher will put into that message (progress, done, failed) with
  placeholders `{bar}`, `{percent}`, `{backup}`. The watcher is a shell script and knows no
  languages; it only fills placeholders.
- `update_status.json`: `{state: running|done|failed|restored|stale, detail, at}` for the
  screen. `update_backups.json`: this bot's snapshots, newest first.
- A busy check covers all of them: a second request is never written over the first, and an
  update and a rollback are never asked for at the same time.

## The steps (identical from the bot and from `<cmd> update`)

0. Find the bot and its current version. An install of the original upstream project (same
   config variables, no `version` file) is upgraded in place. Confirm once (the bot already
   confirmed before writing the request).
1. **Snapshot first.** `tar.gz` of the bot directory without logs, `.git`, backups and the
   request/status files, named `pre-update_<botusername>_<YYYYmmdd>_<HHMMSS>.tar.gz`. Keep the
   newest two per bot; never touch another bot's. If the snapshot fails, stop: nothing changed.
2. **Download** the target archive; check the essential files are in it; delete any packaged
   `config.php` so real credentials are never overwritten.
3. **Dependencies** (composer, npm, pip...) are installed in the extracted copy first, so a
   failure there changes nothing on the live bot.
4. **Overlay** the new files onto the bot directory (copy over, never wipe), so whatever the
   operator added (config, uploads, vendor, custom files) stays. Fix ownership and permissions.
5. **Syntax-check** the core files (`php -l` or the language's equivalent). Any failure:
   restore the snapshot automatically and report "update failed, previous version restored".
6. **Migrate the database additively** (`table.php`: create missing tables, add missing
   columns, fill defaults; never drop or rename). If it cannot run from the shell, try its URL;
   if that fails too, say so and tell the admin to open it once.
7. Repair only what is missing in the web server config; never overwrite a customized vhost.
8. Check that the bot answers.
9. Refresh the management command itself and make sure the watcher's cron line exists.
10. **Tell every admin, in their own language**: "✅ ربات آپدیت شد", the bot's @username, the
    domain, the new version, the time (Jalali | Gregorian), where the previous version's
    snapshot is, and under it every what's-new entry they have not seen yet.

During the run the watcher edits the admin's same message with a progress bar
(`▰▰▰▱▱▱  45٪`) and, at the end, the done or failed text. A rollback follows the same steps
with its own texts ("♻️ در حال بازگشت به نسخه قبلی…", "✅ ربات به نسخه قبلی برگشت.").

## What's new (`whatsnew.php`)

```php
return [
  ['n' => 76, 'date' => '2026-11-07',
   'fa' => "<blockquote>🛠 <b>…title…</b>\n…what changed, an example, on/off by default,\n1️⃣ menu ← 2️⃣ … ← 3️⃣ …\n🌐 پنل وب: …</blockquote>",
   'en' => "<blockquote>…</blockquote>"],
];
```

- A history, oldest first; every change set **adds** one entry with the next `n`. An admin who
  skipped updates is told every entry they missed, in order. Drop the oldest beyond ~15.
- `storage/whatsnew_seen` keeps the last `n` announced. `table.php` announces what is pending,
  once, at the end of each migration; a fresh install starts as "seen" instead of sending the
  whole history.
- Each entry is written for a beginner: what it is, a concrete example, whether it is on or off
  by default, the exact path in the bot step by step, and the web panel path.
- The version number is bumped only when the owner releases; pushing code alone does not bump it.

## Several bots on one server

A registry of installed bots (directory, username, domain). The watcher walks every bot
directory; each bot only ever updates or rolls back itself, and snapshots are named after
their bot.
