<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:listener', description: 'Create a new event listener class')]
final class ListenerMakeCommand extends GenerateCommand
{
    protected string $type = 'Listener';

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

    protected function getOptions(): array
    {
        return [
            ['event', 'e', InputOption::VALUE_REQUIRED, 'The event class being listened for (registered in the service provider)'],
            ['queued', null, InputOption::VALUE_NONE, 'Indicates the event listener should be queued'],
            ...parent::getOptions(),
        ];
    }
}
