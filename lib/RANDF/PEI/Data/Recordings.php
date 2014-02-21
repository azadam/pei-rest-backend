<?php

namespace RANDF\PEI\Data;

use \Respect\Validation\Validator as v;
use \RANDF\Database as Database;
use \PDO as PDO;
use \S3 as S3;
use \S3Exception as S3Exception;

/**
* Recordings
*
* @uses \PDO
* @uses \RANDF\Database
* @uses \Respect\Validation\Validator
* @uses \S3
*
* @package PEI
* @author Adam Hooker <adamh@rodanandfields.com>
*/
class Recordings
{
    /**
     * sanitize
     * 
     * @param array $recording
     *
     * @access public
     * @static
     *
     * @return array
     */
    public static function sanitize($recording)
    {
        // Typecast the recording to an array in case it comes in as a stdClass object
        $recording = (array) $recording;
        $outRecording = array();

        $fields = array(
            'recording_id',
            'contact_id',
            'name',
            'category',
            'recorded_date_time',
            'running_time',
            'notes',
            'audio_data',
            );
        foreach ($fields as $field) {
            $outRecording[$field] = isset($recording[$field]) ? $recording[$field] : null;
        }

        return $outRecording;
    }

    /**
     * validate
     * 
     * @param array $recording
     *
     * @access public
     * @static
     */
    public static function validate($recording)
    {
        v::int()->positive()->assert($recording['recording_id']);
        v::when(v::int(), v::positive(), v::nullValue())->assert($recording['contact_id']);
        v::when(v::string(), v::length(1, 100), v::nullValue())->assert($recording['name']);
        v::when(v::int(), v::positive(), v::nullValue())->assert($recording['category']);
        v::oneOf(v::int(), v::nullValue())->assert($recording['running_time']);
        v::when(v::int(), v::positive(), v::nullValue())->assert($recording['recorded_date_time']);
        v::when(v::string(), v::length(0, 65534), v::nullValue())->assert($recording['notes']);
        v::when(v::string(), v::length(0, 16000000), v::nullValue())->assert($recording['audio_data']);
    }

    /**
     * create
     * 
     * @param array $recording
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     *
     * @return array
     */
    public static function create($UserID, $recording)
    {
        self::validate($recording);
        $recording['user_id'] = $UserID;
        
        if (!isset($recording['audio_data']) && isset($_FILES['audio_data']) && is_uploaded_file($_FILES['audio_data']['tmp_name'])) {
            $recording['audio_data'] = file_get_contents($_FILES['audio_data']['tmp_name']);
        }
        
        $db = Database::getInstance('pei');
        
        $db->beginTransaction();

        $sql = "
            INSERT
                INTO `Recording`
                    (`RecordingID`, `UserID`, `ContactID`, `Name`, `RecordedDateTime`, `Notes`, `Category`, `RunningTime`)
                VALUES
                    (:RecordingID, :UserID, :ContactID, :Name, :RecordedDateTime, :Notes, :Category, :RunningTime)
        ";
        $stmt = $db->prepare($sql);
        $params = array(
            ':RecordingID' => $recording['recording_id'],
            ':UserID' => $UserID,
            ':ContactID' => $recording['contact_id'],
            ':Name' => $recording['name'],
            ':RecordedDateTime' => isset($recording['recorded_date_time']) ? gmdate('Y-m-d H:i:s', strtotime($recording['recorded_date_time'])) : gmdate('Y-m-d H:i:s'),
            ':Notes' => $recording['notes'],
            ':Category' => $recording['category'],
            ':RunningTime' => isset($recording['running_time']) ? $recording['running_time'] : 0,
            );
        if (!$stmt->execute($params)) {
            $db->rollback();
            
            throw new \RuntimeException('Unable to execute SQL command');
        }
        
        $recordingID = $db->lastInsertId();
        if (isset($recording['audio_data'])) {
            if (substr($recording['audio_data'], 0, 1) === '<') {
                // Decode from hex stream
                $binaryData = '';
                $position = 1;
                $length = strlen($recording['audio_data']);
                do {
                    $word = substr($recording['audio_data'], $position, 9);
                    
                    if (strlen($word) === 9) {
                        $word = substr($word, 0, 8);
                    } else {
                        $word = substr($word, 0, -1);
                    }
                    $binaryData .= pack("H*", $word);
                    
                    $position += 9;
                } while ($position < $length);
                $recording['audio_data'] =& $binaryData;
                unset($binaryData);
            }
            $url = self::storeRecordingData($UserID, $recordingID, $recording['audio_data']);
            
            $db->query("UPDATE `Recording` SET `HasRecordingData`=1 WHERE `RecordingID` = " . $db->quote($recordingID));
            
            if (!$url) {
                $db->rollback();
                throw new \RuntimeException('Unable to save recording data to AWS');
            }
        }
        $db->commit();
        return self::get($recordingID);
    }

    /**
     * update
     * 
     * @param array $recording
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     *
     * @return array
     */
    public static function update($UserID, $recording)
    {
        self::validate($recording);
        $recording['user_id'] = $UserID;
        v::int()->positive()->assert($recording['recording_id']);

        $db = Database::getInstance('pei');

        $update = array();
        if (isset($recording['name'])) {
            $update['Name'] = $recording['name'];
        }
        if (isset($recording['category'])) {
            $update['Category'] = $recording['category'];
        }
        if (isset($recording['contact_id'])) {
            $update['ContactID'] = $recording['contact_id'];
        }
        if (isset($recording['notes'])) {
            $update['Notes'] = $recording['notes'];
        }
        if (isset($recording['running_time'])) {
            $update['RunningTime'] = $recording['running_time'];
        }


        if (count($update) == 0) {
            return self::get($recording['recording_id']);
        } else {
            $fields = array();
            foreach ($update as $k=>$v) {
                $fields[] = '`' . $k . '`=' . $db->quote($v);
            }
            $fields = join(', ', $fields);
        }

        $sql = "
            UPDATE
                `Recording`
            SET
                $fields
            WHERE
                `RecordingID` = " . $db->quote($recording['recording_id']);

        if ($db->query($sql) === false) {
            throw new \RuntimeException('Unable to execute SQL command: ' . print_r($stmt->errorInfo(), true));
        }

        if (!isset($recording['audio_data']) && isset($_FILES['audio_data']) && is_uploaded_file($_FILES['audio_data']['tmp_name'])) {
            $recording['audio_data'] = file_get_contents($_FILES['audio_data']['tmp_name']);
        }
        if (isset($recording['audio_data'])) {
            if (substr($recording['audio_data'], 0, 1) === '<') {
                // Decode from hex stream
                $binaryData = '';
                $position = 1;
                $length = strlen($recording['audio_data']);
                do {
                    $word = substr($recording['audio_data'], $position, 9);
                    
                    if (strlen($word) === 9) {
                        $word = substr($word, 0, 8);
                    } else {
                        $word = substr($word, 0, -1);
                    }
                    $binaryData .= pack("H*", $word);
                    
                    $position += 9;
                } while ($position < $length);
                $recording['audio_data'] =& $binaryData;
                unset($binaryData);
            }
            $url = self::storeRecordingData($UserID, $recording['recording_id'], $recording['audio_data']);
            $db->query("UPDATE `Recording` SET `HasRecordingData`=1 WHERE `RecordingID` = " . $db->quote($recording['recording_id']));
            
            if (!$url) {
                $db->rollback();
                throw new \RuntimeException('Unable to save recording data to AWS');
            }
        }
        
        return self::get($recording['recording_id']);
    }

    /**
     * get
     * 
     * @param int $recordingID
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     *
     * @return array
     */
    public static function get($recordingID)
    {
        v::int()->positive()->assert($recordingID);

        $db = Database::getInstance('pei');

        $sql = "
            SELECT
                `RecordingID` `recording_id`,
                `UserID` `user_id`,
                `ContactID` `contact_id`,
                `Name` `name`,
                `Category` `category`,
                UNIX_TIMESTAMP(`RecordedDateTime`) `recorded_date_time`,
                `RunningTime` `running_time`,
                `Notes` `notes`,
                `IsCorporate` `is_corporate`,
                `AuthorName` `author_name`,
                `HasRecordingData` `has_recording_data`
            FROM
                `Recording`
            WHERE
                `RecordingID` = :RecordingID
        ";
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            throw new \RuntimeException('Unable to prepare SQL command');
        }
        if (!$stmt->execute(array(':RecordingID' => $recordingID))) {
            throw new \RuntimeException('Unable to execute SQL command');
        }

        $recording = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $fp = fopen('/tmp/ick.txt', 'w');
        fputs($fp, "recordingID: $recordingID\n");
        fputs($fp, print_r($recording, true) . "\n");
        fclose($fp);
        self::expandDbRow($recording);

        return $recording;
    }

    /**
     * expandDbRow
     * 
     * @param array
     *
     * @access private
     * @static
     */
    private static function expandDbRow(&$recording)
    {
        $recording['is_corporate'] = (bool) $recording['is_corporate'];
        if (isset($recording['has_recording_data']) && $recording['has_recording_data'] == 1) {
            $recording['audio_url'] = self::getRecordingDataUrl($recording['user_id'], $recording['recording_id']);
        } else {
            $recording['audio_url'] = null;
        }
    }

    /**
     * getAll
     * 
     * @param int $userID
     *
     * @access public
     * @static
     *
     * @return array
     */
    public static function getAll($userID)
    {
        v::int()->positive()->assert($userID);

        $db = Database::getInstance('pei');

        $sql = "
            SELECT
                `RecordingID` `recording_id`,
                `UserID` `user_id`,
                `ContactID` `contact_id`,
                `Name` `name`,
                `Category` `category`,
                UNIX_TIMESTAMP(`RecordedDateTime`) `recorded_date_time`,
                `RunningTime` `running_time`,
                `Notes` `notes`,
                `IsCorporate` `is_corporate`,
                `AuthorName` `author_name`,
                `HasRecordingData` `has_recording_data`
            FROM
                `Recording`
            WHERE
                `UserID` = :UserID
        ";
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return false;
        }
        if (!$stmt->execute(array(':UserID' => $userID))) {
            throw new \RuntimeException('Unable to execute SQL command: ' . print_r($stmt->errorInfo(), true));
        }
        $recordings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($recordings as &$recording) {
            self::expandDbRow($recording);
        }

        return $recordings;
    }

    /**
     * userOwnsRecording
     * 
     * @param int $userID
     * @param int $recordingID
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     *
     * @return bool
     */
    public static function userOwnsRecording($userID, $recordingID)
    {
        v::int()->positive()->assert($userID);
        v::int()->positive()->assert($recordingID);

        $db = Database::getInstance('pei');

        $sql = "
            SELECT
                COUNT(*) `Count`
            FROM
                `Recording`
            WHERE
                `UserID` = :UserID
                AND
                `RecordingID` = :RecordingID
        ";

        $stmt = $db->prepare($sql);
        $params = array(
            ':UserID' => $userID,
            ':RecordingID' => $recordingID,
            );
        if (!$stmt->execute($params)) {
            throw new \RuntimeException('Unable to execute SQL command: ' . print_r($stmt->errorInfo(), true));
        }

        $results = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($results['Count'] == 1) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * delete
     * 
     * @param int $recordingID
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     *
     * @return bool
     */
    public static function delete($recordingID)
    {
        v::int()->positive()->assert($recordingID);

        $recording = self::get($recordingID);

        $db = Database::getInstance('pei');

        $sql = "
            DELETE
                FROM `Recording`
            WHERE
                `RecordingID` = " . $db->quote($recordingID);
        $success = $db->exec($sql);

        if ($success > 0) {
            self::deleteRecordingData($recording['user_id'], $recording['recording_id']);

            return true;
        } elseif ($success === false) {
            throw new \RuntimeException('Unable to execute SQL command: ' . print_r($stmt->errorInfo(), true));
        } else {
            return false;
        }
    }

    /**
     * getStorageUri
     * 
     * @param int $userID
     * @param int $recordingID
     *
     * @access private
     * @static
     *
     * @return string
     */
    private static function getStorageUri($userID, $recordingID)
    {
        $appConfig = \RANDF\Core::getConfig();

        return $appConfig['s3']['uriPrefix'] . '/' . $userID . '/' . $recordingID;
    }

    /**
     * storeRecordingData
     * 
     * @param int $userID
     * @param int $recordingID
     * @param string $recording Raw recording data
     *
     * @access public
     * @static
     *
     * @return mixed Value.
     */
    public static function storeRecordingData($userID, $recordingID, &$recording, $retries=2)
    {
        try {
            $appConfig = \RANDF\Core::getConfig();
            $s3 = new S3($appConfig['s3']['accessKeyId'], $appConfig['s3']['secretAccessKey'], false, $appConfig['s3']['endpoint']);

            $uri = self::getStorageUri($userID, $recordingID);
            $success = $s3->putObject($recording, $appConfig['s3']['bucketName'], $uri, S3::ACL_AUTHENTICATED_READ);
            if (!$success) {
                throw new S3Exception('false returned from putObject');;
            }

            $url = self::getRecordingDataUrl($userID, $recordingID);
            self::logDebug(array(
                'recordedDataToUrl' => $url,
                ));
        } catch (S3Exception $e) {
            self::logDebug(array(
                'exception' => serialize($e),
                ));
            if ($retries-- < 1) {
                $url = false;
            } else {
                $url = self::storeRecordingData($userID, $recordingID, $recording);
            }
        }

        return $url;
    }

    /**
     * $recordingUrls Internal cache of recording URLs
     *
     * @var mixed
     *
     * @access private
     * @static
     */
    private static $recordingUrls;

    /**
     * getRecordingDataUrl
     * 
     * @param int $userID
     * @param int $recordingID
     *
     * @access public
     * @static
     *
     * @return mixed URL string or boolean false
     */
    public static function getRecordingDataUrl($userID, $recordingID)
    {
        if (isset(self::$recordingUrls[$userID][$recordingID])) {
            return self::$recordingUrls[$userID][$recordingID];
        }

        try {
            $appConfig = \RANDF\Core::getConfig();
            $s3 = new S3($appConfig['s3']['accessKeyId'], $appConfig['s3']['secretAccessKey'], false, $appConfig['s3']['endpoint']);

            $uri = self::getStorageUri($userID, $recordingID);
            $url = $s3->getAuthenticatedURL($appConfig['s3']['bucketName'], $uri, 3600, false, true);
        } catch (S3Exception $e) {
            $url = false;
        }

        self::$recordingUrls[$userID][$recordingID] = $url;

        return $url;
    }

    /**
     * deleteRecordingData
     * 
     * @param int $userID
     * @param int $recordingID
     *
     * @access public
     * @static
     *
     * @return bool
     */
    public static function deleteRecordingData($userID, $recordingID)
    {
        try {
            $appConfig = \RANDF\Core::getConfig();
            $s3 = new S3($appConfig['s3']['accessKeyId'], $appConfig['s3']['secretAccessKey'], false, $appConfig['s3']['endpoint']);

            $uri = self::getStorageUri($userID, $recordingID);
            $s3->deleteObject($appConfig['s3']['bucketName'], $uri);

            unset(self::$recordingUrls[$userID][$recordingID]);

            return true;
        } catch (S3Exception $e) {
            return false;
        }
    }
    /**
     * Debug S3 exchanges
     *
     * @param mixed $debugData Debug data
     *
     * @access private
     * @static
     *
     * @return mixed Value.
     */
    private static function logDebug($debugData)
    {
        $lw = new \RANDF\PEI\LogWriter();
        $lw->setLogfile('s3-recordings.log');
        $lw->write($debugData);
    }
}
