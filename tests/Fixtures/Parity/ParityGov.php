<?php

declare(strict_types=1);

namespace LauLamanApps\Tokenable\Tests\Fixtures\Parity;

use LauLamanApps\Tokenable\Attribute\Tokenable;

#[Tokenable(prefix: 'gov', prime: 625932641, inverse: 2146658977, random: 143053268)]
final class ParityGov
{
    public function __construct(private readonly int $id = 1)
    {
    }

    public function getId(): int
    {
        return $this->id;
    }
}
