<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Controller\Admin;

use Caeligo\ContactUsBundle\Event\CrudDeleteEvent;
use Caeligo\ContactUsBundle\Event\CrudListEvent;
use Caeligo\ContactUsBundle\Event\CrudShowEvent;
use Caeligo\ContactUsBundle\Model\ContactMessage;
use Caeligo\ContactUsBundle\Service\Crud\CrudManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base CRUD controller for contact messages.
 * Developers can extend this controller in their app to override behavior or templates.
 */
abstract class AbstractContactCrudController extends AbstractController
{
    public function __construct(
        protected CrudManagerInterface $manager,
        protected EventDispatcherInterface $dispatcher
    ) {
    }

    public function index(Request $request): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, (int) $request->query->get('limit', 20));

        $messages = $this->manager->list($page, $limit);
        $total = $this->manager->count();

        $event = new CrudListEvent($messages, $page, $limit, $total);
        $this->dispatcher->dispatch($event, CrudListEvent::NAME);

        return $this->render('@ContactUs/admin/contact/index.html.twig', [
            'messages' => $event->getMessages(),
            'page' => $event->getPage(),
            'limit' => $event->getLimit(),
            'total' => $event->getTotal(),
        ]);
    }

    public function show(int $id): Response
    {
        $message = $this->manager->find($id);

        $event = new CrudShowEvent($message, $id);
        $this->dispatcher->dispatch($event, CrudShowEvent::NAME);

        if ($event->getMessage() === null) {
            throw $this->createNotFoundException('Message not found');
        }

        return $this->render('@ContactUs/admin/contact/show.html.twig', [
            'message' => $event->getMessage(),
        ]);
    }

    public function delete(Request $request, int $id): RedirectResponse
    {
        $message = $this->manager->find($id);

        $event = new CrudDeleteEvent($message, $id);
        $this->dispatcher->dispatch($event, CrudDeleteEvent::NAME);

        if ($event->getMessage() !== null) {
            $this->manager->delete($event->getMessage());
            $this->addFlash('contact_success', 'contact.admin.deleted');
        } else {
            $this->addFlash('contact_error', 'contact.admin.not_found');
        }

        return $this->redirectToRoute('contact_us_admin_index');
    }
}
