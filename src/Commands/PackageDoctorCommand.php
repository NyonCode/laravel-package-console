<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use NyonCode\LaravelPackageConsole\Support\Doctor;
use NyonCode\LaravelPackageConsole\Support\Finding;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Checks that the service provider registers what the package has, and nothing it does not
 * have. Exits with 1 when there are errors (with --strict: warnings too), so it can run in CI.
 */
#[AsCommand(name: 'package:doctor', description: 'Check that the service provider and composer.json match the files of the package')]
final class PackageDoctorCommand extends BaseCommand
{
    private const LABELS = [
        Finding::ERROR => '<fg=red;options=bold>ERROR</>',
        Finding::WARNING => '<fg=yellow;options=bold>WARN</> ',
        Finding::INFO => '<fg=blue;options=bold>INFO</> ',
    ];

    public function handle(): int
    {
        $package = $this->package();

        if (! $package->exists()) {
            $this->warning('No composer.json in '.$package->path().'. Run "package:init" to create the package.');

            return self::FAILURE;
        }

        $doctor = new Doctor($package);
        $findings = $doctor->examine();
        $fixed = [];

        $declined = [];

        // Listing the provider in composer.json makes its registrations checkable, hence the rounds.
        for ($round = 0; $this->option('fix') && $round < 3; $round++) {
            $offered = array_values(array_filter(
                $this->fixable($findings),
                fn (Finding $finding) => ! in_array($finding->subject, $declined, true)
            ));

            if ($offered === []) {
                break;
            }

            $chosen = $this->choose($offered);
            $declined = [...$declined, ...array_diff(array_map(fn (Finding $finding) => $finding->subject, $offered), $chosen ?? [])];

            if ($chosen === []) {
                break;
            }

            array_push($fixed, ...$doctor->fix($chosen));
            $findings = $doctor->examine();
        }

        $errors = $this->count($findings, Finding::ERROR);
        $warnings = $this->count($findings, Finding::WARNING);
        $failed = $errors > 0 || ($this->option('strict') && $warnings > 0);

        if ($this->option('json')) {
            $this->output()->writeln((string) json_encode([
                'errors' => $errors,
                'warnings' => $warnings,
                'fixed' => array_map(fn (Finding $finding) => $finding->toArray(), $fixed),
                'findings' => array_map(fn (Finding $finding) => $finding->toArray(), $findings),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $failed ? self::FAILURE : self::SUCCESS;
        }

        foreach ($fixed as $finding) {
            $this->output()->writeln("  <fg=green;options=bold>FIXED</> <options=bold>{$finding->subject}</>: {$finding->fix}.");
        }

        foreach ([Finding::ERROR, Finding::WARNING, Finding::INFO] as $severity) {
            foreach ($findings as $finding) {
                if ($finding->severity === $severity) {
                    $this->output()->writeln('  '.self::LABELS[$severity].' '.$this->describe($finding));
                }
            }
        }

        if ($fixed !== [] || $findings !== []) {
            $this->output()->writeln('');
        }

        $this->output()->writeln('  '.$this->summary($findings, $errors, $warnings, count($fixed)));

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * In a terminal, --fix asks which of the findings to fix; otherwise it fixes them all.
     *
     * @param  list<Finding>  $offered
     * @return list<string>|null The subjects to fix; null for all of them.
     */
    private function choose(array $offered): ?array
    {
        if (! $this->prompts()->interactive() || $this->option('json')) {
            return null;
        }

        $options = [];

        foreach ($offered as $finding) {
            $options[(string) $finding->subject] = $finding->subject.': '.$finding->fix;
        }

        return array_map('strval', $this->prompts()->multiselect(
            $this->trans('Which of these should be fixed?'),
            $options,
            array_keys($options),
        ));
    }

    /**
     * @param  list<Finding>  $findings
     */
    private function summary(array $findings, int $errors, int $warnings, int $fixed): string
    {
        $parts = array_filter([
            $fixed > 0 ? "$fixed fixed" : null,
            $errors > 0 ? $errors.' '.($errors === 1 ? 'error' : 'errors') : null,
            $warnings > 0 ? $warnings.' '.($warnings === 1 ? 'warning' : 'warnings') : null,
        ]);

        if ($errors === 0 && $warnings === 0) {
            return '<fg=green;options=bold>DONE</> '.($fixed > 0 ? "$fixed fixed. " : '').'The service provider and composer.json match the package.';
        }

        $fixable = count($this->fixable($findings));

        return ucfirst(implode(', ', $parts)).'.'.($fixable > 0
            ? " Run <options=bold>package:doctor --fix</> to fix $fixable of them."
            : '');
    }

    private function describe(Finding $finding): string
    {
        return ($finding->subject !== null ? "<options=bold>{$finding->subject}</> " : '').$finding->message;
    }

    /**
     * @param  list<Finding>  $findings
     * @return list<Finding>
     */
    private function fixable(array $findings): array
    {
        return array_values(array_filter($findings, fn (Finding $finding) => $finding->fixable()));
    }

    /**
     * @param  list<Finding>  $findings
     */
    private function count(array $findings, string $severity): int
    {
        return count(array_filter($findings, fn (Finding $finding) => $finding->severity === $severity));
    }

    protected function getOptions(): array
    {
        return [
            ['fix', null, InputOption::VALUE_NONE, 'Register what is missing in the service provider and composer.json'],
            ['strict', null, InputOption::VALUE_NONE, 'Exit with 1 on warnings as well as on errors'],
            ['json', null, InputOption::VALUE_NONE, 'Print the findings as JSON'],
        ];
    }
}
