<?php

namespace NyonCode\LaravelPackageConsole\Concerns;

use NyonCode\LaravelPackageConsole\Commands\BaseCommand;
use Symfony\Component\Console\Input\InputOption;

trait HasOptions
{
    /**
     * Specify the options on the command.
     *
     * @return HasOptions|BaseCommand
     */
    protected function specifyOptions(): self
    {
        foreach ($this->getOptions() as $option) {
            if ($option instanceof InputOption) {
                $this->getDefinition()->addOption($option);
            } else {
                $this->addOption(...$option);
            }
        }

        return $this;
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [];
    }
}
