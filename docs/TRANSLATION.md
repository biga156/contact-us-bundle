# Translation Guide

This guide explains how the ContactUsBundle handles translations and how to customize them.

## Table of Contents

1. [Overview](#overview)
2. [Default Behavior (Auto-Detect)](#default-behavior-auto-detect)
3. [Installation](#installation)
4. [Default Translations](#default-translations)
5. [Customizing Translations](#customizing-translations)
6. [Adding New Languages](#adding-new-languages)
7. [Configuration Options](#configuration-options)
8. [Using Custom Translation Filter](#using-custom-translation-filter)
9. [Translation Keys Reference](#translation-keys-reference)
10. [Examples](#examples)
11. [Best Practices](#best-practices)
12. [Troubleshooting](#troubleshooting)
13. [Migration from Other Bundles](#migration-from-other-bundles)
14. [See Also](#see-also)

---

## Overview

The bundle supports **optional translations** with automatic fallback:

- ✅ **Symfony Translation component detected** → Uses translation keys
- ❌ **No translation component** → Falls back to plain text labels
- 🔧 **Configurable** → Force enable/disable via config

When translations are disabled or missing, the Twig `contact_trans` filter renders readable English labels by default. If you prefer another language without enabling Symfony Translation, adjust the provided `translations/contact_us.en.yaml` (or add your own file) or override labels directly in `contact_us.fields`.

## Default Behavior (Auto-Detect)

The bundle automatically detects if `symfony/translation` is available:

```yaml
# config/packages/contact_us.yaml
contact_us:
  translation:
    enabled: auto  # Default - auto-detect translator
    domain: contact_us  # Translation domain
    fallback_locale: en
```

**With translator installed:**
```twig
{{ 'contact.title'|trans }}  → "Contact Us" (from translations)
```

**Without translator:**
```twig
{{ 'contact.title'|trans }}  → "Title" (automatic plain text extraction)
{{ 'contact.field.name'|trans }}  → "Name"
```

## Installation

### Option 1: With Translation Support (Recommended)

```bash
composer require symfony/translation
```

Then add your translations:

```
your-project/
└─ translations/
   ├─ contact_us.en.yaml
   ├─ contact_us.fr.yaml
   └─ contact_us.de.yaml
```

### Option 2: Without Translation (Minimalist)

The bundle works out of the box without `symfony/translation`. Translation keys will be converted to plain text automatically.

No additional configuration needed.

## Default Translations

The bundle provides default translations in English (`en`) and Hungarian (`hu`):

**English** (`translations/contact_us.en.yaml`):
```yaml
contact:
  title: 'Contact Us'
  submit: 'Send Message'
  field:
    name: 'Name'
    email: 'Email Address'
    message: 'Message'
  message:
    success: 'Your message has been sent successfully.'
    error: 'An error occurred while sending your message.'
```

**Hungarian** (`translations/contact_us.hu.yaml`):
```yaml
contact:
  title: 'Kapcsolat'
  submit: 'Üzenet küldése'
  field:
    name: 'Név'
    email: 'E-mail cím'
    message: 'Üzenet'
```

## Customizing Translations

### Method 1: Override in Your Application

Create your own translation file to override bundle translations:

```yaml
# translations/contact_us.en.yaml
contact:
  title: 'Get in Touch'  # Override bundle default
  submit: 'Submit Form'
  field:
    name: 'Full Name'    # Override
    company: 'Company'   # Add new field
```

### Method 2: Use Different Translation Domain

```yaml
# config/packages/contact_us.yaml
contact_us:
  translation:
    domain: 'messages'  # Use default Symfony domain
```

Then put translations in `translations/messages.en.yaml`.

### Method 3: Custom Field Labels in Config

Override labels directly in configuration (works with or without translation):

```yaml
contact_us:
  fields:
    name:
      label: 'Your Full Name'  # Plain text or translation key
    email:
      label: 'app.contact.email_label'  # Custom translation key
```

## Adding New Languages

Create translation files for your language:

```yaml
# translations/contact_us.fr.yaml
contact:
  title: 'Contactez-nous'
  submit: 'Envoyer le message'
  field:
    name: 'Nom'
    email: 'Adresse e-mail'
    message: 'Message'
  message:
    success: 'Votre message a été envoyé avec succès.'
```

```yaml
# translations/contact_us.de.yaml
contact:
  title: 'Kontakt'
  submit: 'Nachricht senden'
  field:
    name: 'Name'
    email: 'E-Mail-Adresse'
    message: 'Nachricht'
```

## Configuration Options

### Auto-Detect (Default)

```yaml
contact_us:
  translation:
    enabled: auto  # Detect if translator available
```

### Force Enable

```yaml
contact_us:
  translation:
    enabled: 'true'  # Always use translation (requires symfony/translation)
```

If translator not available, will throw exception.

### Force Disable

```yaml
contact_us:
  translation:
    enabled: 'false'  # Never use translation, always plain text
```

Useful if you have translator installed but don't want to use it for contact form.

### Custom Translation Domain

```yaml
contact_us:
  translation:
    domain: 'my_app'  # Use custom domain
```

Your translations:
```yaml
# translations/my_app.en.yaml
contact:
  title: 'Custom Contact Page'
```

### Fallback Locale

```yaml
contact_us:
  translation:
    fallback_locale: en  # If translation missing, try English
```

## Using Custom Translation Filter

The bundle provides a custom Twig filter with automatic fallback:

```twig
{# In your custom templates #}
{{ 'contact.title'|contact_trans }}
{{ 'contact.field.name'|contact_trans({'%name%': user.name}) }}
```

**With translator:**
Uses standard Symfony translation.

**Without translator:**
Extracts plain text from key:
- `contact.title` → "Title"
- `contact.field.name` → "Name"
- `my_custom_label` → "My Custom Label"

## Translation Keys Reference

### Form Labels

```yaml
contact.field.name
contact.field.email
contact.field.phone
contact.field.subject
contact.field.message
```

### Buttons

```yaml
contact.submit
contact.submit_loading
contact.verification.button
```

### Messages

```yaml
contact.message.success
contact.message.error
contact.message.spam_detected
contact.message.rate_limit_exceeded
contact.message.validation_failed
```

### Headers

```yaml
contact.title
contact.description
contact.privacy_note
```

### Email Verification (Level 2)

```yaml
contact.verification.title
contact.verification.message
contact.verification.expired
contact.verification.success
```

## Examples

### Example 1: Using Bundle with Translation

```yaml
# composer.json
{
  "require": {
    "symfony/translation": "^6.4"
  }
}

# config/packages/contact_us.yaml
contact_us:
  translation:
    enabled: auto  # Default

# translations/contact_us.en.yaml
contact:
  title: 'Contact Our Team'
  submit: 'Send Inquiry'
```

### Example 2: Without Translation

```yaml
# No symfony/translation installed

# config/packages/contact_us.yaml
contact_us:
  fields:
    name:
      label: 'Full Name'  # Plain text label
    email:
      label: 'Email Address'
```

Template will display plain text labels directly.

### Example 3: Mixed Approach

```yaml
# Translation enabled but custom labels for some fields

# config/packages/contact_us.yaml
contact_us:
  translation:
    enabled: auto
  fields:
    name:
      label: 'contact.field.name'  # Uses translation
    custom_field:
      label: 'Your Custom Question'  # Plain text, no translation key
```

## Best Practices

1. **Use translation keys in config** for consistency:
   ```yaml
   fields:
     name:
       label: 'contact.field.name'  # Good
   ```

2. **Provide fallback locales**:
   ```yaml
   contact_us:
     translation:
       fallback_locale: en
   ```

3. **Override in your app, not in vendor**:
   ```
   ✅ translations/contact_us.en.yaml
   ❌ vendor/caeligo/contact-us-bundle/translations/...
   ```

4. **Keep translation domain consistent**:
   ```yaml
   translation:
     domain: contact_us  # Use bundle's domain
   ```

5. **Test without translator** to ensure fallback works:
   ```yaml
   contact_us:
     translation:
       enabled: 'false'  # Test plain text mode
   ```

## Troubleshooting

### Translation keys showing instead of text

**Problem:** Seeing `contact.title` instead of "Contact Us"

**Solutions:**
1. Install symfony/translation: `composer require symfony/translation`
2. Clear cache: `php bin/console cache:clear`
3. Check translation file exists: `translations/contact_us.en.yaml`
4. Verify locale: `app.request.locale` matches translation file

### Fallback not working

**Problem:** Seeing raw keys even with `enabled: 'false'`

**Solution:**
Clear cache and check Twig extension is registered:
```bash
php bin/console debug:container ContactUsExtension
```

### Custom domain not working

**Problem:** Translations not loading from custom domain

**Solution:**
Ensure translation files match the domain:
```yaml
# config: domain: 'app'
# file: translations/app.en.yaml  (not contact_us.en.yaml)
```

## Migration from Other Bundles

If migrating from another contact bundle with different translation keys:

1. Keep old keys in your `translations/contact_us.en.yaml`
2. Map to bundle keys in config:
   ```yaml
   fields:
     name:
       label: 'old_bundle.form.name'  # Your existing key
   ```

## See Also

- [Symfony Translation Documentation](https://symfony.com/doc/current/translation.html)
- [Configuration Reference](CONFIGURATION.md)
- [Customization Guide](CUSTOMIZATION.md)
