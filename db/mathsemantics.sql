CREATE TABLE `mathsemantics` (
  `pageId` int(5) NOT NULL,
  `identifier` varchar(4) NOT NULL,
  `evidence` double NOT NULL,
  `noun` varchar(20) NOT NULL,
  `sentence` text NOT NULL,
  KEY `pageId` (`pageId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
