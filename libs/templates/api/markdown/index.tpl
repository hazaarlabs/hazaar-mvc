{import "include/header.tpl"}

# {$project.title}

{$project.description}

This is an automatically generated documentation for **{{project.title}}**.

## Namespaces

{foreach $namespaces as $namespace}
### {$namespace->fullName()}
{if $namespace->classes}
#### Classes

| Class | Description |
|-------|-------------|
{foreach $namespace.classes as $class}
| {{ class.mdClassLink(class) }} | {{ class.summary|replace({'|': '&#124;'})|nl2br|replace({"\n": "", "\r": "", "\t": ""})|raw }}|
{/foreach}

{/if}
{/foreach}


{import "include/footer.tpl"}