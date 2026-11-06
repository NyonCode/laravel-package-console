# Changelog

All notable changes to `nyoncode/laravel-package-console` are documented in this file.

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
