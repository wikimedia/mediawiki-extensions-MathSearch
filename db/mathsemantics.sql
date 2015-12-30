CREATE TABLE /*_*/mathsemantics (
  `revision_id` int(10) UNSIGNED NOT NULL,
  `identifier` varchar(20) NOT NULL,
  `evidence` double NOT NULL,
  `noun` varchar(255) NOT NULL,
  `sentence` text NULL,
  KEY `revision_id` (`revision_id`)
) /*$wgDBTableOptions*/;
