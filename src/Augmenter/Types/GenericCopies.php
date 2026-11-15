<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Augmenter\Types;

use OpenApi\Spec as OA;
use OpenApi\Specification;
use OpenApi\Specification\ComponentIndex;
use OpenApi\Specification\ComponentKey;
use OpenApi\Type\Generics\TemplateReader;

/**
 * Makes the schema for a generic class used with type arguments, as a copy of the class's own.
 *
 * A class declaring `@template T` and used as `Envelope<Customer>` gets a copy of its schema under
 * the key `Envelope` with the type argument `Customer`. `Augmenter\Types` asks for it where it
 * references the use, and then types the copy's properties, which read `T` as `Customer`.
 *
 * The copy is taken from the schema as it was before `Types` typed anything in it, and is named
 * here. It is not added to the document here: `Types` takes each copy with `takePending()`, types
 * it and adds it.
 *
 * Whether a use gets a copy, and its name, is `CopyRule`'s to say. A use the rule refuses keeps
 * the class's plain schema, and is given the `Refusal`, for `Types` to report where the use is.
 */
class GenericCopies
{
    /** @var array<string, OA\Schema> the schema of each generic class as the class declares it, before `Types` types anything in it, by class */
    protected array $declared = [];

    /** @var array<string, OA\Schema|string|Refusal> what each key was decided: a schema registered under it, the name of its copy, or why it keeps the plain schema */
    protected array $decided = [];

    /** @var array<string, OA\Schema> the copy made for each key, by its string */
    protected array $made = [];

    /** @var list<OA\Schema> the copies made and not handed out yet, their properties still to be typed */
    protected array $pending = [];

    public function __construct(
        Specification $specification,
        protected ComponentIndex $index,
        protected TemplateReader $templateReader,
        protected CopyRule $rule,
    ) {
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
     * The schema for the key's class used with the key's type arguments, made the first time it is
     * asked for. A `Refusal` where the use keeps the class's plain schema.
     */
    public function for(ComponentKey $key): OA\Schema|Refusal
    {
        $decision = $this->decide($key);
        if (!is_string($decision)) {
            return $decision;
        }

        // made once for each key, so `Box<Customer>` used in ten places is one copy
        return $this->made[(string) $key] ??= $this->make($key, $decision);
    }

    /**
     * Why a use keeps the class's plain schema, or null where it would not. Makes no copy: for a
     * use nothing will reference, such as a member of a union `Types` drops.
     */
    public function check(ComponentKey $key): ?Refusal
    {
        $decision = $this->decide($key);

        return $decision instanceof Refusal ? $decision : null;
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
     * What the key gets, decided once for each key: a schema registered under it, the name of its
     * copy, or why it keeps the plain schema.
     */
    protected function decide(ComponentKey $key): OA\Schema|string|Refusal
    {
        $id = (string) $key;
        if (array_key_exists($id, $this->decided)) {
            return $this->decided[$id];
        }

        // a schema registered under this key by hand, or by a plugin, is the one referenced
        $existing = $this->index->findByKey($key, 'schemas');
        if ($existing instanceof OA\Schema) {
            return $this->decided[$id] = $existing;
        }

        // first what the class and the arguments say, which needs no schema
        $refusal = $this->rule->refusal($key);
        if ($refusal instanceof Refusal) {
            return $this->decided[$id] = $refusal;
        }

        // then the name, which needs the schema the copy is made from. One was kept for every
        // generic class with one schema, and `Types` asks about no other
        return $this->decided[$id] = $this->rule->name($key, $this->declared[$key->class]);
    }

    protected function make(ComponentKey $key, string $name): OA\Schema
    {
        // The copy is the declared schema under the key with the arguments. The key is what tells
        // the resolver to read `T` as its argument when `Types` types the copy's properties.
        $copy = $this->declared[$key->class]->copy();
        $copy->setComponentKey($key);
        $copy->component = $name;
        // its properties are still as declared: `Types` takes it from here and types them
        $this->pending[] = $copy;

        return $copy;
    }
}
