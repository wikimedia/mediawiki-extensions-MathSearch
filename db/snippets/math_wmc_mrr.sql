select
  count(distinct `W`.`qId`) AS `cnt`,
  round(avg((1 / `W`.`rank`)), 3) AS `mrr`,
  user.user_name user,
  `W`.`runId` AS `runId`
from
  (math_wmc_ref `r`
    join math_wmc_results_pages `W` ON (`W`.oldId = r.oldId
                                        and `W`.`qId` = `r`.`qId`)
    join math_wmc_runs runs ON (W.runId = runs.runId)
    join user ON (runs.userId = user.user_id))
where
  userID <> 1 AND W.qId in (SELECT qID FROM math_wmc_easy_topics)
group by `W`.`runId`
order by
  count( distinct `W`.`qId` ) desc,
  avg( ( 1 / `W`.`rank`) ) desc;