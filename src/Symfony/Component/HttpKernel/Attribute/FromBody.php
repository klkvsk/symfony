<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Attribute;

/**
 * Controller argument tag forcing ValueResolvers to use the value from request's body.
 *
 * @author Mike Kulakovsky <mike@kulakovs.ky>
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class FromBody extends FromRequestParameter
{
    /**
     * @param string|null    $name                 The name of body parameter. Use "*" to collect all parameters. By default, the name of the argument in the controller will be used.
     * @param int|array|null $filter               the filter for `filter_var()` if int, or for `filter_var_array()` if array
     * @param int|array|null $options              The filter flag mask if int, or options array. If $filter is array, $options accepts only an array with "add_empty" key to be used as 3rd argument for filter_var_array()
     * @param bool           $throwOnFilterFailure whether to throw '400 Bad Request' on filtering failure or not, falling back to default (if any)
     */
    public function __construct(
        ?string $name = null,
        int|array|null $filter = null,
        int|array|null $options = \FILTER_FLAG_EMPTY_STRING_NULL,
        bool $throwOnFilterFailure = true,
    ) {
        parent::__construct('request', $name, $filter, $options, $throwOnFilterFailure);
    }
}
