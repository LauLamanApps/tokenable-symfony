<?php

declare(strict_types=1);

namespace LauLamanApps\Tokenable\Recorder;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Per-request log of token activity. Self-disables when kernel.debug=false so
 * the prod cost is one boolean check per encode/decode call.
 */
final class TokenableRecorder implements ResetInterface
{
    /** @var list<array{route: string, class: string, id: int, token: string}> */
    private array $generations = [];

    /** @var list<array{token: string, expected: string, class: ?string, id: ?int, status: string}> */
    private array $resolutions = [];

    public function __construct(
        #[Autowire('%kernel.debug%')]
        private readonly bool $enabled = false,
    ) {
    }

    public function recordGeneration(string $route, string $class, int $id, string $token): void
    {
        if (!$this->enabled) {
            return;
        }
        $this->generations[] = ['route' => $route, 'class' => $class, 'id' => $id, 'token' => $token];
    }

    public function recordResolution(
        string $token,
        string $expected,
        ?string $class,
        ?int $id,
        string $status,
    ): void {
        if (!$this->enabled) {
            return;
        }
        $this->resolutions[] = [
            'token' => $token,
            'expected' => $expected,
            'class' => $class,
            'id' => $id,
            'status' => $status,
        ];
    }

    /** @return list<array{route: string, class: string, id: int, token: string}> */
    public function getGenerations(): array
    {
        return $this->generations;
    }

    /** @return list<array{token: string, expected: string, class: ?string, id: ?int, status: string}> */
    public function getResolutions(): array
    {
        return $this->resolutions;
    }

    public function reset(): void
    {
        $this->generations = [];
        $this->resolutions = [];
    }
}
