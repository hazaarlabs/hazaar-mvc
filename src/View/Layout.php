<?php

declare(strict_types=1);

/**
 * @file        Hazaar/View/Layout.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\View;

use Hazaar\View;

class Layout extends View
{
    private string $_content = '';

    private ?string $_rendered_views = null;

    /**
     * @var array<View>
     */
    private array $_views = [];

    public function __construct(string $view = null)
    {
        if (!$view) {
            $view = 'application';
        }
        parent::__construct($view, ['hazaar']);
    }

    /**
     * Sets the content of the layout.
     *
     * @param string $content the content to set
     */
    public function setContent(string $content): void
    {
        $this->_content = $content;
    }

    /**
     * Prepares the layout for rendering.
     *
     * This method prepares the layout by rendering all the views added to it. It adds the layout's helpers to each view,
     * extends the view's data with the layout's data, and renders each view. If the `$merge_data` parameter is set to true,
     * it also extends the layout's data with each view's data.
     *
     * @param bool $merge_data Whether to merge the layout's data with each view's data. Default is true.
     *
     * @return bool returns true if the layout was prepared successfully, false if it was already prepared
     */
    public function prepare(bool $merge_data = true): bool
    {
        if (null !== $this->_rendered_views) {
            return false;
        }
        $this->_rendered_views = '';
        foreach ($this->_views as $view) {
            $view->addHelper($this->_helpers);
            $view->extend($this->_data);
            $this->_rendered_views .= $view->render();
            if ($merge_data) {
                $this->extend($view->getData());
            }
        }

        return true;
    }

    /**
     * Renders the layout and returns it as a string.
     *
     * If the 'prepare' flag is set to true in the view configuration, the layout will be prepared before rendering.
     *
     * @return string the rendered layout as a string
     */
    public function render(): string
    {
        if (true === $this->application->config['view']['prepare']) {
            $this->prepare();
        }

        return parent::render();
    }

    /**
     * Returns the layout content.
     *
     * This method prepares the views and merges the data back in, if necessary.
     * The layout content is then returned as a string.
     *
     * @return string the layout content
     */
    public function layout(): string
    {
        $output = $this->_content;
        if (null === $this->_rendered_views) {
            $this->prepare(false);
        } // Prepare the views now, but don't bother merging data back in
        $output .= $this->_rendered_views;

        return $output;
    }

    /**
     * Add a view to the layout.
     *
     * This method will add a view based on the supplied argument.  If the argument is a string a new Hazaar\View object
     * is created using the view file named in the argument.  Alterntively, the argument can be a Hazaar\View object
     * which will simply then be added to the layout.
     *
     * @param string|View $view A string naming the view to load, or an existing Hazaar_View object
     * @param string      $key  Optional key to store the view as.  Allows direct referencing later.
     */
    public function add(string|View $view, string $key = null): View
    {
        if (!$view instanceof View) {
            $view = new View($view);
        }
        if ($key) {
            $this->_views[$key] = $view;
        } else {
            $this->_views[] = $view;
        }

        return $view;
    }

    /**
     * Removes a view from the layout.
     *
     * @param string $key the key of the view to remove
     */
    public function remove(string $key): void
    {
        if (array_key_exists($key, $this->_views)) {
            unset($this->_views[$key]);
        }
    }
}
