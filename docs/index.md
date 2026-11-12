---
title: Overview
description: Anvil is an Artisan-like console for developing Laravel packages. It scaffolds the package, generates every kind of file and registers it in your service provider.
---

# Anvil

Anvil is `php artisan make:*` for people who build **Laravel packages**. It supports
**Laravel 12 and 13**.

```bash
php anvil make:model Post -a
```

```
  INFO Model [src/Models/Post.php] created successfully.
  INFO Factory [database/factories/PostFactory.php] created successfully.
  INFO Migration [database/migrations/create_posts_table.php] created successfully.
  DONE Registered ->hasMigrations() in src/BlogServiceProvider.php.
  INFO Seeder [database/seeders/PostSeeder.php] created successfully.
  DONE Registered ->hasSeeders() in src/BlogServiceProvider.php.
  INFO Controller [src/Http/Controllers/PostController.php] created successfully.
  INFO Policy [src/Policies/PostPolicy.php] created successfully.
```

Artisan's generators assume an application: an `App\` namespace and an `app/` directory, and
config, routes and views that are loaded for you. A package has none of that. A config file does
nothing until the service provider merges it. A migration does nothing until the provider
publishes it. A command does nothing until the provider registers it.

Anvil closes that gap. Every generator does two things:

1. It **writes the file** where the package's PSR-4 autoloading expects it, in the package's
   namespace, from stubs that follow Laravel 12/13's own.
2. It **registers the file in your service provider**: it adds `->hasMigrations()` to your
   [toolkit](https://github.com/NyonCode/laravel-package-toolkit) configuration, or
   `$this->publishesMigrations(...)` to the `boot()` method of a plain Laravel `ServiceProvider`.

Registration edits your provider in place. It adds only what is missing and never reformats
code you wrote, so running a command twice changes nothing.

## What's included

- **`package:init`** creates `composer.json` (or completes yours) and scaffolds the package:
  - a service provider and a config file;
  - Pest + Testbench, Larastan, Pint;
  - a GitHub Actions matrix for PHP × Laravel;
  - the `anvil` launcher.
- **34 `make:*` commands**: models, migrations, factories, seeders, controllers, middleware,
  routes, config, views, Blade components, translations, mailables, jobs, events, listeners,
  console commands, tests and more. See the [command reference](commands.md).
- **Three registration modes**, chosen automatically from your service provider:
  - [nyoncode/laravel-package-toolkit](https://github.com/NyonCode/laravel-package-toolkit), with
    `->hasX()` calls in `configure(Packager $packager)`;
  - [spatie/laravel-package-tools](https://github.com/spatie/laravel-package-tools), with
    `->hasX()` calls in `configurePackage(Package $package)`;
  - a plain `Illuminate\Support\ServiceProvider`, with the standard Laravel calls in
    `register()`/`boot()`.
- **Existing packages welcome**: `package:init` completes what is there and keeps your
  provider, test setup and CI, and [`package:doctor`](doctor.md) checks that the provider
  registers what the package has and that its languages have the same keys.
- **Questions instead of errors**: in a terminal, a command that is missing its name asks for
  it and for the options that go with it, [in English or Czech](interactive.md#language).
- **Your own stubs**: put a copy of any stub in `stubs/anvil/` in your package and Anvil uses it
  instead of the built-in one.

## Documentation

**Prologue**

- [Installation](installation.md)
- [Quickstart](quickstart.md)

**The package**

- [Creating a package](package-init.md): `package:init`, `make:structure`
- [How Anvil reads your package](how-it-works.md): namespaces, the short name, the main provider, `package:info`
- [Service provider registration](service-provider.md): exactly what each generator adds, in toolkit, spatie and plain mode
- [Checking a package](doctor.md): `package:doctor` finds files the provider does not register and registrations of files that are gone

**Generators**

- [Generators: the basics](generators.md): naming, nesting, `--force`, validation, output
- [Interactive mode](interactive.md): what Anvil asks in a terminal, `--no-interaction`, the language of the questions
- [Config & routes](config-and-routes.md): `make:config`, `make:route`
- [Database & Eloquent](database.md): `make:model`, `make:migration`, `make:factory`, `make:seeder`, `make:cast`, `make:scope`, `make:observer`
- [HTTP](http.md): `make:controller`, `make:middleware`, `make:request`, `make:resource`
- [Views & translations](views-and-translations.md): `make:view`, `make:component`, `make:lang`
- [Console commands](console-commands.md): `make:command`
- [Events, queues & mail](events-queues-and-mail.md): `make:event`, `make:listener`, `make:job`, `make:notification`, `make:mail`, `make:channel`
- [Classes](classes.md): `make:class`, `make:interface`, `make:trait`, `make:enum`, `make:exception`, `make:facade`, `make:rule`, `make:policy`, `make:provider`
- [Tests](testing.md): `make:test` and the test setup `package:init` creates

**Reference**

- [Customising stubs](stubs.md)
- [Command reference](commands.md)
- [Troubleshooting](troubleshooting.md)
- [Contributing](contributing.md)
