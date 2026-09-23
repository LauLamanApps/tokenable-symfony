<?php

declare(strict_types=1);

namespace LauLamanApps\Tokenable\Tests\Fixtures\Parity;

use LauLamanApps\Tokenable\Attribute\Tokenable;

#[Tokenable(prefix: 'par', prime: 2130167569, inverse: 48105969, random: 306971961)]
final class ParityPar
{
    public function __construct(private readonly int $id = 1)
    {
    }

    public function getId(): int
    {
        return $this->id;
    }
}
