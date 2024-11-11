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

/**
 * Provides a value for controller argument of types: string, int, float, bool, array.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 * @author Mike Kulakovsky <mike@kulakovs.ky>
 */
final class RequestAttributeValueResolver implements ValueResolverInterface
{
    use RequestParameterValueResolverTrait;

    protected function resolveValue(Request $request, ArgumentMetadata $argument, ParameterBag $valueBag): array
    {
        return $valueBag->has($argument->getName()) ? [ $valueBag->get($argument->getName()) ] : [];
    }
}
