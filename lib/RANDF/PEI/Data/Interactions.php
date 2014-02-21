<?php

namespace RANDF\PEI\Data;

use \Respect\Validation\Validator as v;
use \RANDF\Database as Database;
use \PDO as PDO;

/**
* Interactions
*
* @uses \PDO
* @uses \RANDF\Database
* @uses \Respect\Validation\Validator
*
* @package PEI
* @author "Adam Hooker" <adamh@rodanandfields.com>
*/
class Interactions
{
    /**
     * Translate incoming data before it touches the database methods; ensure all keys
     * have values, ensure their contents are valid (null if unset, etc)
     *
     * @param array $interaction Raw interaction data (typically $_POST or equivalent)
     *
     * @access public
     * @static
     *
     * @return array Processed interaction data
     */
    public static function sanitize($interaction)
    {
        // Typecast the interaction to an array in case it comes in as a stdClass object
        $interaction = (array) $interaction;
        $outInteraction = array();

        $fields = array(
            'interaction_id',
            'contact_id',
            'method',
            'outcome',
            'location_id',
            'template_id',
            'event_id',
            'interaction_date_time',
            'notes',
            );
        foreach ($fields as $field) {
            $outInteraction[$field] = isset($interaction[$field]) ? $interaction[$field] : null;
        }

        return $outInteraction;
    }

    /**
     * validate
     * 
     * @param array $interaction
     *
     * @access public
     * @static
     */
    public static function validate($interaction)
    {
        v::int()->positive()->assert($interaction['interaction_id']);
        v::when(v::int(), v::positive(), v::nullValue())->assert($interaction['contact_id']);
        v::when(v::string(), v::length(0, 30), v::nullValue())->assert($interaction['method']);
        v::when(v::string(), v::length(0, 30), v::nullValue())->assert($interaction['outcome']);
        v::when(v::int(), v::positive(), v::nullValue())->assert($interaction['location_id']);
        v::when(v::int(), v::positive(), v::nullValue())->assert($interaction['template_id']);
        v::when(v::int(), v::positive(), v::nullValue())->assert($interaction['event_id']);
        v::when(v::int(), v::positive(), v::nullValue())->assert($interaction['interaction_date_time']);
        v::when(v::string(), v::length(0, 65534), v::nullValue())->assert($interaction['notes']);
    }

    /**
     * create
     * 
     * @param array $interaction
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     *
     * @return array
     */
    public static function create($userID, $interaction)
    {
        self::validate($interaction);

        $db = Database::getInstance('pei');

        $sql = "
            INSERT
                INTO `Interaction`
                    (`InteractionID`, `ContactID`, `Method`, `Outcome`, `LocationID`, `TemplateID`, `EventID`, `InteractionDateTime`, `Notes`)
                VALUES
                    (:InteractionID, :ContactID, :Method, :Outcome, :LocationID, :TemplateID, :EventID, :InteractionDateTime, :Notes)
        ";
        $stmt = $db->prepare($sql);

        $params = array(
            ':InteractionID' => $interaction['interaction_id'],
            ':ContactID' => $interaction['contact_id'],
            ':Method' => $interaction['method'],
            ':Outcome' => $interaction['outcome'],
            ':LocationID' => $interaction['location_id'],
            ':TemplateID' => $interaction['template_id'],
            ':EventID' => $interaction['event_id'],
            ':InteractionDateTime' => isset($interaction['interaction_date_time']) ? gmdate('Y-m-d H:i:s', $interaction['interaction_date_time']) : gmdate('Y-m-d H:i:s'),
            ':Notes' => $interaction['notes'],
            );
        if (!$stmt->execute($params)) {
            throw new \RuntimeException('Unable to execute SQL command: ' . print_r($stmt->errorInfo(), true));
        }

        $interaction['interaction_id'] = $db->lastInsertId();

        return self::get($interaction['interaction_id']);
    }

    /**
     * getFieldMap
     * 
     * @access private
     * @static
     *
     * @return array
     */
    private static function getFieldMap()
    {
        return array(
            'interaction_id' => 'InteractionID',
            'contact_id' => 'ContactID',
            'method' => 'Method',
            'outcome' => 'Outcome',
            'location_id' => 'LocationID',
            'template_id' => 'TemplateID',
            'event_id' => 'EventID',
            'interaction_date_time' => 'InteractionDateTime',
            'notes' => 'Notes',
            );
    }

    /**
     * update an interaction
     * 
     * @param array $interaction
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     *
     * @return array
     */
    public static function update($userID, $interaction)
    {
        if (!isset($interaction['interaction_id'])) {
            throw new \InvalidArgumentException('interaction_id is required to update a current interaction');
        }
        v::int()->positive()->assert($interaction['interaction_id']);

        $fields = array();
        $params = array(':InteractionID' => $interaction['interaction_id']);
        foreach (self::getFieldMap() as $api_field => $db_field) {
            if (isset($interaction[$api_field])) {
                $fields[] = '`' . $db_field . '` = :' . $db_field;
                if ($api_field == 'interaction_date_time') {
                    $params[':' . $db_field] = gmdate('Y-m-d H:i:s', $interaction[$api_field]);
                } else {
                    $params[':' . $db_field] = $interaction[$api_field];
                }
            }
        }
        if (count($fields) == 0) {
            throw new \InvalidArgumentException('no fields to update');
        }

        $fields = join(', ', $fields);

        $sql = "
            UPDATE
                `Interaction`
            SET
                $fields
            WHERE
                `InteractionID` = :InteractionID
        ";

        $db = Database::getInstance('pei');

        $stmt = $db->prepare($sql);
        if (!$stmt->execute($params)) {
            throw new \RuntimeException('Unable to execute SQL command: ' . print_r($stmt->errorInfo(), true));
        }

        return self::get($interaction['interaction_id']);
    }

    /**
     * get an interaction using its ID
     * 
     * @param int $interactionID
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     *
     * @return array or boolean false
     */
    public static function get($interactionID)
    {
        v::int()->positive()->assert($interactionID);

        $fields = array();
        foreach (self::getFieldMap() as $api_field => $db_field) {
            switch ($db_field) {
                case 'InteractionDateTime':
                    $fields[] = 'UNIX_TIMESTAMP(`' . $db_field . '`) `' . $api_field . '`';
                    break;
                default:
                    $fields[] = '`' . $db_field . '` `' . $api_field . '`';
                    break;
            }
        }
        $fields = join(', ', $fields);

        $sql = "
            SELECT
                $fields
            FROM
                `Interaction`
            WHERE
                `InteractionID` = :InteractionID
        ";

        $db = Database::getInstance('pei');
        $stmt = $db->prepare($sql);
        $params = array(
            ':InteractionID' => $interactionID,
            );
        if (!$stmt->execute($params)) {
            throw new \RuntimeException('Unable to execute SQL command: ' . print_r($stmt->errorInfo(), true));
        }

        $interaction = $stmt->fetch(PDO::FETCH_ASSOC);

        return $interaction;
    }

    /**
     * getAll interactions for a specific userID
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

        $fields = array();
        foreach (self::getFieldMap() as $api_field => $db_field) {
            switch ($db_field) {
                case 'InteractionDateTime':
                    $fields[] = 'UNIX_TIMESTAMP(`Interaction`.`' . $db_field . '`) `' . $api_field . '`';
                    break;
                default:
                    $fields[] = '`Interaction`.`' . $db_field . '` `' . $api_field . '`';
                    break;
            }
        }
        $fields = join(', ', $fields);

        $sql = "
            SELECT
                $fields
            FROM
                `Interaction`
            INNER JOIN
                `Contact`
                    USING (`ContactID`)
            WHERE
                `Contact`.`UserID` = :UserID
        ";

        $db = Database::getInstance('pei');
        $stmt = $db->prepare($sql);
        $params = array(
            ':UserID' => $userID,
            );
        if (!$stmt->execute($params)) {
            throw new \RuntimeException('Unable to execute SQL command: ' . print_r($stmt->errorInfo(), true));
        }

        $interactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $interactions;
    }

    /**
     * userOwnsInteraction
     * 
     * @param int $userID
     * @param int $interactionID
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     *
     * @return bool
     */
    public static function userOwnsInteraction($userID, $interactionID)
    {
        $db = Database::getInstance('pei');

        $sql = "
            SELECT
                COUNT(*) `Count`
            FROM
                `Interaction`
            INNER JOIN
                `Contact`
                    USING (`ContactID`)
            WHERE
                `Contact`.`UserID` = :UserID
                AND
                `InteractionID` = :InteractionID
        ";

        $stmt = $db->prepare($sql);
        $params = array(
            ':UserID' => $userID,
            ':InteractionID' => $interactionID,
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
     * @param int $interactionID
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     *
     * @return bool
     */
    public static function delete($interactionID)
    {
        v::int()->positive()->assert($interactionID);
        $db = Database::getInstance('pei');

        $sql = "
            DELETE
            FROM
                `Interaction`
            WHERE
                `InteractionID` = :InteractionID
        ";

        $stmt = $db->prepare($sql);
        $params = array(
            ':InteractionID' => $interactionID,
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
