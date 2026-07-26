<?php

declare(strict_types=1);

namespace LauLamanApps\Tokenable\DataCollector;

use LauLamanApps\Tokenable\Attribute\Tokenable;
use LauLamanApps\Tokenable\Exception\InvalidTokenException;
use LauLamanApps\Tokenable\Recorder\TokenableRecorder;
use LauLamanApps\Tokenable\Routing\TokenableUrlGenerator;
use LauLamanApps\Tokenable\Tokenizer;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @phpstan-type InboundRow array{name: string, value: string, id: int|null, class: string, source: string, status: string|null}
 * @phpstan-type ResolutionRow array{token: string, expected: string, class: string|null, id: int|null, status: string}
 * @phpstan-type TokenRow array{class: array{name: class-string, file: string|false, line: int|false}, prefix: string, prime: int, inverse: int, random: int, samples: array<int, string>, errors: list<string>, warnings: list<string>}
 * @phpstan-type CollectorData array{inbound: list<InboundRow>, tokens: array<class-string, TokenRow>, routeMap: array<string, array<string, class-string>>, separator: string, base: int}
 */
final class TokenCollector extends AbstractDataCollector
{
    private const SAMPLE_IDS = [1, 42, 1000, 99999];
    private const OPTIMUS_MAX_INT = 2147483647;

    public function __construct(
        private readonly Tokenizer $tokenizer,
        private readonly TokenableRecorder $recorder,
        private readonly TokenableUrlGenerator $urlGenerator,
    ) {
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        /** @var array<string, list<ResolutionRow>> $resolutionsByToken */
        $resolutionsByToken = [];
        foreach ($this->recorder->getResolutions() as $resolution) {
            $resolutionsByToken[$resolution['token']][] = $resolution;
        }

        $inbound = [];
        $this->scanInbound($request, $resolutionsByToken, $inbound);

        // Any resolver entries that didn't match a scanned request attribute
        // (e.g. token sourced from a custom listener) — append them as standalone rows.
        foreach ($resolutionsByToken as $token => $remaining) {
            foreach ($remaining as $resolution) {
                $inbound[] = [
                    'name' => '—',
                    'value' => $token,
                    'id' => $resolution['id'],
                    'class' => $resolution['class'] ?? $resolution['expected'],
                    'source' => 'controller arg',
                    'status' => $resolution['status'],
                ];
            }
        }

        $this->data = [
            'inbound' => $inbound,
            'tokens' => $this->buildTokens(),
            'routeMap' => $this->urlGenerator->getRouteMappings(),
            'separator' => $this->tokenizer->getSeparator(),
            'base' => $this->tokenizer->getBase(),
        ];
    }

    public static function getTemplate(): string
    {
        return '@Tokenable/data_collector/token.html.twig';
    }

    /**
     * @param array<string, list<ResolutionRow>> $resolutionsByToken
     * @param list<InboundRow>                    $inbound
     */
    private function scanInbound(Request $request, array &$resolutionsByToken, array &$inbound): void
    {
        $routeParams = $request->attributes->get('_route_params');
        $this->scanBag(\is_array($routeParams) ? $routeParams : [], '', 'route', $resolutionsByToken, $inbound);
        $this->scanBag($request->query->all(), '', 'query', $resolutionsByToken, $inbound);
        $this->scanBag($request->request->all(), '', 'post', $resolutionsByToken, $inbound);
    }

    /**
     * @param array<array-key, mixed>             $params
     * @param array<string, list<ResolutionRow>>  $resolutionsByToken
     * @param list<InboundRow>                    $inbound
     */
    private function scanBag(array $params, string $prefix, string $source, array &$resolutionsByToken, array &$inbound): void
    {
        foreach ($params as $name => $value) {
            $key = '' !== $prefix ? $prefix.'['.$name.']' : (string) $name;
            if (\is_array($value)) {
                $this->scanBag($value, $key, $source, $resolutionsByToken, $inbound);
            } elseif (\is_string($value)) {
                $this->addInbound($key, $value, $source, $resolutionsByToken, $inbound);
            }
        }
    }

    /**
     * @param array<string, list<ResolutionRow>> $resolutionsByToken
     * @param list<InboundRow>                   $inbound
     */
    private function addInbound(string $name, string $value, string $source, array &$resolutionsByToken, array &$inbound): void
    {
        try {
            [$class, $id] = $this->tokenizer->decode($value);
        } catch (InvalidTokenException) {
            return;
        }

        $status = null;
        if (isset($resolutionsByToken[$value]) && [] !== $resolutionsByToken[$value]) {
            $status = array_shift($resolutionsByToken[$value])['status'];
            if ([] === $resolutionsByToken[$value]) {
                unset($resolutionsByToken[$value]);
            }
        }

        $inbound[] = [
            'name' => $name,
            'value' => $value,
            'id' => $id,
            'class' => $class,
            'source' => $source,
            'status' => $status,
        ];
    }

    /** @return array<class-string, TokenRow> */
    private function buildTokens(): array
    {
        $registered = $this->declaringOnly($this->tokenizer->getRegistered());
        [$errors, $warnings] = $this->validate($registered);

        $tokens = [];
        foreach ($registered as $class => $config) {
            $reflection = new \ReflectionClass($class);
            $samples = [];
            foreach (self::SAMPLE_IDS as $sampleId) {
                $samples[$sampleId] = $this->tokenizer->encode($class, $sampleId);
            }

            // Each row is built complete in one literal so it always matches TokenRow.
            $tokens[$class] = [
                'class' => [
                    'name' => $class,
                    'file' => $reflection->getFileName(),
                    'line' => $reflection->getStartLine(),
                ],
                'prefix' => $config->prefix,
                'prime' => $config->prime,
                'inverse' => $config->inverse,
                'random' => $config->random,
                'samples' => $samples,
                'errors' => $errors[$class] ?? [],
                'warnings' => $warnings[$class] ?? [],
            ];
        }

        return $tokens;
    }

    /**
     * Keeps only classes that *declare* #[Tokenable] directly, dropping subclasses
     * that merely inherit it through Doctrine inheritance. Those subclasses share
     * their base's prefix and triplet by design (the Tokenizer registers the prefix
     * once, on the declaring base — see Tokenizer::buildPrefixMap); listing them here
     * would make validate() flag the intended sharing as a prefix/triplet collision.
     *
     * getRegistered() can contain such subclasses because configFor() caches an
     * inherited config under the concrete class the moment a subclass instance is
     * encoded during the request.
     *
     * @param array<class-string, Tokenable> $registered
     *
     * @return array<class-string, Tokenable>
     */
    private function declaringOnly(array $registered): array
    {
        return array_filter(
            $registered,
            static fn (string $class): bool => [] !== (new \ReflectionClass($class))->getAttributes(Tokenable::class),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Validates the registered tokenable configs and returns two class-keyed maps:
     * [errors, warnings]. Kept separate from the token map, and accumulating into
     * uniform list<string> maps (never shaped-array mutation), so it stays clean at
     * PHPStan max.
     *
     * @param array<class-string, Tokenable> $registered
     *
     * @return array{0: array<class-string, list<string>>, 1: array<class-string, list<string>>}
     */
    private function validate(array $registered): array
    {
        $separator = $this->tokenizer->getSeparator();

        /** @var array<class-string, list<string>> $errors */
        $errors = [];
        /** @var array<class-string, list<string>> $warnings */
        $warnings = [];
        /** @var array<string, list<class-string>> $byTriplet */
        $byTriplet = [];
        /** @var array<string, class-string> $byPrefix */
        $byPrefix = [];

        foreach ($registered as $name => $config) {
            $tripletKey = sprintf('%d.%d.%d', $config->prime, $config->inverse, $config->random);
            if (isset($byTriplet[$tripletKey])) {
                foreach ($byTriplet[$tripletKey] as $existing) {
                    $errors[$name][] = sprintf('Triplet collides with %s', $existing);
                    $errors[$existing][] = sprintf('Triplet collides with %s', $name);
                }
            }
            $byTriplet[$tripletKey][] = $name;

            if (isset($byPrefix[$config->prefix])) {
                $errors[$name][] = sprintf('Prefix "%s" is already used by %s', $config->prefix, $byPrefix[$config->prefix]);
            } else {
                $byPrefix[$config->prefix] = $name;
            }

            if (str_contains($config->prefix, $separator)) {
                $errors[$name][] = sprintf(
                    'Prefix "%s" contains the token separator "%s", which will break decoding.',
                    $config->prefix,
                    $separator,
                );
            }

            if (strlen($config->prefix) < 3) {
                $warnings[$name][] = sprintf('Prefix "%s" is shorter than 3 characters; collisions are likelier.', $config->prefix);
            }

            if ($config->prime <= 0 || $config->prime > self::OPTIMUS_MAX_INT) {
                $errors[$name][] = sprintf('Prime %d is outside Optimus range (1..%d).', $config->prime, self::OPTIMUS_MAX_INT);
            }
        }

        return [$errors, $warnings];
    }

    /** @return list<InboundRow> */
    public function getInbound(): array
    {
        return $this->data()['inbound'];
    }

    /** @return list<TokenRow> */
    public function getTokens(): array
    {
        return array_values($this->data()['tokens']);
    }

    /** @return array<string, array<string, class-string>> */
    public function getRouteMap(): array
    {
        return $this->data()['routeMap'];
    }

    public function getSeparator(): string
    {
        return $this->data()['separator'];
    }

    public function getBase(): int
    {
        return $this->data()['base'];
    }

    /** @return array<class-string, TokenRow> */
    public function getErrors(): array
    {
        return array_filter($this->data()['tokens'], static fn (array $row): bool => [] !== $row['errors']);
    }

    /** @return array<class-string, TokenRow> */
    public function getWarnings(): array
    {
        return array_filter($this->data()['tokens'], static fn (array $row): bool => [] !== $row['warnings']);
    }

    /** @return CollectorData */
    private function data(): array
    {
        \assert(\is_array($this->data));

        /** @var CollectorData */
        return $this->data;
    }
}
