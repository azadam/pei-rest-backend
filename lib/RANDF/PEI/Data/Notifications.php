<?php

namespace RANDF\PEI\Data;

use \Respect\Validation\Validator as v;
use \RANDF\Database as Database;
use \PDO as PDO;

/**
* Notifications
*
* @uses \PDO
* @uses \RANDF\Database
* @uses \Respect\Validation\Validator
*
* @package PEI
* @author Adam Hooker <adamh@rodanandfields.com>
*/
class Notifications
{
    /**
     * Update a notification's read status
     * 
     * @param int $notificationID
     * @param bool $readStatus
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     *
     * @return bool
     */
    public static function markReadStatus($notificationID, $readStatus)
    {
        v::int()->positive()->setName('notificationID')->assert($notificationID);
        v::oneOf(v::bool(), v::int())->setName('readStatus')->assert($readStatus);

        $db = Database::getInstance('pei');

        $sql = "
            UPDATE
                `Notification`
            SET
                `IsRead` = :IsRead
            WHERE
                `NotificationID` = :NotificationID
        ";

        $stmt = $db->prepare($sql);
        $params = array(
            ':IsRead' => ($readStatus ? 1 : 0),
            ':NotificationID' => $notificationID,
            );
        if (!$stmt->execute($params)) {
            throw new \RuntimeException('Unable to execute SQL command');
        }

        return true;
    }

    /**
     * Get all notifications belonging to the given UserID
     * 
     * @param int $userID
     * @param bool $onlyUnread optional
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     *
     * @return array
     */
    public static function getAll($userID, $onlyUnread=false)
    {
        v::int()->positive()->assert($userID);

        $db = Database::getInstance('pei');

        $sql = "
            SELECT
                `NotificationID` `notification_id`,
                `IsRead` `is_read`,
                `Contents` `contents`,
                UNIX_TIMESTAMP(`NotificationDateTime`) `notification_date_time`
            FROM
                `Notification`
            WHERE
                `UserID` = :UserID
        ";
        if ($onlyUnread) {
            $sql .= "
                AND
                !`IsRead`
            ";
        }

        $stmt = $db->prepare($sql);
        $params = array(
            ':UserID' => $userID,
            );
        if (!$stmt->execute($params)) {
            throw new \RuntimeException('Unable to execute SQL command');
        }

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $results;
    }

    /**
     * Determines whether the given user owns the given notification
     * 
     * @param int $userID
     * @param int $notificationID
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     *
     * @return bool
     */
    public static function userOwnsNotification($userID, $notificationID)
    {
        v::int()->positive()->assert($userID);
        v::int()->positive()->assert($notificationID);

        $db = Database::getInstance('pei');

        $sql = "
            SELECT
                COUNT(*) `Count`
            FROM
                `Notification`
            WHERE
                `NotificationID` = :NotificationID
                AND
                `UserID` = :UserID
        ";

        $stmt = $db->prepare($sql);
        $params = array(
            ':UserID' => $userID,
            ':NotificationID' => $notificationID,
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
     * Delete the specified notification
     * 
     * @param int $notificationID
     *
     * @access public
     * @static
     *
     * @throws \RuntimeException
     *
     * @return bool
     */
    public static function delete($notificationID)
    {
        v::int()->positive()->assert($notificationID);

        $db = Database::getInstance('pei');

        $sql = "
            DELETE
                FROM `Notification`
            WHERE
                `NotificationID` = :NotificationID
        ";

        $stmt = $db->prepare($sql);
        $params = array(
            ':NotificationID' => $notificationID,
            );
        if (!$stmt->execute($params)) {
            throw new \RuntimeException('Unable to execute SQL command');
        }

        if ($stmt->rowCount() == 1) {
            return true;
        } else {
            return false;
        }
    }
    
    public static function _createTestNotifications($userID)
    {
        $db = Database::getInstance('pei');
        
        self::createNotification($userID, "This is a test notification!");
        self::createNotification($userID, "This is another test notification!");
        self::createNotification($userID, "This is one more test notification!");
    }
    
    public static function createSharingNotification($senderUserID, $recipientUserID, $sharedDocumentType)
    {
        v::int()->positive()->assert($senderUserID);
        v::int()->positive()->assert($recipientUserID);
        
        $sender = \RANDF\PEI\Authentication::getProfileInfoFromUserId($senderUserID);
        $notificationContents = $sender['FullName'] . ' shared a new ' . $sharedDocumentType . ' with you via email.';
        return self::createNotification($recipientUserID, $notificationContents);
    }
    
    public static function createKudosNotification($senderUserID, $recipientUserID, $message)
    {
        v::int()->positive()->assert($senderUserID);
        v::int()->positive()->assert($recipientUserID);
        v::string()->length(1, 70)->assert($message);
        
        $sender = \RANDF\PEI\Authentication::getProfileInfoFromUserId($senderUserID);
        $notificationContents = $sender['FullName'] . ' says, "' . $message . '"';
        return self::createNotification($recipientUserID, $notificationContents);
    }
    
    public static function createNotification($recipientUserID, $notificationContents)
    {
        v::int()->positive()->assert($recipientUserID);
        v::string()->length(1,65500)->assert($notificationContents);
        
        $db = Database::getInstance('pei');
        
        $UUID = new \RANDF\PEI\Data\UUID($recipientUserID);
        $uuid = $UUID->createNewUUID();
        $notificationIDPair = $UUID->generateID($uuid);
        
        $sql = "
            INSERT
                INTO `Notification`
            (`NotificationID`, `UserID`, `IsRead`, `Contents`, `NotificationDateTime`)
                VALUES
            (:NotificationID, :UserID, 0, :Contents, NOW())
        ";
        
        $stmt = $db->prepare($sql);
        
        return (bool)$stmt->execute(array(
            ':NotificationID' => $notificationIDPair['ID'],
            ':UserID' => $recipientUserID,
            ':Contents' => $notificationContents,
            ));
    }
    
    public static function createNotificationIfDoesNotExist($recipientUserID, $notificationContents)
    {
        if (self::notificationAlreadyExists($recipientUserID, $notificationContents)) {
            return true;
        }
        
        return self::createNotification($recipientUserID, $notificationContents);
    }
    
    public static function notificationAlreadyExists($recipientUserID, $notificationContents)
    {
        v::int()->positive()->assert($recipientUserID);
        v::string()->length(1,65500)->assert($notificationContents);
        
        $db = Database::getInstance('pei');
        
        $notifications = self::getAll($recipientUserID);
        foreach ($notifications as &$notification) {
            if ($notification['contents'] == $notificationContents) {
                return true;
            }
        }
        return false;
    }
}
