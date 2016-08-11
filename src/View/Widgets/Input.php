<?php

namespace Hazaar\View\Widgets;

/**
 * @detail          Basic button widget.
 *
 * @since           1.1
 */
class Input extends Widget {

    /**
     * @detail      Initialise a button widget
     *
     * @param       string $id The ID of the button element to create.
     *
     * @param       string $label The label to display on the button.
     */
    function __construct($name, $value = null, $buttons = null, $params = array(), $input_type = 'text', $element_type = 'input') {

        if(!is_array($params))
            $params = array();

        if($buttons) {

            parent::__construct('div', $name);

            parent::add(new \Hazaar\Html\Input($input_type, $name, $value, $params));

            if(!is_array($buttons))
                $buttons = array($this->name . '_button' => $buttons);

            foreach($buttons as $id => $button) {

                $button = new \Hazaar\Html\Div($button);

                $button->id($id);

                parent::add($button);

            }

        } else {

            if($value)
                $params['value'] = $value;

            if($element_type == 'input')
                $params['type'] = $input_type;

            $params['name'] = $name;

            parent::__construct($element_type, $name, $params);

        }

    }

    /**
     * @detail      Sets or gets a value indicating whether the auto-suggest popup is opened.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      Hazaar\\jqWidgets\\Input
     */
    public function opened($value) {

        return $this->set('opened', $value, 'bool');

    }

    /**
     * @detail      Sets or gets the search mode. When the user types into the edit field, the jqxInput widget tries to
     *              find the searched item using the entered text and the selected search mode.
     *
     *              Possible Values:
     *              * 'none'
     *              * 'contains'
     *              * 'containsignorecase'
     *              * 'equals'
     *              * 'equalsignorecase'
     *              * 'startswithignorecase'
     *              * 'startswith'
     *              * 'endswithignorecase'
     *              * 'endswith'
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      Hazaar\\jqWidgets\\Input
     */
    public function searchMode($value) {

        return $this->set('searchMode', $value, 'string');

    }

    /**
     * @detail      Sets the widget's data source. The 'source' function is passed two arguments, the input field's value
     *              and a callback function. The 'source' function may be used synchronously by returning an array of
     *              items or asynchronously via the callback.
     *
     * @since       1.1
     *
     * @param       bool $source The DataSource object that defines where data is coming from.
     *
     * @param       string $root The root of the returned data
     *
     * @param       JavaScript $map Optional javascript to map returned data values.  Data must end up as pairs of the
     *              keys "label" and "value".  If your returned data does not have keys with these names you need to
     *              use this function to map the data and return an object with label and value fields.  The function
     *              provides one parameter named 'item' which is the current item being mapped.  This function is then
     *              called for every record returned.
     *
     *              Example:
     *
     *              <pre><code class="php">
     *              new JavaScript("return { label : item.name, value : item.code }");
     *              </code></pre>
     *
     *              This will map the name field to the label field and the code field to the value field.
     *
     * @return      Hazaar\\jqWidgets\\Input
     */
    public function source(DataAdapter $source, $root = null, JavaScript $map = null, JavaScript $formatData = null) {

        $data = 'data';

        if($root)
            $data .= '.' . $root;

        if($map) {

            $map->setArgs('item');

            $map = '$.map(data, ' . $map->anon() . ' )';

        } else {

            $map = $data;

        }

        $source->autoBind(true)->loadComplete(new JavaScript("if({$data}.length > 0){ response($map); }", 'data'));

        if($formatData)
            $source->formatData($formatData);

        $func = new JavaScript("var dataAdapter = " . $source, array(
            'query',
            'response'
        ));

        return $this->set('source', $func);

    }

    /**
     * @detail      Sets or gets the maximum number of items to display in the popup menu.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      Hazaar\\jqWidgets\\Input
     */
    public function items($value) {

        return $this->set('items', $value, 'int');

    }

    /**
     * @detail      Sets or gets the minimum character length needed before triggering auto-complete suggestions.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      Hazaar\\jqWidgets\\Input
     */
    public function minLength($value) {

        return $this->set('minLength', $value, 'int');

    }

    /**
     * @detail      Sets or gets the input's place holder.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      Hazaar\\jqWidgets\\Input
     */
    public function placeHolder($value) {

        return $this->set('placeHolder', $value, 'string');

    }

    /**
     * @detail      Sets or gets the auto-suggest popup's z-index.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      Hazaar\\jqWidgets\\Input
     */
    public function popupZIndex($value) {

        return $this->set('popupZIndex', $value, 'int');

    }

    /**
     * @detail      Sets or gets the displayMember of the Items. The displayMember specifies the name of an object
     *              property to display. The name is contained in the collection specified by the 'source' property.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      Hazaar\\jqWidgets\\Input
     */
    public function displayMember($value) {

        return $this->set('displayMember', $value, 'string');

    }

    /**
     * @detail      Sets or gets the valueMember of the Items. The valueMember specifies the name of an object property
     *              to set as a 'value' of the list items. The name is contained in the collection specified by the
     *              'source' property.
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      Hazaar\\jqWidgets\\Input
     */
    public function valueMember($value) {

        return $this->set('valueMember', $value, 'string');

    }

    /**
     * @detail      Determines the input's query.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      Hazaar\\jqWidgets\\Input
     */
    public function query($value) {

        return $this->set('query', $value, 'string');

    }

    /**
     * @detail      Enables you to update the input's value, after a selection from the auto-complete popup.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      Hazaar\\jqWidgets\\Input
     */
    public function renderer($value) {

        return $this->set('renderer', $value);

    }

    /**
     * @detail      This event is triggered when the value is changed.
     *
     * @since       1.1
     *
     * @param       string $code The JavaScript code to execute when the event is triggered.
     *
     * @return      Hazaar\\jqWidgets\\Input
     */
    public function onChange($code) {

        return $this->event('change', $code);

    }

    /**
     * @detail      This event is triggered when an item is selected from the auto-suggest popup.
     *
     * @since       1.1
     *
     * @param       string $code The JavaScript code to execute when the event is triggered.
     *
     * @return      Hazaar\\jqWidgets\\Input
     */
    public function onSelect($code) {

        return $this->event('select', $code);

    }

    /**
     * @detail      This event is triggered when the auto-suggest popup is opened.
     *
     * @since       1.1
     *
     * @param       string $code The JavaScript code to execute when the event is triggered.
     *
     * @return      Hazaar\\jqWidgets\\Input
     */
    public function onOpen($code) {

        return $this->event('open', $code);

    }

    /**
     * @detail      This event is triggered when the auto-suggest popup is closed.
     *
     * @since       1.1
     *
     * @param       string $code The JavaScript code to execute when the event is triggered.
     *
     * @return      Hazaar\\jqWidgets\\Input
     */
    public function onClose($code) {

        return $this->event('close', $code);

    }

    public function onButtonClick($code) {

        $event = new Event('dummyname', $code);

        $this->exec("$('#" . $this->name . "_button').on('click', " . $event->script() . ');');

        return $this;

    }

    /**
     * @detail      Selects the text in the input field.
     *
     * @since       1.1
     *
     * @return      string
     */
    public function selectAll() {

        return $this->method('selectAll');

    }

}
