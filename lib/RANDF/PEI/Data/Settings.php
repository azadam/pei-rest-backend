<?php

namespace RANDF\PEI\Data;

use \Respect\Validation\Validator as v;
use \RANDF\Database as Database;
use \PDO as PDO;

class Settings
{
    private static function getFieldMap()
    {
        return array(
            'receive_progress_notifications' => 'ReceiveProgressNotifications',
            'notify_about_upcoming_business_meetings' => 'NotifyMeBusinessMeetings',
            'notify_about_upcoming_corporate_events' => 'NotifyMeCorporateEvents',
            );
    }
    
    public static function getUserSettings($UserID)
    {
        v::int()->positive()->assert($UserID);
        $db = Database::getInstance('pei');
        
        $stmt = $db->query("
            SELECT
                `ReceiveProgressNotifications` `receive_progress_notifications`,
                `NotifyMeBusinessMeetings` `notify_about_upcoming_business_meetings`,
                `NotifyMeCorporateEvents` `notify_about_upcoming_corporate_events`
            FROM
                `UserSettings`
            WHERE
                `UserID` = " . $db->quote($UserID));
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($settings) {
            foreach ($settings as &$setting) {
                if (is_numeric($setting)) {
                    $setting = (bool)$setting;
                }
            }
            return $settings;
        } else {
            $db->query("
                INSERT INTO
                    `UserSettings`
                (`UserID`)
                    VALUES
                (" . $db->quote($UserID) . ")");
            
            return self::getUserSettings($UserID);
        }
    }
    
    public static function setUserSettings($UserID, $settings)
    {
        v::int()->positive()->assert($UserID);
        $db = Database::getInstance('pei');
        
        $fieldMap = self::getFieldMap();
        $newSettings = self::getUserSettings($UserID);
        foreach ($newSettings as $k => $v) {
            $newSettings[':' . $fieldMap[$k]] = $v;
            unset($newSettings[$k]);
        }
        
        foreach (array_keys($fieldMap) as $field) {
            if (isset($settings[$field])) {
                v::in('true false')->assert($settings[$field]);
                $newSettings[':' . $fieldMap[$field]] = ($settings[$field] == 'true' ? '1' : '0');
            }
        }
        
        $newSettings[':UserID'] = $UserID;
        
        $sql = "
            UPDATE
                `UserSettings`
            SET
                `ReceiveProgressNotifications` = :ReceiveProgressNotifications,
                `NotifyMeBusinessMeetings` = :NotifyMeBusinessMeetings,
                `NotifyMeCorporateEvents` = :NotifyMeCorporateEvents
            WHERE
                `UserID` = :UserID
        ";
        $stmt = $db->prepare($sql);
        
        return (bool)$stmt->execute($newSettings);
    }
}