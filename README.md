# Ciro

**A Telegram VPN subscription shop and management bot for PHP 8.2+.**

[English](README.md) · [فارسی](README.fa.md)

## What is Ciro?

Ciro is a Telegram bot for selling, delivering, renewing and managing VPN subscriptions. It includes a web admin panel, multi-language customer messages, payment gateways and adapters for several VPN panels.

The customer interface supports Persian and English. New users start in English and can change their language from the bot menu. Admin-only controls are shown only to administrators; the admin panel entry uses Telegram's reply keyboard.

## Features

- Sell, renew, disable and delete subscriptions
- Marzban, Marzneshin, X-UI, Hiddify, S-UI, WGDashboard, Mikrotik, IBSng and other panel adapters
- Persian and English texts, buttons, prices and currency settings
- Web admin panel
- Card-to-card, Zarinpal, AqayePardakht, IranPay, Plisio, NOWPayments, TRX, TON, USDT and Telegram Stars support
- Help/tutorial screens with Telegram callback alerts
- Agent and reseller bot support
- Database backup and self-update commands

## Requirements

- Ubuntu 20.04+ or Debian 11+
- Root access
- A domain pointed to the server
- A Telegram bot token from [@BotFather](https://t.me/BotFather)
- Your numeric Telegram user ID

## One-command installation

Run this command as `root` on a clean server:

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/Alfred-1313/Ciro/master/install.sh)
```

The installer installs the web stack, PHP, MySQL/MariaDB, Composer dependencies, SSL, the database schema and the Telegram webhook. It also creates the `Ciro` management command.

If `curl` is unavailable:

```bash
wget -qO- https://raw.githubusercontent.com/Alfred-1313/Ciro/master/install.sh | bash
```

## Management commands

```bash
Ciro              # Open the management menu
Ciro update       # Update the application and keep config/database data
Ciro version      # Show installed and available versions
Ciro backup       # Create/send a database backup
Ciro renew        # Renew the SSL certificate
Ciro --help       # Show all available options
```

## Update an existing installation

Use the same installer command and select **Update** when prompted:

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/Alfred-1313/Ciro/master/install.sh)
```

The updater preserves `config.php`, uploaded assets and the database. Always make a backup before a major update:

```bash
Ciro backup
```

## Configuration

For a manual deployment, copy `config.example.php` to `config.php` and set the database, Telegram token, admin ID and domain values. The installer creates this file automatically.

## Troubleshooting

- Installer log: `/tmp/ciro_install.log`
- Confirm the domain resolves to the server and ports `80` and `443` are open.
- Confirm the bot token and numeric admin ID are correct.
- Run `Ciro renew` if the certificate has expired.
- Check the PHP and web-server error logs before changing database data.

## License

Ciro is released under the GNU AGPL-3.0 license and is based on the Mirza project.
