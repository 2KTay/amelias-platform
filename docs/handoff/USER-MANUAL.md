# Amelia's by EAT — Owner & Staff User Manual

> Everything you need to run the platform without a developer. Each section is a
> task you'll actually do, with where to click and what to expect.
>
> **Admin lives at:** `https://ameliasaz.com/admin`
> **Roles:** **Owner** (everything incl. money + users + settings) ·
> **Manager** (operations + menu + reservations + feedback, no settings/users) ·
> **Staff** (order queue + reservations only; no financials).

---

## 0. Signing in

1. Go to `https://ameliasaz.com/admin` → the staff login screen.
2. Enter your email + password. (5 wrong tries in 15 minutes locks you out for a
   bit — that's the security lockout, not a bug. Wait and retry.)
3. You land on the **Dashboard**. The nav on the left shows only the screens your
   role can use.

> Forgot your password? Use the reset link, or ask the Owner to send a new invite
> from **Users & roles**.

---

## 1. Settings & key rotation (Owner only) — `/admin/settings`

This is where the business runs itself — no engineer needed to change a key.

**Integration cards** (Stripe, email/SendGrid, Twilio SMS, Google, reCAPTCHA,
social):
- Each card has a field for the key/secret, a **Test connection** button, and a
  status badge (green = working).
- Keys are stored **encrypted**. After saving, a key shows masked (`••••••3xQ`).
  Click reveal to see it briefly.

**To rotate a key (e.g. a new Stripe secret):**
1. Open Settings → the Stripe card.
2. Paste the new key over the old one → **Save**.
3. Click **Test connection** → wait for the green badge.
4. Done. The change is logged (who + when) in the audit log.

> If a key is left blank, the platform falls back to the server's environment
> value. Once you set it here, your value wins.

**Business config** (same screen, lower section): open/close hours, AZ tax rates
by category, pickup-slot capacity, deposit threshold + amount, service fee,
holiday closures. Change a value → Save → the public site and checkout update
immediately.

> **Security:** never paste a secret into an email or chat. Only this screen. The
> public website never shows any secret.

---

## 2. Website content & media (CMS) — `/admin/content`, `/admin/media`, `/admin/team`

Edit the public site yourself — no deploy.

**Content** (`/admin/content`): hero headline, Our Story, hours blurb, footer,
per-page SEO title/description. Edit → Save → reload the public page to see it.

**Media** (`/admin/media`): upload photos. The system renames + stores them
safely and asks for **alt text** (a short description for accessibility/SEO —
please fill it in). Use uploaded images in content blocks.

**Team** (`/admin/team`): add/edit/reorder team members (name, role, bio, photo)
shown on Our Story.

**Blog posts:** create posts under Content (type = post). Published posts appear
at `/blog` and on `/blog/your-slug`.

> Tip: write SEO descriptions in plain, appetizing language — they're what shows
> up in Google.

---

## 3. Menu & 86'ing — `/admin/menu`

The kitchen drives the menu here; changes hit the public menu instantly.

**Change a price:** find the item → edit the price → **Save** (top sticky bar).
**Bulk price edit:** select multiple items → set new prices together.
**86 an item (sold out):** flip the **86 toggle**. The public menu immediately
greys it out as "Sold out" and blocks adding it to a cart. Un-toggle to bring it
back.
**Modifiers & dietary tags:** add option groups (e.g. "Choose a side"), mark
required vs optional, set dietary/allergen badges.
**Day-parts:** assign items to Day / PM / Happy Hour / Catering. Guests can browse
any day-part but can only **order** the one that's currently active.
**Photos:** attach an image from the media library.

> 86'ing is the fastest lever in the building — use it the moment something runs
> out so no guest orders what you can't make.

---

## 4. Order queue — `/admin/orders`

Run pickup orders in real time.

- Orders appear by status. A **sound + badge** fires on a new paid order.
- Move an order through **received → preparing → ready**. Marking **ready**
  automatically notifies the guest (email, and SMS once that's turned on).
- **Refund** (full or partial): restores stock/slot if the order hasn't been
  fulfilled yet. **Comp** (zero it out, with a reason — no card refund) and
  **Void** (cancel an unfulfilled order) are available to Owner/Manager and are
  logged in the audit trail.

> The confirmation a guest sees exists even if Stripe's notification lags — the
> order is real the moment it's paid. Trust the queue.

---

## 5. Reservations & table management — `/admin/reservations`

Replaces the Yelp host view.

- **Day view / timeline:** see today's bookings, deposits, and walk-ins.
- **Seat / clear / turn:** seating a party assigns them a physical table and frees
  capacity when they leave (held → seated → completed).
- **Waitlist:** when full, guests join the waitlist; offer a freed table and it
  rolls to the next person if unclaimed in the window.
- **Deposits:** large parties/events show a deposit status; unpaid deposits are
  flagged.
- **Walk-ins:** add a walk-in directly to the floor.

> Deposit refunds follow the policy: refundable before the 48-hour cutoff,
> forfeit after / on no-show; if the restaurant cancels, the guest is refunded in
> full.

---

## 6. Feedback & service recovery — `/admin/feedback`

Turn experiences into Google reviews and catch problems early.

- Every guest who scans the QR sees the **Google Review link** (we never hide it).
- A **low rating** also raises a **service-recovery alert** here so you can reach
  out before it becomes a public 1-star.
- The dashboard shows the **rating trend** and your **response rate**. Respond to
  an alert, then **mark resolved**.

---

## 7. Customers — `/admin/customers`

Search any guest (by email/phone) to see their full history across orders,
reservations, feedback, and Wine Club, plus lifetime value. Export the list when
you need it.

---

## 8. Reports & reconciliation (Owner only) — `/admin/reports`

One place for "how did we do?"

- **Sales by stream/period:** orders, market, gift cards, catering, events,
  subscriptions — one combined number.
- **Tax report:** splits prepared food vs retail for the bookkeeper.
- **Stripe payout reconciliation:** match the order/payment ledger against your
  Stripe payouts. Historical reports use the price that was charged at the time
  (they never re-total when you change a menu price later).
- **Export** for your accountant.

---

## 9. Inventory — `/admin/inventory`

Stock levels with low-stock alerts. The view shows **reservable** stock (on-hand
minus what's currently held in open carts), so you see what's truly sellable.
86 directly from here too.

---

## 10. Users & roles (Owner only) — `/admin/users`

Invite staff, set their role (Owner / Manager / Staff), deactivate someone who
leaves, and review the **audit log** of sensitive changes (settings, money
actions, role changes — who did what, when).

---

## Things that are intentionally off until we say go

Some features are built but switched off until a business decision/registration
lands. You don't need to do anything — we flip them on:

- **SMS notifications** (waiting on carrier registration; email works now).
- **Wine shipping** (legal per-state confirmation; pickup works now).
- **Old Square gift-card balances** (handled per the cutover plan).

If a customer asks about any of these, see the front-desk note in the quick
reference or call Renato.

---

## Who to call

- **Anything broken / money looks wrong / can't log in:** Renato (Aslan Advisors).
- **Routine changes (prices, hours, content, 86, reservations):** you've got this —
  see the sections above.
