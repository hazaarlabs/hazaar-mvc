<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty;

use Hazaar\DateTime;
use Hazaar\Template\Exception\IncludeFileNotFound;
use Hazaar\Template\Smarty;

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
                $replacement = $this->replaceCONFIG_VAR(substr($matches[1], 1, -1));
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
            function render(array \$params = []): void {
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
     * @return array<mixed>
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
            list($left, $right) = $partParts;
            $right = trim(preg_replace_callback('/`(.*)`/', function ($matches) {
                return '{'.$this->compileVAR($matches[1]).'}';
            }, $right));
            $params[$left] = $this->parseVALUE($right);
        }

        return $params;
    }

    protected function parseVALUE(string $array): mixed
    {
        if (!('[' === substr($array, 0, 1) && ']' === substr($array, -1))) {
            if (preg_match("/'(.*)'$/", $array, $matches)) {
                return $matches[1];
            }

            return $array;
        }
        $compiledArray = [];
        $array = preg_split('/\s*,\s*(?![^\[]*\])/', substr($array, 1, -1));
        foreach ($array as &$item) {
            if (strpos($item, '=>')) {
                list($key, $value) = preg_split('/\s*=>\s*/', $item);
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

    /**
     * @param array<mixed> $array
     */
    protected function compileARRAY(array $array): string
    {
        $out = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $value = $this->compileARRAY($value);
            } else {
                $value = $this->compilePARAMS($value);
            }
            $out[] = "'{$key}' => ".$value;
        }

        return '['.implode(', ', $out).']';
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
        $this->sectionStack[] = ['name' => $name, 'else' => false];
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

    protected function compileFOREACH(mixed $params): string
    {
        $params = $this->parsePARAMS($params);
        $code = '';
        // Make sure we have the name and loop required parameters.
        if (($from = ake($params, 'from')) && ($item = ake($params, 'item'))) {
            $name = ake($params, 'name', 'foreach_'.uniqid());
            $var = $this->compileVAR($from);
            $this->foreachStack[] = ['name' => $name, 'else' => false];
            $target = (($key = ake($params, 'key')) ? '$'.$key.' => ' : '').'$'.$item;
            $code = "<?php \$smarty['foreach']['{$name}'] = ['index' => -1, 'total' => ((isset({$var}) && is_array({$var}))?count({$var}):0)]; ";
            $code .= "if(isset({$var}) && is_array({$var}) && count({$var}) > 0): ";
            $code .= "foreach({$var} as {$target}): \$smarty['foreach']['{$name}']['index']++; ?>";
        } elseif ('as' === ake($params, 1)) { // Smarty 3 support
            $name = ake($params, 'name', 'foreach_'.uniqid());
            $var = $this->compileVAR(ake($params, 0));
            $target = $this->compileVAR(ake($params, 2));
            $this->foreachStack[] = ['name' => $name, 'else' => false];
            $code = "<?php \$smarty['foreach']['{$name}'] = ['index' => -1, 'total' => ((isset({$var}) && is_array({$var}))?count({$var}):0)]; ";
            $code .= "if(isset({$var}) && is_array({$var}) && count({$var}) > 0): ";
            $code .= "foreach({$var} as {$target}): \$smarty['foreach']['{$name}']['index']++; ?>";
        }

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

    protected function compileCAPTURE(mixed $params): string
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

        return "<?php\n echo \$this->callFunctionHandler('{$name}'{$compiledParams});\n?>";
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

        return $this->compileFUNCTIONHANDLER($call_params['name'], $params);
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
