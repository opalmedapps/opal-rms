-- SPDX-FileCopyrightText: Copyright (C) 2023 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
--
-- SPDX-License-Identifier: AGPL-3.0-or-later

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
SET @@system_versioning_alter_history = 1;

ALTER TABLE `Patient`
    ADD COLUMN `OpalUUID` VARCHAR(36) NOT NULL DEFAULT '' COMMENT 'UUID provided only for Opal patients, and received from Opal' COLLATE 'latin1_swedish_ci' AFTER `OpalPatient`;

SET @@system_versioning_alter_history = 0;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
