# Google Services

## OAuth

Google OAuth is used to authenticate with a Google Service. It is possible to create a Hazaar\Google\OAuth object which handles the OAuth procedure to obtain an authorisation token from Google to use some of their services.

## Maps

Google Maps are implemented as a View Helper (see section on View Helpers).

Adding a map to your view is as simple as adding the helper to the view from your controller and then calling the map view helper method from inside your view.

### Adding the GMaps view helper.

```php
class IndexController extends \Hazaar\Controller\Action {
    public function index() {
        $this->view('index');
        $this->view->addHelper('GMaps', ['api_key' => 'eicaquahvahnaeshaich5Ooqu']);
    }

}
```

### Adding the map to your view.

```php
<div class="mymap" style="width: 500px; height: 300px;">
    <?=$this->gmaps->viewport('mymap', new \Hazaar\Google\Location('Canberra, AU'));?>
</div>
```

This will create a DIV in your view with an ID of mymap, initialise a Google Maps canvas inside the DIV and display the location of "Canberra, AU".