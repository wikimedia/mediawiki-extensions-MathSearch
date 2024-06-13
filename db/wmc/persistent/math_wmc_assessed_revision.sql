CREATE TABLE math_wmc_assessed_revision (
  qId INT NOT NULL,
  oldId BIGINT(20) UNSIGNED NOT NULL,
  assessment SMALLINT NOT NULL ,
  assessor INT(10) UNSIGNED,
  FOREIGN KEY ( qId ) REFERENCES math_wmc_ref ( qId ) ON DELETE CASCADE,
  FOREIGN KEY ( assessor ) REFERENCES user ( user_id ) ON DELETE SET NULL,
  FOREIGN KEY ( oldId ) REFERENCES revision ( rev_id ) ON DELETE CASCADE,
  UNIQUE KEY ( qId, oldId, assessor)
);
