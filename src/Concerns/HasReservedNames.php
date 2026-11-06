<?php

namespace NyonCode\LaravelPackageConsole\Concerns;

trait HasReservedNames
{
    /**
     * Reserved names that cannot be used for generation.
     *
     * @var string[]
     */
    protected array $reservedNames = [
        '__halt_compiler',
        'abstract',
        'and',
        'array',
        'as',
        'break',
        'callable',
        'case',
        'catch',
        'class',
        'clone',
        'const',
        'continue',
        'declare',
        'default',
        'die',
        'do',
        'echo',
        'else',
        'elseif',
        'empty',
        'enddeclare',
        'endfor',
        'endforeach',
        'endif',
        'endswitch',
        'endwhile',
        'enum',
        'eval',
        'exit',
        'extends',
        'false',
        'final',
        'finally',
        'fn',
        'for',
        'foreach',
        'function',
        'global',
        'goto',
        'if',
        'implements',
        'include',
        'include_once',
        'instanceof',
        'insteadof',
        'interface',
        'isset',
        'list',
        'match',
        'namespace',
        'new',
        'or',
        'parent',
        'print',
        'private',
        'protected',
        'public',
        'readonly',
        'require',
        'require_once',
        'return',
        'self',
        'static',
        'switch',
        'throw',
        'trait',
        'true',
        'try',
        'unset',
        'use',
        'var',
        'while',
        'xor',
        'yield',
        // Reserved class names (types)
        'bool',
        'float',
        'int',
        'iterable',
        'mixed',
        'never',
        'null',
        'object',
        'string',
        'void',
        '__CLASS__',
        '__DIR__',
        '__FILE__',
        '__FUNCTION__',
        '__LINE__',
        '__METHOD__',
        '__NAMESPACE__',
        '__TRAIT__',
    ];

    /**
     * Checks if the given name is a reserved name.
     *
     * @param  string  $name  The name to check.
     * @return bool True if the name is reserved, false otherwise.
     */
    protected function isReservedName(string $name): bool
    {
        $reservedNames = array_map('strtolower', $this->reservedNames);
        $name = trim(strtolower($name));

        if (in_array(strtolower($name), $reservedNames)) {
            return true;
        }

        return false;
    }
}
