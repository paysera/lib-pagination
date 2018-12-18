<?php
declare(strict_types=1);

include __DIR__ . '/vendor/autoload.php';

return Paysera\PhpCsFixerConfig\Config\PayseraConventionsConfig::create()
    ->setDefaultFinder(['src', 'tests'], [])
    ->setRiskyRules([
        'Paysera/php_basic_comment_php_doc_necessity' => false,
        'Paysera/php_basic_code_style_splitting_in_several_lines' => false,
        'Paysera/php_basic_code_style_chained_method_calls' => false,
    ])
;
