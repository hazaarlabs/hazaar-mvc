{function name=sort params=['array']}
    {php}ksort($array);{/php}
{/function}

{function name=link params=['root','name','text']}{php}echo "[$text](/$root/".str_replace('\\', '/', ltrim($name, '\\')).")";{/php}{/function}