<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Fixtures\Generics;

enum Status: string
{
    case Active = 'active';
    case Closed = 'closed';
}
