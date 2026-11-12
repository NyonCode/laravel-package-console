<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use NyonCode\LaravelPackageConsole\Concerns\HasArguments;
use NyonCode\LaravelPackageConsole\Concerns\HasInteractionWithIO;
use NyonCode\LaravelPackageConsole\Concerns\HasOptions;
use NyonCode\LaravelPackageConsole\Concerns\InputValidator;
use NyonCode\LaravelPackageConsole\Concerns\NameNormalizer;
use NyonCode\LaravelPackageConsole\Support\Package;
use NyonCode\LaravelPackageConsole\Support\Prompts;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class BaseCommand extends Command
{
    use HasArguments;
    use HasInteractionWithIO;
    use HasOptions;
    use InputValidator;
    use NameNormalizer;

    private ?Prompts $prompts = null;

    /**
     * Execute the console command. Return an exit code (int), or false for failure.
     */
    abstract public function handle();

    /**
     * Call another console command (non-interactively) and return its exit code.
     *
     * @param  array<string, mixed>  $arguments  Arguments and options, e.g. ['name' => 'Post', '--force' => true].
     *
     * @throws ExceptionInterface
     */
    public function call(string $commandName, array $arguments = [], array $options = []): int
    {
        $command = $this->getApplication()->find($commandName);

        $input = new ArrayInput(array_merge($arguments, $options));
        $input->setInteractive(false);

        return $command->run($input, $this->output ?? new ConsoleOutput);
    }

    /**
     * Run the console command.
     *
     * @throws ExceptionInterface
     */
    public function run(InputInterface $input, OutputInterface $output): int
    {
        $this->prompts = null;

        return parent::run($this->input = $input, $this->output = $output);
    }

    /**
     * The questions this run may ask: prompts in a terminal, defaults when not interactive.
     */
    protected function prompts(): Prompts
    {
        return $this->prompts ??= new Prompts($this->input, $this->output);
    }

    /**
     * An interactive question in the user's language.
     *
     * @param  array<string, string|int>  $replace
     */
    protected function trans(string $line, array $replace = []): string
    {
        return anvil_trans($line, $replace);
    }

    /**
     * The Laravel package being developed.
     */
    protected function package(): Package
    {
        return anvil_package();
    }

    /**
     * Configure the console command.
     */
    protected function configure(): void
    {
        $this->specifyArguments()->specifyOptions();
    }

    /**
     * Ask for required arguments that were not passed on the command line. A command that had
     * to ask may go on to ask about its options in afterPromptingForMissingArguments().
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $prompted = false;

        foreach ($this->getDefinition()->getArguments() as $argument) {
            $value = $input->getArgument($argument->getName());

            if (! $argument->isRequired() || ! ($value === null || $value === [] || $value === '')) {
                continue;
            }

            [$label, $placeholder] = $this->promptForArgument($argument);
            $name = $argument->getName();

            $input->setArgument($name, $this->prompts()->text(
                $label,
                placeholder: $placeholder,
                required: true,
                validate: fn (string $value) => ($error = $this->validationError($name, $value)) !== null ? $this->trans($error) : null,
            ));
            $prompted = true;
        }

        if ($prompted) {
            $this->afterPromptingForMissingArguments();
        }
    }

    /**
     * The question and the placeholder for a missing argument.
     *
     * @return array{0: string, 1: string}
     */
    protected function promptForArgument(InputArgument $argument): array
    {
        return [$this->trans($argument->getDescription() ?: ucfirst($argument->getName())), ''];
    }

    /**
     * Ask about options once the missing arguments were asked for (as Artisan does). Only
     * called in an interactive run, and only when an argument was missing.
     */
    protected function afterPromptingForMissingArguments(): void {}

    /**
     * Execute the console command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;
        $this->ioStyle = new SymfonyStyle($this->input, $this->output);

        $validationErrors = $this->validateArguments($input);

        if ($validationErrors !== []) {
            foreach ($validationErrors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        try {
            $result = $this->handle();

            return match (true) {
                is_int($result) => $result,
                $result === false => static::FAILURE,
                default => static::SUCCESS,
            };
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return static::FAILURE;
        }
    }
}
