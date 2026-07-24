<?php

declare(strict_types=1);

namespace LauLamanApps\Tokenable\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class Tokenable
{
    public function __construct(
        public string $prefix,
        public int $prime,
        public int $inverse,
        public int $random,
    ) {
    }
}
