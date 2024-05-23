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

    public function __construct(string $name, Application $application)
    {
        parent::__construct($name, $application);
        $this->view = new ViewRenderer();
    }

    public function __initialize(Request $request): ?Response
    {
        if ($request instanceof HTTP
            && false === $request->isXmlHttpRequest()
            && null !== $this->application
            && $this->application->getResponseType() === 'html'
            && $this->application->config['app']->has('layout')) {
            $this->view->layout($this->application->config['app']['layout']);
        }

        return parent::__initialize($request);
    }

    public function __registerMethod(string $name, callable $callback): bool
    {
        if (array_key_exists($name, $this->methods)) {
            throw new Exception\MethodExists($name);
        }
        $this->methods[$name] = $callback;

        return true;
    }

    public function __run(): Response
    {
        $response = parent::__runAction();
        if (!$response instanceof Response) {
            if (null === $response) {
                $response = new Response\HTML();
                $this->view->__exec($response);
            }
        }
        $this->cacheResponse($response);
        $response->setController($this);

        return $response;
    }

    /**
     * Forwards an action from the requested controller to another controller.
     *
     * This is some added magic to assist with poorly designed MVC applications where too much "common" code
     * has been implemented in a controller action.  This allows the action request to be forwarded and the
     * response returned.  The target action is executed as though it was called on the requested controller.
     * This means that view data can be modified after the action has executed to modify the response.
     *
     * Note: If you don't need to modify any response data, then it would be more efficient to use an alias.
     *
     * @param string       $controller the name of the controller to forward to
     * @param string       $action     Optional. The name of the action to call on the target controller.  If ommitted, the
     *                                 name of the requested action will be used.
     * @param array<mixed> $actionArgs Optional. An array of arguments to forward to the action.  If ommitted, the arguments
     *                                 sent to the calling action will be forwarded.
     * @param Controller   $target     The target controller.  Allows direct access to the forward controller after it has
     *                                 been loaded.
     *
     * @return Response retuns the same return value returned by the forward controller action
     */
    public function forwardAction(string $controller, ?string $action = null, ?array $actionArgs = null, ?Controller &$target = null): Response
    {
        $response = parent::forwardAction($controller, $action, $actionArgs, $target);
        $this->methods = $target->methods;
        $this->view = $target->view;

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
}
