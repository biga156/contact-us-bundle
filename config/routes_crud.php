<?php

declare(strict_types=1);

use Caeligo\ContactUsBundle\Controller\Admin\DefaultContactCrudController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    // Routes are relative; prefix is applied at import time in config/routes.yaml
    $routes->add('contact_us_admin_index', '/')
        ->controller([DefaultContactCrudController::class, 'index'])
        ->methods(['GET']);

    $routes->add('contact_us_admin_show', '/{id}')
        ->controller([DefaultContactCrudController::class, 'show'])
        ->requirements(['id' => '\\d+'])
        ->methods(['GET']);

    $routes->add('contact_us_admin_edit', '/{id}/edit')
        ->controller([DefaultContactCrudController::class, 'edit'])
        ->requirements(['id' => '\\d+'])
        ->methods(['GET', 'POST']);

    $routes->add('contact_us_admin_delete', '/{id}/delete')
        ->controller([DefaultContactCrudController::class, 'delete'])
        ->requirements(['id' => '\\d+'])
        ->methods(['POST']);
};
