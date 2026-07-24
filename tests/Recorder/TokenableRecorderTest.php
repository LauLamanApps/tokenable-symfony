<?php

declare(strict_types=1);

namespace LauLamanApps\Tokenable\Tests\Recorder;

use LauLamanApps\Tokenable\Recorder\TokenableRecorder;
use LauLamanApps\Tokenable\Tests\Fixtures\FooEntity;
use PHPUnit\Framework\TestCase;

final class TokenableRecorderTest extends TestCase
{
    public function testDisabledRecorderKeepsNothing(): void
    {
        $recorder = new TokenableRecorder(enabled: false);

        $recorder->recordGeneration('route', FooEntity::class, 1, 'foo_1');
        $recorder->recordResolution('foo_1', FooEntity::class, FooEntity::class, 1, 'success');

        self::assertSame([], $recorder->getGenerations());
        self::assertSame([], $recorder->getResolutions());
    }

    public function testEnabledRecorderCapturesActivity(): void
    {
        $recorder = new TokenableRecorder(enabled: true);

        $recorder->recordGeneration('route', FooEntity::class, 1, 'foo_1');
        $recorder->recordResolution('foo_2', FooEntity::class, FooEntity::class, 2, 'success');

        self::assertSame(
            [['route' => 'route', 'class' => FooEntity::class, 'id' => 1, 'token' => 'foo_1']],
            $recorder->getGenerations(),
        );
        self::assertCount(1, $recorder->getResolutions());
        self::assertSame('success', $recorder->getResolutions()[0]['status']);
    }

    public function testResetClearsEverything(): void
    {
        $recorder = new TokenableRecorder(enabled: true);
        $recorder->recordGeneration('route', FooEntity::class, 1, 'foo_1');

        $recorder->reset();

        self::assertSame([], $recorder->getGenerations());
        self::assertSame([], $recorder->getResolutions());
    }
}
