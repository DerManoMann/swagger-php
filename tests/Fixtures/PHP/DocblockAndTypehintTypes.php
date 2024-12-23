<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Fixtures\PHP;

class DocblockAndTypehintTypes
{
    public $nothing;

    /**
     * @var string
     */
    public string $firstName;

    /**
     * @var string|null
     */
    public ?string $secondName;

    /**
     * @var string[]
     */
    public array $names;

    /**
     * @var array<string>
     */
    public array $moreNames;

    /**
     * @var ?array<string>
     */
    public ?array $optionalNames;

    /**
     * @var array<string>|null
     */
    public array|null $moreOptionalNames;

    /**
     * @var DocblockAndTypehintTypes
     */
    public DocblockAndTypehintTypes $class;

    /**
     * @var DocblockAndTypehintTypes|null
     */
    public ?DocblockAndTypehintTypes $nullableClass;

    /**
     * @var \DateTime
     */
    public \DateTime $namespacedGlobalClass;

    /**
     * @var \DateTime|null
     */
    public \DateTime|null $nullableNamespacedGlobalClass;

    /**
     * @var \DateTime|null
     */
    public null|\DateTime $alsoNullableNamespacedGlobalClass;

    /**
     * @var int<min,10> The maximum range integer
     */
    public int $maxRangeInt;

    /**
     * @var positive-int The positive integer
     */
    public int $positiveInt;

    /**
     * @var array{foo: boolean}
     */
    public array $arrayShape;

    /**
     * @param string $promotedString
     * @param bool $bool
     */
    public function __construct(protected string $promotedString, bool $bool = true)
    {
    }

    /**
     * @return string
     */
    public function getString(): string
    {
        return 'string';
    }
}
