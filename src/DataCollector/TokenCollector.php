<?php

declare(strict_types=1);

namespace LauLamanApps\Tokenable\DataCollector;

use LauLamanApps\Tokenable\Exception\InvalidTokenException;
use LauLamanApps\Tokenable\Recorder\TokenableRecorder;
use LauLamanApps\Tokenable\Routing\TokenableUrlGenerator;
use LauLamanApps\Tokenable\Tokenizer;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @phpstan-type InboundRow array{name: string, value: string, id: int|null, class: string, source: string, status: string|null}
 * @phpstan-type GenerationRow array{route: string, class: string, id: int, token: string}
 * @phpstan-type ResolutionRow array{token: string, expected: string, class: string|null, id: int|null, status: string}
 * @phpstan-type TokenRow array{class: array{name: class-string, file: string|false, line: int|false}, prefix: string, prime: int, inverse: int, random: int, samples: array<int, string>, errors: list<string>, warnings: list<string>}
 * @phpstan-type CollectorData array{inbound: list<InboundRow>, tokens: array<class-string, TokenRow>, generations: list<GenerationRow>, routeMap: array<string, array<string, class-string>>}
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
            'generations' => $this->recorder->getGenerations(),
            'routeMap' => $this->urlGenerator->getRouteMappings(),
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
        $tokens = [];
        foreach ($this->tokenizer->getRegistered() as $class => $config) {
            $reflection = new \ReflectionClass($class);
            $samples = [];
            foreach (self::SAMPLE_IDS as $sampleId) {
                $samples[$sampleId] = $this->tokenizer->encode($class, $sampleId);
            }

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
                'errors' => [],
                'warnings' => [],
            ];
        }

        $this->validateTokens($tokens);

        return $tokens;
    }

    /** @param array<class-string, TokenRow> $tokens */
    private function validateTokens(array &$tokens): void
    {
        $separator = $this->tokenizer->getSeparator();

        /** @var array<string, list<class-string>> $byTriplet */
        $byTriplet = [];
        /** @var array<string, class-string> $byPrefix */
        $byPrefix = [];

        foreach ($tokens as $name => $entry) {
            $tripletKey = sprintf('%d.%d.%d', $entry['prime'], $entry['inverse'], $entry['random']);
            if (isset($byTriplet[$tripletKey])) {
                foreach ($byTriplet[$tripletKey] as $existing) {
                    $tokens[$name]['errors'][] = sprintf('Triplet collides with %s', $existing);
                    $tokens[$existing]['errors'][] = sprintf('Triplet collides with %s', $name);
                }
            }
            $byTriplet[$tripletKey][] = $name;

            if (isset($byPrefix[$entry['prefix']])) {
                $tokens[$name]['errors'][] = sprintf(
                    'Prefix "%s" is already used by %s',
                    $entry['prefix'],
                    $byPrefix[$entry['prefix']],
                );
            } else {
                $byPrefix[$entry['prefix']] = $name;
            }

            if (str_contains($entry['prefix'], $separator)) {
                $tokens[$name]['errors'][] = sprintf(
                    'Prefix "%s" contains the token separator "%s", which will break decoding.',
                    $entry['prefix'],
                    $separator,
                );
            }

            if (strlen($entry['prefix']) < 3) {
                $tokens[$name]['warnings'][] = sprintf(
                    'Prefix "%s" is shorter than 3 characters; collisions are likelier.',
                    $entry['prefix'],
                );
            }

            if ($entry['prime'] <= 0 || $entry['prime'] > self::OPTIMUS_MAX_INT) {
                $tokens[$name]['errors'][] = sprintf(
                    'Prime %d is outside Optimus range (1..%d).',
                    $entry['prime'],
                    self::OPTIMUS_MAX_INT,
                );
            }
        }
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

    /** @return list<GenerationRow> */
    public function getGenerations(): array
    {
        return $this->data()['generations'];
    }

    /** @return array<string, array<string, class-string>> */
    public function getRouteMap(): array
    {
        return $this->data()['routeMap'];
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
