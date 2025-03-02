<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty;

use Hazaar\Template\Smarty;

class Compiler
{
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
    private string $ldelim = '{';
    private string $rdelim = '}';
    private string $compiledTemplate;

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

    public function __construct(string $ldelim = '{', string $rdelim = '}')
    {
        $this->ldelim = $ldelim;
        $this->rdelim = $rdelim;
    }

    public function exec(?string $content = null): string
    {
        $compiledContent = preg_replace(['/\<\?/', '/\?\>/'], ['&lt;?', '?&gt;'], $content);
        $regex = '/\\'.$this->ldelim.'([#\$\*][^\}]+|(\/?\w+)\s*([^\}]*))\\'.$this->rdelim.'(\r?\n)?/';
        $literal = false;
        $strip = false;

        return $this->compiledTemplate = preg_replace_callback($regex, function ($matches) use (&$literal, &$strip) {
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
        }, $compiledContent);
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
        if (!($name = trim(ake($params, 'name'), '\'"'))) {
            return '';
        }
        unset($params['name']);
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
        $params = empty($params) ? [] : $this->parsePARAMS($params);
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
        $code .= "echo \$this->functions['{$name}']({$compiledParams});\n?>";

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
