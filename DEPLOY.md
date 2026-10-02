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

Upgrading an existing database? Run the migrations in order. Each one is safe
to re-run, so running all of them is fine even if some were applied before:

```bash
mysql -u pss -p paysmallsmall < database/migrations/2026-10-01-paystack.sql
mysql -u pss -p paysmallsmall < database/migrations/2026-10-02-payment-options.sql
mysql -u pss -p paysmallsmall < database/migrations/2026-10-03-product-features.sql
mysql -u pss -p paysmallsmall < database/migrations/2026-10-04-accounts.sql
mysql -u pss -p paysmallsmall < database/migrations/2026-10-05-reviews.sql
```

**Run new migrations before the `git pull` that needs them** (they only add
columns and tables, so the old code keeps working on the upgraded database).
The new pages read the new columns and will error until the migrations have run.

`2026-10-04-accounts.sql` adds phone verification, SMS codes (PIN/password
reset, number change), saved addresses, saved cards and MoMo wallet, login
lockout after wrong PINs, and "declined" for merchants. Customers who signed up
before it count as verified, so nobody is locked out.

`2026-10-03-product-features.sql` adds SKU, old price, stock, specifications,
delivery/returns notes, product options (size/colour/storage), saved items, and
the quantity/option a plan was bought with.

Set `ADMIN_PHONE` in `.env` to the admin's real number: it receives an SMS
whenever a new shop is waiting for approval.

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

PAYSTACK_SECRET_KEY=sk_test_your-test-secret-key
PAYSTACK_BASE_URL=https://api.paystack.co
PAYSTACK_CURRENCY=GHS
PAYSTACK_CHANNELS=mobile_money,card
PAYSTACK_EMAIL_DOMAIN=paysmallsmall.com
PAYSTACK_PENDING_EXPIRY_HOURS=24
RECONCILE_AFTER_MINUTES=2

# SMS still goes through Moolre (Paystack has no SMS product)
SMS_MODE=mock
MOOLRE_BASE_URL=https://api.moolre.com
MOOLRE_VAS_KEY=your-moolre-vas-key
MOOLRE_SMS_SENDER=PaySmall
MOOLRE_PATH_SMS=/open/sms/send
MOOLRE_PATH_SMS_STATUS=/open/sms/status

PLATFORM_FEE_PCT=5
CANCEL_FEE_PCT=5
GRACE_DAYS=3
SESSION_LIFETIME_DAYS=30

ADMIN_PHONE=233XXXXXXXXX
ADMIN_PASSWORD=pick-a-strong-one

USSD_CODE=*920*77#
ENV
chmod 600 .env
```

> `PAYMENTS_MODE=mock` works without any Paystack keys. `sandbox` needs a test
> key (`sk_test_…`), `live` a live key (`sk_live_…`) — see section 8.

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
mkdir -p storage/sessions && chmod 700 storage/sessions   # logins live here (SESSION_LIFETIME_DAYS)
```

## 6. Cloudflare / HTTPS

- Cloudflare SSL mode: **Full (strict)** with the CloudPanel-issued Let's Encrypt cert.
- The app reads `X-Forwarded-Proto` for HTTPS detection (secure cookies) — no extra config needed, but keep Cloudflare's default header pass-through on.

## 7. Cron: reminders + payment reconciliation

Two cron jobs, both as the site user (CloudPanel → Site → Cron Jobs):

```
# Grace-period reminders, once a day
0 8 * * * cd /home/paysmallsmall/htdocs/paysmallsmall.com && php scripts/reminders.php >> ~/reminders.log 2>&1

# Settle pending payments — safety net for any webhook Paystack couldn't deliver
*/2 * * * * cd /home/paysmallsmall/htdocs/paysmallsmall.com && php scripts/reconcile.php >> ~/reconcile.log 2>&1
```

The reconcile job asks Paystack about every still-pending transaction and applies
the result exactly as a webhook would (credit installment, pay out, SMS). It is
idempotent, so a webhook and the cron settling the same payment cannot
double-credit. Admins can also trigger it by hand at **Admin → All plans →
Reconcile pending payments**.

## 8. Going live with Paystack (do this carefully)

The app ships in `PAYMENTS_MODE=mock`. Every Paystack call lives in
`app/Services/PaystackService.php`; endpoints and field names were checked
against Paystack's API reference (paystack.com/docs/api).

1. **Test first.** Put your test secret key in `.env` and set
   `PAYMENTS_MODE=sandbox`:

   ```bash
   sed -i 's/^PAYMENTS_MODE=.*/PAYMENTS_MODE=sandbox/; s/^PAYSTACK_SECRET_KEY=.*/PAYSTACK_SECRET_KEY=sk_test_xxxxxxxx/' .env
   ```

   Start a plan, pay on Paystack's test checkout, and check Admin → All plans:
   the installment is credited and the ledger row is `success`.
2. **Set the webhook URL** (section 9) — without it, payments still settle via
   the callback redirect and the reconcile cron, just slower.
3. **Disable transfer OTP** (Paystack dashboard → Settings → Preferences →
   Transfers). If OTP is on, every payout waits for someone to type a code and
   merchants are never paid automatically.
4. **Fund payouts.** Transfers are paid from your Paystack *balance*. Make sure
   collections settle to the balance (or top it up) so payouts don't fail with
   "balance is not enough". A failed payout can be retried from Admin → plan →
   **Retry payout**.
5. **Go live:** swap in the live secret key and set `PAYMENTS_MODE=live`. Test
   one real GHS 1 collection end to end (pay → SMS receipt → ledger row
   `success`) before opening up.

Merchants pick their MoMo network (MTN / Telecel / AirtelTigo) or bank on the
register and Shop settings pages; payouts go to that account.

## 9. Paystack dashboard settings

Settings → API Keys & Webhooks:

- **Webhook URL:** `https://paysmallsmall.com/webhook/paystack`. Events are
  checked with the `x-paystack-signature` header (HMAC-SHA512 with your secret
  key), so no separate webhook secret is needed.
- **Callback URL:** leave empty — the app sends its own per payment.

USSD still runs on your USSD gateway: point its callback at
`https://paysmallsmall.com/webhook/ussd`.

## Quick smoke test after deploy

```bash
curl -s -o /dev/null -w "%{http_code}\n" https://paysmallsmall.com/        # 200
curl -s -o /dev/null -w "%{http_code}\n" https://paysmallsmall.com/shop    # 200
curl -s -o /dev/null -w "%{http_code}\n" https://paysmallsmall.com/.env    # 404 — must NOT be readable
```
