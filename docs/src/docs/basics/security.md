# Security

::: danger
This page is tagged as a draft and is a work in progress.  It is not yet complete and may contain errors or inaccuracies.
:::

## Basic Authentication using Databases

This uses the Hazaar\Auth\Adapter\DbTable class.

### PDO Databases

```php
class AuthController  extends \Hazaar\Controller\Action  {
    private $auth;

    public  function init ( ) {
        $this->auth  =  new \Hazaar\Auth\DBTable(new \Hazaar\Db\Adapter());
    }

    public  function index ( ) {
        if(!$this->auth->authenticated())
             $this->redirect($this->url('login'));

        $this->view('index');
    }

    public function login(){
        $this->view('login');
    }

}
```

### MongoDB

Custom Authentication using Models