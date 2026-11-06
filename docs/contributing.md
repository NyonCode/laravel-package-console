---
title: Contributing
description: How Anvil is built and tested, and how to add a generator.
---

# Contributing

- [Setup](#setup)
- [The test suites](#the-test-suites)
- [Integration tests](#integration-tests)
- [Adding a generator](#adding-a-generator)
- [Documentation](#documentation)

## Setup

```bash
git clone https://github.com/NyonCode/laravel-package-console.git
cd laravel-package-console
composer install
composer test
```

Run Anvil against any directory with `bin/anvil`:

```bash
mkdir /tmp/acme-blog && cd /tmp/acme-blog
/path/to/laravel-package-console/bin/anvil package:init
```

Anvil supports Symfony `^7.2|^8.0` and Pest `^3.8|^4.0`. Before a release, run the suite with the
lowest and the highest dependencies:

```bash
composer update --prefer-lowest && composer test
composer update && composer test
```

## The test suites

`composer test` runs the **Unit** and **Feature** suites in about a minute, without network
access. Every test works in a fresh temporary package created by the helpers in
`tests/Pest.php`:

| Helper | |
|---|---|
| `tempPackage(?array $composer)` | an empty directory, optionally with a `composer.json` |
| `initPackage(bool $toolkit, array $options)` | a package created with `package:init` |
| `anvil(string $dir, string $command, array $input)` | runs a command, returns `[exitCode, output]` |
| `buildFullPackage(string $dir)` | runs every generator, with the options a real package uses |
| `expectValidPhp(string $path)` | `php -l` |
| `expectPintClean(string $dir)` | Laravel Pint `--test` on the whole package |

| File | Covers |
|---|---|
| `tests/Feature/FullPackageTest.php` | a complete package in both modes: the exact file list, `php -l` and Pint on every file, the service providers compared byte for byte with `tests/Fixtures/*ServiceProvider.php`, and that running everything again changes nothing |
| `tests/Feature/CommandOptionsTest.php` | every option of every generator |
| `tests/Feature/ProviderRegistrationTest.php` | registration edge cases: existing calls, multi-line arrays, split `$packager` statements, missing methods, manual warnings |
| `tests/Feature/PackageInitTest.php` | `composer.json` per Laravel version, the CI matrix, existing packages, prompts |
| `tests/Unit/*` | `PhpFileEditor`, `Package`, `ComposerJson`, and the documentation |

When a change to a stub or to the registrar changes the providers on purpose, update the fixtures
in `tests/Fixtures/` and review the diff.

## Integration tests

```bash
composer test:integration
```

The integration suite proves that the generated code works in Laravel, not only that it looks
right. For the toolkit and for a plain provider, on Laravel 12 and on Laravel 13 (four packages),
it:

1. builds a complete package with every generator;
2. fills in a few stubs as an author would (a route, a translation, a JSON translation);
3. runs `composer update` and checks that the expected Laravel major version was installed;
4. runs the package's own Pest suite, including `tests/Fixtures/PackageRuntimeTest.stub`. In a
   real application, that test checks:
   - config merging;
   - commands;
   - web, api and custom routes;
   - the middleware alias;
   - listeners;
   - views, class, inline and anonymous components;
   - PHP and JSON translations;
   - mailables;
   - migrations and the factory;
   - every publish tag;
   - the facade and a job;
5. runs Larastan and Pint on the package.

It needs network access and takes about a minute and a half with a warm Composer cache.

## Adding a generator

1. **Add a class to `src/Commands/`** with `#[AsCommand]`. Commands there are discovered
   automatically.
   - A class generator extends `GenerateCommand` and sets `$type`, `$namespace` (relative to the
     root namespace) and `$stub`.
   - A file generator extends `FileGenerateCommand` and implements `getFilePath()`.
2. **Add the stub to `stubs/`.** Follow Laravel's own stub, and make sure it passes Pint's
   `laravel` preset and ends with a newline.
3. **Use the hooks you need:**
   - `getReplacements()` for extra placeholders;
   - `getImports()` for classes to import;
   - `getValidationRules()` for arguments, and for options keyed `--option`;
   - `afterGenerate()` for registration: call `$this->report($this->registrar()->…)`.
4. **Add it to `fullPackageCommands()`** in `tests/Pest.php`. The full-package tests then check
   the generated file in both modes, and the integration tests check it in Laravel.
5. **Document it** in the right page of `docs/`, then run `composer docs:commands`.

Registration must stay idempotent: running a generator twice must leave the provider unchanged.
`FullPackageTest` checks that for every generator.

## Documentation

The documentation is in `docs/` and uses the same format as the
[toolkit's](https://github.com/NyonCode/laravel-package-toolkit/tree/main/docs): one Markdown file
per page, each with `title` and `description` front matter.

- `docs/commands.md` contains a block generated from the command definitions. Regenerate it with
  `composer docs:commands`.
- `tests/Unit/DocumentationTest.php` fails when that block is out of date, when a link points to
  a missing page or anchor, or when a page lacks front matter.
