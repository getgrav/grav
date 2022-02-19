<?php declare(strict_types = 1);

namespace PHPStan\Grav;

use Grav\Common\Utils;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use function count;

class UtilsPathinfoExtension implements DynamicStaticMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return Utils::class;
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'pathinfo';
    }

    public function getTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        Node\Expr\StaticCall $methodCall,
        Scope $scope
	): Type
	{
        $argsCount = count($methodCall->getArgs());
        if ($argsCount === 0) {
            return ParametersAcceptorSelector::selectSingle($methodReflection->getVariants())->getReturnType();
        }
        if ($argsCount === 1) {
            $stringType = new StringType();

            $builder = ConstantArrayTypeBuilder::createFromConstantArray(
                new ConstantArrayType(
                    [new ConstantStringType('dirname'), new ConstantStringType('basename'), new ConstantStringType('filename')],
                    [$stringType, $stringType, $stringType],
                ),
            );
            $builder->setOffsetValueType(new ConstantStringType('extension'), $stringType, true);

            return $builder->getArray();
        }

        return new StringType();
    }
}
