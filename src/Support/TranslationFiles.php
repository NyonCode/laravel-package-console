<?php

declare(strict_types=1);

namespace NyonCode\LaravelPackageConsole\Support;

use PhpParser\Error;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\Finder;

/**
 * The translation files of the package, compared across its languages.
 *
 * PHP files ({lang}/{locale}/{group}.php) are read without running them: the keys come from
 * the returned array literal, and a value is kept as the source text it is written in. JSON
 * files ({lang}/{locale}.json) are compared with one another.
 */
final class TranslationFiles
{
    /**
     * Calls whose first argument is a translation key.
     */
    private const USES = '/(?:\b__|\btrans|\btrans_choice|@lang|@choice|\bLang::(?:get|choice|has))\(\s*([\'"])%s::([\w\-\/]+(?:\.[\w\-]+)*)\1/';

    /** @var array<string, array<string, array<string, array{0: list<string>, 1: string}>|null>>|null */
    private ?array $groups = null;

    /** @var array<string, array<string, string>|null>|null */
    private ?array $json = null;

    public function __construct(private readonly Package $package) {}

    /**
     * Files that lack keys another language has.
     *
     * @return list<array{file: string, exists: bool, keys: list<string>, from: list<string>}>
     */
    public function incomplete(): array
    {
        $incomplete = [];

        foreach ($this->missing() as $file => $missing) {
            $incomplete[] = [
                'file' => $file,
                'exists' => is_file($this->package->path($file)),
                'keys' => array_map(fn (array $key) => implode('.', $key['path']), array_values($missing)),
                'from' => array_values(array_unique(array_map(fn (array $key) => $key['locale'], $missing))),
            ];
        }

        return $incomplete;
    }

    /**
     * Add the keys a file lacks, with the text of the language that has them (an empty
     * string would be shown as is, where a missing key falls back to another language).
     *
     * @return int How many keys were added.
     */
    public function complete(string $file): int
    {
        $missing = $this->missing()[$file] ?? [];

        if ($missing === []) {
            return 0;
        }

        $path = $this->package->path($file);

        if (str_ends_with($file, '.json')) {
            $lines = is_file($path) ? (array) json_decode((string) file_get_contents($path), true) : [];

            foreach ($missing as $key) {
                $lines[$key['path'][0]] = $key['value'];
            }

            file_put_contents($path, json_encode($lines, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
        } else {
            $code = is_file($path) ? (string) file_get_contents($path) : "<?php\n\nreturn [];\n";

            foreach ($missing as $key) {
                $code = $this->insert($code, $key['path'], $key['value']);
            }

            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }

            file_put_contents($path, $code);
        }

        $this->groups = $this->json = null;

        return count($missing);
    }

    /**
     * Keys of the package's namespace that code or views use, but no language defines.
     *
     * @param  list<string>  $directories  Where to look, relative to the package root.
     * @return array<string, string> key ("blog::messages.title") => the first file using it.
     */
    public function undefined(string $namespace, array $directories): array
    {
        $undefined = [];
        $pattern = sprintf(self::USES, preg_quote($namespace, '/'));

        foreach ($directories as $directory) {
            if (! is_dir($this->package->path($directory))) {
                continue;
            }

            foreach ((new Finder)->files()->in($this->package->path($directory))->name(['*.php'])->sortByName() as $file) {
                preg_match_all($pattern, $file->getContents(), $matches);

                foreach ($matches[2] as $key) {
                    if (! isset($undefined["$namespace::$key"]) && ! $this->defines($key)) {
                        $undefined["$namespace::$key"] = trim($directory, '/').'/'.str_replace('\\', '/', $file->getRelativePathname());
                    }
                }
            }
        }

        return $undefined;
    }

    /**
     * Whether any language has "group.key" (or the whole group, or an array under the key).
     * A group that cannot be read counts as defining everything.
     */
    private function defines(string $key): bool
    {
        [$group, $item] = [...explode('.', $key, 2), null];

        foreach ($this->groups() as $groups) {
            if (! array_key_exists($group, $groups)) {
                continue;
            }

            if ($item === null || $groups[$group] === null) {
                return true;
            }

            foreach ($groups[$group] as [$path]) {
                $defined = implode('.', $path);

                if ($defined === $item || str_starts_with($defined, $item.'.')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<string, array<string, array{path: list<string>, value: string, locale: string}>> Keyed by file.
     */
    private function missing(): array
    {
        $missing = [];
        $lang = $this->package->translationsPath();
        $groups = $this->groups();

        foreach ($groups as $locale => $own) {
            foreach ($this->union($groups, $locale) as $group => $keys) {
                // An unreadable file may well have the keys.
                if (array_key_exists($group, $own) && $own[$group] === null) {
                    continue;
                }

                $lacking = $this->lacking($keys, $own[$group] ?? []);

                if ($lacking !== []) {
                    $missing["$lang/$locale/$group.php"] = $lacking;
                }
            }
        }

        $json = array_filter($this->json(), fn (?array $lines) => $lines !== null);

        foreach ($json as $locale => $own) {
            $lacking = [];

            foreach ($json as $other => $lines) {
                foreach ($other === $locale ? [] : $lines as $key => $value) {
                    if (! array_key_exists($key, $own) && ! isset($lacking[$key])) {
                        $lacking[$key] = ['path' => [(string) $key], 'value' => (string) $value, 'locale' => (string) $other];
                    }
                }
            }

            if ($lacking !== []) {
                $missing["$lang/$locale.json"] = $lacking;
            }
        }

        ksort($missing);

        return $missing;
    }

    /**
     * Every key the other languages have, per group, with the language it was found in.
     *
     * @param  array<string, array<string, array<string, array{0: list<string>, 1: string}>|null>>  $groups
     * @return array<string, array<string, array{path: list<string>, value: string, locale: string}>>
     */
    private function union(array $groups, string $except): array
    {
        $union = [];

        foreach ($groups as $locale => $files) {
            foreach ($locale === $except ? [] : $files as $group => $keys) {
                foreach ($keys ?? [] as $id => [$path, $value]) {
                    $union[$group][$id] ??= ['path' => $path, 'value' => $value, 'locale' => (string) $locale];
                }
            }
        }

        return $union;
    }

    /**
     * The keys of $keys that $own does not have. A key that is a string in one language and
     * an array in the other is left alone.
     *
     * @param  array<string, array{path: list<string>, value: string, locale: string}>  $keys
     * @param  array<string, array{0: list<string>, 1: string}>  $own
     * @return array<string, array{path: list<string>, value: string, locale: string}>
     */
    private function lacking(array $keys, array $own): array
    {
        $paths = array_map(fn (array $key) => implode("\0", $key[0])."\0", $own);

        return array_filter($keys, function (array $key, string $id) use ($own, $paths) {
            if (isset($own[$id])) {
                return false;
            }

            $path = implode("\0", $key['path'])."\0";

            foreach ($paths as $existing) {
                if (str_starts_with($existing, $path) || str_starts_with($path, $existing)) {
                    return false;
                }
            }

            return true;
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * locale => group => keys (null when the file is not a plain "return [...]"). English
     * comes first, so its text is the one copied.
     *
     * @return array<string, array<string, array<string, array{0: list<string>, 1: string}>|null>>
     */
    private function groups(): array
    {
        if ($this->groups !== null) {
            return $this->groups;
        }

        $this->groups = [];
        $root = $this->package->path($this->package->translationsPath());

        if (! is_dir($root)) {
            return $this->groups;
        }

        foreach ((new Finder)->directories()->in($root)->depth(0)->notName('vendor')->sortByName() as $directory) {
            $locale = $directory->getFilename();

            foreach ((new Finder)->files()->in($directory->getPathname())->name('*.php')->sortByName() as $file) {
                $group = str_replace('\\', '/', substr($file->getRelativePathname(), 0, -4));
                $this->groups[$locale][$group] = $this->keys($file->getContents());
            }
        }

        uksort($this->groups, fn (string $a, string $b) => [$a !== 'en', $a] <=> [$b !== 'en', $b]);

        return $this->groups;
    }

    /**
     * @return array<string, array<string, string>|null> locale => lines (null when not a JSON object).
     */
    private function json(): array
    {
        if ($this->json !== null) {
            return $this->json;
        }

        $this->json = [];
        $root = $this->package->path($this->package->translationsPath());

        if (! is_dir($root)) {
            return $this->json;
        }

        foreach ((new Finder)->files()->in($root)->depth(0)->name('*.json')->sortByName() as $file) {
            $lines = json_decode($file->getContents(), true);
            $this->json[$file->getBasename('.json')] = match (true) {
                $lines === [] => [],
                is_array($lines) && ! array_is_list($lines) => array_filter($lines, 'is_string'),
                default => null,
            };
        }

        uksort($this->json, fn (string $a, string $b) => [$a !== 'en', $a] <=> [$b !== 'en', $b]);

        return $this->json;
    }

    /**
     * The keys of a PHP translation file, each with the source text of its value.
     *
     * @return array<string, array{0: list<string>, 1: string}>|null
     */
    private function keys(string $code): ?array
    {
        $array = $this->returnedArray($code);

        if ($array === null) {
            return null;
        }

        $keys = [];
        $this->flatten($array, $code, [], $keys);

        return $keys;
    }

    /**
     * @param  list<string>  $prefix
     * @param  array<string, array{0: list<string>, 1: string}>  $keys
     */
    private function flatten(Array_ $array, string $code, array $prefix, array &$keys): void
    {
        foreach ($array->items as $item) {
            if (! $item->key instanceof String_) {
                continue;
            }

            $path = [...$prefix, $item->key->value];

            if ($item->value instanceof Array_ && $this->isMap($item->value)) {
                $this->flatten($item->value, $code, $path, $keys);

                continue;
            }

            $keys[implode("\0", $path)] = [
                $path,
                substr($code, $item->value->getStartFilePos(), $item->value->getEndFilePos() - $item->value->getStartFilePos() + 1),
            ];
        }
    }

    /**
     * An array of keyed lines, as opposed to a list that is a value itself.
     */
    private function isMap(Array_ $array): bool
    {
        foreach ($array->items as $item) {
            if (! $item->key instanceof String_) {
                return false;
            }
        }

        return $array->items !== [];
    }

    private function returnedArray(string $code): ?Array_
    {
        try {
            $statements = (new ParserFactory)->createForHostVersion()->parse($code) ?? [];
        } catch (Error) {
            return null;
        }

        foreach ($statements as $statement) {
            if ($statement instanceof Return_) {
                return $statement->expr instanceof Array_ ? $statement->expr : null;
            }
        }

        return null;
    }

    /**
     * Add one key to the code of a PHP translation file, inside the nested array it belongs
     * to, in the file's own formatting.
     *
     * @param  list<string>  $path
     */
    private function insert(string $code, array $path, string $value): string
    {
        $array = $this->returnedArray($code);

        if ($array === null) {
            return $code;
        }

        while (count($path) > 1 && ($nested = $this->nested($array, $path[0])) !== null) {
            $array = $nested;
            array_shift($path);
        }

        $start = $array->getStartFilePos();
        $end = $array->getEndFilePos();
        $outer = $this->lineIndent($code, $start);

        if ($array->items === []) {
            return substr($code, 0, $start)
                ."[\n$outer    ".$this->entry($path, $value, $outer.'    ').",\n$outer]"
                .substr($code, $end + 1);
        }

        $last = $array->items[count($array->items) - 1];
        $position = $last->getEndFilePos() + 1;
        $multiline = str_contains(substr($code, $start, $end - $start), "\n");
        $indent = $multiline ? $this->lineIndent($code, $array->items[0]->getStartFilePos()) : $outer;

        if (preg_match('/\G\s*,/', $code, $comma, 0, $position)) {
            $position += strlen($comma[0]);
        } else {
            $code = substr($code, 0, $position).','.substr($code, $position);
            $position++;
        }

        if (! $multiline) {
            return substr($code, 0, $position).' '.$this->entry($path, $value, $indent).substr($code, $position);
        }

        // After anything else on the line, such as a comment.
        $position = strpos($code, "\n", $position) ?: $position;

        return substr($code, 0, $position)."\n$indent".$this->entry($path, $value, $indent).','.substr($code, $position);
    }

    private function nested(Array_ $array, string $key): ?Array_
    {
        foreach ($array->items as $item) {
            if ($item->key instanceof String_ && $item->key->value === $key) {
                return $item->value instanceof Array_ ? $item->value : null;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $path
     */
    private function entry(array $path, string $value, string $indent): string
    {
        $key = var_export(array_shift($path), true);

        return $path === []
            ? "$key => $value"
            : "$key => [\n$indent    ".$this->entry($path, $value, $indent.'    ').",\n$indent]";
    }

    private function lineIndent(string $code, int $position): string
    {
        $start = strrpos(substr($code, 0, $position), "\n");
        $line = substr($code, $start === false ? 0 : $start + 1, $position - ($start === false ? 0 : $start + 1));

        return (string) preg_replace('/\S.*$/s', '', $line);
    }
}
