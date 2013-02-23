--
-- Used by the math search module to analyse the variables in the equations.
--
CREATE TABLE /*_*/mathobservation (

  -- Reference to
  -- Binary MD5 hash of the latex fragment, used as an identifier key.
  mathobservation_inputhash varbinary(16) NOT NULL,
  
  --Type of the feature e.g. mo, mi
  mathobservation_featuretype varchar(10) NOT NULL,
  
  --Name of the feature. eg name of the variable
  mathobservation_featurename varchar(10) NOT NULL,

  -- Timestamp. Is set by the database autmatically
  mathobservation_timestamp timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  
) /*$wgDBTableOptions*/;

  CREATE INDEX /*i*/mathobservation_inputhash   ON mathobservation (mathobservation_inputhash);
  CREATE INDEX /*i*/mathobservation_featurename ON mathobservation (mathobservation_featurename);
