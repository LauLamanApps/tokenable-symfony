<?php

declare(strict_types=1);

namespace LauLamanApps\Tokenable\Resolver;

use Doctrine\ORM\EntityManagerInterface;
use LauLamanApps\Tokenable\Exception\InvalidTokenException;
use LauLamanApps\Tokenable\Recorder\TokenableRecorder;
use LauLamanApps\Tokenable\Tokenizer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class TokenableValueResolver implements ValueResolverInterface
{
    public function __construct(
        private readonly Tokenizer $tokenizer,
        private readonly EntityManagerInterface $em,
        private readonly TokenableRecorder $recorder,
    ) {
    }

    /** @return iterable<int, object|null> */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $expectedClass = $argument->getType();
        if (null === $expectedClass || !class_exists($expectedClass) || !$this->tokenizer->isTokenable($expectedClass)) {
            return [];
        }

        $token = $this->findToken($request, $argument, $expectedClass);
        if (null === $token) {
            return [];
        }

        try {
            [$resolvedClass, $id] = $this->tokenizer->decode($token);
        } catch (InvalidTokenException) {
            $this->recorder->recordResolution($token, $expectedClass, null, null, 'invalid_token');
            throw new NotFoundHttpException();
        }

        if (!is_a($resolvedClass, $expectedClass, true)) {
            $this->recorder->recordResolution($token, $expectedClass, $resolvedClass, $id, 'class_mismatch');
            throw new NotFoundHttpException();
        }

        $entity = $this->em->find($resolvedClass, $id);
        if (null === $entity) {
            $this->recorder->recordResolution($token, $expectedClass, $resolvedClass, $id, 'entity_not_found');
            if ($argument->isNullable()) {
                return [null];
            }
            throw new NotFoundHttpException();
        }

        $this->recorder->recordResolution($token, $expectedClass, $resolvedClass, $id, 'success');

        return [$entity];
    }

    private function findToken(Request $request, ArgumentMetadata $argument, string $expectedClass): ?string
    {
        $expectedPrefix = $this->tokenizer->configFor($expectedClass)->prefix.$this->tokenizer->getSeparator();

        $byArgName = $request->attributes->get($argument->getName());
        if (is_string($byArgName) && str_starts_with($byArgName, $expectedPrefix)) {
            return $byArgName;
        }

        foreach ($request->attributes->all() as $key => $value) {
            if (is_string($key) && str_starts_with($key, '_')) {
                continue;
            }
            if (is_string($value) && str_starts_with($value, $expectedPrefix)) {
                return $value;
            }
        }

        return null;
    }
}
