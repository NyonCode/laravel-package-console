<?php

declare(strict_types=1);

namespace NyonCode\LaravelPackageConsole\Support;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\MagicConst\Dir;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\Finder;
use Throwable;

/**
 * A repository with several Composer packages. The root's composer.json says where they are,
 * in one (or more) of the standard ways:
 *
 * - path: Composer "path" repositories inside the root ({"type": "path", "url": "packages/*"});
 *   the root requires each package (as @dev) and Composer symlinks it into vendor/;
 * - merge-plugin: wikimedia/composer-merge-plugin includes the packages' composer.json files
 *   ("extra.merge-plugin.include": ["packages/*\/composer.json"]), so their requirements and
 *   autoloading are merged when Composer runs;
 * - monorepo-builder: symplify/monorepo-builder (monorepo-builder.php, packages in "packages/"
 *   unless packageDirectories() says otherwise); the root holds the merged requirements and
 *   autoloading of every package and replaces them at "self.version";
 * - replace: the root replaces its packages at "self.version" and autoloads their classes
 *   itself, like laravel/framework and its illuminate/* packages.
 *
 * The packages are the directories with a composer.json these point at, and the root itself
 * when it autoloads classes.
 */
final class Monorepo
{
    public const PATH = 'path';

    public const MERGE_PLUGIN = 'merge-plugin';

    public const BUILDER = 'monorepo-builder';

    public const REPLACE = 'replace';

    public const LABELS = [
        self::PATH => 'Composer path repositories',
        self::MERGE_PLUGIN => 'wikimedia/composer-merge-plugin',
        self::BUILDER => 'symplify/monorepo-builder',
        self::REPLACE => 'the root replaces its packages',
    ];

    private const IGNORED = ['vendor', 'node_modules', '.git'];

    private ?ComposerJson $composer = null;

    /** @var array<string, string>|null */
    private ?array $packages = null;

    /** @var array{0: list<string>, 1: list<string>}|null */
    private ?array $builder = null;

    public function __construct(private readonly string $root) {}

    /**
     * The monorepo rooted in $directory, when its composer.json says where its packages are.
     */
    public static function at(string $directory): ?self
    {
        $monorepo = new self($directory);

        return is_file($monorepo->root('composer.json')) && $monorepo->layouts() !== [] ? $monorepo : null;
    }

    /**
     * Whether the composer.json in $directory is a package: it autoloads classes.
     */
    public static function isPackage(string $directory): bool
    {
        $composer = ComposerJson::in($directory);

        return $composer->exists() && (array) $composer->get('autoload.psr-4', []) !== [];
    }

    public function root(string $path = ''): string
    {
        return Path::join($this->root, $path);
    }

    public function composer(): ComposerJson
    {
        return $this->composer ??= ComposerJson::in($this->root);
    }

    /**
     * The ways the root's composer.json points at packages (see the class comment).
     *
     * @return list<string>
     */
    public function layouts(): array
    {
        return array_values(array_filter([
            $this->pathPatterns() !== [] ? self::PATH : null,
            $this->mergePluginPatterns() !== [] ? self::MERGE_PLUGIN : null,
            is_file($this->root('monorepo-builder.php')) ? self::BUILDER : null,
            $this->replaced() !== [] && $this->replacedPackages() !== [] ? self::REPLACE : null,
        ]));
    }

    /**
     * How the root installs a package added to the monorepo: the merge plugin or the
     * monorepo-builder when the root uses one, else as it installs the ones it has, else with
     * a path repository.
     */
    public function layout(): string
    {
        $layouts = $this->layouts();

        foreach ([self::MERGE_PLUGIN, self::BUILDER, self::REPLACE] as $layout) {
            if (in_array($layout, $layouts, true)) {
                return $layout;
            }
        }

        return self::PATH;
    }

    /**
     * Whether the root holds the requirements and autoloading of its packages itself.
     */
    public function merged(): bool
    {
        return in_array($this->layout(), [self::BUILDER, self::REPLACE], true);
    }

    /**
     * The URLs of the path repositories inside the root, relative to it ("packages/*").
     *
     * @return list<string>
     */
    public function pathPatterns(): array
    {
        $patterns = [];

        foreach ((array) $this->composer()->get('repositories', []) as $repository) {
            if (is_array($repository) && ($repository['type'] ?? null) === 'path' && is_string($repository['url'] ?? null)
                && ($relative = $this->inside($repository['url'])) !== null) {
                $patterns[] = $relative;
            }
        }

        return array_values(array_unique($patterns));
    }

    /**
     * The composer.json globs of extra.merge-plugin "include" and "require" inside the root.
     *
     * @return list<string>
     */
    public function mergePluginPatterns(): array
    {
        $patterns = [];

        foreach (['include', 'require'] as $key) {
            foreach ((array) $this->composer()->get("extra.merge-plugin.$key", []) as $pattern) {
                if (is_string($pattern) && ($relative = $this->inside($pattern)) !== null) {
                    $patterns[] = $relative;
                }
            }
        }

        return array_values(array_unique($patterns));
    }

    /**
     * The package directories of monorepo-builder.php, and the excluded ones, relative to the root.
     *
     * @return array{0: list<string>, 1: list<string>}
     */
    public function builderDirectories(): array
    {
        if ($this->builder !== null) {
            return $this->builder;
        }

        $directories = [];
        $excludes = [];

        try {
            $ast = (new ParserFactory)->createForHostVersion()->parse((string) file_get_contents($this->root('monorepo-builder.php'))) ?? [];

            foreach ((new NodeFinder)->findInstanceOf($ast, MethodCall::class) as $call) {
                $name = $call->name instanceof Identifier ? $call->name->toString() : '';
                $arguments = $call->getArgs();
                $list = null;
                $excluded = false;
                $first = $arguments[0]->value ?? null;
                $option = $first instanceof ClassConstFetch && $first->name instanceof Identifier ? $first->name->toString() : '';

                if (in_array($name, ['packageDirectories', 'packageDirectoriesExcludes'], true)) {
                    $list = $first;
                    $excluded = $name === 'packageDirectoriesExcludes';
                } elseif ($name === 'set' && str_starts_with($option, 'PACKAGE_DIRECTORIES')) {
                    // The older parameter style: $parameters->set(Option::PACKAGE_DIRECTORIES, [...]).
                    $list = $arguments[1]->value ?? null;
                    $excluded = $option === 'PACKAGE_DIRECTORIES_EXCLUDES';
                }

                if (! $list instanceof Array_) {
                    continue;
                }

                foreach ($list->items as $item) {
                    if ($item !== null && ($path = $this->stringPath($item->value)) !== null && ($relative = $this->inside($path)) !== null) {
                        if ($excluded) {
                            $excludes[] = $relative;
                        } else {
                            $directories[] = $relative;
                        }
                    }
                }
            }
        } catch (Throwable) {
            // An unreadable config: the default directory.
        }

        return $this->builder = [$directories !== [] ? array_values(array_unique($directories)) : ['packages'], $excludes];
    }

    /**
     * The packages of the monorepo: path relative to the root ("." for the root) => absolute path.
     *
     * @return array<string, string>
     */
    public function packages(): array
    {
        if ($this->packages !== null) {
            return $this->packages;
        }

        $found = [];
        $flags = GLOB_ONLYDIR | (defined('GLOB_BRACE') ? GLOB_BRACE : 0);

        foreach ($this->pathPatterns() as $pattern) {
            foreach (glob($this->root($pattern), $flags) ?: [] as $directory) {
                $found[] = $directory;
            }
        }

        foreach ($this->mergePluginPatterns() as $pattern) {
            foreach (glob($this->root($pattern), defined('GLOB_BRACE') ? GLOB_BRACE : 0) ?: [] as $file) {
                $found[] = dirname($file);
            }
        }

        if (is_file($this->root('monorepo-builder.php'))) {
            [$directories, $excludes] = $this->builderDirectories();

            foreach ($this->composerFiles($directories, 2) as $file) {
                $relative = (string) $this->relative(dirname($file));

                if (! $this->within($relative, $excludes)) {
                    $found[] = dirname($file);
                }
            }
        }

        array_push($found, ...array_values($this->replacedPackages()));

        $packages = [];

        foreach ($found as $directory) {
            $relative = $this->relative($directory);

            if ($relative !== null && $relative !== '.' && is_file($directory.DIRECTORY_SEPARATOR.'composer.json')) {
                $packages[$relative] = $this->root($relative);
            }
        }

        ksort($packages);
        $this->packages = $packages;

        return $this->packages = ($this->rootIsPackage() ? ['.' => $this->root] : []) + $packages;
    }

    /**
     * Whether the root is a package of its own: it autoloads classes from outside its packages
     * (laravel/framework does; a monorepo-builder root only autoloads its packages' classes).
     */
    public function rootIsPackage(): bool
    {
        if ($this->packages === null) {
            $this->packages();
        }

        $packages = array_values(array_diff(array_keys((array) $this->packages), ['.']));

        foreach ((array) $this->composer()->get('autoload.psr-4', []) as $paths) {
            foreach ((array) $paths as $path) {
                if (! $this->within(Path::normalize((string) $path), $packages)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The Composer names of the packages (relative path => name).
     *
     * @return array<string, string>
     */
    public function names(): array
    {
        $names = [];

        foreach ($this->packages() as $relative => $path) {
            $name = ComposerJson::in($path)->get('name');

            if (is_string($name) && $name !== '') {
                $names[$relative] = $name;
            }
        }

        return $names;
    }

    /**
     * The packages $selector names: by directory relative to the root ("packages/blog"),
     * directory name ("blog"), Composer name ("acme/laravel-blog") or short name ("blog").
     * A match by directory or Composer name wins over one by directory name or short name.
     *
     * @return list<string> Relative paths.
     */
    public function find(string $selector): array
    {
        $selector = Path::normalize($selector);
        $names = $this->names();
        $exact = [];
        $loose = [];

        foreach (array_keys($this->packages()) as $relative) {
            $name = $names[$relative] ?? '';

            if ($selector === $relative || $selector === $name) {
                $exact[] = $relative;
            } elseif ($selector === basename($relative) || ($name !== '' && $selector === Package::shortNameFor($name))) {
                $loose[] = $relative;
            }
        }

        return $exact !== [] ? $exact : $loose;
    }

    /**
     * Whether the root, as it is configured, finds a package in the directory (relative to it).
     */
    public function covers(string $relative): bool
    {
        return match ($this->layout()) {
            self::PATH => $this->matches($this->pathPatterns(), $relative),
            self::MERGE_PLUGIN => $this->matches($this->mergePluginPatterns(), $relative.'/composer.json'),
            self::BUILDER => $this->within($relative, $this->builderDirectories()[0]) && ! $this->within($relative, $this->builderDirectories()[1]),
            default => true,
        };
    }

    /**
     * $path relative to the root ("." for the root), or null when it is outside.
     */
    public function relative(string $path): ?string
    {
        $root = Path::normalize($this->root);
        $path = Path::normalize($path);

        if ($path === $root) {
            return '.';
        }

        return str_starts_with($path, $root.'/') ? substr($path, strlen($root) + 1) : null;
    }

    /**
     * What the root's composer.json needs to install the package in $relative, as the
     * monorepo's layout does it, written unless $write is false:
     *
     * - path: a path repository for its directory (unless one matches it), the package in
     *   require-dev as @dev;
     * - merge-plugin: its composer.json in extra.merge-plugin.include (unless a glob matches it);
     * - monorepo-builder and replace: what monorepo-builder's "merge" adds for it: its PSR-4
     *   autoloading with the paths prefixed, its requirements that the root lacks (not the
     *   ones on packages of the monorepo), and "replace" at self.version.
     *
     * @param  array<string, string>  $requireDev  More require-dev entries the root should have (package => constraint).
     * @return list<string> What was (or would be) added.
     */
    public function install(string $relative, array $requireDev = [], bool $write = true): array
    {
        $composer = $write ? $this->composer() : ComposerJson::in($this->root);
        $package = ComposerJson::in($this->root($relative));
        $name = (string) $package->get('name');
        $added = [];

        switch ($this->layout()) {
            case self::PATH:
                if (! $this->covers($relative)) {
                    $this->appendRepository($composer, $relative);
                    $added[] = "path repository $relative";
                }

                if ($name !== '') {
                    $requireDev = [$name => '@dev'] + $requireDev;
                }
                break;

            case self::MERGE_PLUGIN:
                if (! $this->covers($relative)) {
                    $include = (array) $composer->get('extra.merge-plugin.include', []);
                    $include[] = "$relative/composer.json";
                    $composer->set('extra.merge-plugin.include', $include);
                    $added[] = "merge-plugin include $relative/composer.json";
                }
                break;

            default:
                array_push($added, ...$this->merge($composer, $package, $relative));
        }

        array_push($added, ...$this->requireMissing($composer, 'require-dev', $requireDev));

        if ($write && $added !== []) {
            $composer->save();
            $this->packages = null;
        }

        return $added;
    }

    /**
     * Dependencies the packages require with different constraints (what monorepo-builder's
     * "validate" reports), package => [relative path => constraint].
     *
     * @return array<string, array<string, string>>
     */
    public function conflictingConstraints(): array
    {
        $siblings = array_values($this->names());
        $constraints = [];

        foreach ($this->packages() as $relative => $path) {
            if ($relative === '.') {
                continue;
            }

            $composer = ComposerJson::in($path);

            foreach (['require', 'require-dev'] as $section) {
                foreach ((array) $composer->get($section, []) as $dependency => $constraint) {
                    if (is_string($constraint) && ! in_array($dependency, $siblings, true)) {
                        $constraints[$dependency][$relative] = $constraint;
                    }
                }
            }
        }

        return array_filter($constraints, fn (array $byPackage) => count(array_unique($byPackage)) > 1);
    }

    /**
     * Requirements of the package in $relative on other packages of the monorepo that the
     * version Composer gives a path package cannot satisfy: a version range ("^1.0") on a
     * package with neither "version" nor "extra.branch-alias", so it is only "dev-main".
     *
     * @return array<string, string> Package => constraint.
     */
    public function unsatisfiableSiblings(string $relative): array
    {
        if ($this->layout() !== self::PATH) {
            return [];
        }

        $names = array_flip($this->names());
        $composer = ComposerJson::in($this->root($relative));
        $found = [];

        foreach (['require', 'require-dev'] as $section) {
            foreach ((array) $composer->get($section, []) as $dependency => $constraint) {
                if (! is_string($constraint) || ! isset($names[$dependency]) || $names[$dependency] === $relative) {
                    continue;
                }

                $sibling = ComposerJson::in($this->root($names[$dependency]));
                $versioned = $sibling->get('version') !== null || $sibling->get('extra.branch-alias') !== null || $this->repositoryVersion((string) $dependency) !== null;
                $open = preg_match('/(^|[\s|,])(\*|@dev|dev-\S+|self\.version)/', $constraint) === 1;

                if (! $versioned && ! $open) {
                    $found[(string) $dependency] = $constraint;
                }
            }
        }

        return $found;
    }

    /**
     * The globs to give a package's own composer.json so that it finds the other packages when
     * it is installed on its own (in CI): the path repositories, else the directories of the packages.
     *
     * @return list<string>
     */
    public function repositoryPatterns(): array
    {
        if ($this->layout() === self::PATH) {
            return $this->pathPatterns();
        }

        $patterns = [];

        foreach (array_keys($this->packages()) as $relative) {
            if ($relative !== '.') {
                $patterns[] = str_contains($relative, '/') ? dirname($relative).'/*' : $relative;
            }
        }

        return array_values(array_unique($patterns));
    }

    /**
     * monorepo-builder's "merge" for one package.
     *
     * @return list<string>
     */
    private function merge(ComposerJson $root, ComposerJson $package, string $relative): array
    {
        $added = [];
        $siblings = array_values($this->names());
        $name = (string) $package->get('name');

        foreach (['autoload', 'autoload-dev'] as $section) {
            $psr4 = (array) $root->get("$section.psr-4", []);

            foreach ((array) $package->get("$section.psr-4", []) as $namespace => $paths) {
                foreach ((array) $paths as $path) {
                    $prefixed = rtrim(Path::normalize($relative.'/'.$path), '/').'/';

                    if ($this->autoloads($psr4, (string) $namespace, $prefixed)) {
                        continue;
                    }

                    $current = $psr4[$namespace] ?? null;
                    $psr4[$namespace] = $current === null ? $prefixed : [...(array) $current, $prefixed];
                    $added[] = "$section ".rtrim((string) $namespace, '\\').' => '.$prefixed;
                }
            }

            if ($psr4 !== (array) $root->get("$section.psr-4", [])) {
                $root->set("$section.psr-4", $psr4);
            }
        }

        foreach (['require', 'require-dev'] as $section) {
            $missing = [];

            foreach ((array) $package->get($section, []) as $dependency => $constraint) {
                if (is_string($constraint) && ! in_array($dependency, $siblings, true)) {
                    $missing[(string) $dependency] = $constraint;
                }
            }

            array_push($added, ...$this->requireMissing($root, $section, $missing));
        }

        $replace = (array) $root->get('replace', []);

        if ($name !== '' && ! array_key_exists($name, $replace)) {
            $replace[$name] = 'self.version';
            $root->set('replace', $replace);
            $added[] = "replace $name";
        }

        return $added;
    }

    /**
     * Whether a PSR-4 map autoloads $namespace from $path: the same entry, or a shorter
     * namespace whose directory holds it (Laravel's "Illuminate\\" => "src/Illuminate/").
     *
     * @param  array<string, string|list<string>>  $psr4
     */
    private function autoloads(array $psr4, string $namespace, string $path): bool
    {
        $namespace = trim($namespace, '\\');

        foreach ($psr4 as $prefix => $directories) {
            $prefix = trim((string) $prefix, '\\');

            if ($prefix !== '' && $namespace !== $prefix && ! str_starts_with($namespace, $prefix.'\\')) {
                continue;
            }

            $rest = str_replace('\\', '/', ltrim(substr($namespace, strlen($prefix)), '\\'));

            foreach ((array) $directories as $directory) {
                if (rtrim(Path::normalize($directory.'/'.$rest), '/').'/' === $path) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $entries
     * @return list<string>
     */
    private function requireMissing(ComposerJson $composer, string $section, array $entries): array
    {
        $require = (array) $composer->get('require', []);
        $current = (array) $composer->get($section, []);
        $added = [];

        foreach ($entries as $package => $constraint) {
            if (! array_key_exists($package, $require) && ! array_key_exists($package, $current)) {
                $current[$package] = $constraint;
                $added[] = "$section $package";
            }
        }

        if ($added !== []) {
            if ($composer->get('config.sort-packages') === true) {
                ksort($current);
            }

            $composer->set($section, $current);
        }

        return $added;
    }

    private function appendRepository(ComposerJson $composer, string $relative): void
    {
        $repositories = (array) $composer->get('repositories', []);
        $repository = ['type' => 'path', 'url' => $relative];

        if (array_is_list($repositories)) {
            $repositories[] = $repository;
        } else {
            $repositories[basename($relative)] = $repository;
        }

        $composer->set('repositories', $repositories);
    }

    /**
     * The version a path repository's "options.versions" gives a package.
     */
    private function repositoryVersion(string $package): ?string
    {
        foreach ((array) $this->composer()->get('repositories', []) as $repository) {
            $version = is_array($repository) ? ($repository['options']['versions'][$package] ?? null) : null;

            if (is_string($version)) {
                return $version;
            }
        }

        return null;
    }

    /**
     * Packages the root replaces at self.version.
     *
     * @return list<string>
     */
    private function replaced(): array
    {
        return array_keys(array_filter((array) $this->composer()->get('replace', []), fn ($version) => $version === 'self.version'));
    }

    /**
     * The directories of the packages the root replaces, found by their composer.json (name => path).
     *
     * @return array<string, string>
     */
    private function replacedPackages(): array
    {
        $replaced = $this->replaced();

        if ($replaced === []) {
            return [];
        }

        $found = [];

        foreach ($this->composerFiles(['.'], 4) as $file) {
            $name = ComposerJson::in(dirname($file))->get('name');

            if (is_string($name) && in_array($name, $replaced, true) && dirname($file) !== rtrim($this->root, '/\\')) {
                $found[$name] = dirname($file);
            }
        }

        return $found;
    }

    /**
     * The composer.json files in the directories (relative to the root), up to $depth levels down.
     *
     * @param  list<string>  $directories
     * @return list<string>
     */
    private function composerFiles(array $directories, int $depth): array
    {
        $existing = array_values(array_filter(array_map(fn (string $directory) => $this->root($directory), $directories), 'is_dir'));

        if ($existing === []) {
            return [];
        }

        $files = [];

        foreach ((new Finder)->files()->in($existing)->name('composer.json')->exclude(self::IGNORED)->depth('<= '.$depth)->depth('> 0') as $file) {
            $files[] = $file->getPathname();
        }

        sort($files);

        return $files;
    }

    /**
     * A path relative to the root, when $path is inside it.
     */
    private function inside(string $path): ?string
    {
        $relative = Path::isAbsolute($path) ? $this->relative($path) : Path::normalize($path);

        return $relative !== null && $relative !== '.' && ! str_starts_with($relative, '..') ? $relative : null;
    }

    /**
     * @param  list<string>  $patterns
     */
    private function matches(array $patterns, string $relative): bool
    {
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $relative, FNM_PATHNAME)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $directories
     */
    private function within(string $relative, array $directories): bool
    {
        foreach ($directories as $directory) {
            if ($relative === $directory || str_starts_with($relative, rtrim($directory, '/').'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * "__DIR__ . '/packages'" or "'packages'" from monorepo-builder.php.
     */
    private function stringPath(Node $node): ?string
    {
        if ($node instanceof String_) {
            return $node->value;
        }

        if ($node instanceof Concat && $node->left instanceof Dir && $node->right instanceof String_) {
            return $this->root(ltrim($node->right->value, '/'));
        }

        return null;
    }
}
