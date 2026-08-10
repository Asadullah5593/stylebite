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

**Status as of 2026-08-10: all live except entry 11**, which needs re-adding after a
bad schedule was removed. Every command was run by hand on the server on
2026-08-10 and all twelve executed cleanly.

---

## The list (12 entries)

| # | Schedule | Command | Status | Why it matters |
|---|---|---|---|---|
| 1 | **Every minute** | `queue:work --stop-when-empty --max-time=50 --tries=3` | ✅ Live | **Now critical.** Runs queued jobs: post-media optimization (`app/Jobs/OptimizePostMedia.php`) *and* push-notification campaigns (`app/Jobs/ProcessNotificationCampaign.php`). Without it, uploaded images are never compressed **and every push campaign sits at 0% forever** — the admin sees "queued" and nothing is ever delivered. |
| 2 | **Daily** (01:00) | `stylebite:sync-currency-rates` | ✅ Live | Fetches FX rates for creator earnings conversion. **Blocking:** with no stored rates, admin crediting refuses to run by design (it never credits an unconverted amount). Run once by hand right after first deploy. |
| 3 | **Hourly** | `stylebite:settle-ad-earnings` | ✅ Live | Credits reel owners their accumulated ad-revenue share into their wallets. Without it, creators earn nothing. Supports `--dry-run` to preview. |
| 4 | **Hourly** | `stylebite:refresh-ad-eligibility` | ✅ Live | Recomputes the cached ad-eligibility flag that drives `show_ad` on reels and who earns. Stale flag = wrong ads and wrong earnings. |
| 5 | **Daily** (00:30) | `stylebite:refresh-streaks` | ✅ Live | **Required.** Breaks streaks that lapsed. Without it a streak never ends, so every user's streak counts up forever. `--all` recomputes everyone instead of only at-risk profiles. |
| 6 | **Hourly** | `stylebite:lift-expired-suspensions` | ✅ Live | Reactivates users whose suspension window ended. The login/API paths also lift lazily when the user returns, so this is the safety net that keeps admin counts honest for users who never come back. |
| 7 | **Daily** (02:30) | `stylebite:prune-user-sessions` | ✅ Live | Deletes sessions dead for 30+ days. Needed now that sessions expire every 24h — otherwise `user_sessions` grows by at least one row per user per day forever. `--days=` to change retention. |
| 8 | **Daily** (03:30) | `stylebite:prune-user-activity` | ✅ Live | Trims DAU/MAU history past 90 days. MAU only looks back 30 days, so older rows are dead weight. `--days=` to change retention (minimum 31). |
| 9 | **Weekly** (Sun 04:00) | `stylebite:prune-activity-logs` | ✅ Live | Trims the admin audit trail past the retention window (365 days default, `AUDIT_RETENTION_DAYS` in `.env` — currently unset, so 365 applies). Every admin action writes a row, so this table grows steadily. Refuses any window under 30 days. |
| 10 | **Weekly** (Sun 04:30) | `queue:prune-failed --hours=336` | ✅ Live | Framework command. Trims `failed_jobs`, which nothing else clears — a run of failures otherwise accumulates permanently. Recommended, not critical. |
| 11 | **Hourly** (:20) | `stylebite:send-streak-reminders` | ⬜ Needs re-adding | Warns users whose streak lapses tonight (last qualifying day was yesterday, nothing today). One reminder per user per day — safe to run hourly. **Schedule it plainly hourly**: the command itself refuses to send outside 09:00–21:00 in the app's reporting timezone, because cron is UTC and an hour range here would drift five hours. Window and on/off live in Admin → Settings → Streaks; `--force` bypasses it for manual runs; `--limit=` caps a run and says so when capped. |
| 12 | **Hourly** (:50) | `stylebite:send-contest-ending-reminders` | ✅ Live | Tells participants of active contests that entries close within the configured window (Admin → Settings → Contests, default 24h). One reminder per user per contest — safe to run hourly. |

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
30 4 * * 0 /usr/bin/php /home/u353708470/domains/stylebiteapp.com/public_html/artisan queue:prune-failed --hours=336
20 * * * * /usr/bin/php /home/u353708470/domains/stylebiteapp.com/public_html/artisan stylebite:send-streak-reminders
50 * * * * /usr/bin/php /home/u353708470/domains/stylebiteapp.com/public_html/artisan stylebite:send-contest-ending-reminders
```

The three hourly jobs are staggered (`:00`, `:15`, `:45`) so they never compete for the
same shared-hosting CPU minute.

---

## Gotchas — failures that are silent

Worth knowing before you trust a green cron list.

- **A broken FX sync looks like success.** If `sync-currency-rates` runs but the API call
  fails, previously stored rates are deliberately kept and crediting continues on
  last-known-good rates. The only signal is a `Log::warning`. Rates can therefore go
  stale for weeks with no visible symptom — check `currency_rates.updated_at`
  occasionally, not just whether the cron fired.
- **Only one command guards against overlap.** `settle-ad-earnings` takes a `Cache::lock`,
  so a slow run cannot double-credit. The prunes and refreshes have no such guard. They
  are idempotent, but do not schedule any of them more frequently than listed — two
  concurrent copies of `refresh-ad-eligibility` would just burn shared-hosting CPU.
- **`refresh-ad-eligibility` is the one that will hurt at scale.** It runs a query pair
  per video creator, so its cost grows linearly with creators. It is the first cron to
  revisit when the user base grows.
- **Ad eligibility currently evaluates to false for everyone**, regardless of cron.
  Eligibility needs followers ≥ 500 **and** watch-hours ≥ 1000 (both admin-configurable).
  The backend is ready — `POST /api/views/batch` accepts and stores `watch_seconds`
  (`Api/ViewController.php:58-65`) — but **`post_views` is empty (0 rows)**, so the mobile
  app is not reporting views yet and `watch_hours` reads 0. Scheduling entry #4 is
  correct; just don't expect it to make anyone eligible until the app starts sending view
  batches. This is an app-integration gap, not a cron or backend problem.
- **Streaks vs timezones.** The streak day boundary uses the reporting timezone
  (`Asia/Karachi` by default) while cron and `APP_TIMEZONE` are UTC. The "active today or
  yesterday" grace window absorbs the ~5-hour skew, so any nightly slot is safe — don't
  try to "correct" for it.
- **`queue:work` here is not a daemon.** `--stop-when-empty --max-time=50` means it exits
  every minute by design; shared hosting cannot run a supervised worker. Anything
  latency-sensitive (a mass push or email send) is therefore limited to
  minute-granularity progress, not instant delivery.

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


---

## Verified on the server — 2026-08-10

Every scheduled command was run by hand and all executed cleanly. Notable results:

- `sync-currency-rates` — **stored 166 rates**, which unblocks creator crediting
  (it refuses to credit an unconverted amount, so this had been a hard blocker).
- `prune-user-sessions` — pruned 9 dead sessions on its first run.
- `refresh-ad-eligibility` — 3 creators checked, **0 eligible**. Correct: eligibility
  needs 1000 watch-hours and `post_views` is empty until the app sends view batches.
- `refresh-streaks` — **0 profiles checked**, because no user currently holds a
  streak (only 3 accounts have ever posted, none in the last 30 days). The streak
  reminder will stay idle until people post daily. Not a fault.
- The four prunes and `queue:prune-failed` returned 0 — nothing is old enough yet.
- `queue:work` exited immediately: no backlog.

To re-run this health check:

```bash
cd ~/domains/stylebiteapp.com/public_html && for c in "stylebite:sync-currency-rates" "stylebite:refresh-ad-eligibility" "stylebite:lift-expired-suspensions" "stylebite:prune-user-sessions" "stylebite:prune-user-activity" "stylebite:prune-activity-logs" "queue:prune-failed --hours=336" "stylebite:settle-ad-earnings --dry-run" "stylebite:refresh-streaks"; do echo "=== $c ==="; php artisan $c 2>&1 | tail -6; echo; done
```

`settle-ad-earnings` is the one to keep `--dry-run` on when testing — without it,
it credits real money.
