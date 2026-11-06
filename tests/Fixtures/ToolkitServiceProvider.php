<?php

namespace Acme\Blog;

use Acme\Blog\Commands\Prune;
use Acme\Blog\Commands\SyncPostsCommand;
use Acme\Blog\Events\PostPublished;
use Acme\Blog\Http\Middleware\EnsureAuthor;
use Acme\Blog\Listeners\LogLogin;
use Acme\Blog\Listeners\NotifySubscribers;
use Illuminate\Auth\Events\Login;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;

class BlogServiceProvider extends PackageServiceProvider
{
    /**
     * Describe the package's resources.
     */
    public function configure(Packager $packager): void
    {
        $packager
            ->name('Blog')
            ->hasShortName('blog')
            ->hasConfig()
            ->hasRoutes(['web.php', 'api.php', 'admin.php'])
            ->hasBroadcastChannels(['channels.php'])
            ->hasMigrations()
            ->hasSeeders()
            ->hasCommands([SyncPostsCommand::class, Prune::class])
            ->hasMiddlewareAliases(['blog.author' => EnsureAuthor::class])
            ->hasEvent(PostPublished::class, NotifySubscribers::class)
            ->hasEvent(Login::class, LogLogin::class)
            ->hasViews()
            ->hasComponentNamespace('blog', 'Acme\Blog\View\Components')
            ->hasTranslations();
    }
}
