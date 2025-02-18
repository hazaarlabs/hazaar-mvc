# The Filesystem Browser

::: danger
This page is tagged as a draft and is a work in progress.  It is not yet complete and may contain errors or inaccuracies.
:::

The common filesystem browser is a frontend class that allows the use of a common set of methods for accessing file metadata and performing common file operations such as copy, move, etc. The benefit here is that multiple backends are supported.

## Supported Backends

Currently, the following backends are supported:

* `local` - Access to the local host filesystem
* `dropbox` - Access to an application folder on the Dropbox file sync service.

We have plans to implement backends for the following service providers at some point in the future:

* Google Drive - https://developers.google.com/drive/
* Ubuntu One - https://one.ubuntu.com/developer/
* Microsoft Skydrive - http://msdn.microsoft.com/en-us/library/live/hh826521.aspx
* SugarSync - https://www.sugarsync.com/developer
* Box - http://developers.box.com/

Our own propriatary file storage service. - Based on HazaarMVC and MongoDB.

## Configuring a Backend

You can configure the backend at the time of instantiation:

```php
$options = ['app_key' => 'mn3b45kjv543jhg54', 'app_secret' => '89867zxcas98da'];
$browser = new \Hazaar\File\Browser('dropbox', $options);
```

Or, if you find that you are doing this over and over again you can configure some defaults in your bootstrap.php file as follows:

```php
$options = ['app_key' => 'mn3b45kjv543jhg54', 'app_secret' => '89867zxcas98da'];
Hazaar\File\Browser::configure('dropbox', $options);
```

This will set the default backend to 'dropbox' and set it's default options. You can then just use it as:

```php
$browser = new \Hazaar\File\Browser();
```

## Authenticating the Backend

Most backends are designed to work with public service providers, such as Dropbox or Google Drive. What these services have in common is that they require the user to authenticate to allow the application access to their files. This means redirecting them to a web page provided by the service provider where they user can click on an 'Allow Access' link or some such thing. At that point they are redirected back to the application where authentication is completed. We've tried to make this process as painless as possible.

The theory is that you check to see if the service is authenticated, if it's not, you redirect, then we the page is redirected back you check again. Hopefully at this point the application is authorised to use the service.

### authenticate($token, $redirect_url)

The `authenticate()` method is used to authenticate the application with the service provider. This is usually a two step process. The method will return one of three possible responses or if the authentication process requires authorisation through the service providers website and the `$redirect_url` parameter is set, this method will automatically redirect to the authorisation page. Once the service has been authorised the browser will redirect back to `$redirect_url` to continue processing if `$redirect_url` has been specified.

If no `$redirect_url` is set, the method will return false giving you the chance to handle redirection manually.

* *mixed* 'access_token' - Once the authentication process steps are complete the method will return an authorisation token. This will be specific to the service provider and can be anything from an array of a uid, access_token and access_token_secret, to a single code string. This data needs to be stored somewhere (usually in a database associated with a user record) and passed in as the `$token` parameter. It's up to you to store this data and pass it in in the exact same format for the backend to use and verify.
* *true* - The service is authenticated and a valid `$token` parameter was provided. This will only happen when you pass in a valid `$token` parameter to the `authenticate()` method call. If the `$token` is not set, null, or has expired (supported by some providers) then the service is considered unauthorised and the authorisation process will start from the beginning.
* *false* - The service is not authorised. This result will be returned when no `$redirect_url` parameter is set. At this point you should use the `buildAuthUrl()` method to get the auth URL and redirect to that. Normally it is easier to just supply the redirect URL and redirect automatically but this two step process has been created for special situations.

### buildAuthURL($redirect_url)

This method is used to build the URL that we will need to redirect the user to authorise the access by our application. The `$redirect_url` parameter is the URL we want the service provider to redirect back to. It's up to you how you want to handle the authentication flow.

#### Simple authorisation %(muted lead)(automatic redirect)

Here is a simple example of how to authenticate with a backend using automatic redirection:

```php
public function init(){
    $this->fs = new \Hazaar\File\Browser();
    $cache = new \Hazaar\Cache\Adapter();
    if(($token = $this->fs->authorise($cache->load('access_token'), $this->url())) !== true){
        $cache->save('access_token', $token);
        $this->redirect($this->url());
    }
}
```

As you can see, it's best to put this in the controller init method so that it is checked each time the controller is accessed. That way you can have multiple actions that use the browser.

The most interesting lines here are:

```php
if(($token = $this->fs->authorise($cache->load('access_token'), $this->url())) !== true){
```

This line asks the backend to authenticate using the access_token stored in cache. If there is no token in cache it will return false so it's safe to pass that as the $token parameter of `authenticate()`. The methods with automatically redirect the browser to the service providers authorisation page if required.

The last part of interest is:

```php
$cache->save('access_token', $token);
$this->redirect($this->url());
```

This is executed if the returned value is anything but true, meaning we have just received back the access_token and need to store it. After this we do another redirect to remove any request parameters from the URL so that the authentication process doesn't try to perform a partial re-authorise (that will fail).

## Advanced Authorisation %(muted lead)(manual redirect)

Here is a simple example of how to authenticate with a backend using automatic redirection:

```php
public function init(){
    $this->fs = new \Hazaar\File\Browser();
    $cache = new \Hazaar\Cache\Adapter();
    if(($token = $this->fs->authorise($cache->load('access_token'))) === false){
        $this->redirect($this->fs->buildAuthUrl($this->url()));
    }elseif($token !== true){
        $cache->save('access_token', $token);
        $this->redirect($this->url());
    }
}
```

This example is almost identical except for two things. We didn't provide a `$redirect_url` parameter to the `authorise()` method, and we also added a test for false, at which point we redirect to the service providers authorisation page manually.

## Using the Browser

The browser has methods for many common file operation. It then uses the configured backend to fullfill the request, allowing a seamless interface to multiple backend types without changing your access code.

### dir($path) - Get a directory listing

**Returns**: Array of arrays of metdata for files and folders in the requested path. The returned metadata is the same format as that returned by `info()`.

### info($path) - Get info about a file or folder

**Returns**: An array of metadata containing information about the file or folder. Metadata is dependent on the backend but at a minimum the following should be provided:

* path - The full pathname of the file or folder
* bytes - The size of the file in bytes
* size - A user friendly string representation of the size
* modified - The last modified time
* mime-type - Only for files. The mime type of the file.
* is_dir - Boolean indicating if the entry is a directory
* root - The name of the backend the entry came from (ie: local, dropbox, etc)

### exists($path) - Checks if a file or folder exists

**Returns**: true or false to indicate if the path exists

### is_dir($path) - Checks if a path is a folder

**Returns**: true or false to indicate if the path is a folder

### mkdir($path) - Create a folder on the backend

**Returns**: Metadata of the folder that was just created

### read($path) - Returns the contents of the file

**Returns**: string value of the binary data of the file contents. Safe to use with things like `file_put_contents()` or return as the body of a Hazaar\\Controller\\Response object.

### write($path, $data, $content_type) - Write a file to the backend

* `$data` is the data to write to the file.
* `$content_type` is the type of data that is being written. This is usually used to set the header of the request to write the file as most backends will require this.

This is a low-level method of writing files. See `upload()` for a simpler method.

**Returns**: array of metadata of the file that was just written

### upload($source, $target_dir) - Upload a file to the backend

This is a more simplified method of uploading files to the backend. It will automatically detect and set the mime-type and read the file from the local filesystem. It is suggested that this method be used to upload files to backends.

**Returns**: Metadata of the file that was just uploaded

### move($source, $target) - Move a file on the backend

This method will move a file from one path on the backed to another.

**Returns**: Metadata of the file that was just uploaded

### copy($source, $target) - Copy a file on the backend

This method will copy a file on the backend to another location on the backend.

**Returns**: Metadata of the file that was just uploaded

### delete($path) - Delete the file or folder from the backend

**Returns**: true on success, false otherwise

## Detailed Usage Example

Below is an example application that uses the dropbox backend. This example application will delete everything if the indexaction is accessed. If the upload action is access then the application will upload any files in /home/user/Pictures.

### application\bootstrap.php

```php
if(!$this instanceof Hazaar\Application){
    die("Something has gone terribly wrong!");
}
Hazaar\Currency::$default_currency = 'AUD';
Hazaar\File\Browser::configure('dropbox', ['app_key' => 'mn3b45kjv543jhg54', 'app_secret' => '89867zxcas98da']);
```

### application\controllers\Index.php

```php
class IndexController extends \Hazaar\Controller\Action {
    private $fs;
    public function init(){
        $this->fs = new \Hazaar\File\Browser();
        $cache = new \Hazaar\Cache\Adapter();
        if(($token = $this->fs->authorise($cache->load('access_token'))) == false){
            $this->redirect($this->fs->buildAuthURL($this->url()));
        }elseif($token !== true){
            $cache->save('access_token', $token);
            $this->redirect($this->url());
        }
    }

    public function index(){

        $dir = $this->fs->dir('/');

        foreach($dir as $file){
            $this->fs->delete($file['path']);
        }

    }
    public function upload(){

        $path = '/Pictures';

        if(!$this->fs->info($path)){
            $this->fs->mkdir($path);
        }

        $local = new \Hazaar\File\Browser();
        $dir = $local->dir('/home/user/Pictures');

        foreach($dir as $file){
            $this->fs->upload($file['path'], $path);
        }

    }

}
```