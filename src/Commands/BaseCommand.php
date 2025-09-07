<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Exception;
use NyonCode\LaravelPackageConsole\Concerns\HasArguments;
use NyonCode\LaravelPackageConsole\Concerns\HasInteractionWithIO;
use NyonCode\LaravelPackageConsole\Concerns\HasOptions;
use NyonCode\LaravelPackageConsole\Concerns\HasResolver;
use NyonCode\LaravelPackageConsole\Concerns\InputValidator;
use NyonCode\LaravelPackageConsole\Concerns\NameNormalizer;
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
    use HasResolver;
    use InputValidator;
    use NameNormalizer;

    public function __construct()
    {
        parent::__construct();
    }

    abstract public function handle();

    /**
     * Call a console command
     *
     *
     * @throws Exception
     */
    public function call(
        string $commandName,
        array $arguments = [],
        array $options = []
    ): int {
        $application = $this->getApplication();

        $inputParams = array_merge(
            ['command' => $commandName],
            $arguments,
            $options
        );

        $input = new ArrayInput($inputParams);
        $output = new ConsoleOutput;
        $application->run($input, $output);

        return Command::SUCCESS;
    }

    /**
     * Run the console command.
     *
     *
     * @throws ExceptionInterface
     */
    public function run(InputInterface $input, OutputInterface $output): int
    {
        return parent::run($this->input = $input, $this->output = $output);
    }

    /**
     * Configure the console command.
     */
    protected function configure(): void
    {
        $this->specifyArguments()->specifyOptions();
    }

    /**
     * Execute the console command.
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $this->input = $input;
        $this->output = $output;
        $this->ioStyle = new SymfonyStyle($this->input, $this->output);

        $validationErrors = $this->validateArguments($input);

        if (! empty($validationErrors)) {
            foreach ($validationErrors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $method = method_exists($this, 'handle') ? 'handle' : '__invoke';

        try {
            $this->{$method}();

            return static::SUCCESS;
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return static::FAILURE;
        }
    }
}
