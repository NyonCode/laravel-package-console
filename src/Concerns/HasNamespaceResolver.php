<?php

namespace NyonCode\LaravelPackageConsole\Concerns;

trait HasNamespaceResolver
{
    /**
     * Get the default namespace for the class: the root namespace plus the command's $namespace.
     */
    protected function getDefaultNamespace(string $rootNamespace): string
    {
        return $this->namespace !== '' ? $rootNamespace.'\\'.$this->namespace : $rootNamespace;
    }

    /**
     * Get the root namespace of the package (the one mapped to src/).
     */
    public function packageRootNamespace(): string
    {
        return $this->package()->rootNamespace();
    }

    /**
     * Get the namespace part of a fully qualified class name.
     */
    protected function getClassNamespace(string $name): string
    {
        $name = trim($name, '\\');
        $position = strrpos($name, '\\');

        return $position === false ? '' : substr($name, 0, $position);
    }
}
