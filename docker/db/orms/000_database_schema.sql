-- SPDX-FileCopyrightText: Copyright (C) 2023 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
--
-- SPDX-License-Identifier: AGPL-3.0-or-later

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

CREATE TABLE IF NOT EXISTS `AppointmentCode` (
  `AppointmentCodeId` int(11) NOT NULL AUTO_INCREMENT,
  `AppointmentCode` varchar(100) NOT NULL,
  `SpecialityGroupId` int(11) NOT NULL,
  `DisplayName` varchar(100) DEFAULT NULL,
  `SourceSystem` varchar(50) NOT NULL,
  `Active` tinyint(4) NOT NULL DEFAULT 1,
  `LastModified` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`AppointmentCodeId`),
  UNIQUE KEY `AppointmentCode_SpecialityGroupId` (`AppointmentCode`,`SpecialityGroupId`),
  KEY `FK_AppointmentCode_SpecialityGroup` (`SpecialityGroupId`),
  CONSTRAINT `FK_AppointmentCode_SpecialityGroup` FOREIGN KEY (`SpecialityGroupId`) REFERENCES `SpecialityGroup` (`SpecialityGroupId`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 WITH SYSTEM VERSIONING;

CREATE TABLE IF NOT EXISTS `ClinicHub` (
  `ClinicHubId` int(11) NOT NULL AUTO_INCREMENT,
  `SpecialityGroupId` int(11) NOT NULL,
  `ClinicHubName` varchar(50) NOT NULL,
  `LastUpdated` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ClinicHubId`) USING BTREE,
  KEY `FK_ClinicHub_SpecialityGroup` (`SpecialityGroupId`) USING BTREE,
  CONSTRAINT `FK_ClinicHub_SpecialityGroup` FOREIGN KEY (`SpecialityGroupId`) REFERENCES `SpecialityGroup` (`SpecialityGroupId`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 WITH SYSTEM VERSIONING;

CREATE TABLE IF NOT EXISTS `ClinicResources` (
  `ClinicResourcesSerNum` int(11) NOT NULL AUTO_INCREMENT,
  `ResourceCode` varchar(200) NOT NULL,
  `ResourceName` varchar(200) NOT NULL COMMENT 'Both Aria and Medivisit resources listed here',
  `SpecialityGroupId` int(11) NOT NULL,
  `SourceSystem` varchar(50) NOT NULL,
  `LastModified` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `Active` tinyint(4) NOT NULL DEFAULT 1 COMMENT '1 = Active / 0 = Not Active',
  PRIMARY KEY (`ClinicResourcesSerNum`),
  UNIQUE KEY `ClinicResources_SpecialityGroupId` (`ResourceCode`,`SpecialityGroupId`),
  KEY `ID_ResourceName` (`ResourceName`),
  KEY `ID_Active` (`Active`),
  KEY `FK_ClinicResources_SpecialityGroup` (`SpecialityGroupId`),
  CONSTRAINT `FK_ClinicResources_SpecialityGroup` FOREIGN KEY (`SpecialityGroupId`) REFERENCES `SpecialityGroup` (`SpecialityGroupId`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 WITH SYSTEM VERSIONING;

CREATE TABLE IF NOT EXISTS `Cron` (
  `System` varchar(20) NOT NULL,
  `LastReceivedSmsFetch` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`System`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 WITH SYSTEM VERSIONING;

DELIMITER //
CREATE EVENT `Cron_Patch` ON SCHEDULE EVERY 1 MINUTE STARTS '2020-11-04 14:12:51' ON COMPLETION PRESERVE ENABLE COMMENT 'This is temporary until Victor can deploy the fix to the cron is' DO BEGIN

-- This is temporary until Victor can deploy the fix to the cron issue

SET @wsSeconds = (Select UNIX_TIMESTAMP(now()) - UNIX_TIMESTAMP(LastReceivedSmsFetch) as seconds from Cron);

if (@wsSeconds > 60) then

	Update Cron set LastReceivedSmsFetch = now() where `System` = 'ORMS';

	insert into OrmsLog.CronLog (ReStartTime)
	(Select now());

end if;

END//
DELIMITER ;

CREATE TABLE IF NOT EXISTS `DiagnosisChapter` (
  `DiagnosisChapterId` int(11) NOT NULL AUTO_INCREMENT,
  `Chapter` varchar(20) NOT NULL,
  `Description` text NOT NULL,
  PRIMARY KEY (`DiagnosisChapterId`) USING BTREE,
  UNIQUE KEY `Chapter` (`Chapter`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 WITH SYSTEM VERSIONING;

CREATE TABLE IF NOT EXISTS `DiagnosisCode` (
  `DiagnosisCodeId` int(11) NOT NULL AUTO_INCREMENT,
  `DiagnosisChapterId` int(11) NOT NULL,
  `Code` varchar(20) NOT NULL,
  `Category` text NOT NULL,
  `Description` text NOT NULL,
  PRIMARY KEY (`DiagnosisCodeId`) USING BTREE,
  UNIQUE KEY `Code` (`Code`) USING BTREE,
  KEY `FK_DiagnosisCode_DiagnosisChapter` (`DiagnosisChapterId`) USING BTREE,
  CONSTRAINT `FK_DiagnosisCode_DiagnosisChapter` FOREIGN KEY (`DiagnosisChapterId`) REFERENCES `DiagnosisChapter` (`DiagnosisChapterId`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 WITH SYSTEM VERSIONING;

CREATE TABLE IF NOT EXISTS `DiagnosisSubcode` (
  `DiagnosisSubcodeId` int(11) NOT NULL AUTO_INCREMENT,
  `DiagnosisCodeId` int(11) NOT NULL,
  `Subcode` varchar(20) NOT NULL,
  `Description` text NOT NULL,
  PRIMARY KEY (`DiagnosisSubcodeId`) USING BTREE,
  UNIQUE KEY `Subcode` (`Subcode`) USING BTREE,
  KEY `FK_DiagnosisSubcode_DiagnosisCode` (`DiagnosisCodeId`) USING BTREE,
  CONSTRAINT `FK_DiagnosisSubcode_DiagnosisCode` FOREIGN KEY (`DiagnosisCodeId`) REFERENCES `DiagnosisCode` (`DiagnosisCodeId`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 WITH SYSTEM VERSIONING;

CREATE TABLE IF NOT EXISTS `ExamRoom` (
  `AriaVenueId` varchar(250) NOT NULL,
  `ClinicHubId` int(11) NOT NULL,
  `ScreenDisplayName` varchar(100) NOT NULL,
  `VenueEN` varchar(100) NOT NULL DEFAULT '',
  `VenueFR` varchar(100) NOT NULL DEFAULT '',
  `ExamRoomSerNum` int(11) NOT NULL AUTO_INCREMENT,
  `IntermediateVenueSerNum` int(11) DEFAULT NULL,
  `PositionOrder` smallint(6) DEFAULT NULL,
  PRIMARY KEY (`ExamRoomSerNum`),
  UNIQUE KEY `AriaVenueId` (`AriaVenueId`),
  KEY `FK_ExamRoom_ClinicHub` (`ClinicHubId`),
  CONSTRAINT `FK_ExamRoom_ClinicHub` FOREIGN KEY (`ClinicHubId`) REFERENCES `ClinicHub` (`ClinicHubId`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 WITH SYSTEM VERSIONING;

CREATE TABLE IF NOT EXISTS `Hospital` (
  `HospitalId` int(11) NOT NULL AUTO_INCREMENT,
  `HospitalCode` varchar(50) NOT NULL,
  `HospitalName` varchar(100) NOT NULL,
  `Format` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`HospitalId`) USING BTREE,
  UNIQUE KEY `HospitalCode` (`HospitalCode`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 WITH SYSTEM VERSIONING;

CREATE TABLE IF NOT EXISTS `Insurance` (
  `InsuranceId` int(11) NOT NULL AUTO_INCREMENT,
  `InsuranceCode` varchar(50) NOT NULL,
  `InsuranceName` varchar(100) NOT NULL,
  `Format` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`InsuranceId`) USING BTREE,
  UNIQUE KEY `InsuranceCode` (`InsuranceCode`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 WITH SYSTEM VERSIONING;

CREATE TABLE IF NOT EXISTS `IntermediateVenue` (
  `IntermediateVenueSerNum` int(11) NOT NULL AUTO_INCREMENT,
  `AriaVenueId` varchar(250) NOT NULL,
  `ClinicHubId` int(11) NOT NULL,
  `ScreenDisplayName` varchar(100) NOT NULL,
  `VenueEN` varchar(100) NOT NULL,
  `VenueFR` varchar(100) NOT NULL,
  PRIMARY KEY (`IntermediateVenueSerNum`),
  KEY `FK_IntermediateVenue_ClinicHub` (`ClinicHubId`),
  CONSTRAINT `FK_IntermediateVenue_ClinicHub` FOREIGN KEY (`ClinicHubId`) REFERENCES `ClinicHub` (`ClinicHubId`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 WITH SYSTEM VERSIONING;

CREATE TABLE IF NOT EXISTS `MediVisitAppointmentList` (
  `PatientSerNum` int(10) NOT NULL,
  `ClinicResourcesSerNum` int(10) NOT NULL,
  `ScheduledDateTime` datetime NOT NULL,
  `ScheduledDate` date NOT NULL,
  `ScheduledTime` time NOT NULL,
  `AppointmentReminderSent` tinyint(1) NOT NULL DEFAULT 0,
  `AppointmentCodeId` int(11) NOT NULL,
  `AppointId` varchar(100) NOT NULL COMMENT 'From Interface Engine',
  `AppointSys` varchar(50) NOT NULL,
  `Status` enum('Open','Cancelled','Completed','Deleted') NOT NULL,
  `MedivisitStatus` text DEFAULT NULL,
  `CreationDate` datetime NOT NULL,
  `AppointmentSerNum` int(11) NOT NULL AUTO_INCREMENT,
  `LastUpdated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `LastUpdatedUserIP` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`AppointmentSerNum`),
  UNIQUE KEY `MedivisitAppointId` (`AppointId`,`AppointSys`) USING BTREE,
  KEY `ID_PatientSerNum` (`PatientSerNum`),
  KEY `ID_ScheduledDateTime` (`ScheduledDateTime`),
  KEY `ID_Status` (`Status`),
  KEY `ID_ScheduledDate` (`ScheduledDate`),
  KEY `AppointSys` (`AppointSys`),
  KEY `FK_MediVisitAppointmentList_ClinicResources` (`ClinicResourcesSerNum`),
  KEY `FK_MediVisitAppointmentList_AppointmentCode` (`AppointmentCodeId`),
  CONSTRAINT `FK_MediVisitAppointmentList_AppointmentCode` FOREIGN KEY (`AppointmentCodeId`) REFERENCES `AppointmentCode` (`AppointmentCodeId`),
  CONSTRAINT `FK_MediVisitAppointmentList_ClinicResources` FOREIGN KEY (`ClinicResourcesSerNum`) REFERENCES `ClinicResources` (`ClinicResourcesSerNum`),
  CONSTRAINT `FK_MediVisitAppointmentList_Patient` FOREIGN KEY (`PatientSerNum`) REFERENCES `Patient` (`PatientSerNum`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Appointment list to be read in daily from Medivist schedule, as provided by Ngoc' WITH SYSTEM VERSIONING;

CREATE TABLE IF NOT EXISTS `Patient` (
  `PatientSerNum` int(10) NOT NULL AUTO_INCREMENT,
  `LastName` varchar(50) NOT NULL,
  `FirstName` varchar(50) NOT NULL,
  `DateOfBirth` datetime NOT NULL,
  `Sex` varchar(25) NOT NULL,
  `SMSAlertNum` varchar(11) DEFAULT NULL,
  `SMSSignupDate` datetime DEFAULT NULL,
  `OpalPatient` tinyint(1) NOT NULL DEFAULT 0,
  `LanguagePreference` enum('English','French') DEFAULT NULL,
  `LastUpdated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `SMSLastUpdated` datetime DEFAULT NULL,
  PRIMARY KEY (`PatientSerNum`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 WITH SYSTEM VERSIONING;

CREATE TABLE IF NOT EXISTS `PatientDiagnosis` (
  `PatientDiagnosisId` int(11) NOT NULL AUTO_INCREMENT,
  `PatientSerNum` int(11) NOT NULL,
  `RecordedMrn` varchar(50) NOT NULL,
  `DiagnosisSubcodeId` int(11) NOT NULL,
  `Status` enum('Active','Deleted') NOT NULL DEFAULT 'Active',
  `DiagnosisDate` datetime NOT NULL DEFAULT current_timestamp(),
  `CreatedDate` datetime NOT NULL DEFAULT current_timestamp(),
  `LastUpdated` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `UpdatedBy` varchar(50) NOT NULL,
  PRIMARY KEY (`PatientDiagnosisId`) USING BTREE,
  KEY `FK__Patient` (`PatientSerNum`) USING BTREE,
  KEY `FK_PatientDiagnosis_DiagnosisSubcode` (`DiagnosisSubcodeId`) USING BTREE,
  CONSTRAINT `FK_PatientDiagnosis_DiagnosisSubcode` FOREIGN KEY (`DiagnosisSubcodeId`) REFERENCES `DiagnosisSubcode` (`DiagnosisSubcodeId`),
  CONSTRAINT `FK__Patient` FOREIGN KEY (`PatientSerNum`) REFERENCES `Patient` (`PatientSerNum`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 WITH SYSTEM VERSIONING;

CREATE TABLE IF NOT EXISTS `PatientHospitalIdentifier` (
  `PatientHospitalIdentifierId` int(11) NOT NULL AUTO_INCREMENT,
  `PatientId` int(11) NOT NULL,
  `HospitalId` int(11) NOT NULL,
  `MedicalRecordNumber` varchar(50) NOT NULL,
  `Active` tinyint(4) NOT NULL,
  `DateAdded` datetime NOT NULL DEFAULT current_timestamp(),
  `LastModified` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`PatientHospitalIdentifierId`) USING BTREE,
  UNIQUE KEY `HospitalId_MedicalRecordNumber` (`HospitalId`,`MedicalRecordNumber`) USING BTREE,
  KEY `FK_PatientHospitalIdentifier_Patient` (`PatientId`) USING BTREE,
  CONSTRAINT `FK_PatientHospitalIdentifier_Hospital` FOREIGN KEY (`HospitalId`) REFERENCES `Hospital` (`HospitalId`),
  CONSTRAINT `FK_PatientHospitalIdentifier_Patient` FOREIGN KEY (`PatientId`) REFERENCES `Patient` (`PatientSerNum`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 WITH SYSTEM VERSIONING;

CREATE TABLE IF NOT EXISTS `PatientInsuranceIdentifier` (
  `PatientInsuranceIdentifierId` int(11) NOT NULL AUTO_INCREMENT,
  `PatientId` int(11) NOT NULL,
  `InsuranceId` int(11) NOT NULL,
  `InsuranceNumber` varchar(50) NOT NULL,
  `ExpirationDate` datetime NOT NULL,
  `Active` tinyint(4) NOT NULL,
  `DateAdded` datetime NOT NULL DEFAULT current_timestamp(),
  `LastModified` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`PatientInsuranceIdentifierId`) USING BTREE,
  UNIQUE KEY `InsuranceId_InsuranceNumber` (`InsuranceId`,`InsuranceNumber`) USING BTREE,
  KEY `FK_PatientInsuranceIdentifier_Patient` (`PatientId`) USING BTREE,
  CONSTRAINT `FK_PatientInsuranceIdentifier_Insurance` FOREIGN KEY (`InsuranceId`) REFERENCES `Insurance` (`InsuranceId`),
  CONSTRAINT `FK_PatientInsuranceIdentifier_Patient` FOREIGN KEY (`PatientId`) REFERENCES `Patient` (`PatientSerNum`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 WITH SYSTEM VERSIONING;

CREATE TABLE IF NOT EXISTS `PatientLocation` (
  `PatientLocationSerNum` int(10) NOT NULL AUTO_INCREMENT,
  `PatientLocationRevCount` int(3) NOT NULL,
  `AppointmentSerNum` int(10) NOT NULL,
  `CheckinVenueName` varchar(50) NOT NULL,
  `ArrivalDateTime` datetime NOT NULL,
  `LastUpdated` timestamp NOT NULL DEFAULT current_timestamp(),
  `IntendedAppointmentFlag` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`PatientLocationSerNum`),
  KEY `AppointmentSerNum` (`AppointmentSerNum`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 WITH SYSTEM VERSIONING;

CREATE TABLE IF NOT EXISTS `PatientLocationMH` (
  `PatientLocationSerNum` int(10) NOT NULL AUTO_INCREMENT COMMENT 'This key comes from the PatientLocation table but it should be unique',
  `PatientLocationRevCount` int(3) NOT NULL,
  `AppointmentSerNum` int(10) NOT NULL,
  `CheckinVenueName` varchar(50) NOT NULL,
  `ArrivalDateTime` datetime NOT NULL,
  `DichargeThisLocationDateTime` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'This is effectively the LastUpdated Column',
  `IntendedAppointmentFlag` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`PatientLocationSerNum`),
  KEY `AppointmentSerNum` (`AppointmentSerNum`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 WITH SYSTEM VERSIONING;

CREATE TABLE IF NOT EXISTS `PatientMeasurement` (
  `PatientMeasurementSer` int(11) NOT NULL AUTO_INCREMENT,
  `PatientSer` int(11) NOT NULL,
  `AppointmentId` varchar(100) NOT NULL,
  `PatientId` varchar(50) NOT NULL,
  `Date` date NOT NULL,
  `Time` time NOT NULL,
  `Height` double NOT NULL,
  `Weight` double NOT NULL,
  `BSA` double NOT NULL,
  `LastUpdated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`PatientMeasurementSer`),
  KEY `FK_PatientMeasurement_Patient` (`PatientSer`),
  CONSTRAINT `FK_PatientMeasurement_Patient` FOREIGN KEY (`PatientSer`) REFERENCES `Patient` (`PatientSerNum`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 WITH SYSTEM VERSIONING;

CREATE TABLE IF NOT EXISTS `Profile` (
  `ProfileSer` int(11) NOT NULL AUTO_INCREMENT,
  `ProfileId` varchar(255) NOT NULL,
  `Category` enum('PAB','Physician','Nurse','Checkout Clerk','Pharmacy','Treatment Machine') NOT NULL,
  `SpecialityGroupId` int(11) NOT NULL,
  `LastUpdated` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ProfileSer`),
  UNIQUE KEY `ProfileId` (`ProfileId`),
  KEY `FK_Profile_SpecialityGroup` (`SpecialityGroupId`),
  CONSTRAINT `FK_Profile_SpecialityGroup` FOREIGN KEY (`SpecialityGroupId`) REFERENCES `SpecialityGroup` (`SpecialityGroupId`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 WITH SYSTEM VERSIONING;

CREATE TABLE IF NOT EXISTS `ProfileColumnDefinition` (
  `ProfileColumnDefinitionSer` int(11) NOT NULL AUTO_INCREMENT,
  `ColumnName` varchar(255) NOT NULL,
  `DisplayName` varchar(255) NOT NULL,
  `Glyphicon` varchar(255) NOT NULL,
  `Description` text NOT NULL,
  `LastUpdated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ProfileColumnDefinitionSer`),
  UNIQUE KEY `ColumnName` (`ColumnName`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 WITH SYSTEM VERSIONING;

CREATE TABLE IF NOT EXISTS `ProfileColumns` (
  `ProfileColumnSer` int(11) NOT NULL AUTO_INCREMENT,
  `ProfileSer` int(11) NOT NULL,
  `ProfileColumnDefinitionSer` int(11) NOT NULL,
  `Position` int(11) NOT NULL DEFAULT -1,
  `Active` tinyint(1) NOT NULL DEFAULT 0,
  `LastUpdated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ProfileColumnSer`),
  KEY `ProfileSer` (`ProfileSer`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 WITH SYSTEM VERSIONING;

CREATE TABLE IF NOT EXISTS `ProfileOptions` (
  `ProfileOptionSer` int(11) NOT NULL AUTO_INCREMENT,
  `ProfileSer` int(11) NOT NULL,
  `Options` varchar(255) NOT NULL,
  `Type` enum('ExamRoom','IntermediateVenue','Resource') NOT NULL,
  `LastUpdated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ProfileOptionSer`),
  KEY `ProfileSer` (`ProfileSer`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 WITH SYSTEM VERSIONING;

CREATE TABLE IF NOT EXISTS `SmsAppointment` (
  `SmsAppointmentId` int(11) NOT NULL AUTO_INCREMENT,
  `ClinicResourcesSerNum` int(11) NOT NULL,
  `AppointmentCodeId` int(11) NOT NULL,
  `SpecialityGroupId` int(11) NOT NULL,
  `SourceSystem` varchar(50) NOT NULL,
  `Type` varchar(50) DEFAULT NULL,
  `Active` tinyint(4) NOT NULL DEFAULT 0,
  `LastUpdated` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`SmsAppointmentId`),
  UNIQUE KEY `ClinicResourcesSerNum` (`ClinicResourcesSerNum`,`AppointmentCodeId`) USING BTREE,
  KEY `FK__AppointmentCode` (`AppointmentCodeId`),
  KEY `FK__Type` (`Type`),
  KEY `FK_SmsAppointment_SpecialityGroup` (`SpecialityGroupId`),
  CONSTRAINT `FK_SmsAppointment_SpecialityGroup` FOREIGN KEY (`SpecialityGroupId`) REFERENCES `SpecialityGroup` (`SpecialityGroupId`),
  CONSTRAINT `FK__AppointmentCode` FOREIGN KEY (`AppointmentCodeId`) REFERENCES `AppointmentCode` (`AppointmentCodeId`),
  CONSTRAINT `FK__ClinicResources` FOREIGN KEY (`ClinicResourcesSerNum`) REFERENCES `ClinicResources` (`ClinicResourcesSerNum`),
  CONSTRAINT `FK__Type` FOREIGN KEY (`Type`) REFERENCES `SmsMessage` (`Type`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 WITH SYSTEM VERSIONING;

CREATE TABLE IF NOT EXISTS `SmsMessage` (
  `SmsMessageId` int(11) NOT NULL AUTO_INCREMENT,
  `SpecialityGroupId` int(11) DEFAULT NULL,
  `Type` varchar(50) NOT NULL,
  `Event` varchar(50) NOT NULL,
  `Language` enum('English','French') NOT NULL,
  `Message` text NOT NULL,
  `LastUpdated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`SmsMessageId`) USING BTREE,
  UNIQUE KEY `SpecialityGroupId_Type_Event_Language` (`SpecialityGroupId`,`Type`,`Event`,`Language`),
  KEY `Type` (`Type`),
  CONSTRAINT `FK_SmsMessage_SpecialityGroup` FOREIGN KEY (`SpecialityGroupId`) REFERENCES `SpecialityGroup` (`SpecialityGroupId`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 WITH SYSTEM VERSIONING;

CREATE TABLE IF NOT EXISTS `SpecialityGroup` (
  `SpecialityGroupId` int(11) NOT NULL AUTO_INCREMENT,
  `HospitalId` int(11) NOT NULL,
  `SpecialityGroupCode` varchar(50) NOT NULL,
  `SpecialityGroupName` varchar(50) NOT NULL,
  `LastUpdated` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`SpecialityGroupId`) USING BTREE,
  UNIQUE KEY `SpecialityGroupCode` (`SpecialityGroupCode`),
  KEY `FK_SpecialityGroup_Hospital` (`HospitalId`) USING BTREE,
  CONSTRAINT `FK_SpecialityGroup_Hospital` FOREIGN KEY (`HospitalId`) REFERENCES `Hospital` (`HospitalId`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 WITH SYSTEM VERSIONING;

CREATE TABLE IF NOT EXISTS `TEMP_PatientQuestionnaireReview` (
  `PatientQuestionnaireReviewSerNum` int(11) NOT NULL AUTO_INCREMENT,
  `PatientSer` int(11) NOT NULL,
  `ReviewTimestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `User` varchar(50) NOT NULL,
  PRIMARY KEY (`PatientQuestionnaireReviewSerNum`) USING BTREE,
  KEY `PatientSer` (`PatientSer`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 WITH SYSTEM VERSIONING;

/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
