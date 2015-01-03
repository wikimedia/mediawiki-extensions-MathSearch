CREATE TEMPORARY TABLE math_wmc_mrr_formulae
    select
      runId, count(rank) cnt, round(avg(1 / rank), 2) mrr
    from
      math_wmc_results_formulae
    group by runID
    order by cnt desc , mrr asc