# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`nyoncode/laravel-package-console` (CLI name **Anvil**) is a standalone Symfony Console CLI that gives people **developing a Laravel package** (Laravel 12 and 13) Artisan-like generators. It has no Laravel framework dependency.

It is run from the root of the package being developed:
- `php anvil`, the launcher `package:init` writes into the package root (`stubs/package/anvil.stub`), like `php artisan`;
- `vendor/bin/anvil` when installed as a dev dependency;
- `anvil` when installed globally.

It integrates optionally with `nyoncode/laravel-package-toolkit`, whose source lives in `../laravel-package-toolkit`. That repo's `ai/AGENTS.md` is the reference for its `Packager` API.

## Commands

```bash
composer install
composer test                          # Unit + Feature (no network), about a minute
composer test:integration              # installs generated packages on Laravel 12/13 (network)
vendor/bin/pest --filter="toolkit"     # single test / pattern
vendor/bin/pest tests/Unit/PackageTest.php
composer pint                          # Laravel Pint
composer docs:commands                 # regenerate the reference block in docs/commands.md
bin/anvil list                         # run the CLI against the current directory
```

Supported dependency range: Symfony `^7.2|^8.0` and Pest `^3.8|^4.0`. Run the suite with `composer update --prefer-lowest` as well as with the latest versions. Symfony 8 removed `Application::add()`, so `CommandDiscovery` uses `addCommand()` when it exists.

## Architecture

**Two roots.**
- `Support/Config` holds the console's own paths: `config/app.php` and `stubs/`.
- `Support/Package` is the package being developed (`Kernel::package()`, `anvil_package()`). It reads that package's `composer.json` lazily, so `package:init` works in an empty directory. It derives:
  - `rootNamespace()`: the PSR-4 entry mapped to `src/`;
  - `shortName()`: e.g. `acme/laravel-blog` → `blog`;
  - `pathForClass()`: longest PSR-4 prefix wins, which is why factories land in `database/factories`;
  - `usesToolkit()`;
  - `mainProvider()`: the first existing class in `extra.laravel.providers`.

**Monorepos.** `Support/Workspace` decides which package a command works on; `Kernel` holds it.
- `Support/Monorepo` reads the layouts of `docs/monorepo.md`: path repositories, composer-merge-plugin
  (`extra.merge-plugin`), symplify/monorepo-builder (`monorepo-builder.php`, parsed with php-parser)
  and roots that `replace` their packages at `self.version` (laravel/framework). `layout()` is how a
  new package is installed; `rootIsPackage()` is true only when the root autoloads classes outside
  its packages.
- `BaseCommand::initialize()` calls `Kernel::selectPackage()` with the global `--package` option
  (or `ANVIL_PACKAGE`): it locates a package of the monorepo or a directory; without it, the
  working directory, the nearest `composer.json` above, the only package of the monorepo, or a
  prompt. Once chosen, commands run through `call()` keep it. `worksOnPackage()` is false for
  `--all`; `createsPackage()` lets `package:init` name a new directory.
- `Monorepo::install()` adds what the root lacks to install a package (path repository + `@dev`,
  merge-plugin include, or monorepo-builder's merge: prefixed PSR-4, requirements, `replace`).
  With `write: false` it previews: `Doctor::checkMonorepo()` reports the preview and `--fix` writes
  it, so the doctor and `package:init` cannot disagree.
- `package:init` inside a monorepo also writes the root's `anvil`, `.gitignore` and
  `.github/workflows/tests.yml` (`stubs/package/monorepo-workflow.stub`, a `package` matrix it
  extends), and skips the package's own workflow.
- `package:doctor --all` / `package:info --all` loop over the packages with `Kernel::usePackage()`;
  `--all` also reports `Monorepo::conflictingConstraints()`. Tests are in
  `tests/Feature/MonorepoTest.php`.

**Command discovery.** `Kernel` → `CommandDiscovery` registers every instantiable class under `src/Commands` that has `#[AsCommand]`. Adding a command means adding a class there. Use the `aliases:` argument of `#[AsCommand]` for alternative names.

**Command hierarchy.**
- `BaseCommand`:
  - Subclasses implement `handle()`. An int return is the exit code; `false` means failure.
  - Arguments and options are declared Laravel-style as arrays in `getArguments()` / `getOptions()`.
  - `getValidationRules()` validates arguments, and options when keyed `--option`. Validate every option value that ends up in generated code or in a path.
  - Missing required arguments are asked for interactively (`interact()`), with the label and
    placeholder from `promptForArgument()`; a command that had to ask may ask about its options
    in `afterPromptingForMissingArguments()`, as Artisan does.
  - All questions go through `$this->prompts()` (`Support/Prompts`): the default when the input
    is not interactive, a Laravel Prompts prompt in a terminal, a plain Symfony question
    elsewhere (Windows, piped input). Never call Laravel Prompts' helpers directly, and never
    use `Prompt::fallbackWhen()`: it is static and cannot be turned off again.
  - Question texts go through `$this->trans()` / `anvil_trans()` (`Support/Lang`): the English
    text is the key, `lang/{locale}.php` holds the translations, the locale is `ANVIL_LANG` or
    the system's. Only questions are translated; output stays in English.
  - `call()` runs another command non-interactively and returns its exit code.
- `GenerateCommand` (classes):
  - Subclasses set `$type`, `$namespace` (relative to the root namespace) and `$stub` or `getStub()`.
  - Hooks: `getReplacements()` for extra `{{ placeholders }}`, `getImports()` for classes to `use`, and `afterGenerate()` for registration or related files.
  - `tidyImports()` merges, sorts and deduplicates the `use` block.
  - Stubs resolve from the package's `stubs/anvil/` first, then from `stubs/`.
- `FileGenerateCommand` (non-class files): config, routes, migrations, views, lang, tests. Implements `getFilePath()`.

**Registration in the service provider.**
- `afterGenerate()` calls `ProviderRegistrar`, which returns a `Registration` (registered / already / manual / no-provider) printed by `report()`.
- The registrar edits the main provider with `PhpFileEditor`: nikic/php-parser finds positions, and changes are spliced into the original text, so formatting is preserved.
- It picks the mode from the file itself (`PhpFileEditor::mode()`), not from `composer.json`:
  - toolkit, when `configure()` exists: it extends the `$packager` chain (`->hasX()`, or adds an item to an existing array argument);
  - spatie, when `configurePackage()` exists (spatie/laravel-package-tools): it appends additive `->hasX()` calls; anything the package tools have no call for goes into `packageBooted()`/`packageRegistered()`. Never add `register()`/`boot()` there, because they would override Spatie's;
  - plain: it appends standard Laravel calls to `register()`/`boot()`. It creates a missing method only when the provider extends `Illuminate\Support\ServiceProvider`; with any other parent it returns a MANUAL warning with the code.
- In `register()`/`boot()` and the spatie hooks, "already registered" is decided from `PhpFileEditor::registrations()`, which reads the path of every `loadRoutesFrom()`, `publishes()`, `require`, ... in the class, however it is written. A statement passed to `laravelStatements()` names the calls that cover it and its path; only statements without a path (`Event::listen`, `aliasMiddleware`) are matched as text.
- `Package::shortName()` follows the provider (toolkit `hasShortName()`/`Str::kebab(name)`, spatie `Str::after(name, 'laravel-')`) before falling back to the Composer name. `Package::translationsPath()` is `resources/lang` in spatie mode.
- `ProviderRegistrar::preview()` registers in memory: registrations build on one another and `previewedCode()` is the result. A previewing registrar answers `Registration::UNKNOWN` instead of adding next to a registration it cannot read.
- `package:doctor` is `Support/Doctor`: it runs the previewing registrar over the files on disk (REGISTERED means "was missing"), so it can never disagree with the generators, and `--fix` writes the previewed provider. It also checks the other direction (registered files and `Foo::class` that do not exist), `extra.laravel.providers` and PSR-4. A new registrar method needs a check in `Doctor::checkResources()` and a case in `tests/Feature/DoctorTest.php`.
- `Doctor::fix($subjects)` fixes only the findings about those subjects: it previews again with
  the registrar called for nothing else. `Support/TranslationFiles` compares the languages of
  the package (read with php-parser, never `require`d) and splices missing keys into the files.
- Every operation must stay idempotent. `tests/Feature/ProviderRegistrationTest.php` runs every generator twice and asserts the provider is unchanged.

**`package:init`** writes `composer.json` through `Support/ComposerJson`, which keeps Composer's key order and empty objects and only fills in missing values. It then calls `make:provider --main` and `make:config` and scaffolds `stubs/package/*`. Version constraints per Laravel version are in the `PackageInitCommand::LARAVEL` constant.

## Conventions

- Stubs follow Laravel 12/13's own stubs, adapted for packages:
  - controllers have no `App\Http\Controllers\Controller` parent;
  - policies and channels use `Authenticatable`;
  - factories set `$model` explicitly;
  - seeders use `Database\Seeders`.
- Generated code must pass Pint (`laravel` preset), so import classes instead of writing FQCNs in docblocks, and end every stub with a newline.
- Model table names use doctrine/inflector plus Laravel's uncountable list, like `Str::pluralStudly`.

## Tests

- **Helpers.** Every test runs commands against a temporary package. The helpers in `tests/Pest.php`:
  - `tempPackage()`, `initPackage()`, `anvil()`;
  - `buildFullPackage()`, which runs every generator listed in `fullPackageCommands()`;
  - `expectValidPhp()`, `expectPintClean()`;
  - `anvilPrompting()` runs a command interactively and answers its prompts with `keys(...)`;
    `anvilAnswering()` answers the plain-question fallback with lines of text. `tests/Pest.php`
    sets `ANVIL_LANG=en`, so the questions are English whatever the machine's locale.
- **`tests/Feature/FullPackageTest.php`** builds a complete package in both modes and checks:
  - the exact file list;
  - `php -l` and Pint on every file;
  - the providers, byte for byte against `tests/Fixtures/*ServiceProvider.php`;
  - idempotency.

  When a stub or registrar change alters the providers on purpose, update the fixtures.
- **`tests/Integration/PackageRuntimeTest.php`** (group `integration`, skipped unless `ANVIL_INTEGRATION=1`):
  1. builds the full package for toolkit/plain × Laravel 12/13;
  2. runs `composer update`;
  3. runs the package's Pest suite, including `tests/Fixtures/PackageRuntimeTest.stub`, which checks every registration at runtime;
  4. runs Larastan and Pint.
- **A new generator** goes into `fullPackageCommands()` and gets option tests in `tests/Feature/CommandOptionsTest.php`.

## Documentation

- `docs/*.md` has one page per topic, with `title`/`description` front matter (the same format as the toolkit's docs).
- `docs/index.md` is the table of contents.
- `docs/commands.md` contains a block generated by `tests/Support/CommandReference.php`.
- `tests/Unit/DocumentationTest.php` fails when:
  - that block is stale;
  - a link or anchor is broken;
  - front matter is missing.
- Every example output and code snippet in the docs is real output. Re-run the commands when behaviour changes.
