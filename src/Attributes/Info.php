<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Attributes;

use OpenApi\Annotations as OA;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Info extends OA\Info
{

    // allow named attributes
    public function __construct(string $version = UNDEFINED, string $title = UNDEFINED)
    {
        parent::__construct(['version' => $version, 'title' => $title]);
    }

    // trait?
    public function __toString(): string
    {
        return '['.$this::class . ': title='.$this->title.', version='.$this->version.']';
    }
}
