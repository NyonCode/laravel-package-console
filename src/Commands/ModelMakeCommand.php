<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Doctrine\Inflector\InflectorFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:model', description: 'Create a new Eloquent model class')]
final class ModelMakeCommand extends GenerateCommand
{
    protected string $type = 'Model';

    protected string $example = 'Post';

    protected string $namespace = 'Models';

    /**
     * Words Laravel's Pluralizer keeps singular on top of doctrine/inflector's rules.
     */
    private const UNCOUNTABLE = ['recommended', 'related'];

    public function getStub(): string
    {
        return $this->wants('factory') ? 'model.factory.stub' : 'model.stub';
    }

    protected function getReplacements(string $name): array
    {
        return ['factory' => $this->classBasename($name).'Factory'];
    }

    protected function getImports(string $class): array
    {
        return $this->wants('factory')
            ? [$this->packageRootNamespace().'\\Database\\Factories\\'.$this->classBasename($class).'Factory']
            : [];
    }

    /**
     * Generate the related classes requested with -m, -f, -s, -c, -p or --all.
     */
    protected function afterGenerate(string $class, string $path): void
    {
        $model = $this->classBasename($class);
        $force = $this->option('force') ? ['--force' => true] : [];

        if ($this->wants('factory')) {
            $this->call('make:factory', ['name' => $model.'Factory', '--model' => $class, ...$force]);
        }

        if ($this->wants('migration')) {
            $table = $this->tableName($model);
            $this->call('make:migration', ['name' => "create_{$table}_table", '--create' => $table, ...$force]);
        }

        if ($this->wants('seed')) {
            $this->call('make:seeder', ['name' => $model.'Seeder', ...$force]);
        }

        if ($this->wants('controller') || $this->wants('resource') || $this->option('api')) {
            $this->call('make:controller', array_filter([
                'name' => $model.'Controller',
                '--model' => $this->wants('resource') || $this->option('api') ? $class : null,
                '--api' => (bool) $this->option('api'),
                ...$force,
            ]));
        }

        if ($this->wants('policy')) {
            $this->call('make:policy', ['name' => $model.'Policy', '--model' => $class, ...$force]);
        }
    }

    /**
     * Like Artisan: a model created by answering the name question is offered its companions.
     */
    protected function afterPromptingForMissingArguments(): void
    {
        foreach (['all', 'migration', 'factory', 'seed', 'controller', 'resource', 'api', 'policy'] as $option) {
            if ($this->option($option)) {
                return;
            }
        }

        $chosen = $this->prompts()->multiselect($this->trans('Would you like any of the following?'), [
            'migration' => $this->trans('Migration'),
            'factory' => $this->trans('Factory'),
            'seed' => $this->trans('Seeder'),
            'policy' => $this->trans('Policy'),
            'controller' => $this->trans('Controller'),
            'resource' => $this->trans('Resource controller'),
            'api' => $this->trans('API resource controller'),
        ]);

        foreach ($chosen as $option) {
            $this->input()->setOption((string) $option, true);
        }
    }

    private function wants(string $option): bool
    {
        return (bool) $this->option($option)
            || ($this->option('all') && in_array($option, ['factory', 'migration', 'seed', 'resource', 'policy'], true));
    }

    /**
     * The table name Laravel's make:model uses: Str::snake(Str::pluralStudly($model)),
     * e.g. "BlogPost" -> "blog_posts", "Person" -> "people".
     */
    private function tableName(string $model): string
    {
        $words = preg_split('/(.)(?=[A-Z])/u', $model, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$model];
        $last = array_pop($words);
        $plural = in_array(strtolower($last), self::UNCOUNTABLE, true)
            ? $last
            : InflectorFactory::create()->build()->pluralize($last);

        return strtolower((string) preg_replace('/(.)(?=[A-Z])/u', '$1_', implode('', $words).$plural));
    }

    protected function getOptions(): array
    {
        return [
            ['all', 'a', InputOption::VALUE_NONE, 'Generate a migration, seeder, factory, policy and resource controller for the model'],
            ['migration', 'm', InputOption::VALUE_NONE, 'Create a new migration file for the model'],
            ['factory', 'f', InputOption::VALUE_NONE, 'Create a new factory for the model'],
            ['seed', 's', InputOption::VALUE_NONE, 'Create a new seeder for the model'],
            ['controller', 'c', InputOption::VALUE_NONE, 'Create a new controller for the model'],
            ['resource', 'r', InputOption::VALUE_NONE, 'Indicates if the generated controller should be a resource controller'],
            ['api', null, InputOption::VALUE_NONE, 'Indicates if the generated controller should be an API resource controller'],
            ['policy', 'p', InputOption::VALUE_NONE, 'Create a new policy for the model'],
            ['force', null, InputOption::VALUE_NONE, 'Create the class even if the model already exists'],
        ];
    }
}
