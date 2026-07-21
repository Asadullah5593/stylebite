# Admin Panel & Operations — Changelog

Everything that changed in the **admin panel**, artisan commands, and server operations. Newest first.
Companion doc: [MOBILE_CHANGELOG.md](MOBILE_CHANGELOG.md) (mobile app / API changes).

**Deploy:** SSH to the server, then `bash ~/deploy.sh` (pull → composer → migrate → cache clear).

---

## ⏰ Required cron jobs (hPanel → Advanced → Cron Jobs)

| Schedule | Command | Purpose |
|---|---|---|
| **Every minute** | `/usr/bin/php /home/u353708470/domains/stylebiteapp.com/public_html/artisan queue:work --stop-when-empty --max-time=50 --tries=3` | Processes queued jobs (image optimization) |
| **Daily** (e.g. 01:00) | `/usr/bin/php /home/u353708470/domains/stylebiteapp.com/public_html/artisan stylebite:sync-currency-rates` | Refreshes FX rates for earnings conversion |

Both are required. Without the daily rate sync, **admin crediting is blocked** (by design — the system never credits an unconverted amount).

---

## 2026-07-21 — Ad eligibility & contest thresholds are now configurable

**Settings:** Admin → Settings → **Ads** (new tab)

| Key | Meaning | Default |
|---|---|---|
| `ads.min_followers` | Minimum followers a creator needs to be ad-eligible | **500** |
| `ads.min_watch_hours` | Minimum watch hours a creator needs to be ad-eligible | **1000** |

Ads aren't built yet — these are the criteria the ad system will read when it ships. A helper, `stylebite_ad_eligibility($userId)`, already evaluates a creator against them and returns `eligible`, plus each metric with its threshold and pass/fail. It is read-only and nothing calls it yet.

> ⚠️ **Watch hours read 0 today.** They're summed from `post_views.watch_seconds`, and **nothing populates that column yet** — the app doesn't report watch time. Until it does, no creator can meet the watch-hours criterion. The follower criterion works today. This needs a mobile-side change (report watch seconds per view) before the watch-hours rule is meaningful.

**Settings:** Admin → Settings → **Contests** (new tab)

| Key | Meaning | Default |
|---|---|---|
| `contests.min_participants` | Lowest `max_participants` a user may set when creating a contest | **2** |
| `contests.max_participants` | Highest `max_participants` a user may set | **100000** |

Applied to the user-facing contest creation API (`POST /contests/city-vs-city`). **Defaults are exactly the values that were previously hardcoded, so behaviour is unchanged until an admin edits them.** A nonsensical config (min > max) is clamped rather than throwing.

> The **admin panel's own** contest form still uses its existing rule (`min:1`, no upper bound) — deliberately left untouched so admin workflows don't change. Tell the backend if you want the admin form bounded by these settings too.
>
> Contest **vote score** range (1–5) was deliberately **not** made configurable — the mobile app's star UI and the ratings-distribution endpoint both assume a 5-point scale, so changing it would break them.

Also fixed: the settings "Other" filter was omitting `feed.%` (and now `ads.%`), so those keys could show up under Other.

---

## 2026-07-17 — Rewards are entered in USD and converted per creator

**Where:** Admin → Earnings → open a wallet → *Manual Adjustment*

- The amount field is now **"Amount in USD"** (the base currency), not the wallet's currency.
- A **live preview** shows what will actually be applied: *"50 USD = 13,907.99 PKR will be applied"*, plus the rate used and how fresh the rates are.
- On submit, the amount is converted into the creator's wallet currency at that moment's rate and **frozen** — the balance never changes afterwards, even if rates move.
- Every transaction stores an **audit trail**: `base_amount`, `base_currency_code`, `fx_rate`, `fx_rate_at`. Reversals mirror the original conversion.
- If no exchange rate exists for the pair, a warning is shown and **submit is disabled** — nothing is credited.
- Existing balances were **not touched**.

**Settings:** Admin → Settings → **Earnings**
- `earnings.base_currency_code` — currency rewards are entered in (default **USD**)
- `earnings.default_currency_code` — wallet currency when a user's country is unknown (default **PKR**)

**New command:** `php artisan stylebite:sync-currency-rates`
- Pulls daily rates from ExchangeRate-API's open-access endpoint (**no API key needed**).
- If the fetch fails, the last known-good rates are kept and crediting keeps working (failure is logged).
- **Run once manually after deploying**, then leave it to the daily cron.

> Rates powered by [ExchangeRate-API](https://www.exchangerate-api.com) (open access — attribution required).

---

## 2026-07-17 — Wallet currency follows the user's country

New wallets take their currency from the user's **profile country** (Pakistan→PKR, UK→GBP, UAE→AED, US→USD, India→INR, Euro-zone→EUR, Saudi/Qatar/Kuwait/Bahrain/Oman, Turkey, Canada, Australia, Malaysia, Indonesia, Singapore, China, Japan, Bangladesh). Unknown/blank country → `earnings.default_currency_code`.

Currency is fixed at wallet creation; changing the profile country later does **not** re-denominate an existing wallet (balances are held in that currency).

---

## 2026-07-12 — Nearby feed radius is admin-controlled

**Settings:** Admin → Settings → **Feed**
- `feed.nearby_radius_km` — radius for the app's "Nearby" feed (default **10**). Changes apply within ~5 minutes (config cache auto-clears on save).

---

## 2026-07-12 — Duplicate contests prevented + cleanup command

**Where:** Admin → Contests → Create / Edit

Duplicate admin contests were being created because the form had no double-submit protection, titles had no uniqueness rule, and the slug's random suffix meant the DB never blocked a repeat. Fixed at three layers:

1. **Unique title validation** on create/update (scoped to admin contests, case-insensitive, ignores soft-deleted rows and the contest's own title on edit) → error: *"A contest with this title already exists."*
2. **In-transaction re-check** — closes the rapid-resubmit race window.
3. **Submit button disables** with a "Saving…" spinner on submit (double-click protection).

**New command:** `php artisan stylebite:dedupe-contests`
- **Dry-run report by default** — nothing is deleted without `--force`.
- Keeps the copy with the most activity (participants + submissions + votes); ties keep the oldest.
- Only **zero-activity** duplicates are soft-deleted. Any duplicate with activity is **flagged for manual review, never deleted**.

> ⚠️ **Not yet run on live** — existing duplicates remain in the DB. Run the report first, review, then `--force`.

---

## 2026-07-10 — Media optimization pipeline

Uploaded images are compressed and downscaled (max 1080px) automatically via a queued job; results are served to the app. Requires the **every-minute `queue:work` cron** above.

**New command:** `php artisan stylebite:optimize-media`
- Backfills optimized renditions for media uploaded before the pipeline existed.
- `--sync` processes inline (immediate); default dispatches to the queue. `--force` re-optimizes.
- Already run once on live: 15 of 23 items optimized (the rest are videos — see below). Example result: 67 KB → 48 KB.

**Video:** the shared host has **no ffmpeg and shell/`proc_open` disabled**, so videos are **not** transcoded — they're served as originals and marked `processing_status: ready`. This degrades gracefully by design (no errors, no retries). If the app ever moves to a VPS with ffmpeg, ≤720p transcoding starts working automatically with no code change.

---

## 2026-07-12 — Migrations baselined (one-time fix)

The server's schema came from an SQL import, so Laravel's `migrations` table didn't know which migrations had run — `deploy.sh` failed on `migrate` with "table already exists".

Resolved by baselining: 13 migrations were recorded as run (after verifying the schema actually had them) and 1 genuinely-pending migration was applied. **All migrations now show `Ran`, and `deploy.sh` runs cleanly end to end.**

> If "table already exists" ever reappears, a new unbaselined migration is the likely cause — verify the schema, then insert the row into the `migrations` table rather than re-running it.

---

## 2026-07-10 — Deployment moved to GitHub + SSH

- The live site now tracks **`Asadullah5593/stylebite`, branch `asad`** (it previously pointed at a different fork, `asifyounas708/stylebite_website_admin`, and couldn't pull at all).
- One production hotfix found only on the server (admin contest `title` on insert) was preserved into the repo before the switch.
- **`~/deploy.sh`** on the server does the whole deploy: `git pull` → `composer install --no-dev` → `migrate --force` → cache clear.
- User uploads (`posts/`, `users/`, `memories/`), `.env`, and the root `.htaccess` are untracked and are never touched by a deploy.

**Workflow:** commit + `git push origin asad` locally → `ssh -p 65002 u353708470@145.79.26.222` → `bash ~/deploy.sh`.

---

## 🚧 Open items

0. **Watch time is never recorded.** `post_views.watch_seconds` exists but nothing writes to it, so `ads.min_watch_hours` can never be satisfied. Needs the mobile app to report watch seconds per view before ad eligibility can use it.
1. **Run `stylebite:dedupe-contests`** on live to clear existing duplicate contests (report first).
2. **Security hardening:** `.env`, `stylebite_db.sql`, `Archive.zip` etc. sit inside `public_html` (project root = docroot) and may be web-readable. Needs `.htaccess` deny rules. Check `https://stylebiteapp.com/.env`.
3. **Test data on live:** test user id **41** (`avatartest_…@example.com`) + two test images in `public_html/users/41/avatar/`.
4. **Video optimization decision:** Cloudinary / Mux / Bunny Stream, VPS move, or app-side compression.
