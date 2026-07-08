# PaySmallSmall — Project Brief

Read this file fully before writing any code. It is the source of truth for what we are building, how it must look, and how it must sound. When in doubt, re-read this file.

## What this is

PaySmallSmall is an installment/layaway platform for Ghanaian merchants and shoppers, built for the Moolre Startup Challenge (submission deadline: **13 July 2026** — we have only a few days, so bias toward working software over perfection).

The core loop:
1. A merchant lists a product (e.g., phone, GHS 1,200 cash price).
2. A customer commits to a payment plan (e.g., GHS 100 weekly for 12 weeks) and pays the FIRST installment immediately via Moolre Collections (MoMo).
3. Money accumulates in platform escrow. Customer gets an SMS receipt after EVERY installment.
4. When the plan completes, the merchant is paid out via Moolre Disbursements (minus platform fee) and releases the item. Both sides get SMS confirmation.
5. Feature-phone users can check plan progress and pay installments via USSD.

Why this wins for Moolre: one sale becomes 13+ transactions on their rails, and it creates purchases that otherwise never happen. Escrow solves trust in both directions (merchant can't vanish with deposits; customer can't take goods unpaid).

## Tech stack — do not deviate

- **Plain PHP 8.x with a custom MVC structure** (models, views, controllers, a front controller with a simple router). Mirror the structure of a hand-rolled PHP MVC app — NO Laravel, NO Composer frameworks. Composer is fine for small libraries (e.g., Guzzle for HTTP) but the app skeleton is ours.
- **MySQL** (MariaDB-compatible SQL only — avoid MySQL 8-only syntax like `ALTER TABLE ... RENAME COLUMN`; the production server runs MariaDB).
- Vanilla CSS (a single well-organized stylesheet with CSS custom properties) + minimal vanilla JavaScript. **No Tailwind, no Bootstrap, no CSS framework** — framework defaults are visually recognizable and we need this site to look hand-made.
- Deployment target: CloudPanel VPS (Nginx + PHP-FPM). Document root will be `/public`. All config must work behind Cloudflare (respect `X-Forwarded-Proto` for HTTPS detection).
- Environment config in a `.env`-style file loaded by a tiny config class. Never hardcode credentials.

## Deployment notes (production habits of the owner)

- Git-based deploys: code is pulled on the server with `git pull` **run as the site user**, never root (avoids ownership conflicts).
- Config file edits on the server are done with `tee` + heredoc, not interactive editors — when writing deployment docs, show commands in that style.
- MySQL master credentials on the server come from `clpctl`. App connects via `localhost` socket unless there's a reason for `127.0.0.1`.
- Write a short `DEPLOY.md` as part of the project with the exact CloudPanel steps.

## Moolre integration

Moolre provides: **Collections** (accept MoMo/bank payments), **Disbursements** (send payouts), **USSD**, and **SMS**. API docs are at docs.moolre.com — check them for exact endpoints, auth headers, and webhook formats before wiring anything. Do NOT invent endpoint URLs from memory.

Architecture requirements:
- One `MoolreService` class wrapping all API calls (collect, disburse, sms, status-check). Nothing else in the codebase talks to Moolre directly.
- **A `PAYMENTS_MODE=sandbox|live|mock` switch.** In `mock` mode, payment calls succeed instantly against a local simulator table so the full product can be demoed end-to-end even without live API credentials. Build mock mode FIRST so the demo is never blocked.
- Webhook endpoint for payment confirmations, with signature/reference verification and idempotency (a webhook replay must not double-credit an installment).
- Every money movement writes to a `transactions` ledger table (type: collection | disbursement | refund; status; provider reference; raw payload). The ledger is append-only.
- USSD: build the menu handler as a webhook endpoint that takes session state + user input and returns menu text (standard USSD gateway pattern). Menu: 1. My plans → progress + amount left. 2. Pay installment. 3. Help. Keep every screen under 160 chars.

## Data model (starting point — refine as needed)

- `users` (customers): name, phone (unique, Ghana format 233XXXXXXXXX), pin/password hash
- `merchants`: shop name, owner name, phone, location, payout channel (momo/bank), payout number, status
- `products`: merchant_id, name, description, photo, cash_price_pesewas, category, active
- `plans` (an active layaway): product_id, customer_id, total_pesewas, installment_pesewas, frequency (daily/weekly), installments_total, installments_paid, status (active/completed/cancelled/defaulted), grace state
- `installments`: plan_id, number, amount_pesewas, due_date, paid_at, transaction_id
- `transactions`: the ledger described above
- `sms_log`: recipient, body, status, provider ref

**All money in pesewas (integers). Never floats.**

## Product rules

- First installment is paid at commitment — no payment, no plan.
- Missed payment: 3-day grace period, friendly SMS reminder, no penalty. After grace: plan flagged, merchant notified. Cancellation refunds the customer via Disbursement minus a small fee (configurable, default 5%).
- Platform fee deducted from merchant payout (configurable, default 5%).
- Merchant payout is triggered automatically when `installments_paid == installments_total`, and requires the disbursement to succeed before the plan is marked completed.

## Design system — READ CAREFULLY. The owner's #1 requirement is that this site must NOT look AI-generated.

### Banned outright (these are AI-website tells)
- Purple/indigo/blue gradients of any kind. No gradient hero backgrounds.
- Fonts: Inter, Poppins, Roboto, Open Sans, Montserrat. Do not use them.
- Emoji as icons (🚀 ✨ 💡 📱). No emoji anywhere in the UI.
- Glassmorphism, floating blob shapes, mesh gradients, "cards floating on gradients."
- Perfectly symmetrical 3-column feature grids with icon-title-blurb repeated.
- Stock-photo aesthetics: laptops on desks, generic smiling models, handshake photos.
- Dark hero with neon accent glow.
- Default border-radius-everywhere soft SaaS look.

### The look we want instead: "Accra market meets modern editorial"
- **Background:** warm paper cream `#FAF6EF` (not pure white). Text: near-black ink `#1A1714`.
- **Primary:** deep market green `#0E5A3C`. **Accent:** burnt orange `#E8590C` used sparingly (buttons, progress bars, price highlights). A dusty yellow `#F2B705` for small highlights only. NOT the Ghana flag combo laid out as red-gold-green stripes — that's cliché; use these as a warm palette, not a flag.
- **Typography:** a characterful display font for headlines (pick from Google Fonts: "Fraunces" or "Clash Display" via Fontshare or "Space Grotesk" as fallback) paired with a humanist body font ("Instrument Sans" or "Epilogue"). Big, confident headline sizes. Tight leading on headlines, generous on body.
- **Layout:** asymmetric. Text blocks offset from images. Vary section rhythms — a full-bleed photo section, then a narrow text column, then a two-up. It should feel art-directed, not templated.
- Sharp corners or barely-rounded (2–4px max) on cards and buttons. Solid 1.5px ink-colored borders instead of drop shadows. If shadow is needed, a hard offset shadow (e.g., `4px 4px 0 #1A1714`) — a print/poster feel.
- **Imagery:** real Ghanaian context — market stalls, phone shops, tailors, actual products photographed simply. The owner will supply/replace photos; use neutral placeholders with correct aspect ratios and a note where real photos go. Never generate illustration-style AI art.
- Small human details: hand-underlined words in the hero (SVG squiggle underline in orange), a marquee strip of product examples with prices in GHS, progress bars that look like they were drawn with a marker.
- Numbers matter: always show money as `GHS 1,200` and breakdowns like `GHS 100 × 12 weeks`. Money math visible everywhere builds trust.
- Mobile-first. Most visitors are on phones. Test every page at 375px width first.

### Voice and copy — the second half of "humanized"
- Write like a Ghanaian talking to their cousin. Short sentences. Contractions. Direct address ("you").
- Use real local situations: school reopening, rent advance stress, chop money, "momo me."
- Banned words: seamless, empower, unlock, revolutionize, leverage, journey, solution(s), hassle-free, cutting-edge, elevate, effortless. Banned pattern: triple lists ("fast, easy, and secure").
- Example of the register — hero: "That phone you've been eyeing? Pay small small — GHS 100 a week — and it's yours. No lump sum. No borrowing from anybody."
- Merchant pitch talks money, not mission: "Customers who can't pay GHS 1,200 today can pay GHS 100 every Friday. You still make the sale. We hold the money — nobody runs off with your stock."
- Microcopy is written with care: missed-payment SMS says "Life happens. You missed this week's GHS 100 — no penalty yet. Pay by Friday and you're still on track." Not "Payment overdue. Please remit immediately."
- All SMS and receipts fit in 160 characters where possible.

## Pages to build (priority order)

1. Landing page (hero, how-it-works in 3 steps, live example plan with real math, merchant call-out, USSD code displayed big, footer)
2. Customer: browse products → product page with plan picker → commit + first payment → My Plans dashboard (progress bars, receipts, pay-now)
3. Merchant: register → dashboard (products CRUD, active plans, payouts view)
4. Admin (minimal): merchants approval, plans overview, transactions ledger, simulate-payment button (mock mode)
5. USSD webhook + SMS templates
6. DEPLOY.md

## Working style

- Commit early and often with clear messages.
- Seed script with realistic demo data: 3 merchants (phone shop, furniture, seamstress), ~10 products with believable GHS prices, 4–5 plans in various states — the demo video will be recorded from seeded data.
- When something is ambiguous, choose the simplest option that demos well and note the decision in a `DECISIONS.md`.
- Deadline is everything: a smaller thing that works end-to-end beats a bigger thing that half-works.
