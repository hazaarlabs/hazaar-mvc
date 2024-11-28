{include file="include/functions.tpl"}

{include file="include/header.tpl"}

### [{$function->name}](#{$function->name})
{if $function->brief}{$function->brief}{/if}
```php
{$function->access} {$function->return} {$function->name}({{$function->params}})
```
{if $function->detail}{$function->detail}{/if}
{if $function->params}
#### Parameters
| Parameter | Type | Description |
|-----------|------|-------------|
{foreach $function->params as $param}| ```${$param->name}``` | ```{$param->type}``` | {$param->comment} |
{/foreach}
{/if}

{include file="include/footer.tpl"}