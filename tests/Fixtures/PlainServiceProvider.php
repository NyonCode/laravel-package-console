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
use Illuminate\Support\ServiceProvider;

class BlogServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/blog.php', 'blog');
        $this->mergeConfigFrom(__DIR__.'/../config/blog-cache.php', 'blog-cache');
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/blog.php' => config_path('blog.php'),
        ], 'blog-config');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        require __DIR__.'/../routes/channels.php';
        $this->loadRoutesFrom(__DIR__.'/../routes/admin.php');
        $this->publishes([
            __DIR__.'/../config/blog-cache.php' => config_path('blog-cache.php'),
        ], 'blog-config');
        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'blog-migrations');
        $this->publishes([
            __DIR__.'/../database/seeders' => database_path('seeders'),
        ], 'blog-seeders');
        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncPostsCommand::class,
                Prune::class,
            ]);
        }
        $this->app['router']->aliasMiddleware('blog.author', EnsureAuthor::class);
        Event::listen(PostPublished::class, NotifySubscribers::class);
        Event::listen(Login::class, LogLogin::class);
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'blog');
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/blog'),
        ], 'blog-views');
        Blade::componentNamespace('Acme\Blog\View\Components', 'blog');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'blog');
        $this->loadJsonTranslationsFrom(__DIR__.'/../lang');
        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/blog'),
        ], 'blog-translations');
    }
}
