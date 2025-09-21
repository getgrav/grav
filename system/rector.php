<?php

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
    ->withSkip([
        __DIR__ . '/vendor',
        // 'Rector\\CodeQuality\\Rector\\If_\\SimplifyIfElseReturnRector',
        // 'Rector\\CodeQuality\\Rector\\If_\\SimplifyIfReturnBoolRector',
        // 'Rector\\CodeQuality\\Rector\\FuncCall\\RemoveDuplicatedArrayKeyRector',
        // 'Rector\\TypeDeclaration\\Rector\\ClassMethod\\AddArrayParamDocTypeRector',
    ])
    ->withPaths([
        __DIR__
//        __DIR__ . '/user/plugins/admin/classes/plugin',
//        __DIR__ . '/user/plugins/flex-objects/classes',
//        __DIR__ . '/user/plugins/login-oauth2/classes',
//        __DIR__ . '/user/plugins/login-oauth2-extras/classes',
//        __DIR__ . '/user/plugins/page-toc/classes',
//        __DIR__ . '/user/plugins/form/classes',
//        __DIR__ . '/vendor/rockettheme/toolbox',
//        __DIR__ . '/user/plugins/login/classes',
//        __DIR__ . '/user/plugins/taxonomylist/classes',
//        __DIR__ . '/user/plugins/shortcode-core',
//        __DIR__ . '/user/plugins/image-captions',
    ])
    ->withPhpSets(php82: true)
    ->withPhpVersion(Rector\ValueObject\PhpVersion::PHP_84)
    ->withRules([
         Rector\Php84\Rector\Param\ExplicitNullableParamTypeRector::class,
    ]);
