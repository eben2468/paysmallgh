# Deploying PaySmallSmall to CloudPanel (Nginx + PHP-FPM)

Target: CloudPanel VPS behind Cloudflare. Document root is `public/`.

## 1. Create the site in CloudPanel

- Add a **PHP site** (PHP 8.2+), e.g. domain `paysmallsmall.com`, site user `paysmallsmall`.
- In CloudPanel → Site → Settings, set the **document root** to
  `/home/paysmallsmall/htdocs/paysmallsmall.com/public`.

## 2. Pull the code (as the site user, never root)

```bash
ssh paysmallsmall@your-server
cd ~/htdocs/paysmallsmall.com
git clone <repo-url> .
```

Subsequent deploys:

```bash
ssh paysmallsmall@your-server
cd ~/htdocs/paysmallsmall.com && git pull
```

## 3. Database

Get the MySQL root credentials from clpctl, then create the DB and a dedicated user:

```bash
clpctl db:show:master-credentials
```

Create the database via CloudPanel UI (Databases → Add Database), or:

```bash
mysql -u root -p <<'SQL'
CREATE DATABASE paysmallsmall CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pss'@'localhost' IDENTIFIED BY 'CHANGE-THIS-PASSWORD';
GRANT ALL PRIVILEGES ON paysmallsmall.* TO 'pss'@'localhost';
FLUSH PRIVILEGES;
SQL
```

Load the schema (the schema file also contains the CREATE DATABASE — it's idempotent):

```bash
mysql -u pss -p paysmallsmall < database/schema.sql
```

Optional demo data:

```bash
php database/seed.php
```

## 4. Environment config

Write the `.env` with tee (no interactive editors):

```bash
tee .env > /dev/null <<'ENV'
APP_NAME="PaySmallSmall"
APP_URL=https://paysmallsmall.com
APP_DEBUG=false

DB_HOST=localhost
DB_PORT=3306
DB_NAME=paysmallsmall
DB_USER=pss
DB_PASS=CHANGE-THIS-PASSWORD

PAYMENTS_MODE=mock

MOOLRE_BASE_URL=https://api.moolre.com
MOOLRE_API_USER=your-moolre-username
MOOLRE_API_KEY=your-private-key
MOOLRE_API_PUBKEY=your-public-key
MOOLRE_VAS_KEY=your-vas-key
MOOLRE_ACCOUNT_NUMBER=your-moolre-account
MOOLRE_PATH_COLLECT=/open/transact/payment
MOOLRE_PATH_DISBURSE=/open/transact/transfer
MOOLRE_PATH_STATUS=/open/transact/status
MOOLRE_PATH_SMS=/open/vas/sms
MOOLRE_WEBHOOK_SECRET=generate-a-long-random-string

PLATFORM_FEE_PCT=5
CANCEL_FEE_PCT=5
GRACE_DAYS=3

ADMIN_PHONE=233XXXXXXXXX
ADMIN_PASSWORD=pick-a-strong-one

USSD_CODE=*920*77#
ENV
chmod 600 .env
```

> Before setting `PAYMENTS_MODE=sandbox` or `live`: verify every `MOOLRE_PATH_*`
> value and the webhook field names against docs.moolre.com. The mock mode demo
> works without any Moolre credentials.

## 5. Nginx vhost

CloudPanel's default PHP vhost already routes through the document root. Make sure
the location block falls back to the front controller. In CloudPanel → Site →
Vhost Editor, the relevant part should read:

```nginx
location / {
    try_files $uri $uri/ /index.php?$args;
}
```

Uploads directory must be writable by the site user (it is by default when the
site user owns the tree — another reason deploys run as the site user):

```bash
mkdir -p public/uploads && chmod 755 public/uploads
```

## 6. Cloudflare / HTTPS

- Cloudflare SSL mode: **Full (strict)** with the CloudPanel-issued Let's Encrypt cert.
- The app reads `X-Forwarded-Proto` for HTTPS detection (secure cookies) — no extra config needed, but keep Cloudflare's default header pass-through on.

## 7. Cron: reminders + payment reconciliation

Two cron jobs, both as the site user (CloudPanel → Site → Cron Jobs):

```
# Grace-period reminders, once a day
0 8 * * * cd /home/paysmallsmall/htdocs/paysmallsmall.com && php scripts/reminders.php >> ~/reminders.log 2>&1

# Settle pending payments — safety net for any webhook Moolre couldn't deliver
*/2 * * * * cd /home/paysmallsmall/htdocs/paysmallsmall.com && php scripts/reconcile.php >> ~/reconcile.log 2>&1
```

The reconcile job polls Moolre for every still-pending transaction and applies
the result exactly as a webhook would (credit installment, pay out, SMS). It is
idempotent, so a webhook and the cron settling the same payment cannot
double-credit. Admins can also trigger it by hand at **Admin → All plans →
Reconcile pending payments**.

## 8. Going live with Moolre (do this carefully)

The app ships in `PAYMENTS_MODE=mock`. Before switching to `sandbox`/`live`,
**confirm every wire-format value against docs.moolre.com** — these are the only
things the code cannot verify for you, and they all live in `.env` (nothing is
hardcoded):

| `.env` key | Confirm on docs.moolre.com |
|---|---|
| `MOOLRE_PATH_COLLECT` / `_DISBURSE` / `_STATUS` / `_SMS` | exact endpoint paths |
| `MOOLRE_CHANNEL_MOMO` / `_BANK` | numeric channel/network codes |
| `MOOLRE_CURRENCY` | currency code (GHS) |
| request field names | `payer`/`receiver`/`amount`/`externalref`/`accountnumber`/`callbackurl` — adjust in `app/Services/MoolreService.php` if the docs differ |
| webhook payload fields | confirm `externalref` + status field names in `app/Controllers/WebhookController.php` and `MoolreService::readState()` |

Then set the credentials (`MOOLRE_API_USER`, `MOOLRE_API_KEY`,
`MOOLRE_API_PUBKEY`, `MOOLRE_VAS_KEY`, `MOOLRE_ACCOUNT_NUMBER`),
`MOOLRE_CALLBACK_URL=https://paysmallsmall.com/webhook/moolre`, a strong
`MOOLRE_WEBHOOK_SECRET`, and flip `PAYMENTS_MODE`. Test one real GHS 1 collection
end-to-end (approve prompt → SMS receipt → ledger row `success`) before opening up.

## 9. Moolre dashboard settings

Point these URLs at the app once you have live credentials:

- Payment webhook/callback: `https://paysmallsmall.com/webhook/moolre`
- USSD callback: `https://paysmallsmall.com/webhook/ussd`
- Set the same webhook secret you put in `.env` (`MOOLRE_WEBHOOK_SECRET`).

## Quick smoke test after deploy

```bash
curl -s -o /dev/null -w "%{http_code}\n" https://paysmallsmall.com/        # 200
curl -s -o /dev/null -w "%{http_code}\n" https://paysmallsmall.com/shop    # 200
curl -s -o /dev/null -w "%{http_code}\n" https://paysmallsmall.com/.env    # 404 — must NOT be readable
```
