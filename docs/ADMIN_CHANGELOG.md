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
| **Hourly** | `/usr/bin/php /home/u353708470/domains/stylebiteapp.com/public_html/artisan stylebite:lift-expired-suspensions` | Reactivates users whose suspension window ended (auth paths also lift lazily; this keeps counts honest for users who never return) |
| **Weekly** (e.g. Sun 04:00) | `/usr/bin/php /home/u353708470/domains/stylebiteapp.com/public_html/artisan stylebite:prune-activity-logs` | Trims the audit trail past the retention window (default 365 days, `AUDIT_RETENTION_DAYS`) |
| **Daily** (e.g. 02:30) | `/usr/bin/php /home/u353708470/domains/stylebiteapp.com/public_html/artisan stylebite:prune-user-sessions` | Deletes sessions dead for 30+ days |
| **Hourly** (09:20–21:20 PKT → `20 4-16 * * *`) | `/usr/bin/php /home/u353708470/domains/stylebiteapp.com/public_html/artisan stylebite:send-streak-reminders` | Warns users whose streak lapses tonight. Hour-limited on purpose — cron is UTC, so this avoids 3am pushes |
| **Hourly** | `/usr/bin/php /home/u353708470/domains/stylebiteapp.com/public_html/artisan stylebite:send-contest-ending-reminders` | Tells participants a contest closes soon |
| **Weekly** (e.g. Sun 04:30) | `/usr/bin/php /home/u353708470/domains/stylebiteapp.com/public_html/artisan queue:prune-failed --hours=336` | Trims `failed_jobs`, which nothing else clears |

**[docs/CRON_JOBS.md](CRON_JOBS.md) is the source of truth** — it has copy-paste
crontab lines, a status column, verification commands, the silent-failure
gotchas, and the three commands that must never be scheduled.

Two that matter most: without `queue:work` **no push campaign is ever delivered**,
and without the daily rate sync **creator crediting is blocked by design** (the
system never credits an unconverted amount).

---

## 2026-08-20 — Chat-message rows removed from the user notification feed

Requested by the mobile team. `type = 'message'` notification rows are still
**created** on every chat message — they drive the FCM push and the push-log audit
trail (Admin → Push Logs is unaffected) — but the mobile bell feed
(`GET /api/notifications`) no longer lists or counts them, since chat carries its
own unread badge. "Mark all read" and "Clear" on the user side now also skip these
hidden rows, so clearing the bell no longer deletes push-delivery history.

Also fixed while testing: the mobile `DELETE /notifications/clear` endpoint had
never worked — route ordering let the `{notificationId}` wildcard swallow the word
"clear" and the call 500'd. Fixed by registering `clear` before the wildcard.

**No migrations, no config, no new dependencies** — a plain `bash ~/deploy.sh`.

---

## 2026-08-11 — Fixed: the sidebar could not be scrolled 🧭

With every module enabled the navigation is taller than a laptop viewport, and the
bottom entries (System → Legal Documents, Settings) were simply unreachable —
nothing scrolled.

The nav already had `overflow-auto`, but its parent used `min-height: 100vh`, which
does not bound anything: the column grew to fit its content and `overflow: hidden`
clipped the rest off. The scrollable child also needed `min-height: 0`, since a flex
item's automatic minimum size is its content and it will refuse to shrink without
it. Both fixed, plus `100dvh` so mobile browser chrome does not eat the last item.

The scrollbar is now a thin dim line instead of hidden entirely — with a menu this
tall there has to be some hint that more exists below.

---

## 2026-08-11 — Fixed: dashboard push notifications were not arriving 🔔

**Symptom:** campaigns sent from Notifications → Push Sender showed as `completed`
with a real recipient count, but nothing reached the handsets. Chat notifications
worked, which made it look like an app-side problem.

**It was not the queue, the credentials, or the payload.** The cron worker was
running, campaigns completed, and FCM was being reached successfully — some pushes
in the same batch returned real message IDs. The live push log had the answer:

```
"The registration token is not a valid FCM registration token"
INVALID_ARGUMENT — field: message.token
```

**Root cause:** a device's push token was only ever written during login. FCM
rotates tokens by itself (app reinstall, cleared storage, restore onto a new phone,
periodic refresh), and a user who stays signed in never returns to the login path.
The database kept the dead string indefinitely, so every notification to that
device failed. Chat appeared to work because those particular recipients happened
to have recently-issued tokens.

**Two fixes:**

1. **A push token can now be refreshed without logging in again**
   (`POST /api/devices/push-token`). The mobile app has to call this on launch and
   whenever Firebase hands it a new token — that part needs an app release.
2. **Dead tokens are deleted automatically.** When FCM says `UNREGISTERED` or
   rejects the token specifically, the device row is removed instead of being
   retried forever. Users → Devices will therefore shrink as stale rows clear out;
   that is the fix working, not data loss. The push log survives the deletion, so
   the history stays auditable.

The deletion is deliberately narrow: FCM also answers `INVALID_ARGUMENT` for a
malformed **image URL**, and treating that as a dead token would unregister a
perfectly good handset because someone typed a bad URL into the campaign form. Only
a violation naming `message.token` counts.

> **Until the app ships the refresh call**, expect some `failed` rows on new
> campaigns — those are devices whose tokens rotated. They clear themselves now,
> and each affected user re-registers on their next login.

**Diagnosing this in future:** Notifications → Push Logs shows FCM's raw response
per device. A campaign that reports `sent_count: 0, failed_count: N` with token
errors there means stale tokens, not a broken sender.

---

## 2026-08-11 — Logout API (affects Users → Sessions / Devices) 🔑

The mobile app had no logout endpoint, so signing out only cleared the app's local
token. Two visible consequences in the panel:

- **Users → Sessions** kept showing signed-out sessions as live until they aged out
  at the 24-hour cap, so the session list overstated who was actually online
- **Users → Devices** kept the device's FCM token registered, so a push campaign
  still targeted handsets nobody was signed in on — the recipient count was real
  but some of those deliveries went nowhere useful

`POST /api/auth/logout` now revokes that session immediately and deletes that
device's token row. It is **per device**: logging out on a phone leaves the same
user's tablet session live and still pushable, which is why the sessions list can
legitimately show more than one row per user.

Nothing to configure. Once the app adopts it, session and device counts start
reflecting reality rather than the 24-hour ceiling.

---

## 2026-08-10 — Editable legal pages + compliance exports 📄

The last of the cross-cutting requirements: Privacy Policy and Terms are editable
from the panel, and data can leave the system in a form a lawyer or regulator can
read.

### Privacy Policy & Terms are now editable — **System → Legal Documents**

Previously `/privacy-policy` was a hardcoded Blade file, and there was no Terms
page at all. Both are now database-backed and versioned.

- **System → Legal Documents** lists both documents, their live version, and how
  many users have accepted it
- **Edit** writes a **draft** by default — nothing reaches users until you tick
  *Publish*. Save as often as you like; repeated draft saves overwrite the draft
  rather than stacking versions
- **Publishing never edits the live version — it creates the next one.** v1 stays
  in the database forever. This is deliberate: the only way to answer "what did
  this user actually agree to in March?" is to still have March's text
- **Requires re-acceptance** marks a version as a material change
- Every version is viewable and diff-able by eye from the version list
- Publishing and draft-saving are both written to the activity log with the
  summary of changes

Public pages: `/privacy-policy` and `/terms` render the published version (the old
hardcoded view is kept as a fallback in case the table is ever empty).

> **Still needed from the client:** the actual Terms of Service copy. The editor is
> built and v1 is a placeholder — paste the real text in and publish.

### Who accepted what

`legal_acceptances` records user, document, **version**, IP, user agent and
timestamp. Because acceptance is tied to a version, publishing a new version puts
the document back in every user's "pending" list rather than silently inheriting
the old consent. Old acceptance rows are never deleted — they are the evidence.

Export per document, optionally per version: **Legal Documents → Acceptances →
Export CSV**.

### CSV exports are now real, and safe to open

The users-list "Export CSV" button **used to be a lie** — it read the 10 rows
already rendered on the page out of the DOM and called that an export. It now
streams the full filtered result set from the database (15 columns).

All exports moved to one shared writer (`App\Support\CsvExport`), which fixes two
silent problems across the board:

- **UTF-8 BOM** — without it Excel on Windows reads the file as Latin-1 and
  mangles every non-ASCII name. This is what people mean by "we need Excel, not
  CSV"
- **Formula injection** — a cell starting with `=` `+` `-` `@` tab or CR is
  *executed* by Excel, Sheets and LibreOffice. A user whose display name is
  `=HYPERLINK("http://evil","click")` became a live link in your spreadsheet.
  Such values are now prefixed with an apostrophe: neutral, still readable

Retrofitted: users, activity log, earnings transactions, earnings withdrawals,
earnings reconciliation, legal acceptances. The two earnings exports also stopped
loading every row into memory first — they stream by primary key now, so a large
export can't exhaust memory.

### "Send me my data" — one click

**Users → (a user) → Personal Data** downloads everything held about that person as
structured JSON: account, profile, settings, linked accounts, badges, posts,
memories, comments, replies, messages sent, notifications, support tickets,
reports filed, wallet, transactions, withdrawals, sessions, devices, legal
acceptances, blocks. Empty sections are still present — an absent key would read
as "we hold nothing".

Erasure was already possible via the public delete-account page; access and
portability (GDPR Art. 15/20) had no answer short of hand-querying ~20 tables.

### ⚠️ Permission change: bulk export is no longer `users.view`

Both exports were gated on `users.view`, which **Support Agent** and **Finance
Manager** hold. Looking one account up to help its owner and downloading a
spreadsheet of every user's email address are not the same act, so bulk export now
needs a new **`users.export`** permission, granted to **Admin** and **Super Admin**
only.

If you want a specific support lead to keep this ability, add `users.export` to a
custom role — don't widen the built-in one.

---

## 2026-08-10 — The panel works on tablets now 📱

No migration. Layout only — no page lost a feature.

### Navigation existed but was unreachable

Below 768px the sidebar was simply hidden, so **there was no navigation at all**
— you could reach a page but not leave it except by editing the URL. The
hamburger button in the header had been there the whole time, pointing at
`#sidebar`, but the sidebar was never an off-canvas element, so pressing it did
nothing.

The sidebar is now a proper drawer below **992px** and the fixed rail above it,
with a close button and the full permission-filtered menu. The hamburger works.

### The squeezed tablet band is gone

The layout had exactly one breakpoint at 768px, so an iPad in portrait rendered
the desktop shell: a 255px fixed sidebar leaving roughly 500px of content, which
is where the cramped tables and wrapped filter bars came from. The desktop shell
now starts at 992px instead, so tablets get the full width with the menu one tap
away, and nothing between 768 and 991px is squeezed any more.

### Modals were being clipped

Several pages declare their modals inside a `.glass` panel — sometimes inside a
table cell. `.glass` sets `backdrop-filter`, which makes it the containing block
for `position: fixed`, and `overflow: hidden` on top of that, so those dialogs
were cut off instead of covering the screen. Every modal is now moved to the end
of the document on load, which fixes all of them at once regardless of where they
are written.

Phone-width (<768px) is still not a design target — but it is now navigable
rather than a dead end.

---

## 2026-08-10 — Destructive actions now demand a reason 🛑

**Run nothing — no migration.** But note this changes how several forms behave:
they will refuse to submit without a reason.

The requirement was that every destructive action has a confirmation dialog *and*
reason logging. Only ban/suspend did both. The rest either fired on a single
click or asked a bare "are you sure?" that captured nothing.

### The one that mattered most

**Reversing an earnings transaction moved real money on one click.** The method
took no request object at all, so a reason was impossible, and it wrote the
literal string `'Admin reversal'` into the audit metadata — meaning no reversal
could ever be explained afterwards. It now requires a typed reason (minimum 3
characters), stores it on the reversal transaction *and* in the activity log
alongside who did it, and shows a confirmation that spells out that money moves.

### Now confirm + reason

| Action | What changed |
|---|---|
| Reverse a transaction | Reason required; confirmation names the transaction and warns it cannot be undone |
| Delete a user | Reason required; you also can no longer delete your own account |
| Remove a follow | Reason required; confirmation notes follower counts change |
| Remove a block | Reason required; confirmation warns the two users can contact each other again. The blocking user's own stated reason is kept in the log for context |
| Moderate a post | Reason required, in the list and on the detail page |
| Moderate a comment or reply | Reason required |
| Reject or fail a payout | Already required a reason — but a refusal was reported in the **green success box**, reading as though the payout had been rejected when nothing had happened. It is now an error on the field |

### Takedowns finally appear in the moderation history

A post or comment removed from the content lists previously wrote **nothing** to
**Moderation → Actions** — only the report queue did — so most real decisions
were missing from the moderation history. Content moderation now records a
`moderation_actions` row with the moderator, target and reason, mapped from the
moderation status (clean → restore, flagged → hide, restricted → restrict,
blocked → remove).

### Deliberately left as confirm-only

Operational actions that do not touch users or money — clearing a cache, deleting
a queued or failed job, removing a cache lock or an app config row — keep a
confirmation but do not demand a written reason. They are already fully audited
(actor, IP, route, payload, outcome) by the activity middleware, and requiring an
essay to clear a cache would only train people to type "x". Say the word if you
want reasons there too.

---

## 2026-08-10 — Section 3.9: user reports and support tickets 🎟️

Completes 3.9. **Run the migration on deploy** — it adds the ticket tables, three
new permissions, and two notification enum values. No new cron entries.

### Users can finally report things

The moderation queue had no inbound source: a full review UI over a table only
test files ever wrote to, and no way at all for a user to report abuse — an app
store exposure for a user-generated-content app. The app can now file reports on
**users, posts, comments, replies, messages and contests**, and they land in
**Moderation → Reports** exactly as the existing queue expects, with the target
flagged as reported in the content lists.

Abuse guards, because a report endpoint is a spam vector: one open report per
person per target (a repeat returns the original rather than piling up), you
cannot report your own content or yourself, a message can only be reported by
someone actually in that conversation, and the endpoint is rate limited.

**Bug reports deliberately do not come here** — they are a ticket category, since
a bug needs a conversation and device details that the reports table cannot hold.

### Support Tickets (new sidebar item, Trust & Safety)

Its own schema end to end — the chat tables are untouched, as agreed.

- **Categories:** Bug · Payment/Payout · Account & Login · Content appeal · Other
- **Statuses:** Open → In progress → Waiting on user → Resolved → Closed
- **Priorities:** Low / Normal / High / Urgent, and the queue sorts urgent first
- **Reference** like `TK-000042` that a user can quote
- **Screenshots** — up to 5 images per message
- **Device details** (app version, platform, model, OS) captured automatically,
  which is what makes a bug report actionable

Four tiles at the top double as filters: **Waiting on us**, Unassigned, Urgent,
Open total. The sidebar shows a red count of tickets waiting on staff, so the
queue is visible without opening it.

**Internal notes** are staff-only: they never reach the app, never notify the
user, and never move the ticket's state. The switch sits next to the reply box.

Behaviour worth knowing: a staff reply moves the ticket to *Waiting on user* and
sends them a push. A user reply to a **resolved** ticket reopens it — evidently
it was not solved. A **closed** ticket cannot be replied to. Status changes leave
a system note in the thread, so it explains itself. Users are capped at 5 open
tickets.

### Who can do what

| Permission | Roles |
|---|---|
| `tickets.view` | Admin, Super Admin, Support Agent, Content Moderator |
| `tickets.reply` | Admin, Super Admin, Support Agent |
| `tickets.manage` (status, priority, assignment) | Admin, Super Admin, Support Agent |

Content Moderator gets read-only access so an appeal can be seen in context
without being answered by the wrong person.

### Also fixed: API 404s were returning 500

Found while building this. Laravel converts `ModelNotFoundException` into a
`NotFoundHttpException` **before** render callbacks run, so this app's
`ModelNotFoundException` handler could never fire. Every `findOrFail` /
`firstOrFail` across the API — profiles, follows, chat, posts, feed — answered
**500 instead of 404**, and middleware rate limiting answered **500 instead of
429**. Both are now correct, along with unknown routes (404) and wrong methods
(405).

---

## 2026-08-10 — Admin panel 2FA + 24-hour dashboard sessions 🔐

Two-factor and the session cap now apply to the **admin panel**, which is where
they were always meant to be. The mobile app equivalents are switched off (see
the mobile changelog).

### Signing in

`/admin/login` is now two steps: password, then a **6-digit code emailed to the
staff member**. The session stays unauthenticated until the code is confirmed —
a correct password alone reaches nothing. Codes expire in **10 minutes**, allow
**5 attempts**, are single-use, and can be re-sent after a 60-second cooldown.

The copy lives in **Notifications → Email Templates → "Admin panel login code"**,
so you can reword it like any other email, and the built-in wording remains as a
fallback if the template is deactivated.

Everything is audited: `admin_login_2fa_challenged`, `admin_login_2fa_failed`,
`admin_login_2fa_locked`, and `admin_signed_in` (which now records whether 2FA
was used). An account banned or stripped of access *between* the two steps
cannot complete the sign-in.

### 24-hour dashboard sessions

An admin session now ends **24 hours after sign-in, absolutely**. This is
deliberately not Laravel's own session lifetime, which is idle-based and renews
on every request — a dashboard left open in a tab would otherwise stay signed in
forever. Staying active does not extend it; at the cap you are returned to the
login screen with an explanation.

Sessions that were already open when this deployed are stamped on their next
request rather than being cut off, so nobody was kicked out by the upgrade.

### Switches

| Env var | Default | Effect |
|---|---|---|
| `ADMIN_TWO_FACTOR` | `true` | Set `false` to skip the emailed code (e.g. if staff email is down) |
| `ADMIN_SESSION_HOURS` | `24` | Absolute dashboard session length |

---

## 2026-08-09 — Full audit trail of every panel action 🔍

**Activity Logs** existed but only covered part of the panel: 9 of the 58
state-changing admin actions wrote nothing at all — including **admin sign-ins,
failed sign-in attempts, creating a user, and every post/comment moderation
decision** — and nothing recorded attempts that were *refused*. It is now
complete and self-maintaining. **Deploy needs:** migration + one new weekly
cron (see the cron table above).

### What is captured

Every state-changing request in the panel is now recorded automatically by
middleware, so coverage cannot drift as new pages are added — a route added
tomorrow is audited the day it ships. Each row holds:

- **who** — the staff member, plus the role they held at the time (roles change;
  the log keeps what was true then)
- **what** — the event, a plain-English description, and the target record
- **the request** — HTTP method, route name, full URL, IP address, user agent
- **the outcome** — **Applied**, **Blocked** (no permission), **Rejected**
  (invalid input), or **Failed** (server error)
- **what was submitted** — the posted fields, with passwords and tokens
  stripped and long values truncated

Rejected and blocked attempts are the point, not noise: a Support Agent trying
the ban endpoint, or five failed sign-ins on one account, now leaves a trace.

### Newly logged (previously invisible)

Admin sign-in · failed sign-in (with the reason: unknown account, wrong
password, or no panel access) · sign-out · user created · post edited · post
moderated · comment moderated · reply moderated · memory comment moderated ·
changes to your own admin account.

### Reading it

**Activity Logs** now filters by staff member, outcome, actor type, HTTP
method, event, entity and date range, shows 25 per page, and every row opens a
detail panel with the full request context and submitted payload. **Export CSV**
respects the current filters and streams, so a long trail exports without
timing out. Two new tiles — **Blocked Attempts** and **Failed Sign-ins** — link
straight to those filters.

Exporting the audit trail is itself audited.

### Notes

- **Page views are not logged** — that would bury the trail. The exceptions,
  where looking *is* the sensitive act, are private conversations, member
  account pages, session/password-reset lists, and data exports. The list lives
  in `config/audit.php` if you want to add to it.
- Actions still write their own detailed row where one exists (old status → new
  status, the reason, the amount); the middleware only fills gaps, so **one
  action produces one row, never two**.
- Failed sign-ins are recorded as actor type *system* — nobody proved who they
  were — with the attempted email in the detail panel.
- Retention defaults to **365 days**; the prune command refuses any window
  under 30 days.

---

## 2026-08-09 — Five job-shaped staff roles 👥

Ready-made roles so staff can be onboarded without hand-picking permissions.
Assign them on the user create/edit form; tune their permissions any time under
**Roles & Permissions**. All five are built in (cannot be deleted) and none of
them changes a person's app-side account type — a Finance Manager stays a
regular `user` in the app while holding panel access.

| Role | Can do | Cannot do |
|---|---|---|
| **Super Admin** | Everything, incl. roles + system tools (36/36). Locked, like `admin`. | — |
| **Content Moderator** | Posts, reels, comments, memories, media, engagement, reports queue, activity log. Can ban/suspend **through the reports queue**. | Money, contests, settings, roles, create/delete users, user-list ban button |
| **Contest Manager** | Create/edit contests, participants, invitations, submissions, recalculation, **declaring winners**, plus announcements to publish results. | Money, reports, settings, roles, comments |
| **Finance / Payout Manager** | Wallets, transactions, reversals, withdrawals/payouts, reconciliation, exports, activity log. | Content, contests, reports, settings, roles |
| **Support Agent** | Look up users and fix accounts (revoke a stuck session, expire a password reset, disable a device, badges, streak restore), read conversations, view reports. | **No ban/suspend**, no delete, no money, no contests, no settings |

Notes worth knowing:

- **Reels are posts** with video in this schema, so the Content Moderator's
  post permissions already cover reels — there is no separate reels permission.
- **Contest winners** are set on the contest edit form, so `contests.update`
  covers them.
- **Contest Manager can send announcements** (push to users) because
  publishing results usually needs it. Say the word and it can be removed.
- **There is no ticketing system in the codebase**, so "Support Agent" is
  scoped to the user-support tools that do exist (account lookup + the fix-it
  actions above). If you want real tickets — inbox, assignment, replies,
  statuses, SLA — that is a separate feature to build, and the role is ready to
  gate it when it exists.
- `super_admin` and `admin` are both full-access and both locked. `admin` also
  doubles as the app-level account type; `super_admin` is the pure panel role.

---

## 2026-08-09 — Editable email templates + automatic reminders ✉️⏰

Completes section 3.8. **Run the migration on deploy, and add the two new cron
entries** (streak reminders, contest ending reminders) from
[CRON_JOBS.md](CRON_JOBS.md) — the reminders do nothing without them.

### Email templates (Notifications → Email Templates)

Every email Stylebite sends is now editable in the panel: subject, heading,
body, and an optional button. Six templates ship seeded —
verification code, login code, password reset, contest announcement, contest
ending soon, contest winner.

- **Placeholders**: `{{name}}`, `{{username}}`, `{{email}}`, `{{app_name}}`,
  `{{expiry_minutes}}`, plus `{{contest_title}}` and `{{contest_ends_at}}` on
  contest templates. Anything unrecognised is stripped before sending, so a typo
  never ships literal `{{braces}}` to a user.
- **Send test to me** delivers the template to your own admin email with sample
  data, so copy can be checked in a real inbox first.
- **Restore built-in wording** undoes an edit you regret.
- **You cannot break login mail.** Every template keeps its original wording in
  code as a safety net. Deactivate a template, blank it out, even delete the row
  — Stylebite falls back to the built-in copy and the email still goes out. This
  is deliberate: the login code template is now on the critical path for *every*
  sign-in.
- The 6-digit code is still rendered in its own highlighted box automatically —
  don't write it into the body.
- Requires the new `email_templates.view` / `email_templates.manage`
  permissions, granted to **admin** and **super_admin** only. The job-shaped
  staff roles deliberately do not get to rewrite user-facing email.

Note: transactional email stays **synchronous** on purpose. Queuing it would put
login codes behind the once-a-minute queue cron, which would make signing in feel
broken.

### Automatic reminders

Two new commands, both driven by admin settings and both safe to run hourly:

| Reminder | Who gets it | Setting |
|---|---|---|
| **Streak ending tonight** | Users with a live streak whose last qualifying day was yesterday and who haven't posted today — i.e. the streak breaks at midnight unless they act | Settings → Streaks → *Send "Streak Ending Tonight" Reminders* |
| **Contest ending soon** | Participants (joined/approved) of active contests closing inside the window | Settings → Contests → *Send "Contest Ending Soon" Reminders* + *Hours Before Close* (default 24) |

Both are **idempotent**: a new `automated_notification_sends` ledger keys one
reminder per user per day (streaks) or per user per contest (contests), so an
hourly cron cannot spam anyone. Banned and suspended users are never reminded.
Each run is capped (default 500) and **says so when it hits the cap**, so a
backlog is visible rather than silently dropped — the next run continues.

---

## 2026-08-09 — Push notification sender: audiences, campaigns, real delivery 📣

The announcement box became a proper campaign sender. **Run the migration on
deploy, and make sure the `queue:work` cron entry exists** — campaigns are
delivered by the queue worker, so without that cron every campaign sits at 0%
forever.

### Audiences

Notifications → Notifications now targets **All active users · By city ·
Active posters · Creator accounts · Specific users** (it previously offered only
one user or all active users). A **Check recipients** button reports the exact
audience size *before* you send, because a segment can legitimately be empty —
only 2 of 24 profiles currently have a city set, and the city picker only lists
cities users have actually entered.

Two things worth knowing about audiences:

- **"Creator" means two different things on purpose.** *Active posters* is
  behavioural (published within the last N days, default 30) and needs no admin
  upkeep. *Creator accounts* is the curated `role = creator` label an admin
  assigns on the user edit form. Pick whichever matches the message.
- **Banned and suspended accounts are never included**, whichever audience is
  chosen. Recipients who turned push off, or have no registered device, are
  recorded as **skipped** — that is not a failure.
- University targeting is deliberately absent (deferred; no university data
  exists in the schema yet).

### Campaigns tab

A new **Notifications → Campaigns** tab lists every campaign with a live
progress bar, its audience, sent/skipped/failed counts, who sent it, and a
**Stop** button for anything still running. Stopping keeps notifications already
delivered and sends nothing further.

### Why sends now take minutes instead of appearing instant

The old sender looped the whole audience **inside the admin's HTTP request**,
one Firebase call per device. That is fine for 25 users and impossible for
25,000 — the request would simply time out mid-send, with no record of who had
been reached. Delivery now happens in a queued job that processes
**200 recipients per run**, records a resume cursor, and re-queues itself, so a
large campaign makes progress on every cron tick and survives the worker being
killed. Pushes within a chunk go out concurrently (20 at a time, tunable via
`FIREBASE_PUSH_CONCURRENCY`).

Practical effect: a campaign to a large audience completes over minutes to
hours, not instantly. That is the shared-hosting ceiling — there is no
always-on worker available on this plan.

### Two things that were quietly broken, now fixed

- **"Retry" on a failed push did nothing.** It wrote a `queued` log row that no
  code ever consumed, told you it had been queued, and **reset the
  notification's delivery status to `pending`** — destroying the delivery record
  it was supposed to repair. It now performs the send and reports the provider's
  real answer. A failed retry can no longer downgrade a notification that
  already reached another device, and it refuses cleanly when the device is
  disabled or its token is gone.
- **The "sent to N recipients" count was inflated.** The old sender counted
  everyone it looped over, including people the delivery layer had skipped — and
  because it passed the admin as the notification's actor, the delivery layer's
  "don't notify yourself" rule meant **the sending admin never received their own
  announcement** while still being counted. Campaigns are now sent as the system,
  so the sender receives their own message, and the counts separate
  sent/skipped/failed.

---

## 2026-08-09 — Role & permission system (Spatie) 🔐

The panel moved from a single hard-coded "admin only" gate to real
per-permission access control built on `spatie/laravel-permission`. **Deploy
needs:** `composer install` (new package) + migration. Optional env:
`SUPER_ADMIN_EMAILS=you@example.com,other@example.com` — break-glass accounts
that bypass every permission check so a bad role edit can never lock everyone
out.

### How it works

- **36 permissions**, named `module.action` (`users.view`, `users.moderate`,
  `posts.moderate`, `earnings.manage`, `settings.access`, …). Every admin route
  is gated by exactly one of them.
- **Panel entry** = active account holding *at least one* permission — no
  longer "role admin only". Sidebar sections/links only render for modules the
  signed-in person can see.
- **Four built-in roles** mirror the app's account types:
  - `admin` — locked, always has every permission (cannot be edited/deleted)
  - `moderator` — 18 permissions out of the box: dashboards, user list +
    **ban/suspend powers**, content moderation, reports queue, activity logs.
    No money, no settings, no role management, no user create/delete.
  - `creator` / `user` — no panel permissions (app-side labels only)
- Existing accounts were backfilled automatically: everyone with the old
  `admin`/`moderator` enum value got the matching role. **Moderator accounts
  can now sign in to the panel** (they couldn't before) — review who holds
  that role before deploying.

### New pages

- **Users → Roles & Permissions** (`admin/roles`): create/edit/delete custom
  roles with a per-module permission checkbox grid (e.g. a "support" role with
  just `users.view` + `messaging.view`, or a "finance" role with
  `earnings.*`). Built-in roles can be re-tuned but not renamed or deleted;
  roles still assigned to users can't be deleted.
- **User create/edit forms** now assign these roles (including custom ones)
  instead of the fixed enum list. The legacy `users.role` column stays in sync
  for app-side payloads whenever the assigned role is one of the four
  account types. Role changes are written to the activity log
  (`user_role_updated`), and you still can't change your own role or status.

---

## 2026-08-08 — Real ban/suspend system: reasons, durations, bulk actions 🔨

User moderation went from a bare status flip to a full system. **Run the
migration on deploy** — it adds a `suspended` account state, `suspended_until` /
`status_reason` columns, and re-times existing sessions. Two new cron entries
are required (see the cron table above).

### Ban vs Suspend, now with teeth

- **Ban** — permanent, reason **required**. Only Activate lifts it.
- **Suspend** — temporary, reason **required**, duration required: 24 h / 3 d /
  7 d / 30 d presets or a custom end time. Lifts **automatically** when the
  window passes (lazily on the user's next visit + hourly sweep).
- Both now **revoke every session and push token instantly** — before this, a
  banned user's app kept working until their token expired (up to 30 days).
  Suspended users also no longer share a status with unverified signups, which
  previously let an unverified suspended user un-suspend themselves via the
  email-verification flow.
- The user sees the reason: it's stored on the account and returned in the
  login error and API 403s until the action is lifted.

### Where

- **Users list / user page** — Activate / Suspend / Ban open a modal asking for
  reason (+ duration). The old confirm-only dialogs are gone.
- **Users list bulk bar** — select users with the new checkboxes → Ban /
  Suspend / Activate up to 100 at once. Your own account and other admins are
  skipped automatically (admins must be moderated one-by-one).
- **Moderation → report queue** — user-targeted reports now offer Ban /
  **Suspend (new)** / Restore with an optional typed reason (falls back to the
  report's notes). `Hide`/`Restrict` were removed for user targets — they never
  did anything, yet were logged as if applied.
- **Moderation → actions log** — every ban/suspend/unban/unsuspend now lands
  here (the user-list path previously wrote nothing). Editing a suspend
  action's expiry **re-times the actual suspension**, not just the log row.
- **Edit user form** — status choices reduced to Active/Banned (suspending
  needs a duration → use the Suspend button); admins can no longer demote or
  ban **their own** account from this form; status changes here go through the
  same logged pipeline.
- **Dashboard → Trust & Safety** — new "Suspended Users" tile next to Banned.

### Data migration notes

- Verified accounts stuck in the old `inactive` "suspended" state are converted
  to `suspended` with **no end time** ("until lifted by an admin") — review
  them under Users → filter → Suspended.
- The status filter now distinguishes **Pending Verification** (unverified
  signups) from **Suspended**.

Companion changes on the API side (login 2FA, 24-hour sessions, 403 payloads)
are in the mobile changelog — they ship in the same deploy.

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
