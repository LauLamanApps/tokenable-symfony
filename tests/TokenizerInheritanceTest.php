<?php

declare(strict_types=1);

namespace LauLamanApps\Tokenable\Tests;

use LauLamanApps\Tokenable\Tests\Fixtures\Inheritance\AbstractMandate;
use LauLamanApps\Tokenable\Tests\Fixtures\Inheritance\BankTransferMandate;
use LauLamanApps\Tokenable\Tests\Fixtures\Inheritance\CreditCardMandate;
use LauLamanApps\Tokenable\Tests\Fixtures\Inheritance\DirectDebitMandate;
use PHPUnit\Framework\TestCase;

/**
 * Covers #[Tokenable] declared on an abstract Doctrine base with a
 * DiscriminatorMap: the attribute must be inherited by concrete subclasses,
 * and the shared prefix must decode back to the base (Doctrine then loads the
 * concrete subclass).
 */
final class TokenizerInheritanceTest extends TestCase
{
    use CreatesTokenizer;

    private const HIERARCHY = [
        AbstractMandate::class,
        DirectDebitMandate::class,
        CreditCardMandate::class,
        BankTransferMandate::class,
    ];

    public function testSubclassInheritsAttributeFromAbstractBase(): void
    {
        $tokenizer = $this->createTokenizer(self::HIERARCHY);

        self::assertTrue($tokenizer->isTokenable(DirectDebitMandate::class));
        self::assertSame('mnd', $tokenizer->configFor(DirectDebitMandate::class)->prefix);
        self::assertEquals(
            $tokenizer->configFor(AbstractMandate::class),
            $tokenizer->configFor(DirectDebitMandate::class),
        );
    }

    public function testEncodingSubclassInstanceUsesSharedPrefix(): void
    {
        $tokenizer = $this->createTokenizer(self::HIERARCHY);

        $token = $tokenizer->encode(new DirectDebitMandate(42));

        self::assertStringStartsWith('mnd_', $token);
        // Same id, same triplet → identical token whether addressed by instance,
        // by concrete class name, or by the abstract base.
        self::assertSame($tokenizer->encode(DirectDebitMandate::class, 42), $token);
        self::assertSame($tokenizer->encode(AbstractMandate::class, 42), $token);
    }

    public function testSubclassesShareOneTokenSpace(): void
    {
        $tokenizer = $this->createTokenizer(self::HIERARCHY);

        // Different subclasses with the same id collapse to the same token: the
        // prefix identifies the hierarchy, not the concrete class.
        self::assertSame(
            $tokenizer->encode(new CreditCardMandate(7)),
            $tokenizer->encode(new BankTransferMandate(7)),
        );
    }

    public function testSharedPrefixDecodesToAbstractBase(): void
    {
        $tokenizer = $this->createTokenizer(self::HIERARCHY);

        $token = $tokenizer->encode(new DirectDebitMandate(99));
        [$class, $id] = $tokenizer->decode($token);

        self::assertSame(AbstractMandate::class, $class);
        self::assertSame(99, $id);
    }

    public function testInheritedPrefixIsRegisteredOnceWithoutCollision(): void
    {
        $tokenizer = $this->createTokenizer(self::HIERARCHY);

        // The three subclasses inherit the prefix but must not each register it,
        // otherwise the prefix map would throw a duplicate-prefix error.
        $registered = $tokenizer->getRegistered();

        self::assertArrayHasKey(AbstractMandate::class, $registered);
        self::assertArrayNotHasKey(DirectDebitMandate::class, $registered);
        self::assertArrayNotHasKey(CreditCardMandate::class, $registered);
    }
}
