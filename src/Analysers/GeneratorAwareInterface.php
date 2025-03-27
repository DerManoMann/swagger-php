<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Analysers;

use OpenApi\Generator;

interface GeneratorAwareInterface
{
    public function setGenerator(Generator $generator);
}
