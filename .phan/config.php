<?php
return [
    "target_php_version" => null,
    'pretend_newer_core_functions_exist' => true,
    'allow_missing_properties' => false,
    'null_casts_as_any_type' => false,
    'null_casts_as_array' => false,
    'array_casts_as_null' => false,
    'strict_method_checking' => true,
    'quick_mode' => false,
    'simplify_ast' => false,
    'directory_list' => [
        '.',
    ],
    "exclude_analysis_directory_list" => [
        'vendor/'
    ],
    'exclude_file_list' => [
        'system/src/Grav/Common/Errors/Resources/layout.html.php',
        'tests/_support/AcceptanceTester.php',
        'tests/_support/FunctionalTester.php',
        'tests/_support/UnitTester.php',
    ],
    'autoload_internal_extension_signatures' => [
        'memcached' => '.phan/internal_stubs/memcached.phan_php',
        'memcache' => '.phan/internal_stubs/memcache.phan_php',
        'redis' => '.phan/internal_stubs/Redis.phan_php',
    ],
    'plugins' => [
        'AlwaysReturnPlugin',
        'UnreachableCodePlugin',
        'DuplicateArrayKeyPlugin',
        'PregRegexCheckerPlugin',
        'PrintfCheckerPlugin',
    ],
    'suppress_issue_types' => [
        'PhanUnreferencedUseNormal',
        'PhanTypeObjectUnsetDeclaredProperty',
        'PhanTraitParentReference',
        'PhanTypeInvalidThrowsIsInterface',
        'PhanRequiredTraitNotAdded',
        'PhanDeprecatedFunction',  // Uncomment this to see all the deprecated calls
    ]
];
