<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty;

use Hazaar\Application;
use Hazaar\DateTime;

abstract class Renderer
{
    public bool $allowGlobals = true;

    /**
     * @var array<mixed>
     */
    public array $functions = [];
    protected string $compiledContent;

    /**
     * @var array<mixed>
     */
    protected array $customFunctions;

    /**
     * Summary of includeFuncs.
     *
     * @var array<mixed>
     */
    protected array $includeFuncs;

    /**
     * @param array<mixed> $customFunctions
     * @param array<mixed> $includeFuncs
     */
    public function __construct(string $content, ?array $customFunctions = null, ?array $includeFuncs = null)
    {
        $this->compiledContent = $content;
        $this->customFunctions = $customFunctions ?? [];
        $this->includeFuncs = $includeFuncs ?? [];
    }

    /**
     * @param array<mixed> $params
     */
    public function exec(array $params = []): string
    {
        $app = Application::getInstance();
        $defaultParams = [
            'hazaar' => ['version' => HAZAAR_VERSION],
            'application' => $app ?? null,
            'smarty' => [
                'now' => new DateTime(),
                'const' => get_defined_constants(),
                'capture' => [],
                'config' => $app ? $app->config->toArray() : [],
                'section' => [],
                'foreach' => [],
                'template' => null,
                'version' => 2,
            ],
        ];
        if ($this->allowGlobals) {
            $defaultParams['_COOKIE'] = $_COOKIE;
            $defaultParams['_ENV'] = $_ENV;
            $defaultParams['_GET'] = $_GET;
            $defaultParams['_POST'] = $_POST;
            $defaultParams['_SERVER'] = $_SERVER;
        }
        $renderParameters = array_merge($defaultParams, (array) $params);
        if (array_key_exists('*', $renderParameters)) {
            $renderParameters['__DEFAULT_VAR__'] = $renderParameters['*'];
            unset($params['*']);
        } else {
            $renderParameters['__DEFAULT_VAR__'] = '';
        }
        $id = '_template_'.md5(uniqid());

        $code = "class {$id} {
            private \$modify;
            private \$variables = [];
            private \$params = [];
            public  \$functions = [];
            public  \$customFunctions = [];
            public  \$includeFuncs = [];
            function __construct(){ \$this->modify = new \\Hazaar\\Template\\Smarty\\Modifier; }
            public function render(\$params){
                extract(\$this->params = \$params, EXTR_REFS);
                ?>{$this->compiledContent}<?php
            }
            private function url(){
                if(\$customHandler = current(array_filter(\$this->customFunctions, function(\$item){
                    return method_exists(\$item, 'url');
                })))
                    return call_user_func_array([\$customHandler, 'url'], func_get_args());
                return new \\Hazaar\\Application\\Url(urldecode(implode('/', func_get_args())));
            }
            private function write(\$var){
                echo (\$var === null ? \$this->params['__DEFAULT_VAR__'] : @\$var);
            }
            private function include(\$hash, array \$params = []){
                if(!isset(\$this->includeFuncs[\$hash])) return '';
                \$i = \$this->includeFuncs[\$hash];
                \$out = \$i->render(\$params??[]);
                \$this->functions = array_merge(\$this->functions, \$i->functions);
                return \$out;
            }
        }";

        $obj = $this->render($id, $code);
        ob_start();
        $obj->customFunctions = $this->customFunctions;
        $obj->includeFuncs = $this->includeFuncs;
        $obj->functions = $this->customFunctions;
        $obj->render($renderParameters);
        // Merge the functions from the included templates
        $this->functions = array_merge($this->functions, $obj->functions);

        return ob_get_clean();
    }

    protected function render(string $id, string $code): object
    {
        return new \stdClass();
    }
}
