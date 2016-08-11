<?php

namespace Hazaar\View\Widgets;

class DataSource extends JSONObject {

    private function event($name, $code, $argdef = array()) {

        if(!$script instanceof JavaScript)
            $script = new JavaScript($code, $argdef, true);

        $this->set($name, $script);

        return $this;

    }

    /**
     * @detail      A string containing the URL to which the request is sent.
     *
     * @since       1.1
     *
     * @return      \\Hazaar\\Widgets\\DataSource
     */
    public function url($value) {

        return $this->set('url', $value);

    }

    /**
     * @detail      Data to be sent to the server
     *
     * @since       1.1
     *
     * @return      \\Hazaar\\Widgets\\DataSource
     */
    public function data($value) {

        return $this->set('data', $value);

    }

    /**
     * @detail      An array of data to use as a local data source.  If this is set, then nothing else is required.
     *
     * @since       1.1
     *
     * @return      \\Hazaar\\Widgets\\DataSource
     */
    public function localdata($data) {

        $this->set('localdata', $data);

        return $this->set('datatype', 'array');

    }

    /**
     /**
     * @detail      the data's type. Possible values: 'xml', 'json', 'jsonp', 'tsv', 'csv', 'local', 'array'.
     *
     * @since       1.1
     *
     * @return      \\Hazaar\\Widgets\\DataSource
     */
    public function datatype($value) {

        return $this->set('datatype', $value);

    }

    /**
     * @detail      The type of request to make ("POST" or "GET"), default is "GET"
     *
     * @since       1.1
     *
     * @return      \\Hazaar\\Widgets\\DataSource
     */
    public function type($value) {

        return $this->set('type', $value);

    }

    /**
     * @detail      A string containing the Id data field.
     *
     * @since       1.1
     *
     * @return      \\Hazaar\\Widgets\\DataSource
     */
    public function id($value) {

        return $this->set('id', $value);

    }

    /**
     * @detail      A string describing where the data begins and all other loops begin from this element
     *
     * @since       1.1
     *
     * @return      \\Hazaar\\Widgets\\DataSource
     */
    public function root($value) {

        return $this->set('root', $value);

    }

    /**
     * @detail      A string describing the information for a particular record.
     *
     * @since       1.1
     *
     * @return      \\Hazaar\\Widgets\\DataSource
     */
    public function record($value) {

        return $this->set('record', $value);

    }

    /**
     * @detail      An array describing the fields in a particular record. Each datafield must define the following
     * members:
     *              * name - A string containing the data field's name.
     *              * type(optional) - A string containing the data field's type. Possible values: 'string', 'date',
     * 'number',
     *              'bool'
     *              * map(optional) - A mapping to the data field.
     *
     * @since       1.1
     *
     * @param       array $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\DataSource
     */
    public function datafields($value) {

        return $this->set('datafields', $value);

    }

    /**
     * @detail      Add a single data field
     *
     * @since       1.1
     *
     * @return      \\Hazaar\\Widgets\\DataSource
     */
    public function datafield($name, $type = null, $map = null) {

        $field = array('name' => $name);

        if($type)
            $field['type'] = $type;

        if($map)
            $field['map'] = $map;

        return $this->add('datafields', $field);

    }

    /**
     * @detail      Determines the initial page number when paging is enabled.
     *
     * @since       1.2
     *
     * @param       int $page The initial page number
     *
     * @return      \\Hazaar\\Widgets\\DataSource
     */
    public function pagenum($page) {

        return $this->set('pagenum', $page);

    }

    /**
     * @detail      Determines the page size when paging is enabled.
     *
     * @since       1.2
     *
     * @param       int @size The number of items to display on a page
     *
     * @return      \\Hazaar\\Widgets\\DataSource
     */
    public function pagesize($size) {

        return $this->set('pagesize', $size);

    }

    /**
     * @detail      Callback function called when the current page or page size is changed.
     *
     * @since       1.2
     *
     * @param       mixed $code The code to execute when the page size is changed.
     *
     * @return      \\Hazaar\\Widgets\\DataSource
     */
    public function pager($code) {

        return $this->event('pager', $code, array(
            'pagenum',
            'pagesize',
            'oldpagenum'
        ));

    }

    /**
     * @detail      Determines the initial sort column. The expected value is a data field name.
     *
     * @since       1.2
     *
     * @param       string $field The data field name to sort on.
     *
     * @return      \\Hazaar\\Widgets\\DataSource
     */
    public function sortcolumn($field) {

        return $this->set('sortcolumn', $field);

    }

    /**
     * @detail      Determines the sort order. The expected value is 'asc' for (A to Z) sorting or 'desc' for (Z to A)
     *              sorting.
     *
     * @since       1.2
     *
     * @param       string $dir The sort direction.  Valid arguments are 'asc' and 'desc'.
     *
     * @return      \\Hazaar\\Widgets\\DataSource
     */
    public function sortdirection($dir) {

        return $this->set('sortdirection', $dir);

    }

    /**
     * @detail      Callback function called when the sort column or sort order is changed.
     *
     * @since       1.2
     *
     * @param       mixed $code The code to execute when the page size is changed.
     *
     * @return      \\Hazaar\\Widgets\\DataSource
     */
    public function sort($code) {

        return $this->event('sort', $code, array(
            'column',
            'direction'
        ));

    }

    /**
     * @detail      Callback function called when a filter is applied or removed.
     *
     * @since       1.1
     *
     * @param       mixed $code The code to execute when the page size is changed.
     *
     * @return      \\Hazaar\\Widgets\\DataSource
     */
    public function filter($code) {

        return $this->event('filter', $code, array(
            'filters',
            'recordsArray'
        ));

    }

    /**
     * @detail      This event is triggered when a new row is added to the data source records.  This allows the new row
     *              to be sent to the server to be stored permanently.
     *
     *              Arguments: rowid, rowdata, position, commit
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widgets\\DataSource
     */
    public function addrow($code) {

        return $this->event('addrow', $code, array(
            'rowid',
            'rowdata',
            'position',
            'commit'
        ));

    }

    /**
     * @detail      This event is triggered when a row is deleted from the data source records.  This allows code to
     *              execute that can signal the server to permanently remove the row from stored data.
     *
     *              Arguments: rowid, commit
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widgets\\DataSource
     */
    public function deleterow($code) {

        return $this->event('deleterow', $code, array(
            'rowid',
            'commit'
        ));

    }

    /**
     * @detail      This event is triggered when a row is updated in the data source records.  This allows the updated
     *              row to be sent to the server to be stored permenantly.
     *
     *              Arguments: rowid, rowdata, commit
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widgets\\DataSource
     */
    public function updaterow($code) {

        return $this->event('updaterow', $code, array(
            'rowid',
            'rowdata',
            'commit'
        ));

    }

    /**
     * @detail      Extend the default data object sent to the server.
     *
     *              Arguments: data.
     *
     * @since       1.2
     *
     * @param       mixed $code The code to execute
     *
     * @return      \\Hazaar\\Widgets\\DataSource
     */
    public function processdata($code) {

        return $this->event('processdata', $code, array('data'));

    }

    /**
     * @detail      Before the data is sent to the server, you can fully override it by using the 'formatdata' function
     *              of the source object. The result that the 'formatdata' function returns is actually what will be sent
     *              to the server.
     *
     *              Arguments: data.
     *
     * @since       1.2
     *
     * @param       mixed $code The code to execute
     *
     * @return      \\Hazaar\\Widgets\\DataSource
     */
    public function formatdata($code) {

        return $this->event('formatdata', $code, array('data'));

    }

    /**
     * @detail      Use this option, If you want to explicitly pass in a content-type. Default is
     *              "application/x-www-form-urlencoded".
     *
     * @since       1.2
     *
     * @param       string $type The content type for sending data to the server
     *
     * @return      \\Hazaar\\Widgets\\DataSource
     */
    public function contenttype($type) {

        return $this->set('contenttype', $type);

    }

}
