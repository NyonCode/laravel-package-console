---
title: Database & Eloquent
description: make:model, make:migration, make:factory, make:seeder, make:cast, make:scope and make:observer for Laravel packages.
---

# Database & Eloquent

- [make:model](#makemodel)
- [make:migration](#makemigration)
- [make:factory](#makefactory)
- [make:seeder](#makeseeder)
- [make:cast](#makecast)
- [make:scope](#makescope)
- [make:observer](#makeobserver)
- [Referring to models](#referring-to-models)

## make:model

```bash
php anvil make:model Post
php anvil make:model Post -mf         # with a migration and a factory
php anvil make:model Post -a          # with everything
```

| Option | Creates |
|---|---|
| `-m`, `--migration` | `create_posts_table` migration |
| `-f`, `--factory` | `PostFactory`, linked to the model with `newFactory()` |
| `-s`, `--seed` | `PostSeeder` |
| `-c`, `--controller` | `PostController` (plain) |
| `-r`, `--resource` | `PostController` as a resource controller for the model |
| `--api` | `PostController` as an API resource controller for the model |
| `-p`, `--policy` | `PostPolicy` for the model |
| `-a`, `--all` | migration, factory, seeder, policy and a resource controller |
| `--force` | overwrite the model and every related file it creates |

`-f` means `--factory` here, so `--force` has no shortcut on this command.

The related files are created by the matching generators (`make:migration`, `make:factory`, …),
so they are placed and registered exactly as if you ran those commands yourself.

```php title="src/Models/Post.php (with -f)"
<?php

namespace Acme\Blog\Models;

use Acme\Blog\Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): PostFactory
    {
        return PostFactory::new();
    }
}
```

**Why `newFactory()`?** Laravel looks for a model's factory in `Database\Factories\{Model}Factory`.
That is the application's namespace, not your package's. `newFactory()` points the model at the
package's own factory, so `Post::factory()` works in the package's tests and in every application
that installs it. Without `-f` the model has no `HasFactory`.

**Table names.** The migration's table name is built exactly the way Laravel's
`make:model -m` builds it: the last word of the model name is pluralised with the same inflector
Laravel uses ([doctrine/inflector](https://github.com/doctrine/inflector)), then the name is
converted to snake case.

| Model | Table |
|---|---|
| `Post` | `posts` |
| `BlogPost` | `blog_posts` |
| `Category` | `categories` |
| `Person` | `people` |
| `Child` | `children` |
| `Status` | `statuses` |

## make:migration

```bash
php anvil make:migration create_posts_table
php anvil make:migration add_slug_to_posts_table
php anvil make:migration CreatePostsTable           # converted to create_posts_table
php anvil make:migration blog_tables --create=blog_posts
```

| Argument / option | |
|---|---|
| `name` | The migration name, converted to snake case |
| `--create=` | The table to create |
| `--table=` | The table to modify |
| `--timestamp` | Prefix the file name with a timestamp even when the package uses the toolkit |
| `-f`, `--force` | Create the migration even if one with the same name exists |

**The stub is picked from the name**, the way Laravel picks it, unless `--create` or `--table`
says otherwise:

| Name | Stub |
|---|---|
| `create_posts_table`, `create_posts` | `Schema::create('posts', …)` with `id()` and `timestamps()` |
| `add_slug_to_posts_table`, `remove_slug_from_posts_table`, `rename_title_in_posts_table`, `add_slug_to_posts` | `Schema::table('posts', …)` in `up()` and `down()` |
| anything else | empty `up()` and `down()` |

**File names**

| Package | File |
|---|---|
| uses the toolkit | `database/migrations/create_posts_table.php` (timeless) |
| plain | `database/migrations/2026_01_15_093000_create_posts_table.php` |

When the application publishes timeless migrations, the toolkit gives them a timestamp from the
moment of publishing, so they run after the application's own migrations. `--timestamp` gives a
timestamped name even with the toolkit.

Two things matter with timeless migrations:

- **The name must be unique in the application.** Without a date prefix, the name is all that
  identifies the migration. Prefix your tables with the package, for example
  `create_blog_posts_table` rather than `create_posts_table`.
- **Order.** `->hasMigrations()` without arguments publishes the files in whatever order the
  filesystem returns them. When one migration depends on another, for example through a foreign
  key, list them explicitly so the generated timestamps come out in order:

  ```php
  ->hasMigrations([
      'create_blog_posts_table.php',
      'create_blog_comments_table.php',
  ])
  ```

  `make:migration` then appends each new migration to the end of that list.

**Duplicates.** Anvil refuses a second migration with the same name, whether or not either name is
timestamped, because the application would publish both:

```
 [ERROR] A migration named [create_posts_table] already exists: database/migrations/create_posts_table.php
```

**Registration**

| Toolkit | Plain |
|---|---|
| `->hasMigrations()`, or the file is added to an explicit `->hasMigrations([...])` list | `$this->publishesMigrations([__DIR__.'/../database/migrations' => database_path('migrations')], 'blog-migrations');` |

In both cases the application publishes the migrations and runs them with `php artisan migrate`:

```bash
php artisan vendor:publish --tag="blog::migrations"   # toolkit
php artisan vendor:publish --tag="blog-migrations"    # plain
```

## make:factory

```bash
php anvil make:factory Post              # PostFactory for Acme\Blog\Models\Post
php anvil make:factory DraftPost --model=Post
```

| Argument / option | |
|---|---|
| `name` | The factory name. `Factory` is appended when missing. |
| `-m`, `--model=` | The model. Defaults to the name without `Factory`. See [Referring to models](#referring-to-models). |
| `-f`, `--force` | Overwrite an existing file |

```php title="database/factories/PostFactory.php"
<?php

namespace Acme\Blog\Database\Factories;

use Acme\Blog\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Post>
     */
    protected $model = Post::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            //
        ];
    }
}
```

Factories live in `database/factories` in the `{Root}\Database\Factories` namespace. `package:init`
adds the PSR-4 entry for it. If your `composer.json` lacks it, `make:factory` adds it and asks
you to refresh the autoloader:

```
  DONE Added "Acme\Blog\Database\Factories\\" => "database/factories/" to composer.json autoload. Run composer dump-autoload.
```

The factory sets `$model` explicitly, because Laravel's convention-based guessing only finds
models in the application. Factories are autoloaded, not published, so there is nothing to
register.

## make:seeder

```bash
php anvil make:seeder BlogSeeder
php anvil make:seed BlogSeeder     # alias
```

| Argument / option | |
|---|---|
| `name` | The seeder class name |
| `-f`, `--force` | Overwrite an existing file |

```php title="database/seeders/BlogSeeder.php"
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class BlogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
    }
}
```

Package seeders are **published** into the application's `database/seeders`, so they use the
application's namespace, `Database\Seeders`, not your package's. The application then runs them
with `php artisan db:seed --class=BlogSeeder`.

Because they share the application's namespace, give them a name that is clearly yours, such as
`BlogSeeder` or `BlogPostSeeder`, so they do not overwrite one of the application's own seeders.
`make:model -s` names the seeder `{Model}Seeder`.

**Registration**

| Toolkit | Plain |
|---|---|
| `->hasSeeders()`, or the file is added to an explicit `->hasSeeders([...])` list | `$this->publishes([__DIR__.'/../database/seeders' => database_path('seeders')], 'blog-seeders');` |

## make:cast

```bash
php anvil make:cast Money            # implements CastsAttributes: get() and set()
php anvil make:cast Hashed --inbound # implements CastsInboundAttributes: set() only
```

The cast is created in `src/Casts/`. Options: `--inbound`, `-f`/`--force`.

## make:scope

```bash
php anvil make:scope PublishedScope
```

Creates `src/Models/Scopes/PublishedScope.php`, which implements
`Illuminate\Database\Eloquent\Scope` with an `apply(Builder $builder, Model $model): void`
method. Options: `-f`/`--force`.

## make:observer

```bash
php anvil make:observer PostObserver --model=Post
```

| Option | |
|---|---|
| `-m`, `--model=` | Generate `created`, `updated`, `deleted`, `restored` and `forceDeleted` methods for the model |
| `-f`, `--force` | Overwrite an existing file |

The observer is created in `src/Observers/`, and an empty class without `--model`. Anvil does not
register observers. Attach the observer to the model with the attribute it suggests:

```
  Attach it with #[ObservedBy(PostObserver::class)] on the Post model.
```

```php
use Acme\Blog\Observers\PostObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy(PostObserver::class)]
class Post extends Model
```

## Referring to models

`--model` (on `make:factory`, `make:controller`, `make:policy`, `make:observer`) and `--event` (on
`make:listener`) resolve class names the same way:

| You pass | Class |
|---|---|
| `Post` | `Acme\Blog\Models\Post` |
| `Admin/Post` | `Acme\Blog\Models\Admin\Post` |
| `Acme\Blog\Content\Post` | `Acme\Blog\Content\Post`: it is in the root namespace, so it is used as is |
| `'\Illuminate\Foundation\Auth\User'` | `Illuminate\Foundation\Auth\User`: a leading backslash means "use as is" |

The class is imported into the generated file, which uses its short name. A policy for a `User`
model names its parameter `$model`, because `$user` is already the authenticated user, as in
Laravel.
