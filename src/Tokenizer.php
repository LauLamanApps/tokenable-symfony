<?php

declare(strict_types=1);

namespace LauLamanApps\Tokenable;

use Doctrine\ORM\EntityManagerInterface;
use Jenssegers\Optimus\Optimus;
use LauLamanApps\Tokenable\Attribute\Tokenable;
use LauLamanApps\Tokenable\Exception\InvalidTokenException;

final class Tokenizer
{
    /** @var array<class-string, Tokenable> */
    private array $configByClass = [];

    /** @var array<string, Optimus> */
    private array $optimusByClass = [];

    /** @var array<string, class-string>|null */
    private ?array $classByPrefix = null;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly string $separator = '_',
        private readonly int $base = 36,
    ) {
    }

    public function getSeparator(): string
    {
        return $this->separator;
    }

    public function getBase(): int
    {
        return $this->base;
    }

    public function encode(object|string $entityOrClass, ?int $id = null): string
    {
        if (is_object($entityOrClass)) {
            $class = $entityOrClass::class;
            if ($id === null && method_exists($entityOrClass, 'getId')) {
                $id = $this->extractId($entityOrClass->getId());
            }
        } else {
            $class = $entityOrClass;
        }

        if ($id === null) {
            throw new \InvalidArgumentException(\sprintf('Cannot tokenize "%s" without an id.', $class));
        }

        $config = $this->configFor($class);
        $encoded = $this->optimusFor($class, $config)->encode($id);

        return $config->prefix.$this->separator.base_convert((string) $encoded, 10, $this->base);
    }

    /** @return array{class-string, int} */
    public function decode(string $token): array
    {
        $pos = strrpos($token, $this->separator);
        if ($pos === false) {
            throw new InvalidTokenException(\sprintf('Token "%s" is missing the "%s" separator.', $token, $this->separator));
        }

        $prefix = substr($token, 0, $pos);
        $payload = substr($token, $pos + \strlen($this->separator));

        if ('' === $prefix || '' === $payload) {
            throw new InvalidTokenException(\sprintf('Token "%s" is malformed.', $token));
        }

        $class = $this->classForPrefix($prefix);
        $config = $this->configFor($class);
        $id = $this->optimusFor($class, $config)->decode((int) base_convert($payload, $this->base, 10));

        return [$class, $id];
    }

    /**
     * Normalises whatever getId() returns (an int, an id value object exposing
     * getValue(): int, or a numeric scalar) to a plain integer.
     */
    private function extractId(mixed $rawId): int
    {
        if (\is_int($rawId)) {
            return $rawId;
        }

        if (\is_object($rawId) && method_exists($rawId, 'getValue')) {
            $value = $rawId->getValue();

            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        if (is_numeric($rawId)) {
            return (int) $rawId;
        }

        throw new \InvalidArgumentException(\sprintf(
            'Cannot derive an integer id from a value of type "%s".',
            get_debug_type($rawId),
        ));
    }

    public function configFor(string $class): Tokenable
    {
        if (isset($this->configByClass[$class])) {
            return $this->configByClass[$class];
        }

        if (!class_exists($class)) {
            throw new \LogicException(\sprintf('Class "%s" does not exist.', $class));
        }

        $config = $this->resolveConfig($class);
        if (null === $config) {
            throw new \LogicException(\sprintf('Class "%s" is not marked with #[Tokenable].', $class));
        }

        return $this->configByClass[$class] = $config;
    }

    /** @return array<class-string, Tokenable> */
    public function getRegistered(): array
    {
        $this->classByPrefix ??= $this->buildPrefixMap();

        return $this->configByClass;
    }

    public function isTokenable(string $class): bool
    {
        if (isset($this->configByClass[$class])) {
            return true;
        }
        if (!class_exists($class)) {
            return false;
        }

        return null !== $this->resolveConfig($class);
    }

    /**
     * Resolves the #[Tokenable] attribute for a class, walking up the parent
     * chain. This lets a concrete entity inherit the attribute declared on an
     * abstract base (Doctrine single-table/JOINED inheritance): encoding a
     * subclass instance yields the shared-prefix token of its base.
     *
     * Only the declaring class is registered as a prefix owner (see
     * buildPrefixMap), so decoding stays unambiguous — the shared prefix maps
     * back to the base class and Doctrine's discriminator loads the concrete
     * subclass.
     *
     * @param class-string $class
     */
    private function resolveConfig(string $class): ?Tokenable
    {
        for ($reflection = new \ReflectionClass($class); false !== $reflection; $reflection = $reflection->getParentClass()) {
            $attributes = $reflection->getAttributes(Tokenable::class);
            if ([] !== $attributes) {
                return $attributes[0]->newInstance();
            }
        }

        return null;
    }

    private function optimusFor(string $class, Tokenable $config): Optimus
    {
        return $this->optimusByClass[$class] ??= new Optimus($config->prime, $config->inverse, $config->random);
    }

    /** @return class-string */
    private function classForPrefix(string $prefix): string
    {
        $this->classByPrefix ??= $this->buildPrefixMap();

        return $this->classByPrefix[$prefix]
            ?? throw new InvalidTokenException(\sprintf('No tokenable entity is registered with prefix "%s".', $prefix));
    }

    /** @return array<string, class-string> */
    private function buildPrefixMap(): array
    {
        $map = [];
        $driver = $this->em->getConfiguration()->getMetadataDriverImpl();
        $classNames = $driver?->getAllClassNames() ?? [];

        foreach ($classNames as $class) {
            if (!class_exists($class)) {
                continue;
            }
            // Only classes that *declare* the attribute own a prefix. Subclasses
            // that merely inherit it (Doctrine inheritance) are intentionally
            // skipped: registering them would map the shared prefix to several
            // classes and make decoding ambiguous. Decoding resolves the prefix
            // to the declaring base; Doctrine's discriminator loads the concrete
            // subclass from there.
            $attributes = (new \ReflectionClass($class))->getAttributes(Tokenable::class);
            if ([] === $attributes) {
                continue;
            }

            /** @var Tokenable $config */
            $config = $attributes[0]->newInstance();
            if (isset($map[$config->prefix])) {
                throw new \LogicException(\sprintf(
                    'Duplicate Tokenable prefix "%s" on %s and %s.',
                    $config->prefix,
                    $map[$config->prefix],
                    $class,
                ));
            }

            $map[$config->prefix] = $class;
            $this->configByClass[$class] = $config;
        }

        return $map;
    }
}
