-- phpMyAdmin SQL Dump
-- version 3.4.5
-- http://www.phpmyadmin.net
--
-- Host: localhost:6033
-- Generation Time: Nov 04, 2011 at 01:30 AM
-- Server version: 5.0.67
-- PHP Version: 5.2.8

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `letsmod`
--

-- --------------------------------------------------------

--
-- Table structure for table `ls_membergroups`
--

CREATE TABLE IF NOT EXISTS `ls_membergroups` (
  `membergroupID` int(11) NOT NULL auto_increment,
  `membergroupName` varchar(64) NOT NULL,
  `minpoints` int(64) NOT NULL,
  `settings` varchar(1024) NOT NULL,
  `permissions` varchar(1024) NOT NULL,
  PRIMARY KEY  (`membergroupID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `ls_members`
--

CREATE TABLE IF NOT EXISTS `ls_members` (
  `memberID` int(32) NOT NULL auto_increment,
  `membergroupID` int(6) NOT NULL,
  `username` varchar(64) NOT NULL,
  `friendlyname` varchar(64) NOT NULL,
  `password` varchar(64) NOT NULL,
  `email` varchar(64) NOT NULL,
  `avatar` varchar(255) NOT NULL,
  `points` int(64) NOT NULL,
  `registeredIP` varchar(64) NOT NULL,
  `registerdate` varchar(12) NOT NULL,
  `signature` text NOT NULL,
  `about` text NOT NULL,
  `timeoffset` int(12) NOT NULL,
  `isadult` tinyint(1) NOT NULL,
  `isbanned` tinyint(1) NOT NULL,
  `isactivated` tinyint(1) NOT NULL,
  `lastmsaymsg` varchar(255) NOT NULL,
  `lastIP` varchar(64) NOT NULL,
  `lastdate` int(12) NOT NULL,
  `lastactivatedate` int(12) NOT NULL,
  `securitycode` varchar(64) NOT NULL,
  `settings` varchar(2048) NOT NULL,
  `data` varchar(2048) NOT NULL,
  PRIMARY KEY  (`memberID`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `friendlyname` (`friendlyname`),
  KEY `username` (`username`),
  KEY `registeredIP` (`registeredIP`),
  KEY `password` (`password`),
  KEY `isbanned` (`isbanned`),
  KEY `isactivated` (`isactivated`),
  KEY `securitycode` (`securitycode`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;


-- --------------------------------------------------------

--
-- Table structure for table `ls_sessions`
--

CREATE TABLE IF NOT EXISTS `ls_sessions` (
  `sessionID` int(16) NOT NULL auto_increment,
  `memberID` int(32) NOT NULL,
  `alert` varchar(32) NOT NULL,
  `bornin` int(12) NOT NULL,
  `lastactivity` int(12) NOT NULL,
  `ip` varchar(64) NOT NULL,
  `sessionkey` varchar(32) NOT NULL,
  `tokenring` varchar(64) NOT NULL,
  `location` varchar(255) NOT NULL,
  `pagevisited` int(16) NOT NULL,
  `lastsubmit` int(12) NOT NULL,
  `loginfailcount` int(12) NOT NULL,
  `protectionperiod` int(12) NOT NULL,
  PRIMARY KEY  (`sessionID`),
  KEY `page` (`location`)
) ENGINE=MEMORY  DEFAULT CHARSET=utf8 AUTO_INCREMENT=16 ;

-- --------------------------------------------------------

--
-- Table structure for table `ls_sessiontickets`
--

CREATE TABLE IF NOT EXISTS `ls_sessiontickets` (
  `ticketID` int(64) NOT NULL auto_increment,
  `memberID` int(32) NOT NULL,
  `tickethash` varchar(32) NOT NULL,
  `logindate` int(12) NOT NULL,
  `clientIP1` varchar(5) NOT NULL,
  `clientIP2` varchar(5) NOT NULL,
  `clientIP3` varchar(5) NOT NULL,
  `clientIP4` varchar(5) NOT NULL,
  `clientIP5` varchar(5) NOT NULL,
  `clientIP6` varchar(5) NOT NULL,
  `clientIP7` varchar(5) NOT NULL,
  `clientIP8` varchar(5) NOT NULL,
  PRIMARY KEY  (`ticketID`),
  KEY `MemberID` (`memberID`,`tickethash`,`logindate`)
) ENGINE=MEMORY DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `ls_settings`
--

CREATE TABLE IF NOT EXISTS `ls_settings` (
  `settype` enum('general','mail','members','groups','admins','debug','security','files','count') NOT NULL,
  `setkey` tinytext NOT NULL,
  `setvalue` text NOT NULL,
  PRIMARY KEY  (`setkey`(64)),
  KEY `settype` (`settype`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
