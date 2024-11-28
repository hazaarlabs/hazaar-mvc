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
    echo "[$text](/api/$root/".str_replace('\\', '/', ltrim($name, '\\')).")";
{/php}{/function}