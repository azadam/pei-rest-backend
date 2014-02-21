<?php

namespace RANDF\PEI\Data;

use \Respect\Validation\Validator as v;
use \RANDF\Database as Database;
use \PDO as PDO;

/**
* Templates
*
* @uses \PDO
* @uses \RANDF\Database
* @uses \Respect\Validation\Validator
*
* @package PEI
* @author Adam Hooker <adamh@rodanandfields.com>
*/
class Templates
{
    /**
     * Sanitize/normalize incoming data structure
     * 
     * @param mixed $template
     *
     * @access public
     * @static
     *
     * @return array
     */
    public static function sanitize($template)
    {
        // Typecast the template to an array in case it comes in as a stdClass object
        $template = (array) $template;
        $outTemplate = array();

        $fields = array(
            'template_id',
            'name',
            'template_type',
            'template_category',
            'body',
            'notes',
            );
        foreach ($fields as $field) {
            $outTemplate[$field] = isset($template[$field]) ? $template[$field] : null;
        }

        return $outTemplate;
    }

    /**
     * Validate the passed template array for field size and restrictions
     *
     * @param array $template
     *
     * @access public
     * @static
     */
    public static function validate($template)
    {
        v::when(v::int(), v::positive(), v::nullValue())->assert($template['template_id']);
        v::when(v::string(), v::length(1, 100), v::nullValue())->assert($template['name']);
        v::when(v::string(), v::length(0, 50), v::nullValue())->assert($template['template_type']);
        v::when(v::int(), v::positive(), v::nullValue())->assert($template['template_category']);
        v::when(v::string(), v::length(0, 65534), v::nullValue())->assert($template['body']);
        v::when(v::string(), v::length(0, 65534), v::nullValue())->assert($template['notes']);
    }

    /**
     * Create a new template
     * 
     * @param array $template
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     *
     * @return array
     */
    public static function create($UserID, $template)
    {
        self::validate($template);
        $template['user_id'] = $UserID;

        $db = Database::getInstance('pei');

        $sql = "
            INSERT
                INTO `Template`
                    (`TemplateID`, `UserID`, `Name`, `CreatedDateTime`, `LastEditedDateTime`, `Category`, `Type`, `Body`, `Notes`)
                VALUES
                    (:TemplateID, :UserID, :Name, NOW(), NOW(), :Category, :Type, :Body, :Notes)
        ";
        $stmt = $db->prepare($sql);

        $params = array(
            ':TemplateID' => $template['template_id'],
            ':UserID' => $template['user_id'],
            ':Name' => $template['name'],
            ':Category' => $template['template_category'],
            ':Type' => $template['template_type'],
            ':Body' => $template['body'],
            ':Notes' => $template['notes'],
            );
        if (!$stmt->execute($params)) {
            throw new \RuntimeException('Unable to execute SQL command: ' . print_r($stmt->errorInfo(), true));
        }

        $template['template_id'] = $db->lastInsertId();

        return self::get($template['template_id']);
    }

    /**
     * Get the mapping of API -> DB field names
     * 
     * @access private
     * @static
     *
     * @return array
     */
    private static function getFieldMap()
    {
        return array(
            'template_id' => 'TemplateID',
            'user_id' => 'UserID',
            'name' => 'Name',
            'template_category' => 'Category',
            'template_type' => 'Type',
            'body' => 'Body',
            'notes' => 'Notes',
            'author_name' => 'AuthorName',
            'is_corporate' => 'IsCorporate',
            );
    }

    /**
     * Process a database row to make any necessary data conversions for API output
     * 
     * @param array $row
     *
     * @access private
     * @static
     */
    private static function expandDbRow(&$row)
    {
        if (is_array($row)) {
            $row['is_corporate'] = (bool) $row['is_corporate'];
        }
    }

    /**
     * Update a template row
     * 
     * @param array $template
     *
     * @access public
     * @static
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     *
     * @return array
     */
    public static function update($UserID, $template)
    {
        if (!isset($template['template_id'])) {
            throw new \InvalidArgumentException('template_id is required to update a current template');
        }
        v::int()->positive()->assert($template['template_id']);

        $fields = array();
        $params = array(':TemplateID' => $template['template_id']);
        foreach (self::getFieldMap() as $api_field => $db_field) {
            if (isset($template[$api_field])) {
                $fields[] = '`' . $db_field . '` = :' . $db_field;
                $params[':' . $db_field] = $template[$api_field];
            }
        }
        if (count($fields) == 0) {
            throw new \InvalidArgumentException('no fields to update');
        }

        $fields = join(', ', $fields);

        $sql = "
            UPDATE
                `Template`
            SET
                $fields
            WHERE
                `TemplateID` = :TemplateID
        ";

        $db = Database::getInstance('pei');

        $stmt = $db->prepare($sql);
        if (!$stmt->execute($params)) {
            throw new \RuntimeException('Unable to execute SQL command: ' . print_r($stmt->errorInfo(), true));
        }

        return self::get($template['template_id']);
    }

    /**
     * Retreive a specific template by ID
     * 
     * @param int $templateID
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     *
     * @return array
     */
    public static function get($templateID)
    {
        v::int()->positive()->assert($templateID);

        $fields = array();
        foreach (self::getFieldMap() as $api_field => $db_field) {
            $fields[] = '`' . $db_field . '` `' . $api_field . '`';
        }
        $fields[] = 'UNIX_TIMESTAMP(`CreatedDateTime`) `created_date_time`';
        $fields[] = 'UNIX_TIMESTAMP(`LastEditedDateTime`) `last_edited_date_time`';
        $fields = join(', ', $fields);

        $sql = "
            SELECT
                $fields
            FROM
                `Template`
            WHERE
                `TemplateID` = :TemplateID
        ";

        $db = Database::getInstance('pei');
        $stmt = $db->prepare($sql);
        $params = array(
            ':TemplateID' => $templateID,
            );
        if (!$stmt->execute($params)) {
            throw new \RuntimeException('Unable to execute SQL command: ' . print_r($stmt->errorInfo(), true));
        }

        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        self::expandDbRow($template);

        return $template;
    }

    /**
     * Retrieve all of the templates owned by the given UserID
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
        $fields = array();
        foreach (self::getFieldMap() as $api_field => $db_field) {
            $fields[] = '`' . $db_field . '` `' . $api_field . '`';
        }
        $fields[] = 'UNIX_TIMESTAMP(`CreatedDateTime`) `created_date_time`';
        $fields[] = 'UNIX_TIMESTAMP(`LastEditedDateTime`) `last_edited_date_time`';
        $fields = join(', ', $fields);

        $sql = "
            SELECT
                $fields
            FROM
                `Template`
            WHERE
                `UserID` = :UserID
        ";

        $db = Database::getInstance('pei');
        $stmt = $db->prepare($sql);
        $params = array(
            ':UserID' => $userID,
            );
        if (!$stmt->execute($params)) {
            throw new \RuntimeException('Unable to execute SQL command: ' . print_r($stmt->errorInfo(), true));
        }

        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($templates as &$template) {
            self::expandDbRow($template);
        }

        return $templates;
    }

    /**
     * userOwnsTemplate
     * 
     * @param int $userID
     * @param int $templateID
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     *
     * @return mixed Value.
     */
    public static function userOwnsTemplate($userID, $templateID)
    {
        v::int()->positive()->assert($userID);
        v::int()->positive()->assert($templateID);
        
        $db = Database::getInstance('pei');

        $sql = "
            SELECT
                COUNT(*) `Count`
            FROM
                `Template`
            WHERE
                `UserID` = :UserID
                AND
                `TemplateID` = :TemplateID
        ";

        $stmt = $db->prepare($sql);
        $params = array(
            ':UserID' => $userID,
            ':TemplateID' => $templateID,
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
     * Delete the specified template
     * 
     * @param int $templateID
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     *
     * @return bool
     */
    public static function delete($templateID)
    {
        v::int()->positive()->assert($templateID);
        
        $db = Database::getInstance('pei');

        $sql = "
            DELETE
                FROM `Template`
            WHERE
                `TemplateID` = :TemplateID
        ";

        $stmt = $db->prepare($sql);
        $params = array(
            ':TemplateID' => $templateID,
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
