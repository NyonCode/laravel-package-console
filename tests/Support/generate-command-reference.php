<?php

/*
 * Rewrite the generated command reference in docs/commands.md:
 *
 *   composer docs:commands
 */

use NyonCode\LaravelPackageConsole\Tests\Support\CommandReference;

require __DIR__.'/../../vendor/autoload.php';

file_put_contents(CommandReference::DOCUMENT, CommandReference::document());

echo "docs/commands.md updated.\n";
