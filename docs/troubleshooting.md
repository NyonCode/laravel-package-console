---
title: Troubleshooting
description: What Anvil's errors and warnings mean and how to fix them.
---

# Troubleshooting

- [composer.json not found](#composerjson-not-found)
- [No PSR-4 namespace mapped to "src/"](#no-psr-4-namespace-mapped-to-src)
- [No service provider found](#no-service-provider-found)
- [WARN Add … by hand](#warn-add--by-hand)
- [Nothing was registered](#nothing-was-registered)
- [Class not found after make:factory](#class-not-found-after-makefactory)
- [The wrong provider was edited](#the-wrong-provider-was-edited)
- [Toolkit calls in a plain provider, or the other way round](#toolkit-calls-in-a-plain-provider-or-the-other-way-round)
- [A migration named … already exists](#a-migration-named--already-exists)
- [WARN … extends … and has no boot()](#warn--extends--and-has-no-boot)
- [package:init skipped phpunit.xml or the CI workflow](#packageinit-skipped-phpunitxml-or-the-ci-workflow)
- [MissingAppKeyException in tests](#missingappkeyexception-in-tests)
- [Anvil is not installed](#anvil-is-not-installed)

## composer.json not found

```
 [ERROR] composer.json not found in [/path]. Run "package:init" first.
```

Anvil works on the current directory. Either `cd` to the package root, or create the package
with [`package:init`](package-init.md). `php anvil` always works on the directory it is in.

## No PSR-4 namespace mapped to "src/"

```
 [ERROR] No PSR-4 namespace mapped to "src/" in composer.json. Run "package:init" to add it.
```

Anvil takes the [root namespace](how-it-works.md#the-root-namespace) from the PSR-4 entry for
`src/`. Add one to `composer.json`, or run `package:init`, which adds it and keeps everything
else.

## No service provider found

```
  WARN No service provider found in composer.json "extra.laravel.providers". Run "make:provider" to create one, then re-run this command with --force.
```

The file was created but could not be registered. Create the provider, then run the generator
again with `--force` to register it:

```bash
php anvil make:provider
php anvil make:route web --force
```

If you already have a provider, list it in `extra.laravel.providers`. Anvil uses the first entry
whose file exists.

## WARN Add … by hand

```
  WARN Add 'api.php' to ->hasRoutes('web.php') in the service provider.
```

The toolkit call exists, but its argument is not an array that Anvil can extend: a string, a
variable or a method call. Anvil does not guess at code it does not understand. Add the item
yourself, or turn the argument into an array literal and Anvil handles it next time:

```php
->hasRoutes(['web.php', 'api.php'])
```

## Nothing was registered

A generator that prints no `DONE` line found the registration already in place. For example:

- `->hasConfig()` without arguments already covers every config file;
- `->hasRoutes()` without arguments discovers every route file;
- the views were already registered by an earlier `make:view`.

See [Service provider registration](service-provider.md#toolkit-mode).

Some generators never register anything, by design: classes such as models, controllers and
jobs are autoloaded and need no registration. `make:middleware` registers only with `--alias`,
and `make:listener` only with `--event`.

## Class not found after make:factory

```
  DONE Added "Acme\Blog\Database\Factories\\" => "database/factories/" to composer.json autoload. Run composer dump-autoload.
```

`make:factory` added a PSR-4 entry. Composer only picks it up after:

```bash
composer dump-autoload
```

## The wrong provider was edited

Generators edit the [main provider](how-it-works.md#the-main-service-provider): the first entry in
`extra.laravel.providers` whose file exists. Reorder the list, or run
`php anvil make:provider YourProvider --main`. `php anvil package:info` shows which provider is
the main one.

## Toolkit calls in a plain provider, or the other way round

Anvil decides the mode from the provider file:

- a `configure()` method means toolkit mode;
- a `configurePackage()` method means spatie mode;
- anything else is plain.

To switch a package to the toolkit:

1. run `composer require nyoncode/laravel-package-toolkit`;
2. make the provider extend `NyonCode\LaravelPackageToolkit\PackageServiceProvider`;
3. move the registrations into `configure(Packager $packager)`.

## A migration named … already exists

```
 [ERROR] A migration named [create_posts_table] already exists: database/migrations/create_posts_table.php
```

The application would publish both migrations, and the second would fail. Choose another name.
If you really mean to replace the file, pass `--force`.

## WARN … extends … and has no boot()

```
  WARN src/BlogServiceProvider.php extends Vendor\Foundation\BaseServiceProvider and has no boot(). Adding one would override the parent's, so add this to boot() by hand, after parent::boot():
      $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
```

Your provider extends a base class other than Laravel's `ServiceProvider`. Anvil will not create
`boot()` or `register()` in it, because that would replace the parent's. Add the method once,
calling the parent first, and paste the printed code into it:

```php
public function boot(): void
{
    parent::boot();

    $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
}
```

From then on, Anvil adds to it. See
[Providers with a parent class](service-provider.md#providers-with-a-parent-class).

## package:init skipped phpunit.xml or the CI workflow

```
  SKIP   phpunit.xml (phpunit.xml.dist exists)
  SKIP   .github/workflows/tests.yml (.github/workflows/run-tests.yml exists)
```

The package already has its own test configuration and CI. Writing Anvil's versions would
override `phpunit.xml.dist` or run the tests twice, so they are skipped. To take Anvil's files
anyway, pass `--force`. The full list is in
[Running it on an existing package](package-init.md#files).

## MissingAppKeyException in tests

Requests through the `web` middleware group need an encryption key. `package:init` puts a random
`APP_KEY` in `phpunit.xml`. In a package created without it, add:

```xml title="phpunit.xml"
<env name="APP_KEY" value="base64:…"/>
```

Generate a value with `php -r 'echo "base64:".base64_encode(random_bytes(32)), PHP_EOL;'`.

## Anvil is not installed

```
Anvil is not installed. Run "composer require --dev nyoncode/laravel-package-console".
```

The `anvil` launcher needs Anvil in the package's `vendor/`. Install it as a dev dependency. See
[Installation](installation.md).
