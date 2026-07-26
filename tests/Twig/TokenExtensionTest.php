<?php

declare(strict_types=1);

namespace LauLamanApps\Tokenable\Tests\Twig;

use LauLamanApps\Tokenable\Recorder\TokenableRecorder;
use LauLamanApps\Tokenable\Tests\CreatesTokenizer;
use LauLamanApps\Tokenable\Tests\Fixtures\FooEntity;
use LauLamanApps\Tokenable\Twig\TokenExtension;
use PHPUnit\Framework\TestCase;

final class TokenExtensionTest extends TestCase
{
    use CreatesTokenizer;

    public function testFilterEncodesEntity(): void
    {
        $extension = new TokenExtension($this->createTokenizer(), new TokenableRecorder(enabled: false));

        self::assertSame(
            $this->createTokenizer()->encode(FooEntity::class, 42),
            $extension->token(new FooEntity(42)),
        );
    }

    public function testFilterRecordsTheGenerationSoItSurfacesInTheDataCollector(): void
    {
        $recorder = new TokenableRecorder(enabled: true);
        $extension = new TokenExtension($this->createTokenizer(), $recorder);

        $token = $extension->token(new FooEntity(42));

        self::assertSame(
            [['route' => '(|token filter)', 'class' => FooEntity::class, 'id' => 42, 'token' => $token]],
            $recorder->getGenerations(),
        );
    }

    public function testDisabledRecorderStaysEmpty(): void
    {
        $recorder = new TokenableRecorder(enabled: false);
        $extension = new TokenExtension($this->createTokenizer(), $recorder);

        $extension->token(new FooEntity(42));

        self::assertSame([], $recorder->getGenerations());
    }
}
