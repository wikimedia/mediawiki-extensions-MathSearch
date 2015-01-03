CREATE temporary TABLE IF NOT EXISTS math_wmc_page_ranks
  ( UNIQUE KEY uniqueRanks( qId, runId ) )
    select
      `rank`,
      W.qId,
      `W`.`runId` AS `runId`
    from
      (math_wmc_ref `r`
        join math_wmc_results_pages `W` ON (`W`.oldId = r.oldId
                                            and `W`.`qId` = `r`.`qId`)
        join math_wmc_runs runs ON (W.runId = runs.runId)
      )
    where W.runId in (SELECT * FROM math_wmc_results_top)
    group by `W`.`runId`, W.qId