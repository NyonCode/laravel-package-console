---
title: Service provider registration
description: Exactly what every generator adds to your service provider, with laravel-package-toolkit and with a plain Laravel ServiceProvider.
---

# Service provider registration

- [How registration works](#how-registration-works)
- [Toolkit mode](#toolkit-mode)
- [Plain mode](#plain-mode)
- [A complete package](#a-complete-package)
- [Messages](#messages)

## How registration works

After a generator writes its file, it registers the file in the
[main service provider](how-it-works.md#the-main-service-provider). In both modes:

- **Only what is missing is added.** Running the same generator again, or a second generator
  that needs the same registration (a view and a component both need the views), adds nothing.
- **Your code is left alone.** Anvil parses the provider with
  [nikic/php-parser](https://github.com/nikic/PHP-Parser) only to find positions. It then inserts
  text into the original file, so your formatting, comments and other code stay exactly as they
  are.
- **Imports are added for you.** A registration that names a class (`SyncPostsCommand::class`)
  adds the `use` statement in alphabetical order, unless the import already exists or the class
  is in the provider's own namespace.
- **The output reports it.** Each registration prints a `DONE` line. It prints a `WARN` line when
  Anvil cannot do it safely and you need to add it by hand.

The [mode](how-it-works.md#toolkit-mode-or-plain-mode) depends on whether the provider has a
`configure()` method.

## Toolkit mode

With [nyoncode/laravel-package-toolkit](https://github.com/NyonCode/laravel-package-toolkit),
generators add `->hasX()` calls to the `$packager` chain in `configure()`:

| Generator | Adds |
|---|---|
| `make:config` | `->hasConfig()` |
| `make:route web` (any name except `channels`) | `->hasRoutes(['web.php'])` |
| `make:route channels` | `->hasBroadcastChannels(['channels.php'])` |
| `make:migration`, `make:model -m` | `->hasMigrations()` |
| `make:seeder`, `make:model -s` | `->hasSeeders()` |
| `make:command` | `->hasCommands([SyncPostsCommand::class])` |
| `make:middleware --alias=blog.author` | `->hasMiddlewareAliases(['blog.author' => EnsureAuthor::class])` |
| `make:listener --event=PostPublished` | `->hasEvent(PostPublished::class, NotifySubscribers::class)` |
| `make:view`, `make:component`, `make:mail --markdown` / `--view` | `->hasViews()` |
| `make:component` (class-based) | `->hasComponentNamespace('blog', 'Acme\Blog\View\Components')` |
| `make:lang` | `->hasTranslations()` |

When the call is already there, Anvil works with it:

| The provider has | Anvil |
|---|---|
| no such call | appends the call to the chain |
| the call without arguments, e.g. `->hasRoutes()` | does nothing: the toolkit discovers every file in the directory |
| the call with an array, e.g. `->hasRoutes(['web.php'])` | adds the item: `->hasRoutes(['web.php', 'api.php'])` |
| the call with anything else, e.g. `->hasRoutes($this->routes())` | prints a `WARN` asking you to add it by hand |

Some details of how Anvil reads and extends the chain:

- **Multi-line arrays.** These keep their layout and their trailing comma:

  ```php
  ->hasRoutes([
      'web.php',
      'api.php',   // added
  ])
  ```

- **Several statements.** Anvil reads every statement in `configure()` that starts with
  `$packager`. If you split the configuration into several statements, nothing is registered
  twice. New calls go at the end of the last statement.
- **No chain yet.** When `configure()` contains no `$packager` call, Anvil starts one:
  `$packager->hasRoutes(['web.php']);`.
- **Equivalent calls.** Anvil recognises `->hasCommand(X::class)` as covering
  `->hasCommands([...])`, and `->hasEvents([...])` as covering `->hasEvent(...)` when both classes
  appear in it. It also recognises `->hasComponentNamespaces([...])` as covering
  `->hasComponentNamespace(...)`.

**Config files.** `->hasConfig()` without arguments merges and publishes every file in
`config/`. A second `make:config` therefore needs nothing more.

**Migrations.** With the toolkit, `make:migration` writes timeless file names such as
`create_posts_table.php`. The toolkit adds the timestamp when the migrations are published.

## Plain mode

With a plain `Illuminate\Support\ServiceProvider`, generators add the standard Laravel calls
(see [Package Development](https://laravel.com/docs/packages)). Paths are relative to the
provider's own directory, so a provider in `src/Providers/` gets `__DIR__.'/../../config/…'`.

| Generator | Adds to | Code |
|---|---|---|
| `make:config` | `register()` | `$this->mergeConfigFrom(__DIR__.'/../config/blog.php', 'blog');` |
| | `boot()` | `$this->publishes([__DIR__.'/../config/blog.php' => config_path('blog.php')], 'blog-config');` |
| `make:route web` | `boot()` | `$this->loadRoutesFrom(__DIR__.'/../routes/web.php');` |
| `make:route channels` | `boot()` | `require __DIR__.'/../routes/channels.php';` |
| `make:migration` | `boot()` | `$this->publishesMigrations([__DIR__.'/../database/migrations' => database_path('migrations')], 'blog-migrations');` |
| `make:seeder` | `boot()` | `$this->publishes([__DIR__.'/../database/seeders' => database_path('seeders')], 'blog-seeders');` |
| `make:command` | `boot()` | `$this->commands([SyncPostsCommand::class]);` inside `if ($this->app->runningInConsole())` |
| `make:middleware --alias=blog.author` | `boot()` | `$this->app['router']->aliasMiddleware('blog.author', EnsureAuthor::class);` |
| `make:listener --event=PostPublished` | `boot()` | `Event::listen(PostPublished::class, NotifySubscribers::class);` |
| `make:view`, `make:component`, `make:mail` | `boot()` | `$this->loadViewsFrom(__DIR__.'/../resources/views', 'blog');` |
| | `boot()` | `$this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/blog')], 'blog-views');` |
| `make:component` (class-based) | `boot()` | `Blade::componentNamespace('Acme\Blog\View\Components', 'blog');` |
| `make:lang` | `boot()` | `$this->loadTranslationsFrom(__DIR__.'/../lang', 'blog');` |
| | `boot()` | `$this->loadJsonTranslationsFrom(__DIR__.'/../lang');` |
| | `boot()` | `$this->publishes([__DIR__.'/../lang' => lang_path('vendor/blog')], 'blog-translations');` |

`publishes()` calls are written over several lines, as in the [complete example](#a-complete-package).

The details:

- **Each config file is registered separately**, under its file name as the key:
  `make:config blog-cache` adds `mergeConfigFrom(…, 'blog-cache')`. All config files share the
  `blog-config` publish tag.
- **Commands.** The first command creates the `runningInConsole()` block. Later commands are added
  to the same `$this->commands([...])` array, wherever that call is in `boot()`.
- **Missing methods.** If `register()` or `boot()` is missing, Anvil creates it.
- **The `//` placeholder.** A method that holds only `//` has it replaced by the first statement.
- **New code goes at the end** of the method, after your own code.

## A complete package

These are the providers of a package built with every generator: the
[full-package test](contributing.md#the-test-suites) compares them byte for byte on every run.

The toolkit provider:

```php title="src/BlogServiceProvider.php (toolkit)"
<?php

namespace Acme\Blog;

use Acme\Blog\Commands\Prune;
use Acme\Blog\Commands\SyncPostsCommand;
use Acme\Blog\Events\PostPublished;
use Acme\Blog\Http\Middleware\EnsureAuthor;
use Acme\Blog\Listeners\LogLogin;
use Acme\Blog\Listeners\NotifySubscribers;
use Illuminate\Auth\Events\Login;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;

class BlogServiceProvider extends PackageServiceProvider
{
    /**
     * Describe the package's resources.
     */
    public function configure(Packager $packager): void
    {
        $packager
            ->name('Blog')
            ->hasShortName('blog')
            ->hasConfig()
            ->hasRoutes(['web.php', 'api.php', 'admin.php'])
            ->hasBroadcastChannels(['channels.php'])
            ->hasMigrations()
            ->hasSeeders()
            ->hasCommands([SyncPostsCommand::class, Prune::class])
            ->hasMiddlewareAliases(['blog.author' => EnsureAuthor::class])
            ->hasEvent(PostPublished::class, NotifySubscribers::class)
            ->hasEvent(Login::class, LogLogin::class)
            ->hasViews()
            ->hasComponentNamespace('blog', 'Acme\Blog\View\Components')
            ->hasTranslations();
    }
}
```

The same package with a plain provider:

```php title="src/BlogServiceProvider.php (plain)"
<?php

namespace Acme\Blog;

use Acme\Blog\Commands\Prune;
use Acme\Blog\Commands\SyncPostsCommand;
use Acme\Blog\Events\PostPublished;
use Acme\Blog\Http\Middleware\EnsureAuthor;
use Acme\Blog\Listeners\LogLogin;
use Acme\Blog\Listeners\NotifySubscribers;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class BlogServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/blog.php', 'blog');
        $this->mergeConfigFrom(__DIR__.'/../config/blog-cache.php', 'blog-cache');
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/blog.php' => config_path('blog.php'),
        ], 'blog-config');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        require __DIR__.'/../routes/channels.php';
        $this->loadRoutesFrom(__DIR__.'/../routes/admin.php');
        $this->publishes([
            __DIR__.'/../config/blog-cache.php' => config_path('blog-cache.php'),
        ], 'blog-config');
        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'blog-migrations');
        $this->publishes([
            __DIR__.'/../database/seeders' => database_path('seeders'),
        ], 'blog-seeders');
        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncPostsCommand::class,
                Prune::class,
            ]);
        }
        $this->app['router']->aliasMiddleware('blog.author', EnsureAuthor::class);
        Event::listen(PostPublished::class, NotifySubscribers::class);
        Event::listen(Login::class, LogLogin::class);
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'blog');
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/blog'),
        ], 'blog-views');
        Blade::componentNamespace('Acme\Blog\View\Components', 'blog');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'blog');
        $this->loadJsonTranslationsFrom(__DIR__.'/../lang');
        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/blog'),
        ], 'blog-translations');
    }
}
```

Anvil appends each new statement where it can be found again, not where it would read best.
Once the package takes shape, regroup the statements however you like. Anvil still finds them.

In both modes, the [integration tests](contributing.md#integration-tests) install such a package
on Laravel 12 and 13 and check that every registration works in a running application.

## Messages

| Output | Meaning |
|---|---|
| `DONE Registered ->hasRoutes(['web.php']) in src/BlogServiceProvider.php.` | Toolkit: the call was appended |
| `DONE Registered 'api.php' in ->hasRoutes() in src/BlogServiceProvider.php.` | Toolkit: an item was added to an existing array |
| `DONE Registered loadRoutesFrom() in boot() in src/BlogServiceProvider.php.` | Plain: the statement was added to `boot()` |
| `DONE Registered SyncPostsCommand::class in commands() in src/BlogServiceProvider.php.` | Plain: the command was registered |
| `WARN Add 'api.php' to ->hasRoutes('web.php') in the service provider.` | Anvil could not extend the call; add the item by hand |
| `WARN No service provider found in composer.json "extra.laravel.providers". …` | There is no [main provider](how-it-works.md#the-main-service-provider) yet |
| *(nothing)* | It was already registered |
