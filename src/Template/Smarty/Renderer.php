<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty;

use Hazaar\Application\URL;
use Hazaar\File;
use Hazaar\Template\Exception\SmartyTemplateFunctionNotFound;
use Hazaar\Template\Smarty;

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
    public function render(array &$params): string
    {
        ob_start();

        try {
            $this->renderContent($params);
            $content = ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();

            throw $e;
        }

        return $content;
    }

    public function write(mixed $var): void
    {
        echo null === $var ? $this->params['__DEFAULT_VAR__'] : @$var;
    }

    /**
     * @param array<mixed> $params
     */
    public function include(string $file, array $params = [], string $ldelim = '{', string $rdelim = '}'): void
    {
        $include = new Smarty();
        $include->compiler->setDelimiters($ldelim, $rdelim);
        $include->loadFromFile(new File($file));
        echo $include->render($params);
        $this->functions = array_merge($this->functions, $include->functions);
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
                if (!$reflect->isPublic()) {
                    continue;
                }
                $funcParams = [];
                foreach ($reflect->getParameters() as $reflectionParameter) {
                    $parameterName = $reflectionParameter->getName();
                    $parameterValue = null;
                    if (array_key_exists($parameterName, $params) || array_key_exists($parameterName = $reflectionParameter->getPosition(), $params)) {
                        $parameterValue = $params[$parameterName];
                    } elseif ($reflectionParameter->isDefaultValueAvailable()) {
                        $defaultValue = $reflectionParameter->getDefaultValue();
                        $parameterValue = $params[$reflectionParameter->getName()] ?? $defaultValue;
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

    /**
     * @param array<mixed> $params
     */
    protected function renderContent(array &$params = []): void {}
}
