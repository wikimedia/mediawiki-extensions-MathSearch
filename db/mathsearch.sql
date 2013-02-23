--
-- Used by the math module to keep track
-- of previously-rendered items.
--
CREATE TABLE /*_*/mathindex (

  -- Page id where the equation was found.
  mathindex_page_id int(10) unsigned NOT NULL,

  -- Position of the equation on the page
  -- Starting from 0 at the top of the page
  mathindex_anchor int(6) unsigned NOT NULL,

  -- Reference to
  -- Binary MD5 hash of the latex fragment, used as an identifier key.
  mathindex_inputhash varbinary(16) NOT NULL,

  -- Timestamp. Is set by the database autmatically
  mathindex_timestamp timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (mathindex_page_id,mathindex_anchor)

  
) /*$wgDBTableOptions*/;

  CREATE INDEX /*i*/mathindex_inputhash ON mathindex (mathindex_inputhash);
  CREATE INDEX /*i*/mathindex_page_id ON mathindex (mathindex_page_id);

--
-- RELATIONEN DER TABELLE `mathindex`:
--   `inputhash`
--       `math` -> `math_inputhash`
--   `pageid`
--       `page` -> `page_id`
--