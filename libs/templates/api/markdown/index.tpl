{include file="include/functions.tpl"}

{include file="include/header.tpl"}

# {$project.title}

{$project.description}

This is an automatically generated documentation for **{$project.title}**.

## Namespaces

{sort $namespaces}
{foreach $namespaces as $namespace}
### {$namespace->fullName}

{if $namespace->classes}
#### Classes
{sort $namespace->classes}
| Class | Description |
|-------|-------------|
{foreach $namespace->classes as $class}| {link $class} | {$class->brief}|
{/foreach}
{/if}

{if $namespace->interfaces}
#### Interfaces
{sort $namespace->interfaces}
| Interface | Description |
|-----------|-------------|
{foreach $namespace->interfaces as $interface}| {link $interface} | {$interface->brief}|
{/foreach}
{/if}

{if $namespace->traits}
#### Traits
{sort $namespace->traits}
| Trait | Description |
|-------|-------------|
{foreach $namespace->traits as $trait}| {link $trait} | {$trait->brief}|
{/foreach}
{/if}

{if $namespace->functions}
#### Functions
{sort $namespace->functions}
| Function | Description |
|----------|-------------|
{foreach $namespace->functions as $function}| {link $function} | {$function->brief}|
{/foreach}
{/if}

{if $namespace->constants}
#### Constants
{sort $namespace->constants}
| Constant | Description |
|----------|-------------|
{foreach $namespace->constants as $constant}| {link $constant} | {$constant->brief}|
{/foreach}
{/if}

{/foreach}

{if $classes}
## Classes
{sort $classes}
| Class | Description |
|-------|-------------|
{foreach $classes as $class}| {link $class} | {$class->brief}|
{/foreach}
{/if}

{if $interfaces}
## Interfaces
{sort $interfaces}
| Interface | Description |
|-----------|-------------|
{foreach $interfaces as $interface}| {link $interface} | {$interface->brief}|
{/foreach}
{/if}

{if $traits}
## Traits
{sort $traits}
| Trait | Description |
|-------|-------------|
{foreach $traits as $trait}| {link $trait} | {$trait->brief}|
{/foreach}
{/if}

{if $functions}
## Functions
{sort $functions}
| Function | Description |
|----------|-------------|
{foreach $functions as $function}| {link $function} | {$function->brief}|
{/foreach}
{/if}

{if $constants}
## Constants
{sort $constants}
| Constant | Description |
|----------|-------------|
{foreach $constants as $constant}| {link $constant} | {$constant->brief}|
{/foreach}
{/if}

{include file="include/footer.tpl"}