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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueBagResolverTrait;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * Yields a non-variadic argument's value from the request attributes.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
final class RequestAttributeValueResolver implements ValueResolverInterface
{
    use ValueBagResolverTrait;

    public function resolve(Request $request, ArgumentMetadata $argument): array
    {
        $valueBag = $this->resolveValueBag($request, $argument);

        return !$argument->isVariadic() && $valueBag->has($argument->getName()) ? [ $valueBag->get($argument->getName()) ] : [];
    }
}
