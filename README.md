# Open Referral — Backend & Dashboard

Laravel 12 + MySQL backend for the Open Referral network:

- **Mobile agent API** (`/api`) for the Flutter agent app — Sanctum tokens, OTP
  signup, assigned leads, accept/reject with feedback, notifications, push.
- **Staff & admin dashboard** (`/admin`) in Blade + Vite + Tailwind — agent
  approvals, lead creation and routing, analytics, revenue, staff management,
  editable app content.
- **Agent web portal** (`/agent`) — a browser equivalent of the app's core
  screens for agents who need it.

Built to the specs in `docs/`:

| File | What it covers |
|---|---|
| [`docs/BACKEND_GUIDE.md`](docs/BACKEND_GUIDE.md) | The authoritative guide — roles, workflows, both APIs, notification events, analytics |
| [`docs/BACKEND_REQUIREMENTS.md`](docs/BACKEND_REQUIREMENTS.md) | Concise summary of the mobile API surface |
| [`docs/DATABASE_SCHEMA.sql`](docs/DATABASE_SCHEMA.sql) | Reference MySQL schema |
| [`docs/openapi.yaml`](docs/openapi.yaml) | Full OpenAPI spec for the mobile API |

---

## Setup

```bash
composer install && npm install
```

```bash
cp .env.example .env && php artisan key:generate
```

Create the databases (MySQL), then:

```bash
php artisan migrate --seed && php artisan storage:link && npm run build
```

Serve it with XAMPP (point a vhost at `public/`) or:

```bash
php artisan serve
```

Emails and push are queued, so run a worker alongside the app:

```bash
php artisan queue:work
```

### Demo logins

The seeder creates one admin, two staff, and six agents (one pending approval,
one suspended) plus ~40 leads across every status. Password for all of them is
`password`.

| Role | Email |
|---|---|
| Admin | `admin@openreferral.us` |
| Staff | `ethan.b@openreferral.us` |
| Agent | `carlos.r@example.net` |
| Agent (pending) | `sarah.w@example.com` |

---

## How the system fits together

### Roles

| | Agent | Staff | Admin |
|---|:---:|:---:|:---:|
| Mobile app | ✅ | — | — |
| Dashboard | web portal only | ✅ | ✅ |
| Approve / suspend / remove agents | — | ✅ | ✅ |
| Create, edit, assign leads · mark converted | — | ✅ | ✅ |
| Leads analytics | — | ✅ | ✅ |
| Revenue, staff management, app content, settings, audit log | — | — | ✅ |

Enforced by `role` middleware on the route groups plus policies and gates in
`app/Providers/AppServiceProvider.php`. An admin passes every staff gate.

### Account lifecycle

`pending → active → suspended → active`, or `pending → rejected`; removal is a
soft delete. Agents sign up as **pending**: they can log in and edit their
profile, but every `/api/leads` call returns
`403 {"code":"account_not_active","status":"pending"}` until staff approve them.
Suspending, rejecting, or removing an agent revokes their tokens and devices and
returns their open leads to the unassigned pool.

### Lead lifecycle

```
staff creates → new → assign to agent → agent accepts → in_progress → converted
                                     └→ agent rejects (reason) → rejected
```

Two API surfaces drive this from the app: `POST /leads/{id}/respond` for the
first accept/reject of a `new` lead, and `POST /leads/{id}/status` for
everything after that (converted, or a late rejection). Staff and admins are
notified either way, and the dashboard can override any lead's status itself.

Every transition writes `lead_status_history`; every decision writes
`lead_feedback`; privileged actions write `activity_logs`. Accepting a lead
records a `lead_fee` transaction, which is what the admin revenue page sums.
`app/Services/LeadWorkflow.php` owns all of it — controllers just validate and
delegate.

### Notification events

`app/Services/NotificationDispatcher.php` implements the eight events from the
guide. Each one writes an in-app `notifications` row per recipient, sends an FCM
push (honouring `user_settings.push_notifications`), and queues an email
(honouring `email_notifications`, logged to `email_log`).

| # | Event | Recipients | In-app | Push | Email |
|---|---|---|:---:|:---:|:---:|
| 1 | New agent signs up | all staff + admin | `agent_signup` | ✅ | ✅ |
| 2 | Agent approved / rejected | that agent | `system` | ✅ | ✅ |
| 3 | Lead assigned | that agent | `new_lead` | ✅ | — |
| 4 | Agent accepts a lead | all staff + admin | `feedback` | ✅ | ✅ |
| 5 | Agent rejects a lead | all staff + admin | `feedback` | ✅ | ✅ |
| 6 | Agent submits comments | all staff + admin | `feedback` | ✅ | ✅ |
| 7 | Lead marked converted | that agent | `lead_update` | ✅ | — |
| 8 | Agent suspended / removed | that agent | `system` | ✅¹ | ✅ |

¹ No push on removal — the account is gone.

Account emails (approval, rejection, suspension, password reset) ignore the
email preference by design.

### Email delivery — set up from the dashboard

**Nothing is emailed until this is configured.** Agent signup and password reset
both send a 6-digit code, so with delivery switched off nobody can complete
signup — the code only lands in `storage/logs/laravel.log`.

OTP emails are sent **inline rather than queued**, so signup works even when no
queue worker is running, and a transport failure answers
`503 {"code":"mail_not_configured"}` instead of falsely reporting that a code
was sent. Every other email stays queued.

An admin sets it up in the browser at **Settings → Email delivery**
(`/admin/settings#email-delivery`) — no `.env` editing, no redeploy:

1. Pick a **provider preset** (Gmail, Outlook, cPanel/Hostinger, SendGrid,
   Mailgun, Postmark, Brevo) — it fills in host, port and encryption.
2. Enter the SMTP username, password, and the "from" address agents will see.
3. **Save**, then **Send a test email**. The test sends immediately rather than
   through the queue and shows the provider's exact rejection message, so a bad
   password or blocked port is obvious straight away.

The page carries step-by-step instructions for each provider (including how to
create a Google App Password) and a troubleshooting list for the common
failures. Every attempt is recorded in the delivery log at the bottom of the
page. While delivery is off, a red banner on the dashboard says so.

Under the hood (`app/Support/MailSettings.php`): settings live in `app_settings`
with the **password encrypted at rest**, and are layered over `config/mail.php`
at boot. `mail_transport` picks the mode:

| Mode | Behaviour |
|---|---|
| `smtp` | Use the credentials stored in the dashboard |
| `log` | Write emails to the log, deliver nothing (development) |
| `env` | Leave whatever `.env` says — the default |

Saving also runs `queue:restart`, so a running worker picks up the new
credentials instead of holding the old ones in memory. Half-filled SMTP falls
back to the log rather than throwing on every send.

### Push notifications (FCM)

`app/Services/FcmService.php` supports the HTTP v1 API (service-account JSON,
preferred — it signs its own JWT, no extra package) and the legacy server key.

Set it up at **Settings → Push notifications**: paste the Firebase
service-account JSON and save. It is stored encrypted in `app_settings` and
takes effect immediately; `FCM_CREDENTIALS` / `FCM_SERVER_KEY` in `.env` still
work as a fallback. The section also carries the full Firebase setup walkthrough
and lists which files the mobile team needs.

**With neither set, pushes are logged and skipped** — everything else still
works, which is why the test suite and local development need no Firebase
project. Tokens FCM reports as unregistered are pruned from `devices`
automatically.

The push `data` payload the app deep-links on:

```json
{ "type": "lead_assigned", "notification_type": "new_lead",
  "lead_id": "123", "reference_id": "LEAD-10123" }
```

`type` is the action key (`lead_assigned`, `lead_status_updated`,
`lead_feedback`, `agent_signup`, `system`); `notification_type` is the same enum
the `/notifications` list returns.

### Uploaded files

Avatars are stored on the `public` disk and served from `/storage/…`. That disk
has `'serve' => true`, so Laravel streams the files through PHP when
`public/storage` is missing — the usual cause of avatars 404ing on a server
where `storage:link` could not create a usable symlink. Run `php artisan
storage:link` anyway for the faster path; **Settings → Uploaded files** reports
whether the symlink is present, whether storage is writable, and how many
avatars exist.

The private `local` disk is deliberately **not** served over HTTP.

---

## Layout

```
app/
  Enums/                 Role, UserStatus, LeadStatus, LeadIntent,
                         RejectionReason, NotificationType, TransactionType
  Http/Controllers/
    Api/                 Mobile API: Auth, Profile, Lead, Notification, Content
    Admin/               Dashboard: Dashboard, Agent, Lead, Staff, Analytics,
                         Revenue, Content, Setting, ActivityLog
    Agent/               Agent web portal
  Http/Middleware/       RoleMiddleware, EnsureActiveAgent
  Http/Resources/        API response shapes (snake_case, ISO-8601)
  Mail/                  Queued mailables for every email event
  Services/
    OtpService           6-digit codes: hashed, expiring, attempt-capped
    AgentAccountService  Signup + the approval workflow
    LeadWorkflow         Assign / respond / status transitions
    NotificationDispatcher  The 8 events → in-app + push + email
    FcmService           Firebase Cloud Messaging
    AnalyticsService     Pipeline, conversion, scorecard, revenue
  Support/               Mailer (email_log wrapper), ActivityLogger
resources/views/
  components/ui/         Shared Blade components (stat, field, chart, badges)
  components/emails/     Email layout and button
  admin/ agent/ auth/    Pages
docs/                    Specs + OpenAPI
legacy_backup/           Pre-existing code from an older schema (see below)
```

## Tests

```bash
php artisan test
```

107 feature tests cover the OTP signup flow, login and account-state gating,
password reset, profile and avatar handling, lead scoping and filters, the
accept/reject workflow with its notification fan-out and fee billing, the
approval workflow, dashboard access control, staff management, content
publishing, analytics maths, revenue totals, the SMTP setup flow (encryption at
rest, transport switching, test-send error reporting), agent-driven status
changes, avatar serving over HTTP, and the FCM payload contract.

Tests run against a **separate MySQL database** (`openreferral_test`, set in
`phpunit.xml`) so development data is never touched. Create it once:

```bash
mysql -u root -e "CREATE DATABASE openreferral_test"
```

The analytics queries use MySQL functions (`TIMESTAMPDIFF`, `FIELD`), so SQLite
is not a drop-in substitute for the test database.

---

## Notes for whoever picks this up next

- **`legacy_backup/`** holds controllers, models, and views from an earlier
  version of this project that targeted a different schema (packages,
  subscriptions, tickets, follow-ups, `assigned_agent_id`, extra lead statuses).
  Those tables do not exist in `DATABASE_SCHEMA.sql`, so the code was dead and
  several pages were broken. It is kept for reference only — nothing loads it,
  and it can be deleted once you are confident nothing there is wanted.
- **Monetization is wired as a per-lead fee billed on acceptance**
  (`LeadWorkflow::recordLeadFee`). The guide flags this as needing business
  confirmation; if fees should instead be charged on conversion, move that call
  into `changeStatus`. `transactions.type` already supports subscriptions and
  adjustments, and the revenue page can record them by hand.
- **Agent `title` and `verified`** are staff-controlled, not agent-editable.
  Verification is toggled from the agent detail page.
- **Timestamps are UTC** end to end. The dashboard prints raw UTC; add a display
  timezone if the team wants local times.
#   o p e n r e f e r r a l 
 
 