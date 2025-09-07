<?php

namespace NyonCode\LaravelPackageConsole\Concerns;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;

trait InputValidator
{
    protected function validateArguments(InputInterface $input): array
    {
        $validator = Validation::createValidator();
        $constraints = new Assert\Collection($this->getValidationRules());
        $data = [];

        foreach ($this->getArguments() as [$name]) {
            $data[$name] = $input->getArgument($name);
        }

        if (empty($data)) {
            return [];
        }

        $violations = $validator->validate($data, $constraints);
        $errors = [];

        foreach ($violations as $violation) {
            $errors[] = $violation->getMessage();
        }

        return $errors;
    }

    protected function getValidationRules(): array
    {
        return [
            'name' => [
                new Assert\NotBlank(message: 'The name argument is required.'),
                //                new Assert\Regex([
                //                    'pattern' => '/^[A-Za-z][A-Za-z0-9_]+$/',
                //                    'message' => 'The name must start with a letter and contain only letters,
                //                                       numbers, and underscores.',
                //                ]),
            ],
        ];
    }
}
