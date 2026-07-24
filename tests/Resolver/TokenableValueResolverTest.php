<?php

declare(strict_types=1);

namespace LauLamanApps\Tokenable\Tests\Resolver;

use Doctrine\ORM\EntityManagerInterface;
use LauLamanApps\Tokenable\Recorder\TokenableRecorder;
use LauLamanApps\Tokenable\Resolver\TokenableValueResolver;
use LauLamanApps\Tokenable\Tests\CreatesTokenizer;
use LauLamanApps\Tokenable\Tests\Fixtures\FooEntity;
use LauLamanApps\Tokenable\Tests\Fixtures\PlainEntity;
use LauLamanApps\Tokenable\Tokenizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class TokenableValueResolverTest extends TestCase
{
    use CreatesTokenizer;

    public function testIgnoresNonTokenableArgument(): void
    {
        $resolver = $this->resolver($this->createTokenizer(), $this->createMock(EntityManagerInterface::class));
        $argument = new ArgumentMetadata('plain', PlainEntity::class, false, false, null);

        self::assertSame([], $this->resolve($resolver, new Request(), $argument));
    }

    public function testReturnsNothingWhenNoTokenPresent(): void
    {
        $resolver = $this->resolver($this->createTokenizer(), $this->createMock(EntityManagerInterface::class));
        $argument = new ArgumentMetadata('foo', FooEntity::class, false, false, null);

        self::assertSame([], $this->resolve($resolver, new Request(), $argument));
    }

    public function testResolvesEntityFromToken(): void
    {
        $tokenizer = $this->createTokenizer();
        $token = $tokenizer->encode(FooEntity::class, 42);
        $entity = new FooEntity(42);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('find')->with(FooEntity::class, 42)->willReturn($entity);

        $request = new Request();
        $request->attributes->set('foo', $token);
        $argument = new ArgumentMetadata('foo', FooEntity::class, false, false, null);

        self::assertSame([$entity], $this->resolve($this->resolver($tokenizer, $em), $request, $argument));
    }

    public function testMissingEntityYieldsNullForNullableArgument(): void
    {
        $tokenizer = $this->createTokenizer();
        $token = $tokenizer->encode(FooEntity::class, 42);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn(null);

        $request = new Request();
        $request->attributes->set('foo', $token);
        $argument = new ArgumentMetadata('foo', FooEntity::class, false, false, null, isNullable: true);

        self::assertSame([null], $this->resolve($this->resolver($tokenizer, $em), $request, $argument));
    }

    public function testMissingEntityThrowsForNonNullableArgument(): void
    {
        $tokenizer = $this->createTokenizer();
        $token = $tokenizer->encode(FooEntity::class, 42);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn(null);

        $request = new Request();
        $request->attributes->set('foo', $token);
        $argument = new ArgumentMetadata('foo', FooEntity::class, false, false, null);

        $this->expectException(NotFoundHttpException::class);

        $this->resolve($this->resolver($tokenizer, $em), $request, $argument);
    }

    private function resolver(Tokenizer $tokenizer, EntityManagerInterface $em): TokenableValueResolver
    {
        return new TokenableValueResolver($tokenizer, $em, new TokenableRecorder(enabled: false));
    }

    /** @return array<int, mixed> */
    private function resolve(ValueResolverInterface $resolver, Request $request, ArgumentMetadata $argument): array
    {
        return [...$resolver->resolve($request, $argument)];
    }
}
