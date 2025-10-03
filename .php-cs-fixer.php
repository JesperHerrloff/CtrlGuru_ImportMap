<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = (new Finder())
    ->in(__DIR__);

return (new Config())
    ->setRules([
        '@PhpCsFixer' => true,
        '@PHP82Migration' => true,
        'multiline_whitespace_before_semicolons' => [
            'strategy' => 'no_multi_line',
        ],
        'class_definition' => ['multi_line_extends_each_single_line' => true, 'single_line' => false],
    ])
    ->setFinder($finder);
