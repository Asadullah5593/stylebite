# Cron Jobs — What To Add On The Server

**Single source of truth for scheduled tasks.** If you are setting up or checking the
server, this file is the only list you need — do not go digging through the codebase or
the changelogs.

Companion docs: [ADMIN_CHANGELOG.md](ADMIN_CHANGELOG.md) · [MOBILE_CHANGELOG.md](MOBILE_CHANGELOG.md)

---

## Read this first

This project has **no Laravel scheduler**. There is no `app/Console/Kernel.php` and no
`Schedule::` call anywhere — `routes/console.php` contains only the stock `inspire`
command. That means **every job below needs its own cron entry** in Hostinger
hPanel → Advanced → Cron Jobs. Adding a single `schedule:run` entry will do nothing.

- **PHP binary:** `/usr/bin/php`
- **Project path:** `/home/u353708470/domains/stylebiteapp.com/public_html`
- **Queue driver:** `database` (so queued work only moves when `queue:work` runs)
- **Mail:** SMTP, sent **synchronously** inside the request — mail does *not* depend on
  the queue, and OTP/login emails will still deliver even if cron is broken.

**Status as of 2026-08-09: none of these are on the server yet.** Keep the Status
column updated as they go in.

---

## The list (9 entries)

| # | Schedule | Command | Status | Why it matters |
|---|---|---|---|---|
| 1 | **Every minute** | `queue:work --stop-when-empty --max-time=50 --tries=3` | ⬜ Not added | Runs queued jobs. Only user-visible consumer today is post-media optimization (`app/Jobs/OptimizePostMedia.php`) — without it, uploaded images are never compressed into mobile renditions. |
| 2 | **Daily** (01:00) | `stylebite:sync-currency-rates` | ⬜ Not added | Fetches FX rates for creator earnings conversion. **Blocking:** with no stored rates, admin crediting refuses to run by design (it never credits an unconverted amount). Run once by hand right after first deploy. |
| 3 | **Hourly** | `stylebite:settle-ad-earnings` | ⬜ Not added | Credits reel owners their accumulated ad-revenue share into their wallets. Without it, creators earn nothing. Supports `--dry-run` to preview. |
| 4 | **Hourly** | `stylebite:refresh-ad-eligibility` | ⬜ Not added | Recomputes the cached ad-eligibility flag that drives `show_ad` on reels and who earns. Stale flag = wrong ads and wrong earnings. |
| 5 | **Daily** (00:30) | `stylebite:refresh-streaks` | ⬜ Not added | **Required.** Breaks streaks that lapsed. Without it a streak never ends, so every user's streak counts up forever. `--all` recomputes everyone instead of only at-risk profiles. |
| 6 | **Hourly** | `stylebite:lift-expired-suspensions` | ⬜ Not added | Reactivates users whose suspension window ended. The login/API paths also lift lazily when the user returns, so this is the safety net that keeps admin counts honest for users who never come back. |
| 7 | **Daily** (02:30) | `stylebite:prune-user-sessions` | ⬜ Not added | Deletes sessions dead for 30+ days. Needed now that sessions expire every 24h — otherwise `user_sessions` grows by at least one row per user per day forever. `--days=` to change retention. |
| 8 | **Daily** (03:30) | `stylebite:prune-user-activity` | ⬜ Not added | Trims DAU/MAU history past 90 days. MAU only looks back 30 days, so older rows are dead weight. `--days=` to change retention (minimum 31). |
| 9 | **Weekly** (Sun 04:00) | `stylebite:prune-activity-logs` | ⬜ Not added | Trims the admin audit trail past the retention window (365 days default, `AUDIT_RETENTION_DAYS` in `.env`). Every admin action writes a row, so this table grows steadily. Refuses any window under 30 days. |

### Copy-paste ready

```
* * * * * /usr/bin/php /home/u353708470/domains/stylebiteapp.com/public_html/artisan queue:work --stop-when-empty --max-time=50 --tries=3
0 1 * * * /usr/bin/php /home/u353708470/domains/stylebiteapp.com/public_html/artisan stylebite:sync-currency-rates
0 * * * * /usr/bin/php /home/u353708470/domains/stylebiteapp.com/public_html/artisan stylebite:settle-ad-earnings
15 * * * * /usr/bin/php /home/u353708470/domains/stylebiteapp.com/public_html/artisan stylebite:refresh-ad-eligibility
30 0 * * * /usr/bin/php /home/u353708470/domains/stylebiteapp.com/public_html/artisan stylebite:refresh-streaks
45 * * * * /usr/bin/php /home/u353708470/domains/stylebiteapp.com/public_html/artisan stylebite:lift-expired-suspensions
30 2 * * * /usr/bin/php /home/u353708470/domains/stylebiteapp.com/public_html/artisan stylebite:prune-user-sessions
30 3 * * * /usr/bin/php /home/u353708470/domains/stylebiteapp.com/public_html/artisan stylebite:prune-user-activity
0 4 * * 0 /usr/bin/php /home/u353708470/domains/stylebiteapp.com/public_html/artisan stylebite:prune-activity-logs
```

The three hourly jobs are staggered (`:00`, `:15`, `:45`) so they never compete for the
same shared-hosting CPU minute.

---

## Do NOT schedule these

These are manual, run-once-when-needed tools. Putting them on a timer would either waste
resources or cause unwanted writes.

| Command | When to run it |
|---|---|
| `stylebite:backfill-user-activity {--days=90}` | Once, to reconstruct historical DAU/MAU rows after the feature shipped. Not needed again. |
| `stylebite:dedupe-contests` | Manually, when duplicate admin contests need cleaning. Reports first; only `--force` soft-deletes. **Still pending on live** — duplicate rows exist in the production DB. |
| `stylebite:optimize-media` | Manually, as a one-off batch to generate renditions for media uploaded before optimization existed. Ongoing uploads are handled by the queued job (entry #1). |

---

## Verifying it works

After adding the entries, check each one landed:

```bash
# Did the queue actually drain? (should be 0 or dropping)
php artisan tinker --execute="echo DB::table('jobs')->count();"

# Anything failing repeatedly?
php artisan tinker --execute="echo DB::table('failed_jobs')->count();"

# FX rates stored? (blocks crediting if empty)
php artisan stylebite:sync-currency-rates

# Preview earnings settlement without writing anything
php artisan stylebite:settle-ad-earnings --dry-run
```

Admin panel → **Settings → Jobs / Failed Jobs** shows the same queue state in the UI,
and **Activity Logs** shows admin-side effects.

---

## When adding a new scheduled command

1. Add the command under `app/Console/Commands/`.
2. **Add a row to the table above** with its schedule, status, and what breaks without it.
3. Add it to the cron table in `ADMIN_CHANGELOG.md` too, so the change is dated.
4. Tell the client it needs a new server entry — nothing schedules itself here.
