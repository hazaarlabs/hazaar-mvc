<?php

namespace Hazaar\View\Widgets;

/**
 * @detail          Basic button widget.
 *
 * @since           1.1
 */
class Grid extends Widget {

    /**
     * @detail      Initialise a grid
     *
     * @param       string $id The ID of the button element to create.
     */
    function __construct($name) {

        parent::__construct('div', $name, false);

    }

    /**
     * @detail      Enables or disables the alternating rows.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function altrows($value) {

        return $this->set('altrows', $value, 'bool');

    }

    /**
     * @detail      This property specifies the first alternating row.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function altstart($value) {

        return $this->set('altstart', $value, 'int');

    }

    /**
     * @detail      Sets or gets the alternating step
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function altstep($value) {

        return $this->set('altstep', $value, 'int');

    }

    /**
     * @detail      Determines whether the Grid should display the built-in loading element or should use a DIV tag with
     *              class 'jqx-grid-load'
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function showdefaultloadelement($value) {

        return $this->set('showdefaultloadelement', $value, 'bool');

    }

    /**
     * @detail      Determines whether the loading image should be displayed until the Grid's data is loaded.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function autoshowloadelement($value) {

        return $this->set('autoshowloadelement', $value, 'bool');

    }

    /**
     * @detail      Displays the filter icon only when the column is filtered. When the value of this property is set to
     *              false, all grid columns will display a filter icon when the filtering is enabled.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function autoshowfiltericon($value) {

        return $this->set('autoshowfiltericon', $value, 'bool');

    }

    /**
     * @detail      When the value of this property is true, a close button is displayed in each grouping column.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function closeablegroups($value) {

        return $this->set('closeablegroups', $value, 'bool');

    }

    /**
     * @detail      The function is called when a key is pressed. If the result of the function is true, the default
     *              keyboard navigation will be overriden for the pressed key.
     *
     * @since       1.1
     *
     * @param       mixed $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function handlekeyboardnavigation($value) {

        return $this->set('handlekeyboardnavigation', $value);

    }

    /**
     * @detail      Determines whether ellipsis will be displayed, if the cells or columns content overflows.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function enableellipsis($value) {

        return $this->set('enableellipsis', $value, 'bool');

    }

    /**
     * @detail      Determines whether mousewheel scrolling is enabled.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function enablemousewheel($value) {

        return $this->set('enablemousewheel', $value, 'bool');

    }

    /**
     * @detail      Sets or gets the columns menu width.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function columnsmenuwidth($value) {

        return $this->set('columnsmenuwidth', $value, 'int');

    }

    /**
     * @detail      Sets or gets whether the columns menu button will be displayed only when the mouse cursor is over a
     *              columns header or will be always displayed.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function autoshowcolumnsmenubutton($value) {

        return $this->set('autoshowcolumnsmenubutton', $value, 'bool');

    }

    /**
     * @detail      When the enablerowdetailsindent is true, the content of a details row is displayed with left offset
     *              equal to the width of the row details column.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function enablerowdetailsindent($value) {

        return $this->set('enablerowdetailsindent', $value, 'bool');

    }

    /**
     * @detail      Enables or disables the grid animations.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function enableanimations($value) {

        return $this->set('enableanimations', $value, 'bool');

    }

    /**
     * @detail      Enables or disables the grid tooltips.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function enabletooltips($value) {

        return $this->set('enabletooltips', $value, 'bool');

    }

    /**
     * @detail      Enables or disables the grid rows hover state.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function enablehover($value) {

        return $this->set('enablehover', $value, 'bool');

    }

    /**
     * @detail      Enables the text selection of the browser.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function enablebrowserselection($value) {

        return $this->set('enablebrowserselection', $value, 'bool');

    }

    /**
     * @detail      This function is called when a group is rendered. You can use it to customize the default group
     *              rendering.
     *
     * @since       1.1
     *
     * @param       mixed $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function groupsrenderer($value) {

        return $this->set('groupsrenderer', $value);

    }

    /**
     * @detail      Sets or gets a custom renderer for the grouping columns displayed in the grouping header when the
     *              grouping feature is enabled.
     *
     * @since       1.1
     *
     * @param       mixed $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function groupcolumnrenderer($value) {

        return $this->set('groupcolumnrenderer', $value);

    }

    /**
     * @detail      Sets or gets the default state of the grouped rows.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function groupsexpandedbydefault($value) {

        return $this->set('groupsexpandedbydefault', $value, 'bool');

    }

    /**
     * @detail      The function is called when the Grid Pager is rendered. This allows you to customize the default
     *              rendering of the pager.
     *
     * @since       1.1
     *
     * @param       mixed $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function pagerrenderer($value) {

        return $this->set('pagerrenderer', $value);

    }

    /**
     * @detail      When this property is true, the Grid adds an additional visual style to the grid cells in the filter
     *              column(s).
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function showfiltercolumnbackground($value) {

        return $this->set('showfiltercolumnbackground', $value, 'bool');

    }

    /**
     * @detail      Determines whether to display the filtering items in the column's menu.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function showfiltermenuitems($value) {

        return $this->set('showfiltermenuitems', $value, 'bool');

    }

    /**
     * @detail      When this property is true, the Grid adds an additional visual style to the grid cells in the pinned
     *              column(s).
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function showpinnedcolumnbackground($value) {

        return $this->set('showpinnedcolumnbackground', $value, 'bool');

    }

    /**
     * @detail      When this property is true, the Grid adds an additional visual style to the grid cells in the sort
     *              column.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function showsortcolumnbackground($value) {

        return $this->set('showsortcolumnbackground', $value, 'bool');

    }

    /**
     * @detail      Determines whether to display the sort menu items.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function showsortmenuitems($value) {

        return $this->set('showsortmenuitems', $value, 'bool');

    }

    /**
     * @detail      Determines whether to display the group menu items.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function showgroupmenuitems($value) {

        return $this->set('showgroupmenuitems', $value, 'bool');

    }

    /**
     * @detail      Shows an additional column with expand/collapse toggle buttons when the Row details feature is
     *              enabled.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function showrowdetailscolumn($value) {

        return $this->set('showrowdetailscolumn', $value, 'bool');

    }

    /**
     * @detail      Shows or hides the columns header.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function showheader($value) {

        return $this->set('showheader', $value, 'bool');

    }

    /**
     * @detail      Shows or hides the groups header area.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function showgroupsheader($value) {

        return $this->set('showgroupsheader', $value, 'bool');

    }

    /**
     * @detail      Shows or hides the aggregates in the grid's statusbar.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function showaggregates($value) {

        return $this->set('showaggregates', $value, 'bool');

    }

    /**
     * @detail      Shows or hides the filter row.
     *
     *              Possible Values:
     *              * 'textbox' - input field
     *              * 'checkedlist' - dropdownlist with checkboxes that specify which records should be visible and
     * hidden
     *              * 'list' - dropdownlist which specifies the visible records depending on the selection
     *              * 'number' - numeric input field
     *              * 'checkbox' - filter for boolean data
     *              * 'date' - filter for dates
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function showfilterrow($value) {

        return $this->set('showfilterrow', $value, 'bool');

    }

    /**
     * @detail      Shows or hides the empty row label when the Grid has no records to display.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function showemptyrow($value) {

        return $this->set('showemptyrow', $value, 'bool');

    }

    /**
     * @detail      Shows or hides the grid's statusbar.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function showstatusbar($value) {

        return $this->set('showstatusbar', $value, 'bool');

    }

    /**
     * @detail      Sets the statusbar's height.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function statusbarheight($value) {

        return $this->set('statusbarheight', $value, 'int');

    }

    /**
     * @detail      Shows or hides the grid's toolbar.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function showtoolbar($value) {

        return $this->set('showtoolbar', $value, 'bool');

    }

    /**
     * @detail      Sets or gets the toolbar's height.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function toolbarheight($value) {

        return $this->set('toolbarheight', $value, 'int');

    }

    /**
     * @detail      Sets or gets the selection mode.
     *
     *              Possible Values:
     *              * 'none'-disables the selection
     *              * 'singlerow'- full row selection
     *              * 'multiplerows' - each click selects a new row. Click on a selected row unselects it
     *              * 'multiplerowsextended' - multiple rows selection with drag and drop. The selection behavior
     * resembles the selection of icons on your desktop
     *              * 'singlecell' - single cell selection
     *              * 'multiplecells' - each click selects a new cell. Click on a selected cell unselects it
     *              * 'multiplecellsextended' - in this mode, users can select multiple cells with a drag and drop. The
     * selection behavior resembles the selection of icons on your desktop
     *              * 'multiplecellsadvanced' -this mode is the most advanced cells selection mode. In this mode, users
     * can select multiple cells with a drag and drop. The selection behavior resembles the selection of cells in a
     * spreadsheet
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function selectionmode($value) {

        return $this->set('selectionmode', $value, 'string');

    }

    /**
     * @detail      Sets or gets the height of the Grid Pager.
     *
     * @since       1.1
     *
     * @param       mixed $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function pagerheight($value) {

        return $this->set('pagerheight', $value);

    }

    /**
     * @detail      Sets or gets the height of the Grid Groups Header.
     *
     * @since       1.1
     *
     * @param       mixed $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function groupsheaderheight($value) {

        return $this->set('groupsheaderheight', $value);

    }

    /**
     * @detail      Sets or gets the height of the grid rows.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function rowsheight($value) {

        return $this->set('rowsheight', $value, 'int');

    }

    /**
     * @detail      Sets or gets the columns height.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function columnsheight($value) {

        return $this->set('columnsheight', $value, 'int');

    }

    /**
     * @detail      Sets or gets the group indent size. This size is used when the grid is grouped. This is the size of
     *              the columns with expand/collapse toggle buttons.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function groupindentwidth($value) {

        return $this->set('groupindentwidth', $value, 'int');

    }

    /**
     * @detail      Sets or gets the height of the grid to be equal to the summary height of the grid rows. This option
     *              should be set when the Grid is in paging mode.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function autoheight($value) {

        return $this->set('autoheight', $value, 'bool');

    }

    /**
     * @detail      This property works along with the "autoheight" property. When it is set to true, the height of the
     *              Grid rows is dynamically changed depending on the cell values.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function autorowheight($value) {

        return $this->set('autorowheight', $value, 'bool');

    }

    /**
     * @detail      Sets or gets the scrollbars size.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function scrollbarsize($value) {

        return $this->set('scrollbarsize', $value, 'int');

    }

    /**
     * @detail      Determines the scrolling mode.
     *
     *              Possible Values:
     *              * 'default'
     *              * 'logical'- the movement of the scrollbar thumb is by row, not by pixel
     *              * 'deferred'-content is stationary when the user drags the Thumb of a ScrollBar
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function scrollmode($value) {

        return $this->set('scrollmode', $value, 'string');

    }

    /**
     * @detail      Determines the cell values displayed in a tooltip next to the scrollbar when the "scrollmode" is set
     *              to "deferred".
     *
     * @since       1.1
     *
     * @param       mixed $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function deferreddatafields($value) {

        return $this->set('deferreddatafields', $value);

    }

    /**
     * @detail      When the "scrollmode" is set to "deferred", the "scrollfeedback" function may be used to display
     *              custom UI Tooltip next to the scrollbar.
     *
     * @since       1.1
     *
     * @param       mixed $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function scrollfeedback($value) {

        return $this->set('scrollfeedback', $value);

    }

    /**
     * @detail      Sets or gets the scrollbar's step when the user clicks the scroll arrows.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function verticalscrollbarstep($value) {

        return $this->set('verticalscrollbarstep', $value, 'int');

    }

    /**
     * @detail      Sets or gets the scrollbar's large step. This property specifies the step with which the vertical
     *              scrollbar's value is changed when the user clicks the area above or below the thumb.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function verticalscrollbarlargestep($value) {

        return $this->set('verticalscrollbarlargestep', $value, 'int');

    }

    /**
     * @detail      Sets or gets the scrollbar's step when the user clicks the scroll arrows.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function horizontalscrollbarstep($value) {

        return $this->set('horizontalscrollbarstep', $value, 'int');

    }

    /**
     * @detail      Sets or gets the scrollbar's large step. This property specifies the step with which the horizontal
     *              scrollbar's value is changed when the user clicks the area above or below the thumb.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function horizontalscrollbarlargestep($value) {

        return $this->set('horizontalscrollbarlargestep', $value, 'int');

    }

    /**
     * @detail      Enables or disables the keyboard navigation.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function keyboardnavigation($value) {

        return $this->set('keyboardnavigation', $value, 'bool');

    }

    /**
     * @detail      Determines whether the Grid automatically saves its current state.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function autosavestate($value) {

        return $this->set('autosavestate', $value, 'bool');

    }

    /**
     * @detail      Determines whether the Grid automatically loads its current state(if there's already saved one). The
     *              Grid's state is loaded when the page is refreshed.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function autoloadstate($value) {

        return $this->set('autoloadstate', $value, 'bool');

    }

    /**
     * @detail      Sets or gets the number of visible rows per page when the Grid paging is enabled.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function pagesize($value) {

        return $this->set('pagesize', $value, 'int');

    }

    /**
     * @detail      Sets or gets the available page size options.
     *
     * @since       1.1
     *
     * @param       mixed $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function pagesizeoptions($value) {

        return $this->set('pagesizeoptions', $value);

    }

    /**
     * @detail      Enables or disables the row details. When this option is enabled, the Grid can show additional
     *              information below each grid row.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function rowdetails($value) {

        return $this->set('rowdetails', $value, 'bool');

    }

    /**
     * @detail      This function is called when the user expands the row details and the details are going to be
     *              rendered.
     *
     * @since       1.1
     *
     * @param       mixed $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function initrowdetails($value) {

        return $this->set('initrowdetails', $value);

    }

    /**
     * @detail      Determines the template of the row details. The rowdetails field specifies the HTML used for details.
     *              The rowdetailsheight specifies the height of the details.
     *
     * @since       1.1
     *
     * @param       mixed $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function rowdetailstemplate($value) {

        return $this->set('rowdetailstemplate', $value);

    }

    /**
     * @detail      This function is called when the grid is initialized and the binding is complete.
     *
     * @since       1.1
     *
     * @param       mixed $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function ready($value) {

        return $this->set('ready', $value);

    }

    /**
     * @detail      Enables or disables the Grid Paging feature. When the value of this property is true, the Grid
     *              displays a pager below the rows.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function pageable($value) {

        return $this->set('pageable', $value, 'bool');

    }

    /**
     * @detail      Enables or disables the Grid Filtering feature. When the value of this property is true, the Grid
     *              displays a filtering panel in the columns popup menus.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function filterable($value) {

        return $this->set('filterable', $value, 'bool');

    }

    /**
     * @detail      The editable property enables or disables the Grid editing feature.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function editable($value) {

        return $this->set('editable', $value, 'bool');

    }

    /**
     * @detail      The editmode property specifies the action that the end-user should make to open an editor.
     *
     *              Possible Values:
     *              * 'click' - Marks the clicked cell as selected and shows the editor. The editor’s value is equal to
     * the cell’s value
     *              * 'selectedcell' - Marks the cell as selected. A second click on the selected cell shows the editor.
     * The editor’s value is equal to the cell’s value
     *              * 'dblclick' - Marks the clicked cell as selected and shows the editor. The editor’s value is equal
     * to the cell’s value
     *              * 'programmatic' - Cell editors are activated and deactivated only through the API(see begincelledit
     * and endcelledit methods)
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function editmode($value) {

        return $this->set('editmode', $value, 'string');

    }

    /**
     * @detail      The sortable property enables or disables the sorting feature.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function sortable($value) {

        return $this->set('sortable', $value, 'bool');

    }

    /**
     * @detail      This property enables or disables the grouping feature.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function groupable($value) {

        return $this->set('groupable', $value, 'bool');

    }

    /**
     * @detail      Sets or gets the Grid groups when the Grouping feature is enabled.
     *
     * @since       1.1
     *
     * @param       mixed $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function groups($value) {

        return $this->set('groups', $value);

    }

    /**
     * @detail      Sets or gets the Grid columns.
     *
     * @since       1.1
     *
     * @param       mixed $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function columns(Array $value) {

        return $this->set('columns', $value);

    }

    /**
     * @detail      Selects a row at a specified index.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function selectedrowindex($value) {

        return $this->set('selectedrowindex', $value, 'int');

    }

    /**
     * @detail      Selects single or multiple rows.
     *
     * @since       1.1
     *
     * @param       mixed $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function selectedrowindexes($value) {

        return $this->set('selectedrowindexes', $value);

    }

    /**
     * @detail      The source object represents a set of key/value pairs.
     *
     *              * url: A string containing the URL to which the request is sent.
     *              * data: Data to be sent to the server.
     *              * localdata: data array or data string pointing to a local data source.
     *              * datatype: the data's type. Possible values: 'xml', 'json', 'jsonp', 'tsv', 'csv', 'local', 'array',
     *              'observablearray'.
     *              * type: The type of request to make ("POST" or "GET"), default is "GET".
     *              * id: A string containing the Id data field.
     *              * root: A string describing where the data begins and all other loops begin from this element.
     *              * record: A string describing the information for a particular record.
     *
     * @since       1.1
     *
     * @param       mixed $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function source(DataAdapter $value) {

        return $this->set('source', $value);

    }

    /**
     * @detail      Sets or gets the rendering update delay. This could be used for deferred scrolling scenarios.
     *
     * @since       1.1
     *
     * @param       int $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function updatedelay($value) {

        return $this->set('updatedelay', $value, 'int');

    }

    /**
     * @detail      Enables or disables the virtual data mode.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function virtualmode($value) {

        return $this->set('virtualmode', $value, 'bool');

    }

    /**
     * @detail      Enables or disables the columns dropdown menu.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function columnsmenu($value) {

        return $this->set('columnsmenu', $value, 'bool');

    }

    /**
     * @detail      Enables or disables the columns resizing.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function columnsresize($value) {

        return $this->set('columnsresize', $value, 'bool');

    }

    /**
     * @detail      Enables or disables the columns reordering.
     *
     * @since       1.1
     *
     * @param       bool $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function columnsreorder($value) {

        return $this->set('columnsreorder', $value, 'bool');

    }

    /**
     * @detail      Callback function which is called when the jqxGrid's render function is called either internally or
     *              not.
     *
     * @since       1.1
     *
     * @param       mixed $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function rendered($value) {

        return $this->set('rendered', $value);

    }

    /**
     * @detail      Callback function which allows you to customize the rendering of the Grid's statusbar.
     *
     * @since       1.1
     *
     * @param       mixed $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function renderstatusbar($value) {

        return $this->set('renderstatusbar', $value);

    }

    /**
     * @detail      Callback function which allows you to customize the rendering of the Grid's toolbar.
     *
     * @since       1.1
     *
     * @param       mixed $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function rendertoolbar($value) {

        return $this->set('rendertoolbar', $value);

    }

    /**
     * @detail      Sets the sort toggle states.
     *
     *              Possible Values:
     *              * '0'-disables toggling
     *              * '1'-enables togging. Click on a column toggles the sort direction
     *              * '2'-enables remove sorting option
     *
     * @since       1.1
     *
     * @param       string $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function sorttogglestates($value) {

        return $this->set('sorttogglestates', $value, 'string');

    }

    /**
     * @detail      This is a function called when the grid is used in virtual mode. The function should return an array
     *              of rows which will be rendered by the Grid.
     *
     * @since       1.1
     *
     * @param       mixed $value The value to set
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function rendergridrows($value) {

        return $this->set('rendergridrows', $value);

    }

    /**
     * @detail      This event is triggered when the Grid is initialized.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function onInitialized($code) {

        return $this->event('initialized', $code);

    }

    /**
     * @detail      This event is triggered when a row is clicked.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function onRowClick($code) {

        return $this->event('rowclick', $code);

    }

    /**
     * @detail      This event is triggered when a row is double clicked.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function onRowDoubleClick($code) {

        return $this->event('rowdoubleclick', $code);

    }

    /**
     * @detail      This event is triggered when a row is selected.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function onRowSelect($code) {

        return $this->event('rowselect', $code);

    }

    /**
     * @detail      This event is triggered when a row is unselected.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function onRowUnselect($code) {

        return $this->event('rowunselect', $code);

    }

    /**
     * @detail      This event is triggered when a row with details is expanded.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function onRowExpand($code) {

        return $this->event('rowexpand', $code);

    }

    /**
     * @detail      This event is triggered when a row with details is collapsed.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function onRowCollapse($code) {

        return $this->event('rowcollapse', $code);

    }

    /**
     * @detail      This event is triggered when a group is expanded.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function onGroupExpand($code) {

        return $this->event('groupexpand', $code);

    }

    /**
     * @detail      This event is triggered when a group is collapsed.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function onGroupCollapse($code) {

        return $this->event('groupcollapse', $code);

    }

    /**
     * @detail      This event is triggered when the Grid is sorted.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function onSort($code) {

        return $this->event('sort', $code);

    }

    /**
     * @detail      This event is triggered when the Grid is filtered.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function onFilter($code) {

        return $this->event('filter', $code);

    }

    /**
     * @detail      This event is triggered when a Grid Column is resized.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function onColumnResized($code) {

        return $this->event('columnresized', $code);

    }

    /**
     * @detail      This event is triggered when a Grid Column is moved to a new position.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function onColumnReordered($code) {

        return $this->event('columnreordered', $code);

    }

    /**
     * @detail      This event is triggered when a column is clicked.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function onColumnClick($code) {

        return $this->event('columnclick', $code);

    }

    /**
     * @detail      This event is triggered when a cell is clicked.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function onCellClick($code) {

        return $this->event('cellclick', $code);

    }

    /**
     * @detail      This event is triggered when a cell is double-clicked.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function onCellDoubleClick($code) {

        return $this->event('celldoubleclick', $code);

    }

    /**
     * @detail      This event is triggered when a cell is selected.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function onCellSelect($code) {

        return $this->event('cellselect', $code);

    }

    /**
     * @detail      This event is triggered when a cell is unselected.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function onCellUnselect($code) {

        return $this->event('cellunselect', $code);

    }

    /**
     * @detail      This event is triggered when a cell's value is changed.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function onCellValueChanged($code) {

        return $this->event('cellvaluechanged', $code);

    }

    /**
     * @detail      This event is triggered when a cell's editor is displayed.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function onCellBeginEdit($code) {

        return $this->event('cellbeginedit', $code);

    }

    /**
     * @detail      This event is triggered when a cell's edit operation has ended.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function onCellEndEdit($code) {

        return $this->event('cellendedit', $code);

    }

    /**
     * @detail      This event is triggered when the current page is changed.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function onPageChanged($code) {

        return $this->event('pagechanged', $code);

    }

    /**
     * @detail      This event is triggered when the page size is changed.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function onPageSizeChanged($code) {

        return $this->event('pagesizechanged', $code);

    }

    /**
     * @detail      This event is triggered when the binding is completed.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function onBindingComplete($code) {

        return $this->event('bindingcomplete', $code);

    }

    /**
     * @detail      This event is triggered when a group is added, inserted or removed.
     *
     * @since       1.1
     *
     * @param       string $code The code to execute when the event is triggered.
     *
     * @return      \\Hazaar\\Widgets\\Grid
     */
    public function onGroupsChanged($code) {

        return $this->event('groupschanged', $code);

    }

    /**
     * @detail      Gets the index of a column in the columns collection.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function getcolumnindex($column) {

        return $this->method('getcolumnindex', $column);

    }

    /**
     * @detail      Sets the index of a column in the columns collection.
     *
     * @param       string $name The column datafield member.
     *
     * @param       number $value The column's number.
     */
    public function setcolumnindex($name, $value) {

        return $this->method('setcolumnindex', $name, $value);

    }

    /**
     * @detail      Gets a column by datafield value.Column's fields:
     *
     *              * datafield - column's datafield. To get the cells labels and values from the data source, the Grid
     *              uses the "datafield" and "displayfield" properties. If the "displayfield" is not set, the
     *              "displayfield" is equal
     *              to the "datafield'.
     *              * text - column's text.
     *              * displayfield - column's displayfield. To get the cells labels and values from the data source, the
     *              Grid uses the "datafield" and "displayfield" properties. If the "displayfield" is not set, the
     *              "displayfield" is equal to the "datafield'.
     *              * sortable - determines whether the column is sortable.
     *              * filterable - determines whether the column is filterable.
     *              * exportable - determines whether the column will be exported through the "exportdata" method.
     *              * editable - determines whether the column is editable.
     *              * groupable - determines whether the column is groupable.
     *              * resizable - determines whether the column is resizable.
     *              * draggable - determines whether the column is draggable.
     *              * classname - determines the column's header classname.
     *              * cellclassname - determines the column's cells classname.
     *              * width - determines the column's width.
     *              * menu - determines whether the column has an associated popup menu or not.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function getcolumn($value) {

        return $this->method('getcolumn', $value);

    }

    /**
     * @detail      Sets a property of a column. Possible property names: 'text', 'hidden', 'hideable', 'renderer',
     *              'cellsrenderer', 'align', 'cellsalign', 'cellsformat', 'pinned', 'contenttype', 'resizable',
     *              'filterable', 'editable', 'cellclassname', 'classname', 'width', 'minwidth', 'maxwidth'
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function setcolumnproperty($column, $property, $value) {

        return $this->method('setcolumnproperty', $column, $property, $value);

    }

    /**
     * @detail      Gets a property of a column. Possible property names: 'text', 'hidden', 'hideable', 'renderer',
     *              'cellsrenderer', 'align', 'cellsalign', 'cellsformat', 'pinned', 'contenttype', 'resizable',
     *              'filterable', 'editable', 'cellclassname', 'classname', 'width', 'minwidth', 'maxwidth'
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function getcolumnproperty($column, $property) {

        return $this->method('getcolumnproperty', $column, $property);

    }

    /**

     * @detail      Hides a column.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */

    public function hidecolumn($column) {

        return $this->method('hidecolumn', $column);

    }

    /**

     * @detail      Shows a column.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */

    public function showcolumn($column) {

        return $this->method('showcolumn', $column);

    }

    /**
     * @detail      Gets whether a column is visible. Returns a boolean value.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function iscolumnvisible($column) {

        return $this->method('iscolumnvisible', $column);

    }

    /**
     * @detail      Gets whether a column is hideable. Returns a boolean value.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function iscolumnhideable($column) {

        return $this->method('iscolumnhideable', $column);

    }

    /**

     * @detail      Pins the column.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */

    public function pincolumn($column) {

        return $this->method('pincolumn', $column);

    }

    /**
     * @detail      Unpins the column.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function unpincolumn($column) {

        return $this->method('unpincolumn', $column);

    }

    /**
     * @detail      Gets whether a column is pinned. Returns a boolean value.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function iscolumnpinned($column) {

        return $this->method('iscolumnpinned', $column);

    }

    /**
     * @detail      Auto-resizes all columns.
     *
     *              Optional parameter:
     *              * 'all' - resize columns to fit to cells and column header. This is the default option.
     *              * 'cells' - resize columns to fit to the cells text.
     *              * 'column' - resize columns to fit to the columns text.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function autoresizecolumns($mode = null) {

        return $this->method('autoresizecolumns', $mode);

    }

    /**
     * @detail      Auto-resizes a column.
     *
     *              First Parameter - the column's datafield.
     *              Second Parameter(optional:
     *              * 'all' - resize columns to fit to cells and column header. This is the default option.
     *              * 'cells' - resize columns to fit to the cells text.
     *              * 'column' - resize columns to fit to the columns text.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function autoresizecolumn($column, $mode = null) {

        return $this->method('autoresizecolumn', $column, $mode);

    }

    /**
     * @detail      Shows the data loading image.
     *
     * @since       1.1
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function showloadingelement() {

        return $this->method('showloadingelement');

    }

    /**
     * @detail      Hides the data loading image.
     *
     * @since       1.1
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function hideloadingelement() {

        return $this->method('hideloadingelement');

    }

    /**
     * @detail      Sets details to a row.
     *
     * @since       1.1
     *
     * @param       int $index The row index.
     *
     * @param       string $details Row details.
     *
     * @param       int $height Height or Row Details.
     *
     * @param       bool $state Hidden state.
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function setrowdetails($index, $details, $height, $state) {

        return $this->method('setrowdetails');

    }

    /**
     * @detail      Gets the rows details.
     *
     *              Returns an object with the following fields:
     *              * rowdetailshidden - determines whether the details are visible or not.
     *              * rowdetailsheight - determines the details height.
     *              * rowdetails - HTML string which contains the row details.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function getrowdetails($index) {

        return $this->method('getrowdetails', $index);

    }

    /**
     * @detail      Shows the details of a row.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function showrowdetails($index) {

        return $this->method('showrowdetails', $index);

    }

    /**

     * @detail      Hides the details of a row.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function hiderowdetails($index) {

        return $this->method('hiderowdetails', $index);

    }

    /**
     * @detail      Updates the bound data and refreshes the grid. You can pass 'filter' or 'sort' as parameter, if the
     *              update reason is change in 'filtering' or 'sorting'. To update only the data without the columns, use
     *              the 'data' parameter. To make a quick update of the cells, pass "cells" as parameter. Passing "cells"
     *              will refresh only the cells values when the new rows count is equal to the previous rows count. To
     *              make a full update, do not pass any parameter.
     *
     * @since       1.1
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function updatebounddata() {

        return $this->method('updatebounddata');

    }

    /**
     * @detail      Refreshes the data.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function refreshdata() {

        return $this->method('refreshdata');

    }

    /**
     * @detail      Repaints the Grid View.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function refresh() {

        return $this->method('refresh');

    }

    /**
     * @detail      Renders the Grid contents. This method completely refreshes the Grid cells, columns, layout and
     *              repaints the view.
     *
     * @since       1.1
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function render() {

        return $this->method('render');

    }

    /**
     * @detail      Clears the Grid contents.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function clear() {

        return $this->method('clear');

    }

    /**
     * @detail      Gets the id of a row. The returned value is a 'String' or 'Number' depending on the id's type. The
     *              parameter is the row's bound index.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function getrowid($index) {

        return $this->method('getrowid', $index);

    }

    /**
     * @detail      Gets the data of a row. The returned value is a JSON Object. The parameter is the row's bound index.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function getrowdata($index) {

        return $this->method('getrowdata', $index);

    }

    /**
     * @detail      Gets the data of a row. The returned value is a JSON Object. The parameter is the row's id.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function getrowdatabyid($index) {

        return $this->method('getrowdatabyid', $index);

    }

    /**
     * @detail      Gets the data of a row. The parameter is a visible index.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function getrenderedrowdata($index) {

        return $this->method('getrenderedrowdata', $index);

    }

    /**
     * @detail      Gets all rows. Returns an array of all rows loaded in the Grid. If the Grid is filtered, the returned
     *              value is an array of the filtered records.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function getrows() {

        return $this->method('getrows');

    }

    /**

     * @detail      Gets the rows in the view which are displayed to the user. The returned value is an Array.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function getvisiblerows() {

        return $this->method('getvisiblerows');

    }

    /**
     * @detail      Gets an array of the rows loaded in the Grid. Each record in the array includes Grid specific
     *              properties like boundindex and uid(unique row identifier).
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function getloadedrows() {

        return $this->method('getloadedrows');

    }

    /**
     * @detail      Returns the visible index of a row in jqxGrid. The parameter that is expected to be passed is bound
     *              index(i.e the row's index in your data source).
     *
     * @since       1.1
     *
     * @param       string $index
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function getvisibleindex($index) {

        return $this->method('getvisibleindex', $index);

    }

    /**
     * @detail      Returns the bound index of a row in jqxGrid. The parameter that is expected to be passed is a visible
     *              index(i.e the display index of the row).
     *
     * @since       1.1
     *
     * @param       string $index
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function getrowboundindex($index) {

        return $this->method('getrowboundindex', $index);

    }

    /**
     * @detail      Returns the bound index of a row in jqxGrid. The parameter that is expected to be passed is the row's
     *              id.
     *
     * @since       1.1
     *
     * @param       string $rowid
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function getrowboundindexbyid($rowid) {

        return $this->method('getrowboundindexbyid', $rowid);

    }

    /**
     * @detail      Gets bound data information.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function getdatainformation() {

        return $this->method('getdatainformation');

    }

    /**
     * @detail      Gets the sort information.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function getsortinformation() {

        return $this->method('getsortinformation');

    }

    /**
     * @detail      Gets the paging information.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function getpaginginformation() {

        return $this->method('getpaginginformation');

    }

    /**
     * @detail      Localizes the grid strings. This method allows you to change the valus of all Grid strings and also
     *              to change the cells formatting settings.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function localizestrings() {

        return $this->method('localizestrings');

    }

    /**
     * @detail      Scrolls the grid contents.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function scrolloffset($top, $left) {

        return $this->method('scrolloffset', $top, $left);

    }

    /**
     * @detail      Returns an object with two boolean fields - "vertical" and "horizontal". If the user scrolls with the
     *              vertical scrollbar, "vertical" field's value is true. If the user scrolls with the horizontal
     *              scrollbar, the "horizontal" field's value is true.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function scrolling() {

        return $this->method('scrolling');

    }

    /**
     * @detail      Starts an update operation. This is appropriate when calling multiple methods or set multiple
     *              properties at once.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function beginupdate() {

        return $this->method('beginupdate');

    }

    /**
     * @detail      Ends the update operation.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function endupdate() {

        return $this->method('endupdate');

    }

    /**
     * @detail      Gets the updating operation state. Returns a boolean value.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function updating() {

        return $this->method('updating');

    }

    /**
     * @detail      Scrolls to a row. The parameter is a bound index.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function ensurerowvisible($index) {

        return $this->method('ensurerowvisible', $index);

    }

    /**
     * @detail      Sorts the Grid data.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function sortby($datafield, $order = null) {

        return $this->method('sortby', $datafield, $order);

    }

    /**
     * @detail      Removes the sorting.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function removesort() {

        return $this->method('removesort');

    }

    /**
     * @detail      Gets the sort column. Returns the column's datafield or null, if sorting is not applied.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function getsortcolumn() {

        return $this->method('getsortcolumn');

    }

    /**
     * @detail      Groups by a column.
     *
     * @since       1.1
     *
     * @param       string $datafield
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function addgroup($datafield) {

        return $this->method('addgroup', $datafield);

    }

    /**
     * @detail      Groups by a column.
     *
     * @since       1.1

     * @param       string $index
     *
     * @param       string $datafield
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function insertgroup($index, $datafield) {

        return $this->method('insertgroup', $index, $datafield);

    }

    /**
     * @detail      Removes a group at specific index.
     *
     * @since       1.1
     *
     * @param       string $index
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function removegroupat($index) {

        return $this->method('removegroupat', $index);

    }

    /**
     * @detail      Removes a group.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function removegroup($datafield) {

        return $this->method('removegroup', $datafield);

    }

    /**
     * @detail      Clears all groups.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function cleargroups() {

        return $this->method('cleargroups');

    }

    /**
     * @detail      Gets the number of root groups.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function getrootgroupscount() {

        return $this->method('getrootgroupscount');

    }

    /**
     * @detail      Collapses a group.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function collapsegroup($index) {

        return $this->method('collapsegroup', $index);

    }

    /**
     * @detail      Expands a group.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function expandgroup($index) {

        return $this->method('expandgroup', $index);

    }

    /**
     * @detail      Collapses all groups.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function collapseallgroups() {

        return $this->method('collapseallgroups');

    }

    /**
     * @detail      Expands all groups.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function expandallgroups() {

        return $this->method('expandallgroups');

    }

    /**
     * @detail      Gets a group. The method returns an Object with details about the Group.
     *
     *              The object has the following fields:
     *              * group - group's name.
     *              * level - group's level in the group's hierarchy.
     *              * expanded - group's expand state.
     *              * subgroups - an array of sub groups or null.
     *              * subrows - an array of rows or null.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function getgroup($index) {

        return $this->method('getgroup', $index);

    }

    /**
     * @detail      Gets whether the user can group by a column. Returns a boolean value.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function iscolumngroupable($datafield) {

        return $this->method('iscolumngroupable', $datafield);

    }

    /**
     * @detail      Adds a filter to the Grid.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    //TODO: This will require a new object type 'Filter'.
    public function addfilter($datafield, FilterGroup $filtergroup) {

        return $this->method('addfilter', $datafield, $filtergroup);

    }

    /**
     * @detail      Removes a filter from the Grid.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function removefilter($datafield, $refresh = null) {

        return $this->method('removefilter', $datafield, $refresh);

    }

    /**
     * @detail      Clears all filters from the Grid. You can call the method with optional boolean parameter. If the
     *              parameter is "true" or you call the method without parameter, the Grid will clear the filters and
     *              refresh the Grid(default behavior). If the parameter is "false", the method will clear the filters
     *              without refreshing the Grid.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function clearfilters() {

        return $this->method('clearfilters');

    }

    /**
     * @detail      Applies all filters to the Grid.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function applyfilters() {

        return $this->method('applyfilters');

    }

    /**
     * @detail      Refreshes the filter row and updates the filter widgets. The filter row's widgets are synchronized
     *              with the applied filters.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function refreshfilterrow() {

        return $this->method('refreshfilterrow');

    }

    /**
     * @detail      Gets the information about the Grid filters. The method returns an array of the applied filters. The
     *              returned information includes the filter objects and filter columns.
     *
     *              Each filter in the Array has the following fields:
     *              * filter - a filter object which may contain one or more filters.
     *                Properties and Methods of the filter object.
     *              * * getfilters - returns an array of all filters in the filter object. Each filter in the Array has:
     *              * * * filtervalue - filter's value.
     *              * * * comparisonoperator - filter's operator. For String filter the value could be: 'EMPTY',
     *              'NOT_EMPTY', 'CONTAINS', 'CONTAINS_CASE_SENSITIVE', 'DOES_NOT_CONTAIN',
     *              'DOES_NOT_CONTAIN_CASE_SENSITIVE', 'STARTS_WITH', 'STARTS_WITH_CASE_SENSITIVE', 'ENDS_WITH',
     *              'ENDS_WITH_CASE_SENSITIVE', 'EQUAL', 'EQUAL_CASE_SENSITIVE', 'NULL', 'NOT_NULL. For Date and Number
     *              filter the value could be: 'EQUAL', 'NOT_EQUAL', 'LESS_THAN', 'LESS_THAN_OR_EQUAL', 'GREATER_THAN',
     *              'GREATER_THAN_OR_EQUAL', 'NULL', 'NOT_NULL'. For Boolean filter, the value could be: 'EQUAL',
     *              'NOT_EQUAL'
     *              * * * type - filter's type - 'stringfilter', 'numericfilter', 'booleanfilter' or 'datefilter'.
     *              * * operator - 'and' or 'or'. Determines the connection between the filters in the group.
     *              * filtercolumn - the column's datafield.
     *              * filtercolumntext - the column's text.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function getfilterinformation() {

        return $this->method('getfilterinformation');

    }

    /**
     * @detail      Navigates to a page when the Grid paging is enabled i.e when the pageable property value is true.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function gotopage($page) {

        return $this->method('gotopage', $page);

    }

    /**
     * @detail      Navigates to a previous page when the Grid paging is enabled i.e when the pageable property value is
     *              true.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function gotoprevpage() {

        return $this->method('gotoprevpage');

    }

    /**
     * @detail      Navigates to a next page when the Grid paging is enabled i.e when the pageable property value is
     *              true.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function gotonextpage() {

        return $this->method('gotonextpage');

    }

    /**
     * @detail      Gets a cell. Returns an object with the following fields:
     *
     *              * value - cell's value.
     *              * row - cell's row number.
     *              * column - column's datafield.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function getcell($index, $datafield) {

        return $this->method('getcell', $index, $datafield);

    }

    /**
     * @detail      Gets a cell. Returns an object with the following fields:
     *
     *              * value - cell's value.
     *              * row - cell's row number.
     *              * column - column's datafield.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function getrenderedcell($index, $datafield) {

        return $this->method('getrenderedcell', $index, $datafield);

    }

    /**
     * @detail      Gets a cell at specific position. Returns an object with the following fields:
     *
     *              * value - cell's value.
     *              * row - cell's row number.
     *              * column - column's datafield.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function getcellatposition($left, $top) {

        return $this->method('getcellatposition', $left, $top);

    }

    /**
     * @detail      Gets the text of a cell.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function getcelltext($index, $datafield) {

        return $this->method('getcelltext', $index, $datafield);

    }

    /**
     * @detail      Gets the value of a cell.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function getcellvalue($index, $datafield) {

        return $this->method('getcellvalue', $index, $datafield);

    }

    /**
     * @detail      Sets a new value to a cell.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function setcellvalue($index, $cell, $value) {

        return $this->method('setcellvalue', $index, $cell, $value);

    }

    /**
     * @detail      Shows the cell's editor.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function begincelledit($index, $cell) {

        return $this->method('begincelledit', $index, $cell);

    }

    /**
     * @detail      Hides the edit cell's editor and saves or cancels the changes.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function endcelledit($index, $datafield, $cancel = null) {

        return $this->method('endcelledit', $index, $datafield, $cancel);

    }

    /**
     * @detail      Displays a validation popup below a Grid cell.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function showvalidationpopup($index, $column, $value) {

        return $this->method('showvalidationpopup', $index, $column, $value);

    }

    /**
     * @detail      Updates a row.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function updaterow($index, Array $data) {

        return $this->method('updaterow', $index, $data);

    }

    /**
     * @detail      Deletes a row. Returns a boolean value.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function deleterow($index) {

        return $this->method('deleterow', $index);

    }

    /**
     * @detail      Adds a row.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function addrow($index, $data) {

        return $this->method('addrow', $index, $data);

    }

    /**
     * @detail      The expected selection mode is 'singlerow', 'multiplerows' or 'multiplerowsextended'
     *
     *              Selects a row.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function selectrow($index) {

        return $this->method('selectrow', $index);

    }

    /**
     * @detail      The expected selection mode is 'singlerow', 'multiplerows' or 'multiplerowsextended'
     *
     *              Unselects a row.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function unselectrow($index) {

        return $this->method('unselectrow', $index);

    }

    /**
     * @detail      The selection mode should be set to: 'multiplerows' or 'multiplerowsextended'
     *
     *              Selects all Grid rows.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function selectallrows() {

        return $this->method('selectallrows');

    }

    /**
     * @detail      The expected selection mode is 'singlecell', 'multiplecells' or 'multiplecellsextended'
     *
     *              Selects a cell.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function selectcell($index, $column) {

        return $this->method('selectcell', $index, $column);

    }

    /**
     * @detail      The expected selection mode is 'singlecell', 'multiplecells' or 'multiplecellsextended'
     *
     *              Unselects a cell.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function unselectcell($index, $column) {

        return $this->method('unselectcell', $index, $column);

    }

    /**
     * @detail      Clears the selection.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function clearselection() {

        return $this->method('clearselection');

    }

    /**
     * @detail      The expected selection mode is 'singlerow', 'multiplerows' or 'multiplerowsextended'
     *
     *              Gets the bound index of the selected row. Returns -1, if there's no selection.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function getselectedrowindex() {

        return $this->method('getselectedrowindex');

    }

    /**
     * @detail      The expected selection mode is 'singlerow', 'multiplerows' or 'multiplerowsextended'
     *
     *              Gets the indexes of the selected rows. Returns an array of the selected rows.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function getselectedrowindexes() {

        return $this->method('getselectedrowindexes');

    }

    /**
     * @detail      The expected selection mode is 'singlecell', 'multiplecells' or 'multiplecellsextended'
     *
     *              Gets the selected cell. The returned value is an Object with two fields: 'rowindex' - the row's bound
     *              index and 'datafield' - the column's datafield.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function getselectedcell() {

        return $this->method('getselectedcell');

    }

    /**
     * @detail      The expected selection mode is 'singlecell', 'multiplecells' or 'multiplecellsextended'
     *
     *              Gets all selected cells. Returns an array of all selected cells. Each cell in the array is an Object
     *              with two fields: 'rowindex' - the row's bound index and 'datafield' - the column's datafield.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function getselectedcells() {

        return $this->method('getselectedcells');

    }

    /**
     * @detail      Refreshes the Aggregates in the Grid's status bar.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function refreshaggregates() {

        return $this->method('refreshaggregates');

    }

    /**
     * @detail      Renders the aggregates in the Grid's status bar.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function renderaggregates() {

        return $this->method('renderaggregates');

    }

    /**
     * @detail      Gets the aggregated data of a Grid column. Returns a JSON object. Each field name is the aggregate's
     *              type('min', 'max', 'sum', etc.).
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function getcolumnaggregateddata($column, $aggregate) {

        return $this->method('getcolumnaggregateddata', $column, $aggregate);

    }

    /**
     * @detail      Exports all rows loaded within the Grid to Excel, XML, CSV, TSV, HTML, PDF or JSON.
     *
     *              The first parameter of the export method determines the export's type - 'xls', 'xml', 'html', 'json',
     *              'tsv' or 'csv'. The second parameter is the file's name. If you don't provide a file name, the Grid
     *              will export the data to a local variable. For example:
     *
     *                  @var data = $("#jqxgrid").jqxGrid('exportdata', 'json');@
     *
     *              The third parameter is optional and determines whether to export the column's header or not.
     *              Acceptable values are - true and false. By default, the exporter exports the columns header.  The
     *              fourth parameter is optional and determines the array of rows to be exported. By default all rows are
     *              exported.  Set null, if you want all rows to be exported. The fifth parameter is optional and
     *              determines whether to export hidden columns. Acceptable values are - true and false. By default, the
     *              exporter does not export the hidden columns. The last parameter is optional and determines the url of
     *              the export server. By default, the exporter is hosted on a jQWidgets server.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function exportdata() {

        return $this->method('exportdata');

    }

    /**
     * @detail      Saves the Grid's current state. the savestate method saves the following information: 'sort column,
     *              sort order, page number, page size, applied filters and filter row values, column widths and
     *              visibility, cells and rows selection and groups.
     *
     *              The method saves the Grid's state, but also returns a JSON object with the state. In case of browsers
     *              that do not support localStorage, you can pass the state object to the 'loadState' method.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function savestate() {

        return $this->method('savestate');

    }

    /**
     * @detail      Loads the Grid's state. the loadstate method loads the following information: 'sort column, sort
     *              order, page number, page size, applied filters and filter row values, column widths and visibility,
     *              cells and rows selection and groups.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function loadstate($state = null) {

        return $this->method('loadstate', $state);

    }

    /**
     * @detail      Gets the Grid's state. the getstate method gets the following information: 'sort column, sort order,
     * page number, page size, applied filters and filter row values, column widths and visibility, cells and rows
     * selection and groups.
     *
     *              The returned value is a JSON object with the following fields:
     *              * width - the Grid's width.
     *              * height - the Grid's height.
     *              * pagenum - the Grid's page number.
     *              * pagesize - the Grid's page size.
     *              * pagesizeoptions - the Grid's page size options - an array of the available page sizes.
     *              * sortcolumn - the Grid's sort column. The value is the column's datafield or null, if sorting is not
     *              applied.
     *              * sortdirection - JSON Object with two boolean fields: 'ascending' and 'descending'.
     *              * filters - the applied filters. See the 'getfilterinformation' method.
     *              * groups - the Grid's groups array which contains the grouped columns data fields.
     *              * columns - an array of Columns. Each column in the array has the following fields:
     *              * * width - column's width.
     *              * * hidden - column's visible state.
     *              * * pinned - column's pinned state.
     *              * * groupable - column's groupable state.
     *              * * resizable - column's resizable state.
     *              * * draggable - column's draggable state.
     *              * * text - column's text.
     *              * * align - column's align.
     *              * * cellsalign - column's cells align.
     *
     * @since       1.1
     *
     * @param       string $column
     *
     * @return      \\Hazaar\\Widgets\\JavaScript The JavaScript code to execute the method.
     */
    public function getstate() {

        return $this->method('getstate');

    }

}
