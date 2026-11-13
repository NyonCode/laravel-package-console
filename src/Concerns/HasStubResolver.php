<?php

namespace NyonCode\LaravelPackageConsole\Concerns;

use NyonCode\LaravelPackageConsole\Kernel;
use RuntimeException;

trait HasStubResolver
{
    protected string $stub = '';

    /**
     * Get the stub file name.
     */
    public function getStub(): string
    {
        return $this->stub;
    }

    /**
     * Resolve the given stub: a customised copy in the package's stubs/anvil/ directory, or
     * in a monorepo in its root's stubs/anvil/, wins over the one shipped with the console.
     *
     * @throws RuntimeException
     */
    protected function resolveStub(string $stub): string
    {
        if ($stub === '') {
            throw new RuntimeException('No stub defined for ['.static::class.'].');
        }

        $monorepo = Kernel::workspace()->monorepo();

        foreach (array_filter([
            anvil_package_path('stubs/anvil/'.$stub),
            $monorepo?->root('stubs/anvil/'.$stub),
            anvil_stubs_path($stub),
        ]) as $path) {
            if ($this->files->isFile($path)) {
                return $path;
            }
        }

        throw new RuntimeException("Stub [$stub] not found.");
    }
}
