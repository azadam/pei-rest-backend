<?php

namespace RANDF\PEI\Data;

use \Respect\Validation\Validator as v;
use \RANDF\Database as Database;
use \PDO as PDO;

class Stats
{
    public static function getOptOutStatus($userID)
    {
        v::int()->positive()->assert($userID);
        $db = Database::getInstance('pei');
        
        $stmt = $db->query("SELECT COUNT(`UserID`) FROM `User` WHERE `UserID` = " . $db->quote($userID) . " AND `OptOutDate` IS NOT NULL");
        if ($stmt->fetchColumn() == '0') {
            // Not opted out
            return true;
        } else {
            // Opted out
            return false;
        }
    }
    
    public static function setOptOutStatus($userID, $shouldShare)
    {
        v::int()->positive()->assert($userID);
        v::in('true false')->assert($shouldShare);
        $db = Database::getInstance('pei');
        
        if ($shouldShare == 'true') {
            $sql = "
                UPDATE
                    `User`
                SET
                    `OptOutDate` = NULL
                WHERE
                    `UserID` = " . $db->quote($userID);
        } else {
            $sql = "
                UPDATE
                    `User`
                SET
                    `OptOutDate` = NOW()
                WHERE
                    `UserID` = " . $db->quote($userID);
        }
        
        if ($db->query($sql)) {
            return true;
        } else {
            return false;
        }
    }
    
    public static function recordUserSearch($userID, $keywords)
    {
        $db = Database::getInstance('pei');
        
        $sql = "
            INSERT
                INTO `SearchHistory`
            (`UserID`, `SearchDate`, `Keywords`)
                VALUES
            (:UserID, NOW(), :Keywords)
        ";
        $stmt = $db->prepare($sql);
        
        $stmt->execute(array(
            ':UserID' => $userID,
            ':Keywords' => $keywords,
            ));
    }
    
    public static function getReportRanges($userID)
    {
        $time = time();
        while (gmdate('l', $time) != 'Monday') {
            $time -= 86400;
        }
        
        $ranges = array(
            array(
                gmdate('m/d/Y', $time),
                gmdate('m/d/Y', $time+(86400*6)),
                ),
            );
        
        $db = Database::getInstance('pei');
        
        $sql = "
            SELECT
                UNIX_TIMESTAMP(MIN(`FirstAccess`)) `EarliestAccess`
            FROM
                `User`
            WHERE
                `SponsorID` = " . $db->quote($userID);
        $stmt = $db->query($sql);

        $row = $stmt->fetch(PDO::FETCH_OBJ);
        if ($row->EarliestAccess !== null) {
            $enddate = (int)$row->EarliestAccess;
            
            do {
                $time -= (86400*7);
                $ranges[] = array(
                    gmdate('m/d/Y', $time),
                    gmdate('m/d/Y', $time+(86400*6)),
                    );
            } while ($time > $enddate);
        }
        
        return $ranges;
    }
   
    public static function getSearchTerms($userID, $startDate, $endDate)
    {
        $istart = strtotime($startDate);
        $iend = strtotime($endDate);
        
        $db = Database::getInstance('pei');
        $sql = "
            SELECT
                `Keywords`, COUNT(*) `Count`
            FROM
                `SearchHistory`
                    INNER JOIN `User`
                        USING (`UserID`)
            WHERE
                `SponsorID` = " . $db->quote($userID) . "
                AND
                UNIX_TIMESTAMP(`SearchDate`) BETWEEN $istart AND $iend
                AND
                `OptOutDate` IS NULL
            GROUP BY
                `Keywords`
            ORDER BY
                `Keywords`
                ";
        $stmt = $db->query($sql);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public static function getNewStarts($userID, $startDate, $endDate)
    {
        $istart = strtotime($startDate);
        $iend = strtotime($endDate);
        
        $db = Database::getInstance('pei');
        $sql = "
            SELECT
                `UserID`, `AccountID`, `PulseUsername`, `GetAccountResults`
            FROM
                `User`
            WHERE
                `SponsorID` = " . $db->quote($userID) . "
                AND
                UNIX_TIMESTAMP(`FirstAccess`) BETWEEN $istart AND $iend
                AND
                `OptOutDate` IS NULL
            ORDER BY
                `PulseUsername`
                ";
        $stmt = $db->query($sql);
        
        $newUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($newUsers as &$user) {
            $user['GetAccountResults'] = json_decode($user['GetAccountResults'], true);
            if ($user['GetAccountResults'] === null) {
                $user['Name'] = $user['PulseUsername'];
            } else {
                $user['Name'] = $user['GetAccountResults']['FullName'];
            }
        }
        
        return $newUsers;
    }
    
    public static function getNewOptOuts($userID, $startDate, $endDate)
    {
        $istart = strtotime($startDate);
        $iend = strtotime($endDate);
        
        $db = Database::getInstance('pei');
        $sql = "
            SELECT
                `AccountID`, `PulseUsername`, `GetAccountResults`
            FROM
                `User`
            WHERE
                (
                `UserID` = " . $db->quote($userID) . "
                OR
                `SponsorID` = " . $db->quote($userID) . "
                )
                AND
                UNIX_TIMESTAMP(`OptOutDate`) BETWEEN $istart AND $iend
            ORDER BY
                `PulseUsername`
                ";
        $stmt = $db->query($sql);
        
        $optOutUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($optOutUsers as &$user) {
            $user['GetAccountResults'] = json_decode($user['GetAccountResults']);
            if ($user['GetAccountResults'] === null) {
                $user['Name'] = $user['PulseUsername'];
            } else {
                $user['Name'] = $user['GetAccountResults']['FullName'];
            }
        }
        
        return $optOutUsers;
    }
    
    public static function getReportData($userID, $startDate, $endDate)
    {
        $reportData = array();
        
        $istart = strtotime($startDate);
        $iend = strtotime($endDate);
        
        $db = Database::getInstance('pei');
        $sql = "
            SELECT
                `User`.`UserID`,
                `User`.`AccountID`,
                `User`.`NSCoreID`,
                `User`.`GetAccountResults`,
                `User`.`PulseUsername`,
                COUNT(DISTINCT `TotalContacts`.`ContactID`) `TotalContacts`,
                COUNT(DISTINCT `NewContacts`.`ContactID`) `NewContacts`
            FROM
                `User`
                LEFT JOIN `Contact` `TotalContacts`
                    USING (`UserID`)
                LEFT JOIN `Contact` `NewContacts`
                    ON
                        `NewContacts`.`UserID` = `User`.`UserID`
                        AND
                        UNIX_TIMESTAMP(`NewContacts`.`CreatedDateTime`) BETWEEN $istart and $iend
            WHERE
                (
                `User`.`NSCoreID` = " . $db->quote($userID) . "
                OR
                `SponsorID` = " . $db->quote($userID) . "
                )
                AND
                `User`.`OptOutDate` IS NULL
            GROUP BY
                `User`.`UserID`
        ";
        $stmt = $db->query($sql);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $reportData[$row['UserID']] = array(
                'GetAccountResults' => json_decode($row['GetAccountResults'], true),
                'PulseUsername' => $row['PulseUsername'],
                'TotalContacts' => $row['TotalContacts'],
                'NewContacts' => $row['NewContacts'],
                'UserID' => $row['UserID'],
                'AccountID' => $row['AccountID'],
                'NSCoreID' => $row['NSCoreID'],
                );
        }
        if (count($reportData) === 0) {
            return array();
        }

        $sql = "
            SELECT
                `User`.`UserID`,
                COUNT(DISTINCT `InviteeID`) `TotalInvitations`
            FROM
                `User`
                INNER JOIN `Event` USING (`UserID`)
                INNER JOIN `Invitee` USING (`EventID`)
            WHERE
                (
                `User`.`NSCoreID` = " . $db->quote($userID) . "
                OR
                `SponsorID` = " . $db->quote($userID) . "
                )
                AND
                `User`.`OptOutDate` IS NULL
            GROUP BY
                `User`.`UserID`
        ";
        $stmt = $db->query($sql);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $reportData[$row['UserID']]['TotalInvitations'] = $row['TotalInvitations'];
        }

        $sql = "
            SELECT
                `User`.`UserID`,
                `Interaction`.`Method`,
                COUNT(DISTINCT `InteractionID`) `InteractionCount`
            FROM
                `User`
                INNER JOIN `Contact` USING (`UserID`)
                INNER JOIN `Interaction` USING (`ContactID`)
            WHERE
                (
                `User`.`NSCoreID` = " . $db->quote($userID) . "
                OR
                `SponsorID` = " . $db->quote($userID) . "
                )
                AND
                `User`.`OptOutDate` IS NULL
            GROUP BY
                `UserID`, `Method`
        ";
        $stmt = $db->query($sql);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $reportData[$row['UserID']]['Interaction_' . $row['Method']] = $row['InteractionCount'];
        }

        $sql = "
            SELECT
                `User`.`UserID`,
                COUNT(DISTINCT `EventID`) `TotalEvents`
            FROM
                `User`
                INNER JOIN `Event` USING (`UserID`)
            WHERE
                (
                `User`.`NSCoreID` = " . $db->quote($userID) . "
                OR
                `SponsorID` = " . $db->quote($userID) . "
                )
                AND
                `User`.`OptOutDate` IS NULL
            GROUP BY
                `UserID`
        ";
        $stmt = $db->query($sql);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $reportData[$row['UserID']]['TotalEvents'] = $row['TotalEvents'];
        }
        
        $sql = "
            SELECT
                `User`.`UserID`,
                `Template`.`Type` `Type`,
                COUNT(DISTINCT `TemplateID`) `Count`
            FROM
                `User`
                INNER JOIN `Template` USING (`UserID`)
            WHERE
                (
                `User`.`NSCoreID` = " . $db->quote($userID) . "
                OR
                `SponsorID` = " . $db->quote($userID) . "
                )
                AND
                `User`.`OptOutDate` IS NULL
            GROUP BY
                `UserID`, `Template`.`Type`
        ";
        $stmt = $db->query($sql);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $reportData[$row['UserID']][$row['Type'] . 'Templates'] = $row['Count'];
        }
        
        $sql = "
            SELECT
                `User`.`UserID`,
                COUNT(DISTINCT `EventID`) `EventsScheduled`
            FROM
                `User`
                INNER JOIN `Event` USING (`UserID`)
            WHERE
                (
                `User`.`NSCoreID` = " . $db->quote($userID) . "
                OR
                `SponsorID` = " . $db->quote($userID) . "
                )
                AND
                `User`.`OptOutDate` IS NULL
                AND
                UNIX_TIMESTAMP(`EventDateTime`) BETWEEN $istart and $iend
            GROUP BY
                `UserID`
        ";
        $stmt = $db->query($sql);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $reportData[$row['UserID']]['EventsScheduled'] = $row['EventsScheduled'];
        }
        
        $sql = "
            SELECT
                `User`.`UserID`,
                COUNT(DISTINCT `RecordingID`) `TotalRecordings`
            FROM
                `User`
                INNER JOIN `Recording` USING (`UserID`)
            WHERE
                (
                `User`.`NSCoreID` = " . $db->quote($userID) . "
                OR
                `SponsorID` = " . $db->quote($userID) . "
                )
                AND
                `User`.`OptOutDate` IS NULL
            GROUP BY
                `UserID`
        ";
        $stmt = $db->query($sql);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $reportData[$row['UserID']]['TotalRecordings'] = $row['TotalRecordings'];
        }
        
        return $reportData;
    }
}