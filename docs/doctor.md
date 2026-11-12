---
title: Checking a package
description: package:doctor compares the files of a package with what its service provider and composer.json register, and can register what is missing.
---

# Checking a package

- [What it is for](#what-it-is-for)
- [Running it](#running-it)
- [Fixing what it finds](#fixing-what-it-finds)
- [What it checks](#what-it-checks)
    - [Translations](#translations)
- [What it does not check](#what-it-does-not-check)
- [Errors, warnings and the exit code](#errors-warnings-and-the-exit-code)
- [JSON output](#json-output)

## What it is for

The generators register what they create. `package:doctor` looks at everything else: the files
a package already had before Anvil, the ones added by hand, and the registrations left behind
when a file was deleted. It answers two questions:

- is there a file in the package that the service provider does not load?
- does the service provider load something the package does not have?

Run it after installing Anvil into an existing package, and in CI.

## Running it

```bash
php anvil package:doctor
```

Take a package with this provider, a `config/blog.php`, `routes/web.php`, `routes/api.php`, a
view, a migration and a command in `src/Commands/Prune.php`:

```php title="src/BlogServiceProvider.php"
<?php

namespace Acme\Blog;

use Illuminate\Support\ServiceProvider;

class BlogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/blog.php', 'blog');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../routes/admin.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'laravel-blog');
        $this->loadMigrationsFrom($this->migrations);
    }
}
```

```
  ERROR config/blog.php is not registered in the service provider. Missing: publishes() in boot().
  ERROR routes/api.php is not registered in the service provider. Missing: loadRoutesFrom() in boot().
  ERROR resources/views is not registered in the service provider. Missing: publishes() in boot().
  ERROR src/Commands/Prune.php is not registered in the service provider. Missing: Prune::class in commands().
  ERROR routes/admin.php does not exist, but the service provider passes it to loadRoutesFrom().
  WARN  src/BlogServiceProvider.php loads the views as "laravel-blog::", but Anvil's short name for the package is "blog": generated code refers to "blog::".
  INFO  database/migrations/2026_01_01_000000_create_posts_table.php may not be registered: the service provider calls loadMigrationsFrom($this->migrations), which may or may not cover database/migrations.

  5 errors, 1 warning. Run package:doctor --fix to fix 4 of them.
```

A package with nothing to report prints one line and exits with 0:

```
  DONE The service provider and composer.json match the package.
```

## Fixing what it finds

```bash
php anvil package:doctor --fix
```

```
  FIXED config/blog.php: registered publishes() in boot().
  FIXED routes/api.php: registered loadRoutesFrom() in boot().
  FIXED resources/views: registered publishes() in boot().
  FIXED src/Commands/Prune.php: registered Prune::class in commands().
  ERROR routes/admin.php does not exist, but the service provider passes it to loadRoutesFrom().
  WARN  src/BlogServiceProvider.php loads the views as "laravel-blog::", but Anvil's short name for the package is "blog": generated code refers to "blog::".
  INFO  database/migrations/2026_01_01_000000_create_posts_table.php may not be registered: the service provider calls loadMigrationsFrom($this->migrations), which may or may not cover database/migrations.

  4 fixed, 1 error, 1 warning.
```

`--fix` registers a file exactly as the generator for it would, in the provider's own mode
(see [Service provider registration](service-provider.md)), so the provider above ends up with:

```php
        $this->loadMigrationsFrom($this->migrations);
        $this->publishes([
            __DIR__.'/../config/blog.php' => config_path('blog.php'),
        ], 'blog-config');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/laravel-blog'),
        ], 'blog-views');
        if ($this->app->runningInConsole()) {
            $this->commands([
                Prune::class,
            ]);
        }
```

It only ever adds:

- a registration to the service provider;
- the provider to `extra.laravel.providers` in `composer.json`, when the package has a provider
  that is not listed there;
- the keys a translation file lacks, see [Translations](#translations).

It never removes a registration and never touches another file. What is left after `--fix` is
yours to decide: delete the registration of a file that is gone, or restore the file. Running
`--fix` again changes nothing.

In a terminal, `--fix` first asks which of the findings to fix, with all of them selected:

```
 ┌ Which of these should be fixed? ───────────────────────────────────────────┐
 │   ◼ lang/cs/messages.php: added 2 missing keys with the text from en, t…   │
 │ › ◻ routes/web.php: registered loadRoutesFrom() in boot()                  │
 └────────────────────────────────────────────────────────────────────────────┘
  Space selects, Enter confirms.

  FIXED lang/cs/messages.php: added 2 missing keys with the text from en, to be translated.
  ERROR routes/web.php is not registered in the service provider. Missing: loadRoutesFrom() in boot().

  1 fixed, 1 error. Run package:doctor --fix to fix 1 of them.
```

With `-n` / `--no-interaction`, with `--json` and wherever there is no terminal (CI), it fixes
everything without asking. See [Interactive mode](interactive.md).

## What it checks

**Files the provider does not register** (errors, fixed by `--fix`):

| On disk | Expected registration |
|---|---|
| `config/*.php` | the one `make:config` adds |
| `routes/*.php` | the one `make:route` adds; `routes/channels.php` as broadcast channels |
| `database/migrations/*` | the one `make:migration` adds |
| `database/seeders/*` | the one `make:seeder` adds |
| any file in `resources/views` | the one `make:view` adds |
| any `.php` or `.json` file in `lang` (`resources/lang` with a Spatie provider) | the one `make:lang` adds |
| a class that extends `Illuminate\Console\Command`, directly or through a class of the package | the one `make:command` adds |
| `src/View/Components/*` (a warning) | a component namespace, as `make:component` adds |

A registration counts however it is written, as described under
[Plain mode](service-provider.md#plain-mode): `->hasRoutes()` without arguments covers every
route file, `loadMigrationsFrom()` covers the migrations, a published directory covers the
files in it. A route file that another route file requires is loaded through that one and is
left alone, and so are view components the provider registers with `Blade::component()`,
`loadViewComponentsAs()` or `hasComponent()`.

**Registrations of what the package does not have** (errors):

- a path passed to `mergeConfigFrom()`, `loadRoutesFrom()`, `loadViewsFrom()`,
  `loadTranslationsFrom()`, `loadJsonTranslationsFrom()`, `loadMigrationsFrom()`, `publishes()`,
  `publishesMigrations()` or `require` that does not exist;
- a file named in the toolkit's `hasConfig()`, `hasRoutes()`, `hasBroadcastChannels()`,
  `hasMigrations()` or `hasSeeders()`, or in Spatie's `hasConfigFile()`, `hasRoute()`,
  `hasRoutes()`, `hasMigration()` or `hasMigrations()`;
- a class of the package named as `Foo::class` in the provider whose file does not exist.

A call that discovers a directory (`->hasViews()`, `->hasRoutes()` without arguments) for a
directory that does not exist is a warning.

**composer.json**:

- a provider listed in `extra.laravel.providers` that does not exist (error);
- a provider in the package that is not listed, when none is (error, fixed by `--fix`);
- a class in a PSR-4 directory whose namespace or name does not match its path (error);
- a provider with `configure()` or `configurePackage()` when `composer.json` does not require
  `nyoncode/laravel-package-toolkit` or `spatie/laravel-package-tools` (warning).

**Names** (warnings):

- views or translations loaded under a namespace other than the
  [short name](how-it-works.md#the-short-name). `--fix` and the generators publish them under
  the namespace the provider uses;
- translations in `lang` when the provider is a Spatie one, which loads them from
  `resources/lang`.

### Translations

A package with more than one language is checked for keys that one language has and another
lacks (warnings, fixed by `--fix`):

```
  WARN  lang/cs/messages.php has no translation for 2 keys that en has: empty, actions.archive.
```

- PHP files are compared per file: `lang/cs/messages.php` with `lang/en/messages.php`, nested
  keys included. A file a language does not have at all is reported with the number of keys
  it should have.
- JSON files (`lang/cs.json`, `lang/en.json`) are compared with one another.
- `lang/vendor` is left alone, and so is a PHP file that is not a plain `return [...]` (the
  doctor reads the files, it does not run them) and a key that is a string in one language
  and an array in another.

`--fix` adds the missing keys **with the text of the language that has them**, English first,
in the formatting of the file:

```php title="lang/cs/messages.php"
<?php

return [

    'title' => 'Příspěvky',

    'actions' => [
        'publish' => 'Publikovat',
        'archive' => 'Archive',
    ],
    'empty' => 'No posts yet.',

];
```

It copies the text because an empty string would be shown as it is, while a key that is missing
falls back to the application's fallback language: an untranslated line reads better than a
blank one. After `--fix` the languages have the same keys, so what is left to translate is in
the diff of that run, not in the doctor's output.

The doctor also looks for keys of the package that the code uses and no language defines
(a warning, not fixable):

```
  WARN  blog::messages.subtitle is used in resources/views/posts/index.blade.php, but no translation file defines it.
```

It reads the literal keys passed to `__()`, `trans()`, `trans_choice()`, `@lang`, `@choice`,
`Lang::get()`, `Lang::choice()` and `Lang::has()` in the package's PSR-4 directories and in
`resources/views`, under the package's [short name](how-it-works.md#the-short-name)
(`blog::`). Keys that are built at runtime (`__('blog::messages.'.$key)`), JSON keys
(`__('Save')`) and keys of other namespaces are not checked.

## What it does not check

- Middleware aliases and event listeners: a file does not say which alias or event it is for.
- Whether the package boots. That is what the package's own test suite is for.
- Registrations whose path is not in the code. `$this->loadRoutesFrom($this->routes)` may or
  may not cover `routes/web.php`, so the doctor prints an `INFO` line and `--fix` leaves that
  kind of resource alone rather than register it twice.
- Calls nested in a closure, such as the toolkit's `whenLocal(fn (Packager $p) => $p->hasRoutes('dev.php'))`.
  Only the calls made directly on the `$packager` / `$package` variable are read, so a file
  registered only there is reported as unregistered.
- A toolkit or Spatie call it cannot extend, e.g. `->hasRoutes($this->routes())`: it prints a
  `WARN` with what to add by hand, as the [generators](troubleshooting.md#warn-add--by-hand) do.

## Errors, warnings and the exit code

| Line | Meaning | Exit code |
|---|---|---|
| `ERROR` | the package is broken as it stands: a file nothing loads, a registration of a file that is not there | 1 |
| `WARN` | probably not what you meant, or something to add by hand | 0, or 1 with `--strict` |
| `INFO` | something the doctor could not check | 0 |

In CI:

```bash
php anvil package:doctor --strict
```

## JSON output

`--json` prints the findings instead of the lines above, with the same exit code. For a plain
package whose only problem is an unregistered `routes/web.php`:

```json
{
    "errors": 1,
    "warnings": 0,
    "fixed": [],
    "findings": [
        {
            "severity": "error",
            "check": "unregistered",
            "subject": "routes/web.php",
            "message": "is not registered in the service provider. Missing: loadRoutesFrom() in boot().",
            "fix": "registered loadRoutesFrom() in boot()"
        }
    ]
}
```

`fix` is `null` for a finding `--fix` does nothing about. `check` is one of `unregistered`,
`manual`, `unknown`, `missing-file`, `missing-class`, `provider`, `psr-4`, `dependency`,
`short-name`, `translations`, `missing-translation` and `undefined-translation`. With `--fix`, `fixed` lists what was fixed and `findings` what
is left.
