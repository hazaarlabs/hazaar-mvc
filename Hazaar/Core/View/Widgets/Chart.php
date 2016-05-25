<?php

namespace Hazaar\View\Widgets;

/**
 * @detail          ListMenu widget.
 *
 * @since           1.1
 */
class Chart extends Widget {

    /**
     * @detail      Initialise an ListMenu widget
     *
     * @param       string $name The name (ID) of the widget to create.
     *
     * @param       mixed $items The initial items of the input.
     *
     * @param       array $params Optional additional parameters
     */
    function __construct($name, $params = array()) {

        parent::__construct('div', $name, $params);

    }

    public function width($width) {

        $this->element->style->width = \Hazaar\Html\Style::px($width);

        return $this;

    }

    public function height($height) {

        $this->element->style->height = \Hazaar\Html\Style::px($height);

        return $this;

    }

    public function title($value) {

        return $this->set('title', $value, 'string');

    }

    public function description($value) {

        return $this->set('description', $value, 'string');

    }

    public function showBorderLine($value) {

        return $this->set('showBorderLine', $value, 'bool');

    }

    public function borderLineColor($value) {

        return $this->set('borderLineColor', $value, 'string');

    }

    public function borderLineWidth($value) {

        return $this->set('borderLineWidth', $value, 'int');

    }

    public function backgroundColor($value) {

        return $this->set('backgroundColor', $value, 'string');

    }

    public function backgroundImage($value) {

        return $this->set('backgroundImage', $value, 'string');

    }

    public function showLegend($value) {

        return $this->set('showLegend', $value, 'bool');

    }

    public function padding($top = 0, $right = 0, $bottom = 0, $left = 0) {

        $value = array(
            'top' => $top,
            'right' => $right,
            'bottom' => $bottom,
            'left' => $left
        );

        return $this->set('padding', $value);

    }

    public function titlePadding($top = 0, $right = 0, $bottom = 0, $left = 0) {

        $value = array(
            'top' => $top,
            'right' => $right,
            'bottom' => $bottom,
            'left' => $left
        );

        return $this->set('titlePadding', $value);

    }

    public function colorScheme($value) {

        return $this->set('colorScheme', $value, 'string');

    }

    public function greyScale($value) {

        return $this->set('greyScale', $value, 'bool');

    }

    public function showToolTips($value) {

        return $this->set('showToolTips', $value, 'bool');

    }

    public function toolTipShowDelay($value) {

        return $this->set('toolTipShowDelay', $value, 'int');

    }

    public function toolTipHideDelay($value) {

        return $this->set('toolTipHideDelay', $value, 'int');

    }

    public function source($value) {

        return $this->set('source', $value);

    }

    public function categoryAxis($value) {

        return $this->set('categoryAxis', $value);

    }

    public function seriesGroups($value) {

        return $this->set('seriesGroups', $value);

    }

    public function enableAnimations($value) {

        return $this->set('enableAnimations', $value, 'bool');

    }

    public function animationDuration($value) {

        return $this->set('animationDuration', $value, 'int');

    }

    public function renderEngine($value) {

        return $this->set('renderEngine', $value, 'string');

    }

    //Events

    public function onMouseover($code) {

        return $this->event('mouseover', $code);

    }

    public function onMouseout($code) {

        return $this->event('mouseout', $code);

    }

    public function onClick($code) {

        return $this->event('click', $code);

    }

    //Methods

    public function refresh() {

        return $this->method('refresh');

    }

    public function addColorScheme($schemeName, $colours = array()) {

        return $this->method('addColorScheme', $schemeName, $colours);

    }

    public function removeColorScheme($schemeName) {

        return $this->method('removeColorScheme', $schemeName);

    }

    public function getColorScheme($schemeName) {

        return $this->method('getColorScheme', $schemeName);

    }

    public function saveAsJPEG($filenName = 'chart.jpg', $exportServer = null) {

        return $this->method('saveAsJPEG', $filename, $exportServer);

    }

    public function saveAsPNG($filename = 'chart.png', $exportServer = null) {

        return $this->method('saveAsPNG', $filename, $exportServer);

    }

}
