# View Helpers

::: danger
This page is tagged as a draft and is a work in progress.  It is not yet complete and may contain errors or inaccuracies.
:::

View helpers provide a way to programatically generate views and layouts using PHP.  A helper can be created that provides one or more functions that returns a displayable object such as a `Hazaar\Html\Div`, `Hazaar\Html\Span` or any other object derrived from the `Hazaar\Html\Element` class.

There are a number of view helpers built into Hazaar MVC, such as:

* `html` for easily generating HTML objects programatically, simplifying the interaction between PHP code, variables and the final HTML output.
* `jquery` which easily configures jQuery for use in a view by automatically linking the JavaScript library required and adding custom JavaScript code.
* `fontawesome` will link the FontAwesome library to the selected view, optionally allowing a specific version to be selected.
* `application` which provides direct access to the application object.  While this does not return displayable object it is useful for accessing the application configuration.

## Adding a View Helper

Adding a view helper is done in the controller, usually around the same time the view is being selected.  This is done by calling `$this->view->addHelper('name')` on the controller.

For example, to link a view and add the Font Awesome view helper:

```php
<?php

class MyController extends \Hazaar\Controller\Action {

    public function index(){

        $this->view('index');

        $this->view->addHelper('fontawesome');

    }

}
```

### Optional Arguments

Some view helpers will support initialisation arguments.  This depends on the view helper itself so you will need to review the documentation for the view helper you are adding.

For example, the FontAwesome view helper supports selecting a specific version (the latest version is selected by default).  To select the version we provide an options array that contains a `version` element with a value of the version we want to use.

```php
<?php

class MyController extends \Hazaar\Controller\Action {

    public function index(){

        $this->view('index');

        $this->view->addHelper('fontawesome', ['version' => '5.0.0']);

    }

}
```

## Using a view helper

View helpers are accessible from within the view context, which means that all you need to do is call methods on `$this->viewhelpername` from inside your view source file.

For example, to use the HTML view helper to generate a div that uses a view data variable:

```php
<h1>Example</h1>
<?=$this->html->div($this->myString)->class('example-class');?>
```

::: info
The HTML view helper uses the `Hazaar\Html\Element` child classes which support chaining.  This is a feature of the HTML classes, not of the view helpers however, it is suggested that view helpers output `Hazaar\Html\Element` classes where possible.
:::

# Using the Textile View Helper

Textile is a simple text markup. Simple symbols mark words' emphasis. Blocks of text can be easily tagged as headers, quotes, or lists. A Textile document can then be converted to HTML for viewing on the web.

You can try Textile out on the Textile home page.

## Usage

### Block modifier syntax

#### Header: h(1-6).

Paragraphs beginning with 'hn. ' (where n is 1-6) are wrapped in header tags.

```
h1. Header...
```

##### Outputs:

```
<h1>Header...</h1>
```

#### Paragraph: p. (also applied by default)

```
p. Text
```

##### Output:

```
<p>Text</p>
```

#### Blockquote: bq.

```
bq. Block quotation...
```

##### Output:

```
<blockquote>Block quotation...</blockquote>
```

#### Blockquote with citation: bq.:http://citation.url

```
bq.:http://textism.com/ Text...
```

##### Output:

```
<blockquote cite="http://textism.com">Text...</blockquote>
```

#### Footnote: fn(1-100).

```
fn1. Footnote... 
```

##### Output:

```
<p id="fn1">Footnote...</p>
```

#### Numeric list: #, ##

Consecutive paragraphs beginning with # are wrapped in ordered list tags.

```
<ol><li>ordered list</li></ol>
```

#### Bulleted list: *, **

Consecutive paragraphs beginning with * are wrapped in unordered list tags.

```
<ul><li>unordered list</li></ul>
```

#### Definition list: Terms ;, ;;

##### Definitions :, ::

Consecutive paragraphs beginning with ; or : are wrapped in definition list tags.

```
<dl><dt>term</dt><dd>definition</dd></dl>
```

Redcloth-style Definition list:

* Term1 := Definition1
* Term2 := Extended
* definition =:

#### Phrase modifier syntax

#### emphasis

```
<em>emphasis</em>
```

#### italic

```
<i>italic</i>
```

#### strong

```
<strong>strong</strong>
```

#### bold

```
<b>bold</b>
```

#### citation

```
<cite>citation</cite>
```

#### deleted text

```
<del>deleted</del>
```

#### inserted text

```
<ins>inserted</ins>
```

#### superscript

```
<sup>superscript</sup>
```

#### subscript

```
<sub>subscript</sub>
```

#### code

```
<code>computer code</code>
```

#### span

```
<span class="bob">span</span>
```

#### notextile

```
leave text alone (do not format)
```

#### linktext

```
<a href="url">linktext</a>
```

#### linktext

```
<a href="url" title="title">linktext</a>
```

#### url

```
<a href="url">url</a>
```

#### url

```

<a href="url" title="title">url</a>
<img src="imageurl" />
<img src="imageurl" alt="alt text" />
<a href="linkurl"><img src="imageurl" /></a>
```


#### ABC

```
<acronym title="Always Be Closing">ABC</acronym>
```

### Linked Notes:

Allows the generation of an automated list of notes with links.

Linked notes are composed of three parts, a set of named definitions, a set of references to those definitions and one or moreplaceholders indicating where the consolidated list of notes is to be placed in your document.

#### Definitions

Each note definition must occur in its own paragraph and should look like this...

```

note#mynotelabel. Your definition text here.
```


You are free to use whatever label you wish after the # as long as it is made up of letters, numbers, colon(:) or dash(-).

#### References

Each note reference is marked in your text like this1 and it will be replaced with a superscript reference that links into the list of note definitions.

#### List Placeholder(s).

The note list can go anywhere in your document. You have to indicate where like this...

```
notelist.
```

notelist can take attributes (class#id) like this: notelist(class#id).

By default, the note list will show each definition in the order that they are referenced in the text by the references. It will show each definition with a full list of backlinks to each reference. If you do not want this, you can choose to override the backlinks like this...

```

notelist(class#id)!.    Produces a list with no backlinks.
notelist(class#id)^.    Produces a list with only the first backlink.
```


Should you wish to have a specific definition display backlinks differently to this then you can override the backlink method by appending a link override to the definition you wish to customise.

```

note#label.    Uses the citelist's setting for backlinks.
note#label!.   Causes that definition to have no backlinks.
note#label^.   Causes that definition to have one backlink (to the first ref.)
note#label*.   Causes that definition to have all backlinks.
```


Any unreferenced notes will be left out of the list unless you explicitly state you want them by adding a '+'. Like this...

```

notelist(class#id)!+. Giving a list of all notes without any backlinks.
```


You can mix and match the list backlink control and unreferenced links controls but the backlink control (if any) must go first. Like so: notelist^+. , not like this: notelist+^.

##### Example

```

Scientists say2 the moon is small.

notelist(myclass#myid)+.
```


Would output (the actual IDs used would be randomised)...

```
html
<p>Scientists say<sup><a href="#def_id_1" id="ref_id_1a">1</sup> the moon is small.</p>
<ol class="myclass" id="myid">
  <li class="myliclass"><a href="#ref_id_1a"><sup>a</sup></a><span id="def_id_1"> </span><a href="url">Proof</a> of a small moon.</li>
  <li>An unreferenced note.</li>
</ol>
```


The 'a b c' backlink characters can be altered too. For example if you wanted the notes to have numeric backlinks starting from 1:

```

notelist:1.
```


### Table syntax

#### Simple tables

```

|a|simple|table|row|
|And|Another|table|row|
|With an||empty|cell|
|=. My table caption goes here  (NB. Table captions *must* be the first line of the table else treated as a center-aligned cell.)
|_. A|_. table|_. header|_.row|
|A|simple|table|row|
```


#### Tables with attributes

```

table{border:1px solid black}. My table summary here
{background:#ddd;color:red}. |{}| | | |
```


To specify thead / tfoot / tbody groups, add one of these on its own line above the row(s) you wish to wrap (you may specify attributes before the dot):

```

|^.     # thead
|-.     # tbody
|~.     # tfoot
```


#### Column groups

```

|:\3. 100|
```


Becomes:

```
html
<colgroup span="3" width="100"></colgroup>
```


You can omit either or both of the \N or width values. You may also add cells after the colgroup definition to specify col elements with span, width, or standard Textile attributes:

```

|:. 50|(firstcol). |\2. 250||300|
```


Becomes:

```
html
<colgroup width="50">
  <col class="firstcol" />
  <col span="2" width="250" />
  <col />
  <col width="300" />
</colgroup>
```


::: info
Note that, per the HTML specification, you should not add span to the colgroup if specifying col elements.)
:::

### Applying Attributes

Most anywhere Textile code is used, attributes such as arbitrary css style, css classes, and ids can be applied. The syntax is fairly consistent.

The following characters quickly alter the alignment of block elements:

#### Left align <

```
p<. left-aligned para
```

#### Right align >

```
h3>. right-aligned header 3
```

#### Centred =

```
h4=. centred header 4
```

#### Justified <>

```
p<>. justified paragraph
```

These will change vertical alignment in table cells:

#### Top ^

```
|^. top-aligned table cell|
```

#### Middle -

```
|-. middle aligned|
```

#### Bottom ~

```
|~. bottom aligned cell|
```

Plain (parentheses) inserted between block syntax and the closing dot-space indicate classes and ids:

```
p(hector). paragraph -> <p class="hector">paragraph</p>
```

```
p(#fluid). paragraph -> <p id="fluid">paragraph</p>
```

```

(classes and ids can be combined)
p(hector#fluid). paragraph -> <p class="hector" id="fluid">paragraph</p>
```


Curly {brackets} insert arbitrary css style

```
p{line-height:18px}. paragraph -> <p style="line-height:18px">paragraph</p>
```

```
h3{color:red}. header 3 -> <h3 style="color:red">header 3</h3>
```

Square [brackets] insert language attributes

```
p[no]. paragraph -> <p lang="no">paragraph</p>
```

```
%[fr]phrase% -> <span lang="fr">phrase</span>
```

Usually Textile block element syntax requires a dot and space before the block begins, but since lists don't, they can be styled just using braces

```

#{color:blue} one
# big                    
# list                   
```


```

<ol style="color:blue">
<li>one</li>
<li>big</li>
<li>list</li>
</ol>
```


Using the span tag to style a phrase

```
It goes like this, %{color:red}the fourth the fifth%
```

```
It goes like this, <span style="color:red">the fourth the fifth</span>
```

### Ordered List Start & Continuation

You can control the start attribute of an ordered list like so;

```

<a href="http://git.funkynerd.com/hazaar/hazaar-mvc/issues/5">#5</a> Item 5
# Item 6
```


You can resume numbering list items after some intervening anonymous block like so...

```

#_ Item 7
# Item 8
```


# Custom View Helpers

Custom view helpers allow application developers to implement their own view helpers.  This is now the recommended way of implementing re-usable code that directly generates HTML output.  Custom view helpers are implemented in the exact same way as the built-in view helpers and therefore have access to all the same functionality.

::: warning
It is possible to override built-in view helpers by creating custom view helpers with the same name.  This is by design to allow developers that are not happy with a built-in view helper to create their own implementations.  However this can also not be what was intended so *be careful* when selecting custom view helper names.
:::

## Creating a custom view helper

By default, custom view helpers are stored in the *helpers* application directory in a sub-directory names *views* (in future there may be other types of helpers).  If you do no have a *helpers/view* directory in your application directory you will need to create that before continuing.

Create a new *.php* file in the *helpers/view* directory called *Example.php* (note the first uppercase character as per the standard naming convention).  Custom view helpers need to exist in the application namespace so set the namespace to `Application\Helper\View` and create a new class called `Example` that extends the `Hazaar\View\Helper` abstract base class.

```php
<?php

namespace Application\Helper\View;

class Example extends \Hazaar\View\Helper {

  public function tag($label){

    return $this->html->div([$this->html->h1('Example Tag'), $this->html->div($label)]);

  }

}
```

The above view helper provides a method names `tag()` that takes a sinle argument and returns a DIV object that contains a H1 header and another DIV containing the label.  This is a very simplistic example but it is enough for this demonstration.

## Using a custom view helper

Using a custom view helper is EXACTLY the same as using a built-in view helper.  So to use our new *example* view helper we add it to our view. 

```php
<?php

class MyController extends \Hazaar\Controller\Action {

    public function index(){

        $this->view('index');

        $this->view->addHelper('example');

    }

}
```

Once the view helper has been added to the view we can then access the methods it provides as a normal view helper:

```php
<h1>Example</h1>
<?=$this->example->tag('Hello, World');?>
```
