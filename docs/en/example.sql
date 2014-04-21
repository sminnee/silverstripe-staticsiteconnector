-- MySQL dump 10.14  Distrib 5.5.35-MariaDB, for osx10.9 (i386)
--
-- Host: localhost    Database: SS_ssc_dev
-- ------------------------------------------------------
-- Server version	5.5.35-MariaDB

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
-- Table structure for table `StaticSiteContentSource`
--

DROP TABLE IF EXISTS `StaticSiteContentSource`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `StaticSiteContentSource` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `BaseUrl` varchar(255) DEFAULT NULL,
  `UrlProcessor` varchar(255) DEFAULT NULL,
  `ExtraCrawlUrls` mediumtext,
  `UrlExcludePatterns` mediumtext,
  `ParseCSS` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `AutoRunTask` tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `StaticSiteContentSource`
--

LOCK TABLES `StaticSiteContentSource` WRITE;
/*!40000 ALTER TABLE `StaticSiteContentSource` DISABLE KEYS */;
INSERT INTO `StaticSiteContentSource` VALUES (1,'http://my-site.localhost','StaticSiteURLProcessor_DropExtensions',NULL,'http://www.not-my-site.co.nz/*\r\n/search/*\r\n/cgi-bin/*',1,1);
/*!40000 ALTER TABLE `StaticSiteContentSource` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `StaticSiteContentSource_ImportRule`
--

DROP TABLE IF EXISTS `StaticSiteContentSource_ImportRule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `StaticSiteContentSource_ImportRule` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `ClassName` enum('StaticSiteContentSource_ImportRule') DEFAULT 'StaticSiteContentSource_ImportRule',
  `Created` datetime DEFAULT NULL,
  `LastEdited` datetime DEFAULT NULL,
  `FieldName` varchar(50) DEFAULT NULL,
  `CSSSelector` mediumtext,
  `ExcludeCSSSelector` mediumtext,
  `Attribute` varchar(50) DEFAULT NULL,
  `PlainText` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `OuterHTML` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `SchemaID` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`ID`),
  KEY `SchemaID` (`SchemaID`),
  KEY `ClassName` (`ClassName`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `StaticSiteContentSource_ImportRule`
--

LOCK TABLES `StaticSiteContentSource_ImportRule` WRITE;
/*!40000 ALTER TABLE `StaticSiteContentSource_ImportRule` DISABLE KEYS */;
INSERT INTO `StaticSiteContentSource_ImportRule` VALUES (1,'StaticSiteContentSource_ImportRule','2014-03-19 21:31:36','2014-04-21 21:48:38','Title','h2.title',NULL,NULL,1,0,1),(2,'StaticSiteContentSource_ImportRule','2014-03-19 21:34:03','2014-04-21 21:49:22','Content','div#main',NULL,NULL,0,0,2),(3,'StaticSiteContentSource_ImportRule','2014-03-19 21:34:42','2014-03-19 21:34:46','Title','#content h1',NULL,NULL,1,0,2),(4,'StaticSiteContentSource_ImportRule','2014-03-19 21:35:31','2014-04-21 21:49:09','Content','div#main','.panel-left',NULL,0,0,1);
/*!40000 ALTER TABLE `StaticSiteContentSource_ImportRule` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `StaticSiteContentSource_ImportSchema`
--

DROP TABLE IF EXISTS `StaticSiteContentSource_ImportSchema`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `StaticSiteContentSource_ImportSchema` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `ClassName` enum('StaticSiteContentSource_ImportSchema') DEFAULT 'StaticSiteContentSource_ImportSchema',
  `Created` datetime DEFAULT NULL,
  `LastEdited` datetime DEFAULT NULL,
  `DataType` varchar(50) DEFAULT NULL,
  `Order` int(11) NOT NULL DEFAULT '0',
  `AppliesTo` varchar(255) DEFAULT NULL,
  `MimeTypes` mediumtext,
  `ContentSourceID` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`ID`),
  KEY `ContentSourceID` (`ContentSourceID`),
  KEY `ClassName` (`ClassName`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `StaticSiteContentSource_ImportSchema`
--

LOCK TABLES `StaticSiteContentSource_ImportSchema` WRITE;
/*!40000 ALTER TABLE `StaticSiteContentSource_ImportSchema` DISABLE KEYS */;
INSERT INTO `StaticSiteContentSource_ImportSchema` VALUES (1,'StaticSiteContentSource_ImportSchema','2014-03-19 21:30:16','2014-03-19 21:30:16','HomePage',1,'^/?$','text/html',1),(2,'StaticSiteContentSource_ImportSchema','2014-03-19 21:33:07','2014-03-19 21:33:07','Page',2,'.*','text/html',1),(3,'StaticSiteContentSource_ImportSchema','2014-03-19 21:36:09','2014-03-19 21:36:09','Image',3,'.*','image/png\r\nimage/gif\r\nimage/jpeg',1),(4,'StaticSiteContentSource_ImportSchema','2014-03-19 21:36:41','2014-03-19 21:36:41','File',4,'.*','application/pdf\r\ntext/plain\r\ntext/csv',1);
/*!40000 ALTER TABLE `StaticSiteContentSource_ImportSchema` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2014-04-21 21:54:04
