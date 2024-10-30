<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Controller;

use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\ValueBagResolver;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

trait ValueBagResolverTrait
{
    private function resolveValueBag(Request $request, ArgumentMetadata $argument): ParameterBag
    {
        /** @var ValueBagResolver[] $valueResolverBagAttributes */
        $valueResolverBagAttributes = $argument->getAttributesOfType(ValueBagResolver::class, $argument::IS_INSTANCEOF);
        if (!$valueResolverBagAttributes) {
            // Fall back to attributes by default to keep BC.
            return $request->attributes;
        }

        foreach ($valueResolverBagAttributes as $attribute) {
            [ $requestBag, $isReturned ] = match ($attribute->bag) {
                'attributes' => [ new ParameterBag($request->attributes->all()), true ],
                'request'    => [ new ParameterBag($request->getPayload()->all()), true ],
                'query'      => [ new ParameterBag($request->query->all()), true ],
                'headers'    => [ $request->headers, false ],
                'files'      => [ $request->files, false ],
                default      => throw new \InvalidArgumentException(sprintf('Unknown bag "%s" for value consumer.', $attribute->bag)),
            };

            $requestParameterName = $attribute->name ?? $argument->getName();
            if ($requestParameterName === '*') {
                $value = $requestBag->all();
            } else if ($requestBag->has($requestParameterName)) {
                $value = $requestBag->get($requestParameterName);
            } else {
                continue;
            }

            $filter = $attribute->filter ?? match ($argument->getType()) {
                'int'   => \FILTER_VALIDATE_INT,
                'float' => \FILTER_VALIDATE_FLOAT,
                'bool'  => \FILTER_VALIDATE_BOOL,
                default => \FILTER_DEFAULT,
            };

            $options = [ 'flags' => 0 ];
            if (is_int($attribute->options)) {
                $options['flags'] = $attribute->options;
            } else if (is_array($attribute->options)) {
                $options['options'] = $attribute->options['options'] ?? $attribute->options;
                $options['flags'] = $attribute->options['flags'] ?? 0;
            }
            if ($argument->getType() === 'bool') {
                $options['flags'] |= FILTER_NULL_ON_FAILURE;
            }
            if ($argument->getType() === 'array' || $argument->isVariadic() || $requestParameterName === '*') {
                $options['flags'] |= FILTER_REQUIRE_ARRAY | FILTER_FORCE_ARRAY;
            }

            $isNullOnFailure = $options['flags'] & FILTER_NULL_ON_FAILURE;

            if ($value !== null) {
                if (is_array($filter)) {
                    if (is_array($value)) {
                        $filtered = filter_var_array($value, $filter, $options['add_empty'] ?? true);
                    } else {
                        $filtered = $isNullOnFailure ? null : false;
                    }
                } else {
                    $filtered = filter_var($value, $filter, $options);
                }

                if (($filtered === false && !$isNullOnFailure) || ($filtered === null && $isNullOnFailure)) {
                    continue;
                }

                $value = $filtered;
            }

            // For attributes, query, request bags, return other values too.
            // This way, some sophisticated ValueResolvers like EntityValueResolver can receive them.
            $valueBag = $isReturned ? $requestBag : new ParameterBag();
            $valueBag->set($argument->getName(), $value);

            return $valueBag;
        }

        return new ParameterBag();
    }

}
