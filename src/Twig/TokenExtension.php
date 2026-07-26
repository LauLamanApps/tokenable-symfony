<?php

declare(strict_types=1);

namespace LauLamanApps\Tokenable\Twig;

use LauLamanApps\Tokenable\Recorder\TokenableRecorder;
use LauLamanApps\Tokenable\Tokenizer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class TokenExtension extends AbstractExtension
{
    private const MANUAL_ROUTE_LABEL = '(|token filter)';

    public function __construct(
        private readonly Tokenizer $tokenizer,
        private readonly TokenableRecorder $recorder,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('token', $this->token(...)),
        ];
    }

    public function token(object $entity): string
    {
        $token = $this->tokenizer->encode($entity);

        $id = method_exists($entity, 'getId') ? $entity->getId() : null;
        if (is_int($id)) {
            $this->recorder->recordGeneration(self::MANUAL_ROUTE_LABEL, $entity::class, $id, $token);
        }

        return $token;
    }
}
