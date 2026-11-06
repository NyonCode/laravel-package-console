<?php

declare(strict_types=1);

namespace NyonCode\LaravelPackageConsole\Support;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeFinder;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use RuntimeException;

/**
 * Small, format-preserving editor for PHP class files.
 *
 * The file is parsed with nikic/php-parser only to find exact positions; every change is
 * spliced into the original text, so the rest of the file (comments, blank lines, alignment)
 * stays exactly as the author wrote it. Each operation re-parses the current code.
 */
final class PhpFileEditor
{
    private const INDENT = '    ';

    private static ?Parser $parser = null;

    private NodeFinder $finder;

    /**
     * The method that holds the fluent package description: configure() for
     * laravel-package-toolkit, configurePackage() for spatie/laravel-package-tools.
     */
    private string $configurationMethod = 'configure';

    public function __construct(private string $code)
    {
        $this->finder = new NodeFinder;
    }

    public static function fromFile(string $path): self
    {
        $code = @file_get_contents($path);

        if ($code === false) {
            throw new RuntimeException("Unable to read [$path].");
        }

        return new self($code);
    }

    public function code(): string
    {
        return $this->code;
    }

    public function contains(string $needle): bool
    {
        return str_contains($this->code, $needle);
    }

    public function namespace(): ?string
    {
        $namespace = $this->finder->findFirstInstanceOf($this->parse(), Namespace_::class);

        return $namespace?->name?->toString();
    }

    /**
     * Import a class (`use Foo\Bar;`) unless it is already imported or lives in the file's namespace.
     */
    public function addUse(string $class): self
    {
        $class = trim($class, '\\');

        if (! str_contains($class, '\\')) {
            return $this;
        }

        $stmts = $this->parse();
        $namespace = $this->finder->findFirstInstanceOf($stmts, Namespace_::class);

        if ($namespace?->name?->toString() === substr($class, 0, (int) strrpos($class, '\\'))) {
            return $this;
        }

        /** @var list<Use_> $uses */
        $uses = array_values(array_filter(
            $namespace !== null ? $namespace->stmts : $stmts,
            fn (Node $node) => $node instanceof Use_ && $node->type === Use_::TYPE_NORMAL
        ));

        foreach ($uses as $use) {
            foreach ($use->uses as $item) {
                if (strcasecmp($item->name->toString(), $class) === 0) {
                    return $this;
                }
            }
        }

        if ($uses !== []) {
            // Keep the import block alphabetically sorted.
            foreach ($uses as $use) {
                if (strcasecmp($use->uses[0]->name->toString(), $class) > 0) {
                    return $this->splice($use->getStartFilePos(), "use $class;\n");
                }
            }

            return $this->splice(end($uses)->getEndFilePos() + 1, "\nuse $class;");
        }

        if ($namespace?->name !== null) {
            $semicolon = strpos($this->code, ';', $namespace->name->getEndFilePos());

            return $this->splice($semicolon + 1, "\n\nuse $class;");
        }

        return $this->splice(strpos($this->code, "\n") + 1, "\nuse $class;\n");
    }

    public function hasMethod(string $name): bool
    {
        return $this->findMethod($this->parse(), $name) !== null;
    }

    /**
     * Add a method (given as code without indentation) at the end of the class.
     */
    public function addMethod(string $code): self
    {
        $class = $this->findClass($this->parse());
        $closing = $class->getEndFilePos();
        $indent = $this->lineIndent($closing).self::INDENT;
        $lineStart = $this->lineStart($closing);

        $prefix = trim(substr($this->code, $class->getStartFilePos(), $closing - $class->getStartFilePos()));

        return $this->splice(
            $lineStart,
            (str_ends_with($prefix, '{') ? '' : "\n").$this->indentCode($code, $indent)."\n"
        );
    }

    /**
     * Append statements (given as code without indentation) at the end of a method body.
     * A lone `//` placeholder in an otherwise empty body is replaced.
     */
    public function appendToMethod(string $method, string $code): self
    {
        $node = $this->findMethod($this->parse(), $method)
            ?? throw new RuntimeException("Method [$method()] not found.");

        $closing = $node->getEndFilePos();
        $methodIndent = $this->lineIndent($closing);
        $bodyIndent = $methodIndent.self::INDENT;

        // A body holding only comments is parsed as a single Nop statement.
        $statements = array_filter($node->stmts ?? [], fn (Node $stmt) => ! $stmt instanceof Node\Stmt\Nop);

        if ($statements === []) {
            $opening = strrpos(substr($this->code, 0, $closing), '{');
            $inner = trim(substr($this->code, $opening + 1, $closing - $opening - 1));

            if ($inner === '' || $inner === '//') {
                return $this->splice(
                    $opening + 1,
                    "\n".$this->indentCode($code, $bodyIndent)."\n".$methodIndent,
                    $closing - $opening - 1
                );
            }
        }

        return $this->splice($this->lineStart($closing), $this->indentCode($code, $bodyIndent)."\n");
    }

    public function hasPackagerConfiguration(): bool
    {
        return $this->findMethod($this->parse(), 'configure') !== null;
    }

    /**
     * How the provider describes the package: "toolkit" (configure(Packager)), "spatie"
     * (configurePackage(Package)) or "plain" (register()/boot()).
     */
    public function mode(): string
    {
        $stmts = $this->parse();

        return match (true) {
            $this->findMethod($stmts, 'configure') !== null => 'toolkit',
            $this->findMethod($stmts, 'configurePackage') !== null => 'spatie',
            default => 'plain',
        };
    }

    /**
     * Read the Packager chain from another method, e.g. configurePackage().
     */
    public function usingConfigurationMethod(string $method): self
    {
        $this->configurationMethod = $method;

        return $this;
    }

    /**
     * The fully qualified class the provider extends, resolved through its imports.
     */
    public function parentClass(): ?string
    {
        $stmts = $this->parse();
        $extends = $this->findClass($stmts)->extends;

        if ($extends === null) {
            return null;
        }

        if ($extends->isFullyQualified()) {
            return $extends->toString();
        }

        $first = $extends->getFirst();
        $namespace = $this->finder->findFirstInstanceOf($stmts, Namespace_::class);

        foreach ($this->finder->findInstanceOf($stmts, Use_::class) as $use) {
            if ($use->type !== Use_::TYPE_NORMAL) {
                continue;
            }

            foreach ($use->uses as $item) {
                if (strcasecmp($item->getAlias()->toString(), $first) === 0) {
                    $rest = $extends->slice(1);

                    return $item->name->toString().($rest !== null ? '\\'.$rest->toString() : '');
                }
            }
        }

        $prefix = $namespace?->name?->toString();

        return ($prefix !== null ? $prefix.'\\' : '').$extends->toString();
    }

    /**
     * The value of a Packager call's first argument when it is a string literal,
     * e.g. "Blog" for ->name('Blog').
     */
    public function packagerStringArgument(string $method): ?string
    {
        foreach ($this->allPackagerCalls($this->parse()) as $call) {
            if ($call->name instanceof Node\Identifier && $call->name->toString() === $method) {
                $value = $call->getArgs()[0]->value ?? null;

                return $value instanceof Node\Scalar\String_ ? $value->value : null;
            }
        }

        return null;
    }

    /**
     * Replace the arguments of the first Packager call named $method.
     *
     * @return bool False when there is no such call.
     */
    public function replacePackagerCallArguments(string $method, string $arguments): bool
    {
        foreach ($this->allPackagerCalls($this->parse()) as $call) {
            if (! $call->name instanceof Node\Identifier || $call->name->toString() !== $method) {
                continue;
            }

            $args = $call->getArgs();

            if ($args !== []) {
                $start = $args[0]->getStartFilePos();
                $this->splice($start, $arguments, end($args)->getEndFilePos() - $start + 1);
            } else {
                $open = strpos($this->code, '(', $call->name->getEndFilePos() + 1);
                $close = $call->getEndFilePos();
                $this->splice($open + 1, $arguments, $close - $open - 1);
            }

            return true;
        }

        return false;
    }

    /**
     * Calls made on the Packager in configure(), in order, across every statement
     * that starts with the Packager variable.
     *
     * @return list<array{name: string, args: string}>
     */
    public function packagerCalls(): array
    {
        return array_map(fn (MethodCall $call) => [
            'name' => $call->name instanceof Node\Identifier ? $call->name->toString() : '',
            'args' => $this->argsSource($call),
        ], $this->allPackagerCalls($this->parse()));
    }

    /**
     * Append `->$call` to the Packager chain in configure(), or start a chain.
     */
    public function appendPackagerCall(string $call): self
    {
        $stmts = $this->parse();
        $configure = $this->findMethod($stmts, $this->configurationMethod)
            ?? throw new RuntimeException("Method [{$this->configurationMethod}()] not found.");
        $chain = $this->packagerChain($stmts);

        if ($chain === null) {
            return $this->appendToMethod($this->configurationMethod, '$'.$this->packagerVariable($configure)."\n".self::INDENT."->$call;");
        }

        $outer = $chain->expr;
        assert($outer instanceof MethodCall);

        $arrow = strrpos(substr($this->code, 0, $outer->name->getStartFilePos()), '->');
        $beforeArrow = substr($this->code, $this->lineStart($arrow), $arrow - $this->lineStart($arrow));
        $indent = trim($beforeArrow) === ''
            ? $beforeArrow
            : $this->lineIndent($chain->getStartFilePos()).self::INDENT;

        return $this->splice($outer->getEndFilePos() + 1, "\n$indent->$call");
    }

    /**
     * Add an item to the array passed as the first argument of a Packager call.
     *
     * @return bool False when the call does not exist or its first argument is not an array.
     */
    public function addItemToPackagerCallArray(string $method, string $item): bool
    {
        foreach ($this->allPackagerCalls($this->parse()) as $call) {
            if ($call->name instanceof Node\Identifier && $call->name->toString() === $method) {
                $array = $call->getArgs()[0]->value ?? null;

                if ($array instanceof Array_) {
                    $this->addArrayItem($array, $item);

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Add an item to the array passed to a `$this->$call([...])` call inside a method.
     *
     * @return bool False when no such call exists.
     */
    public function addItemToThisCallArray(string $method, string $call, string $item): bool
    {
        $node = $this->findMethod($this->parse(), $method);

        if ($node === null) {
            return false;
        }

        /** @var MethodCall|null $found */
        $found = $this->finder->findFirst((array) $node->stmts, fn (Node $n) => $n instanceof MethodCall
            && $n->var instanceof Variable && $n->var->name === 'this'
            && $n->name instanceof Node\Identifier && $n->name->toString() === $call
            && ($n->getArgs()[0]->value ?? null) instanceof Array_);

        if ($found === null) {
            return false;
        }

        $this->addArrayItem($found->getArgs()[0]->value, $item);

        return true;
    }

    private function addArrayItem(Array_ $array, string $item): void
    {
        $items = array_values(array_filter($array->items));

        if ($items === []) {
            $this->splice($array->getStartFilePos() + 1, $item);

            return;
        }

        $last = end($items);
        $lastEnd = $last->getEndFilePos() + 1;
        $multiline = str_contains(
            substr($this->code, $array->getStartFilePos(), $items[0]->getStartFilePos() - $array->getStartFilePos()),
            "\n"
        );

        if (! $multiline) {
            $this->splice($lastEnd, ", $item");

            return;
        }

        $indent = $this->lineIndent($last->getStartFilePos());
        $after = substr($this->code, $lastEnd, $array->getEndFilePos() - $lastEnd);

        if (str_starts_with(ltrim($after), ',')) {
            $this->splice($lastEnd + strpos($after, ',') + 1, "\n$indent$item,");
        } else {
            $this->splice($lastEnd, ",\n$indent$item");
        }
    }

    /**
     * @param  Node[]  $stmts
     */
    /**
     * The last statement in configure() that is a method chain on the Packager variable;
     * new calls are appended to it.
     */
    private function packagerChain(array $stmts): ?Expression
    {
        $chains = $this->packagerChains($stmts);

        return $chains === [] ? null : $chains[array_key_last($chains)];
    }

    /**
     * Every statement in configure() that is a method chain on the Packager variable.
     *
     * @return list<Expression>
     */
    private function packagerChains(array $stmts): array
    {
        $configure = $this->findMethod($stmts, $this->configurationMethod);

        if ($configure === null) {
            return [];
        }

        $variable = $this->packagerVariable($configure);
        $chains = [];

        foreach ((array) $configure->stmts as $stmt) {
            if ($stmt instanceof Expression && $stmt->expr instanceof MethodCall) {
                $root = $stmt->expr;

                while ($root instanceof MethodCall) {
                    $root = $root->var;
                }

                if ($root instanceof Variable && $root->name === $variable) {
                    $chains[] = $stmt;
                }
            }
        }

        return $chains;
    }

    /**
     * @return list<MethodCall>
     */
    private function allPackagerCalls(array $stmts): array
    {
        $calls = [];

        foreach ($this->packagerChains($stmts) as $chain) {
            array_push($calls, ...$this->unwrapChain($chain->expr));
        }

        return $calls;
    }

    private function packagerVariable(ClassMethod $configure): string
    {
        $param = $configure->params[0]->var ?? null;

        return $param instanceof Variable && is_string($param->name)
            ? $param->name
            : ($this->configurationMethod === 'configurePackage' ? 'package' : 'packager');
    }

    /**
     * @return list<MethodCall> Innermost (first) call first.
     */
    private function unwrapChain(Node $expr): array
    {
        $calls = [];

        while ($expr instanceof MethodCall) {
            array_unshift($calls, $expr);
            $expr = $expr->var;
        }

        return $calls;
    }

    private function argsSource(MethodCall $call): string
    {
        $args = $call->getArgs();

        if ($args === []) {
            return '';
        }

        $start = $args[0]->getStartFilePos();

        return substr($this->code, $start, end($args)->getEndFilePos() - $start + 1);
    }

    /**
     * @param  Node[]  $stmts
     */
    private function findClass(array $stmts): Class_
    {
        $class = $this->finder->findFirstInstanceOf($stmts, Class_::class);

        if (! $class instanceof Class_) {
            throw new RuntimeException('No class found in the file.');
        }

        return $class;
    }

    /**
     * @param  Node[]  $stmts
     */
    private function findMethod(array $stmts, string $name): ?ClassMethod
    {
        return $this->findClass($stmts)->getMethod($name);
    }

    /**
     * @return Node[]
     */
    private function parse(): array
    {
        self::$parser ??= (new ParserFactory)->createForNewestSupportedVersion();

        return self::$parser->parse($this->code) ?? [];
    }

    private function splice(int $position, string $insert, int $length = 0): self
    {
        $this->code = substr($this->code, 0, $position).$insert.substr($this->code, $position + $length);

        return $this;
    }

    private function lineStart(int $position): int
    {
        $newline = strrpos(substr($this->code, 0, $position), "\n");

        return $newline === false ? 0 : $newline + 1;
    }

    private function lineIndent(int $position): string
    {
        $start = $this->lineStart($position);
        preg_match('/^[ \t]*/', substr($this->code, $start), $match);

        return $match[0];
    }

    private function indentCode(string $code, string $indent): string
    {
        return implode("\n", array_map(
            fn (string $line) => $line === '' ? '' : $indent.$line,
            explode("\n", $code)
        ));
    }
}
