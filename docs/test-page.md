# Test Page

This page is used for testing features of the Hazaar CODEX page renderer.

## Markdown

Markdown is fully supported so we can `embed` inline code in paragraphs, as well as [links to other pages](index.md) that will automatically be referenced correctly.

Some links:

* [External Link](http://www.google.com)
* [Internal Page Link](index.md)
* [Class Link](\Hazaar\Application)

> Create blockquote sections.

Make bullet point lists of stuff:

* Item 1
* Item 2
* Item 3

```php
function runTest($string = null){

    echo 'We can even render code blocks!';

}
```

We can even automatically reference links to namespaces, classes and functions such as:

* Hazaar\Application
* ake()
* array_to_dot_notation($array)
* errorAndDie('My Message').

## Extensions

Markdown supports extensions.  Currently I have implemented the Admonition extension which will render notice blocks as per [this page](https://github.com/Python-Markdown/markdown/blob/master/docs/extensions/admonition.md).

As such, you can create the following:

!!! notice
    This is a simple notice

!!! danger
    This is an error admonition!

Available classes are as standard bootstrap notice classes, such as:

!!! notice
    This is a notice.

!!! warning
    This is a warning.

!!! danger
    This is something dangerous!

!!! success
    This is for great success!

Enjoy!