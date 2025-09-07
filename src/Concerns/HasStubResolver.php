<?php

namespace NyonCode\LaravelPackageConsole\Concerns;

use RuntimeException;

trait HasStubResolver
{
    protected string $stub = '';

    /**
     * Get the stub.
     */
    public function getStub(): string
    {
        return $this->stub;
    }

    /**
     * Resolve the given stub.
     *
     *
     * @throws RuntimeException
     */
    protected function resolveStub(string $stub): string
    {
        if (! $this->files->isFile(pcli_stubs_path($stub))) {
            throw new RuntimeException("Stub [$stub] not found.");
        }

        return pcli_stubs_path($stub);
    }
}
