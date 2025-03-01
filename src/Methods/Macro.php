<?php

declare(strict_types=1);

namespace NunoMaduro\Larastan\Methods;

use Closure;
use ErrorException;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\FunctionVariant;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Reflection\PassedByReference;
use PHPStan\TrinaryLogic;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\MixedType;
use PHPStan\Type\TypehintHelper;
use ReflectionFunction;
use ReflectionParameter;
use stdClass;

final class Macro implements MethodReflection
{
    /**
     * @var ClassReflection
     */
    private $classReflection;

    /**
     * The method name.
     *
     * @var string
     */
    private $methodName;

    /**
     * The reflection function.
     *
     * @var ReflectionFunction
     */
    private $reflectionFunction;

    /**
     * The parameters.
     *
     * @var ReflectionParameter[]
     */
    private $parameters;

    /**
     * The is static.
     *
     * @var bool
     */
    private $isStatic = false;

    public function __construct(ClassReflection $classReflection, string $methodName, ReflectionFunction $reflectionFunction)
    {
        $this->classReflection = $classReflection;
        $this->methodName = $methodName;
        $this->reflectionFunction = $reflectionFunction;
        $this->parameters = $this->reflectionFunction->getParameters();

        if ($this->reflectionFunction->isClosure()) {
            try {
                /** @var Closure $closure */
                $closure = $this->reflectionFunction->getClosure();
                Closure::bind($closure, new stdClass);
                // The closure can be bound so it was not explicitly marked as static
            } catch (ErrorException $e) {
                // The closure was explicitly marked as static
                $this->isStatic = true;
            }
        }
    }

    public function getDeclaringClass(): ClassReflection
    {
        return $this->classReflection;
    }

    public function isPrivate(): bool
    {
        return false;
    }

    public function isPublic(): bool
    {
        return true;
    }

    public function isFinal(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }

    public function isInternal(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }

    public function isStatic(): bool
    {
        return $this->isStatic;
    }

    /**
     * Set the is static value.
     *
     * @param  bool  $isStatic
     * @return void
     */
    public function setIsStatic(bool $isStatic): void
    {
        $this->isStatic = $isStatic;
    }

    /**
     * {@inheritdoc}
     */
    public function getDocComment(): ?string
    {
        return $this->reflectionFunction->getDocComment() ?: null;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->methodName;
    }

    /** @return ParameterReflection[] */
    public function getParameters(): array
    {
        return \array_map(function (\ReflectionParameter $reflection): ParameterReflection {
            return new class($reflection) implements ParameterReflection
            {
                /**
                 * @var ReflectionParameter
                 */
                private $reflection;

                public function __construct(ReflectionParameter $reflection)
                {
                    $this->reflection = $reflection;
                }

                public function getName(): string
                {
                    return $this->reflection->getName();
                }

                public function isOptional(): bool
                {
                    return $this->reflection->isOptional();
                }

                public function getType(): \PHPStan\Type\Type
                {
                    $type = $this->reflection->getType();

                    if ($type === null) {
                        return new MixedType();
                    }

                    return TypehintHelper::decideTypeFromReflection($this->reflection->getType());
                }

                public function passedByReference(): \PHPStan\Reflection\PassedByReference
                {
                    return PassedByReference::createNo();
                }

                public function isVariadic(): bool
                {
                    return $this->reflection->isVariadic();
                }

                public function getDefaultValue(): ?\PHPStan\Type\Type
                {
                    return null;
                }
            };
        }, $this->parameters);
    }

    /**
     * Set the parameters value.
     *
     * @param  ReflectionParameter[]  $parameters
     * @return void
     */
    public function setParameters(array $parameters): void
    {
        $this->parameters = $parameters;
    }

    public function getReturnType(): ?\ReflectionType
    {
        return $this->reflectionFunction->getReturnType();
    }

    public function isDeprecated(): TrinaryLogic
    {
        return TrinaryLogic::createFromBoolean($this->reflectionFunction->isDeprecated());
    }

    public function getPrototype(): \PHPStan\Reflection\ClassMemberReflection
    {
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getVariants(): array
    {
        return [
            new FunctionVariant(TemplateTypeMap::createEmpty(), null, $this->getParameters(), $this->reflectionFunction->isVariadic(), TypehintHelper::decideTypeFromReflection($this->getReturnType())),
        ];
    }

    public function getDeprecatedDescription(): ?string
    {
        return null;
    }

    public function getThrowType(): ?\PHPStan\Type\Type
    {
        return null;
    }

    public function hasSideEffects(): \PHPStan\TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }
}
