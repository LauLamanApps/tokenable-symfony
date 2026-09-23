<?php

declare(strict_types=1);

namespace LauLamanApps\Tokenable\Tests;

use Jenssegers\Optimus\Optimus;
use LauLamanApps\Tokenable\Attribute\Tokenable;
use LauLamanApps\Tokenable\Tests\Fixtures\Parity\ParityDoc;
use LauLamanApps\Tokenable\Tests\Fixtures\Parity\ParityGov;
use LauLamanApps\Tokenable\Tests\Fixtures\Parity\ParityMtg;
use LauLamanApps\Tokenable\Tests\Fixtures\Parity\ParityPar;
use LauLamanApps\Tokenable\Tests\Fixtures\Parity\ParityPer;
use LauLamanApps\Tokenable\Tokenizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The runtime hash used to come from jenssegers/optimus. That package pins
 * symfony/console to ^5||^6||^7 for a CLI command this bundle never calls,
 * which kept the bundle off Symfony 8, so the two expressions it contributed
 * were inlined into {@see Tokenizer::obfuscate()}.
 *
 * Inlining an algorithm is only safe when the output is bit-for-bit the same:
 * tokens already sit in bookmarks, emails and links, and a drifted hash would
 * silently resolve them to the wrong row rather than to a 404. This pins that
 * equivalence against the real package for as long as it is installed as a dev
 * dependency.
 */
final class OptimusParityTest extends TestCase
{
    use CreatesTokenizer;

    /** @return iterable<string, array{class-string}> */
    public static function tokenableEntities(): iterable
    {
        // Real triplets, as minted by app:tokenable:generate.
        yield 'per' => [ParityPer::class];
        yield 'gov' => [ParityGov::class];
        yield 'par' => [ParityPar::class];
        yield 'doc' => [ParityDoc::class];
        yield 'mtg' => [ParityMtg::class];
    }

    /** @param class-string $class */
    #[DataProvider('tokenableEntities')]
    public function testTheInlinedHashMatchesOptimusExactly(string $class): void
    {
        if (!class_exists(Optimus::class)) {
            self::markTestSkipped('jenssegers/optimus is not installed.');
        }

        $config = (new \ReflectionClass($class))->getAttributes(Tokenable::class)[0]->newInstance();
        \assert($config instanceof Tokenable);

        $optimus = new Optimus($config->prime, $config->inverse, $config->random);
        $tokenizer = $this->createTokenizer([$class]);

        foreach (self::sampleIds() as $id) {
            $token = $tokenizer->encode($class, $id);
            $payload = substr($token, strrpos($token, '_') + 1);

            self::assertSame(
                base_convert((string) $optimus->encode($id), 10, 36),
                $payload,
                \sprintf('Payload for id %d drifted from Optimus.', $id),
            );

            self::assertSame([$class, $id], $tokenizer->decode($token));
        }
    }

    /**
     * Small ids (the everyday case), both ends of the 31-bit range, and an even
     * spread in between. Deterministic on purpose: a random id that fails once
     * and never again is not a regression test.
     *
     * @return list<int>
     */
    private static function sampleIds(): array
    {
        $ids = range(1, 200);
        $ids[] = Tokenizer::MAX_ID;
        $ids[] = Tokenizer::MAX_ID - 1;

        for ($step = 1; $step <= 50; $step++) {
            $ids[] = intdiv(Tokenizer::MAX_ID, 51) * $step;
        }

        return $ids;
    }
}
