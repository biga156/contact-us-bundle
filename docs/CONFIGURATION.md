# Configuration Reference

Complete configuration reference for ContactUsBundle.

## Basic Configuration

```yaml
# config/packages/contact_us.yaml
contact_us:
    recipients: ['admin@example.com']
```

## All Options

### recipients (required)
Array of email addresses that will receive contact form submissions.

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
- `database` - Save to database only
- `both` - Send email and save to database

**Note**: Database storage requires Doctrine ORM to be installed and configured.

### spam_protection

#### level
Protection level (1-3):
1. Honeypot + Rate limiting + Timing check (default)
2. Level 1 + Email verification
3. Level 2 + Third-party captcha

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
Define custom form fields with validation.

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
Two-step verification via email (Level 2 protection).

```yaml
contact_us:
    email_verification:
        enabled: true
        token_ttl: '1 hour'
```

### mailer
Email sender configuration.

```yaml
contact_us:
    mailer:
        from_email: 'noreply@example.com'
        from_name: 'My Company'
```

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
