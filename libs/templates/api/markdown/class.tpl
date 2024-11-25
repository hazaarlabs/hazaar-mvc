{include file="include/functions.tpl"}

{include file="include/header.tpl"}

# {$name}

{$description}

## Table of Contents

{if $properties}
## Properties

{foreach $properties as $property}
### [{$property->name}](#{$property->name})
{if $property->brief} - {$property->brief}{/if}
```php
{$property->access} {$property->type} ${$property->name}
```
{if $property->detail} - {$property->detail}{/if}
{/foreach}
{else}
No properties defined.
{/if}

{if $methods}
{osort $methods}
## Methods

{foreach $methods as $method}> [{$method->name}](#{$method->name})
{if $method->description} - {$method->description}{/if}
{/foreach}
{else}
No methods defined.
{/if}

{if $properties}
### Properties
{foreach $properties as $property}
#### {$property->name}

{$property->description}

**Type:** {$property->type}

{if $property->default}**Default:** {$property->default}{/if}

{if $property->required}**Required:** Yes{/if}

{/foreach}
{/if}

{include file="include/footer.tpl"}