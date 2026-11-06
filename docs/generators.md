---
title: "Generators: the basics"
description: How every make:* command names, places and validates files, and what it prints.
---

# Generators: the basics

- [Class generators and file generators](#class-generators-and-file-generators)
- [Names](#names)
- [Existing files and --force](#existing-files-and---force)
- [Validation](#validation)
- [Missing arguments](#missing-arguments)
- [Output and exit codes](#output-and-exit-codes)

## Class generators and file generators

There are two kinds of generators:

- **Class generators** create a PHP class in the package's namespace, for example
  `make:model`, `make:controller` and `make:job`. The file path follows from your
  [PSR-4 mappings](how-it-works.md#where-classes-are-written).
- **File generators** create a file that is not a class, at a fixed place: `make:config`,
  `make:route`, `make:migration`, `make:view`, `make:lang` and `make:test`.

Every generator is built from a [stub](stubs.md), which you can customise.

## Names

Each class generator has a default namespace under the
[root namespace](how-it-works.md#the-root-namespace):

| Generator | Namespace | Directory |
|---|---|---|
| `make:class` | `Acme\Blog` | `src/` |
| `make:model` | `Acme\Blog\Models` | `src/Models/` |
| `make:controller` | `Acme\Blog\Http\Controllers` | `src/Http/Controllers/` |
| `make:factory` | `Acme\Blog\Database\Factories` | `database/factories/` |
| `make:seeder` | `Database\Seeders` | `database/seeders/` |

The other generators are listed in the [command reference](commands.md#namespaces).

The name you pass is relative to that namespace:

```bash
php anvil make:model Post                 # Acme\Blog\Models\Post         src/Models/Post.php
php anvil make:model Admin/User           # Acme\Blog\Models\Admin\User   src/Models/Admin/User.php
php anvil make:model 'Admin\User'         # the same; quote backslashes in the shell
php anvil make:model Post.php             # the same as Post
php anvil make:class 'Acme\Blog\Services\Publisher'   # starts with the root namespace: used as is
```

- **Nesting.** Nest with `/` or `\`. Each segment becomes a namespace segment and a directory.
- **Capitalisation.** Only the first letter of the class name is capitalised, so
  `make:model post` creates `Post`. Write namespace segments the way you want them:
  `admin/post` creates `Acme\Blog\Models\admin\Post`.
- **Suffixes.** A few generators add a suffix when the name lacks it:
  - `make:factory Post` creates `PostFactory`;
  - `make:test PostApi` creates `PostApiTest`.

File generators take names that suit the file:

```bash
php anvil make:view posts/index           # resources/views/posts/index.blade.php
php anvil make:view posts.index           # the same
php anvil make:migration CreatePostsTable # database/migrations/create_posts_table.php
php anvil make:route admin                # routes/admin.php
```

## Existing files and --force

A generator never overwrites a file silently:

```
 [ERROR] Class [src/Sync.php] already exists!
```

Pass `-f` / `--force` to overwrite it. `make:model` has no `-f` shortcut, because there `-f` means
`--factory`, and it passes `--force` on to the files it creates with `-a`, `-m`, `-f`, `-s`, `-c`,
`-r` and `-p`.

[Registration](service-provider.md) is never duplicated, so `--force` regenerates the file
without touching the provider again.

## Validation

**Class names.** A class name must be a valid PHP class name. It may be nested with `/` or `\`,
and may end in `.php`:

```
 [ERROR] The name must be a valid class name, optionally nested with "/" (e.g. "Admin/UserService").
```

**Reserved words.** Names that PHP reserves are refused, compared without regard to case:

```
 [ERROR] The name [Class] is reserved by PHP.
```

The list covers keywords such as `class`, `list`, `enum` and `readonly`, the magic constants
such as `__CLASS__`, and the type names PHP reserves as class names, such as `int`, `string` and
`mixed`.

**File names.** File generators accept letters, numbers, `_`, `-`, `.` and `/`, but not `..`, so
a file can never be written outside the package:

```
 [ERROR] The name may only contain letters, numbers, "_", "-", "." and "/".
```

## Missing arguments

When you leave out a required argument in an interactive terminal, the generator asks for it:

```
$ php anvil make:model

 The name of the model:
 > Post
```

With `-n` / `--no-interaction` the command fails instead:

```
  Not enough arguments (missing: "name").
```

## Output and exit codes

Every generator prints what it created, then what it registered:

```
  INFO Console command [src/Commands/SyncPostsCommand.php] created successfully.
  DONE Registered ->hasCommands([SyncPostsCommand::class]) in src/BlogServiceProvider.php.
```

Some print a hint on how to use what they created:

```
  Use it as <x-blog::alert />
  Render it with view('blog::posts.index')
  Use it with __('blog::messages.key')
  Attach it with #[ObservedBy(PostObserver::class)] on the Post model.
```

| Exit code | When |
|---|---|
| `0` | The file was created. A `WARN` about registration does not change the exit code. |
| `1` | Invalid input, the file already exists, or the package is not set up (no `composer.json`, no PSR-4 entry for `src/`) |

You can rely on the exit code in scripts:

```bash
php anvil make:model Post -mf && php anvil make:controller PostController --model=Post
```
