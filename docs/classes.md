---
title: Classes
description: make:class, make:interface, make:trait, make:enum, make:exception, make:facade, make:rule, make:policy and make:provider.
---

# Classes

- [make:class](#makeclass)
- [make:interface](#makeinterface)
- [make:trait](#maketrait)
- [make:enum](#makeenum)
- [make:exception](#makeexception)
- [make:facade](#makefacade)
- [make:rule](#makerule)
- [make:policy](#makepolicy)
- [make:provider](#makeprovider)

## make:class

```bash
php anvil make:class Blog                         # src/Blog.php
php anvil make:class Support/Slugger              # src/Support/Slugger.php
php anvil make:class Actions/PublishPost -i       # with __invoke()
```

| Option | |
|---|---|
| `-i`, `--invokable` | Add an `__invoke(): void` method |
| `-f`, `--force` | Overwrite an existing file |

`make:class` uses the root namespace itself, so the name decides where the class goes.

## make:interface

```bash
php anvil make:interface Publishable    # src/Contracts/Publishable.php
```

## make:trait

```bash
php anvil make:trait HasSlug            # src/Concerns/HasSlug.php
```

## make:enum

```bash
php anvil make:enum Visibility             # enum Visibility
php anvil make:enum PostStatus --string    # enum PostStatus: string
php anvil make:enum Priority --int         # enum Priority: int
```

| Option | |
|---|---|
| `-s`, `--string` | A string-backed enum |
| `-i`, `--int` | An integer-backed enum |
| `-f`, `--force` | Overwrite an existing file |

Enums are created in `src/Enums/`.

## make:exception

```bash
php anvil make:exception InvalidSlug
php anvil make:exception PostNotFound --render
```

| Option | |
|---|---|
| `--render` | Add `report(): void` and `render(Request $request): ?Response` methods |
| `-f`, `--force` | Overwrite an existing file |

Exceptions are created in `src/Exceptions/` and extend `Exception`. With `--render`, `render()`
returns `null`, so Laravel renders the exception as usual until you return a response.

## make:facade

```bash
php anvil make:facade Blog                          # resolves \Acme\Blog\Blog::class
php anvil make:facade Blog --accessor=blog.manager  # resolves the 'blog.manager' binding
```

| Option | |
|---|---|
| `--accessor=` | The container binding the facade resolves. By default it is the class of the same name in the root namespace. |
| `-f`, `--force` | Overwrite an existing file |

```php title="src/Facades/Blog.php"
<?php

namespace Acme\Blog\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Acme\Blog\Blog
 */
class Blog extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return \Acme\Blog\Blog::class;
    }
}
```

The container resolves a concrete class without any setup. If you use `--accessor`, bind that
name in your service provider, for example with
`$this->app->singleton('blog.manager', fn () => new BlogManager)`. In a toolkit provider, put
the binding in the `registeringPackage()` hook (see the toolkit's lifecycle hooks).

## make:rule

```bash
php anvil make:rule Slug    # src/Rules/Slug.php
```

Creates a class that implements `Illuminate\Contracts\Validation\ValidationRule`, with a
`validate(string $attribute, mixed $value, Closure $fail): void` method.

## make:policy

```bash
php anvil make:policy PostPolicy --model=Post
php anvil make:policy CommentPolicy
```

| Option | |
|---|---|
| `-m`, `--model=` | Generate `viewAny`, `view`, `create`, `update`, `delete`, `restore` and `forceDelete` for the model. See [Referring to models](database.md#referring-to-models). |
| `-f`, `--force` | Overwrite an existing file |

Policies are created in `src/Policies/`. The methods type-hint
`Illuminate\Contracts\Auth\Authenticatable` for the user, because a package does not know the
application's user model:

```php
public function update(Authenticatable $user, Post $post): bool
{
    return false;
}
```

Anvil does not register policies. Laravel discovers policies by convention only for the
application's own models, so register yours in the service provider:

```php
use Illuminate\Support\Facades\Gate;

Gate::policy(Post::class, PostPolicy::class);
```

## make:provider

```bash
php anvil make:provider                          # the main provider, if there is none yet
php anvil make:provider EventServiceProvider     # src/Providers/EventServiceProvider.php
php anvil make:provider CoreServiceProvider --main
```

| Argument / option | |
|---|---|
| `name` | The class name. Defaults to `{Package}ServiceProvider`, e.g. `BlogServiceProvider`. |
| `--main` | Make it the [main provider](how-it-works.md#the-main-service-provider) |
| `-f`, `--force` | Overwrite an existing file |

There are two kinds of provider:

| | Main provider | Other providers |
|---|---|---|
| When | `--main`, or the package has no main provider yet | otherwise |
| Namespace | the root namespace, `src/` | `{Root}\Providers`, `src/Providers/` |
| Stub | the toolkit stub, if `composer.json` requires the toolkit; the plain stub otherwise | the plain stub |
| `extra.laravel.providers` | added **first** | added last |

The provider is added to `extra.laravel.providers` so that Laravel's package discovery loads it:

```
  INFO Provider [src/Providers/EventServiceProvider.php] created successfully.
  DONE Registered in composer.json "extra.laravel.providers".
```

`package:init` creates the main provider for you, so you only need `make:provider` for extra
providers, or to move the main role to another class.
