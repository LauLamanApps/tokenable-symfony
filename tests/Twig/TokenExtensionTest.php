<?php

declare(strict_types=1);

namespace LauLamanApps\Tokenable\Tests\Twig;

use LauLamanApps\Tokenable\Tests\CreatesTokenizer;
use LauLamanApps\Tokenable\Tests\Fixtures\FooEntity;
use LauLamanApps\Tokenable\Twig\TokenExtension;
use PHPUnit\Framework\TestCase;

final class TokenExtensionTest extends TestCase
{
    use CreatesTokenizer;

    public function testFilterEncodesEntity(): void
    {
        $extension = new TokenExtension($this->createTokenizer());

        self::assertSame(
            $this->createTokenizer()->encode(FooEntity::class, 42),
            $extension->token(new FooEntity(42)),
        );
    }
}
