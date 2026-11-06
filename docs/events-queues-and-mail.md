---
title: Events, queues & mail
description: make:event, make:listener, make:job, make:notification, make:mail and make:channel for Laravel packages.
---

# Events, queues & mail

- [make:event](#makeevent)
- [make:listener](#makelistener)
- [make:job](#makejob)
- [make:notification](#makenotification)
- [make:mail](#makemail)
- [make:channel](#makechannel)

## make:event

```bash
php anvil make:event PostPublished
```

Creates `src/Events/PostPublished.php` with the `Dispatchable` and `SerializesModels` traits.
Options: `-f`/`--force`.

## make:listener

```bash
php anvil make:listener NotifySubscribers --event=PostPublished
php anvil make:listener NotifySubscribers --event=PostPublished --queued
php anvil make:listener LogLogin --event='Illuminate\Auth\Events\Login'
```

| Option | |
|---|---|
| `-e`, `--event=` | The event to listen for. The listener is registered for it. |
| `--queued` | Implement `ShouldQueue` and use `InteractsWithQueue` |
| `-f`, `--force` | Overwrite an existing file |

The event is resolved like this:

| `--event` | Class |
|---|---|
| `PostPublished` | `Acme\Blog\Events\PostPublished` |
| `Admin/UserBanned` | `Acme\Blog\Events\Admin\UserBanned` |
| `Acme\Blog\Other\Saved` | used as is (in the root namespace) |
| `Illuminate\Auth\Events\Login` | used as is (starts with `Illuminate\`) |
| `'\Laravel\Cashier\Events\WebhookHandled'` | used as is (leading backslash) |

```php title="src/Listeners/NotifySubscribers.php (--queued)"
<?php

namespace Acme\Blog\Listeners;

use Acme\Blog\Events\PostPublished;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class NotifySubscribers implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(PostPublished $event): void
    {
        //
    }
}
```

Without `--event`, `handle()` takes `object $event` and nothing is registered.

**Registration**

| Toolkit | Spatie | Plain |
|---|---|---|
| `->hasEvent(PostPublished::class, NotifySubscribers::class)` | the plain code, in `packageBooted()` | `Event::listen(PostPublished::class, NotifySubscribers::class);` |

Each pair of event and listener gets its own call. With the toolkit, a pair that is already listed
in a `->hasEvents([...])` call is not added again.

## make:job

```bash
php anvil make:job IndexPost          # queued
php anvil make:job ClearCache --sync  # synchronous
```

| Option | |
|---|---|
| `--sync` | A synchronous job (only `Dispatchable`) instead of a queued one (`ShouldQueue` and `Queueable`) |
| `-f`, `--force` | Overwrite an existing file |

Jobs are created in `src/Jobs/`.

## make:notification

```bash
php anvil make:notification PostCommented
```

Creates `src/Notifications/PostCommented.php` with `via()`, `toMail()` and `toArray()`.
Options: `-f`/`--force`.

## make:mail

```bash
php anvil make:mail OrderShipped                       # view: 'view.name' placeholder
php anvil make:mail OrderShipped --markdown            # + resources/views/mail/order-shipped.blade.php
php anvil make:mail OrderShipped -m emails.shipped     # + resources/views/emails/shipped.blade.php
php anvil make:mail OrderShipped --view                # Blade template instead of Markdown
php anvil make:mail OrderShipped --view=emails.shipped
```

| Option | |
|---|---|
| `-m`, `--markdown[=view]` | Create a Markdown template. The view defaults to `mail.{class in kebab case}`. |
| `--view[=view]` | Create a Blade template, with the same default |
| `-f`, `--force` | Overwrite existing files |

The subject is derived from the class name: `OrderShipped` gets `Order Shipped`. The content
points at the package's namespaced view:

```php title="src/Mail/OrderShipped.php (--markdown)"
public function envelope(): Envelope
{
    return new Envelope(
        subject: 'Order Shipped',
    );
}

public function content(): Content
{
    return new Content(
        markdown: 'blog::mail.order-shipped',
    );
}
```

```blade title="resources/views/mail/order-shipped.blade.php"
<x-mail::message>
# Introduction

The body of your message.

<x-mail::button :url="''">
Button Text
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
```

The template is created only if it does not exist yet, unless you pass `--force`. Creating it
[registers the views](views-and-translations.md#makeview). Without `--markdown` or `--view`, no
template is created, and `content()` points at the placeholder `view.name`.

## make:channel

```bash
php anvil make:channel PostChannel
```

Creates `src/Broadcasting/PostChannel.php`, a broadcast channel class whose
`join(Authenticatable $user): array|bool` method authorises the user. It type-hints the
`Authenticatable` contract, not `App\Models\User`, because a package does not know the
application's user model. Options: `-f`/`--force`.

Register the channel class in your `routes/channels.php`, which you can create with
[`make:route channels`](config-and-routes.md#makeroute):

```php
Broadcast::channel('blog.posts.{post}', PostChannel::class);
```
