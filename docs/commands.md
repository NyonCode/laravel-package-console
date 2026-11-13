---
title: Command reference
description: Every Anvil command with its arguments, options, shortcuts and defaults, the namespaces it writes to and what it registers.
---

# Command reference

- [At a glance](#at-a-glance)
- [Namespaces](#namespaces)
- [Global options](#global-options)
- [All commands](#all-commands)

## At a glance

| Command | Creates | Registers (toolkit / plain) | Docs |
|---|---|---|---|
| `package:init` | `composer.json`, provider, config, tests, tooling | `extra.laravel.providers` | [Creating a package](package-init.md) |
| `package:info` | | | [How Anvil reads your package](how-it-works.md#packageinfo) |
| `package:doctor` | | with `--fix`: whatever the files of the package need | [Checking a package](doctor.md) |
| `make:structure` | the standard directories | | [Creating a package](package-init.md#makestructure) |
| `make:provider` | `src/{Package}ServiceProvider.php`, `src/Providers/*` | `extra.laravel.providers` | [Classes](classes.md#makeprovider) |
| `make:config` | `config/*.php` | `hasConfig()` / `mergeConfigFrom` + `publishes` | [Config & routes](config-and-routes.md#makeconfig) |
| `make:route` | `routes/*.php` | `hasRoutes([...])`, `hasBroadcastChannels([...])` / `loadRoutesFrom`, `require` | [Config & routes](config-and-routes.md#makeroute) |
| `make:model` | `src/Models/*` and related files | through the related generators | [Database](database.md#makemodel) |
| `make:migration` | `database/migrations/*` | `hasMigrations()` / `publishesMigrations` | [Database](database.md#makemigration) |
| `make:factory` | `database/factories/*` | PSR-4 entry in `composer.json` | [Database](database.md#makefactory) |
| `make:seeder` | `database/seeders/*` | `hasSeeders()` / `publishes` | [Database](database.md#makeseeder) |
| `make:cast` | `src/Casts/*` | | [Database](database.md#makecast) |
| `make:scope` | `src/Models/Scopes/*` | | [Database](database.md#makescope) |
| `make:observer` | `src/Observers/*` | | [Database](database.md#makeobserver) |
| `make:controller` | `src/Http/Controllers/*` | | [HTTP](http.md#makecontroller) |
| `make:middleware` | `src/Http/Middleware/*` | with `--alias`: `hasMiddlewareAliases` / `aliasMiddleware` | [HTTP](http.md#makemiddleware) |
| `make:request` | `src/Http/Requests/*` | | [HTTP](http.md#makerequest) |
| `make:resource` | `src/Http/Resources/*` | | [HTTP](http.md#makeresource) |
| `make:view` | `resources/views/*.blade.php` | `hasViews()` / `loadViewsFrom` + `publishes` | [Views](views-and-translations.md#makeview) |
| `make:component` | `src/View/Components/*` + view | `hasViews()`, `hasComponentNamespace` / `loadViewsFrom`, `Blade::componentNamespace` | [Views](views-and-translations.md#makecomponent) |
| `make:lang` | `lang/{locale}/*.php`, `lang/{locale}.json` | `hasTranslations()` / `loadTranslationsFrom`, `loadJsonTranslationsFrom` + `publishes` | [Translations](views-and-translations.md#makelang) |
| `make:command` | `src/Commands/*` | `hasCommands([...])` / `commands([...])` | [Console commands](console-commands.md) |
| `make:event` | `src/Events/*` | | [Events](events-queues-and-mail.md#makeevent) |
| `make:listener` | `src/Listeners/*` | with `--event`: `hasEvent` / `Event::listen` | [Events](events-queues-and-mail.md#makelistener) |
| `make:job` | `src/Jobs/*` | | [Queues](events-queues-and-mail.md#makejob) |
| `make:notification` | `src/Notifications/*` | | [Notifications](events-queues-and-mail.md#makenotification) |
| `make:mail` | `src/Mail/*` + template | with a template: views | [Mail](events-queues-and-mail.md#makemail) |
| `make:channel` | `src/Broadcasting/*` | | [Broadcasting](events-queues-and-mail.md#makechannel) |
| `make:class` | `src/*` | | [Classes](classes.md#makeclass) |
| `make:interface` | `src/Contracts/*` | | [Classes](classes.md#makeinterface) |
| `make:trait` | `src/Concerns/*` | | [Classes](classes.md#maketrait) |
| `make:enum` | `src/Enums/*` | | [Classes](classes.md#makeenum) |
| `make:exception` | `src/Exceptions/*` | | [Classes](classes.md#makeexception) |
| `make:facade` | `src/Facades/*` | | [Classes](classes.md#makefacade) |
| `make:rule` | `src/Rules/*` | | [Classes](classes.md#makerule) |
| `make:policy` | `src/Policies/*` | | [Classes](classes.md#makepolicy) |
| `make:test` | `tests/Feature/*Test.php`, `tests/Unit/*Test.php` | | [Tests](testing.md#maketest) |

## Namespaces

Class generators write into these namespaces, relative to the
[root namespace](how-it-works.md#the-root-namespace) (`Acme\Blog` below):

| Generator | Namespace |
|---|---|
| `make:class` | `Acme\Blog` |
| `make:provider` | `Acme\Blog` (main provider), `Acme\Blog\Providers` (others) |
| `make:interface` | `Acme\Blog\Contracts` |
| `make:trait` | `Acme\Blog\Concerns` |
| `make:enum` | `Acme\Blog\Enums` |
| `make:exception` | `Acme\Blog\Exceptions` |
| `make:facade` | `Acme\Blog\Facades` |
| `make:rule` | `Acme\Blog\Rules` |
| `make:policy` | `Acme\Blog\Policies` |
| `make:model` | `Acme\Blog\Models` |
| `make:scope` | `Acme\Blog\Models\Scopes` |
| `make:cast` | `Acme\Blog\Casts` |
| `make:observer` | `Acme\Blog\Observers` |
| `make:factory` | `Acme\Blog\Database\Factories` |
| `make:seeder` | `Database\Seeders` (the application's namespace, see [make:seeder](database.md#makeseeder)) |
| `make:controller` | `Acme\Blog\Http\Controllers` |
| `make:middleware` | `Acme\Blog\Http\Middleware` |
| `make:request` | `Acme\Blog\Http\Requests` |
| `make:resource` | `Acme\Blog\Http\Resources` |
| `make:command` | `Acme\Blog\Commands` |
| `make:event` | `Acme\Blog\Events` |
| `make:listener` | `Acme\Blog\Listeners` |
| `make:job` | `Acme\Blog\Jobs` |
| `make:notification` | `Acme\Blog\Notifications` |
| `make:mail` | `Acme\Blog\Mail` |
| `make:channel` | `Acme\Blog\Broadcasting` |
| `make:component` | `Acme\Blog\View\Components` |
| `make:test --phpunit` | `Acme\Blog\Tests\Feature`, `Acme\Blog\Tests\Unit` |

## Global options

Every command also accepts Symfony Console's global options, and `--package`:

| Option | |
|---|---|
| `--package=` | The package to work on in a [monorepo](monorepo.md#choosing-the-package): its directory, directory name or Composer name |
| `-h`, `--help` | Show the command's help |
| `-n`, `--no-interaction` | Never ask; use defaults, and fail on missing required arguments |
| `-q`, `--quiet` | Only print errors |
| `--silent` | Print nothing |
| `-v`, `-vv`, `-vvv` | More verbose output |
| `--ansi`, `--no-ansi` | Force or disable colours |
| `-V`, `--version` | Show Anvil's version |

## All commands

This section is generated from the commands themselves by `composer docs:commands`, and a test
fails when it is out of date.

<!-- commands:start -->

### `make:cast`

Create a new custom Eloquent cast class.

```
make:cast [options] [--] <name>
```

| Argument / option | Type | Default | Description |
|---|---|---|---|
| `name` | required |  | The name of the cast |
| `--inbound` | flag |  | Generate an inbound cast class |
| `-f`, `--force` | flag |  | Create the cast even if it already exists |

### `make:channel`

Create a new broadcast channel class.

```
make:channel [options] [--] <name>
```

| Argument / option | Type | Default | Description |
|---|---|---|---|
| `name` | required |  | The name of the channel |
| `-f`, `--force` | flag |  | Create the channel even if it already exists |

### `make:class`

Create a new class.

```
make:class [options] [--] <name>
```

| Argument / option | Type | Default | Description |
|---|---|---|---|
| `name` | required |  | The name of the class |
| `-i`, `--invokable` | flag |  | Generate a single method, invokable class |
| `-f`, `--force` | flag |  | Create the class even if it already exists |

### `make:command`

Create a new Artisan command. Alias: `make:console`.

```
make:command [options] [--] <name>
```

| Argument / option | Type | Default | Description |
|---|---|---|---|
| `name` | required |  | The name of the console command |
| `--command=` | value |  | The terminal command that will be used to invoke the class |
| `-f`, `--force` | flag |  | Create the console command even if it already exists |

### `make:component`

Create a new view component class.

```
make:component [options] [--] <name>
```

| Argument / option | Type | Default | Description |
|---|---|---|---|
| `name` | required |  | The name of the component |
| `--inline` | flag |  | Create a component that renders an inline view |
| `--view` | flag |  | Create an anonymous component with only a view |
| `-f`, `--force` | flag |  | Create the component even if it already exists |

### `make:config`

Create a new config file.

```
make:config [options] [--] [<name>]
```

| Argument / option | Type | Default | Description |
|---|---|---|---|
| `name` | optional |  | The name of the config file (defaults to the package short name) |
| `-f`, `--force` | flag |  | Create the config even if it already exists |

### `make:controller`

Create a new controller class.

```
make:controller [options] [--] <name>
```

| Argument / option | Type | Default | Description |
|---|---|---|---|
| `name` | required |  | The name of the controller |
| `-r`, `--resource` | flag |  | Generate a resource controller class |
| `--api` | flag |  | Exclude the create and edit methods from the controller |
| `-i`, `--invokable` | flag |  | Generate a single method, invokable controller class |
| `-m`, `--model=` | value |  | Generate a resource controller for the given model |
| `-f`, `--force` | flag |  | Create the controller even if it already exists |

### `make:enum`

Create a new enum.

```
make:enum [options] [--] <name>
```

| Argument / option | Type | Default | Description |
|---|---|---|---|
| `name` | required |  | The name of the enum |
| `-s`, `--string` | flag |  | Generate a string backed enum |
| `-i`, `--int` | flag |  | Generate an integer backed enum |
| `-f`, `--force` | flag |  | Create the enum even if it already exists |

### `make:event`

Create a new event class.

```
make:event [options] [--] <name>
```

| Argument / option | Type | Default | Description |
|---|---|---|---|
| `name` | required |  | The name of the event |
| `-f`, `--force` | flag |  | Create the event even if it already exists |

### `make:exception`

Create a new custom exception class.

```
make:exception [options] [--] <name>
```

| Argument / option | Type | Default | Description |
|---|---|---|---|
| `name` | required |  | The name of the exception |
| `--render` | flag |  | Create the exception with report() and render() methods |
| `-f`, `--force` | flag |  | Create the exception even if it already exists |

### `make:facade`

Create a new facade class.

```
make:facade [options] [--] <name>
```

| Argument / option | Type | Default | Description |
|---|---|---|---|
| `name` | required |  | The name of the facade |
| `--accessor=` | value |  | The container binding the facade resolves (defaults to the root class of the same name) |
| `-f`, `--force` | flag |  | Create the facade even if it already exists |

### `make:factory`

Create a new model factory.

```
make:factory [options] [--] <name>
```

| Argument / option | Type | Default | Description |
|---|---|---|---|
| `name` | required |  | The name of the factory |
| `-m`, `--model=` | value |  | The name of the model |
| `-f`, `--force` | flag |  | Create the factory even if it already exists |

### `make:interface`

Create a new interface.

```
make:interface [options] [--] <name>
```

| Argument / option | Type | Default | Description |
|---|---|---|---|
| `name` | required |  | The name of the interface |
| `-f`, `--force` | flag |  | Create the interface even if it already exists |

### `make:job`

Create a new job class.

```
make:job [options] [--] <name>
```

| Argument / option | Type | Default | Description |
|---|---|---|---|
| `name` | required |  | The name of the job |
| `--sync` | flag |  | Indicates that the job should be synchronous |
| `-f`, `--force` | flag |  | Create the job even if it already exists |

### `make:lang`

Create a new translation file.

```
make:lang [options] [--] [<name>]
```

| Argument / option | Type | Default | Description |
|---|---|---|---|
| `name` | optional | `messages` | The name of the translation file |
| `-l`, `--locale=` | value | `en` | The locale, or several separated by commas (cs,en) |
| `--json` | flag |  | Create a JSON translation file for the locale |
| `-f`, `--force` | flag |  | Create the translation even if it already exists |

### `make:listener`

Create a new event listener class.

```
make:listener [options] [--] <name>
```

| Argument / option | Type | Default | Description |
|---|---|---|---|
| `name` | required |  | The name of the listener |
| `-e`, `--event=` | value |  | The event class being listened for (registered in the service provider) |
| `--queued` | flag |  | Indicates the event listener should be queued |
| `-f`, `--force` | flag |  | Create the listener even if it already exists |

### `make:mail`

Create a new email class.

```
make:mail [options] [--] <name>
```

| Argument / option | Type | Default | Description |
|---|---|---|---|
| `name` | required |  | The name of the mailable |
| `-m`, `--markdown[=]` | optional value |  | Create a new Markdown template for the mailable |
| `--view[=]` | optional value |  | Create a new Blade template for the mailable |
| `-f`, `--force` | flag |  | Create the mailable even if it already exists |

### `make:middleware`

Create a new HTTP middleware class.

```
make:middleware [options] [--] <name>
```

| Argument / option | Type | Default | Description |
|---|---|---|---|
| `name` | required |  | The name of the middleware |
| `-a`, `--alias=` | value |  | Register the middleware under this alias |
| `-f`, `--force` | flag |  | Create the middleware even if it already exists |

### `make:migration`

Create a new migration file.

```
make:migration [options] [--] <name>
```

| Argument / option | Type | Default | Description |
|---|---|---|---|
| `name` | required |  | The name of the migration |
| `--create=` | value |  | The table to be created |
| `--table=` | value |  | The table to migrate |
| `--timestamp` | flag |  | Prefix the file name with a timestamp even when using the toolkit |
| `-f`, `--force` | flag |  | Create the migration even if it already exists |

### `make:model`

Create a new Eloquent model class.

```
make:model [options] [--] <name>
```

| Argument / option | Type | Default | Description |
|---|---|---|---|
| `name` | required |  | The name of the model |
| `-a`, `--all` | flag |  | Generate a migration, seeder, factory, policy and resource controller for the model |
| `-m`, `--migration` | flag |  | Create a new migration file for the model |
| `-f`, `--factory` | flag |  | Create a new factory for the model |
| `-s`, `--seed` | flag |  | Create a new seeder for the model |
| `-c`, `--controller` | flag |  | Create a new controller for the model |
| `-r`, `--resource` | flag |  | Indicates if the generated controller should be a resource controller |
| `--api` | flag |  | Indicates if the generated controller should be an API resource controller |
| `-p`, `--policy` | flag |  | Create a new policy for the model |
| `--force` | flag |  | Create the class even if the model already exists |

### `make:notification`

Create a new notification class.

```
make:notification [options] [--] <name>
```

| Argument / option | Type | Default | Description |
|---|---|---|---|
| `name` | required |  | The name of the notification |
| `-f`, `--force` | flag |  | Create the notification even if it already exists |

### `make:observer`

Create a new observer class.

```
make:observer [options] [--] <name>
```

| Argument / option | Type | Default | Description |
|---|---|---|---|
| `name` | required |  | The name of the observer |
| `-m`, `--model=` | value |  | The model that the observer applies to |
| `-f`, `--force` | flag |  | Create the observer even if it already exists |

### `make:policy`

Create a new policy class.

```
make:policy [options] [--] <name>
```

| Argument / option | Type | Default | Description |
|---|---|---|---|
| `name` | required |  | The name of the policy |
| `-m`, `--model=` | value |  | The model that the policy applies to |
| `-f`, `--force` | flag |  | Create the policy even if it already exists |

### `make:provider`

Create a new service provider class.

```
make:provider [options] [--] [<name>]
```

| Argument / option | Type | Default | Description |
|---|---|---|---|
| `name` | optional |  | The name of the provider (defaults to "{Package}ServiceProvider") |
| `--main` | flag |  | Make it the main package provider (generators register resources in it) |
| `-f`, `--force` | flag |  | Create the provider even if it already exists |

### `make:request`

Create a new form request class.

```
make:request [options] [--] <name>
```

| Argument / option | Type | Default | Description |
|---|---|---|---|
| `name` | required |  | The name of the request |
| `-f`, `--force` | flag |  | Create the request even if it already exists |

### `make:resource`

Create a new API resource.

```
make:resource [options] [--] <name>
```

| Argument / option | Type | Default | Description |
|---|---|---|---|
| `name` | required |  | The name of the resource |
| `-c`, `--collection` | flag |  | Create a resource collection |
| `-f`, `--force` | flag |  | Create the resource even if it already exists |

### `make:route`

Create a new route file (web, api, channels, ...).

```
make:route [options] [--] [<name>]
```

| Argument / option | Type | Default | Description |
|---|---|---|---|
| `name` | optional | `web` | The name of the route file: web, api, channels or any other |
| `-f`, `--force` | flag |  | Create the routes even if it already exists |

### `make:rule`

Create a new validation rule.

```
make:rule [options] [--] <name>
```

| Argument / option | Type | Default | Description |
|---|---|---|---|
| `name` | required |  | The name of the rule |
| `-f`, `--force` | flag |  | Create the rule even if it already exists |

### `make:scope`

Create a new Eloquent scope class.

```
make:scope [options] [--] <name>
```

| Argument / option | Type | Default | Description |
|---|---|---|---|
| `name` | required |  | The name of the scope |
| `-f`, `--force` | flag |  | Create the scope even if it already exists |

### `make:seeder`

Create a new seeder class. Alias: `make:seed`.

```
make:seeder [options] [--] <name>
```

| Argument / option | Type | Default | Description |
|---|---|---|---|
| `name` | required |  | The name of the seeder |
| `-f`, `--force` | flag |  | Create the seeder even if it already exists |

### `make:structure`

Create the standard Laravel package directory structure.

```
make:structure
```

### `make:test`

Create a new test.

```
make:test [options] [--] <name>
```

| Argument / option | Type | Default | Description |
|---|---|---|---|
| `name` | required |  | The name of the test |
| `-u`, `--unit` | flag |  | Create a unit test |
| `--phpunit` | flag |  | Create a PHPUnit test class instead of a Pest test |
| `-f`, `--force` | flag |  | Create the test even if it already exists |

### `make:trait`

Create a new trait.

```
make:trait [options] [--] <name>
```

| Argument / option | Type | Default | Description |
|---|---|---|---|
| `name` | required |  | The name of the trait |
| `-f`, `--force` | flag |  | Create the trait even if it already exists |

### `make:view`

Create a new Blade view.

```
make:view [options] [--] <name>
```

| Argument / option | Type | Default | Description |
|---|---|---|---|
| `name` | required |  | The name of the view |
| `-f`, `--force` | flag |  | Create the view even if it already exists |

### `package:doctor`

Check that the service provider and composer.json match the files of the package.

```
package:doctor [options]
```

| Argument / option | Type | Default | Description |
|---|---|---|---|
| `--fix` | flag |  | Register what is missing in the service provider and composer.json |
| `--strict` | flag |  | Exit with 1 on warnings as well as on errors |
| `--json` | flag |  | Print the findings as JSON |
| `--all` | flag |  | Check every package of the monorepo |

### `package:info`

Show what the console knows about the package.

```
package:info [options]
```

| Argument / option | Type | Default | Description |
|---|---|---|---|
| `--all` | flag |  | List every package of the monorepo |

### `package:init`

Create composer.json and scaffold a Laravel package. Alias: `init`.

```
package:init [options]
```

| Argument / option | Type | Default | Description |
|---|---|---|---|
| `--name=` | value |  | Composer package name (vendor/name) |
| `--description=` | value |  | Package description |
| `--namespace=` | value |  | Root PHP namespace (e.g. "Acme\Blog") |
| `--author=` | value |  | Author name (defaults to git config user.name) |
| `--email=` | value |  | Author email (defaults to git config user.email) |
| `--toolkit` | flag |  | Use nyoncode/laravel-package-toolkit |
| `--no-toolkit` | flag |  | Use a plain Laravel ServiceProvider |
| `--laravel=` | value | `12,13` | Supported Laravel versions, comma separated |
| `--no-tests` | flag |  | Do not scaffold Pest, Testbench and the CI workflow |
| `--no-config` | flag |  | Do not create a config file |
| `-f`, `--force` | flag |  | Overwrite scaffolded files that already exist |

<!-- commands:end -->
