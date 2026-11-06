<?php

use NyonCode\LaravelPackageConsole\Kernel;
use Symfony\Component\Console\Tester\ApplicationTester;

/*
 * The options of every generator, one behaviour per test.
 */

describe('make:model', function () {
    it('creates only the model by default', function () {
        $dir = initPackage();

        generate($dir, 'make:model', ['name' => 'Post']);

        expect(contents($dir, 'src/Models/Post.php'))
            ->toContain('namespace Acme\Blog\Models;')
            ->toContain('class Post extends Model')
            ->not->toContain('HasFactory')
            ->and(glob("$dir/database/migrations/*"))->toBe([])
            ->and("$dir/database/factories/PostFactory.php")->not->toBeFile();
    });

    it('creates the related files for each option', function (array $options, array $files, array $missing) {
        $dir = initPackage();

        generate($dir, 'make:model', ['name' => 'Post', ...$options]);

        foreach ($files as $file) {
            expect("$dir/$file")->toBeFile();
        }

        foreach ($missing as $file) {
            expect("$dir/$file")->not->toBeFile();
        }
    })->with([
        '-m' => [['--migration' => true], ['database/migrations/create_posts_table.php'], ['database/factories/PostFactory.php']],
        '-f' => [['--factory' => true], ['database/factories/PostFactory.php'], ['database/migrations/create_posts_table.php']],
        '-s' => [['--seed' => true], ['database/seeders/PostSeeder.php'], ['src/Http/Controllers/PostController.php']],
        '-c' => [['--controller' => true], ['src/Http/Controllers/PostController.php'], ['src/Policies/PostPolicy.php']],
        '-p' => [['--policy' => true], ['src/Policies/PostPolicy.php'], ['src/Http/Controllers/PostController.php']],
        '-a' => [['--all' => true], [
            'database/migrations/create_posts_table.php',
            'database/factories/PostFactory.php',
            'database/seeders/PostSeeder.php',
            'src/Http/Controllers/PostController.php',
            'src/Policies/PostPolicy.php',
        ], []],
    ]);

    it('links the model to its factory with newFactory()', function () {
        $dir = initPackage();

        generate($dir, 'make:model', ['name' => 'Post', '--factory' => true]);

        expect(contents($dir, 'src/Models/Post.php'))
            ->toContain('use Acme\Blog\Database\Factories\PostFactory;')
            ->toContain('/** @use HasFactory<PostFactory> */')
            ->toContain('protected static function newFactory(): PostFactory')
            ->and(contents($dir, 'database/factories/PostFactory.php'))
            ->toContain('use Acme\Blog\Models\Post;')
            ->toContain('protected $model = Post::class;');
    });

    it('makes a controller of the right kind', function (array $options, string $snippet, ?string $absent) {
        $dir = initPackage();

        generate($dir, 'make:model', ['name' => 'Post', ...$options]);

        $controller = contents($dir, 'src/Http/Controllers/PostController.php');

        expect($controller)->toContain($snippet);

        if ($absent !== null) {
            expect($controller)->not->toContain($absent);
        }
    })->with([
        'plain (-c)' => [['--controller' => true], "class PostController\n{\n    //\n}", 'function index'],
        'resource (-r)' => [['--resource' => true], 'public function edit(Post $post)', null],
        'api (--api)' => [['--api' => true], 'public function show(Post $post)', 'public function create()'],
        '-a uses a resource controller' => [['--all' => true], 'public function create()', null],
    ]);

    it('pluralizes the table name in English', function (string $model, string $table) {
        $dir = initPackage();

        generate($dir, 'make:model', ['name' => $model, '--migration' => true]);

        expect(contents($dir, "database/migrations/create_{$table}_table.php"))
            ->toContain("Schema::create('$table'");
    })->with([
        ['Post', 'posts'],
        ['BlogPost', 'blog_posts'],
        ['Category', 'categories'],
        ['Person', 'people'],
        ['Tax', 'taxes'],
        ['Child', 'children'],
        ['Fish', 'fish'],
        ['Status', 'statuses'],
    ]);

    it('supports nested model names', function () {
        $dir = initPackage();

        generate($dir, 'make:model', ['name' => 'Admin/User']);

        expect(contents($dir, 'src/Models/Admin/User.php'))->toContain('namespace Acme\Blog\Models\Admin;');
    });

    it('passes --force to the related files', function () {
        $dir = initPackage();

        generate($dir, 'make:model', ['name' => 'Post', '--all' => true]);

        [$code, $output] = anvil($dir, 'make:model', ['name' => 'Post', '--all' => true]);
        expect($code)->toBe(1)->and($output)->toContain('Model [src/Models/Post.php] already exists!');

        $output = generate($dir, 'make:model', ['name' => 'Post', '--all' => true, '--force' => true]);
        expect($output)->not->toContain('already exists');
    });

    it('uses -f for --factory, so --force has no shortcut', function () {
        $dir = initPackage();

        [$code] = anvil($dir, 'make:model', ['name' => 'Post', '-f' => true]);

        expect($code)->toBe(0)->and("$dir/database/factories/PostFactory.php")->toBeFile();
    });
});

describe('make:migration', function () {
    it('guesses the table and the stub from the name', function (string $name, string $snippet) {
        $dir = initPackage();

        generate($dir, 'make:migration', ['name' => $name]);

        $file = glob("$dir/database/migrations/*.php")[0];

        expect(file_get_contents($file))->toContain($snippet);
    })->with([
        'create_*_table' => ['create_posts_table', "Schema::create('posts'"],
        'create_*' => ['create_posts', "Schema::create('posts'"],
        '*_to_*_table' => ['add_slug_to_posts_table', "Schema::table('posts'"],
        '*_from_*_table' => ['remove_slug_from_posts_table', "Schema::table('posts'"],
        '*_in_*_table' => ['rename_title_in_posts_table', "Schema::table('posts'"],
        '*_to_*' => ['add_slug_to_posts', "Schema::table('posts'"],
        'anything else' => ['archive_old_posts', "public function up(): void\n    {\n        //\n    }"],
    ]);

    it('uses --create and --table over the guess', function () {
        $dir = initPackage();

        generate($dir, 'make:migration', ['name' => 'blog_tables', '--create' => 'blog_posts']);
        generate($dir, 'make:migration', ['name' => 'blog_changes', '--table' => 'blog_posts']);

        expect(contents($dir, 'database/migrations/blog_tables.php'))->toContain("Schema::create('blog_posts'")
            ->and(contents($dir, 'database/migrations/blog_changes.php'))->toContain("Schema::table('blog_posts'");
    });

    it('converts the name to snake case', function () {
        $dir = initPackage();

        generate($dir, 'make:migration', ['name' => 'CreatePostsTable.php']);

        expect("$dir/database/migrations/create_posts_table.php")->toBeFile();
    });

    it('is timeless with the toolkit unless --timestamp is given', function () {
        $dir = initPackage();

        generate($dir, 'make:migration', ['name' => 'create_posts_table']);
        generate($dir, 'make:migration', ['name' => 'create_tags_table', '--timestamp' => true]);

        expect("$dir/database/migrations/create_posts_table.php")->toBeFile()
            ->and(glob("$dir/database/migrations/*_create_tags_table.php"))->toHaveCount(1)
            ->and(basename(glob("$dir/database/migrations/*_create_tags_table.php")[0]))->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_create_tags_table\.php$/');
    });

    it('is timestamped without the toolkit', function () {
        $dir = initPackage(toolkit: false);

        generate($dir, 'make:migration', ['name' => 'create_posts_table']);

        expect(basename(glob("$dir/database/migrations/*.php")[0]))->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_create_posts_table\.php$/');
    });

    it('refuses a second migration with the same name, timestamped or not', function (bool $toolkit) {
        $dir = initPackage(toolkit: $toolkit);

        generate($dir, 'make:migration', ['name' => 'create_posts_table']);

        [$code, $output] = anvil($dir, 'make:migration', ['name' => 'create_posts_table', '--timestamp' => true]);

        expect($code)->toBe(1)
            ->and($output)->toContain('A migration named [create_posts_table] already exists');
    })->with(['toolkit' => true, 'plain' => false]);
});

describe('make:factory', function () {
    it('appends the Factory suffix and guesses the model', function (string $name) {
        $dir = initPackage();

        generate($dir, 'make:factory', ['name' => $name]);

        expect(contents($dir, 'database/factories/PostFactory.php'))
            ->toContain('namespace Acme\Blog\Database\Factories;')
            ->toContain('class PostFactory extends Factory')
            ->toContain('@extends Factory<Post>')
            ->toContain('use Acme\Blog\Models\Post;');
    })->with(['Post', 'PostFactory']);

    it('uses --model', function () {
        $dir = initPackage();

        generate($dir, 'make:factory', ['name' => 'DraftFactory', '--model' => 'Post']);

        expect(contents($dir, 'database/factories/DraftFactory.php'))->toContain('protected $model = Post::class;');
    });

    it('adds the factories namespace to composer.json when it is missing', function () {
        $dir = tempPackage(['name' => 'acme/blog', 'autoload' => ['psr-4' => ['Acme\\Blog\\' => 'src/']]]);

        $output = generate($dir, 'make:factory', ['name' => 'Post']);

        expect(composerJson($dir)['autoload']['psr-4'])->toBe([
            'Acme\\Blog\\' => 'src/',
            'Acme\\Blog\\Database\\Factories\\' => 'database/factories/',
        ])
            ->and($output)->toContain('Run composer dump-autoload')
            ->and("$dir/database/factories/PostFactory.php")->toBeFile();
    });
});

describe('make:seeder', function () {
    it('uses the application namespace Database\Seeders', function () {
        $dir = initPackage();

        generate($dir, 'make:seeder', ['name' => 'BlogPostSeeder']);

        expect(contents($dir, 'database/seeders/BlogPostSeeder.php'))
            ->toContain('namespace Database\Seeders;')
            ->toContain('class BlogPostSeeder extends Seeder');
    });

    it('has the make:seed alias', function () {
        $dir = initPackage();

        generate($dir, 'make:seed', ['name' => 'BlogSeeder']);

        expect("$dir/database/seeders/BlogSeeder.php")->toBeFile();
    });

    it('publishes the seeders', function (bool $toolkit, string $snippet) {
        $dir = initPackage(toolkit: $toolkit);

        generate($dir, 'make:seeder', ['name' => 'BlogSeeder']);

        expect(provider($dir))->toContain($snippet);
    })->with([
        'toolkit' => [true, '->hasSeeders()'],
        'plain' => [false, "__DIR__.'/../database/seeders' => database_path('seeders'),\n        ], 'blog-seeders');"],
    ]);
});

describe('make:command', function () {
    it('derives the signature from the package and class name', function (string $name, string $path, string $signature) {
        $dir = initPackage();

        generate($dir, 'make:command', ['name' => $name]);

        expect(contents($dir, $path))->toContain("protected \$signature = '$signature';");
    })->with([
        ['SyncPostsCommand', 'src/Commands/SyncPostsCommand.php', 'blog:sync-posts'],
        ['Prune', 'src/Commands/Prune.php', 'blog:prune'],
        ['Admin/ResetCommand', 'src/Commands/Admin/ResetCommand.php', 'blog:reset'],
    ]);

    it('uses --command as the signature', function () {
        $dir = initPackage();

        generate($dir, 'make:command', ['name' => 'Install', '--command' => 'blog:install {--force}']);

        expect(contents($dir, 'src/Commands/Install.php'))->toContain("protected \$signature = 'blog:install {--force}';");
    });

    it('has the make:console alias', function () {
        $dir = initPackage();

        generate($dir, 'make:console', ['name' => 'Sync']);

        expect("$dir/src/Commands/Sync.php")->toBeFile();
    });

    it('registers nested commands with their import', function (bool $toolkit, string $snippet) {
        $dir = initPackage(toolkit: $toolkit);

        generate($dir, 'make:command', ['name' => 'Admin/ResetCommand']);

        expect(provider($dir))
            ->toContain('use Acme\Blog\Commands\Admin\ResetCommand;')
            ->toContain($snippet);
    })->with([
        'toolkit' => [true, '->hasCommands([ResetCommand::class])'],
        'plain' => [false, "\$this->commands([\n                ResetCommand::class,\n            ]);"],
    ]);
});

describe('make:controller', function () {
    it('creates the right kind of controller', function (array $options, array $contains, array $absent) {
        $dir = initPackage();

        generate($dir, 'make:controller', ['name' => 'PostController', ...$options]);

        $controller = contents($dir, 'src/Http/Controllers/PostController.php');

        foreach ($contains as $snippet) {
            expect($controller)->toContain($snippet);
        }

        foreach ($absent as $snippet) {
            expect($controller)->not->toContain($snippet);
        }
    })->with([
        'plain' => [[], ["class PostController\n{\n    //\n}"], ['use Illuminate\Http\Request;', 'extends']],
        '--resource' => [['--resource' => true], ['public function create()', 'public function show(string $id)'], []],
        '--api' => [['--api' => true], ['public function store(Request $request)', 'public function destroy(string $id)'], ['public function create()', 'public function edit(']],
        '--invokable' => [['--invokable' => true], ['public function __invoke(Request $request)'], ['public function index()']],
        '--model' => [['--model' => 'Post'], ['use Acme\Blog\Models\Post;', 'public function update(Request $request, Post $post)', 'public function create()'], []],
        '--api --model' => [['--api' => true, '--model' => 'Post'], ['public function show(Post $post)'], ['public function create()']],
        '--invokable wins over --model' => [['--invokable' => true, '--model' => 'Post'], ['public function __invoke(Request $request)'], ['use Acme\Blog\Models\Post;']],
        '--api wins over --resource' => [['--api' => true, '--resource' => true], ['public function store(Request $request)'], ['public function create()']],
    ]);
});

describe('make:middleware', function () {
    it('does not touch the provider without --alias', function (bool $toolkit) {
        $dir = initPackage(toolkit: $toolkit);
        $before = provider($dir);

        generate($dir, 'make:middleware', ['name' => 'EnsureAuthor']);

        expect(provider($dir))->toBe($before)
            ->and(contents($dir, 'src/Http/Middleware/EnsureAuthor.php'))
            ->toContain('public function handle(Request $request, Closure $next): Response');
    })->with(['toolkit' => true, 'plain' => false]);

    it('registers the alias with -a', function (bool $toolkit, string $snippet) {
        $dir = initPackage(toolkit: $toolkit);

        generate($dir, 'make:middleware', ['name' => 'EnsureAuthor', '-a' => 'blog.author']);
        generate($dir, 'make:middleware', ['name' => 'EnsureEditor', '--alias' => 'blog.editor']);

        expect(provider($dir))->toContain($snippet);
    })->with([
        'toolkit' => [true, "->hasMiddlewareAliases(['blog.author' => EnsureAuthor::class, 'blog.editor' => EnsureEditor::class])"],
        'plain' => [false, "\$this->app['router']->aliasMiddleware('blog.editor', EnsureEditor::class);"],
    ]);
});

describe('make:listener', function () {
    it('listens for any object without --event and is not registered', function (bool $toolkit) {
        $dir = initPackage(toolkit: $toolkit);
        $before = provider($dir);

        generate($dir, 'make:listener', ['name' => 'Audit']);

        expect(contents($dir, 'src/Listeners/Audit.php'))->toContain('public function handle(object $event): void')
            ->and(provider($dir))->toBe($before);
    })->with(['toolkit' => true, 'plain' => false]);

    it('resolves --event in the package, as a FQCN, or from Illuminate', function (string $event, string $import, string $type) {
        $dir = initPackage();

        generate($dir, 'make:listener', ['name' => 'Handle', '-e' => $event]);

        expect(contents($dir, 'src/Listeners/Handle.php'))
            ->toContain("use $import;")
            ->toContain("public function handle($type \$event): void");
    })->with([
        'short name' => ['PostPublished', 'Acme\Blog\Events\PostPublished', 'PostPublished'],
        'nested name' => ['Admin/UserBanned', 'Acme\Blog\Events\Admin\UserBanned', 'UserBanned'],
        'package FQCN' => ['Acme\Blog\Other\Saved', 'Acme\Blog\Other\Saved', 'Saved'],
        'Illuminate' => ['Illuminate\Auth\Events\Login', 'Illuminate\Auth\Events\Login', 'Login'],
        'leading backslash' => ['\Laravel\Cashier\Events\WebhookHandled', 'Laravel\Cashier\Events\WebhookHandled', 'WebhookHandled'],
    ]);

    it('creates a queued listener with --queued', function () {
        $dir = initPackage();

        generate($dir, 'make:listener', ['name' => 'Notify', '--queued' => true]);

        expect(contents($dir, 'src/Listeners/Notify.php'))
            ->toContain('class Notify implements ShouldQueue')
            ->toContain('use InteractsWithQueue;');
    });
});

describe('make:component', function () {
    it('creates the class and its view', function () {
        $dir = initPackage();

        $output = generate($dir, 'make:component', ['name' => 'Forms/TextInput']);

        expect(contents($dir, 'src/View/Components/Forms/TextInput.php'))
            ->toContain('namespace Acme\Blog\View\Components\Forms;')
            ->toContain("return view('blog::components.forms.text-input');")
            ->and(contents($dir, 'resources/views/components/forms/text-input.blade.php'))->toContain('{{-- components.forms.text-input --}}')
            ->and($output)->toContain('Use it as <x-blog::forms.text-input />');
    });

    it('renders an inline view with --inline', function () {
        $dir = initPackage();

        generate($dir, 'make:component', ['name' => 'Alert', '--inline' => true]);

        expect(contents($dir, 'src/View/Components/Alert.php'))->toContain("return <<<'blade'")
            ->and("$dir/resources/views/components/alert.blade.php")->not->toBeFile();
    });

    it('creates an anonymous component with --view', function () {
        $dir = initPackage();

        $output = generate($dir, 'make:component', ['name' => 'forms.input', '--view' => true]);

        expect("$dir/resources/views/components/forms/input.blade.php")->toBeFile()
            ->and("$dir/src/View/Components")->not->toBeDirectory()
            ->and($output)->toContain('Use it as <x-blog::forms.input />');

        [$code, $output] = anvil($dir, 'make:component', ['name' => 'forms.input', '--view' => true]);

        expect($code)->toBe(1)->and($output)->toContain('already exists');
    });

    it('registers views and the component namespace', function (bool $toolkit, array $snippets) {
        $dir = initPackage(toolkit: $toolkit);

        generate($dir, 'make:component', ['name' => 'Alert']);

        foreach ($snippets as $snippet) {
            expect(provider($dir))->toContain($snippet);
        }
    })->with([
        'toolkit' => [true, ['->hasViews()', "->hasComponentNamespace('blog', 'Acme\Blog\View\Components')"]],
        'plain' => [false, [
            "\$this->loadViewsFrom(__DIR__.'/../resources/views', 'blog');",
            "Blade::componentNamespace('Acme\Blog\View\Components', 'blog');",
            'use Illuminate\Support\Facades\Blade;',
        ]],
    ]);

    it('registers only the views for an anonymous component', function () {
        $dir = initPackage();

        generate($dir, 'make:component', ['name' => 'badge', '--view' => true]);

        expect(provider($dir))->toContain('->hasViews()')->not->toContain('hasComponentNamespace');
    });
});

describe('make:view', function () {
    it('normalises the view name', function (string $name) {
        $dir = initPackage();

        $output = generate($dir, 'make:view', ['name' => $name]);

        expect(contents($dir, 'resources/views/posts/index.blade.php'))->toBe("<div>\n    {{-- posts.index --}}\n</div>\n")
            ->and($output)->toContain("Render it with view('blog::posts.index')");
    })->with(['posts.index', 'posts/index', 'posts/index.blade.php']);
});

describe('make:lang', function () {
    it('creates lang/en/messages.php by default', function () {
        $dir = initPackage();

        $output = generate($dir, 'make:lang');

        expect("$dir/lang/en/messages.php")->toBeFile()
            ->and($output)->toContain("Use it with __('blog::messages.key')");
    });

    it('uses --locale and the name', function () {
        $dir = initPackage();

        generate($dir, 'make:lang', ['name' => 'validation', '-l' => 'cs']);

        expect("$dir/lang/cs/validation.php")->toBeFile();
    });

    it('rejects a locale that is not a plain name', function () {
        [$code, $output] = anvil(initPackage(), 'make:lang', ['--locale' => '../x']);

        expect($code)->toBe(1)->and($output)->toContain('The locale may only contain');
    });

    it('creates a JSON file with --json', function () {
        $dir = initPackage();

        generate($dir, 'make:lang', ['--json' => true, '--locale' => 'de']);

        expect(contents($dir, 'lang/de.json'))->toBe("{\n}\n");
    });
});

describe('make:route', function () {
    it('creates routes/web.php by default', function () {
        $dir = initPackage();

        generate($dir, 'make:route');

        expect(contents($dir, 'routes/web.php'))
            ->toContain("Route::middleware('web')")
            ->toContain("->prefix('blog')")
            ->toContain("->name('blog.')");
    });

    it('prefixes api routes', function () {
        $dir = initPackage();

        generate($dir, 'make:route', ['name' => 'api']);

        expect(contents($dir, 'routes/api.php'))
            ->toContain("Route::middleware('api')")
            ->toContain("->prefix('api/blog')")
            ->toContain("->name('blog.api.')");
    });

    it('groups any other route file under its name', function () {
        $dir = initPackage();

        generate($dir, 'make:route', ['name' => 'admin.php']);

        expect(contents($dir, 'routes/admin.php'))
            ->toContain("Route::prefix('blog/admin')")
            ->toContain("->name('blog.admin.')");
    });

    it('registers channels as broadcast channels', function (bool $toolkit, string $snippet) {
        $dir = initPackage(toolkit: $toolkit);

        generate($dir, 'make:route', ['name' => 'channels']);

        expect(contents($dir, 'routes/channels.php'))->toContain('use Illuminate\Support\Facades\Broadcast;')
            ->and(provider($dir))->toContain($snippet)->not->toContain('hasRoutes');
    })->with([
        'toolkit' => [true, "->hasBroadcastChannels(['channels.php'])"],
        'plain' => [false, "require __DIR__.'/../routes/channels.php';"],
    ]);
});

describe('make:config', function () {
    it('names the file after the package by default', function () {
        $dir = initPackage(options: ['--no-config' => true]);

        generate($dir, 'make:config');

        expect(contents($dir, 'config/blog.php'))->toContain('// Configuration for Blog.');
    });

    it('merges and publishes each config file without the toolkit', function () {
        $dir = initPackage(toolkit: false);

        generate($dir, 'make:config', ['name' => 'blog-cache']);

        expect(provider($dir))
            ->toContain("\$this->mergeConfigFrom(__DIR__.'/../config/blog-cache.php', 'blog-cache');")
            ->toContain("__DIR__.'/../config/blog-cache.php' => config_path('blog-cache.php'),\n        ], 'blog-config');");
    });

    it('relies on the toolkit discovering every config file', function () {
        $dir = initPackage();
        $before = provider($dir);

        $output = generate($dir, 'make:config', ['name' => 'blog-cache']);

        expect(provider($dir))->toBe($before)->toContain('->hasConfig()')
            ->and($output)->not->toContain('Registered');
    });
});

describe('make:test', function () {
    it('creates Pest tests in the feature or unit suite', function (array $input, string $path) {
        $dir = initPackage();

        generate($dir, 'make:test', $input);

        expect(contents($dir, $path))->toContain("it('works', function () {");
    })->with([
        [['name' => 'PostApi'], 'tests/Feature/PostApiTest.php'],
        [['name' => 'PostApiTest'], 'tests/Feature/PostApiTest.php'],
        [['name' => 'support/slug', '-u' => true], 'tests/Unit/Support/SlugTest.php'],
    ]);

    it('creates a PHPUnit class with --phpunit', function () {
        $dir = initPackage();

        generate($dir, 'make:test', ['name' => 'Http/PostApi', '--phpunit' => true]);

        expect(contents($dir, 'tests/Feature/Http/PostApiTest.php'))
            ->toContain('namespace Acme\Blog\Tests\Feature\Http;')
            ->toContain('use Acme\Blog\Tests\TestCase;')
            ->toContain('class PostApiTest extends TestCase');
    });
});

describe('class generators', function () {
    it('creates backed and pure enums', function (array $options, string $declaration) {
        $dir = initPackage();

        generate($dir, 'make:enum', ['name' => 'Status', ...$options]);

        expect(contents($dir, 'src/Enums/Status.php'))->toContain($declaration);
    })->with([
        [[], "enum Status\n{"],
        [['--string' => true], 'enum Status: string'],
        [['-s' => true], 'enum Status: string'],
        [['--int' => true], 'enum Status: int'],
        [['-i' => true], 'enum Status: int'],
    ]);

    it('creates a resource collection with -c or a *Collection name', function (array $input) {
        $dir = initPackage();

        generate($dir, 'make:resource', $input);

        $file = glob("$dir/src/Http/Resources/*.php")[0];

        expect(file_get_contents($file))->toContain('extends ResourceCollection');
    })->with([
        [['name' => 'Posts', '-c' => true]],
        [['name' => 'PostCollection']],
    ]);

    it('resolves the facade accessor', function (array $options, string $accessor, string $see) {
        $dir = initPackage();

        generate($dir, 'make:facade', ['name' => 'Blog', ...$options]);

        expect(contents($dir, 'src/Facades/Blog.php'))
            ->toContain("return $accessor;")
            ->toContain("@see $see");
    })->with([
        'default' => [[], '\Acme\Blog\Blog::class', '\Acme\Blog\Blog'],
        '--accessor' => [['--accessor' => 'blog.manager'], "'blog.manager'", 'blog.manager'],
    ]);

    it('creates plain observers and policies without --model', function (string $command, string $path, string $snippet) {
        $dir = initPackage();

        generate($dir, $command, ['name' => basename($path, '.php')]);

        expect(contents($dir, $path))->toContain($snippet)->not->toContain('use Acme\Blog\Models');
    })->with([
        ['make:observer', 'src/Observers/PostObserver.php', "class PostObserver\n{\n    //\n}"],
        ['make:policy', 'src/Policies/PostPolicy.php', 'public function __construct()'],
    ]);

    it('resolves --model in the package, or as is with a leading backslash', function (string $model, string $import) {
        $dir = initPackage();

        generate($dir, 'make:policy', ['name' => 'UserPolicy', '--model' => $model]);

        expect(contents($dir, 'src/Policies/UserPolicy.php'))
            ->toContain("use $import;")
            ->toContain('public function update(Authenticatable $user, User $model): bool');

        expectValidPhp("$dir/src/Policies/UserPolicy.php");
    })->with([
        ['User', 'Acme\Blog\Models\User'],
        ['Admin/User', 'Acme\Blog\Models\Admin\User'],
        ['Acme\Blog\Auth\User', 'Acme\Blog\Auth\User'],
        ['\Illuminate\Foundation\Auth\User', 'Illuminate\Foundation\Auth\User'],
    ]);

    it('hints at #[ObservedBy] for model observers', function () {
        $dir = initPackage();

        $output = generate($dir, 'make:observer', ['name' => 'PostObserver', '-m' => 'Post']);

        expect($output)->toContain('Attach it with #[ObservedBy(PostObserver::class)] on the Post model.');
    });

    it('creates a mailable with a Markdown or Blade template', function (array $options, string $content, ?string $view) {
        $dir = initPackage();

        generate($dir, 'make:mail', ['name' => 'OrderShipped', ...$options]);

        expect(contents($dir, 'src/Mail/OrderShipped.php'))
            ->toContain("subject: 'Order Shipped',")
            ->toContain($content);

        if ($view === null) {
            expect("$dir/resources/views")->not->toBeDirectory();
        } else {
            expect("$dir/resources/views/$view")->toBeFile();
        }
    })->with([
        'no template' => [[], "view: 'view.name',", null],
        '--markdown' => [['--markdown' => null], "markdown: 'blog::mail.order-shipped',", 'mail/order-shipped.blade.php'],
        '-m=name' => [['-m' => 'emails.shipped'], "markdown: 'blog::emails.shipped',", 'emails/shipped.blade.php'],
        '--view' => [['--view' => null], "view: 'blog::mail.order-shipped',", 'mail/order-shipped.blade.php'],
        '--view=name' => [['--view' => 'emails.shipped'], "view: 'blog::emails.shipped',", 'emails/shipped.blade.php'],
    ]);

    it('puts make:class in the root namespace', function () {
        $dir = initPackage();

        generate($dir, 'make:class', ['name' => 'Support/Slugger.php']);

        expect(contents($dir, 'src/Support/Slugger.php'))->toContain('namespace Acme\Blog\Support;')->toContain('class Slugger');
    });

    it('accepts a fully qualified name in the package namespace', function () {
        $dir = initPackage();

        generate($dir, 'make:class', ['name' => 'Acme\Blog\Services\Publisher']);

        expect("$dir/src/Services/Publisher.php")->toBeFile();
    });
});

describe('make:provider', function () {
    it('creates the main provider in the root namespace and extra ones in Providers', function (bool $toolkit, string $extends) {
        $dir = initPackage(toolkit: $toolkit);

        generate($dir, 'make:provider', ['name' => 'RouteServiceProvider']);

        expect(contents($dir, 'src/Providers/RouteServiceProvider.php'))
            ->toContain('namespace Acme\Blog\Providers;')
            ->toContain('class RouteServiceProvider extends ServiceProvider')
            ->and(provider($dir))->toContain($extends);
    })->with([
        'toolkit' => [true, 'extends PackageServiceProvider'],
        'plain' => [false, 'extends ServiceProvider'],
    ]);

    it('makes a provider the main one with --main', function () {
        $dir = initPackage(toolkit: false);

        generate($dir, 'make:provider', ['name' => 'CoreServiceProvider', '--main' => true]);

        expect("$dir/src/CoreServiceProvider.php")->toBeFile()
            ->and(composerJson($dir)['extra']['laravel']['providers'])->toBe([
                'Acme\\Blog\\CoreServiceProvider',
                'Acme\\Blog\\BlogServiceProvider',
            ]);

        generate($dir, 'make:route');

        expect(contents($dir, 'src/CoreServiceProvider.php'))->toContain('loadRoutesFrom');
    });
});

describe('make:structure', function () {
    it('creates the standard directories once', function () {
        $dir = initPackage();

        $output = generate($dir, 'make:structure');

        foreach (['config', 'database/factories', 'database/migrations', 'database/seeders', 'lang', 'resources/views', 'routes', 'src', 'tests/Feature', 'tests/Unit'] as $directory) {
            expect("$dir/$directory")->toBeDirectory();
        }

        expect($output)->toContain('CREATE database/migrations')->toContain('EXISTS config')
            ->and(generate($dir, 'make:structure'))->toContain('Package structure is complete.');
    });
});

describe('package:info', function () {
    it('shows what was detected', function (bool $toolkit, string $mode) {
        $dir = initPackage(toolkit: $toolkit);

        $output = generate($dir, 'package:info');

        expect($output)
            ->toContain('acme/laravel-blog')
            ->toContain('blog (config key, view/translation namespace, publish tag prefix)')
            ->toContain('Acme\Blog\ => src/')
            ->toContain($mode)
            ->toContain('src/BlogServiceProvider.php')
            ->toContain('^12.0|^13.0');
    })->with([
        'toolkit' => [true, 'yes (nyoncode/laravel-package-toolkit)'],
        'plain' => [false, 'no (plain Laravel ServiceProvider)'],
    ]);

    it('fails without composer.json', function () {
        [$code, $output] = anvil(tempPackage(), 'package:info');

        expect($code)->toBe(1)->and($output)->toContain('Run "package:init"');
    });
});

describe('input validation', function () {
    it('rejects invalid names', function (string $command, string $name, string $message) {
        $dir = initPackage();

        [$code, $output] = anvil($dir, $command, ['name' => $name]);

        expect($code)->toBe(1)->and($output)->toContain($message);
    })->with([
        ['make:class', '9Lives', 'must be a valid class name'],
        ['make:class', 'Post-Model', 'must be a valid class name'],
        ['make:class', 'Admin/Class', 'is reserved by PHP'],
        ['make:class', 'Admin/List', 'is reserved by PHP'],
        ['make:class', 'Int', 'is reserved by PHP'],
        ['make:enum', 'Mixed', 'is reserved by PHP'],
        ['make:view', '../outside', 'may only contain'],
        ['make:config', '../outside', 'may only contain'],
        ['make:route', '../outside', 'may only contain'],
        ['make:lang', '../outside', 'may only contain'],
        ['make:migration', 'create posts', 'may only contain'],
    ]);

    it('rejects option values that would break the generated code', function (string $command, array $input, string $message) {
        $dir = initPackage();

        [$code, $output] = anvil($dir, $command, $input);

        expect($code)->toBe(1)->and($output)->toContain($message)
            ->and(filesIn("$dir/src"))->toBe(['BlogServiceProvider.php']);
    })->with([
        ['make:middleware', ['name' => 'Auth', '--alias' => "x'); exit; ('"], 'The alias may only contain'],
        ['make:migration', ['name' => 'posts', '--create' => "posts'"], 'The table name may only contain'],
        ['make:migration', ['name' => 'posts', '--table' => 'posts table'], 'The table name may only contain'],
        ['make:mail', ['name' => 'Shipped', '--markdown' => '../../outside'], 'The view name may only contain'],
        ['make:mail', ['name' => 'Shipped', '--view' => 'a b'], 'The view name may only contain'],
        ['make:facade', ['name' => 'Blog', '--accessor' => "blog'"], 'The accessor must be'],
        ['make:command', ['name' => 'Sync', '--command' => "blog:sync'"], 'may not contain'],
    ]);

    it('accepts the usual option values', function (string $command, array $input) {
        [$code, $output] = anvil(initPackage(), $command, $input);

        expect($code)->toBe(0, $output);
    })->with([
        ['make:middleware', ['name' => 'Auth', '--alias' => 'blog.auth:admin']],
        ['make:migration', ['name' => 'posts', '--create' => 'blog_posts']],
        ['make:mail', ['name' => 'Shipped', '--markdown' => 'emails/orders.shipped']],
        ['make:facade', ['name' => 'Blog', '--accessor' => 'Acme\Blog\Manager']],
        ['make:command', ['name' => 'Sync', '--command' => 'blog:sync {post} {--force : Overwrite}']],
    ]);

    it('fails without a required name when not interactive', function () {
        [$code] = anvil(initPackage(), 'make:class');

        expect($code)->toBe(1);
    });

    it('asks for a missing name when interactive', function () {
        $dir = initPackage();

        $kernel = new Kernel($dir);
        $kernel->application->setAutoExit(false);
        $tester = new ApplicationTester($kernel->application);
        $tester->setInputs(['Slugger']);

        $code = $tester->run(['command' => 'make:class'], ['interactive' => true, 'decorated' => false]);

        expect($code)->toBe(0, $tester->getDisplay())
            ->and("$dir/src/Slugger.php")->toBeFile();
    });

    it('explains how to start when there is no composer.json', function () {
        [$code, $output] = anvil(tempPackage(), 'make:class', ['name' => 'Thing']);

        expect($code)->toBe(1)->and($output)->toContain('Run "package:init" first.');
    });
});
