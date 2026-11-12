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
| `tests/Feature/FullPackageTest.php` | a complete package in all three modes: the exact file list, `php -l` and Pint on every file, the service providers compared byte for byte with `tests/Fixtures/*ServiceProvider.php`, and that running everything again changes nothing |
| `tests/Feature/CommandOptionsTest.php` | every option of every generator |
| `tests/Feature/ProviderRegistrationTest.php` | registration edge cases: existing calls, multi-line arrays, split `$packager` statements, missing methods, manual warnings |
| `tests/Feature/ExistingPackageTest.php` | existing packages: spatie/laravel-package-tools providers, providers with a parent class, providers composer.json does not list, short names from the provider, test setup and CI that must not be overridden |
| `tests/Feature/DoctorTest.php` | `package:doctor`: a generated package has nothing to report, every kind of unregistered file, registrations of missing files and classes, `composer.json`, `--fix`, `--strict`, `--json` |
| `tests/Feature/PackageInitTest.php` | `composer.json` per Laravel version, the CI matrix, existing packages, prompts |
| `tests/Unit/*` | `PhpFileEditor`, `Package`, `ComposerJson`, and the documentation |

When a change to a stub or to the registrar changes the providers on purpose, update the fixtures
in `tests/Fixtures/` and review the diff.

## Integration tests

```bash
composer test:integration
```

The integration suite proves that the generated code works in Laravel, not only that it looks
right. For three providers, on Laravel 12 and on Laravel 13 (six packages), it:

- a new package with the toolkit;
- a new package with a plain provider;
- an existing package built on spatie/laravel-package-tools.

For each of them, it:

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
   - `afterGenerate()` for registration: call `$this->report($this->registrar()->…)`;
   - `$example` for the placeholder of the name question, and
     `afterPromptingForMissingArguments()` to ask about options with `$this->prompts()`. Wrap
     every question in `$this->trans()` and add it to each file in `lang/`.
4. **Add it to `fullPackageCommands()`** in `tests/Pest.php`. The full-package tests then check
   the generated file in both modes, and the integration tests check it in Laravel.
5. **Document it** in the right page of `docs/`, then run `composer docs:commands`.

Registration must stay idempotent: running a generator twice must leave the provider unchanged.
`FullPackageTest` checks that for every generator.

## Questions and languages

Commands ask through `$this->prompts()` (`Support/Prompts`), never through Laravel Prompts or
Symfony's question helper directly. It answers with the default when the run is not
interactive, draws a prompt in a terminal and falls back to a plain question elsewhere, so a
command is written once for all three.

- Ask only for what was not given as an argument or option, and never from a command that
  another command calls.
- Pass every label, hint and choice through `$this->trans('The English text')`. The English
  text is the key.
- Add the line to every `lang/*.php`. A test in `tests/Feature/InteractiveTest.php` fails when
  a language lacks a question.
- Do not translate what a command prints: output stays in English.

To add a language, copy `lang/cs.php` to `lang/{language}.php` and translate the values. The
language is picked up from the file name: `ANVIL_LANG=de` uses `lang/de.php`.

Test a question with `anvilPrompting()`, which answers prompts with key presses, and with
`anvilAnswering()` where the plain fallback matters:

```php
[$code, $output, $prompts] = anvilPrompting($dir, 'make:model', [], keys('Post', Key::ENTER, Key::ENTER));
```

## Documentation

The documentation is in `docs/` and uses the same format as the
[toolkit's](https://github.com/NyonCode/laravel-package-toolkit/tree/main/docs): one Markdown file
per page, each with `title` and `description` front matter.

- `docs/commands.md` contains a block generated from the command definitions. Regenerate it with
  `composer docs:commands`.
- `tests/Unit/DocumentationTest.php` fails when that block is out of date, when a link points to
  a missing page or anchor, or when a page lacks front matter.
