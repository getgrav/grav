<?php

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withSkip([
        __DIR__ . '/vendor',
    ])
    ->withPaths([
        __DIR__
    ])
    ->withPhpSets(php82: true)
    ->withPhpVersion(Rector\ValueObject\PhpVersion::PHP_84)
    ->withRules([
         Rector\Php84\Rector\Param\ExplicitNullableParamTypeRector::class,
    ]);
