# Configuration Reference

Complete configuration reference for ContactUsBundle.

## Table of Contents

1. [Basic Configuration](#basic-configuration)
2. [All Options](#all-options)
   - [recipients](#recipients-required)
   - [subject_prefix](#subject_prefix)
   - [storage](#storage)
   - [spam_protection](#spam_protection)
   - [crud_route_prefix](#crud_route_prefix)
   - [fields](#fields)
   - [api](#api)
   - [email_verification](#email_verification)
   - [mailer](#mailer)
3. [Environment Variables](#environment-variables)

---

## Basic Configuration

```yaml
# config/packages/contact_us.yaml
contact_us:
    recipients: ['admin@example.com']
```

## All Options

### recipients (required for email/both)
Array of email addresses that will receive contact form submissions.
Skip or leave empty when `storage: database` (no email delivery).

```yaml
contact_us:
    recipients:
        - 'admin@example.com'
        - 'support@example.com'
```

### subject_prefix
Prefix added to email subjects. Default: `[Contact Form]`

### storage
Storage mode for contact messages:
- `email` - Send email only (default)
- `database` - Save to database only (no mailer needed)
- `both` - Send email and save to database

**Note**: Database storage requires Doctrine ORM. When using the bundle entity with database/both storage, the bundle provides auto-imported admin CRUD routes (configured during setup wizard). When using your own entity, no CRUD routes are imported; you can wire your own controller or extend the bundle's abstract CRUD controller if desired.

**Cleanup when using `email` storage or switching to custom entity:** The setup wizard checks if the bundle table already exists and offers to drop it with a double confirmation (prompt + short random code) when:
- You switch to `email` storage (bundle table no longer needed)
- You switch from bundle entity to a custom entity while staying in `database` or `both` mode (migrating off the bundle)

Only the bundle-owned table is ever offered for removal; custom tables are never touched.

### crud_route_prefix
Base URL path for admin CRUD routes (only used when `storage` is `database` or `both` AND using the bundle's `ContactMessageEntity`).
Default: `/admin/contact`

**Note**: This is configured interactively during the setup wizard to avoid conflicts with existing routes (e.g., EasyAdmin routes). You can change it manually in the config if needed.

```yaml
contact_us:
    crud_route_prefix: /admin/contact-messages
```

Routes will be available at:
- `{crud_route_prefix}` - List all messages
- `{crud_route_prefix}/<id>` - View message details
- `{crud_route_prefix}/<id>/edit` - Edit message
- `{crud_route_prefix}/<id>/delete` - Delete message

### spam_protection

Base protections are always enabled by default: **Honeypot**, **Timing check**, and **Rate limiting**.
You can optionally enable a captcha provider. Email verification is configured separately and only applies when `storage: both`.

#### rate_limit
- `limit`: Maximum submissions per interval (default: 3)
- `interval`: Time interval (e.g., "15 minutes", "1 hour")

#### min_submit_time
Minimum seconds between form load and submission (default: 3)

#### captcha
- `provider`: none, turnstile, hcaptcha, friendly, recaptcha
- `site_key`: Public key for captcha provider
- `secret_key`: Secret key for captcha provider

### fields
Define custom form fields with validation. The defaults include `name`, `email`, `subject`, and `message`.

```yaml
contact_us:
    fields:
        custom_field:
            type: text  # text, email, textarea, tel, url, number, choice
            required: true
            label: 'Custom Field Label'
            options:
                attr:
                    placeholder: 'Enter value...'
            constraints:
                - NotBlank: ~
                - Length: { max: 100 }
```

**Available field types**:
- `text` - Single line text
- `email` - Email field with validation
- `textarea` - Multi-line text
- `tel` - Phone number
- `url` - URL field
- `number` - Numeric input
- `choice` - Dropdown/select

**Common constraints**:
- `NotBlank` - Required field
- `Email` - Valid email format
- `Length` - Min/max length
- `Regex` - Pattern matching
- `Url` - Valid URL

### api
Enable REST API endpoints for headless/SPA usage.

```yaml
contact_us:
    api:
        enabled: true
        route_prefix: '/api/contact'
```

### email_verification
Two-step verification via email. Only applicable when `storage: both`.

```yaml
contact_us:
    email_verification:
        enabled: true
        token_ttl: '24 hours'
```

- When enabled, the sender **always** receives a verification email (containing their message and the confirmation link).
- Admin notification is sent **only after** the sender verifies.
- The sender does **not** get a second notification after verification; they already received the verification email.

### mailer
Email sender configuration.

```yaml
contact_us:
    mailer:
        from_email: 'noreply@example.com'
        from_name: 'My Company'
```

- `send_copy_to_sender` is only honored when email verification is **disabled**. If email verification is enabled, the verification email already goes to the sender, and no extra copy is sent after verification.

## Environment Variables

You can use environment variables in configuration:

```yaml
contact_us:
    recipients: ['%env(CONTACT_EMAIL)%']
    mailer:
        from_email: '%env(CONTACT_FROM_EMAIL)%'
```

```bash
# .env
CONTACT_EMAIL=admin@example.com
CONTACT_FROM_EMAIL=noreply@example.com
```
