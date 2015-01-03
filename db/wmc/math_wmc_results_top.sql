CREATE TEMPORARY TABLE IF NOT EXISTS math_wmc_results_top
( UNIQUE INDEX  runId(runId) )
    SELECT `W`.`runId` AS `runId`
    FROM
      (math_wmc_ref `r`
        JOIN math_wmc_results_pages `W` ON (`W`.oldId = r.oldId
                                            AND `W`.`qId` = `r`.`qId`)
        JOIN math_wmc_runs runs ON ( W.runId = runs.runId ) )
    WHERE
      userId <> 1 -- TODO: Document that the admin is excluded.
    GROUP BY `W`.`runId`
    HAVING count(DISTINCT `W`.`qId`) > 50