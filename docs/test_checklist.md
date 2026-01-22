
## Test Scenarios & Checklist

### Scenario 1: Fresh Install, Email-Only Mode
**Choices:**
- Storage: `email`
- Recipients: `admin@example.com`
- Form Fields: default or custom
- Spam Protection: base only, or with captcha

**Expected Results:**
- [x] `config/packages/contact_us.yaml` created with storage=email
- [x] Form routes imported to `config/routes.yaml`
- [x] No CRUD routes
- [x] No database migration needed
- [x] No table cleanup offered (first install)
- [x] Form available at `/contact`
- [x] Admin receives email on submission
- [x] Client receives email on submission (when enabled)

---

### Scenario 2: Fresh Install, Database-Only Mode (Bundle Entity)
**Choices:**
- Storage: `database`
- Entity: Bundle's ContactMessageEntity
- Form Fields: default or custom
- Spam Protection: base only, or with captcha
- CRUD Route Prefix: `/admin/contact` (default)

**Expected Results:**
- [x] `config/packages/contact_us.yaml` created with storage=database
- [x] Form routes imported to `config/routes.yaml`
- [x] CRUD admin routes imported with prefix `/admin/contact`
- [x] `entity_class` set to bundle entity
- [x] Doctrine migration offered (user accepts or runs manually)
- [x] After migration: admin routes available at `/admin/contact`
- [x] Form available at `/contact`
- [x] Messages saved to database, no email sent

---

### Scenario 3: Fresh Install, Database-Only Mode (Custom Entity)
**Choices:**
- Storage: `database`
- Entity: User's custom entity (e.g., `App\Entity\ContactSubmission`)
- Form Fields: auto-detect from entity or custom
- Spam Protection: base only, or with captcha
- CRUD Route Prefix: not asked (custom entity)

**Expected Results:**
- [ ] `config/packages/contact_us.yaml` created with storage=database
- [ ] Form routes imported to `config/routes.yaml`
- [ ] No CRUD routes (custom entity, user handles their own)
- [ ] `entity_class` set to custom entity
- [ ] No migration offered (custom entity already exists)
- [ ] Form available at `/contact`
- [ ] Messages saved to custom entity table, no email

---

### Scenario 4: Fresh Install, Both Mode (Bundle Entity, Verification ON)
**Choices:**
- Storage: `both`
- Recipients: `admin@example.com`
- Entity: Bundle's ContactMessageEntity
- Email Verification: `enabled`
- Form Fields: default or custom
- Spam Protection: base only, or with captcha
- CRUD Route Prefix: `/admin/contact` (default)

**Expected Results:**
- [ ] `config/packages/contact_us.yaml` created with storage=both, email_verification.enabled=true
- [ ] Form routes imported to `config/routes.yaml`
- [ ] CRUD admin routes imported with prefix `/admin/contact`
- [ ] `entity_class` set to bundle entity
- [ ] Doctrine migration offered
- [ ] After form submission:
  - [ ] Message saved as unverified (verified=false)
  - [ ] Sender receives verification email with link + message preview
  - [ ] Admin does NOT receive email yet
  - [ ] Sender clicks verification link
  - [ ] Message marked as verified (verified=true)
  - [ ] Admin receives notification email
  - [ ] Sender does NOT receive another copy (already got verification email)
  - [ ] Verification success page displays

---

### Scenario 5: Fresh Install, Both Mode (Bundle Entity, Verification OFF, send_copy=ON)
**Choices:**
- Storage: `both`
- Recipients: `admin@example.com`
- Entity: Bundle's ContactMessageEntity
- Email Verification: `disabled`
- send_copy_to_sender: `true`
- Form Fields: default or custom
- Spam Protection: base only, or with captcha
- CRUD Route Prefix: `/admin/contact` (default)

**Expected Results:**
- [ ] `config/packages/contact_us.yaml` created with storage=both, email_verification.enabled=false, send_copy_to_sender=true
- [ ] After form submission:
  - [ ] Message saved as verified (verified=true)
  - [ ] Admin receives notification email
  - [ ] Sender receives CC'd copy of admin notification
  - [ ] Success page displays immediately (no verification step)

---

### Scenario 6: Upgrade: Email → Database (Bundle Entity)
**Previous Config:** storage=email
**New Choices:**
- Storage: `database` (changed from email)
- Entity: Bundle's ContactMessageEntity
- CRUD Route Prefix: `/admin/contact`

**Expected Results:**
- [ ] Old config updated to storage=database
- [ ] Old email table check offered → **double confirmation + code**
- [ ] If confirmed: old table dropped
- [ ] New CRUD routes imported
- [ ] Migration offered for bundle entity
- [ ] After migration: admin can view old contact emails if any were saved in transition

---

### Scenario 7: Upgrade: Database (Bundle) → Database (Custom Entity)
**Previous Config:** storage=database, entity=bundle
**New Choices:**
- Storage: `database` (unchanged)
- Entity: Custom entity (changed from bundle)
- CRUD Route Prefix: not asked

**Expected Results:**
- [ ] Old config updated to point to custom entity
- [ ] Bundle table check offered → **double confirmation + code**
- [ ] If confirmed: old bundle table dropped
- [ ] No CRUD routes (custom entity)
- [ ] No migration offered
- [ ] Form uses new custom entity
- [ ] Old messages in dropped table are lost (warned by double confirmation)

---

### Scenario 8: Upgrade: Both (Bundle, Verification OFF) → Both (Bundle, Verification ON)
**Previous Config:** storage=both, email_verification.enabled=false
**New Choices:**
- Storage: `both` (unchanged)
- Entity: Bundle's ContactMessageEntity (unchanged)
- Email Verification: `enabled` (changed from disabled)

**Expected Results:**
- [ ] Old config updated with email_verification.enabled=true
- [ ] No table cleanup offered (same entity)
- [ ] New submissions now require verification
- [ ] Old verified messages remain in database as-is
- [ ] Admin behavior changes for new submissions (only after sender verifies)

---

### Scenario 9: Upgrade: Both (Bundle) → Email (Drop Old Table)
**Previous Config:** storage=both, entity=bundle
**New Choices:**
- Storage: `email` (changed from both)
- Recipients: keep existing or update

**Expected Results:**
- [ ] Old config updated to storage=email
- [ ] Bundle table check offered → **double confirmation + code**
- [ ] If confirmed: bundle table dropped (including all old messages)
- [ ] No CRUD routes
- [ ] Form reverts to email-only
- [ ] Old messages in database are deleted

---

### Scenario 10: Fresh Install, Non-Interactive Mode
**Command:** `php bin/console contact:setup --no-interaction`

**Expected Results:**
- [ ] All defaults applied (both mode, bundle entity, no verification, base spam protection)
- [ ] `config/packages/contact_us.yaml` auto-created
- [ ] Routes auto-imported
- [ ] Cache cleared
- [ ] Migration offered (user can run manually)
- [ ] No prompts, no double confirmations (auto-complete)

---

## Test Execution Checklist

Use this checklist when running full regression:

- [x] Scenario 1: Email-only (fresh)
- [ ] Scenario 2: Database-only + Bundle (fresh)
- [ ] Scenario 3: Database-only + Custom (fresh)
- [ ] Scenario 4: Both + Bundle + Verification ON (fresh)
- [ ] Scenario 5: Both + Bundle + Verification OFF + send_copy (fresh)
- [ ] Scenario 6: Upgrade email → database
- [ ] Scenario 7: Upgrade database (bundle) → database (custom)
- [ ] Scenario 8: Upgrade both (no verify) → both (verify)
- [ ] Scenario 9: Upgrade both → email (with drop)
- [ ] Scenario 10: Non-interactive mode

**Additional Checks for Each:**
- [x] Config file syntax and structure
- [x] Routes properly imported
- [x] Cache cleared successfully
- [x] Form renders and works
- [x] Email sending (if applicable)
- [ ] Database operations (if applicable)
- [ ] CRUD admin (if applicable)
- [ ] Migrations generated & runnable (if applicable)
- [ ] Table cleanup with double confirmation (if applicable)
