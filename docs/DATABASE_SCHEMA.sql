-- ============================================================================
-- Open Referral — MySQL schema (reference for the Laravel backend)
-- ----------------------------------------------------------------------------
-- Engine: InnoDB, charset utf8mb4. Timestamps are UTC. Money is stored as
-- integer CENTS (avoid floats). This schema covers BOTH the mobile agent app
-- and the admin/staff web dashboard. See docs/BACKEND_GUIDE.md for the process,
-- workflows, endpoints, and email/notification events.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- 1. USERS & ROLES  (agent = mobile app; staff + admin = web dashboard)
-- ============================================================================

CREATE TABLE users (
  id                BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  role              ENUM('agent','staff','admin') NOT NULL DEFAULT 'agent',
  name              VARCHAR(120) NOT NULL,
  email             VARCHAR(190) NOT NULL,
  email_verified_at TIMESTAMP NULL,
  password_hash     VARCHAR(255) NOT NULL,
  phone             VARCHAR(40) NULL,
  avatar_url        VARCHAR(500) NULL,
  -- Account lifecycle. Agents start 'pending' until a staff/admin approves.
  -- Staff/admin are created 'active' by an admin.
  status            ENUM('pending','active','suspended','rejected') NOT NULL DEFAULT 'pending',
  created_by        BIGINT UNSIGNED NULL,   -- admin who created a staff account
  last_login_at     TIMESTAMP NULL,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at        TIMESTAMP NULL,          -- soft delete (removing an agent/staff)
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role_status (role, status),
  CONSTRAINT fk_users_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Agent professional profile (1:1 with users where role='agent').
CREATE TABLE agent_profiles (
  user_id        BIGINT UNSIGNED PRIMARY KEY,
  title          VARCHAR(120) NOT NULL DEFAULT 'Real Estate Agent',
  verified       TINYINT(1) NOT NULL DEFAULT 0,   -- blue-check shown in the app
  license_number VARCHAR(80) NULL,
  license_state  VARCHAR(80) NULL,
  brokerage      VARCHAR(160) NULL,
  bio            TEXT NULL,
  -- Approval workflow (staff/admin gate agent access):
  approved_by    BIGINT UNSIGNED NULL,
  approved_at    TIMESTAMP NULL,
  rejected_reason VARCHAR(255) NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_agent_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_agent_profiles_approver FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Service areas an agent covers (1:N). Used to match leads to agents.
CREATE TABLE agent_service_areas (
  id       BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id  BIGINT UNSIGNED NOT NULL,
  area     VARCHAR(160) NOT NULL,             -- e.g. "Miami, FL"
  CONSTRAINT fk_service_area_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_user_area (user_id, area)
) ENGINE=InnoDB;

-- Per-user app/notification preferences.
CREATE TABLE user_settings (
  user_id             BIGINT UNSIGNED PRIMARY KEY,
  push_notifications  TINYINT(1) NOT NULL DEFAULT 1,
  email_notifications TINYINT(1) NOT NULL DEFAULT 1,
  language            VARCHAR(10) NOT NULL DEFAULT 'en',
  theme               ENUM('system','light','dark') NOT NULL DEFAULT 'system',
  CONSTRAINT fk_user_settings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================================
-- 2. AUTH  (email OTP for signup + password reset; API tokens)
-- ============================================================================

CREATE TABLE verification_codes (
  id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  email       VARCHAR(190) NOT NULL,
  code_hash   VARCHAR(255) NOT NULL,          -- store a hash, never the raw code
  purpose     ENUM('signup','password_reset') NOT NULL,
  attempts    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  consumed_at TIMESTAMP NULL,
  expires_at  TIMESTAMP NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_codes_email_purpose (email, purpose),
  KEY idx_codes_expires (expires_at)
) ENGINE=InnoDB;

-- If not using Laravel Sanctum's personal_access_tokens table.
CREATE TABLE auth_tokens (
  id         BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id    BIGINT UNSIGNED NOT NULL,
  token_hash VARCHAR(255) NOT NULL,
  device_id  BIGINT UNSIGNED NULL,
  expires_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_token_hash (token_hash),
  CONSTRAINT fk_token_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================================
-- 3. LEADS
-- ============================================================================

CREATE TABLE leads (
  id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  reference_id  VARCHAR(20) NOT NULL,                 -- e.g. LEAD-10234
  -- Client (the buyer/seller behind the lead):
  name          VARCHAR(160) NOT NULL,
  avatar_url    VARCHAR(500) NULL,
  phone         VARCHAR(40) NULL,
  email         VARCHAR(190) NULL,
  -- Lead details (shown on the app's lead detail screen):
  intent        ENUM('buying','selling') NOT NULL,
  property_type VARCHAR(120) NULL,
  price_min     INT UNSIGNED NULL,                    -- property budget, whole dollars
  price_max     INT UNSIGNED NULL,
  location      VARCHAR(190) NULL,
  timeline      VARCHAR(80) NULL,
  bedrooms      VARCHAR(20) NULL,
  bathrooms     VARCHAR(20) NULL,
  source        VARCHAR(80) NULL,                     -- Website, Referral, ...
  notes         TEXT NULL,
  -- Monetization: the fee this lead is worth to the business (cents).
  lead_fee_cents INT UNSIGNED NOT NULL DEFAULT 0,
  -- Lifecycle + assignment:
  status        ENUM('new','in_progress','converted','rejected') NOT NULL DEFAULT 'new',
  assigned_to   BIGINT UNSIGNED NULL,                 -- agent user id
  assigned_by   BIGINT UNSIGNED NULL,                 -- staff/admin user id
  assigned_at   TIMESTAMP NULL,
  responded_at  TIMESTAMP NULL,                       -- when agent accepted/rejected
  converted_at  TIMESTAMP NULL,
  created_by    BIGINT UNSIGNED NULL,                 -- staff/admin who created the lead
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at    TIMESTAMP NULL,
  UNIQUE KEY uq_leads_reference (reference_id),
  KEY idx_leads_assigned (assigned_to, status),
  KEY idx_leads_status (status),
  KEY idx_leads_created (created_at),
  CONSTRAINT fk_leads_assigned_to FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_leads_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_leads_created_by  FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Assignment history (a lead may be reassigned between agents over time).
CREATE TABLE lead_assignments (
  id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  lead_id     BIGINT UNSIGNED NOT NULL,
  agent_id    BIGINT UNSIGNED NOT NULL,
  assigned_by BIGINT UNSIGNED NULL,             -- staff/admin
  assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  unassigned_at TIMESTAMP NULL,
  CONSTRAINT fk_assign_lead  FOREIGN KEY (lead_id)  REFERENCES leads(id) ON DELETE CASCADE,
  CONSTRAINT fk_assign_agent FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE CASCADE,
  KEY idx_assign_lead (lead_id),
  KEY idx_assign_agent (agent_id)
) ENGINE=InnoDB;

-- Agent's accept/reject decision + feedback (this is what triggers the
-- email/notification to staff + admin).
CREATE TABLE lead_feedback (
  id         BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  lead_id    BIGINT UNSIGNED NOT NULL,
  agent_id   BIGINT UNSIGNED NOT NULL,
  decision   ENUM('accept','reject') NOT NULL,
  reason     ENUM('not_in_service_area','not_interested','not_serious',
                  'budget_too_low','property_type_mismatch',
                  'already_working_other','other') NULL,   -- required when reject
  comments   TEXT NULL,                                    -- optional free text
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_feedback_lead  FOREIGN KEY (lead_id)  REFERENCES leads(id) ON DELETE CASCADE,
  CONSTRAINT fk_feedback_agent FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE CASCADE,
  KEY idx_feedback_lead (lead_id),
  KEY idx_feedback_agent (agent_id),
  KEY idx_feedback_created (created_at)
) ENGINE=InnoDB;

-- Every status transition (for the analytics timeline + audit).
CREATE TABLE lead_status_history (
  id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  lead_id     BIGINT UNSIGNED NOT NULL,
  from_status ENUM('new','in_progress','converted','rejected') NULL,
  to_status   ENUM('new','in_progress','converted','rejected') NOT NULL,
  changed_by  BIGINT UNSIGNED NULL,             -- agent / staff / admin / system
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_lsh_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
  KEY idx_lsh_lead (lead_id),
  KEY idx_lsh_created (created_at)
) ENGINE=InnoDB;

-- ============================================================================
-- 4. NOTIFICATIONS  (in-app list + push + email)
-- ============================================================================

-- One row PER RECIPIENT. Broadcasting to "all staff + admin" = insert one row
-- per staff/admin user. Mirrors the app's Notifications screen.
CREATE TABLE notifications (
  id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id     BIGINT UNSIGNED NOT NULL,               -- recipient
  type        ENUM('new_lead','lead_update','feedback','agent_signup','system') NOT NULL,
  title       VARCHAR(190) NOT NULL,
  body        VARCHAR(500) NOT NULL,
  lead_id     BIGINT UNSIGNED NULL,                   -- deep-link target (app)
  actor_id    BIGINT UNSIGNED NULL,                   -- who caused it (e.g. the agent)
  read_at     TIMESTAMP NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notif_user  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_notif_lead  FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL,
  KEY idx_notif_user_read (user_id, read_at),
  KEY idx_notif_created (created_at)
) ENGINE=InnoDB;

-- Registered push tokens (FCM) per device, per user.
CREATE TABLE devices (
  id           BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id      BIGINT UNSIGNED NOT NULL,
  token        VARCHAR(400) NOT NULL,
  platform     ENUM('android','ios','web') NOT NULL,
  last_seen_at TIMESTAMP NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_device_token (token),
  CONSTRAINT fk_device_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  KEY idx_device_user (user_id)
) ENGINE=InnoDB;

-- Audit log of emails sent (optional but recommended for deliverability debugging).
CREATE TABLE email_log (
  id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id     BIGINT UNSIGNED NULL,                   -- recipient (null for external)
  to_email    VARCHAR(190) NOT NULL,
  event       VARCHAR(60) NOT NULL,                   -- e.g. 'lead_feedback','agent_signup'
  subject     VARCHAR(190) NOT NULL,
  status      ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
  error       VARCHAR(500) NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_email_event (event),
  KEY idx_email_user (user_id)
) ENGINE=InnoDB;

-- ============================================================================
-- 5. REVENUE  (drives the admin "total revenue" analytics)
-- ============================================================================
-- Confirm the exact monetization with the business. Modeled flexibly here:
-- revenue = SUM(amount_cents) of transactions WHERE status='completed'.

CREATE TABLE transactions (
  id           BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  agent_id     BIGINT UNSIGNED NOT NULL,              -- who paid
  lead_id      BIGINT UNSIGNED NULL,                  -- set for per-lead fees
  type         ENUM('lead_fee','subscription','adjustment') NOT NULL DEFAULT 'lead_fee',
  amount_cents INT NOT NULL,                          -- can be negative (refund/adjustment)
  currency     CHAR(3) NOT NULL DEFAULT 'USD',
  status       ENUM('pending','completed','refunded','failed') NOT NULL DEFAULT 'completed',
  reference    VARCHAR(120) NULL,                     -- external gateway id (Stripe, etc.)
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_txn_agent FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_txn_lead  FOREIGN KEY (lead_id)  REFERENCES leads(id) ON DELETE SET NULL,
  KEY idx_txn_agent (agent_id),
  KEY idx_txn_status_created (status, created_at)
) ENGINE=InnoDB;

-- ============================================================================
-- 6. ADMIN / AUDIT / CONTENT
-- ============================================================================

-- Who did what (admin oversight): approvals, assignments, removals, etc.
CREATE TABLE activity_logs (
  id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  actor_id    BIGINT UNSIGNED NULL,                   -- staff/admin/agent
  action      VARCHAR(80) NOT NULL,                   -- 'agent.approved','lead.assigned',...
  subject_type VARCHAR(40) NULL,                      -- 'user','lead',...
  subject_id  BIGINT UNSIGNED NULL,
  meta_json   JSON NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_activity_actor (actor_id),
  KEY idx_activity_subject (subject_type, subject_id),
  KEY idx_activity_created (created_at)
) ENGINE=InnoDB;

-- Editable static content for the app's About/Privacy/Terms/Help screens.
CREATE TABLE content_pages (
  id         BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  slug       VARCHAR(40) NOT NULL,                    -- about | privacy | terms | help
  title      VARCHAR(190) NOT NULL,
  intro      TEXT NULL,
  body_json  JSON NOT NULL,                           -- [{heading, body}, ...]
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_content_slug (slug)
) ENGINE=InnoDB;

-- ============================================================================
-- Example analytics queries (for the dashboard) — not tables:
--   Total revenue:        SELECT SUM(amount_cents) FROM transactions WHERE status='completed';
--   Leads by status:      SELECT status, COUNT(*) FROM leads GROUP BY status;
--   Conversion rate:      converted / NULLIF(total,0) from the above.
--   Per-agent scorecard:  GROUP BY assigned_to over leads + lead_feedback.
--   New agents to approve: SELECT * FROM users WHERE role='agent' AND status='pending';
-- ============================================================================
