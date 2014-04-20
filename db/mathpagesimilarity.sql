CREATE TABLE /*_*/mathpagesimilarity (
  `pagesimilarity_A` int(11) NOT NULL,
  `pagesimilarity_B` int(11) NOT NULL,
  `pagesimilarity_Value` double NOT NULL DEFAULT '0',
  PRIMARY KEY (`pagesimilarity_B`,`pagesimilarity_A`)
) /*$wgDBTableOptions*/;
