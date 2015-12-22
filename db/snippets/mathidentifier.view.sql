-- This view is outdated
CREATE
  ALGORITHM = UNDEFINED
  DEFINER = `root`@`localhost`
  SQL SECURITY DEFINER
VIEW `math_identifier` AS
  SELECT
    `S`.`identifier`   AS `identifier`,
    `S`.`noun`         AS `noun`,
    `S`.`evidence`     AS `evidence`,
    `S`.`sentence`     AS `sentence`,
    `S`.`sentenceHash` AS `sentenceHash`,
    `M`.`pageTitle`    AS `pageTitle`,
    `M`.`pageId`       AS `pageID`
  FROM
    (`mathsemantics` `S`
      JOIN `mathIdMap` `M` ON ((`S`.`pageId` = `M`.`pageId`)))