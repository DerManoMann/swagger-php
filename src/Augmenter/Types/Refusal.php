<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Augmenter\Types;

/**
 * Why a generic class used with type arguments keeps its plain schema rather than getting a copy.
 *
 * `CopyRule` gives the reason, and the message says it at the use.
 */
enum Refusal
{
    /** the class declares no `@template`, so the arguments bind nothing */
    case NoTemplates;

    /** not one argument for each template: no telling which argument is meant */
    case Mismatch;

    /** all arguments `mixed`, or a template still in them: the plain schema, said another way */
    case SaysNothing;

    /** the arguments nest deeper than `CopyRule::MAX_DEPTH` */
    case TooDeep;

    /** the namer gives the copy no name, as for a union argument */
    case Unnamed;

    /** the namer gives the copy the name of the class's own schema, which the copy would replace */
    case SameName;

    /**
     * The warning for a use refused for this reason, or null where there is nothing to warn about.
     *
     * @param string $use     the use as it is written, `App\Box<App\Customer>`
     * @param string $subject where the use is
     */
    public function message(string $use, string $subject): ?string
    {
        return match ($this) {
            self::NoTemplates => sprintf('Type "%s" has type arguments, but its class declares no @template, using the plain schema in %s', $use, $subject),
            self::Mismatch => sprintf('Type "%s" does not match the templates of its class, using the plain schema in %s', $use, $subject),
            // no warning: `Box<mixed>` and the class's own `Box<T>` mean the plain schema
            self::SaysNothing => null,
            self::TooDeep => sprintf('Type "%s" nests its type arguments more than %d deep, using the plain schema in %s', $use, CopyRule::MAX_DEPTH, $subject),
            self::Unnamed => sprintf('Type "%s" has type arguments a schema cannot be named for, using the plain schema in %s', $use, $subject),
            self::SameName => sprintf('Type "%s" would be named as the schema of its class, using the plain schema in %s', $use, $subject),
        };
    }
}
