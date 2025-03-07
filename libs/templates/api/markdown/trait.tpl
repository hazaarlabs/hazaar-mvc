{include file="../include/functions.tpl"}

{include file="../include/header.tpl"}

# {$trait->name}

{$trait->brief}

{$trait->detail}

{if $trait->constants}
## Constants

{foreach $trait->constants as $constant}
### [{$constant->name}](#{$constant->name})
{if $constant->brief}{$constant->brief}{/if}
```php
{$constant->access} const {$constant->name} = {value $constant->value}
```
{if $constant->detail}{$constant->detail}{/if}
{/foreach}
{/if}

{if $trait->properties}
## Properties

{foreach $trait->properties as $property}
{include file="property.tpl" property=$property}
{/foreach}
{/if}

{if $trait->methods}
## Methods

{foreach $trait->methods as $method}
{include file="method.tpl" method=$method}
{/foreach}
{/if}

{include file="../include/footer.tpl"}