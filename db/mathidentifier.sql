--
-- Used by the math search module to annotate meanings of identifiers.
--
CREATE TABLE /*_*/mathidentifier (
  identifier varchar(5) NOT NULL,
  noun varchar(40) NOT NULL,
  evidence double NOT NULL,
  pageTitle varchar(255) NOT NULL,
  pageID int(8) NOT NULL,
  KEY mathidentifier_key( identifier , pageTitle )
) /*$wgDBTableOptions*/;