--
-- Used by the math module to keep track
-- of previously-rendered items.
--
CREATE TABLE /*_*/mathindex (

  -- Revision id where the equation was found.
  mathindex_revision_id int(10) unsigned NOT NULL,

  -- Position of the equation on the page
  -- Starting from math0 at the top of the page
  -- or manually specified by the id field
  mathindex_anchor varchar(50) NOT NULL,

  -- Reference to
  -- Binary MD5 hash of the latex fragment, used as an identifier key.
  mathindex_inputhash varbinary(16) NOT NULL,

  -- Timestamp. Is set by the database automatically
  mathindex_timestamp timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (mathindex_revision_id,mathindex_anchor),
  FOREIGN KEY ( /*i*/mathindex_inputhash ) REFERENCES mathlatexml( math_inputhash ),
  FOREIGN KEY ( /*i*/mathindex_revision_id ) REFERENCES revision( rev_id )

) /*$wgDBTableOptions*/;