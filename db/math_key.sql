--
-- Public keys of wiki users. A user can have several keys, a key belongs to
-- one user. The key is the raw 32-byte Ed25519 public key.
--
CREATE TABLE /*_*/math_key (
  mk_key binary(32) NOT NULL,
  mk_user int unsigned NOT NULL,
  mk_timestamp binary(14) NOT NULL,
  PRIMARY KEY (mk_key)
) /*$wgDBTableOptions*/;
