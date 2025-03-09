{include file="../include/functions.tpl"}

{if $header || $header !== false}### [{$method->name}](#{$method->name}){/if}
{if $method->brief}{$method->brief}{/if}
```php
{$method->access} {$method->name}({params $method->params}): {return $method->returns}
```
{if $method->detail}{$method->detail}{/if}
{if $method->params}
#### Parameters
| Parameter | Type | Description |
|-----------|------|-------------|
{foreach $method->params as $param}| ```${$param->name}``` | ```{$param->type}``` | {$param->comment} |
{/foreach}
{/if}
