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
 * Defines which value bag from request be used for a given parameter.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::IS_REPEATABLE)]
class ValueBagResolver
{
    /**
     * @param "attributes"|"request"|"query"|"body"|"headers" $bag The bag to consume the value from
     * @param string|null $name The name of the parameter in bag. Use "*" to collect all parameters. By default, the name of the argument in the controller will be used.
     * @param int|array|null $filter The filter for `filter_var()` if int, or for `filter_var_array()` if array.
     * @param int|array|null $options The filter flag mask if int, or options array. If $filter is array, $options accepts only an array with "add_empty" key to be used as 3rd argument for filter_var_array()
     */
    public function __construct(
        public string         $bag,
        public string|null    $name = null,
        public int|array|null $filter = null,
        public int|array|null $options = null,
    )
    {
    }
}
