<?php

declare(strict_types=1);

use LauLamanApps\Tokenable\Twig\TokenExtension;

return static function (Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator $container): void {
    $container->services()
        ->set(TokenExtension::class)
        ->autowire()
        ->tag('twig.extension');
};
