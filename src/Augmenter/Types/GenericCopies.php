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
use Psr\Log\LoggerInterface;

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
 * the class's plain schema, with the warning the `Refusal` gives.
 */
class GenericCopies
{
    /** @var array<string, OA\Schema> the schema of each generic class as the class declares it, before `Types` types anything in it, by class */
    protected array $declared = [];

    /** @var array<string, OA\Schema|null> what each key was answered with, by its string, null for the plain schema */
    protected array $answers = [];

    /** @var list<OA\Schema> the copies made and not handed out yet, their properties still to be typed */
    protected array $pending = [];

    public function __construct(
        Specification $specification,
        protected ComponentIndex $index,
        protected TemplateReader $templateReader,
        protected CopyRule $rule,
        protected ?LoggerInterface $logger = null,
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

    protected function make(ComponentKey $key, string $subject): ?OA\Schema
    {
        // first what the class and the arguments say, which needs no schema
        $refusal = $this->rule->refusal($key);

        // nothing was kept for a class with more than one schema, or none: `Types` references neither
        $declared = $this->declared[$key->class] ?? null;
        if (!$refusal instanceof Refusal && $declared instanceof OA\Schema) {
            // then the name, which needs the schema the copy is made from
            $name = $this->rule->name($key, $declared);
            if (is_string($name)) {
                // The copy is the declared schema under the key with the arguments. The key is what
                // tells the resolver to read `T` as its argument when `Types` types the copy's
                // properties.
                $copy = $declared->copy();
                $copy->setComponentKey($key);
                $copy->component = $name;
                // its properties are still as declared: `Types` takes it from here and types them
                $this->pending[] = $copy;

                return $copy;
            }

            $refusal = $name;
        }

        if ($refusal instanceof Refusal) {
            // the use as it is written, for the message: `App\Box<App\Customer>`
            $message = $refusal->message($key->class . '<' . $key->typeArguments . '>', $subject);
            if ($message !== null) {
                $this->logger?->warning($message);
            }
        }

        return null;
    }
}
