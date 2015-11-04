ALTER TABLE /*_*/mathindex
  ADD CONSTRAINT fk_mathindex_hash
  FOREIGN KEY ( /*i*/mathindex_inputhash )
  REFERENCES /*_*/mathlatexml( math_inputhash )
  ON DELETE CASCADE;