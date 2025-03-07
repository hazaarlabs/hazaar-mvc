{include file="../include/functions.tpl"}
{sort $functions}
{sort $interfaces}
{sort $traits}
{sort $classes}
{sort $namespaces}
{if $namespaces}
    {
        text: "Namespaces"
    },
    {foreach $namespaces as $namespace}
        {
            text: "{$namespace->name}",
            collapsible: true,
            icon: "tip",
            children: [
                {include file="namespace.tpl" namespaces=$namespace->namespaces functions=$namespace->functions interfaces=$namespace->interfaces traits=$namespace->traits classes=$namespace->classes}
            ]
        },
    {/foreach}
{/if}
{if $classes}
    {
        text: "Classes"
    },
{foreach $classes as $class}
    {
        text: "{$class->name}",
        link: "/api/class/{$class->fullName|replace:'\\':'/'|trim:'/'}",
        collapsible: true,
    },
{/foreach}
{/if}
{if $functions}
    {
        text: "Functions"
    },
{foreach $functions as $function}
    {
        text: "{$function->name}",
        link: "/api/function/{$function->fullName|replace:'\\':'/'|trim:'/'}"
    },
{/foreach}
{/if}
{if $interfaces}
    {
        text: "Interfaces"
    },
{foreach $interfaces as $interface}
    {
        text: "{$interface->name}",
        link: "/api/interface/{$interface->fullName|replace:'\\':'/'|trim:'/'}",
        collapsible: true,
    },
{/foreach}
{/if}
{if $traits}
    {
        text: "Traits"
    },
{foreach $traits as $trait}
    {
        text: "{$trait->name}",
        link: "/api/trait/{$trait->fullName|replace:'\\':'/'|trim:'/'}",
        collapsible: true,
    },
{/foreach}
{/if}

