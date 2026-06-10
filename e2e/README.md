# E2E Smoke Tests — Amelia's by EAT

Playwright specs for the critical money/booking flows (Task 7.3). They assert
each page renders and the happy path runs **up to the Stripe step**. The Stripe
card-entry + webhook portions are gated so the suite is useful even before a live
Stripe test environment exists.

## Specs

| File | Flow |
|---|---|
| `order.spec.js` | Menu → item → cart → checkout → Stripe Payment Element |
| `reservation.spec.js` | Reserve → availability → large-party deposit (Stripe) |
| `giftcard.spec.js` | Gift-cards page → purchase → Stripe (tax-exempt) |
| `feedback.spec.js` | QR feedback renders; Google link shown to all; `?table=` escaped |

`fixtures.js` holds the **Stripe test-card matrix** (success, declines, 3DS,
dispute) and the shared expiry/CVC/ZIP. `playwright.config.js` sets the base URL
and the chromium + mobile projects.

## Running

> **Note:** dependencies are **declared** in `package.json` but NOT installed in
> this repo. Install before first run.

```bash
# Install (one time)
npm install
npx playwright install        # download browser binaries

# Run against local dev (default base URL http://localhost:8080)
npm run test:e2e

# Run against staging
E2E_BASE_URL=https://parityrfp.com/cs/amelias npm run test:e2e

# Enable the Stripe-step assertions (needs Stripe TEST keys in admin Settings
# on the target env). Without this, those steps are skipped.
E2E_STRIPE=1 E2E_BASE_URL=https://parityrfp.com/cs/amelias npm run test:e2e

# Headed / debug + HTML report
npm run test:e2e:headed
npm run test:e2e:report
```

## Environment variables

| Var | Default | Purpose |
|---|---|---|
| `E2E_BASE_URL` | `http://localhost:8080` | App base URL (supports the subdir mount) |
| `E2E_STRIPE` | unset | `1` enables the Stripe-step assertions (test keys required) |
| `CI` | unset | enables retries + HTML report, forbids `test.only` |

## Stripe test-card matrix (test mode only — never live)

From <https://stripe.com/docs/testing>. Any future expiry, any 3-digit CVC, any ZIP.

| Scenario | Card | Expected platform behavior |
|---|---|---|
| Success (Visa) | `4242 4242 4242 4242` | order → `paid` via webhook; confirmation + email |
| Success (MC) | `5555 5555 5555 4444` | same |
| Generic decline | `4000 0000 0000 0002` | order stays `pending`; hold honored; graceful retry |
| Insufficient funds | `4000 0000 0000 9995` | decline message; no oversell |
| Lost card | `4000 0000 0000 9987` | decline |
| Expired card | `4000 0000 0000 0069` | decline |
| Incorrect CVC | `4000 0000 0000 0127` | decline |
| 3DS required | `4000 0027 6000 3184` | 3DS challenge appears; completes on auth |
| 3DS supported | `4000 0000 0000 3220` | optional challenge |
| Dispute (fraud) | `4000 0000 0000 0259` | charges, then `charge.dispute.created` → order `disputed` + staff alert |

### Edge cases to exercise once Stripe is live (documented in the specs as `test.skip`)

- Double-click-pay prevention (one charge, not two).
- Back-button after pay (no re-charge).
- Expired checkout session (graceful re-prompt).
- Slot/stock taken between view and submit (atomic claim rejects → re-prompt).
