{include file="include/functions.tpl"}

{include file="include/header.tpl"}

# {$class->name}

{$class->brief}

{$class->detail}

{if $class->constants}
## Constants

{foreach $class->constants as $constant}
### [{$constant->name}](#{$constant->name})
{if $constant->brief}{$constant->brief}{/if}
```php
{$constant->access} const {$constant->name} = {$constant->value}
```
{if $constant->detail}{$constant->detail}{/if}
{/foreach}
{/if}

{if $class->properties}
## Properties

{foreach $class->properties as $property}
### [{$property->name}](#{$property->name})
{if $property->brief}{$property->brief}{/if}
```php
{$property->access} {$property->type} ${$property->name}
```
{if $property->detail}{$property->detail}{/if}
{/foreach}
{/if}

{if $class->methods}
## Methods

{foreach $class->methods as $method}
### [{$method->name}](#{$method->name})
{if $method->brief}{$method->brief}{/if}
```php
{$method->access} {$method->name}({$method->params|implode:, }): {$method->returns}
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