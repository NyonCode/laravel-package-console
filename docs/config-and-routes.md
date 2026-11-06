---
title: Config & routes
description: make:config and make:route create config and route files and register them for the toolkit or a plain service provider.
---

# Config & routes

- [make:config](#makeconfig)
- [make:route](#makeroute)

## make:config

```bash
php anvil make:config            # config/blog.php
php anvil make:config blog-cache # config/blog-cache.php
```

| Argument / option | |
|---|---|
| `name` | File name without `.php`. Defaults to the [short name](how-it-works.md#the-short-name). |
| `-f`, `--force` | Overwrite an existing file |

```php title="config/blog.php"
<?php

// Configuration for Blog.

return [

    //

];
```

**Registration**

| Toolkit | Plain |
|---|---|
| `->hasConfig()` merges and publishes every file in `config/`, so later config files need nothing more | each file is merged under its own key and published with the `blog-config` tag |

```php title="Plain provider, after make:config blog-cache"
public function register(): void
{
    $this->mergeConfigFrom(__DIR__.'/../config/blog-cache.php', 'blog-cache');
}

public function boot(): void
{
    $this->publishes([
        __DIR__.'/../config/blog-cache.php' => config_path('blog-cache.php'),
    ], 'blog-config');
}
```

Applications publish the config with:

```bash
php artisan vendor:publish --tag="blog::config"   # toolkit
php artisan vendor:publish --tag="blog-config"    # plain
```

`package:init` already runs `make:config` for the main config file, unless you pass `--no-config`.

## make:route

```bash
php anvil make:route            # routes/web.php
php anvil make:route api        # routes/api.php
php anvil make:route channels   # routes/channels.php
php anvil make:route admin      # routes/admin.php
```

| Argument / option | |
|---|---|
| `name` | `web` (default), `api`, `channels` or any other file name, without `.php` |
| `-f`, `--force` | Overwrite an existing file |

Each name has its own stub. The routes are prefixed and named after the package, so they never
clash with the application's routes:

```php title="routes/web.php"
<?php

use Illuminate\Support\Facades\Route;

Route::middleware('web')
    ->prefix('blog')
    ->name('blog.')
    ->group(function () {
        //
    });
```

```php title="routes/api.php"
<?php

use Illuminate\Support\Facades\Route;

Route::middleware('api')
    ->prefix('api/blog')
    ->name('blog.api.')
    ->group(function () {
        //
    });
```

```php title="routes/admin.php (any other name)"
<?php

use Illuminate\Support\Facades\Route;

Route::prefix('blog/admin')
    ->name('blog.admin.')
    ->group(function () {
        //
    });
```

The `web` and `api` files apply the matching middleware group, but files with any other name do
not, so add one when you need it.

```php title="routes/channels.php"
<?php

use Illuminate\Support\Facades\Broadcast;

// Broadcast::channel('blog.{id}', function ($user, $id) {
//     return (int) $user->id === (int) $id;
// });
```

**Registration**

| File | Toolkit | Plain |
|---|---|---|
| `web`, `api`, others | `->hasRoutes(['web.php', 'api.php'])` | `$this->loadRoutesFrom(__DIR__.'/../routes/web.php');` |
| `channels` | `->hasBroadcastChannels(['channels.php'])` | `require __DIR__.'/../routes/channels.php';` |

Channels are broadcast authorisation callbacks, not routes, so they are registered separately.
For channel classes, see [make:channel](events-queues-and-mail.md#makechannel).
