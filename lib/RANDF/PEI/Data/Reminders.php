<?php

namespace RANDF\PEI\Data;

use \Respect\Validation\Validator as v;
use \RANDF\Database as Database;
use \PDO as PDO;

/**
* Reminders
*
* @uses \PDO
* @uses \RANDF\Database
* @uses \Respect\Validation\Validator
*
* @package PEI
* @author Adam Hooker <adamh@rodanandfields.com>
*/
class Reminders
{
    /**
     * Translate out a passed reminder array to filter the appropriate fields and make sure
     * missing ones have empty strings for later use
     *
     * @param array $reminder
     *
     * @access public
     * @static
     *
     * @return array
     */
    public static function sanitize($reminder)
    {
        // Typecast the reminder to an array in case it comes in as a stdClass object
        $reminder = (array) $reminder;
        $outReminder = array();

        $fields = array(
            'reminder_id',
            'event_id',
            'lead_time_minutes',
            );
        foreach ($fields as $field) {
            $outReminder[$field] = isset($reminder[$field]) ? $reminder[$field] : null;
        }

        return $outReminder;
    }

    /**
     * Validate the passed reminder object for field size and restrictions
     *
     * @param array $reminder
     *
     * @access public
     * @static
     */
    public static function validate($reminder)
    {
        v::int()->positive()->assert($reminder['reminder_id']);
        v::when(v::int(), v::positive(), v::nullValue())->assert($reminder['event_id']);
        v::when(v::int(), v::positive(), v::nullValue())->assert($reminder['lead_time_minutes']);
    }

    /**
     * Create a new reminder using the passed sanitized/validated data
     *
     * @param array $reminder Description.
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     *
     * @return array
     */
    public static function create($UserID, $reminder)
    {
        self::validate($reminder);
        v::int()->positive()->assert($reminder['event_id']);

        $db = Database::getInstance('pei');

        $sql = "
            INSERT
                INTO `Reminder`
                    (`ReminderID`, `EventID`, `LeadTimeInMinutes`)
                VALUES
                    (:ReminderID, :EventID, :LeadTimeInMinutes)
        ";
        $stmt = $db->prepare($sql);
        $params = array(
            ':ReminderID' => $reminder['reminder_id'],
            ':EventID' => $reminder['event_id'],
            ':LeadTimeInMinutes' => $reminder['lead_time_minutes'],
            );
        if (!$stmt->execute($params)) {
            throw new \RuntimeException('Unable to execute SQL command: ' . print_r($stmt->errorInfo(), true));
        }

        $reminderID = $db->lastInsertId();

        return self::get($reminderID);
    }

    /**
     * Update the given reminder with the passed values
     *
     * @param array $reminder
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     *
     * @return array
     */
    public static function update($UserID, $reminder)
    {
        self::validate($reminder);
        v::int()->positive()->assert($reminder['reminder_id']);

        $db = Database::getInstance('pei');

        $sql = "
            REPLACE
                INTO `Reminder`
                    (`ReminderID`, `EventID`, `LeadTimeInMinutes`)
                VALUES
                    (:ReminderID, :EventID, :LeadTimeInMinutes)
        ";
        $stmt = $db->prepare($sql);
        $params = array(
            ':ReminderID' => $reminder['reminder_id'],
            ':EventID' => $reminder['reminder_id'],
            ':LeadTimeInMinutes' => $reminder['lead_time_minutes'],
            );
        if (!$stmt->execute($params)) {
            throw new \RuntimeException('Unable to execute SQL command: ' . print_r($stmt->errorInfo(), true));
        }

        return self::get($reminder['reminder_id']);
    }

    /**
     * Retrieve a single reminder by its id
     *
     * @param int $reminderID
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     *
     * @return array
     */
    public static function get($reminderID)
    {
        v::int()->positive()->assert($reminderID);

        $db = Database::getInstance('pei');

        $sql = "
            SELECT
                `ReminderID` `reminder_id`,
                `EventID` `event_id`,
                `LeadTimeInMinutes` `lead_time_minutes`
            FROM
                `Reminder`
            WHERE
                `ReminderID` = :ReminderID
        ";
        $stmt = $db->prepare($sql);
        $params = array(
            ':ReminderID' => $reminderID,
            );
        if (!$stmt->execute($params)) {
            throw new \RuntimeException('Unable to execute SQL command: ' . print_r($stmt->errorInfo(), true));
        }

        $dbReminder = $stmt->fetch(PDO::FETCH_ASSOC);

        return $dbReminder;
    }

    /**
     * Check whether the given userID owns the given reminderID
     *
     * @param int $userID
     * @param int $reminderID
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     *
     * @return bool
     */
    public static function userOwnsReminder($userID, $reminderID)
    {
        v::int()->positive()->assert($userID);
        v::int()->positive()->assert($reminderID);

        $db = Database::getInstance('pei');

        $sql = "
            SELECT
                COUNT(*) `Count`
            FROM
                `Reminder`
            INNER JOIN
                `Event`
                    USING (`EventID`)
            WHERE
                `ReminderID` = :ReminderID
                AND
                `Event`.`UserID` = :UserID
        ";

        $stmt = $db->prepare($sql);
        $params = array(
            ':UserID' => $userID,
            ':ReminderID' => $reminderID,
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
     * Retrieve all reminders attached to the given userID
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
                `Reminder`.`ReminderID` `reminder_id`,
                `Reminder`.`EventID` `event_id`,
                `Reminder`.`LeadTimeInMinutes` `lead_time_minutes`
            FROM
                `Reminder`
            INNER JOIN
                `Event`
                    USING (`EventID`)
            WHERE
                `Event`.`UserID` = :UserID
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
     * Delete the specified reminder row
     *
     * @param int $reminderID
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     *
     * @return bool
     */
    public static function delete($reminderID)
    {
        v::int()->positive()->assert($reminderID);

        $db = Database::getInstance('pei');

        $sql = "
            DELETE
                FROM `Reminder`
            WHERE
                `ReminderID` = :ReminderID
        ";

        $stmt = $db->prepare($sql);
        $params = array(
            ':ReminderID' => $reminderID,
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
}
