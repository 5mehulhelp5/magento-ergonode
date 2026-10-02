<?php

declare(strict_types=1);

namespace Vendivo\PHPStan\Type;

use Magento\Backend\App\AbstractAction;
use Magento\Framework\Controller\ResultFactory;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

class AdminhtmlResultFactoryReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    private const TYPE_MAP = [
        'TYPE_JSON' => \Magento\Framework\Controller\Result\Json::class,
        'TYPE_RAW' => \Magento\Framework\Controller\Result\Raw::class,
        'TYPE_REDIRECT' => \Magento\Framework\Controller\Result\Redirect::class,
        'TYPE_FORWARD' => \Magento\Framework\Controller\Result\Forward::class,
        'TYPE_LAYOUT' => \Magento\Framework\View\Result\Layout::class,
        'TYPE_PAGE' => \Magento\Framework\View\Result\Page::class,
    ];

    public function getClass(): string
    {
        return ResultFactory::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'create';
    }

    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope
    ): ?Type {
        if (count($methodCall->getArgs()) === 0) {
            return null;
        }

        $expr = $methodCall->getArgs()[0]->value;
        if (!$expr instanceof ClassConstFetch || !$expr->name instanceof Identifier) {
            return null;
        }

        $typeName = $expr->name->toString();
        $className = self::TYPE_MAP[$typeName] ?? null;
        if ($className === null) {
            return null;
        }

        if ($typeName === 'TYPE_PAGE' && $this->isInsideAdminhtmlAction($scope)) {
            return new ObjectType(\Magento\Backend\Model\View\Result\Page::class);
        }

        return new ObjectType($className);
    }

    private function isInsideAdminhtmlAction(Scope $scope): bool
    {
        $classReflection = $scope->getClassReflection();

        return $classReflection !== null && $classReflection->isSubclassOf(AbstractAction::class);
    }
}
