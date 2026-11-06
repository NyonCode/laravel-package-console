<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Validator\Constraints as Assert;

use function Symfony\Component\String\u;

/**
 * With laravel-package-toolkit, migrations are timeless ("create_posts_table.php"); the
 * toolkit adds the timestamp when they are published. Otherwise they get the usual
 * timestamp prefix and are published with publishesMigrations().
 */
#[AsCommand(name: 'make:migration', description: 'Create a new migration file')]
final class MigrationMakeCommand extends FileGenerateCommand
{
    protected string $type = 'Migration';

    private const CREATE_PATTERNS = ['/^create_(\w+)_table$/', '/^create_(\w+)$/'];

    private const CHANGE_PATTERNS = ['/.+_(to|from|in)_(\w+)_table$/', '/.+_(to|from|in)_(\w+)$/'];

    public function handle(): int
    {
        $name = $this->getNameInput();

        if (($existing = $this->existingMigration($name)) !== null && ! $this->option('force')) {
            $this->error("A migration named [$name] already exists: database/migrations/$existing");

            return self::FAILURE;
        }

        return parent::handle();
    }

    public function getStub(): string
    {
        [$table, $create] = $this->table();

        return match (true) {
            $table === null => 'migration.stub',
            $create => 'migration.create.stub',
            default => 'migration.update.stub',
        };
    }

    protected function getNameInput(): string
    {
        $name = parent::getNameInput();
        $name = str_ends_with($name, '.php') ? substr($name, 0, -4) : $name;

        return u($name)->snake()->toString();
    }

    protected function getFilePath(string $name): string
    {
        $timeless = $this->package()->usesToolkit() && ! $this->option('timestamp');

        return $this->package()->path(
            'database/migrations/'.($timeless ? '' : date('Y_m_d_His').'_').$name.'.php'
        );
    }

    protected function getReplacements(string $name): array
    {
        return ['table' => $this->table()[0] ?? ''];
    }

    protected function afterGenerate(string $name, string $path): void
    {
        $this->report($this->registrar()->migrations(basename($path)));
    }

    /**
     * The table from --create / --table, or guessed from the name like Laravel does.
     *
     * @return array{0: ?string, 1: bool} [table, create]
     */
    private function table(): array
    {
        if ($this->option('create')) {
            return [$this->option('create'), true];
        }

        if ($this->option('table')) {
            return [$this->option('table'), false];
        }

        $name = $this->getNameInput();

        foreach (self::CREATE_PATTERNS as $pattern) {
            if (preg_match($pattern, $name, $matches)) {
                return [$matches[1], true];
            }
        }

        foreach (self::CHANGE_PATTERNS as $pattern) {
            if (preg_match($pattern, $name, $matches)) {
                return [$matches[2], false];
            }
        }

        return [null, false];
    }

    private function existingMigration(string $name): ?string
    {
        $directory = $this->package()->path('database/migrations');

        if (! is_dir($directory)) {
            return null;
        }

        foreach ((new Finder)->files()->in($directory)->depth(0)->name('*.php') as $file) {
            $basename = (string) preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $file->getFilename());

            if ($basename === $name.'.php') {
                return $file->getFilename();
            }
        }

        return null;
    }

    protected function getValidationRules(): array
    {
        return [
            ...parent::getValidationRules(),
            '--create' => [self::tableRule()],
            '--table' => [self::tableRule()],
        ];
    }

    private static function tableRule(): Assert\Regex
    {
        return new Assert\Regex(
            pattern: '/^[A-Za-z_][A-Za-z0-9_.]*$/',
            message: 'The table name may only contain letters, numbers, "_" and ".".',
        );
    }

    protected function getOptions(): array
    {
        return [
            ['create', null, InputOption::VALUE_REQUIRED, 'The table to be created'],
            ['table', null, InputOption::VALUE_REQUIRED, 'The table to migrate'],
            ['timestamp', null, InputOption::VALUE_NONE, 'Prefix the file name with a timestamp even when using the toolkit'],
            ...parent::getOptions(),
        ];
    }
}
