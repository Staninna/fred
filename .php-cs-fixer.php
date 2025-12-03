<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in(__DIR__ . '/bin')
    ->in(__DIR__ . '/config')
    ->in(__DIR__ . '/migrations')
    ->in(__DIR__ . '/public')
    ->in(__DIR__ . '/src')
    ->name('*.php');

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'declare_strict_types' => true,
        'native_function_invocation' => [
            'scope' => 'namespaced',
            'include' => ['@compiler_optimized'],
        ],
        'no_unused_imports' => true,
        'single_quote' => true,
    ])
    ->setFinder($finder);
