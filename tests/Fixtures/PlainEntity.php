<?php

declare(strict_types=1);

namespace LauLamanApps\Tokenable\Tests\Fixtures;

/** Deliberately NOT marked #[Tokenable]. */
final class PlainEntity
{
    public function getId(): int
    {
        return 1;
    }
}
