--
-- Signatures of "dlockss-upload <CID>" by keys in math_key, which D-LOCKSS
-- records in the manifest of the file. The CID is the binary CIDv1 with a
-- sha2-256 hash, as Kubo makes them with the unixfs-v1-2025 profile.
--
CREATE TABLE /*_*/math_cid_signature (
  mcs_cid binary(36) NOT NULL,
  mcs_key binary(32) NOT NULL,
  mcs_signature binary(64) NOT NULL,
  mcs_timestamp binary(14) NOT NULL,
  PRIMARY KEY (mcs_cid, mcs_key)
) /*$wgDBTableOptions*/;
