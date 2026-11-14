<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Augmenter\Types;

use OpenApi\Contracts\ComponentNamerInterface;
use OpenApi\Spec as OA;
use OpenApi\Specification;
use OpenApi\Specification\ComponentIndex;
use OpenApi\Specification\ComponentKey;
use OpenApi\Type\Generics\TemplateBinder;
use OpenApi\Type\Generics\TemplateReader;
use OpenApi\Type\Generics\TypeArguments;
use Psr\Log\LoggerInterface;
use Symfony\Component\TypeInfo\Type;

/**
 * Makes the schema for a generic class used with type arguments, as a copy of the class's own.
 *
 * A class declaring `@template T` and used as `Envelope<Customer>` gets a copy of its schema under
 * the key `Envelope` with the type argument `Customer`. `Augmenter\Types` asks for it where it
 * references the use, and then types the copy's properties, which read `T` as `Customer`.
 *
 * The copy is taken from the schema as it was before `Types` typed anything in it, and is named
 * here, by the namer every component is named by. It is not added to the document here: `Types`
 * takes each copy with `takePending()`, types it and adds it.
 *
 * A use keeps the class's plain schema where a copy would say nothing more: without arguments,
 * with `mixed` only, or with a template left in the arguments, as in the class's own `Node<T>`. It
 * keeps the plain schema with a warning where no copy can be made: the class declares no
 * template, there is not one argument for each template, the namer gives the copy no name, or
 * copies ask for copies too many times.
 */
class GenericCopies
{
    /** @var array<string, OA\Schema> the schema of each generic class as the class declares it, before `Types` types anything in it, by class */
    protected array $declared = [];

    /** @var array<string, OA\Schema|null> what each key was answered with, by its string, null for the plain schema */
    protected array $answers = [];

    /** @var list<OA\Schema> the copies made and not handed out yet, their properties still to be typed */
    protected array $pending = [];

    /** whether copies are still made */
    protected bool $making = true;

    protected TemplateBinder $templateBinder;

    public function __construct(
        Specification $specification,
        protected ComponentIndex $index,
        protected TemplateReader $templateReader,
        protected ComponentNamerInterface $namer,
        protected ?LoggerInterface $logger = null,
    ) {
        $this->templateBinder = new TemplateBinder();

        // Keep the schema of every generic class now. `Types` is about to type the document, and a
        // copy of `Box` taken after that would carry what `Types` made of the unbound `T`, such
        // as `nullable` from a property declared `mixed`.
        foreach ($specification->schemas as $schema) {
            $key = $schema->getComponentKey();
            // only the class's one plain schema: a class with several is not referenced by its type
            if ($key?->isPlain() === true && $this->templateReader->templateNames($key->class) !== [] && count($index->findAllByKey($key, 'schemas')) === 1) {
                $this->declared[$key->class] = $schema->copy();
            }
        }
    }

    /**
     * The schema for the key's class used with the key's type arguments, or null where the use
     * keeps the class's plain schema.
     *
     * @param string $subject where the use is, for a log message
     */
    public function for(ComponentKey $key, string $subject): ?OA\Schema
    {
        // answered once for each key, so `Box<Customer>` used in ten places is one copy, and a
        // use that keeps the plain schema is reported once
        $id = (string) $key;
        if (!array_key_exists($id, $this->answers)) {
            // a schema registered under this key by hand, or by a plugin, is the one referenced
            $existing = $this->index->findByKey($key, 'schemas');
            $this->answers[$id] = $existing instanceof OA\Schema ? $existing : $this->make($key, $subject);
        }

        return $this->answers[$id];
    }

    /**
     * The copies made since this was last asked, for their properties to be typed.
     *
     * @return list<OA\Schema>
     */
    public function takePending(): array
    {
        // emptied, so a copy is handed out once and typed once
        $copies = $this->pending;
        $this->pending = [];

        return $copies;
    }

    /**
     * Make no more copies. A use asking for one from here on keeps the plain schema, and is
     * reported.
     */
    public function stop(): void
    {
        $this->making = false;
    }

    protected function make(ComponentKey $key, string $subject): ?OA\Schema
    {
        $typeArguments = $key->typeArguments;
        if (!$typeArguments instanceof TypeArguments) {
            return null;
        }

        // the use as it is written, for the messages: `App\Box<App\Customer>`
        $use = $key->class . '<' . $typeArguments . '>';

        // a wrong docblock more than anything else, and the author may expect a schema named for the arguments
        if ($this->templateReader->templateNames($key->class) === []) {
            $this->logger?->warning(sprintf('Type "%s" has type arguments, but its class declares no @template, using the plain schema in %s', $use, $subject));

            return null;
        }

        // `Box<int, string>` for a class with one template: no telling which argument is meant
        if (!$this->templateReader->accepts($key->class, $typeArguments)) {
            $this->logger?->warning(sprintf('Type "%s" does not match the templates of its class, using the plain schema in %s', $use, $subject));

            return null;
        }

        // nothing was kept for a class with more than one schema, or none: `Types` references neither
        $declared = $this->declared[$key->class] ?? null;
        if (!$declared instanceof OA\Schema) {
            return null;
        }

        // no warning: `Box<mixed>` and the class's own `Box<T>` are the plain schema, said another way
        if ($this->saysNothing($typeArguments)) {
            return null;
        }

        // checked last, so only a use that would have got a copy is reported as nested too deep
        if (!$this->making) {
            $this->logger?->warning(sprintf('Type "%s" is nested too deep in schemas made for type arguments, using the plain schema in %s', $use, $subject));

            return null;
        }

        // The copy is the declared schema under the key with the arguments. The key is what tells
        // the resolver to read `T` as its argument when `Types` types the copy's properties.
        $copy = $declared->copy();
        $copy->setComponentKey($key);

        // named from its key, not with the name the declared schema has
        $copy->component = null;
        $name = $this->namer->name($copy);
        // a namer that cannot write one of the arguments, such as a union, gives no name
        if ($name === null) {
            $this->logger?->warning(sprintf('Type "%s" has type arguments a schema cannot be named for, using the plain schema in %s', $use, $subject));

            return null;
        }

        $copy->component = $name;
        // its properties are still as declared: `Types` takes it from here and types them
        $this->pending[] = $copy;

        return $copy;
    }

    /**
     * Whether the arguments say nothing the class's templates do not: all are `mixed`, or one
     * still holds a template.
     */
    protected function saysNothing(TypeArguments $typeArguments): bool
    {
        // `Pair<mixed, int>` does say something, so only all of them count
        $mixed = array_filter($typeArguments->types, static fn (Type $argument): bool => (string) $argument === 'mixed');
        $templates = array_filter($typeArguments->types, $this->templateBinder->hasTemplate(...));

        return count($mixed) === count($typeArguments->types) || $templates !== [];
    }
}
