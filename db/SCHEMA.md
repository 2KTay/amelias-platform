# Database Schema — Amelia's by EAT

Single source of truth for the platform's data. MySQL/InnoDB, `utf8mb4_unicode_ci`,
timestamps stored UTC (connection `time_zone='+00:00'`, displayed America/Phoenix).
**Money = integer cents (BIGINT). Tax rates = integer basis points.**

Apply with `php db/migrate.php`; seed demo/menu content with `php db/seed.php`.

## Migrations → tables

| File | Tables |
|---|---|
| 0002 customers_users | `customers`, `customer_favorites`, `customer_addresses`, `users`, `audit_log`, `login_attempts`, `rate_limits` |
| 0003 catalog | `categories`, `products`, `product_category_map`, `product_variants`, `modifier_groups`, `modifiers`, `product_modifier_map`, `dietary_tags`, `product_dietary_map`, `inventory` |
| 0004 orders | `orders`, `order_items`, `order_item_modifiers`, `order_tax_lines`, `promotions`, `order_discounts`, `tax_rates`, `pickup_slots`, `inventory_holds`, `catering_requests` |
| 0005 giftcards | `gift_cards`, `gift_card_transactions`, `product_shippable_states` |
| 0006 bookings | `booking_resources`, `tables`, `booking_slots`, `bookings`, `waitlist` |
| 0007 subscriptions | `subscription_tiers`, `subscriptions` |
| 0008 feedback | `feedback`, `feedback_alerts` |
| 0009 cms | `content_blocks`, `pages`, `media`, `team_members` |
| 0010 settings_payments | `settings`, `payments`, `webhook_events` |
| 0011 notifications | `notification_queue`, `notification_log` |

## Core invariants (enforced by design)

- **Snapshotting:** `order_items` store `name_snapshot`, `unit_price_cents`,
  `tax_treatment`, `tax_amount_cents`; `order_item_modifiers` store
  `price_delta_cents`; `order_tax_lines` store `rate_bps`. Past orders never
  re-total from live product prices.
- **Oversell safety:** capacity counters (`pickup_slots.booked_count`,
  `booking_slots.booked_count`) and `gift_cards.current_balance` are mutated only
  via guarded atomic `UPDATE … WHERE counter + n <= capacity` / `>= :amt` with an
  affected-rows check. `inventory_holds` (status active→converted/released,
  `expires_at`) back the pending-order lifecycle.
- **Money source of truth:** `payments` is a real webhook-fed ledger (not a view)
  for stable reconciliation; `webhook_events.event_id` is unique for idempotency.
- **Gift cards:** `gift_card_transactions` is the append-only ledger;
  `gift_cards.current_balance` is a reconciled cache.
- **Send-once:** `notification_log` unique `(entity_type, entity_id, template, channel)`.
- **Secrets:** `settings.is_secret=1` rows hold AES-GCM ciphertext (key in env).

## RTM — backward trace (field → requirement)

| Field / table | Requirement it serves |
|---|---|
| `orders.status` (12-value enum incl. comped/voided/disputed/expired) | full order lifecycle; admin comp/void; dispute handling |
| `orders.kind` + `catering_request_id` | catering deposit + balance as two payable orders (C-V7-3) |
| `orders.public_token` | tokenized guest status/cancel link (C-V7-13) |
| `products.day_part` + `available_from/to` | browse-anytime / order-in-window gating (C-V7-8) |
| `products.tax_category` + `tax_rates.rate_bps` | per-category AZ TPT; gift cards non-taxable (C-TAX-5) |
| `products.ships_dtc` + `product_shippable_states` | wine DTC per-state gate, pickup-only default (Q#1/#2) |
| `inventory_holds` + `pickup_slots.booked_count` | oversell-safe pending lifecycle + capacity (C-T1-2) |
| `bookings.table_id` (NULL until seated) + `tables` | capacity-booked, table-assigned-on-seating (C-V5-1) |
| `promotions` + `order_discounts.discount_amount_cents` | promo codes, snapshotted pre-tax discount (C-V7-6) |
| `gift_cards.current_balance` + `gift_card_transactions` | atomic balance claim + reconciled ledger (C-V7-1) |
| `customers.notify_channel` + `notification_log` | customer-chosen channel, send-once (C-V5, C-V7-9) |
| `audit_log` | accountability for settings/role/comp/void changes (C-DATA-3) |
| `login_attempts` / `rate_limits` | lockout + rate limiting (C-T1-7) |

## Owner report questions → answerable in SQL

- **Revenue by stream / period:** `payments` (type='payment') joined to
  `orders.kind`/product `type`, grouped by period; `subscriptions` for MRR.
- **Tax report:** `order_tax_lines` grouped by `tax_category` over a date range
  (snapshotted rates, so historically stable).
- **Stripe payout reconciliation:** `payments.payout_id` vs Stripe payout totals.
- **Lifetime value:** computed aggregate `SUM(total_cents)` over paid `orders`
  per `customer_id` (not a stored column — C-DATA-3).
- **Reservable stock:** `inventory.stock − SUM(active inventory_holds.quantity)`.
