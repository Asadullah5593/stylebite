# Mobile App — API Changelog

Everything backend has changed that affects the **mobile app**. Newest first.
Companion doc: [ADMIN_CHANGELOG.md](ADMIN_CHANGELOG.md) (admin panel changes).

**Base URL:** `https://stylebiteapp.com/api`
**Auth:** `Authorization: Bearer <access_token>` + `Accept: application/json`

---

## 2026-08-11 — Logout endpoint 🆕 NEW ENDPOINT · ⚠️ PLEASE ADOPT

There was no logout API at all. On logout the app could only drop its local token,
which left the session valid server-side for the rest of its 24 hours and left the
FCM token registered — so a signed-out handset kept receiving push.

```
POST /auth/logout        (Authorization: Bearer <token>)
```

Body is optional. Response:

```json
{ "status_code": 1, "message": "Logged out successfully.", "push_token_removed": true }
```

**What it does:** revokes the session the call was made with (the token is dead
immediately) and **deletes that device's FCM token row**.

### This is per-device, and it works out of the box

The server identifies the device from the **session**, not from what you post — the
`device_id` you sent at login is on the session row. So a plain
`POST /auth/logout` with no body is correct and sufficient.

Logging out on a phone leaves a tablet signed in and still receiving notifications.

Only send `push_token` (or `device_id`) in the body as a **fallback for sessions
created without a `device_id`** — i.e. if your login call omitted it. If nothing
identifies the device, the session is still revoked but no token is deleted, and
you get `push_token_removed: false`; it deliberately does not guess, because
guessing could kill push on the user's other handset.

### Handling the response

- `200` — done. Clear local state.
- **`401` — treat as success.** The token was already invalid (expired, or logout
  called twice). Clear local state and move on; do not show an error or block the
  user on the logout screen.
- Don't block logout on the network either: clear local state regardless, and fire
  this call so the server side is cleaned up too.

### After logout, push stops

The token row is deleted, so notifications for that user are recorded and marked
`skipped` rather than delivered. Next login re-registers the token normally (keep
sending `device_id` + `push_token` on login as you do today) — deleting does not
lock the device out of notifications.

---

## 2026-08-10 — Legal documents available over the API 🆕 NEW ENDPOINTS

**Nothing breaks and nothing is required of the app.** Privacy Policy and Terms are
now editable from the admin panel and versioned. The existing
`https://stylebiteapp.com/privacy-policy` page is unchanged as a URL — it just
serves the edited text now, so a WebView keeps working and picks up changes on its
own. `/terms` exists at the same style of URL if you want to link it.

These endpoints are here for whenever the app wants them:

- **Native legal screens** instead of a WebView — optional polish
- **Consent records** — who agreed to which version. This one *only* works if the
  app calls `POST /legal/{key}/accept`; until then the acceptance table stays empty
  and the admin-side acceptance export returns nothing. Worth doing if the policy
  is ever going to change materially, since re-consent is otherwise unprovable

### 1. Fetch a document — no login needed

- `GET /legal/privacy_policy`
- `GET /legal/terms`

Both are **public** (no `Authorization` header) — a user has to be able to read the
terms before they have an account.

```json
{
  "status_code": 1,
  "document": {
    "key": "privacy_policy",
    "version": 3,
    "title": "Privacy Policy",
    "body": "Full text…\n\nWith blank-line paragraph breaks.",
    "paragraphs": ["Full text…", "With blank-line paragraph breaks."],
    "summary_of_changes": "Reduced data collection.",
    "requires_reacceptance": true,
    "published_at": "2026-08-10T09:12:00.000000Z"
  }
}
```

Use **`paragraphs`** to render — it's `body` already split on blank lines, so you
don't need to parse anything. `404` means nothing is published yet for that key;
show a graceful empty state rather than an error.

### 2. What the user still has to agree to

`GET /legal/pending/all` (needs a session)

```json
{ "status_code": 1, "has_pending": true,
  "pending": [ { "key": "terms", "version": 2, "title": "Terms", "requires_reacceptance": false } ] }
```

Call this after login. If `has_pending` is `false`, show nothing.

### 3. Record the acceptance

`POST /legal/{key}/accept` with `{ "version": 3 }` — the version you actually
displayed, not a hardcoded number.

- `200` — recorded. Calling it twice is safe (no duplicate row)
- **`409`** — the user was looking at stale text. The response carries
  `current_version`; **re-fetch the document, show it again, and ask again.** Do
  not retry with the new version number without showing the new text — the whole
  point is that they agreed to what was on screen
- `404` — not published yet

### ⚠️ A new version means asking again

Acceptance is tied to a **version**, not to the document. When we publish a new
version, that document reappears in `pending` even for users who accepted the old
one. So: don't cache "accepted" as a boolean forever — check `pending` on login
and gate on that.

`requires_reacceptance: true` marks a material change. Suggested handling: block
the app behind a consent screen for `true`, and use a dismissible banner for
`false`.

### If and when you pick this up

1. Legal screens read from these endpoints instead of hardcoded/WebView text
2. Registration links to `GET /legal/terms` and `GET /legal/privacy_policy`
3. After login: `GET /legal/pending/all` → consent screen if `has_pending`
4. `POST /legal/{key}/accept` on agree, and handle `409` by re-fetching

Steps 3 and 4 are the consent-record half; 1 and 2 are cosmetic. Doing none of it
leaves the app exactly as it is today.

---

## 2026-08-10 — Reporting and support tickets are live 🆕 NEW ENDPOINTS

Two new features for the app to build, plus one bug fix you'll notice.

### 1. Users can report content and accounts

- `GET /reports/meta` — target types and reasons, so nothing is hardcoded
- `POST /reports` — `{ target_type, target_id, reason, description? }`
  - `target_type`: `user`, `post`, `comment`, `reply`, `message`, `contest`
  - `reason`: `spam`, `harassment`, `hate`, `nudity`, `violence`, `copyright`, `fake`, `other`
  - `201` on success. A repeat report of the same thing returns `200` with
    `already_reported: true` — show "already reported", not an error.
  - `422` for your own content, `404` if it no longer exists, `429` if rate limited
- `GET /reports/mine` — the user's own report history with status, so reporting
  doesn't feel like shouting into a void

### 2. Support tickets — this is where bug reports go

- `GET /support/meta` — categories, statuses, limits
- `POST /support/tickets` — multipart. `category` (`bug`, `payment`, `account`,
  `content_appeal`, `other`), `subject`, `body`, up to 5 `attachments[]` (jpg,
  png, webp, 5MB each), plus **`app_version`, `platform`, `device_model`,
  `os_version`** — please send these automatically, they are what makes a bug
  report actionable
- `GET /support/tickets` — list with `status`, `unread_count`, `last_reply_by`
- `GET /support/tickets/{id}` — full thread; opening it marks replies read
- `POST /support/tickets/{id}/messages` — reply, multipart, attachments allowed
- `PATCH /support/tickets/{id}/close` — user closes their own ticket

Rules to reflect in the UI: a `429` means the user has 5 open tickets already; a
closed ticket returns `422` on reply (offer "open a new ticket"); replying to a
**resolved** ticket reopens it automatically. Staff internal notes exist but are
never returned to the app.

### 3. Ticket notifications use two new enum values

`GET /notifications` can now return `type: "support"` with
`entity_type: "support_ticket"` and `entity_id` = the ticket id. Route those to
the ticket thread. **Treat unknown `type`/`entity_type` values defensively** —
show the title and body and fall back to no deep link, rather than crashing.

### 4. Fixed: missing records were returning 500 instead of 404

A real backend bug we found and fixed. `GET /profiles/{unknown-username}`,
missing posts, missing conversations and similar were answering **500**; they now
answer **404** with the normal `{ status_code: 0, message }` shape. Rate limiting
now answers **429** instead of 500, unknown endpoints **404**, and wrong HTTP
methods **405**. If you had special-casing for 500s here, it can go.

---

## 2026-08-10 — Login 2FA and 24-hour sessions are OFF for the app ✅ REVERSAL

**This reverses the breaking change from 2026-08-08.** Two-factor login and the
24-hour session cap were only ever meant for the **admin dashboard**, not the
mobile app. They are now switched off on the API:

- `POST /auth/login` returns `access_token` directly again, exactly as it did
  before. It no longer returns `requires_two_factor`.
- Sessions are back to **30 days**, not 24 hours.
- `POST /auth/login/verify-otp` and `/auth/login/resend-otp` still exist and
  still work, but you do not need them. **No app change is required.**

One after-effect to expect: the earlier deploy capped existing sessions at 24
hours, so users are logged out **once**, then get 30-day sessions again.

Everything else from 2026-08-08 stands and still needs handling: the
banned/suspended `403` payloads with `code`, `reason` and `suspended_until` on
login and on any authenticated endpoint. That part was never about 2FA.

---

## 2026-08-09 — Admin audit trail (no app impact) ℹ️

Admin-side only: every action taken in the admin panel is now recorded in the
activity log. No mobile endpoint, request, response or column changed.

---

## 2026-08-09 — Two new automatic notifications (no app change needed) ℹ️

Users will start receiving two notifications nobody triggered by hand. Both
arrive through the existing `GET /notifications` list and the normal push
pipeline — **no new fields, no new endpoints, no payload changes.**

| Notification | `type` | `entity_type` | `entity_id` |
|---|---|---|---|
| "Your N-day streak ends tonight" | `system` | `user` | the user's own id |
| "{Contest} closes soon" | `contest` | `contest` | the contest id |

Both use `type`/`entity_type` values the app already handles, so existing
deep-link handling applies unchanged — a contest reminder should route to the
contest exactly as other contest notifications do. Neither carries an
`action_url`; tapping should fall back to your usual behaviour for that
`entity_type`.

Worth knowing for testing: these fire from hourly server jobs, are sent **at most
once per user per day** (streaks) or **once per user per contest**, and are never
sent to banned or suspended accounts. Email copy for verification, login and
password-reset codes is now admin-editable, so the exact wording of those emails
may change without any backend release — the 6-digit code behaviour is unchanged.

---

## 2026-08-09 — Push campaigns behind the scenes (no app change needed) ℹ️

Admin announcements are now delivered by a background campaign worker instead of
inside the admin's request. **The notification payload the app receives is
byte-for-byte identical** — still `type: "system"`, `entity_type: "system"`,
`entity_id: null`, same title/body/action_url/image_url, same
`GET /notifications` shape. Nothing to change.

Two behavioural notes that may show up in testing:

- **A broadcast now arrives in waves, not all at once.** Recipients are processed
  in chunks of 200 per worker run, so a large announcement lands over minutes
  rather than simultaneously. A device receiving a push later than another device
  is expected, not a bug.
- **Multi-device users get one push per registered device**, sent concurrently
  (this was already true, just slower before).

Push still respects the user's `push_notifications_enabled` setting, and banned
or suspended accounts are never included in a campaign.

---

## 2026-08-09 — Admin panel role & permission system (no app impact) ℹ️

Admin-side only: panel access is now governed by Spatie roles/permissions
(moderators and custom staff roles can hold scoped panel access). No mobile
endpoint, request, response or column changed — `user.role` in API payloads
behaves exactly as before.

---

## 2026-08-08 — Email 2FA on login, 24-hour sessions, ban/suspend enforcement 🚨 BREAKING

Three account-security changes land together. **The app must be updated — the
old login flow no longer returns a token.**

### 1. Password login now requires an emailed code (every login)

`POST /auth/login` with correct credentials **no longer returns
`access_token`**. Instead it emails a 6-digit code and returns:

```json
{ "status_code": 1, "requires_two_factor": true, "email": "user@example.com", "otp_resend_in": 60 }
```

Show the OTP screen (same UX as registration verification), then call:

- `POST /auth/login/verify-otp` — `{ email, code }` plus the usual optional
  device fields (`device_id`, `platform`, `push_token`, `app_version` — send
  them **here** now, not on `/login`). Success returns exactly what login used
  to return: `access_token`, `bearer_token`, `user`.
- `POST /auth/login/resend-otp` — `{ email }`. 60-second cooldown
  (`429` + `retry_after` when called too soon). Only works while a
  password-verified login is pending, so call it from the OTP screen only.

Code rules: expires in **10 minutes**, **5 wrong attempts** kill it, single-use.
When a code is dead the user must go back to the password step — the login call
issues a fresh one.

**Google / Apple login are unchanged** (`/auth/google-login`, `/auth/apple-login`
still return the token directly) — the provider already authenticated the user.
Registration is also unchanged: `verify-email-otp` still logs the user in; there
is no double-OTP on signup.

### 2. Sessions now expire 24 hours after login — no renewal

Every token (password *and* social login) dies exactly 24 h after it was
issued, regardless of activity. Expect a daily `401 Invalid or expired token.`
and route the user to the login screen. Tokens issued before this deploy were
capped to 24 h from deploy time. Handle the `401` gracefully on app start —
this is now an everyday event, not an edge case.

### 3. Banned / suspended accounts get real, distinct errors

Admins can now **ban** (permanent) or **suspend** (timed, auto-lifts) accounts,
and it takes effect immediately: all the user's sessions are revoked the moment
the action is taken.

Two places you'll see it:

- **At login** (password or social): `403` with a machine-readable `code`.
- **Mid-session** on *any* authenticated endpoint (if a token somehow outlives
  the action): same `403` body — treat it as a forced logout to a dedicated
  screen, do not retry.

```json
{
  "status_code": 0,
  "code": "account_banned" | "account_suspended" | "account_inactive",
  "message": "Your account is suspended. Reason: …",
  "reason": "Repeated harassment reports",   // nullable
  "suspended_until": "2026-08-15T10:00:00Z"  // nullable; null on bans
}
```

Show `reason` and, for suspensions, `suspended_until` ("You're suspended until
…"). A suspension past its end time lifts automatically the next time the user
logs in or calls the API — no admin needed.

Also: `POST /auth/forgot-password` silently sends nothing for banned accounts
(response body unchanged, to avoid leaking ban status).

### Migration checklist for the app

1. Handle `requires_two_factor` on login → OTP screen → `login/verify-otp`.
2. Move device/push params from `/login` to `/login/verify-otp`.
3. Handle daily `401` re-login gracefully.
4. Add banned/suspended screens keyed on the `403` `code` field (login **and**
   global response interceptor).

---

## 2026-08-05 — Creator payout counts on the admin dashboard (no app impact) ℹ️

Admin-side reporting only. No endpoint, request, response or database column the
app touches was changed — withdrawal amounts and statuses behave exactly as
before.

---

## 2026-08-05 — Daily streaks now actually work 🔥

**No app change needed — but the numbers you already display stop being 0.**

`streak.days` / `streak.label` on `GET /api/profile`, and `current_streak_days` /
`current_streak_label` on the profile payload, have always returned **0 / null**
for every user: the columns existed but nothing on the backend ever filled them.
A streak engine now maintains them, so the values the app is already rendering
become real.

Nothing about the request or response shape changed — same endpoints, same
fields, same types.

### What the values mean now

A streak is the run of consecutive days a user was active, counted in the app's
reporting timezone (Asia/Karachi by default). It stays alive if the user was
active **today or yesterday**, so it will not read 0 in the morning before the
user has posted.

What counts as "active" is an admin setting, so **do not hardcode a rule in the
app copy**: it can be an outfit post (the default), any post, or simply opening
the app. `streak.label` is a ready-made string like `"12 day streak"` — prefer it
over composing your own text so the wording stays in one place.

### Worth knowing

- A streak updates the moment the user publishes (or, in login mode, on their
  first authenticated request of the day) — so refreshing the profile right after
  posting shows the new value.
- Deleting a post can **shorten or break** the streak; it is recomputed from the
  user's real activity, never just incremented. Do not cache the number across a
  delete.
- An admin can restore or reset a user's streak, so it can change without the
  user doing anything. Read it from the profile response rather than tracking it
  client-side.

---

## 2026-08-05 — Engagement dashboard counts (no app impact) ℹ️

Admin-side reporting only — no endpoint, request or response changed.

📌 **Correction.** An earlier version of this entry warned that the counters on
`posts` (`like_count`, `comment_count`, `share_count`) — the numbers shown under
each post in the app — had drifted below the real totals. **That was wrong.** The
comparison behind it counted likes and comments belonging to deleted posts
against a sum that excluded those posts. Checked per post, the counters match the
real rows. **No app-side action is needed and no recount is required.**

---

## 2026-08-05 — Reels, Food Reviews & Completed Contests dashboard counts (no app impact) ℹ️

Admin-side reporting only. No endpoint, request, response or database column
that the app touches was changed — logged here purely so the admin and mobile
changelogs stay in step.

---

## 2026-08-05 — Activity tracking for DAU/MAU (no app changes needed) ℹ️

**Nothing to implement.** Recorded here only so the behaviour is not a surprise.

The bearer-token middleware that already updates `last_seen_at` on every
authenticated request now also records one row per user per active day, which the
admin dashboard counts DAU/MAU from. No request or response shape changed, no new
header or field, and no endpoint was added or removed.

The only user-visible effect is that a user counts as "active today" from their
first authenticated API call of the day — so keep sending the bearer token on
normal app usage exactly as you do now.

---

## 2026-08-06 — Empty chats hidden, explicit block flags, 2-message opening limit

### Conversations with no messages no longer appear in `GET /chats` ⚠️
Tapping a profile calls `initialize`, which creates the conversation. Previously
that empty thread immediately showed in the chat list. Now a conversation appears
**only once it has at least one message**.

`POST /chats/initialize` still returns the chat object exactly as before, so you can
open the thread straight away — it just won't be listed until something is sent.

### Block status is now explicit — no more guessing from a bare 403 ⚠️

Two booleans tell you which side the block came from:

| Field | Meaning |
|---|---|
| `is_blocked_by_me` | The logged-in user blocked the other party |
| `is_blocked_by_other` | The other party blocked the logged-in user |
| `is_blocked` | Convenience: either of the above is true |

**On the `403` error bodies** for `POST /chats/initialize` and
`POST /chats/{conversationId}/messages`:

```json
{
  "status_code": 0,
  "message": "You cannot start a chat with this user.",
  "is_blocked_by_me": false,
  "is_blocked_by_other": true,
  "is_blocked": true
}
```

This covers the re-entry case: user1 blocks user2, goes back to the chat list, taps
that chat again, `initialize` fires and 403s. The error body now carries everything
needed — no profile fetch, no guessing.

**On every chat object** — `GET /chats` items, `initialize`, `messages`, `sync`,
`stop`/`resume`, and the `chat.updated` socket event. So a blocked conversation can
be tagged the moment the list loads, without opening it to trigger a 403 first.

**Blocked conversations still appear in `GET /chats`** — they are flagged, not
hidden.

⚠️ **Do not open the socket for a blocked conversation.** Channel authorisation
rejects blocked pairs with a `403`, by design. Check `is_blocked` before subscribing
to `presence-conversation.{id}` and skip it if true — otherwise you'll get auth
errors in the client.

### Opening-message limit raised from 1 to 2
A user may now send **2 messages** before the other person replies; the 3rd returns
`422` "Wait for reply before sending another message."

As before, this cap only applies **until the first reply**. Once the other person
answers even once, it stops applying for the rest of the conversation.

---

## 2026-08-05 — Realtime chat over Pusher (WebSockets) 🚀

Chat no longer needs polling. The REST endpoints all still work and remain the
source of truth — realtime is an accelerator layered on top, so a dropped socket
degrades to the old behaviour instead of breaking.

### Connection

| Setting | Value |
|---|---|
| Provider | **Pusher Channels** (use the Pusher SDK, or Laravel Echo with the pusher broadcaster) |
| Key | shared separately — the app key, not the secret |
| Cluster | `ap2` |
| TLS | required (`forceTLS: true`) |
| Auth endpoint | `POST https://stylebiteapp.com/api/broadcasting/auth` |
| Auth headers | `Authorization: Bearer <access_token>`, `Accept: application/json` |

The auth endpoint uses **the same bearer token as the REST API** — there is no
separate JWT. Point the SDK's authorizer at it and pass the header through.

### Channels

**`presence-conversation.{conversationId}`** — subscribe while a chat is open.
Presence membership is how you know who is in the thread right now, so
`member_added` / `member_removed` give you online/offline **inside the chat** with
no polling and no server call.

**`private-user.{userId}`** — subscribe once at login and keep it for the whole
session. Chat list ordering and the unread badge arrive here while the user is
anywhere else in the app.

Subscribing to a conversation you are not an active member of returns **403**.
Blocking also cuts the channel, not just the REST endpoints.

### Server → client events

| Channel | Event | Payload |
|---|---|---|
| `presence-conversation.{id}` | `message.sent` | `{ conversation_id, message }` |
| `presence-conversation.{id}` | `messages.delivered` | `{ conversation_id, recipient_user_id, message_ids[], delivered_at }` |
| `presence-conversation.{id}` | `messages.read` | `{ conversation_id, reader_user_id, last_read_message_id, message_ids[], read_at }` |
| `presence-conversation.{id}` | `messaging.state` | `{ conversation_id, is_messaging_stopped, messaging_stopped_by_user_id, messaging_stopped_at }` |
| `private-user.{id}` | `chat.updated` | `{ chat, total_unread_count }` |

The `message` object in `message.sent` matches your existing `ChatMessageModel`
field-for-field, with one deliberate difference: **it has no `is_mine`**. A
broadcast goes to both sides of the conversation, so derive it yourself with
`message.sender_user_id == myUserId`. Its `status` is the sender-side status
(`sent` / `delivered`).

The `chat` object in `chat.updated` is exactly the same shape as an entry in
`GET /chats`, including `unread_count` — drop it straight into the list.

### Client → server

Sending stays on REST — `POST /chats/{id}/messages`. **The HTTP response is your
`message_ack`**: it returns the saved message with its real `id` and `sent_at`, so
swap your optimistic/pending row for it there. This is why there is no
`send_message` socket event; Pusher is publish/subscribe and clients don't push
business events up the socket.

**Typing indicators** use Pusher **client events** on the presence channel — they
travel client-to-client and never touch our server:

```
trigger: client-typing   payload: { user_id, is_typing }
```

Enable client events for the app in the Pusher dashboard. ⚠️ Please throttle:
fire once when typing starts and once when it stops, minimum ~3s apart. Do **not**
fire per keystroke — we're on the free tier (100 concurrent connections,
200k messages/day) and per-keystroke typing events alone would exhaust it.

### Reconnect

On reconnect call `GET /chats/{id}/sync?after_message_id=<last id you hold>` and
replay from there. Nothing is lost while the socket is down, because the database
— not the socket — is the source of truth.

### Still to confirm

`sync_messages`, `message_ack` and `send_message` from your document are covered by
REST as described above rather than as socket events. If you need any of them as
actual socket events, tell me before you wire up the client.

---

## 2026-08-05 — Chat read receipts, unread counts, delivery status, sync + blocking

Groundwork for the WebSocket chat migration. **All of this is plain REST and works
today** — none of it depends on the socket layer landing. Adopting it now means the
realtime switch-over is mostly a transport change on your side.

### Message status: `sent` → `delivered` → `seen`
Every message payload (from `GET /chats/{username}/messages`,
`POST /chats/{id}/messages`, and `GET /chats/{id}/sync`) now includes:

| Field | Meaning |
|---|---|
| `status` | `sent` / `delivered` / `seen` for your own messages, `received` for theirs |
| `delivered_at` | ISO-8601 timestamp, `null` until the recipient's device pulls it |

`delivered_at` is set automatically when the recipient loads the thread or calls
`sync` — you don't have to report delivery yourself.

### New: `POST /chats/{conversationId}/read` — mark messages as read
```json
{ "up_to_message_id": 1234 }
```
`up_to_message_id` is **optional** — omit it to mark the whole conversation read.

Returns `last_read_message_id`, `read_message_ids`, `unread_count` (this chat) and
`total_unread_count` (all chats — use it for the tab badge). Safe to call repeatedly;
it will not create duplicate read records. This is what flips the sender's `status`
to `seen`.

### New: `GET /chats/{conversationId}/sync?after_message_id=123` — reconnect recovery
Returns everything newer than a message id you already hold, so a dropped connection
can catch up without re-paginating the thread.

- `limit` optional, default **100**, max **200**
- Response: `messages`, `has_more`, `cursor` (pass `cursor` back as the next
  `after_message_id` while `has_more` is true)

Use an **id cursor, not a timestamp** — it's immune to device clock skew.

### Chat list now carries unread counts
`GET /chats` — each chat gains **`unread_count`**, and the response gains a
top-level **`total_unread_count`**. Both are computed in a single query, so the list
is no slower than before.

### Presence now expires ⚠️ behaviour change
`user.is_online` previously stayed `true` forever if the app was force-quit. It is
now only `true` when presence was reported within the **last 2 minutes**.

**Action required:** keep calling `PATCH /profile/me/presence` on a timer (roughly
every 60s) while the app is foregrounded, otherwise the user will show as offline.
Conversation payloads also now expose **`user.last_seen_at`** and
**`messaging_stopped_by_user_id`**.

### Blocking is now enforced in chat ⚠️ new error
Blocks were never checked in chat before. Now, if either user has blocked the other:
- `POST /chats/initialize` → **`403`** "You cannot start a chat with this user."
- `POST /chats/{id}/messages` → **`403`** "You can no longer send messages in this chat."

Handle `403` on both — previously neither could fail this way.

### Fixed: chat push notifications
New-message pushes were being written with an invalid `entity_type`, which could fail
after the message had already been saved. They now correctly reference the message.

---

## 2026-07-28 — Signup email OTP + `show_ad` on reels

### Signup now verifies email with a 6-digit OTP (not a link) ⚠️ flow change
`POST /auth/register` now emails a **6-digit code** instead of a verification link.

**The account is not usable until the email is verified:**
- `register` **no longer returns an `access_token`** — it returns `{ requires_verification: true, user }`. Send the user to the OTP screen.
- **`verify-email-otp` returns the `access_token`** on success (auto-login). That's where you get the token now.
- `login` before verifying → **`403`** `{ requires_verification: true, email }` — route to the OTP screen and call `resend-email-otp`.
- (Existing already-active accounts are unaffected — only new signups require verification. Google/Apple logins are pre-verified.)

Two new endpoints:

**`POST /auth/verify-email-otp`**
```json
{ "email": "user@example.com", "code": "123456" }
```
- Success → email marked verified, returns the `user` payload. Wrong code → `422` "The verification code is incorrect."
- Code expires in **15 min**; **5 wrong attempts** locks that code (request a new one → `422`). Already-verified → friendly `200`.

**`POST /auth/resend-email-otp`**
```json
{ "email": "user@example.com" }
```
- **60-second cooldown**: too soon → `429` with `retry_after` (seconds). Response is generic (doesn't reveal whether the account exists). Register already sends the first code, so a resend right after signup is on cooldown.

**Forgot password** already uses a 6-digit OTP (`/auth/forgot-password` → `/auth/reset-password` with `code`); it now has the same 60-second cooldown. All three OTP endpoints are rate-limited.

### Reels/feed items now include `show_ad`
Each item in `GET /feed/home` and `GET /reels` now has **`show_ad`** (boolean) — `true` when that reel's creator is ad-eligible (monetized). Use it to decide whether to run a mid-reel ad on the reel. Scroll ads (between reels) are unaffected — those are your own placement.

### `GET /reels` no longer returns the viewer's own reels
Own reels are now excluded from the reels feed (matching `/feed/home`). Use the profile endpoints to show a user their own reels.

> Note: if the API docs (`/docs/api`) look out of date, it's a **deploy lag** — Scramble regenerates them from the live code. After the backend deploys, the new endpoints appear.

---

## 2026-07-28 — Ads & monetization (watch time, eligibility, impressions)

New endpoints the app calls for the reel ad system. Revenue split is admin-configurable (no hard rule).

### `POST /api/views/batch` — report watch time (required for eligibility)
Batch it (e.g. every ~30s or 10 views), never one call per view. Drives view counts + the watch hours used for ad eligibility.
```json
{ "views": [ { "post_id": 123, "watch_seconds": 18, "view_source": "reel" } ] }
```
- Send the **viewer's** watch seconds per reel — it credits the reel's **author**. `view_source`: `feed|reel|detail|explore|profile` (optional, default `reel`).
- Server caps each `watch_seconds` at the video's duration, so don't worry about over-reporting. Response: `{ recorded, skipped }`.
- Per-item hard limit: `watch_seconds` ≤ 86400. Unknown/deleted `post_id` is skipped (not an error).

### `GET /api/ads/eligibility` — read eligibility + config
```json
{ "status_code": 1, "data": {
  "eligible": false,
  "followers": 320, "min_followers": 500, "meets_followers": false,
  "watch_hours": 640.5, "min_watch_hours": 1000, "meets_watch_hours": false,
  "config": { "reel_owner_share_percent": 30, "mid_reel_trigger_percent": 30 }
} }
```
- **No opt-in switch.** Once `eligible` is true, the creator earns automatically — nothing to toggle. Use this to show progress ("180 more followers, 360 more watch hours") and, once eligible, a "you're earning from ads" state.
- Use `config.mid_reel_trigger_percent` as the watch-% that triggers a mid-reel ad (don't hardcode it).

### `POST /api/ads/impressions` — report ad impressions + revenue
```json
{ "impressions": [
  { "ad_type": "scroll",   "revenue": 0.0123, "currency": "USD", "impression_ref": "<admob-impression-id>" },
  { "ad_type": "mid_reel", "post_id": 123, "revenue": 0.02, "currency": "USD", "ad_unit_id": "ca-app-pub-…", "impression_ref": "<admob-impression-id>" }
] }
```
- `ad_type`: **`scroll`** (platform ad, 100% admin — no `post_id`) or **`mid_reel`** (tied to a reel, split with its owner **when that owner is ad-eligible**). `post_id` is **required for `mid_reel`**.
- `revenue`: use AdMob's **paid-event (impression-level) revenue** (`OnPaidEvent` / `paidEventHandler`); `currency` = the AdMob account currency (default USD).
- **Always send a unique `impression_ref`** (AdMob impression id or a per-ad UUID). It de-dupes retries — without it, retried batches double-count.
- Response: `{ accepted, duplicates, rejected }`. Owners aren't credited for ads shown to themselves.

### `GET /api/earnings/ads` — the Ad Earnings section
```json
{ "summary": {
    "currency_code": "PKR", "lifetime_ad_earned": 83.45, "eligible": true,
    "pending": [ { "currency_code": "USD", "amount": 0.30, "impressions": 5 } ]
  },
  "earnings": [ /* ad_revenue transactions, wallet currency */ ],
  "pagination": { … }
}
```
Amounts come back in the creator's wallet currency (already converted). `pending` is revenue earned but not yet paid out (settled on a schedule).

**Client-side (your side, no backend involvement):** scroll-ad placement, the mid-reel `mid_reel_trigger_percent` → timer → ad trigger, AdMob consent (UMP/GDPR) and store policy.

---

## 2026-07-17 — Earnings currency is now per-country and properly converted

**Affects:** `GET /profile/me`, `GET /earnings/*`
**Mobile work required:** none (contract unchanged) — but see the display rule.

- A creator's wallet currency is decided by their **profile country** when the wallet is first created (Pakistan→PKR, UK→GBP, UAE→AED, US→USD, India→INR, Euro-zone→EUR, and other common markets). Unknown/blank country falls back to an admin-set default.
- Rewards are defined in a **base currency (USD)** and converted into the creator's wallet currency **at the moment they're credited**, using that day's exchange rate. The converted amount is then **frozen** — balances never drift with exchange rates.
- `currency_code` is still `null` until the wallet is initialized (first call to any `/earnings/*` endpoint). Treat `null` as "not initialized", not an error.
- **Currency is fixed once the wallet exists.** If the user later changes their profile country, the existing wallet keeps its currency.

> ⚠️ **Display rule:** always render amounts using the `currency_code` returned by the API. Never hardcode "PKR"/"Rs"/"$" — users in different countries now have different currencies.
>
> 💡 For the country-based currency to be right, set the user's **country in their profile** (`PUT /profile`) **before** they first open the earnings screen.

---

## 2026-07-17 — New: profile ratings distribution

**Endpoint:** `GET /profiles/{username}/ratings-distribution`

Star-rating breakdown (style points) across the user's **published** posts.

```json
{
  "status_code": 1,
  "message": "Rating distribution fetched successfully",
  "data": {
    "average_rating": 4.7,
    "total_ratings": 1240,
    "distribution": { "5": 850, "4": 250, "3": 100, "2": 30, "1": 10 }
  }
}
```

- All five keys (`"5"`…`"1"`) are **always present**, zero-filled — no missing-key handling needed.
- `average_rating` is rounded to 1 decimal; `0` when there are no ratings.
- Empty state: check `total_ratings === 0`.
- Blocked users / unknown username → `404`.

---

## 2026-07-17 — Join a contest using an existing post

**Endpoints:** `POST /contests/{contest_id}/join`, `POST /contests/{contest_id}/submissions`

| Param | Type | Rules |
|---|---|---|
| `post_id` | integer, optional | **New.** One of the logged-in user's own published posts |
| `asset` | file | Now required **only when `post_id` is absent** |
| `caption` | string, optional | Used only with `asset`; **ignored when `post_id` is sent** (the post's own caption is used) |

With `post_id`, the selected post's media + caption are linked to the submission directly — no duplicate post is created. Response shape unchanged (`post_id` echoes the selected post).

**Errors:**

| Scenario | Response |
|---|---|
| Neither `asset` nor `post_id` | `422` — "Either an asset file or a post_id must be provided." |
| `post_id` not yours / unpublished / blocked | `status_code: 0` — "Selected post was not found or does not belong to you." |
| Post has no media | `status_code: 0` — "Selected post has no media attached." |
| Already submitted to this contest | `status_code: 0` — "You have already submitted one post for this contest." |

---

## 2026-07-12 — Home feed: For You / Nearby (`discover_type`)

**Endpoints:** `GET /feed/home`, `GET /reels`

```
/feed/home?type=food&page=1&per_page=10&discover_type=nearby&lat=31.5204&lng=74.3587
```

| Param | Values | Notes |
|---|---|---|
| `discover_type` | `for_you` \| `nearby` | Optional; omitted = `for_you` |
| `lat` / `lng` | -90..90 / -180..180 | Device GPS. Send **both or neither** |

- **`for_you`** — feed as before, latest first, no filter.
- **`nearby`** — only posts within a radius of the user (admin-controlled, currently 10 km — don't hardcode it), sorted **nearest first**. Posts without a location are never included. Works together with `type=food` / `type=outfit`.

**New response fields:**

```json
{
  "discover_type": "nearby",       // the mode ACTUALLY applied
  "nearby_radius_km": 10,          // null in for_you mode
  "location_source": "request",    // "request" | "last_known" | null
  "feed": [ { "distance_km": 2.35, "...": "..." } ]
}
```

**Location fallback:** any feed call that includes `lat`/`lng` saves them as the user's last known location (latest fix only — no history, no tracking). If `nearby` is later called **without** coordinates (GPS off/denied), the server uses the **saved** coordinates (`location_source: "last_known"`). Only if none exist does it fall back to the latest feed (`discover_type: "for_you"`).

- Keep sending fresh GPS when available — even on `for_you` calls — so the fallback stays current.
- Consider a "using last known location" hint when `location_source === "last_known"`.
- `distance_km` → for a "2.4 km away" label; `null` in for_you mode.

**Errors:** only one of lat/lng → `422` ("Both lat and lng are required together."). Invalid `discover_type` → `422`.

---

## 2026-07-12 — Memories date filters

**Endpoint:** `GET /memories`

| Param | Values |
|---|---|
| `date_filter` | `last_week` \| `last_month` (optional) |

Rolling window on `memory_date` (past 7 days / past 1 month). Invalid value → `422`.

---

## 2026-07-10 — Signup accepts a profile image ⚠️ request format change

**Endpoint:** `POST /auth/register` — now **`multipart/form-data`** (not JSON)

| Field | Required | Notes |
|---|---|---|
| `name` | ✅ | |
| `email` | ✅ | |
| `password` | ✅ | min 8 |
| `password_confirmation` | ✅ | must match |
| **`avatar`** | ❌ | Image file — jpg/jpeg/png/webp, **max 5MB**. Auto-compressed & resized server-side |
| `username` | ❌ | lowercase letters/numbers/underscore; auto-generated if omitted |
| `device_id`, `platform`, `push_token`, `app_version` | ❌ | as before |

Response `201` unchanged in shape; `user.avatar_url` is populated when an avatar was sent, otherwise `null`.

**Errors:** email taken → `422`; image >5MB → `422` ("The profile image may not be larger than 5MB."); wrong type → `422`.

---

## 2026-07-10 — Home feed optimized ⚠️ BREAKING response shape

**Endpoints:** `GET /feed/home`, `GET /reels`

- **Payload slimmed to feed-card fields only.** Update your models.
- **Pagination:** `per_page` optional, **max 15**, default 10.
- **The user's own posts are no longer returned** in the home feed — use profile endpoints for "my posts".
- `/reels` returns the same slim shape under the key **`"reels"`** (not `"feed"`).

**Fields REMOVED from the feed list** (move these to the post-detail endpoint): `tags`, `comments` / `comment_preview`, `latest_share`, the full `location` object (now a plain string), granular `food/service/staff/ambience` ratings, extra author fields (`bio`, `city`, `follower_count`, …), `published_at`, `created_at`.

**Media entries (feed + detail):**

```json
{
  "file_url": "https://.../optimized/uuid.jpg",   // optimized when available, else original — ALWAYS use this
  "original_url": "https://.../uuid.jpg",         // untouched full-size
  "poster_url": "https://.../poster.jpg",         // video still frame; null for images
  "width": 1080, "height": 720,
  "duration_seconds": null,
  "is_optimized": true,
  "processing_status": "ready"                    // "pending" right after upload
}
```

- **Always display `file_url`** — it's the optimized rendition when ready, the original otherwise, and always valid.
- **Videos:** show `poster_url` while scrolling; only load `file_url` when the item is in view. **Do not autoplay off-screen videos.**
- Right after creating a post, `processing_status` may be `"pending"` (original served); the optimized image appears on the next fetch (~1 min).

---

## Reference — Followers / Following (existing, unchanged)

```
GET /profiles/{username}/followers?page=1
GET /profiles/{username}/following?page=1
```

20 per page, standard `pagination` object. Blocked users excluded both ways. Each item:

```json
{
  "id": 12, "username": "sara_k", "display_name": "Sara Khan", "full_name": "Sara Khan",
  "avatar_url": "https://.../avatar.jpg", "bio": "…", "is_private": false,
  "is_self": false, "is_following": true, "follows_you": false, "is_mutual_follow": false,
  "follower_count": 240, "following_count": 180
}
```

Use `is_following` / `follows_you` to render Follow / Follow Back / Following states — no extra call per row. Actions: `POST` / `DELETE /profiles/{username}/follow`.

---

## 🚧 Known blocker — video optimization

Videos are served at their **original resolution & bitrate**. The current shared hosting has no `ffmpeg`/shell access, so server-side transcoding to ≤720p is not possible.

**Interim mitigation (mobile side):** compress/downscale video to **≤720p before upload** (like Instagram/TikTok) — also cuts upload time and bandwidth.
**Backend options pending a decision:** Cloudinary / Mux / Bunny Stream, or a VPS with ffmpeg. When enabled, nothing changes for the app — keep using `file_url` + `poster_url`.

Until resolved: always use `poster_url` for video thumbnails and lazy-load the actual video.
