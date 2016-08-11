<?php

namespace Hazaar\View\Widgets;

class TreeItem extends \Hazaar\Html\Block {

    private $list;

    function __construct($name, $content, $expanded = false, $params = array()) {

        if($expanded)
            $params['item-expanded'] = 'true';

        $params['id'] = $name;

        parent::__construct('li', $content, $params);

    }

    public function items($items) {

        if($items instanceof TreeItem) {

            if(!$this->list)
                $this->add($this->list = new \Hazaar\Html\Block('ul'));

            $this->list->add($items);

        } elseif(is_array($items)) {

            if(array_key_exists('name', $items) && array_key_exists('html', $items)) {

                $expanded = (array_key_exists('expanded', $items) ? $items['expanded'] : null);

                $params = (array_key_exists('params', $items) ? $items['params'] : null);

                $item = new TreeItem($items['name'], $items['html'], null, $expanded, $params);

                $this->items($item);

                if(array_key_exists('items', $items)) {

                    $item->items($items['items']);

                }

            } else {

                foreach($items as $item) {

                    $this->items($item);

                }

            }

        }

        return $this;

    }

}
