<?php

declare(strict_types=1);

use LauLamanApps\Tokenable\DataCollector\TokenCollector;

return static function (Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator $container): void {
    $container->services()
        ->set(TokenCollector::class)
        ->autowire()
        ->tag('data_collector', [
            'template' => '@Tokenable/data_collector/token.html.twig',
            'id' => TokenCollector::class,
            'priority' => 255,
        ]);
};
