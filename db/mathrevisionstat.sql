--
-- Used by the math search module to analyse the variables in the equations.
--
CREATE TABLE /*_*/mathrevisionstat (
  revstat_revid INT(10) UNSIGNED NOT NULL,
  revstat_featureid INT(6) NOT NULL,
  revstat_featurecount INT(11) NOT NULL,
  PRIMARY KEY (revstat_revid,revstat_featureid),
  FOREIGN KEY `revision` ( revstat_revid ) REFERENCES revision( rev_id ),
  FOREIGN KEY `featureID` ( revstat_featureid ) REFERENCES mathvarstat ( varstat_id )
) /*$wgDBTableOptions*/;

