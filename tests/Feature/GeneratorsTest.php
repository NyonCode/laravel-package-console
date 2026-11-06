<?php

/*
 * Every class generator: the file lands where PSR-4 expects it, in the right
 * namespace, and is valid PHP.
 */
it('generates valid classes', function (string $command, array $input, string $path, array $contains) {
    $dir = initPackage();

    [$code, $output] = anvil($dir, $command, $input);

    expect($code)->toBe(0, $output)
        ->and("$dir/$path")->toBeFile();

    expectValidPhp("$dir/$path");

    foreach ($contains as $snippet) {
        expect(file_get_contents("$dir/$path"))->toContain($snippet);
    }
})->with([
    'class' => ['make:class', ['name' => 'Admin/UserService'], 'src/Admin/UserService.php', ['namespace Acme\Blog\Admin;', 'class UserService']],
    'invokable class' => ['make:class', ['name' => 'Action', '--invokable' => true], 'src/Action.php', ['public function __invoke(): void']],
    'interface' => ['make:interface', ['name' => 'Publishable'], 'src/Contracts/Publishable.php', ['interface Publishable']],
    'trait' => ['make:trait', ['name' => 'HasSlug'], 'src/Concerns/HasSlug.php', ['trait HasSlug']],
    'enum' => ['make:enum', ['name' => 'Status', '--string' => true], 'src/Enums/Status.php', ['enum Status: string']],
    'exception' => ['make:exception', ['name' => 'PostMissing', '--render' => true], 'src/Exceptions/PostMissing.php', ['public function render(Request $request): ?Response']],
    'controller' => ['make:controller', ['name' => 'PostController', '--model' => 'Post'], 'src/Http/Controllers/PostController.php', ['use Acme\Blog\Models\Post;', 'public function show(Post $post)']],
    'api controller' => ['make:controller', ['name' => 'Api/PostController', '--api' => true], 'src/Http/Controllers/Api/PostController.php', ['namespace Acme\Blog\Http\Controllers\Api;', 'public function update(Request $request, string $id)']],
    'request' => ['make:request', ['name' => 'StorePostRequest'], 'src/Http/Requests/StorePostRequest.php', ['extends FormRequest']],
    'resource' => ['make:resource', ['name' => 'PostResource'], 'src/Http/Resources/PostResource.php', ['extends JsonResource']],
    'resource collection' => ['make:resource', ['name' => 'PostCollection'], 'src/Http/Resources/PostCollection.php', ['extends ResourceCollection']],
    'event' => ['make:event', ['name' => 'PostPublished'], 'src/Events/PostPublished.php', ['use Dispatchable, SerializesModels;']],
    'job' => ['make:job', ['name' => 'SyncPost'], 'src/Jobs/SyncPost.php', ['implements ShouldQueue', 'use Queueable;']],
    'sync job' => ['make:job', ['name' => 'SyncPost', '--sync' => true], 'src/Jobs/SyncPost.php', ['use Dispatchable;']],
    'notification' => ['make:notification', ['name' => 'PostPublished'], 'src/Notifications/PostPublished.php', ['extends Notification']],
    'rule' => ['make:rule', ['name' => 'Slug'], 'src/Rules/Slug.php', ['implements ValidationRule']],
    'cast' => ['make:cast', ['name' => 'Money'], 'src/Casts/Money.php', ['implements CastsAttributes']],
    'inbound cast' => ['make:cast', ['name' => 'Hash', '--inbound' => true], 'src/Casts/Hash.php', ['implements CastsInboundAttributes']],
    'scope' => ['make:scope', ['name' => 'Published'], 'src/Models/Scopes/Published.php', ['implements Scope']],
    'channel' => ['make:channel', ['name' => 'PostChannel'], 'src/Broadcasting/PostChannel.php', ['public function join(Authenticatable $user)']],
    'observer' => ['make:observer', ['name' => 'PostObserver', '--model' => 'Post'], 'src/Observers/PostObserver.php', ['use Acme\Blog\Models\Post;', 'public function created(Post $post): void']],
    'policy' => ['make:policy', ['name' => 'PostPolicy', '--model' => 'Post'], 'src/Policies/PostPolicy.php', ['public function update(Authenticatable $user, Post $post): bool']],
    'facade' => ['make:facade', ['name' => 'Blog'], 'src/Facades/Blog.php', ['return \Acme\Blog\Blog::class;']],
    'facade with accessor' => ['make:facade', ['name' => 'Blog', '--accessor' => 'blog.manager'], 'src/Facades/Blog.php', ["return 'blog.manager';"]],
    'factory' => ['make:factory', ['name' => 'Post'], 'database/factories/PostFactory.php', ['namespace Acme\Blog\Database\Factories;', 'use Acme\Blog\Models\Post;', 'protected $model = Post::class;']],
    'seeder' => ['make:seeder', ['name' => 'BlogPostSeeder'], 'database/seeders/BlogPostSeeder.php', ['namespace Database\Seeders;']],
    'mailable' => ['make:mail', ['name' => 'PostDigest'], 'src/Mail/PostDigest.php', ["subject: 'Post Digest'", "view: 'view.name'"]],
    'phpunit test' => ['make:test', ['name' => 'PostApi', '--phpunit' => true], 'tests/Feature/PostApiTest.php', ['namespace Acme\Blog\Tests\Feature;', 'class PostApiTest extends TestCase']],
    'pest unit test' => ['make:test', ['name' => 'Support/Slug', '--unit' => true], 'tests/Unit/Support/SlugTest.php', ["it('works'"]],
]);

it('generates a model with its migration, factory, seeder, controller and policy', function () {
    $dir = initPackage();

    [$code, $output] = anvil($dir, 'make:model', ['name' => 'BlogPost', '--all' => true]);

    expect($code)->toBe(0, $output);

    foreach ([
        'src/Models/BlogPost.php',
        'database/factories/BlogPostFactory.php',
        'database/migrations/create_blog_posts_table.php',
        'database/seeders/BlogPostSeeder.php',
        'src/Http/Controllers/BlogPostController.php',
        'src/Policies/BlogPostPolicy.php',
    ] as $file) {
        expectValidPhp("$dir/$file");
    }

    expect(file_get_contents("$dir/src/Models/BlogPost.php"))
        ->toContain('use Acme\Blog\Database\Factories\BlogPostFactory;')
        ->toContain('protected static function newFactory(): BlogPostFactory');
});

it('names migrations like Laravel and keeps them timeless with the toolkit', function () {
    $toolkit = initPackage();
    anvil($toolkit, 'make:migration', ['name' => 'add_title_to_posts_table']);

    expect(file_get_contents("$toolkit/database/migrations/add_title_to_posts_table.php"))
        ->toContain("Schema::table('posts'");

    $plain = initPackage(toolkit: false);
    anvil($plain, 'make:migration', ['name' => 'CreatePostsTable']);

    expect(glob("$plain/database/migrations/*_create_posts_table.php"))->toHaveCount(1);
    expect(anvil($plain, 'make:migration', ['name' => 'create_posts_table'])[1])->toContain('already exists');
});

it('refuses to overwrite without --force, and rejects reserved or invalid names', function () {
    $dir = initPackage();

    expect(anvil($dir, 'make:class', ['name' => 'Thing'])[0])->toBe(0)
        ->and(anvil($dir, 'make:class', ['name' => 'Thing'])[0])->toBe(1)
        ->and(anvil($dir, 'make:class', ['name' => 'Thing', '--force' => true])[0])->toBe(0)
        ->and(anvil($dir, 'make:class', ['name' => 'Admin/Class'])[1])->toContain('reserved by PHP')
        ->and(anvil($dir, 'make:class', ['name' => '9Lives'])[0])->toBe(1);
});

it('uses customised stubs from the package', function () {
    $dir = initPackage();
    mkdir("$dir/stubs/anvil", 0755, true);
    file_put_contents("$dir/stubs/anvil/class.stub", "<?php\n\nnamespace {{ namespace }};\n\nfinal class {{ class }} // custom\n{\n}\n");

    anvil($dir, 'make:class', ['name' => 'Thing']);

    expect(file_get_contents("$dir/src/Thing.php"))->toContain('final class Thing // custom');
});

it('uses customised package skeleton stubs', function () {
    $dir = tempPackage();
    mkdir("$dir/stubs/anvil/package", 0755, true);
    file_put_contents("$dir/stubs/anvil/package/README.stub", "# {{ packageName }} by {{ author }}\n");

    anvil($dir, 'package:init', ['--name' => 'acme/laravel-blog', '--author' => 'Jane Doe', '--toolkit' => true]);

    expect(file_get_contents("$dir/README.md"))->toBe("# Blog by Jane Doe\n");
});
