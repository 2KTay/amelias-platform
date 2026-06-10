# Deploy & Rollback Runbook — Amelia's by EAT

> Push-button, reversible deploys. The client has no developer on staff, so a
> bad deploy must be a ~5-minute rollback, never a data-loss incident.

## One-time setup (HUMAN-BLOCKED on Q#10 — cPanel/GoDaddy credentials)

1. **Provision the cPanel MySQL DB + user** (`data-mysql-setup`).
   - Create DB `amelias_prod` (utf8mb4 / utf8mb4_unicode_ci).
   - Create a **least-privilege** user: `SELECT, INSERT, UPDATE, DELETE` only —
     **never `ALL PRIVILEGES`**. DDL is applied manually via `php db/migrate.php`
     during a maintenance window, not by the app user at runtime.
2. **Pin the production host + DocumentRoot.** Confirm whether DocumentRoot can
   target `public/`. If **yes**, point it at `public/`. If **no**, keep
   `includes/`, `config/`, `db/`, `uploads/` ABOVE the web root (or rely on the
   `.htaccess` deny-set) and set `UPLOADS_PATH` to an off-webroot path.
3. **Set host environment variables** (cPanel "Environment Variables" or
   `SetEnv` in an above-root `.htaccess`): all keys from `.env.example`,
   including a generated `APP_KEY`.
4. **GitHub Actions secrets:** `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`,
   `FTP_REMOTE_DIR`, and optionally `BACKUP_HOOK_URL`.

## Routine deploy

1. Merge to `master` → `.github/workflows/deploy.yml` runs automatically:
   composer install (prod) → conventions audit gate → pre-deploy DB backup →
   FTP-sync.
2. After the first deploy of a release that adds migrations, run
   `php db/migrate.php` against prod (cPanel "Terminal" or scheduled one-off).
3. Smoke-test: home loads, `/menu` loads, place a Stripe **test-mode** order.

## Backups

- **Between deploys:** `cron/backup_db.php` runs daily via `cron/dispatcher.php`,
  writing dated `mysqldump`s to `BACKUP_DIR` (off web root), 30-day retention.
- **Before each deploy:** the workflow hits `BACKUP_HOOK_URL` (a tokenized route
  that runs `backup_db.php`) so the current state is captured immediately prior.

## Rollback

1. **Code:** re-run the previous green deploy (GitHub Actions → re-run a prior
   successful run), or revert the offending commit and let CI redeploy.
2. **Data:** download the latest pre-deploy dump from `BACKUP_DIR` and restore
   into a scratch DB FIRST; verify, then swap. For post-launch data issues,
   prefer **forward-fix migrations + Stripe reconciliation** over a
   drop-and-reimport restore (a restore would destroy real orders/payments
   taken since the backup — C-MISC-10).
3. Always **dry-run a restore** off-server before trusting a backup at cutover.

## Cron (single line)

```
*/5 * * * * /usr/local/bin/php /home/USER/app/cron/dispatcher.php >/dev/null 2>&1
```

The dispatcher fans out to hold-sweep (every tick), slot generation (hourly),
reminders (15 min), notification drain (every tick), and the daily DB backup —
each `flock`-guarded, logging to `logs/cron.log`.
