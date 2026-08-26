<?php

// Lints the bundle's own code. `resources/stubs` holds verbatim templates for consuming
// projects (including a PhpCsFixer config), so it is intentionally excluded.
$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('vendor')
    ->exclude('tools')
    ->exclude('resources')
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'single_quote' => true,
        'yoda_style' => true,
    ])
    ->setFinder($finder)
;