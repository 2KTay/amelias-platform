# Production-Readiness Checklist — Amelia's by EAT

> **Last updated:** 2026-06-04
> **Gate:** This 63-item checklist must pass before the first production deploy
> (Task 7.3). Run it again at cutover (see `cutover.md` gate G9).

**Status legend**

| Mark | Meaning |
|---|---|
| ✅ done | Implemented + verified in the build. |
| 🔧 todo | Engineering work remaining (no external blocker). |
| 🚧 human-blocked | Waiting on a client answer / credential / external registration. |

Counts are maintained at the bottom. Items are grouped; each has an ID (`P-NN`).

---

## A. Security headers / CSP (P-01 … P-09)

- [ ] **P-01** 🔧 Hand-authored **Content-Security-Policy** allowlisting only what
  the app needs: `frame-src js.stripe.com`, `script-src` for Stripe.js + GTM/GA4
  + reCAPTCHA, `connect-src` Stripe + GA4, `img-src` self + data + maps, fonts,
  `form-action` self + Formspree. No `unsafe-inline` for scripts.
- [ ] **P-02** 🔧 `Permissions-Policy: payment=(self)` so the Payment Request /
  wallet API is allowed for first-party Stripe.
- [ ] **P-03** 🔧 `X-Content-Type-Options: nosniff`.
- [ ] **P-04** 🔧 `X-Frame-Options: SAMEORIGIN` (+ CSP `frame-ancestors 'self'`).
- [ ] **P-05** 🔧 `Referrer-Policy: strict-origin-when-cross-origin`.
- [ ] **P-06** 🚧 **HSTS** (`Strict-Transport-Security`) — enable only AFTER HTTPS
  is verified working on the prod domain (premature HSTS can lock out the site).
- [ ] **P-07** 🔧 Force HTTPS (HTTP → HTTPS 301) in `.htaccess` (canonicalization
  block already present — verify it survives alongside the redirect map).
- [ ] **P-08** 🔧 **SRI** on CDN scripts where the resource is static; document the
  documented exceptions for Stripe.js / GTM (versioned/dynamic — SRI not viable).
- [ ] **P-09** 🔧 No secrets in client-side source / inline JS (Settings secrets
  are AES-encrypted at rest, env-only key, never emitted to public pages).

## B. Uploads locked (P-10 … P-15)

- [ ] **P-10** ✅ Uploads stored **out of web root** (or `UPLOADS_PATH` above
  DocumentRoot) — see `config/app.php` `paths.uploads`.
- [ ] **P-11** 🔧 `uploads/.htaccess` with `php_flag engine off` + deny
  `.php/.phtml/.phar` execution (defense-in-depth even out of webroot).
- [ ] **P-12** ✅ `finfo` MIME validation on upload (Media service) — not trusting
  the client-sent type/extension.
- [ ] **P-13** ✅ Random-hex stored filenames (no user-controlled paths) — Media.
- [ ] **P-14** 🔧 `Options -Indexes` globally (no directory listing).
- [ ] **P-15** 🔧 Explicit `.htaccess` deny-set for `includes/`, `config/`, `db/`,
  dotfiles, `*.sql`, `*.md`, `composer.*`.

## C. PCI / payments (SAQ-A) (P-16 … P-22)

- [ ] **P-16** ✅ **SAQ-A**: raw card data never touches the server — Stripe hosted
  fields / Payment Element only.
- [ ] **P-17** ✅ Webhook is the **source of truth**; signature-verified;
  idempotent via `webhook_events`.
- [ ] **P-18** ✅ Webhook route **CSRF-exempt but signature-verified**, raw body,
  **excluded from www/HTTPS 301 canonicalization** (a 301 on POST drops the body).
- [ ] **P-19** ✅ `pending` order/hold created before redirect to payment; flipped
  to `paid` only by the webhook.
- [ ] **P-20** ✅ `charge.dispute.created` → order `disputed` + staff alert; handler
  returns 500 for retryable, 200 for permanent.
- [ ] **P-21** 🚧 Stripe **LIVE** keys entered in admin Settings (test-connection
  passes) — pending credentials (Q#10) + Stripe account.
- [ ] **P-22** 🚧 Stripe webhook endpoint registered for the prod URL with the
  live signing secret in Settings — pending live deploy.

## D. Error pages / error handling (P-23 … P-27)

- [ ] **P-23** ✅ Custom **404** page (`templates/errors/404.php`) via the router
  not-found handler.
- [ ] **P-24** 🔧 Custom **500** page (`templates/errors/500.php`) — no stack trace
  to guests in production.
- [ ] **P-25** ✅ `display_errors=Off` + `log_errors=On` in production (bootstrap).
- [ ] **P-26** 🔧 405 handled (method-not-allowed view) — router supports it.
- [ ] **P-27** 🔧 Errors logged to `logs/php-error.log`; log dir not web-served.

## E. Performance / Core Web Vitals (P-28 … P-35)

- [ ] **P-28** 🔧 Lighthouse **Perf ≥ 90** on the top 5 pages (home, menu, story,
  location, reserve).
- [ ] **P-29** 🔧 Lighthouse **A11y ≥ 95** on the top 5 pages (pa11y-ci wired).
- [ ] **P-30** 🔧 Image optimization pass (`client-image-optimization`):
  responsive sizes, modern formats, lazy-loading below the fold.
- [ ] **P-31** 🔧 Hero scrim ≥ `rgba(0,0,0,0.5)` for text contrast (C-CONV-1).
- [ ] **P-32** 🔧 CSS/JS minified + cache headers on static assets.
- [ ] **P-33** 🔧 Query-budget check on hot pages (no N+1 on menu/dashboard).
- [ ] **P-34** ✅ Mobile-first CSS (audit enforces `min-width`-only media queries).
- [ ] **P-35** 🔧 Fonts: `font-display: swap`; preconnect to the font host.

## F. Backups / recovery (P-36 … P-40)

- [ ] **P-36** ✅ Scheduled **daily off-server DB backup** (`cron/backup_db.php`),
  ~30-day retention, flock-guarded on the shared dispatcher (C-V7-10).
- [ ] **P-37** 🚧 Backup verified **writing off-server** on prod — pending host (Q#10).
- [ ] **P-38** 🚧 **Restore dry-run** succeeds into a scratch DB — pending host.
- [ ] **P-39** ✅ Pre-deploy backup hook in the deploy workflow (`deploy.md`).
- [ ] **P-40** ✅ Documented rollback = **forward-fix migrations + Stripe
  reconciliation**, NOT drop-and-reimport (would destroy real orders) — `cutover.md`.

## G. Money-path reconciliation (P-41 … P-46)

- [ ] **P-41** ✅ Money stored/computed as **integer cents**; tax as basis points.
- [ ] **P-42** ✅ `order_items` **snapshot** price/tax/name (past orders never re-total).
- [ ] **P-43** ✅ `payments`/`webhook_events` ledger committed (not a view) for
  stable reconciliation.
- [ ] **P-44** 🔧 Reconciliation report matches the order/payment ledger against
  Stripe payouts (Reports service) — verify with a real payout at cutover.
- [ ] **P-45** ✅ Gift-card redemption is an **atomic guarded claim** + append-only
  ledger; balance never negative under concurrency (C-V7-1).
- [ ] **P-46** 🚧 Gift-card import (if honor-by-import) reconciles to the **exact**
  Square export total — blocked on Q#4 export.

## H. Concurrency / oversell (P-47 … P-48)

- [ ] **P-47** ✅ Atomic conditional claim (`UPDATE … WHERE booked_count + n <=
  capacity` + affected-rows) — orders, slots, tickets never oversell.
- [ ] **P-48** ✅ Expiry-sweep cron releases stale `pending` holds; flock-guarded;
  staggered dispatcher (no `* * * * *`).

## I. Apple/Google Pay domain file (P-49 … P-50)

- [ ] **P-49** 🚧 `/.well-known/apple-developer-merchantid-domain-association`
  served as static `text/plain` 200 — **placeholder committed**; the **real file
  comes from Stripe** when the prod domain is registered for Apple Pay. Until then
  the wallet buttons silently don't render (C-V7-14).
- [ ] **P-50** 🚧 Prod domain registered in Stripe for Apple Pay + Google Pay —
  pending live domain.

## J. SEO surface (P-51 … P-55)

- [ ] **P-51** ✅ `public/robots.txt` (allow crawl, sitemap ref, disallow
  `/admin` + `/account` + private surfaces).
- [ ] **P-52** ✅ `public/sitemap.xml` generated from public routes + published
  pages/posts (`scripts/generate_sitemap.php`); regenerated on prod at cutover.
- [ ] **P-53** ✅ `config/redirects.php` old→new 301 map (no blanket-to-home);
  translated to `.htaccess` at cutover (`cutover.md` § 5).
- [ ] **P-54** ✅ Restaurant + Menu **JSON-LD** emit via `src/Support/Seo.php` on
  home/menu/location; canonical + OG tags present.
- [ ] **P-55** 🚧 Google Business Profile URL updated; reviews preserved — at cutover.

## K. Privacy / compliance (P-56 … P-59)

- [ ] **P-56** ✅ Cookie-consent banner gates GA4/GTM; analytics **never** loads on
  `/admin/` (C-T1-5).
- [ ] **P-57** ✅ Privacy policy + **Terms / refund-cancellation** page (CMS),
  linked from footer + checkout + deposit step (C-V7-11).
- [ ] **P-58** ✅ Marketing sends separated from transactional; CAN-SPAM unsubscribe
  + physical address (marketing dormant until Q#3).
- [ ] **P-59** ✅ Reflected params (`?table=`, comment fields) escaped; CMS HTML
  sanitized against an allowlist (C-T1-4).

## L. Auth / hardening (P-60 … P-63)

- [ ] **P-60** ✅ CSRF tokens on all forms (`hash_equals`); session regen on login;
  IP/UA hijack detection.
- [ ] **P-61** ✅ Login lockout (5×/15min via `login_attempts`); checkout / reset /
  feedback POST rate-limited (`rate_limits`) — C-T1-7.
- [ ] **P-62** ✅ Passwords `password_hash` only; demo credentials render only when
  `aslan_is_demo()` (env AND hostname); seed holds bcrypt hashes only.
- [ ] **P-63** ✅ Role gating enforced (Staff can't see financials/users; Settings
  + Reports + Users are Owner-only).

---

## Status summary

| Group | ✅ done | 🔧 todo | 🚧 human-blocked |
|---|---|---|---|
| A. Security headers/CSP | 0 | 8 | 1 |
| B. Uploads | 3 | 3 | 0 |
| C. PCI/payments | 5 | 0 | 2 |
| D. Error pages | 2 | 3 | 0 |
| E. Performance/CWV | 1 | 7 | 0 |
| F. Backups | 3 | 0 | 2 |
| G. Money reconciliation | 4 | 1 | 1 |
| H. Concurrency | 2 | 0 | 0 |
| I. Apple/Google Pay | 0 | 0 | 2 |
| J. SEO | 4 | 0 | 1 |
| K. Privacy | 5 | 0 | 0 |
| L. Auth/hardening | 4 | 0 | 0 |
| **Total (63)** | **33** | **22** | **8** |

### Human-blocked items (need a client answer / credential / registration)

- **P-06 / P-37 / P-38** — host/HTTPS + off-server backup verify + restore dry-run → **Q#10** (cPanel/GoDaddy creds + host).
- **P-21 / P-22** — Stripe LIVE keys + prod webhook endpoint → live Stripe account + creds.
- **P-46** — gift-card import reconciliation → **Q#4** (Square export + honor decision).
- **P-49 / P-50** — Apple/Google Pay domain file + Stripe domain registration → live prod domain (C-V7-14).
- **P-55** — Google Business Profile URL update → at cutover with client access.

> The remaining 🔧 items are engineering tasks (mostly the `.htaccess`/CSP
> hardening pass + performance/image pass in Task 7.3 steps 2–3) with no external
> blocker. Several touch `public/.htaccess` and `templates/partials/head.php`,
> which are owned by other tracks — coordinate before editing.
