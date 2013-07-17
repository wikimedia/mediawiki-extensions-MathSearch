--
-- Used by the math search module to analyse the variables in the equations.
--
CREATE TABLE /*_*/mathpagestat (
  pagestat_pageid int(10) NOT NULL,
  pagestat_featureid int(6) NOT NULL,
  pagestat_featurecount int(11) NOT NULL,
  PRIMARY KEY (pagestat_pageid,pagestat_featureid),
  KEY `pagestat_pageid` (`pagestat_pageid`)
) /*$wgDBTableOptions*/;

