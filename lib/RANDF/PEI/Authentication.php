<?php

namespace RANDF\PEI;

use \Memcache as Memcache;
use \Respect\Validation\Validator as v;
use \RANDF\Database as Database;
use \PDO as PDO;

/**
* Authentication
*
* Authenication layer for PEI REST API
*
* @uses \RANDF\Database
* @uses \Respect\Validation\Validator
*
* @package PEI
* @author Adam Hooker <adamh@rodanandfields.com>
*/
class Authentication
{
    /**
     * getUserForToken
     *
     * Validate a token and return the corresponding UserID value
     *
     * @param string $token            String token value being checked
     * @param string $deviceIdentifier String with whatever unique identifier is assigned to the requesting device
     *
     * @access public
     *
     * @return mixed (int) UserID or (bool) false
     */
    public static function getUserForToken($token, $deviceIdentifier=null, $ignoreActiveStatus=false)
    {
        $dbh = Database::getInstance('pei');

        if ($deviceIdentifier !== null) {
            $sql = "
                SELECT
                    `UserID`
                FROM
                    `User`
                INNER JOIN
                    `UserToken`
                        USING (`UserID`)
                WHERE
                    `Token` = :token
                    AND
                    `DeviceIdentifier` = :deviceIdentifier
                    AND
                    `UserToken`.`IsActive`
            ";

            $stmt = $dbh->prepare($sql);
            if (!$stmt->execute(array(':token' => $token, ':deviceIdentifier' => $deviceIdentifier))) {
                return false;
            }
        } else {
            $sql = "
                    SELECT
                        `UserID`
                    FROM
                        `User`
                    INNER JOIN
                        `UserToken`
                            USING (`UserID`)
                    WHERE
                        `Token` = :token
                ";
                if (!$ignoreActiveStatus) {
                    $sql .= "
                        AND
                        `UserToken`.`IsActive`
                    ";
                }

                $stmt = $dbh->prepare($sql);
                if (!$stmt->execute(array(':token' => $token))) {
                    return false;
                }
        }

        $UserID = false;
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $UserID = $row['UserID'];
        }

        return $UserID;
    }

    /**
     * validateToken
     *
     * Validate a token with a username/password pair.  Record the token validity
     * and username association to serve subsequent requests
     *
     * @param string $username   Description.
     * @param string $password   Text username
     * @param string $theirToken Text token passed from an API consumer
     *
     * @access public
     *
     * @return boolean
     */
    public static function validateToken($username, $password, $theirToken)
    {
        // Username & password were rejected; bail here, nothing will save this
        $ourToken = self::validateUserPassword($username, $password);
        if (!$ourToken) {
            self::authDebug(array(
                'message' => 'Failure condition A',
                'username' => $username,
                'password' => $password,
                'theirToken' => $theirToken,
                'theirDecrypted' => self::decryptToken($theirToken),
                ));

            return false;
        }

        if ($ourToken == $theirToken) {
            self::clearTokenFailures($theirToken);
            return true;
        }

        $ourFingerprint = self::getTokenFingerprint($ourToken);
        $theirFingerprint = self::getTokenFingerprint($theirToken);
        if ($ourFingerprint == $theirFingerprint) {
            self::clearTokenFailures($theirToken);
            return true;
        } else {
            self::authDebug(array(
                'message' => 'Failure condition B',
                'username' => $username,
                'password' => $password,
                'ourToken' => $ourToken,
                'theirToken' => $theirToken,
                'ourDecrypted' => self::decryptToken($ourToken),
                'theirDecrypted' => self::decryptToken($theirToken),
                'ourFingerprint' => $ourFingerprint,
                'theirFingerprint' => $theirFingerprint,
                ));

            return false;
        }
    }

    /**
     * Debug failed authentications (things that we wouldn't expect to fail)
     *
     * @param mixed $debugData Debug data
     *
     * @access private
     * @static
     *
     * @return mixed Value.
     */
    private static function authDebug($debugData)
    {
        $lw = new \RANDF\PEI\LogWriter();
        $lw->setLogfile('api.log');
        $lw->write($debugData);
    }

    /**
     * deviceIsMaster
     *
     * @param int    $userID
     * @param string $deviceIdentifier
     *
     * @access public
     * @static
     *
     * @return boolean
     */
    public static function deviceIsMaster($userID, $deviceIdentifier)
    {
        $db = \RANDF\Database::getInstance('pei');

        $userID = $db->quote($userID);
        $deviceIdentifier = $db->quote($deviceIdentifier);
        $stmt = $db->query("SELECT COUNT(*) `Count` FROM `UserToken` WHERE `UserID` = $userID AND `IsActive` AND `DeviceIdentifier` = $deviceIdentifier");
        $isMaster = $stmt->fetchColumn();

        if ($isMaster > 0) {
            return true;
        } else {
            // Have they ever logged in before?  If not, they're master by default
            $stmt = $db->query("SELECT COUNT(*) `Count` FROM `UserToken` WHERE `UserID` = $userID");
            $tokenCount = $stmt->fetchColumn();
            if ($tokenCount > 0) {
                return false;
            } else {
                return true;
            }
        }
    }

    /**
     * Record the given username and token as being associated with each other
     * along with the passed unique device identifier.
     *
     * @param string $username
     * @param string $token
     * @param string $deviceIdentifier
     * @param array $genealogy
     * @param string $version
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     *
     * @return boolean Indication of whether a full device resync is required
     */
    public static function storeUserToken($username, $token, $deviceIdentifier=null, $genealogy=null, $version=null)
    {
        $syncNeeded = true;

        $dbh = \RANDF\Database::getInstance('pei');
        $AccountID = self::getAccountIdFromToken($token);
        if (!$AccountID) {
            throw new \RuntimeException('Unable to decrypt/extract AccountID from token');
        }

        $sql = "
            INSERT IGNORE
                INTO `User`
                    (`PulseUsername`, `AccountID`)
                VALUES
                    (:username, :accountId)
        ";
        $stmt = $dbh->prepare($sql);
        if (!$stmt->execute(array(':username' => $username, ':accountId' => $AccountID))) {
            return false;
        }

        $profileData = self::getProfileInfoFromToken($token);
        if ($profileData !== false) {
            $sql = "
                UPDATE
                    `User`
                SET
                    `PulseUsername` = :username,
                    `GetAccountResults` = :getAccountResults
                WHERE
                    `AccountID` = :accountId
            ";
            $stmt = $dbh->prepare($sql);
            $stmt->execute(array(
                ':username' => $username,
                ':accountId' => $AccountID,
                ':getAccountResults' => json_encode($profileData),
                ));
        }

        if (is_array($genealogy) && isset($genealogy['SponsorID'])) {
            $sql = "
                UPDATE
                    `User`
                SET
                    `SponsorID` = :sponsorId,
                    `NSCoreID` = :nscoreId
                WHERE
                    `AccountID` = :accountId
            ";
            $stmt = $dbh->prepare($sql);
            $stmt->execute(array(
                ':accountId' => $AccountID,
                ':sponsorId' => $genealogy['SponsorID'],
                ':nscoreId' => $genealogy['NSCoreID'],
                ));
        }

        if ($deviceIdentifier !== null && $stmt->rowCount() == 0) {
            $sql = "
                SELECT
                    `Token`, `DeviceIdentifier`
                FROM
                    `User`
                INNER JOIN
                    `UserToken`
                        USING (`UserID`)
                WHERE
                    `IsActive`
                    AND
                    `AccountID` = :accountId
            ";

            $stmt = $dbh->prepare($sql);
            if (!$stmt->execute(array(':accountId' => $AccountID))) {
                throw new \Exception('Unable to locate existing token data');
            }

            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row['DeviceIdentifier'] == $deviceIdentifier) {
                $syncNeeded = false;
            }
        }

        if ($deviceIdentifier !== null && $deviceIdentifier !== 'PEI Activity Report') {
            $sql = "
                UPDATE
                    `User`
                INNER JOIN
                    `UserToken`
                        USING (`UserID`)
                SET
                    `UserToken`.`IsActive`=0
                WHERE
                    `AccountID`=:accountId
                    AND
                    `Token`!=:token
            ";

            $stmt = $dbh->prepare($sql);
            if (!$stmt->execute(array(':accountId' => $AccountID, ':token' => $token))) {
                throw new \Exception('Unable to invalidate older user tokens');
            }
        }

        $sql = "
            REPLACE
                INTO `UserToken`
                    (`UserID`, `Token`, `TokenLastUsed`, `IsActive`, `DeviceIdentifier`, `PulseVersion`)
                SELECT
                    `UserID`, :token, NOW(), :isActive, :deviceIdentifier, :pulseVersion
                FROM
                    `User`
                WHERE
                    `User`.`AccountID`=:accountId
        ";

        if ($deviceIdentifier === null) {
            $IsActive = 0;
            $deviceIdentifier = '';
        } else {
            $IsActive = 1;
        }

        $stmt = $dbh->prepare($sql);
        if (!$stmt->execute(array(':accountId' => $AccountID, ':token' => $token, ':deviceIdentifier' => $deviceIdentifier, ':isActive' => $IsActive, ':pulseVersion' => $version))) {
            throw new \RuntimeException('Unable to store user token');
        }

        return $syncNeeded;
    }

    /**
     * Access the geneaology (sponsor, etc) for a given account
     *
     * @param string $username
     * @param string $password
     * @param int    $AccountID
     *
     * @access public
     * @static
     *
     * @return mixed genealogy in an assoc array, or null
     */
    public static function getGenealogyForUser($username, $password, $AccountID)
    {
        $memcache = self::getMemcacheHandle();
        $_cid = 'getGenealogyForUser:' . $AccountID;
        
        $respArray = $memcache->get($_cid);
        if (isset($respArray)) {
            return $respArray;
        }
        
        try {
            $ch = curl_init();

            $postData = array(
                'UserName' => $username,
                'Password' => $password,
                );
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_HEADER => 0,
                CURLOPT_URL => 'https://www.myrfpulse.com/Account/LogOn',
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8_2) AppleWebKit/537.11 (KHTML, like Gecko) Chrome/23.0.1271.64 Safari/537.11',
                CURLOPT_COOKIEJAR => '/dev/null',
                CURLOPT_POST => 1,
                CURLOPT_POSTFIELDS => $postData,
                CURLOPT_CONNECTTIMEOUT => 1,
                ));

            // Log in
            curl_exec($ch);

            // Snatch the account details
            $postData = array(
                'nodeId' => (string) $AccountID,
                );
            curl_setopt_array($ch, array(
                CURLOPT_URL => 'https://www.myrfpulse.com/Performance/GetAccountDetails',
                CURLOPT_POSTFIELDS => $postData,
                ));
            $resp = json_decode(curl_exec($ch));

            $respArray = array(
                'SponsorID' => 0,
                'EnrollerID' => 0,
                'NSCoreID' => 0,
                );
            if ($resp !== null && isset($resp->details)) {
                $resp->details;

                if (preg_match('{<li class=\"Alt\">[\r\n\t]*<label class=\"bold\">[\r\n\t]*ID:</label><div>[\r\n\t]*([0-9]+)</div>}', $resp->details, $regs)) {
                    $respArray['NSCoreID'] = (int) $regs[1];
                }
                if (preg_match('{<li class=\"Alt\">[\r\n\t]*<label class=\"bold\">[\r\n\t]*Sponsor ID:</label><div>[\r\n\t]*([0-9]+)</div>}', $resp->details, $regs)) {
                    $respArray['SponsorID'] = (int) $regs[1];
                }
            }

            curl_close($ch);

            $memcache->set($_cid, $respArray, null, 86400*7);
            return $respArray;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Take the given token and translate it into a uniquely identifiable hash value
     *
     * @param string $token
     *
     * @access private
     * @static
     *
     * @return string
     */
    private static function getTokenFingerprint($token)
    {
        return self::getAccountIdFromToken($token);

        try {
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_POST => 1,
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_URL => 'https://mobile.rodanandfields.com/Pulse2/Stats/MyStats',
                CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
                CURLOPT_USERAGENT => 'Pulse 1.0.1 (iPhone Simulator; iPhone OS 6.0; en_US)',

                /* debuggery (w/ Charles)
                CURLOPT_PROXY => 'http://localhost:8888',
                CURLOPT_SSL_VERIFYPEER => 0,
                //*/
                ));

            $postData = array(
                'token' => $token,
                'periodId' => date('Ym', time()),
                );
            $postData = json_encode($postData);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

            $resp = curl_exec($ch);
            if (strlen($resp) < 1) {
                throw new \Exception('No MyStats response received');
            }

            $respArray = json_decode($resp, true);
            if ($respArray === null) {
                throw new \Exception('MyStats JSON response was invalid');
            }

            if (!isset($respArray['authResult']['Token']) || $respArray['authResult']['Token'] != $token) {
                throw new \Exception('MyStats JSON response was valid but contained no token confirmation, an unexpected data structure, or a mismatched token');
            }

            if (!isset($respArray['data'])) {
                throw new \Exception('MyStats JSON response was valid but contained no data for fingerprinting');
            }

            return md5(serialize($respArray['data']));
        } catch (\Exception $e) {
            return false;
        }

        return false;
    }

    /**
     * Authenticate the given username/password, returning the new authorization
     * token if successful
     *
     * @param string $username
     * @param string $password
     *
     * @access public
     * @static
     *
     * @throws \Exception
     *
     * @return string
     */
    public static function validateUserPassword($username, $password)
    {
        $memcache = self::getMemcacheHandle();
        $_cid = 'validateUserPassword:' . md5($username . $password);
        
        $token = $memcache->get($_cid);
        if ($token !== false) {
            if ($token == 'failed') {
                return false;
            } else {
                return $token;
            }
        }
        
        try {
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_POST => 1,
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_URL => 'https://mobile.rodanandfields.com/Pulse2/Security/AuthConsultant',
                CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
                CURLOPT_USERAGENT => 'Pulse 1.0.1 (iPhone Simulator; iPhone OS 6.0; en_US)',

                /* debuggery (w/ Charles)
                CURLOPT_PROXY => 'http://localhost:8888',
                CURLOPT_SSL_VERIFYPEER => 0,
                //*/
                ));

            $postData = array(
                'version' => '1.0.1',
                'username' => $username,
                'password' => $password,
                );
            $postData = json_encode($postData);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

            $resp = curl_exec($ch);
            if (strlen($resp) < 1) {
                throw new \Exception('No authentication response received');
            }

            $respArray = json_decode($resp, true);
            if ($respArray === null) {
                throw new \Exception('Authentication JSON response was invalid');
            }

            if (isset($respArray['authResult']['Token'])) {
                $token = $respArray['authResult']['Token'];
                $memcache->set($_cid, $token, null, 86400);
                return $token;
            } else {
                throw new \Exception('Authentication JSON response was valid but contained no token or an unexpected data structure');
            }
        } catch (\Exception $e) {
            $memcache->set($_cid, 'failed', null, 300);
            return false;
        }

        return false;
    }

    /**
     * Extract the accountId from an encrypted token
     *
     * @param string $token
     *
     * @access public
     * @static
     *
     * @return int AccountID (or null if failed)
     */
    public static function getAccountIdFromToken($token)
    {
        $decrypted = self::decryptToken($token);

        // list($tokenExpires, $AccountID, $sessionStarted) =
        $decrypted = explode('|', $decrypted);
        if (count($decrypted) === 3) {
            return $decrypted[1];
        } else {
            return false;
        }
    }

    /**
     * Decrypt a pulse session token string
     *
     * @param string $token
     *
     * @access public
     * @static
     *
     * @return string decrypted
     */
    public static function decryptToken($token)
    {
        $mcrypt_cipher = MCRYPT_RIJNDAEL_128;
        $mcrypt_mode = MCRYPT_MODE_CBC;

        $encrypted = base64_decode($token);

        $key = 'abcdefghijklmnmoabcdefghijklmnmo';
        $iv = 'abcdefghijklmnmo';

        while (strlen($iv) < 16) {
            $iv .= '#';
        }
        while (strlen($key) < 16) {
            $key .= '#';
        }

        $decrypted = trim(mcrypt_decrypt($mcrypt_cipher, $key, $encrypted, $mcrypt_mode, $iv));

        return $decrypted;
    }

    /**
     * Grab profile data from Pulse for the given account
     *
     * @param string $token
     * @param int    $AccountID
     *
     * @access public
     * @static
     *
     * @return mixed profile array or false
     */
    public static function getProfileInfoFromToken($token, $AccountID = null)
    {
        if ($AccountID === null) {
            $AccountID = self::getAccountIdFromToken($token);
        }
        
        $memcache = self::getMemcacheHandle();
        $_cid = 'getProfileInfoFromToken:' . $AccountID;
        $profileInfo = $memcache->get($_cid);
        if ($profileInfo !== false) {
            if ($profileInfo == 'failed') {
                return false;
            } else {
                return $profileInfo;
            }
        }

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_POST => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => 'https://mobile.rodanandfields.com/Pulse2/Stats/MyStats',
            CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
            CURLOPT_USERAGENT => 'Pulse 1.0.1 (iPhone Simulator; iPhone OS 6.0; en_US)',
            ));

        $postData = array(
            'token' => $token,
            'periodId' => gmdate('Ym', time()),
            'accountId' => $AccountID,
            );
        $postData = json_encode($postData);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

        $resp = curl_exec($ch);
        $resp = json_decode($resp);

        if (isset($resp->data->FullName)) {
            $profileInfo = (array) $resp->data;
            $memcache->set($_cid, $profileInfo, null, 86400);
            return $profileInfo;
        } else {
            $memcache->set($_cid, 'failed', null, 300);
            return false;
        }
    }

    /**
     * Pulls stored profile info from the db for this userid
     *
     * @param int $UserID
     *
     * @access public
     * @static
     *
     * @return mixed profile array or false
     */
    public static function getProfileInfoFromUserId($UserID)
    {
        v::int()->positive()->assert($UserID);
        $db = \RANDF\Database::getInstance('pei');

        $sql = "
            SELECT
                `GetAccountResults`
            FROM
                `User`
            WHERE
                `UserID` = " . $db->quote($UserID);
        $rows = $db->query($sql);

        if ($rows->rowCount() == 1) {
            $row = $rows->fetch(PDO::FETCH_ASSOC);

            return json_decode($row['GetAccountResults'], true);
        } else {
            return false;
        }
    }

    /**
     * Wraps getProfileInfoFromUserId so it can be accessed via AccountID
     *
     * @param int $AccountID
     *
     * @access public
     * @static
     *
     * @return mixed profile array or false
     */
    public static function getProfileInfoFromAccountId($AccountID)
    {
        v::int()->positive()->assert($AccountID);

        return self::getProfileInfoFromUserId(self::getUserIdFromAccountId($AccountID));
    }

    /**
     * Grabs the associated AccountID for this UserID
     *
     * @param int $UserID
     *
     * @access public
     * @static
     *
     * @return int
     */
    public static function getAccountIdFromUserId($UserID)
    {
        v::int()->positive()->assert($UserID);
        $db = \RANDF\Database::getInstance('pei');

        $sql = "
            SELECT
                `AccountID`
            FROM
                `User`
            WHERE
                `UserID` = " . $db->quote($UserID);
        $rows = $db->query($sql);

        if ($rows->rowCount() == 1) {
            $row = $rows->fetch(PDO::FETCH_ASSOC);

            return $row['AccountID'];
        } else {
            return false;
        }
    }


    /**
     * Grabs the associated UserID for this AccountID
     *
     * @param int $AccountID
     *
     * @access public
     * @static
     *
     * @return int
     */
    public static function getUserIdFromAccountId($AccountID)
    {
        v::int()->positive()->assert($AccountID);
        $db = \RANDF\Database::getInstance('pei');

        $sql = "
            SELECT
                `UserID`
            FROM
                `User`
            WHERE
                `AccountID` = " . $db->quote($AccountID);
        $rows = $db->query($sql);

        if ($rows->rowCount() == 1) {
            $row = $rows->fetch(PDO::FETCH_ASSOC);

            return $row['UserID'];
        } else {
            return false;
        }
    }

    /**
     * Records the acceptance of the PEI TOS
     *
     * @param int $UserID
     *
     * @access public
     * @static
     */
    public static function storeTOSAcceptance($UserID)
    {
        v::int()->positive()->assert($UserID);
        $db = \RANDF\Database::getInstance('pei');
        $db->exec("UPDATE `User` SET `TOSAcceptanceDate` = NOW() WHERE `UserID` = " . $db->quote($UserID) . " AND `TOSAcceptanceDate` IS NULL");
    }

    /**
     * Tells whether a user has accepted the TOS yet
     *
     * @param int $UserID
     *
     * @access public
     * @static
     *
     * @return boolean
     */
    public static function hasAcceptedTOS($UserID)
    {
        return true;
        
        /*
        if (!$UserID) {
            return false;
        }
        $db = \RANDF\Database::getInstance('pei');
        $stmt = $db->query("SELECT (IFNULL(UNIX_TIMESTAMP(`TOSAcceptanceDate`), 0) > 0) TOSAccepted FROM `User` WHERE `UserID` = " . $db->quote($UserID));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row['TOSAccepted'] == 1) {
            return true;
        } else {
            return false;
        }
        */
    }

    /**
     * Get the NSCoreID associated with the given AccountID
     *
     * @param int $AccountID
     *
     * @access public
     * @static
     *
     * @return int
     */
    public static function getNSCoreIdFromAccountId($AccountID)
    {
        $db = \RANDF\Database::getInstance('pei');
        $stmt = $db->query("SELECT `NSCoreID` FROM `User` WHERE `AccountID` = " . $db->quote($AccountID));
        $NSCoreID = $stmt->fetchColumn();

        return $NSCoreID;
    }
    
    private static function getMemcacheHandle()
    {
        static $memcache = null;
        if ($memcache === null) {
            $memcache = new Memcache;
            $memcache->addServer('endorcache.m9qcl0.0001.usw2.cache.amazonaws.com', 11211);
            $memcache->addServer('endorcache.m9qcl0.0002.usw2.cache.amazonaws.com', 11211);
        }
        return $memcache;
    }
    
    private static function getTokenFailureCacheKey($token)
    {
        return 'tokenFailures:' . md5(\RANDF\Core::getConfigFile()) . ':' . md5($token);
    }
    
    public static function clearTokenFailures($token)
    {
        $memcache = self::getMemcacheHandle();
        $cacheKey = self::getTokenFailureCacheKey($token);
        $memcache->delete($cacheKey);
    }
    
    public static function recordTokenFailure($token)
    {
        $memcache = self::getMemcacheHandle();
        $cacheKey = self::getTokenFailureCacheKey($token);
        
        $memcache->add($cacheKey, 0, null, 900);
        $failures = $memcache->increment($cacheKey);
        return $failures;
    }
    
    public static function getTokenFailures($token)
    {
        $memcache = self::getMemcacheHandle();
        $cacheKey = self::getTokenFailureCacheKey($token);
        $failures = $memcache->get($cacheKey);
        if ($failures === false) {
            return 0;
        }
        return $failures;
    }
    
    public static function getAppVersion($UserID)
    {
        $db = \RANDF\Database::getInstance('pei');
        $stmt = $db->query("SELECT `PulseVersion` FROM `UserToken` WHERE `UserID` = " . $db->quote($UserID) . " AND `IsActive`");
        $PulseVersion = $stmt->fetchColumn();
        
        return $PulseVersion;
    }
}
