DELIMITER $$
DROP FUNCTION IF EXISTS math_wmc_pageTitleFromRevId$$
CREATE FUNCTION math_wmc_pageTitleFromRevId(oldId int) RETURNS text CHARSET utf8
  BEGIN
    DECLARE res TEXT;
    SELECT REPLACE(REPLACE(page_title, "_", " "),"ő", "ö") into res FROM
      revision R
      JOIN
      `page` P on R.rev_page = P.page_id
    WHERE
      R.rev_id = oldId;
    return res;
  END$$
DROP FUNCTION IF EXISTS math_wmc_TeXCol$$
CREATE FUNCTION math_wmc_TeXCol(tex BLOB) RETURNS text CHARSET utf8
  RETURN concat('{$', REPLACE(convert( tex using utf8),"\n"," "),	'$}')$$
DROP FUNCTION IF EXISTS math_wmc_WikiLink$$
CREATE FUNCTION math_wmc_WikiLink(oldId INT, fId INT) RETURNS text CHARSET utf8
DETERMINISTIC
  RETURN concat('{\\wikiLink{', math_wmc_pageTitleFromRevId(oldId) ,'}{', oldId ,'}{', fId ,'}}')$$
DROP FUNCTION IF EXISTS math_wmc_WikiLinksFromHash$$
CREATE FUNCTION math_wmc_WikiLinksFromHash(fh varbinary(16), queryId INT) RETURNS text CHARSET utf8
  BEGIN
    Declare res text;
    SELECT
      group_concat(concat(link, ' (', cnt ,')'))
    into res from
      (SELECT
         math_wmc_WikiLink(oldId, fId) link,
         count(concat(oldId, '.', fId)) cnt
       from
         math_wmc_results
       where
         math_wmc_results.math_inputhash = fh
         and runId in (Select
                         *
                       from
                         math_wmc_results_top)
         and runId <> 2 -- TODO: Document that run without fIds was excluded
         and qId = queryId
       group by concat(oldId, '.', fId)
       order by count(concat(oldId, '.', fId)) desc
       LIMIT 5) t
    group by 'all'
    ;
    return res;
  END$$
DROP PROCEDURE IF EXISTS math_wmc_createMostFrequentHits$$
CREATE PROCEDURE math_wmc_createMostFrequentHits()
  BEGIN
    DECLARE currentQId INT;
    DECLARE done INT DEFAULT 0;
    DECLARE qCur CURSOR FOR
      SELECT qID FROM math_wmc_ref;
    DECLARE CONTINUE HANDLER FOR SQLSTATE '02000' SET done = 1;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;


    CREATE TABLE IF NOT EXISTS `math_wmc_freq_hits` (
      qId INT,
      `rendering` text,
      `cntUser` INT NOT NULL DEFAULT '0',
      `cntRun` INT NOT NULL DEFAULT '0',
      `minRank` INT,
      `links` longtext
    )  ENGINE=InnoDB DEFAULT CHARSET=utf8;
    TRUNCATE TABLE math_wmc_freq_hits;
    OPEN qCur;

    qCurL: LOOP
      FETCH qCur INTO currentQId;
      IF done THEN
        LEAVE qCurL;
      END IF;
      INSERT INTO math_wmc_freq_hits (
        SELECT
          currentQId qId, rendering, cntUser, cntRun, minRank, math_wmc_WikiLinksFromHash(math_inputhash, currentQId) links
        from
          (select
             math_wmc_TeXCol(math_inputtex) as rendering,
             count(distinct runs.userId) cntUser,
             count(distinct runs.runId) cntRun,
             min(`rank`) minRank,
             r.math_inputhash
           from
             math_wmc_results r
             join mathlatexml l ON r.math_inputhash = l.math_inputhash
             join math_wmc_runs runs ON r.runId = runs.runId
           where
             r.qId = currentQId
             and r.runId in (select
                               *
                             from
                               math_wmc_results_top)
             and userId <> 6
           group by r.math_inputhash
           having min(rank) < 50
           order by count(distinct runs.userId) desc , min(rank) asc
           Limit 15) mt
      );
      IF currentQId = 100 THEN -- TODO: Fix done handling
        LEAVE qCurL;
      END IF;
    END LOOP qCurL;
    CLOSE qCur;
  END$$
DELIMITER ;
CALL math_wmc_createMostFrequentHits();
