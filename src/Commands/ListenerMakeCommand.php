<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:listener', description: 'Create a new event listener class')]
final class ListenerMakeCommand extends GenerateCommand
{
    protected string $type = 'Listener';

    protected string $example = 'NotifySubscribers';

    protected string $namespace = 'Listeners';

    public function getStub(): string
    {
        return $this->option('queued') ? 'listener.queued.stub' : 'listener.stub';
    }

    protected function getReplacements(string $name): array
    {
        $event = $this->event();

        return ['eventType' => $event !== null ? $this->classBasename($event) : 'object'];
    }

    protected function getImports(string $class): array
    {
        return array_filter([$this->event()]);
    }

    /**
     * When --event is given, the listener is registered for it in the service provider.
     */
    protected function afterGenerate(string $class, string $path): void
    {
        if (($event = $this->event()) !== null) {
            $this->report($this->registrar()->listener($event, $class));
        }
    }

    /**
     * The event class, e.g. "PostPublished" -> "Acme\Blog\Events\PostPublished". Classes in the
     * root namespace or in Illuminate, and names with a leading backslash, are used as is.
     */
    private function event(): ?string
    {
        $event = $this->option('event');

        if (! $event) {
            return null;
        }

        if (str_starts_with($event, '\\')) {
            return ltrim($event, '\\');
        }

        $event = str_replace('/', '\\', ltrim($event, '\\/'));
        $root = $this->packageRootNamespace();

        return str_starts_with($event, $root.'\\') || str_starts_with($event, 'Illuminate\\')
            ? $event
            : $root.'\\Events\\'.$event;
    }

    protected function afterPromptingForMissingArguments(): void
    {
        if ($this->option('event')) {
            return;
        }

        $event = $this->prompts()->suggest(
            $this->trans('What event should be listened for?'),
            $this->classesIn('Events'),
            hint: $this->trans('Optional.'),
            validate: fn (string $value) => ($error = $this->validationError('--event', $value)) !== null ? $this->trans($error) : null,
        );

        if ($event !== '') {
            $this->input()->setOption('event', $event);
        }
    }

    protected function getValidationRules(): array
    {
        return [
            ...parent::getValidationRules(),
            '--event' => [$this->classOptionRule('The event must be a class name that PHP does not reserve, e.g. "PostPublished" or "\\Illuminate\\Auth\\Events\\Login".')],
        ];
    }

    protected function getOptions(): array
    {
        return [
            ['event', 'e', InputOption::VALUE_REQUIRED, 'The event class being listened for (registered in the service provider)'],
            ['queued', null, InputOption::VALUE_NONE, 'Indicates the event listener should be queued'],
            ...parent::getOptions(),
        ];
    }
}
