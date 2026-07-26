<?php

declare(strict_types=1);

use LauLamanApps\Tokenable\Command\ConvertTokenCommand;
use LauLamanApps\Tokenable\Command\GenerateTokenableCommand;
use LauLamanApps\Tokenable\Recorder\TokenableRecorder;
use LauLamanApps\Tokenable\Resolver\TokenableValueResolver;
use LauLamanApps\Tokenable\Routing\TokenableUrlGenerator;
use LauLamanApps\Tokenable\Tokenizer;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure(false);

    $services->set(Tokenizer::class)
        ->arg('$separator', param('tokenable.separator'))
        ->arg('$base', param('tokenable.base'));

    $services->set(TokenableRecorder::class)
        ->tag('kernel.reset', ['method' => 'reset']);

    // Runs before Doctrine's EntityValueResolver (priority 110) so a token wins over a raw id lookup.
    $services->set(TokenableValueResolver::class)
        ->tag('controller.argument_value_resolver', ['priority' => 200]);

    // Decorates the router so path()/generateUrl() transparently encode ids and entities into tokens.
    $services->set(TokenableUrlGenerator::class)
        ->decorate('router')
        ->args([
            service('.inner'),
            service(Tokenizer::class),
            '%kernel.cache_dir%',
        ]);

    $services->set(GenerateTokenableCommand::class)
        ->tag('console.command');

    $services->set(ConvertTokenCommand::class)
        ->tag('console.command');
};
