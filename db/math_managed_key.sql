--
-- Private keys that the wiki holds for users. The owner is the user of the
-- same key in math_key. mmk_secret is encrypted with a key derived by Argon2id
-- from the user's passphrase, which may be empty.
--
CREATE TABLE /*_*/math_managed_key (
  mmk_key binary(32) NOT NULL,
  mmk_secret binary(80) NOT NULL,
  mmk_salt binary(16) NOT NULL,
  mmk_nonce binary(24) NOT NULL,
  mmk_opslimit int unsigned NOT NULL,
  mmk_memlimit int unsigned NOT NULL,
  PRIMARY KEY (mmk_key)
) /*$wgDBTableOptions*/;
