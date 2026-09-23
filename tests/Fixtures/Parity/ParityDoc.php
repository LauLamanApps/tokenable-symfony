<?php

declare(strict_types=1);

namespace LauLamanApps\Tokenable\Tests\Fixtures\Parity;

use LauLamanApps\Tokenable\Attribute\Tokenable;

#[Tokenable(prefix: 'doc', prime: 1816261597, inverse: 321322101, random: 1068326100)]
final class ParityDoc
{
    public function __construct(private readonly int $id = 1)
    {
    }

    public function getId(): int
    {
        return $this->id;
    }
}
