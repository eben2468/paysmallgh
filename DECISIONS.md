# Decisions

Running log of choices made where the brief was ambiguous. Newest last.

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
