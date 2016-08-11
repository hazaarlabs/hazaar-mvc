<?php
/**
 * @file        Hazaar/View/Helper/Form.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\View\Helper;

class Form extends \Hazaar\View\Helper {

    public function import() {

        $this->requires('html');

    }

    public function open($action = null, $method = 'post', $args = array()) {

        if($action)
            $args['action'] = $action;

        $args['method'] = strtoupper($method);

        return $this->html->block('form', null, $args, false);

    }

    public function close() {

        return "</form>";

    }

    public function input($type, $name, $value = null, $args = array()) {

        $args['type'] = $type;

        $args['name'] = $name;

        if($type == 'checkbox') {

            if(boolify($value)) {

                $args[] = 'checked';

            }

            $args['value'] = 1;

        } else {

            $args['value'] = $value;

        }

        return $this->html->inline('input', $args);

    }

    public function textarea($name, $content = null, $args = array()) {

        $args['name'] = $args['id'] = $name;

        return $this->html->block('textarea', $content, $args);

    }

    public function select($name, $options, $default = null, $nullable = 'Select', $args = array()) {

        $ops = array();

        if($nullable) {

            $ops[] = $this->html->block('option', $nullable, array('value' => 'null'));

        }

        if(is_array($options)) {

            foreach($options as $key => $value) {

                $params = array();

                $type = 'option';

                if(is_array($value)) {

                    $type = 'optgroup';

                    $params['label'] = $key;

                    $group_ops = array();

                    foreach($value as $opkey => $label) {

                        $op_params = array('value' => $opkey);

                        if($default !== null && $opkey == $default) {

                            $op_params[] = 'selected';

                        }

                        $group_ops[] = $this->html->block('option', $label, $op_params);

                    }

                    $content = implode("\n", $group_ops);

                } else {

                    $params['value'] = $key;

                    $content = $value;

                    if($default !== null && $key == $default) {

                        $params[] = 'selected';

                    }

                }

                $ops[] = $this->html->block($type, $content, $params);

            }

        }

        $args['name'] = $name;

        return $this->html->block('select', implode("\n", $ops), $args);

    }

    public function radioGroup($name, $radios, $default = null, $args = array()) {

        $group = array();

        foreach($radios as $value => $label) {

            $id = $name . "[$value]";

            $attr = array('id' => $id);

            if($value == $default) {

                $attr[] = 'checked';

            }

            $group[] = $this->html->block('label', $label, array('for' => $id)) . $this->form->input('radio', $name, $value, $attr);

        }

        return implode("\n", $group);

    }

    public function button($name, $value, $attr = array()) {

        if(!is_array($attr))
            throw new \Exception('Parameter 3 to ' . __METHOD__ . '() should be an array of attributes');

        $attr['id'] = $name;

        return $this->html->block('button', $value, $attr);

    }

}
