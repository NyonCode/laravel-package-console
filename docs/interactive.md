---
title: Interactive mode
description: What Anvil asks when it runs in a terminal without everything it needs, how to keep it from asking, and how to have the questions in your language.
---

# Interactive mode

- [When Anvil asks](#when-anvil-asks)
- [A missing name](#a-missing-name)
- [Follow-up questions](#follow-up-questions)
- [package:init](#packageinit)
- [package:doctor --fix](#packagedoctor---fix)
- [Without a terminal](#without-a-terminal)
- [Language](#language)

## When Anvil asks

Every command runs from its arguments and options alone, and that is how scripts and CI should
call it. In a terminal, a command that is missing something asks for it instead of failing, with
[Laravel Prompts](https://laravel.com/docs/prompts): the same boxes `php artisan` draws.

Anvil asks in three places:

- a generator that was not given its **name**, and then about the options that go with it;
- **`package:init`**, for everything it was not given as an option;
- **`package:doctor --fix`**, for which findings to fix.

It never asks when you pass `-n` / `--no-interaction`, when one command calls another
(`make:model -a` calling `make:factory`), or when there is no way to answer.

## A missing name

```
$ php anvil make:model

 ┌ What should the model be named? ─────────────────────────────┐
 │ E.g. Post                                                    │
 └──────────────────────────────────────────────────────────────┘
```

The name is checked as you submit it, with the same [rules](generators.md#validation) the
argument has, so a name that cannot be used never gets as far as a file:

```
 ┌ What should the class be named? ─────────────────────────────┐
 │ 1x                                                           │
 └──────────────────────────────────────────────────────────────┘
  ⚠ The name must be a valid class name, optionally nested with "/" (e.g. "Adm…
```

## Follow-up questions

A generator that had to ask for its name goes on to ask about its options, as Artisan does. It
asks only about options you did not pass, and not at all when you gave the name on the command
line: `php anvil make:model Post` creates the model and nothing else.

```
$ php anvil make:model

 ┌ What should the model be named? ─────────────────────────────┐
 │ Post                                                         │
 └──────────────────────────────────────────────────────────────┘

 ┌ Would you like any of the following? ────────────────────────┐
 │   ◼ Migration                                                │
 │ › ◼ Factory                                                  │
 │   ◻ Seeder                                                   │
 │   ◻ Policy                                                   │
 │   ◻ Controller                                               │
 │   ◻ Resource controller                                      │
 │   ◻ API resource controller                                  │
 └──────────────────────────────────────────────────────────────┘
  Space selects, Enter confirms.

  INFO Model [src/Models/Post.php] created successfully.
  INFO Factory [database/factories/PostFactory.php] created successfully.
  INFO Migration [database/migrations/create_posts_table.php] created successfully.
  DONE Registered ->hasMigrations() in src/BlogServiceProvider.php.
```

| Command | Asks | Same as |
|---|---|---|
| `make:model` | which companions to create | `-m`, `-f`, `-s`, `-p`, `-c`, `-r`, `--api` |
| `make:controller` | the type: empty, resource, API resource or invokable; for a resource one, the model | `-r`, `--api`, `-i`, `--model=` |
| `make:listener` | the event to listen for | `--event=` |
| `make:observer` | the model it applies to | `--model=` |
| `make:policy` | the model it applies to | `--model=` |
| `make:test` | feature or unit; Pest or PHPUnit | `-u`, `--phpunit` |

Questions about a model or an event suggest the classes the package already has in
`src/Models` and `src/Events`. Type to narrow the list, or type any other class:

```
 ┌ What model should this controller be for? ───────────────────┐
 │ Po                                                           │
 ├──────────────────────────────────────────────────────────────┤
 │ › Post                                                       │
 └──────────────────────────────────────────────────────────────┘
  Optional.
```

These questions are optional: Enter on an empty line skips them.

## package:init

[`package:init`](package-init.md#packageinit) asks for whatever it was not given as an option,
shows what it is about to do and writes nothing until you confirm:

```
 ┌ Which service provider should the package have? ─────────────┐
 │ nyoncode/laravel-package-toolkit, with fluent ->hasX() calls │
 └──────────────────────────────────────────────────────────────┘

 ┌ Which Laravel versions should the package support? ──────────┐
 │ Laravel 12                                                   │
 │ Laravel 13                                                   │
 └──────────────────────────────────────────────────────────────┘

  Package           acme/laravel-blog
  Description       A Laravel package.
  Namespace         Acme\Blog
  Author            Jane Doe <jane@example.com>
  Service provider  nyoncode/laravel-package-toolkit
  Laravel           12, 13

 ┌ Create the package with these settings? ─────────────────────┐
 │ ● Yes / ○ No                                                 │
 └──────────────────────────────────────────────────────────────┘
```

`Ctrl+U` goes back to the previous question. Answering No prints `Nothing was written.` and
exits with 1.

On a package that already has a `composer.json`, the questions are prefilled with what is in it,
and the summary shows what your answers change (`13 → 12, 13`). That makes `package:init` the
way to edit the name, description, author and Laravel versions of a package. See
[Editing the package's settings](package-init.md#editing-the-packages-settings).

## package:doctor --fix

In a terminal, [`package:doctor --fix`](doctor.md#fixing-what-it-finds) lists what it can fix,
all of it selected, and fixes what you leave selected:

```
 ┌ Which of these should be fixed? ───────────────────────────────────────────┐
 │   ◼ lang/cs/messages.php: added 2 missing keys with the text from en, t…   │
 │ › ◻ routes/web.php: registered loadRoutesFrom() in boot()                  │
 └────────────────────────────────────────────────────────────────────────────┘
  Space selects, Enter confirms.

  FIXED lang/cs/messages.php: added 2 missing keys with the text from en, to be translated.
  ERROR routes/web.php is not registered in the service provider. Missing: loadRoutesFrom() in boot().

  1 fixed, 1 error. Run package:doctor --fix to fix 1 of them.
```

With `--no-interaction` or `--json` it fixes everything without asking.

## Without a terminal

| Where it runs | What happens |
|---|---|
| `-n` / `--no-interaction` | Nothing is asked. A missing name is an error (`Not enough arguments (missing: "name")`), `package:init` uses its defaults, `package:doctor --fix` fixes everything. |
| Windows without WSL, or input that is piped in | The same questions, as plain lines of text. |
| CI | Pass `-n`. Most CI systems have no terminal, so nothing would be asked anyway, but the flag makes that certain. |

The plain questions take the same answers. A choice is answered with the word in brackets, and
several with a comma between them:

```
$ printf 'Comment\nmigration,policy\n' | php anvil make:model

 What should the model be named?:
 >
 Would you like any of the following? (Separate several with a comma.) [None]:
  [none      ] None
  [migration ] Migration
  [factory   ] Factory
  [seed      ] Seeder
  [policy    ] Policy
  [controller] Controller
  [resource  ] Resource controller
  [api       ] API resource controller
 >
  INFO Model [src/Models/Comment.php] created successfully.
  INFO Migration [database/migrations/create_comments_table.php] created successfully.
  INFO Policy [src/Policies/CommentPolicy.php] created successfully.
```

## Language

The questions can be asked in another language. English and Czech (`cs`) ship with Anvil.

```bash
ANVIL_LANG=cs php anvil make:model
```

```
 ┌ Jak se má model jmenovat? ───────────────────────────────────┐
 │ Post                                                         │
 └──────────────────────────────────────────────────────────────┘

 ┌ Chcete vytvořit i něco z následujícího? ─────────────────────┐
 │ › ◼ Migrace                                                  │
 │   ◻ Factory                                                  │
 │   ◻ Seeder                                                   │
 │   ◻ Policy                                                   │
 │   ◻ Controller                                               │
 │   ◻ Resource controller                                      │
 │   ◻ API resource controller                                  │
 └──────────────────────────────────────────────────────────────┘
  Mezerník vybírá, Enter potvrzuje.

  INFO Model [src/Models/Post.php] created successfully.
  INFO Migration [database/migrations/create_posts_table.php] created successfully.
  DONE Registered ->hasMigrations() in src/BlogServiceProvider.php.
```

Anvil takes the language from the first of these that is set:

1. `ANVIL_LANG`, e.g. `cs`;
2. the system locale: `LC_ALL`, `LC_MESSAGES`, `LANG`, e.g. `cs_CZ.UTF-8`.

A language Anvil has no translation for falls back to English. To keep English on a machine
with another locale, set `ANVIL_LANG=en`, for example in your shell profile.

**Only the questions are translated**: the labels, hints, choices and the validation messages
of a prompt. Everything a command prints (`INFO`, `DONE`, `WARN`, errors, `--help`,
`package:doctor` findings and `--json`) stays in English, so the output is the same on every
machine and safe to match in scripts. The generated code is never translated.

To add a language, copy `lang/cs.php` to `lang/{language}.php` and translate the values. The
keys are the English questions as they are written in the code. A line you leave out is shown in
English. See [Contributing](contributing.md).
