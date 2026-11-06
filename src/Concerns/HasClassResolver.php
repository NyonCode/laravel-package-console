<?php

namespace NyonCode\LaravelPackageConsole\Concerns;

use function Symfony\Component\String\u;

trait HasClassResolver
{
    /**
     * Qualify the given class name, e.g. "Admin/User" -> "Acme\Blog\Models\Admin\User".
     */
    public function qualifyClass(string $name): string
    {
        $name = str_replace('/', '\\', ltrim($name, '\\/'));

        $rootNamespace = $this->packageRootNamespace();

        if (str_starts_with($name, $rootNamespace.'\\')) {
            return $name;
        }

        $segments = explode('\\', $name);
        $segments[] = $this->normalizeClassSegment(array_pop($segments));

        return trim($this->getDefaultNamespace($rootNamespace), '\\').'\\'.implode('\\', $segments);
    }

    /**
     * Normalize the class part of the name (e.g. "user_service" -> "UserService").
     */
    protected function normalizeClassSegment(string $name): string
    {
        return ucfirst($name);
    }

    /**
     * Get the class name without its namespace (e.g. "Admin/User" -> "User").
     */
    protected function classBasename(string $name): string
    {
        $name = trim(str_replace('/', '\\', $name), '\\');
        $position = strrpos($name, '\\');

        return $position === false ? $name : substr($name, $position + 1);
    }

    /**
     * Build the class with the given fully qualified name.
     */
    protected function buildClass(string $name): string
    {
        $stub = $this->files->get($this->resolveStub($this->getStub()));

        return $this->populateStub($stub, array_merge($this->defaultReplacements($name), $this->getReplacements($name)));
    }

    /**
     * Extra placeholder values for the stub, keyed by placeholder name without braces.
     *
     * @return array<string, string>
     */
    protected function getReplacements(string $name): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    protected function defaultReplacements(string $name): array
    {
        $class = $this->classBasename($name);

        return [
            'class' => $class,
            'namespace' => $this->getClassNamespace($name),
            'rootNamespace' => $this->packageRootNamespace(),
            'package' => $this->package()->shortName(),
            'packageName' => $this->package()->displayName(),
            'kebabClass' => u($class)->kebab()->toString(),
            'snakeClass' => u($class)->snake()->toString(),
        ];
    }

    /**
     * Replace `{{ key }}` / `{{key}}` placeholders (and the legacy Dummy* ones).
     *
     * @param  array<string, string>  $replacements
     */
    protected function populateStub(string $stub, array $replacements): string
    {
        $legacy = ['DummyRootNamespace' => 'rootNamespace', 'DummyNamespace' => 'namespace', 'DummyClass' => 'class'];

        foreach ($legacy as $search => $key) {
            if (isset($replacements[$key])) {
                $stub = str_replace($search, $replacements[$key], $stub);
            }
        }

        foreach ($replacements as $key => $value) {
            $stub = str_replace(['{{ '.$key.' }}', '{{'.$key.'}}'], $value, $stub);
        }

        return $stub;
    }
}
