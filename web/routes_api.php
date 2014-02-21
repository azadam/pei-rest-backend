<?php

use \RANDF\Core as Core;
use \Respect\Validation\Validator as v;

/**
 * Authentication and token validation routine
 */
$app->post('/v1/api/validate_token/', function() use ($app, $registry) {
    // Authentication step; this is the only one that won't trigger the authentication hook
    $req = $app->request();
    $auth = new \RANDF\PEI\Authentication();
    $username = $req->post('username');
    $password = $req->post('password');
    $deviceIdentifier = $req->post('device_id');
    $version = $req->post('version');
    $declare_master = $req->post('declare_master');
    if ($declare_master === 'yes') {
        $declare_master = true;
    } else {
        $declare_master = false;
    }
    if ($version === '0.01' || $version === '0.02' || $version == '') {
        $declare_master = true;
    }
    $token = $req->get('token');

    $failures = $auth::getTokenFailures($token);
    if ($failures > 10) {
        $app->halt(429); // Too many requests
    }
    
    try {
        v::oneOf(v::int(), v::string()->notEmpty())->assert($username);
        v::string()->notEmpty()->assert($password);
        v::string()->notEmpty()->assert($deviceIdentifier);
        v::string()->notEmpty()->assert($token);
        
        $UserID = $auth::getUserForToken($token, $deviceIdentifier);
        if ($UserID > 0) {
            // This token is active already, all subsequent code is irrelevant.  Just drop out here for efficiency.
            echo json_encode(array(
                'must_declare_master' => false,
                'master_status_granted' => false,
                'sponsor_email_address' => null,
                'has_accepted_tos' => true, // $auth::hasAcceptedTOS($UserID),
                ));

            return;
        }
        
        $isAuthenticated = false;
        if ($token != '' && $username != '' && $password != '') {
            // Failures here indicate a bad username/password/token combo
            $isAuthenticated = $auth::validateToken($username, $password, $token);
        }
        
        if ($isAuthenticated) {
            switch ($version) {
                // Block these versions of the app; send back a 498 with the corresponding link to the updated client in the iTunes store
                case '0.01':
                case '0.02':
                case '0.03':
                case '0.04':
                case '0.05':
                case '0.06':
                case '1.12':
                case '1.5':
                case '1.50':
                case '1.51':
                $app->halt(498, 'https://itunes.apple.com/us/app/r+f-pulse/id516785288?mt=8'); // Outdated Client
                    break;
            }
            $AccountID = $auth::getAccountIdFromToken($token);
            $UserID = $auth::getUserIdFromAccountId($AccountID);
            $resp = array(
                'must_declare_master' => false,
                'master_status_granted' => false,
                'sponsor_email_address' => null,
                'has_accepted_tos' => true, // $auth::hasAcceptedTOS($UserID),
                );
            
            /*
             * Is this device_id already the master device, or are they asking to become the master device?  Either way is OK to us,
             * storeUserToken will stash the new token (if they're a master) and also make it the master device so either condition is
             * covered.
             */
            $isMaster = $auth::deviceIsMaster($UserID, $deviceIdentifier);
            if ($isMaster || $declare_master) {
                $genealogy = $auth::getGenealogyForUser($username, $password, $auth::getAccountIdFromToken($token));
                
                $sponsorProfileInfo = $auth::getProfileInfoFromToken($token, $genealogy['SponsorID']);
                if (isset($sponsorProfileInfo) && isset($sponsorProfileInfo['Email'])) {
                    $resp['sponsor_email_address'] = $sponsorProfileInfo['Email'];
                }
                
                $resp['master_status_granted'] = $auth::storeUserToken($username, $token, $deviceIdentifier, $genealogy, $version);
                
                /*
                // Allow these versions of the app in, but add a notification informing them they should upgrade ASAP
                if ($version === '0.01' || $version === '0.02' || $version == '') {
                    $not = new \RANDF\PEI\Data\Notifications();
                    $not->createNotificationIfDoesNotExist($UserID, "A new version of PEI is available. Please upgrade as soon as possible...");
                }
                */
            } else {
                // They are neither master nor declaring they're the new master; this signals they need to accept the master status to continue.
                $resp['must_declare_master'] = true;
            }
            
            echo json_encode($resp);
        } else {
            $app->response()->header('WWW-Authenticate', 'Basic realm="PEI API"');
            $app->halt(401);
        }
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage() . "\n\n" . $e->getTraceAsString());
    }
});

/**
 * Batch operations
 */
$app->post('/v1/api/batch/', function() use ($app, $registry) {
    $requests = $app->request()->post('requests');
    if (is_array($requests)) {
        $app->halt(400, 'requests should be a json string');
    }
    $requests = json_decode($requests, true);

    if ($requests == null) {
        $app->halt(400, 'Invalid requests received');
    }
    
    try {
        $response = array();
        $stringTest = v::string()->length(1);
        foreach ($requests as $request) {
            if (!isset($request['method']) || $request['method'] == '') {
                throw new \InvalidArgumentException('requests.request.method is not optional');
            }
            if (!isset($request['url']) || $request['url'] == '') {
                throw new \InvalidArgumentException('requests.request.url is not optional');
            }
            if (!isset($request['body'])) {
                throw new \InvalidArgumentException('requests.request.body is not optional');
            }
            
            $result = $app->runNewRequest($request['method'], $request['url'], $request['body']);

            $headers = array();
            foreach ($result[1] as $key => $value) {
                $headers[] = array('name' => $key, 'value' => $value);
            }

            $response[] = array(
                'request_id' => isset($request['request_id']) ? $request['request_id'] : null,
                'code' => $result[0],
                'headers' => $headers,
                'body' => json_decode($result[2]),
                );
        }
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    echo Core::json_encode($response);
});

/**
 * TOS Stuff
 */
$app->post('/v1/api/accept_tos/', function() use ($app, $registry) {
    $auth = new \RANDF\PEI\Authentication();
    
    try {
        $auth::storeTOSAcceptance($registry->UserID);
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    }
    
    $app->halt(204);
});
