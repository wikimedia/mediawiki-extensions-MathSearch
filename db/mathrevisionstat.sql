--
-- Used by the math search module to analyse the variables in the equations.
--
CREATE TABLE /*_*/mathrevisionstat (
  revstat_revid int(10) NOT NULL,
  revstat_featureid int(6) NOT NULL,
  revstat_featurecount int(11) NOT NULL,
  PRIMARY KEY (revstat_revid,revstat_featureid),
  FOREIGN KEY `revision` ( revstat_revid ) REFERENCES revision( rev_id ),
  FOREIGN KEY `featureID` ( revstat_featureid ) REFERENCES mathvarstat ( varstat_featurename )
) /*$wgDBTableOptions*/;

