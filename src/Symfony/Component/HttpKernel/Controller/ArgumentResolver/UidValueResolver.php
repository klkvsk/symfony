<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Controller\ArgumentResolver;

use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\RequestParameterValueResolverTrait;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\AbstractUid;

/**
 * Convert to Uid instance from request parameter value.
 *
 * @author Thomas Calvet
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Mike Kulakovsky <mike@kulakovs.ky>
 */
final class UidValueResolver implements ValueResolverInterface
{
    use RequestParameterValueResolverTrait;

    protected function supports(ArgumentMetadata $argument): bool
    {
        $uidClass = $argument->getType();
        return $uidClass && is_subclass_of($uidClass, AbstractUid::class, true);
    }

    protected function resolveValue(Request $request, ArgumentMetadata $argument, ParameterBag $valueBag): array
    {
        if (!$valueBag->has($argument->getName()) || !\is_string($value = $valueBag->get($argument->getName()))) {
            return [];
        }

        /** @var class-string<AbstractUid> $uidClass */
        $uidClass = $argument->getType();

        try {
            return [$uidClass::fromString($value)];
        } catch (\InvalidArgumentException $e) {
            throw new NotFoundHttpException(\sprintf('The uid for the "%s" parameter is invalid.', $argument->getName()), $e);
        }
    }
}
