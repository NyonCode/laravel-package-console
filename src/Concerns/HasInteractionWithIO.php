<?php

namespace NyonCode\LaravelPackageConsole\Concerns;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

trait HasInteractionWithIO
{
    private InputInterface $input;

    private OutputInterface $output;

    private SymfonyStyle $ioStyle;

    public function error(string $message): void
    {
        $this->ioStyle->error($message);
    }

    public function comment(string $message): void
    {
        $this->ioStyle->comment($message);
    }

    public function warning(string $message): void
    {
        $this->ioStyle->warning($message);
    }

    public function success(string $message): void
    {
        $this->ioStyle->success($message);
    }

    public function failure(string $message): void
    {
        $this->output->writeln("<fg=red>$message</>");
    }

    public function note(string $message): void
    {
        $this->ioStyle->note($message);
    }

    public function message(string|iterable $message): void
    {
        $this->output->writeln($message);
    }

    public function input(): InputInterface
    {
        return $this->input;
    }

    public function argument(string $name)
    {
        return $this->input->getArgument($name);
    }

    public function hasArgument(string $name): bool
    {
        return $this->input->hasArgument($name);
    }

    public function option(string $name)
    {
        return $this->input->getOption($name);
    }

    public function hasOption(string $name): bool
    {
        return $this->input->hasOption($name);
    }

    public function output(): OutputInterface
    {
        return $this->output;
    }

    /**
     * Writes a message to the output with the "info" style.
     *
     * @param  string  $message  The message to write
     */
    public function info(string $message): void
    {
        $this->ioStyle->info($message);
    }

    public function title(string $message): void
    {
        $this->ioStyle->title($message);
    }

    /**
     * Write a line to the output, with the given style and message.
     *
     * @param  string  $style  The style of the line, e.g. "error", "success", "comment"
     * @param  string  $message  The message to write
     * @param  int  $options  The output options (e.g. verbosity level)
     */
    public function line(string $style, string $message, int $options = 0): void
    {
        $this->output->writeln("<$style>$message</$style>", $options);
    }

    /**
     * Display a message in case of right answer.
     */
    public function right(string $message): void
    {
        $this->ioStyle->block(sprintf(' 🎉  %s', $message), null, 'fg=white;bg=green', ' ', true);
    }

    /**
     * Display a message in case of wrong answer.
     */
    public function wrong(string $message): void
    {
        $this->newLine();
        $this->ioStyle->block(sprintf(' 🚫 %s', $message), null, 'fg=white;bg=red', ' ', true);
    }

    public function newLine(): void
    {
        $this->ioStyle->newLine();
    }

    public function confirm(string $question, bool $default = true): bool
    {
        return $this->ioStyle->confirm($question, $default);
    }

    public function question(string $question, ?string $default = null, ?callable $validator = null): mixed
    {
        return $this->ioStyle->ask($question, $default, $validator);
    }

    public function ask(string $question, ?string $default = null, ?callable $validator = null): mixed
    {
        return $this->ioStyle->ask($question, $default, $validator);
    }

    public function progressStart(int $max = 0): void
    {
        $this->ioStyle->progressStart($max);
    }

    public function progressAdvance(int $step = 1): void
    {
        $this->ioStyle->progressAdvance($step);
    }

    public function progressFinish(): void
    {
        $this->ioStyle->progressFinish();
    }
}
