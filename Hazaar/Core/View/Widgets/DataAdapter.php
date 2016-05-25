<?php

namespace Hazaar\View\Widgets;

/**
 * @brief       The jqWidgets dataAdapter wrapper class
 *
 * @detail      This class wraps the functionality and parameters available by a jqx.dataAdapter class.  It will
 *              correctly format the source and settings arguments using JSONObject type classes.  The data source
 *              used in the constructor should be an object of type \\Hazaar\\Widgets\\DataSource
 *
 *              This class is quite involved when it comes to advanced usage.  For details on how to use the advanced
 *              features of this class, refer to the jqWidgets documentation at
 *              http://www.jqwidgets.com/jquery-widgets-documentation/documentation/jqxdataadapter/jquery-data-adapter.htm
 *
 * @since       1.1
 */
class DataAdapter extends \Hazaar\View\ViewableObject {

    private $source;

    private $settings;

    private $name;

    static private $count = 0;

    private $rendered = false;

    /**
     * @detail      DataAdapter Constructor
     *
     * @since       1.1
     *
     * @param       Hazaar\View\Widgets\DataSource $source The data source object used to define the location of the data.
     *
     * @param       array $settings Optional settings to set on the DataAdapter that influence it's function.
     */
    function __construct($source, $settings = null) {

        if(!$source instanceof DataSource)
            $source = new DataSource($source);

        $this->source = $source;

        if(!$settings instanceof JSONObject)
            $settings = new JSONObject($settings);

        $this->settings = $settings;

        $this->name = 'data' . DataAdapter::$count++;

    }

    /**
     * @detail      Render the dataAdapter as a new JavaScript object.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function renderObject() {

        if($this->rendered)
            return $this->name;

        $script = array();

        $script[] = $this->name . ' = new $.jqx.dataAdapter( ' . $this->source;

        if($this->settings->count() > 0)
            $script[] = ', ' . $this->settings;

        $script[] = ' )';
        
        $this->rendered = true;

        return implode($script);

    }

    /**
     * @detail      By default, all requests are sent asynchronously (i.e. this is set to true by default). If you need
     *              synchronous requests, set this option to false. When the binding is "asynchronous", the data binding
     *              operation occurs in parallel and the order of completion is not guaranteed.
     *
     * @since       1.1
     *
     * @param       bool $value False will turn off asynchronous mode.
     *
     * @return      Hazaar\\Widgets\\DataAdapter
     */
    public function async($value = true) {

        $this->settings->set('async', $value, 'bool');

        return $this;

    }

    /**
     * @detail      Enabled/disable automatic data binding.
     *
     * @since       1.1
     *
     * @param       bool $value Specify whether autobind is active or not.  Default: true.
     *
     * @return      Hazaar\\Widgets\\DataAdapter
     */
    public function autoBind($value = true) {

        $this->settings->set('autoBind', $value, 'bool');

        return $this;

    }

    /**
     * @detail      Use this option, If you want to explicitly pass in a content-type. Default is
     *              "application/x-www-form-urlencoded".
     *
     * @since       1.1
     *
     * @param       string $value The content type you want to set.
     *
     * @return      Hazaar\\Widgets\\DataAdapter
     */
    public function contentType($value) {

        $this->settings->set('contentType', $value, 'string');

    }

    /**
     * @detail      A callback function which allows you to modify the default data object sent to the server.
     *
     * @since       1.1
     *
     * @param       mixed $func JavaScript function code as either a string or a JavaScript object.
     *
     * @return      Hazaar\\Widgets\\DataAdapter
     */
    public function processData($func) {

        $this->settings->setFunction('processData', $func);

        return $this;

    }

    /**
     * @detail      A callback function which is called before the data is sent to the server. You can use it to fully
     *              override the data sent to the server. If you define a 'formatData' function, the result that the
     *              function returns will be sent to the server.
     *
     * @since       1.1
     *
     * @param       mixed $func JavaScript function code as either a string or a JavaScript object.
     *
     * @return      Hazaar\\Widgets\\DataAdapter
     */
    public function formatData($func) {

        $this->settings->setFunction('formatData', $func);

        return $this;

    }

    /**
     * @detail      A pre-request callback function that can be used to modify the jqXHR.
     *
     *              Provides: function($jqXHR, $settings)
     *
     * @since       1.1
     *
     * @param       mixed $func JavaScript function code as either a string or a JavaScript object.
     *
     * @return      Hazaar\\Widgets\\DataAdapter
     */
    public function beforeSend($func) {

        $this->settings->setFunction('beforeSend', $func);

        return $this;
    }

    /**
     * @detail      A callback function called when the request has failed.
     *
     *              Provides: function($jqXHR, $status, $error)
     *
     * @since       1.1
     *
     * @param       mixed $func JavaScript function code as either a string or a JavaScript object.
     *
     * @return      Hazaar\\Widgets\\DataAdapter
     */
    public function loadError($func) {

        $this->settings->setFunction('loadError', $func);

        return $this;

    }

    /**
     * @detail      A callback function which is called if the request succeeds. The function gets passed three
     *              arguments: The data returned from the server, formatted according to the dataType parameter; a string
     *              describing the status; and the jqXHR.
     *
     *              Provides: function($edata, $textStatus, $jqXHR)
     *
     * @since       1.1
     *
     * @param       mixed $func JavaScript function code as either a string or a JavaScript object.
     *
     * @return      Hazaar\\Widgets\\DataAdapter
     */
    public function downloadComplete($func) {

        $this->settings->setFunction('downloadComplete', $func, array('edata', 'textStatus', 'jqXHR'));

        return $this;

    }

    /**
     * @detail      A callback function which is called before the data is fully loaded. The function gets passed two
     *              arguments: The loaded records. The second argument is the original data. If the function returns an
     *              array, the dataAdapter's records field will be set to it.
     *
     * @since       1.1
     *
     * @param       mixed $func JavaScript function code as either a string or a JavaScript object.
     *
     * @return      Hazaar\\Widgets\\DataAdapter
     */
    public function beforeLoadComplete($func) {

        $this->settings->setFunction('beforeLoadComplete', $func, array('records'));

        return $this;

    }

    /**
     * @detail      A callback function which is called when the data is fully loaded.
     *
     * @since       1.1
     *
     * @param       mixed $func JavaScript function code as either a string or a JavaScript object.
     *
     * @return      Hazaar\\Widgets\\DataAdapter
     */
    public function loadComplete($func) {

        $this->settings->setFunction('loadComplete', $func);

        return $this;

    }

    /**
     * @detail      A callback function which allows you to manually handle the ajax calls through the jqxDataAdapter.
     *              The function gets passed three arguments: The data to be sent to the server, the source object which
     *              initializes the jqxDataAdapter plug-in and a callback function to be called when the ajax call is
     *              handled.
     *
     * @since       1.1
     *
     * @param       mixed $func JavaScript function code as either a string or a JavaScript object.
     *
     * @return      Hazaar\\Widgets\\DataAdapter
     */
    public function loadServerData($func) {

        $this->settings->setFunction('loadServerData', $func);

        return $this;

    }

    /**
     * @detail      Performs data binding.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function dataBind() {

        return $this->name . '.dataBind()';

    }

    /**
     * @detail      Gets the array of the loaded data records when the data binding is completed.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function records() {

        return $this->name . '.records';

    }

    /**
     * @detail      Begins data update operation. If you need to update multiple records at once, call this method before
     *              the update.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function beginUpdate() {

        return $this->name . '.beginUpdate()';

    }

    /**
     * @detail      ends the data update operation. Performs data refresh by default, if you call the method without
     *              params or with true parameter.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function endUpdate($refresh = true) {

        return $this->name . '.endUpdate(' . strbool($refresh) . ')';

    }

    /**
     * @detail      gets the array of the loaded data records and builds a data tree. The method has 4 parameters, the
     *              last 2 of which are optional. The first parameter is the field’s id. The second parameter represents
     *              the parent field’s id. These parameters should point to a valid ‘datafield’ from the Data Source. The
     *              third parameter which is optional specifies the name of the ‘children’ collection. The last parameter
     *              specifies the mapping between the Data Source fields and custom data fields.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function getRecordsHierarchy($id, $parent, $collection = null, $map = null) {

        $params = new JSONObject();

        $properties = array(
            $id,
            $parent
        );

        if($collection)
            $properties[] = $collection;

        if($map)
            $properties[] = $map;

        return $this->name . '.getRecordsHierarchy( ' . $params->renderProperties($properties) . ' )';

    }

    /**
     * @detail      Gets the array of the loaded data records and groups them. The method has 4 parameters. The first
     *              parameter is an array of grouping fields. The second parameter is the sub items collection name. The
     *              third parameter is the group's name. The last parameter specifies the mapping between the Data Source
     *              fields and custom data fields.
     *
     * @param       array $groupedFields
     *
     * @param       string $collection
     *
     * @param       string $name
     *
     * @param       string $map
     *
     * @since       1.1
     *
     * @return      string
     */
    public function getGroupedRecords($groupedFields, $collection, $name, $map) {

        $params = new JSONObject( array(
            $groupedFields,
            $collection,
            $name,
            $map
        ));

        return $this->name . '.getGroupedRecords( ' . $params->renderProperties() . ' )';

    }

    /**
     * @detail      gets an array of aggregated data.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function getAggregatedData($fields, $object = null, $records = null) {

        $params = new JSONObject();

        $properties = array($fields);

        if($object)
            $properties[] = $object;

        if($records)
            $properties[] = $records;

        return $this->name . '.getAggregatedData( ' . $params->renderProperties($properties) . ' )';

    }

}
