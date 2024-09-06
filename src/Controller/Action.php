<?php

declare(strict_types=1);

/**
 * @file        Controller/Action.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\Controller;

use Hazaar\Application;
use Hazaar\Application\Request;
use Hazaar\Application\Request\HTTP;
use Hazaar\Controller;
use Hazaar\Controller\Action\ViewRenderer;
use Hazaar\View;

/**
 * Abstract controller action class.
 *
 * This controller handles actions and responses using views
 */
abstract class Action extends Basic
{
    public ViewRenderer $view;

    /**
     * @var array<mixed>
     */
    protected array $methods = [];

    public function __construct(Application $application, string $name)
    {
        parent::__construct($application, $name);
        $this->view = new ViewRenderer();
    }

    public function initialize(Request $request): ?Response
    {
        if ($request instanceof HTTP
            && false === $request->isXmlHttpRequest()
            && null !== $this->application
            && 'html' === $this->application->getResponseType()
            && $this->application->config['app']->has('layout')) {
            $this->view->layout($this->application->config['app']['layout']);
        }

        return parent::initialize($request);
    }

    public function registerMethod(string $name, callable $callback): bool
    {
        if (array_key_exists($name, $this->methods)) {
            throw new Exception\MethodExists($name);
        }
        $this->methods[$name] = $callback;

        return true;
    }

    public function runAction(string $actionName, array $actionArgs = [], bool $namedActionArgs = false): Response
    {
        try {
            $response = parent::runAction($actionName, $actionArgs, $namedActionArgs);
        } catch (Exception\ResponseInvalid $e) {
            $response = null;
        }
        if (null === $response) {
            $response = new Response\HTML();
            $this->view->exec($response);
        }

        return $response;
    }

    /**
     * Loads a view.
     *
     * @param string $view the name of the view to load
     */
    protected function view(string $view): void
    {
        $this->view->view($view);
    }

    protected function layout(string $view): void
    {
        $this->view->layout($view);
    }
}
