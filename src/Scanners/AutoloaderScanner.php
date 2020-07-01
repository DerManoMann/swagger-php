<?php

/**
 * @license Apache 2.0
 */

namespace OpenApi\Scanners;

use Composer\Autoload\ClassLoader;

/**
 * Scans for classes/interfaces/traits.
 *
 * Relies on a `composer --optimized` run in order to utilize
 * the generated class map.
 */
class AutoloaderScanner
{
    /**
     * Collect all classes/interfaces/traits known by autoloaders.
     */
    public function scan($namespaces): array
    {
        $units = [];
        if ($autoloader = $this->getComposerAutoloader()) {
            foreach (array_keys($autoloader->getClassMap()) as $unit) {
                foreach ((array) $namespaces as $namespace) {
                    if (0 === strpos($unit, $namespace)) {
                        $units[] = $unit;
                        break;
                    }
                }
            }
        }

        return $units;
    }

    protected function getComposerAutoloader(): ?ClassLoader
    {
        foreach (spl_autoload_functions() as $fkt) {
            if (is_array($fkt) && $fkt[0] instanceof ClassLoader) {
                return $fkt[0];
            }
        }

        return null;
    }
}
