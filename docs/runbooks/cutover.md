# Cutover Runbook — Amelia's by EAT

> **Last updated:** 2026-06-04
> **Owner:** Renato (Aslan Advisors) · **Client contact:** Stacey Weber
> **Purpose:** A dated, reversible procedure for replacing the incumbent stack
> (WordPress/WooCommerce brochure + Square online ordering/gift cards + Yelp
> reservations) with the unified Aslan platform, without stranding customers,
> losing money owed, or tanking local-search rankings.

This is a **money-touching** cutover. Square gift-card balances are **real money
owed**. Follow the steps in order. Do not skip the reconciliation or the
rollback-readiness checks. Every step is reversible up to the DNS/redirect flip;
after the flip, recovery is **forward-fix**, not restore (see § Rollback).

---

## 0. Pre-flight gates (must ALL be green before scheduling a date)

| Gate | Question | Status today | Blocks |
|---|---|---|---|
| G1 | cPanel/GoDaddy creds + host + DocumentRoot confirmed | **BLOCKED — Q#10** | deploy, DB provision |
| G2 | AZ TPT tax approach + rates confirmed (or Stripe Tax committed) | **BLOCKED — Q#8** | live checkout tax correctness |
| G3 | Square gift-card export received + honor/parallel/hard-cut decision | **BLOCKED — Q#4** | gift-card honor |
| G4 | WooCommerce data: carry-over vs fresh decided (+ export if carry) | **BLOCKED — Q#6** | catalog/customer import |
| G5 | Existing Wine Club members: migrate vs new-only decided (+ list) | **BLOCKED — Q#7** | subscription billing |
| G6 | Stripe LIVE keys entered in admin Settings + webhook endpoint live | pending creds | all money paths |
| G7 | SendGrid domain/DKIM verified (transactional email deliverable) | pending | confirmations/receipts |
| G8 | Apple/Google Pay domain registered in Stripe + association file served | pending | wallet buttons |
| G9 | Production-readiness checklist (63 items) passed | see production-readiness.md | launch trust |
| G10 | Off-server DB backup verified + restore dry-run succeeded | pending | rollback safety |

> **Rule:** No 🔴 gate (G2/G3/G4/G5) ships its dependent capability live until
> answered. The platform builds and deploys with each gated capability
> **wired-but-disabled** behind its feature flag (see `docs/superpowers/HUMAN-QUESTIONS.md`).
> Cutover can proceed for the un-gated surfaces; gated ones flip on later.

---

## 1. Money-touching decision: per-item honor strategy

Before any data moves, record the **honor / parallel / hard-cut** decision for
every incumbent money balance. Do not assume.

### 1a. Square gift cards (Q#4) — REAL MONEY OWED 🔴

| Strategy | What it means | Implementation |
|---|---|---|
| **honor-by-import** | Import Square balances into native `gift_cards`; platform redeems them. | `src/Services/GiftCardImport.php` (gated `FEATURE_GIFTCARD_IMPORT`). Must reconcile to the EXACT Square export total or roll back. |
| **parallel** | Keep Square gift cards redeemable at Square for a defined window; staff manually credit on presentation. No import. | No code path; staff SOP + signage. Set a sunset date. |
| **hard-cut** | Square off on date D; balances must be imported (honor-by-import) or comped — **never silently stranded**. | If not imported, a stranded customer = a complaint + a refund. Only choose if balances are imported. |

**Default until Q#4 answered:** native gift cards work; **no Square balances
imported** (`FEATURE_GIFTCARD_IMPORT=false`). A customer presenting a Square card
post-cutover is handled manually by staff until the decision lands.

**Reconciliation rule (honor-by-import):** `SUM(gift_cards.current_balance)` over
imported cards **MUST equal** the Square export total to the cent. The import
service rolls back on any mismatch. Verify with a SQL sum against the export
spreadsheet total before declaring the import done.

### 1b. WooCommerce data (Q#6)

- **Default:** fresh catalog (seeded from `docs/content/menus/*.md`); no Woo import.
- **If carrying over:** WP password hashes **cannot** carry into the platform's
  scheme → set `customers.must_reset_password = 1` and run the forced-reset flow
  on first login. **Download all product media before WP teardown** (route
  through `client-image-optimization`); a torn-down WP loses its uploads.
- Generate the per-product `/product/{old-slug} => /market/{new-slug}` 301 lines
  from the export and append them to the redirect translation (§ 5).

### 1c. Existing Wine Club members (Q#7)

- **Default:** new subscriptions only; **no existing member imported or charged**
  (prevents double-billing / accidental cancellation).
- **If migrating:** explicit sign-off + member list required; create Stripe
  subscriptions deliberately, never bulk-charge on import.

---

## 2. Final export + import + reconcile (T-minus 1 day → T-0)

1. **Freeze incumbent writes window** (announce a short maintenance window; place
   the Square store in a "back soon" state if hard-cutting ordering).
2. **Final exports** (only the ones whose gate is green):
   - Square gift-card balances (Q#4) → CSV/JSON.
   - WooCommerce products/customers/orders (Q#6) → CSV/XML + media archive.
   - Wine Club member list (Q#7), if migrating.
   - Live WP blog post slug list (for the 301 map, § 5).
3. **Import into the platform DB** on staging first, then prod:
   - Gift cards via `GiftCardImport` (gate on, then **off** again after).
   - Woo catalog/customers via the import path (with `must_reset_password`).
4. **Reconcile EXACTLY:**
   - Gift cards: imported balance sum **===** Square export total (§ 1a).
   - Orders/customers row counts match the export counts.
   - Spot-check 5 gift cards and 5 customers by hand.
   - **If any total mismatches → STOP, roll back the import, investigate.**

> **Never** "drop and re-import" the live DB after launch to fix a data issue
> (see § Rollback). Reconcile before the flip; forward-fix after.

---

## 3. Deploy with backup (T-0)

1. **Backup current production** (off-server): run `cron/backup_db.php` manually
   and confirm the dated dump landed off-webroot (G10). Pull a copy locally and
   **dry-run a restore** into a scratch DB — confirm it restores cleanly.
2. **Deploy** via the GitHub Actions FTP workflow (push to `master`). The deploy
   takes its own pre-deploy backup (see `docs/runbooks/deploy.md`).
3. **Smoke the deploy on staging** (`https://parityrfp.com/cs/amelias`) before
   touching prod DNS: home/menu/reserve render; admin login works.
4. **Verify config on prod:** `APP_ENV=production`, `display_errors=Off`, Stripe
   **LIVE** keys present in Settings, webhook secret set, SendGrid verified.

---

## 4. Flip (go-live)

1. **DNS / DocumentRoot:** point `ameliasaz.com` at the new platform
   (DocumentRoot → `public/`, or the documented above-webroot layout).
2. **Regenerate the sitemap on prod:** `php scripts/generate_sitemap.php` so
   `public/sitemap.xml` carries the live `APP_URL` + published pages/posts.
3. **Confirm robots.txt + sitemap** are served:
   `https://ameliasaz.com/robots.txt` and `/sitemap.xml`.

---

## 5. Turn 301s on (translate `config/redirects.php` → `.htaccess`)

`config/redirects.php` is the authoritative old→new URL map (data file). The
front controller is owned by another track and is not edited here, so the
**redirects are applied at the Apache layer** (preferred — server-level, fastest,
best for SEO). Translate the map into `.htaccess` 301 lines.

> **Do NOT hand-edit the existing canonicalization/front-controller block in
> `public/.htaccess`.** Add the redirect block ABOVE the front-controller rewrite
> so a matched old URL 301s before it reaches `index.php`, and keep the
> webhook-route 301-exemption intact (a 301 on a POST drops the body).

**Translation recipe** (run locally, paste the output into the redirect block):

```bash
# Generate Apache 301 lines from the PHP map (same-host keys only; off-domain
# keys like square.site / yelp.com are configured at the registrar/CDN instead).
/c/xampp/php/php.exe -r '
  $map = require "config/redirects.php";
  foreach ($map as $old => $new) {
    if (preg_match("#^https?://#", $old)) continue;   // off-domain: handle elsewhere
    printf("Redirect 301 %s %s\n", $old, $new);
  }
'
```

Paste the result into `public/.htaccess` like so (illustrative — place above the
front-controller `RewriteRule`):

```apache
# --- Incumbent-URL 301s (generated from config/redirects.php — Task 7.1) ---
# Keep this block ABOVE the front-controller rewrite. Do not 301 the webhook route.
Redirect 301 /about       /story
Redirect 301 /shop        /market
Redirect 301 /reservations /reserve
# ... (paste the full generated set) ...
```

For dated WP permalinks and per-product Woo slugs, generate the explicit
`/YYYY/MM/DD/{slug} => /blog/{slug}` and `/product/{old} => /market/{new}` lines
from the export (§ 2.2) and append them — **no blanket `/* -> /`** (a blanket
301-to-home tells search engines the old pages are gone and tanks rankings).

**Off-domain incumbents** (Square store, Yelp): configure host-level redirects at
the registrar/CDN for those domains. The absolute-URL keys in
`config/redirects.php` are the inventory.

**Q#15 (Amelia's Kiln + EAT by Stacey Weber):** those old paths stay **commented
out** in `config/redirects.php` (default = omit/not-linked) so they are not
301'd to a wrong/nonexistent page. Once the client decides link-out vs
in-platform, uncomment + point them, then re-run the translation.

**Verify:** crawl the old-URL inventory → each returns `301` to the correct new
path (not 200-to-home, not 404). Run Google's Rich Results test on `/menu`
(Restaurant + Menu JSON-LD emit via `src/Support/Seo.php`).

---

## 6. Smoke-test the money paths (on prod, with LIVE keys, immediately)

Run the real flows with a real card (small amount) + refund, or Stripe LIVE test
mode where available. **All must pass before announcing.**

- [ ] **Order + pay:** add items → checkout → pay → order flips `paid` via webhook
      → confirmation page + email received.
- [ ] **Gift card buy + redeem:** buy a card → emailed code → redeem at checkout
      reduces amount due → balance ledger correct, never negative.
- [ ] **Reservation + deposit:** book a large party → deposit charged → confirmed
      → reminder scheduled.
- [ ] **Sunday Supper ticket:** buy the last seats → next buyer blocked (no oversell).
- [ ] **Feedback:** submit a rating → Google link shown to everyone → low score
      raises a staff alert.
- [ ] **Refund:** refund a test order → stock/slot restored (pre-fulfillment),
      Stripe refund recorded, ledger reconciles.
- [ ] **Apple/Google Pay:** wallet buttons render on checkout (confirms G8).

See `e2e/` for the automated versions of these (run against staging/prod base URL).

---

## 7. Comms

- [ ] Notify affected customers per the chosen gift-card strategy (§ 1a). If
      hard-cut, the notice must tell holders how their balance is honored.
- [ ] Update the **Google Business Profile** website URL (preserve reviews).
- [ ] Update Square/Yelp/social links that point at the old surfaces.
- [ ] Internal: brief staff on the new order queue + reservation dashboard +
      the gift-card SOP (see `docs/handoff/`).

---

## 8. Watch 24–48h

- [ ] Tail `logs/php-error.log` and `logs/cron.log`; watch the Stripe dashboard
      for failed/disputed payments and webhook delivery failures.
- [ ] Confirm the **hold-sweep** and **slot-generation** crons run (no `* * * * *`;
      staggered dispatcher) and the **daily off-server backup** writes.
- [ ] Spot-check that 301s still resolve and the sitemap is current.
- [ ] Watch for gift-card mismatches or oversell complaints (should be zero).

---

## Rollback (reversibility)

**Before the flip:** fully reversible — repoint DNS/DocumentRoot back to the
incumbent; the old stack is untouched until you tear it down (don't tear down WP
until § 8 is clean and media is archived).

**After the flip — FORWARD-FIX, not restore:**

> Once real orders/payments exist in the new DB, **do NOT drop-and-reimport** or
> restore an older dump over the live DB — that would **destroy real customer
> orders and payment records**. Instead:
>   1. Fix data issues with **forward-fix migrations** (`db/migrations/00NN_*.sql`)
>      that correct rows in place.
>   2. Reconcile money against **Stripe** as the source of truth for payments
>      (the `payments`/`webhook_events` ledger + Stripe payouts).
>   3. The pre-cutover backup (§ 3.1) is for **disaster recovery only** (total
>      loss), and even then you replay Stripe to recover post-backup payments.

If the platform is fundamentally broken at the flip, the safe rollback is to
**repoint to the incumbent** (if still standing) and reschedule — not to mutate
the new DB. This is why WP is not torn down until the watch window is clean.

---

## Appendix: feature-flag flip order (as gates clear)

| Flag | Gate | Effect when flipped |
|---|---|---|
| `FEATURE_GIFTCARD_IMPORT` | Q#4 | enables `GiftCardImport` for the one-time import (then turn back off) |
| `FEATURE_STRIPE_TAX` | Q#8 | commit to Stripe Tax; `order_tax_lines` snapshots Stripe amounts |
| `FEATURE_SMS` | Q#9 | SMS confirmations/reminders fire (email remains fallback) |
| `FEATURE_WINE_DTC` | Q#1/#2 | per-state wine shipping (pickup-only until then) |
