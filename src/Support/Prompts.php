<?php

declare(strict_types=1);

namespace NyonCode\LaravelPackageConsole\Support;

use Closure;
use Laravel\Prompts\ConfirmPrompt;
use Laravel\Prompts\MultiSelectPrompt;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\SelectPrompt;
use Laravel\Prompts\SuggestPrompt;
use Laravel\Prompts\TextPrompt;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * The questions a command asks. Each one is answered in one of three ways:
 *
 * - not interactive (--no-interaction, CI, a command called by another): the default;
 * - a terminal: a Laravel Prompts prompt;
 * - anything else that can still answer (Windows without WSL, piped input): a plain
 *   Symfony question.
 *
 * A validator returns the error message, or null when the value is fine.
 */
final class Prompts
{
    /**
     * Prompts were faked for a test, so the "terminal" is the queue of key presses.
     */
    private static bool $faked = false;

    private ?SymfonyStyle $style = null;

    public function __construct(private readonly InputInterface $input, private readonly OutputInterface $output) {}

    /**
     * Answer the prompts of the next commands with these key presses (tests).
     *
     * @param  list<string>  $keys
     */
    public static function fake(array $keys = []): void
    {
        Prompt::fake($keys);
        self::$faked = true;
    }

    public static function stopFaking(): void
    {
        self::$faked = false;
    }

    /**
     * Whether questions may be asked at all.
     */
    public function interactive(): bool
    {
        return $this->input->isInteractive();
    }

    /**
     * @param  (Closure(string): ?string)|null  $validate
     */
    public function text(string $label, string $default = '', string $placeholder = '', bool $required = false, ?Closure $validate = null, string $hint = ''): string
    {
        if (! $this->interactive()) {
            return $this->accept($default, $required, $validate);
        }

        if ($this->native()) {
            return (string) $this->prompt(new TextPrompt($label, $placeholder, $default, $this->required($required), $validate, $hint));
        }

        return (string) $this->style()->ask($this->plain($label, $hint), $default !== '' ? $default : null, $this->validator($required, $validate));
    }

    /**
     * Free text with suggestions to pick from.
     *
     * @param  list<string>  $options
     * @param  (Closure(string): ?string)|null  $validate
     */
    public function suggest(string $label, array $options, string $default = '', string $placeholder = '', bool $required = false, ?Closure $validate = null, string $hint = ''): string
    {
        if ($options === []) {
            return $this->text($label, $default, $placeholder, $required, $validate, $hint);
        }

        if (! $this->interactive()) {
            return $this->accept($default, $required, $validate);
        }

        if ($this->native()) {
            return (string) $this->prompt(new SuggestPrompt($label, $options, $placeholder, $default, required: $this->required($required), validate: $validate, hint: $hint));
        }

        $question = (new Question($this->plain($label, $hint), $default !== '' ? $default : null))
            ->setAutocompleterValues($options)
            ->setValidator($this->validator($required, $validate));

        return (string) $this->style()->askQuestion($question);
    }

    public function confirm(string $label, bool $default = true, string $hint = ''): bool
    {
        if (! $this->interactive()) {
            return $default;
        }

        if ($this->native()) {
            return (bool) $this->prompt(new ConfirmPrompt($label, $default, anvil_trans('Yes'), anvil_trans('No'), hint: $hint));
        }

        return $this->style()->confirm($this->plain($label, $hint), $default);
    }

    /**
     * @param  array<int|string, string>  $options  A list of values, or value => label.
     */
    public function select(string $label, array $options, int|string|null $default = null, string $hint = ''): int|string
    {
        $default ??= array_is_list($options) ? $options[0] : array_key_first($options);

        if (! $this->interactive()) {
            return $default;
        }

        if ($this->native()) {
            return $this->prompt(new SelectPrompt($label, $options, $default, scroll: min(count($options), 10), hint: $hint));
        }

        return $this->key($options, $this->style()->choice($this->plain($label, $hint), $options, $default));
    }

    /**
     * @param  array<int|string, string>  $options  A list of values, or value => label.
     * @param  list<int|string>  $default
     * @return list<int|string>
     */
    public function multiselect(string $label, array $options, array $default = [], bool $required = false, string $hint = ''): array
    {
        if (! $this->interactive()) {
            return $default;
        }

        if ($this->native()) {
            return array_values((array) $this->prompt(new MultiSelectPrompt(
                $label,
                $options,
                $default,
                scroll: min(count($options), 10),
                required: $this->required($required),
                hint: $hint !== '' ? $hint : anvil_trans('Space selects, Enter confirms.'),
            )));
        }

        // A Symfony choice cannot be answered with nothing, so "none" is a choice of its own.
        $list = array_is_list($options);
        $none = anvil_trans('None');
        $choices = $required ? $options : ($list ? [$none, ...$options] : ['none' => $none] + $options);

        $question = (new ChoiceQuestion(
            $this->plain($label, $hint !== '' ? $hint : anvil_trans('Separate several with a comma.')),
            $choices,
            $default !== [] ? implode(',', $default) : ($required ? null : ($list ? $none : 'none')),
        ))->setMultiselect(true);

        $selected = (array) $this->style()->askQuestion($question);

        return array_values(array_filter(
            array_map(fn (int|string $value) => $this->key($choices, $value), $selected),
            fn (int|string $value) => $required || $value !== ($list ? $none : 'none')
        ));
    }

    /**
     * Symfony answers with the key of a choice only when the keys are strings; with numeric
     * keys ("12" => "Laravel 12") it answers with the label.
     *
     * @param  array<int|string, string>  $options
     */
    private function key(array $options, int|string $answer): int|string
    {
        if (array_is_list($options) || array_key_exists($answer, $options)) {
            return $answer;
        }

        $key = array_search($answer, $options, true);

        return $key === false ? $answer : $key;
    }

    /**
     * Real prompts need a terminal on both ends; a test fakes one.
     */
    private function native(): bool
    {
        if (self::$faked) {
            return true;
        }

        if (windows_os() || ($this->input instanceof StreamableInputInterface && $this->input->getStream() !== null)) {
            return false;
        }

        return defined('STDIN') && stream_isatty(STDIN);
    }

    private function prompt(Prompt $prompt): mixed
    {
        // A faked prompt keeps its own buffer, where the test reads what was rendered.
        if (! self::$faked) {
            Prompt::setOutput($this->output);
        }

        Prompt::interactive();
        $prompt->cancelMessage = anvil_trans('Cancelled.');

        return $prompt->prompt();
    }

    /**
     * The default answer of a question that cannot be asked.
     *
     * @param  (Closure(string): ?string)|null  $validate
     */
    private function accept(string $default, bool $required, ?Closure $validate): string
    {
        return ($this->validator($required, $validate))($default);
    }

    /**
     * @param  (Closure(string): ?string)|null  $validate
     * @return Closure(?string): string
     */
    private function validator(bool $required, ?Closure $validate): Closure
    {
        return function (?string $value) use ($required, $validate): string {
            $value = trim((string) $value);

            if ($required && $value === '') {
                throw new RuntimeException(anvil_trans('Required.'));
            }

            $error = $validate !== null ? $validate($value) : null;

            if (is_string($error) && $error !== '') {
                throw new RuntimeException($error);
            }

            return $value;
        };
    }

    private function required(bool $required): bool|string
    {
        return $required ? anvil_trans('Required.') : false;
    }

    private function plain(string $label, string $hint): string
    {
        return $hint !== '' ? "$label ($hint)" : $label;
    }

    private function style(): SymfonyStyle
    {
        return $this->style ??= new SymfonyStyle($this->input, $this->output);
    }
}
