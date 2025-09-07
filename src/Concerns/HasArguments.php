<?php

namespace NyonCode\LaravelPackageConsole\Concerns;

use NyonCode\LaravelPackageConsole\Commands\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;

trait HasArguments
{
    /**
     * Adds the arguments defined in the `getArguments` method to the command's
     * definition. If the argument is an instance of `InputArgument`, it is added
     * directly. Otherwise, the argument is passed to the `addArgument` method.
     *
     * @return HasArguments|BaseCommand
     */
    protected function specifyArguments(): self
    {
        foreach ($this->getArguments() as $argument) {
            if ($argument instanceof InputArgument) {
                $this->getDefinition()->addArgument($argument);
            } else {
                $this->addArgument(...$argument);
            }
        }

        return $this;
    }

    protected function getArguments(): array
    {
        return [];
    }
}
