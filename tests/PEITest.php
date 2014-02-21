<?php

date_default_timezone_set('UTC');
error_reporting(E_ALL ^ E_NOTICE);

require_once __DIR__ . '/../lib/Slim/Slim.php';
\Slim\Slim::registerAutoloader();

class PEITest extends PHPUnit_Framework_TestCase
{
    public function testResetDatabase()
    {
        \RANDF\Core::setConfigFile('phpunit.ini');
        $appConfig = \RANDF\Core::getConfig();
        $dbConfig = $appConfig['pei'];
        
        $dbh = \RANDF\Database::getInstance('pei');
        
        $tables = $dbh->query("SHOW TABLES")->fetchAll(PDO::FETCH_NUM);
        $dbh->exec("SET foreign_key_checks = 0");
        foreach ($tables as $table) {
            $dbh->exec("DROP TABLE `" . $table[0] . "`");
        }
        $dbh->exec("SET foreign_key_checks = 1");
        
        $sqlDumpFile = realpath(__DIR__ . '/../sql/PEI.sql');
        $cmd = '/usr/bin/mysql -h ' . escapeshellarg($dbConfig['host']) . ' -u ' . escapeshellarg($dbConfig['username']) . ' -p' . escapeshellarg($dbConfig['password']) . ' ' . $dbConfig['dbname'] . ' < ' . escapeshellarg($sqlDumpFile);
        
        exec($cmd, $cmdOutput, $cmdReturn);
        if ($cmdReturn !== 0) {
            throw new Exception("Something horrible happened while setting up the database: " . $cmd);
        }
        
        $app = new \Slim\RFSlim();
        $this->assertInstanceOf('\Slim\RFSlim', $app, 'Slim app was not created');
        
        $registry = new stdClass();
        require('web/hooks.php');
        require('web/routes.php');
        
        return array(
            'app' => $app,
            );
    }
    
    private static $authToken;
    /**
     * @depends testResetDatabase
     */
    public function testAuthentication($stack)
    {
        $app = $stack['app'];
        
        $queryString = http_build_query(array(
            'token' => '2qOgmhwC09T81eZdVFdmYFUmJ01c3ukVzb/+chT1BeMJsG5LGzBHuHUzXFHg6iSM98hxU/WbdbgxedW6tMzinA==',
            ));
        $postData = http_build_query(array(
            'device_id' => 'PHPUNIT-2',
            'username' => 'doe',
            'password' => 'doe',
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'POST',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/api/validate_token/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Authentication request failed");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to authentication request was not JSON data');;
        
        $this->assertEquals(1, $responseArray['resync_needed'], 'API did not indicate that a resync was required despite the device_id changing');
        
        self::$authToken = '2qOgmhwC09T81eZdVFdmYFUmJ01c3ukVzb/+chT1BeMJsG5LGzBHuHUzXFHg6iSM98hxU/WbdbgxedW6tMzinA==';
        
        $queryString = http_build_query(array(
            'token' => '7boPqG26XzJUFS8IzNuhhi5urugcIEBLAEv3dB2BsPBu7mS0nF7NNt+4o3xbgvnTtnKHRwH/u5gyXcC5r6gCvw==',
            ));
        $postData = http_build_query(array(
            'device_id' => 'PHPUNIT-1',
            'username' => 'doe',
            'password' => 'doe',
            ));
        
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'POST',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/api/validate_token/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Authentication request failed");
        
        
        $queryString = http_build_query(array(
            'token' => '2qOgmhwC09T81eZdVFdmYFUmJ01c3ukVzb/+chT1BeMJsG5LGzBHuHUzXFHg6iSM98hxU/WbdbgxedW6tMzinA==',
            ));
        $postData = http_build_query(array(
            'device_id' => 'PHPUNIT-2',
            'username' => 'doe',
            'password' => 'doe',
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'POST',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/api/validate_token/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Authentication request failed");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to authentication request was not JSON data');;
        
        $this->assertEquals(1, $responseArray['resync_needed'], 'API did not indicate that a resync was required despite the device_id changing');
        
        return array('app' => $app);
    }
    
        /*
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '',
            'QUERY_STRING' => '',
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => '',
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        */

    /**
     * @depends testAuthentication
     */
    public function testUnauthorizedRequest($stack)
    {
        $app = $stack['app'];
        
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/api/batch/',
            'QUERY_STRING' => '',
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => '',
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        $this->assertEquals(400, $status, 'Unauthorized request not rejected properly');
        
        return $stack;
    }
    
    /**
     * @depends testAuthentication
     */
    public function testLocations($stack)
    {
        $app = $stack['app'];
        
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/locations/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Location request failed");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to authentication request was not JSON data');;
        
        $this->assertCount(0, $responseArray, 'Response included locations objects but none should exist yet');
        
        
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'POST',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/locations/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(400, $status, "Location request returned an unexpected response to an invalid request");
        
        
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            'name' => 'Test Location Name',
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'POST',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/locations/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Location request returned an unexpected response to a valid creation request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to location create request was not JSON data');
        
        $this->assertInternalType('int', $responseArray['location_id'], 'Response to location create request did not contain a valid location_id');
        
        $stack['location_id'] = $responseArray['location_id'];
        
        
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/locations/' . $stack['location_id'],
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Location request returned an unexpected response to a valid creation request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to location create request was not JSON data');
        
        
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/locations/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Location request returned an unexpected response to a valid creation request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to location create request was not JSON data');
        
        $this->assertCount(1, $responseArray, 'Response included a number of location objects that makes no sense');
        
        
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'DELETE',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/locations/' . $stack['location_id'],
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(204, $status, "Location request returned an unexpected response to a valid creation request");
        
        
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/locations/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Location request returned an unexpected response to a valid creation request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to location create request was not JSON data');
        
        $this->assertCount(0, $responseArray, 'Response included a number of location objects that makes no sense');
        
        
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            'name' => 'Test Location Name',
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'POST',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/locations/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Location request returned an unexpected response to a valid creation request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to location create request was not JSON data');
        
        $this->assertInternalType('int', $responseArray['location_id'], 'Response to location create request did not contain a valid location_id');
        
        $stack['location_id'] = $responseArray['location_id'];
        
        
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            'name' => 'Test Location Name What Has Been Updated',
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'POST',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/locations/' . $stack['location_id'],
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Location request returned an unexpected response to a valid update request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to location update request was not JSON data');
        
        $this->assertInternalType('int', $responseArray['location_id'], 'Response to location create request did not contain a valid location_id');
        
        
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/locations/' . $stack['location_id'],
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Location request returned an unexpected response to a valid creation request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to location create request was not JSON data');
        
        $this->assertEquals('Test Location Name What Has Been Updated', $responseArray['name'], 'Returned location does not reflect latest update request');
        
        
        return $stack;
    }
    
    /**
     * @depends testLocations
     */
    public function testContacts($stack)
    {
        $app = $stack['app'];
        
        
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/contacts/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Contact request returned an unexpected response to a valid getAll request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to location create request was not JSON data');
        
        $this->assertCount(0, $responseArray, 'Response included a number of location objects that makes no sense');
        
        
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            'first_name' => 'John',
            'last_name' => 'Doe',
            'location_id' => $stack['location_id'],
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'POST',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/contacts/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Contact request returned an unexpected response to a valid creation request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to location create request was not JSON data');
        
        
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/contacts/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Contact request returned an unexpected response to a valid getAll request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to location create request was not JSON data');
        
        $this->assertCount(1, $responseArray, 'Response included a number of location objects that makes no sense');
        
        $stack['contact_id'] = $responseArray[0]['contact_id'];
        
        
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'DELETE',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/contacts/' . $stack['contact_id'],
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(204, $status, "Contact request returned an unexpected response to a valid delete request");
        

        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/contacts/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Contact request returned an unexpected response to a valid getAll request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to location getAll request was not JSON data');
        
        $this->assertCount(0, $responseArray, 'Response included a number of location objects that makes no sense');
        
        
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            'first_name' => 'John',
            'last_name' => 'Doee',
            'location_id' => $stack['location_id'],
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'POST',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/contacts/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Contact request returned an unexpected response to a valid creation request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to location create request was not JSON data');
        
        $stack['contact_id'] = $responseArray['contact_id'];
        
        
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            'first_name' => 'John',
            'last_name' => 'Doe',
            'location_id' => $stack['location_id'],
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'POST',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/contacts/' . $stack['contact_id'],
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Contact update returned an unexpected response to a valid creation request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to location create request was not JSON data');
        
        
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/contacts/' . $stack['contact_id'],
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Contact request returned an unexpected response to a valid getAll request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to location getAll request was not JSON data');
        
        $this->assertEquals('John', $responseArray['first_name'], 'Returned contact does not match expectations');
        $this->assertEquals('Doe', $responseArray['last_name'], 'Returned contact does not match expectations');
        
        
        return $stack;
    }
    
    /**
     * @depends testContacts
     */
    public function testEvents($stack)
    {
        $app = $stack['app'];
        
        
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/events/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Event request returned an unexpected response to a valid getAll request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to event getAll request was not JSON data');
        
        $this->assertCount(0, $responseArray, 'Response included a number of event objects that makes no sense');
        
        
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            'name' => 'My Big Adventure',
            'location_id' => $stack['location_id'],
            'event_date_time' => '2011-05-05 10:00:00',
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'POST',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/events/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Event request returned an unexpected response to a valid create request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to event create request was not JSON data');
        
        $stack['event_id'] = $responseArray['event_id'];
        
        
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/events/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Event request returned an unexpected response to a valid getAll request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to event getAll request was not JSON data');
        
        $this->assertCount(1, $responseArray, 'Response included a number of event objects that makes no sense');
        
        
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'DELETE',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/events/' . $stack['event_id'],
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(204, $status, "Event request returned an unexpected response to a delete request");
        
        
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/events/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Event request returned an unexpected response to a valid getAll request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to event getAll request was not JSON data');
        
        $this->assertCount(0, $responseArray, 'Response included a number of event objects that makes no sense');
        
        
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            'name' => 'The Maain Event',
            'location_id' => $stack['location_id'],
            'event_date_time' => gmdate('Y-m-d H:i:s'),
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'POST',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/events/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Event request returned an unexpected response to a valid create request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to event create request was not JSON data');
        
        $stack['event_id'] = $responseArray['event_id'];
        
        
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            'name' => 'The Main Event',
            'location_id' => $stack['location_id'],
            'event_date_time' => gmdate('Y-m-d H:i:s'),
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'POST',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/events/' . $stack['event_id'],
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Event request returned an unexpected response to a valid update request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to event update request was not JSON data');
        
        
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/events/' . $stack['event_id'],
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Event request returned an unexpected response to a valid get request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to event getAll request was not JSON data');
        
        $this->assertEquals('The Main Event', $responseArray['name'], 'Event returned does not match expected contents');
        
        
        return $stack;
    }
    
    /**
     * @depends testEvents
     */
    public function testInvitees($stack)
    {
        $app = $stack['app'];
        
        
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/events/' . $stack['event_id'] . '/invitees/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Invitee request returned an unexpected response to a valid getAll request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to invitee getAll request was not JSON data');
        
        $this->assertCount(0, $responseArray, 'Invitee included a number of invitee objects that makes no sense');
        
        
        // CREATE
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            'contact_id' => $stack['contact_id'],
            'status' => 'Invited',
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'POST',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/events/' . $stack['event_id'] . '/invitees/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Invitee request returned an unexpected response to a valid getAll request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to invitee getAll request was not JSON data');
        
        $stack['invitee_id'] = $responseArray['invitee_id'];
        
        
        // GETALL
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/events/' . $stack['event_id'] . '/invitees/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Invitee request returned an unexpected response to a valid getAll request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to invitee getAll request was not JSON data');
        
        $this->assertCount(1, $responseArray, 'Invitee included a number of invitee objects that makes no sense');
        
        
        // DELETE
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'DELETE',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/events/' . $stack['event_id'] . '/invitees/' . $stack['invitee_id'],
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(204, $status, "Invitee request returned an unexpected response to a valid delete request");
        
        
        // GETALL
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/events/' . $stack['event_id'] . '/invitees/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Invitee request returned an unexpected response to a valid getAll request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to invitee getAll request was not JSON data');
        
        $this->assertCount(0, $responseArray, 'Invitee included a number of invitee objects that makes no sense');
        
        
        // CREATE
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            'contact_id' => $stack['contact_id'],
            'status' => 'Invited',
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'POST',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/events/' . $stack['event_id'] . '/invitees/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Invitee request returned an unexpected response to a valid getAll request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to invitee getAll request was not JSON data');
        
        $stack['invitee_id'] = $responseArray['invitee_id'];
        
        
        // UPDATE
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            'status' => 'Accepted',
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'POST',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/events/' . $stack['event_id'] . '/invitees/' . $stack['invitee_id'],
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Invitee request returned an unexpected response to a valid update request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to invitee update request was not JSON data');
        
        
        // GETONE
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/events/' . $stack['event_id'] . '/invitees/' . $stack['invitee_id'],
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Invitee request returned an unexpected response to a valid get request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to invitee get request was not JSON data');
        
        $this->assertEquals('Accepted', $responseArray['status'], 'Response to getOne request did not match expected invitee status');
        
        
        return $stack;
    }
    
    /**
     * @depends testEvents
     */
    public function testReminders($stack)
    {
        $app = $stack['app'];
        
        
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/reminders/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Reminder request returned an unexpected response to a valid getAll request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to reminder getAll request was not JSON data');
        
        $this->assertCount(0, $responseArray, 'Reminder request included a number of reminder objects that makes no sense');
        
        
        // CREATE
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            'event_id' => $stack['event_id'],
            'lead_time_minutes' => 120,
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'POST',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/reminders/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Reminder request returned an unexpected response to a valid create request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to reminder create request was not JSON data');
        
        $stack['reminder_id'] = $responseArray['reminder_id'];
        
        
        // GETALL
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/reminders/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Reminder request returned an unexpected response to a valid getAll request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to reminder getAll request was not JSON data');
        
        $this->assertCount(1, $responseArray, 'Reminder included a number of invitee objects that makes no sense');
        
        
        // DELETE
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'DELETE',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/reminders/' . $stack['reminder_id'],
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(204, $status, "Reminder request returned an unexpected response to a valid delete request");
        
        
        // GETALL
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/reminders/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Reminder request returned an unexpected response to a valid getAll request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to reminder getAll request was not JSON data');
        
        $this->assertCount(0, $responseArray, 'Response included a number of reminder objects that makes no sense');
        
        
        // CREATE
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            'event_id' => $stack['event_id'],
            'lead_time_minutes' => 120,
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'POST',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/reminders/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Reminder request returned an unexpected response to a valid getAll request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to reminder getAll request was not JSON data');
        
        $stack['reminder_id'] = $responseArray['reminder_id'];
        
        
        // UPDATE
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            'lead_time_minutes' => 60,
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'POST',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/reminders/' . $stack['reminder_id'],
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Reminder request returned an unexpected response to a valid update request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to reminder update request was not JSON data');
        
        
        // GETONE
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/reminders/' . $stack['reminder_id'],
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Reminder request returned an unexpected response to a valid get request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to reminder get request was not JSON data');
        
        $this->assertEquals(60, $responseArray['lead_time_minutes'], 'Response to getOne request did not match expected reminder lead time');
        
        
        return $stack;
    }
    
    /**
     * @depends testAuthentication
     */
    public function testTemplates($stack)
    {
        $app = $stack['app'];
        
        
        // GETALL
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/templates/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Template request returned an unexpected response to a valid getAll request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Template request for getAll was not JSON data');
        
        $this->assertCount(0, $responseArray, 'Template request returned an unexpected number of object');
        
        
        // CREATE
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            'name' => 'My Template (Soon To Be Deleted)',
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'POST',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/templates/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Template request returned an unexpected response to a valid create request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Template request for getAll was not JSON data');
        
        $stack['template_id'] = $responseArray['template_id'];
        
        
        // GETALL
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/templates/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Template request returned an unexpected response to a valid getAll request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Template request for getAll was not JSON data');
        
        $this->assertCount(1, $responseArray, 'Template request returned an unexpected number of object');

        
        // DELETE
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'DELETE',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/templates/' . $stack['template_id'],
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(204, $status, "Template request returned an unexpected response to a valid delete request");
        
        
        // GETALL
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/templates/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Template request returned an unexpected response to a valid getAll request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Template request for getAll was not JSON data');
        
        $this->assertCount(0, $responseArray, 'Template request returned an unexpected number of object');
        
        
        // CREATE
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            'name' => 'My Temmplate',
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'POST',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/templates/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Template request returned an unexpected response to a valid create request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Template request for getAll was not JSON data');
        
        $stack['template_id'] = $responseArray['template_id'];
        
        
        // UPDATE
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            'name' => 'My Wonderful Template',
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'POST',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/templates/' . $stack['template_id'],
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Template request returned an unexpected response to a valid update request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Template request for update was not JSON data');
        
        
        
        // GETONE
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/templates/' . $stack['template_id'],
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Template request returned an unexpected response to a valid getAll request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Template request for getAll was not JSON data');
        
        $this->assertEquals('My Wonderful Template', $responseArray['name'], 'Name of returned template does not match expectations');
        
        
        return $stack;
    }
    
    /**
     * @depends testTemplates
     * @depends testEvents
     */
    public function testInteractions($stackTemplates, $stackEvents)
    {
        $stack = array_merge($stackTemplates, $stackEvents);
        $app = $stack['app'];
        
        
        // GETALL
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/interactions/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Interactions request returned an unexpected response to a valid getAll request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Interactions request for getAll was not JSON data');
        
        $this->assertCount(0, $responseArray, 'Interactions request returned an unexpected number of object');
        
        
        // CREATE
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            'contact_id' => $stack['contact_id'],
            'location_id' => $stack['location_id'],
            'template_id' => $stack['template_id'],
            'event_id' => $stack['event_id'],
            'outcome' => 'Success!',
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'POST',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/interactions/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Interactions request returned an unexpected response to a valid getAll request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Interactions request for getAll was not JSON data');
        
        $stack['interaction_id'] = $responseArray['interaction_id'];
        
        
        // GETALL
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/interactions/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Interactions request returned an unexpected response to a valid getAll request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Interactions request for getAll was not JSON data');
        
        $this->assertCount(1, $responseArray, 'Interactions request returned an unexpected number of object');
        
        
        // DELETE
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'DELETE',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/interactions/' . $stack['interaction_id'],
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(204, $status, "Interactions request returned an unexpected response to a valid delete request");
        
        
        // GETALL
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/interactions/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Interactions request returned an unexpected response to a valid getAll request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Interactions request for getAll was not JSON data');
        
        $this->assertCount(0, $responseArray, 'Interactions request returned an unexpected number of object');
        
        
        // CREATE
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            'contact_id' => $stack['contact_id'],
            'location_id' => $stack['location_id'],
            'template_id' => $stack['template_id'],
            'event_id' => $stack['event_id'],
            'outcome' => 'Success!',
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'POST',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/interactions/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Interactions request returned an unexpected response to a valid getAll request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Interactions request for getAll was not JSON data');
        
        $stack['interaction_id'] = $responseArray['interaction_id'];
        
        
        // UPDATE
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            'outcome' => 'Failure!',
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'POST',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/interactions/' . $stack['interaction_id'],
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Interactions request returned an unexpected response to a valid update request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Interactions request for update was not JSON data');
        
        
        // GETONE
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/interactions/' . $stack['interaction_id'],
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Interactions request returned an unexpected response to a valid getOne request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Interactions request for getOne was not JSON data');
        
        $this->assertEquals('Failure!', $responseArray['outcome'], 'Outcome value not expected in interaction getOne response object');
        
        
        return $stack;
    }
    
    /**
     * @depends testAuthentication
     */
    public function testRecordings($stack)
    {
        $app = $stack['app'];
        
        // GETALL
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/recordings/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Recordings request returned an unexpected response to a valid getAll request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Recordings request for getAll was not JSON data');
        
        $this->assertCount(0, $responseArray, 'Recordings request returned an unexpected number of objects');
        
        
        // CREATE
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            'name' => 'My Recording',
            'audio_data' => 'SOME DATA HERE',
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'POST',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/recordings/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Recordings request returned an unexpected response to a valid create request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Recordings request for create was not JSON data');
        
        $stack['recording_id'] = $responseArray['recording_id'];
        
        
        // GETALL
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/recordings/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Recordings request returned an unexpected response to a valid getAll request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Recordings request for getAll was not JSON data');
        
        $this->assertCount(1, $responseArray, 'Recordings request returned an unexpected number of objects');
        
        
        // DELETE
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'DELETE',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/recordings/' . $stack['recording_id'],
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(204, $status, "Recordings request returned an unexpected response to a valid delete request");
        
        
        // GETALL
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/recordings/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Recordings request returned an unexpected response to a valid getAll request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Recordings request for getAll was not JSON data');
        
        $this->assertCount(0, $responseArray, 'Recordings request returned an unexpected number of objects');
        
        
        // CREATE
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            'name' => 'My Recording',
            'audio_data' => 'SOME AUDIO DATA HERE',
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'POST',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/recordings/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Recordings request returned an unexpected response to a valid create request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Recordings request for getAll was not JSON data');
        
        $stack['recording_id'] = $responseArray['recording_id'];
        
        
        // UPDATE
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            'name' => 'My Finalized Recording',
            'notes' => 'Some notes about my final recording',
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'POST',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/recordings/' . $stack['recording_id'],
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Recordings request returned an unexpected response to a valid create request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Recordings request for getAll was not JSON data');
        
        
        // GETONE
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/recordings/' . $stack['recording_id'],
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Recordings request returned an unexpected response to a valid getOne request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Recordings request for getOne was not JSON data');
        
        $this->assertEquals('My Finalized Recording', $responseArray['name'], 'Recording object returned from getOne contains unexpected values');
        $this->assertEquals('Some notes about my final recording', $responseArray['notes'], 'Recording object returned from getOne contains unexpected values');
        
        
        // Check recording data
        $recordingData = file_get_contents($responseArray['audio_url']);
        $this->assertEquals('SOME AUDIO DATA HERE', $recordingData, 'Recording data retreived from S3 does not match expected value');
        
        
        // DELETE
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'DELETE',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/recordings/' . $stack['recording_id'],
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(204, $status, "Recordings request returned an unexpected response to a valid delete request");
        
        
        return $stack;
    }
    
    /**
     * @depends testAuthentication
     */
    public function testNotifications($stack)
    {
        $app = $stack['app'];
        
        // GETALL
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/notifications/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Notifications request returned an unexpected response to a valid getAll request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Notifications request for getAll was not JSON data');
        
        $this->assertCount(0, $responseArray, 'Notifications request returned an unexpected number of objects');
        
        
        // CREATE (fake)
        \RANDF\PEI\Data\Notifications::_createTestNotifications(1);
        
        
        // GETALL
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/notifications/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Notifications request returned an unexpected response to a valid getAll request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Notifications request for getAll was not JSON data');
        
        $this->assertCount(2, $responseArray, 'Notifications request returned an unexpected number of objects');
        
        
        // DELETE BOTH
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'DELETE',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/notifications/' . $responseArray[0]['notification_id'],
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(204, $status, "Notification request returned an unexpected response to a valid delete request");
        
        
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'DELETE',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/notifications/' . $responseArray[1]['notification_id'],
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(204, $status, "Notification request returned an unexpected response to a valid delete request");
        
        
        // GETALL
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/notifications/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Notifications request returned an unexpected response to a valid getAll request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Notifications request for getAll was not JSON data');
        
        $this->assertCount(0, $responseArray, 'Notifications request returned an unexpected number of objects');
        
        
        // CREATE (fake)
        \RANDF\PEI\Data\Notifications::_createTestNotifications(1);
        
        
        // GETALL (updated ids)
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/notifications/',
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Notifications request returned an unexpected response to a valid getAll request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Notifications request for getAll was not JSON data');
        
        $this->assertCount(2, $responseArray, 'Notifications request returned an unexpected number of objects');
        
        
        // UPDATE
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            'is_read' => 1,
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'POST',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/notifications/' . $responseArray[0]['notification_id'],
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Notifications request returned an unexpected response to a valid getAll request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Notifications request for getAll was not JSON data');
        
        
        // GETONE
        $queryString = http_build_query(array(
            'token' => self::$authToken,
            ));
        $postData = http_build_query(array(
            ));
        $environment = \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/v1/notifications/' . $responseArray[0]['notification_id'],
            'QUERY_STRING' => $queryString,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*' . '/' . '*;q=0.8',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
            'slim.url_scheme' => 'http',
            'slim.input' => $postData,
            'slim.errors' => @fopen('php://stderr', 'w'),
            ));
        
        $app->setEnvironment($environment);
        $app->call();
        
        list($status, $header, $body) = $app->response()->finalize();
        
        $this->assertEquals(200, $status, "Notifications request returned an unexpected response to a valid getAll request");
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Notifications request for getAll was not JSON data');
        
        $this->assertEquals(0, $responseArray['is_read'], 'Notification is_read status was not as expected');
        
        
        return $stack;
    }
    
    public function testIntertecServices()
    {
        // Only enabled when testing Intertec directly
        // return;
        
        $baseUrl = 'https://services.randfstaging.com';
        
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            ));
        
        
        $fields = array(
            'format' => 'json',
            'culture' => 'en-US',
            'source' => '1',
            'username' => 'doe',
            'password' => 'doe',
            'type' => '1',
            );
        curl_setopt_array($ch, array(
            CURLOPT_URL => $baseUrl . '/SecurityService/Login',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            ));
        $body = curl_exec($ch);
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to authentication request was not JSON data');;
        
        $this->assertTrue(isset($responseArray['IsValid']), 'IsValid is missing from response');
        $this->assertTrue(isset($responseArray['Token']), 'Token is missing from response');
        $this->assertTrue(isset($responseArray['Data'][0]['RequestorId']), 'RequestorId is missing from response');
        $this->assertTrue((bool)$responseArray['IsValid'], 'Login was not accepted');
        $token = $responseArray['Token'];
        $accountId = $responseArray['Data'][0]['RequestorId'];
        
        
        
        /*
        $fields = array(
            'format' => 'json',
            'culture' => 'en-US',
            'source' => '1',
            'username' => 'doe',
            'password' => 'BADPASSWORD',
            'type' => '1',
            );
        curl_setopt_array($ch, array(
            CURLOPT_URL => $baseUrl . '/SecurityService/Login',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            ));
        $body = curl_exec($ch);
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to authentication request was not JSON data');;
        
        $this->assertTrue(isset($responseArray['IsValid']), 'IsValid is missing from response');
        $this->assertFalse((bool)$responseArray['IsValid'], 'Bad login was accepted but should not have been');
        */
        
        
        // curl -k -d "format=json&culture=en-US&source=1&token=4e9bf9ef-617a-44e2-acd0-5acf386813de&accountId=888" https://services.randfstaging.com/AccountService/GetAccount
        $fields = array(
            'format' => 'json',
            'culture' => 'en-US',
            'source' => '1',
            'token' => $token,
            'accountId' => $accountId,
            );
        curl_setopt_array($ch, array(
            CURLOPT_URL => $baseUrl . '/AccountService/GetAccount',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            ));
        $body = curl_exec($ch);
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to GetAccount request was not JSON data');;
        
        $this->assertTrue(isset($responseArray['Data'][0]['FirstName']), 'Return data has an unexpected structure');
        $this->assertTrue(isset($responseArray['Data'][0]['LastName']), 'Return data has an unexpected structure');
        $this->assertTrue(isset($responseArray['Data'][0]['SponsorId']), 'Return data has an unexpected structure');
        $this->assertTrue(isset($responseArray['Data'][0]['EnrollerId']), 'Return data has an unexpected structure');
        
        
        $fields = array(
            'format' => 'json',
            'culture' => 'en-US',
            'source' => '1',
            'token' => $token,
            'accountId' => $responseArray['Data'][0]['SponsorId'],
            );
        curl_setopt_array($ch, array(
            CURLOPT_URL => $baseUrl . '/AccountService/GetAccount',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            ));
        $body = curl_exec($ch);
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to GetAccount request was not JSON data');;
        
        $this->assertTrue(isset($responseArray['Data'][0]['FirstName']), 'Return data has an unexpected structure');
        $this->assertTrue(isset($responseArray['Data'][0]['LastName']), 'Return data has an unexpected structure');
        $this->assertTrue(isset($responseArray['Data'][0]['SponsorId']), 'Return data has an unexpected structure');
        $this->assertTrue(isset($responseArray['Data'][0]['EnrollerId']), 'Return data has an unexpected structure');
        
        return;
        
        
        // curl -k -d "format=json&culture=en-US&source=1&token=badtoken&accountId=888" https://services.randfstaging.com/AccountService/GetMoreAboutMe
        $fields = array(
            'format' => 'json',
            'culture' => 'en-US',
            'source' => '1',
            'token' => $token,
            'accountId' => $accountId,
            );
        curl_setopt_array($ch, array(
            CURLOPT_URL => $baseUrl . '/AccountService/GetMoreAboutMe',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            ));
        $body = curl_exec($ch);
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to authentication request was not JSON data');;
        
        $this->assertTrue(isset($responseArray['IsValid']), 'IsValid is missing from response');
        $this->assertTrue((bool)$responseArray['IsValid'], 'Request was not accepted but should have been');
        $this->assertTrue(isset($responseArray['Data'][0]['Name']), 'Return data has an unexpected structure');
        $this->assertTrue(isset($responseArray['Data'][0]['Html']), 'Return data has an unexpected structure');
        
        
        // curl -k -d "format=json&culture=en-US&source=1&token=badtoken&accountId=888" https://services.randfstaging.com/AccountService/GetSuccessStory
        $fields = array(
            'format' => 'json',
            'culture' => 'en-US',
            'source' => '1',
            'token' => $token,
            'accountId' => $accountId,
            );
        curl_setopt_array($ch, array(
            CURLOPT_URL => $baseUrl . '/AccountService/GetSuccessStory',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            ));
        $body = curl_exec($ch);
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to authentication request was not JSON data');;
        
        $this->assertTrue(isset($responseArray['IsValid']), 'IsValid is missing from response');
        $this->assertTrue((bool)$responseArray['IsValid'], 'Request was not accepted but should have been');
        $this->assertTrue(isset($responseArray['Data'][0]['SuccessStory']), 'Return data has an unexpected structure');
        
        
        // curl -k -d "format=json&culture=en-US&source=1&token=badtoken&accountId=888" https://services.randfstaging.com/AccountService/GetBestBusinessMoment
        $fields = array(
            'format' => 'json',
            'culture' => 'en-US',
            'source' => '1',
            'token' => $token,
            'accountId' => $accountId,
            );
        curl_setopt_array($ch, array(
            CURLOPT_URL => $baseUrl . '/AccountService/GetBestBusinessMoment',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            ));
        $body = curl_exec($ch);
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to authentication request was not JSON data');;
        
        $this->assertTrue(isset($responseArray['IsValid']), 'IsValid is missing from response');
        $this->assertTrue((bool)$responseArray['IsValid'], 'Request was not accepted but should have been');
        $this->assertTrue(isset($responseArray['Data'][0]['BusinessMomentValue']), 'Return data has an unexpected structure');
        
        
        // curl -k -d "format=json&culture=en-US&source=1&token=badtoken&accountId=888" https://services.randfstaging.com/AccountService/GetFavoriteProducts
        $fields = array(
            'format' => 'json',
            'culture' => 'en-US',
            'source' => '1',
            'token' => $token,
            'accountId' => $accountId,
            );
        curl_setopt_array($ch, array(
            CURLOPT_URL => $baseUrl . '/AccountService/GetFavoriteProducts',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            ));
        $body = curl_exec($ch);
        
        $responseArray = json_decode($body, true);
        $this->assertInternalType('array', $responseArray, 'Response to authentication request was not JSON data');;
        
        $this->assertTrue(isset($responseArray['IsValid']), 'IsValid is missing from response');
        $this->assertTrue((bool)$responseArray['IsValid'], 'Request was not accepted but should have been');
        $this->assertTrue(isset($responseArray['Data'][0]['FavoriteProducts']), 'Return data has an unexpected structure');
        
        
        curl_close($ch);
    }
}
