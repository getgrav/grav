<?php

namespace PHPStan\Toolbox;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Extension to handle UniformResourceLocator return types.
 */
class UniformResourceLocatorExtension implements DynamicMethodReturnTypeExtension
{
    /**
     * @return string
     */
    public function getClass(): string
    {
        return UniformResourceLocator::class;
    }

    /**
     * @param MethodReflection $methodReflection
     * @return bool
     */
    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'findResource';
    }

    /**
     * @param MethodReflection $methodReflection
     * @param MethodCall $methodCall
     * @param Scope $scope
     * @return Type
     */
    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type
    {
        $first = $methodCall->getArgs()[2] ?? false;
        if ($first) {
            return new StringType();
        }

        return ParametersAcceptorSelector::selectSingle($methodReflection->getVariants())->getReturnType();
    }
}
