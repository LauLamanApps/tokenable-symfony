<?php

declare(strict_types=1);

namespace LauLamanApps\Tokenable\Tests;

use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use LauLamanApps\Tokenable\Tests\Fixtures\BarEntity;
use LauLamanApps\Tokenable\Tests\Fixtures\FooEntity;
use LauLamanApps\Tokenable\Tokenizer;

/**
 * Builds a real Tokenizer whose Doctrine metadata driver reports a fixed set of
 * entity classes, so decoding / discovery can run without a database.
 *
 * @mixin \PHPUnit\Framework\TestCase
 */
trait CreatesTokenizer
{
    /**
     * @param list<class-string> $classes
     */
    private function createTokenizer(
        array $classes = [FooEntity::class, BarEntity::class],
        string $separator = '_',
        int $base = 36,
    ): Tokenizer {
        return new Tokenizer($this->createEntityManager(null, $classes), $separator, $base);
    }

    /**
     * Builds an EntityManager mock whose metadata driver reports the given
     * classes and whose find() delegates to the supplied callback.
     *
     * @param (callable(class-string, int): ?object)|null $find
     * @param list<class-string>                          $classes
     */
    private function createEntityManager(
        ?callable $find = null,
        array $classes = [FooEntity::class, BarEntity::class],
    ): EntityManagerInterface {
        $driver = $this->createMock(MappingDriver::class);
        $driver->method('getAllClassNames')->willReturn($classes);

        $configuration = $this->createMock(Configuration::class);
        $configuration->method('getMetadataDriverImpl')->willReturn($driver);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConfiguration')->willReturn($configuration);

        if (null !== $find) {
            $entityManager->method('find')->willReturnCallback(
                static fn (string $className, mixed $id): ?object => $find($className, (int) $id),
            );
        }

        return $entityManager;
    }
}
