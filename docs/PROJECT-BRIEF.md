# Project Brief — Amelia's by EAT

> Generated via the `client-onboarding` skill on 2026-06-02 from a scrape of
> https://ameliasaz.com/ + the engagement brief. Square menu page
> (amelias-105290.square.site) is JS-rendered and was not captured; menu
> inventory is an OPEN ITEM (see below).

## Client
- **Name / Business:** Amelia's by EAT
- **Concept:** Conscious, made-from-scratch, all-day eating place **+ retail market**
- **Founder / Chef:** Stacey Weber
- **Current site:** https://ameliasaz.com/ (WordPress-style brochure; ordering/menu/gift cards offloaded to Square; reservations offloaded to Yelp)
- **Location:** 8240 N Hayden Road, Suite B-105, Scottsdale, AZ 85258
- **Hours:** Sun 7am–3pm · Mon–Thu 7am–8pm · Fri–Sat 7am–close
- **Phone:** (602) 499-5195
- **Instagram:** @ameliasbyeat
- **Taglines:** "When you eat well, you feel good." / "The neighborhood just got better."

## Project Scope
- **Type:** Redesign + platform build (replace brochure site + unify the bolted-on third-party tools)
- **Stack:** Aslan default — PHP 8+, MySQL, GoDaddy/cPanel, GitHub Actions FTP deploy (confirmed by Renato)
- **Project type:** `internal-tool` (full workflow gate; public pages still bespoke)
- **Spec approach:** Full platform up front (all 6 systems in one spec)

## The 6 systems (from the engagement brief)
1. **Website modernization** — bespoke, appetizing, mobile-first; CMS for menu/promos; SEO + Google Business
2. **E-commerce** — online food ordering + retail market (merch, gift cards, specialty/wine); cart, customization, scheduling, accounts, order history, inventory
3. **Reservations** — real-time table availability, party size, confirmations, SMS reminders, modify/cancel, staff table mgmt, private events, waitlist
4. **Payments** — gateway (cards + digital wallets), PCI compliance, deposits, refunds, receipts, tax by category, optional POS integration
5. **QR feedback** — table-specific dynamic QR → mobile feedback → route happy guests to Google Reviews; internal dashboard; staff service-recovery alerts
6. **Admin dashboard** — unified orders/reservations/feedback metrics, sales reporting, customer DB, menu/pricing mgmt, inventory, financial reconciliation, staff roles

## Current third-party surfaces to absorb / decide on
| Surface | Today | Decision needed |
|---|---|---|
| Online ordering | Square (amelias-105290.square.site) | Replace native vs keep Square |
| In-restaurant POS | (assumed Square POS) | Keep vs integrate |
| Gift cards | Square | Native vs Square |
| Reservations | Yelp | Replace native (brief wants native) |
| Retail "Market" | ? | Native e-commerce |
| Wine Club | ? subscription | Model in scope? |
| Catering | order form | Native ordering flow |
| Sunday Supper | events | Ticketed/booked events? |

## Open questions (to resolve in brainstorm)
- [ ] Payments/ordering architecture: replace Square online ordering with native PHP + Stripe, or integrate with Square?
- [ ] Does the restaurant keep Square (or other) POS for in-person, and must online + in-person inventory stay in sync?
- [ ] SMS provider for reservation reminders (Twilio?) — budget/account
- [ ] Brand assets: exact colors, fonts, logo files (not captured from scrape — see BRAND-GUIDE.md)
- [ ] Full menu data (Day, PM, Happy Hour, Catering) with prices, modifiers, dietary tags
- [ ] Wine Club + Sunday Supper business models (subscription? ticketed events?)
- [ ] Delivery: in-house, or third-party (DoorDash/Uber Eats) — pickup-only to start?
- [ ] cPanel/GoDaddy hosting account + DB + FTP credentials access (Tim)

## Reference
- Engagement brief (provided by Renato, 2026-06-02)
- BRAND-GUIDE.md (sibling file)
