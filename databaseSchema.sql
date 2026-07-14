SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
CREATE DATABASE IF NOT EXISTS `botbreakdown` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `botbreakdown`;

CREATE TABLE `addressChange` (
  `id` int NOT NULL,
  `scouterId` int NOT NULL,
  `newEmail` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `oldAddressConfirmed` tinyint(1) NOT NULL DEFAULT '0',
  `newAddressConfirmed` tinyint(1) NOT NULL DEFAULT '0',
  `oldAddressCode` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `newAddressCode` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `expireDateTime` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `blog` (
  `id` int NOT NULL,
  `slug` tinytext COLLATE utf8mb4_general_ci NOT NULL,
  `startDateTime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expireDateTime` datetime DEFAULT NULL,
  `author` tinytext COLLATE utf8mb4_general_ci NOT NULL,
  `title` tinytext COLLATE utf8mb4_general_ci NOT NULL,
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `modifiedDateTime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `dataPoint` (
  `id` int NOT NULL,
  `season` int NOT NULL,
  `name` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `dataKey` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `dataType` enum('int','count','text') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `blankValue` varchar(16) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `grouping` varchar(48) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `dataSet` enum('minimum','full') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'full',
  `modifiedDateTime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `eventData` (
  `event` varchar(12) COLLATE utf8mb4_general_ci NOT NULL,
  `eventName` varchar(64) COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `flag` (
  `id` int NOT NULL,
  `submissionId` int NOT NULL,
  `reportingScouterId` int NOT NULL,
  `flagDateTime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewNote` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `history` (
  `id` int NOT NULL,
  `sessionId` varchar(48) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `modifiedDateTime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `operation` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `scouterId` int NOT NULL,
  `data` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `recordType` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `recordId` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `pendingScouter` (
  `sessionId` varchar(48) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `name` varchar(48) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `displayName` varchar(24) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(48) COLLATE utf8mb4_general_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `totp` int NOT NULL,
  `expireDateTime` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `scouter` (
  `id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `displayName` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `isAdmin` int NOT NULL DEFAULT '0',
  `modifiedDateTime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `scouterAuth` (
  `uuid` varchar(48) COLLATE utf8mb4_general_ci NOT NULL,
  `scouterId` int NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `totp` int DEFAULT NULL,
  `expireDateTime` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `session` (
  `sessionId` varchar(48) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `ipAddress` varchar(48) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `userAgent` varchar(1024) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `modifiedDateTime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `setting` (
  `id` int NOT NULL,
  `key` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `value` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `setting` (`id`, `key`, `value`) VALUES(1, 'currentSeason', '2025');
INSERT INTO `setting` (`id`, `key`, `value`) VALUES(2, 'flagLimit', '4');
INSERT INTO `setting` (`id`, `key`, `value`) VALUES(3, 'flagThreshold', '1');
INSERT INTO `setting` (`id`, `key`, `value`) VALUES(4, 'blueAllianceApiKey', '[YourKeyHere]');
INSERT INTO `setting` (`id`, `key`, `value`) VALUES(5, 'sendGridApiKey', '[YourKeyHere]');
INSERT INTO `setting` (`id`, `key`, `value`) VALUES(6, 'firstApiAuthToken', '[YourKeyHere]');

CREATE TABLE `siteUpdate` (
  `id` int NOT NULL,
  `title` varchar(128) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Latest Update',
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `startDateTime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expireDateTime` datetime DEFAULT NULL,
  `author` tinytext COLLATE utf8mb4_general_ci,
  `modifiedDateTime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `submission` (
  `id` int NOT NULL,
  `teamMatchId` int NOT NULL,
  `scouterId` int DEFAULT NULL,
  `status` enum('Pending','Approved','Disabled') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Approved',
  `modifiedDateTime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `submissionData` (
  `id` int NOT NULL,
  `submissionId` int NOT NULL,
  `dataPointId` int NOT NULL,
  `dataValue` int DEFAULT NULL,
  `dataText` varchar(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `modifiedDateTime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `teamMatch` (
  `id` int NOT NULL,
  `event` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `level` enum('qm','f') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `match` int NOT NULL,
  `teamNumber` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `alliance` enum('Blue','Red') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `allianceResults` json DEFAULT NULL,
  `allianceResultsRetrievalDateTime` datetime DEFAULT NULL,
  `modifiedDateTime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


ALTER TABLE `addressChange`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_scouterId>scouter.Id` (`scouterId`);

ALTER TABLE `blog`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`(32)) USING BTREE;

ALTER TABLE `dataPoint`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `season-key` (`season`,`dataKey`);

ALTER TABLE `eventData`
  ADD PRIMARY KEY (`event`);

ALTER TABLE `flag`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `submissionId-reportingScouterId` (`submissionId`,`reportingScouterId`),
  ADD KEY `fk_flag.scouterId > scouter.id` (`reportingScouterId`);

ALTER TABLE `history`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `scouter`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

ALTER TABLE `session`
  ADD PRIMARY KEY (`sessionId`);

ALTER TABLE `setting`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `siteUpdate`
  ADD PRIMARY KEY (`id`),
  ADD KEY `startDateTime` (`startDateTime`);

ALTER TABLE `submission`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `match-scouter` (`teamMatchId`,`scouterId`),
  ADD KEY `fk_submission.scouterId > scouter.id` (`scouterId`);

ALTER TABLE `submissionData`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_submissionData.dataPointId > dataPoint.id` (`dataPointId`),
  ADD KEY `fk_submissionData.submissionId > teamMatch.id` (`submissionId`) USING BTREE;

ALTER TABLE `teamMatch`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `event-level-match-team` (`event`,`level`,`match`,`teamNumber`(16)) USING BTREE;


ALTER TABLE `addressChange`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `blog`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `dataPoint`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `flag`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `scouter`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `setting`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

ALTER TABLE `siteUpdate`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `submission`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `submissionData`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `teamMatch`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;


ALTER TABLE `addressChange`
  ADD CONSTRAINT `fk_scouterId>scouter.Id` FOREIGN KEY (`scouterId`) REFERENCES `scouter` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `flag`
  ADD CONSTRAINT `fk_flag.scouterId > scouter.id` FOREIGN KEY (`reportingScouterId`) REFERENCES `scouter` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_flag.submissionId > submission.id` FOREIGN KEY (`submissionId`) REFERENCES `submission` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `submission`
  ADD CONSTRAINT `fk_submission.scouterId > scouter.id` FOREIGN KEY (`scouterId`) REFERENCES `scouter` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_submission.teamMatchId > teamMatch.id` FOREIGN KEY (`teamMatchId`) REFERENCES `teamMatch` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `submissionData`
  ADD CONSTRAINT `fk_submissionData.dataPointId > dataPoint.id` FOREIGN KEY (`dataPointId`) REFERENCES `dataPoint` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_submissionData.submissionId > submission.id` FOREIGN KEY (`submissionId`) REFERENCES `submission` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;