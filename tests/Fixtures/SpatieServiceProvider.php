<?php

namespace Acme\Blog;

use Acme\Blog\Commands\Prune;
use Acme\Blog\Commands\SyncPostsCommand;
use Acme\Blog\Events\PostPublished;
use Acme\Blog\Http\Middleware\EnsureAuthor;
use Acme\Blog\Listeners\LogLogin;
use Acme\Blog\Listeners\NotifySubscribers;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class BlogServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-blog')
            ->hasConfigFile(['blog', 'blog-cache'])
            ->hasRoute('web')
            ->hasRoute('api')
            ->hasRoute('admin')
            ->hasMigration('create_posts_table')
            ->hasMigration('add_slug_to_posts_table')
            ->hasMigration('archive_old_posts')
            ->hasCommand(SyncPostsCommand::class)
            ->hasCommand(Prune::class)
            ->hasViews()
            ->hasTranslations();
    }

    public function packageBooted(): void
    {
        require __DIR__.'/../routes/channels.php';
        $this->publishes([
            __DIR__.'/../database/seeders' => database_path('seeders'),
        ], 'blog-seeders');
        $this->app['router']->aliasMiddleware('blog.author', EnsureAuthor::class);
        Event::listen(PostPublished::class, NotifySubscribers::class);
        Event::listen(Login::class, LogLogin::class);
        Blade::componentNamespace('Acme\Blog\View\Components', 'blog');
    }
}
