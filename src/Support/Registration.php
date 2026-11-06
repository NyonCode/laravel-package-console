<?php

declare(strict_types=1);

namespace NyonCode\LaravelPackageConsole\Support;

/**
 * Outcome of registering a generated resource in the package's service provider.
 */
final class Registration
{
    public const REGISTERED = 'registered';

    public const ALREADY_REGISTERED = 'already';

    public const MANUAL = 'manual';

    public const NO_PROVIDER = 'no-provider';

    private function __construct(
        public readonly string $status,
        public readonly string $message = '',
        public readonly ?string $warning = null,
    ) {}

    /**
     * @param  string|null  $warning  What could not be registered and has to be added by hand.
     */
    public static function registered(string $message, ?string $warning = null): self
    {
        return new self(self::REGISTERED, $message, $warning);
    }

    public static function already(): self
    {
        return new self(self::ALREADY_REGISTERED);
    }

    public static function manual(string $message): self
    {
        return new self(self::MANUAL, $message);
    }

    public static function noProvider(): self
    {
        return new self(
            self::NO_PROVIDER,
            'No service provider found in composer.json "extra.laravel.providers". Run "make:provider" to create one, then re-run this command with --force.'
        );
    }
}
