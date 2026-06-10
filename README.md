# Amelia's by EAT — Platform

Restaurant digital platform for Amelia's by EAT (ameliasaz.com): website,
online ordering/menu, reservations, payments, QR feedback → Google Reviews,
and an admin dashboard. Built on PHP 8+, MySQL/MariaDB, deployed on
GoDaddy/cPanel.

## 🔗 Live (staging / parity)

**https://TBD-new-parity-link/** _(placeholder — update with the new parity URL)_

This is the active parity environment — the latest build of the platform.

## Stack

- **Backend:** PHP 8.3, custom router/front-controller (no framework), PDO/MySQL
- **Frontend:** server-rendered PHP templates + vanilla JS, token-based CSS architecture
- **Integrations:** Stripe (payments), SendGrid (email), Twilio (SMS)
- **Hosting:** cPanel / GoDaddy; document root → `public/`

## Layout

| Path | What |
| --- | --- |
| `public/` | Web root: front controller (`index.php`), assets |
| `src/` | App code (Controllers, Http, Services, Support, Database) |
| `config/` | App config + route table |
| `templates/` | Public, admin, and email views |
| `db/` | Migrations + seeds |
| `docs/` | Specs, plans, runbooks |

## Local development

Copy `.env.example` → `.env`, fill in values, then run migrations/seeds and
serve `public/` with any PHP 8 server, e.g.:

```bash
php -S 127.0.0.1:8000 -t public public/index.php
```
