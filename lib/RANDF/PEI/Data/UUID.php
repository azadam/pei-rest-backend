<?php

namespace RANDF\PEI\Data;

use \Respect\Validation\Validator as v;
use \RANDF\Database as Database;
use \PDO as PDO;

class UUID
{
    private $db;
    private $UserID;
    private $Statements;
    
    private $idMap;
    private $uuidMap;
    
    public function __construct($UserID)
    {
        $this->idMap = array();
        $this->uuidMap = array();
        
        v::int()->positive()->assert($UserID);
        $this->UserID = (int)$UserID;
        
        $this->db = $db = Database::getInstance('pei');
        
        $this->Statements = array();
        $this->Statements['getByID'] = $db->prepare("
            SELECT
                `NumericID` `ID`,
                HEX(`UUID`) `UUID`
            FROM
                `UserUUIDMap`
            WHERE
                `UserID` = " . $db->quote($this->UserID) . "
                AND
                `NumericID` = :NumericID
            ");
        $this->Statements['getByUUID'] = $db->prepare("
            SELECT
                `NumericID` `ID`,
                HEX(`UUID`) `UUID`
            FROM
                `UserUUIDMap`
            WHERE
                `UserID` = " . $db->quote($this->UserID) . "
                AND
                `UUID` = UNHEX(:UUID)
            ");
        $this->Statements['generateID'] = $db->prepare("
            INSERT INTO
                `UserUUIDMap`
            (`UUID`, `UserID`)
                VALUES
            (UNHEX(:UUID), " . $db->quote($this->UserID) . ")
            ");
        $this->Statements['removeUUID'] = $db->prepare("
            DELETE FROM
                `UserUUIDMap`
            WHERE
                `UserID` = " . $db->quote($this->UserID) . "
                AND
                `UUID` = UNHEX(:UUID)
            ");
        $this->Statements['removeUUIDByID'] = $db->prepare("
            DELETE FROM
                `UserUUIDMap`
            WHERE
                `UserID` = " . $db->quote($this->UserID) . "
                AND
                `NumericID` = :NumericID
            ");
        $this->Statements['createNewUUID'] = $db->prepare("
            SELECT
                UPPER(REPLACE(UUID(), '-', '')) `UUID`
            ");
    }
    
    public function getByID($ID)
    {
        if (isset($this->idMap[$ID])) {
            return $this->idMap[$ID];
        }
        
        $this->Statements['getByID']->execute(array(
            ':NumericID' => $ID
            ));
        
        if ($this->Statements['getByID']->rowCount() === 1) {
            $row = $this->Statements['getByID']->fetch(PDO::FETCH_ASSOC);
            $row['UUID'] = $this->exportUUID($row['UUID']);
            
            $this->idMap[$ID] = $row;
            $this->uuidMap[$row['UUID']] = $row;
            
            return $row;
        } else {
            throw new \RuntimeException('An unexpected ID was requested (' . $ID . ')');
        }
    }
    
    public function getByUUID($UUID)
    {
        $UUID = $this->cleanUUID($UUID);
        
        if (isset($this->uuidMap[$UUID])) {
            return $this->uuidMap[$UUID];
        }
        
        $this->Statements['getByUUID']->execute(array(
            ':UUID' => $UUID
            ));
        
        if ($this->Statements['getByUUID']->rowCount() === 1) {
            $row = $this->Statements['getByUUID']->fetch(PDO::FETCH_ASSOC);
            $row['UUID'] = $this->exportUUID($row['UUID']);
            
            $this->idMap[$row['ID']] = $row;
            $this->uuidMap[$UUID] = $row;
            
            return $row;
        } else {
            return null;
        }
    }
    
    public function generateID($UUID)
    {
        $UUID = $this->cleanUUID($UUID);
        v::string()->length(32)->assert($UUID);
        $success = $this->Statements['generateID']->execute(array(
            ':UUID' => $UUID,
            ));
        if (!$success) {
            throw new \RuntimeException('Unable to generate a new Autoincrement ID');
        }
        
        $row = array(
            'ID' => $this->db->lastInsertId(),
            'UUID' => $this->exportUUID($UUID),
            );
        
        $this->idMap[$row['ID']] = $row;
        $this->uuidMap[$row['UUID']] = $row;
        
        return $row;
    }
    
    private function cleanUUID($UUID)
    {
        // In: 68753A44-4D6F-1226-9C60-0050E4C00067
        // Out: 68753A444D6F12269C600050E4C00067
        $UUID = str_replace('-', '', $UUID);
        v::string()->length(32)->assert($UUID);
        return $UUID;
    }
    
    private function exportUUID($UUID)
    {
        // In: 68753A444D6F12269C600050E4C00067
        // Out: 68753A44-4D6F-1226-9C60-0050E4C00067
        v::string()->length(32)->assert($UUID);
        
        return substr($UUID, 0, 8) . '-' . substr($UUID, 8, 4) . '-' . substr($UUID, 12, 4) . '-' . substr($UUID, 16, 4) . '-' . substr($UUID, 20, 12);
    }
    
    public function removeUUID($UUID)
    {
        $row = $this->getByUUID($UUID);
        $this->Statements['removeUUID']->execute(array(
            ':UUID' => $this->cleanUUID($UUID),
            ));
        unset($this->idMap[$row['ID']]);
        unset($this->uuidMap[$row['UUID']]);
    }
    
    public function removeUUIDByID($ID)
    {
        v::int()->positive()->assert($ID);
        $row = $this->getByID($ID);
        $this->Statements['removeUUIDByID']->execute(array(
            ':NumericID' => $ID,
            ));
        unset($this->idMap[$row['ID']]);
        unset($this->uuidMap[$row['UUID']]);
    }
    
    public function createNewUUID()
    {
        $this->Statements['createNewUUID']->execute();
        return $this->Statements['createNewUUID']->fetchColumn();
    }
    
    
    public function getEntityReference($entityType, $UUID)
    {
            $IDPair = $this->getByUUID($UUID);
            if (isset($IDPair)) {
                return $IDPair['ID'];
            } else {
                // Create???
                $IDPair = $this->generateID($UUID);
                
                if ($entityType == 'Statuses') {
                    $stub = array(
                        'status_id' => $IDPair['ID'],
                        );
                    \RANDF\PEI\Data\Contacts::createStatus($stub);
                } elseif ($entityType == 'Invitees') {
                    $stub = array(
                        'invitee_id' => $IDPair['ID'],
                        'contact_id' => null,
                        'event_id' => null,
                        'status' => null,
                        );
                    \RANDF\PEI\Data\Events::addInvitee($stub);
                } else {
                    $id = strtolower($entityType);
                    $id = preg_replace('/s$/', '', $id);
                    /*
                    if (substr($id, -1, 1) === 's') {
                        $id = substr($id, 0, -1);
                    }
                    */
                    $id .= '_id';
                    $stub = array(
                        $id => $IDPair['ID'],
                        );
                    $et = "\\RANDF\\PEI\\Data\\$entityType";
                    $et = new $et();
                    $stub = $et::sanitize($stub);
                    
                    $et::create($this->UserID, $stub);
                }
                
                return $IDPair['ID'];
            }
    }
    
    public function cleanup()
    {
        // This is bad bad bad.
        $sql = "DELETE UserUUIDMap FROM UserUUIDMap LEFT JOIN Contact ON (Contact.ContactID=NumericID) LEFT JOIN Event ON (Event.EventID=NumericID) LEFT JOIN Interaction ON (Interaction.InteractionID=NumericID) LEFT JOIN Invitee ON (Invitee.InviteeID=NumericID) LEFT JOIN Location ON (Location.LocationID=NumericID) LEFT JOIN Notification ON (Notification.NotificationID=NumericID) LEFT JOIN Recording ON (Recording.RecordingID=NumericID) LEFT JOIN Reminder ON (Reminder.ReminderID=NumericID) LEFT JOIN Template ON (Template.TemplateID=NumericID) WHERE Contact.ContactID IS NULL AND Event.EventID IS NULL AND Interaction.InteractionID IS NULL AND Invitee.InviteeID IS NULL AND Location.LocationID IS NULL AND Notification.NotificationID IS NULL AND Recording.RecordingID IS NULL AND Reminder.ReminderID IS NULL AND Template.TemplateID IS NULL";
        // $db->query($sql);
    }
}