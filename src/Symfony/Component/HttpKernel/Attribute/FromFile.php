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
 * Controller argument tag forcing ValueResolvers to use the value from request's files.
 *
 * @author Mike Kulakovsky <mike@kulakovs.ky>
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class FromFile extends FromRequestParameter
{
    /**
     * @param string|null $name The name of route attribute. Use "*" to collect all parameters. By default, the name of the argument in the controller will be used.
     */
    public function __construct(
        ?string $name = null,
    ) {
        parent::__construct('files', $name);
    }
}
