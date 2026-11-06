---
title: Quickstart
description: Build a Laravel package with a model, routes, a command, views and translations, from an empty directory to green tests.
---

# Quickstart

In this guide we build `acme/laravel-blog`, a package with posts, a web route, an Artisan
command, a Blade component and translations. It uses
[laravel-package-toolkit](https://github.com/NyonCode/laravel-package-toolkit). Without it, add
`--no-toolkit` to the first command: every step stays the same, and only the code Anvil adds to
the service provider differs.

## 1. Create the package

```bash
mkdir laravel-blog && cd laravel-blog
anvil package:init --name=acme/laravel-blog --toolkit
```

This uses a global install. With Anvil installed in another project, run
`/path/to/vendor/bin/anvil package:init …` from the new directory instead.

Next, install the dependencies, Anvil itself and run the generated tests:

```bash
composer update
composer require --dev nyoncode/laravel-package-console
vendor/bin/pest
```

From now on, use the launcher in the package root:

```bash
php anvil list
```

## 2. Add the posts

```bash
php anvil make:model Post -mf
```

```
  INFO Model [src/Models/Post.php] created successfully.
  INFO Factory [database/factories/PostFactory.php] created successfully.
  INFO Migration [database/migrations/create_posts_table.php] created successfully.
  DONE Registered ->hasMigrations() in src/BlogServiceProvider.php.
```

Fill in the migration and the factory:

```php title="database/migrations/create_posts_table.php"
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->timestamps();
});
```

```php title="database/factories/PostFactory.php"
public function definition(): array
{
    return [
        'title' => fake()->sentence(),
    ];
}
```

## 3. Add a route and a controller

```bash
php anvil make:route web
php anvil make:controller PostController --invokable
```

```php title="routes/web.php"
<?php

use Acme\Blog\Http\Controllers\PostController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')
    ->prefix('blog')
    ->name('blog.')
    ->group(function () {
        Route::get('posts', PostController::class)->name('posts');
    });
```

The route is available at `/blog/posts` as `route('blog.posts')`.

## 4. Add a view, a component and translations

```bash
php anvil make:view posts.index
php anvil make:component PostCard
php anvil make:lang
```

```
  INFO View [resources/views/posts/index.blade.php] created successfully.
  DONE Registered ->hasViews() in src/BlogServiceProvider.php.
  Render it with view('blog::posts.index')
  INFO Component [src/View/Components/PostCard.php] created successfully.
  INFO View [resources/views/components/post-card.blade.php] created successfully.
  DONE Registered ->hasComponentNamespace('blog', ...) in src/BlogServiceProvider.php.
  Use it as <x-blog::post-card />
  INFO Translation [lang/en/messages.php] created successfully.
  DONE Registered ->hasTranslations() in src/BlogServiceProvider.php.
  Use it with __('blog::messages.key')
```

## 5. Add an Artisan command

```bash
php anvil make:command PruneDraftsCommand
```

```
  INFO Console command [src/Commands/PruneDraftsCommand.php] created successfully.
  DONE Registered ->hasCommands([PruneDraftsCommand::class]) in src/BlogServiceProvider.php.
```

The signature is `blog:prune-drafts`.

## 6. Look at the service provider

Every step registered itself:

```php title="src/BlogServiceProvider.php"
public function configure(Packager $packager): void
{
    $packager
        ->name('Blog')
        ->hasShortName('blog')
        ->hasConfig()
        ->hasMigrations()
        ->hasRoutes(['web.php'])
        ->hasViews()
        ->hasComponentNamespace('blog', 'Acme\Blog\View\Components')
        ->hasTranslations()
        ->hasCommands([PruneDraftsCommand::class]);
}
```

## 7. Test it

```bash
php anvil make:test Posts
```

```php title="tests/Feature/PostsTest.php"
<?php

use Acme\Blog\Models\Post;

beforeEach(function () {
    (require __DIR__.'/../../database/migrations/create_posts_table.php')->up();
});

it('creates posts through the package factory', function () {
    expect(Post::factory()->create()->title)->toBeString();
});

it('serves the posts page', function () {
    $this->get(route('blog.posts'))->assertOk();
});
```

```bash
composer test
composer analyse
composer format
```

## Next steps

- [Service provider registration](service-provider.md): what each generator adds, in both modes
- [Command reference](commands.md): every option of every command
- [Customising stubs](stubs.md): make the generated code yours
