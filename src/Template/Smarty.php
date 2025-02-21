<?php

declare(strict_types=1);

namespace Hazaar\Template;

use Hazaar\Application;
use Hazaar\DateTime;
use Hazaar\File;

/**
 * Smarty 2.0 Templates.
 *
 * This class implements the entire Smarty 2.0 template specification.  For documentation on the
 * Smarty 2.0 template format see the Smarty 2.0 online documentation: https://www.smarty.net/docsv2/en/
 *
 * Tags are in the format of {$tagname}.  This tag would reference a parameter passed to the parser
 * with the array key value of 'tagname'.  Such as:
 *
 * ```
 * $tpl = new \Hazaar\Template\Smarty($template_content);
 * $tpl->render(['tagname' => 'Hello, World!']);
 * ```
 */
class Smarty
{
    public string $ldelim = '{';
    public string $rdelim = '}';
    public bool $allowGlobals = true;
    public ?string $sourceFile = null;
    public ?string $cwd = null;

    /**
     * @var array<mixed>
     */
    public array $__functions = [];

    /**
     * @var array<string>
     */
    protected array $__includeFuncs = [];

    /**
     * @var array<mixed>
     */
    protected array $__customFunctions = [];

    /**
     * @var array<string>
     */
    protected static array $tags = [
        'if',
        'elseif',
        'else',
        'section',
        'sectionelse',
        'url',
        'foreach',
        'foreachelse',
        'ldelim',
        'rdelim',
        'capture',
        'assign',
        'include',
        // Hybrid Smarty 3.0 Bits
        'function',
        'call',
        'php',
    ];
    protected string $__content = '';
    protected string $__compiledContent = '';

    /**
     * @var array<string>
     */
    protected array $__includes = [];

    /**
     * @var array<object>
     */
    private array $__customFunctionHandlers = [];

    /**
     * @var array<mixed>
     */
    private array $__sectionStack = [];

    /**
     * @var array<mixed>
     */
    private array $__foreachStack = [];

    /**
     * @var array<mixed>
     */
    private array $__captureStack = [];

    /**
     * @var array<\Closure>
     */
    private array $__filters = [];

    /**
     * Create a new Smarty template object.
     *
     * @param array<mixed> $customFunctions
     * @param array<mixed> $includeFuncs
     */
    public function __construct(
        ?string $content = null,
        ?array $customFunctions = null,
        ?array $includeFuncs = null
    ) {
        $this->__customFunctions = $customFunctions ?? [];
        $this->__includeFuncs = $includeFuncs ?? [];
        if ($content) {
            $this->loadFromString($content);
        }
    }

    /**
     * Load the SMARTy template from a supplied string.
     *
     * @param string $content The template source code
     */
    public function loadFromString(string $content): void
    {
        $this->__content = (string) $content;
        $this->__compiledContent = '';
    }

    /**
     * Read the template from a file.
     *
     * @param File $file can be either a Hazaar\File object or a string to a file on disk
     */
    public function loadFromFile(File|string $file): void
    {
        if (!$file instanceof File) {
            $file = new File($file);
        }
        if (!$file->exists()) {
            throw new Exception\IncludeFileNotFound($file->fullpath());
        }
        $this->sourceFile = $file->fullpath();
        $this->cwd = $file->dirname();
        $this->loadFromString($file->getContents());
    }

    public function registerFunctionHandler(object $object): void
    {
        $this->__customFunctionHandlers[] = $object;
    }

    public function registerPlugin(string $modifier, callable $callback): void
    {
        $this->__customFunctions[$modifier] = $callback;
    }

    /**
     * Returns the original un-compiled template.
     */
    public function getTemplate(): string
    {
        return $this->__content;
    }

    /**
     * Retrieves the template file path.
     *
     * @return null|string the path to the template file, or null if not set
     */
    public function getTemplateFile(): ?string
    {
        return $this->sourceFile;
    }

    /**
     * Prepend a string to the existing content.
     */
    public function prepend(string $string): void
    {
        $this->__content = $string.$this->__content;
    }

    /**
     * Append a string to the existing content.
     */
    public function append(string $string): void
    {
        $this->__content .= $string;
    }

    /**
     * Add a post-processing filter to the template.
     *
     * Filters are applied after the template has been rendered and can be used to modify the output.  Useful for
     * things like minifying the output or removing whitespace.
     */
    public function addFilter(\Closure $filter): void
    {
        $this->__filters[] = $filter;
    }

    /**
     * Render the template with the supplied parameters and return the rendered content.
     *
     * @param array<mixed> $params parameters to use when embedding variables in the rendered template
     */
    public function render(array $params = []): string
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
                'ldelim' => $this->ldelim,
                'rdelim' => $this->rdelim,
            ],
        ];
        if ($this->allowGlobals) {
            $defaultParams['_COOKIE'] = $_COOKIE;
            $defaultParams['_ENV'] = $_ENV;
            $defaultParams['_GET'] = $_GET;
            $defaultParams['_POST'] = $_POST;
            $defaultParams['_REQUEST'] = $_REQUEST;
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
        if (!$this->__compiledContent) {
            $this->__compiledContent = $this->compile();
        }
        $code = "class {$id} {
            private \$modify;
            private \$variables = [];
            private \$params = [];
            public  \$functions = [];
            public  \$customHandlers;
            public  \$includeFuncs = [];
            function __construct(){ \$this->modify = new \\Hazaar\\Template\\Smarty\\Modifier; }
            public function render(\$params){
                extract(\$this->params = \$params, EXTR_REFS);
                ?>{$this->__compiledContent}<?php
            }
            private function url(){
                if(\$custom_handler = current(array_filter(\$this->customHandlers, function(\$item){
                    return method_exists(\$item, 'url');
                })))
                    return call_user_func_array([\$custom_handler, 'url'], func_get_args());
                return new \\Hazaar\\Application\\Url(urldecode(implode('/', func_get_args())));
            }
            private function write(\$var){
                echo (\$var === null ? \$this->params['__DEFAULT_VAR__'] : @\$var);
            }
            private function include(\$hash, array \$params = []){
                if(!isset(\$this->includeFuncs[\$hash])) return '';
                \$i = \$this->includeFuncs[\$hash];
                \$out = \$i->render(\$params??[]);
                \$this->functions = array_merge(\$this->functions, \$i->__functions);
                return \$out;
            }
        }";
        $errors = error_reporting();
        error_reporting(0);

        try {
            eval($code);
            $obj = new $id();
            ob_start();
            $obj->customHandlers = $this->__customFunctionHandlers;
            $obj->includeFuncs = $this->__includeFuncs;
            $obj->render($renderParameters);
            // Merge the functions from the included templates
            $this->__functions = array_merge($this->__functions, $obj->functions);
        } catch (\Throwable $e) {
            throw new Exception\SmartyTemplateError($e);
        } finally {
            error_clear_last();
            error_reporting($errors);
        }
        $content = ob_get_clean();
        if (count($this->__filters) > 0) {
            foreach ($this->__filters as $filter) {
                $content = $filter($content);
            }
        }

        return $content;
    }

    /**
     * Compile the template ready for rendering.
     *
     * This will normally happen automatically when calling Hazaar\Template\Smarty::render() but can be called
     * separately if needed.  The compiled template content is returned and can be stored externally.
     */
    public function compile(): string
    {
        if ($this->__compiledContent) {
            return $this->__compiledContent;
        }
        $compiled_content = preg_replace(['/\<\?/', '/\?\>/'], ['&lt;?', '?&gt;'], $this->__content);
        $regex = '/\{([#\$\*][^\}]+|(\/?\w+)\s*([^\}]*))\}(\r?\n)?/';
        $literal = false;
        $strip = false;

        return $this->__compiledContent = preg_replace_callback($regex, function ($matches) use (&$literal, &$strip) {
            $replacement = '';
            if (preg_match('/(\/?)literal/', $matches[1], $literals)) {
                $literal = ('/' !== $literals[1]);
            } elseif ($literal) {
                return $matches[0];
            // It matched a variable
            } elseif ('$' === substr($matches[1], 0, 1)) {
                $replacement = $this->replaceVAR($matches[1]);
            // Matched a config variable
            } elseif ('#' === substr($matches[1], 0, 1) && '#' === substr($matches[1], -1)) {
                $replacement = $this->replaceCONFIG_VAR(substr($matches[1], 1, -1));
            // Must be a function so we exec the internal function handler
            } elseif (('/' == substr($matches[2], 0, 1)
                && in_array(substr($matches[2], 1), Smarty::$tags))
                || in_array($matches[2], Smarty::$tags)) {
                $func = 'compile'.str_replace('/', 'END', strtoupper($matches[2]));
                $replacement = $this->{$func}($matches[3]);
            } elseif (count($this->__customFunctionHandlers) > 0
                && $custom_handler = current(array_filter($this->__customFunctionHandlers, function ($item, $index) use ($matches) {
                    if (!method_exists($item, $matches[2])) {
                        return false;
                    }
                    $item->__index = $index;

                    return true;
                }, ARRAY_FILTER_USE_BOTH))) {
                $replacement = $this->compileCUSTOMHANDLERFUNC($custom_handler, $matches[2], $matches[3], $custom_handler->__index);
            } elseif (preg_match('/(\/?)strip/', $matches[1], $flags)) {
                $strip = ('/' !== $flags[1]);
            } else {
                // Anything else is considered a custom function.
                $replacement = $this->compileCUSTOMFUNC($matches[2], $matches[3]);
            }
            if (true === $strip) {
                $replacement = trim($replacement);
            } elseif (isset($matches[4])) {
                $replacement .= " \r\n";
            }

            return $replacement;
        }, $compiled_content);
    }

    public function compilePHP(): string
    {
        return '<?php ';
    }

    public function compileENDPHP(): string
    {
        return '?>';
    }

    protected function setType(mixed $value, string $type = 'string', ?string $args = null): string
    {
        switch ($type) {
            case 'date':
                if (!$value instanceof DateTime) {
                    $value = new DateTime($value);
                }
                $value = ($args ? $value->format($args) : (string) $value);

                break;

            case 'string':
            default:
                $value = (string) $value;
        }

        return $value;
    }

    /**
     * @return array<string>
     */
    protected function parsePARAMS(string $params, bool $keep_quotes = true): array
    {
        $parts = preg_split("/['\"][^'\"]*['\"](*SKIP)(*F)|\x20/", $params);
        $params = [];
        foreach ($parts as $part) {
            $part_parts = explode('=', $part, 2);
            if (count($part_parts) >= 2) {
                list($left, $right) = $part_parts;
                if (preg_match_all('/`(.*)`/', $right, $matches)) {
                    foreach ($matches[0] as $id => $match) {
                        $right = str_replace($match, '{'.$this->compileVAR($matches[1][$id]).'}', $right);
                    }
                }
                $params[$left] = $right;
            } else {
                $params[] = $part;
            }
        }

        return $params;
    }

    protected function compileVAR(string $name): string
    {
        $modifiers = [];
        if (false !== strpos($name, '|')) {
            $c_part = '';
            $quote = null;
            for ($i = 0; $i < strlen($name); ++$i) {
                if ('|' === $name[$i] && null === $quote) {
                    $modifiers[] = $c_part;
                    $c_part = '';

                    continue;
                }
                if ('"' === $name[$i] || "'" == $name[$i]) {
                    $quote = ($quote == $name[$i]) ? null : $name[$i];
                }
                $c_part .= $name[$i];
            }
            $modifiers[] = $c_part;
            $name = array_shift($modifiers);
        }
        $parts = preg_split('/(\.|->|\[)/', $name, -1, PREG_SPLIT_DELIM_CAPTURE);
        $name = array_shift($parts);
        if (count($parts) > 0) {
            foreach ($parts as $idx => $part) {
                if (!$part || '.' == $part || '->' == $part || '[' == $part) {
                    continue;
                }
                if ('->' == ake($parts, $idx - 1)) {
                    $name .= '->'.$part;
                } elseif ('$' == substr($part, 0, 1)) {
                    $name .= "[{$part}]";
                } elseif (']' == substr($part, -1)) {
                    if ("'" == substr($part, 0, 1) && substr($part, -2, 1) == substr($part, 0, 1)) {
                        $name .= '['.$part;
                    } else {
                        $name .= '[$smarty[\'section\'][\''.substr($part, 0, -1)."']['index']]";
                    }
                } else {
                    $name .= "['{$part}']";
                }
            }
        }
        if (count($modifiers) > 0) {
            foreach ($modifiers as $modifier) {
                $params = str_getcsv($modifier, ':', '"', '\\');
                $func = array_shift($params);
                $name = '$this->modify->execute("'.$func.'", '.$name.((count($params) > 0) ? ', "'.implode('", "', $params).'"' : '').')';
            }
        }

        return $name;
    }

    protected function compileVARS(string $string): string
    {
        if (preg_match_all('/\$[\w\.\[\]]+/', $string, $matches)) {
            foreach ($matches[0] as $match) {
                $string = str_replace($match, '\' . '.$this->compileVAR($match).' . \'', $string);
            }
        }

        return $string;
    }

    protected function replaceVAR(string $name): string
    {
        $var = $this->compileVAR($name);

        return "<?php \$this->write({$var}); ?>";
    }

    protected function replaceCONFIG_VAR(string $name): string
    {
        return $this->replaceVAR("\$this->variables['{$name}']");
    }

    protected function compilePARAMS(mixed $params): string
    {
        if (is_array($params)) {
            $out = [];
            foreach ($params as $p) {
                $out[] = $this->compilePARAMS($p);
            }

            return implode(', ', $out);
        }
        if (is_string($params)) {
            if (preg_match_all('/\$\w[\w\.\$\-]+/', $params, $matches)) {
                foreach ($matches[0] as $match) {
                    $params = str_replace($match, $this->compileVAR($match), $params);
                }
            } else {
                $params = "'{$params}'";
            }
        } elseif (is_int($params) || is_float($params)) {
            $params = (string) $params;
        } elseif (is_bool($params)) {
            $params = $params ? 'true' : 'false';
        }

        return $params;
    }

    protected function compileIF(mixed $params): string
    {
        return '<?php if(@'.$this->compilePARAMS($params).'): ?>';
    }

    protected function compileELSEIF(mixed $params): string
    {
        return '<?php elseif(@'.$this->compilePARAMS($params).'): ?>';
    }

    protected function compileELSE(mixed $params): string
    {
        return '<?php else: ?>';
    }

    protected function compileENDIF(): string
    {
        return '<?php endif; ?>';
    }

    protected function compileSECTION(mixed $params): string
    {
        $parts = preg_split('/\s+/', $params);
        $params = [];
        foreach ($parts as $part) {
            $params += array_unflatten($part);
        }
        // Make sure we have the name and loop required parameters.
        if (!(($name = ake($params, 'name')) && ($loop = ake($params, 'loop')))) {
            return '';
        }
        $this->__sectionStack[] = ['name' => $name, 'else' => false];
        $var = $this->compileVAR($loop);
        $index = '$smarty[\'section\'][\''.$name.'\'][\'index\']';
        $count = '$__count_'.$name;
        $code = "<?php \$smarty['section']['{$name}'] = []; if(is_array({$var}) && count({$var})>0): ";
        $code .= "for({$count}=1, {$index}=".ake($params, 'start', 0).'; ';
        $code .= "{$index}<".(is_numeric($loop) ? $loop : 'count('.$this->compileVAR($loop).')').'; ';
        $code .= "{$count}++, {$index}+=".ake($params, 'step', 1).'): ';
        if ($max = ake($params, 'max')) {
            $code .= 'if('.$count.'>'.$max.') break; ';
        }
        $code .= '?>';

        return $code;
    }

    protected function compileSECTIONELSE(): string
    {
        end($this->__sectionStack);
        $this->__sectionStack[key($this->__sectionStack)]['else'] = true;

        return '<?php endfor; else: ?>';
    }

    protected function compileENDSECTION(): string
    {
        $section = array_pop($this->__sectionStack);
        if (true === $section['else']) {
            return '<?php endif; ?>';
        }

        return '<?php endfor; endif; array_pop($smarty[\'section\']); ?>';
    }

    protected function compileURL(string $tag): string
    {
        $vars = '';
        if ($tag) {
            $nodes = [];
            $tags = preg_split('/\s+/', $tag);
            foreach ($tags as $tag) {
                $nodes[] = "'".$this->compileVARS(trim($tag, "'"))."'";
            }
            $vars = implode(', ', $nodes);
        } else {
            $vars = "'".trim($tag, "'")."'";
        }

        return '<?php echo $this->url('.$vars.');?>';
    }

    protected function compileFOREACH(mixed $params): string
    {
        $params = $this->parsePARAMS($params);
        $code = '';
        // Make sure we have the name and loop required parameters.
        if (($from = ake($params, 'from')) && ($item = ake($params, 'item'))) {
            $name = ake($params, 'name', 'foreach_'.uniqid());
            $var = $this->compileVAR($from);
            $this->__foreachStack[] = ['name' => $name, 'else' => false];
            $target = (($key = ake($params, 'key')) ? '$'.$key.' => ' : '').'$'.$item;
            $code = "<?php \$smarty['foreach']['{$name}'] = ['index' => -1, 'total' => ((isset({$var}) && is_array({$var}))?count({$var}):0)]; ";
            $code .= "if(isset({$var}) && is_array({$var}) && count({$var}) > 0): ";
            $code .= "foreach({$var} as {$target}): \$smarty['foreach']['{$name}']['index']++; ?>";
        } elseif ('as' === ake($params, 1)) { // Smarty 3 support
            $name = ake($params, 'name', 'foreach_'.uniqid());
            $var = $this->compileVAR(ake($params, 0));
            $target = $this->compileVAR(ake($params, 2));
            $this->__foreachStack[] = ['name' => $name, 'else' => false];
            $code = "<?php \$smarty['foreach']['{$name}'] = ['index' => -1, 'total' => ((isset({$var}) && is_array({$var}))?count({$var}):0)]; ";
            $code .= "if(isset({$var}) && is_array({$var}) && count({$var}) > 0): ";
            $code .= "foreach({$var} as {$target}): \$smarty['foreach']['{$name}']['index']++; ?>";
        }

        return $code;
    }

    protected function compileFOREACHELSE(): string
    {
        end($this->__foreachStack);
        $this->__foreachStack[key($this->__foreachStack)]['else'] = true;

        return '<?php endforeach; else: ?>';
    }

    protected function compileENDFOREACH(): string
    {
        $loop = array_pop($this->__foreachStack);
        if (true === $loop['else']) {
            return '<?php endif; ?>';
        }

        return '<?php endforeach; endif; ?>';
    }

    protected function compileLDELIM(): string
    {
        return $this->ldelim;
    }

    protected function compileRDELIM(): string
    {
        return $this->rdelim;
    }

    protected function compileCAPTURE(mixed $params): string
    {
        $params = $this->parsePARAMS($params);
        if (!array_key_exists('name', $params)) {
            return '';
        }
        $this->__captureStack[] = $params;

        return '<?php ob_start(); ?>';
    }

    protected function compileENDCAPTURE(): string
    {
        $params = array_pop($this->__captureStack);
        $code = '<?php $'.$this->compileVAR('smarty.capture.'.$params['name']);
        if (array_key_exists('assign', $params)) {
            $code .= ' = $'.$this->compileVAR($params['assign']);
        }

        return $code.' = ob_get_clean(); ?>';
    }

    protected function compileASSIGN(mixed $params): string
    {
        if ('var' === substr($params, 0, 3)) {
            $params = $this->parsePARAMS($params);
        } else {
            $parts = $this->parsePARAMS($params);
            $params = [
                'var' => $parts[0],
                'value' => $parts[1],
            ];
        }
        if (!(array_key_exists('var', $params) && array_key_exists('value', $params))) {
            return '';
        }
        $value = preg_match('/(.+)/', $params['value'], $matches) ? $matches[1] : 'null';

        return '<?php @$'.trim($params['var'], '"\'')."={$value};?>";
    }

    protected function compileFUNCTION(mixed $params): string
    {
        $params = $this->parsePARAMS($params);
        if (!($name = trim(ake($params, 'name'), '\'"'))
            || array_key_exists($name, $this->__customFunctions)) {
            return '';
        }
        unset($params['name']);
        $this->__customFunctions[$name] = $params;
        if (array_key_exists('params', $params)) {
            $funcParams = eval('return '.$params['params'].';');
            $compiledParams = implode(', ', array_map(function ($item) { return '&$'.$item; }, $funcParams));
        } else {
            $compiledParams = '';
        }

        return "<?php (\$this->functions['{$name}'] = function({$compiledParams}){ global \$smarty; ?>";
    }

    protected function compileENDFUNCTION(): string
    {
        return '<?php })->bindTo($this); ?>';
    }

    protected function compileCUSTOMFUNC(string $name, mixed $params): string
    {
        $code = "<?php\n";
        $params = $this->parsePARAMS($params);
        foreach ($params as &$value) {
            if ('$' === substr($value, 0, 1)) {
                continue;
            }
            $key = '$var_'.uniqid();
            $code .= "{$key} = {$value};\n";
            $value = $key;
        }
        $compiledParams = match (true) {
            (count($params) > 0) => implode(', ', $params),
            default => ''
        };
        $code .= "if(!isset(\$this->functions['{$name}'])) throw new \\Exception('Function \\'{$name}\\' not found');\n";
        $code .= "\$this->functions['{$name}']({$compiledParams});\n?>";

        return $code;
    }

    protected function compileCUSTOMHANDLERFUNC(object $handler, string $method, mixed $params, int $index): string
    {
        $params = $this->parsePARAMS($params);
        $reflect = new \ReflectionMethod($handler, $method);
        $func_params = [];
        foreach ($reflect->getParameters() as $p) {
            $name = $p->getName();
            $value = 'null';
            if (array_key_exists($name, $params) || array_key_exists($name = $p->getPosition(), $params)) {
                $value = $this->compilePARAMS($params[$name]);
            } elseif ($p->isDefaultValueAvailable()) {
                $defaultValue = $p->getDefaultValue();
                $value = ake($params, $p->getName(), $defaultValue);
                $value = $this->compilePARAMS($value);
            }
            $func_params[$p->getPosition()] = $value;
        }
        $params = implode(', ', $func_params);

        return "<?php echo call_user_func_array([\$this->customHandlers[{$index}], '{$method}'], [{$params}]); ?>";
    }

    protected function compileCALL(mixed $params): string
    {
        $call_params = $this->parsePARAMS($params);
        if (isset($call_params[0])) {
            $call_params['name'] = $call_params[0];
        }
        $params = substr($params, strpos($params, ' ') + 1);
        if (!isset($call_params['name'])) {
            return '';
        }

        return $this->compileCUSTOMFUNC($call_params['name'], $params);
    }

    protected function compileINCLUDE(mixed $params): string
    {
        $params = $this->parsePARAMS($params);
        if (!array_key_exists('file', $params)) {
            return '';
        }
        $file = trim($params['file'], '\'"');
        unset($params['file']);
        if ('/' !== $file[0] && !preg_match('/^\w+\:\/\//', $file)) {
            $file = realpath($this->cwd ? rtrim($this->cwd, ' /') : getcwd()).DIRECTORY_SEPARATOR.$file;
        }
        if (!file_exists($file)) {
            throw new Exception\IncludeFileNotFound($file);
        }
        $info = pathinfo($file);
        if (!(array_key_exists('extension', $info) && $info['extension'])
            && file_exists($file.'.tpl')) {
            $file .= '.tpl';
        }
        $hash = hash('crc32b', $file);
        if (!array_key_exists($hash, $this->__includeFuncs)) {
            $this->__includes[] = $file;
            $include = new Smarty(null, $this->__customFunctions, $this->__includeFuncs);
            $include->loadFromFile($file);
            $this->__includeFuncs[$hash] = $include;
        }
        $args = count($params) > 0
            ? '['.implode(', ', array_map(function ($item, $key) { return "'{$key}' => {$item}"; }, $params, array_keys($params))).']'
            : '';

        return "<?php echo \$this->include('{$hash}'".($args ? ", {$args}" : '').'); ?>';
    }
}
