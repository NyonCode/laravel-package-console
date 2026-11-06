<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use NyonCode\LaravelPackageConsole\Concerns\HasArguments;
use NyonCode\LaravelPackageConsole\Concerns\HasInteractionWithIO;
use NyonCode\LaravelPackageConsole\Concerns\HasOptions;
use NyonCode\LaravelPackageConsole\Concerns\InputValidator;
use NyonCode\LaravelPackageConsole\Concerns\NameNormalizer;
use NyonCode\LaravelPackageConsole\Support\Package;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\ArrayInput;
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
        return parent::run($this->input = $input, $this->output = $output);
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
     * Ask for required arguments that were not passed on the command line.
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $io = new SymfonyStyle($input, $output);

        foreach ($this->getDefinition()->getArguments() as $argument) {
            $value = $input->getArgument($argument->getName());

            if ($argument->isRequired() && ($value === null || $value === [] || $value === '')) {
                $input->setArgument(
                    $argument->getName(),
                    $io->ask($argument->getDescription() ?: ucfirst($argument->getName()))
                );
            }
        }
    }

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
