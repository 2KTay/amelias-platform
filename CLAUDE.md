# ameliasbyeat — Claude Code Context

## What This File Is

AI assistant context for Amelia's By Eat — a restaurant digital platform built
on the Aslan Advisors tech stack (PHP 8+, MySQL, GoDaddy/cPanel). See the
`aslan` master skill for cross-project conventions, lifecycle routing, and
defaults.

**Project type:** `internal-tool` (see `.claude/project-type`). On non-trivial
tasks, the `aslan` master skill enforces a workflow gate — `workflow-brainstorm`
must produce an approved spec before any code lands.

## Skill routing
<!-- aslan-skills:install -->

For any work in this repo, invoke the `aslan` master skill first. It will
route to the right specialist (workflow / frontend / backend / data / deploy /
security / quality / client-lifecycle) based on what you're trying to do.

For any non-trivial task (new file, new schema column, new feature, new
dependency), `aslan` routes to the `workflow` mother FIRST — brainstorm and
plan before code. Trivial work (copy fixes, one-line CSS, README edits,
dependency bumps, typos) bypasses.

To override the workflow gate on a legitimate fast-fix moment, type
`skip workflow:` followed by what you want. The override is logged in
the commit message footer.

## Project-specific notes

- **Hybrid platform.** Operational surfaces (e-commerce/ordering, reservations,
  payments, QR feedback, admin dashboard) are *templated* per
  `internal-tool-*` conventions. Public marketing pages are *bespoke* per
  `public-website-conventions`. `project-type` is `internal-tool` so the full
  workflow gate covers the operational software.
- **Client:** Amelia's By Eat (ameliasaz.com). Current ordering/menu runs on
  Square (amelias-105290.square.site).
- **Scope:** Full-platform spec up front (website modernization, e-commerce,
  reservations, payments, QR feedback → Google Reviews, admin dashboard).
- **Single source of truth:** the design spec at
  `docs/superpowers/specs/2026-06-02-amelias-platform-design.md` holds the
  decision log, RTM, and open client questions inline — do NOT split these into
  separate `docs/client-context/` files (per Renato). Plans go in
  `docs/superpowers/plans/`.
