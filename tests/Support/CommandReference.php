<?php

namespace NyonCode\LaravelPackageConsole\Tests\Support;

use NyonCode\LaravelPackageConsole\Kernel;
use Symfony\Component\Console\Command\Command;

/**
 * Renders the per-command reference in docs/commands.md from the command definitions,
 * so the documented arguments, options, shortcuts and defaults can never drift.
 *
 *   composer docs:commands   # rewrite the block in docs/commands.md
 */
final class CommandReference
{
    public const START = '<!-- commands:start -->';

    public const END = '<!-- commands:end -->';

    public const DOCUMENT = __DIR__.'/../../docs/commands.md';

    public static function render(): string
    {
        $kernel = new Kernel(sys_get_temp_dir());
        $commands = $kernel->application->all();
        ksort($commands);

        $sections = [];

        foreach ($commands as $name => $command) {
            if (in_array($name, ['help', 'list', 'completion', '_complete'], true) || $command->getName() !== $name) {
                continue;
            }

            $sections[] = self::section($command);
        }

        return implode("\n", $sections);
    }

    /**
     * The docs/commands.md content with the generated block replaced.
     */
    public static function document(): string
    {
        $document = (string) file_get_contents(self::DOCUMENT);
        $start = strpos($document, self::START);
        $end = strpos($document, self::END);

        if ($start === false || $end === false) {
            throw new \RuntimeException('docs/commands.md has no '.self::START.' / '.self::END.' block.');
        }

        return substr($document, 0, $start + strlen(self::START))."\n\n".self::render()."\n".substr($document, $end);
    }

    private static function section(Command $command): string
    {
        $out = '### `'.$command->getName()."`\n\n".$command->getDescription().'.';

        if ($command->getAliases() !== []) {
            $out .= ' Alias: '.implode(', ', array_map(fn (string $alias) => "`$alias`", $command->getAliases())).'.';
        }

        $out .= "\n\n```\n".trim($command->getSynopsis(true))."\n```\n";

        $rows = [];

        foreach ($command->getDefinition()->getArguments() as $argument) {
            $default = $argument->getDefault();

            $rows[] = sprintf(
                '| `%s` | %s | %s | %s |',
                $argument->getName(),
                $argument->isRequired() ? 'required' : 'optional',
                is_string($default) && $default !== '' ? "`$default`" : '',
                $argument->getDescription(),
            );
        }

        foreach ($command->getDefinition()->getOptions() as $option) {
            $suffix = $option->isValueRequired() ? '=' : ($option->isValueOptional() ? '[=]' : '');
            $default = $option->getDefault();

            $rows[] = sprintf(
                '| %s`--%s%s` | %s | %s | %s |',
                $option->getShortcut() !== null ? '`-'.$option->getShortcut().'`, ' : '',
                $option->getName(),
                $suffix,
                $option->acceptValue() ? ($option->isValueRequired() ? 'value' : 'optional value') : 'flag',
                is_string($default) && $default !== '' ? "`$default`" : '',
                str_replace('|', '\|', $option->getDescription()),
            );
        }

        if ($rows !== []) {
            $out .= "\n| Argument / option | Type | Default | Description |\n|---|---|---|---|\n".implode("\n", $rows)."\n";
        }

        return $out;
    }
}
