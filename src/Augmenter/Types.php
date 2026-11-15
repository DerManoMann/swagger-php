<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Augmenter;

use OpenApi\Augmenter\Types\CopyRule;
use OpenApi\Augmenter\Types\GenericCopies;
use OpenApi\Augmenter\Types\Refusal;
use OpenApi\ComponentNamer;
use OpenApi\Contracts\ComponentNamerAwareInterface;
use OpenApi\Contracts\ComponentNamerInterface;
use OpenApi\Contracts\TypeResolverAwareInterface;
use OpenApi\HybridBridge;
use OpenApi\Spec as OA;
use OpenApi\Specification;
use OpenApi\Specification\ComponentIndex;
use OpenApi\Specification\ComponentKey;
use OpenApi\Type\Generics\TypeArguments;
use OpenApi\Type\SchemaType;
use OpenApi\Type\TypeMapper;
use OpenApi\Type\TypeResolver;
use OpenApi\Undefined;
use OpenApi\Utils\PipeInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Infers schema type, format, nullable, items, etc. from PHP type declarations and docblocks.
 *
 * Fills the schema of each property, parameter and request body that
 * `Specification\Walker::eachTypedAttribute()` lists, from its reflector's type information.
 *
 * A generic class used with type arguments, such as `Envelope<Customer>`, references a schema made
 * for those arguments: a copy of the class's own schema, whose properties read each template as
 * its argument. `Types\GenericCopies` makes the copy and says when a use keeps the plain schema.
 *
 * @implements PipeInterface<Specification>
 */
class Types implements PipeInterface, LoggerAwareInterface, TypeResolverAwareInterface, ComponentNamerAwareInterface
{
    protected ?LoggerInterface $logger = null;

    /** the components a class type is looked up in, by its plain key, in the bucket its use means */
    protected ?ComponentIndex $index = null;

    /** the member whose type is being applied, for the warning when a class in it has no schema */
    protected ?\Reflector $subject = null;

    /** asked for the schema of a generic class used with type arguments */
    protected ?GenericCopies $copies = null;

    /** @var array<string, true> the messages logged in this run, so typing a copy does not log its class's again */
    protected array $reported = [];

    /** @var \SplObjectStorage<OA\Schema, null> schemas whose `type` this augmenter inferred, not the author */
    protected \SplObjectStorage $inferredTypes;

    public function __construct(
        protected TypeResolver $typeResolver = new TypeResolver(),
        protected ComponentNamerInterface $namer = new ComponentNamer\ShortName(),
    ) {
    }

    public function __invoke(mixed $payload): mixed
    {
        $this->index = $payload->buildComponentIndex();
        $this->inferredTypes = new \SplObjectStorage();
        $this->reported = [];

        // before anything is typed, so a copy starts from the schema as its class declares it
        $templateReader = $this->typeResolver->getTemplateReader();
        $this->copies = new GenericCopies($payload, $this->index, $templateReader, new CopyRule($templateReader, $this->namer));

        // types the document as written; a use with type arguments asks for its copy on the way
        $this->augment($payload);

        // The copies made so far have a name and a key, but their properties are still as the class
        // declares them. Typing them is the same work as for any schema, so each round puts the new
        // copies in a specification of their own, only for the walker to visit them and nothing
        // else. Typing a copy can ask for further copies, as `Pair<A, B>` does with a property
        // typed `Box<TSecond>`, and those are typed in the next round, until none is left. That
        // ends: `CopyRule` refuses arguments nested too deep, so a class using itself with growing
        // arguments runs out of copies.
        $made = [];
        while (($pending = $this->copies->takePending()) !== []) {
            $this->augment((new Specification())->add(...$pending));
            $made = [...$made, ...$pending];
        }

        // every copy made in any round joins the document, typed
        $payload->add(...$made);

        return null;
    }

    public function setNamer(ComponentNamerInterface $namer): static
    {
        $this->namer = $namer;

        return $this;
    }

    public function setTypeResolver(TypeResolver $typeResolver): static
    {
        $this->typeResolver = $typeResolver;
        if ($this->logger instanceof LoggerInterface) {
            $this->typeResolver->setLogger($this->logger);
        }

        return $this;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
        $this->typeResolver->setLogger($logger);
    }

    public function group(): string|\BackedEnum
    {
        return Group::Resolve;
    }

    /**
     * Types every schema and typed attribute in the specification.
     */
    protected function augment(Specification $specification): void
    {
        $walker = $specification->getWalker();
        $walker->eachSchema($this->inferSchemaType(...));
        $walker->eachTypedAttribute(function (OA\Property|OA\Parameter|OA\RequestBody $attribute, ?OA\Schema $schema): void {
            match (true) {
                // a property is read in the component it belongs to
                $attribute instanceof OA\Property => $this->augmentProperty($attribute, $schema?->getComponentKey()),
                $attribute instanceof OA\Parameter => $this->augmentParameter($attribute),
                $attribute instanceof OA\RequestBody => $this->augmentRequestBody($attribute),
            };
        });
    }

    protected function inferSchemaType(OA\Schema $schema): void
    {
        if ($schema->type !== null) {
            return;
        }

        if ($schema->items instanceof OA\Schema) {
            $schema->type = 'array';
            $this->inferredTypes->offsetSet($schema);
        } elseif ($schema->properties || $schema->patternProperties) {
            $schema->type = 'object';
            $this->inferredTypes->offsetSet($schema);
        }
    }

    protected function augmentRequestBody(OA\RequestBody $requestBody): void
    {
        $reflector = $requestBody->getReflector();
        if ($reflector instanceof \ReflectionParameter) {
            $resolved = $this->typeResolver->resolve($reflector);

            if ($resolved instanceof SchemaType) {
                if ($resolved->type !== null && $resolved->isRef()) {
                    $this->refRequestBody($requestBody, $resolved->type, $reflector);
                }

                if ($resolved->nullable !== null && $requestBody->required === null) {
                    $requestBody->required = !$resolved->nullable;
                }
            }
        }
    }

    protected function augmentProperty(OA\Property $property, ?ComponentKey $component = null): void
    {
        $reflector = $property->getReflector();
        if ($reflector instanceof \ReflectionMethod) {
            $this->augmentMethodProperty($property, $reflector, $component);

            return;
        }

        if (!$reflector instanceof \ReflectionProperty
            && !$reflector instanceof \ReflectionParameter
            && !$reflector instanceof \ReflectionClassConstant
        ) {
            return;
        }

        $property->property ??= $reflector->getName();

        if ($reflector instanceof \ReflectionClassConstant) {
            $this->augmentConstantProperty($property, $reflector);

            return;
        }

        if ($property->schema instanceof OA\Schema && $property->schema->ref !== null) {
            return;
        }

        $resolved = $this->typeResolver->resolveIn($reflector, $component);
        if (!$resolved instanceof SchemaType) {
            return;
        }

        $this->subject = $reflector;

        if (!$property->schema instanceof OA\Schema) {
            $property->schema = $this->applySchemaType($resolved);
        } else {
            $this->mergeIntoSchema($property->schema, $resolved);
        }
    }

    protected function augmentConstantProperty(OA\Property $property, \ReflectionClassConstant $reflector): void
    {
        $value = $reflector->getValue();

        $schema = $property->schema ?? new OA\Schema();
        // Can't use ??= here — const defaults to Undefined::UNDEFINED, not null
        if (Undefined::isDefault($schema->const)) {
            $schema->const = $value;
        }
        // an array constant stays untyped, as it always has
        $schema->type ??= is_array($value) ? null : TypeMapper::valueType($value);
        $property->schema = $schema;
    }

    protected function augmentMethodProperty(OA\Property $property, \ReflectionMethod $method, ?ComponentKey $component = null): void
    {
        if ($property->schema instanceof OA\Schema && $property->schema->ref !== null) {
            return;
        }

        $resolved = $this->typeResolver->resolveIn($method, $component);
        if (!$resolved instanceof SchemaType) {
            return;
        }

        $this->subject = $method;

        if (!$property->schema instanceof OA\Schema) {
            $property->schema = $this->applySchemaType($resolved);
        } else {
            $this->mergeIntoSchema($property->schema, $resolved);
        }
    }

    protected function augmentParameter(OA\Parameter $parameter): void
    {
        $reflector = $parameter->getReflector();
        if (!$reflector instanceof \ReflectionParameter) {
            return;
        }

        $parameter->name ??= $reflector->getName();

        if ($parameter->schema instanceof OA\Schema && $parameter->schema->ref !== null) {
            return;
        }

        $resolved = $this->typeResolver->resolve($reflector);
        if (!$resolved instanceof SchemaType) {
            return;
        }

        $isNullable = $resolved->nullable === true;

        // PHP nullable type on a parameter means "can be omitted" (required: false),
        // not "accepts null when present" — suppress nullable on the schema
        if ($parameter->required === true || $isNullable) {
            $resolved->nullable = null;
        }

        $this->subject = $reflector;

        if (!$parameter->schema instanceof OA\Schema) {
            $parameter->schema = $this->applySchemaType($resolved);
        } else {
            $this->mergeIntoSchema($parameter->schema, $resolved);
        }

        // a default means a value exists when the parameter is left out, and one converted from classic
        // keeps classic's rule. A path parameter has `required` already, from `PathParameters`
        $hasDefault = $reflector->isDefaultValueAvailable()
            && $parameter->getMeta(HybridBridge::class) !== true;

        $parameter->required ??= !$isNullable && !$hasDefault;
    }

    /**
     * Points a request body at the request body component its parameter's class names. One left
     * with neither a ref nor content is reported, since it describes nothing.
     */
    protected function refRequestBody(OA\RequestBody $requestBody, string $class, \ReflectionParameter $reflector): void
    {
        $count = $this->countComponents($class, 'requestBodies');
        if ($count === 1) {
            $requestBody->ref = $class;

            return;
        }

        if ($requestBody->ref !== null || $requestBody->content !== null) {
            return;
        }

        $subject = $this->typeResolver->describe($reflector);
        $this->logger?->error(match (true) {
            $count > 1 => "Request body \"{$class}\" in {$subject} matches several request bodies, left empty; reference one by name",
            $this->countComponents($class) === 1 => "Request body \"{$class}\" in {$subject} matches no request body, left empty; its schema belongs in the request body's content",
            default => "Request body \"{$class}\" in {$subject} matches no request body, left empty",
        });
    }

    /**
     * How many components a class names by its plain key in a bucket. The class references one only
     * when it is the only one.
     */
    protected function countComponents(string $class, string $bucket = 'schemas'): int
    {
        return count($this->index?->findAllByKey(new ComponentKey(ltrim($class, '\\')), $bucket) ?? []);
    }

    /**
     * A class as the ref to its schema: the one made for the type arguments it is used with where
     * there is one, otherwise the class's plain one.
     *
     * @param bool $dropped whether nothing will reference it, as a member of a dropped union
     */
    protected function refTo(string $class, ?TypeArguments $typeArguments, string $subject, bool $dropped = false): string|OA\Schema\Ref
    {
        if (!$typeArguments instanceof TypeArguments) {
            return $class;
        }

        // The first use of these arguments makes the copy, every later one gets the same answer. A
        // member of a dropped union is referenced by nothing, so it is only checked.
        $key = new ComponentKey(ltrim($class, '\\'), typeArguments: $typeArguments);
        $answer = $dropped ? $this->copies?->check($key) : $this->copies?->for($key);

        // reported at each use that keeps the plain schema, not only at the first
        if ($answer instanceof Refusal) {
            // the use as it is written, for the message: `App\Box<App\Customer>`
            $this->report(LogLevel::WARNING, $answer->message($key->class . '<' . $key->typeArguments . '>', $subject));
        }

        // a ref with the arguments, which `Refs` resolves to the copy's name; without a copy the class alone
        return $answer instanceof OA\Schema ? new OA\Schema\Ref(ref: $key->class, of: $typeArguments) : $class;
    }

    /**
     * Logs a message once in a run. A copy's properties are those of its class, typed again, and
     * what is wrong in them was reported for the class.
     */
    protected function report(string $level, ?string $message): void
    {
        if ($message === null || isset($this->reported[$message])) {
            return;
        }

        $this->reported[$message] = true;
        $this->logger?->log($level, $message);
    }

    protected function mergeIntoSchema(OA\Schema $schema, SchemaType $schemaType): void
    {
        if ($schema->type === null && $schema->oneOf === null && $schema->allOf === null && $schema->anyOf === null) {
            $this->applySchemaType($schemaType, $schema);
        }

        // a type the author wrote out is the whole type, so the PHP type's `null` is not added to it
        if (!$this->hasExplicitType($schema)) {
            $schema->nullable ??= $schemaType->nullable;
        }
    }

    /**
     * Whether the author gave the schema its type, rather than a shortcut or this augmenter.
     *
     * `Schema\Items` is always an array, whatever its arguments say, and `inferSchemaType()` types
     * a schema from its `items` or `properties`.
     */
    protected function hasExplicitType(OA\Schema $schema): bool
    {
        if ($schema->oneOf !== null || $schema->allOf !== null || $schema->anyOf !== null) {
            return true;
        }

        return $schema->type !== null
            && !$schema instanceof OA\Schema\Items
            && !$this->inferredTypes->offsetExists($schema);
    }

    /**
     * Applies a type to a schema, a new one unless one is given, and returns the schema.
     *
     * @param bool $dropped whether the type is read only to report what is wrong in it, as a member
     *                      of a dropped union: a use with type arguments in it makes no copy
     */
    protected function applySchemaType(SchemaType $schemaType, OA\Schema $schema = new OA\Schema(), bool $dropped = false): OA\Schema
    {
        // the members of the type, read the way the type is
        $member = fn (SchemaType $type): OA\Schema => $this->applySchemaType($type, dropped: $dropped);

        if ($schemaType->nullable !== null && $schema->nullable === null) {
            $schema->nullable = $schemaType->nullable;
        }

        if ($schemaType->type !== null) {
            if ($schemaType->isRef()) {
                // a class is referenced only when it is a component, and never in place of an explicit enum
                if ($schema->enum === null) {
                    $count = $this->countComponents($schemaType->type);
                    $subject = $this->subject instanceof \Reflector ? $this->typeResolver->describe($this->subject) : 'a schema';
                    if ($count === 1) {
                        $schema->ref = $this->refTo($schemaType->type, $schemaType->typeArguments, $subject, $dropped);
                    } else {
                        $this->report(LogLevel::ERROR, $count === 0
                            ? "Type \"{$schemaType->type}\" in {$subject} matches no schema, omitted"
                            : "Type \"{$schemaType->type}\" in {$subject} matches several schemas, omitted; reference one by name");
                    }
                }
            } else {
                $schema->type = $schemaType->type;
            }
        }

        $schema->format ??= $schemaType->format;

        $schema->minimum ??= $schemaType->minimum;

        $schema->maximum ??= $schemaType->maximum;

        if ($schemaType->not !== null && !$schema->not instanceof OA\Schema) {
            $schema->not = new OA\Schema(const: $schemaType->not['const']);
        }

        if ($schemaType->items instanceof SchemaType) {
            $schema->type = 'array';
            $schema->items ??= $member($schemaType->items);
        }

        if ($schemaType->additionalProperties instanceof SchemaType) {
            $schema->additionalProperties ??= $member($schemaType->additionalProperties);
        } elseif ($schemaType->additionalProperties === true) {
            $schema->additionalProperties ??= true;
        }

        if ($schemaType->oneOf !== null) {
            // a member typed with a class that is no component matches anything, and so does the union
            $unknown = array_filter(
                $schemaType->oneOf,
                fn (SchemaType $member): bool => $member->isRef() && is_string($member->type) && $this->countComponents($member->type) !== 1,
            );
            if ($unknown === []) {
                $schema->oneOf = array_map($member, $schemaType->oneOf);
            } else {
                // A dropped union references nothing, but every member is still read, to report
                // what is wrong in it, nested members too. A use with type arguments in it makes no
                // copy, which nothing would reference.
                foreach ($schemaType->oneOf as $type) {
                    $this->applySchemaType($type, dropped: true);
                }
            }
        }

        if ($schemaType->allOf !== null) {
            $schema->allOf = array_map($member, $schemaType->allOf);
        }

        if ($schemaType->properties !== null) {
            $schema->type = 'object';
            $schema->properties = [];
            foreach ($schemaType->properties as $name => $propType) {
                $property = new OA\Property(property: $name, schema: $member($propType));
                $schema->properties[] = $property;
            }
            if ($schemaType->required !== null) {
                $schema->required = $schemaType->required;
            }
        }

        return $schema;
    }
}
