# Changelog

All notable changes to `nyoncode/laravel-package-console` are documented in this file.

## 1.1.0 - 2026-11-12

Anvil checks a package, asks for what it is missing and edits the package's settings.

### Added

- `package:doctor` compares the files of a package with what its service provider and
  `composer.json` register. It reports config, routes, migrations, seeders, views,
  translations, commands and view components that nothing registers, registrations of files
  and classes that do not exist, providers missing from `extra.laravel.providers` and classes
  that are not where PSR-4 expects them. `--fix` registers what is missing, the way the
  generators do; `--strict` fails on warnings; `--json` prints the findings. See
  [Checking a package](docs/doctor.md).
- Interactive mode with [Laravel Prompts](https://laravel.com/docs/prompts). A generator that
  is run without its name asks for it, validates it as it is submitted and then asks about its
  options: `make:model` offers the migration, factory, seeder, policy and controller,
  `make:controller` its type and model, `make:listener` the event, `make:observer` and
  `make:policy` the model, `make:test` the suite and the framework. Models and events of the
  package are suggested. `package:init` shows a summary and writes nothing before it is
  confirmed (`Ctrl+U` goes back a question), and asks for the Laravel versions.
  `package:doctor --fix` asks which findings to fix. Nothing is asked with `--no-interaction`;
  where prompts cannot be drawn (Windows without WSL, piped input) the same questions are
  asked as plain text. See [Interactive mode](docs/interactive.md).
- The questions are translated. English and Czech ship with Anvil; the language comes from
  `ANVIL_LANG` or the system locale. The output of the commands stays in English.
- `make:lang --locale=en,cs` creates the file for several locales at once.
- `package:doctor` compares the languages of a package: it warns about keys one language has
  and another lacks, and `--fix` adds them with the text of the language that has them. It
  also warns about translation keys the code uses that no language defines.
- `package:init` edits an existing package: every question is prefilled from `composer.json`,
  a changed answer is written (name, description, author, Laravel versions) and the summary
  shows what changes. Changing the Laravel versions rewrites the Laravel, Testbench and Pest
  constraints and raises PHP when needed, and warns when the CI matrix no longer matches.
  Without a terminal only what an option says is changed. See
  [Editing the package's settings](docs/package-init.md#editing-the-packages-settings).

### Changed

- On an existing package, `package:init` no longer asks for the namespace (it is never renamed;
  `--namespace` asking for another one is a warning), takes the Laravel versions it requires
  as the default instead of 12 and 13, and no longer adds git's email to an author that has
  none. `--author`, `--email` and `--laravel` now replace the current values, as `--name` and
  `--description` already did.
- `package:init` validates the root namespace and no longer guesses one that starts with a
  digit (`9lives/3d-tools` gives `Package9lives\Package3dTools`).
- Requires `laravel/prompts` and the `mbstring` extension.

### Fixed

- `--model` (`make:controller`, `make:factory`, `make:observer`, `make:policy`) and `--event`
  (`make:listener`) are validated as class names. A value such as `"Post Model"` or
  `"Admin/Class"` used to be written into the generated `use` statement and type.
- In a plain provider that loads its views or translations under a namespace other than the
  short name, they are now published under that namespace
  (`resource_path('views/vendor/laravel-blog')`), where Laravel looks for them.
- In a plain provider (and in the `packageBooted()`/`packageRegistered()` hooks of a Spatie one),
  a generator no longer registers a resource the provider already registers in its own words.
  Anvil used to look for the exact code it writes, so
  `$this->loadRoutesFrom(__DIR__ . '/../routes/api.php')` or
  `$this->loadMigrationsFrom(dirname(__DIR__).'/database/migrations')` got a second
  registration. It now reads the paths the provider registers: any spelling of the path, in any
  method, with `loadMigrationsFrom()` and `publishes()` standing in for `publishesMigrations()`
  and a published directory covering the files in it.

## 1.0.1 - 2026-11-07

Installing Anvil into an existing package is now safe.

### Added

- Support for providers built on `spatie/laravel-package-tools`. Generators add `->hasX()` calls
  to `configurePackage()` (`hasConfigFile`, `hasRoute`, `hasMigration`, `hasCommand`,
  `hasViews`, `hasTranslations`). Everything else goes into the `packageBooted()` hook.
  Translations go to `resources/lang`, and migrations are timeless.
- `package:init` finds a service provider that `extra.laravel.providers` does not list, and
  registers it instead of creating a second one.
- `package:init` adds `nyoncode/laravel-package-console` to `require-dev`, so `php anvil` works
  after `composer update`.

### Fixed

- Anvil no longer adds `register()` or `boot()` to a provider that extends a base class other
  than `Illuminate\Support\ServiceProvider`, which overrode the parent's methods. With a Spatie
  provider this stopped the package from booting. Anvil now adds to the methods the provider
  has, and prints the code for the rest.
- In a package that already has a provider, `package:init` no longer adds
  `nyoncode/laravel-package-toolkit` without `--toolkit`.
- `package:init` no longer writes files that clash with an existing setup:
  - `phpunit.xml`, which overrode `phpunit.xml.dist`;
  - a second CI workflow that runs the tests;
  - Pest files when `tests/TestCase.php` exists;
  - `phpstan.neon.dist`, `pint.json`, `LICENSE.md` or `README.md` when a variant exists.
- The short name follows the provider: the toolkit's `hasShortName()`/`name()`, and
  spatie's `name()`. Config files, view and translation namespaces and tags then match what
  the provider loads.

## 1.0.0 - 2026-11-06

### Added

- The console is now called **Anvil**:
  - `vendor/bin/anvil`, or `anvil` when installed globally;
  - `php anvil`, through the launcher that `package:init` writes into the package root.
- `package:init` (alias `init`): creates or completes `composer.json` for Laravel 12 and/or 13 and
  scaffolds the service provider, config, Pest + Testbench (with an `APP_KEY`), Larastan, Pint and
  a GitHub Actions matrix.
- Optional integration with `nyoncode/laravel-package-toolkit`: generators register their files as
  `->hasX()` calls in `configure()`, or as standard Laravel calls in a plain `ServiceProvider`.
  Registration is idempotent and leaves the rest of the provider untouched.
- Generators: `make:cast`, `make:channel`, `make:command` (alias `make:console`),
  `make:component`, `make:config`, `make:controller`, `make:enum`, `make:event`,
  `make:exception`, `make:facade`, `make:factory`, `make:interface`, `make:job`, `make:lang`,
  `make:listener`, `make:mail`, `make:middleware`, `make:migration`, `make:model`,
  `make:notification`, `make:observer`, `make:policy`, `make:provider`, `make:request`,
  `make:resource`, `make:route`, `make:rule`, `make:scope`, `make:seeder` (alias `make:seed`),
  `make:test`, `make:trait`, `make:view`.
- `package:info` shows what Anvil detected about the package.
- Stubs can be customised in the package's `stubs/anvil/` directory.
- Documentation in `docs/`.

### Changed

- Supports Symfony Console `^7.2|^8.0`.

### Removed

- `bin/pcli_setup` and the `package` launcher it copied. `package:init` writes the `anvil`
  launcher instead.
