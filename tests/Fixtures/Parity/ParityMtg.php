<?php

declare(strict_types=1);

namespace LauLamanApps\Tokenable\Tests\Fixtures\Parity;

use LauLamanApps\Tokenable\Attribute\Tokenable;

#[Tokenable(prefix: 'mtg', prime: 803610877, inverse: 1643576405, random: 1965290067)]
final class ParityMtg
{
    public function __construct(private readonly int $id = 1)
    {
    }

    public function getId(): int
    {
        return $this->id;
    }
}
