# Changelog

All notable changes to `nyoncode/laravel-package-console` are documented in this file.

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
