# Amelia's by EAT — Platform

Restaurant digital platform for Amelia's by EAT (ameliasaz.com): website,
online ordering/menu, reservations, payments, QR feedback → Google Reviews,
and an admin dashboard. Built on PHP 8+, MySQL/MariaDB, deployed on
GoDaddy/cPanel.

## 🔗 GitHub Pages

**https://2ktay.github.io/amelias-platform/**

> ⚠️ Static landing page only. GitHub Pages cannot run PHP, so this is **not**
> the working ordering/menu/cart app — that requires PHP hosting (cPanel /
> staging). Use this link for a static preview of the project.

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
