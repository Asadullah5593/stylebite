# Mobile App — API Changelog

Everything backend has changed that affects the **mobile app**. Newest first.
Companion doc: [ADMIN_CHANGELOG.md](ADMIN_CHANGELOG.md) (admin panel changes).

**Base URL:** `https://stylebiteapp.com/api`
**Auth:** `Authorization: Bearer <access_token>` + `Accept: application/json`

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
