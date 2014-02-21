-- MySQL dump 10.13  Distrib 5.1.61, for redhat-linux-gnu (x86_64)
--
-- Host: localhost    Database: pei
-- ------------------------------------------------------
-- Server version	5.1.61

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `Contact`
--

DROP TABLE IF EXISTS `Contact`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Contact` (
  `ContactID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `UserID` int(11) unsigned NOT NULL,
  `FirstName` varchar(45) NOT NULL,
  `LastName` varchar(45) NOT NULL,
  `LocationID` int(10) unsigned DEFAULT NULL,
  `Commitment` varchar(45) NOT NULL,
  `IsNew` tinyint(4) NOT NULL DEFAULT '0',
  `Source` varchar(45) NOT NULL,
  `Photo` varchar(45) NOT NULL,
  `CreatedDateTime` datetime NOT NULL,
  `UpdatedDateTime` datetime NOT NULL,
  `Interest` varchar(50) DEFAULT NULL,
  `Engage` varchar(50) DEFAULT NULL,
  `SortName` varchar(50) DEFAULT NULL,
  `CurrentStatusID` int(10) unsigned DEFAULT NULL,
  `ExternalSourceIdentifier` varchar(255) DEFAULT NULL,
  `Notes` text,
  PRIMARY KEY (`ContactID`),
  KEY `UserID` (`UserID`),
  KEY `LocationID` (`LocationID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ContactEmailAddress`
--

DROP TABLE IF EXISTS `ContactEmailAddress`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ContactEmailAddress` (
  `EmailID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ContactID` int(10) unsigned NOT NULL,
  `EmailAddress` varchar(255) NOT NULL,
  `ContactMethodQualifierID` int(10) unsigned NOT NULL,
  PRIMARY KEY (`EmailID`),
  KEY `ContactMethodQualifierID` (`ContactMethodQualifierID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ContactMethodQualifier`
--

DROP TABLE IF EXISTS `ContactMethodQualifier`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ContactMethodQualifier` (
  `ContactMethodQualifierID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Name` varchar(25) NOT NULL,
  PRIMARY KEY (`ContactMethodQualifierID`),
  UNIQUE KEY `Name` (`Name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ContactPhoneNumber`
--

DROP TABLE IF EXISTS `ContactPhoneNumber`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ContactPhoneNumber` (
  `NumberID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ContactID` int(10) unsigned NOT NULL,
  `PhoneNumber` varchar(25) NOT NULL,
  `Extension` varchar(25) NOT NULL,
  `ContactMethodQualifierID` int(10) unsigned NOT NULL,
  PRIMARY KEY (`NumberID`),
  KEY `ContactMethodQualifierID` (`ContactMethodQualifierID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Device`
--

DROP TABLE IF EXISTS `Device`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Device` (
  `DeviceID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `DeviceIdentifier` blob NOT NULL,
  `LastSync` datetime NOT NULL,
  PRIMARY KEY (`DeviceID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Event`
--

DROP TABLE IF EXISTS `Event`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Event` (
  `EventID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `EventType` varchar(100) DEFAULT NULL,
  `AssociatedEventID` int(10) unsigned DEFAULT NULL,
  `UserID` int(10) unsigned NOT NULL,
  `EventDateTime` datetime DEFAULT NULL,
  `EventEndDateTime` datetime DEFAULT NULL,
  `CreatedDateTime` datetime DEFAULT NULL,
  `UpdatedDateTime` datetime DEFAULT NULL,
  `Name` varchar(250) DEFAULT NULL,
  `LocationID` int(10) unsigned DEFAULT NULL,
  `Status` varchar(25) DEFAULT NULL,
  `ContactMode` varchar(25) DEFAULT NULL,
  `Notes` text,
  PRIMARY KEY (`EventID`),
  KEY `LocationID` (`LocationID`),
  KEY `UserID` (`UserID`),
  KEY `AssociatedEventID` (`AssociatedEventID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Interaction`
--

DROP TABLE IF EXISTS `Interaction`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Interaction` (
  `InteractionID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ContactID` int(10) unsigned DEFAULT NULL,
  `Method` varchar(30) DEFAULT NULL,
  `Outcome` varchar(30) DEFAULT NULL,
  `LocationID` int(10) unsigned DEFAULT NULL,
  `TemplateID` int(10) unsigned DEFAULT NULL,
  `EventID` int(10) unsigned DEFAULT NULL,
  `InteractionDateTime` datetime NOT NULL,
  `Notes` text,
  PRIMARY KEY (`InteractionID`),
  KEY `ContactID` (`ContactID`),
  KEY `LocationID` (`LocationID`),
  KEY `TemplateID` (`TemplateID`),
  KEY `AssociatedEventID` (`EventID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Invitee`
--

DROP TABLE IF EXISTS `Invitee`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Invitee` (
  `InviteeID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ContactID` int(10) unsigned DEFAULT NULL,
  `EventID` int(10) unsigned DEFAULT NULL,
  `Status` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`InviteeID`),
  KEY `ContactID` (`ContactID`),
  KEY `EventID` (`EventID`),
  KEY `StatusID` (`Status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Location`
--

DROP TABLE IF EXISTS `Location`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Location` (
  `LocationID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `OwnerUserID` int(10) unsigned NOT NULL,
  `Name` varchar(250) NOT NULL,
  `Address1` varchar(100) NOT NULL,
  `Address2` varchar(100) NOT NULL,
  `City` varchar(100) NOT NULL,
  `State` varchar(100) NOT NULL,
  `Zipcode` varchar(10) NOT NULL,
  PRIMARY KEY (`LocationID`),
  KEY `UserID` (`OwnerUserID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Notification`
--

DROP TABLE IF EXISTS `Notification`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Notification` (
  `NotificationID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `UserID` int(10) unsigned NOT NULL,
  `IsRead` tinyint(3) unsigned NOT NULL,
  `Contents` text NOT NULL,
  `NotificationDateTime` datetime NOT NULL,
  PRIMARY KEY (`NotificationID`),
  KEY `UserID` (`UserID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Recording`
--

DROP TABLE IF EXISTS `Recording`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Recording` (
  `RecordingID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `UserID` int(10) unsigned NOT NULL,
  `ContactID` int(10) unsigned DEFAULT NULL,
  `Name` varchar(100) DEFAULT NULL,
  `AuthorName` varchar(100) DEFAULT NULL,
  `RecordedDateTime` datetime DEFAULT NULL,
  `Notes` text,
  `Category` int(10) unsigned DEFAULT NULL,
  `IsCorporate` tinyint(4) NOT NULL DEFAULT '0',
  `RunningTime` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`RecordingID`),
  KEY `UserID` (`UserID`),
  KEY `ContactID` (`ContactID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Reminder`
--

DROP TABLE IF EXISTS `Reminder`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Reminder` (
  `ReminderID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `EventID` int(10) unsigned NOT NULL,
  `LeadTimeInMinutes` int(10) unsigned NOT NULL,
  PRIMARY KEY (`ReminderID`),
  KEY `EventID` (`EventID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `SearchHistory`
--

DROP TABLE IF EXISTS `SearchHistory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `SearchHistory` (
  `SearchHistoryID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `UserID` int(10) unsigned NOT NULL,
  `SearchDate` datetime NOT NULL,
  `Keywords` varchar(255) NOT NULL,
  PRIMARY KEY (`SearchHistoryID`),
  KEY `UserID` (`UserID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Status`
--

DROP TABLE IF EXISTS `Status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Status` (
  `StatusID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ContactID` int(10) unsigned NOT NULL,
  `StatusName` varchar(255) CHARACTER SET latin1 NOT NULL,
  `StarRating` tinyint(4) unsigned DEFAULT NULL,
  `UpdatedDateTime` datetime NOT NULL,
  PRIMARY KEY (`StatusID`),
  KEY `ContactID` (`ContactID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Template`
--

DROP TABLE IF EXISTS `Template`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Template` (
  `TemplateID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `UserID` int(10) unsigned NOT NULL,
  `AuthorName` varchar(100) DEFAULT NULL,
  `IsCorporate` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `Name` varchar(100) DEFAULT NULL,
  `CreatedDateTime` datetime DEFAULT NULL,
  `LastEditedDateTime` datetime DEFAULT NULL,
  `Category` int(10) unsigned DEFAULT NULL,
  `Type` varchar(50) DEFAULT NULL,
  `Body` text,
  `Notes` text,
  PRIMARY KEY (`TemplateID`),
  KEY `UserID` (`UserID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `User`
--

DROP TABLE IF EXISTS `User`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `User` (
  `UserID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `PulseUsername` varchar(255) NOT NULL,
  `AccountID` int(10) unsigned NOT NULL,
  `SponsorID` int(10) unsigned NOT NULL,
  `EnrollerID` int(10) unsigned NOT NULL,
  `NSCoreID` int(10) unsigned NOT NULL DEFAULT '0',
  `FirstAccess` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `OptOutDate` datetime DEFAULT NULL,
  `GetAccountResults` mediumtext NOT NULL,
  `TOSAcceptanceDate` datetime DEFAULT NULL,
  PRIMARY KEY (`UserID`),
  UNIQUE KEY `PulseUsername` (`PulseUsername`),
  UNIQUE KEY `AccountID_2` (`AccountID`),
  KEY `AccountID` (`AccountID`,`SponsorID`,`EnrollerID`),
  KEY `UserID` (`UserID`,`SponsorID`,`OptOutDate`),
  KEY `NSCoreID` (`NSCoreID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `UserDevice`
--

DROP TABLE IF EXISTS `UserDevice`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `UserDevice` (
  `UserID` int(10) unsigned NOT NULL,
  `DeviceID` int(10) unsigned NOT NULL,
  PRIMARY KEY (`UserID`,`DeviceID`),
  KEY `UserDevice_ibfk_2` (`DeviceID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `UserSettings`
--

DROP TABLE IF EXISTS `UserSettings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `UserSettings` (
  `UserID` int(10) unsigned NOT NULL,
  `ReceiveProgressNotifications` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `NotifyMeBusinessMeetings` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `NotifyMeCorporateEvents` tinyint(3) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`UserID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `UserToken`
--

DROP TABLE IF EXISTS `UserToken`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `UserToken` (
  `UserID` int(10) unsigned NOT NULL,
  `Token` varchar(255) NOT NULL,
  `TokenLastUsed` datetime NOT NULL,
  `IsActive` tinyint(4) NOT NULL,
  `DeviceIdentifier` text NOT NULL,
  `PulseVersion` varchar(32) DEFAULT NULL,
  UNIQUE KEY `UserID` (`UserID`,`Token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `UserUUIDMap`
--

DROP TABLE IF EXISTS `UserUUIDMap`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `UserUUIDMap` (
  `NumericID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `UUID` binary(16) NOT NULL,
  `UserID` int(11) unsigned NOT NULL,
  PRIMARY KEY (`NumericID`),
  UNIQUE KEY `UUID` (`UUID`,`UserID`),
  KEY `NumericID` (`NumericID`,`UUID`,`UserID`),
  KEY `UserID` (`UserID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary table structure for view `tmp_UUID`
--

DROP TABLE IF EXISTS `tmp_UUID`;
/*!50001 DROP VIEW IF EXISTS `tmp_UUID`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE TABLE `tmp_UUID` (
  `UserID` int(11) unsigned,
  `NumericID` int(10) unsigned,
  `PlainUUID` varchar(32),
  `UUID` varchar(36)
) ENGINE=MyISAM */;
SET character_set_client = @saved_cs_client;

--
-- Final view structure for view `tmp_UUID`
--

/*!50001 DROP TABLE IF EXISTS `tmp_UUID`*/;
/*!50001 DROP VIEW IF EXISTS `tmp_UUID`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = latin1 */;
/*!50001 SET character_set_results     = latin1 */;
/*!50001 SET collation_connection      = latin1_swedish_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `tmp_UUID` AS select `UserUUIDMap`.`UserID` AS `UserID`,`UserUUIDMap`.`NumericID` AS `NumericID`,hex(`UserUUIDMap`.`UUID`) AS `PlainUUID`,concat(substr(hex(`UserUUIDMap`.`UUID`),1,8),'-',substr(hex(`UserUUIDMap`.`UUID`),9,4),'-',substr(hex(`UserUUIDMap`.`UUID`),13,4),'-',substr(hex(`UserUUIDMap`.`UUID`),17,4),'-',substr(hex(`UserUUIDMap`.`UUID`),21,12)) AS `UUID` from `UserUUIDMap` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2012-12-11  0:11:46
