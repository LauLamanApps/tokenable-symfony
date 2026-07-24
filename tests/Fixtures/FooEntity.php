<?php

declare(strict_types=1);

namespace LauLamanApps\Tokenable\Tests\Fixtures;

use LauLamanApps\Tokenable\Attribute\Tokenable;

#[Tokenable(prefix: 'foo', prime: 2062703171, inverse: 392512107, random: 628259887)]
final class FooEntity
{
    public function __construct(private readonly int $id = 1)
    {
    }

    public function getId(): int
    {
        return $this->id;
    }
}
