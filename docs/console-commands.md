---
title: Console commands
description: make:command creates an Artisan command for your package and registers it, prefixed with the package's short name.
---

# Console commands

## make:command

```bash
php anvil make:command SyncPostsCommand              # signature blog:sync-posts
php anvil make:command Prune --command=blog:prune
php anvil make:console Install                       # alias
```

| Argument / option | |
|---|---|
| `name` | The class name, created in `src/Commands/` |
| `--command=` | The command's signature. By default it is `{short-name}:{class name in kebab case, without "Command"}`. |
| `-f`, `--force` | Overwrite an existing file |

The default signature is prefixed with the package's [short name](how-it-works.md#the-short-name)
so it doesn't clash with other commands:

| Class | Signature |
|---|---|
| `SyncPostsCommand` | `blog:sync-posts` |
| `Prune` | `blog:prune` |
| `Admin/ResetCommand` | `blog:reset` |

```php title="src/Commands/SyncPostsCommand.php"
<?php

namespace Acme\Blog\Commands;

use Illuminate\Console\Command;

class SyncPostsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blog:sync-posts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        return self::SUCCESS;
    }
}
```

`--command` takes a full signature, with arguments and options:

```bash
php anvil make:command Install --command="blog:install {--force : Overwrite existing files}"
```

The signature may not contain `'` or `\`, because it is written into a single-quoted string.

**Registration**

| Toolkit | Spatie | Plain |
|---|---|---|
| `->hasCommands([SyncPostsCommand::class])`; later commands are added to the array | `->hasCommand(SyncPostsCommand::class)`, one call per command | `$this->commands([SyncPostsCommand::class]);` inside `if ($this->app->runningInConsole())` in `boot()`; later commands are added to the array |

The class is imported into the provider. With the toolkit:

- A `->hasCommands()` call without arguments discovers every command in `src/Commands`, so Anvil
  adds nothing.
- A command already registered with `->hasCommand(SyncPostsCommand::class)` is not added again.

```php title="src/BlogServiceProvider.php (plain)"
if ($this->app->runningInConsole()) {
    $this->commands([
        SyncPostsCommand::class,
        Prune::class,
    ]);
}
```
