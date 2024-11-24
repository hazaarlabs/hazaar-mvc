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
{foreach $namespace->classes as $class}| [{$class->name}]({$class->fullName()}) | {$class->description()}|
{/foreach}

{/if}
{/foreach}


{import "include/footer.tpl"}