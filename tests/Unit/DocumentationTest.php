<?php

use NyonCode\LaravelPackageConsole\Tests\Support\CommandReference;

it('documents every command, argument and option exactly as defined', function () {
    expect(file_get_contents(CommandReference::DOCUMENT))->toBe(
        CommandReference::document(),
        'docs/commands.md is out of date. Run "composer docs:commands".'
    );
});

it('links only to documentation pages and anchors that exist', function () {
    $docs = dirname(__DIR__, 2).'/docs';

    foreach (glob("$docs/*.md") as $page) {
        preg_match_all('/\]\(([a-z0-9-]+\.md)(?:#([a-z0-9-]+))?\)/', (string) file_get_contents($page), $links, PREG_SET_ORDER);

        foreach ($links as $link) {
            expect("$docs/{$link[1]}")->toBeFile(basename($page)." links to a missing page {$link[1]}");

            if (isset($link[2])) {
                expect(in_array($link[2], anchorsOf("$docs/{$link[1]}"), true))
                    ->toBeTrue(basename($page)." links to a missing anchor {$link[1]}#{$link[2]}");
            }
        }
    }
});

it('has front matter with a title and a description on every page', function () {
    foreach (glob(dirname(__DIR__, 2).'/docs/*.md') as $page) {
        expect((string) file_get_contents($page))->toMatch('/\A---\ntitle: .+\ndescription: .+\n---\n/', basename($page));
    }
});

/**
 * GitHub-style anchors of the headings in a Markdown file.
 *
 * @return list<string>
 */
function anchorsOf(string $file): array
{
    $content = (string) preg_replace('/```.*?```/s', '', (string) file_get_contents($file));
    preg_match_all('/^#{1,6} (.+)$/m', $content, $headings);

    return array_map(function (string $heading) {
        $anchor = strtolower(trim($heading));
        $anchor = (string) preg_replace('/[^a-z0-9 _-]/', '', $anchor);

        return str_replace(' ', '-', $anchor);
    }, $headings[1]);
}
