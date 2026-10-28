<?php

namespace Pkly\QueryHelper\PHPStan;

use Doctrine\Common\Collections\Collection;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use Pkly\QueryHelper\Model;
use Pkly\QueryHelper\QueryHelper;

/**
 * Narrows QueryHelper::modelRows() / modelRow() so that a literal `alias => Model::of(X::class, ...)`
 * map yields `array{alias: X, ...}` rows instead of `array<string, object>`.
 */
final class ModelRowsReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    #[\Override]
    public function getClass(): string
    {
        return QueryHelper::class;
    }

    #[\Override]
    public function isMethodSupported(
        MethodReflection $methodReflection
    ): bool {
        return in_array($methodReflection->getName(), ['modelRows', 'modelRow'], true);
    }

    #[\Override]
    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope
    ): Type|null {
        $args = $methodCall->getArgs();

        if (!isset($args[0])) {
            return null;
        }

        if (null === $shape = $this->resolveRowShape($scope->getType($args[0]->value))) {
            return null;
        }

        return 'modelRow' === $methodReflection->getName()
            ? TypeCombinator::addNull($shape)
            : new GenericObjectType(Collection::class, [new IntegerType(), $shape]);
    }

    private function resolveRowShape(
        Type $models
    ): Type|null {
        $constantArrays = $models->getConstantArrays();

        if (1 !== count($constantArrays)) {
            return null;
        }

        $constantArray = $constantArrays[0];
        $builder = ConstantArrayTypeBuilder::createEmpty();

        foreach ($constantArray->getKeyTypes() as $index => $keyType) {
            $modelType = $constantArray->getValueTypes()[$index];

            if (!$modelType->isObject()->yes()) {
                return null;
            }

            $hydrated = $modelType->getTemplateType(Model::class, 'TModel');

            if ($hydrated->isObject()->no()) {
                return null;
            }

            $builder->setOffsetValueType($keyType, $hydrated, $constantArray->isOptionalKey($index));
        }

        return $builder->getArray();
    }
}
