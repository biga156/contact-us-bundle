<?php

declare(strict_types=1);

use Caeligo\ContactUsBundle\Controller\ContactController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->add('contact_us_form', '/contact')
        ->controller([ContactController::class, 'index'])
        ->methods(['GET', 'POST']);

    $routes->add('contact_us_verify', '/contact/verify/{token}')
        ->controller([ContactController::class, 'verify'])
        ->methods(['GET'])
        ->requirements(['token' => '[a-f0-9]{64}']);
};
