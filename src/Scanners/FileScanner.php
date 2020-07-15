<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Scanners;

use Symfony\Component\Finder\Finder;

/**
 * Scans files for classes/interfaces/traits.
 */
class FileScanner
{
    /**
     * Scans files for all classes, interfaces and traits.
     *
     * @return string[] list of code entities
     */
    public function scan(Finder $finder, bool $load = false): array
    {
        $units = [
            'classes' => [],
            'interfaces' => [],
            'traits' => [],
        ];

        foreach ($finder as $file) {
            $fileUnits = $this->scanFile($pathname = $file->getPathname());
            if ($load) {
                require_once ($pathname);
            }
            $units = array_merge_recursive($units, $fileUnits);
        }

        return $units;
    }

    /**
     * Scan file for all classes, interfaces and traits.
     *
     * @return string[] list of code entities
     */
    protected function scanFile(string $filename): array
    {
        $tokens = token_get_all(file_get_contents($filename));
        $units = [
            'classes' => [],
            'interfaces' => [],
            'traits' => [],
        ];

        $namespace = '';
        $lastToken = null;
        while (false !== ($token = $this->nextToken($tokens))) {
            if (!is_array($token)) {
                continue;
            }
            switch ($token[0]) {
                case T_NAMESPACE:
                    $namespace = $this->nextWord($tokens);
                    break;
                case T_CLASS:
                    if ($lastToken && is_array($lastToken) && $lastToken[0] === T_DOUBLE_COLON) {
                        // ::class
                        break;
                    }

                    $token = $this->nextToken($tokens);

                    if (is_string($token) && ($token === '(' || $token === '{')) {
                        // new class() { ... }
                        break;
                    }
                    if (is_array($token) && in_array($token[1], ['extends', 'implements'])) {
                        // new class() extends { ... }
                        break;
                    }

                    $name = $namespace.'\\'.$token[1];
                    $units['classes'][] = $name;
                    break;
                case T_INTERFACE:
                    $token = $this->nextToken($tokens);
                    $name = $namespace.'\\'.$token[1];
                    $units['interfaces'][] = $name;
                    break;
                case T_TRAIT:
                    $token = $this->nextToken($tokens);
                    $name = $namespace.'\\'.$token[1];
                    $units['traits'][] = $name;
                    break;
            }
            $lastToken = $token;
        }

        return $units;
    }

    /**
     * Get the next token that is not whitespace or comment.
     */
    protected function nextToken(array &$tokens)
    {
        $token = true;
        while ($token) {
            $token = next($tokens);
            if (is_array($token)) {
                if (in_array($token[0], [T_WHITESPACE, T_COMMENT])) {
                    continue;
                }
            }

            return $token;
        }

        return $token;
    }

    /**
     * Read next word.
     *
     * Skips leading whitespace.
     */
    protected function nextWord(array &$tokens): string
    {
        $word = '';
        while (false !== ($token = next($tokens))) {
            if (is_array($token)) {
                if ($token[0] === T_WHITESPACE) {
                    if ($word) {
                        break;
                    }
                    continue;
                }
                $word .= $token[1];
            }
        }

        return $word;
    }
}
