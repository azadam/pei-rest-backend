<?php

namespace Slim;

class RFSlim extends Slim
{

    public function __construct($userSettings = array())
    {
        parent::__construct($userSettings);
    }
    
    public function setEnvironment($environment)
    {
        $this->environment = $environment;
        $this->request = new \Slim\Http\Request($this->environment);
        $this->response = new \Slim\Http\Response();
        $this->router->setResourceUri($this->request->getResourceUri());
        $this->router->getMatchedRoutes(true);
    }

    public function runNewRequest($method, $routePattern, $requestBody=null)
    {
        // Grab foundational elements
        $env = $this->getInstance()->environment();

        // Save current state
        $origResponse = $this->response;
        if (isset($env['REQUEST_METHOD'])) {
            $origMethod = $env['REQUEST_METHOD'];
        }

        // Update Slim instance with new route information
        $this->response = new \Slim\Http\Response();
        $this->router->setResourceUri($routePattern);
        $this->router->getMatchedRoutes(true);          // Forces router to loop again to find a match
        $env['REQUEST_METHOD'] = $method;

        if ($method == 'POST') {
            // Override post values
            if ($requestBody === null) {
                $env['slim.request.form_hash'] = array();
            } else {
                parse_str($requestBody, $form_hash);
                $env['slim.request.form_hash'] = $form_hash;
            }
        }

        // Slim does its routing magic using the new route
        $this->call();
        $newRequestResponse = $this->response->finalize(); // array of status, header, and body

        // We restore previous state
        $this->response = $origResponse;
        if (isset($origMethod)) {
            $env['REQUEST_METHOD'] = $origMethod;
        }
        unset($env['slim.request.form_hash']);

        return $newRequestResponse;
    }

}
