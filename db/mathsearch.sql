-- phpMyAdmin SQL Dump
-- version 3.3.7deb7
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Erstellungszeit: 19. Juli 2012 um 17:33
-- Server Version: 5.1.61
-- PHP-Version: 5.3.3-7+squeeze13

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Datenbank: `betawiki`
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `mathindex`
--

CREATE TABLE IF NOT EXISTS `mathindex` (
  `pageid` int(10) unsigned NOT NULL,
  `anchor` int(6) unsigned NOT NULL,
  `inputhash` varbinary(16) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `pagename` (`pageid`,`anchor`),
  KEY `inputhash` (`inputhash`),
  KEY `pageid` (`pageid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- RELATIONEN DER TABELLE `mathindex`:
--   `inputhash`
--       `math` -> `math_inputhash`
--   `pageid`
--       `page` -> `page_id`
--

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `mathsearch`
--
CREATE TABLE IF NOT EXISTS `mathsearch` (
`pageid` int(10) unsigned
,`anchor` int(6) unsigned
,`mathml` text
);
-- --------------------------------------------------------

--
-- Struktur des Views `mathsearch`
--
DROP TABLE IF EXISTS `mathsearch`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `mathsearch` AS select `s`.`pageid` AS `pageid`,`s`.`anchor` AS `anchor`,`math`.`math_mathml` AS `mathml` from (`mathindex` `s` join `math` on((`s`.`inputhash` = `math`.`math_inputhash`)));

--
-- Constraints der exportierten Tabellen
--

--
-- Constraints der Tabelle `mathindex`
--
ALTER TABLE `mathindex`
  ADD CONSTRAINT `mathindex_ibfk_1` FOREIGN KEY (`inputhash`) REFERENCES `math` (`math_inputhash`) ON DELETE CASCADE ON UPDATE NO ACTION,
  ADD CONSTRAINT `mathindex_ibfk_2` FOREIGN KEY (`pageid`) REFERENCES `page` (`page_id`) ON DELETE CASCADE ON UPDATE CASCADE;
