# Using WebDAV and CalDAV

::: danger
This page is tagged as a draft and is a work in progress.  It is not yet complete and may contain errors or inaccuracies.
:::

## WebDAV

WebDAV is an extension to the HTTP protocol that allows accessing properties of resources provided by the web server. Resources such as files and directories. The Hazaar WebDAV class provides methods to more simply access these resources by taking care of protocol particulars and exposing a few simple methods to query information.

For more information on WebDAV, see [RFC 4918](https://tools.ietf.org/html/rfc4918).

### PROPFIND

The first method that WebDAV provides is called PROPFIND. This method does as advertised and find properties for available resources. By default the PROPFIND method will return properties for the requested resource only. However if the depth argument is supplied and is 1, any resources contained in the requested collection resource will also be returned. In the context of a file browser, this is sort of like doing a "dir()" call to get a list of files and directories.

The WebDAV class in Hazaar will perform the PROPFIND request on the target web server and return the result.

For example:

```php
$dav = new \Hazaar\Http\WebDAV(['baseuri' => 'http://localhost/dav']);
$dir = $dav->propfind('/', [
    'displayname',
    'creationdate',
    'getcontenttype'
], 1);
```

In the above example, $dir will be an array where the keys are the name of the resource (ie: /dav/myimagefile.png) and the value is an array containing the requested properties, in this case displayname, creationdate and getcontenttype. If null, or an empty array is provided as the second argument the WebDAV class will automatically request all properties (ie: Using theALLPROPS property).

### PROPPATCH

The Hazaar WebDAV class also exposes the PROPPATCH method which is used to update properties on resources.

For example:

```php
$dav = new \Hazaar\Http\WebDAV(['baseuri' => 'http://localhost/dav']);
$dir = $dav->proppatch('/myimagefile.png', ['displayname' => 'My Image File']);
This call will update the displayname property of the myimagefile.png resource.
```

### MKCOL

The MKCOL method is used to create collections, ie: directories. Once a collection has been created then resources can be stored inside the collection.

```php
$dav = new \Hazaar\Http\WebDAV(['baseuri' => 'http://localhost/dav']);
$dav->mkcol('/images');
```

This example creates an images collection on the root collection.

### PUT

The PUT method is used to create a new resource at the specified URI. This can be used to effectively 'upload' a file to the web server.

```php
$dav = new \Hazaar\Http\WebDAV(['baseuri' => 'http://localhost/dav']);
$file = file_get_contents('/path/to/imagefile.png');
$dav->put('/images/myuploadedimage.png', $file, 'image/png');
```

In the above example we upload a file to the images collection that we previously created.

Alternatively, to simplify the process a little, the putFile() method has been provided to allow using a \Hazaar\File object. The advantage of this is that it takes care of reading the file contents and the content type argument.

```php
$dav = new \Hazaar\Http\WebDAV(['baseuri' => 'http://localhost/dav']);
$file = new \Hazaar\File('/path/to/imagefile.png');
$dav->putFile('/images/myuploadedimage.png', $file);
```

If the above resource exists, then it will be overwritten.

### DELETE

The last method that is provided is the DELETE method which is used to, you guessed it, delete resources.

So to delete the resource we just created you can do this:

```php
$dav = new \Hazaar\Http\WebDAV(['baseuri' => 'http://localhost/dav']);
$dav->delete('/images/myuploadedimage.png');
```

Keep in mind that if the resource you are deleting is a collection then all resources contained in the collection are deleted as well.

## CalDAV

CalDAV is an extension of WebDAV that was created to work with calendaring resources.

For more information on CalDAV see [RFC 4791](https://tools.ietf.org/html/rfc4791)

```php
$dav = new \Hazaar\Http\CalDAV(['baseuri' => 'http://localhost/caldav']);
$dav->getEvents('personal', [
    'time-range' => [
        'start' => new \Hazaar\Date('2013-10-01'),
        'end' => new \Hazaar\Date('2013-11-01')
     )
));
```

This example will return all events in the date range specified by the start and end filters.