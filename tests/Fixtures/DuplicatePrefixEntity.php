<?php

declare(strict_types=1);

namespace LauLamanApps\Tokenable\Tests\Fixtures;

use LauLamanApps\Tokenable\Attribute\Tokenable;

/** Shares the "foo" prefix with {@see FooEntity} — used to assert collision detection. */
#[Tokenable(prefix: 'foo', prime: 996983459, inverse: 1312489739, random: 1636615799)]
final class DuplicatePrefixEntity
{
    public function getId(): int
    {
        return 1;
    }
}
