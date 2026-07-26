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

        $recorder->recordResolution('foo_1', FooEntity::class, FooEntity::class, 1, 'success');

        self::assertSame([], $recorder->getResolutions());
    }

    public function testEnabledRecorderCapturesResolutions(): void
    {
        $recorder = new TokenableRecorder(enabled: true);

        $recorder->recordResolution('foo_2', FooEntity::class, FooEntity::class, 2, 'success');

        self::assertCount(1, $recorder->getResolutions());
        self::assertSame('success', $recorder->getResolutions()[0]['status']);
    }

    public function testResetClearsEverything(): void
    {
        $recorder = new TokenableRecorder(enabled: true);
        $recorder->recordResolution('foo_1', FooEntity::class, FooEntity::class, 1, 'success');

        $recorder->reset();

        self::assertSame([], $recorder->getResolutions());
    }
}
