<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Fixtures\PHP;

class DocblockAndTypehintTypes
{
    /**
     * The first name.
     *
     * @var string
     */
    public string $firstName;

    /**
     * Optional second name.
     *
     * @var ?string
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
     * A class type property.
     *
     * @var DocblockAndTypehintTypes
     */
    public DocblockAndTypehintTypes $class;

    /**
     * A nullable class type property.
     *
     * @var null|DocblockAndTypehintTypes
     */
    public ?DocblockAndTypehintTypes $nullableClass;

    /**
     * A namespaced global class type property.
     *
     * @var \DateTime
     */
    public \DateTime $namespacedGlobalClass;

    /**
     * A nullable namespaced global class type property.
     *
     * @var \DateTime|null
     */
    public \DateTime|null $nullableNamespacedGlobalClass;

    /**
     * Also a namespaced global class type property.
     *
     * @var null|\DateTime
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
}
