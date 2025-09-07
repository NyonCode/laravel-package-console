<?php

namespace NyonCode\LaravelPackageConsole\Concerns;

use Symfony\Component\String\Inflector\EnglishInflector;

trait HasNamespaceResolver
{
    protected EnglishInflector $inflector;

    /**
     * Replace placeholders in the given stub with the correct namespace.
     *
     * Replaces the placeholders `DummyRootNamespace`, `DummyNamespace`, `{{ namespace }}` and `{{namespace}}` with
     * the fully qualified namespace of the package. If $namespace is given, it will be appended to the base package
     * path. Otherwise, it will be the root namespace of the package.
     *
     * @param  string  $stub  The stub to replace the placeholders in.
     * @return $this
     */
    protected function replaceNamespace(string &$stub): static
    {

        $searches = ['DummyRootNamespace', 'DummyNamespace', '{{ namespace }}', '{{namespace}}'];

        foreach ($searches as $search) {
            $stub = str_replace($search, $this->getDefaultNamespace($this->packageRootNamespace()), $stub);
        }

        return $this;
    }

    /**
     * Get the default namespace for the class.
     */
    protected function getDefaultNamespace(string $rootNamespace): string
    {
        $type = $this->inflector->pluralize($this->type);

        return $this->type ? $rootNamespace.'\\'.$type[0] : $rootNamespace;
    }

    /**
     * Get the root namespace for the package.
     */
    public function packageRootNamespace(): string
    {
        return pcli_packageRootNamespace();
    }

    /**
     * Get the fully qualified namespace.
     *
     * If a specific namespace is provided, it returns the namespace
     * appended to the base package path. Otherwise, it returns the
     * root namespace of the package.
     *
     * @param  string  $namespace  The specific namespace to append.
     * @return string The fully qualified namespace.
     */
    protected function getNamespace(string $namespace = ''): string
    {
        if (! empty($namespace)) {
            return trim(pcli_package_path().'\\'.$namespace);
        }

        return pcli_packageRootNamespace();
    }
}
