<?php

use \Respect\Validation\Validator as v;

$app->hook('slim.before', function() use ($app, $registry) {
    $req = $app->request();
    try {
        v::string()->notEmpty()->assert($req->get('token'));
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    }
});

$app->hook('slim.before', function() use ($app, $registry) {
    $req = $app->request();
    $requestPath = $req->getPathInfo();
    if (strpos($requestPath, '/v1/api/validate_token') === 0) {
        return;
    }

    $auth = new \RANDF\PEI\Authentication();
    $token = $req->get('token');
    try {
        v::string()->notEmpty()->setName('token must be a GET variable and must not be blank')->assert($token);
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    }
    
    $failures = $auth::getTokenFailures($token);
    if ($failures > 10) {
        $app->halt(429); // Too many requests
    }
    
    $UserID = $auth::getUserForToken($token);

    if ($UserID === false) {
        $auth::recordTokenFailure($token);
        $app->response()->header('WWW-Authenticate', 'Basic realm="PEI API"');
        $app->halt(401);
    }

    $registry->UserID = $UserID;
    $registry->UUID = new \RANDF\PEI\Data\UUID($registry->UserID);
    $registry->PulseVersion = $auth::getAppVersion($registry->UserID);
});

if (isset($appConfig['logging']['enabled']) && $appConfig['logging']['enabled'] && $appConfig['logging']['level'] == 'DEBUG') {
    $app->hook('slim.after', function() use ($app, $registry) {
        $log = $app->getLog();

        $body = json_decode($app->response()->body());
        if ($body === null) {
            $body = $app->response()->body();
        }

        $logData = array(
            'status' => $app->response()->status(),
            'user_id' => isset($registry->UserID) ? $registry->UserID : 0,
            'method' => $app->request()->getMethod(),
            'path' => $app->request()->getPath(),
            'post' => $app->request()->post(),
            'get' => $app->request()->get(),
            'response' => $body,
            );

        $log->debug($logData);
    });
}
