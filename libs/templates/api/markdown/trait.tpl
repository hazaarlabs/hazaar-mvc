{include file="include/functions.tpl"}

{include file="include/header.tpl"}

# {$trait->name}

{$trait->brief}

{$trait->detail}

{if $trait->constants}
## Constants

{foreach $trait->constants as $constant}
### [{$constant->name}](#{$constant->name})
{if $constant->brief}{$constant->brief}{/if}
```php
{$constant->access} const {$constant->name} = {$constant->value}
```
{if $constant->detail}{$constant->detail}{/if}
{/foreach}
{/if}

{if $trait->properties}
## Properties

{foreach $trait->properties as $property}
### [{$property->name}](#{$property->name})
{if $property->brief}{$property->brief}{/if}
```php
{$property->access} {$property->type} ${$property->name}
```
{if $property->detail}{$property->detail}{/if}
{/foreach}
{/if}

{if $trait->methods}
## Methods

{foreach $trait->methods as $method}
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