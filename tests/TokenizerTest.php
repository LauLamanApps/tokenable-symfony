<?php

declare(strict_types=1);

namespace LauLamanApps\Tokenable\Tests;

use LauLamanApps\Tokenable\Exception\InvalidTokenException;
use LauLamanApps\Tokenable\Tests\Fixtures\BarEntity;
use LauLamanApps\Tokenable\Tests\Fixtures\DuplicatePrefixEntity;
use LauLamanApps\Tokenable\Tests\Fixtures\FakeId;
use LauLamanApps\Tokenable\Tests\Fixtures\FooEntity;
use LauLamanApps\Tokenable\Tests\Fixtures\PlainEntity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TokenizerTest extends TestCase
{
    use CreatesTokenizer;

    public function testEncodeProducesPrefixSeparatorPayload(): void
    {
        $token = $this->createTokenizer()->encode(FooEntity::class, 42);

        self::assertStringStartsWith('foo_', $token);
        self::assertMatchesRegularExpression('/^foo_[0-9a-z]+$/', $token);
    }

    #[DataProvider('ids')]
    public function testEncodeDecodeRoundTrip(int $id): void
    {
        $tokenizer = $this->createTokenizer();

        $token = $tokenizer->encode(FooEntity::class, $id);
        [$class, $decodedId] = $tokenizer->decode($token);

        self::assertSame(FooEntity::class, $class);
        self::assertSame($id, $decodedId);
    }

    /** @return iterable<string, array{int}> */
    public static function ids(): iterable
    {
        yield 'one' => [1];
        yield 'small' => [42];
        yield 'thousand' => [1000];
        yield 'large' => [99999];
        yield 'max 31-bit' => [2147483647];
    }

    public function testEncodeAcceptsEntityWithIntId(): void
    {
        $tokenizer = $this->createTokenizer();

        self::assertSame(
            $tokenizer->encode(FooEntity::class, 7),
            $tokenizer->encode(new FooEntity(7)),
        );
    }

    public function testEncodeAcceptsEntityWithIdValueObject(): void
    {
        $tokenizer = $this->createTokenizer();

        self::assertSame(
            $tokenizer->encode(BarEntity::class, 7),
            $tokenizer->encode(new BarEntity(new FakeId(7))),
        );
    }

    public function testTokensDoNotCollideAcrossTypes(): void
    {
        $tokenizer = $this->createTokenizer();

        self::assertNotSame(
            $tokenizer->encode(FooEntity::class, 1),
            $tokenizer->encode(BarEntity::class, 1),
        );
    }

    public function testDecodeUnknownPrefixThrows(): void
    {
        $this->expectException(InvalidTokenException::class);

        $this->createTokenizer()->decode('nope_abc');
    }

    public function testDecodeMissingSeparatorThrows(): void
    {
        $this->expectException(InvalidTokenException::class);

        $this->createTokenizer()->decode('foobar');
    }

    public function testDecodeEmptyPayloadThrows(): void
    {
        $this->expectException(InvalidTokenException::class);

        $this->createTokenizer()->decode('foo_');
    }

    public function testIsTokenable(): void
    {
        $tokenizer = $this->createTokenizer();

        self::assertTrue($tokenizer->isTokenable(FooEntity::class));
        self::assertFalse($tokenizer->isTokenable(PlainEntity::class));
        self::assertFalse($tokenizer->isTokenable('This\\Class\\Does\\Not\\Exist'));
    }

    public function testConfigForNonTokenableThrows(): void
    {
        $this->expectException(\LogicException::class);

        $this->createTokenizer()->configFor(PlainEntity::class);
    }

    public function testDuplicatePrefixThrows(): void
    {
        $tokenizer = $this->createTokenizer([FooEntity::class, DuplicatePrefixEntity::class]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/Duplicate Tokenable prefix "foo"/');

        // Any decode triggers the prefix map build.
        $tokenizer->decode('foo_1');
    }

    public function testCustomSeparatorAndBaseRoundTrip(): void
    {
        $tokenizer = $this->createTokenizer(separator: '__', base: 16);

        $token = $tokenizer->encode(FooEntity::class, 12345);
        self::assertStringStartsWith('foo__', $token);
        self::assertMatchesRegularExpression('/^foo__[0-9a-f]+$/', $token);

        [$class, $id] = $tokenizer->decode($token);
        self::assertSame(FooEntity::class, $class);
        self::assertSame(12345, $id);
    }

    public function testGettersExposeConfiguration(): void
    {
        $tokenizer = $this->createTokenizer(separator: '-', base: 32);

        self::assertSame('-', $tokenizer->getSeparator());
        self::assertSame(32, $tokenizer->getBase());
    }

    public function testGetRegisteredReturnsEveryTokenableEntity(): void
    {
        $registered = $this->createTokenizer()->getRegistered();

        self::assertArrayHasKey(FooEntity::class, $registered);
        self::assertArrayHasKey(BarEntity::class, $registered);
        self::assertSame('foo', $registered[FooEntity::class]->prefix);
    }
}
