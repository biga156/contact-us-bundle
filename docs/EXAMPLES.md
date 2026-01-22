# Examples

Practical snippets for common ContactUsBundle customizations. Copy/paste into your app and adjust names/paths.

## Table of Contents

1. [Minimal Bootstrap Override](#1-minimal-bootstrap-override)
2. [Custom Form Fields via Config](#2-custom-form-fields-via-config)
3. [Embed the Form in an Existing Page](#3-embed-the-form-in-an-existing-page)
4. [Use Your Own Base Layout](#4-use-your-own-base-layout)
5. [Admin CRUD with a Custom Entity](#5-admin-crud-with-a-custom-entity)
6. [Submission Flow: Email Verification](#6-submission-flow-email-verification)
7. [Override Form Submission with Custom Logic](#7-override-form-submission-with-custom-logic)

---

## 1. Minimal Bootstrap Override

Override only the markup/classes while keeping bundle logic.

```twig
{# templates/bundles/ContactUsBundle/contact/form.html.twig #}
{% extends '@!ContactUs/contact/form.html.twig' %}

{% block contact_body %}
<div class="container py-5">
  <h1 class="mb-4">{{ 'contact.title'|trans }}</h1>
  {% for msg in app.flashes('contact_success') %}
    <div class="alert alert-success">{{ msg|trans }}</div>
  {% endfor %}
  {% for msg in app.flashes('contact_error') %}
    <div class="alert alert-danger">{{ msg }}</div>
  {% endfor %}

  {{ form_start(form, {'attr': {'class': 'needs-validation', 'data-controller': 'contact'}}) }}
    {% for field in form if field.vars.name not in ['_token','email_confirm','_form_token_time'] %}
      <div class="mb-3">
        {{ form_label(field, null, {'label_attr': {'class': 'form-label'}}) }}
        {{ form_widget(field, {'attr': {'class': 'form-control'}}) }}
        {{ form_errors(field) }}
      </div>
    {% endfor %}

    <button class="btn btn-primary">{{ 'contact.submit'|trans }}</button>

    <div class="contact-honeypot" aria-hidden="true">{{ form_row(form.email_confirm) }}</div>
    {{ form_widget(form._form_token_time, {'attr': {'class': 'contact-timing-token'}}) }}
    {{ form_row(form._token) }}
  {{ form_end(form, {'render_rest': false}) }}
</div>
{% endblock %}
```

---

## 2. Custom Form Fields via Config

Add/remove/retitle fields without touching templates.

```yaml
# config/packages/contact_us.yaml
contact_us:
  storage: email
  recipients: ['admin@example.com']
  fields:
    name:
      label: 'Your name'
    email:
      type: email
      required: true
    subject:
      required: false
      label: 'Topic'
    message:
      type: textarea
      constraints:
        - Length: { min: 10, max: 2000 }
    company:
      type: text
      required: false
      label: 'Company (optional)'
  design:
    load_default_styles: false
    form_theme: 'bootstrap_5_layout.html.twig'
```

---

## 3. Embed the Form in an Existing Page

Render the bundle route inside any template (keeps spam protections and flashes intact).

```twig
{# templates/page/contact_section.html.twig #}
<section id="contact">
  <h2>Contact us</h2>
  {{ render(path('contact_us_form')) }}
</section>
```

If using Symfony UX LiveComponent, you can instead drop in:

```twig
{{ component('ContactUs') }}
```

---

## 4. Use Your Own Base Layout

Point the bundle to your layout and keep the default form template.

```yaml
# config/packages/contact_us.yaml
contact_us:
  templates:
    base: 'base.html.twig'
  design:
    load_default_styles: false
```

Ensure your base defines the standard blocks:

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

---

## 5. Admin CRUD with a Custom Entity

Wire your own entity by providing a custom CRUD manager and reusing the bundle controller.

```php
// src/Controller/Admin/MyContactCrudController.php
namespace App\Controller\Admin;

use Caeligo\ContactUsBundle\Controller\Admin\AbstractContactCrudController;

class MyContactCrudController extends AbstractContactCrudController
{
    // Override methods only if you need custom behavior/templates.
}
```

Register your manager as the service for `Caeligo\ContactUsBundle\Service\Crud\CrudManagerInterface` (simplified example):

```php
// src/Service/Contact/CustomCrudManager.php
namespace App\Service\Contact;

use App\Entity\ContactSubmission;
use Caeligo\ContactUsBundle\Model\ContactMessage;
use Caeligo\ContactUsBundle\Service\Crud\CrudManagerInterface;
use Doctrine\ORM\EntityManagerInterface;

final class CustomCrudManager implements CrudManagerInterface
{
    public function __construct(private EntityManagerInterface $em) {}

    public function list(int $page, int $limit): array
    {
        $repo = $this->em->getRepository(ContactSubmission::class);
        return $repo->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function count(): int
    {
        return (int) $this->em->getRepository(ContactSubmission::class)
            ->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function find(int $id): ?object
    {
        return $this->em->find(ContactSubmission::class, $id);
    }

    public function delete(object $message): void
    {
        $this->em->remove($message);
        $this->em->flush();
    }
}
```

In `services.yaml`:

```yaml
Caeligo\ContactUsBundle\Service\Crud\CrudManagerInterface: '@App\Service\Contact\CustomCrudManager'
```

Define a route to your controller (skip auto-imported CRUD when using a custom entity):

```yaml
# config/routes.yaml
contact_admin:
  path: /admin/contact
  controller: App\Controller\Admin\MyContactCrudController::index
```

Add `show` and `delete` routes as needed.

---

## 6. Submission Flow: Email Verification

Enable verification when using `storage: both` so admin mail waits until the sender confirms.

```yaml
# config/packages/contact_us.yaml
contact_us:
  storage: both
  recipients: ['admin@example.com']
  email_verification:
    enabled: true
    token_ttl: '24 hours'
  mailer:
    from_email: 'noreply@example.com'
    from_name: 'My Site'
```

User flow:
1. Form POST saves an unverified message and emails the sender a verification link.
2. Sender clicks `/contact/verify/{token}`.
3. Message is marked verified; admin notification is then sent; sender does not get a second copy.

---

## 7. Override Form Submission with Custom Logic

Create your own controller to add custom processing before/after submission (logging, CRM integration, custom redirects, etc.).

```php
// src/Controller/CustomContactController.php
namespace App\Controller;

use Caeligo\ContactUsBundle\Form\ContactFormType;
use Caeligo\ContactUsBundle\Service\ContactSubmissionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Psr\Log\LoggerInterface;

class CustomContactController extends AbstractController
{
    public function __construct(
        private ContactSubmissionService $submissionService,
        private \Symfony\Component\Form\FormFactoryInterface $formFactory,
        private \Symfony\Component\Routing\Generator\UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger
    ) {
    }

    #[Route('/custom-contact', name: 'custom_contact_form', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $form = $this->formFactory->create(ContactFormType::class, null, [
            'action' => $this->urlGenerator->generate('custom_contact_form'),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // CUSTOM LOGIC BEFORE SUBMISSION
                // Example: Log IP, add to analytics, check against internal blacklist
                $this->logger->info('Contact form submitted', [
                    'ip' => $request->getClientIp(),
                    'email' => $form->get('email')->getData(),
                ]);

                // Process via bundle service (handles email/database/verification)
                $message = $this->submissionService->process($form, $request);

                // CUSTOM LOGIC AFTER SUBMISSION
                // Example: Notify via Slack, add to CRM, trigger webhook
                // $this->slackNotifier->send("New contact: {$message->getEmail()}");
                // $this->crmService->createLead($message);

                // Custom success message and redirect
                $this->addFlash('contact_success', 'Thank you! We will respond within 24 hours.');
                return $this->redirectToRoute('custom_contact_success');

            } catch (\RuntimeException $e) {
                // Custom error handling
                $this->logger->error('Contact form error', [
                    'message' => $e->getMessage(),
                    'ip' => $request->getClientIp(),
                ]);

                $this->addFlash('contact_error', 'Sorry, there was an error. Please try again later.');
            }
        }

        // Use bundle template or your own
        return $this->render('@ContactUs/contact/form.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/custom-contact/success', name: 'custom_contact_success')]
    public function success(): Response
    {
        return $this->render('contact/success.html.twig');
    }
}
```

**Route configuration:**

```yaml
# config/routes.yaml
# Disable bundle's default route if you want only your custom route
# contact_us:
#     resource: '@ContactUsBundle/config/routes.php'
#     type: php

# Or keep both and use different paths
```

**Custom success template:**

```twig
{# templates/contact/success.html.twig #}
{% extends 'base.html.twig' %}

{% block title %}Contact Success{% endblock %}

{% block body %}
<div class="container py-5">
    <div class="alert alert-success">
        <h2>Thank you for contacting us!</h2>
        <p>Your message has been received. We will respond within 24 hours.</p>
    </div>
    <a href="{{ path('home') }}" class="btn btn-primary">Back to Home</a>
</div>
{% endblock %}
```
