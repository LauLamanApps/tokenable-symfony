<?php

declare(strict_types=1);

namespace LauLamanApps\Tokenable\Tests\Fixtures\Parity;

use LauLamanApps\Tokenable\Attribute\Tokenable;

#[Tokenable(prefix: 'per', prime: 457259753, inverse: 1096891737, random: 2026340003)]
final class ParityPer
{
    public function __construct(private readonly int $id = 1)
    {
    }

    public function getId(): int
    {
        return $this->id;
    }
}
