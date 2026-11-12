<?php

declare(strict_types=1);

namespace NyonCode\LaravelPackageConsole\Support;

/**
 * One thing package:doctor found.
 */
final class Finding
{
    /**
     * The package does not work as it stands: a file nothing loads, a registration of a file
     * that is not there.
     */
    public const ERROR = 'error';

    /**
     * Probably not what the author meant, or something Anvil cannot decide.
     */
    public const WARNING = 'warning';

    /**
     * Something the doctor could not check.
     */
    public const INFO = 'info';

    /**
     * @param  string  $check  What was checked: "unregistered", "missing-file", "psr-4", ...
     * @param  string|null  $subject  The file or class the finding is about, relative to the package.
     * @param  string|null  $fix  What "package:doctor --fix" does about it; null when it does nothing.
     */
    public function __construct(
        public readonly string $severity,
        public readonly string $check,
        public readonly string $message,
        public readonly ?string $subject = null,
        public readonly ?string $fix = null,
    ) {}

    public function fixable(): bool
    {
        return $this->fix !== null;
    }

    /**
     * @return array{severity: string, check: string, subject: string|null, message: string, fix: string|null}
     */
    public function toArray(): array
    {
        return [
            'severity' => $this->severity,
            'check' => $this->check,
            'subject' => $this->subject,
            'message' => $this->message,
            'fix' => $this->fix,
        ];
    }
}
