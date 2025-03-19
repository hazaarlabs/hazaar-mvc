<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty;

use Hazaar\Template\Exception\IncludeFileNotFound;
use Hazaar\Util\Arr;
use Hazaar\Util\Boolean;
use Hazaar\Util\DateTime;

/**
 * Compiler for Smarty templates
 *
 * This class handles the compilation of Smarty template syntax into executable PHP code.
 * It parses Smarty tags, variables, modifiers, and directives and transforms them into
 * their PHP equivalents. The Compiler manages template delimiters, section and foreach
 * loops, variable captures, and includes.
 * 
 * The compilation process follows these main steps:
 * 1. Template content is parsed for Smarty syntax using delimiters
 * 2. Tags and variables are identified and processed by their respective handlers
 * 3. PHP code is generated that, when executed, will render the template output
 * 
 * This class is used internally by the Smarty template engine and should not be
 * instantiated directly by application code.
 */
class Compiler
{
    /**
     * @var array<string>
     */
    protected static array $tags = [
        'if',
        'elseif',
        'else',
        'section',
        'sectionelse',
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

    /**
     * Map of conditional operators to PHP operators.
     *
     * @var array<string,string>
     */
    protected static array $conditionalMap = [
        'eq' => '==',
        'eqeq' => '===',
        'ne' => '!=',
        'neq' => '!==',
        'lt' => '<',
        'gt' => '>',
        'lte' => '<=',
        'gte' => '>=',
        'and' => '&&',
        'or' => '||',
    ];
    private string $ldelim = '{';
    private string $rdelim = '}';
    private ?string $cwd = null;

    /**
     * @var array<mixed>
     */
    private array $sectionStack = [];

    /**
     * @var array<mixed>
     */
    private array $foreachStack = [];

    /**
     * @var array<mixed>
     */
    private array $captureStack = [];

    private string $compiledContent;

    public function __construct(string $ldelim = '{', string $rdelim = '}')
    {
        $this->ldelim = $ldelim;
        $this->rdelim = $rdelim;
    }

    public function setCWD(string $cwd): void
    {
        $this->cwd = $cwd;
    }

    public function setDelimiters(string $ldelim, string $rdelim): void
    {
        $this->ldelim = $ldelim;
        $this->rdelim = $rdelim;
    }

    public function reset(): void
    {
        $this->sectionStack = [];
        $this->foreachStack = [];
        $this->captureStack = [];
        unset($this->compiledContent);
    }

    public function exec(?string $content = null): bool
    {
        $compiledContent = preg_replace(['/\<\?/', '/\?\>/'], ['&lt;?', '?&gt;'], $content);
        $regex = '/\\'.$this->ldelim.'([#\$\*][^\}]+|(\/?\w+)\s*([^\}]*))\\'.$this->rdelim.'(\r?\n)?/';
        $literal = false;
        $strip = false;
        $this->compiledContent = preg_replace_callback($regex, function ($matches) use (&$literal, &$strip) {
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
                $replacement = $this->replaceCONFIGVAR(substr($matches[1], 1, -1));
            // Must be a function so we exec the internal function handler
            } elseif (('/' == substr($matches[2], 0, 1)
                && in_array(substr($matches[2], 1), self::$tags))
                || in_array($matches[2], self::$tags)) {
                $func = 'compile'.str_replace('/', 'END', strtoupper($matches[2]));
                $replacement = $this->{$func}($matches[3]);
            } elseif (preg_match('/(\/?)strip/', $matches[1], $flags)) {
                $strip = ('/' !== $flags[1]);
            } else {
                // Anything else is considered a custom function.
                $replacement = $this->compileFUNCTIONHANDLER($matches[2], $matches[3]);
            }
            if (true === $strip) {
                $replacement = trim($replacement);
            } elseif (isset($matches[4])) {
                $replacement .= " \r\n";
            }

            return $replacement;
        }, $compiledContent);

        return true;
    }

    public function getCompiledContent(): string
    {
        return $this->compiledContent ?? '';
    }

    public function isCompiled(): bool
    {
        return isset($this->compiledContent);
    }

    public function getCode(string $templateObjectId): string
    {
        return "class {$templateObjectId} extends \\Hazaar\\Template\\Smarty\\Renderer {
            function renderContent(array &\$params = []): void {
                \$this->params = \$params;
                extract(\$this->params, EXTR_REFS);
                ?>{$this->compiledContent}<?php
            }
        }";
    }

    public function compilePHP(): string
    {
        return '<?php ';
    }

    public function compileENDPHP(): string
    {
        return '?>';
    }

    /**
     * Transforms the provided value into the specified type with optional formatting arguments.
     *
     * @param mixed       $value The value to be type converted
     * @param string      $type  The target type to convert to ('date' or 'string')
     * @param null|string $args  Optional formatting arguments (e.g., date format string)
     *
     * @return string The transformed value as a string
     */
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
     * Parses a parameter string into an associative array of parameter values.
     * Handles complex parameter strings including arrays, quoted strings, and variable references.
     *
     * @param string $params The parameter string to parse
     *
     * @return array<mixed> Associative array of parsed parameters
     */
    protected function parsePARAMS(string $params): array
    {
        $parts = preg_split("/['\"][^'\"]*['\"](*SKIP)(*F)|\\[[^\\]]*\\](*SKIP)(*F)|\x20/", $params);
        $params = [];
        foreach ($parts as $part) {
            $partParts = preg_split('/=(?![^\[]*\])/', $part, 2);
            if (1 === count($partParts)) {
                $value = $this->parseVALUE($partParts[0]);
                if (is_array($value)) {
                    $params = array_merge($params, $value);
                } else {
                    $params[] = $value;
                }

                continue;
            }
            [$left, $right] = $partParts;
            $right = trim(preg_replace_callback('/`(.*)`/', function ($matches) {
                return '{'.$this->compileVAR($matches[1]).'}';
            }, $right));
            $params[$left] = $this->parseVALUE($right);
        }

        return $params;
    }

    /**
     * Parses a value string that may represent an array or simple value.
     * Handles array syntax with brackets and arrow notation.
     *
     * @param string $array The string to parse as a value
     *
     * @return mixed The parsed value, either as an array or simple value
     */
    protected function parseVALUE(string $array): mixed
    {
        if (!('[' === substr($array, 0, 1) && ']' === substr($array, -1))) {
            if (preg_match('/(["\'])(.*)\1$/', $array, $matches)) {
                return $matches[2];
            }

            return $array;
        }
        $compiledArray = [];
        $array = preg_split('/\s*,\s*(?![^\[]*\])/', substr($array, 1, -1));
        foreach ($array as &$item) {
            if (strpos($item, '=>')) {
                [$key, $value] = preg_split('/\s*=>\s*/', $item);
                $key = $this->parseVALUE($key);
                $value = $this->parseVALUE($value);
            } else {
                $key = $this->parseVALUE($item);
                $value = null;
            }
            $compiledArray[$key] = $value;
        }

        return $compiledArray;
    }

    /**
     * Compiles a variable reference into its PHP equivalent.
     * Handles variable modifiers, array access, object properties, and section variables.
     *
     * @param string $name The variable name/reference to compile
     *
     * @return string The compiled PHP code for the variable
     */
    protected function compileVAR(string $name): string
    {
        $modifiers = [];
        if (false !== strpos($name, '|')) {
            // Split on '|' not inside quotes
            $modifiers = preg_split('/\|(?=(?:[^"\']|"[^"]*"|\'[^\']*\')*$)/', $name);
            $name = array_shift($modifiers);
        }
        $parts = preg_split('/(\.|->|\[)/', $name, -1, PREG_SPLIT_DELIM_CAPTURE);
        $name = array_shift($parts);
        if (count($parts) > 0) {
            foreach ($parts as $idx => $part) {
                if (!$part || '.' == $part || '->' == $part || '[' == $part) {
                    continue;
                }
                if ('->' == ($parts[$idx - 1] ?? null)) {
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
        $name = "({$name}??null)";
        if (count($modifiers) > 0) {
            foreach ($modifiers as $modifier) {
                $params = str_getcsv($modifier, ':', '"', '\\');
                $func = array_shift($params);
                foreach ($params as &$param) {
                    $param = $this->parseValue($param);
                }
                $name = '$this->modify->execute("'.$func.'", '.$name.((count($params) > 0) ? ', "'.implode('", "', $params).'"' : '').')';
            }
        }

        return $name;
    }

    /**
     * Compiles variables within a string into their PHP equivalents.
     * Replaces all variable references with their compiled versions.
     *
     * @param string $string The string containing variables to compile
     *
     * @return string The string with compiled variable references
     */
    protected function compileVARS(string $string): string
    {
        if (preg_match_all('/\$\w[\w\.\->]+/', $string, $matches)) {
            foreach ($matches[0] as $match) {
                $string = str_replace($match, '\' . '.$this->compileVAR($match).' . \'', $string);
            }
        }

        return $string;
    }

    /**
     * Generates PHP code to write a variable value to output.
     *
     * @param string $name The variable reference to output
     *
     * @return string The PHP code to write the variable
     */
    protected function replaceVAR(string $name): string
    {
        $var = $this->compileVAR($name);

        return "<?php \$this->write({$var}); ?>";
    }

    /**
     * Generates PHP code to write a configuration variable value to output.
     *
     * @param string $name The configuration variable name
     *
     * @return string The PHP code to write the config variable
     */
    protected function replaceCONFIG_VAR(string $name): string
    {
        return $this->replaceVAR("\$this->variables['{$name}']");
    }

    /**
     * Compiles parameters into valid PHP code expressions.
     * Handles arrays, variables, logical operators, and literal values.
     *
     * @param array<mixed>|string $params Parameters to compile
     *
     * @return string The compiled PHP expression
     */
    protected function compileCONDITIONS(array|string $params): string
    {
        if (is_array($params)) {
            $out = [];
            foreach ($params as $p) {
                $out[] = $this->compileCONDITIONS($p);
            }

            return implode(', ', $out);
        }
        $parts = preg_split('/\s+/', $params);
        foreach ($parts as &$part) {
            if ('$' === substr($part, 0, 1)) {
                $part = $this->compileVAR($part);
            } elseif (array_key_exists($part, self::$conditionalMap)) {
                $part = self::$conditionalMap[$part];
            } elseif (!(Boolean::is($parts) || is_numeric($part) || preg_match('/^(["\']).*\1$/', $part))
                && !in_array($part, self::$conditionalMap)) {
                $part = "'{$part}'";
            }
        }

        return implode(' ', $parts);
    }

    /**
     * Recursively compiles an array into its PHP array syntax representation.
     *
     * @param array<mixed> $array The array to compile
     *
     * @return string The PHP array syntax as a string
     */
    protected function compileARRAY(array $array): string
    {
        $out = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $value = $this->compileARRAY($value);
            } else {
                $value = $this->compileCONDITIONS($value);
            }
            $out[] = "'{$key}' => ".$value;
        }

        return '['.implode(', ', $out).']';
    }

    /**
     * Compiles an IF statement into its PHP equivalent.
     *
     * @param string $params The condition expression to evaluate
     *
     * @return string The compiled PHP if statement
     */
    protected function compileIF(string $params): string
    {
        return '<?php if('.$this->compileCONDITIONS($params).'): ?>';
    }

    /**
     * Compiles an ELSEIF statement into its PHP equivalent.
     *
     * @param string $params The condition expression to evaluate
     *
     * @return string The compiled PHP elseif statement
     */
    protected function compileELSEIF(string $params): string
    {
        return '<?php elseif('.$this->compileCONDITIONS($params).'): ?>';
    }

    /**
     * Compiles an ELSE statement into its PHP equivalent.
     *
     * @return string The compiled PHP else statement
     */
    protected function compileELSE(): string
    {
        return '<?php else: ?>';
    }

    protected function compileENDIF(): string
    {
        return '<?php endif; ?>';
    }

    protected function compileSECTION(string $params): string
    {
        $parts = preg_split('/\s+/', $params);
        $params = [];
        foreach ($parts as $part) {
            $params += Arr::unflatten($part);
        }
        // Make sure we have the name and loop required parameters.
        if (!(($name = $params['name'] ?? null) && ($loop = $params['loop'] ?? null))) {
            return '';
        }
        $this->sectionStack[] = ['name' => $name, 'else' => false];
        $var = $this->compileVAR($loop);
        $index = '$smarty[\'section\'][\''.$name.'\'][\'index\']';
        $count = '$__count_'.$name;
        $code = "<?php \$smarty['section']['{$name}'] = []; if(is_array({$var}) && count({$var})>0): ";
        $code .= "for({$count}=1, {$index}=".($params['start'] ?? '0').'; ';
        $code .= "{$index}<".(is_numeric($loop) ? $loop : 'count('.$this->compileVAR($loop).')').'; ';
        $code .= "{$count}++, {$index}+=".($params['step'] ?? '1').'): ';
        if ($max = $params['max'] ?? null) {
            $code .= 'if('.$count.'>'.$max.') break; ';
        }
        $code .= '?>';

        return $code;
    }

    protected function compileSECTIONELSE(): string
    {
        end($this->sectionStack);
        $this->sectionStack[key($this->sectionStack)]['else'] = true;

        return '<?php endfor; else: ?>';
    }

    protected function compileENDSECTION(): string
    {
        $section = array_pop($this->sectionStack);
        if (true === $section['else']) {
            return '<?php endif; ?>';
        }

        return '<?php endfor; endif; array_pop($smarty[\'section\']); ?>';
    }

    protected function compileFOREACH(string $params): string
    {
        $params = $this->parsePARAMS($params);
        $code = '';
        // Make sure we have the name and loop required parameters.
        if (($from = ($params['from'] ?? null)) && ($item = ($params['item'] ?? null))) {
            $name = $params['name'] ?? 'foreach_'.uniqid();
            $var = $this->compileVAR($from);
            $this->foreachStack[] = ['name' => $name, 'else' => false];
            $target = (($key = ($params['key'] ?? null)) ? '$'.$key.' => ' : '').'$'.$item;
        } elseif ('as' === ($params[1] ?? null)) { // Smarty 3 support
            $name = $params['name'] ?? 'foreach_'.uniqid();
            $var = $this->compileVAR($params[0] ?? '');
            $target = $params[2] ?? '$item';
            $this->foreachStack[] = ['name' => $name, 'else' => false];
        } else {
            return '';
        }
        $code = "<?php \$smarty['foreach']['{$name}'] = ['index' => -1, 'total' => (({$var} && is_array({$var}))?count({$var}):0)]; ";
        $code .= "if({$var} && is_array({$var}) && count({$var}) > 0): ";
        $code .= "foreach({$var} as {$target}): \$smarty['foreach']['{$name}']['index']++; ?>";

        return $code;
    }

    protected function compileFOREACHELSE(): string
    {
        end($this->foreachStack);
        $this->foreachStack[key($this->foreachStack)]['else'] = true;

        return '<?php endforeach; else: ?>';
    }

    protected function compileENDFOREACH(): string
    {
        $loop = array_pop($this->foreachStack);
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

    protected function compileCAPTURE(string $params): string
    {
        $params = $this->parsePARAMS($params);
        if (!array_key_exists('name', $params)) {
            return '';
        }
        $this->captureStack[] = $params;

        return '<?php ob_start(); ?>';
    }

    protected function compileENDCAPTURE(): string
    {
        $params = array_pop($this->captureStack);
        $code = '<?php $'.$this->compileVAR('smarty.capture.'.$params['name']);
        if (array_key_exists('assign', $params)) {
            $code .= ' = $'.$this->compileVAR($params['assign']);
        }

        return $code.' = ob_get_clean(); ?>';
    }

    protected function compileASSIGN(string $params): string
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

        return '<?php $'.trim($params['var'], '"\'')."={$value};?>";
    }

    protected function compileFUNCTION(string $params): string
    {
        $params = $this->parsePARAMS($params);
        if (!($name = $params['name'] ?? '')) {
            return '';
        }
        unset($params['name']);
        if (array_key_exists('params', $params) && is_array($params['params'])) {
            $compiledParams = implode(', ', array_map(function ($item, $key) {
                $value = empty($item) ? 'null' : (is_string($item) ? "'{$item}'" : $item);

                return '&$'.$key.'='.$value;
            }, $params['params'], array_keys($params['params'])));
        } else {
            $compiledParams = '';
        }

        return "<?php (\$this->functions['{$name}'] = function({$compiledParams}){ global \$smarty; ?>";
    }

    protected function compileENDFUNCTION(): string
    {
        return '<?php })->bindTo($this); ?>';
    }

    protected function compileFUNCTIONHANDLER(string $name, mixed $params): string
    {
        $params = empty($params) ? [] : $this->parsePARAMS($params);
        array_walk($params, function (&$item) {
            $item = '$' == substr($item, 0, 1) || is_numeric($item) ? '&'.$item : "'".trim($item, "'")."'";
        });
        $compiledParams = count($params) > 0 ? ', ['.implode(', ', $params).']' : '';

        return "<?php echo \$this->callFunctionHandler('{$name}'{$compiledParams}); ?>";
    }

    protected function compileCALL(string $params): string
    {
        $callParams = $this->parsePARAMS($params);
        if (isset($callParams[0])) {
            $callParams['name'] = $callParams[0];
        }
        $params = substr($params, strpos($params, ' ') + 1);
        if (!isset($callParams['name'])) {
            return '';
        }

        return $this->compileFUNCTIONHANDLER($callParams['name'], $params);
    }

    protected function compileINCLUDE(string $params): string
    {
        $params = $this->parsePARAMS($params);
        if (!array_key_exists('file', $params)) {
            return '';
        }
        $file = $params['file'];
        unset($params['file']);
        if ('/' !== $file[0] && !preg_match('/^\w+\:\/\//', $file)) {
            $file = realpath($this->cwd ? rtrim($this->cwd, ' /') : getcwd()).DIRECTORY_SEPARATOR.$file;
        }
        if (!file_exists($file)) {
            throw new IncludeFileNotFound($file);
        }
        $info = pathinfo($file);
        if (!(array_key_exists('extension', $info) && $info['extension'])
            && file_exists($file.'.tpl')) {
            $file .= '.tpl';
        }
        if (!file_exists($file)) {
            throw new IncludeFileNotFound($file);
        }
        $args = $this->compileARRAY($params);

        return "<?php \$this->include('{$file}', {$args}, '{$this->ldelim}', '{$this->rdelim}'); ?>";
    }
}
