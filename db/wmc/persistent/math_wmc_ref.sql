CREATE TABLE math_wmc_ref (
    qId INT PRIMARY KEY NOT NULL,
    oldId BIGINT,
    fId INT,
    qVarCount SMALLINT,
    texQuery TEXT,
    isDraft TINYINT,
    math_inputhash VARBINARY(16)
);
