<?php

/*
 * A complete package built with every generator, in both modes. The service providers
 * are compared to the snapshots in tests/Fixtures, and every generated file must be
 * valid PHP and pass Laravel Pint.
 */

dataset('modes', ['toolkit' => true, 'plain' => false]);

it('generates every file where Laravel and the toolkit expect it', function (bool $toolkit) {
    $dir = initPackage(toolkit: $toolkit);

    buildFullPackage($dir);

    $migrations = $toolkit
        ? ['add_slug_to_posts_table.php', 'archive_old_posts.php', 'create_posts_table.php']
        : array_map(fn (string $file) => basename($file), glob("$dir/database/migrations/*.php"));

    expect(filesIn($dir))->toBe([
        '.gitattributes',
        '.github/workflows/tests.yml',
        '.gitignore',
        'LICENSE.md',
        'README.md',
        'anvil',
        'composer.json',
        'config/blog-cache.php',
        'config/blog.php',
        'database/factories/PostFactory.php',
        ...array_map(fn (string $file) => "database/migrations/$file", $migrations),
        'database/seeders/PostSeeder.php',
        'lang/cs.json',
        'lang/en/messages.php',
        'phpstan.neon.dist',
        'phpunit.xml',
        'pint.json',
        'resources/views/components/alert.blade.php',
        'resources/views/components/badge.blade.php',
        'resources/views/mail/hello.blade.php',
        'resources/views/mail/post-digest.blade.php',
        'resources/views/posts/index.blade.php',
        'routes/admin.php',
        'routes/api.php',
        'routes/channels.php',
        'routes/web.php',
        'src/Actions/PublishPost.php',
        'src/Blog.php',
        'src/BlogServiceProvider.php',
        'src/Broadcasting/PostChannel.php',
        'src/Casts/Hashed.php',
        'src/Casts/Money.php',
        'src/Commands/Prune.php',
        'src/Commands/SyncPostsCommand.php',
        'src/Concerns/HasSlug.php',
        'src/Contracts/Publishable.php',
        'src/Enums/PostStatus.php',
        'src/Enums/Priority.php',
        'src/Enums/Visibility.php',
        'src/Events/PostPublished.php',
        'src/Exceptions/InvalidSlug.php',
        'src/Exceptions/PostNotFound.php',
        'src/Facades/Blog.php',
        'src/Http/Controllers/Api/PostController.php',
        'src/Http/Controllers/DashboardController.php',
        'src/Http/Controllers/PingController.php',
        'src/Http/Controllers/PostController.php',
        'src/Http/Middleware/EnsureAuthor.php',
        'src/Http/Requests/StorePostRequest.php',
        'src/Http/Resources/PostCollection.php',
        'src/Http/Resources/PostResource.php',
        'src/Jobs/ClearCache.php',
        'src/Jobs/IndexPost.php',
        'src/Listeners/LogLogin.php',
        'src/Listeners/NotifySubscribers.php',
        'src/Mail/PostDigest.php',
        'src/Mail/Welcome.php',
        'src/Models/Post.php',
        'src/Models/Scopes/PublishedScope.php',
        'src/Notifications/PostCommented.php',
        'src/Observers/PostObserver.php',
        'src/Policies/CommentPolicy.php',
        'src/Policies/PostPolicy.php',
        'src/Providers/EventServiceProvider.php',
        'src/Rules/Slug.php',
        'src/View/Components/Alert.php',
        'src/View/Components/Forms/TextInput.php',
        'testbench.yaml',
        'tests/ArchTest.php',
        'tests/Feature/PostApiTest.php',
        'tests/Feature/ServiceProviderTest.php',
        'tests/Pest.php',
        'tests/TestCase.php',
        'tests/Unit/SlugTest.php',
    ]);

    if (! $toolkit) {
        expect($migrations)->each->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_\w+\.php$/');
    }
})->with('modes');

it('produces valid PHP that passes Laravel Pint', function (bool $toolkit) {
    $dir = initPackage(toolkit: $toolkit);

    buildFullPackage($dir);

    foreach (filesIn($dir) as $file) {
        if (str_ends_with($file, '.php') && ! str_ends_with($file, '.blade.php')) {
            expectValidPhp("$dir/$file");
        }
    }

    expectValidPhp("$dir/anvil");
    expectPintClean($dir);

    expect(json_decode((string) file_get_contents("$dir/lang/cs.json"), true))->toBe([]);
})->with('modes');

it('builds the expected service provider', function (bool $toolkit, string $fixture) {
    $dir = initPackage(toolkit: $toolkit);

    buildFullPackage($dir);

    expect(file_get_contents("$dir/src/BlogServiceProvider.php"))
        ->toBe(file_get_contents(__DIR__."/../Fixtures/$fixture"));
})->with([
    'toolkit' => [true, 'ToolkitServiceProvider.php'],
    'plain' => [false, 'PlainServiceProvider.php'],
]);

it('leaves the provider and composer.json untouched when everything is generated again', function (bool $toolkit) {
    $dir = initPackage(toolkit: $toolkit);

    buildFullPackage($dir);
    $provider = file_get_contents("$dir/src/BlogServiceProvider.php");
    $composer = file_get_contents("$dir/composer.json");

    $outputs = buildFullPackage($dir, ['--force' => true]);

    expect(file_get_contents("$dir/src/BlogServiceProvider.php"))->toBe($provider)
        ->and(file_get_contents("$dir/composer.json"))->toBe($composer);

    foreach ($outputs as $command => $output) {
        expect($output)->not->toContain('DONE Registered', $command)
            ->not->toContain('WARN', $command);
    }
})->with('modes');

it('reports what it registered', function (bool $toolkit, array $expected) {
    $dir = initPackage(toolkit: $toolkit);

    $outputs = buildFullPackage($dir);

    foreach ($expected as $command => $message) {
        expect($outputs[$command])->toContain("DONE Registered $message in src/BlogServiceProvider.php.");
    }
})->with([
    'toolkit' => [true, [
        'make:route web' => "->hasRoutes(['web.php'])",
        'make:route api' => "'api.php' in ->hasRoutes()",
        'make:route channels' => "->hasBroadcastChannels(['channels.php'])",
        'make:model Post' => '->hasMigrations()',
        'make:command SyncPostsCommand' => '->hasCommands([SyncPostsCommand::class])',
        'make:command Prune' => 'Prune::class in ->hasCommands()',
        'make:middleware EnsureAuthor' => "->hasMiddlewareAliases(['blog.author' => EnsureAuthor::class])",
        'make:listener NotifySubscribers' => '->hasEvent(PostPublished::class, NotifySubscribers::class)',
        'make:listener LogLogin' => '->hasEvent(Login::class, LogLogin::class)',
        'make:component Alert' => "->hasComponentNamespace('blog', ...)",
        'make:lang messages' => '->hasTranslations()',
    ]],
    'plain' => [false, [
        'make:route web' => 'loadRoutesFrom() in boot()',
        'make:route channels' => 'require routes/channels.php in boot()',
        'make:config blog-cache' => 'mergeConfigFrom() in register() and publishes() in boot()',
        'make:model Post' => 'publishesMigrations() in boot()',
        'make:command SyncPostsCommand' => 'SyncPostsCommand::class in commands()',
        'make:middleware EnsureAuthor' => 'aliasMiddleware() in boot()',
        'make:listener NotifySubscribers' => 'Event::listen() in boot()',
        'make:component Alert' => 'Blade::componentNamespace() in boot()',
        'make:lang messages' => 'loadTranslationsFrom(), loadJsonTranslationsFrom(), publishes() in boot()',
    ]],
]);

it('keeps the composer.json it generated valid and complete', function (bool $toolkit) {
    $dir = initPackage(toolkit: $toolkit);

    buildFullPackage($dir);

    $composer = composerJson($dir);

    expect(array_keys($composer))->toBe([
        'name', 'description', 'type', 'keywords', 'license', 'authors', 'require', 'require-dev',
        'autoload', 'autoload-dev', 'extra', 'scripts', 'config', 'minimum-stability', 'prefer-stable',
    ])
        ->and($composer['extra']['laravel']['providers'])->toBe([
            'Acme\\Blog\\BlogServiceProvider',
            'Acme\\Blog\\Providers\\EventServiceProvider',
        ])
        ->and($composer['autoload']['psr-4'])->toBe([
            'Acme\\Blog\\' => 'src/',
            'Acme\\Blog\\Database\\Factories\\' => 'database/factories/',
        ]);

    exec('composer validate --no-check-publish --no-check-lock '.escapeshellarg("$dir/composer.json").' 2>&1', $output, $code);

    expect($code)->toBe(0, implode("\n", $output));
})->with('modes');
