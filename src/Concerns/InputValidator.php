<?php

namespace NyonCode\LaravelPackageConsole\Concerns;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;

trait InputValidator
{
    /**
     * Validate the arguments and options that have rules in getValidationRules().
     * Option rules are keyed "--option"; an option that was not given is not validated.
     *
     * @return list<string> Error messages.
     */
    protected function validateArguments(InputInterface $input): array
    {
        $data = [];

        foreach (array_keys($this->getValidationRules()) as $key) {
            if (str_starts_with($key, '--')) {
                if ($input->hasOption(substr($key, 2))) {
                    $value = $input->getOption(substr($key, 2));
                    // VALUE_OPTIONAL options default to false when they are not given.
                    $data[$key] = $value === false ? null : $value;
                }
            } elseif ($input->hasArgument($key)) {
                $data[$key] = $input->getArgument($key);
            }
        }

        $rules = array_intersect_key($this->getValidationRules(), $data);

        if ($rules === []) {
            return [];
        }

        $violations = Validation::createValidator()->validate(
            $data,
            new Assert\Collection(fields: $rules, allowExtraFields: true, allowMissingFields: true)
        );

        $errors = [];

        foreach ($violations as $violation) {
            $errors[] = (string) $violation->getMessage();
        }

        return $errors;
    }

    /**
     * The first error of one value under the rules of an argument, or of an "--option".
     */
    protected function validationError(string $key, mixed $value): ?string
    {
        $rules = $this->getValidationRules()[$key] ?? [];

        foreach (Validation::createValidator()->validate($value, $rules) as $violation) {
            return (string) $violation->getMessage();
        }

        return null;
    }

    /**
     * Validation rules keyed by argument name, or by "--option" for options.
     *
     * @return array<string, list<Constraint>>
     */
    protected function getValidationRules(): array
    {
        return [];
    }
}
