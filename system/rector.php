<?php

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\CodeQualitySetList;
use Rector\Set\ValueObject\DeadCodeSetList;
use Rector\Set\ValueObject\TypeDeclarationSetList;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withSkip([
        __DIR__ . '/vendor',
    ])
    ->withPaths([
        __DIR__,
    ])
    // 8.x language upgrades — stack these once and move on
    ->withSets([
        LevelSetList::UP_TO_PHP_84,                      // includes 8.2, 8.3, 8.4 language rules in one go  [oai_citation:2‡DEV Community](https://dev.to/robertobutti/why-you-should-upgrade-to-php-84-or-at-least-php-8x-1ab0?utm_source=chatgpt.com)
        CodeQualitySetList::UP_TO_CODE_QUALITY,          // safe, general polish (early returns, etc.)  [oai_citation:3‡Rector](https://getrector.com/documentation/levels?utm_source=chatgpt.com)
        DeadCodeSetList::DEAD_CODE,                      // remove unused code
        TypeDeclarationSetList::UP_TO_TYPE_DECLARATION,  // add missing types where inferable
    ])

    // (Optional) Pin a few “important for 8.4” rules explicitly
    ->withRules([
        Rector\Php84\Rector\Param\ExplicitNullableParamTypeRector::class,   // `?T` requirement for nullable params in 8.4  [oai_citation:4‡DEV Community](https://dev.to/gromnan/fix-php-84-deprecation-implicitly-marking-parameter-as-nullable-is-deprecated-the-explicit-nullable-type-must-be-used-instead-5gp3?utm_source=chatgpt.com)
        Rector\Php84\Rector\FuncCall\AddEscapeArgumentRector::class,        // add `escape` to CSV funcs in 8.4  [oai_citation:5‡Rector](https://getrector.com/find-rule?rectorSet=php-php-84&utm_source=chatgpt.com)
        Rector\Php84\Rector\MethodCall\NewMethodCallWithoutParenthesesRector::class, // 8.4 new() parentheses tweak  [oai_citation:6‡Rector](https://getrector.com/find-rule?rectorSet=php-php-84&utm_source=chatgpt.com)
    ]);
