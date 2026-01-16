# Customization Guide

This guide explains how to customize the Contact Us Bundle to match your application's design and structure.

## Table of Contents

1. [Overriding Templates](#overriding-templates)
2. [Using Your Own Base Template](#using-your-own-base-template)
3. [Custom Styling](#custom-styling)
4. [Disabling Default Assets](#disabling-default-assets)
5. [Form Theme Customization](#form-theme-customization)
6. [Example: Complete Integration with Existing App](#example-complete-integration-with-existing-app)
7. [Available Configuration Options](#available-configuration-options)
8. [Need Help?](#need-help)

---

## Overriding Templates

Following Symfony's standard mechanism, you can override any bundle template by creating templates in your application's `templates/bundles/` directory.

### Directory Structure

```
your-project/
├─ templates/
│  └─ bundles/
│     └─ ContactUsBundle/
│        ├─ base.html.twig              # Override base layout
│        ├─ contact/
│        │  └─ form.html.twig           # Override form template
│        └─ email/
│           ├─ contact_notification.html.twig
│           └─ contact_notification.txt.twig
```

### Example: Extending Default Template

When overriding templates, you can extend from the original template using the special `@!` syntax:

```twig
{# templates/bundles/ContactUsBundle/contact/form.html.twig #}

{# DON'T DO THIS: causes infinite loop #}
{% extends '@ContactUs/contact/form.html.twig' %}

{# DO THIS: the '!' tells Symfony to extend from the original template #}
{% extends '@!ContactUs/contact/form.html.twig' %}

{% block contact_body %}
    <div class="my-custom-wrapper">
        {{ parent() }}
    </div>
{% endblock %}
```

---

## Using Your Own Base Template

If you want the contact form to use your application's existing base template instead of the standalone bundle template:

### Option 1: Configuration (Recommended)

```yaml
# config/packages/contact_us.yaml
contact_us:
  templates:
    base: 'base.html.twig'  # Your app's base template
```

Make sure your base template has the required blocks:

```twig
{# templates/base.html.twig #}
<!DOCTYPE html>
<html>
<head>
    {% block title %}{% endblock %}
    {% block stylesheets %}{% endblock %}
</head>
<body>
    {% block body %}{% endblock %}
    {% block javascripts %}{% endblock %}
</body>
</html>
```

### Option 2: Override Form Template

```twig
{# templates/bundles/ContactUsBundle/contact/form.html.twig #}
{% extends 'base.html.twig' %}

{% block title %}{{ 'contact.title'|trans }}{% endblock %}

{% block body %}
    {# Copy form content from @!ContactUs/contact/form.html.twig #}
    <div class="contact-form-container">
        {# ... form markup ... #}
    </div>
{% endblock %}
```

---

## Custom Styling

### Option 1: Disable Bundle Styles, Use Your Own

```yaml
# config/packages/contact_us.yaml
contact_us:
  design:
    load_default_styles: false  # Don't load bundle CSS
```

Then create your own styles:

```css
/* assets/styles/contact-form.css */
.contact-form-container {
    /* Your custom styles */
}

.contact-form-field {
    /* Your custom field styles */
}

/* REQUIRED: Hide honeypot field for spam protection */
.contact-honeypot {
    position: absolute;
    left: -9999px;
    width: 1px;
    height: 1px;
    overflow: hidden;
}
```

### Option 2: Load Minimal CSS + Add Your Styles

The bundle provides a minimal CSS file with only structural styles:

```twig
{# templates/bundles/ContactUsBundle/base.html.twig #}
{% block stylesheets %}
    <link rel="stylesheet" href="{{ asset('bundles/contactus/styles/contact-minimal.css') }}">
    <link rel="stylesheet" href="{{ asset('styles/my-contact-form.css') }}">
{% endblock %}
```

### Option 3: Use Bootstrap/Tailwind Classes

Override the form template and add your framework classes:

```twig
{# templates/bundles/ContactUsBundle/contact/form.html.twig #}
{% extends '@!ContactUs/contact/form.html.twig' %}

{# Override to add Bootstrap classes #}
{% block contact_body %}
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            {# Form content with Bootstrap classes #}
            {{ form_start(form, {'attr': {'class': 'needs-validation'}}) }}
                {% for field in form %}
                    {% if field.vars.name not in ['_token', 'email_confirm', '_form_token_time'] %}
                        <div class="mb-3">
                            {{ form_label(field, null, {'label_attr': {'class': 'form-label'}}) }}
                            {{ form_widget(field, {'attr': {'class': 'form-control'}}) }}
                            {{ form_errors(field, {'attr': {'class': 'invalid-feedback d-block'}}) }}
                        </div>
                    {% endif %}
                {% endfor %}
                
                <button type="submit" class="btn btn-primary">
                    {{ 'contact.submit'|trans }}
                </button>
                
                {# Hidden fields #}
                <div class="contact-honeypot" aria-hidden="true">
                    {{ form_row(form.email_confirm) }}
                </div>
                {{ form_widget(form._form_token_time) }}
                {{ form_row(form._token) }}
            {{ form_end(form, {'render_rest': false}) }}
        </div>
    </div>
</div>
{% endblock %}
```

---

## Disabling Default Assets

If you're handling Stimulus controllers yourself or don't need the bundle's JavaScript:

```yaml
# config/packages/contact_us.yaml
contact_us:
  design:
    load_default_styles: false      # No CSS from bundle
    load_stimulus_controller: false  # No JS from bundle
```

Then implement your own form enhancements:

```javascript
// assets/controllers/contact_controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        // Your custom form handling
        const timingField = this.element.querySelector('.contact-timing-token');
        if (timingField) {
            timingField.value = Math.floor(Date.now() / 1000).toString();
        }
    }
}
```

---

## Form Theme Customization

Symfony allows you to customize form rendering globally or per-form.

### Option 1: Global Form Theme

```yaml
# config/packages/twig.yaml
twig:
    form_themes:
        - 'bootstrap_5_layout.html.twig'  # Use Bootstrap 5
        # - 'tailwind_2_layout.html.twig'  # Or Tailwind
```

### Option 2: Per-Bundle Form Theme

```yaml
# config/packages/contact_us.yaml
contact_us:
  design:
    form_theme: 'bootstrap_5_layout.html.twig'
```

### Option 3: Custom Form Theme

Create a custom form theme:

```twig
{# templates/form/contact_theme.html.twig #}
{% use 'bootstrap_5_layout.html.twig' %}

{% block form_row %}
    <div class="custom-form-group">
        {{ form_label(form) }}
        {{ form_widget(form) }}
        {{ form_errors(form) }}
    </div>
{% endblock %}
```

Then configure it:

```yaml
contact_us:
  design:
    form_theme: 'form/contact_theme.html.twig'
```

---

## Example: Complete Integration with Existing App

### Scenario: Integrate into app with Bootstrap 5 + custom design

**Step 1: Configure to use app base template**

```yaml
# config/packages/contact_us.yaml
contact_us:
  templates:
    base: 'base.html.twig'  # Your app's base
  design:
    load_default_styles: false  # Use your app styles
    load_stimulus_controller: true  # Keep bundle JS functionality
    form_theme: 'bootstrap_5_layout.html.twig'
```

**Step 2: Override form template with Bootstrap classes**

```twig
{# templates/bundles/ContactUsBundle/contact/form.html.twig #}
{% extends 'base.html.twig' %}

{% block title %}{{ 'contact.title'|trans }}{% endblock %}

{% block body %}
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <h1 class="mb-4">{{ 'contact.title'|trans }}</h1>
            
            {% for message in app.flashes('contact_success') %}
                <div class="alert alert-success">{{ message|trans }}</div>
            {% endfor %}
            
            {% for message in app.flashes('contact_error') %}
                <div class="alert alert-danger">{{ message }}</div>
            {% endfor %}
            
            {{ form_start(form, {
                'attr': {
                    'class': 'contact-form',
                    'data-controller': 'contact'
                }
            }) }}
                {% for field in form if field.vars.name not in ['_token', 'email_confirm', '_form_token_time'] %}
                    <div class="mb-3">
                        {{ form_label(field, null, {'label_attr': {'class': 'form-label'}}) }}
                        {{ form_widget(field, {'attr': {'class': 'form-control'}}) }}
                        {{ form_errors(field) }}
                    </div>
                {% endfor %}
                
                <button type="submit" class="btn btn-primary" data-contact-target="submit">
                    {{ 'contact.submit'|trans }}
                </button>
                
                {# Required hidden fields #}
                <div class="contact-honeypot" aria-hidden="true">
                    {{ form_row(form.email_confirm) }}
                </div>
                {{ form_widget(form._form_token_time, {'attr': {'class': 'contact-timing-token'}}) }}
                {{ form_row(form._token) }}
            {{ form_end(form, {'render_rest': false}) }}
        </div>
    </div>
</div>
{% endblock %}
```

**Step 3: Add minimal required CSS to your app stylesheet**

```css
/* assets/styles/app.css */

/* REQUIRED: Hide honeypot for spam protection */
.contact-honeypot {
    position: absolute;
    left: -9999px;
    width: 1px;
    height: 1px;
    overflow: hidden;
}

/* Optional: Your custom contact form styles */
.contact-form {
    /* Custom styles here */
}
```

---

## Available Configuration Options

Complete reference of template/design configuration:

```yaml
contact_us:
  templates:
    base: null                          # null = standalone, or 'base.html.twig'
    form: '@ContactUs/contact/form.html.twig'
    email_html: '@ContactUs/email/contact_notification.html.twig'
    email_text: '@ContactUs/email/contact_notification.txt.twig'
  
  design:
    load_default_styles: true           # Load bundle CSS
    load_stimulus_controller: true      # Load bundle JS
    form_theme: null                    # Symfony form theme
```

---

## Need Help?

- Check the [README.md](../README.md) for basic setup
- See [INSTALLATION.md](INSTALLATION.md) for installation instructions
- View [CONFIGURATION.md](CONFIGURATION.md) for all config options
- Read [TRANSLATION.md](TRANSLATION.md) for translation setup
