<?php
/**
 * @file        Hazaar/View/Helper/Widget.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\View\Helper;

/**
 * @brief       The Widget UI framework View Helper
 *
 * @detail      HazaarMVC has full support for the jqWidgets library of JavaScript widgets and supplied wrapper objects
 *              for all widgets.  This view helper provides shortcuts to creating jqWidgets objects from inside a view.
 *
 * @since       1.0.0
 *
 */
class Widget extends \Hazaar\View\Helper {

    public function import() {

        $this->requires('html');

        $this->requires('jQuery');

    }

    public function init($view, $args = array()) {

        $settings = new \Hazaar\Map( array(
            'theme' => 'classic',
            'widgets' => array(),
            'noload' => false,
            'use_editor' => false
        ), $args);

        if(!\Hazaar\Map::is_array($settings['widgets']))
            $settings['widgets'] = explode(',', $settings['widgets']);

        if($settings['noload'] !== true) {

            if($settings['widgets']->count() > 0) {

                $view->requires($this->application->url('hazaar/jqWidgets/jqxcore.js'));

                foreach($settings['widgets'] as $widget) {

                    $file = 'jqWidgets/jqx' . $widget . '.js';

                    if(!\Hazaar\Loader::getFilePath(FILE_PATH_SUPPORT, $file)) {

                        throw new \Exception("Unknown jqWidget requested.  '$widget' does not exist!");

                    }

                    $view->requires($this->application->url('hazaar/' . $file));

                }

            } else {

                $view->requires($this->application->url('hazaar/jqWidgets/jqx-all.js'));

            }

            $view->requires($this->application->url('hazaar/jqWidgets/globalization/globalize.js'));

            $view->link($this->application->url('hazaar/jqWidgets/styles/jqx.base.css'));

            if(preg_match('/^http[s]?\:\/\//', $settings['theme'])) {

                $view->link($settings['theme']);

            } else {

                $view->link($this->application->url('hazaar/jqWidgets/styles/jqx.' . $settings['theme'] . '.css'));

            }

            if($settings['use_editor']) {

                $view->requires($this->application->url('hazaar/jqWidgets/jqxeditor.js'));

                $view->link($this->application->url('hazaar/jqWidgets/styles/jqxeditor.css'));

            }

            $view->script('var jqxTheme = "' . $settings['theme'] . '";');

        }

    }

    /**
     * @detail      Returns the version of jQWidgets currently being loaded.
     *
     * @since       1.2
     *
     * @param       boolean $assoc Return the version as an associative array with values for major, minor and revision.
     *              Defaults to false.
     *
     * @return      mixed String normally.  Array when $assoc is true.  Returns null when the version can no be detected.
     */
    public function getVersion($assoc = false) {

        $version = null;

        $fh = fopen(LIBRARY_PATH . '/Support/jqWidgets/jqx-all.js', 'r');

        while($line = fgets($fh)) {

            if(preg_match('/^jQWidgets\s+v(.*)\s+\(.*\)/', $line, $matches)) {

                $version = $matches[1];

                if($assoc) {

                    $n = explode('.', $version);

                    $version = array(
                        'major' => $n[0],
                        'minor' => $n[1],
                        'revision' => $n[2]
                    );

                }

                break;

            }

        }

        fclose($fh);

        return $version;

    }

    /**
     * @detail      Returns a DataSource object
     *
     * @since       1.0.0
     *
     * @return      \\Hazaar\\Widgets\\DataSource
     */
    public function datasource($values = array()) {

        return new \Hazaar\View\Widgets\DataSource($values);

    }

    /**
     * @detail      Returns a DataAdapter object
     *
     * @since       1.0.0
     *
     * @return      \\Hazaar\\Widgets\\DataAdapter
     */
    public function dataadapter($source, $settings = array()) {

        return new \Hazaar\View\Widgets\DataAdapter($source, $settings);

    }

    /**
     * @detail      Returns a Grid object
     *
     * @since       1.0.0
     *
     * @param       string $name The name of the element.  Prefix '#' to refer to an existing element.
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function grid($name, $params = array()) {

        return new \Hazaar\View\Widgets\Grid($name, $params);

    }

    /**
     * @detail      Returns a jqxChart object
     *
     * @since       1.0.0
     *
     * @param       string $name The name of the element.  Prefix '#' to refer to an existing element.
     *
     * @return      \\Hazaar\\Widgets\\Chart
     */
    public function chart($name, $params = array()) {

        return new \Hazaar\View\Widgets\Chart($name, $params);

    }

    /**
     * @detail      Returns a jqxGauge object
     *
     * @since       1.0.0
     *
     * @param       string $name The name of the element.  Prefix '#' to refer to an existing element.
     *
     * @return      \\Hazaar\\Widgets\\Gauge
     */
    public function gauge($name, $params = array()) {

        return new \Hazaar\View\Widgets\Gauge($name, $params);

    }

    /**
     * @detail      Returns a jqxMenu object
     *
     * @since       1.0.0
     *
     * @param       string $name The name of the element.  Prefix '#' to refer to an existing element.
     *
     * @return      \\Hazaar\\Widgets\\Menu
     */
    public function menu($name, $params = array()) {

        return new \Hazaar\View\Widgets\Menu($name, $params);

    }

    /**
     * @detail      Returns a jqxButton object
     *
     * @since       1.0.0
     *
     * @param       string $name The name of the element.  Prefix '#' to refer to an existing element.
     *
     * @return      \\Hazaar\\Widgets\\Button
     */
    public function button($name, $text = 'Button', $style = null, $params = array()) {

        return new \Hazaar\View\Widgets\Button($name, $text, $style, $params);

    }

    /**
     * @detail      Returns a jqxToggleButton object
     *
     * @since       1.0.0
     *
     * @param       string $name The name of the element.  Prefix '#' to refer to an existing element.
     *
     * @return      \\Hazaar\\Widgets\\Button\\Toggle
     */
    public function togglebutton($name, $text = 'Button', $params = array()) {

        return new \Hazaar\View\Widgets\Button\Toggle($name, $text, $params);

    }

    /**
     * @detail      Returns a jqxRepeatButton object
     *
     * @since       1.0.0
     *
     * @param       string $name The name of the element.  Prefix '#' to refer to an existing element.
     *
     * @return      \\Hazaar\\Widgets\\Button\\Repeat
     */
    public function repeatbutton($name, $text = 'Button', $params = array()) {

        return new \Hazaar\View\Widgets\Button\Repeat($name, $text, $params);

    }

    /**
     * @detail      Returns a jqxLinkButton object
     *
     * @since       1.0.0
     *
     * @param       string $name The name of the element.  Prefix '#' to refer to an existing element.
     *
     * @return      \\Hazaar\\Widgets\\Button\\Link
     */
    public function linkbutton($name, $link, $text = 'Button', $params = array()) {

        return new \Hazaar\View\Widgets\Button\Link($name, $link, $text, $params);

    }

    /**
     * @detail      Returns a jqxDropDownButton object
     *
     * @since       1.0.0
     *
     * @param       string $name The name of the element.  Prefix '#' to refer to an existing element.
     *
     * @return      \\Hazaar\\Widgets\\Button\\DropDown
     */
    public function dropdownbutton($name, $content, $params = array()) {

        return new \Hazaar\View\Widgets\Button\DropDown($name, $content, $params);

    }

    /**
     * @detail      Returns a jqxCheckBox object
     *
     * @since       1.0.0
     *
     * @param       string $name The name of the element.  Prefix '#' to refer to an existing element.
     *
     * @return      \\Hazaar\\Widgets\\Button\\Checkbox
     */
    public function checkbox($name, $text = 'Checkbox', $params = array()) {

        return new \Hazaar\View\Widgets\CheckBox($name, $text, $params);

    }

    /**
     * @detail      Returns a jqxRadioButton object
     *
     * @since       1.0.0
     *
     * @param       string $name The name of the element.  Prefix '#' to refer to an existing element.
     *
     * @return      \\Hazaar\\Widgets\\Button\\Radio
     */
    public function radiobutton($name, $text = 'Button', $params = array()) {

        return new \Hazaar\View\Widgets\Button\Radio($name, $text, $params);

    }

    /**
     * @detail      Returns a jqxSwitchButton object
     *
     * @since       1.0.0
     *
     * @param       string $name The name of the element.  Prefix '#' to refer to an existing element.
     *
     * @return      \\Hazaar\\Widgets\\Button\\SwitchButton
     */
    public function switchbutton($name, $params = array()) {

        return new \Hazaar\View\Widgets\Button\SwitchButton($name, $params);

    }

    /**
     * @detail      Returns a jqxButtonGroup object
     *
     * @since       1.0.0
     *
     * @param       string $name The name of the element.  Prefix '#' to refer to an existing element.
     *
     * @return      \\Hazaar\\Widgets\\Button\\Group
     */
    public function buttongroup($name, $buttons = array(), $params = array()) {

        return new \Hazaar\View\Widgets\Button\Group($name, $buttons, $params);

    }

    /**
     * @detail      Returns a jqxSlider object
     *
     * @since       1.0.0
     *
     * @param       string $name The name of the element.  Prefix '#' to refer to an existing element.
     *
     * @return      \\Hazaar\\Widgets\\Slider
     */
    public function slider($name, $params = array()) {

        return new \Hazaar\View\Widgets\Slider($name, $params);

    }

    /**
     * @detail      Returns a jqxListBox object
     *
     * @since       1.0.0
     *
     * @param       string $name The name of the element.  Prefix '#' to refer to an existing element.
     *
     * @return      \\Hazaar\\Widgets\\ListBox
     */
    public function listbox($name, $params = array()) {

        return new \Hazaar\View\Widgets\ListBox($name, $params);

    }

    /**
     * @detail      Returns a jqxDropDownList object
     *
     * @since       1.0.0
     *
     * @param       string $name The name of the element.  Prefix '#' to refer to an existing element.
     *
     * @return      \\Hazaar\\Widgets\\DropDownList
     */
    public function dropdownlist($name, $params = array()) {

        return new \Hazaar\View\Widgets\DropDownList($name, $params);

    }

    /**
     * @detail      Returns a jqxComboBox object
     *
     * @since       1.0.0
     *
     * @param       string $name The name of the element.  Prefix '#' to refer to an existing element.
     *
     * @return      \\Hazaar\\Widgets\\ComboBox
     */
    public function combobox($name, $params = array()) {

        return new \Hazaar\View\Widgets\ComboBox($name, $params);

    }

    /**
     * @detail      Returns a jqxCalendar object
     *
     * @since       1.0.0
     *
     * @param       string $name The name of the element.  Prefix '#' to refer to an existing element.
     *
     * @return      \\Hazaar\\Widgets\\Calendar
     */
    public function calendar($name, $params = array()) {

        return new \Hazaar\View\Widgets\Calendar($name, $params);

    }

    /**
     * @detail      Returns a jqxDateTimeInput object
     *
     * @since       1.0.0
     *
     * @param       string $name The name of the element.  Prefix '#' to refer to an existing element.
     *
     * @return      \\Hazaar\\Widgets\\DateTimeInput
     */
    public function datetimeinput($name, $value = null, $params = array()) {

        return new \Hazaar\View\Widgets\DateTimeInput($name, $value, $params);

    }

    /**
     * @detail      Returns a jqxNumberInput object
     *
     * @since       1.0.0
     *
     * @param       string $name The name of the element.  Prefix '#' to refer to an existing element.
     *
     * @return      \\Hazaar\\Widgets\\NumberInput
     */
    public function numberinput($name, $value = null, $params = array()) {

        return new \Hazaar\View\Widgets\NumberInput($name, $value, $params);

    }

    /**
     * @detail      Returns a jqxMaskedInput object
     *
     * @since       1.0.0
     *
     * @param       string $name The name of the element.  Prefix '#' to refer to an existing element.
     *
     * @return      \\Hazaar\\Widgets\\MaskedInput
     */
    public function maskedinput($name, $value = null, $params = array()) {

        return new \Hazaar\View\Widgets\MaskedInput($name, $value, $params);

    }

    /**
     * @detail      Returns a jqxInput object
     *
     * @since       1.0.0
     *
     * @param       string $name The name of the element.  Prefix '#' to refer to an existing element.
     *
     * @return      \\Hazaar\\Widgets\\Input
     */
    public function input($name, $value = null, $button = null, $params = array(), $input_type = 'text', $element_type = 'input') {

        return new \Hazaar\View\Widgets\Input($name, $value, $button, $params, $input_type, $element_type);

    }
    
    /**
     * @detail      Returns a jqxPasswordInput object
     *
     * @since       1.0.0
     *
     * @param       string $name The name of the element.  Prefix '#' to refer to an existing element.
     *
     * @return      \\Hazaar\\Widgets\\Input
     */
    public function passwordInput($name, $value = null, $button = null, $params = array(), $input_type = 'text', $element_type = 'input') {

        return new \Hazaar\View\Widgets\PasswordInput($name, $value, $params);

    }

    /**
     * @detail      Returns a jqxTree object
     *
     * @since       1.0.0
     *
     * @param       string $name The name of the element.  Prefix '#' to refer to an existing element.
     *
     * @return      \\Hazaar\\Widgets\\Tree
     */
    public function tree($name, $params = array()) {

        return new \Hazaar\View\Widgets\Tree($name, $params);

    }

    public function treeitem($name, $label, $expanded = null, $params = array()) {

        return new \Hazaar\View\Widgets\TreeItem($name, $label, $expanded, $params);

    }

    /**
     * @detail      Returns a jqxButton object
     *
     * @since       1.0.0
     *
     * @param       string $name The name of the element.  Prefix '#' to refer to an existing element.
     *
     * @return      \\Hazaar\\Widgets\\Button
     */
    public function tabs($name, $params = array()) {

        return new \Hazaar\View\Widgets\Tabs($name, $params);

    }

    /**
     * @detail      Returns a jqxSplitter object
     *
     * @since       1.0.0
     *
     * @param       string $name The name of the element.  Prefix '#' to refer to an existing element.
     *
     * @return      \\Hazaar\\Widgets\\Splitter
     */
    public function splitter($name, $params = array()) {

        return new \Hazaar\View\Widgets\Splitter($name, $params);

    }

    /**
     * @detail      Returns a jqxScrollView object
     *
     * @since       1.0.0
     *
     * @param       string $name The name of the element.  Prefix '#' to refer to an existing element.
     *
     * @return      \\Hazaar\\Widgets\\ScrollView
     */
    public function scrollview($name, $items = array(), $params = array()) {

        return new \Hazaar\View\Widgets\ScrollView($name, $items, $params);

    }

    /**
     * @detail      Returns a jqxWindow object
     *
     * @since       1.0.0
     *
     * @param       string $name The name of the element.  Prefix '#' to refer to an existing element.
     *
     * @return      \\Hazaar\\Widgets\\Window
     */
    public function window($name, $content = null, $title = null, $params = array()) {

        return new \Hazaar\View\Widgets\Window($name, $content, $title, $params);

    }

    /**
     * @detail      Returns a jqxDocking object
     *
     * @since       1.0.0
     *
     * @param       string $name The name of the element.  Prefix '#' to refer to an existing element.
     *
     * @return      \\Hazaar\\Widgets\\Docking
     */
    public function docking($name, $params = array()) {

        return new \Hazaar\View\Widgets\Docking($name, $params);

    }

    /**
     * @detail      Returns a jqxProgressBar object
     *
     * @since       1.0.0
     *
     * @param       string $name The name of the element.  Prefix '#' to refer to an existing element.
     *
     * @return      \\Hazaar\\Widgets\\ProgressBar
     */
    public function progressbar($name, $params = array()) {

        return new \Hazaar\View\Widgets\ProgressBar($name, $params);

    }

    /**
     * @detail      Returns a jqxTooltip object
     *
     * @since       1.0.0
     *
     * @param       mixed $name The object, or the ID of the object to apply the tooltip to.
     *
     * @return      \\Hazaar\\Widgets\\Tooltip
     */
    public function tooltip($object, $params = array()) {

        return new \Hazaar\View\Widgets\Tooltip($object, $params);

    }

    /**
     * @detail      Returns a jqxPanel object
     *
     * @since       1.0.0
     *
     * @param       string $name The name of the element.  Prefix '#' to refer to an existing element.
     *
     * @return      \\Hazaar\\Widgets\\Panel
     */
    public function panel($name, $content = null, $params = array()) {

        return new \Hazaar\View\Widgets\Panel($name, $content, $params);

    }

    /**
     * @detail      Returns a jqxRating object
     *
     * @since       1.0.0
     *
     * @param       string $name The name of the element.  Prefix '#' to refer to an existing element.
     *
     * @return      \\Hazaar\\Widgets\\Rating
     */
    public function rating($name, $value = null, $params = array()) {

        return new \Hazaar\View\Widgets\Rating($name, $value, $params);

    }

    /**
     * @detail      Returns a jqxScrollBar object
     *
     * @since       1.0.0
     *
     * @param       string $name The name of the element.  Prefix '#' to refer to an existing element.
     *
     * @return      \\Hazaar\\Widgets\\ScrollBar
     */
    public function scrollbar($name, $params = array()) {

        return new \Hazaar\View\Widgets\ScrollBar($name, $params);

    }

    /**
     * @detail      Returns a jqxNavigationBar object
     *
     * @since       1.0.0
     *
     * @param       string $name The name of the element.  Prefix '#' to refer to an existing element.
     *
     * @return      \\Hazaar\\Widgets\\NavigationBar
     */
    public function navigationbar($name, $params = array()) {

        return new \Hazaar\View\Widgets\NavigationBar($name, $params);

    }

    /**
     * @detail      Returns a jqxExpander object
     *
     * @since       1.0.0
     *
     * @param       string $name The name of the element.  Prefix '#' to refer to an existing element.
     *
     * @return      \\Hazaar\\Widgets\\Expander
     */
    public function expander($name, $title = null, $content = null, $params = array()) {

        return new \Hazaar\View\Widgets\Expander($name, $title, $content, $params);

    }

    /**
     * @detail      Returns a jqxListMenu object
     *
     * @since       1.0.0
     *
     * @param       string $name The name of the element.  Prefix '#' to refer to an existing element.
     *
     * @return      \\Hazaar\\Widgets\\ListMenu
     */
    public function listmenu($name, $params = array()) {

        return new \Hazaar\View\Widgets\ListMenu($name, $params);

    }

    /**
     * @detail      Returns a jqxColorPicker object
     *
     * @since       1.0.0
     *
     * @param       string $name The name of the element.  Prefix '#' to refer to an existing element.
     *
     * @return      \\Hazaar\\Widgets\\ColorPicker
     */
    public function colorpicker($name, $params = array()) {

        return new \Hazaar\View\Widgets\ColorPicker($name, $params);

    }

    /**
     * @detail      Returns a JavaScriptFunction object
     *
     * @since       1.0.0
     *
     * @param       string $code The JavaScript code.
     *
     * @return      \\Hazaar\\Widgets\\JavaScriptFunction
     */
    public function javascript($code, $arglist = array(), $params = array()) {

        return new \Hazaar\View\Widgets\JavaScript($code, $arglist, $params);

    }

    /**
     * @detail      Returns a Editor object
     *
     * @since       2.0.0
     *
     * @param       string $name The name of the editor widget.
     *
     * @param       string $content The initial contents of the editor
     *
     * @param       array $params Any additional parameters to set on the DIV container
     *
     * @return      \\Hazaar\\Widgets\\Editor
     */
    public function editor($name, $content = null, $params = array()) {

        if($this->get('use_editor')) {

            return new \Hazaar\View\Widgets\Editor($name, $content, $params);

        }

        return new \Hazaar\Html\Div('The Editor widget is not enabled.  Use the \'use_editor\' setting to enabled it.');

    }

}
