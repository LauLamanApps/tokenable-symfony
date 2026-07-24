<?php

declare(strict_types=1);

namespace LauLamanApps\Tokenable\Tests\Fixtures;

use LauLamanApps\Tokenable\Attribute\Tokenable;

#[Tokenable(prefix: 'bar', prime: 275803571, inverse: 1902119291, random: 1515296780)]
final class BarEntity
{
    public function __construct(private readonly FakeId $id)
    {
    }

    public function getId(): FakeId
    {
        return $this->id;
    }
}
