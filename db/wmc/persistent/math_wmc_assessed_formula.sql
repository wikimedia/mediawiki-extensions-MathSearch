CREATE TABLE math_wmc_assessed_formula (
  qId INT NOT NULL,
  inputhash VARBINARY(16) NOT NULL,
  assessment SMALLINT NOT NULL,
  assessor INT(10) UNSIGNED,
  FOREIGN KEY ( qId ) REFERENCES math_wmc_ref ( qId ) ON DELETE CASCADE,
  FOREIGN KEY ( assessor ) REFERENCES user ( user_id ) ON DELETE SET NULL,
  UNIQUE KEY ( qId, inputhash, assessor)
);
