---
title: How Anvil reads your package
description: Anvil has no configuration file. It derives namespaces, paths, the short name and the main service provider from your composer.json.
---

# How Anvil reads your package

- [composer.json is the configuration](#composerjson-is-the-configuration)
- [The root namespace](#the-root-namespace)
- [Where classes are written](#where-classes-are-written)
- [The short name](#the-short-name)
- [The main service provider](#the-main-service-provider)
- [Toolkit mode or plain mode](#toolkit-mode-or-plain-mode)
- [package:info](#packageinfo)

## composer.json is the configuration

Anvil has no config file of its own. Everything it needs comes from the `composer.json` in the
package root:

| Anvil needs | It reads |
|---|---|
| the root namespace | the `autoload.psr-4` entry mapped to `src/` |
| where a class goes | `autoload.psr-4` and `autoload-dev.psr-4` |
| the short name | `name` |
| the main service provider | `extra.laravel.providers` |
| whether you use the toolkit | `require` / `require-dev` |

When you edit `composer.json` by hand, for example to rename the namespace or reorder the
providers, the next command uses the new values. No cache needs clearing.

## The root namespace

The root namespace is the PSR-4 namespace mapped to `src/` (or to `./` or the package root). The
generators put their classes under it. For example, `make:model` uses `{Root}\Models`.

```json
"autoload": {
    "psr-4": {
        "Acme\\Blog\\": "src/"
    }
}
```

Without such an entry, generators stop with an explanation:

```
 [ERROR] composer.json not found in [/path/to/package]. Run "package:init" first.
```

```
 [ERROR] No PSR-4 namespace mapped to "src/" in composer.json. Run "package:init" to add it.
```

## Where classes are written

The file path of a class follows from your PSR-4 mappings. Anvil takes both `autoload` and
`autoload-dev` into account, and the **longest matching namespace prefix wins**. This is
exactly how Composer resolves the class, so the file is always where Composer looks for it:

```json
"psr-4": {
    "Acme\\Blog\\": "src/",
    "Acme\\Blog\\Database\\Factories\\": "database/factories/"
}
```

| Class | File |
|---|---|
| `Acme\Blog\Models\Post` | `src/Models/Post.php` |
| `Acme\Blog\Database\Factories\PostFactory` | `database/factories/PostFactory.php` |

Names you pass to a generator are relative to its namespace: `make:class Other/Thing` creates
`Acme\Blog\Other\Thing` in `src/Other/Thing.php`. See
[Generators: the basics](generators.md#names).

## The short name

The short name is your package's name inside a Laravel application. Anvil derives it from the
Composer name:

1. drop the vendor;
2. drop a `laravel-` prefix or a `-laravel` suffix;
3. convert to kebab case.

| Composer name | Short name | Display name | Default provider |
|---|---|---|---|
| `acme/laravel-blog` | `blog` | `Blog` | `BlogServiceProvider` |
| `acme/blog-laravel` | `blog` | `Blog` | `BlogServiceProvider` |
| `acme/laravel-blog-posts` | `blog-posts` | `Blog Posts` | `BlogPostsServiceProvider` |
| `my-company/seo-tools` | `seo-tools` | `Seo Tools` | `SeoToolsServiceProvider` |

The short name appears everywhere a package needs a prefix:

| | Example for `blog` |
|---|---|
| the config file and key | `config/blog.php`, `config('blog.…')` |
| the view namespace | `view('blog::posts.index')` |
| the translation namespace | `__('blog::messages.welcome')` |
| the Blade component prefix | `<x-blog::alert />` |
| publish tags | `blog::config` (toolkit), `blog-config` (plain) |
| console command signatures | `blog:sync-posts` |
| route prefixes and names | `/blog/…`, `blog.…` |

With the toolkit, the generated provider pins the short name with `->hasShortName('blog')`, so
Anvil and the toolkit always agree on it.

## The main service provider

Generators register their files in the **main service provider**. It is the first class in
`extra.laravel.providers` whose file exists:

```json
"extra": {
    "laravel": {
        "providers": [
            "Acme\\Blog\\BlogServiceProvider",
            "Acme\\Blog\\Providers\\EventServiceProvider"
        ]
    }
}
```

Here it is `Acme\Blog\BlogServiceProvider`. Anvil skips entries whose class file is missing, so
the first existing provider is used even when the list contains a stale entry.

To make another provider the main one, move it to the top of the list, or create it with
[`make:provider --main`](classes.md#makeprovider).

If the package has no provider yet, every generator still writes its file, then warns:

```
  WARN No service provider found in composer.json "extra.laravel.providers". Run "make:provider" to create one, then re-run this command with --force.
```

## Toolkit mode or plain mode

Anvil reads the main provider itself to decide how to register files:

- If the provider has a **`configure()` method**, it is a
  [laravel-package-toolkit](https://github.com/NyonCode/laravel-package-toolkit) provider, and
  Anvil extends the `$packager` chain.
- Otherwise it is a plain Laravel `ServiceProvider`, and Anvil adds standard calls to
  `register()` and `boot()`.

The **provider file** decides the mode, not `composer.json`. A package can require the toolkit and
still have a plain provider, and Anvil then registers in plain mode.

Whether `composer.json` requires `nyoncode/laravel-package-toolkit` matters in three places:

- `make:provider` uses the toolkit stub for the main provider;
- [`make:migration`](database.md#makemigration) writes timeless migrations;
- `package:init` uses the toolkit by default.

[Service provider registration](service-provider.md) shows what each generator adds in both
modes.

## package:info

`package:info` prints everything above, which helps when a generator does something you did
not expect:

```bash
php anvil package:info
```

```
+----------------+-------------------------------------------------------------------+
| Path           | /path/to/laravel-blog                                             |
| Composer name  | acme/laravel-blog                                                 |
| Short name     | blog (config key, view/translation namespace, publish tag prefix) |
| Root namespace | Acme\Blog                                                         |
| PSR-4          | Acme\Blog\ => src/                                                |
|                | Acme\Blog\Database\Factories\ => database/factories/              |
| Toolkit        | yes (nyoncode/laravel-package-toolkit)                            |
| Main provider  | Acme\Blog\BlogServiceProvider                                     |
|                | src/BlogServiceProvider.php                                       |
| Providers      | Acme\Blog\BlogServiceProvider                                     |
|                | Acme\Blog\Providers\EventServiceProvider                          |
| Laravel        | ^12.0|^13.0                                                       |
+----------------+-------------------------------------------------------------------+
```

Without a `composer.json`, it exits with code 1:

```
 [WARNING] No composer.json in /path/to/package. Run "package:init" to create the package.
```
