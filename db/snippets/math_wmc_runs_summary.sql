SELECT
  r.runId id , -- userId,
  user_name user, count(resultId) as '\# results',runName,
  mrr.cnt  '\# topics (page)',
  mrrf.cnt '\# topics (formula)',
  mrr.mrr  'mrr (page)',
  mrrf.mrr 'mrr (formula)'
from
  math_wmc_runs r
  join
  user ON user_id = userId
  NATURAL join
  math_wmc_results
  Left Join math_wmc_mrr mrr on
                               r.runId = mrr.runId
  left join math_wmc_mrr_formulae mrrf on
                                         r.runId = mrrf.runId
where
  userId <> 1
group by r.runId
order by user_id, id