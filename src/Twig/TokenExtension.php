<?php

declare(strict_types=1);

namespace LauLamanApps\Tokenable\Twig;

use LauLamanApps\Tokenable\Tokenizer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class TokenExtension extends AbstractExtension
{
    public function __construct(
        private readonly Tokenizer $tokenizer,
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
        return $this->tokenizer->encode($entity);
    }
}
