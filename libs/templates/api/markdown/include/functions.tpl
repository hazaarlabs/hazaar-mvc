{function name=sort params=['array']}
    {php}ksort($array);{/php}
{/function}

{function name=osort params=['array']}
    {php}usort($array, function($a, $b){ return strcasecmp($a->name, $b->name); });{/php}
{/function}

{function name=link params=['object']}{php}
    $class = get_class($object);
    $root = match($class){
        'Hazaar\Parser\PHP\ParserInterface' => 'interface',
        'Hazaar\Parser\PHP\ParserTrait' => 'trait',
        'Hazaar\Parser\PHP\ParserClass' => 'class',
        'Hazaar\Parser\PHP\ParserFunction' => 'function',
        'Hazaar\Parser\PHP\ParserConstant' => 'constant',
        default => 'namespace'
    };
    $name = $object->fullName;
    $text = $object->name;
    return "[$text](/api/$root/".str_replace('\\', '/', ltrim($name, '\\')).".md)";
{/php}{/function}

{function name=return params=['value']}{php}
    return $value ? ($value->isNullable ? '?' : '').$value->type : 'void';
{/php}{/function}

{function name=value params=['value']}{php}
    $type = gettype($value);
    return match($type){
        'boolean' => $value ? 'true' : 'false',
        'integer', 'double' => $value,
        'string' => "'$value'",
        'array' => var_export($value, true),
        'object' => 'object',
        'NULL' => 'null',
        default => 'unknown'
    };
{/php}{/function}

{function name=params params=['params']}{php}
    $items = [];
    foreach($params as $param){
        $item = ($param->isNullable ? '?' : '')
            . $param->type.' $'.$param->name;
        if($param->default){
            $type = gettype($param->default);
            $item .= ' = ' . match($type){
                'boolean' => $param->default ? 'true' : 'false',
                'integer','double' => $param->default,
                'string' => "'$param->default'",
                'array' => '[]',
                'object' => '{}',
                'NULL' => 'null',
                default => 'unknown'
            };
        }
        $items[] = $item;
    }
    return implode(', ', $items);
{/php}{/function}