# PaySmallSmall

Installment/layaway platform for Ghanaian merchants and shoppers, built on Moolre
rails (Collections, Disbursements, SMS, USSD) for the Moolre Startup Challenge.

A customer picks a product, commits to a weekly plan, and pays the first
installment by MoMo on the spot. Money accumulates in platform escrow; every
payment gets an SMS receipt. When the plan completes, the merchant is paid out
automatically (minus platform fee) and releases the item.

## Local setup (XAMPP)

```
# 1. Create schema
C:\xampp\mysql\bin\mysql.exe -u root < database\schema.sql

# 2. Copy .env.example to .env (defaults work for XAMPP; PAYMENTS_MODE=mock)

# 3. Seed demo data
C:\xampp\php\php.exe database\seed.php

# 4. Serve
cd public && C:\xampp\php\php.exe -S localhost:8080
```

Demo logins after seeding (also printed by the seed script):

| Who | Login | Secret |
|---|---|---|
| Customer (Ama, 2 plans) | 0241000001 | PIN 1234 |
| Customer (Kwame) | 0501000002 | PIN 1234 |
| Merchant (phone shop) | 0244111222 | demo1234 |
| Merchant (furniture) | 0209333444 | demo1234 |
| Admin | 233000000000 | admin123 |

## Layout

```
public/          document root: front controller, css, js, uploads
app/Core/        config, db, router, view, auth, csrf, helpers
app/Controllers/ one per area (shop, plans, merchant, admin, webhooks)
app/Models/      thin static query classes per table
app/Services/    MoolreService (ONLY Moolre gateway), PlanService (money logic),
                 SmsTemplates, UssdMenu
app/Views/       php templates, one layout
database/        schema.sql + seed.php
scripts/         reminders.php (daily cron)
```

- All money is **pesewas (integers)**.
- `transactions` is an **append-only ledger** — every collection, disbursement
  and refund, with provider references and raw payloads.
- `PAYMENTS_MODE=mock` makes every payment succeed instantly through the same
  code path a real webhook would take — full demo without Moolre credentials.

See `DEPLOY.md` for production (CloudPanel) and `DECISIONS.md` for the choices
made along the way.
