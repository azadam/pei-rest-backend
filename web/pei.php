<?php

/*
ini_set('display_errors','On');
error_reporting(E_ALL);
//*/

/**
 * Step 1: Instantiate Slim and register a PSR-0 compatble autoloader routine
 */
require_once '../lib/Slim/Slim.php';

\Slim\Slim::registerAutoloader();

/**
 * Step 2: Instantiate a Slim application
 *
 * This example instantiates a Slim application using
 * its default settings. However, you will usually configure
 * your Slim application now by passing an associative array
 * of setting names and values into the application constructor.
 */
$slimConfig = array();
$slimConfig['debug'] = false;

\RANDF\Core::setConfigFile('dev.ini');
if (isset($_SERVER['HTTP_HOST'])) {
    switch ($_SERVER['HTTP_HOST']) {
        case 'api.randfapi.com':
            \RANDF\Core::setConfigFile('pei.ini');
            break;
        case 'dev.randfapi.com':
            $slimConfig['debug'] = true;
            \RANDF\Core::setConfigFile('dev.ini');
            break;
        case 'qa.randfapi.com':
            $slimConfig['debug'] = true;
            \RANDF\Core::setConfigFile('qa.ini');
            break;
        case 'staging.randfapi.com':
            \RANDF\Core::setConfigFile('staging.ini');
            break;
    }
}

$appConfig = \RANDF\Core::getConfig();
if ($appConfig['logging']['enabled']) {
    $slimConfig['log.writer'] = new \RANDF\PEI\LogWriter();
    $slimConfig['log.enabled'] = true;
    $slimConfig['log.level'] = constant('\Slim\Log::' . $appConfig['logging']['level']);
}
$app = new \Slim\RFSlim($slimConfig);

// Default response type is JSON
$res = $app->response();
$res['Content-Type'] = 'application/json';

// Registry of information that may be handed between request handlers
$registry = new stdClass();

/**
 * Step 3: Register authentication hook
 *
 * Intercept all requests except the token validation call and make sure
 * a valid token has been passed before the request is serviced
 */
require_once('hooks.php');

/**
 * Step 4: Define the Slim application routes
 *
 * Here we define several Slim application routes that respond
 * to appropriate HTTP request methods. In this example, the second
 * argument for `Slim::get`, `Slim::post`, `Slim::put`, and `Slim::delete`
 * is an anonymous function.
 */
require_once('routes.php');

/**
 * Step 5: Run the Slim application
 *
 * This method should be called last. This executes the Slim application
 * and returns the HTTP response to the HTTP client.
 */
if (!isset($skipSlimAutorun) || !$skipSlimAutorun) {
    $app->run();
}
