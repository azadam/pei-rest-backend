<?php

namespace RANDF\PEI\Data;

use \Respect\Validation\Validator as v;
use \RANDF\Database as Database;
use \PDO as PDO;

/**
* Locations
*
* @uses \PDO
* @uses \RANDF\Database
* @uses \Respect\Validation\Validator
*
* @package PEI
* @author "Adam Hooker" <adamh@rodanandfields.com>
*/
class Locations
{
    /**
     * Translate out a passed location array to filter the appropriate fields and make sure
     * missing ones have empty strings for later use
     *
     * @param array $location
     *
     * @access public
     * @static
     *
     * @return array
     */
    public static function sanitize($location)
    {
        // Typecast the location to an array in case it comes in as a stdClass object
        $location = (array) $location;
        $outLocation = array();

        $fields = array(
            'location_id',
            'name',
            'address1',
            'address2',
            'city_name',
            'state_name',
            'zipcode',
            );
        foreach ($fields as $field) {
            $outLocation[$field] = isset($location[$field]) ? $location[$field] : '';
        }

        return $outLocation;
    }

    /**
     * Validate the passed location object for field size and restrictions
     *
     * @param array $location
     *
     * @access public
     * @static
     *
     * @throws \InvalidArgumentException
     */
    public static function validate($location)
    {
        v::int()->positive()->assert($location['location_id']);
        v::string()->length(0, 250)->assert($location['name']);
        v::string()->length(0, 100)->assert($location['address1']);
        v::string()->length(0, 100)->assert($location['address2']);
        v::string()->length(0, 100)->assert($location['city_name']);
        v::string()->length(0, 100)->assert($location['state_name']);
        v::string()->length(0, 10)->assert($location['zipcode']);
    }

    /**
     * Create a new location using the passed sanitized/validated data
     *
     * @param int   $UserID
     * @param array $location Description.
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     *
     * @return array
     */
    public static function create($UserID, $location)
    {
        v::int()->positive()->assert($UserID);
        self::validate($location);

        $db = Database::getInstance('pei');

        $sql = "
            INSERT
                INTO `Location`
                    (`LocationID`, `OwnerUserID`, `Name`, `Address1`, `Address2`, `City`, `State`, `Zipcode`)
                VALUES
                    (:LocationID, :UserID, :Name, :Address1, :Address2, :City, :State, :Zipcode)
        ";
        $stmt = $db->prepare($sql);
        $params = array(
            ':UserID' => $UserID,
            ':LocationID' => $location['location_id'],
            ':Name' => $location['name'],
            ':Address1' => $location['address1'],
            ':Address2' => $location['address2'],
            ':City' => $location['city_name'],
            ':State' => $location['state_name'],
            ':Zipcode' => $location['zipcode'],
            );
        if (!$stmt->execute($params)) {
            throw new \RuntimeException('Unable to execute SQL command: ' . print_r($stmt->errorInfo(), true));
        }

        $locationID = $db->lastInsertId();

        return self::get($locationID);
    }

    /**
     * Update the given Location with the passed values
     *
     * @param int   $UserID
     * @param array $location
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     *
     * @return array
     */
    public static function update($UserID, $location)
    {
        v::int()->positive()->assert($UserID);
        self::validate($location);

        $db = Database::getInstance('pei');

        // @TODO: Need to convert this to an UPDATE instead of REPLACE to avoid fk breakage
        $sql = "
            REPLACE
                INTO `Location`
                    (`LocationID`, `OwnerUserID`, `Name`, `Address1`, `Address2`, `City`, `State`, `Zipcode`)
                VALUES
                    (:LocationID, :UserID, :Name, :Address1, :Address2, :City, :State, :Zipcode)
        ";
        $stmt = $db->prepare($sql);
        $params = array(
            ':LocationID' => $location['location_id'],
            ':UserID' => $UserID,
            ':Name' => $location['name'],
            ':Address1' => $location['address1'],
            ':Address2' => $location['address2'],
            ':City' => $location['city_name'],
            ':State' => $location['state_name'],
            ':Zipcode' => $location['zipcode'],
            );
        if (!$stmt->execute($params)) {
            throw new \RuntimeException('Unable to execute SQL command: ' . print_r($stmt->errorInfo(), true));
        }

        return self::get($location['location_id']);
    }

    /**
     * Retrieve a single location by its id
     *
     * @param int $locationID
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     *
     * @return array
     */
    public static function get($locationID)
    {
        v::int()->positive()->assert($locationID);

        $db = Database::getInstance('pei');

        $sql = "
            SELECT
                `LocationID` `location_id`,
                `Name` `name`,
                `Address1` `address1`,
                `Address2` `address2`,
                `City` `city_name`,
                `State` `state_name`,
                `Zipcode` `zipcode`
            FROM
                `Location`
            WHERE
                `LocationID` = :LocationID
        ";
        $stmt = $db->prepare($sql);
        $params = array(
            ':LocationID' => $locationID,
            );
        if (!$stmt->execute($params)) {
            throw new \RuntimeException('Unable to execute SQL command: ' . print_r($stmt->errorInfo(), true));
        }

        $dbLocation = $stmt->fetch(PDO::FETCH_ASSOC);

        return $dbLocation;
    }

    /**
     * Check whether the given userID owns the given locationID
     *
     * @param int $userID
     * @param int $locationID
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     *
     * @return bool
     */
    public static function userOwnsLocation($userID, $locationID)
    {
        v::int()->positive()->assert($userID);
        v::int()->positive()->assert($locationID);

        $db = Database::getInstance('pei');

        $sql = "
            SELECT
                COUNT(*) `Count`
            FROM
                `Location`
            WHERE
                `OwnerUserID` = :UserID
                AND
                `LocationID` = :LocationID
        ";

        $stmt = $db->prepare($sql);
        $params = array(
            ':UserID' => $userID,
            ':LocationID' => $locationID,
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
     * Retrieve all locations attached to the given userID
     *
     * @param int $userID
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     *
     * @return array
     */
    public static function getAll($userID)
    {
        v::int()->positive()->assert($userID);

        $db = Database::getInstance('pei');

        $sql = "
            SELECT
                `LocationID` `location_id`,
                `Name` `name`,
                `Address1` `address1`,
                `Address2` `address2`,
                `City` `city_name`,
                `State` `state_name`,
                `Zipcode` `zipcode`
            FROM
                `Location`
            WHERE
                `OwnerUserID` = :UserID
        ";

        $stmt = $db->prepare($sql);
        $params = array(
            ':UserID' => $userID,
            );
        if (!$stmt->execute($params)) {
            throw new \RuntimeException('Unable to execute SQL command: ' . print_r($stmt->errorInfo(), true));
        }

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $results;
    }

    /**
     * Delete the specified location row
     *
     * @param int $locationID
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     *
     * @return bool
     */
    public static function delete($locationID)
    {
        v::int()->positive()->assert($locationID);

        $db = Database::getInstance('pei');
        
        $db->beginTransaction();

        $sql = "
            UPDATE
                `Contact`
            LEFT JOIN
                `Interaction`
                    USING (`LocationID`)
            LEFT JOIN
                `Event`
                    USING (`LocationID`)
            SET
                `Contact`.`LocationID` = null,
                `Interaction`.`LocationID` = null,
                `Event`.`LocationID` = null
            WHERE
                `LocationID` = " . $db->quote($locationID);
        if ($db->query($sql) === false) {
            $db->rollback();
            throw new \RuntimeException('Unable to execute SQL command: ' . print_r($stmt->errorInfo(), true));
        }

        $sql = "
            DELETE
                FROM `Location`
            WHERE
                `LocationID` = " . $db->quote($locationID);
        $numRows = $db->exec($sql);
        if ($numRows === false) {
            $db->rollback();
            throw new \RuntimeException('Unable to execute SQL command: ' . print_r($stmt->errorInfo(), true));
        }

        if ($numRows) {
            $db->commit();

            return true;
        } else {
            $db->rollback();

            return false;
        }
    }
}
