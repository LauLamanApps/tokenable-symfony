<?php

declare(strict_types=1);

namespace LauLamanApps\Tokenable;

use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Twig\Extension\AbstractExtension;

final class TokenableBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('separator')
                    ->info('String placed between the prefix and the obfuscated id, e.g. the "_" in "per_3f9k2".')
                    ->defaultValue('_')
                    ->cannotBeEmpty()
                ->end()
                ->integerNode('base')
                    ->info('Numeric base used to encode the obfuscated id (2-36). 36 gives the shortest tokens.')
                    ->defaultValue(36)
                    ->min(2)
                    ->max(36)
                ->end()
            ->end();
    }

    /** @param array<string, mixed> $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->parameters()
            ->set('tokenable.separator', $config['separator'])
            ->set('tokenable.base', $config['base']);

        $container->import('../config/services.php');

        // NB: $builder->hasExtension() is unreliable here — loadExtension() runs against a
        // per-extension temporary container that has no other bundle's extension registered.
        // Gate on class availability instead (deterministic, autoload-based).

        // The |token Twig filter is only registered when Twig is installed.
        if (class_exists(AbstractExtension::class)) {
            $container->import('../config/twig.php');
        }

        // The profiler panel is only registered when the web profiler is available (dev/test).
        if (class_exists(WebProfilerBundle::class)) {
            $container->import('../config/profiler.php');
        }
    }

    /**
     * The bundle class lives in src/, but templates/ and config/ sit at the package
     * root — point Symfony (and its Twig `@Tokenable` namespace) one level up.
     */
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
