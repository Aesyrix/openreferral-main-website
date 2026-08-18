# Open Referral — Complete Backend Guide

This is the authoritative guide for building the **Open Referral** backend so it
matches the finished Flutter agent app. It covers the data model, roles, every
workflow, the mobile **and** dashboard APIs, push/email notifications, and
revenue/analytics.

- **Stack:** Laravel (PHP) + MySQL. Admin/staff dashboard in Blade + Vite +
  Vanilla JS + Tailwind. Auth via Laravel Sanctum (recommended).
- **Companion files:** [`DATABASE_SCHEMA.sql`](DATABASE_SCHEMA.sql) (exact
  tables/columns) and [`BACKEND_REQUIREMENTS.md`](BACKEND_REQUIREMENTS.md)
  (concise app-API summary). **This guide supersedes and expands both.**
- **Conventions:** JSON APIs, `snake_case` keys, ISO-8601 UTC timestamps,
  `Authorization: Bearer <token>`, money as integer **cents**, soft-deletes for
  users/leads, pagination `{ data, meta:{page,per_page,total} }`.

---

## 1. Who uses what

| Actor | Surface | In one line |
|-------|---------|-------------|
| **Agent** | Mobile app (Flutter) | Receives assigned leads, accepts/rejects with feedback, manages profile |
| **Staff** | Web dashboard | Approves & manages agents, creates & assigns leads, reviews feedback & analytics |
| **Admin** | Web dashboard | Everything staff can do **+** manages staff and sees revenue |

There is **one admin** (super-user) and **multiple staff**. All three are rows in
`users` distinguished by `role`. Only `role='agent'` can log into the mobile app;
only `staff`/`admin` can log into the dashboard.

---

## 2. Roles & permissions matrix

| Capability | Agent | Staff | Admin |
|---|:---:|:---:|:---:|
| Sign up / log into mobile app | ✅ | — | — |
| View & respond to own assigned leads | ✅ | — | — |
| Edit own profile (phone, brokerage, license, service areas, bio) | ✅ | — | — |
| Log into dashboard | — | ✅ | ✅ |
| **Approve / reject agent signups** | — | ✅ | ✅ |
| Create leads | — | ✅ | ✅ |
| **Assign / reassign leads to agents** | — | ✅ | ✅ |
| View agent feedback, accepted/rejected leads | — | ✅ | ✅ |
| Suspend / remove an agent | — | ✅ | ✅ |
| View leads analytics (totals, conversion, per-agent) | — | ✅ | ✅ |
| **Manage staff (add / edit / remove staff)** | — | — | ✅ |
| **View total revenue** | — | — | ✅ |
| Manage app content (About/Privacy/Terms/Help) | — | — | ✅ |

> Enforce with Laravel Policies/Gates keyed on `users.role`. Admin passes every
> staff gate.

---

## 3. Account lifecycle & the agent-approval workflow

`users.status` ∈ `pending → active → suspended / rejected`.

**Agent signup (mobile):**
1. Agent submits email → backend emails a 6-digit OTP (`verification_codes`).
2. Agent verifies OTP → sets a password → `users` row created with
   `role='agent'`, `status='pending'`; `agent_profiles` row created.
3. **Notify staff + admin**: in-app `notification (type=agent_signup)` + **email**
   to every staff/admin — "New agent awaiting approval."
4. Agent can log in but sees a **"pending approval"** state (the app should gate
   lead access on `status`). *Backend:* return `status` in `/me`.

**Staff/admin approval (dashboard):**
- **Approve** → `users.status='active'`, `agent_profiles.approved_by/at` set →
  notify the agent (push + email: "You're approved"). Log to `activity_logs`.
- **Reject** → `users.status='rejected'`, store `rejected_reason` → email agent.
- **Suspend** → `users.status='suspended'` (revoke tokens; block API).
- **Remove** → soft-delete (`users.deleted_at`); revoke tokens/devices.

Only `active` agents may be assigned leads or call lead endpoints.

**Staff creation (admin only):** admin creates a `users` row with `role='staff'`,
`status='active'`, `created_by=admin.id`, and emails an invite / temp password.

---

## 4. Lead lifecycle (the core loop)

```
 (staff creates lead)                (agent opens app)         (staff/admin)
        │                                    │                       │
        ▼                                    ▼                       ▼
      new ──assign to agent──► new(assigned) ──accept──► in_progress ──► converted
        │                                    │                              ▲
        └───────────────────────────────────┴──reject(reason)──► rejected  │
                                                                            └ (deal closes)
```

1. **Create** — staff/admin create a `leads` row (client info + property details
   + `lead_fee_cents`), `status='new'`, `created_by`.
2. **Assign** — staff/admin set `assigned_to`, `assigned_by`, `assigned_at`;
   append `lead_assignments`. **Notify that agent** (push + in-app
   `type=new_lead`): "New lead assigned."
3. **Agent responds** (`POST /leads/{id}/respond`):
   - **accept** → `status='in_progress'`, `responded_at=now`; insert
     `lead_feedback(decision=accept)`; (optionally create a `transactions`
     `lead_fee` row for revenue).
   - **reject** → `status='rejected'`, `responded_at=now`; insert
     `lead_feedback(decision=reject, reason, comments)`.
   - Either way: append `lead_status_history` and **notify staff + admin**
     (in-app `type=feedback` + **email**: "Agent X accepted/rejected lead Y").
4. **Convert** — later, staff/admin (or an outcome update) set
   `status='converted'`, `converted_at`; notify the agent (`type=lead_update`).

Every transition writes `lead_status_history` (powers analytics + audit).

---

## 5. Notifications — push, in-app, and email

Two delivery channels off the same events. For each event: create one
`notifications` row **per recipient** (in-app list + FCM push, respecting
`user_settings.push_notifications`) and send an **email** (respecting
`user_settings.email_notifications`), logged in `email_log`.

| # | Event (trigger) | Recipients | in-app `type` | Push | Email |
|---|---|---|:---:|:---:|:---:|
| 1 | New agent signs up | all staff + admin | `agent_signup` | ✅ | ✅ |
| 2 | Agent approved / rejected | that agent | `system` | ✅ | ✅ |
| 3 | Lead assigned to agent | that agent | `new_lead` | ✅ | — |
| 4 | **Agent accepts a lead** | all staff + admin | `feedback` | ✅ | ✅ |
| 5 | **Agent rejects a lead** (with reason) | all staff + admin | `feedback` | ✅ | ✅ |
| 6 | **Agent submits feedback/comments** | all staff + admin | `feedback` | ✅ | ✅ |
| 7 | Lead marked converted | that agent | `lead_update` | ✅ | — |
| 8 | Agent removed/suspended | that agent | `system` | — | ✅ |

**Push:** Firebase Cloud Messaging (Android + iOS/APNs). The app registers a
token via `POST /devices` after login and on refresh; unregister on logout. The
app already has a `NotificationService` interface ready for the FCM wiring.

**Email:** send to each staff+admin recipient; use Laravel queued Mailables +
notifications. Emails 4–6 are the "status update" emails the business requires on
every agent decision.

---

## 6. Mobile app API (agent)

Base: `/api`. All except auth require `Authorization: Bearer`. Reject non-`active`
agents with `403 { code: "account_not_active", status }`.

**Auth**
| Method & path | Body → Result |
|---|---|
| `POST /auth/register/start` | `{email}` → emails OTP |
| `POST /auth/register/verify` | `{email,code}` → `{verification_token}` |
| `POST /auth/register/complete` | `{email,verification_token,password,name}` → creates agent (`pending`), returns token; **fires event #1** |
| `POST /auth/login` | `{email,password}` → `{token, agent}` |
| `POST /auth/password/forgot` | `{email}` → emails OTP |
| `POST /auth/password/verify` | `{email,code}` → `{reset_token}` |
| `POST /auth/password/reset` | `{email,reset_token,password}` |
| `POST /auth/logout` | revoke token + device |
| `GET  /me` | agent profile incl. `status`, `verified` |
| `PUT  /me` | `{name,phone,service_areas[],license_number,license_state,brokerage,bio}` |
| `POST /me/avatar` | multipart image |
| `POST /me/change-password` | `{current_password,new_password}` |
| `GET/PUT /me/settings` | `{push_notifications,email_notifications,language}` |
| `DELETE /me` | soft-delete account |

**Leads** (scoped to the authenticated agent)
| Method & path | Purpose |
|---|---|
| `GET /leads` | query: `status,intent,q,sort(newest|oldest|price_high|price_low|name_az),page` |
| `GET /leads/{id}` | full detail |
| `GET /leads/stats` | `{total,new,in_progress,converted,rejected}` (dashboard cards) |
| `GET /leads/analytics?range=week` | `{ series:[{date,count}], pipeline:{...} }` (charts) |
| `POST /leads/{id}/respond` | `{decision:'accept'}` or `{decision:'reject',reason,comments?}` → **fires events #4/#5/#6** |

**Notifications**
| `GET /notifications` · `POST /notifications/{id}/read` · `POST /notifications/read-all` · `GET /notifications/unread-count` · `POST /devices` `{token,platform}` · `DELETE /devices/{token}` |

**Content**: `GET /content/{about|privacy|terms|help}` → `{title,intro?,sections:[{heading,body}]}`.

---

## 7. Dashboard API / pages (staff + admin)

Base: `/admin` (or dashboard web routes). Gate by role.

**Agents**
- `GET /agents` — filter by `status` (esp. `pending`), search, paginate.
- `GET /agents/{id}` — profile + their leads + feedback + performance.
- `POST /agents/{id}/approve` · `POST /agents/{id}/reject` `{reason}` — **event #2**.
- `POST /agents/{id}/suspend` · `POST /agents/{id}/restore`.
- `DELETE /agents/{id}` — soft-delete/remove — **event #8**.

**Leads**
- `GET /leads` — all leads; filter by status/agent/date; search.
- `POST /leads` — create; `PUT /leads/{id}` — edit; `DELETE /leads/{id}`.
- `POST /leads/{id}/assign` `{agent_id}` — assign/reassign — **event #3**.
- `POST /leads/{id}/status` `{status}` — e.g. mark converted — **event #7**.
- `GET /leads/{id}/feedback` — the agent's decision, reason, comments.

**Analytics** (§8) · **Staff (admin only)** · **Content (admin only)**.

**Staff management (admin only)**
- `GET /staff` · `POST /staff` `{name,email}` (invite) · `PUT /staff/{id}` ·
  `POST /staff/{id}/suspend` · `DELETE /staff/{id}`.

---

## 8. Analytics & revenue

All derived from the tables — no extra storage needed beyond
`lead_status_history` (timeline) and `transactions` (money).

**Leads analytics (staff + admin)**
- Totals by status: `GROUP BY leads.status`.
- Conversion rate: `converted / total`.
- Trend: leads created/responded per day/week from `leads`/`lead_status_history`.
- Rejection reasons breakdown: `GROUP BY lead_feedback.reason`.
- Per-agent scorecard: assigned, accepted, rejected, converted, conversion %,
  avg. response time (`responded_at - assigned_at`).

**Revenue (admin only)**
- **Total revenue** = `SUM(amount_cents) WHERE status='completed'` from
  `transactions` (÷100 for dollars).
- Revenue over time: `GROUP BY DATE(created_at)`.
- Revenue by agent / by lead. A per-lead fee is recorded as a `lead_fee`
  transaction (created on accept, or on conversion — **confirm with business**).

> **Confirm the monetization model** (per-lead fee vs subscription vs commission).
> The schema supports all via `transactions.type`; wire the exact trigger once
> decided.

---

## 9. Enumerations (must match the app exactly)

- **user role:** `agent`, `staff`, `admin`
- **user status:** `pending`, `active`, `suspended`, `rejected`
- **lead status:** `new`, `in_progress`, `converted`, `rejected`
- **lead intent:** `buying`, `selling`
- **rejection reason:** `not_in_service_area`, `not_interested`, `not_serious`,
  `budget_too_low`, `property_type_mismatch`, `already_working_other`, `other`
- **notification type:** `new_lead`, `lead_update`, `feedback`, `agent_signup`, `system`
- **transaction type:** `lead_fee`, `subscription`, `adjustment`

---

## 10. Security & operational notes

- Hash passwords (bcrypt/argon) and OTP codes; never store raw codes.
- Rate-limit auth/OTP endpoints; expire OTPs (~10 min), cap attempts, throttle resend.
- Scope every agent query by `assigned_to = auth id`; never trust client-supplied ids.
- Revoke tokens + devices on logout, suspend, and remove.
- Queue all emails and push sends (Laravel queues) so API responses stay fast.
- Validate on the server to the same rules the app enforces (email format, password ≥ 8).
- Log privileged actions to `activity_logs` for admin audit.

## 11. Suggested build order

1. Migrations from `DATABASE_SCHEMA.sql`; seed one admin.
2. Auth (OTP signup, login, reset) + Sanctum tokens.
3. Agent profile + settings endpoints (`/me`).
4. Leads CRUD + assignment (dashboard) and agent read/respond (app).
5. Notification engine (in-app rows → FCM push → queued emails) wired to events #1–#8.
6. Agent approval + staff/agent management (dashboard).
7. Analytics + revenue endpoints and dashboard pages.
8. Content pages; app points to `/content/*`.

Once auth + leads + notifications exist, point the app's data layer at the real
API (the Flutter repositories/controllers already isolate this behind method
signatures that match these endpoints).
