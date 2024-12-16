# Application Views

Content is yet to come...

All of the below API may change as it has not yet been finalised!

## Adding Styles

As a block

```php
<?=$this->html->style('body')->set('background', 'black');?>
```

As a parameter

```php
<?=$this->html->div('Content')->style($this->html->style()->set('background', 'black'));?>
```