#!/usr/bin/env php
<?php

declare(strict_types=1);

use NyonCode\LaravelPackageConsole\Kernel;

require_once __DIR__.'/vendor/autoload.php';

$kernel = new Kernel(__DIR__);
$kernel->run();
