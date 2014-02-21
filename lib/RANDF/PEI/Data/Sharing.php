<?php

namespace RANDF\PEI\Data;

use \Respect\Validation\Validator as v;
use \RANDF\Database as Database;
use \PDO as PDO;

class Sharing
{
    public static function santitize($sharing)
    {
        $sharing = (array) $sharing;
        $outSharing = array();
        $fields = array(
            'sharing_id',
            'user_id',
            'entity_type',
            'entity_id',
            'entity_location',
            'sharing_date_time',
            'recipient_id',
            );
        
        foreach ($fields as $field) {
            $outLocation[$field] = isset($location[$field]) ? $location[$field] : '';
        }

        return $outLocation;
    }
    
    public static function validate(&$sharing)
    {
        v::int()->positive()->setName('sharing_id')->assert($sharing['sharing_id']);
        v::int()->positive()->setName('user_id')->assert($sharing['user_id']);
        v::string()->length(1)->setName('entity_type')->assert($sharing['entity_type']);
        v::int()->positive()->setName('entity_id')->assert($sharing['entity_id']);
        v::int()->positive()->setName('sharing_date_time')->assert($sharing['sharing_date_time']);
        v::int()->positive()->setName('recipient_id')->assert($sharing['recipient_id']);
    }
    
    public static function recordSharingEvent($sharing)
    {
        self::validate($sharing);
        
        $db = Database::getInstance('pei');
        $stmt = $db->prepare("
            INSERT
                INTO `UserSharing`
                    (`UserID`, `RecipientID`, `EntityType`, `EntityID`, `EntityLocation`, `SharedDateTime`)
                VALUES
                    (:UserID, :RecipientID, :EntityType, :EntityID, :EntityLocation, :SharedDateTime)
            ");
        
        $params = array(
            ':UserID' => $sharing['user_id'],
            ':RecipientID' => $sharing['recipient_id'],
            ':EntityType' => $sharing['entity_type'],
            ':EntityID' => $sharing['entity_id'],
            ':EntityLocation' => $sharing['entity_location'],
            ':SharedDateTime' => gmdate('Y-m-d H:i:s', strtotime($event['sharing_date_time'])),
            );
        
        if (!$stmt->execute($params)) {
            throw new \RuntimeException('Unable to execute SQL command: ' . print_r($stmt->errorInfo(), true));
        }

        return true;
    }
}