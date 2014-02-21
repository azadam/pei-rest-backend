<?php

namespace RANDF\PEI;

/**
* Log writer class (compatible with Slim's logging engine)
*
* @uses \RANDF\Core
*
* @package PEI
* @author Adam Hooker <adamh@rodanandfields.com>
*/
class LogWriter
{
    private $logFile = 'debug.log';

    /**
     * Set the logfile to be written to the given basename
     *
     * @param string $filename Logfile filename
     *
     * @access public
     */
    public function setLogfile($filename)
    {
        $this->logFile = basename($filename);
    }

    /**
     * Write a message (mixed datatype) to the application log
     *
     * @param mixed $message
     *
     * @access public
     */
    public function write($message)
    {
        $appConfig = \RANDF\Core::getConfig();

        $debugLog = __DIR__ . '/../../../logs/' . $this->logFile;
        if (!is_file($debugLog)) {
            if (is_writeable(dirname($debugLog))) {
                if (!touch($debugLog)) {
                    return;
                }
            } else {
                return;
            }
        }

        if (!is_writeable($debugLog)) {
            return;
        }

        $fp = fopen($debugLog, 'r+');
        if (flock($fp, LOCK_EX)) {
            fseek($fp, 0, SEEK_END);

            if (is_array($message)) {
                if (isset($_GET['token'])) {
                    $message['token'] = $_GET['token'];
                }
                if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                    $message['orig_ip'] = $_SERVER['HTTP_X_FORWARDED_FOR'];
                }
                if (isset($_SERVER['REMOTE_ADDR'])) {
                    $message['ip'] = $_SERVER['REMOTE_ADDR'];
                }
            }
            fputs($fp, time() . "\t" . \RANDF\Core::json_encode($message) . "\n");

            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }
}
