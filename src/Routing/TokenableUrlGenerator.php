<?php

declare(strict_types=1);

namespace LauLamanApps\Tokenable\Routing;

use LauLamanApps\Tokenable\Tokenizer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

final class TokenableUrlGenerator implements RouterInterface, RequestMatcherInterface, WarmableInterface
{
    private const CACHE_FILE = '/tokenable_routes.php';

    /** @var array<string, array<string, class-string>>|null */
    private ?array $mappings = null;

    public function __construct(
        private readonly RouterInterface $inner,
        private readonly Tokenizer $tokenizer,
        private readonly string $cacheDir,
    ) {
    }

    /** @param array<string, mixed> $parameters */
    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        // Encode every mapped id/entity parameter into its token. Only mapped
        // parameters are auto-encoded here; a string value is already a token.
        foreach ($this->getMappings()[$name] ?? [] as $param => $class) {
            if (!isset($parameters[$param])) {
                continue;
            }

            $value = $parameters[$param];
            if (is_int($value)) {
                $parameters[$param] = $this->tokenizer->encode($class, $value);
            } elseif (is_object($value) && is_a($value, $class)) {
                $parameters[$param] = $this->tokenizer->encode($value);
            }
        }

        return $this->inner->generate($name, $parameters, $referenceType);
    }

    /** @return array<string, array<string, class-string>> */
    public function getRouteMappings(): array
    {
        return $this->getMappings();
    }

    /** @return array<string, mixed> */
    public function match(string $pathinfo): array
    {
        /** @var array<string, mixed> */
        return $this->inner->match($pathinfo);
    }

    /** @return array<string, mixed> */
    public function matchRequest(Request $request): array
    {
        if ($this->inner instanceof RequestMatcherInterface) {
            /** @var array<string, mixed> */
            return $this->inner->matchRequest($request);
        }

        /** @var array<string, mixed> */
        return $this->inner->match($request->getPathInfo());
    }

    public function getRouteCollection(): RouteCollection
    {
        return $this->inner->getRouteCollection();
    }

    public function setContext(RequestContext $context): void
    {
        $this->inner->setContext($context);
    }

    public function getContext(): RequestContext
    {
        return $this->inner->getContext();
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        $innerFiles = $this->inner instanceof WarmableInterface
            ? $this->inner->warmUp($cacheDir, $buildDir)
            : [];

        $cacheFile = ($buildDir ?? $cacheDir).self::CACHE_FILE;
        $mappings = $this->buildMappings();
        file_put_contents($cacheFile, '<?php return '.var_export($mappings, true).';');

        return [...$innerFiles, $cacheFile];
    }

    /** @return array<string, array<string, class-string>> */
    private function getMappings(): array
    {
        if (null !== $this->mappings) {
            return $this->mappings;
        }

        $cacheFile = $this->cacheDir.self::CACHE_FILE;
        if (is_file($cacheFile)) {
            /** @var array<string, array<string, class-string>> $loaded */
            $loaded = require $cacheFile;

            return $this->mappings = $loaded;
        }

        return $this->mappings = $this->buildMappings();
    }

    /** @return array<string, array<string, class-string>> */
    private function buildMappings(): array
    {
        $map = [];

        foreach ($this->inner->getRouteCollection() as $name => $route) {
            $controller = $route->getDefault('_controller');
            if (!is_string($controller)) {
                continue;
            }

            $method = $this->reflectController($controller);
            if (null === $method) {
                continue;
            }

            /** @var list<string> $variables */
            $variables = array_values($route->compile()->getVariables());

            $entries = $this->mapRouteVariables($variables, $method->getParameters());
            if ([] === $entries) {
                continue;
            }

            $map[$name] = $entries;

            // i18n routes are registered as "route_name.locale" — also map the base
            // name so that path('route_name') in Twig finds the mapping.
            $dotPos = strrpos($name, '.');
            if (false !== $dotPos) {
                $map[substr($name, 0, $dotPos)] ??= $entries;
            }
        }

        return $map;
    }

    /**
     * Links each route variable to the Tokenable entity class of the controller
     * argument that consumes it.
     *
     * @param list<string>                    $variables
     * @param list<\ReflectionParameter>      $params
     *
     * @return array<string, class-string>
     */
    private function mapRouteVariables(array $variables, array $params): array
    {
        $entries = [];
        $consumed = [];

        // Pass 1: route var name matches controller arg name.
        foreach ($params as $param) {
            if (!in_array($param->getName(), $variables, true)) {
                continue;
            }
            $consumed[] = $param->getName();
            $paramClass = $this->tokenableClassOf($param);
            if (null !== $paramClass) {
                $entries[$param->getName()] = $paramClass;
            }
        }

        // Pass 2: for each unmatched route var, link it to the single remaining
        // Tokenable controller arg (handles the "{id} ↔ $event" case).
        foreach (array_values(array_diff($variables, $consumed)) as $var) {
            $candidates = [];
            foreach ($params as $param) {
                if (in_array($param->getName(), $consumed, true)) {
                    continue;
                }
                if (null !== ($class = $this->tokenableClassOf($param))) {
                    $candidates[] = [$param->getName(), $class];
                }
            }
            if (1 === count($candidates)) {
                [$argName, $class] = $candidates[0];
                $entries[$var] = $class;
                $consumed[] = $argName;
            }
        }

        return $entries;
    }

    /** @return class-string|null */
    private function tokenableClassOf(\ReflectionParameter $param): ?string
    {
        $type = $param->getType();
        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        /** @var class-string $class */
        $class = $type->getName();
        if (!$this->tokenizer->isTokenable($class)) {
            return null;
        }

        return $class;
    }

    private function reflectController(string $controller): ?\ReflectionMethod
    {
        if (str_contains($controller, '::')) {
            [$class, $method] = explode('::', $controller, 2);
            if (!class_exists($class) || !method_exists($class, $method)) {
                return null;
            }

            return new \ReflectionMethod($class, $method);
        }

        // service-id "service:method" — out of scope without container access
        if (str_contains($controller, ':')) {
            return null;
        }

        if (class_exists($controller) && method_exists($controller, '__invoke')) {
            return new \ReflectionMethod($controller, '__invoke');
        }

        return null;
    }
}
