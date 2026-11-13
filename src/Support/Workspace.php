<?php

declare(strict_types=1);

namespace NyonCode\LaravelPackageConsole\Support;

use RuntimeException;

/**
 * Where the console runs, and which package it works on there.
 *
 * Without --package it works on:
 * - the working directory, when it has a composer.json (unless it is the root of a monorepo
 *   that is not a package itself);
 * - the only package of a monorepo, in the monorepo's root;
 * - the package of the nearest composer.json above, in a subdirectory of a package;
 * - the working directory, when there is no composer.json (package:init creates it).
 *
 * In the root of a monorepo with several packages it asks which one, or fails when it cannot
 * ask. --package names a package of the monorepo (see Monorepo::find()) or a directory.
 */
final class Workspace
{
    private Monorepo|false|null $monorepo = null;

    public function __construct(private readonly string $directory) {}

    public function directory(): string
    {
        return $this->directory;
    }

    /**
     * The monorepo around the working directory: the nearest directory, the working directory
     * included, whose composer.json lists path repositories inside it. A working directory
     * whose composer.json is not a package and lists none is a monorepo with no packages yet.
     */
    public function monorepo(): ?Monorepo
    {
        if ($this->monorepo === null) {
            $this->monorepo = false;

            foreach ($this->upwards() as $directory) {
                if (($monorepo = Monorepo::at($directory)) !== null) {
                    $this->monorepo = $monorepo;
                    break;
                }
            }

            if ($this->monorepo === false && is_file($this->directory.DIRECTORY_SEPARATOR.'composer.json') && ! Monorepo::isPackage($this->directory)) {
                $this->monorepo = new Monorepo($this->directory);
            }
        }

        return $this->monorepo ?: null;
    }

    /**
     * The packages of the monorepo, relative path => absolute path; none outside a monorepo.
     *
     * @return array<string, string>
     */
    public function packages(): array
    {
        return $this->monorepo()?->packages() ?? [];
    }

    /**
     * The directory of the package to work on.
     *
     * @param  string|null  $selector  --package: a package of the monorepo, or a directory.
     * @param  bool  $creating  For package:init: the directory may be one without a package yet.
     *
     * @throws RuntimeException When the package cannot be found, or it is not clear which one is meant.
     */
    public function select(?string $selector, bool $creating, Prompts $prompts): string
    {
        if ($selector !== null && $selector !== '') {
            return $this->locate($selector, $creating);
        }

        $root = $this->monorepoRoot($creating);

        if ($root === null) {
            return $this->defaultPackage($creating);
        }

        $packages = $root->packages();

        if (count($packages) === 1) {
            return reset($packages);
        }

        if ($prompts->interactive() && $creating) {
            return $this->locate($prompts->text(Lang::get('In which directory of the monorepo should the package be?'), placeholder: 'packages/blog', required: true), true);
        }

        if ($packages === []) {
            throw new RuntimeException('The monorepo in '.$root->root().' has no packages yet. Create one with "package:init --package=packages/<name>".');
        }

        if ($prompts->interactive()) {
            return $packages[$prompts->select(Lang::get('Which package of the monorepo?'), $this->labels($packages))];
        }

        throw new RuntimeException(sprintf(
            'The monorepo has %d packages, choose one with --package: %s.',
            count($packages),
            implode(', ', array_keys($packages))
        ));
    }

    /**
     * The package --package names: a package of the monorepo, else a directory relative to the
     * working directory or to the monorepo's root that has a composer.json (or, when creating,
     * any directory inside the monorepo or relative to the working directory).
     */
    public function locate(string $selector, bool $creating = false): string
    {
        $monorepo = $this->monorepo();
        $matches = $monorepo?->find($selector) ?? [];

        if (count($matches) === 1) {
            return $monorepo->root($matches[0]);
        }

        if (count($matches) > 1) {
            throw new RuntimeException(sprintf('"%s" matches several packages: %s. Use the directory.', $selector, implode(', ', $matches)));
        }

        $candidates = [Path::join($this->directory, $selector)];

        if ($monorepo !== null && ! Path::isAbsolute($selector)) {
            $candidates[] = $monorepo->root($selector);
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate.DIRECTORY_SEPARATOR.'composer.json')) {
                return $this->clean($candidate);
            }
        }

        if ($creating) {
            return $this->clean($candidates[array_key_last($candidates)]);
        }

        $known = $monorepo !== null ? array_keys($monorepo->packages()) : [];

        throw new RuntimeException(sprintf('No package "%s" found.', $selector).($known !== []
            ? ' The packages of the monorepo: '.implode(', ', $known).'.'
            : ' A package is a directory with a composer.json; create one with "package:init --package='.$selector.'".'));
    }

    /**
     * The monorepo whose package must be chosen: the one rooted in the directory the console
     * works from, when that is not a package itself.
     */
    private function monorepoRoot(bool $creating): ?Monorepo
    {
        $start = $this->directory;

        if (! is_file($start.DIRECTORY_SEPARATOR.'composer.json')) {
            if ($creating) {
                return null;
            }

            $start = $this->nearestComposer() ?? $start;
        }

        $monorepo = Monorepo::at($start);

        return $monorepo !== null && ! $monorepo->rootIsPackage() ? $monorepo : null;
    }

    private function defaultPackage(bool $creating): string
    {
        if (! $creating && ! is_file($this->directory.DIRECTORY_SEPARATOR.'composer.json')) {
            return $this->nearestComposer() ?? $this->directory;
        }

        return $this->directory;
    }

    /**
     * The nearest directory above the working one with a composer.json.
     */
    private function nearestComposer(): ?string
    {
        foreach ($this->upwards() as $directory) {
            if (is_file($directory.DIRECTORY_SEPARATOR.'composer.json')) {
                return $directory;
            }
        }

        return null;
    }

    /**
     * The working directory and the ones above it, up to a trust boundary: a monorepo's stubs
     * end up in generated code and package:init writes to its root, so a composer.json planted
     * in a shared directory above the package must not count. The walk stops at the root of the
     * git repository, below the home and the temporary directory, and at a directory owned by
     * another user.
     *
     * @return iterable<string>
     */
    private function upwards(): iterable
    {
        $directory = rtrim($this->directory, '/\\') ?: DIRECTORY_SEPARATOR;
        $boundaries = array_filter(array_map(
            fn (string|false $path) => $path === false || $path === '' ? null : (realpath($path) ?: null),
            [getenv('HOME'), getenv('USERPROFILE'), sys_get_temp_dir()]
        ));
        $user = function_exists('posix_geteuid') ? posix_geteuid() : null;

        while (true) {
            yield $directory;

            $parent = dirname($directory);

            if ($parent === $directory || file_exists($directory.DIRECTORY_SEPARATOR.'.git')) {
                return;
            }

            $real = realpath($parent) ?: $parent;

            foreach ($boundaries as $boundary) {
                if ($real === $boundary || str_starts_with($boundary.DIRECTORY_SEPARATOR, rtrim($real, '/\\').DIRECTORY_SEPARATOR)) {
                    return;
                }
            }

            if ($user !== null && @fileowner($parent) !== $user) {
                return;
            }

            $directory = $parent;
        }
    }

    /**
     * @param  array<string, string>  $packages
     * @return array<string, string>
     */
    private function labels(array $packages): array
    {
        $labels = [];

        foreach ($packages as $relative => $path) {
            $name = ComposerJson::in($path)->get('name');
            $labels[$relative] = $relative.(is_string($name) ? " ($name)" : '');
        }

        return $labels;
    }

    /**
     * A directory without "." and ".." segments (written with the platform's separator).
     */
    private function clean(string $path): string
    {
        return str_replace('/', DIRECTORY_SEPARATOR, Path::normalize($path));
    }
}
