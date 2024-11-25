{include file="include/functions.tpl"}

{include file="include/header.tpl"}

# {$project.title}

{$project.description}

This is an automatically generated documentation for **{$project.title}**.

## Namespaces

{sort $namespaces}
{foreach $namespaces as $namespace}
### {$namespace->fullName()}
{if $namespace->classes}
#### Classes
{sort $namespace->classes}
{assign var=type value='class'}
| Class | Description |
|-------|-------------|
{foreach $namespace->classes as $class}| {link $type $class->fullName() $class->name} | {$class->description()}|
{/foreach}

{/if}
{/foreach}

{include file="include/footer.tpl"}