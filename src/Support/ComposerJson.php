<?php

declare(strict_types=1);

namespace NyonCode\LaravelPackageConsole\Support;

use RuntimeException;

/**
 * Reads and writes a composer.json file, keeping Composer's own formatting
 * (4-space indent, unescaped slashes and unicode, trailing newline).
 */
final class ComposerJson
{
    /**
     * Keys that Composer expects to be JSON objects even when empty.
     */
    private const OBJECT_KEYS = [
        'require', 'require-dev', 'autoload', 'autoload-dev', 'extra',
        'scripts', 'config', 'suggest', 'conflict', 'replace', 'provide',
    ];

    /**
     * Composer's conventional order of the top-level keys; unknown keys follow.
     */
    private const KEY_ORDER = [
        'name', 'description', 'type', 'keywords', 'homepage', 'license', 'authors', 'support', 'funding',
        'require', 'require-dev', 'suggest', 'conflict', 'replace', 'provide', 'autoload', 'autoload-dev',
        'extra', 'scripts', 'config', 'bin', 'minimum-stability', 'prefer-stable',
    ];

    private array $data;

    public function __construct(private readonly string $path)
    {
        $this->data = $this->load();
    }

    public static function in(string $directory): self
    {
        return new self(rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'composer.json');
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    public function path(): string
    {
        return $this->path;
    }

    public function all(): array
    {
        return $this->data;
    }

    /**
     * Get a value using "dot" notation (e.g. "extra.laravel.providers").
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->data;

        foreach (explode('.', $key) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Set a value using "dot" notation.
     */
    public function set(string $key, mixed $value): self
    {
        $segments = explode('.', $key);
        $target = &$this->data;

        foreach ($segments as $segment) {
            if (! isset($target[$segment]) || ! is_array($target[$segment])) {
                $target[$segment] = [];
            }

            $target = &$target[$segment];
        }

        $target = $value;

        return $this;
    }

    /**
     * Set a value only when the key is not present yet.
     */
    public function setIfMissing(string $key, mixed $value): self
    {
        if ($this->get($key) === null) {
            $this->set($key, $value);
        }

        return $this;
    }

    /**
     * Append a value to a list, unless it is already there.
     *
     * @return bool Whether the value was added.
     */
    public function pushUnique(string $key, mixed $value): bool
    {
        $list = $this->get($key, []);

        if (in_array($value, $list, true)) {
            return false;
        }

        $list[] = $value;
        $this->set($key, $list);

        return true;
    }

    /**
     * Add entries to a map (e.g. "require"), keeping the values already there.
     *
     * @param  array<string, mixed>  $entries
     */
    public function mergeMissing(string $key, array $entries): self
    {
        $this->set($key, array_merge($entries, $this->get($key, [])));

        return $this;
    }

    public function hasPackage(string $package): bool
    {
        return $this->get('require.'.$package) !== null
            || $this->get('require-dev.'.$package) !== null;
    }

    public function save(): void
    {
        $data = $this->data;
        $sorted = [];

        foreach (self::KEY_ORDER as $key) {
            if (array_key_exists($key, $data)) {
                $sorted[$key] = $data[$key];
                unset($data[$key]);
            }
        }

        $data = $sorted + $data;

        foreach (self::OBJECT_KEYS as $key) {
            if (array_key_exists($key, $data) && $data[$key] === []) {
                $data[$key] = (object) [];
            }
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        if (file_put_contents($this->path, $json."\n") === false) {
            throw new RuntimeException("Unable to write [{$this->path}].");
        }
    }

    private function load(): array
    {
        if (! is_file($this->path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($this->path), true);

        if (! is_array($data)) {
            throw new RuntimeException("[{$this->path}] does not contain valid JSON.");
        }

        return $data;
    }
}
