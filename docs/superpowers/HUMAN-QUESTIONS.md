# Human Questions — Build Blockers Log

> Consolidated, actionable list of every client/owner answer the platform build
> needs. The **spec** (`specs/2026-06-02-amelias-platform-design.md`) remains the
> canonical decision log — this file is the single place Renato can scan what's
> outstanding and what shipped as a safe default in the meantime.
>
> **Build rule:** no question stops the build. Every gated capability ships
> wired-but-disabled behind a feature flag (`config/app.php` → `features`,
> overridable via `.env`). Answering a question = flip the flag (+ any data step).

| Legend |  |
|---|---|
| 🔴 | Legality / real-money blocker — must be answered before that capability goes live |
| 🟡 | Policy / scope — shipped with a documented default, refine on answer |
| flag | env / Settings toggle that turns the capability on |

---

## 🔴 Q#1 — Wine DTC shipping legality (per ship-to state)
- **Affects:** Task 4.4 (alcohol compliance gate), Market & Wine Club shipping.
- **Default shipped:** wine is **pickup-only**; no ship-to-state is selectable.
- **Flips on:** `FEATURE_WINE_DTC=true` + per-state allow-list in
  `product_shippable_states`, once licensing + carrier 21+ adult-signature + tax
  registration are confirmed for each state.

## 🔴 Q#2 — Wine carrier / age-verification details
- **Affects:** Task 4.4. **Default:** age confirmation required at purchase;
  shipping stays off (see Q#1). **Flips on:** same gate as Q#1.

## 🟡 Q#3 — Marketing email/SMS sends (consent + list source)
- **Affects:** Task 1.4 (marketing vs transactional split).
- **Default:** only **transactional** notifications send; marketing is built but
  dormant (CAN-SPAM unsubscribe + physical address wired). **Flips on:** confirmed
  opt-in list + sender identity.

## 🔴 Q#4 — Square gift-card balances (export + honor strategy)
- **Affects:** Task 2.5 (gift cards), Task 7.2 (cutover).
- **Default:** native gift cards work; **no Square balances imported**.
  `FEATURE_GIFTCARD_IMPORT=false`. **Flips on:** Square export received + an
  explicit honor/parallel/hard-cut decision; import reconciles to the export total.

## 🟡 Q#5 — POS sync (does the kitchen POS need a feed?)
- **Affects:** order queue integration. **Default:** orders live only in this
  platform's queue (screen 20); no POS push. **Flips on:** POS vendor/API named.

## 🟡 Q#6 — WooCommerce data migration (carry over vs fresh start)
- **Affects:** Task 7.2. **Default:** fresh catalog (seeded from menus); no Woo
  import. **Flips on:** decision + export; WP password hashes can't carry →
  `must_reset_password` + forced reset, product media downloaded pre-teardown.

## 🔴 Q#7 — Existing Wine Club members migration
- **Affects:** Task 4.3 (Stripe Billing). **Default:** new subscriptions only; **no
  existing member imported or charged** (avoids double-billing). **Flips on:**
  member list + explicit migration sign-off.

## 🔴 Q#8 — AZ TPT tax approach + rates
- **Affects:** Task 2.4 (checkout tax). **Default:** per-category `tax_rates`
  table seeded with placeholder AZ TPT rates; the **Stripe Tax adapter seam** is
  built. **Flips on:** bookkeeper confirms rates, or `FEATURE_STRIPE_TAX=true`
  commits to Stripe Tax (then `order_tax_lines` snapshots Stripe amounts).

## 🟡 Q#9 — Twilio A2P 10DLC (SMS) registration
- **Affects:** Task 1.4. **Default:** **email only**; SMS adapter built behind
  `FEATURE_SMS=false`. **Flips on:** A2P approval → `FEATURE_SMS=true` (zero code
  change; email remains the fallback channel).

## 🔴 Q#10 — cPanel / GoDaddy credentials + host details
- **Affects:** Task 0.3 (deploy), DB provisioning. **Default:** deploy workflow +
  backup cron are committed; **GH Actions secrets unset**, DB not provisioned.
  **Flips on:** credentials → set FTP secrets, provision least-privilege DB user,
  pin host + DocumentRoot.
- **Staging note:** the GitHub Actions auto-deploy to `parityrfp.com/cs/amelias`
  is **live and green** (FTP creds from the infra-ftp-deploy skill); the home page
  works. DB-backed pages (menu, admin, ordering) show the **maintenance page (503)**
  until a **MySQL DB is provisioned on parityrfp** and its creds added as workflow
  secrets `STAGING_DB_NAME/USER/PASS`. parityrfp's cPanel/MySQL API creds aren't in
  the skills (only FTP), so the staging DB is blocked on that access.

## 🟡 Q#11 — Delivery (link out to a 3rd party, or none?)
- **Affects:** ordering fulfillment options. **Default:** **pickup only**; schema
  reserves room for a `delivery` fulfillment type without migration. **Flips on:**
  delivery partner named (link-out) or in-house decision.

## 🟡 Q#12 — Menu data gaps (new items, full dietary/allergen coverage)
- **Affects:** Task 2.1, 4.x. **Default:** catalog seeded from
  `docs/content/menus/{day,pm,happy-hour,catering}-menu.md`; Wine Club $40/mo +
  Sunday Supper monthly cadence captured. **Flips on:** any new items / complete
  dietary tags swap in on receipt.

## 🟡 Q#13a — Display font "Audrey" licensing
- **Affects:** Task 0.4, 1.6. **Default:** **Fraunces** stand-in (approved). **Flips
  on:** license confirmed → swap `--font-display-stack` in tokens.css.

## 🟡 Q#13b — Photography — RESOLVED (v6)
- Real photo set received in `images/` (high-key daytime + B&W heritage). No block.

## 🟡 Q#14 — Reservation deposit / no-show money policy
- **Affects:** Task 3.2, 4.5. **Default:** deposit required at large-party
  threshold; refundable before a **48h** cutoff, forfeit after / on no-show;
  restaurant-cancel refunds in full. **Flips on:** owner confirms thresholds.

## 🟡 Q#15 — Amelia's Kiln + EAT by Stacey Weber scope (link-out vs omit)
- **Affects:** Task 7.1 (301 map), nav/footer. **Default:** not linked (omitted)
  until decided so no entry point is orphaned. **Flips on:** link-out vs omit decision.

## 🟡 Q#16 — Tipping (on pickup orders? default %?)
- **Affects:** Task 2.2 cart tip. **Default:** optional tip field present, no
  preset enforced. **Flips on:** owner's preferred presets / on-off.

---

### Status ledger (update as answers arrive)
| Q | Status | Answer / Date |
|---|---|---|
| 1 | ⏳ open | |
| 2 | ⏳ open | |
| 3 | ⏳ open | |
| 4 | ⏳ open | |
| 5 | ⏳ open | |
| 6 | ⏳ open | |
| 7 | ⏳ open | |
| 8 | ⏳ open | |
| 9 | ⏳ open | |
| 10 | ⏳ open | |
| 11 | ⏳ open | |
| 12 | 🟢 substantially received (v6) | menus + program cadence seeded |
| 13a | ⏳ open | Fraunces stand-in in use |
| 13b | ✅ resolved | photos received |
| 14 | 🟡 default assumed | 48h cutoff |
| 15 | ⏳ open | |
| 16 | 🟡 default assumed | optional tip, no preset |
