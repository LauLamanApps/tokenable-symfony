<?php

declare(strict_types=1);

namespace LauLamanApps\Tokenable\Tests\Resolver;

use Doctrine\ORM\EntityManagerInterface;
use LauLamanApps\Tokenable\Recorder\TokenableRecorder;
use LauLamanApps\Tokenable\Resolver\TokenableValueResolver;
use LauLamanApps\Tokenable\Tests\CreatesTokenizer;
use LauLamanApps\Tokenable\Tests\Fixtures\Inheritance\AbstractMandate;
use LauLamanApps\Tokenable\Tests\Fixtures\Inheritance\BankTransferMandate;
use LauLamanApps\Tokenable\Tests\Fixtures\Inheritance\CreditCardMandate;
use LauLamanApps\Tokenable\Tests\Fixtures\Inheritance\DirectDebitMandate;
use LauLamanApps\Tokenable\Tokenizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Resolving a shared-prefix token against a Doctrine inheritance hierarchy: the
 * token decodes to the abstract base, find() returns the concrete subclass, and
 * the resolver verifies that concrete instance against the requested argument
 * type.
 */
final class TokenableValueResolverInheritanceTest extends TestCase
{
    use CreatesTokenizer;

    private const HIERARCHY = [
        AbstractMandate::class,
        DirectDebitMandate::class,
        CreditCardMandate::class,
        BankTransferMandate::class,
    ];

    public function testResolvesConcreteSubclassForAbstractArgument(): void
    {
        $tokenizer = $this->createTokenizer(self::HIERARCHY);
        $token = $tokenizer->encode(new DirectDebitMandate(42));
        $entity = new DirectDebitMandate(42);

        $em = $this->createMock(EntityManagerInterface::class);
        // Prefix decodes to the base; Doctrine loads the concrete subclass.
        $em->expects(self::once())->method('find')->with(AbstractMandate::class, 42)->willReturn($entity);

        $argument = new ArgumentMetadata('mandate', AbstractMandate::class, false, false, null);

        self::assertSame([$entity], $this->resolve($this->resolver($tokenizer, $em), $this->request($token), $argument));
    }

    public function testResolvesWhenArgumentIsTheConcreteSubclass(): void
    {
        $tokenizer = $this->createTokenizer(self::HIERARCHY);
        $token = $tokenizer->encode(new DirectDebitMandate(42));
        $entity = new DirectDebitMandate(42);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('find')->with(AbstractMandate::class, 42)->willReturn($entity);

        $argument = new ArgumentMetadata('mandate', DirectDebitMandate::class, false, false, null);

        self::assertSame([$entity], $this->resolve($this->resolver($tokenizer, $em), $this->request($token), $argument));
    }

    public function testRejectsWhenLoadedSubclassDoesNotMatchArgumentType(): void
    {
        $tokenizer = $this->createTokenizer(self::HIERARCHY);
        $token = $tokenizer->encode(new DirectDebitMandate(42));
        // The token resolves to a sibling subclass, not the requested one.
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn(new CreditCardMandate(42));

        $argument = new ArgumentMetadata('mandate', DirectDebitMandate::class, false, false, null);

        $this->expectException(NotFoundHttpException::class);

        $this->resolve($this->resolver($tokenizer, $em), $this->request($token), $argument);
    }

    private function request(string $token): Request
    {
        $request = new Request();
        $request->attributes->set('mandate', $token);

        return $request;
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
