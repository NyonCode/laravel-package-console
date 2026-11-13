---
title: Monorepos
description: Develop several Laravel packages in one repository. Anvil reads Composer path repositories, composer-merge-plugin, monorepo-builder and laravel/framework-style roots, works on the package --package names, keeps the root in step and checks every package with --all.
---

# Monorepos

- [How a monorepo works](#how-a-monorepo-works)
- [The layouts Anvil reads](#the-layouts-anvil-reads)
- [Creating a package in a monorepo](#creating-a-package-in-a-monorepo)
- [Choosing the package](#choosing-the-package)
- [Every package at once](#every-package-at-once)
- [What package:doctor checks in a monorepo](#what-packagedoctor-checks-in-a-monorepo)
- [CI](#ci)
- [Custom stubs for the whole monorepo](#custom-stubs-for-the-whole-monorepo)
- [What Anvil leaves to other tools](#what-anvil-leaves-to-other-tools)

## How a monorepo works

Composer has no workspaces. A PHP monorepo is a root `composer.json` that brings packages in
subdirectories together. Each package keeps its own `composer.json`, so it can be split into a
repository of its own and published. The root does three jobs:

1. **It installs the packages.** It can require them through path repositories, merge their
   `composer.json` files when Composer runs, or hold their merged requirements and autoloading
   and `replace` them.
2. **It holds the shared tooling**: one `vendor/`, the CI workflow (GitHub runs only the
   workflows in the root's `.github/`), and often shared stubs and configs.
3. **It keeps the packages consistent.** The same dependency should have the same constraint
   everywhere, and a package that requires another package of the monorepo must accept the
   version that package has inside the monorepo.

Anvil does all three for the package you are working on, whichever layout the root uses.

## The layouts Anvil reads

Anvil finds the packages in the root's `composer.json` (and `monorepo-builder.php`). A package
is a directory with a `composer.json`.

| Layout | The root has | The packages are | A new package is installed by |
|---|---|---|---|
| **Composer path repositories** | `"repositories": [{"type": "path", "url": "packages/*"}]` | the directories the URLs match (globs allowed) | a path repository (unless a glob matches it) and `"acme/laravel-blog": "@dev"` in `require-dev` |
| **[wikimedia/composer-merge-plugin](https://github.com/wikimedia/composer-merge-plugin)** | `"extra": {"merge-plugin": {"include": ["packages/*/composer.json"]}}` (and `require`) | the directories of the matched files | its `composer.json` in `include` (unless a glob matches it) |
| **[symplify/monorepo-builder](https://github.com/symplify/monorepo-builder)** | `monorepo-builder.php` | `composer.json` files in `packageDirectories()` (default `packages/`), minus `packageDirectoriesExcludes()` | what `monorepo-builder merge` adds: see below |
| **A root that replaces its packages** (laravel/framework) | `"replace": {"illuminate/support": "self.version"}` | the directories whose `composer.json` has a replaced name | the same as monorepo-builder |

Path repositories and globs that point outside the root, such as `../laravel-package-toolkit` in
a package that develops against a local copy of a dependency, do not make a monorepo. A package
with one works exactly as before.

When the root autoloads classes from outside its packages, as `laravel/framework` does, the
root is a package of the monorepo too, listed as `.`. A monorepo-builder root that only
autoloads the classes of its packages is not.

**Merged roots** (monorepo-builder, and roots that replace their packages) hold everything their
packages need. For a package, Anvil adds what `monorepo-builder merge` would:

- its `autoload` and `autoload-dev` PSR-4 entries, with the package's directory in front of the
  paths, unless the root already autoloads them (`"Illuminate\\": "src/Illuminate/"` covers
  `illuminate/support` in `src/Illuminate/Support`);
- its `require` and `require-dev` entries that the root lacks, except the ones on packages of
  the monorepo;
- `"replace": {"acme/laravel-blog": "self.version"}`.

## Creating a package in a monorepo

Run `package:init` in the root and give it the directory of the new package:

```bash
php anvil package:init --package=packages/blog
```

It creates the package in that directory as it would anywhere else, and then makes the root
install it:

```
Creating a new Laravel package
==============================

  CREATE composer.json

  INFO Provider [src/BlogServiceProvider.php] created successfully.
  DONE Registered in composer.json "extra.laravel.providers".
  INFO Config [config/blog.php] created successfully.
  DONE Registered ->hasConfig() in src/BlogServiceProvider.php.
  CREATE anvil
  CREATE .gitignore
  CREATE .gitattributes
  CREATE README.md
  CREATE LICENSE.md
  CREATE pint.json
  CREATE phpstan.neon.dist
  CREATE testbench.yaml
  CREATE phpunit.xml
  CREATE tests/TestCase.php
  CREATE tests/Pest.php
  CREATE tests/ArchTest.php
  CREATE tests/Feature/ServiceProviderTest.php
  SKIP   .github/workflows/tests.yml (the monorepo's workflow tests the package)
  UPDATE ../../composer.json (require-dev acme/laravel-blog, require-dev nyoncode/laravel-package-console)
  CREATE ../../anvil
  CREATE ../../.gitignore
  CREATE ../../.github/workflows/tests.yml

  🎉  Package ready (with laravel-package-toolkit).

  Next steps:
    composer update                     in the monorepo root
    composer update && vendor/bin/pest  in packages/blog

  Then add resources with the make:* commands, e.g. php anvil make:model Post -mf --package=packages/blog
  in the monorepo root. They are registered in src/BlogServiceProvider.php automatically.
```

The root gets what the layout needs to install the package (see the
[table above](#the-layouts-anvil-reads)), Anvil in `require-dev`, and, once, the `anvil`
launcher, a `.gitignore` and the [CI workflow](#ci). With path repositories and two packages:

```json title="composer.json"
{
    "name": "acme/monorepo",
    "require-dev": {
        "acme/laravel-blog": "@dev",
        "nyoncode/laravel-package-console": "^1.0",
        "acme/laravel-shop": "@dev"
    },
    "repositories": [
        {
            "type": "path",
            "url": "packages/*"
        }
    ]
}
```

With composer-merge-plugin, a package outside the included globs is added to them:

```
  UPDATE ../../composer.json (merge-plugin include modules/forum/composer.json, require-dev nyoncode/laravel-package-console)
```

With monorepo-builder, the root gets the package's autoloading and requirements:

```
  UPDATE ../../composer.json (autoload Acme\Blog => packages/blog/src/, autoload Acme\Blog\Database\Factories => packages/blog/database/factories/, autoload-dev Acme\Blog\Tests => packages/blog/tests/, require php, require illuminate/support, require nyoncode/laravel-package-toolkit, require-dev larastan/larastan, require-dev laravel/pint, require-dev nyoncode/laravel-package-console, require-dev orchestra/testbench, require-dev pestphp/pest, require-dev pestphp/pest-plugin-laravel, replace acme/laravel-blog)
```

```json title="composer.json"
{
    "name": "acme/monorepo",
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
    "replace": {
        "acme/laravel-blog": "self.version"
    },
    "autoload": {
        "psr-4": {
            "Acme\\Blog\\": "packages/blog/src/",
            "Acme\\Blog\\Database\\Factories\\": "packages/blog/database/factories/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Acme\\Blog\\Tests\\": "packages/blog/tests/"
        }
    }
}
```

A package created outside `packageDirectories()` gets a warning, because Anvil does not edit
`monorepo-builder.php`.

The root can start as a bare `composer.json` with a name: `--package` names a directory inside
it, so it becomes a monorepo with path repositories. You can also run `package:init` in an empty
directory inside the monorepo, without `--package`. In the root of a monorepo with several
packages and no `--package`, it asks in which directory the package should be.

The package keeps its own `anvil` launcher, test setup and `.gitattributes`, so it still works
on its own after it is split into a repository of its own.

## Choosing the package

Every command works on one package. `--package` names it, by any of:

| `--package=` | |
|---|---|
| `packages/blog` | its directory, relative to the monorepo root (or to the working directory) |
| `blog` | the name of its directory |
| `acme/laravel-blog` | its Composer name |
| `blog` | its [short name](how-it-works.md#the-short-name), derived from the Composer name |

```bash
php anvil make:model Post -m --package=blog
```

```
  INFO Model [src/Models/Post.php] created successfully.
  INFO Migration [database/migrations/create_posts_table.php] created successfully.
  DONE Registered ->hasMigrations() in src/BlogServiceProvider.php.
```

The paths in the output are relative to the package. A name that matches several packages is
an error; use the directory then. The `ANVIL_PACKAGE` environment variable does the same as
`--package`:

```bash
export ANVIL_PACKAGE=blog
php anvil make:event PostPublished
```

Without either, Anvil works on:

- the package you are in: `packages/blog`, or any directory inside it, such as
  `packages/blog/src`;
- the root, when it is a package itself;
- the only package of the monorepo, in its root;
- in the root of a monorepo with several packages, the one you choose when it asks
  (*Which package of the monorepo?*).

Where it cannot ask (`-n`, CI), it stops:

```
 [ERROR] The monorepo has 2 packages, choose one with --package: packages/blog,
         packages/shop.
```

`--package` works from inside a package too: `php anvil make:event Paid --package=shop` in
`packages/blog` writes to `packages/shop`. Shell completion suggests the packages.

Anvil looks for the monorepo root in the directories above the package. Its stubs end up in
generated code and `package:init` writes to it, so the search stops at a trust boundary: the
root of the git repository (a directory with `.git`), the directory below your home or the
temporary directory, and a directory owned by another user. A package that is a git
repository of its own is therefore never treated as part of a monorepo above it.

## Every package at once

`package:info --all` lists the packages:

```bash
php anvil package:info --all
```

```
+---------------+-------------------+------------+----------------+---------+-------------------------------+
| Package       | Composer name     | Short name | Root namespace | Mode    | Main provider                 |
+---------------+-------------------+------------+----------------+---------+-------------------------------+
| packages/blog | acme/laravel-blog | blog       | Acme\Blog      | toolkit | Acme\Blog\BlogServiceProvider |
| packages/shop | acme/laravel-shop | shop       | Acme\Shop      | plain   | Acme\Shop\ShopServiceProvider |
+---------------+-------------------+------------+----------------+---------+-------------------------------+
```

`package:info` of one package shows the monorepo it is in, its layout and its `--package`.

`package:doctor --all` checks the monorepo and then every package. It exits with 1 when any
package has an error, so one CI step covers the monorepo:

```bash
php anvil package:doctor --all
```

```
Monorepo (Composer path repositories)
  INFO  php is required with different constraints: ^8.2 in packages/blog, ^8.3 in packages/shop.
  INFO  illuminate/support is required with different constraints: ^12.0|^13.0 in packages/blog, ^13.0 in packages/shop.
  INFO  orchestra/testbench is required with different constraints: ^10.0|^11.0 in packages/blog, ^11.0 in packages/shop.
  INFO  pestphp/pest is required with different constraints: ^3.8|^4.0 in packages/blog, ^4.0 in packages/shop.
  INFO  pestphp/pest-plugin-laravel is required with different constraints: ^3.2|^4.0 in packages/blog, ^4.0 in packages/shop.

packages/blog (acme/laravel-blog)
  DONE The service provider and composer.json match the package.

packages/shop (acme/laravel-shop)
  ERROR src/Commands/Prune.php is not registered in the service provider. Missing: Prune::class in commands().

  1 error. Run package:doctor --all --fix to fix 1 of them.
```

`--fix` applies to every package. With
`--json`, `monorepo` holds the monorepo's findings, `packages` holds the report of each package
under its directory, and `errors` and `warnings` are the totals:

```json
{
    "errors": 1,
    "warnings": 0,
    "monorepo": {
        "root": "/path/to/acme",
        "layouts": [
            "path"
        ],
        "warnings": 0,
        "findings": []
    },
    "packages": {
        "packages/blog": {
            "errors": 0,
            "warnings": 0,
            "fixed": [],
            "findings": []
        },
        "packages/shop": {
            "errors": 1,
            "warnings": 0,
            "fixed": [],
            "findings": [
                {
                    "severity": "error",
                    "check": "unregistered",
                    "subject": "src/Commands/Prune.php",
                    "message": "is not registered in the service provider. Missing: ->hasCommands([Prune::class]).",
                    "fix": "registered ->hasCommands([Prune::class])"
                }
            ]
        }
    }
}
```

## What package:doctor checks in a monorepo

On top of its [usual checks](doctor.md#what-it-checks), `package:doctor` checks for a package
in a monorepo, with or without `--all`:

| Finding | Severity | `--fix` |
|---|---|---|
| The root does not install the package as its layout does: a missing path repository, `require-dev` entry or merge-plugin `include`; in a merged root, a missing PSR-4 entry, requirement or `replace` | error | adds it, exactly as `package:init` would |
| The package requires another package of the monorepo with a version range (`^1.0`), but that package has neither `version` nor `extra.branch-alias`, so a path repository gives it only as `dev-main` | warning | none: add `"extra": {"branch-alias": {"dev-main": "1.x-dev"}}` to it, or require it as `@dev` |

The merged-root check matters after the package changed: `make:factory` adds a PSR-4 entry for
`database/factories`, a new dependency is added to the package, and so on. `--fix` does what
`monorepo-builder merge` would do for that package.

With `--all` it also checks the monorepo as a whole, as monorepo-builder's `validate` does: it
lists the dependencies that the packages require with different constraints. It is only `INFO`,
so `--strict` does not fail on it: packages often support different Laravel versions on
purpose. Align the constraints you did not mean to differ.

## CI

GitHub runs only the workflows in the root's `.github/workflows`, so a package of a monorepo
does not get one of its own. The first `package:init` in the monorepo writes
`.github/workflows/tests.yml` in the root instead, and each later one adds its package to the
matrix:

```yaml title=".github/workflows/tests.yml"
name: tests

on:
  push:
  pull_request:

jobs:
  test:
    runs-on: ubuntu-latest

    strategy:
      fail-fast: false
      matrix:
        package: ['packages/blog', 'packages/shop']
        php: ['8.2', '8.3', '8.4', '8.5']
        dependencies: [lowest, highest]

    name: ${{ matrix.package }} - PHP ${{ matrix.php }} - ${{ matrix.dependencies }}

    defaults:
      run:
        working-directory: ${{ matrix.package }}

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          coverage: none

      - name: Check that the package supports this PHP version
        id: php
        run: |
          supported=$(php -r '
            Phar::loadPhar($argv[1], "composer.phar");
            require "phar://composer.phar/vendor/autoload.php";
            $php = json_decode(file_get_contents("composer.json"), true)["require"]["php"] ?? "*";
            echo Composer\Semver\Semver::satisfies(PHP_VERSION, $php) ? "true" : "false";
          ' -- "$(command -v composer)")
          echo "supported=$supported" >> "$GITHUB_OUTPUT"
          if [ "$supported" != true ]; then echo "::notice::${{ matrix.package }} does not support PHP ${{ matrix.php }}, its tests are skipped."; fi

      - name: Use the other packages of the monorepo
        if: steps.php.outputs.supported == 'true'
        run: |
          composer config repositories.monorepo-1 '{"type": "path", "url": "${{ github.workspace }}/packages/*"}'
          composer config minimum-stability dev

      - name: Install dependencies
        if: steps.php.outputs.supported == 'true'
        run: composer update --prefer-dist --no-interaction --no-progress ${{ matrix.dependencies == 'lowest' && '--prefer-lowest' || '' }}

      - name: Run tests
        if: steps.php.outputs.supported == 'true'
        run: vendor/bin/pest --ci
```

Each package is installed on its own, as it will be once it is split and published. The
packages of the monorepo it requires come from path repositories rather than from Packagist.
The lowest and the highest dependencies cover the Laravel versions the package supports.
The matrix has every PHP version Anvil supports. A job whose PHP version the package does not
accept (its `require.php`, checked with Composer's own semver) skips its steps with a notice,
so packages with different PHP requirements share one matrix.

A workflow of your own is left alone. When it does not mention the new package, `package:init`
warns.

## Custom stubs for the whole monorepo

A stub in the monorepo root's `stubs/anvil/` is used by every package. A package's own
`stubs/anvil/` still wins over it. See [Customising stubs](stubs.md).

## What Anvil leaves to other tools

- **Splitting** the packages into read-only repositories: use
  [symplify/github-action-monorepo-split](https://github.com/symplify/github-action-monorepo-split)
  or splitsh-lite.
- **Releasing** and bumping the versions the packages require of one another: use
  `monorepo-builder release` and `monorepo-builder bump-interdependency`.
- **Editing `monorepo-builder.php`**: a package outside its `packageDirectories()` gets a
  warning, not an edit.
