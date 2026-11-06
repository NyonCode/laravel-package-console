<?php

namespace NyonCode\LaravelPackageConsole\Commands\Concerns;

trait CreatesViews
{
    /**
     * Create resources/views/{dotted.name}.blade.php from a stub (unless it exists)
     * and register the package's views in the service provider.
     *
     * @param  array<string, string>  $replacements
     */
    protected function createView(string $view, string $stub, array $replacements = []): void
    {
        $path = $this->package()->path('resources/views/'.str_replace('.', '/', $view).'.blade.php');

        if (! $this->files->isFile($path) || $this->option('force')) {
            $contents = $this->files->get($this->resolveStub($stub));

            foreach (['name' => $view, ...$replacements] as $key => $value) {
                $contents = str_replace(['{{ '.$key.' }}', '{{'.$key.'}}'], $value, $contents);
            }

            $this->makeDirectory($path);
            $this->files->put($path, $contents);

            $this->output()->writeln(sprintf(
                '  <fg=blue;options=bold>INFO</> View [<options=bold>%s</>] created successfully.',
                $this->package()->relativePath($path)
            ));
        }

        $this->report($this->registrar()->views());
    }
}
