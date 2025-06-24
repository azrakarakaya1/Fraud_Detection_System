-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jun 23, 2025 at 02:51 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.0.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `fraud_detection_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `Alert`
--

CREATE TABLE `Alert` (
  `AlertID` varchar(20) NOT NULL,
  `AnomalyID` varchar(20) NOT NULL,
  `UserID` varchar(20) NOT NULL,
  `Timestamp` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `Alert`
--

INSERT INTO `Alert` (`AlertID`, `AnomalyID`, `UserID`, `Timestamp`) VALUES
('AL002', 'A002', 'U002', '2025-05-07 17:30:00'),
('AL003', 'A003', 'U003', '2025-05-06 15:00:00'),
('AL557147177', 'AN557147177', 'U002', '2025-06-10 15:05:47'),
('AL557868913', 'AN557868913', 'U2496', '2025-06-10 15:17:48'),
('AL557883358', 'AN557883358', 'U2496', '2025-06-10 15:18:03'),
('AL557928854', 'AN557928854', 'U2496', '2025-06-10 15:18:48'),
('AL557948242', 'AN557948242', 'U2496', '2025-06-10 15:19:08'),
('AL557965958', 'AN557965958', 'U2496', '2025-06-10 15:19:25'),
('AL557984750', 'AN557984750', 'U2496', '2025-06-10 15:19:44'),
('AL558038662', 'AN558038662', 'U2496', '2025-06-10 15:20:38'),
('AL558050109', 'AN558050109', 'U2496', '2025-06-10 15:20:50'),
('AL559602683', 'AN559602683', 'U2496', '2025-06-10 15:46:42'),
('AL777777', 'AN777777', 'U002', '2025-06-10 14:18:50'),
('AL888888', 'AN888888', 'U002', '2025-06-10 14:18:50'),
('AL999999', 'AN999999', 'U002', '2025-06-10 14:18:50');

-- --------------------------------------------------------

--
-- Table structure for table `AnomalyRecord`
--

CREATE TABLE `AnomalyRecord` (
  `AnomalyID` varchar(20) NOT NULL,
  `TransactionID` varchar(20) NOT NULL,
  `UserID` varchar(20) NOT NULL,
  `AnomalyType` varchar(50) NOT NULL,
  `DetectedDate` date NOT NULL,
  `Status` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `AnomalyRecord`
--

INSERT INTO `AnomalyRecord` (`AnomalyID`, `TransactionID`, `UserID`, `AnomalyType`, `DetectedDate`, `Status`) VALUES
('A001', 'T002', 'U001', 'High Spending', '2025-05-05', 'Pending'),
('A002', 'T005', 'U002', 'Unusual Location', '2025-05-07', 'Resolved'),
('A003', 'T008', 'U003', 'Multiple Transactions', '2025-05-06', 'Pending'),
('AN557147177', 'T472', 'U002', 'critical_amount', '2025-06-10', 'pending_review'),
('AN557868913', 'T580', 'U2496', 'suspicious_activity', '2025-06-10', 'pending_review'),
('AN557883358', 'T293', 'U2496', 'suspicious_activity', '2025-06-10', 'pending_review'),
('AN557928854', 'T742', 'U2496', 'suspicious_activity', '2025-06-10', 'pending_review'),
('AN557948242', 'T889', 'U2496', 'suspicious_activity', '2025-06-10', 'pending_review'),
('AN557965958', 'T874', 'U2496', 'suspicious_activity', '2025-06-10', 'pending_review'),
('AN557984750', 'T378', 'U2496', 'very_high_amount', '2025-06-10', 'pending_review'),
('AN558038662', 'T905', 'U2496', 'suspicious_activity', '2025-06-10', 'pending_review'),
('AN558050109', 'T804', 'U2496', 'suspicious_activity', '2025-06-10', 'pending_review'),
('AN559602683', 'T714', 'U2496', 'suspicious_activity', '2025-06-10', 'pending_review'),
('AN777777', 'T370', 'U002', 'critical_amount', '2025-06-10', 'pending_review'),
('AN888888', 'T851', 'U002', 'very_high_amount', '2025-06-10', 'pending_review'),
('AN999999', 'T820', 'U002', 'high_amount', '2025-06-10', 'pending_review');

-- --------------------------------------------------------

--
-- Table structure for table `Category`
--

CREATE TABLE `Category` (
  `CategoryID` varchar(20) NOT NULL,
  `CategoryName` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `Category`
--

INSERT INTO `Category` (`CategoryID`, `CategoryName`) VALUES
('C001', 'Food & Dining'),
('C002', 'Electronics'),
('C003', 'Cosmetics'),
('C004', 'Bills'),
('C005', 'Entertainment');

-- --------------------------------------------------------

--
-- Table structure for table `Profile`
--

CREATE TABLE `Profile` (
  `UserID` varchar(20) NOT NULL,
  `AvgMonthlySpend` decimal(10,2) DEFAULT NULL,
  `CategoryLimits` varchar(500) DEFAULT NULL,
  `TimeWindows` varchar(200) DEFAULT NULL,
  `FrequentLocations` varchar(300) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `Profile`
--

INSERT INTO `Profile` (`UserID`, `AvgMonthlySpend`, `CategoryLimits`, `TimeWindows`, `FrequentLocations`) VALUES
('U001', 40000.00, '{\"C001\":2000,\"C002\":4000,\"C003\":500,\"C004\":20000,\"C005\":500}', '09:00-23:00', 'Istanbul,Ankara'),
('U002', 20000.00, '{\"C001\":3000,\"C002\":4000,\"C003\":5000,\"C004\":5000,\"C005\":30000}', '09:00-21:00', 'Istanbul'),
('U003', 50000.00, '{\"C001\":3000,\"C002\":4000,\"C003\":5000,\"C004\":5000,\"C005\":30000}', '10:00-22:00', 'Istanbul'),
('U004', 50000.00, '{\"C001\":3000,\"C002\":4000,\"C003\":5000,\"C004\":5000,\"C005\":30000}', '09:00-22:00', 'Istanbul,Ankara'),
('U005', 60000.00, '{\"C001\":5000,\"C002\":6000,\"C003\":10000,\"C004\":4000,\"C005\":40000}', '10:00-22:00', 'Istanbul,Ankara'),
('U006', 50000.00, '{\"C001\":5000,\"C002\":10000,\"C003\":2000,\"C004\":3000,\"C005\":30000}', '10:00-23:00', 'Istanbul,Ankara'),
('U007', 40000.00, '{\"C001\":4000,\"C002\":5000,\"C003\":2000,\"C004\":90000,\"C005\":10000}', '10:00-22:00', 'Istanbul,Ankara'),
('U008', 50000.00, '{\"C001\":3000,\"C002\":4000,\"C003\":5000,\"C004\":6000,\"C005\":10000}', '10:00-22:00', 'Istanbul,Ankara'),
('U009', 20000.00, '{\"C001\":4000,\"C002\":5000,\"C003\":6000,\"C004\":10000,\"C005\":10000}', '10:00-22:00', 'Istanbul,Ankara'),
('U2496', 40000.00, '{\"C001\":5000,\"C002\":2000,\"C003\":5000,\"C004\":20000,\"C005\":5000}', '09:00-22:00', 'Istanbul,Izmir'),
('U3218', 30000.00, '{\"C001\":4000,\"C002\":5000,\"C003\":2000,\"C004\":7000,\"C005\":3000}', '10:00-22:00', 'Istanbul,Ankara'),
('U6725', 40000.00, '{\"C001\":4000,\"C002\":5000,\"C003\":1000,\"C004\":3000,\"C005\":10000}', '10:00-22:00', 'Istanbul,Ankara'),
('U7867', 40000.00, '{\"C001\":2000,\"C002\":3000,\"C003\":5000,\"C004\":10000,\"C005\":10000}', '09:00-23:00', 'Istanbul,Ankara');

-- --------------------------------------------------------

--
-- Table structure for table `Transaction`
--

CREATE TABLE `Transaction` (
  `TransactionID` varchar(20) NOT NULL,
  `UserID` varchar(20) NOT NULL,
  `CategoryID` varchar(20) NOT NULL,
  `Amount` decimal(10,2) NOT NULL,
  `Date` date NOT NULL,
  `Time` time NOT NULL,
  `Location` varchar(100) DEFAULT NULL,
  `PaymentMethod` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `Transaction`
--

INSERT INTO `Transaction` (`TransactionID`, `UserID`, `CategoryID`, `Amount`, `Date`, `Time`, `Location`, `PaymentMethod`) VALUES
('T001', 'U001', 'C001', 45.75, '2025-05-01', '12:30:00', 'Istanbul', 'Credit Card'),
('T002', 'U001', 'C002', 299.99, '2025-05-05', '15:00:00', 'Istanbul', 'Debit Card'),
('T003', 'U001', 'C003', 80.00, '2025-05-10', '18:45:00', 'Istanbul', 'Cash'),
('T004', 'U002', 'C001', 30.50, '2025-05-03', '13:10:00', 'Istanbul', 'Credit Card'),
('T005', 'U002', 'C002', 150.00, '2025-05-07', '16:00:00', 'Istanbul', 'Credit Card'),
('T006', 'U002', 'C003', 55.00, '2025-05-12', '19:30:00', 'Istanbul', 'Cash'),
('T007', 'U003', 'C001', 22.20, '2025-05-02', '11:20:00', 'Istanbul', 'Debit Card'),
('T008', 'U003', 'C002', 420.00, '2025-05-06', '14:50:00', 'Istanbul\r\n', 'Credit Card'),
('T009', 'U003', 'C003', 90.00, '2025-05-09', '17:00:00', 'Istanbul', 'Cash'),
('T200', 'U001', 'C001', 100.00, '2025-06-08', '20:24:47', 'Istanbul', 'Credit Card'),
('T206', 'U6725', 'C003', 500.00, '2025-05-30', '15:10:10', 'İstanbul', 'Credit Card'),
('T234', 'U2496', 'C001', 200.00, '2025-06-10', '15:18:17', 'Ankara', 'Credit Card'),
('T293', 'U2496', 'C002', 4000.00, '2025-06-10', '15:18:03', 'Istanbul', 'Bank Transfer'),
('T304', 'U2496', 'C001', 372.00, '2025-06-14', '18:24:45', 'Istanbul', 'Credit Card'),
('T335', 'U005', 'C001', 500.00, '2025-05-28', '15:50:23', 'Istanbul', 'Credit Card'),
('T369', 'U7867', 'C003', 20000.00, '2025-06-09', '11:31:05', 'Bursa', 'Bank Transfer'),
('T370', 'U002', 'C001', 15000.00, '2025-06-09', '11:42:19', 'İzmir', 'Credit Card'),
('T378', 'U2496', 'C005', 20000.00, '2025-06-10', '15:19:44', 'Istanbul', 'Debit Card'),
('T398', 'U6725', 'C002', 800000.00, '2025-05-30', '15:13:00', 'İstanbul', 'Credit Card'),
('T427', 'U002', 'C002', 15000.00, '2025-06-09', '10:53:32', 'Istanbul', 'Credit Card'),
('T432', 'U2496', 'C001', 209.00, '2025-06-14', '18:24:38', 'Istanbul', 'Credit Card'),
('T463', 'U6725', 'C004', 200.00, '2025-05-30', '15:11:10', 'İstanbul', 'Credit Card'),
('T472', 'U002', 'C003', 30000.00, '2025-06-10', '15:05:47', 'Istanbul', 'Bank Transfer'),
('T475', 'U002', 'C001', 3000.00, '2025-06-09', '11:34:15', 'İzmir', 'Bank Transfer'),
('T523', 'U6725', 'C001', 1000.00, '2025-05-30', '15:09:44', 'İstanbul', 'Credit Card'),
('T536', 'U002', 'C001', 100.00, '2025-06-08', '20:21:45', 'İstanbul', 'Credit Card'),
('T577', 'U001', 'C004', 200.00, '2025-06-08', '23:48:02', 'Istanbul', 'Cash'),
('T580', 'U2496', 'C002', 3000.00, '2025-06-10', '15:17:48', 'Istanbul', 'Debit Card'),
('T607', 'U2496', 'C005', 500.00, '2025-06-10', '15:18:37', 'Istanbul', 'Debit Card'),
('T638', 'U002', 'C001', 40000.00, '2025-05-30', '14:43:59', 'İzmir', 'Credit Card'),
('T640', 'U7867', 'C002', 100.00, '2025-06-09', '11:29:19', 'Ankara', 'Bank Transfer'),
('T714', 'U2496', 'C001', 380.00, '2025-06-10', '14:46:42', 'Istanbul', 'Credit Card'),
('T742', 'U2496', 'C001', 1000.00, '2025-06-10', '15:18:48', 'Istanbul', 'Cash'),
('T751', 'U6725', 'C002', 200000.00, '2025-05-30', '15:12:44', 'Ankara', 'Debit Card'),
('T773', 'U001', 'C003', 20000.00, '2025-06-08', '23:47:16', 'Ankara', 'Debit Card'),
('T798', 'U001', 'C002', 15000.00, '2025-06-08', '22:44:27', 'Istanbul', 'Credit Card'),
('T804', 'U2496', 'C001', 4000.00, '2025-06-10', '15:20:50', 'Istanbul', 'Credit Card'),
('T808', 'U002', 'C001', 90000.00, '2025-06-08', '19:56:27', 'İzmir', 'Debit Card'),
('T815', 'U002', 'C001', 800000.00, '2025-05-30', '15:05:16', 'Ankara', 'Bank Transfer'),
('T820', 'U002', 'C002', 15000.00, '2025-06-10', '10:07:32', 'Istanbul', 'Credit Card'),
('T840', 'U002', 'C001', 100.00, '2025-06-08', '20:23:29', 'Istanbul', 'Credit Card'),
('T851', 'U002', 'C001', 30000.00, '2025-06-09', '11:34:33', 'Istanbul', 'Bank Transfer'),
('T854', 'U003', 'C002', 15000.00, '2025-06-08', '22:42:24', 'Istanbul', 'Credit Card'),
('T861', 'U002', 'C002', 15000.00, '2025-06-10', '10:58:04', 'Istanbul', 'Credit Card'),
('T874', 'U2496', 'C003', 9000.00, '2025-06-10', '15:19:25', 'Istanbul', 'Debit Card'),
('T889', 'U2496', 'C001', 7000.00, '2025-06-10', '15:19:08', 'Istanbul', 'Debit Card'),
('T892', 'U7867', 'C002', 100000.00, '2025-06-09', '11:29:33', 'İzmir', 'Bank Transfer'),
('T905', 'U2496', 'C001', 200.00, '2025-06-10', '15:20:38', 'Istanbul', 'Credit Card'),
('T942', 'U6725', 'C002', 20000.00, '2025-05-30', '15:12:03', 'Ankara', 'Credit Card'),
('T947', 'U002', 'C002', 20000.00, '2025-05-30', '14:54:55', 'Bursa', 'Cash'),
('TEST1748605443714', 'U002', 'C002', 10000.00, '2025-05-30', '13:44:03', 'Van', 'Credit Card'),
('TEST1748605449914', 'U002', 'C002', 10000.00, '2025-05-30', '13:44:09', 'Van', 'Credit Card'),
('TEST1748605817249', 'U002', 'C002', 10000.00, '2025-05-30', '13:50:17', 'Van', 'Credit Card'),
('TEST1748606047370', 'U002', 'C002', 10000.00, '2025-05-30', '13:54:07', 'Van', 'Credit Card'),
('TEST1748606444757', 'U002', 'C002', 10000.00, '2025-05-30', '14:00:44', 'Van', 'Credit Card'),
('TEST1748606447212', 'U002', 'C002', 10000.00, '2025-05-30', '14:00:47', 'Van', 'Credit Card'),
('TEST1748606703042', 'U002', 'C002', 10000.00, '2025-05-30', '14:05:03', 'Van', 'Credit Card'),
('TEST1748607090528', 'U6725', 'C002', 10000.00, '2025-05-30', '14:11:30', 'Van', 'Credit Card'),
('TEST1749401697098', 'U002', 'C002', 10000.00, '2025-06-08', '18:54:57', 'Van', 'Credit Card'),
('TEST1749404609214', 'U003', 'C002', 10000.00, '2025-06-08', '19:43:29', 'Van', 'Credit Card'),
('TEST1749404628281', 'U003', 'C002', 10000.00, '2025-06-08', '19:43:48', 'Van', 'Credit Card'),
('TEST1749404637613', 'U003', 'C002', 10000.00, '2025-06-08', '19:43:57', 'Van', 'Credit Card'),
('TEST1749404644497', 'U003', 'C002', 10000.00, '2025-06-08', '19:44:04', 'Van', 'Credit Card'),
('TEST1749410253912', 'U003', 'C002', 15000.00, '2025-06-08', '03:00:00', 'Van', 'Credit Card'),
('TEST1749414171696_1', 'U003', 'C005', 12000.00, '2025-06-08', '03:45:00', 'Ankara', 'Credit Card'),
('TXN1748606465790', 'U002', 'C001', 163.00, '2025-05-30', '14:01:05', 'İstanbul', 'Credit Card');

-- --------------------------------------------------------

--
-- Table structure for table `User1`
--

CREATE TABLE `User1` (
  `UserID` varchar(20) NOT NULL,
  `Name` varchar(50) NOT NULL,
  `Email` varchar(50) NOT NULL,
  `Phone` varchar(20) DEFAULT NULL,
  `Password` varchar(255) NOT NULL,
  `RegistrationDate` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `User1`
--

INSERT INTO `User1` (`UserID`, `Name`, `Email`, `Phone`, `Password`, `RegistrationDate`) VALUES
('U001', 'Beril Mutlu', 'beril.mutlu@std.medipol.edu.tr', '5495295039', 'beril123', '2024-05-01'),
('U002', 'Erva Şengül', 'erva.sengul@std.medipol.edu.tr', '5349763877', 'erva123', '2024-04-15'),
('U003', 'Azra Karakaya', 'azra.karakaya@std.medipol.edu.tr', '5318836979', 'azra123', '2024-03-20'),
('U004', 'Feyza Şengül', 'feyza.sengul@std.medipol.edu.tr', '+905348212005', '$2y$10$N268iwxklQ6I2KpdCciUUuhzX6ueQHf4U694c8CBjplxE8ljiBaZy', '2025-05-27'),
('U005', 'Eren Şengül', 'eren.sengul@std.medipol.edu.tr', '+905414130880', '$2y$10$u8CHBW9bM/FIergvDRSeiORf8mReN7aFbgZtDmyGKbRsJyxWdYK2C', '2025-05-27'),
('U006', 'Aslı Zorlu', 'aslizorlu@gmail.com', '+90 555 55 55', '$2y$10$gcXk5BRGGEWQrXm7fYsWc.s6APVhWqBELpcxvo1dr73ez3iUZs7bC', '2025-05-29'),
('U007', 'Nazli Zorlu', 'nazlizorlu@gmail.com', '+90 566 666 66 66', '$2y$10$elV9LVW7O5afJvvfbaC/QeoVCbbkYnFESquTr.2T0bOBldiUr4LFy', '2025-05-29'),
('U008', 'Efe Mehmet Karakaya', 'efekarakaya@gmail.com', '+90 577 777 77 77 ', '$2y$10$fhrruzOhZyt2FVoA5NjoJu4N5dLZK5FfD9Us/vJpHmkg13Hgt6Wbq', '2025-05-29'),
('U009', 'Simay Yıldırım', 'simay.yildirim@gmail.com', '+90 588 888 88 88 ', '$2y$10$d96/XmHCz2vWKTkWIJRxGuNm4BAF7dBJiLd2AssdXTGCU1XEAjYNq', '2025-05-30'),
('U2496', 'Kerem Ugur', 'keremugur@gmail.com', '+90 555 898 89 89', '$2y$10$TtANISknLes2fu2qZ2pWLe6QjwM3ocqwTCxVrS.jEC3IJmcq/F4Xu', '2025-06-10'),
('U3218', 'Zafer Ege Karakaya', 'zaferege@gmail.com', '+905306034195', '$2y$10$FHH7aOY0mtTcEdI4qaX.ze.TBVRE38ZPs1QumOST7iwCPouP2PPje', '2025-06-08'),
('U6725', 'First Example', 'example@gmail.com', '+90 000 00 00 ', '$2y$10$meLbreqPBb32wqA.eJJo3OqqEHARG0kwGVHwOkphWoU1wL7xVvurm', '2025-05-30'),
('U7867', 'C Sengul', 'csengul@gmail.com', '+90 666 777 88 88 ', '$2y$10$/4LdTDBnMrCSBOXDpjY0r.OoZRyQSDGx0fyb.c6zqPb2S3MDOyNCm', '2025-06-09');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `Alert`
--
ALTER TABLE `Alert`
  ADD PRIMARY KEY (`AlertID`),
  ADD KEY `AnomalyID` (`AnomalyID`),
  ADD KEY `UserID` (`UserID`);

--
-- Indexes for table `AnomalyRecord`
--
ALTER TABLE `AnomalyRecord`
  ADD PRIMARY KEY (`AnomalyID`),
  ADD KEY `TransactionID` (`TransactionID`),
  ADD KEY `UserID` (`UserID`);

--
-- Indexes for table `Category`
--
ALTER TABLE `Category`
  ADD PRIMARY KEY (`CategoryID`);

--
-- Indexes for table `Profile`
--
ALTER TABLE `Profile`
  ADD PRIMARY KEY (`UserID`);

--
-- Indexes for table `Transaction`
--
ALTER TABLE `Transaction`
  ADD PRIMARY KEY (`TransactionID`),
  ADD KEY `UserID` (`UserID`),
  ADD KEY `CategoryID` (`CategoryID`);

--
-- Indexes for table `User1`
--
ALTER TABLE `User1`
  ADD PRIMARY KEY (`UserID`),
  ADD UNIQUE KEY `Email` (`Email`);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `Alert`
--
ALTER TABLE `Alert`
  ADD CONSTRAINT `alert_ibfk_1` FOREIGN KEY (`AnomalyID`) REFERENCES `AnomalyRecord` (`AnomalyID`) ON DELETE CASCADE,
  ADD CONSTRAINT `alert_ibfk_2` FOREIGN KEY (`UserID`) REFERENCES `User1` (`UserID`) ON DELETE CASCADE;

--
-- Constraints for table `AnomalyRecord`
--
ALTER TABLE `AnomalyRecord`
  ADD CONSTRAINT `anomalyrecord_ibfk_1` FOREIGN KEY (`TransactionID`) REFERENCES `Transaction` (`TransactionID`) ON DELETE CASCADE,
  ADD CONSTRAINT `anomalyrecord_ibfk_2` FOREIGN KEY (`UserID`) REFERENCES `User1` (`UserID`) ON DELETE CASCADE;

--
-- Constraints for table `Profile`
--
ALTER TABLE `Profile`
  ADD CONSTRAINT `profile_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `User1` (`UserID`) ON DELETE CASCADE;

--
-- Constraints for table `Transaction`
--
ALTER TABLE `Transaction`
  ADD CONSTRAINT `transaction_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `User1` (`UserID`) ON DELETE CASCADE,
  ADD CONSTRAINT `transaction_ibfk_2` FOREIGN KEY (`CategoryID`) REFERENCES `Category` (`CategoryID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
