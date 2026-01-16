<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Controller;

use Caeligo\ContactUsBundle\Form\ContactFormType;
use Caeligo\ContactUsBundle\Service\ContactSubmissionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ContactController extends AbstractController
{
    public function __construct(
        private ContactSubmissionService $submissionService,
        private array $fieldsConfig
    ) {
    }

    #[Route('/contact', name: 'contact_us_form', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $form = $this->createForm(ContactFormType::class, null, [
            'action' => $this->generateUrl('contact_us_form'),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->submissionService->process($form, $request);

                $this->addFlash('contact_success', 'contact.message.success');

                return $this->redirectToRoute('contact_us_form');
            } catch (\RuntimeException $e) {
                $this->addFlash('contact_error', $e->getMessage());
            }
        }

        return $this->render('@ContactUs/contact/form.html.twig', [
            'form' => $form,
        ]);
    }
}
