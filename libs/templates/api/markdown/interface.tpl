{include file="include/functions.tpl"}

{include file="include/header.tpl"}

# {$interface->name}

{$interface->brief}

{$interface->detail}

{if $interface->methods}
## Methods

{foreach $interface->methods as $method}
### [{$method->name}](#{$method->name})
{if $method->brief}{$method->brief}{/if}
```php
{$method->access} {$method->return} {$method->name}({{$method->params}})
```
{if $method->detail}{$method->detail}{/if}
{if $method->params}
#### Parameters
| Parameter | Type | Description |
|-----------|------|-------------|
{foreach $method->params as $param}| ```${$param->name}``` | ```{$param->type}``` | {$param->comment} |
{/foreach}
{/if}

{/foreach}
{/if}

{include file="include/footer.tpl"}