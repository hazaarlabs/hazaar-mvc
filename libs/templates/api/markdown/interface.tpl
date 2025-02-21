{include file="include/functions.tpl"}

{include file="include/header.tpl"}

# {$interface->name}

{$interface->brief}

{$interface->detail}

{if $interface->methods}
## Methods

{foreach $interface->methods as $method}
{include file="method.tpl" method=$method}
{/foreach}
{/if}

{include file="include/footer.tpl"}