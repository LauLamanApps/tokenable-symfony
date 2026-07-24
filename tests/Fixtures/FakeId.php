<?php

declare(strict_types=1);

namespace LauLamanApps\Tokenable\Tests\Fixtures;

/** Stand-in for an application id value object (e.g. PersonId) exposing getValue(): int. */
final class FakeId
{
    public function __construct(private readonly int $value)
    {
    }

    public function getValue(): int
    {
        return $this->value;
    }
}
