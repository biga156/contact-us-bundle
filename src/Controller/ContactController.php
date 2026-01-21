<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Controller;

use Caeligo\ContactUsBundle\Form\ContactFormType;
use Caeligo\ContactUsBundle\Service\ContactSubmissionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ContactController extends AbstractController
{
    /**
     * @param array<string, mixed> $fieldsConfig
     * @phpstan-ignore property.onlyWritten
     */
    public function __construct(
        private ContactSubmissionService $submissionService,
        private array $fieldsConfig,
        private \Symfony\Component\Form\FormFactoryInterface $formFactory,
        private \Symfony\Component\Routing\Generator\UrlGeneratorInterface $urlGenerator
    ) {
    }

    #[Route('/contact', name: 'contact_us_form', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $form = $this->formFactory->create(ContactFormType::class, null, [
            'action' => $this->urlGenerator->generate('contact_us_form'),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $message = $this->submissionService->process($form, $request);

                // Different flash message depending on whether email verification is required
                if ($this->submissionService->isEmailVerificationEnabled()) {
                    $this->addFlash('contact_success', 'contact.message.verification_sent');
                } else {
                    $this->addFlash('contact_success', 'contact.message.success');
                }

                return $this->redirectToRoute('contact_us_form');
            } catch (\RuntimeException $e) {
                $this->addFlash('contact_error', $e->getMessage());
            }
        }

        return $this->render('@ContactUs/contact/form.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/contact/verify/{token}', name: 'contact_us_verify', methods: ['GET'])]
    public function verify(string $token): Response
    {
        try {
            $message = $this->submissionService->verifyMessage($token);
            
            return $this->render('@ContactUs/contact/verified.html.twig', [
                'message' => $message,
                'success' => true,
            ]);
        } catch (\RuntimeException $e) {
            return $this->render('@ContactUs/contact/verified.html.twig', [
                'message' => null,
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
