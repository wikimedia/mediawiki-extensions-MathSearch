--
-- Maps a content address back to the input it stands for, so
-- /math/v0/render/{format}/{hash} can resolve a hash it was given. The stored
-- value is the exact JSON the address is the sha1 of, so a row verifies itself.
--
CREATE TABLE /*_*/math_rest_input (
  math_rest_hash varbinary(40) NOT NULL,
  math_rest_input blob NOT NULL,
  PRIMARY KEY (math_rest_hash)
) /*$wgDBTableOptions*/;
