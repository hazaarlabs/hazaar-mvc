<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty;

use Hazaar\Application\URL;
use Hazaar\Template\Exception\SmartyTemplateFunctionNotFound;

abstract class Renderer
{
    /**
     * @var array<mixed>
     */
    public array $functions = [];

    /**
     * @var array<mixed>
     */
    public array $functionHandlers = [];

    /**
     * @var array<mixed>
     */
    public array $includeFuncs = [];

    /**
     * @var array<mixed>
     */
    public array $variables = [];

    /**
     * @var array<mixed>
     */
    public array $params = [];
    public Modifier $modify;

    public function __construct()
    {
        $this->modify = new Modifier();
    }

    /**
     * @param array<mixed> $params
     */
    public function render(array $params): void
    {
        echo 'no content';
    }

    public function write(mixed $var): void
    {
        echo null === $var ? $this->params['__DEFAULT_VAR__'] : @$var;
    }

    /**
     * @param array<mixed> $params
     */
    public function include(string $hash, array $params = []): mixed
    {
        if (!isset($this->includeFuncs[$hash])) {
            return '';
        }
        $i = $this->includeFuncs[$hash];
        $out = $i->render($params);
        $this->functions = array_merge($this->functions, $i->functions);

        return $out;
    }

    /**
     * @param array<mixed> $params
     */
    public function callFunctionHandler(string $name, array $params = []): mixed
    {
        if (array_key_exists($name, $this->functions)) {
            return $this->functions[$name](...$params);
        }
        if (count($this->functionHandlers) > 0) {
            foreach ($this->functionHandlers as $handler) {
                if (!method_exists($handler, $name)) {
                    continue;
                }
                $reflect = new \ReflectionMethod($handler, $name);
                $funcParams = [];
                foreach ($reflect->getParameters() as $reflectionParameter) {
                    $parameterName = $reflectionParameter->getName();
                    $parameterValue = null;
                    if (array_key_exists($parameterName, $params) || array_key_exists($parameterName = $reflectionParameter->getPosition(), $params)) {
                        $parameterValue = $params[$parameterName];
                    } elseif ($reflectionParameter->isDefaultValueAvailable()) {
                        $defaultValue = $reflectionParameter->getDefaultValue();
                        $parameterValue = ake($params, $reflectionParameter->getName(), $defaultValue);
                    }
                    $funcParams[$reflectionParameter->getPosition()] = $parameterValue;
                }

                return call_user_func_array([$handler, $name], $params);
            }
        }
        // Special case for the url function
        if ('url' === $name) {
            return new URL(urldecode(implode('/', $params())));
        }

        throw new SmartyTemplateFunctionNotFound($name);
    }
}
