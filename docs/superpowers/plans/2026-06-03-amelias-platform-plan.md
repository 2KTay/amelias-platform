# Amelia's by EAT — Platform Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `workflow-execute` to implement this plan task-by-task (fallback: `superpowers:executing-plans`). Steps use checkbox (`- [ ]`) syntax for tracking. Do NOT skip to a domain mother — execute through the workflow layer.

**Spec:** `docs/superpowers/specs/2026-06-02-amelias-platform-design.md`
**Date:** 2026-06-03
**Status:** Approved (plan phase). **Aligned to spec v7 (2026-06-04)** — see *Spec alignment (v5 / v6)* and *Gap-closure corrections (v7)* below.
**Pilot project:** Amelia's by EAT (ameliasaz.com → Aslan platform)

**Goal:** Replace Amelia's fragmented stack (WordPress/WooCommerce brochure, Square online ordering + gift cards, Yelp reservations) with one unified Aslan platform: one customer DB, one catalog, one order ledger, one admin dashboard. Six systems — bespoke public storefront + CMS, native e-commerce, native reservations, Stripe payments, compliant QR feedback, role-gated admin — ship in dependency order so the revenue path (ordering) lands first and the dangerous cutover lands last.

**Architecture:** PHP 8+ front controller + router (`public/index.php` + `.htaccess` pretty URLs); PDO singleton with prepared statements; **money as integer cents, tax as basis points**; historical rows (`order_items`) **snapshot** price/tax/name so past orders never re-total; **oversell-safe atomic conditional claims** in InnoDB (no Redis on shared cPanel); orders created **`pending` at checkout initiation** with an `inventory_holds` row, flipped to `paid` by the **webhook (source of truth)**, swept to `expired` by cron; Stripe Checkout/Elements (SAQ-A) for one-time + Stripe Billing for Wine Club; provider-agnostic notification interface (email now, SMS behind A2P gate); self-service Owner-only Settings (AES-encrypted secrets, env fallback, masked, test-connection, audited) and a DB-backed CMS. See the spec for full rationale; this plan does not re-derive it.

**Tech Stack:** PHP 8+ · MySQL (InnoDB, utf8mb4, UTC) · Vanilla JS + CSS (5-file token architecture) · GoDaddy/cPanel · GitHub Actions FTP deploy · Stripe (Checkout/Elements + Billing + candidate Stripe Tax) · Twilio SMS + SendGrid/SMTP email · Playwright (E2E) · `php audit/conventions.php` (CI gate).

**Testing approach:** Per task — `php audit/conventions.php --quiet` must pass on touched files; Playwright smoke on critical money/booking flows once `e2e/` exists; manual user-workflow checklists (framed as operator/guest journeys, not unit assertions); `quality-production-readiness` (63 items) before the first prod deploy; money-path reconciliation against Stripe before cutover.

---

## How to read this plan

- **Phases mirror the spec's "Suggested build sequencing"** (§ build sequencing). Ship revenue first, cutover last.
- **Human-blocked items** are gathered in **Phase H (the client-question gate)** and cross-referenced from the tasks they block. `workflow-execute` must NOT fabricate the missing fact, must NOT retry, and must surface the block to Renato.
- **`parallel: true`** marks tasks a fresh subagent can run without ordering deps. Most foundation tasks are sequential.
- Each Stripe/booking/money task carries an **unhappy-path** note (spec § *Unhappy paths & screen states*).

## Audit corrections incorporated (v4)

A 2026-06-03 skill audit produced corrections now recorded in the spec's *Aslan-skills conformance corrections (v4)* section. Each binds to a task here:

| Correction | Binds to task |
|---|---|
| C-T1-1 Timezone `America/Phoenix`, UTC connection tz, `utf8mb4_unicode_ci`, BIGINT cents | 0.2, 1.1 |
| C-T1-2 `flock` + dispatcher + `cron.log` + 5–15min hold-sweep | 2.3, 3.1 |
| C-T1-3 Upload security (finfo/random-name/engine-off/out-of-webroot) | 1.6 (Media) |
| C-T1-4 CMS HTML sanitize + reflected-param escaping | 1.6, 5.1 |
| C-T1-5 Cookie consent + privacy policy, no tracking in `/admin/` | 1.6, 7.1 |
| C-T1-6 Staff-login screen + full demo-credential standard | 1.2 |
| C-T1-7 `login_attempts`/`rate_limits` tables, lockout, session regen | 1.1, 1.2 |
| C-T1-8 CSP/SRI/headers, webhook route survives 301 + CSRF-exempt-signed | 1.5, 7.3 |
| C-CONV-1 Admin typography (sans), two theme layers, min-width, card-stack | 0.4 |
| C-DATA-2 Pickup capacity only in `pickup_slots` | 1.1 |
| C-DATA-3 `audit_log`/`payments`/`product_shippable_states`/LTV defined | 1.1, 6.3 |
| C-STRIPE-4 Billing net-new; `disputed` status; retry policy | 1.5, 4.3 |
| C-TAX-5 Commit Stripe Tax **or** `tax_rates` | 2.4 |
| C-MIG-6 `must_reset_password` + media download on Woo import | 7.2 |
| C-RTM-7 Render forward+backward RTM (screen-map table below = forward half) | this plan |
| C-QUAL-8 `.frontend-conventions.json`, pa11y/Lighthouse CI, test-card matrix, email skeleton, custom error pages | 0.4, 1.4, 7.3 |
| C-HAND-9 USER-MANUAL + training + quick-ref + handoff email | 7.4 |
| C-MISC-10 Logo path, Restaurant/Menu JSON-LD, llms.txt/canonical/OG, pin host + DocumentRoot | 0.3, 7.1 |

---

## Spec alignment (v5 / v6)

Two later spec passes refine this plan; the affected tasks below already reflect them. Deltas:

**v5 (internal consistency):**
- **`tables` entity + `bookings.table_id`** — reservations are *capacity-booked, table-assigned-on-seating*; a host assigns a physical table at seat time. Binds: Task 1.1 (0006), Task 3.1, Task 3.3.
- **`catering_requests`** table (new|quoted|accepted|declined|expired) — a catering enquiry the admin prices; acceptance spawns a `pending` order. Binds: Task 1.1, Task 4.2.
- **`product_shippable_states`** join table (already in 0005) + **`customers.notify_channel`** (email|sms|both). Binds: Task 1.1, Task 1.4.
- **Notifications = customer choice** — the guest picks email and/or SMS; SMS activates on A2P clearance, email is the fallback. Binds: Task 1.4.

**v6 (public visual direction — already built as mockups):**
- **Verified earth-tone brand:** ink `#313530`; creams `#f9f6e4`; taupe `#ccc6b9`; sage `#9a9a8d`; **olive `#5f8219`** + **wheat-gold `#e1c188`** accents; **navy `#171A2D` dropped** from the public brand (admin-only). Fonts: **Audrey** display + **Montserrat** body (Fraunces stand-in pending Q13a). Binds: Task 0.4, Task 1.6.
- **v3 design system already built** — `mockups/brand-v3.css` + `home-v3.html` + 19 public pages are the visual reference. **Task 0.4 ports `brand-v3.css` into the production 5-file token set**; the public-page tasks (1.6, 2.x, 3.2, 4.x) implement their approved v3 mockups.
- **Two new public pages:** **Purveyors** (`purveyors.html`) + **Careers** (`careers.html`). Binds: Task 1.6.
- **Menus extracted for seeding:** `docs/content/menus/{day,pm,happy-hour,catering}-menu.md` (from `docs/assets/*.pdf`); the Menu screen renders them on-page (day-part tabs) with a print/PDF link. **Reduces the Q#12 block** (Day/PM/Happy Hour/Catering items+prices received; Wine Club $40/mo + Sunday Supper cadence captured). Binds: Task 2.1, Task 4.2, Task 4.3, Task 4.5.
- **Photography received:** real set in `images/` (two modes — high-key daytime + B&W heritage). **Reduces the Q#13b block.** Binds: Task 0.4, Task 1.6.

## Gap-closure corrections incorporated (v7)

A 2026-06-04 gap-analysis pass produced corrections now recorded in the spec's
*Gap-closure corrections (v7)* section. Each binds to a task here:

| Correction | Binds to task |
|---|---|
| C-V7-1 Gift-card balance atomic claim + ledger + reconciled `current_balance` | 1.1, 2.5 |
| C-V7-2 Refund/cancel → stock & slot restoration (pre- vs post-fulfillment) | 2.3, 2.5 |
| C-V7-3 Catering balance-due = second payable order; request `paid` when both clear | 1.1, 4.2 |
| C-V7-4 Reservation/event cancel + no-show money policy (cutoff/forfeit; restaurant-cancel refunds all) | 3.2, 4.5 |
| C-V7-5 Customer Account portal + `customer_favorites`/`customer_addresses`; reorder rehydrates snapshots | **new 6.6**, 1.1 |
| C-V7-6 `promotions` + `order_discounts` (snapshotted, pre-tax, atomic cap) or drop the field+tables | 1.1, 2.2, 2.4 |
| C-V7-7 ASAP → next-open-slot atomic claim + min prep-lead (no uncapped path) | 2.2 |
| C-V7-8 Day-part ordering rule (browse anytime, order active day-part only) | 2.1 |
| C-V7-9 `notification_log` unique (entity, template, channel) — send-once | 1.4, 3.2 |
| C-V7-10 Scheduled daily off-server DB backup; blog carried as CMS posts type | 0.3, 1.6, 7.1 |
| C-V7-11 Terms/refund-policy page (CMS), linked from checkout + deposit step | 1.6, 7.1 |
| C-V7-12 `comped`/`voided` as real audit-logged admin actions | 2.5 |
| C-V7-13 Guest order cancellation (tokenized, paid & pre-`preparing`) | 2.5 |
| C-V7-14 Apple/Google Pay domain-association file + Stripe domain registration | 2.4, 7.3 |
| C-V7 (consistency) H1 inline-in-spec, RTM map +2b/18a/18b, relabel phantom skills | H1, this plan |

---

## Phase H: Client-question gate (human-blocked — runs continuously, blocks specific tasks)

> These are the spec's *Open client questions*. They are not code tasks; they are blockers tracked here so execution never stalls silently or invents answers. Email/collect now; most of the platform builds without them, but the flagged tasks below cannot **launch** until answered.

### Task H1: Send the client-question list and track answers

**Why this matters:** This platform replaces tools real customers already use (Square gift cards = real money owed). Building on guessed tax rates, guessed gift-card handling, or unconfirmed wine-shipping legality would ship something wrong, illegal, or financially harmful. Getting answers in flight unblocks launch without blocking the build.

**Files:**
- Modify: the spec's § *Questions we need Amelia's to answer* — track answers **inline in the spec** (the single source of truth), appending an **Answer / Date** line under each question as it arrives. **Do NOT create `docs/client-context/open-questions.md`** — per Renato + CLAUDE.md, the decision log, RTM, and open questions all live in the spec, not in separate `docs/client-context/` files (C-V7 consistency fix; the earlier plan draft wrongly created that file).

**Acceptance (EARS):**
- *Ubiquitous:* The platform shall not enable any feature whose legality or money-correctness depends on an unanswered 🔴 question (wine DTC shipping, Square gift-card handling).
- *Event-driven:* When the client answers a question, the system owner shall record the answer + date **inline in the spec's questions section** and unblock the dependent task.

**Verification:**
- [ ] Renato workflow: open the spec's *Questions* section → every question present with owner + status + (once answered) an Answer/Date line; no separate `docs/client-context/` file exists.
- [ ] No 🔴 item is marked resolved without a recorded client answer.

**parallel:** true

- [ ] **Step 1: Draft the question list** (human-blocked)
  Track the **16** questions inline in the spec, grouped (wine/alcohol, tool switch-off, money/tax, build-launch needs, menu/programs/look, **policies** Q14/Q16, **existing-content scope** Q15). Mark 🔴 blockers: #1 (wine shipping), #4 (Square gift cards). Q14 (deposit/no-show), Q15 (Kiln/blog/EAT scope), Q16 (tipping) are Policy/Scope — assumed-and-noted defaults, not launch blockers.
- [ ] **Step 2: Send to client via Renato** (human-blocked)
  Block launch of: wine shipping (#1, #2), marketing sends (#3), gift-card cutover (#4), POS sync (#5), Woo data migration (#6), Wine Club migration (#7), tax rates (#8), SMS/A2P (#9), credentials (#10), delivery link-out (#11), menu/program data (#12), brand fonts/photos (#13).
- [ ] **Step 3: Record answers as they arrive** (human-blocked)
  Each answer updates `open-questions.md` and releases the named task.

---

## Phase 0: Scaffolding & infrastructure

### Task 0.1: Initialize repo, directory structure, and front controller

**Why this matters:** Every later task needs a place to put files and a router to reach them. Standardizing the structure now (per `infra-project-scaffolding`) means a fresh subagent in Phase 3 knows exactly where a controller, view, or migration goes.

**Files:**
- Create: `.gitignore`, `README.md`, `composer.json` (Stripe SDK, PHPMailer/SendGrid), `.editorconfig`
- Create: `public/index.php` (front controller), `public/.htaccess` (pretty URLs + hardening)
- Create: `includes/Router.php`, `includes/Request.php`, `includes/Response.php`
- Create: `config/app.php` (reads env), `config/routes.php`
- Create directory skeleton: `src/Controllers/`, `src/Services/`, `src/Models/`, `templates/public/`, `templates/admin/`, `templates/partials/`, `db/migrations/`, `cron/`, `e2e/`, `audit/`

**Acceptance (EARS):**
- *Ubiquitous:* The app shall route every request through `public/index.php` (no direct PHP file access).
- *Event-driven:* When a request hits an undefined route, the system shall return a 404 view (not a server error).

**Verification:**
- [ ] Dev workflow: hit `/`, `/menu`, `/nope` → home renders, menu renders, 404 renders.
- [ ] `php audit/conventions.php --quiet` passes (no short tags, no inline styles in templates).

**parallel:** false

- [ ] **Step 1: `git init`** and write `.gitignore` (vendor/, uploads/, .env, *.local).
- [ ] **Step 2: Build the front controller + router** — path → controller@method dispatch, 404/500 handlers.
- [ ] **Step 3: Write `.htaccess`** — route all to `index.php`, deny `includes/`/`config/`/`db/`, block dotfiles (full hardening lands in Task 0.4 / security pass).

### Task 0.2: PDO database layer + helpers + migration runner

**Why this matters:** Mechanical — enables every data task. A single PDO singleton with prepared statements and `fmt_*`/cents helpers is the spine the whole order ledger and concurrency model sit on; getting integer-cents and UTC right here prevents money bugs everywhere downstream.

**Files:**
- Create: `includes/Database.php` (PDO singleton, exception mode, utf8mb4, UTC session tz)
- Create: `includes/helpers.php` (`cents()`, `fmt_money()`, `e()` htmlspecialchars wrapper, `csrf_token()`, `bps()` )
- Create: `db/migrate.php` (applies `db/migrations/*.sql` in order, records in `schema_migrations`)
- Create: `db/migrations/0001_schema_migrations.sql`

**Acceptance (EARS):**
- *Ubiquitous:* All DB access shall use prepared statements (no string interpolation in SQL).
- *Ubiquitous:* All money shall be stored and computed as integer cents (BIGINT for sums that can exceed INT range — gift-card totals, lifetime value); tax rates as integer basis points.
- *Ubiquitous:* Timestamps shall be stored UTC (`time_zone='+00:00'` per connection) and **displayed in `America/Phoenix`** (no DST); every table shall be `utf8mb4_unicode_ci` (C-T1-1).

**Verification:**
- [ ] Dev workflow: `php db/migrate.php` on an empty DB creates `schema_migrations`; re-running is idempotent.
- [ ] `php audit/conventions.php --quiet` passes (prepared statements, no interpolation).

**parallel:** false

- [ ] **Step 1: PDO singleton** — `ATTR_ERRMODE=EXCEPTION`, `ATTR_EMULATE_PREPARES=false`, `SET time_zone='+00:00'`.
- [ ] **Step 2: Helpers** — cents↔display, escaping (`e()`), CSRF (`hash_equals`), basis-points math; `fmt_*` block with `FMT_TZ=America/Phoenix` (override the `America/New_York` default — C-T1-1).
- [ ] **Step 3: Migration runner** — ordered apply + idempotency table.

### Task 0.3: cPanel MySQL provisioning + GitHub Actions FTP deploy + backup-before-deploy

**Why this matters:** The client operates without a developer; deploys must be push-button and reversible. A backup-before-deploy step (per `infra-backup-rollback`) is the difference between a bad deploy being a 5-minute rollback vs a lost-data incident on a live restaurant.

**Files:**
- Create: `.github/workflows/deploy.yml` (FTP on push to `master`, per `infra-ftp-deploy`)
- Create: `docs/runbooks/deploy.md` (env vars, secrets in GH Actions, rollback steps)
- Create: `config/env.example.php` (documents required env: DB creds, `APP_KEY`, `APP_ENV`)
- Create: `cron/backup_db.php` (scheduled daily `mysqldump` → dated off-server copy, ~30-day retention, flock-guarded via the shared dispatcher — C-V7-10)

**Acceptance (EARS):**
- *Event-driven:* When code is pushed to `master`, the system shall back up the current deploy, then FTP-sync, excluding `vendor/`-built artifacts per the workflow.
- *Ubiquitous:* The deploy shall never ship `.env`, secrets, or `db/` to the web root.

**Verification:**
- [ ] Infra workflow: provision cPanel DB + user (`data-mysql-setup`), record creds in GH Actions secrets (human-blocked on credentials — Q#10).
- [ ] Deploy workflow runs green to staging (`parityrfp.com` or client staging) and the home route loads.

**parallel:** false

- [ ] **Step 1: Provision DB/user** on cPanel (human-blocked: GoDaddy/cPanel login — Q#10) — **least-privilege grants** (SELECT/INSERT/UPDATE/DELETE, never ALL PRIVILEGES); pin the production host + path and confirm whether DocumentRoot can target `public/` (else keep `includes/config/uploads` above web root or hard-deny) — C-MISC-10.
- [ ] **Step 2: FTP deploy workflow** + GH secrets.
- [ ] **Step 3: Backup-before-deploy** hook + documented rollback.
- [ ] **Step 4: Scheduled daily DB backup** — `cron/backup_db.php` dated off-server dump (~30-day retention), flock-guarded on the shared dispatcher; restore is dry-run-tested at cutover (C-V7-10). This is independent of the pre-deploy backup so live data between deploys is protected.

### Task 0.4: CSS 5-file token architecture + brand application + conventions audit script

**Why this matters:** The bespoke public site and the templated admin both read from one token layer; defining primitives→semantic→component tokens now (with Amelia's **verified earth-tone brand**: ink `#313530`/`#292B2C`, creams `#f9f6e4`, taupe `#ccc6b9`, sage `#9a9a8d`, **olive `#5f8219`** + **wheat-gold `#e1c188`** accents; **navy `#171A2D` dropped** from the public palette, admin-only) means no hardcoded hex leaks later, and the CI audit catches drift on every PR. **This task ports the already-built `mockups/brand-v3.css` (the approved v3 public design system) into the 5-file token set.**

**Files:**
- Create: `public/assets/css/{tokens,reset,theme,base,components,utilities}.css` (5-file structure per `frontend-css-architecture`)
- Create: `audit/conventions.php` (the `frontend-conventions-audit` script wired for CI)
- Create: `templates/partials/head.php`, `templates/partials/footer.php`
- Reference: `docs/BRAND-GUIDE.md` (v6 verified brand), `docs/assets/brand/` (logo SVG), and **`docs/superpowers/specs/mockups/brand-v3.css` + `home-v3.html`** (the approved v3 public design system this task ports into the 5-file set)

**Acceptance (EARS):**
- *Ubiquitous:* No hardcoded hex shall appear outside `tokens.css`/`theme.css`; no inline `style="..."` in templates; all media queries shall be `min-width` (mobile-first).
- *Ubiquitous:* Two theme layers shall share one primitive set — public uses the **Audrey** display (Fraunces stand-in pending Q13a); the **admin theme overrides `--font-display` to the body sans** (the public display serif never in-tool); admin list tables card-stack on phone via `data-label` (C-CONV-1).
- *Ubiquitous:* The CI audit shall run `php audit/conventions.php --quiet` (+ pa11y-ci + Lighthouse CI) on every PR against the **production app dir** (mockups excluded) and fail on violations; waivers live in `.frontend-conventions.json` (`AslanException FE-EX-{id}`) — C-QUAL-8.

**Verification:**
- [ ] Audit workflow: introduce a hardcoded hex in a template → audit fails; remove → passes.
- [ ] Visual: head/footer partials render with Amelia's logo + brand tokens.

**parallel:** false

- [ ] **Step 1: Token files** — primitives (extracted palette/fonts), semantic, component layers.
- [ ] **Step 2: Audit script** + `.frontend-conventions.json` + `.github/workflows/audit.yml` (conventions + pa11y-ci + Lighthouse CI) wired as a PR check against the production app dir.
- [ ] **Step 3: Port `brand-v3.css` + fonts** — fold the verified earth-tone tokens + the v3 component layer into the 5-file set; display **Audrey** may be licensed (Q#13a), so the v3 mockups use **Fraunces** as the stand-in until confirmed (human-blocked on confirmation, not on build).

---

## Phase 1: Foundation (DB · auth · settings/secrets · CMS · Stripe+webhooks · notifications · public shell)

### Task 1.1: Full database schema (DDL) — all core tables

**Why this matters:** This is the single source of truth the entire platform reads and writes. Getting the snapshot columns, complete status enums, join tables, FKs, and integer-cents/basis-points types right *here* means past orders never re-total, oversell has a home, and the owner's reports are answerable in SQL. A wrong schema is the most expensive thing to fix after launch.

**Files:**
- Create: `db/migrations/0002_customers_users.sql` (`customers` [+ `must_reset_password` for Woo imports, + `notify_channel` email|sms|both — C-V5], `customer_favorites` [customer_id, product_id, created_at], `customer_addresses` [customer_id, label, line1/2, city, state, postal, is_default, purpose] — C-V7-5; `users` [owner|manager|staff], `audit_log` [actor, action, entity_type, entity_id, before_json, after_json, at], `login_attempts` [ip, username, at], `rate_limits` [key, window, count] — C-T1-7, C-DATA-3)
- Create: `db/migrations/0003_catalog.sql` (`categories`, `products` [type, `tax_category`, `ships_dtc`+allowed-states, **`day_part` + `available_from`/`available_to` window** for order-time gating — C-V7-8], `product_category_map` join, `product_variants`, `modifier_groups`, `modifiers`, `product_modifier_map`, `dietary_tags` [incl. allergen flags where supplied], `inventory`)
- Create: `db/migrations/0004_orders.sql` (`orders` [full status enum, + `kind` standard|catering_deposit|catering_balance, + nullable `catering_request_id`], `order_items` [snapshotted], `order_item_modifiers`, `order_tax_lines` join, `order_discounts` [order_id, promotion_id, `discount_amount_cents` snapshotted — C-V7-6], `promotions` [code, type, value, min_subtotal_cents, window, max_redemptions, per_customer_limit, applies_to, active — C-V7-6], `tax_rates`, `pickup_slots`, `inventory_holds`, `catering_requests` [event_date, headcount, package/details, `quoted_total_cents`, `deposit_cents`, status new|quoted|accepted|deposit_paid|balance_due|paid|declined|expired — accepted spawns a deposit `pending` order, balance is a second payable order; C-V5-5/C-V7-3])
- Create: `db/migrations/0005_giftcards.sql` (`gift_cards` [+ `status` active|depleted|void; `current_balance` cached, reconciled to the ledger; redeemed via guarded atomic `UPDATE … WHERE current_balance >= :amt` + affected-rows — C-V7-1], `gift_card_transactions` [append-only ledger incl. refund-credit rows], `product_shippable_states` [product_id, state] join table for wine allowed-states — C-DATA-3)
- Create: `db/migrations/0006_bookings.sql` (`booking_resources`, `booking_slots`, `bookings` [+ `table_id` NULL until seat-time], `tables` [physical floor: label, section, min/max party, status, sort], `waitlist`) — reservations are capacity-booked, table-assigned-on-seating (C-V5-1)
- Create: `db/migrations/0007_subscriptions.sql` (`subscriptions`, `subscription_tiers`)
- Create: `db/migrations/0008_feedback.sql` (`feedback`, `feedback_alerts`)
- Create: `db/migrations/0009_cms.sql` (`content_blocks`, `pages`, `media`, `team_members`)
- Create: `db/migrations/0010_settings_payments.sql` (`settings` [is_secret], `payments` ledger — **commit to a webhook-fed table** (not a view) so reconciliation is stable; `webhook_events` [event_id, type, status, payload, error_message] idempotency — C-DATA-3)
- Note: pickup capacity lives **only** in `pickup_slots` (0004); `booking_resources` (0006) has no `pickup_window` type (C-DATA-2). Lifetime value is a computed SQL aggregate over paid orders, not a stored column (C-DATA-3).
- Create: `db/SCHEMA.md` (ER notes + the RTM data-model half: every field → requirement)

**Acceptance (EARS):**
- *Ubiquitous:* Every money column shall be integer cents; every tax-rate column integer basis points; every timestamp UTC.
- *Ubiquitous:* `order_items` shall snapshot `unit_price_cents`, `tax_amount_cents`, `tax_treatment`, name, and modifier deltas (never read live product price).
- *Ubiquitous:* Every status enum shall cover the complete lifecycle (orders: pending|paid|fulfilled|cancelled|refunded|partially_refunded|comped|voided|expired; bookings: held|confirmed|seated|completed|cancelled|no_show).
- *Ubiquitous:* Hidden many-to-many relations (product↔category, order↔tax-lines) shall use join tables; all FKs declared (InnoDB).

**Verification:**
- [ ] Dev workflow: `php db/migrate.php` builds the full schema clean; FKs enforced (insert orphan → rejected).
- [ ] SCHEMA.md: each of the owner's report questions (revenue by stream, tax report) is answerable in SQL against this schema (inline schema-design methodology — the "write the reports as SQL" lens; not an installed skill).
- [ ] `order_schema` supports adding `delivery` fulfillment type without migrating existing rows (column default + enum room).

**parallel:** false

- [ ] **Step 1: customers/users/audit** — guest = no `password_hash`; staff roles.
- [ ] **Step 2: catalog** — tax_category enum [prepared_food|retail_goods|grocery|service|non_taxable], `ships_dtc`+allowed-states for wine.
- [ ] **Step 3: orders/holds/slots** — snapshot columns, `expires_at`, `booked_count`, `inventory_holds`.
- [ ] **Step 4: gift cards, bookings, subscriptions, feedback, CMS, settings/payments/webhook_events.**
- [ ] **Step 5: Write SCHEMA.md** with the RTM backward-trace (every field → a requirement; cut orphans).

### Task 1.2: Auth & sessions — staff roles + optional customer accounts + guest

**Why this matters:** Staff need role-appropriate access to the back office (a host must not see financials); guests must be able to check out without an account; the few customers who want order history get an optional login. This is the gate that makes every later role-restricted screen enforceable.

**Files:**
- Create: `src/Services/Auth.php` (login, `password_hash`/`verify`, role checks, rate limiting)
- Create: `includes/session.php` (secure cookie flags, regen on login, CSRF middleware)
- Create: `src/Controllers/AuthController.php`, `templates/public/auth.php` (screen 18), `templates/admin/login.php`
- Create: `db/seed/demo_users.sql` (per `security-demo-credentials`: `admin/password`, `user/password123`, env+hostname gated)

**Acceptance (EARS):**
- *State-driven:* While a user's role is Staff, the system shall hide financial reconciliation and user management.
- *Event-driven:* When a guest checks out without an account, the system shall complete the order against a `customers` row with no `password_hash`.
- *Ubiquitous:* All forms shall carry CSRF tokens (`hash_equals`); passwords shall be stored with `password_hash` only; the session ID shall regenerate on login with IP/UA hijack detection.
- *Event-driven:* When login fails 5×/15min for an IP+username, the system shall lock out (via `login_attempts`); checkout, password-reset, and the QR-feedback POST shall be rate-limited (via `rate_limits`) — C-T1-7.
- *State-driven:* While not on a demo host, the system shall not render demo credentials; `seed.sql` shall contain bcrypt hashes only (plaintext in README) — C-T1-6.

**Verification:**
- [ ] Staff workflow: open the **staff-login screen** (separate from customer `auth.php`) → log in as Staff → no Reports/Users nav; as Owner → both visible.
- [ ] Guest workflow: reach checkout with no account → allowed.
- [ ] Demo creds (`admin/password`, `user/password123`) render only when `aslan_is_demo()` (env AND hostname) is true, with copy-to-clipboard (no autofill); 6th bad login locks out.

**parallel:** false

- [ ] **Step 1: Auth service + sessions** (secure flags, regen, rate limit).
- [ ] **Step 2: Customer auth screen** (login/create/guest-continue/reset — screen 18).
- [ ] **Step 3: Staff login + role middleware**; demo-credential gating.

### Task 1.3: Self-service Settings & Integrations (encrypted secrets, Owner-only) — screen 28

**Why this matters:** The client must run the business without a developer. Every Stripe/Twilio/email/Google key is entered and rotated here — not hardcoded by an engineer. Encrypting at rest, masking, test-connection, and env-fallback are what let Stacey change a key herself without ever exposing it to the public site.

**Files:**
- Create: `src/Services/Settings.php` (get/set, env fallback, `is_secret` AES encrypt/decrypt with `APP_KEY` from env)
- Create: `src/Services/Crypto.php` (AES-GCM, key from env, never in DB)
- Create: `src/Controllers/Admin/SettingsController.php`, `templates/admin/settings.php` (masked reveal, per-integration Test connection, status badges)
- Modify: `config/app.php` (env fallback contract: DB value wins once set)

**Acceptance (EARS):**
- *State-driven:* While a setting is unset in the DB, the system shall fall back to the env var; once set in DB, the DB value shall win.
- *Ubiquitous:* Secrets shall be AES-encrypted at rest with a key held in env (never in DB), masked in UI (`••••••3xQ`), and never emitted to public pages or client-side JS.
- *State-driven:* While the user's role is not Owner, the system shall not render the Settings screen.
- *Event-driven:* When a setting changes, the system shall write who/when to `audit_log`.

**Verification:**
- [ ] Owner workflow: open Settings → enter a Stripe test key → "Test connection" succeeds → key shows masked; reveal shows full only on click.
- [ ] Security: view public page source / JS → no secret present. Non-Owner → Settings 403.
- [ ] Audit: change a setting → `audit_log` row with user + timestamp.

**parallel:** false

- [ ] **Step 1: Crypto + Settings service** — authenticated **AES-256-GCM with per-value IV** (or libsodium `crypto_secretbox`), app key env-only (never DB), env fallback, masking; document a key-rotation story.
- [ ] **Step 2: Settings screen** — integration cards (Stripe, Twilio, email, Google Places/GA4/Maps, reCAPTCHA, social) + business config (hours, AZ TPT rates by category, slot capacity, deposit threshold/amount, service fee, closures).
- [ ] **Step 3: Test-connection per integration** (server-side, no secret exposure).

### Task 1.4: Notification service — provider-agnostic interface (email now, SMS gated)

**Why this matters:** Order confirmations, ready-alerts, and reservation reminders are the platform's voice to guests. Building one interface with email + SMS adapters means email ships immediately (independent of the weeks-long Twilio A2P approval), and SMS flips on later with zero code changes — no guest ever waits on a carrier registration to get their receipt.

**Files:**
- Create: `src/Services/Notifications/NotifierInterface.php`, `EmailAdapter.php` (SendGrid/SMTP via `client-transactional-email`), `SmsAdapter.php` (Twilio), `Notifier.php` (channel selection by `customers.notify_channel` + enabled flags)
- Create: `templates/emails/` (order-confirmation, order-ready, reservation-confirmation, reservation-reminder, gift-card-delivery, receipt)
- Create: `db/migrations/0011_notifications_queue.sql` (queue + retry, transactional vs marketing separation, **`notification_log`** [entity_type, entity_id, template, channel, sent_at] **unique on (entity_type, entity_id, template, channel)** for send-once idempotency — C-V7-9)

**Acceptance (EARS):**
- *Ubiquitous:* The notifier shall send via the **customer's chosen channel(s)** (`customers.notify_channel`: email and/or SMS); SMS activates when the channel (A2P 10DLC) is enabled, with email as the fallback (so notifications never fully depend on SMS).
- *Event-driven:* When a send fails, the system shall queue and retry (email independent of SMS).
- *Ubiquitous:* Each reminder/confirmation shall send **exactly once** per (entity, template, channel) via `notification_log`, so cron overlap or a retried webhook never double-sends (C-V7-9).
- *Ubiquitous:* Marketing sends shall be separated from transactional and shall include an unsubscribe link (CAN-SPAM).

**Verification:**
- [ ] Dev workflow: trigger an order-confirmation → email sends (SMS skipped while disabled, no error).
- [ ] Enable SMS flag in Settings (with test creds) → both channels fire.
- [ ] A2P 10DLC registration is human-blocked (Q#9); email path verified independently.

**parallel:** false

- [ ] **Step 1: Interface + email adapter + templates** — production skeleton per `client-email-templates` (bulletproof button, hand-written plain-text MIME part, CAN-SPAM footer with Amelia's physical address); requires SendGrid domain/DKIM verification at launch (C-QUAL-8).
- [ ] **Step 2: SMS adapter** (Twilio) behind the enabled-flag (A2P human-blocked — Q#9).
- [ ] **Step 3: Queue + retry**; transactional/marketing split.

### Task 1.5: Stripe integration core + webhook handler (source of truth) + idempotency

**Why this matters:** Webhooks — not the browser redirect — are the authoritative record that money moved. Building the idempotent webhook handler and the `pending`-order contract now, before any checkout screen exists, means every later revenue stream (orders, gift cards, deposits, subscriptions) plugs into one correct payment spine instead of each reinventing payment state.

**Files:**
- Create: `src/Services/Payments/Stripe.php` (client init from Settings, PaymentIntent/Checkout session, refund)
- Create: `src/Controllers/StripeWebhookController.php` (signature verify, idempotency via `webhook_events`)
- Create: `public/webhooks/stripe.php` route (raw body, no CSRF, allowlisted)
- Create: `src/Services/Payments/PaymentStateMachine.php` (`pending → paid`, release on failure)
- Modify: `config/routes.php` (webhook route)

**Acceptance (EARS):**
- *Ubiquitous:* The system shall never receive or store raw card numbers (SAQ-A; Stripe hosted fields only).
- *Event-driven:* When Stripe sends a webhook, the system shall verify the signature, process it idempotently (`webhook_events`), and treat it as authoritative (`pending → paid`, or release on failure).
- *Event-driven:* When a guest initiates checkout, the system shall create a `pending` order/booking before redirecting to payment.
- *Event-driven:* When `charge.dispute.created` arrives, the system shall set the order `disputed` and alert staff; the handler shall return 500 for retryable failures and 200 for permanent ones (C-STRIPE-4).
- *Ubiquitous:* The webhook route shall be a real path **excluded from www/HTTPS 301 canonicalization** (a 301 on POST drops the body) and **CSRF-exempt but Stripe-signature-verified** (C-T1-8).

**Verification:**
- [ ] Dev workflow: replay a `payment_intent.succeeded` webhook twice → order flips to `paid` once, second is a no-op (idempotent).
- [ ] Send `payment_intent.payment_failed` → hold released, order `expired`; send `charge.dispute.created` → order `disputed` + staff alert.
- [ ] Signature check rejects an unsigned/forged payload; the webhook URL survives the www/HTTPS redirect with its POST body intact.

**parallel:** false

- [ ] **Step 1: Stripe client** (keys from Settings, test mode).
- [ ] **Step 2: Webhook handler** — verify, `webhook_events` idempotency, dispatch by event type.
- [ ] **Step 3: Payment state machine** + the `pending`-creation contract reused by all streams.

### Task 1.6: Bespoke public site shell + CMS-driven content + Home/Story/Location — screens 1–3, 29–31

**Why this matters:** The storefront IS the brand and the proposal — a generic page undercuts Stacey's "source-to-plate" positioning. A CMS-backed shell means staff edit hero copy, hours, and Our Story without a deploy, and the bespoke layout (per `public-website-conventions`, no AI-slop tells) reinforces the brand the fragmented Square page can't.

**Files:**
- Create: `src/Controllers/PublicController.php`, `templates/public/{home,story,location,purveyors,careers}.php` (screens 1–3 + **2b Purveyors** + **18a Careers** — the v6 additions; implement their approved v3 mockups)
- Create: `src/Services/Content.php` (reads `content_blocks`/`pages`; **server-side HTML sanitize against an allowlist** before render — C-T1-4), `src/Services/Media.php` (**finfo MIME check, random-hex filename, store out of web root, `uploads/.htaccess` `php_flag engine off` + deny `.php/.phtml/.phar`** — C-T1-3) via `frontend-file-upload`
- Create: `src/Controllers/Admin/{ContentController,MediaController,TeamController}.php` + `templates/admin/{content,media,team}.php` (screens 29–31)
- Create: `templates/partials/nav.php` (v3 IA: Menu · Market · Wine Club · Sunday Supper · Catering · Our Story; CTAs Reserve + Order; footer carries Now Hiring · Our Values · Our Purveyors · Gift Cards + visit/hours; Location folds into the footer + home Come-Visit section)
- Create: `templates/public/privacy.php` + `templates/public/terms.php` (**Terms of Service + refund/cancellation policy**, CMS-editable, linked from footer + checkout + the deposit step — C-V7-11) + cookie-consent banner partial (gates GA4/GTM; tracking never loads on `/admin/` — C-T1-5); Restaurant + Menu JSON-LD partials (authored from schema.org), `llms.txt`, canonical + OG/Twitter tags (C-MISC-10)
- Create: `templates/public/{blog,post}.php` + a CMS **posts** content type (list + single) so the live site's existing blog URLs survive cutover via 301s rather than being dropped (C-V7-10)
- Create: contact form via Formspree (`client-formspree`, with `_gotcha` honeypot + domain restriction) on Location; logo read from `docs/assets/brand/` (single canonical path — C-MISC-10)

**Acceptance (EARS):**
- *Ubiquitous:* The site shall render mobile-first and pass Lighthouse Perf ≥ 90 / A11y ≥ 95 on the top 5 pages.
- *Event-driven:* When staff edit homepage hero / Our Story / hours / footer / per-page SEO in admin, the public site shall reflect it without a deploy.
- *Ubiquitous:* The site shall emit valid Restaurant + Menu JSON-LD, canonical + OG tags, and `llms.txt` on relevant pages; nav shall not wrap (≤6 primary items).
- *Event-driven:* When a visitor first loads a public page, the system shall request cookie consent before loading GA4/GTM; analytics shall never load on `/admin/` (C-T1-5).

**Verification:**
- [ ] Owner workflow: edit hero copy in admin Content → reload home → new copy, no deploy.
- [ ] Guest workflow: home → open/closed indicator reflects hours; nav CTAs reach Order/Reserve.
- [ ] Visual: passes `public-website-conventions` (2-font pairing, no banned indigo/violet, no 3-card hero cliché). Team screen demonstrates structured CMS content (optional per SOW).
- [ ] Lighthouse mobile ≥ 90/95 on home/story/location.

**parallel:** false

- [ ] **Step 1: Shell + nav/footer partials + Content/Media services** — hamburger is a real `<button>` with `aria-expanded`/`aria-controls` + ESC + focus-return; home hero carries a min `rgba(0,0,0,0.5)` scrim (C-CONV-1).
- [ ] **Step 2: Home, Our Story, Location, Purveyors, Careers** (CMS-bound; Formspree contact + careers apply; JSON-LD; open/closed by hours; Purveyors lists named local partners; implement the approved v3 mockups).
- [ ] **Step 3: Admin Content / Media / Team screens** (no-code editing, SEO fields, alt text).
- [ ] **Step 4: Brand visual finalization** — implement the **v3 design system** (port `brand-v3.css`); verified earth-tone palette + Audrey (Fraunces stand-in pending Q13a); two photography modes per `BRAND-GUIDE.md` (human-blocked on font licensing only, not on build).

---

## Phase 2: Ordering + payments (first revenue online)

### Task 2.1: Catalog + Menu browse + Item detail/modifiers — screens 4, 5, 22 (admin menu)

**Why this matters:** No menu in code — staff change prices and 86 items themselves. The public menu and the admin menu manager read the same catalog, so a price edit or a sold-out toggle propagates instantly. This is the difference between a deploy-per-price-change brochure and a tool the kitchen actually drives.

**Files:**
- Create: `src/Controllers/MenuController.php`, `templates/public/{menu,item}.php` (screens 4–5; day-parts Day/PM/Happy Hour/Catering, dietary badges, 86 state, happy-hour time gating, required-modifier validation) — **primary on-page responsive menu (day-part tabs) + secondary view/print-PDF link to `docs/assets/*.pdf`**; seed the catalog from `docs/content/menus/{day,pm,happy-hour,catering}-menu.md`
- Create: `src/Controllers/Admin/MenuController.php`, `templates/admin/menu.php` (screen 22: CRUD, bulk price edit, modifiers, dietary tags, photos, **86 toggle → public menu**, day-part assignment, Save in sticky-top)
- Create: `src/Models/Product.php`, `Category.php`, `Modifier.php`

**Acceptance (EARS):**
- *Event-driven:* When staff edit a menu item price or 86 it in admin, the public menu shall reflect it without a deploy.
- *State-driven:* While an item is 86'd, the public menu shall show it greyed/"Sold out" and reject adds.
- *State-driven:* While outside happy-hour windows, the system shall not offer happy-hour-gated items/prices.
- *State-driven:* While a day-part is not active, the system shall let guests **browse** its items but **disable Add** (showing the availability window); ordering is allowed only for the active day-part (C-V7-8).

**Verification:**
- [ ] Manager workflow: 86 the quinoa bowl in admin → public menu greys it; un-86 → returns.
- [ ] Guest workflow: open item with required modifier → cannot add without choosing; live price updates with add-ons.
- [ ] **Menu data substantially received (v6):** Day/PM/Happy Hour/Catering items + prices + modifiers are seeded from `docs/content/menus/*.md`; remaining Q#12 gaps (any new items, full dietary-tag coverage) swap on receipt.

**parallel:** false

- [ ] **Step 1: Catalog models + admin Menu manager** (CRUD, bulk, 86, day-parts).
- [ ] **Step 2: Public Menu browse** (tabbed day-parts, badges, sticky cart summary).
- [ ] **Step 3: Item detail/modifiers** (groups, special instructions, live price, validation).

### Task 2.2: Cart + pickup scheduling + reservable-stock guard — screen 6

**Why this matters:** The cart is where kitchen capacity becomes real — a guest can't pick a 12:30 slot that's full, and can't add the last Market item two people are buying at once. The reservable-stock guard messaging here is the front line of the oversell protection that keeps an angry guest from showing up to a sold-out order.

**Files:**
- Create: `src/Services/Cart.php` (session cart, line items + modifiers, subtotal in cents)
- Create: `src/Controllers/CartController.php`, `templates/public/cart.php` (edit/remove, pickup ASAP/scheduled slot picker with full slots disabled, tip, promo/gift-card field, stock-guard messaging)
- Create: `src/Services/Availability.php` (reservable stock = `inventory.stock − Σ active holds`; open pickup slots)

**Acceptance (EARS):**
- *State-driven:* While a pickup slot's `booked_count` ≥ capacity, the cart shall disable that slot.
- *State-driven:* While reservable stock for a finite item is exhausted, the cart shall show it unavailable and block adding.
- *Event-driven:* When a guest picks "ASAP", the system shall bind the order to the **next open pickup slot** (respecting the min prep-lead) and show the quoted-ready time, never an uncapped queue (C-V7-7).
- *Event-driven:* When a guest applies a promo code, the system shall validate it against `promotions` (window/min-subtotal/cap/per-customer limit), compute the discount pre-tax, and snapshot it to `order_discounts` at order creation (C-V7-6).

**Verification:**
- [ ] Guest workflow: add items → pick scheduled slot → full slots greyed; tip + gift-card field present.
- [ ] Concurrency probe: hold the last unit elsewhere → cart reflects it unavailable immediately.

**parallel:** false

- [ ] **Step 1: Cart service** (cents math, modifier deltas).
- [ ] **Step 2: Availability service** (reservable-stock view, open-slot query).
- [ ] **Step 3: Cart screen** (slot picker, tip, promo/gift-card, guard messaging).

### Task 2.3: Concurrency engine — atomic holds + pending-order creation + expiry sweep

**Why this matters:** This is the spec's #1 risk (oversell, High impact). On shared cPanel there's no Redis — correctness must live in InnoDB. An atomic conditional `UPDATE ... WHERE booked_count + n <= capacity` plus affected-rows check is what makes "two guests, one last slot" resolve correctly instead of producing a double-sold counter dispute.

**Files:**
- Create: `src/Services/HoldManager.php` (atomic conditional claim, `inventory_holds` insert, `pending` order create — one transaction)
- Create: `cron/expire_holds.php` (sweep stale `pending` → `expired`, release holds)
- Create: `docs/runbooks/concurrency.md` (the claim SQL + affected-rows contract)
- Modify: `src/Services/Payments/PaymentStateMachine.php` (convert hold → permanent decrement on `paid`)

**Acceptance (EARS):**
- *Event-driven:* When a guest initiates checkout, the system shall create a `pending` order and an inventory/slot hold atomically, or reject if no capacity remains.
- *Ubiquitous:* The system shall never let `booked_count`/stock go negative under concurrent checkouts.
- *Event-driven:* When a `pending` order's hold expires without payment, the system shall release the hold and mark the order `expired`.
- *Event-driven:* When an order is cancelled/voided/expired or refunded **before fulfillment**, the system shall restore the permanent stock decrement and free any pickup slot; a refund **after fulfillment** shall not restore stock (C-V7-2).

**Verification:**
- [ ] Concurrency test: two simultaneous claims on the last unit → exactly one succeeds, one rejected; counter never negative.
- [ ] Cron workflow: create a `pending` order, let the window lapse, run `expire_holds.php` → hold released, order `expired`, stock returns.
- [ ] Webhook `paid` converts the hold to a permanent decrement (no double-decrement).

**parallel:** false

- [ ] **Step 1: HoldManager** — guarded `UPDATE` + affected-rows; `SELECT ... FOR UPDATE` fallback for finite SKUs.
- [ ] **Step 2: Pending-order creation** in the same transaction as the hold.
- [ ] **Step 3: Expiry-sweep cron** + payment-success hold→decrement conversion. Cron uses `flock(LOCK_EX|LOCK_NB)` (concurrent sweeps would double-release holds), logs to `logs/cron.log`, runs every 5–15 min aligned to Stripe checkout-session expiry, via one staggered dispatcher (no `* * * * *`) — C-T1-2.

### Task 2.4: Checkout + tax + Stripe Payment Element — screen 7

**Why this matters:** This is where money is taken. AZ TPT is not a flat rate — prepared food and retail are taxed differently and gift cards aren't taxed at purchase — so the tax must compute per category, not globally. SAQ-A hosted fields keep raw card data off our server entirely, minimizing PCI burden for a small restaurant.

**Files:**
- Create: `src/Controllers/CheckoutController.php`, `templates/public/checkout.php` (contact/guest, Stripe Payment Element, Apple/Google Pay, order summary, AZ TPT by category)
- Create: `src/Services/Tax.php` (per-category treatment; **Stripe Tax** adapter candidate vs maintained `tax_rates`; gift-card purchase exempt)
- Modify: `src/Services/Payments/Stripe.php` (PaymentIntent from cart + tax)

**Acceptance (EARS):**
- *Event-driven:* When a guest places an order, the system shall create the `pending` order + hold (Task 2.3), then take payment via Stripe hosted fields (SAQ-A).
- *Ubiquitous:* The system shall apply tax per product-category treatment (prepared food vs retail) and shall NOT tax gift-card purchases.
- **Unhappy paths:** payment declined → order stays `pending`, hold honored until expiry, guest re-prompted; webhook never arrives → success page reads the `pending` row; slot taken between view and submit → atomic claim rejects → re-prompt.

**Verification:**
- [ ] Guest workflow: checkout a prepared-food + retail cart → tax line splits by category; gift-card-only cart → no tax.
- [ ] Decline test card → graceful retry, no oversell, hold intact.
- [ ] Tax approach (Stripe Tax vs maintained rates) is human-blocked on bookkeeper confirmation (Q#8); build the adapter seam so either works, then **commit to one** — if Stripe Tax wins, `order_tax_lines` snapshots Stripe-returned amounts and `tax_rates` is dropped (C-TAX-5).

**parallel:** false

- [ ] **Step 1: Tax service** (per-category; Stripe Tax adapter + maintained-rate fallback).
- [ ] **Step 2: Checkout screen** (Payment Element, wallets, summary) — Apple/Google Pay require hosting `/.well-known/apple-developer-merchantid-domain-association` + registering the prod domain in Stripe (verified at launch in Task 7.3, else wallet buttons silently don't render — C-V7-14); discount line (`order_discounts`) and tax compute on the post-discount subtotal.
- [ ] **Step 3: Wire `pending`-order + hold + PaymentIntent**; unhappy-path handling.

### Task 2.5: Order confirmation/status + admin order queue + gift cards — screens 8, 20, 10

**Why this matters:** After paying, the guest needs a confirmation that exists even if the webhook lags, and a live "preparing → ready" status; the kitchen needs a queue that pings on new orders and fires the ready-notification. Gift cards are tax-exempt at purchase and emailed as a code — a real revenue stream Square charges fees on today.

**Files:**
- Create: `src/Controllers/OrderController.php`, `templates/public/order-confirmation.php` (screen 8: confirmation #, status received→preparing→ready, add-to-calendar)
- Create: `src/Controllers/Admin/OrderQueueController.php`, `templates/admin/orders.php` (screen 20: live by status, mark-ready fires notification, refund [full/partial, restores stock pre-fulfillment], **comp**/**void** actions [audit-logged], sound/badge on new)
- Create: `src/Controllers/GiftCardController.php`, `templates/public/giftcards.php` (screen 10), `src/Services/GiftCard.php` (purchase → emailed code, tax-exempt; redeem via **atomic guarded balance claim** + ledger row, reconcile cached balance; refund = inverse credit — C-V7-1)
- Modify: `src/Services/Cart.php` (gift-card redemption reduces amount due)

**Acceptance (EARS):**
- *Event-driven:* When the webhook reports payment success, the system shall flip the order to `paid`, convert the hold, and send an email confirmation (and SMS when enabled).
- *Event-driven:* When staff mark an order ready, the system shall notify the guest.
- *Event-driven:* When a gift card code is applied, the system shall claim the balance via a guarded atomic `UPDATE` (affected-rows check) inside the checkout transaction, write a `gift_card_transactions` row, and never go negative under concurrent redemptions; gift-card purchase shall not be taxed (C-V7-1).
- *Event-driven:* When an Owner/Manager **comps** (zero-out + reason, no Stripe) or **voids** (cancel unfulfilled) an order, the system shall record it in `audit_log` and restore stock per the reversal rule (C-V7-12).
- *Event-driven:* When a guest uses the tokenized cancel link while the order is `paid` and not yet `preparing`, the system shall refund and restore stock; after `preparing`, cancellation is staff-only (C-V7-13).

**Verification:**
- [ ] Guest workflow: pay → confirmation page shows from the `pending→paid` row even before webhook; status advances as kitchen updates.
- [ ] Staff workflow: new order pings the queue → mark ready → guest gets notification.
- [ ] Gift-card workflow: buy a $50 card → emailed code → redeem at checkout reduces due by balance; insufficient balance handled.
- [ ] Concurrency probe: two simultaneous redemptions of the same card → total redeemed never exceeds the balance; balance never negative; ledger sums to `current_balance` (C-V7-1).

**parallel:** false

- [ ] **Step 1: Order confirmation + status** (reads pending row; live status).
- [ ] **Step 2: Admin order queue** (statuses, mark-ready notify, refund, new-order alert).
- [ ] **Step 3: Gift cards** (purchase emailed code, tax-exempt; redeem flow).

---

## Phase 3: Reservations (retire Yelp)

### Task 3.1: Booking model + real-time availability + slot generation cron

**Why this matters:** Reservations are why guests leave Yelp behind. Real-time availability against tables — with atomic claims so two guests can't book the same slot — plus rolling slot pre-generation respecting hours/blackouts is the engine that makes "book instantly" trustworthy.

**Files:**
- Create: `src/Services/Booking.php` (availability by party size + time against `booking_resources`/`booking_slots`; atomic `booked` claim)
- Create: `cron/generate_slots.php` (rolling horizon, respects hours/blackouts/lead-time)
- Create: `src/Models/Booking.php`, `BookingResource.php`

**Acceptance (EARS):**
- *Event-driven:* When a guest books an available slot, the system shall claim the **slot** atomically (capacity-booked; no double-booking under concurrency) and confirm instantly; the physical `tables` row is assigned at seat time (C-V5-1).
- *Event-driven:* When no slot is available, the system shall offer the waitlist.

**Verification:**
- [ ] Concurrency test: two bookings for the last slot → one confirmed, one offered waitlist; `booked` never exceeds capacity.
- [ ] Cron workflow: `generate_slots.php` creates future slots honoring hours + blackouts.

**parallel:** false

- [ ] **Step 1: Booking service + atomic slot claim** (reuses HoldManager pattern).
- [ ] **Step 2: Slot-generation cron** (hours/blackouts/lead-time) — `flock`-guarded, **idempotent** (no duplicate slots on re-run), UTC/DST-safe, blackouts do not retro-cancel already-booked slots; same dispatcher as the hold-sweep (C-T1-2).

### Task 3.2: Reservation booking flow + deposits + modify/cancel + waitlist — screen 12

**Why this matters:** Standard tables are free, but large parties, events, and Sunday Suppers need a Stripe deposit to cut no-shows that cost the kitchen real prep. Tokenized modify/cancel links and a waitlist mean guests self-serve without calling, and the restaurant captures demand it currently loses.

**Files:**
- Create: `src/Controllers/ReservationController.php`, `templates/public/reserve.php` (party size + date + real-time slots; guest details; deposit step for large parties/events; tokenized modify/cancel; waitlist when full)
- Create: `src/Services/Waitlist.php` (waiting|offered|converted|expired)
- Modify: `src/Services/Notifications/Notifier.php` (confirmation + T-minus reminder)

**Acceptance (EARS):**
- *State-driven:* While party size ≥ the large-party threshold (or for events/Sunday Suppers), the system shall require a Stripe deposit before confirming; the slot is held `pending` until deposit succeeds, released on failure/expiry.
- *Event-driven:* When a guest books, the system shall send an email confirmation (and SMS when enabled).
- *Time-driven:* The reminder shall send T-minus a configurable window before slot start via enabled channels, **exactly once** per booking (`notification_log` — C-V7-9).
- *State-driven:* While a deposit cancellation is before the configurable cutoff (default 48h), the system shall refund it in full; after the cutoff or on `no_show`, it shall forfeit per policy (Q14, C-V7-4).
- *Event-driven:* When a waitlisted table is offered, the system shall give a configurable claim window (default 10 min) and roll the offer to the next waiter on expiry (no held capacity) (C-V7-4).
- **Unhappy paths:** deposit abandoned → booking hold released; slot taken between view and submit → re-prompt; cancellation after cutoff → no refund, clear messaging.

**Verification:**
- [ ] Guest workflow: book a 2-top free + instant confirm; book a 10-top → deposit required → pay → confirmed.
- [ ] Modify/cancel via tokenized link works without login; full date → waitlist offered.
- [ ] Reminder fires at the configured window (email; SMS if enabled).

**parallel:** false

- [ ] **Step 1: Booking flow + deposit gate** (reuses pending/hold + Stripe).
- [ ] **Step 2: Tokenized modify/cancel + waitlist.**
- [ ] **Step 3: Confirmation + reminder notifications.**

### Task 3.3: Admin table management / reservations dashboard — screen 21

**Why this matters:** A host runs the floor from this screen — seat, clear, turn tables, work the waitlist, see deposit status and walk-ins on a day timeline. It's the in-restaurant workflow the spec promises the back office should match, replacing Yelp's host view.

**Files:**
- Create: `src/Controllers/Admin/ReservationsController.php`, `templates/admin/reservations.php` (day view, timeline, seat/clear/turn, waitlist, deposits, walk-ins, party details, calendar) — seating assigns a `bookings.table_id` from the `tables` floor (C-V5-1)

**Acceptance (EARS):**
- *Event-driven:* When a host seats/clears/turns a table, the system shall update booking status (held→seated→completed) and free capacity.
- *State-driven:* While a deposit is unpaid for a deposit-required booking, the dashboard shall flag it.

**Verification:**
- [ ] Host workflow: day view → seat a party → clear → slot frees; waitlist convert works; walk-in added.

**parallel:** false

- [ ] **Step 1: Reservations dashboard** (timeline, seat/clear/turn, waitlist, deposits, walk-ins).

---

## Phase 4: Catering · Market · Wine Club · Sunday Supper

### Task 4.1: Market (retail) storefront — screen 9

**Why this matters:** The Market is a real revenue stream sharing the same cart as food, with finite stock that the oversell engine already protects. Bringing retail in-platform ends the Square fees on it and unifies it into the one order ledger the owner reconciles.

**Files:**
- Create: `src/Controllers/MarketController.php`, `templates/public/market.php` (product grid, filters/categories, stock indicators, add-to-cart, product detail; shares cart with food)

**Acceptance (EARS):**
- *Ubiquitous:* Market goods shall use the reservable-stock guard (Task 2.3) so finite items can't oversell.
- *State-driven:* While a Market item has `ships_dtc=false` or wine without confirmed shipping, the system shall offer pickup only (see Task 4.4).

**Verification:**
- [ ] Guest workflow: add a Market item + a food item to one cart → single checkout; out-of-stock item blocked.

**parallel:** true

- [ ] **Step 1: Market grid + detail + filters** (shared cart, stock indicators).

### Task 4.2: Catering — packages + lead-time + quote/deposit — screen 11

**Why this matters:** Catering is high-value and currently handled manually. Lead-time rules and a deposit flow let the restaurant accept and partially-secure catering online instead of trading emails, capturing bookings that fall through today.

**Files:**
- Create: `src/Controllers/CateringController.php`, `templates/public/catering.php` — three offerings (**Party Platters** 48-hr lead, **Boutique Catering by EAT** inquiry, **Meal Delivery by EAT** order-Thu/deliver-Mon); headcount, date/time, customization; quote tracked in **`catering_requests`** (acceptance spawns a `pending` order — C-V5-5); deposit via Stripe; platter catalog seeded from `docs/content/menus/catering-menu.md` (serves 12|24)

**Acceptance (EARS):**
- *State-driven:* While the requested date violates the lead-time rule, the system shall block submission and explain the minimum notice.
- *Event-driven:* When a catering deposit is required, the system shall take it via Stripe (pending/hold lifecycle).
- *Event-driven:* When the deposit clears, the system shall set `catering_requests.status = deposit_paid` and let the admin issue a **second payable balance order** for `quoted_total − deposit`; the request reaches `paid` only when both orders are `paid` (C-V7-3).

**Verification:**
- [ ] Guest workflow: pick a package + date inside lead-time → quote → deposit → confirmed; date too soon → blocked with message.
- [ ] **Catering platter data received (v6)** — seeded from `catering-menu.md`; boutique + meal-delivery copy captured.

**parallel:** true

- [ ] **Step 1: Catering flow** (packages, lead-time, quote/deposit).

### Task 4.3: Wine Club — signup (Stripe Billing) + member portal — screens 13, 14

**Why this matters:** Wine Club is recurring revenue the restaurant may bill ad-hoc today. Native Stripe subscriptions + a self-serve member portal (pause/cancel/payment method) means predictable MRR and members who manage themselves — but the migration of any existing members must avoid double-billing or accidental cancellation.

**Files:**
- Create: `src/Controllers/WineClubController.php`, `templates/public/{wineclub,wineclub-portal.php}` (perks + price; Stripe subscription checkout; portal: status, next billing, payment method, pause/cancel, history; age/compliance note) — program copy captured (v6): **$40/mo**, 3rd-Wednesday tasting 5–7 pm, two somm-selected bottles, waived corkage, 15% off the wall, somms April + Stacey
- Create: `src/Services/Subscriptions.php` (Stripe Billing; webhook `invoice.paid` / subscription lifecycle)

**Acceptance (EARS):**
- *Event-driven:* When a guest subscribes to a tier, the system shall create a Stripe subscription and reflect status via webhooks.
- *State-driven:* While a member pauses/cancels, the portal shall update Stripe and reflect next-billing accordingly.
- *Ubiquitous:* Wine Club shall display an age/compliance note; shipping follows the DTC gate (Task 4.4).

**Verification:**
- [ ] Guest workflow: join a tier → Stripe subscription created → portal shows next billing; pause → reflected.
- [ ] Existing-member migration is human-blocked (Q#7) — do not import/charge without confirmation.

**parallel:** false

- [ ] **Step 1: Subscriptions service** (Stripe Billing + lifecycle webhooks) — **net-new code; `backend-stripe` covers only PaymentIntent/refund, not Billing** (C-STRIPE-4).
- [ ] **Step 2: Signup + member portal screens.**

### Task 4.4: Alcohol (wine) DTC compliance gate — pickup-only default, per-state shipping

**Why this matters:** Shipping wine to the wrong state isn't a bug, it's illegal. This gate enforces pickup-only by default and only ever offers shipping to a state once licensing, carrier 21+ adult-signature, and tax registration are confirmed. It protects the client from a compliance violation the build could otherwise cause.

**Files:**
- Create: `src/Services/AlcoholCompliance.php` (per-product `ships_dtc` + allowed-states; pickup-only default; age confirmation at purchase)
- Modify: `templates/public/{market,wineclub}.php`, `src/Services/Cart.php` (suppress wine shipping unless state confirmed)

**Acceptance (EARS):**
- *State-driven:* While DTC wine shipping is not legally confirmed for a ship-to state, the system shall not offer wine shipping to that state (pickup only).
- *Event-driven:* When wine is purchased, the system shall require age confirmation; shipped wine shall require carrier adult-signature (21+).

**Verification:**
- [ ] Guest workflow: wine in cart → only pickup offered; no ship-to-state selectable while gated.
- [ ] Wine shipping legality is human-blocked (Q#1, Q#2) — shipping stays off until confirmed per-state.

**parallel:** false

- [ ] **Step 1: Compliance service** (`ships_dtc`/allowed-states, age confirmation).
- [ ] **Step 2: Enforce pickup-only in Market + Wine Club + cart** until per-state confirmation.

### Task 4.5: Sunday Supper — ticketed events — screen 15

**Why this matters:** Sunday Suppers are capacity-limited ticketed events — prepay secures the seat and the atomic claim prevents overselling a 20-seat dinner to 25 guests. It turns a manual RSVP into a paid, self-managing event funnel.

**Files:**
- Create: `src/Controllers/SupperController.php`, `templates/public/supper.php` (upcoming suppers: date/theme/menu/price/seats-left; seat selection; prepay via Stripe; ticket confirmation)
- Reuse: `booking_resources` type=event + atomic claim

**Acceptance (EARS):**
- *State-driven:* While a supper's seats are exhausted, the system shall not sell another ticket (atomic claim).
- *Event-driven:* When a guest prepays, the system shall issue a ticket confirmation.
- *Event-driven:* When the restaurant cancels a supper, the system shall refund every ticket in full via Stripe and notify all ticket-holders (C-V7-4).

**Verification:**
- [ ] Guest workflow: buy the last 2 seats of an event → third buyer blocked; ticket confirmation sent.
- [ ] **Supper cadence captured (v6):** one Sunday a month, multi-course, all-AZ sourcing; exact per-event price/seats swap on receipt.

**parallel:** true

- [ ] **Step 1: Events screen + seat claim + prepay + ticket.**

---

## Phase 5: QR feedback → Google Reviews + service recovery

### Task 5.1: QR feedback page (compliant) + admin feedback dashboard — screens 16, 25

**Why this matters:** This is the growth engine — more Google Reviews lift local search, which drives the discovery the old brochure couldn't. It must be compliant: the Google link shows to *every* guest (no review-gating), while low scores additionally route to staff for service recovery before a bad experience becomes a public 1-star.

**Files:**
- Create: `src/Controllers/FeedbackController.php`, `templates/public/feedback.php` (screen 16: star rating + comment; **Google Review link shown to everyone**; table-coded via QR param; thank-you), built per `frontend-feedback-system`
- Create: `src/Controllers/Admin/FeedbackController.php`, `templates/admin/feedback.php` (screen 25: list + filters, rating-trend chart, response-rate metric, service-recovery alert queue, mark-resolved, respond)
- Create: `src/Services/Feedback.php` (low-score threshold → `feedback_alert`)

**Acceptance (EARS):**
- *Ubiquitous:* The feedback page shall present the Google Review link to every respondent regardless of rating (policy compliance).
- *Event-driven:* When a rating is at/below the low-score threshold, the system shall create a staff alert and flag it in the dashboard.
- *Ubiquitous:* The dashboard shall show response rate and rating trend over time.

**Verification:**
- [ ] Guest workflow: submit 5★ → Google link shown; submit 2★ → Google link still shown AND staff alert created.
- [ ] Manager workflow: low-score alert appears in service-recovery queue → respond → mark resolved; trend + response-rate render.

**parallel:** false

- [ ] **Step 1: QR feedback page** (compliant; table-coded — **escape the reflected `?table=` param and the comment field**, C-T1-4; rate-limited submit, C-T1-7; threshold alerting).
- [ ] **Step 2: Admin feedback dashboard** (trend, response rate, recovery queue).

---

## Phase 6: Admin depth — dashboard · customers · reports/recon · inventory · users

### Task 6.1: Dashboard (role-filtered KPIs) — screen 19

**Why this matters:** This is the one screen that answers "how did we do across every stream this week?" — the question no one can answer today without stitching exports. Role-filtering means Staff see operations without seeing financials.

**Files:**
- Create: `src/Controllers/Admin/DashboardController.php`, `templates/admin/dashboard.php` (KPIs: today's revenue across all streams, orders, covers, avg feedback; revenue chart; live order-queue snapshot; upcoming reservations; recent feedback; low-stock alerts)

**Acceptance (EARS):**
- *Ubiquitous:* The dashboard shall show one combined revenue figure across orders, market, gift cards, catering, events, and subscriptions for a selected period.
- *State-driven:* While the user's role is Staff, the dashboard shall hide financials.

**Verification:**
- [ ] Owner workflow: dashboard shows combined revenue + charts; Staff login shows ops-only.

**parallel:** false

- [ ] **Step 1: Role-filtered dashboard + combined-revenue query.**

### Task 6.2: Customers DB — screen 24

**Why this matters:** Owning the customer relationship is a core goal the fragmented stack denies. A unified profile (orders, reservations, feedback, Wine Club, lifetime value) keyed by email/phone is the asset the restaurant has never had.

**Files:**
- Create: `src/Controllers/Admin/CustomersController.php`, `templates/admin/customers.php` (search, profile, lifetime value, preferences, export; unified by email/phone)

**Acceptance (EARS):**
- *Ubiquitous:* The customer record shall unify orders, reservations, feedback, and Wine Club by email/phone.

**Verification:**
- [ ] Staff workflow: search a guest → see full cross-stream history + LTV; export works.

**parallel:** true

- [ ] **Step 1: Customer DB screen** (search, unified profile, export).

### Task 6.3: Reports & reconciliation (Owner-only) — screen 26

**Why this matters:** The owner reconciles a single Stripe payout against the order ledger instead of four tools. Snapshotted order rows make historical reports stable; the tax report supports the bookkeeper. This closes the loop on "one revenue number."

**Files:**
- Create: `src/Controllers/Admin/ReportsController.php`, `templates/admin/reports.php` (sales by stream/period, tax report, Stripe payout reconciliation, refunds, export; **Owner-only**)
- Create: `src/Services/Reconciliation.php` (ledger vs Stripe payouts)

**Acceptance (EARS):**
- *State-driven:* While the user is not Owner, the system shall not render Reports.
- *Ubiquitous:* Reconciliation shall match the order/payment ledger against Stripe payouts; historical reports shall read snapshotted values (never live prices).

**Verification:**
- [ ] Owner workflow: run a period report → totals match Stripe payout; tax report splits prepared-food vs retail; Staff/Manager → 403.

**parallel:** false

- [ ] **Step 1: Reports + reconciliation service** (snapshot-based; payout match; export).

### Task 6.4: Inventory + low-stock alerts + 86 propagation — screen 23

**Why this matters:** The kitchen needs to see reservable-vs-on-hand (netting holds) and get alerted before a finite good runs out, with 86'ing that instantly hides the item on the public menu. This is the operational safety valve for the oversell risk.

**Files:**
- Create: `src/Controllers/Admin/InventoryController.php`, `templates/admin/inventory.php` (stock levels, low-stock thresholds + alerts, adjust counts, 86 status, **reservable vs on-hand nets holds**)

**Acceptance (EARS):**
- *Event-driven:* When inventory crosses the low-stock threshold, the system shall alert and allow 86'ing that propagates to the public menu.
- *Ubiquitous:* The inventory view shall show reservable stock (on-hand minus active holds).

**Verification:**
- [ ] Manager workflow: drop stock below threshold → alert; 86 an item → public menu greys it; reservable reflects holds.

**parallel:** true

- [ ] **Step 1: Inventory screen** (thresholds, alerts, 86, reservable-vs-on-hand).

### Task 6.5: Users & roles + audit log — screen 27

**Why this matters:** The Owner manages who can do what and can review an audit trail of sensitive changes. This hardens the role model the whole admin depends on and gives accountability for money/settings changes.

**Files:**
- Create: `src/Controllers/Admin/UsersController.php`, `templates/admin/users.php` (staff list, role assignment Owner/Manager/Staff, invite, deactivate, **audit log**; Owner-only)

**Acceptance (EARS):**
- *State-driven:* While the user is not Owner, the system shall not render Users & roles.
- *Event-driven:* When a role/user/setting change occurs, the system shall record it in `audit_log`.

**Verification:**
- [ ] Owner workflow: invite a Manager, deactivate a user, view audit log; non-Owner → 403.

**parallel:** true

- [ ] **Step 1: Users & roles screen + audit-log view.**

### Task 6.6: Customer Account portal — screen 17 (history · reorder · favorites · addresses)

**Why this matters:** Owning the customer relationship is a core goal, and the spec promises guests a self-serve account (order history, reorder, saved favorites, addresses, Wine Club + reservation status). This is the *customer-facing* surface — distinct from the admin Customers DB (6.2) — and it was previously unbuilt by any task (the coverage map mis-mapped screen 17 to auth). Reorder is a real retention lever for a pickup-heavy restaurant.

**Files:**
- Create: `src/Controllers/AccountController.php`, `templates/public/account.php` (screen 17: order history + **reorder** [rehydrate a past order's snapshotted lines into the cart, re-priced against live products, flagging any 86'd/changed item], **saved favorites**, **saved addresses**, Wine Club status, upcoming/past reservations, profile)
- Create: `src/Models/Favorite.php`, `Address.php` (over `customer_favorites` / `customer_addresses` from Task 1.1)

**Acceptance (EARS):**
- *State-driven:* While logged in, the customer shall see their cross-stream history (orders, reservations, Wine Club) and manage favorites/addresses; guest checkout remains available without an account.
- *Event-driven:* When a customer taps **reorder**, the system shall rebuild the cart from the order's snapshotted lines, re-price against current products, and flag any item now 86'd or changed (C-V7-5).
- *Ubiquitous:* Saved addresses shall stay dormant for shipping until the DTC wine gate clears; a billing address may feed Stripe/AVS.

**Verification:**
- [ ] Customer workflow: log in → see history + Wine Club + reservations; add a favorite; reorder a past order → cart rehydrates, a since-86'd item is flagged.
- [ ] Guest with no account still checks out (no regression).

**parallel:** true

- [ ] **Step 1: Account portal** (history, reorder, favorites, addresses) over the Task 1.1 tables.

---

## Phase 7: Cutover + pre-launch

### Task 7.1: SEO surface + JSON-LD + analytics + Google Business

**Why this matters:** Cutover can silently tank rankings. Mapping and 301-redirecting every indexed old URL, preserving the Google Business Profile + reviews, and emitting valid structured data protect the local-search position the brochure built — the discovery channel the new site depends on.

**Files:**
- Create: `public/sitemap.xml` (generated), `public/robots.txt`
- Create: `config/redirects.php` + `.htaccess` 301 map (every indexed Woo/Square/Yelp URL → new equivalent; no blanket-to-home) per `infra-htaccess` — **include the live blog post URLs** (→ the new CMS posts) and apply the client's Q15 decision on **Amelia's Kiln + EAT by Stacey Weber** links (link-out vs omit) so those entry points aren't orphaned (C-V7-10/11)
- Create: JSON-LD partials (Restaurant + Menu); analytics wiring (`client-analytics`, GA4/Tag Manager from Settings)

**Acceptance (EARS):**
- *Ubiquitous:* Every indexed incumbent URL shall 301 to its new equivalent (mapped, not blanket).
- *Ubiquitous:* The site shall emit valid Restaurant + Menu JSON-LD and a current sitemap.

**Verification:**
- [ ] SEO workflow: crawl old URL list → each 301s to the right new page; rich-results test passes on menu.
- [ ] Google Business Profile URL updated; reviews preserved (`client-seo-sao`).

**parallel:** false

- [ ] **Step 1: 301 map** from the indexed-URL inventory.
- [ ] **Step 2: JSON-LD + sitemap/robots + analytics.**

### Task 7.2: Platform cutover runbook + gift-card balance handling (money-touching)

**Why this matters:** This is the dangerous part — replacing tools real customers already use. Square gift-card balances are real money owed; a silent hard-cut would strand guests at the counter. The runbook forces an explicit honor/parallel/hard-cut decision per money-touching item and a dated, reversible switchover.

**Files:**
- Create: `docs/runbooks/cutover.md` (dated runbook per the inline platform-cutover methodology — not an installed skill: final export → import + reconcile totals exactly → deploy w/ backup → flip → 301s on → smoke-test money paths → comms → watch 24–48h, rollback ready)
- Create: `src/Services/GiftCardImport.php` (import Square balances into `gift_cards` if honor-by-import chosen)

**Acceptance (EARS):**
- *Ubiquitous:* Every incumbent money balance shall have a documented honor/parallel/hard-cut decision before cutover.
- *Event-driven:* When a customer presents a pre-existing Square gift card after cutover, the system shall honor it per the chosen strategy.
- *Event-driven:* When cutover completes, affected customers shall have been notified.

**Verification:**
- [ ] Reconciliation: imported gift-card balances total exactly the Square export.
- [ ] Cutover dry-run: buy+redeem gift card, place+pay order, book reservation all pass on staging before flip.
- [ ] Gift-card strategy + Square export are human-blocked (Q#4); Woo data + existing subscriptions (Q#6, Q#7) decided before flip.

**parallel:** false

- [ ] **Step 1: Cutover runbook** (dated; money-path smoke list; rollback) — **post-launch DB issues use forward-fix migrations + Stripe reconciliation, NOT a drop-and-reimport restore** (would destroy real orders/payments); pull the pre-cutover backup off-server and dry-run a restore first (C-MISC-10).
- [ ] **Step 2: Gift-card import/reconcile** (if honor-by-import) — blocked on Q#4 export.
- [ ] **Step 3: WooCommerce data decision** (carry over vs fresh — Q#6) and existing Wine Club migration (Q#7). If importing: WP password hashes can't carry — set `must_reset_password` + forced-reset flow; download all product media before WP teardown, route through `client-image-optimization` (C-MIG-6).

### Task 7.3: Production-readiness audit + performance + E2E smoke + security pass

**Why this matters:** Before a live restaurant depends on this, the 63-item production-readiness checklist, full security headers/CSP/.htaccess hardening, image optimization, and Playwright smoke on the money/booking paths are what separate "demo" from "trusted with payroll." This is the gate before the first prod deploy.

**Files:**
- Create: `e2e/` Playwright specs (critical flows: order+pay, gift card buy+redeem, reservation+deposit, feedback, subscription) per `quality-playwright-e2e`
- Create: `docs/runbooks/production-readiness.md` (the 63-item checklist results)
- Modify: `public/.htaccess`, `templates/partials/head.php` (full CSP/security headers per `security-hardening`); image optimization pass (`client-image-optimization`)

**Acceptance (EARS):**
- *Ubiquitous:* The platform shall pass `quality-production-readiness` (63 items) before the first prod deploy.
- *Ubiquitous:* The platform shall ship full security headers/CSP, locked uploads, and SAQ-A confirmed; Lighthouse Perf ≥ 90 / A11y ≥ 95 on the top 5 pages.

**Verification:**
- [ ] Quality workflow: `php audit/conventions.php --quiet` clean repo-wide; Playwright smoke green on all money/booking flows.
- [ ] Production-readiness checklist fully checked; CSP present; uploads locked; Lighthouse thresholds met.
- [ ] Apple/Google Pay: `/.well-known/apple-developer-merchantid-domain-association` is served and the prod domain is registered in Stripe → wallet buttons render on checkout (C-V7-14).
- [ ] Scheduled daily DB backup verified writing off-server; a restore dry-run succeeds (C-V7-10).

**parallel:** false

- [ ] **Step 1: Playwright smoke** on critical flows — incl. Stripe **test-card matrix** (decline 4000…0002, 3DS, insufficient funds), double-click-pay prevention, back-button-after-pay (no recharge), expired-session (C-QUAL-8).
- [ ] **Step 2: Hand-authored CSP** (allowlist Stripe `frame-src js.stripe.com`, GTM/GA4, Maps, Formspree, reCAPTCHA, fonts) + `Permissions-Policy: payment=(self)` + `X-Content-Type-Options`/`X-Frame-Options`/`Referrer-Policy` + HTTPS-force + HSTS (post-verify) + `Options -Indexes` + explicit `.htaccess` deny-set + SRI on CDN scripts (document Stripe.js/GTM exceptions) + upload lockdown (C-T1-8).
- [ ] **Step 3: Custom 404/500 pages + `display_errors=Off` in prod; performance/image pass (CWV/query-budget targets); run the 63-item checklist** (C-QUAL-8).

### Task 7.4: Client handoff — training + user manual

**Why this matters:** The client operates without a developer. A user manual and training on the self-service Settings, CMS, menu/86, order queue, reservations, and reports are what make the platform sustainable after handoff instead of generating support calls.

**Files:**
- Create: `docs/handoff/user-manual.md` (per `client-handoff`: how to run each admin surface)
- Create: `docs/handoff/training-checklist.md`

**Acceptance (EARS):**
- *Ubiquitous:* The handoff shall document every operator workflow (Settings, CMS/media, menu+86, order queue, reservations/table mgmt, feedback recovery, reports).

**Verification:**
- [ ] Handoff workflow: Stacey can, from the manual alone, change a price, 86 an item, edit hero copy, rotate a Stripe key, and read a revenue report.

**parallel:** false

- [ ] **Step 1: `USER-MANUAL.md` + training checklist + quick-reference card + handoff email** (per `client-handoff` — C-HAND-9); share GA viewer access + confirm form-recipient email.
- [ ] **Step 2: Training session** (human-blocked: scheduled with client).

---

## Self-review (completed before presenting)

- [x] Every task has `**Why this matters:**` (business justification; mechanical tasks use the escape hatch).
- [x] Every task has a file-touch list with paths.
- [x] Every task has EARS acceptance + user-workflow verification.
- [x] Human-blocked items marked explicitly (Phase H + inline on blocked tasks; cross-referenced to spec questions Q#1–13).
- [x] Phases group related tasks; the largest (Phase 1, Phase 6) are split so no phase exceeds ~6–7 tasks; all **34** screens (incl. 2b Purveyors, 18a Careers, 18b Staff login, and the now-built 17 Account portal) are covered.
- [x] No `TODO`/`TBD` placeholders.
- [x] Covers everything the spec promised (6 systems, 34 screens, cutover, wine DTC, concurrency, self-service, RTM data-model) plus the v7 gap-closure items, and nothing extra.
- [x] `REQUIRED SUB-SKILL: workflow-execute` header present; Tech Stack + Testing approach filled.

## Screen-coverage map (RTM forward check — every spec screen → a task)

| Screen | Task | Screen | Task |
|---|---|---|---|
| 1 Home | 1.6 | 17 Account | **6.6** (auth in 1.2) |
| 2 Our Story | 1.6 | 18 Auth | 1.2 |
| 3 Location | 1.6 | 19 Dashboard | 6.1 |
| 4 Menu browse | 2.1 | 20 Order queue | 2.5 |
| 5 Item/modifiers | 2.1 | 21 Reservations mgmt | 3.3 |
| 6 Cart | 2.2 | 22 Menu & pricing | 2.1 |
| 7 Checkout | 2.4 | 23 Inventory | 6.4 |
| 8 Order confirm/status | 2.5 | 24 Customers | 6.2 |
| 9 Market | 4.1 | 25 Feedback dash | 5.1 |
| 10 Gift cards | 2.5 | 26 Reports/recon | 6.3 |
| 11 Catering | 4.2 | 27 Users & roles | 6.5 |
| 12 Reservations book | 3.2 | 28 Settings/Integrations | 1.3 |
| 13 Wine Club signup | 4.3 | 29 Content (CMS) | 1.6 |
| 14 Wine Club portal | 4.3 | 30 Media library | 1.6 |
| 15 Sunday Supper | 4.5 | 31 Team members | 1.6 |
| 16 QR feedback | 5.1 | 2b Purveyors | 1.6 |
| 18a Careers | 1.6 | 18b Staff login | 1.2 |

---

## User review gate

Plan written and committed to `docs/superpowers/plans/2026-06-03-amelias-platform-plan.md`. Read it and let me know if any task needs adjustment before I (or `workflow-execute`) start running through it. Note in particular: (a) phase ordering ships the revenue path first and cutover last; (b) Phase H gathers the human-blocked client questions so execution never invents an answer; (c) all 34 screens are mapped in the coverage table above; (d) the v7 gap-closure pass added the Account portal (Task 6.6), gift-card/promo/catering-balance/refund-restore money flows, ASAP throttling, send-once notifications, scheduled backups, a Terms page, and blog/Kiln scope handling.
