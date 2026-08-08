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
| **Hourly (or daily)** | `/usr/bin/php /home/u353708470/domains/stylebiteapp.com/public_html/artisan stylebite:settle-ad-earnings` | Credits reel owners their accumulated ad-revenue share |
| **Hourly** | `/usr/bin/php /home/u353708470/domains/stylebiteapp.com/public_html/artisan stylebite:refresh-ad-eligibility` | Refreshes the cached ad-eligibility flag driving reel `show_ad` + earning |
| **Daily** (e.g. 03:30) | `/usr/bin/php /home/u353708470/domains/stylebiteapp.com/public_html/artisan stylebite:prune-user-activity` | Trims DAU/MAU history past 90 days so the table stays bounded |
| **Daily** (e.g. 00:30) | `/usr/bin/php /home/u353708470/domains/stylebiteapp.com/public_html/artisan stylebite:refresh-streaks` | **Required** — breaks streaks that lapsed. Without it a streak never ends |

Both are required. Without the daily rate sync, **admin crediting is blocked** (by design — the system never credits an unconverted amount).

---

## 2026-08-08 — Dashboard redesigned as a tabbed Command Center 🗂️

The dashboard page was restructured to the approved "Command Center" design
(Option 1a). **Layout only** — the admin shell (sidebar, topbar, fonts, colors,
dark/light theme toggle) is unchanged, and **every metric, chart and list from
the old dashboard is still present**, just re-organised.

### New structure

1. **Needs your attention** — the four action queues now sit in a single strip
   (posts under review · pending withdrawals · failed pushes · banned users),
   each item linking to its queue.
2. **Five tabs** replace the long scroll: **Overview · Audience · Content ·
   Money · Trust & Safety**. The last-viewed tab is remembered per browser.

| Tab | KPI tiles | Panels |
|---|---|---|
| **Overview** | Total Users, Monthly Active, Posts, Total Balance, DAU, Reels, Open Reports, Pending Payouts | Users & Posts growth (14d), Withdrawal Queue, Top Posts, Activity Feed |
| **Audience** | DAU, MAU, New Signups, Total Users, Active/Longest/Average Streak, Banned Users | Recent Users |
| **Content** | Posts, Reels, Food Reviews, Memories, Likes, Comments, Shares, Active + Completed Contests | Top Posts, Top Reels, Media Uploads by Type |
| **Money** | Total Balance, Pending Payouts, Completed Payouts, Pending Withdrawals | Earnings & Withdrawals (7d), Withdrawal Queue |
| **Trust & Safety** | Posts Under Review, Open Reports, Banned Users, Failed Pushes | Top Report Reasons, Recent Reports |

### Notes

- The old **status snapshot** link tiles are gone as a separate section — their
  four metrics now render as **linked KPI tiles** on the Money and Trust &
  Safety tabs (a tile with a route is clickable), so nothing was lost.
- KPI tiles follow the new design: no icons, uppercase label, large value, sub
  line, delta comparison pinned to the tile bottom.
- Charts initialise lazily per tab (a chart built inside a hidden pane renders
  at zero size), and still re-render on theme toggle.
- Duplicate queries removed: banned users / posts-under-review / pending
  withdrawals / failed pushes are each counted once per request now.
- Files: `Admin/DashboardController.php` (tab tile composition,
  `statusSnapshots()` removed), `admin/dashboard.blade.php` (rewritten),
  `admin/partials/metric-cards.blade.php` (now the KPI tile grid), new
  `admin/partials/withdrawal-queue.blade.php`.

No migration, no new endpoints, no mobile impact. Deploy is a plain pull.

---

## 2026-08-05 — Creator Payouts: pending vs completed 💸

A new **Creator Payouts** section with two cards:

| Card | Counts | Subtitle |
|---|---|---|
| **Pending Payouts** | `pending` + `processing` | `X awaiting review · Y processing` |
| **Completed Payouts** | `completed`, all time | `X in the last 30 days`, compared against the previous 30 |

Pending groups the two in-flight statuses the same way the withdrawals queue and
the action cards already do. Failed and rejected payouts are in neither card —
they live on the withdrawals page.

### Counts, not amounts — on purpose

Every payout row carries its own `currency_code`. Summing amounts would add PKR
to USD the moment a second currency exists, and the total would be quietly wrong
rather than visibly broken. A count answers the same operational question — "four
payouts are waiting on you" — and cannot go wrong in any currency.

If money totals are ever wanted here, they need the payout's value locked in one
reporting currency at the moment it settles. The FX columns for that already
exist on `earning_transactions` but are not filled for withdrawals, so that is a
separate piece of work, not a display change.

### Deploy

`php artisan migrate` — adds an index on `withdrawal_requests.processed_at`.
`status` and `requested_at` were already indexed; `processed_at` was not, and the
"last 30 days" count filters on it. Cached for 5 minutes.

---

## 2026-08-05 — Daily streaks: engine, admin controls & dashboard stats 🔥

### What was there before

Nothing, despite appearances. `profiles.current_streak_days` and
`current_streak_label` existed and the profile API read them, but **no code
anywhere ever wrote them** — every profile sat at 0, so the app showed every user
a 0-day streak. This adds the engine that was missing.

Because the mobile API already reads those columns, streaks start working in the
app as soon as this ships — **no app-side change and no new endpoint.**

### What keeps a streak alive — Admin → Settings → Streaks

| `streaks.mode` | A day counts when the user… |
|---|---|
| `outfit` *(default)* | publishes an outfit post |
| `any_post` | publishes any post, outfit or food |
| `login` | simply opens the app — nothing to post |

Also configurable there: `streaks.max_restores` (default 5) and
`streaks.max_restore_gap_days` (default 7).

### How it behaves

A streak is the run of consecutive days, cut in the reporting timezone. It stays
alive if the user was active **today or yesterday**, so it does not collapse in
the morning before they have posted.

The engine **recomputes from scratch every time** — it never adds to the stored
number. That is what makes the awkward cases correct rather than special-cased:

| Case | Result |
|---|---|
| Five posts in one day | One day |
| Post deleted afterwards | That day drops out; the streak shortens or breaks |
| Deleted post restored / reposted | The day comes back |
| Admin removes a post in moderation | Author's streak recomputes |
| Posted at 11pm in Pakistan | Counts for that day, not the next |
| Mode changed later | Every streak re-derives under the new rule |

### Admin controls (user detail page → Streak Controls)

**Restore Streak** credits the days the user missed rather than writing a streak
number — so the restore survives every later recomputation instead of being
overwritten by the next nightly run. Guarded twice: a lifetime quota per user,
and a cap on how long a break one restore may bridge, so a user who stopped
posting months ago cannot be handed a months-long streak in one click.

**Reset to 0** moves a boundary forward instead of zeroing a column (a zeroed
column would simply come back on the next run). Activity from before the reset
instant stops counting, so it reads 0 immediately even if the user already posted
today, and their next post starts a fresh streak. Their personal best is kept.

Both actions are written to the admin activity log with the before/after values.

### Dashboard

A new **Streaks** section: Active Streaks, Longest Streak (with the leading
username) and Average Streak. The first card's subtitle names the rule in force,
because "142 active streaks" means something very different under `login` than
under `outfit`.

### Deploy

1. `php artisan migrate` — adds the streak columns, an index, and
   `streak_grace_days`.
2. **Add the nightly `stylebite:refresh-streaks` cron** from the table above.
   Posting and logging in update a streak instantly, but nothing fires when a
   user simply stops — without this cron, a streak never breaks.

⚠️ **Set expectations before showing this to the client:** the stats will read
**0 active streaks** on day one. Only three users have ever published a post, on
non-consecutive days, and nobody posted today or yesterday. That is the data
being accurate, not the engine failing.

---

## 2026-08-05 — Top Posts & Top Reels charts 🏅

Two charts side by side, above the growth chart. Each shows the **top five items
by engagement** as a horizontal stacked bar — one bar per item, split into likes,
comments and shares, so you can see *what kind* of engagement drove it rather
than just a single total.

- **Top Posts** — images and carousels
- **Top Reels** — video posts

The two lists are **disjoint**: a reel is a video post, so nothing appears in both
charts.

Bar labels are the caption, shortened to one line; hover to see the author. Items
with zero engagement are left out rather than padding the chart to five empty
rows, so a chart can legitimately show fewer than five bars — or an "no
engagement recorded yet" message when there is nothing to rank.

Ranking reads the counters on `posts` rather than counting rows in the engagement
tables: it keeps this to a single pass over `posts`, and those counters are the
same numbers the app shows under each post. Cached for 5 minutes.

---

## 2026-08-05 — Engagement metrics on the dashboard ❤️💬↗️

A new **Engagement** section (between *Audience* and *Overview*) with three cards:

| Card | Counts | Subtitle |
|---|---|---|
| **Likes** | Every like on feed content — on posts, on comments, and on replies | `X on posts` |
| **Comments** | Comments plus replies | `X replies` |
| **Shares** | Post shares | `X in the last 14 days` |

A like on a comment is still a like, so the cards read as platform totals rather
than post-only totals. **Memories engagement is deliberately excluded** — it is a
separate module and already has its own Overview card.

### Engagement on removed content does not count

Every tally is scoped through to the owning post, so once a post is deleted its
likes, comments and shares drop out of these totals. Without that scope the cards
count activity on content nobody can see any more and stop matching the Overview
post counts.

📌 **Correction to an earlier draft of this entry.** It claimed the
`posts.like_count` / `comment_count` / `share_count` counters had drifted out of
sync and that the mobile app was therefore showing wrong numbers. **That was
wrong** — it compared a sum that excludes deleted posts against a row count that
included them. On live posts the counters match the rows exactly (9 likes, 5
comments). The counters are fine and the app is showing correct numbers.

### Deploy

`php artisan migrate` — adds a `created_at` index to `comment_likes` and
`reply_likes`. The other four engagement tables already had one; without it the
period comparison would scan those tables in full.

Counts are cached for 5 minutes.

### Also in this change

The three card sections (Overview, Audience, Engagement) now render through one
shared Blade partial (`admin/partials/metric-cards`) instead of three copies of
the same markup, so a card looks identical wherever it appears.

---

## 2026-08-05 — Reels, Food Reviews & Completed Contests on the dashboard 🎬🍽🏆

The **Overview** section has three new cards:

| Card | Counts | Subtitle |
|---|---|---|
| **Reels** | Posts carrying video | `X published` |
| **Food Reviews** | Posts posted to the Bite feed (`post_type = food`) | `X rated` — how many have received at least one rating |
| **Completed Contests** | Contests with status `completed`, sitting next to *Active Contests* | `X with a winner` — a completed contest without one still needs a decision |

All three compare against the previous period like every other Overview card. The
grid stays three cards per row, so the nine cards now fill three even rows; phone
and tablet layouts are unchanged.

**Completed Contests compares on `end_at`, not `created_at`** — the useful
question is how many contests *finished* in the period, not how many were created
in it and happen to be finished now.

### ⚠️ These numbers do not add up to Total Posts — by design

Reels and Food Reviews are **subsets** of Total Posts, and they **overlap each
other**: a food review shot as a video counts in both. So `Posts ≠ Reels + Food
Reviews + …`, and that is correct, not a bug.

### How each is defined

**Food Review** is `post_type = 'food'` — the app sets `post_type`, `feed_type`
(`bite`) and `content_type` (`food`) together when a food post is created, so all
three agree.

**Reel** is any post carrying video. The `post_type` enum does contain a `reel`
value, but **nothing ever writes it** — the create-post API only accepts `outfit`
and `food`. The reels feed itself selects reels by their video media, so the card
matches the feed rather than the unused enum value.

### Deploy

`php artisan migrate` — adds a `(media_kind, status)` index on `posts`.
`post_type` was already indexed, but `media_kind` was not, so counting reels
would otherwise scan the whole posts table on every dashboard load.

Both counts are cached for 5 minutes.

---

## 2026-08-05 — DAU / MAU on the dashboard 📊

The dashboard has a new **Audience** section (directly under *Needs your
attention*) with three cards:

| Card | What it counts | Comparison |
|---|---|---|
| **Daily Active Users** | Unique users who used the app today | vs yesterday |
| **Monthly Active Users** | Unique users active in the last 30 days | vs the previous 30 days |
| **New Signups** | Accounts created today (7-day and 30-day totals in the subtitle) | vs yesterday |

### Why a new table was needed

`users.last_seen_at` is overwritten on every request, so it can say who is active
*right now* but never how many were active *yesterday* — no history, no trend, no
comparison. A new **`user_daily_activity`** table stores one row per user per
active day, which is what DAU/MAU are actually counted from.

Writes are cheap: the API auth middleware already loads the user and its previous
`last_seen_at`, so it can tell without any extra query whether this is the user's
first request of the day. That means **one INSERT per user per day**, not one per
request.

### Day boundaries

A "day" is cut in the timezone from **Settings → General → Default Timezone**
(defaults to `Asia/Karachi` if unset or invalid). Storage stays UTC — this only
decides where the reporting day starts, so a user active at 11pm in Pakistan is
counted today rather than tomorrow.

### Deploy steps

1. `php artisan migrate` — adds `user_daily_activity` and an index on
   `users.last_seen_at` (DAU/MAU range-scan it; without the index every dashboard
   load was a full table scan).
2. `php artisan stylebite:backfill-user-activity` — **run once.** Reconstructs up
   to 90 days of history from traces users already left (posts, comments, likes,
   sessions, activity log) so the cards are not empty on day one. Approximate by
   nature; live tracking from the middleware is exact and takes over immediately.
3. Add the daily `stylebite:prune-user-activity` cron from the table above.

The cards are cached for 5 minutes, so a change in the app shows up on the next
dashboard load within that window.

---

## 2026-08-06 — Chat list, block visibility, and opening-message limit

Requested by the mobile team.

- **Empty conversations are hidden from the chat list.** Opening someone's profile
  creates a conversation row; those with no messages no longer appear in
  `GET /chats`. Nothing is deleted — the rows still exist and appear in the admin
  messaging screens, they are just not listed to the user until a message is sent.
- **Block state is reported explicitly** as `is_blocked_by_me` / `is_blocked_by_other`
  on chat objects and on the `403` bodies, so the app can tell "I blocked them" from
  "they blocked me". Blocked conversations remain listed, flagged rather than hidden.
  Block lookups for the list are done in one query for the whole page, not per row.
- **Opening-message limit raised from 1 to 2.** A user may send two messages before
  the other person replies. Unchanged: the cap only applies until the first reply,
  after which it no longer applies at all. Tunable via `MAX_UNANSWERED_MESSAGES` in
  `ChatController`.

**No migrations, no config, no new dependencies** — a plain `bash ~/deploy.sh`.

---

## 2026-08-05 — Realtime chat via Pusher ⚠️ deploy needs new env vars

Chat now broadcasts over **Pusher Channels**. Shared hosting cannot run a socket
server (no long-lived processes, no custom ports), so the app only makes outbound
HTTPS calls to Pusher and the phones connect to Pusher directly.

**Required on the server before deploying** — add to `.env`, then
`php artisan config:clear`:

```
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=<from Pusher dashboard → App Keys>
PUSHER_APP_KEY=<from Pusher dashboard → App Keys>
PUSHER_APP_SECRET=<from Pusher dashboard → App Keys>
PUSHER_APP_CLUSTER=ap2
PUSHER_SCHEME=https
PUSHER_PORT=443
PUSHER_TIMEOUT=5
```

🔒 **Never commit the real values.** `PUSHER_APP_SECRET` can publish to any channel
and read the whole app's state — it belongs in `.env` only, which is untracked.
(`PUSHER_APP_KEY` is public by design; it ships inside the mobile app.)

If these are missing the app **does not break** — broadcasting falls back to doing
nothing and chat behaves exactly as it did before (REST only).

- **New composer dependency:** `pusher/pusher-php-server` — deploy must run
  `composer install`, which `~/deploy.sh` already does.
- **No migrations.**
- **Broadcasts are sent synchronously**, never queued. This is deliberate: the
  queue is drained by a once-per-minute cron, so a queued broadcast would land up
  to 60 seconds late. Timeout is capped at 5s.
- **Broadcast failures are swallowed and logged**, never surfaced to the user. If
  Pusher is down or the plan limit is hit, messages still send and save normally —
  clients recover missed events via the sync endpoint. Watch for
  `Chat broadcast failed` in the logs.

**Plan limits (free Sandbox):** 100 concurrent connections, 200k messages/day.
Roughly 1,500–2,000 daily active users. Over the connection cap Pusher silently
refuses new connections and those users fall back to REST — worth monitoring in
the Pusher dashboard as usage grows.

⚠️ **Use a Pusher app dedicated to Stylebite.** The credentials this was first wired
against belonged to an unrelated two-year-old project, which still had live
subscribers on public `notification-{id}` channels. Sharing an app means sharing the
100-connection free cap with traffic we don't control, so Stylebite has its own app.
The chat channels here are all private or presence and individually authorised.

---

## 2026-08-05 — Chat read state, delivery tracking, and blocking enforcement

Backend groundwork for moving chat from REST polling to realtime WebSockets.

- **Read receipts are now actually recorded.** The `message_reads` table and each
  member's `last_read_message_id` were defined in the schema but never populated by
  the API — the admin *Read receipts* screen was therefore always empty. A new
  mark-as-read endpoint now writes both, so that screen shows real data going forward.
  (Historic conversations stay empty — nothing backfills them.)
- **`messages.delivered_at` is now written** when the recipient's device pulls a
  message. Previously the column existed but was permanently `null`.
- **Blocking is enforced in chat.** `user_blocks` was never consulted by the chat
  API, so a blocked user could still open a conversation and send messages. Blocked
  pairs are now rejected with `403` in both directions.
- **Online status expires after 2 minutes.** `users.is_online` was purely
  self-reported and never cleared, so anyone who force-quit the app stayed "online"
  indefinitely — including in admin views. It is now derived from `last_seen_at`.
- **Fixed a latent 500 on chat push notifications.** New-message notifications were
  written with `entity_type = 'conversation'`, which is not a valid value for that
  column's enum. On a strict-mode MySQL server this throws *after* the message is
  saved — the message would send but the request would error. Now recorded as
  `entity_type = 'message'` pointing at the message id.

**No migrations and no config changes** — deploy is a normal `bash ~/deploy.sh`.

⚠️ **Note for the upcoming realtime work:** the queue runs from a once-per-minute
cron, so anything queued is delayed up to 60 seconds. Realtime broadcasts must
therefore be sent synchronously, not queued. Shared hosting cannot host the socket
server itself — that runs through a managed provider (Pusher).

---

## 2026-07-28 — Signup OTP, `show_ad`, and an eligibility-cache flag

- **Signup email verification is now a 6-digit OTP** (was a magic link): 15-min expiry, 5-attempt lockout per code, 60-second resend cooldown, rate-limited endpoints. Forgot-password (already OTP) got the same cooldown.
- **`show_ad`** added to each reels/feed item — true when the reel owner is ad-eligible. Backed by a cached `profiles.ad_eligible` flag so the hot feed path doesn't recompute watch hours per request.
- New command **`stylebite:refresh-ad-eligibility`** (cron, hourly) keeps that flag current; the `/ads/eligibility` endpoint also refreshes a creator's own flag when they open the monetization screen. The impressions revenue split now reads this cached flag.
- Fixed a timezone skew (DB `useCurrent()` vs app `now()`) that would have broken the OTP/reset cooldown timing.

---

## 2026-07-28 — Ads & monetization system (backend)

Reel-based ads: eligible creators enable ads on their reels and earn a share of the revenue.

**Settings → Ads** (all admin-editable):
| Key | Meaning | Default |
|---|---|---|
| `ads.min_followers` | Followers needed to be ad-eligible | 500 |
| `ads.min_watch_hours` | Watch hours needed to be ad-eligible | 1000 |
| `ads.reel_owner_share_percent` | Reel owner's cut of **mid-reel** ad revenue (rest to admin) | 30 |
| `ads.mid_reel_trigger_percent` | Watch % that triggers a mid-reel ad (app reads this) | 30 |
| `ads.min_payout_threshold` | Minimum accrued ad revenue (base currency) before it's credited | 1 |

**Ad types & split:**
- **Scroll ads** (between reels) — platform ads, **100% admin**, not tied to any reel.
- **Mid-reel ads** — tied to a reel, split **`reel_owner_share_percent`% to the owner / rest to admin**. **A creator earns automatically once eligible — there is no opt-in switch.** Ineligible owners (or an owner viewing their own reel) earn nothing → 100% admin. Eligibility is evaluated per reel owner when impressions are ingested.

**How money flows:** the app reports AdMob paid-event revenue per impression (in USD). Impressions are stored in `ad_impressions`; the owner's share accrues as `pending`. A scheduled command converts each owner's pending total once (USD → their wallet currency, frozen via the FX system) and credits a single `ad_revenue` earning transaction — shown in the app's Ad Earnings section.

**New command:** `php artisan stylebite:settle-ad-earnings` (`--dry-run` to preview)
- Holds a single atomic lock and locks rows during crediting, so overlapping runs can't double-pay. Safe to run hourly or daily.

**Money-safety:** validated by a 19-agent adversarial review; the confirmed race conditions (overlapping-run double-credit, mid-run lost credit), self-crediting, duplicate-key 500s, currency-blind revenue cap, and 0%-share orphan rows were all fixed and re-tested.

> ⚠️ **Known limitations (by design — client-reported revenue):**
> - Revenue/impressions come from the app and are inherently spoofable. The intended defense is **AdMob Reporting-API reconciliation (Phase 3, not built yet)** — pull AdMob totals server-side and flag discrepancies. Until then, trust + the per-impression cap + `impression_ref` de-dup are the only guards.
> - Watch hours can be inflated by re-watching (each view is capped at the video duration, but view count isn't). Same reconciliation caveat.
> - Eligibility is computed live on the impressions path (per distinct reel owner in a batch). At large scale this watch-hours aggregate should be cached / materialized rather than recomputed per batch.

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
