Select res.runId, res.qId, ranks.rank, count(*) 'submissions' from math_wmc_results_pages res
  left join
  math_wmc_page_ranks ranks on res.qId = ranks.qId and res.runId = ranks.runId
where res.runID = 96 and res.qId in (select * from math_wmc_easy_queries)
group by res.qId;

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
  userID <> 1
group by `W`.`runId`
order by
  count( distinct `W`.`qId` ) desc,
  avg( ( 1 / `W`.`rank`) ) desc;

SELECT
  qID,
  oldID,
  fId,
  qVarCount,
  count(distinct A.mathindex_page_id) matches,
  concat('{$',
         REPLACE(convert( math_inputtex using utf8),"\n"," "),
         '$}') as rendering
FROM
  math_wmc_ref
  join
  mathindex I ON ( ( oldId = I.mathindex_page_id ) AND ( fId = I.mathindex_anchor ) )
  join
  mathindex A ON I.mathindex_inputhash = A.mathindex_inputhash
  join
  mathlatexml ON math_inputhash = A.mathindex_inputhash
group by qId;

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
  userID <> 1
group by `W`.`runId`
order by
  count( distinct `W`.`qId` ) desc,
  avg( ( 1 / `W`.`rank`) ) desc;

SELECT
  runId id , -- userId,
  user_name user, count(resultId) as '\# results', 	runName
from
  math_wmc_runs
  join
  user ON user_id = userId
  NATURAL join
  math_wmc_results
where
  userId <> 1
group by runId;
Create table math_wmc_ref2  (select qId, oldId, curId, fId, qVarCount, texQuery, mathindex_inputhash math_inputhash from math_wmc_ref ref
join mathindexbkf iOld on fId = iOld.mathindex_anchor and oldId = iOld.mathindex_page_id
where math_inputhash <> mathindex_inputhash) union (Select * from math_wmc_ref)
SELECT * from math_wmc_ref natural join math_wmc_formula_counts