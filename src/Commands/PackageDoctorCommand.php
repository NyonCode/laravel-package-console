<?php

namespace NyonCode\LaravelPackageConsole\Commands;

use NyonCode\LaravelPackageConsole\Kernel;
use NyonCode\LaravelPackageConsole\Support\Doctor;
use NyonCode\LaravelPackageConsole\Support\Finding;
use NyonCode\LaravelPackageConsole\Support\Monorepo;
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

    protected function worksOnPackage(): bool
    {
        return ! $this->option('all');
    }

    public function handle(): int
    {
        if (! $this->option('all')) {
            $package = $this->package();

            if (! $package->exists()) {
                $this->warning('No composer.json in '.$package->path().'. Run "package:init" to create the package.');

                return self::FAILURE;
            }

            [$failed, $report] = $this->examine();

            if ($this->option('json')) {
                $this->output()->writeln($this->json($report));
            }

            return $failed ? self::FAILURE : self::SUCCESS;
        }

        $packages = Kernel::workspace()->packages();

        if ($packages === []) {
            $this->warning('--all checks the packages of a monorepo, and there are none: see "Monorepos" in the docs for how Anvil finds them.');

            return self::FAILURE;
        }

        $monorepo = $this->examineMonorepo();
        $failed = $this->option('strict') && $monorepo['warnings'] > 0;
        $reports = [];

        foreach ($packages as $relative => $path) {
            $package = Kernel::usePackage($path);

            if (! $this->option('json')) {
                $this->output()->writeln("\n<options=bold>$relative</> <fg=gray>({$package->name()})</>");
            }

            [$packageFailed, $reports[$relative]] = $this->examine();
            $failed = $failed || $packageFailed;
        }

        if ($this->option('json')) {
            $this->output()->writeln($this->json([
                'errors' => array_sum(array_column($reports, 'errors')),
                'warnings' => array_sum(array_column($reports, 'warnings')) + $monorepo['warnings'],
                'monorepo' => $monorepo,
                'packages' => $reports,
            ]));
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * What concerns the monorepo as a whole: dependencies its packages require with different
     * constraints (as monorepo-builder's "validate" checks). Only INFO: packages often support
     * different Laravel versions on purpose. Printed unless --json.
     *
     * @return array{root: string, layouts: list<string>, warnings: int, findings: list<array<string, mixed>>}
     */
    private function examineMonorepo(): array
    {
        $monorepo = Kernel::workspace()->monorepo();
        $findings = [];

        foreach ($monorepo?->conflictingConstraints() ?? [] as $dependency => $constraints) {
            $findings[] = new Finding(Finding::INFO, 'constraints', 'is required with different constraints: '.implode(', ', array_map(
                fn (string $package, string $constraint) => "$constraint in $package",
                array_keys($constraints),
                $constraints
            )).'.', $dependency);
        }

        $layouts = $monorepo?->layouts() ?? [];

        if (! $this->option('json')) {
            $this->output()->writeln('<options=bold>Monorepo</> <fg=gray>('.implode(', ', array_map(fn (string $layout) => Monorepo::LABELS[$layout], $layouts ?: [Monorepo::PATH])).')</>');

            foreach ($findings as $finding) {
                $this->output()->writeln('  '.self::LABELS[$finding->severity].' '.$this->describe($finding));
            }

            if ($findings === []) {
                $this->output()->writeln('  <fg=green;options=bold>DONE</> The packages require their dependencies with the same constraints.');
            }
        }

        return [
            'root' => (string) $monorepo?->root(),
            'layouts' => $layouts,
            'warnings' => $this->count($findings, Finding::WARNING),
            'findings' => array_map(fn (Finding $finding) => $finding->toArray(), $findings),
        ];
    }

    /**
     * Examine (and with --fix fix) the current package, and print the findings unless --json.
     *
     * @return array{0: bool, 1: array{errors: int, warnings: int, fixed: list<array<string, mixed>>, findings: list<array<string, mixed>>}} Whether it failed, and the report.
     */
    private function examine(): array
    {
        $doctor = new Doctor($this->package(), Kernel::workspace()->monorepo());
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
        $report = [
            'errors' => $errors,
            'warnings' => $warnings,
            'fixed' => array_map(fn (Finding $finding) => $finding->toArray(), $fixed),
            'findings' => array_map(fn (Finding $finding) => $finding->toArray(), $findings),
        ];

        if ($this->option('json')) {
            return [$failed, $report];
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

        return [$failed, $report];
    }

    private function json(array $report): string
    {
        return (string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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
            ? ' Run <options=bold>package:doctor '.($this->option('all') ? '--all ' : '')."--fix</> to fix $fixable of them."
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
            ['all', null, InputOption::VALUE_NONE, 'Check every package of the monorepo'],
        ];
    }
}
