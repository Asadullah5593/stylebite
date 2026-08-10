# Roadmap Audit — Notifications (3.8), Reports & Support (3.9), Cross-Cutting

**Audited 2026-08-09** against the current `asad` branch. Read-only audit; nothing was
changed except the one defect noted as fixed. Keep this file updated as items ship.

Companion docs: [CRON_JOBS.md](CRON_JOBS.md) · [ADMIN_CHANGELOG.md](ADMIN_CHANGELOG.md) ·
[MOBILE_CHANGELOG.md](MOBILE_CHANGELOG.md)

---

## Decisions made

| Date | Question | Decision |
|---|---|---|
| 2026-08-09 | Q1 — University as a push audience | **Deferred.** University will be introduced later. Build the sender with the other audiences and leave a seam for it; no schema, no mobile change, no backfill for now. |
| 2026-08-09 | Q5 — Support tickets: reuse chat tables or own module | **Own schema.** Do *not* mix tickets into `conversations`/`messages`. The messaging schema stays prior art only; the unused `conversations.type = 'support'` value stays unused. |
| 2026-08-09 | Q9 — Where destructive-action reasons persist | **Decided internally (no client input needed):** `moderation_actions` for anything with a moderatable target (users, posts, comments, replies, memories, messages, contests), since it is already polymorphic with `reason` and `expires_at`. Non-moderation destructive actions (config/cache/job deletion, payout rejection, transaction reversal) keep their reason in `activity_logs` metadata, which the audit middleware already persists. |

| 2026-08-09 | Q2 — What is a "Creator"? | **Both, as two separate audiences.** *Active posters* (published within the last N days, default 30) is the behavioural segment for engagement messaging; `users.role = 'creator'` stays the curated/manual label for designated creators. "Monetised creators" (`ad_eligible`) is added later, once the app reports view time. |
| 2026-08-09 | Q3 — City targeting with sparse data | **Ship it anyway.** City lives on `profiles.city` (2 of 24 filled). The segment is correct; the audience is small because the data is. Campaign UI must show a recipient count *before* sending so nobody mistakes an empty audience for a broken feature. |
| 2026-08-09 | Q4 — Where bug reports live | **A ticket category, not a report type.** Bugs need device model, app version and a screenshot — none of which fit the `reports` enums — and they need a conversation. `reports` stays for content/user abuse only. |
| 2026-08-09 | Tickets — categories, workflow | Categories: Bug · Payment/Payout · Account & Login · Content appeal · Other. Statuses: open → in progress → waiting on user → resolved → closed. Priority: low/normal/high/urgent. **No SLA timers in v1** — age column + overdue highlight. User gets push + in-app on staff reply (email later). Screenshot attachments via the existing upload path. |
| 2026-08-09 | Q6 — Excel vs CSV | **CSV with a UTF-8 BOM** plus CSV-injection-safe escaping. No `.xlsx` library on shared hosting unless contractually required. |
| 2026-08-09 | Q7 — GDPR per-user export | **In scope, admin-triggered.** |
| 2026-08-09 | Q8 — Terms of Service | **We build the editor + versioning + acceptance tracking; the client supplies the copy** (there is no existing Terms document to migrate). |
| 2026-08-09 | Q10 — Announcements by email | **Push + in-app only for v1.** Email broadcast waits until a marketing-consent/opt-out flag exists — legally distinct from transactional OTP mail. |
| 2026-08-09 | Q11 — Responsive floor | **Tablet down to 768px** (iPad portrait). Phone-width admin is out of scope, but note there is currently *no* navigation below 768px. |
| 2026-08-09 | Q12 — Broadcast delivery window | **Minutes-to-hours accepted.** Build chunked + resumable for shared hosting. Revisit only if the client moves to a VPS. |

### Still open

Nothing blocking. Remaining unknowns are downstream product details (ticket
notification copy, export column sets) that can be settled as each piece is built.

---

## Status at a glance

| # | Requirement | Status | Where it actually stands |
|---|---|---|---|
| 3.8a | Push Sender — All / City / University / Creators / Specific | 🟢 **Done 2026-08-09** | All / City / Creators / Specific-users audiences with live recipient preview; University deferred by client decision (Q1) with the seam left in. Delivery moved to a queued, chunked job using `Http::pool()`. **Needs the per-minute `queue:work` cron or nothing is ever delivered.** |
| 3.8b | Email / Templates | 🟢 **Done 2026-08-09** | `email_templates` table + `EmailTemplates` registry; subjects and bodies editable in the panel with placeholder tokens. Sending is queued. |
| 3.8c | Contest Announcement Templates | 🟢 **Done 2026-08-09** | Contest announcement templates ship in the built-in set. |
| 3.8d | Automated Notifications (streak reminder, contest ending soon) | 🟢 **Done 2026-08-09** | `stylebite:send-streak-reminders` and `stylebite:send-contest-ending-reminders`, both idempotent and quiet-hours aware (there is no Laravel scheduler on this host, so each needs its own hPanel cron — see [CRON_JOBS.md](CRON_JOBS.md)). |
| 3.9a | User Reports (Content, Users, **Bugs**) | 🟢 **Done 2026-08-10** | `POST /api/reports` feeds the existing queue for user/post/comment/reply/message/contest, with duplicate, self-report, message-membership and rate-limit guards. Bugs went to tickets instead, as decided. |
| 3.9b | Support Ticket System | 🟢 **Done 2026-08-10** | Own schema (`support_tickets`, `support_ticket_messages`, `support_ticket_attachments`); 5 categories, 5 statuses, 4 priorities, quotable reference, screenshots, device metadata, internal notes, assignment; mobile API + admin queue; `tickets.*` permissions. Chat tables untouched. |
| X1 | Admin panel responsive (Desktop + Tablet) | 🟢 **Done 2026-08-10** | Sidebar became a Bootstrap `offcanvas-lg` drawer, so the 768–1199px band gets real navigation instead of nothing, and the breakpoint moved 768→992 to match. Modals are relocated to `<body>` on load because the layout's `backdrop-filter` created a containing block that broke `position: fixed`. |
| X2 | Destructive actions: confirm + reason | 🟢 **Done 2026-08-10** | Shared confirm modal (`window.confirmDestructive`) plus server-side required reasons. `reverseTransaction` now demands a reason and records it with the reversing admin. Reasons are persisted, not just logged — moderation actions write a `moderation_actions` row. `DestructiveActionReasonTest` pins the server half, because a dialog is worthless if the endpoint still accepts a bare request. |
| X3 | Every admin action logged (timestamp, admin ID, IP) | 🟢 **Done** | `LogAdminActivity` middleware audits every mutating admin request plus sensitive reads — actor, role held at the time, IP, user agent, route, payload, and outcome (applied/blocked/rejected/failed). All 58 mutating routes covered. |
| X4 | Privacy Policy & Terms editable in admin | 🟢 **Done 2026-08-10** | `legal_documents` + `legal_acceptances`. Versioned by insert: publishing creates the next version and never edits a published one, so the text a user accepted stays recoverable. Drafts are invisible to users. `/privacy-policy` keeps its URL and `/terms` now exists. Acceptance is recorded per version, with an export. **Client still owes the actual Terms copy — v1 is a placeholder.** |
| X5 | Data export CSV / Excel | 🟢 **Done 2026-08-10** | The DOM-scraping users "export" is gone; it streams the full filtered set (15 columns) and writes an audit row. All exports share `App\Support\CsvExport`, which adds the UTF-8 BOM (without it Excel reads Latin-1 and mangles non-ASCII names) and neutralises formula injection. Per-user GDPR JSON export across ~20 relations. Bulk export moved off `users.view` to a new `users.export` permission. **Caveat: this is BOM'd CSV that Excel opens correctly, not native `.xlsx`** — no spreadsheet library is installed, and none was added. |

**Fixed during this audit:** the activity-log CSV export silently truncated to ~500 rows
with duplicates (`latest()` combined with `lazyById()` broke the keyset pagination). Now
uses `lazyByIdDesc()` and is pinned by a regression test.

---

## Recommended build order

Dependencies first — several 3.8 features share one root cause, so building them
feature-by-feature would produce five things that each work in a demo and fail
identically in production.

### Phase A — Delivery backbone (unblocks all of 3.8)

**A1. Make sending asynchronous and batched.** ✅ **DONE 2026-08-09.**
`notification_campaigns` + `ProcessNotificationCampaign` (chunked at 200, keyset resume
cursor, self-requeueing, cache-locked against overlap); concurrent per-device sends via
`Http::pool` (`stylebite_send_firebase_push_batch`, 20 at a time); the announcement
request now only records intent. "Retry push" performs a real send and can no longer
downgrade a delivered notification. The inflated sent-count and the
sender-never-receives-their-own-broadcast bugs are fixed. 15 feature tests.
*Still outstanding from A1:* `GlobalAppMail` is deliberately **not** queued — OTP and
login codes must not wait for a once-per-minute cron. Bulk email, when it arrives, gets a
queued job that sends inside the worker instead.

**A2. Audience segmentation + the real Push Sender UI.** ✅ **DONE 2026-08-09.**
`NotificationAudience` resolves All active / By city / Active posters / Creator accounts /
Specific users, always excluding banned and suspended accounts; recipient-count preview
before sending; Campaigns tab with progress, per-outcome counts and a Stop control.
*University excluded — deferred (Q1).* Not yet done: the city fan-out in
`Api/ContestController.php:1158-1196` still duplicates this logic and should be moved onto
the resolver.

**A3. Email templates.** ✅ **DONE 2026-08-09.** `email_templates` + `EmailTemplates`
service rendering through `GlobalAppMail`; six seeded templates (verification, login code,
password reset, contest announcement / ending-soon / winner); admin editor with
placeholder help, live preview, test-send-to-me and restore-defaults; new
`email_templates.view|manage` permissions on admin/super_admin only. **Built-in copy is
retained in code as a fallback**, so a deactivated, blanked or deleted template cannot stop
a login or reset email. Transactional mail stays synchronous by design.

**A4. Automated notifications.** ✅ **DONE 2026-08-09.**
`stylebite:send-streak-reminders` (streak lapses tonight) and
`stylebite:send-contest-ending-reminders` (participants of contests closing inside a
configurable window), both toggleable from Admin → Settings, both idempotent via the new
`automated_notification_sends` ledger so an hourly cron cannot spam, both capped per run
with an explicit warning when capped. No notification enum changes were needed — the
existing `system`/`user` and `contest`/`contest` values cover both, so the mobile contract
is untouched.

**Section 3.8 is complete.** Remaining in that area, deliberately deferred: University
targeting (needs schema + a mobile release), email *broadcast* (needs a marketing-consent
flag first — Q10), and moving `Api/ContestController.php`'s duplicated city fan-out onto
the audience resolver.

### Phase B — Reports, support, compliance

**B1. Mobile reporting API.** ✅ **DONE 2026-08-10.** ~5 days: the admin consumer is already built and good, so
this is endpoints + rate limiting + abuse guards + a `bug` path. **Do this before any
support work** — the moderation queue is currently a well-built UI over a table no
production code can write to, and there is no way for a user to report abuse (an App
Store / Play Store exposure for a UGC app).

**B2. Support tickets — own schema** ✅ **DONE 2026-08-10.** (client decision, 2026-08-09). Dedicated tables for
ticket state *and* the reply thread; the chat tables are not involved. Gate with a new
permission; the `support_agent` role already exists with the right read grants and
deliberately no ban/money powers.

**B3. Destructive-action sweep.** Generalise the existing lifecycle modal into one shared
partial (runtime action, per-action copy, required-reason flag) and apply it across the
31 non-compliant actions. **Start with `reverseTransaction`** — highest risk, zero
guards. The audit middleware already persists sanitised request input, so any reason
field added to any form is logged automatically.

**B4. Tablet responsiveness.** Add `md`/`lg` grid steps and an off-canvas nav below
768px. `dashboard.blade.php` already has the one correct tablet band — use it as the
template. One finding needs 60 seconds in a browser first: detail modals declared inside
`.glass` (which sets `backdrop-filter` + `overflow: hidden`) may clip on ~14 pages —
check `FailedJobsPage`. If they clip, the fix is either dropping `overflow:hidden`
(small) or relocating the modals (medium).

**B5. Legal pages + compliance exports.** Editable Privacy/Terms with versioning and an
API endpoint for the app; replace the fake users-list JS export with a real streamed one;
add UTF-8 BOM and CSV-injection escaping; audit every export.

---

## Highest-risk findings

1. **Push may never have actually fired, and the credential does not deploy itself.**
   11 notifications are marked `delivery_status='sent'` but `push_notification_logs` is
   **empty**, while the helper writes a log row for every attempted token — so those rows
   almost certainly came from a seeder and FCM has probably never reached real Firebase
   here. Verified 2026-08-09: `public/service_file.json` exists locally and is a valid
   service account (project `stylebite-f28fa`), **but it is gitignored and untracked
   (`.gitignore:29`), so `git pull` does not deploy it.** If that file is absent on the
   server, `stylebite_firebase_service_account()` throws and *every* push fails — which
   now means every campaign reports 100% failed. Note the missing `FIREBASE_PROJECT_ID`
   env var is *not* a problem: the code prefers the `project_id` inside the file. The file
   is the single point of failure. Confirm it is present on live, then do one real-device
   smoke test.
2. **The moderation queue has no inbound source.** Filters, assignment, status
   transitions, ban-from-report, dashboard tiles and a header alert — over a table only
   test files write to.
3. **Compliance exports could mislead an auditor.** The audit-log truncation is fixed; the
   users-list DOM-scraping "export" is not. The one export where PII egress matters most
   is the only one invisible to the audit trail.
4. **Several features are inert but present in the UI** — worse than being absent.
   Settings → Email (sender name, reply-to, SMTP host/port) saves successfully and is
   **read by no code**. "Contest Digest Enabled" advertises a feature that never existed.
   `app_configs` has **0 rows in the live database**, so every admin setting is running on
   its hardcoded fallback and no setting has ever been saved successfully.

---

## Open decisions (needed before estimating)

| # | Question | Why it blocks |
|---|---|---|
| ~~Q1~~ | ~~Does "University" exist as data at all?~~ | ✅ **DECIDED 2026-08-09 — deferred.** Introduced later; build the other audiences now and leave a seam. |
| Q2 | **What is a "Creator"?** `users.role='creator'` (currently **zero** users), `profiles.ad_eligible`, or the Spatie `creator` role? | Three competing definitions, and `ad_eligible` can never be true today (needs 1000 watch-hours; `watch_seconds` is never populated). This segment ships empty under any definition. |
| Q3 | **How does City get populated?** | Only 2 of 24 live profiles have a city, set via an optional profile edit. A City segment against this data looks broken. |
| Q4 | **Do Bug reports go in `reports` or their own table?** | Enums have no `bug`, `target_id` is NOT NULL, no room for device/app-version/screenshot. Recommend a separate table. The table is empty in production — cheapest moment to decide. (Also: `memory` is missing from `target_type`, so memories can't be reported despite having `is_reported`.) |
| ~~Q5~~ | ~~Tickets: reuse chat tables or own module?~~ | ✅ **DECIDED 2026-08-09 — own schema.** Tickets get their own tables end to end, including their own reply/message table. `conversations`/`messages` are not touched, which also means `Api/ChatController.php`'s eight `where('type','direct')` filters need no changes and the `'support'` enum value stays unused. |
| Q6 | **True `.xlsx`, or is CSV enough?** | No Excel library installed; adding one is memory-hungry on shared hosting. If the real complaint is "Excel mangles our data", a UTF-8 BOM is a one-line fix. |
| Q7 | **GDPR per-user data export — in scope? Self-service or admin-only?** | Absent entirely, while a public `/delete-account` page exists — so erasure was recognised and access/portability was not. No manual fallback: ~40 tables. |
| Q8 | **Who authors the Terms, and do you need versioning with forced re-acceptance?** | No Terms document exists to migrate. No `terms_accepted_at`, no policy version, no consent flag — you currently cannot prove what any user agreed to. |
| Q9 | **Where do destructive-action reasons persist** — `moderation_actions` for all target types, or `activity_logs` metadata? | Decide before B3 or you get two parallel audit trails. |
| Q10 | **Should admin announcements also go by email?** | If yes, `email_notifications_enabled` (stored, displayed, never checked) must be honoured and a marketing-consent flag added — an opt-out requirement distinct from transactional OTPs. |
| Q11 | **Minimum tablet width, and is phone-width admin in scope?** | 768px iPad portrait behaves very differently from 744px or 600–720px Android tablets. Determines whether B4 is a breakpoint fix or also a mobile-nav build. |
| Q12 | **Are minutes-to-hours delivery windows acceptable for a million-recipient broadcast?** | Shared hosting with a once-per-minute cron worker (no supervised daemon) is a hard ceiling. A chunked resumable campaign is right *for this host*; "broadcast lands in minutes at millions" is a VPS conversation. Settle before A1. |

---

## Reuse, don't rebuild

- `LogAdminActivity` + `ActivityLog::record()` + `config/audit.php` — the audit
  requirement is done, and it auto-logs any reason field added to any form.
- `UserModerationService` + `users/partials/lifecycle-modal.blade.php` — the reference
  standard for confirm + mandatory reason; generalise the modal rather than writing 31.
- `moderation_actions` — already polymorphic with `reason` and `expires_at`.
- FCM v1 transport, service-account JWT exchange, `push_notification_logs`, `PushLogsPage`
  — keep; A1 replaces only the per-token loop.
- `notifications` table + the mobile read API — reminders, results, report feedback and
  ticket updates all deliver through it. Only two enum values needed.
- Database queue + `app/Jobs/OptimizePostMedia.php` — a correct `ShouldQueue`/`afterCommit`
  precedent to copy for the campaign job.
- `reports` + `Report` + `Admin/ModerationController` + `ReportsPage` — the entire
  moderation consumer; B1 doesn't touch it.
- ~~`conversations`/`messages` for the ticket thread~~ — **not being used** (client chose
  own schema). Still worth reading as prior art for reply threading, attachments and read
  receipts before designing the ticket tables.
- `support_agent` role + Spatie permissions — gate new modules with new named permissions.
- `GlobalAppMail` + `emails/global.blade.php` — fully parameterised; needs `ShouldQueue`
  and branding wiring, not a rewrite.
- `ActivityController::export`'s `streamDownload` + `fputcsv` + `lazyByIdDesc` — the
  correct export shape; the earnings exports `->get()` everything first and should adopt it.
- `dashboard.blade.php` tab strip + KPI grid — the only component with a real tablet band.
- **Do not** reuse `stylebite_app_config()` for legal documents: `config_value` is `text`
  (~64KB) with no length validation, so an oversized policy truncates silently.


---

## Section 3.9 delivered — 2026-08-10

Both requirements are live. Notes for whoever picks this up next:

- **Reporting deliberately excludes `memory` targets.** `reports.target_type` has
  no `memory` value and the admin queue cannot resolve or action one, so allowing
  it would create reports nobody could work. `memory_comments` carries an
  `is_reported` column with no matching report path — a pre-existing gap, left
  alone rather than half-built.
- **Bug reports are a ticket category, not a report reason** (client decision).
  The reports table has nowhere to put a screenshot, device model or app version,
  and a bug needs a conversation.
- **Staff identity is generic to users.** The API returns `author_type: "staff"`
  and never the individual agent's name.
- **Found while building:** every `findOrFail`/`firstOrFail` in the API was
  answering 500 instead of 404, because Laravel converts `ModelNotFoundException`
  to `NotFoundHttpException` before render callbacks run — so the app's
  `ModelNotFoundException` handler was dead code. Fixed with an
  `HttpExceptionInterface` handler; rate limiting now returns 429 rather than 500
  too. `ApiErrorResponseTest` pins this.

### Still open on this roadmap

**Nothing, in code.** B3 (destructive actions), B4 (tablet), and B5 (legal pages +
compliance exports) all landed 2026-08-10. What remains needs someone other than a
developer:

- **The client owes the Terms of Service copy.** The versioned editor is live and
  Terms v1 is a placeholder. Privacy Policy was seeded from the old hardcoded view.
- **Native `.xlsx` was not built** (X5). Exports are BOM'd CSV, which Excel opens
  correctly. If the client specifically wants xlsx formatting or multiple sheets,
  that is `phpoffice/phpspreadsheet` and a separate piece of work.
- **Consent records need app work to ever fill.** `legal_acceptances` only receives
  rows when the mobile app calls `POST /api/legal/{key}/accept`. Reading the
  document does not imply consent. Until then the acceptances export returns
  headers only — fine unless the policy changes materially and someone asks who
  agreed to what.
- **Nothing here is deployed.** All of 3.8, 3.9 and X1–X5 sit on `asad`, and three
  legal migrations (`2026_08_10_000005`–`000007`) need `php artisan migrate`.
