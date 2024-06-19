--
-- Used by the math search module to analyse the variables in the equations.
--
CREATE TABLE /*_*/mathrevisionstat (
  revstat_revid BIGINT(20) UNSIGNED NOT NULL,
  revstat_featureid INT(6) NOT NULL,
  revstat_featurecount INT(11) NOT NULL,
  PRIMARY KEY (revstat_revid, revstat_featureid)
) /*$wgDBTableOptions*/;

