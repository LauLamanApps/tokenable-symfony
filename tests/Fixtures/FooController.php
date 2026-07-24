<?php

declare(strict_types=1);

namespace LauLamanApps\Tokenable\Tests\Fixtures;

/** Invokable controller typed against a tokenable entity — used by the router-decorator test. */
final class FooController
{
    public function __invoke(FooEntity $foo): void
    {
    }
}
