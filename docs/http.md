---
title: HTTP
description: make:controller, make:middleware, make:request and make:resource for Laravel packages.
---

# HTTP

- [make:controller](#makecontroller)
- [make:middleware](#makemiddleware)
- [make:request](#makerequest)
- [make:resource](#makeresource)

Routes are covered in [Config & routes](config-and-routes.md#makeroute).

## make:controller

```bash
php anvil make:controller PostController
php anvil make:controller PostController --resource
php anvil make:controller Api/PostController --api --model=Post
php anvil make:controller PingController --invokable
```

| Option              | Generates                                                                                                   |
|---------------------|-------------------------------------------------------------------------------------------------------------|
| *(none)*            | an empty class                                                                                              |
| `-r`, `--resource`  | `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`                                             |
| `--api`             | the resource methods without `create` and `edit`                                                            |
| `-i`, `--invokable` | a single `__invoke(Request $request)` method                                                                |
| `-m`, `--model=`    | resource methods that take the model, e.g. `show(Post $post)`; implies `--resource` unless `--api` is given |
| `-f`, `--force`     | overwrite an existing file                                                                                  |

When options combine, `--invokable` wins over `--api`, and `--api` wins over `--resource`.
Without `--model`, the resource methods take `string $id`.

Controllers are created in `src/Http/Controllers/`. They do not extend a base controller,
because a package has no `App\Http\Controllers\Controller`. Add a base class of your own when you
need one.

```php title="src/Http/Controllers/Api/PostController.php (--api --model=Post)"
<?php

namespace Acme\Blog\Http\Controllers\Api;

use Acme\Blog\Models\Post;
use Illuminate\Http\Request;

class PostController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Post $post)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Post $post)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Post $post)
    {
        //
    }
}
```

Register the controller in one of the package's [route files](config-and-routes.md#makeroute):

```php title="routes/api.php"
Route::apiResource('posts', PostController::class);
```

## make:middleware

```bash
php anvil make:middleware EnsureAuthor
php anvil make:middleware EnsureAuthor --alias=blog.author
```

| Option           |                                          |
|------------------|------------------------------------------|
| `-a`, `--alias=` | Register the middleware under this alias |
| `-f`, `--force`  | Overwrite an existing file               |

```php title="src/Http/Middleware/EnsureAuthor.php"
<?php

namespace Acme\Blog\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAuthor
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
}
```

Without `--alias`, the middleware is not registered: you reference its class directly. With
`--alias`:

| Toolkit                                                                                                       | Plain                                                                        |
|---------------------------------------------------------------------------------------------------------------|------------------------------------------------------------------------------|
| `->hasMiddlewareAliases(['blog.author' => EnsureAuthor::class])`; further aliases are added to the same array | `$this->app['router']->aliasMiddleware('blog.author', EnsureAuthor::class);` |

Applications then use it as `->middleware('blog.author')`. Prefix aliases with the package's short
name so they don't clash with the application's own. An alias may contain letters, numbers,
`_`, `-`, `.` and `:`.

## make:request

```bash
php anvil make:request StorePostRequest
```

Creates `src/Http/Requests/StorePostRequest.php`, a `FormRequest` with `authorize()` (returning
`false`) and an empty `rules()`. Options: `-f`/`--force`.

## make:resource

```bash
php anvil make:resource PostResource        # extends JsonResource
php anvil make:resource PostCollection      # extends ResourceCollection
php anvil make:resource Posts --collection  # extends ResourceCollection
```

| Option               |                                                                                    |
|----------------------|------------------------------------------------------------------------------------|
| `-c`, `--collection` | Create a resource collection. Names ending in `Collection` are collections anyway. |
| `-f`, `--force`      | Overwrite an existing file                                                         |

Resources are created in `src/Http/Resources/`.
