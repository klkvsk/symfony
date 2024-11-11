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
use Symfony\Component\HttpKernel\Attribute\FromRoute;
use Symfony\Component\HttpKernel\Attribute\FromRequestParameter;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * A common trait for ValueResolvers that resolve value from request parameters.
 * Uses FromRequestParameter attribute to determine which request parameter to use.
 * Supports filtering and validation via `filter_var`.
 *
 * Target ValueResolver must implement `resolveValue` method and use `$valueBag->get($argument->getName())` to get the value.
 * Target ValueResolver should NOT have a separate logic for variadic arguments. This trait handles it.
 *
 * @see \Symfony\Component\HttpKernel\Attribute\FromRoute
 * @see \Symfony\Component\HttpKernel\Attribute\FromQuery
 * @see \Symfony\Component\HttpKernel\Attribute\FromBody
 * @see \Symfony\Component\HttpKernel\Attribute\FromHeader
 * @see \Symfony\Component\HttpKernel\Attribute\FromFile
 *
 * @author Mike Kulakovsky <mike@kulakovs.ky>
 */
trait RequestParameterValueResolverTrait
{
    public function resolve(Request $request, ArgumentMetadata $argument): array
    {
        return $this->resolveFromRequestParameters($request, $argument);
    }

    protected function supports(ArgumentMetadata $argument): bool
    {
        return true;
    }

    final protected function resolveFromRequestParameters(Request $request, ArgumentMetadata $argument): array
    {
        if (!$this->supports($argument)) {
            return [];
        }

        /** @var FromRequestParameter[] $attributes */
        $attributes = $argument->getAttributesOfType(FromRequestParameter::class, $argument::IS_INSTANCEOF);

        $attribute = match (count($attributes)) {
            0       => new FromRoute(), // Fall back to route attributes by default to keep BC.
            1       => $attributes[0],
            default => throw new \LogicException('Multiple FromRequestParameter attributes are not allowed on a single argument.'),
        };

        [ $originalBag, $isOriginalBagCopied ] = match ($attribute->bag) {
            'attributes' => [ new ParameterBag($request->attributes->all()), true ],
            'request'    => [ new ParameterBag($request->getPayload()->all()), true ],
            'query'      => [ new ParameterBag($request->query->all()), true ],
            'headers'    => [ $request->headers, false ],
            'files'      => [ $request->files, false ],
            default      => throw new \InvalidArgumentException(sprintf('Unknown bag "%s" for value consumer.', $attribute->bag)),
        };

        $requestParameterName = $attribute->name ?? $argument->getName();

        if ($requestParameterName === '*') {
            // Complete bag contents, useful to get full query or payload as an array argument
            $values = [ $originalBag->all() ];
        } else if ($originalBag->has($requestParameterName)) {
            $value = $originalBag->get($requestParameterName);
            // Expand variadic only if a list of values provided in request data. Otherwise, treated as a single element.
            if ($argument->isVariadic() && is_array($value) && array_is_list($value)) {
                $values = [ ...$value ];
            } else {
                $values = [ $value ];
            }
        } else {
            // No values bound to this argument, but we still need to call resolve
            $values = [];
        }

        $filter = $attribute->filter ?? match ($argument->getType()) {
            'int'             => \FILTER_VALIDATE_INT,
            'float'           => \FILTER_VALIDATE_FLOAT,
            'bool'            => \FILTER_VALIDATE_BOOL,
            'string', 'array' => \FILTER_DEFAULT,
            default           => null,
        };

        if ($filter !== null && !empty($values)) {
            $options = [ 'flags' => 0 ];
            if (is_int($attribute->options)) {
                $options['flags'] = $attribute->options;
            } else if (is_array($attribute->options)) {
                $options['options'] = $attribute->options['options'] ?? $attribute->options;
                $options['flags'] = $attribute->options['flags'] ?? 0;
            }
            if ($filter === \FILTER_VALIDATE_BOOL) {
                $options['flags'] |= FILTER_NULL_ON_FAILURE;
            }
            if ($argument->getType() === 'array') {
                $options['flags'] |= FILTER_FORCE_ARRAY | FILTER_REQUIRE_ARRAY;
            }

            $isNullOnEmptyString = $options['flags'] & FILTER_FLAG_EMPTY_STRING_NULL;
            $isNullOnFailure = $options['flags'] & FILTER_NULL_ON_FAILURE;

            $failedValues = [];

            foreach ($values as $i => $value) {
                if (is_object($value)) {
                    continue;
                }

                if ($value === null) {
                    unset($values[$i]);
                    continue;
                }

                // Manually apply FILTER_FLAG_EMPTY_STRING_NULL to ignore empty string value for numeric arguments
                if ($value === '' && $isNullOnEmptyString && ($filter === \FILTER_VALIDATE_INT || $filter === \FILTER_VALIDATE_FLOAT)) {
                    unset($values[$i]);
                    continue;
                }

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
                    $failedValues[] = $value;
                    unset($values[$i]);
                } else {
                    $values[$i] = $filtered;
                }
            }

            if ($failedValues && $attribute->throwOnFilterFailure) {
                throw new BadRequestHttpException(sprintf('Parameter "%s" is invalid.', $requestParameterName));
            }
        }

        $baseParametersBag = $isOriginalBagCopied ? $originalBag : new ParameterBag();
        $parametersBags = [];
        if ($values) {
            // For variadic arguments, multiple bags are created, each with a single value
            foreach ($values as $value) {
                $bag = clone $baseParametersBag;
                $bag->set($argument->getName(), $value);
                $parametersBags[] = $bag;
            }
        } else {
            $bag = clone $baseParametersBag;
            // Ensure we do not pass unfiltered value
            $bag->remove($argument->getName());
            $parametersBags = [ $bag ];
        }

        $resolvedValues = [];
        foreach ($parametersBags as $bag) {
            $resolved = $this->resolveValue($request, $argument, $bag);
            if (count($resolved) === 1) {
                $resolvedValues[] = $resolved[0];
            } else if (count($resolved) > 1) {
                throw new \UnexpectedValueException('Method resolveValue() should return an array with a single item containing resolved value or an empty array if nothing was resolved.');
            }
        }

        return $resolvedValues;
    }

    abstract protected function resolveValue(Request $request, ArgumentMetadata $argument, ParameterBag $valueBag): array;

}
