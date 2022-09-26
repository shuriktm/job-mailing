/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

CREATE TABLE IF NOT EXISTS `users` (
    `email` varchar(254) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `username` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `validts` int NOT NULL DEFAULT '0',
    `confirmed` tinyint(1) NOT NULL DEFAULT '0',
    `notified` tinyint(1) NOT NULL DEFAULT '0',
    PRIMARY KEY (`email`) USING BTREE,
    UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `emails` (
    `email` varchar(254) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `checked` tinyint(1) NOT NULL DEFAULT '0',
    `valid` tinyint(1) NOT NULL DEFAULT '0',
    PRIMARY KEY (`email`),
    CONSTRAINT `emails_email` FOREIGN KEY (`email`) REFERENCES `users` (`email`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `queue_check` (
    `email` varchar(254) COLLATE utf8mb4_unicode_ci NOT NULL,
    `queuets` int DEFAULT NULL,
    `processts` int DEFAULT NULL,
    PRIMARY KEY (`email`),
    CONSTRAINT `queue_check_email` FOREIGN KEY (`email`) REFERENCES `emails` (`email`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `queue_send` (
    `email` varchar(254) COLLATE utf8mb4_unicode_ci NOT NULL,
    `queuets` int DEFAULT NULL,
    `processts` int DEFAULT NULL,
    PRIMARY KEY (`email`),
    CONSTRAINT `queue_send_email` FOREIGN KEY (`email`) REFERENCES `emails` (`email`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
