<?php

/*
 * Czech translations of the interactive questions. The key is the English text as it is
 * written in the code; tests/Unit/LangTest.php fails when a question has no line here.
 */

return [

    // Prompts
    'Yes' => 'Ano',
    'No' => 'Ne',
    'None' => 'Nic',
    'Required.' => 'Povinný údaj.',
    'Optional.' => 'Nepovinné.',
    'Cancelled.' => 'Zrušeno.',
    'Space selects, Enter confirms.' => 'Mezerník vybírá, Enter potvrzuje.',
    'Separate several with a comma.' => 'Více voleb oddělte čárkou.',

    // The name of what is generated
    'What should the :type be named?' => 'Jak se má :type jmenovat?',
    'E.g. :example' => 'Např. :example',
    'cast' => 'cast',
    'channel' => 'kanál',
    'class' => 'třída',
    'console command' => 'konzolový příkaz',
    'component' => 'komponenta',
    'controller' => 'controller',
    'enum' => 'enum',
    'event' => 'událost',
    'exception' => 'výjimka',
    'facade' => 'fasáda',
    'factory' => 'factory',
    'interface' => 'rozhraní',
    'job' => 'job',
    'listener' => 'listener',
    'mailable' => 'mailable',
    'middleware' => 'middleware',
    'migration' => 'migrace',
    'model' => 'model',
    'notification' => 'notifikace',
    'observer' => 'observer',
    'policy' => 'policy',
    'provider' => 'provider',
    'request' => 'request',
    'resource' => 'resource',
    'rule' => 'validační pravidlo',
    'scope' => 'scope',
    'seeder' => 'seeder',
    'test' => 'test',
    'trait' => 'trait',
    'view' => 'šablona',
    'The name argument is required.' => 'Název je povinný.',
    'The name must be a valid class name, optionally nested with "/" (e.g. "Admin/UserService").' => 'Název musí být platný název třídy, případně zanořený pomocí "/" (např. "Admin/UserService").',
    'The name may only contain letters, numbers, "_", "-", "." and "/".' => 'Název smí obsahovat jen písmena, číslice, "_", "-", "." a "/".',

    'The model must be a class name that PHP does not reserve, e.g. "Post", "Admin/Post" or "\\App\\Models\\User".' => 'Model musí být název třídy, který PHP nerezervuje, např. "Post", "Admin/Post" nebo "\\App\\Models\\User".',
    'The event must be a class name that PHP does not reserve, e.g. "PostPublished" or "\\Illuminate\\Auth\\Events\\Login".' => 'Událost musí být název třídy, který PHP nerezervuje, např. "PostPublished" nebo "\\Illuminate\\Auth\\Events\\Login".',

    // make:model
    'Would you like any of the following?' => 'Chcete vytvořit i něco z následujícího?',
    'Migration' => 'Migrace',
    'Factory' => 'Factory',
    'Seeder' => 'Seeder',
    'Policy' => 'Policy',
    'Controller' => 'Controller',
    'Resource controller' => 'Resource controller',
    'API resource controller' => 'API resource controller',

    // make:controller
    'Which type of controller would you like?' => 'Jaký typ controlleru chcete?',
    'Empty' => 'Prázdný',
    'Resource' => 'Resource',
    'API resource' => 'API resource',
    'Invokable' => 'Invokable (jedna metoda)',
    'What model should this controller be for?' => 'Pro který model má controller být?',

    // make:observer, make:policy, make:listener
    'What model should this observer apply to?' => 'Ke kterému modelu observer patří?',
    'What model should this policy apply to?' => 'Ke kterému modelu policy patří?',
    'What event should be listened for?' => 'Na kterou událost má listener reagovat?',

    // make:test
    'Which type of test would you like?' => 'Jaký typ testu chcete?',
    'Feature' => 'Feature',
    'Unit' => 'Unit',
    'Which test framework should it use?' => 'Který testovací framework má použít?',

    // package:init
    'Composer package name' => 'Název balíčku pro Composer',
    'Description' => 'Popis',
    'Root namespace' => 'Kořenový namespace',
    'Author name' => 'Jméno autora',
    'Author email' => 'E-mail autora',
    'The package name must be lowercase "vendor/name", e.g. "acme/laravel-blog".' => 'Název balíčku musí být malými písmeny ve tvaru "vendor/name", např. "acme/laravel-blog".',
    'The namespace must be a valid PHP namespace, e.g. "Acme\Blog".' => 'Namespace musí být platný PHP namespace, např. "Acme\Blog".',
    'Which service provider should the package have?' => 'Jaký service provider má balíček mít?',
    'nyoncode/laravel-package-toolkit, with fluent ->hasX() calls' => 'nyoncode/laravel-package-toolkit, s řetězením ->hasX()',
    'A plain Laravel ServiceProvider' => 'Obyčejný Laravel ServiceProvider',
    'Which Laravel versions should the package support?' => 'Které verze Laravelu má balíček podporovat?',
    'Package' => 'Balíček',
    'Namespace' => 'Namespace',
    'Author' => 'Autor',
    'Service provider' => 'Service provider',
    'the one the package has' => 'ten, který balíček už má',
    'plain Laravel ServiceProvider' => 'obyčejný Laravel ServiceProvider',
    'Create the package with these settings?' => 'Vytvořit balíček s tímto nastavením?',
    'Complete the package with these settings?' => 'Doplnit balíček s tímto nastavením?',
    'Nothing was written.' => 'Nic nebylo zapsáno.',

    // Monorepo
    'Which package of the monorepo?' => 'Se kterým balíčkem monorepa pracovat?',
    'In which directory of the monorepo should the package be?' => 'Ve kterém adresáři monorepa má balíček být?',

    // package:doctor --fix
    'Which of these should be fixed?' => 'Které z těchto nálezů opravit?',

];
