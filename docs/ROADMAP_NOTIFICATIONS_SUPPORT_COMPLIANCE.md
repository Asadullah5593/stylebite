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
| 3.8a | Push Sender — All / City / University / Creators / Specific | 🔴 **Mostly missing** | The sender supports exactly **two** audiences: one specific user, or all active users. No city, university, creator, or multi-user targeting. Delivery loops token-by-token **inside the HTTP request** — unusable past a few hundred recipients. |
| 3.8b | Email / Templates | 🔴 **Missing** | No template storage of any kind. Three hardcoded transactional emails (all auth OTP). Every subject and sentence is a PHP literal. `GlobalAppMail` is *not* queued. |
| 3.8c | Contest Announcement Templates | 🔴 **Missing** | Nothing contest-specific exists in the mail or announcement paths. |
| 3.8d | Automated Notifications (streak reminder, contest ending soon) | 🔴 **Missing** | No scheduled-notification infrastructure at all. All five notification write points fire synchronously from a user action. Nothing warns about a lapsing streak or a closing contest. |
| 3.9a | User Reports (Content, Users, **Bugs**) | 🟠 **Half-built — admin side only** | Full moderation queue exists and is good. **No mobile endpoint lets a user file a report**, so the queue has no inbound source. Bug reports are not representable: `reports.reason` enum has no `bug`, `target_id` is NOT NULL, and there is nowhere for device/app-version/screenshot. |
| 3.9b | Support Ticket System | 🔴 **Missing** | No ticket concept anywhere. *But* `conversations.type` already contains an unused `'support'` value and the messaging schema is ticket-shaped (member roles, system messages, attachments, read receipts). |
| X1 | Admin panel responsive (Desktop + Tablet) | 🟠 **Desktop yes, tablet no** | Desktop ≥1200px is genuinely well built (all 55 tables wrapped, filter bars wrap). The **768–1199px tablet band renders as a squeezed single-column mobile layout**, and **below 768px there is no navigation at all** — the sidebar is `d-none d-md-flex` with no off-canvas replacement. |
| X2 | Destructive actions: confirm + reason | 🟠 **1 of 33** | Only the ban/suspend/bulk-lifecycle path does both. 33 destructive actions enumerated: 2 compliant, ~10 confirm-without-reason, the rest neither. **`reverseTransaction` moves money with no dialog, no reason, and a hardcoded `'reason' => 'Admin reversal'`.** |
| X3 | Every admin action logged (timestamp, admin ID, IP) | 🟢 **Done** | `LogAdminActivity` middleware audits every mutating admin request plus sensitive reads — actor, role held at the time, IP, user agent, route, payload, and outcome (applied/blocked/rejected/failed). All 58 mutating routes covered. |
| X4 | Privacy Policy & Terms editable in admin | 🔴 **Missing** | Both pages are hardcoded Blade. **There is no Terms page at all** — no route, no view, no stub. No long-form editor anywhere (`app_configs.value_type` is `string\|number\|boolean\|json`, no text type). No acceptance/version tracking. |
| X5 | Data export CSV / Excel | 🟠 **Partial and partly untrustworthy** | 5 server-side CSV exports; **no Excel and no Excel library installed**. The users-list "Export" is client-side JavaScript that scrapes the rendered page — with pagination at 10, "export all users" yields a **10-row file**, writes no audit row, and does not escape quotes. Most entities (users, posts, comments, reports, contests) have no real export. No GDPR per-user export. |

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

**B1. Mobile reporting API.** ~5 days: the admin consumer is already built and good, so
this is endpoints + rate limiting + abuse guards + a `bug` path. **Do this before any
support work** — the moderation queue is currently a well-built UI over a table no
production code can write to, and there is no way for a user to report abuse (an App
Store / Play Store exposure for a UGC app).

**B2. Support tickets — own schema** (client decision, 2026-08-09). Dedicated tables for
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
