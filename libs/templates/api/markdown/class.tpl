{include file="../include/functions.tpl"}

{include file="../include/header.tpl"}

# {$class->name}

{$class->brief}

{$class->detail}

{if $class->constants}
## Constants

{foreach $class->constants as $constant}
### [{$constant->name}](#{$constant->name})
{if $constant->brief}{$constant->brief}{/if}
```php
{$constant->access} const {$constant->name} = {value $constant->value}
```
{if $constant->detail}{$constant->detail}{/if}
{/foreach}
{/if}

{if $class->properties}
## Properties

{foreach $class->properties as $property}
{include file="property.tpl" property=$property}
{/foreach}
{/if}

{if $class->methods}
## Methods

{foreach $class->methods as $method}
{include file="method.tpl" method=$method}
{/foreach}
{/if}

{include file="../include/footer.tpl"}