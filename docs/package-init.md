---
title: Creating a package
description: package:init creates or completes composer.json, the service provider, the config file and the test and tooling setup of a Laravel 12/13 package.
---

# Creating a package

- [package:init](#packageinit)
- [Options](#options)
- [What gets created](#what-gets-created)
- [The generated composer.json](#the-generated-composerjson)
- [Laravel versions](#laravel-versions)
- [Toolkit or plain service provider](#toolkit-or-plain-service-provider)
- [Running it on an existing package](#running-it-on-an-existing-package)
- [make:structure](#makestructure)

## package:init

```bash
anvil package:init
```

`package:init` (alias `init`) turns an empty directory, or an existing package, into a Laravel
12/13 package that you can install, test and publish. In a terminal it asks for what it needs,
one question at a time, each with a default you accept with Enter:

```
Creating a new Laravel package
==============================

 ┌ Composer package name ───────────────────────────────────────┐
 │ acme/laravel-blog                                            │
 └──────────────────────────────────────────────────────────────┘

 ┌ Description ─────────────────────────────────────────────────┐
 │ A Laravel package.                                           │
 └──────────────────────────────────────────────────────────────┘

 ┌ Root namespace ──────────────────────────────────────────────┐
 │ Acme\Blog                                                    │
 └──────────────────────────────────────────────────────────────┘

 ┌ Author name ─────────────────────────────────────────────────┐
 │ Jane Doe                                                     │
 └──────────────────────────────────────────────────────────────┘

 ┌ Author email ────────────────────────────────────────────────┐
 │ jane@example.com                                             │
 └──────────────────────────────────────────────────────────────┘

 ┌ Which service provider should the package have? ─────────────┐
 │ nyoncode/laravel-package-toolkit, with fluent ->hasX() calls │
 └──────────────────────────────────────────────────────────────┘

 ┌ Which Laravel versions should the package support? ──────────┐
 │ Laravel 12                                                   │
 │ Laravel 13                                                   │
 └──────────────────────────────────────────────────────────────┘

  Package           acme/laravel-blog
  Description       A Laravel package.
  Namespace         Acme\Blog
  Author            Jane Doe <jane@example.com>
  Service provider  nyoncode/laravel-package-toolkit
  Laravel           12, 13

 ┌ Create the package with these settings? ─────────────────────┐
 │ ● Yes / ○ No                                                 │
 └──────────────────────────────────────────────────────────────┘
```

Nothing is written before you confirm the summary, and `Ctrl+U` takes you back to the previous
question. See [Interactive mode](interactive.md) for the questions in another language and for
terminals that cannot draw prompts.

Every question has an option, and a question whose option you pass is not asked. So the command
also runs without prompts, for example in a script:

```bash
anvil package:init --name=acme/laravel-blog --toolkit --no-interaction
```

With `--no-interaction`, every value you leave out falls back to its default, and there is no
summary to confirm.

## Options

| Option | Default | |
|---|---|---|
| `--name=` | `{github.user}/{directory}` | Composer name, `vendor/name`, lowercase |
| `--description=` | `A Laravel package.` | |
| `--namespace=` | derived from the name | Root PHP namespace, e.g. `Acme\Blog` |
| `--author=` | `git config user.name` | |
| `--email=` | `git config user.email` | |
| `--toolkit` | see below | Use nyoncode/laravel-package-toolkit |
| `--no-toolkit` | | Use a plain Laravel `ServiceProvider` |
| `--laravel=` | `12,13` | Supported Laravel versions, comma separated |
| `--no-tests` | | Skip Pest, Testbench, `phpunit.xml` and the CI workflow |
| `--no-config` | | Do not create a config file |
| `-f`, `--force` | | Overwrite the scaffolded files that already exist |

**Defaults.**

- **Name.** The vendor is your `git config github.user` and the package name is the directory
  name, both in kebab case. Without `github.user` the vendor is `vendor`.
- **Namespace.** It is built from the vendor and the [short name](how-it-works.md#the-short-name):
  - `acme/laravel-blog` → `Acme\Blog`
  - `acme/laravel-blog-posts` → `Acme\BlogPosts`
  - `my-company/seo-tools` → `MyCompany\SeoTools`
- **Author.** Without git config, the author is `Your Name` and the email is left out.

The package name must be a valid lowercase Composer name, otherwise:

```
 [ERROR] The package name must be lowercase "vendor/name", e.g. "acme/laravel-blog".
```

## What gets created

For `acme/laravel-blog` with the toolkit:

```
laravel-blog/
├── .github/workflows/tests.yml   CI: PHP × Laravel matrix
├── .gitattributes                export-ignore for tests, tooling and the launcher
├── .gitignore
├── LICENSE.md                    MIT, with the year and the author
├── README.md                     installation, config publishing, testing
├── anvil                         the launcher: php anvil …
├── composer.json
├── config/blog.php               merged and publishable
├── phpstan.neon.dist             Larastan, level 5, src/
├── phpunit.xml                   with a random APP_KEY for HTTP tests
├── pint.json                     the "laravel" preset
├── src/BlogServiceProvider.php   the main service provider
├── testbench.yaml                registers the provider for Testbench
└── tests/
    ├── ArchTest.php              no dd(), dump(), ray(), var_dump()
    ├── Feature/ServiceProviderTest.php
    ├── Pest.php
    └── TestCase.php              Orchestra Testbench, loads the provider
```

The command prints each step:

```
  CREATE composer.json

  INFO Provider [src/BlogServiceProvider.php] created successfully.
  DONE Registered in composer.json "extra.laravel.providers".
  INFO Config [config/blog.php] created successfully.
  DONE Registered ->hasConfig() in src/BlogServiceProvider.php.
  CREATE anvil
  CREATE .gitignore
  …
  CREATE .github/workflows/tests.yml
```

In order, it:

1. writes `composer.json`;
2. runs [`make:provider --main`](classes.md#makeprovider), unless the package already has a main
   provider;
3. runs [`make:config`](config-and-routes.md#makeconfig), unless you pass `--no-config` or
   `config/{short-name}.php` exists;
4. writes the remaining files.

`--no-tests` leaves out `phpunit.xml`, `tests/` and the workflow. The `anvil` launcher,
`testbench.yaml`, `pint.json` and `phpstan.neon.dist` are always written.

Next, install the dependencies and run the tests:

```bash
composer update
vendor/bin/pest
```

## The generated composer.json

```json title="composer.json"
{
    "name": "acme/laravel-blog",
    "description": "A Laravel package.",
    "type": "library",
    "keywords": [
        "laravel",
        "blog"
    ],
    "license": "MIT",
    "authors": [
        {
            "name": "Jane Doe",
            "email": "jane@example.com",
            "role": "Developer"
        }
    ],
    "require": {
        "php": "^8.2",
        "illuminate/support": "^12.0|^13.0",
        "nyoncode/laravel-package-toolkit": "^2.5"
    },
    "require-dev": {
        "larastan/larastan": "^3.0",
        "laravel/pint": "^1.24",
        "nyoncode/laravel-package-console": "^1.0",
        "orchestra/testbench": "^10.0|^11.0",
        "pestphp/pest": "^3.8|^4.0",
        "pestphp/pest-plugin-laravel": "^3.2|^4.0"
    },
    "autoload": {
        "psr-4": {
            "Acme\\Blog\\": "src/",
            "Acme\\Blog\\Database\\Factories\\": "database/factories/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Acme\\Blog\\Tests\\": "tests/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "Acme\\Blog\\BlogServiceProvider"
            ]
        }
    },
    "scripts": {
        "test": "vendor/bin/pest",
        "format": "vendor/bin/pint",
        "analyse": "vendor/bin/phpstan analyse"
    },
    "config": {
        "sort-packages": true,
        "allow-plugins": {
            "pestphp/pest-plugin": true
        }
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
```

- The provider is listed in `extra.laravel.providers`, so Laravel's
  [package discovery](https://laravel.com/docs/packages#package-discovery) registers it in the
  application.
- Factories get their own PSR-4 entry so they can live in `database/factories`. See
  [make:factory](database.md#makefactory).
- Without the toolkit, `nyoncode/laravel-package-toolkit` is not required.
- Anvil itself is in `require-dev`, so the `anvil` launcher works as soon as `composer update` has
  run.

## Laravel versions

`--laravel` takes `12`, `13` or both (`12,13`, the default for a new package; an existing one
keeps the versions it requires). The constraints follow from the versions you choose:

| | Laravel 12 | Laravel 13 | Laravel 12 + 13 |
|---|---|---|---|
| `php` | `^8.2` | `^8.3` | `^8.2` |
| `illuminate/support` | `^12.0` | `^13.0` | `^12.0\|^13.0` |
| `orchestra/testbench` | `^10.0` | `^11.0` | `^10.0\|^11.0` |
| `pestphp/pest` | `^3.8\|^4.0` | `^4.0` | `^3.8\|^4.0` |
| `pestphp/pest-plugin-laravel` | `^3.2\|^4.0` | `^4.0` | `^3.2\|^4.0` |
| CI PHP versions | 8.2 – 8.5 | 8.3 – 8.5 | 8.2 – 8.5, without 8.2 on Laravel 13 |

Any other version is rejected:

```
 [ERROR] Supported Laravel versions: 12, 13.
```

The CI workflow for Laravel 12 + 13:

```yaml title=".github/workflows/tests.yml"
    strategy:
      fail-fast: false
      matrix:
        php: ['8.2', '8.3', '8.4', '8.5']
        laravel: ['12.*', '13.*']
        include:
          - laravel: '12.*'
            testbench: '10.*'
          - laravel: '13.*'
            testbench: '11.*'
        exclude:
          - laravel: '13.*'
            php: '8.2'
```

Each job requires `laravel/framework` and `orchestra/testbench` at the matrix versions, then runs
`vendor/bin/pest --ci`.

## Toolkit or plain service provider

| | `--toolkit` | `--no-toolkit` |
|---|---|---|
| Dependency | `nyoncode/laravel-package-toolkit: ^2.5` | none |
| Provider | extends `PackageServiceProvider`, has `configure(Packager $packager)` | extends `Illuminate\Support\ServiceProvider`, has `register()` and `boot()` |
| Registration | `->hasX()` calls | `mergeConfigFrom()`, `loadRoutesFrom()`, `publishes()`, … |
| Publish tags | `blog::config` | `blog-config` |
| Migrations | timeless (`create_posts_table.php`) | timestamped |

For a **new package**, one without a service provider, `package:init` asks when you pass neither
option. Without a prompt (`--no-interaction`), it uses the toolkit.

A package that **already has a provider** keeps its dependencies. The provider's own style
decides how generators register, so `package:init` does not ask and adds the toolkit only with
an explicit `--toolkit`. In that case it warns that the existing provider keeps its style:

```
  WARN src/BlogServiceProvider.php is not a laravel-package-toolkit provider, so generators keep registering in its own style.
```

The two service providers `package:init` creates:

```php title="src/BlogServiceProvider.php (toolkit)"
<?php

namespace Acme\Blog;

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
            ->hasConfig();
    }
}
```

```php title="src/BlogServiceProvider.php (plain)"
<?php

namespace Acme\Blog;

use Illuminate\Support\ServiceProvider;

class BlogServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/blog.php', 'blog');
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/blog.php' => config_path('blog.php'),
        ], 'blog-config');
    }
}
```

Every later `make:*` command [registers its file](service-provider.md) in this provider.

## Running it on an existing package

`package:init` is safe to run on a package that already exists, whether its provider is a
plain Laravel `ServiceProvider`, a toolkit provider or a
[spatie/laravel-package-tools](https://github.com/spatie/laravel-package-tools) provider.
Afterwards, [`package:doctor`](doctor.md) shows which of the package's files the provider
does not register yet.

```bash
composer require --dev nyoncode/laravel-package-console
vendor/bin/anvil package:init
```

```
  UPDATE composer.json

  DONE Found src/BlogServiceProvider.php and registered Acme\Blog\BlogServiceProvider in composer.json "extra.laravel.providers".
  INFO Config [config/blog.php] created successfully.
  DONE Registered ->hasConfigFile() in src/BlogServiceProvider.php.
  …
  SKIP   phpunit.xml (phpunit.xml.dist exists)
  …
  SKIP   .github/workflows/tests.yml (.github/workflows/run-tests.yml exists)

  🎉  Package ready (with spatie/laravel-package-tools).
```

### The service provider

**Your provider is kept.** `package:init` creates a provider only when the package has none.
Generators then register in your provider, in [its own style](how-it-works.md#registration-modes).

**A provider that composer.json does not list is found.** When `extra.laravel.providers` is
missing or names no existing class, `package:init` looks for a class in the package's source
whose parent class name ends in `ServiceProvider`:

- `{Package}ServiceProvider` in the root namespace is preferred;
- otherwise the least nested class wins, then the first in alphabetical order.

The class it finds goes first in `extra.laravel.providers`, so Laravel's package discovery loads
it. No second provider is created.

### Editing the package's settings

Run in a terminal, `package:init` on an existing package is also the way to change its
settings. Every question is prefilled from `composer.json`, so Enter keeps a value and a new
answer replaces it. The summary shows what changes:

```
  Package           acme/laravel-blog
  Description       A blog. → A blog for Laravel.
  Namespace         Acme\Blog
  Author            Jane Doe <jane@example.com>
  Service provider  nyoncode/laravel-package-toolkit
  Laravel           13 → 12, 13
```

| Setting | Prefilled from | When it changes |
|---|---|---|
| Package name | `name` | `name` is replaced |
| Description | `description` | `description` is replaced |
| Author name and email | the first entry of `authors` | its `name` and `email` are replaced, its other keys (`role`, `homepage`) are kept |
| Laravel versions | `illuminate/support` (or another `illuminate/*` package, or `laravel/framework`) | the constraints below are rewritten |
| Namespace | the PSR-4 entry for `src/` | not asked: renaming it would break every class of the package |
| Service provider | the provider | not asked: generators register in the provider's own style |

When the Laravel versions change, the `illuminate/*` and `laravel/framework` constraints in
`require` and `orchestra/testbench`, `pestphp/pest` and `pestphp/pest-plugin-laravel` in
`require-dev` are set as in [Laravel versions](#laravel-versions). `php` is only raised, when it
allows a PHP version the new Laravel versions do not support (`^8.2` becomes `^8.3` when
Laravel 12 is dropped); a package that requires a newer PHP keeps its constraint. The CI
workflow is not rewritten, because you may have edited it. When its matrix no longer matches,
`package:init` says so:

```
  WARN The CI matrix in .github/workflows/tests.yml does not match the new Laravel versions. Update it by hand, or delete it and run package:init again.
```

With `--no-interaction` nothing is asked, so nothing that is there changes, except what an
option says: `--description`, `--author`, `--email`, `--name` and `--laravel` replace the
current values as an answer would. `--namespace` does not rename an existing namespace:

```
  WARN Kept the namespace Acme\Blog from composer.json: package:init does not rename the namespace of existing classes.
```

### composer.json

Apart from the settings above, `package:init` **completes** the file and keeps what is there:

- `type`, `keywords`, `license`, `authors`, `minimum-stability`, `prefer-stable` and the `config`
  settings are only added when they are missing. Git's `user.name` and `user.email` fill in an
  author `composer.json` does not have, not the email of one it has.
- Packages in `require`, `require-dev` and `scripts` are only added when they are missing. An
  existing constraint, such as `"orchestra/testbench": "^10.5"`, is kept unless the Laravel
  versions change.
- If an existing PSR-4 entry maps a namespace to `src/`, Anvil keeps it as the root namespace and
  only adds the factories entry.
- The toolkit is only added with `--toolkit` (see
  [Toolkit or plain service provider](#toolkit-or-plain-service-provider)).
- The file is saved with 4-space indentation and the top-level keys in Composer's usual order.

### Files

Files that exist are skipped:

```
  SKIP   README.md (exists)
```

So are files that would clash with what the package already has:

| Not written | When the package has | Why |
|---|---|---|
| `phpunit.xml` | `phpunit.xml.dist` or `phpunit.dist.xml` | PHPUnit would prefer `phpunit.xml` and ignore yours |
| `.github/workflows/tests.yml` | a workflow that runs `pest`, `phpunit` or `composer test` | the tests would run twice |
| `tests/Pest.php`, `tests/ArchTest.php`, `tests/Feature/ServiceProviderTest.php` | `tests/TestCase.php` | they assume Anvil's `TestCase`; your test setup stays as it is |
| `phpstan.neon.dist` | `phpstan.neon` or `phpstan.dist.neon` | |
| `pint.json` | `.pint.json` | |
| `LICENSE.md` | `LICENSE`, `LICENSE.txt` or `license.md` | |
| `README.md` | `readme.md`, `README` or `README.rst` | |

`--force` overwrites the scaffolded files, including the ones above. It does not touch the
existing service provider or the config file.

## make:structure

```bash
php anvil make:structure
```

`make:structure` creates the standard directory layout of a Laravel package and skips
directories that already exist:

```
  EXISTS config
  CREATE database/factories
  CREATE database/migrations
  CREATE database/seeders
  CREATE lang
  CREATE resources/views
  CREATE routes
  EXISTS src
  EXISTS tests/Feature
  CREATE tests/Unit

  🎉  Created 7 directories.
```

When every directory already exists it says `Package structure is complete.` instead. You rarely need it, because every generator creates the directories it writes to.
