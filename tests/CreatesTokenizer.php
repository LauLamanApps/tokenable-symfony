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
        $driver = $this->createMock(MappingDriver::class);
        $driver->method('getAllClassNames')->willReturn($classes);

        $configuration = $this->createMock(Configuration::class);
        $configuration->method('getMetadataDriverImpl')->willReturn($driver);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConfiguration')->willReturn($configuration);

        return new Tokenizer($entityManager, $separator, $base);
    }
}
