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

/**
 * Layout view class.
 *
 * The layout view class is used to create a layout that can contain multiple views.  The layout is rendered
 * as a single view and the content of the layout is the content of all the views added to it.
 */
class Layout extends View
{
    private string $content = '';
    private ?string $renderedViews = null;

    /**
     * @var array<View>
     */
    private array $views = [];

    /**
     * Creates a new layout view.
     *
     * @param null|string  $view the view file to use for the layout
     * @param array<mixed> $data the data to render the layout with
     */
    public function __construct(?string $view = null, array $data = [])
    {
        $view ??= 'app';
        parent::__construct($view, $data);
    }

    /**
     * Sets the content of the layout.
     *
     * @param string $content the content to set
     */
    public function __setContent(string $content): void
    {
        $this->content = $content;
    }

    /**
     * Prepares the layout for rendering.
     *
     * This method prepares the layout by rendering all the views added to it. It adds the layout's helpers to each view,
     * extends the view's data with the layout's data, and renders each view. If the `$mergeData` parameter is set to true,
     * it also extends the layout's data with each view's data.
     *
     * @return bool returns true if the layout was prepared successfully, false if it was already prepared
     */
    public function prepare(): bool
    {
        if (null !== $this->renderedViews) {
            return false;
        }
        $this->renderedViews = '';
        foreach ($this->views as $view) {
            $view->addHelper($this->helpers);
            $view->setFunctionHandlers($this->functionHandlers);
            $view->setFunctions($this->functions);
            $this->renderedViews .= $view->render($this->data);
        }

        return true;
    }

    /**
     * Renders the layout and returns it as a string.
     *
     * If the 'prepare' flag is set to true in the view configuration, the layout will be prepared before rendering.
     *
     * @param array<mixed> $data the data to render the layout with
     *
     * @return string the rendered layout as a string
     */
    public function render(?array $data = []): string
    {
        if ($this->application->config['view']['prepare'] ?? false) {
            $this->prepare();
        }

        return parent::render($data);
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
        $output = $this->content;
        if (null === $this->renderedViews) {
            $this->prepare();
        }
        $output .= $this->renderedViews;

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
    public function add(string|View $view, ?string $key = null): View
    {
        if (!$view instanceof View) {
            $view = new View($view);
        }
        if ($key) {
            $this->views[$key] = $view;
        } else {
            $this->views[] = $view;
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
        if (array_key_exists($key, $this->views)) {
            unset($this->views[$key]);
        }
    }
}
