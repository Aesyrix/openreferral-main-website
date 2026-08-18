# Open Referral — Backend Requirements

> ℹ️ **See [`BACKEND_GUIDE.md`](BACKEND_GUIDE.md) for the complete, authoritative
> guide** — it adds the admin/staff dashboard, agent-approval workflow, staff
> management, revenue/analytics, and the full email/notification event list. This
> file remains a concise summary of the **mobile app** API surface.


This document specifies the backend the **Open Referral agent app** (Flutter)
needs. The frontend is built first against a mock data layer; this spec lets the
web team build the **Laravel + MySQL** backend and **admin/staff dashboard**
(Blade + Vite + Vanilla JS + Tailwind) to match it.

> The mobile app is for the **agent** role only. **Admin** and **staff** are
> web-dashboard roles. Staff assign leads to agents; admins oversee everything.

---

## 1. Roles & high-level flow

| Role  | Where            | Responsibilities |
|-------|------------------|------------------|
| Agent | Mobile app       | Sign up, receive assigned leads, accept/reject with feedback, manage profile |
| Staff | Web dashboard    | Create leads, assign leads to agents, review agent feedback |
| Admin | Web dashboard    | Everything staff can do + manage staff/agents, view analytics |

**Lead lifecycle (agent's view):**
`new` → agent **accepts** → `in_progress` → (staff/admin later) → `converted`
`new` → agent **rejects** (reason required) → `rejected`

The app's accept/reject maps to status changes and always records feedback.

---

## 2. Authentication

Email-based auth (no social login in v1). JWT or Laravel Sanctum tokens.

| Step | Endpoint | Notes |
|------|----------|-------|
| Request signup code | `POST /auth/register/start` | body `{ email }`; emails a 6-digit OTP; rate-limit |
| Verify code | `POST /auth/register/verify` | body `{ email, code }`; returns short-lived `verification_token` |
| Complete signup | `POST /auth/register/complete` | body `{ email, verification_token, password }`; creates agent, returns auth token; **notifies admin+staff (new agent registered)** |
| Login | `POST /auth/login` | body `{ email, password }`; returns auth token + agent profile |
| Forgot password | `POST /auth/password/forgot` | body `{ email }`; emails a 6-digit OTP |
| Verify reset code | `POST /auth/password/verify` | body `{ email, code }`; returns `reset_token` |
| Reset password | `POST /auth/password/reset` | body `{ email, reset_token, password }` |
| Logout | `POST /auth/logout` | invalidates token |
| Current agent | `GET /me` | returns the authenticated agent profile |

**OTP:** 6 digits, expire in ~10 min, max attempts, resend throttled (app shows a
45s resend timer). **Password:** min 8 chars (app shows a strength meter; server
enforces the minimum).

---

## 3. Agent profile

`GET /me` and `PUT /me` — fields the app reads/edits:

```
id, name, email (read-only after signup), phone, avatar_url, title,
verified (bool), service_areas (string[]), license_number, license_state,
brokerage, bio
```

`PUT /me` accepts: `name, phone, service_areas, license_number, license_state,
brokerage, bio`. `POST /me/avatar` (multipart) for the avatar.
`POST /me/change-password` body `{ current_password, new_password }`.
`DELETE /me` — delete account (soft-delete recommended).

---

## 4. Leads

All lead endpoints are scoped to the authenticated agent (only their assigned
leads).

| Endpoint | Purpose |
|----------|---------|
| `GET /leads` | List assigned leads. Query: `status`, `intent`, `q` (search name/location/property/reference), `sort` (`newest`,`oldest`,`price_high`,`price_low`,`name_az`), `page`. |
| `GET /leads/{id}` | Full lead detail. |
| `GET /leads/stats` | Dashboard counts: `{ total, new, in_progress, converted, rejected }`. |
| `POST /leads/{id}/respond` | Accept/reject + feedback (see below). |

**Lead object:**
```
id, reference_id (e.g. "LEAD-10234"), name, avatar_url, phone, email,
intent ("buying"|"selling"), property_type, price_min, price_max, location,
timeline, bedrooms, bathrooms, source, notes, status, assigned_at, created_at
```

**Respond (accept/reject) — `POST /leads/{id}/respond`:**
```jsonc
// Accept:
{ "decision": "accept" }                 // → status becomes "in_progress"
// Reject (reason required):
{
  "decision": "reject",                  // → status becomes "rejected"
  "reason": "budget_too_low",            // enum, see §7
  "comments": "optional free text"
}
```
On success the server: updates lead status, stores a `lead_feedback` row, and
**notifies admin + staff** (agent responded). Returns the updated lead.

**Dashboard analytics:** the app also shows a weekly activity chart (leads
received per day, last 7 days) and a pipeline breakdown. Provide
`GET /leads/analytics?range=week` → `{ series: [{date, count}], pipeline: {...} }`.

---

## 5. Notifications

In-app list + push. The app keeps an unread badge.

| Endpoint | Purpose |
|----------|---------|
| `GET /notifications` | List `{ id, type, title, body, lead_id?, read, created_at }`. `type` ∈ `new_lead`, `lead_update`, `feedback`, `system`. |
| `POST /notifications/{id}/read` | Mark one read. |
| `POST /notifications/read-all` | Mark all read. |
| `GET /notifications/unread-count` | Badge count. |
| `POST /devices` | Register a push token `{ token, platform }` for the agent. |
| `DELETE /devices/{token}` | Unregister (logout / disable). |

**Push events the backend must emit** (create an in-app notification **and** send
a push to the relevant recipients):

| Event | Push to | type |
|-------|---------|------|
| New lead assigned to an agent | that agent | `new_lead` |
| Agent accepts a lead | admin + staff | `feedback` |
| Agent rejects a lead (with reason) | admin + staff | `feedback` |
| Agent submits feedback | admin + staff | `feedback` |
| New agent registers | admin + staff | `system` |
| Lead marked converted (by staff/admin) | that agent | `lead_update` |

Push provider: **FCM** (Android + iOS via APNs). The app has a
`NotificationService` interface ready for the FCM implementation; respect each
agent's `push_notifications` / `email_notifications` preferences.

---

## 6. Settings

Per-agent preferences: `GET /me/settings`, `PUT /me/settings`:
```
{ push_notifications: bool, email_notifications: bool, language: string }
```
Static content (About, Privacy, Terms, Help) may be served from the backend so
copy can change without an app release: `GET /content/{about|privacy|terms|help}`
→ `{ title, intro?, sections: [{heading, body}] }`. The app currently ships
fallback copy (see `lib/features/settings/data/legal_content.dart`).

---

## 7. Enumerations (keep in sync with the app)

- **lead status:** `new`, `in_progress`, `converted`, `rejected`
- **lead intent:** `buying`, `selling`
- **rejection reason:** `not_in_service_area`, `not_interested`, `not_serious`,
  `budget_too_low`, `property_type_mismatch`, `already_working_other`, `other`
- **notification type:** `new_lead`, `lead_update`, `feedback`, `system`
- **user role:** `agent`, `staff`, `admin`

---

## 8. Conventions

- JSON everywhere; `snake_case` keys; ISO-8601 UTC timestamps.
- Auth via `Authorization: Bearer <token>`.
- Standard error shape: `{ "message": "...", "errors": { "field": ["..."] } }`.
- Pagination: `{ data: [...], meta: { page, per_page, total } }`.
- Money stored as integers (whole dollars) — app formats to currency.

See `docs/DATABASE_SCHEMA.sql` for the MySQL schema.
