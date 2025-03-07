{include file="../include/functions.tpl"}

### [{$property->name}](#{$property->name})
{if $property->brief}{$property->brief}{/if}
```php
{$property->access} {$property->type} ${$property->name} {if $property->value}= {value $property->value}{/if}
```
{if $property->detail}{$property->detail}{/if}