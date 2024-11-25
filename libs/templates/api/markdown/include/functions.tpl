{function name=sort params=['array']}
    {php}ksort($array);{/php}
{/function}

{function name=osort params=['array']}
    {php}usort($array, function($a, $b){ return strcasecmp($a->name, $b->name); });{/php}
{/function}

{function name=link params=['root','name','text']}{php}echo "[$text](/api/$root/".str_replace('\\', '/', ltrim($name, '\\')).")";{/php}{/function}