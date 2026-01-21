<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Controller\Admin;

use Caeligo\ContactUsBundle\Service\Crud\CrudManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Default CRUD controller wired by the bundle when using the bundle entity.
 * Can be replaced by defining your own controller that extends AbstractContactCrudController.
 */
class DefaultContactCrudController extends AbstractContactCrudController
{
    public function __construct(CrudManagerInterface $manager, EventDispatcherInterface $dispatcher)
    {
        parent::__construct($manager, $dispatcher);
    }
}
