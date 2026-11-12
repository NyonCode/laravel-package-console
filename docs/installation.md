---
title: Installation
description: Install Anvil as a dev dependency of your package or globally, and run it with php anvil.
---

# Installation

## Requirements

| | |
|---|---|
| PHP | 8.2 or newer, with the `readline` and `mbstring` extensions |
| Composer | 2.x |
| Packages you generate | Laravel 12 and/or 13 |

Anvil is a standalone Symfony Console application and does not depend on Laravel. The packages
it generates depend on Laravel: `package:init` sets their constraints. See
[Creating a package](package-init.md#laravel-versions).

## As a dev dependency (recommended)

Install Anvil in the package you are building:

```bash
composer require --dev nyoncode/laravel-package-console
vendor/bin/anvil package:init
```

`package:init` writes an `anvil` launcher into the package root. From then on you use it just
like `php artisan`:

```bash
php anvil list
php anvil make:model Post -a
```

The launcher is excluded from the dist archive in `.gitattributes` (`/anvil export-ignore`), so
the people who install your package never download it.

## Globally

A global install lets you start a package in an empty directory, before it has a
`composer.json`:

```bash
composer global require nyoncode/laravel-package-console

mkdir laravel-blog && cd laravel-blog
anvil package:init
```

Make sure Composer's global `vendor/bin` directory is on your `PATH`.
`composer global config bin-dir --absolute` prints where it is.

## From GitHub

To use the development version, add the repository to your package's `composer.json` and
require the `@dev` version:

```bash
composer config repositories.anvil vcs https://github.com/NyonCode/laravel-package-console
composer require --dev nyoncode/laravel-package-console:@dev
```

## Running Anvil

There are three ways to run Anvil. All of them work on the same package:

| Command | Where the package is |
|---|---|
| `php anvil …` | the directory that contains the `anvil` launcher |
| `vendor/bin/anvil …` | the current working directory |
| `anvil …` (global) | the current working directory |

Run `vendor/bin/anvil` and the global `anvil` from the package root, the directory that holds
the package's `composer.json`. The launcher always uses its own directory, so `php anvil` works
from anywhere.

The launcher needs Composer's autoloader and Anvil itself. It tells you what is missing:

```
Run "composer install" first.
```

```
Anvil is not installed. Run "composer require --dev nyoncode/laravel-package-console".
```

## Getting help

```bash
php anvil list                 # every command
php anvil help make:model      # arguments and options of one command
php anvil package:info         # what Anvil detected about your package
php anvil package:doctor       # what the service provider registers, and what it misses
```

Anvil also supports Symfony Console's shell completion:

```bash
php anvil completion --help
```
