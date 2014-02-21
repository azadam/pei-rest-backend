<?php

namespace RANDF\PEI\Data;

use \Respect\Validation\Validator as v;
use \RANDF\Database as Database;
use \PDO as PDO;
use \S3 as S3;
use \S3Exception as S3Exception;

/**
* Manage Contacts and related data for the PEI project API
*
* @uses \PDO
* @uses \RANDF\Database
* @uses \Respect\Validation\Validator
*
* @package PEI
* @author "Adam Hooker" <adamh@rodanandfields.com>
*/
class Contacts
{
    /**
     * Translate incoming data before it touches the database methods; ensure all keys
     * have values, ensure their contents are valid (null if unset, etc)
     * Automatically decodes json objects for email/phone as needed
     *
     * @param array $contact Raw contact data (typically $_POST or equivalent)
     *
     * @access public
     * @static
     *
     * @return array Processed contact data
     */
    public static function sanitize($contact)
    {
        // Typecast the contact to an array in case it comes in as a stdClass object
        $contact = (array) $contact;
        $outContact = array();

        $fields = array(
            'contact_id',
            'first_name',
            'last_name',
            'location_id',
            'source_id',
            'commitment',
            'is_new',
            'source',
            'interest',
            'engage',
            'sort_name',
            'emails',
            'phone_numbers',
            'current_status_id',
            'notes',
            'photo_data',
            );
        foreach ($fields as $field) {
            $outContact[$field] = isset($contact[$field]) ? $contact[$field] : null;
        }

        if (isset($outContact['is_new'])) {
            $outContact['is_new'] = (bool) $outContact['is_new'];
        }

        if (isset($outContact['emails']) && is_string($outContact['emails'])) {
            $outContact['emails'] = json_decode($outContact['emails'], true);
            if (is_array($outContact['emails'])) {
                foreach ($outContact['emails'] as &$email) {
                    $email['email_address'] = isset($email['email_address']) ? str_replace(',', '.', $email['email_address']) : null;
                    $email['type'] = isset($email['type']) ? $email['type'] : null;
                }
            } else {
                $outContact['emails'] = null;
            }
        }

        if (isset($outContact['phone_numbers']) && is_string($outContact['phone_numbers'])) {
            $outContact['phone_numbers'] = json_decode($outContact['phone_numbers'], true);
            if (is_array($outContact['phone_numbers'])) {
                foreach ($outContact['phone_numbers'] as &$number) {
                    $number['phone_number'] = isset($number['phone_number']) ? preg_replace('/[^0-9]/', '', $number['phone_number']) : null;
                    $number['extension'] = isset($number['extension']) ? $number['extension'] : null;
                    $number['type'] = isset($number['type']) ? $number['type'] : null;
                }
            } else {
                $outContact['phone_numbers'] = null;
            }
        }

        return $outContact;
    }

    /**
     * Perform validation on all data within the passed contact array; verify field lengths, data types, etc
     *
     * @param mixed $contact Description.
     *
     * @access public
     * @static
     *
     * @throws \InvalidArgumentException
     */
    public static function validate($contact)
    {
        v::int()->positive()->setName('contact_id')->assert($contact['contact_id']);
        v::when(v::string(), v::length(0, 45), v::nullValue())->setName('first_name')->assert($contact['first_name']);
        v::when(v::string(), v::length(0, 45), v::nullValue())->setName('last_name')->assert($contact['last_name']);
        v::when(v::int(), v::positive(), v::nullValue())->setName('location_id')->assert($contact['location_id']);
        v::when(v::int(), v::positive(), v::nullValue())->setName('current_status_id')->assert($contact['current_status_id']);
        v::when(v::string(), v::length(0, 255), v::nullValue())->setName('source_id')->assert($contact['source_id']);
        v::oneOf(v::bool(), v::nullValue())->setName('is_new')->assert($contact['is_new']);
        v::when(v::string(), v::length(0, 25), v::nullValue())->setName('source')->assert($contact['source']);
        v::when(v::string(), v::length(0, 50), v::nullValue())->setName('interest')->assert($contact['interest']);
        v::when(v::string(), v::length(0, 50), v::nullValue())->setName('engage')->assert($contact['engage']);
        v::when(v::string(), v::length(0, 50), v::nullValue())->setName('sort_name')->assert($contact['sort_name']);
        v::when(v::string(), v::length(0, 65534), v::nullValue())->setName('notes')->assert($contact['notes']);

        if (isset($contact['emails'])) {
            // $addressValidator = v::email()->setName('emails.email_address');
            $addressValidator = v::string()->length(0, 255)->setName('emails.email_address');;
            $typeValidator = v::string()->setName('emails.type');
            foreach ($contact['emails'] as $email) {
                $addressValidator->assert($email['email_address']);
                $typeValidator->assert($email['type']);
            }
        }

        if (isset($contact['phone_numbers']) && is_array($contact['phone_numbers'])) {
            $numberValidator = v::when(v::string(), v::length(0, 25), v::nullValue())->setName('phone_numbers.phone_number or extension');
            $typeValidator = v::when(v::string(), v::length(0, 25), v::nullValue())->setName('phone_numbers.type');
            foreach ($contact['phone_numbers'] as $number) {
                $numberValidator->assert($number['phone_number']);
                $numberValidator->assert($number['extension']);
                $typeValidator->assert($number['type']);
            }
        }
    }

    /**
     * Create a new contact with affiliated data based on passed array
     *
     * @param int   $userID  UserID creating the new contact
     * @param array $contact Associative array depicting the new contact
     *
     * @access public
     * @static
     *
     * @return array The resulting data object from the insertion
     */
    public static function create($userID, $contact)
    {
        v::int()->positive()->setName('userID')->assert($userID);
        self::validate($contact);

        $db = Database::getInstance('pei');

        $sql = "
            INSERT
                INTO `Contact`
                    (`ContactID`, `UserID`, `FirstName`, `LastName`, `LocationID`, `Commitment`, `IsNew`, `Source`, `CreatedDateTime`, `UpdatedDateTime`, `CurrentStatusID`, `Interest`, `Engage`, `SortName`, `Photo`, `ExternalSourceIdentifier`, `Notes`)
                VALUES
                    (:ContactID, :UserID, :FirstName, :LastName, :LocationID, :Commitment, :IsNew, :Source, NOW(), NOW(), :CurrentStatusID, :Interest, :Engage, :SortName, :Photo, :ExternalSourceIdentifier, :Notes)
        ";
        $stmt = $db->prepare($sql);

        foreach ($contact as $k=>$v) {
            if ($k != 'location_id' && $k != 'notes' && $k != 'current_status_id' && $v === null) {
                $contact[$k] = '';
            }
        }

        $params = array(
            ':UserID' => $userID,
            ':ContactID' => $contact['contact_id'],
            ':FirstName' => $contact['first_name'],
            ':LastName' => $contact['last_name'],
            ':LocationID' => $contact['location_id'],
            ':Commitment' => $contact['commitment'],
            ':IsNew' => $contact['is_new'],
            ':Source' => $contact['source'],
            ':CurrentStatusID' => $contact['current_status_id'],
            ':Interest' => $contact['interest'],
            ':Engage' => $contact['engage'],
            ':SortName' => $contact['sort_name'],
            ':Photo' => '0',
            ':ExternalSourceIdentifier' => $contact['source_id'],
            ':Notes' => $contact['notes'],
            );

        $db->beginTransaction();
        if (!$stmt->execute($params)) {
            $db->rollback();
            throw new \RuntimeException('Unable to execute SQL command');
        }

        $contact['contact_id'] = $db->lastInsertId();

        try {
            if (isset($contact['phone_numbers']) && is_array($contact['phone_numbers'])) {
                self::storePhoneNumbers($contact['contact_id'], $contact['phone_numbers']);
            }
            if (isset($contact['emails']) && is_array($contact['emails'])) {
                self::storeEmailAddresses($contact['contact_id'], $contact['emails']);
            }
            if (isset($contact['photo_data']) && $contact['photo_data']) {
                $photo_url = self::storePhotoData($userID, $contact['contact_id'], $contact['photo_data']);
                if ($photo_url) {
                    $db->query("UPDATE `Contact` SET `Photo` = '1' WHERE `ContactID` = " . $db->quote($contact['contact_id']));
                } else {
                    throw new \Exception('Failed to save photo_data');
                }
            }
        } catch (\Exception $e) {
            $db->rollback();
            throw $e;
        }

        $db->commit();

        return self::get($userID, $contact['contact_id']);
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
            'user_id' => 'UserID',
            'contact_id' => 'ContactID',
            'first_name' => 'FirstName',
            'last_name' => 'LastName',
            'location_id' => 'LocationID',
            'commitment' => 'Commitment',
            'is_new' => 'IsNew',
            'source' => 'Source',
            'interest' => 'Interest',
            'engage' => 'Engage',
            'current_status_id' => 'CurrentStatusID',
            'source_id' => 'ExternalSourceIdentifier',
            'notes' => 'Notes',
            'has_photo' => 'Photo',
            );
    }

    private static $typeArray = null;
    private static $typeCodeArray = null;

    /**
     * Populates the static arrays used to translate contact qualifier codes to and from type strings
     *
     * @access private
     * @static
     */
    private static function populateTypeArrays()
    {
        $db = Database::getInstance('pei');

        self::$typeArray = array();
        self::$typeCodeArray = array();

        $stmt = $db->query("SELECT `ContactMethodQualifierID`, `Name` FROM `ContactMethodQualifier`");
        if ($stmt === false) {
            throw new \RuntimeException('Unable to execute SQL command');
        }
        $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
        unset($stmt);

        foreach ($types as $type) {
            self::$typeArray[ucfirst($type['Name'])] = $type['ContactMethodQualifierID'];
            self::$typeCodeArray[$type['ContactMethodQualifierID']] = ucfirst($type['Name']);
        }
    }

    /**
     * Add a new contact method type; inserts into the db and updates the locally cached
     * data stores with the newly inserted id
     *
     * @param string $type Type description
     *
     * @access private
     * @static
     */
    private static function addNewType($type)
    {
        v::string()->length(1, 25)->setName('type')->assert($type);

        $type = ucfirst(strtolower($type));

        $db = Database::getInstance('pei');
        $db->query("INSERT INTO `ContactMethodQualifier` (`Name`) VALUES (" . $db->quote($type) . ")");
        $typeID = $db->lastInsertId();
        self::$typeArray[$type] = $typeID;
        self::$typeCodeArray[$typeID] = $type;
    }

    /**
     * Translates a contact method qualifier ID into the equivalent type string
     *
     * @param int $typeID ContactMethodQualifierID
     *
     * @access private
     * @static
     *
     * @return mixed String for the type value or a boolean false if the ID is invalid
     */
    private static function convertIDToContactType($typeID)
    {
        if (self::$typeCodeArray == null) {
            self::populateTypeArrays();
        }

        if (isset(self::$typeCodeArray[$typeID])) {
            return self::$typeCodeArray[$typeID];
        } else {
            return false;
        }
    }

    /**
     * Translates a contact method type string into the equivalent ID, creating a new row if needed
     *
     * @param string $type Type description
     *
     * @access private
     * @static
     *
     * @return int ContactMethodQualifierID
     */
    private static function convertContactTypeToID($type)
    {
        if (self::$typeArray == null) {
            self::populateTypeArrays();
        }

        $type = ucfirst(strtolower($type));
        if (!isset(self::$typeArray[$type])) {
            self::addNewType($type);
        }

        return self::$typeArray[$type];
    }

    /**
     * Accepts a contactID and array of addresses; removes all existing
     * addresses and inserts the new ones.
     *
     * @param int   $contactID
     * @param array $addresses
     *
     * @access private
     * @static
     */
    private static function storeEmailAddresses($contactID, $addresses)
    {
        $db = Database::getInstance('pei');

        if ($db->query("DELETE FROM `ContactEmailAddress` WHERE `ContactID` = " . $db->quote($contactID)) === false) {
            throw new \RuntimeException('Unable to execute SQL command');
        }

        if (count($addresses) > 0) {
            $stmt = $db->prepare("
                INSERT
                    INTO `ContactEmailAddress`
                        (`ContactID`, `EmailAddress`, `ContactMethodQualifierID`)
                    VALUES
                        (:ContactID, :EmailAddress, :ContactMethodQualifierID)
                ");
            foreach ($addresses as &$email) {
                $params = array(
                    ':ContactID' => $contactID,
                    ':EmailAddress' => $email['email_address'],
                    ':ContactMethodQualifierID' => self::convertContactTypeToID($email['type']),
                    );
                if (!$stmt->execute($params)) {
                    throw new \RuntimeException('Unable to execute SQL command');
                }
            }
        }
    }

    /**
     * Accepts a contactID and an array of phone numbers; removes all existing
     * numbers and inserts the new ones.
     *
     * @param int   $contactID
     * @param array $numbers
     *
     * @access private
     * @static
     */
    private static function storePhoneNumbers($contactID, $numbers)
    {
        v::int()->positive()->setName('contactID')->assert($contactID);

        $db = Database::getInstance('pei');

        if ($db->query("DELETE FROM `ContactPhoneNumber` WHERE `ContactID` = " . $db->quote($contactID)) === false) {
            throw new \RuntimeException('Unable to execute SQL command');
        }
        if (count($numbers) > 0) {
            $stmt = $db->prepare("
                INSERT
                    INTO `ContactPhoneNumber`
                        (`ContactID`, `PhoneNumber`, `Extension`, `ContactMethodQualifierID`)
                    VALUES
                        (:ContactID, :PhoneNumber, :Extension, :ContactMethodQualifierID)
                ");
            foreach ($numbers as &$number) {
                $params = array(
                    ':ContactID' => $contactID,
                    ':PhoneNumber' => $number['phone_number'],
                    ':Extension' => isset($number['extension']) ? $number['extension'] : '',
                    ':ContactMethodQualifierID' => self::convertContactTypeToID($number['type']),
                    );
                if (!$stmt->execute($params)) {
                    throw new \RuntimeException('Unable to execute SQL command');
                }
            }
        }
    }

    /**
     * Update an existing contact; only modify passed fields, ignore the others and leave them as-is.
     *
     * @param int   $userID
     * @param array $contact
     *
     * @access public
     * @static
     *
     * @return array Complete contact object with updates applied
     */
    public static function update($userID, $contact)
    {
        v::int()->positive()->setName('userID')->assert($userID);
        self::validate($contact);

        $db = Database::getInstance('pei');

        $fieldMap = self::getFieldMap();

        $db->beginTransaction();

        $updates = array();
        $params = array(':ContactID' => $contact['contact_id']);

        foreach ($contact as $apifield => $value) {
            if ($apifield !== 'contact_id' && isset($fieldMap[$apifield])) {
                $dbfield = $fieldMap[$apifield];
                $updates[] = '`' . $dbfield . '` = :' . $dbfield;
                $params[':' . $dbfield] = $value;
            }
        }
        if (count($updates) > 0) {
            $updates = join(', ', $updates);

            $sql = "
                UPDATE
                    `Contact`
                SET
                    `UpdatedDateTime` = NOW(),
                    $updates
                WHERE
                    `ContactID` = :ContactID
            ";
            $stmt = $db->prepare($sql);
            if (!$stmt->execute($params)) {
                $db->rollback();
                throw new \RuntimeException('Unable to execute SQL command');
            }
        }

        try {
            if (isset($contact['phone_numbers']) && is_array($contact['phone_numbers'])) {
                self::storePhoneNumbers($contact['contact_id'], $contact['phone_numbers']);
            }
            if (isset($contact['emails']) && is_array($contact['emails'])) {
                self::storeEmailAddresses($contact['contact_id'], $contact['emails']);
            }
            if (isset($contact['photo_data']) && $contact['photo_data']) {
                $photo_url = self::storePhotoData($userID, $contact['contact_id'], $contact['photo_data']);
                if ($photo_url) {
                    $db->query("UPDATE `Contact` SET `Photo` = '1' WHERE `ContactID` = " . $db->quote($contact['contact_id']));
                } else {
                    throw new \Exception('Failed to save photo_data');
                }
            }
        } catch (\Exception $e) {
            $db->rollback();
            throw $e;
        }

        $db->commit();

        return self::get($userID, $contact['contact_id']);
    }

    /**
     * Retrieve a single contact
     *
     * @param int $userID
     * @param int $contactID
     *
     * @access public
     * @static
     *
     * @return array
     */
    public static function get($userID, $contactID)
    {
        v::int()->positive()->setName('userID')->assert($userID);
        v::int()->positive()->setName('contactID')->assert($contactID);

        $contact = self::getAll($userID, $contactID);
        if (is_array($contact) && count($contact) == 1) {
            return $contact[0];
        } else {
            return false;
        }
    }

    /**
     * Determine whether the given userID owns the given contactID
     *
     * @param int $userID
     * @param int $contactID
     *
     * @access public
     * @static
     *
     * @return bool
     */
    public static function userOwnsContact($userID, $contactID)
    {
        v::int()->positive()->setName('userID')->assert($userID);
        v::int()->positive()->setName('contactID')->assert($contactID);

        $db = Database::getInstance('pei');

        $sql = "
            SELECT
                COUNT(*) `Count`
            FROM
                `Contact`
            WHERE
                `UserID` = :UserID
                AND
                `ContactID` = :ContactID
        ";

        $stmt = $db->prepare($sql);
        $params = array(
            ':UserID' => $userID,
            ':ContactID' => $contactID,
            );
        if (!$stmt->execute($params)) {
            throw new \RuntimeException('Unable to execute SQL command');
        }

        $results = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($results['Count'] == 1) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Take a database row and expand out the aggregated values
     * (specifically the phone numbers and email addresses, if any)
     * also convert the is_new value to a literal boolean
     *
     * @param array $row
     *
     * @access private
     * @static
     */
    private static function expandDbRow(&$row)
    {
        $row['is_new'] = (bool) $row['is_new'];

        if (strlen($row['PhoneNumbers']) > 0) {
            $numbers = array();
            $a = explode(',', $row['PhoneNumbers']);
            $b = explode(',', $row['PhoneTypes']);
            $c = explode(',', $row['Extensions']);
            foreach ($a as $i => $number) {
                /**
                 * This array keying is deliberate; the double left join can result in
                 * duplicate results in the data.  Filter them out here.
                 */
                $numbers[$number] = array(
                    'phone_number' => $number,
                    'type' => self::convertIDToContactType($b[$i]),
                    'extension' => $c[$i],
                    );
            }
            $row['phone_numbers'] = array_values($numbers);
        }
        unset($row['PhoneNumbers'], $row['Extensions'], $row['PhoneTypes']);

        if (strlen($row['EmailAddresses']) > 0) {
            $emails = array();
            $a = explode(',', $row['EmailAddresses']);
            $b = explode(',', $row['EmailAddressTypes']);
            foreach ($a as $i => $address) {
                /**
                 * This array keying is deliberate; the double left join can result in
                 * duplicate results in the data.  Filter them out here.
                 */
                $emails[$address] = array(
                    'email_address' => $address,
                    'type' => self::convertIDToContactType($b[$i]),
                    );
            }
            $row['emails'] = array_values($emails);
        }
        unset($row['EmailAddresses'], $row['EmailAddressTypes']);

        if ($row['has_photo']) {
            $row['photo_url'] = self::getPhotoDataUrl($row['user_id'], $row['contact_id']);
        }
        unset($row['has_photo']);
    }

    /**
     * Retrieve all of the contacts owned by the given UserID
     *
     * @param int $userID
     * @param int $contactID Optional, narrows results to a single contactID
     *
     * @access public
     * @static
     *
     * @return array
     */
    public static function getAll($userID, $contactID=null)
    {
        v::int()->positive()->setName('userID')->assert($userID);
        v::when(v::int(), v::positive(), v::nullValue())->setName('contactID')->assert($contactID);

        $db = Database::getInstance('pei');

        $fields = array();
        foreach (self::getFieldMap() as $api_field => $db_field) {
            $fields[] = '`' . $db_field . '` `' . $api_field . '`';
        }
        $fields[] = 'UNIX_TIMESTAMP(`CreatedDateTime`) `created_date_time`';
        $fields[] = 'UNIX_TIMESTAMP(`UpdatedDateTime`) `updated_date_time`';

        $fields[] = 'GROUP_CONCAT(`ContactEmailAddress`.`EmailAddress`) `EmailAddresses`';
        $fields[] = 'GROUP_CONCAT(`ContactEmailAddress`.`ContactMethodQualifierID`) `EmailAddressTypes`';
        $fields[] = 'GROUP_CONCAT(`ContactPhoneNumber`.`PhoneNumber`) `PhoneNumbers`';
        $fields[] = 'GROUP_CONCAT(`ContactPhoneNumber`.`Extension`) `Extensions`';
        $fields[] = 'GROUP_CONCAT(`ContactPhoneNumber`.`ContactMethodQualifierID`) `PhoneTypes`';

        $fields = join(', ', $fields);

        $sql = "
            SELECT
                $fields
            FROM
                `Contact`
            LEFT JOIN
                `ContactPhoneNumber` USING (`ContactID`)
            LEFT JOIN
                `ContactEmailAddress` USING (`ContactID`)
            WHERE
                `Contact`.`UserID` = :UserID
        ";

        $params = array(
            ':UserID' => $userID,
            );

        if (isset($contactID) && $contactID > 0) {
            $sql .= " AND `Contact`.`ContactID` = :ContactID ";
            $params[':ContactID'] = $contactID;
        }

        $sql .= " GROUP BY `ContactID`";

        $stmt = $db->prepare($sql);
        if (!$stmt->execute($params)) {
            throw new \RuntimeException('Unable to execute SQL command');
        }

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as &$row) {
            self::expandDbRow($row);
        }

        return $results;
    }

    /**
     * Delete the given contactID
     *
     * @param int $contactID
     *
     * @access public
     * @static
     *
     * @return bool
     */
    public static function delete($contactID)
    {
        v::int()->positive()->setName('contactID')->assert($contactID);
        $db = Database::getInstance('pei');

        $db->beginTransaction();

        $success = true;
        $success = $success && $db->query("
            DELETE
            FROM
                `ContactPhoneNumber`
            WHERE
                `ContactID` = " . $db->quote($contactID));
        $success = $success && $db->query("
            DELETE
            FROM
                `ContactEmailAddress`
            WHERE
                `ContactID` = " . $db->quote($contactID));
        $success = $success && $db->query("
            DELETE
                `Interaction`, `UserUUIDMap`
            FROM `Interaction`
                INNER JOIN `UserUUIDMap` ON (`InteractionID`=`NumericID`)
            WHERE
                `ContactID` = " . $db->quote($contactID));
        $success = $success && $db->query("
            DELETE
            FROM
                `Contact`
            WHERE
                `ContactID` = " . $db->quote($contactID));
        
        if ($success) {
            $db->commit();

            return true;
        } else {
            $db->rollback();
            throw new \RuntimeException('Unable to execute SQL command');
        }
    }

    /**
     * Delete the specified contact status entry
     *
     * @param int $statusID
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     * @return boolean
     */
    public static function deleteStatus($statusID)
    {
        v::int()->positive()->setName('statusID')->assert($statusID);
        $db = Database::getInstance('pei');

        $success = $db->exec("DELETE FROM `Status` WHERE `StatusID` = " . $db->quote($statusID));
        if ($success === false) {
            throw new \RuntimeException('Unable to execute SQL command');
        }

        return true;
    }

    /**
     * Retrieves the given contact status entry
     *
     * @param int $statusID
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     * @return mixed
     */
    public static function getStatus($statusID)
    {
        v::int()->positive()->setName('statusID')->assert($statusID);
        $db = Database::getInstance('pei');

        $stmt = $db->query("
            SELECT
                `StatusID` `status_id`,
                `StatusName` `status_name`,
                `StarRating` `star_rating`,
                UNIX_TIMESTAMP(`UpdatedDateTime`) `updated_date_time`
            FROM
                `Status`
            WHERE
                `StatusID` = " . $db->quote($statusID));

        if ($stmt === false) {
            throw new \RuntimeException('Unable to execute SQL command');
        }

        if ($stmt->rowCount() == 1) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            return false;
        }
    }

    /**
     * Get all statuses attached to the given Contact
     *
     * @param int $contactID
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     * @return mixed             Value.
     */
    public static function getAllStatuses($contactID)
    {
        v::int()->positive()->setName('contactID')->assert($contactID);
        $db = Database::getInstance('pei');

        $stmt = $db->query("
            SELECT
                `StatusID` `status_id`,
                `StatusName` `status_name`,
                `StarRating` `star_rating`,
                UNIX_TIMESTAMP(`UpdatedDateTime`) `updated_date_time`
            FROM
                `Status`
            WHERE
                `ContactID` = " . $db->quote($contactID));

        if ($stmt === false) {
            throw new \RuntimeException('Unable to execute SQL command');
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Store a new contact status
     *
     * @param string $status
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     * @return boolean
     */
    public static function createStatus($status)
    {
        self::validateStatus($status);

        $db = Database::getInstance('pei');
        $stmt = $db->prepare("
            INSERT
                INTO `Status`
                    (`StatusID`, `ContactID`, `StatusName`, `StarRating`, `UpdatedDateTime`)
                VALUES
                    (:StatusID, :ContactID, :StatusName, :StarRating, FROM_UNIXTIME(:UpdatedDateTime))
            ");
        $params = array(
            ':StatusID' => $status['status_id'],
            ':ContactID' => $status['contact_id'],
            ':StatusName' => $status['status_name'],
            ':StarRating' => $status['star_rating'],
            ':UpdatedDateTime' => $status['updated_date_time'],
            );

        $success = $stmt->execute($params);
        if ($success) {
            return true;
        } else {
            throw new \RuntimeException('Unable to execute SQL command');
        }
    }

    private static function getStatusFieldMap()
    {
        return array(
            'StatusID' => 'status_id',
            'ContactID' => 'contact_id',
            'StatusName' => 'status_name',
            'StarRating' => 'star_rating',
            'UpdatedDateTime' => 'updated_date_time',
            );
    }

    public static function updateStatus($status)
    {
        self::validateStatus($status);

        $db = Database::getInstance('pei');

        $params = array();
        $updates = array();
        foreach (self::getStatusFieldMap() as $dbKey => $inputKey) {
            if (isset($status[$inputKey])) {
                $updates[] = "`$dbKey`=:$dbKey";
                $params[":$dbKey"] = $inputKey;
            }
        }
        $sql = "
            UPDATE
                `Status`
            SET
                " . join(', ', $updates) . "
            WHERE
                `StatusID` = :StatusID
            ";
        $stmt = $db->prepare($sql);

        $success = $stmt->execute($params);
        if ($success) {
            return true;
        } else {
            throw new \RuntimeException('Unable to execute SQL command');
        }

    }

    private static function validateStatus(&$status)
    {
        v::int()->positive()->setName('status_id')->assert($status['status_id']);
        v::when(v::int(), v::positive(), v::nullValue())->setName('contact_id')->assert($status['contact_id']);
        v::when(v::string(), v::length(0, 255), v::nullValue())->setName('status_name')->assert($status['status_name']);
        v::when(v::int(), v::positive(), v::nullValue())->setName('star_rating')->assert($status['star_rating']);
        v::when(v::int(), v::positive(), v::nullValue())->setName('updated_date_time')->assert($status['updated_date_time']);
    }

    private static function sanitizeStatus(&$status)
    {
        if (!isset($status['status_name'])) {
            $status['status_name'] = null;
        }
        if (!isset($status['star_rating'])) {
            $status['star_rating'] = null;
        }
        if (!isset($status['updated_date_time'])) {
            $status['updated_date_time'] = null;
        }
    }

    private static $photoUrls;
    public static function getPhotoDataUrl($userID, $contactID)
    {
        if (isset(self::$photoUrls[$userID][$contactID])) {
            return self::$photoUrls[$userID][$contactID];
        }

        try {
            $appConfig = \RANDF\Core::getConfig();
            $s3 = new S3($appConfig['s3']['accessKeyId'], $appConfig['s3']['secretAccessKey'], false, $appConfig['s3']['endpoint']);

            $uri = self::getStorageUri($userID, $contactID);
            $url = $s3->getAuthenticatedURL($appConfig['s3']['bucketName'], $uri, 3600, false, true);
        } catch (S3Exception $e) {
            $url = false;
        }

        self::$photoUrls[$userID][$contactID] = $url;

        return $url;
    }
    public static function deletePhotoData($userID, $contactID)
    {
        try {
            $appConfig = \RANDF\Core::getConfig();
            $s3 = new S3($appConfig['s3']['accessKeyId'], $appConfig['s3']['secretAccessKey'], false, $appConfig['s3']['endpoint']);

            $uri = self::getStorageUri($userID, $contactID);
            $s3->deleteObject($appConfig['s3']['bucketName'], $uri);

            unset(self::$photoUrls[$userID][$contactID]);

            return true;
        } catch (S3Exception $e) {
            return false;
        }
    }
    private static function getStorageUri($userID, $contactID)
    {
        $appConfig = \RANDF\Core::getConfig();

        return $appConfig['s3']['uriPrefix'] . '/' . $userID . '/contact-photos/' . $contactID . '/1';
    }
    public static function storePhotoData($userID, $contactID, &$photo, $retries=2)
    {
        try {
            if (substr($photo, 0, 1) === '<') {
                // Decode from hex stream
                $binaryData = '';
                $position = 1;
                $length = strlen($photo);
                do {
                    $word = substr($photo, $position, 9);

                    if (strlen($word) === 9) {
                        $word = substr($word, 0, 8);
                    } else {
                        $word = substr($word, 0, -1);
                    }
                    $binaryData .= pack("H*", $word);

                    $position += 9;
                } while ($position < $length);
                $photo =& $binaryData;
                unset($binaryData);
            }

            $appConfig = \RANDF\Core::getConfig();
            $s3 = new S3($appConfig['s3']['accessKeyId'], $appConfig['s3']['secretAccessKey'], false, $appConfig['s3']['endpoint']);

            $uri = self::getStorageUri($userID, $contactID);
            $s3->putObject($photo, $appConfig['s3']['bucketName'], $uri, S3::ACL_AUTHENTICATED_READ);

            $url = self::getPhotoDataUrl($userID, $contactID);
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
                $url = self::storePhotoData($userID, $contactID, $photo);
            }
        }

        return $url;
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
        $lw->setLogfile('s3-photos.log');
        $lw->write($debugData);
    }
}
