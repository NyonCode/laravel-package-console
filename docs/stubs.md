---
title: Customising stubs
description: Override any stub Anvil generates from by putting your own copy in stubs/anvil/, with the full list of stubs and placeholders.
---

# Customising stubs

- [Overriding a stub](#overriding-a-stub)
- [Placeholders](#placeholders)
- [Class stubs](#class-stubs)
- [File stubs](#file-stubs)
- [Package skeleton stubs](#package-skeleton-stubs)

## Overriding a stub

Anvil looks for each stub in your package first:

1. `stubs/anvil/{stub}` in the package you are building;
2. in a [monorepo](monorepo.md#custom-stubs-for-the-whole-monorepo), `stubs/anvil/{stub}` in its root;
3. `stubs/{stub}` shipped with Anvil.

To change what a generator writes, copy its stub into `stubs/anvil/` under the same name and edit
it:

```bash
mkdir -p stubs/anvil
cp vendor/nyoncode/laravel-package-console/stubs/model.stub stubs/anvil/model.stub
```

```php title="stubs/anvil/model.stub"
<?php

namespace {{ namespace }};

use Illuminate\Database\Eloquent\Model;

final class {{ class }} extends Model
{
    protected $guarded = [];
}
```

Every `make:model` without `-f` now uses it. Overrides work for one stub at a time, so you only
copy the ones you change.

Keep `stubs/` out of your dist archive with `/stubs export-ignore` in `.gitattributes`.

Write stubs that pass Pint's `laravel` preset, as the built-in ones do:

- import classes instead of writing fully qualified names in docblocks;
- end the file with a newline.

Class generators sort and deduplicate the `use` block for you.

## Placeholders

Placeholders are written `{{ name }}` or `{{name}}`. Laravel's legacy `DummyClass`,
`DummyNamespace` and `DummyRootNamespace` also work in class stubs.

**Every class stub** gets:

| Placeholder | Example |
|---|---|
| `{{ class }}` | `PostController` |
| `{{ namespace }}` | `Acme\Blog\Http\Controllers` |
| `{{ rootNamespace }}` | `Acme\Blog` |
| `{{ package }}` | `blog`, the [short name](how-it-works.md#the-short-name) |
| `{{ packageName }}` | `Blog`, the display name |
| `{{ kebabClass }}` | `post-controller` |
| `{{ snakeClass }}` | `post_controller` |

**Every file stub** (config, routes, migrations, views, translations, tests) gets:

| Placeholder | Example |
|---|---|
| `{{ name }}` | the normalised name: `blog-cache`, `admin`, `create_posts_table`, `posts.index`, `messages` |
| `{{ package }}` | `blog` |
| `{{ packageName }}` | `Blog` |

The templates that `make:component` and `make:mail` create (`view.stub`,
`mail.markdown.view.stub`) only get `{{ name }}`, the dotted view name.

## Class stubs

| Stub | Used by | Extra placeholders |
|---|---|---|
| `class.stub` | `make:class` | |
| `class.invokable.stub` | `make:class --invokable` | |
| `interface.stub` | `make:interface` | |
| `trait.stub` | `make:trait` | |
| `enum.stub` | `make:enum` | |
| `enum.backed.stub` | `make:enum --string` / `--int` | `{{ type }}`: `string` or `int` |
| `exception.stub` | `make:exception` | |
| `exception.render-report.stub` | `make:exception --render` | |
| `facade.stub` | `make:facade` | `{{ facadeAccessor }}`: `\Acme\Blog\Blog::class` or `'blog.manager'`; `{{ accessorClass }}`: the same without `::class` and quotes |
| `rule.stub` | `make:rule` | |
| `policy.stub` | `make:policy --model` | `{{ model }}`, `{{ modelVariable }}` |
| `policy.plain.stub` | `make:policy` | |
| `provider.stub` | `make:provider` (plain, and every non-main provider) | |
| `provider.toolkit.stub` | `make:provider` (main provider with the toolkit) | |
| `model.stub` | `make:model` | |
| `model.factory.stub` | `make:model -f` | `{{ factory }}`: `PostFactory` |
| `factory.stub` | `make:factory` | `{{ model }}`: `Post` |
| `seeder.stub` | `make:seeder` | |
| `cast.stub` | `make:cast` | |
| `cast.inbound.stub` | `make:cast --inbound` | |
| `scope.stub` | `make:scope` | |
| `observer.stub` | `make:observer --model` | `{{ model }}`, `{{ modelVariable }}` |
| `observer.plain.stub` | `make:observer` | |
| `controller.plain.stub` | `make:controller` | |
| `controller.resource.stub` | `make:controller --resource` / `--model` | `{{ modelParameter }}`: `Post $post` or `string $id` |
| `controller.api.stub` | `make:controller --api` | `{{ modelParameter }}` |
| `controller.invokable.stub` | `make:controller --invokable` | |
| `middleware.stub` | `make:middleware` | |
| `request.stub` | `make:request` | |
| `resource.stub` | `make:resource` | |
| `resource-collection.stub` | `make:resource --collection` | |
| `console.stub` | `make:command` | `{{ command }}`: the signature |
| `event.stub` | `make:event` | |
| `listener.stub` | `make:listener` | `{{ eventType }}`: the event's short name, or `object` |
| `listener.queued.stub` | `make:listener --queued` | `{{ eventType }}` |
| `job.stub` | `make:job` | |
| `job.sync.stub` | `make:job --sync` | |
| `notification.stub` | `make:notification` | |
| `mail.stub` | `make:mail` | `{{ subject }}`, `{{ contentType }}` (`markdown` or `view`), `{{ view }}` (`blog::mail.order-shipped`) |
| `channel.stub` | `make:channel` | |
| `component.stub` | `make:component` | `{{ view }}`: `view('blog::components.alert')` or the inline template |

## File stubs

| Stub | Used by | Extra placeholders |
|---|---|---|
| `config.stub` | `make:config` | |
| `route.web.stub` | `make:route web` | |
| `route.api.stub` | `make:route api` | |
| `route.channels.stub` | `make:route channels` | |
| `route.stub` | `make:route {other}` | |
| `migration.stub` | `make:migration` (no table) | `{{ table }}` |
| `migration.create.stub` | `make:migration` (create) | `{{ table }}` |
| `migration.update.stub` | `make:migration` (change) | `{{ table }}` |
| `view.stub` | `make:view`, `make:component` views, `make:mail --view` | |
| `mail.markdown.view.stub` | `make:mail --markdown` | |
| `lang.stub` | `make:lang` | |
| `test.stub` | `make:test` | `{{ description }}`: `works` |
| `test.phpunit.stub` | `make:test --phpunit` | `{{ class }}`, `{{ namespace }}`, `{{ rootNamespace }}` |

`make:lang --json` does not use a stub: it always writes an empty JSON object.

## Package skeleton stubs

The files `package:init` writes live in `stubs/package/`. Override them in
`stubs/anvil/package/`, for example `stubs/anvil/package/README.stub`.

| Stub | Writes |
|---|---|
| `anvil.stub` | `anvil` |
| `gitignore.stub` | `.gitignore` |
| `gitattributes.stub` | `.gitattributes` |
| `README.stub` | `README.md` |
| `LICENSE.stub` | `LICENSE.md` |
| `pint.json.stub` | `pint.json` |
| `phpstan.neon.dist.stub` | `phpstan.neon.dist` |
| `testbench.yaml.stub` | `testbench.yaml` |
| `phpunit.xml.stub` | `phpunit.xml` |
| `TestCase.stub` | `tests/TestCase.php` |
| `Pest.stub` | `tests/Pest.php` |
| `ArchTest.stub` | `tests/ArchTest.php` |
| `ExampleTest.stub` | `tests/Feature/ServiceProviderTest.php` |
| `workflow.stub` | `.github/workflows/tests.yml` |

They get these placeholders:

| Placeholder | Example |
|---|---|
| `{{ rootNamespace }}` | `Acme\Blog` |
| `{{ package }}` | `blog` |
| `{{ packageName }}` | `Blog` |
| `{{ composerName }}` | `acme/laravel-blog` |
| `{{ description }}` | `A Laravel package.` |
| `{{ provider }}` | `Acme\Blog\BlogServiceProvider` |
| `{{ providerClass }}` | `BlogServiceProvider` |
| `{{ php }}` | `^8.2` |
| `{{ laravel }}` | `12.x / 13.x` |
| `{{ configTag }}` | `blog::config` (toolkit) or `blog-config` |
| `{{ year }}` | `2026` |
| `{{ author }}` | `Jane Doe` |
| `{{ appKey }}` | `base64:…`, a random key |
| `{{ phpVersions }}` | `'8.2', '8.3', '8.4', '8.5'` |
| `{{ laravelVersions }}` | `'12.*', '13.*'` |
| `{{ matrixInclude }}` | the workflow's `include:` entries |
| `{{ matrixExclude }}` | the workflow's `exclude:` block, or nothing |
