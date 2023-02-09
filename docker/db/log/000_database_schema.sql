/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

CREATE TABLE IF NOT EXISTS `CronLog` (
  `ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `ReStartTime` datetime DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `KioskLog` (
  `KioskLogId` int(11) NOT NULL AUTO_INCREMENT,
  `Timestamp` datetime NOT NULL DEFAULT current_timestamp(),
  `KioskInput` varchar(50) DEFAULT NULL,
  `KioskLocation` varchar(50) NOT NULL,
  `PatientDestination` varchar(100) DEFAULT NULL,
  `CenterImage` varchar(100) DEFAULT NULL,
  `ArrowDirection` varchar(50) DEFAULT NULL,
  `DisplayMessage` text DEFAULT NULL,
  PRIMARY KEY (`KioskLogId`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `SmsLog` (
  `SmsLogSer` int(11) NOT NULL AUTO_INCREMENT,
  `SmsTimestamp` datetime NOT NULL,
  `ProcessedTimestamp` datetime NOT NULL DEFAULT current_timestamp(),
  `Result` text NOT NULL DEFAULT '',
  `Action` enum('SENT','RECEIVED') NOT NULL,
  `Service` varchar(50) NOT NULL,
  `MessageId` varchar(50) NOT NULL,
  `ServicePhoneNumber` varchar(50) NOT NULL,
  `ClientPhoneNumber` varchar(50) NOT NULL,
  `Message` text NOT NULL,
  PRIMARY KEY (`SmsLogSer`) USING BTREE,
  UNIQUE KEY `Service` (`Service`,`MessageId`) USING BTREE,
  KEY `SmsTimestamp` (`SmsTimestamp`) USING BTREE,
  KEY `ProcessedTimestamp` (`ProcessedTimestamp`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `VirtualWaitingRoomLog` (
  `VirtualWaitingRoomLogSer` int(11) NOT NULL AUTO_INCREMENT,
  `DateTime` datetime NOT NULL,
  `FileName` varchar(255) NOT NULL,
  `Identifier` varchar(255) NOT NULL,
  `Type` varchar(255) NOT NULL,
  `Message` text NOT NULL,
  `LastUpdated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`VirtualWaitingRoomLogSer`),
  KEY `Date` (`DateTime`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
