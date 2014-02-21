<?php

namespace RANDF\PEI\Data;

use \Respect\Validation\Validator as v;
use \RANDF\Database as Database;
use \PDO as PDO;

/**
* Events
*
* @uses \PDO
* @uses \RANDF\Database
* @uses \Respect\Validation\Validator
*
* @package PEI
* @author "Adam Hooker" <adamh@rodanandfields.com>
*/
class Events
{
    /**
     * Translate out a passed event array to filter the appropriate fields and make sure
     * missing ones have null values
     *
     * @param array $event
     *
     * @access public
     * @static
     *
     * @return array
     */
    public static function sanitize($event)
    {
        // Typecast the event to an array in case it comes in as a stdClass object
        $event = (array) $event;
        $outEvent = array();

        $fields = array(
            'event_id',
            'name',
            'event_type',
            'associated_event_id',
            'event_date_time',
            'end_date_time',
            'event_status',
            'contact_mode',
            'simple_location',
            'location_id',
            'calendar_id',
            'notes',
            );
        foreach ($fields as $field) {
            $outEvent[$field] = isset($event[$field]) ? $event[$field] : null;
        }

        return $outEvent;
    }

    /**
     * Validate the passed event object for field size and other restrictions
     *
     * @param array $event
     *
     * @access public
     * @static
     *
     * @throws \InvalidArgumentException
     */
    public static function validate($event)
    {
        v::int()->positive()->assert($event['event_id']);
        v::when(v::string(), v::length(0, 250), v::nullValue())->assert($event['name']);
        v::when(v::int(), v::positive(), v::nullValue())->assert($event['event_date_time']);
        v::when(v::int(), v::positive(), v::nullValue())->assert($event['end_date_time']);
        v::when(v::string(), v::length(0, 25), v::nullValue())->assert($event['event_status']);
        v::when(v::string(), v::length(0, 25), v::nullValue())->assert($event['contact_mode']);
        v::when(v::int(), v::positive(), v::nullValue())->assert($event['associated_event_id']);
        v::when(v::int(), v::positive(), v::nullValue())->assert($event['location_id']);
        v::when(v::string(), v::length(0, 255), v::nullValue())->assert($event['simple_location']);
        v::when(v::string(), v::length(0, 255), v::nullValue())->assert($event['calendar_id']);
        v::when(v::string(), v::length(0, 65534), v::nullValue())->assert($event['notes']);
    }

    /**
     * Returns an array of field mappings to convert the "api name" to the equivalent db column name
     *
     * @access private
     * @static
     *
     * @return array
     */
    private static function getFieldMap()
    {
        return array(
            'event_id' => 'EventID',
            'name' => 'Name',
            'event_type' => 'EventType',
            'associated_event_id' => 'AssociatedEventID',
            'event_date_time' => 'EventDateTime',
            'end_date_time' => 'EventEndDateTime',
            'event_status' => 'Status',
            'contact_mode' => 'ContactMode',
            'location_id' => 'LocationID',
            'simple_location' => 'SimpleLocation',
            'calendar_id' => 'CalendarIdentifier',
            'notes' => 'Notes',
            );
    }

    /**
     * Create a new event using the passed sanitized/validated data
     *
     * @param int   $userID
     * @param array $event  Description.
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     *
     * @return array
     */
    public static function create($userID, $event)
    {
        self::validate($event);

        $db = Database::getInstance('pei');

        $sql = "
            INSERT
                INTO `Event`
                    (`EventID`, `EventType`, `AssociatedEventID`, `UserID`, `EventDateTime`, `EventEndDateTime`, `CreatedDateTime`, `UpdatedDateTime`, `Name`, `LocationID`, `Status`, `ContactMode`, `CalendarIdentifier`, `SimpleLocation`, `Notes`)
                VALUES
                    (:EventID, :EventType, :AssociatedEventID, :UserID, :EventDateTime, :EventEndDateTime, NOW(), NOW(), :Name, :LocationID, :Status, :ContactMode, :CalendarIdentifier, :SimpleLocation, :Notes)
        ";

        $stmt = $db->prepare($sql);
        $params = array(
            ':EventID' => $event['event_id'],
            ':UserID' => $userID,
            ':EventType' => $event['event_type'],
            ':AssociatedEventID' => $event['associated_event_id'],
            ':EventDateTime' => gmdate('Y-m-d H:i:s', $event['event_date_time']),
            ':EventEndDateTime' => gmdate('Y-m-d H:i:s', $event['end_date_time']),
            ':Name' => $event['name'],
            ':LocationID' => (isset($event['simple_location']) && $event['simple_location'] != '') ? null : $event['location_id'],
            ':Status' => $event['event_status'],
            ':ContactMode' => $event['contact_mode'],
            ':CalendarIdentifier' => $event['calendar_id'],
            ':SimpleLocation' => $event['simple_location'],
            ':Notes' => $event['notes'],
            );
        if (!$stmt->execute($params)) {
            throw new \RuntimeException('Unable to execute SQL command: ' . print_r($stmt->errorInfo(), true));
        }

        $eventID = $db->lastInsertId();

        return self::get($eventID);
    }

    /**
     * Update the given event with the passed values
     *
     * @param int   $userID
     * @param array $event
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     *
     * @return array
     */
    public static function update($userID, $event)
    {
        v::int()->positive()->assert($userID);
        self::validate($event);

        $db = Database::getInstance('pei');

        $fieldMap = self::getFieldMap();

        $updates = array();
        $params = array(':EventID' => $event['event_id']);
        if (isset($event['event_date_time'])) {
            $event['event_date_time'] = gmdate('Y-m-d H:i:s', $event['event_date_time']);
        }
        if (isset($event['end_date_time'])) {
            $event['end_date_time'] = gmdate('Y-m-d H:i:s', $event['end_date_time']);
        }

        foreach ($event as $apifield => $value) {
            if (isset($fieldMap[$apifield])) {
                $dbfield = $fieldMap[$apifield];
                $updates[] = '`' . $dbfield . '` = :' . $dbfield;
                $params[':' . $dbfield] = $value;
            }
        }
        if (count($updates) == 0) {
            return false;
        }
        $updates = join(', ', $updates);
        
        if (isset($params[':LocationID']) && isset($params[':SimpleLocation'])) {
            $params[':LocationID'] = null;
        }
        
        $sql = "
            UPDATE
                `Event`
            SET
                `UpdatedDateTime` = NOW(),
                $updates
            WHERE
                `EventID` = :EventID
        ";
        $stmt = $db->prepare($sql);
        if (!$stmt->execute($params)) {
            throw new \RuntimeException('Unable to execute SQL command: ' . print_r($stmt->errorInfo(), true));
        }

        return self::get($event['event_id']);
    }

    /**
     * Retrieve a single event by its id
     *
     * @param int $eventID
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     *
     * @return array
     */
    public static function get($eventID)
    {
        v::int()->positive()->assert($eventID);

        $db = Database::getInstance('pei');

        $sql = "
            SELECT
                `EventID` `event_id`,
                `EventType` `event_type`,
                `AssociatedEventID` `associated_event_id`,
                UNIX_TIMESTAMP(`EventDateTime`) `event_date_time`,
                UNIX_TIMESTAMP(`EventEndDateTime`) `end_date_time`,
                UNIX_TIMESTAMP(`CreatedDateTime`) `created_date_time`,
                UNIX_TIMESTAMP(`UpdatedDateTime`) `updated_date_time`,
                `Status` `event_status`,
                `Event`.`Name` `name`,
                `LocationID` `location_id`,
                `SimpleLocation` `simple_location`,
                `CalendarIdentifier` `calendar_id`,
                CONCAT(IFNULL(`Location`.`Name`, ''), ' ', IFNULL(`Location`.`Address1`, ''), ' ', IFNULL(`Location`.`Address2`, ''), ' ', IFNULL(`Location`.`City`, ''), ' ', IFNULL(`Location`.`State`, ''), ' ', IFNULL(`Location`.`Zipcode`, '')) `FakeSimpleLocation`,
                `Notes` `notes`
            FROM
                `Event`
                LEFT JOIN `Location` USING (`LocationID`)
            WHERE
                `EventID` = :EventID
        ";
        $stmt = $db->prepare($sql);
        $params = array(
            ':EventID' => $eventID,
            );
        if (!$stmt->execute($params)) {
            throw new \RuntimeException('Unable to execute SQL command: ' . print_r($stmt->errorInfo(), true));
        }

        $dbEvent = $stmt->fetch(PDO::FETCH_ASSOC);
        if (isset($dbEvent['location_id']) && $dbEvent['location_id'] != '') {
            $dbEvent['simple_location'] =& $dbEvent['FakeSimpleLocation'];
        }
        unset($dbEvent['FakeSimpleLocation']);

        return $dbEvent;
    }

    /**
     * Check whether the given userID owns the given eventID
     *
     * @param int $userID
     * @param int $eventID
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     *
     * @return bool
     */
    public static function userOwnsEvent($userID, $eventID)
    {
        v::int()->positive()->assert($userID);
        v::int()->positive()->assert($eventID);

        $db = Database::getInstance('pei');

        $sql = "
            SELECT
                COUNT(*) `Count`
            FROM
                `Event`
            WHERE
                `EventID` = :EventID
                AND
                `UserID` = :UserID
        ";

        $stmt = $db->prepare($sql);
        $params = array(
            ':EventID' => $eventID,
            ':UserID' => $userID,
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
     * Retrieve all events attached to the given userID
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
                `EventID` `event_id`,
                `EventType` `event_type`,
                `AssociatedEventID` `associated_event_id`,
                UNIX_TIMESTAMP(`EventDateTime`) `event_date_time`,
                UNIX_TIMESTAMP(`EventEndDateTime`) `end_date_time`,
                UNIX_TIMESTAMP(`CreatedDateTime`) `created_date_time`,
                UNIX_TIMESTAMP(`UpdatedDateTime`) `updated_date_time`,
                `Status` `event_status`,
                `Event`.`Name` `name`,
                `LocationID` `location_id`,
                `SimpleLocation` `simple_location`,
                `CalendarIdentifier` `calendar_id`,
                CONCAT(IFNULL(`Location`.`Name`, ''), ' ', IFNULL(`Location`.`Address1`, ''), ' ', IFNULL(`Location`.`Address2`, ''), ' ', IFNULL(`Location`.`City`, ''), ' ', IFNULL(`Location`.`State`, ''), ' ', IFNULL(`Location`.`Zipcode`, '')) `FakeSimpleLocation`,
                `Notes` `notes`
            FROM
                `Event`
                LEFT JOIN `Location` USING (`LocationID`)
            WHERE
                `UserID` = :UserID
        ";

        $stmt = $db->prepare($sql);
        $params = array(
            ':UserID' => $userID,
            );
        if (!$stmt->execute($params)) {
            throw new \RuntimeException('Unable to execute SQL command: ' . print_r($stmt->errorInfo(), true));
        }

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($results as &$dbEvent) {
            if (isset($dbEvent['location_id']) && $dbEvent['location_id'] != '') {
                $dbEvent['simple_location'] =& $dbEvent['FakeSimpleLocation'];
            }
            unset($dbEvent['FakeSimpleLocation']);
        }

        return $results;
    }

    /**
     * Delete the specified event row
     *
     * @param int $eventID
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     *
     * @return bool
     */
    public static function delete($eventID)
    {
        v::int()->positive()->assert($eventID);

        $db = Database::getInstance('pei');

        $sql = "
            DELETE
                `Event`.*, `Reminder`.*, `Invitee`.*
            FROM
                `Event`
                LEFT JOIN `Reminder`
                    USING (`EventID`)
                LEFT JOIN `Invitee`
                    USING (`EventID`)
            WHERE
                `Event`.`EventID` = :EventID
        ";

        $stmt = $db->prepare($sql);
        $params = array(
            ':EventID' => $eventID,
            );
        if ($stmt->execute($params) === false) {
            throw new \RuntimeException('Unable to execute SQL command: ' . print_r($stmt->errorInfo(), true));
        }

        if ($stmt->rowCount() == 1) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * sanitizeInvitee
     * 
     * @param array $invitee
     *
     * @access public
     * @static
     *
     * @return array
     */
    public static function sanitizeInvitee($invitee)
    {
        // Typecast the invitee to an array in case it comes in as a stdClass object
        $invitee = (array) $invitee;
        $outInvitee = array();

        $fields = array(
            'invitee_id',
            'contact_id',
            'status',
            );
        foreach ($fields as $field) {
            $outInvitee[$field] = isset($invitee[$field]) ? $invitee[$field] : null;
        }

        return $outInvitee;
    }

    /**
     * validate an invitee object
     *
     * @param array $invitee
     *
     * @access public
     * @static
     */
   public static function validateInvitee($invitee)
   {
        v::int()->positive()->setName('invitee.invitee_id')->assert($invitee['invitee_id']);
        v::when(v::int(), v::positive(), v::nullValue())->setName('invitee.contact_id')->assert($invitee['contact_id']);
        v::when(v::int(), v::positive(), v::nullValue())->setName('invitee.event_id')->assert($invitee['event_id']);
        v::when(v::string(), v::length(0, 45), v::nullValue())->setName('invitee.status')->assert($invitee['status']);
    }

    /**
     * addInvitee
     * 
     * @param array $invitee
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     *
     * @return array
     */
    public static function addInvitee($invitee)
    {
        self::validateInvitee($invitee);

        $db = Database::getInstance('pei');

        $sql = "
            REPLACE
                INTO `Invitee`
                    (`InviteeID`, `ContactID`, `EventID`, `Status`)
                VALUES
                    (:InviteeID, :ContactID, :EventID, :Status)
        ";

        $stmt = $db->prepare($sql);
        $params = array(
            ':InviteeID' => $invitee['invitee_id'],
            ':ContactID' => $invitee['contact_id'],
            ':EventID' => $invitee['event_id'],
            ':Status' => $invitee['status'],
            );
        if (!$stmt->execute($params)) {
            throw new \RuntimeException('Unable to execute SQL command: ' . print_r($stmt->errorInfo(), true));
        }

        $inviteeID = $db->lastInsertId();

        return self::getInvitee($inviteeID);
    }

    /**
     * getAllInvitees
     * 
     * @param int $eventID
     *
     * @access public
     * @static
     *
     * @return array
     */
    public static function getAllInvitees($eventID)
    {
        $db = Database::getInstance('pei');

        $sql = "
            SELECT
                `InviteeID` `invitee_id`,
                `ContactID` `contact_id`,
                `EventID` `event_id`,
                `Status` `status`
            FROM
                `Invitee`
            WHERE
                `EventID` = :EventID
        ";
        $stmt = $db->prepare($sql);
        $params = array(
            ':EventID' => $eventID,
            );
        if (!$stmt->execute($params)) {
            throw new \RuntimeException('Unable to execute SQL command: ' . print_r($stmt->errorInfo(), true));
        }

        $invitees = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $invitees;
    }

    /**
     * getInvitee
     * 
     * @param int $inviteeID
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     *
     * @return array
     */
    public static function getInvitee($inviteeID)
    {
        $db = Database::getInstance('pei');

        $sql = "
            SELECT
                `InviteeID` `invitee_id`,
                `ContactID` `contact_id`,
                `EventID` `event_id`,
                `Status` `status`
            FROM
                `Invitee`
            WHERE
                `InviteeID` = :InviteeID
        ";
        $stmt = $db->prepare($sql);
        $params = array(
            ':InviteeID' => $inviteeID,
            );
        if (!$stmt->execute($params)) {
            throw new \RuntimeException('Unable to execute SQL command: ' . print_r($stmt->errorInfo(), true));
        }

        $dbInvitee = $stmt->fetch(PDO::FETCH_ASSOC);

        return $dbInvitee;
    }

    /**
     * userOwnsInvitee
     * 
     * @param int $userID
     * @param int $inviteeID
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     *
     * @return bool
     */
    public static function userOwnsInvitee($userID, $inviteeID)
    {
        $db = Database::getInstance('pei');

        $sql = "
            SELECT
                COUNT(*) `Count`
            FROM
                `Invitee`
            LEFT JOIN
                `Event`
                    USING (`EventID`)
            WHERE
                `InviteeID` = :InviteeID
                AND
                `UserID` = :UserID
        ";

        $stmt = $db->prepare($sql);
        $params = array(
            ':InviteeID' => $inviteeID,
            ':UserID' => $userID,
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
     * deleteInvitee
     * 
     * @param int $inviteeID
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     *
     * @return bool
     */
    public static function deleteInvitee($inviteeID)
    {
        $db = Database::getInstance('pei');

        $sql = "
            DELETE
            FROM
                `Invitee`
            WHERE
                `InviteeID` = :InviteeID
        ";

        $stmt = $db->prepare($sql);
        $params = array(
            ':InviteeID' => $inviteeID,
            );
        if (!$stmt->execute($params)) {
            throw new \RuntimeException('Unable to execute SQL command: ' . print_r($stmt->errorInfo(), true));
        }

        if ($stmt->rowCount() == 1) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * updateInviteeStatus
     * 
     * @param int $inviteeID
     * @param mixed $status int or boolean
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     *
     * @return mixed Value.
     */
    public static function updateInviteeStatus($inviteeID, $status)
    {
        v::when(v::string(), v::length(0,45), v::nullValue())->assert($status);

        $db = Database::getInstance('pei');

        $success = $db->query("UPDATE `Invitee` SET `Status`=" . $db->quote($status) . " WHERE `InviteeID`=" . $db->quote($inviteeID));
        if (!$success) {
            return false;
        }

        return self::getInvitee($inviteeID);
    }
    
    public static function updateInvitee($inviteeID, $invitee)
    {
        $db = Database::getInstance('pei');
        
        $params = array(
            ':InviteeID' => $inviteeID,
            );
        
        $updates = array();
        if (isset($invitee['contact_id'])) {
            $params[':ContactID'] = $invitee['contact_id'];
            $updates[] = '`ContactID` = :ContactID';
        }
        if (isset($invitee['event_id'])) {
            $params[':EventID'] = $invitee['event_id'];
            $updates[] = '`EventID` = :EventID';
        }
        if (isset($invitee['status'])) {
            $params[':Status'] = $invitee['status'];
            $updates[] = '`Status` = :Status';
        }
        
        if (count($updates) === 0) {
            return false;
        }
        $sql = "
            UPDATE
                `Invitee`
            SET
                " . join(', ', $updates) . "
            WHERE
                `InviteeID` = :InviteeID
        ";
        
        $stmt = $db->prepare($sql);
        if (!$stmt->execute($params)) {
            throw new \RuntimeException('Unable to execute SQL command: ' . print_r($stmt->errorInfo(), true));
        }

        return self::getInvitee($inviteeID);
    }
}
