# Less CSS

LESS is THE dynamic stylesheet language. LESS extends CSS with dynamic behavior such as variables, mixins, operations and functions. LESS runs as part of the style controller response, which automatically detects when a LESS file is being requested and internally compiles it into valid CSS.
For more information on LESS and it's syntax, see the LESS website.

## How do I use it?

Using LESS with HazaarMVC could not be easier. All you need to do to activate LESS is to save your stylesheet file in the same directory as your normal CSS files, except instead of using the .css extension, give them a .less extension. The style controller response object will do the rest. The only other thing you will need to do, is learn LESS.

To learn how to write LESS scripts, see: the LESS website.

This example LESS script will declare a few variables then replace them where needed.

```css
@base: 24px;
@border-color: #B2B;
@pad: 50px;
.underline { border-bottom: 1px solid green }
#header {
  color: black;
  border: 1px solid @border-color + <a href="http://git.funkynerd.com/hazaar/hazaar-mvc/issues/222222">#222222</a>;
  .navigation {
    font-size: @base / 2;
    a {
    .underline;
    }
  }
  .logo {
    width: 300px;
    :hover { text-decoration: none }
  }
}
.container { padding: @pad; }
```

This will generate the following CSS:

```css
.underline {
    border-bottom: 1px solid green;
}
#header {
    color: black;border: 1px solid #dd44dd;
}
#header .navigation {
    font-size: 12px;
}
#header .navigation a {
    border-bottom: 1px solid green;
}
#header .logo {
    width: 300px;
}
#header .logo :hover {
    text-decoration: none;
}
.container {
    padding: 50px;
}
```