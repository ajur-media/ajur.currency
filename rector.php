<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\TypeDeclaration\Rector\ClassMethod\AddReturnTypeDeclarationRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictNativeCallRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/sources',
    ])
    // uncomment to reach your current PHP version
    ->withPhpSets()
    ->withTypeCoverageLevel(0)
    ->withRules([
        AddReturnTypeDeclarationRector::class,
        ReturnTypeFromStrictNativeCallRector::class,
    ])
    ->withSets([
        LevelSetList::UP_TO_PHP_82,
    ])
    ->withSkip([
        // https://github.com/rectorphp/rector/blob/main/docs/rector_rules_overview.md#curlytosquarebracketarraystringrector
        ClosureToArrowFunctionRector::class,
    ])

    ;
