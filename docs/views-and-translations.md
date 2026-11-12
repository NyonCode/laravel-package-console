---
title: Views & translations
description: make:view, make:component and make:lang create namespaced Blade views, Blade components and translations for a Laravel package.
---

# Views & translations

- [make:view](#makeview)
- [make:component](#makecomponent)
- [make:lang](#makelang)

Views and translations of a package are namespaced by the package's
[short name](how-it-works.md#the-short-name): `view('blog::posts.index')`, `<x-blog::alert />`,
`__('blog::messages.welcome')`.

## make:view

```bash
php anvil make:view posts.index
php anvil make:view posts/index              # the same
php anvil make:view posts/index.blade.php    # the same
```

```
  INFO View [resources/views/posts/index.blade.php] created successfully.
  DONE Registered ->hasViews() in src/BlogServiceProvider.php.
  Render it with view('blog::posts.index')
```

```blade title="resources/views/posts/index.blade.php"
<div>
    {{-- posts.index --}}
</div>
```

Options: `-f`/`--force`.

**Registration**

| Toolkit | Spatie | Plain |
|---|---|---|
| `->hasViews()` | `->hasViews()` | `$this->loadViewsFrom(__DIR__.'/../resources/views', 'blog');` and `$this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/blog')], 'blog-views');` |

Applications can publish the views to `resources/views/vendor/blog` and override them there.

## make:component

```bash
php anvil make:component Alert                    # class + view
php anvil make:component Forms/TextInput          # nested
php anvil make:component Alert --inline           # class with an inline template
php anvil make:component badge --view             # anonymous: a view only
```

| Option | |
|---|---|
| `--inline` | Render the template from the class instead of a separate view |
| `--view` | Create an anonymous component: only the Blade view, no class |
| `-f`, `--force` | Overwrite existing files |

A class-based component gets a class in `src/View/Components/` and a view in
`resources/views/components/`, named in kebab case:

| Name | Class | View | Usage |
|---|---|---|---|
| `Alert` | `Acme\Blog\View\Components\Alert` | `components/alert.blade.php` | `<x-blog::alert />` |
| `Forms/TextInput` | `Acme\Blog\View\Components\Forms\TextInput` | `components/forms/text-input.blade.php` | `<x-blog::forms.text-input />` |
| `badge --view` | none | `components/badge.blade.php` | `<x-blog::badge />` |

```php title="src/View/Components/Alert.php"
<?php

namespace Acme\Blog\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Alert extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('blog::components.alert');
    }
}
```

With `--inline`, `render()` returns the template itself and no view file is created:

```php
public function render(): View|Closure|string
{
    return <<<'blade'
        <div>
            {{-- blog::components.alert --}}
        </div>
    blade;
}
```

An anonymous component (`--view`) accepts a view name such as `forms.input` or `forms/input`.

**Registration**

| | Toolkit | Spatie | Plain |
|---|---|---|---|
| views (every component) | `->hasViews()` | `->hasViews()` | `loadViewsFrom()` + `publishes()`, as for `make:view` |
| class namespace (class-based components) | `->hasComponentNamespace('blog', 'Acme\Blog\View\Components')` | the plain code, in `packageBooted()` | `Blade::componentNamespace('Acme\Blog\View\Components', 'blog');` |

The component namespace maps the `blog` prefix to your component classes. That is how
`<x-blog::forms.text-input />` finds `Acme\Blog\View\Components\Forms\TextInput`, and it is
registered once for all components. Anonymous components need only the views.

## make:lang

```bash
php anvil make:lang                      # lang/en/messages.php
php anvil make:lang validation -l cs     # lang/cs/validation.php
php anvil make:lang --json --locale=cs   # lang/cs.json
php anvil make:lang --locale=en,cs       # lang/en/messages.php and lang/cs/messages.php
```

| Argument / option | |
|---|---|
| `name` | File name without `.php`, default `messages`. Ignored with `--json`. |
| `-l`, `--locale=` | The locale, default `en`. Several locales, separated by commas, create a file for each. |
| `--json` | Create the JSON file of the locale |
| `-f`, `--force` | Overwrite an existing file |

```php title="lang/en/messages.php"
<?php

return [

    //

];
```

```
  Use it with __('blog::messages.key')
```

With several locales, the translations are still registered once:

```
$ php anvil make:lang --locale=en,cs

  INFO Translation [lang/en/messages.php] created successfully.
  INFO Translation [lang/cs/messages.php] created successfully.
  DONE Registered loadTranslationsFrom(), loadJsonTranslationsFrom(), publishes() in boot() in src/BlogServiceProvider.php.
  Use it with __('blog::messages.key')
```

A locale whose file already exists is reported and skipped (the command then exits with 1),
and the other locales are still created.

With spatie/laravel-package-tools, the files go to `resources/lang/` instead of `lang/`
(`resources/lang/en/messages.php`, `resources/lang/cs.json`), because that is where the package
tools load translations from.

PHP translation files are namespaced: `__('blog::messages.welcome')`. JSON files are not. Their
keys are global strings, `__('Hello')`, merged with the application's own translations.

**Registration**

| Toolkit | Spatie | Plain |
|---|---|---|
| `->hasTranslations()` | `->hasTranslations()` | `$this->loadTranslationsFrom(__DIR__.'/../lang', 'blog');`, `$this->loadJsonTranslationsFrom(__DIR__.'/../lang');` and `$this->publishes([__DIR__.'/../lang' => lang_path('vendor/blog')], 'blog-translations');` |

Applications publish the translations to `lang/vendor/blog` and override them there.

**Keeping the languages in step**

[`package:doctor`](doctor.md#translations) warns when one language lacks keys another has, and
when the code uses a key no language defines. `package:doctor --fix` adds the missing keys, with
the text of the language that has them, ready to be translated:

```
  WARN  lang/cs/messages.php has no translation for 2 keys that en has: empty, actions.archive.
```
