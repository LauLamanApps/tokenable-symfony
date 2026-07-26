<?php

declare(strict_types=1);

namespace LauLamanApps\Tokenable\Tests\DataCollector;

use LauLamanApps\Tokenable\DataCollector\TokenCollector;
use LauLamanApps\Tokenable\Recorder\TokenableRecorder;
use LauLamanApps\Tokenable\Routing\TokenableUrlGenerator;
use LauLamanApps\Tokenable\Tests\CreatesTokenizer;
use LauLamanApps\Tokenable\Tests\Fixtures\Inheritance\AbstractMandate;
use LauLamanApps\Tokenable\Tests\Fixtures\Inheritance\BankTransferMandate;
use LauLamanApps\Tokenable\Tests\Fixtures\Inheritance\CreditCardMandate;
use LauLamanApps\Tokenable\Tests\Fixtures\Inheritance\DirectDebitMandate;
use LauLamanApps\Tokenable\Tokenizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

/**
 * Guards the profiler collector against the false-positive collisions that a
 * shared-prefix Doctrine hierarchy used to trigger: once a subclass instance is
 * encoded during a request, the Tokenizer caches its inherited config under the
 * concrete class, and the collector must not then report the base and its
 * subclasses as prefix/triplet collisions.
 */
final class TokenCollectorInheritanceTest extends TestCase
{
    use CreatesTokenizer;

    private const HIERARCHY = [
        AbstractMandate::class,
        DirectDebitMandate::class,
        CreditCardMandate::class,
        BankTransferMandate::class,
    ];

    public function testInheritedSubclassesDoNotProduceCollisionErrors(): void
    {
        $tokenizer = $this->createTokenizer(self::HIERARCHY);

        // Simulate a request that encodes concrete subclass instances: this caches
        // their inherited config under the concrete class name in the Tokenizer,
        // which is exactly what used to leak into the collector as a collision.
        $tokenizer->encode(new DirectDebitMandate(1));
        $tokenizer->encode(new CreditCardMandate(2));
        $tokenizer->encode(new BankTransferMandate(3));

        $collector = $this->createCollector($tokenizer);
        $collector->collect(new Request(), new Response());

        $rows = $collector->getTokens();

        // Only the declaring abstract base owns the prefix; subclasses that merely
        // inherit it must not appear as their own tokenable rows.
        self::assertCount(1, $rows);
        self::assertSame(AbstractMandate::class, $rows[0]['class']['name']);
        self::assertSame([], $rows[0]['errors']);
    }

    private function createCollector(Tokenizer $tokenizer): TokenCollector
    {
        $recorder = new TokenableRecorder(false);

        $inner = $this->createMock(RouterInterface::class);
        $inner->method('getRouteCollection')->willReturn(new RouteCollection());
        $urlGenerator = new TokenableUrlGenerator($inner, $tokenizer, sys_get_temp_dir());

        return new TokenCollector($tokenizer, $recorder, $urlGenerator);
    }
}
