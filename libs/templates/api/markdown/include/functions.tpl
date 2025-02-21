{function name=sort params=['array']}
    {php}ksort($array);{/php}
{/function}

{function name=osort params=['array']}
    {php}usort($array, function($a, $b){ return strcasecmp($a->name, $b->name); });{/php}
{/function}

{function name=link params=['object']}{php}
    $root = match(true){
    $object instanceof \Hazaar\Parser\PHP\ParserInterface => 'interface',
    $object instanceof \Hazaar\Parser\PHP\ParserClass => 'class',
    $object instanceof \Hazaar\Parser\PHP\ParserTrait => 'trait',
    $object instanceof \Hazaar\Parser\PHP\ParserFunction => 'function',
    $object instanceof \Hazaar\Parser\PHP\ParserConstant => 'constant',
    default => 'namespace'
    };
    $name = $object->fullName;
    $text = $object->name;
    echo "[$text](/api/$root/".str_replace('\\', '/', ltrim($name, '\\')).".md)";
{/php}{/function}

{function name=return params=['value']}{php}
    echo $value ? ($value->isNullable ? '?' : '').$value->type : 'void';
{/php}{/function}

{function name=value params=['value']}{php}
    $type = gettype($value);
    echo match($type){
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
        if(isset($param->default)){
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
    echo implode(', ', $items);
{/php}{/function}