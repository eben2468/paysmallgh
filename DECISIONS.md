# Decisions

Running log of choices made where the brief was ambiguous. Newest last.

## 2026-07-09 — Real payments (async lifecycle)

- **Live/sandbox payments are asynchronous, with a status-check safety net.** In mock mode a collection is instant. In sandbox/live, `collect()`/`disburse()` only *accept* the request; the final result arrives via the webhook (`/webhook/moolre`) or by polling `status()`. Both paths funnel through the same idempotent `PlanService::applyCollectionSuccess()` / `finalizePayout()`, so a webhook and a status-check settling the same payment can't double-credit (guarded by `installments.paid_at` + transaction status).
- **Three ways a payment can settle**, in order of speed: (1) Moolre webhook, (2) customer taps "I've paid — check now" on the plan page, (3) `scripts/reconcile.php` cron sweep / admin "Reconcile pending payments" button. This means a demo never dead-ends even if webhooks aren't reachable (e.g. localhost).
- **Pending plans are now visible to the customer.** `Plan::forCustomer` includes `pending` plans (awaiting-payment first) so a customer can return and finish/confirm the first payment. The merchant view still excludes pending — a not-yet-started plan is not the merchant's concern.
- **`readState()` parses provider status defensively** and defaults to `pending` on anything ambiguous, so an unexpected response shape is retried rather than wrongly credited or failed. Exact field names/codes are still to be confirmed against docs.moolre.com (see DEPLOY.md §8).
- **All Moolre wire-format details moved to `.env`** (channel codes, currency, callback URL) — nothing about the provider's format is hardcoded, so going live is a config exercise, not a code change (unless field names differ).
- **SMS never breaks a payment.** `MoolreService::sms()` swallows its own errors and always writes an `sms_log` row (`sent`/`failed`); a failed receipt can't roll back a confirmed installment.

## 2026-07-08 — Initial build

- **Moolre endpoints are config-driven placeholders.** docs.moolre.com is a JS app that can't be scraped, and the brief forbids inventing endpoints from memory. The auth header scheme (`X-API-USER`, `X-API-KEY`, `X-API-PUBKEY`, `X-API-VASKEY`) is confirmed from Moolre's public materials; the endpoint *paths* live in `.env` (`MOOLRE_PATH_*`) and MUST be checked against docs.moolre.com before flipping `PAYMENTS_MODE` to `sandbox` or `live`. Mock mode is complete and is the demo path.
- **Mock mode shares the real confirmation code path.** A mock payment calls the same `PlanService::applyCollectionSuccess()` a webhook would, so switching to live changes only where the confirmation comes from, not what it does.
- **"No payment, no plan"** is implemented as: plan row created with status `pending`, invisible everywhere, activated only when the first collection is confirmed. Pending rows are harmless orphans if payment never lands.
- **Plan picker offers preset weekly counts** (4/6/8/12/16/24 weeks) with a floor of GHS 20 per installment, instead of free-form input. Simpler to demo, harder to fat-finger. Weekly only for now — the schema supports daily, the UI doesn't yet.
- **Installment amount = ceil(price / weeks)**, so the customer may overpay by a few pesewas on the total (e.g. GHS 1,250 over 12 weeks = GHS 104.17 → GHS 105/week). The overage stays in the payout to the merchant. Simple beats clever at this stage.
- **First installment is due "today"**, then weekly from there. Grace sweep (`runReminders`) is a method callable from an admin button *and* a cron (see DEPLOY.md); no background worker needed.
- **Merchant payout on completion, not partial payouts.** One disbursement per plan, minus platform fee, per the brief. The plan is only marked `completed` after the disbursement succeeds.
- **Refund on cancellation** goes out as a `refund`-type disbursement to the customer's MoMo, minus the configurable cancel fee. Only `active` plans can be cancelled by the customer.
- **Webhook verification** uses a shared secret (header `X-Webhook-Secret` or `secret` field) + the reference must match a transaction we created. Idempotency is enforced at two layers: transaction status check and `installments.paid_at IS NULL` guard on the UPDATE.
- **Admin is a single account from `.env`** (`ADMIN_PHONE` / `ADMIN_PASSWORD`). Not worth a table for one operator before the deadline.
- **USSD sessions are stored in a DB table** keyed by gateway session id, since USSD gateways POST each hop statelessly. Field names in the USSD webhook (`sessionid`, `msisdn`, `message`) are the common gateway pattern — confirm Moolre's exact names in their docs and adjust `WebhookController::ussd()` if needed.
- **Product photos** are merchant uploads to `public/uploads/` (JPG/PNG/WebP, 4MB cap, MIME-checked). Seeded products ship without photos on purpose — the placeholder block marks where the owner's real photos go.
- **Fonts:** Fraunces + Instrument Sans from Google Fonts per the design brief. If offline demo is a risk, download the woff2 files into `public/assets/fonts/` and swap the `<link>` for `@font-face`.

## 2026-10-01 — Payments moved from Moolre to Paystack

- **Paystack handles all money movement; Moolre keeps SMS.** Paystack has no SMS product, so SMS moved out into its own `SmsService` (same Moolre SMS API and behaviour as before). `PaystackService` is now the only class that talks to a payments API. `MoolreService` is gone.
- **Wire format checked against Paystack's own API reference** (the OpenAPI spec and docs code samples Paystack publishes). Base `https://api.paystack.co`, `Authorization: Bearer <secret>`, amounts in pesewas (the same unit we store, so no conversion anywhere).
- **Web payments use hosted checkout** (`/transaction/initialize`). The customer pays by MoMo or card on Paystack's page and comes back to `/plan/{id}?reference=…`, which is verified on arrival. If they tap Pay again while a checkout is open, they get the *same* checkout rather than a second charge.
- **USSD uses a direct MoMo charge** (`/charge` with `mobile_money`). The network comes from the number's prefix. Telecel Cash asks for a voucher (`send_otp`) that can't be collected inside a USSD session, so the customer is told to pay on the website and nothing is charged.
- **Nothing is credited from a webhook payload.** The `x-paystack-signature` HMAC-SHA512 must match, and then the reference is re-verified with Paystack's API. A success only counts if amount *and* currency match the ledger row; a mismatch is marked failed and never credited.
- **Unpaid checkouts expire** after `PAYSTACK_PENDING_EXPIRY_HOURS` (default 24), so a plan can't be stuck behind an abandoned page. If that old page is somehow paid later, the signed `charge.success` webhook re-checks the expired row and still credits it.
- **Merchant payouts are Paystack Transfers.** The merchant's MoMo network or bank is stored as `merchants.payout_bank_code`. The Paystack transfer recipient is created on the first payout, cached in `merchants.paystack_recipient_code`, and cleared whenever the payout details change. Only one payout per plan can be pending or done at a time; a failed one can be retried from the admin plan page.
- **Cancellation refunds use Paystack Refunds**, one per paid installment (each minus `CANCEL_FEE_PCT`), so money goes back to whatever the customer paid with: MoMo wallet or card. This means we never have to guess the customer's network. Cancelling claims the plan atomically, so a double-tap can't refund twice. If no refund at all is accepted, the plan goes back to active. Partial failures can be retried from the admin plan page. Money paid into an already-cancelled plan (a late checkout) is refunded automatically.
- **Paystack needs an email; customers only give a phone.** We send `<phone>@PAYSTACK_EMAIL_DOMAIN`.
- **Seed fix:** each seeded plan's opening checkout is now settled as its first payment. Before this, every plan seeded one payment short and the "completed" demo plan never completed.

## 2026-10-01 — Stay logged in

- **Logins last 30 days (`SESSION_LIFETIME_DAYS`) and the clock resets on every visit.** Before, PHP's defaults ended sessions after 24 idle minutes or when the browser closed. Customers got logged out while paying on Paystack or approving a MoMo prompt.
- **Sessions are stored in `storage/sessions`** instead of the system temp folder, so the server's own session cleanup (24-minute default on Debian/CloudPanel) can't delete them early.
- **Paystack sends customers back to the host they were on** (`absolute_url()`): `www.` or bare domain, whichever they used, so the login cookie comes with them. Any other host falls back to `APP_URL`.
- **Logout is per role.** Logging out as a customer doesn't end an admin or merchant login in the same browser. The session is destroyed only when no role is left. Admin got a Log out button (there was none).

## 2026-10-01 — Admin + merchant portals, consistent header

- **Admin and merchant pages use their own portal layout** (`layouts/portal.php`): a sidebar with grouped navigation and to-do counts, a top bar, and a user card with Log out. On phones the sidebar becomes a slide-out drawer. Public, customer and login pages keep the main site layout.
- **Admin is split into clear sections:** Dashboard (key numbers + "Needs your attention"), Merchants, Customers, Plans, Transactions, SMS log, and System (Paystack/SMS status, test SMS, run-now jobs). Lists have filter tabs, and actions return you to the page you were on.
- **One wording everywhere:** "Log in" / "Log out" (never "Sign in"), "Create account" for customers, "Register your shop" for merchants.
- **The header knows everyone who's logged in** on that browser (customer, merchant, admin), with a dashboard link and a separate Log out for each. Login pages skip straight to your dashboard if you're already logged in.

## 2026-10-02 — Merchant payment options, category list, pay in full

- **Merchants choose the schedules per product** (`products.plan_frequencies`: any of daily/weekly/monthly, at least one). The product page only offers those, and `PlanController` rejects any other choice server-side.
- **Pay in full is always on** (merchants can't switch it off — it only helps them). It's stored as a one-payment plan with `frequency = 'once'`, so it reuses the same checkout, escrow ledger, automatic payout and SMS flow. The customer gets a "paid in full" SMS, then the usual "go collect it" once the shop is paid.
- **Categories come from a fixed list** (`Product::CATEGORIES`). Products saved earlier under a custom category keep it until edited.
- **A fully paid plan can't be cancelled** while its payout is going through. Cancelling then would refund the customer and pay the shop for the same money.
- Plan wording ("GHS 100 × 12 weeks", "Pay this month's…") now comes from shared helpers (`freq_words`, `plan_math`, `plan_rate`). This also fixes monthly plans being called "a week" in the start SMS.
