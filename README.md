<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="art/logo/lockup-horizontal-dark.svg">
    <img src="art/logo/lockup-horizontal.svg" alt="Anvil" width="320">
  </picture>
</p>

# Anvil: an Artisan-like console for Laravel packages

Anvil is `php artisan make:*` for people who build **Laravel packages**. It supports **Laravel 12 and 13**.

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

- **`package:init`** creates or completes `composer.json` and scaffolds the package:
    - a service provider and a config file;
    - Pest + Testbench, Larastan and Pint;
    - a GitHub Actions matrix for PHP × Laravel.
- **34 `make:*` commands** write each file in your package's namespace **and register it in your
  service provider**:
    - with [laravel-package-toolkit](https://github.com/NyonCode/laravel-package-toolkit), as
      `->hasX()` calls in `configure()`;
    - with a plain Laravel `ServiceProvider`, as the standard calls in `register()`/`boot()`.
- **Registration is idempotent.** It only adds what is missing and never reformats your code.
- **Every generated file passes Pint and Larastan.** Integration tests install a generated
  package on Laravel 12 and 13 and check every registration in a running application.

## Installation

```bash
composer require --dev nyoncode/laravel-package-console
vendor/bin/anvil package:init
php anvil list
```

Requires PHP 8.2+.

## Documentation

The full documentation is in [`docs/`](docs/index.md):

- [Installation](docs/installation.md) and [Quickstart](docs/quickstart.md)
- [Creating a package](docs/package-init.md)
- [Service provider registration](docs/service-provider.md): exactly what each generator adds,
  with and without the toolkit
- [Command reference](docs/commands.md)
- [Customising stubs](docs/stubs.md)

## Testing

```bash
composer test               # unit and feature tests
composer test:integration   # installs generated packages on Laravel 12 and 13 (needs network)
```

See [Contributing](docs/contributing.md).

## Changelog

See [CHANGELOG](CHANGELOG.md).

## License

The MIT License (MIT). See [LICENSE](LICENSE).
