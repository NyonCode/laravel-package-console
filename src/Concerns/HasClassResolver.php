<?php

namespace NyonCode\LaravelPackageConsole\Concerns;

trait HasClassResolver
{
    /**
     * Qualify the given class name.
     */
    public function qualifyClass(string $name): string
    {
        $name = ltrim($name, '\\/');
        $name = str_replace('/', '\\', $name);

        $rootNamespace = $this->packageRootNamespace();

        if (str_starts_with($name, $rootNamespace)) {
            return $name;
        }

        return $this->qualifyClass(
            $this->getDefaultNamespace(trim($rootNamespace, '\\')).'\\'.$name
        );
    }

    /**
     * Build the class with the given name.
     */
    protected function buildClass(string $name): string
    {
        $stub = $this->files->get($this->resolveStub($this->getStub()));

        $this->replaceClass($stub, $name)->replaceNamespace($stub);

        return $stub;
    }

    /**
     * Replaces placeholders in the given stub with the correct class name.
     *
     * Replaces the placeholders `DummyClass`, `{{ class }}` and `{{class}}` with
     * the name of the class that is being generated. If $class is given, it will
     * be used instead of the name of the class that is being generated.
     *
     * @param  string  $stub  The stub to replace the placeholders in.
     * @param  string  $class  The name of the class that is being generated.
     * @return $this
     */
    protected function replaceClass(string &$stub, string $class): static
    {
        $searches = ['DummyClass', '{{ class }}', '{{class}}'];

        foreach ($searches as $search) {
            $stub = str_replace($search, $class, $stub);
        }

        return $this;
    }
}
