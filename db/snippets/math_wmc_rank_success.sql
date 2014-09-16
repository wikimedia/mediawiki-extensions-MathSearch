CREATE OR REPLACE ALGORITHM = UNDEFINED
  SQL SECURITY DEFINER VIEW `wmc_rank_sucess` AS (
  SELECT
    count(DISTINCT `r`.`qId`)  AS `c`,
    `math_wmc_results`.`runId` AS `runId`,
    `l`.`level`                AS `level`
  FROM (math_wmc_rank_levels `l` JOIN (`math_wmc_ref` `r` JOIN `math_wmc_results`
      ON (((`math_wmc_results`.`oldId` = `r`.`oldId`) AND
           (`math_wmc_results`.`qId` = `r`.`qId`)))))
  WHERE (`math_wmc_results`.`rank` <= `l`.`level`)
  GROUP BY `math_wmc_results`.`runId`, `l`.`level`
  ORDER BY count(DISTINCT `r`.`qId`) DESC);