<?php

namespace NyonCode\LaravelPackageConsole\Commands\Concerns;

trait ResolvesModels
{
    /**
     * Qualify a model name: "Post" -> "Acme\Blog\Models\Post". A name in the root namespace,
     * or one with a leading backslash ("\App\Models\User"), is used as is.
     */
    protected function qualifyModel(string $model): string
    {
        if (str_starts_with($model, '\\')) {
            return ltrim($model, '\\');
        }

        $model = str_replace('/', '\\', ltrim($model, '\\/'));
        $root = $this->packageRootNamespace();

        if (str_starts_with($model, $root.'\\')) {
            return $model;
        }

        return $root.'\\Models\\'.$model;
    }

    /**
     * Replacements for stubs that reference a model: {{ model }}, {{ modelVariable }}.
     *
     * @return array<string, string>
     */
    protected function modelReplacements(string $model): array
    {
        $class = $this->classBasename($this->qualifyModel($model));

        return [
            'model' => $class,
            'modelVariable' => lcfirst($class),
        ];
    }
}
