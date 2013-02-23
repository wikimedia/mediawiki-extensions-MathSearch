--
-- Used by the math search module to analyse the variables in the equations.
--
CREATE TABLE /*_*/mathvarstat (
  varstat_id smallint(6) NOT NULL AUTO_INCREMENT,
  varstat_featurename varchar(10) NOT NULL,
  varstat_featuretype varchar(10) NOT NULL,
  varstat_featurecount int(11) NOT NULL,
  PRIMARY KEY (`varstat_id`),
  UNIQUE KEY `varstat_featurename` (`varstat_featurename`,`varstat_featuretype`)
) /*$wgDBTableOptions*/;
